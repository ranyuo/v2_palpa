<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base _test.');
$app=portal_app();$db=$app->db();$backup='intranet_mail_review_'.bin2hex(random_bytes(4));$db->exec('RENAME TABLE intranet_mail TO '.$backup);$count=0;
function check(bool $v,string $m):void{global $count;if(!$v)throw new RuntimeException($m);$count++;echo "OK $m\n";}
try{
 $db->exec('CREATE TABLE intranet_mail LIKE '.$backup);
 foreach(['source_type','source_id','source_key','event'] as $column)$db->exec('ALTER TABLE intranet_mail DROP COLUMN '.$column);
 $db->exec('ALTER TABLE intranet_mail MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
 $data=json_decode($db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn(),true);$exp=$data['expedientes'][0];$id=hash('sha256','cola-version-6');
 $q=$db->prepare("INSERT INTO intranet_mail (id,record_id,reference,recipient,subject,body,status,attempts,last_error,created_at,next_attempt_at) VALUES (?,?,?,?,?,?,'Error',2,'SMTP no confirmó el envío.','2026-01-01 08:00:00','2026-01-01 08:10:00')");$q->execute([$id,$exp['id'],$exp['numero'],'migracion@example.test','Mensaje anterior','Contenido anterior']);
 PortalSchema::apply($db);$r=$db->query('SELECT * FROM intranet_mail')->fetch();
 check($r['id']===$id&&$r['body']==='Contenido anterior'&&$r['recipient']==='migracion@example.test','Migración conserva mensaje y destinatario de versión 6');
 check($r['status']==='Error'&&(int)$r['attempts']===2&&$r['last_error']==='SMTP no confirmó el envío.','Migración conserva intentos y error');
 check($r['source_type']==='expedientes'&&$r['source_id']===''&&$r['source_key']===''&&$r['event']==='registro','Se agregan metadatos compatibles para la cola anterior');
 check(PortalNotifications::exists($data,$r),'Mensaje anterior sigue vinculado a su expediente');
 $info=$db->query("SHOW COLUMNS FROM intranet_mail LIKE 'created_at'")->fetch();check(strtolower($info['Type'])==='datetime(6)','Se conserva precisión temporal para ordenar eventos');
 PortalSchema::apply($db);check($db->query('SELECT * FROM intranet_mail')->fetch()===$r,'Volver a actualizar no cambia el mensaje');
 $db->exec('ALTER TABLE intranet_mail DROP COLUMN source_key');PortalSchema::apply($db);check($db->query("SHOW COLUMNS FROM intranet_mail LIKE 'source_key'")->fetch()!==false,'Actualización parcial puede completarse sin perder la cola');
 $changed=$data;foreach($changed['expedientes'] as &$row)if($row['id']===$exp['id'])$row['numero']='OTRO-2026-999999';check(!PortalNotifications::exists($changed,$r),'Referencia anterior no se entrega a un expediente reutilizado');
 echo "$count comprobaciones de migración de la cola aprobadas.\n";
}finally{$db->exec('DROP TABLE IF EXISTS intranet_mail');$db->exec('RENAME TABLE '.$backup.' TO intranet_mail');}
ob_end_flush();
