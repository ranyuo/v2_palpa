<?php
declare(strict_types=1);
// Correo de prueba, alerta de cron detenido y alerta de cuenta SMTP rechazada.
// Ejecutar después de tests/intranet.php y tests/mail.php, sobre la misma base desechable _test.
$_SERVER['REMOTE_ADDR']='127.0.0.32';
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
if(($config['smtp']['enabled']??false)!==false)throw new RuntimeException('Deshabilita SMTP real en la configuración de pruebas.');
$app=portal_app();$db=$app->db();PortalSchema::apply($db);
$db->exec('DELETE FROM intranet_rate_limits');
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function rejects(callable $fn,int $status,string $message):void{try{$fn();}catch(PortalError $e){check($e->status===$status,$message.' ('.$e->getMessage().')');return;}throw new RuntimeException($message.': no se rechazó');}
$keys=['mail_worker_last_run','mail_last_ok','mail_last_auth_error'];
$clearStamps=function()use($db,$keys){$db->exec("DELETE FROM intranet_settings WHERE setting_key IN ('".implode("','",$keys)."')");};
$warnings=fn(PortalMail $m)=>implode("\n",$m->status($app)['warnings']);

// Clasificación de fallos: mensajes fijos y seguros, sin datos del diálogo SMTP.
$e=fn(string $t)=>new \PHPMailer\PHPMailer\Exception($t);
check(PortalMail::failure($e('SMTP Error: Could not authenticate.'))===PortalMail::AUTH_ERROR,'Fallo de autenticación se identifica como cuenta rechazada');
check(str_contains(PortalMail::failure($e('SMTP Error: Could not connect to SMTP host. Failed to connect to server')),'conectar'),'Fallo de conexión se identifica como conexión');
check(str_contains(PortalMail::failure($e('SMTP Error: The following recipients failed: x@y.pe: 550 secreto')),'destinatario')&&!str_contains(PortalMail::failure($e('SMTP Error: The following recipients failed: x@y.pe: 550 secreto')),'secreto'),'Destinatario rechazado sin exponer la respuesta del servidor');
check(PortalMail::failure($e('SMTP Error: data not accepted.'))===PortalMail::GENERIC_ERROR,'Otros fallos conservan el mensaje general');
check(PortalMail::failure(new InvalidArgumentException('SMTP'))==='Configuración SMTP no válida.','Configuración inválida conserva su mensaje');

