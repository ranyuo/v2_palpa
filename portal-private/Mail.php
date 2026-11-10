<?php
declare(strict_types=1);
require_once __DIR__.'/vendor/phpmailer/src/Exception.php';
require_once __DIR__.'/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__.'/vendor/phpmailer/src/SMTP.php';

/** Cola privada: se confirma junto al trámite y se entrega después del commit. */
final class PortalMail
{
    public const AUTH_ERROR='SMTP rechazó el usuario o la contraseña. Revise la cuenta de envío y la contraseña de aplicación.';
    public const GENERIC_ERROR='SMTP no confirmó el envío. Revise credenciales, conexión y destinatario.';
    /** Minutos de atraso a partir de los cuales se considera que el cron de correo no está corriendo. */
    public const WORKER_GRACE_MINUTES=15;

    public function __construct(private readonly array $config) {}

    public function ready(): bool
    {
        return ($this->config['enabled']??false)===true
            && !empty($this->config['password']) && !empty($this->config['username']);
    }

    public static function enqueue(PDO $db,array $record,array $data): void
    {
        $reference=$record['numero'];
        $institution=$data['config']['inst']['nombre'];
        $messages=[
            'solicitante'=>[$record['correo'], 'Cargo de recepción '.$reference,
                "Su trámite ha sido registrado en $institution.\n\nExpediente: $reference\nDocumento: {$record['numDocumento']}\nFecha de registro: {$record['fecha']}\nRecepción administrativa: {$record['fechaRecepcion']}\nCódigo de verificación: {$record['cargoCod']}\n\nConserve estos datos para consultar el seguimiento en Mesa de Partes.\n"],
            'mesa'=>[$data['config']['mpv']['correo'], '['.$reference.'] Nuevo trámite registrado',
                "Se ha registrado un nuevo trámite en $institution.\n\nExpediente: $reference\nSolicitante: {$record['nombres']}\nCorreo del solicitante: {$record['correo']}\nTipo: {$record['tipoTramite']}\nAsunto: {$record['asunto']}\nÁrea de destino: {$record['areaDestino']}\n\nIngrese a la intranet para revisar el expediente y sus adjuntos privados.\n"],
        ];
        $insert=$db->prepare('INSERT INTO intranet_mail (id,record_id,reference,recipient,subject,body,created_at,next_attempt_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id');
        $seen=[];foreach($messages as $kind=>[$to,$subject,$body]){
            if(!is_string($to)||strlen($to)>254||!filter_var($to,FILTER_VALIDATE_EMAIL))throw new PortalError(422,'Configura un correo receptor válido para Mesa de Partes.');
            $key=strtolower(trim($to));if(isset($seen[$key]))continue;$seen[$key]=true;
            $insert->execute([hash('sha256',$record['id'].'|'.$kind),$record['id'],$reference,$to,$subject,$body]);
        }
    }

    public static function queue(PDO $db,string $module,string $sourceId,string $event,string $version,string $to,string $reference,string $subject,string $body,string $sourceKey=''): void
    {
        preg_match('/^.{0,200}/us',$subject,$short);$subject=$short[0]??'Notificación institucional';
        if(strlen($body)>60000){preg_match('/^.{0,14000}/us',$body,$short);$body=($short[0]??'')."\nMensaje resumido. Consulta el contenido completo en la plataforma.\n";}
        $batch=hash('sha256',$module.'|'.$sourceId);
        $id=hash('sha256',$batch.'|'.$sourceKey.'|'.$event.'|'.$version.'|'.$to);
        $insert=$db->prepare('INSERT INTO intranet_mail (id,record_id,source_type,source_id,source_key,event,reference,recipient,subject,body,created_at,next_attempt_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id');
        $insert->execute([$id,$batch,$module,$sourceId,$sourceKey,$event,$reference,$to,$subject,'Evento registrado: '.date(DATE_ATOM)."\n".$body]);
    }

    /** No se incluyen credenciales ni cuerpos de correos en el panel. */
    public function status(PortalApp $app): array
    {
        $app->requireAdmin();
        $rows=$app->db()->query('SELECT id,source_type,event,reference,recipient,status,attempts,last_error,created_at,sent_at FROM intranet_mail ORDER BY created_at DESC,id LIMIT 30')->fetchAll();
        return ['enabled'=>($this->config['enabled']??false)===true,'configured'=>$this->ready(),
            'from'=>$this->config['from_email']??'notificaciones@proyectos.pe','messages'=>$rows,
            'worker_last_run'=>self::localTime(self::stamp($app->db(),'mail_worker_last_run')),'warnings'=>$this->warnings($app)];
    }

