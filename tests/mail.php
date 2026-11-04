<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');
if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;
if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
if(($config['smtp']['enabled']??false)!==false)throw new RuntimeException('Deshabilita SMTP real en la configuración de pruebas.');
$app=portal_app();$db=$app->db();PortalSchema::apply($db);
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);
$count=0;
function check(bool $value,string $message): void{global $count;if(!$value)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function setting(string $email): void{global $app;$b=$app->bootstrap();$d=$b['data'];$d['config']['mpv']['correo']=$email;$app->save(['data'=>$d,'revision'=>$b['revision']]);}
function input(): array{global $app;$c=$app->challenge(['purpose'=>'expedientes']);return ['record'=>['nombres'=>'Solicitante QA','tipoPersona'=>'Persona natural','tipoDoc'=>'DNI','numDoc'=>'12345678','correo'=>'solicitante@example.test','telefono'=>'999888777','tipoTramite'=>'Solicitud','asunto'=>'Prueba real de transporte SMTP local','folios'=>1,'areaDestino'=>'MP','archivos'=>[['original'=>'solicitud.pdf','data'=>'data:application/pdf;base64,'.base64_encode("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")]]],'idempotency_key'=>sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(6))),'proof'=>['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true]];}
$dir=__DIR__.'/.results/mail-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);
$cert=$dir.'/cert.pem';$key=$dir.'/key.pem';$output=$dir.'/messages.jsonl';$portFile=$dir.'/port';
$proc=proc_open(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',$key,'-out',$cert,'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],[0=>['pipe','r'],1=>['file',$dir.'/openssl.log','a'],2=>['file',$dir.'/openssl.log','a']],$pipes);
fclose($pipes[0]);if(proc_close($proc)!==0)throw new RuntimeException('No se pudo crear el certificado local.');chmod($key,0600);
$server=proc_open(['python3',__DIR__.'/fixtures/smtp.py',$cert,$key,$output,$portFile],[0=>['pipe','r'],1=>['file',$dir.'/smtp.log','a'],2=>['file',$dir.'/smtp.log','a']],$pipes);fclose($pipes[0]);
$original=$app->bootstrap()['data']['config']['mpv']['correo'];
try{
 for($i=0;$i<100&&!is_file($portFile);$i++)usleep(50000);
 if(!is_file($portFile))throw new RuntimeException('El SMTP local no inició.');
 $smtp=['enabled'=>true,'host'=>'localhost','port'=>(int)file_get_contents($portFile),'encryption'=>'ssl','username'=>'notificaciones@proyectos.pe','password'=>'solo-prueba-local','from_email'=>'notificaciones@proyectos.pe','ca_file'=>$cert];
 $mail=new PortalMail($smtp);
 setting('mesa-inicial@example.test');$in=input();$r=$app->submit('expedientes',$in);
 $id=$r['record']['id'];$q=$db->prepare('SELECT * FROM intranet_mail WHERE record_id=? ORDER BY recipient');$q->execute([$id]);$rows=$q->fetchAll();
 check(count($rows)===2,'Registro crea dos correos aunque SMTP no esté habilitado');
 check(array_column($rows,'recipient')===['mesa-inicial@example.test','solicitante@example.test'],'Destinatarios institucional y solicitante separados');
 check(array_unique(array_column($rows,'status'))===['Pendiente'],'Sin credenciales no se finge un envío');
 check($app->submit('expedientes',$in)['status']===200,'Reintento del mismo formulario devuelve el trámite original');
 $q->execute([$id]);check(count($q->fetchAll())===2,'Reintento del formulario no duplica mensajes');
 setting('mesa-nueva@example.test');
 $badTls=new PortalMail([...$smtp,'ca_file'=>__DIR__.'/../portal-private/vendor/phpmailer/LICENSE']);
 check($badTls->process($db,2,$id)['failed']===2,'Certificado sin confianza impide el envío');
 check(!is_file($output),'No se entregan mensajes cuando falla TLS');
 $db->prepare('UPDATE intranet_mail SET next_attempt_at=UTC_TIMESTAMP() WHERE record_id=?')->execute([$id]);
 check($mail->process($db,2,$id)['sent']===2,'SMTP autenticado y TLS verificado entregan ambos correos');
 $messages=array_map(fn($s)=>json_decode($s,true,flags:JSON_THROW_ON_ERROR),file($output,FILE_IGNORE_NEW_LINES));
 check(array_column($messages,'recipient')===['mesa-inicial@example.test','solicitante@example.test']||array_column($messages,'recipient')===['solicitante@example.test','mesa-inicial@example.test'],'El cambio de configuración no redirige avisos ya registrados');
 foreach($messages as $m){check($m['sender']==='notificaciones@proyectos.pe','Remitente central autenticado');check(str_contains($m['message'],'Message-ID: <palpa-'),'Identificador estable del mensaje');check(!str_contains($m['message'],'solo-prueba-local'),'El correo no contiene credenciales');$parts=preg_split('/\r?\n\r?\n/',$m['message'],2);$body=base64_decode($parts[1]??'',true);check(is_string($body)&&str_contains($body,$r['record']['numero']),'Contenido incluye el expediente registrado');if($m['recipient']==='solicitante@example.test')check(str_contains($body,$r['record']['cargoCod']),'Confirmación incluye el código de seguimiento del solicitante');}
 check($mail->process($db,2,$id)['sent']===0,'Procesar de nuevo no reenvía mensajes entregados');
 $q->execute([$id]);check(array_unique(array_column($q->fetchAll(),'status'))===['Enviado'],'Entrega SMTP queda registrada');
 setting('reject@example.test');$failed=$app->submit('expedientes',input());$fid=$failed['record']['id'];
 $out=$mail->process($db,2,$fid);check($out['sent']===1&&$out['failed']===1,'Fallo institucional no bloquea la confirmación al solicitante');
 $q->execute([$fid]);$failedRow=array_values(array_filter($q->fetchAll(),fn($r)=>$r['status']==='Error'))[0];
 check(str_contains($failedRow['last_error'],'SMTP')&&!str_contains($failedRow['last_error'],'solo-prueba-local'),'Error útil sin credenciales ni diálogo SMTP');
 check($mail->process($db,2,$fid)['failed']===0,'Reintento respeta el intervalo de espera');
 PortalMail::retry($app,['id'=>$failedRow['id']]);
 $wrong=new PortalMail([...$smtp,'password'=>'incorrecta']);check($wrong->process($db,2,$fid)['failed']===1,'Autenticación incorrecta no entrega el mensaje');
 $db->prepare("UPDATE intranet_mail SET attempts=5,next_attempt_at=UTC_TIMESTAMP() WHERE id=?")->execute([$failedRow['id']]);
 check($mail->process($db,2,$fid)['failed']===0,'Se detienen reintentos automáticos tras cinco fallos');
 $before=(int)$db->query('SELECT COUNT(*) FROM intranet_mail')->fetchColumn();
 $db->beginTransaction();PortalMail::enqueue($db,[...$r['record'],'id'=>str_repeat('f',64)],$app->bootstrap()['data']);$db->rollBack();
 check((int)$db->query('SELECT COUNT(*) FROM intranet_mail')->fetchColumn()===$before,'Reversión del trámite revierte sus notificaciones');
 proc_terminate($server);proc_close($server);unlink($portFile);
 $server=proc_open(['python3',__DIR__.'/fixtures/smtp.py',$cert,$key,$output,$portFile,'starttls'],[0=>['pipe','r'],1=>['file',$dir.'/smtp.log','a'],2=>['file',$dir.'/smtp.log','a']],$pipes);fclose($pipes[0]);
 for($i=0;$i<100&&!is_file($portFile);$i++)usleep(50000);
 if(!is_file($portFile))throw new RuntimeException('STARTTLS local no inició.');
 $smtp=[...$smtp,'port'=>(int)file_get_contents($portFile),'encryption'=>'tls'];
 $live=new PortalApp([...$config,'smtp'=>['ca_file'=>$cert]],$dir.'/config.test.php');$live->saveSmtp(array_intersect_key([...$smtp,'from_name'=>'Palpa QA'],array_flip(['enabled','host','port','encryption','username','password','from_email','from_name'])));setting('mesa-nueva@example.test');$immediate=$live->submit('expedientes',input());$iid=$immediate['record']['id'];
 check($immediate['status']===201,'Registro con SMTP habilitado se confirma correctamente');
 $q->execute([$iid]);check(array_unique(array_column($q->fetchAll(),'status'))===['Enviado'],'Registro entrega ambos mensajes con STARTTLS verificado después del commit');
 $down=new PortalApp([...$config,'smtp'=>[...$smtp,'password'=>'incorrecta']]);$accepted=$down->submit('expedientes',input());$aid=$accepted['record']['id'];
 check($accepted['status']===201,'Fallo de autenticación SMTP no pierde el trámite');
 $q->execute([$aid]);check(array_unique(array_column($q->fetchAll(),'status'))===['Error'],'Fallo de entrega queda disponible para reintento');
 $status=$mail->status($app);check(!str_contains(json_encode($status),'solo-prueba-local'),'Panel no expone contraseña SMTP');
 $app->logout();try{$mail->status($app);throw new RuntimeException('Panel público');}catch(PortalError $e){check($e->status===403,'Cola privada no accesible sin sesión');}
 $app->login(['username'=>'atencion','password'=>'PruebaIntranet-2026!']);try{PortalMail::retry($app,['id'=>$failedRow['id']]);throw new RuntimeException('Reintento sin permiso');}catch(PortalError $e){check($e->status===403,'Personal sin administración no puede reintentar');}
 echo "$count comprobaciones de correo aprobadas.\n";
}finally{
 if($db->inTransaction())$db->rollBack();$app->logout();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);setting($original);
 foreach([$id??null,$fid??null,$iid??null,$aid??null] as $recordId)if($recordId)$db->prepare('DELETE FROM intranet_mail WHERE record_id=?')->execute([$recordId]);
 proc_terminate($server);proc_close($server);$app->logout();
}

ob_end_flush();