$dir=__DIR__.'/.results/mail-alerts-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);
$cert=$dir.'/cert.pem';$key=$dir.'/key.pem';$output=$dir.'/messages.jsonl';$portFile=$dir.'/port';
$proc=proc_open(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',$key,'-out',$cert,'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],[0=>['pipe','r'],1=>['file',$dir.'/openssl.log','a'],2=>['file',$dir.'/openssl.log','a']],$pipes);
fclose($pipes[0]);if(proc_close($proc)!==0)throw new RuntimeException('No se pudo crear el certificado local.');chmod($key,0600);
$server=proc_open(['python3',__DIR__.'/fixtures/smtp.py',$cert,$key,$output,$portFile],[0=>['pipe','r'],1=>['file',$dir.'/smtp.log','a'],2=>['file',$dir.'/smtp.log','a']],$pipes);fclose($pipes[0]);
$created=[];
try{
 for($i=0;$i<100&&!is_file($portFile);$i++)usleep(50000);
 if(!is_file($portFile))throw new RuntimeException('El SMTP local no inició.');
 $smtp=['enabled'=>true,'host'=>'localhost','port'=>(int)file_get_contents($portFile),'encryption'=>'ssl','username'=>'notificaciones@proyectos.pe','password'=>'solo-prueba-local','from_email'=>'notificaciones@proyectos.pe','ca_file'=>$cert];
 $good=new PortalMail($smtp);$bad=new PortalMail([...$smtp,'password'=>'incorrecta']);
 $clearStamps();

 // Correo de prueba.
 $r=$good->sendTest($db,'prueba-destino@example.test','IES Público Palpa');
 check($r['ok']===true&&str_contains($r['message'],'prueba-destino@example.test'),'El correo de prueba se acepta con la configuración correcta');
 $sent=array_map(fn($l)=>json_decode($l,true),array_filter(explode("\n",(string)@file_get_contents($output))));
 $last=end($sent);check(is_array($last)&&$last['recipient']==='prueba-destino@example.test'&&str_contains(quoted_printable_decode($last['message']).base64_decode(preg_replace('/^.*?\r?\n\r?\n/s','',$last['message'])),'IES Público Palpa'),'El correo de prueba llega al servidor con el nombre de la institución');
 check(PortalMail::stamp($db,'mail_last_ok')!==null,'Un envío aceptado deja constancia');
 check((int)$db->query("SELECT COUNT(*) FROM intranet_mail WHERE recipient='prueba-destino@example.test'")->fetchColumn()===0,'El correo de prueba no pasa por la cola de avisos');
 $r=$bad->sendTest($db,'prueba-destino@example.test','IES');
 check($r['ok']===false&&$r['message']===PortalMail::AUTH_ERROR,'Una contraseña rechazada se informa como credenciales incorrectas');
 check(!str_contains(json_encode($r),'incorrecta')&&!str_contains(json_encode($r),'535'),'La respuesta de la prueba no expone contraseña ni diálogo SMTP');
 $r=$good->sendTest($db,'reject@example.test','IES');
 check($r['ok']===false&&str_contains($r['message'],'destinatario'),'Un destinatario rechazado se informa como tal');
 $closed=stream_socket_server('tcp://127.0.0.1:0');$closedPort=(int)substr(strrchr(stream_socket_get_name($closed,false),':'),1);fclose($closed);
 $r=(new PortalMail([...$smtp,'port'=>$closedPort]))->sendTest($db,'prueba-destino@example.test','IES');
 check($r['ok']===false&&str_contains($r['message'],'conectar'),'Un servidor inaccesible se informa como fallo de conexión');
 rejects(fn()=>(new PortalMail([...$smtp,'password'=>'']))->sendTest($db,'a@example.test','IES'),409,'Sin contraseña guardada no se intenta la prueba');
 check((new PortalMail([...$smtp,'enabled'=>false]))->sendTest($db,'prueba-destino@example.test','IES')['ok']===true,'La prueba funciona antes de activar el envío');

 // Ruta de la intranet: validación, permisos y auditoría.
 rejects(fn()=>$app->testSmtp(['to'=>'no-es-correo']),422,'La prueba rechaza un destinatario no válido');
 rejects(fn()=>$app->testSmtp(['to'=>'a@example.test']),409,'La prueba avisa cuando la configuración guardada no tiene contraseña');
 $withSmtp=new PortalApp([...$config,'smtp'=>$smtp]);$r=$withSmtp->testSmtp([]);
 check($r['ok']===true&&$r['to']===$app->user()['correo'],'Sin destinatario la prueba se envía al correo de la cuenta administradora');
 $a=$db->query("SELECT details FROM intranet_audit WHERE action='Probar correo SMTP' ORDER BY id DESC LIMIT 1")->fetchColumn();
 check(is_string($a)&&str_contains($a,'Aceptado')&&!str_contains($a,'solo-prueba-local'),'La prueba queda en la auditoría con su resultado y sin la contraseña');
 $app->logout();rejects(fn()=>$app->testSmtp([]),403,'La prueba de correo requiere sesión');
 $app->login(['username'=>'atencion','password'=>'PruebaIntranet-2026!']);rejects(fn()=>$app->testSmtp([]),403,'Personal sin administración no puede probar el correo');
 $app->logout();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);
 $status=0;for($i=0;$i<6&&$status!==429;$i++){try{$app->testSmtp(['to'=>'x']);}catch(PortalError $e){$status=$e->status;}}
 check($status===429,'Las pruebas de correo tienen límite de frecuencia');

 // Alerta de cuenta rechazada: dos avisos con fallo de autenticación y ningún envío aceptado desde entonces.
 $clearStamps();$current=$app->bootstrap()['data'];$claim=$current['reclamos'][0];
 foreach(['a','b'] as $n){PortalMail::queue($db,'reclamos',(string)$claim['id'],'alerta-'.$n,'auth','destino@example.test','QA-AUTH-'.$n,'Prueba','Prueba de alerta.');}
 $created[]=hash('sha256','reclamos|'.$claim['id']);
 $out=$bad->process($db,10,hash('sha256','reclamos|'.$claim['id']));
 check($out['failed']>=2,'La cola registra los fallos de autenticación');
 $q=$db->prepare("SELECT last_error FROM intranet_mail WHERE source_type='reclamos' AND event LIKE 'alerta-%'");$q->execute();
 check(array_unique($q->fetchAll(PDO::FETCH_COLUMN))===[PortalMail::AUTH_ERROR],'El detalle del aviso indica credenciales rechazadas');
 check(str_contains($warnings($good),'rechazando la cuenta de envío'),'El panel alerta que la cuenta SMTP está siendo rechazada');
 check(!str_contains($warnings(new PortalMail([...$smtp,'enabled'=>false])),'rechazando'),'Sin envío activado no se muestran alertas de entrega');
 $good->sendTest($db,'prueba-destino@example.test','IES');
 check(!str_contains($warnings($good),'rechazando'),'Tras un envío aceptado desaparece la alerta de cuenta rechazada');
 $db->exec("UPDATE intranet_mail SET status='Enviado',next_attempt_at=NULL WHERE source_type='reclamos' AND event LIKE 'alerta-%'");

 // Alerta de cron detenido.
 $clearStamps();
 $db->exec("UPDATE intranet_mail SET next_attempt_at=UTC_TIMESTAMP() WHERE status IN ('Pendiente','Error') AND attempts<5");
 check(!str_contains($warnings($good),'atraso'),'Sin avisos atrasados no hay alerta de cron');
 check($good->status($app)['worker_last_run']===null,'Sin ejecuciones registradas el panel lo indica');
 PortalMail::queue($db,'reclamos',(string)$claim['id'],'cron','late','destino@example.test','QA-CRON','Prueba','Prueba de cron.');
 $db->exec("UPDATE intranet_mail SET next_attempt_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE event='cron' AND reference='QA-CRON'");
 check(!str_contains($warnings($good),'atraso'),'Un aviso con 10 minutos de espera todavía no alerta');
 $db->exec("UPDATE intranet_mail SET next_attempt_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE) WHERE event='cron' AND reference='QA-CRON'");
 $w=$warnings($good);check(str_contains($w,'1 aviso(s) con más de 15 minutos de atraso')&&str_contains($w,'mail-worker.php')&&str_contains($w,'No hay ninguna ejecución registrada'),'Un aviso con 20 minutos de atraso alerta que el cron no corre');
 $db->exec("UPDATE intranet_mail SET status='Error',attempts=5 WHERE event='cron' AND reference='QA-CRON'");
 check(!str_contains($warnings($good),'atraso'),'Los avisos que agotaron sus intentos no cuentan como atraso del cron');
 $db->exec("UPDATE intranet_mail SET status='Pendiente',attempts=0 WHERE event='cron' AND reference='QA-CRON'");

 // El cron real deja constancia aunque el envío esté desactivado en la configuración de pruebas.
 $env=['PORTAL_CONFIG_FILE'=>$path]+getenv();
 $worker=proc_open([PHP_BINARY,dirname(__DIR__).'/portal-private/mail-worker.php'],[0=>['pipe','r'],1=>['file',$dir.'/worker.log','a'],2=>['file',$dir.'/worker.log','a']],$pipes,null,$env);fclose($pipes[0]);proc_close($worker);
 $stamp=PortalMail::stamp($db,'mail_worker_last_run');
 check($stamp!==null&&abs(strtotime($stamp.' UTC')-time())<120,'mail-worker.php registra la hora de su ejecución en UTC');
 $status=$good->status($app);
 check(is_string($status['worker_last_run'])&&preg_match('#^\d\d/\d\d/\d{4} \d\d:\d\d$#',$status['worker_last_run'])===1,'El panel muestra la última ejecución del cron en hora local');
 check(str_contains(implode("\n",$status['warnings']),'Última ejecución registrada del cron: '.$status['worker_last_run']),'La alerta de atraso incluye la última ejecución del cron');
 check(!str_contains(json_encode($status),'solo-prueba-local'),'El panel no expone la contraseña SMTP');

 echo "$count comprobaciones de alertas de correo aprobadas.\n";
}finally{
 foreach($created as $batch)$db->prepare('DELETE FROM intranet_mail WHERE record_id=?')->execute([$batch]);
 $clearStamps();
 proc_terminate($server);proc_close($server);$app->logout();
}
ob_end_flush();