    /**
     * Mensaje seguro para el panel según el tipo de fallo SMTP.
     * Solo se mira el texto fijo de PHPMailer; nunca se guarda el diálogo SMTP ni datos del servidor.
     */
    public static function failure(Throwable $error): string
    {
        if($error instanceof InvalidArgumentException)return 'Configuración SMTP no válida.';
        $text=$error->getMessage();
        if(stripos($text,'Could not authenticate')!==false)return self::AUTH_ERROR;
        if(stripos($text,'Could not connect')!==false||stripos($text,'Failed to connect')!==false)return 'No se pudo conectar con el servidor SMTP. Revise servidor, puerto, cifrado y la red del hosting.';
        if(stripos($text,'recipients failed')!==false)return 'El servidor SMTP rechazó el destinatario. Revise la dirección de correo.';
        return self::GENERIC_ERROR;
    }

    /** Marcas de tiempo (UTC) del correo: última ejecución del cron, último envío aceptado y último rechazo de la cuenta. */
    public static function mark(PDO $db,string $key): void
    {
        $db->prepare('INSERT INTO intranet_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute([$key,json_encode(gmdate('Y-m-d H:i:s'),JSON_THROW_ON_ERROR)]);
    }

    public static function stamp(PDO $db,string $key): ?string
    {
        $get=$db->prepare('SELECT setting_value FROM intranet_settings WHERE setting_key=?');$get->execute([$key]);
        $value=$get->fetchColumn();$value=is_string($value)?json_decode($value,true):null;
        return is_string($value)&&preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D',$value)?$value:null;
    }

    /** Fecha local legible de una marca UTC. */
    public static function localTime(?string $utc): ?string
    {return $utc===null?null:(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/Y H:i');}

    /** Envía un correo de prueba con la configuración guardada, sin pasar por la cola. */
    public function sendTest(PDO $db,string $to,string $institution): array
    {
        // Se permite probar antes de activar el envío; solo hace falta la cuenta y la contraseña guardadas.
        if(empty($this->config['username'])||empty($this->config['password']))throw new PortalError(409,'Primero guarda la cuenta SMTP y su contraseña de aplicación.');
        $now=date('d/m/Y H:i');
        try{
            $this->send(['id'=>bin2hex(random_bytes(16)),'recipient'=>$to,'subject'=>'Correo de prueba de la intranet',
                'body'=>"Este es un correo de prueba enviado desde la intranet de $institution el $now.\n\nSi lo recibió, la configuración SMTP funciona correctamente. No es necesario responder.\n"]);
        }catch(Throwable $error){
            $message=self::failure($error);if($message===self::AUTH_ERROR)self::mark($db,'mail_last_auth_error');
            return ['ok'=>false,'message'=>$message];
        }
        self::mark($db,'mail_last_ok');
        return ['ok'=>true,'message'=>"Correo de prueba aceptado por el servidor SMTP para $to. Revise la bandeja de entrada y la de spam."];
    }

    private function warnings(PortalApp $app): array
    {
        $raw=$app->db()->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn();$data=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);$warnings=[];
        if(empty($data['config']['lr']['correo'])&&empty($data['config']['correoReclamos']))$warnings[]='Configura el correo responsable del Libro de Reclamaciones.';
        if(empty($data['config']['bolsa']['correo']))$warnings[]='Configura el correo responsable de Bolsa Laboral.';
        if(!$this->ready())return $warnings;
        $db=$app->db();
        // Cron detenido: hay avisos que debieron salir hace más de 15 minutos y siguen en cola.
        $late=(int)$db->query("SELECT COUNT(*) FROM intranet_mail WHERE status IN ('Pendiente','Error') AND attempts<5 AND next_attempt_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ".self::WORKER_GRACE_MINUTES." MINUTE)")->fetchColumn();
        if($late>0){
            $last=self::localTime(self::stamp($db,'mail_worker_last_run'));
            $warnings[]="Hay $late aviso(s) con más de ".self::WORKER_GRACE_MINUTES.' minutos de atraso. Revise que el cron de correo (portal-private/mail-worker.php) esté programado en el hosting. '
                .($last?"Última ejecución registrada del cron: $last.":'No hay ninguna ejecución registrada del cron.');
        }
        // Cuenta SMTP rechazada: dos o más avisos con fallo de autenticación y ningún envío aceptado desde el último rechazo.
        $auth=$db->prepare("SELECT COUNT(*) FROM intranet_mail WHERE status='Error' AND last_error=?");$auth->execute([self::AUTH_ERROR]);
        $rejected=self::stamp($db,'mail_last_auth_error');$accepted=self::stamp($db,'mail_last_ok');
        if((int)$auth->fetchColumn()>=2&&$rejected!==null&&($accepted===null||$accepted<$rejected))
            $warnings[]='El servidor SMTP está rechazando la cuenta de envío (usuario o contraseña). Los avisos no saldrán hasta corregirla: genere una nueva contraseña de aplicación, guárdela y use «Enviar correo de prueba».';
        return $warnings;
    }

    public static function retry(PortalApp $app,array $input): array
    {
        $app->requireAdmin();$id=$input['id']??null;
        if(!is_string($id)||!preg_match('/^[a-f0-9]{64}$/D',$id))throw new PortalError(422,'Notificación no válida.');
        $update=$app->db()->prepare("UPDATE intranet_mail SET status='Pendiente',attempts=0,next_attempt_at=UTC_TIMESTAMP(),last_error=NULL WHERE id=? AND status='Error'");
        $update->execute([$id]);
        if(!$update->rowCount())throw new PortalError(409,'Solo se pueden reintentar notificaciones con error.');
        PortalAudit::write($app,'Reintentar notificación','Notificaciones',$id);
        return ['ok'=>true];
    }

    public function process(PDO $db,int $limit=10,?string $recordId=null): array
    {
        $result=['sent'=>0,'failed'=>0,'skipped'=>0];
        if(!$this->ready())return $result+['configured'=>false];
        if($db->inTransaction())throw new LogicException('Los correos se procesan después de confirmar el trámite.');
        // Un solo emisor por base. El bloqueo se libera incluso si el proceso muere.
        $lock='palpa-mail-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $get=$db->prepare('SELECT GET_LOCK(?,0)');$get->execute([$lock]);
        if((int)$get->fetchColumn()!==1)return $result+['busy'=>true];
        try{
            $sql="SELECT * FROM intranet_mail WHERE status IN ('Pendiente','Error') AND attempts<5 AND next_attempt_at<=UTC_TIMESTAMP()";
            if($recordId!==null)$sql.=' AND record_id=?';
            $select=$db->prepare($sql.' ORDER BY created_at,id LIMIT '.max(1,min(50,$limit)));
            $select->execute($recordId!==null?[$recordId]:[]);
            foreach($select->fetchAll() as $row){
                // Una copia restaurada no debe notificar trámites ausentes de su estado.
                $state=$db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn();
                $data=$state?json_decode($state,true,flags:JSON_THROW_ON_ERROR):[];
                if(!PortalNotifications::exists($data,$row)){
                    $db->prepare("UPDATE intranet_mail SET status='Cancelado',next_attempt_at=NULL,last_error='Aviso cancelado: el registro de origen fue retirado o ya no corresponde.' WHERE id=?")->execute([$row['id']]);$result['skipped']++;continue;
                }
                $attempt=(int)$row['attempts']+1;
                try{
                    $this->send($row);
                }catch(Throwable $error){
                    // Nunca persistir ErrorInfo, el diálogo SMTP, contraseñas o tokens.
                    $message=self::failure($error);
                    if($message===self::AUTH_ERROR)self::mark($db,'mail_last_auth_error');
                    $delay=min(3600,60*(2**($attempt-1)));
                    $db->prepare("UPDATE intranet_mail SET status='Error',attempts=?,last_error=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL $delay SECOND) WHERE id=?")
                        ->execute([$attempt,$message,$row['id']]);
                    $result['failed']++;continue;
                }
                $db->prepare("UPDATE intranet_mail SET status='Enviado',attempts=?,sent_at=UTC_TIMESTAMP(),next_attempt_at=NULL,last_error=NULL WHERE id=?")
                    ->execute([$attempt,$row['id']]);$result['sent']++;self::mark($db,'mail_last_ok');
            }
            // Mantener fallos/pendientes para revisión; eliminar contenido entregado antiguo.
            $db->exec("DELETE FROM intranet_mail WHERE status IN ('Enviado','Cancelado') AND created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)");
        }finally{
            $release=$db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lock]);
        }
        return $result+['configured'=>true];
    }

