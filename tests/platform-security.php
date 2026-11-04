<?php
declare(strict_types=1);
ob_start();$_SERVER['REMOTE_ADDR']='127.0.0.28';
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');
if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;
if(!str_ends_with($config['db_name'],'_test')||($config['smtp']['enabled']??false)!==false)throw new RuntimeException('Solo base _test sin SMTP real.');
$app=portal_app();$db=$app->db();$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function reject(int $status,callable $fn,string $message):void{try{$fn();}catch(PortalError $e){check($e->status===$status,$message);return;}throw new RuntimeException($message);}
function actor(string $name):void{global $db;$s=$db->prepare('SELECT id,auth_version FROM intranet_users WHERE username=?');$s->execute([$name]);$u=$s->fetch();if(!$u)throw new RuntimeException('Ejecuta tests/intranet.php primero.');$_SESSION['user_id']=(int)$u['id'];$_SESSION['auth_version']=(int)$u['auth_version'];}
reject(419,fn()=>$app->checkCsrf('incorrecto'),'Token CSRF incorrecto rechazado');
$app->checkCsrf($_SESSION['csrf']);check(true,'Token CSRF de la sesión aceptado');
actor('estudiante');
$file=PortalFiles::store($app,'privado.pdf',"%PDF-1.4\n%%EOF");
try{
    check(PortalFiles::metadata($app,$file)['fileId']===$file['fileId'],'Propietario consulta su archivo privado');
    reject(422,fn()=>PortalFiles::store($app,'ejecutable.php',"<?php echo 1;"),'Carga ejecutable rechazada');
    reject(422,fn()=>PortalFiles::store($app,'disfrazado.pdf','texto plano'),'Extensión falsa rechazada');
    reject(422,fn()=>PortalFiles::metadata($app,['fileId'=>'../../config.php']),'Referencia de archivo inválida rechazada');
    actor('egresado');reject(403,fn()=>PortalFiles::metadata($app,$file),'Otra cuenta no descarga el archivo privado');
    reject(403,fn()=>$app->saveSmtp([]),'Otra cuenta no configura SMTP');
    reject(403,fn()=>PortalBackups::restore($app,'/ruta/inexistente'),'Otra cuenta no restaura respaldos');
    reject(403,fn()=>$app->accountOperation(['action'=>'create']),'Otra cuenta no crea usuarios');
    $app->logout();reject(403,fn()=>PortalFiles::metadata($app,$file),'Visitante no descarga archivo de cuenta');
    actor('admin');$b=$app->bootstrap();
    reject(409,fn()=>$app->save(['data'=>$b['data'],'revision'=>$b['revision']-1]),'Escritura obsoleta rechazada sin sobrescribir datos');
    $backup=PortalBackups::create($app);$backupPath=PortalBackups::path($backup['id']);
    $bad=tempnam(sys_get_temp_dir(),'palpa-corrupt-');
    try{
        $in=gzopen($backupPath,'rb');$out=gzopen($bad,'wb');
        while(($line=gzgets($in,67108866))!==false){$item=json_decode($line,true,flags:JSON_THROW_ON_ERROR);if(isset($item['end']))$line=json_encode([...$item,'sha256'=>str_repeat('0',64)])."\n";gzwrite($out,$line);}
        gzclose($in);gzclose($out);
        $before=$db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn();
        $users=$db->query('SELECT id,password,auth_version FROM intranet_users ORDER BY id')->fetchAll();
        reject(422,fn()=>PortalBackups::restore($app,$bad),'Respaldo con hash alterado rechazado');
        check($db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn()===$before,'Restauración fallida conserva todo el estado');
        check($db->query('SELECT id,password,auth_version FROM intranet_users ORDER BY id')->fetchAll()===$users,'Restauración fallida conserva cuentas y sesiones');
    }finally{unlink($bad);unlink($backupPath);}
}finally{$db->prepare('DELETE FROM intranet_files WHERE id=?')->execute([$file['fileId']]);}
echo "$count comprobaciones de seguridad y recuperación aprobadas.\n";ob_end_flush();
