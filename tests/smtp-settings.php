<?php
declare(strict_types=1);
// Cliente independiente para no acumular cuotas entre suites distintas.
$_SERVER['REMOTE_ADDR']='127.0.0.24';
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base _test.');
portal_app();
$dir=__DIR__.'/.results/smtp-settings-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);
$app=new PortalApp($config,$dir.'/config.test.php');
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$count=0;
function check(bool $v,string $message):void{global $count;if(!$v)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function denied(callable $f,int $code):void{try{$f();throw new RuntimeException('No se rechazó el cambio.');}catch(PortalError $e){check($e->status===$code,'Solicitud rechazada con '.$code);}}
$input=['enabled'=>false,'host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls','username'=>'notificaciones@proyectos.pe','from_email'=>'notificaciones@proyectos.pe','from_name'=>'Palpa QA','password'=>'solo-prueba-cifrado'];
try{
 $view=$app->saveSmtp($input);check($view['has_password']&&!array_key_exists('password',$view),'Respuesta indica presencia sin devolver contraseña');
 $file=$dir.'/.smtp-settings.json';$raw=file_get_contents($file);check(!str_contains($raw,$input['password'])&&!str_contains($raw,$input['username']),'Archivo privado cifrado sin credenciales en texto plano');
 check((fileperms($file)&0777)===0600,'Permisos privados 0600');
 $next=new PortalApp($config,$dir.'/config.test.php');check($next->smtpForm()['host']==='smtp.gmail.com','Configuración persiste en nuevas instancias');
 $next->saveSmtp([...$input,'password'=>'','enabled'=>true]);check((new SmtpSettings($config,$dir.'/config.test.php'))->read()['password']===$input['password'],'Contraseña vacía conserva el secreto guardado');
 check($next->smtpForm()['enabled']===true,'Activación se guarda');
 denied(fn()=>$next->saveSmtp([...$input,'password'=>'','username'=>'otra@example.test','enabled'=>true]),422);
 foreach([['host'=>'https://smtp.gmail.com'],['port'=>0],['encryption'=>'none'],['from_email'=>"uno@example.test\r\nBcc: otro@example.test"],['enabled'=>'true'],['from_name'=>"Palpa\nOtro"],['password'=>"incorrecta\n"]] as $change)denied(fn()=>$next->saveSmtp([...$input,...$change]),422);
 $replacement='otra-clave-solo-prueba';$next->saveSmtp([...$input,'password'=>$replacement]);check((new SmtpSettings($config,$dir.'/config.test.php'))->read()['password']===$replacement,'Se puede reemplazar la contraseña');
 check(!str_contains(json_encode($next->bootstrap()),$replacement),'Bootstrap no contiene el secreto');
 check(!str_contains(json_encode($next->mailStatus()),$replacement),'Panel no contiene el secreto');
 denied(fn()=>(new SmtpSettings([...$config,'install_key'=>str_repeat('z',64)],$dir.'/config.test.php'))->read(),503);
 $blob=json_decode(file_get_contents($file),true);$blob['tag']=base64_encode(str_repeat('x',16));file_put_contents($file,json_encode($blob));denied(fn()=>$next->smtpForm(),503);file_put_contents($file,$raw);
 $app->logout();denied(fn()=>$app->smtpForm(),403);denied(fn()=>$app->saveSmtp($input),403);
 $app->login(['username'=>'atencion','password'=>'PruebaIntranet-2026!']);denied(fn()=>$app->smtpForm(),403);denied(fn()=>$app->saveSmtp($input),403);
 echo "$count comprobaciones de configuración SMTP aprobadas.\n";
}finally{$app->logout();foreach(glob($dir.'/{,.}*',GLOB_BRACE) as $f)if(is_file($f))unlink($f);rmdir($dir);}
ob_end_flush();