    private function send(array $row): void
    {
        $c=$this->config;$host=$c['host']??'smtp.gmail.com';$port=$c['port']??587;
        $security=$c['encryption']??'tls';$from=$c['from_email']??'notificaciones@proyectos.pe';
        if(!is_string($host)||!preg_match('/^[a-zA-Z0-9.-]+$/D',$host)||!is_int($port)||$port<1||$port>65535
            ||!in_array($security,['tls','ssl'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL)
            ||!filter_var($c['username'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('SMTP');
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();$mail->Host=$host;$mail->Port=$port;$mail->SMTPAuth=true;
        $mail->SMTPSecure=$security;$mail->SMTPAutoTLS=true;
        $mail->Username=$c['username'];$mail->Password=$c['password'];
        $mail->Timeout=10;$mail->getSMTPInstance()->Timelimit=15;$mail->SMTPDebug=0;
        $mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
        if(!empty($c['ca_file']))$mail->SMTPOptions['ssl']['cafile']=$c['ca_file'];
        $mail->CharSet='UTF-8';$mail->Encoding='base64';
        $mail->setFrom($from,$c['from_name']??'Intranet Palpa');
        $mail->addAddress($row['recipient']);
        $domain=substr(strrchr($from,'@'),1);
        $mail->MessageID='<palpa-'.$row['id'].'@'.$domain.'>';
        $mail->Subject=$row['subject'];$mail->Body=$row['body'];$mail->isHTML(false);
        $mail->send();
    }
}
