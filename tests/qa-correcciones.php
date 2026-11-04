<?php
declare(strict_types=1);
// Correcciones del control de calidad del 04/11/2026. Ejecutar después de tests/intranet.php.
$_SERVER['REMOTE_ADDR']='127.0.0.31';
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base _test.');
portal_app();$app=new PortalApp([...$config,'smtp'=>['enabled'=>false]]);$db=$app->db();PortalSchema::apply($db);
const ADMIN_PASS='PruebaIntranet-2026!';$app->login(['username'=>'admin','password'=>ADMIN_PASS]);$count=0;
function check(bool $v,string $m):void{global $count;if(!$v)throw new RuntimeException($m);$count++;echo "OK $m\n";}
function edit(callable $f):void{global $app;$b=$app->bootstrap();$d=$b['data'];$f($d);$app->save(['data'=>$d,'revision'=>$b['revision']]);}
function rejected(callable $f,string $m):void{try{$f();}catch(PortalError $e){check($e->status===422,$m.' ('.$e->getMessage().')');return;}throw new RuntimeException($m);}
function mails(string $module,string $id):int{global $db;$q=$db->prepare('SELECT COUNT(*) FROM intranet_mail WHERE source_type=? AND source_id=?');$q->execute([$module,$id]);return (int)$q->fetchColumn();}
function actor(string $name):void{global $db;$s=$db->prepare('SELECT id,auth_version FROM intranet_users WHERE username=?');$s->execute([$name]);$u=$s->fetch();$_SESSION['user_id']=(int)$u['id'];$_SESSION['auth_version']=(int)$u['auth_version'];}
function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20);}

// 1. Anulación de reclamos: estado y bitácora coherentes; el registro anulado queda cerrado.
$template=$app->bootstrap()['data']['reclamos'][0];$c=$app->challenge(['purpose'=>'reclamos']);
$claim=$app->submit('reclamos',['record'=>$template,'idempotency_key'=>uuid(),'proof'=>['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true]])['record'];
rejected(fn()=>edit(function(&$d)use($claim){foreach($d['reclamos'] as &$r)if($r['id']===$claim['id']){$r['anulado']=true;$r['motivoAnulacion']='x';}}),'La anulación exige motivo');
edit(function(&$d)use($claim){foreach($d['reclamos'] as &$r)if($r['id']===$claim['id']){$r['anulado']=true;$r['motivoAnulacion']='Registro duplicado de prueba';}});
$saved=current(array_filter($app->bootstrap()['data']['reclamos'],fn($r)=>$r['id']===$claim['id']));
check($saved['anulado']===true&&$saved['estado']==='Anulado','El reclamo anulado queda con estado Anulado');
check(end($saved['historial'])['estado']==='Anulado','La bitácora registra Anulado y no el estado anterior');
rejected(fn()=>edit(function(&$d)use($claim){foreach($d['reclamos'] as &$r)if($r['id']===$claim['id'])$r['estado']='Atendido';}),'Un reclamo anulado no admite cambios de estado');

// 2. Accesos denegados registrados en la auditoría del servidor.
$before=(int)$db->query("SELECT COUNT(*) FROM intranet_audit WHERE action='Acceso denegado'")->fetchColumn();
$app->interfaceEvent(['action'=>'Acceso denegado','entity'=>'Usuarios y roles','route'=>'#/admin/usuarios','reason'=>'']);
$row=$db->query("SELECT * FROM intranet_audit WHERE action='Acceso denegado' ORDER BY id DESC LIMIT 1")->fetch();
check((int)$db->query("SELECT COUNT(*) FROM intranet_audit WHERE action='Acceso denegado'")->fetchColumn()===$before+1&&$row['record_key']==='#/admin/usuarios'&&$row['username']==='admin','El acceso denegado queda en intranet_audit con usuario y ruta');
rejected(fn()=>$app->interfaceEvent(['action'=>'Acceso denegado','entity'=>str_repeat('x',300)]),'Eventos de acceso denegado con datos excesivos se rechazan');

// 3. Contraseñas temporales: sin prefijo fijo y con cambio obligatorio.
$student=$app->bootstrap()['data']['academico']['estudiantes'][2];
$db->prepare('DELETE FROM intranet_users WHERE username=?')->execute([$student['dni']]);
$created=$app->accountOperation(['action'=>'student','student_id'=>$student['id']]);$temp=$created['temporary_password'];
check(!str_contains($temp,'Demo')&&preg_match('/^[A-Za-z0-9]{4}(-[A-Za-z0-9]{4}){3}$/',$temp)===1,'La contraseña temporal es aleatoria y sin prefijo fijo');
check(PortalApp::temporaryPassword()!==PortalApp::temporaryPassword(),'Dos contraseñas temporales consecutivas son distintas');
check(($created['user']['cambioClave']??false)===true,'La cuenta creada con clave temporal exige cambio');
$app->logout();$app->login(['username'=>$student['dni'],'password'=>$temp]);check(($app->user()['cambioClave']??false)===true,'El titular inicia sesión con la marca de cambio obligatorio');
rejected(fn()=>$app->changePassword(['current_password'=>$temp,'password'=>$temp,'password_confirmation'=>$temp]),'La contraseña nueva debe ser distinta de la temporal');
$app->changePassword(['current_password'=>$temp,'password'=>'NuevaClaveSegura-2026','password_confirmation'=>'NuevaClaveSegura-2026']);
check(empty($app->user()['cambioClave']),'Al cambiar la contraseña se levanta la marca');
$app->logout();$app->login(['username'=>'admin','password'=>ADMIN_PASS]);
$id=(int)$db->query("SELECT id FROM intranet_users WHERE username=".$db->quote($student['dni']))->fetchColumn();
$dto=current(array_filter($app->bootstrap()['data']['usuarios'],fn($u)=>$u['server_id']===$id));
$app->accountOperation(['action'=>'update','id'=>$id,'account'=>['username'=>$dto['usuario'],'name'=>$dto['nombre'],'email'=>$dto['correo'],'role'=>$dto['rol'],'vin'=>$dto['vin']??'','dni'=>$dto['dni']??'','password'=>'ClaveRestablecida-2026','password_confirmation'=>'ClaveRestablecida-2026','estado'=>'Activo']]);
$profile=json_decode((string)$db->query("SELECT profile FROM intranet_users WHERE id=$id")->fetchColumn(),true);
check(($profile['cambioClave']??false)===true,'Restablecer la clave de otra cuenta vuelve a exigir el cambio');

// 4. Aviso por correo opcional en Mesa de Partes; la marca no se guarda en el expediente.
$file=current($app->bootstrap()['data']['expedientes']);$exp=$file['id'];
$before=mails('expedientes',$exp);
edit(function(&$d)use($exp){foreach($d['expedientes'] as &$x)if($x['id']===$exp){$x['estado']='En proceso';$x['sinAviso']=true;$x['historial'][]=['nota'=>'Corrección interna sin aviso'];}});
check(mails('expedientes',$exp)===$before,'Un cambio marcado sin aviso no encola correos');
$stored=json_decode($db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn(),true);
check(!array_key_exists('sinAviso',current(array_filter($stored['expedientes'],fn($x)=>$x['id']===$exp))),'La marca sinAviso no se persiste');
edit(function(&$d)use($exp){foreach($d['expedientes'] as &$x)if($x['id']===$exp){$x['estado']='Atendido';$x['historial'][]=['nota'=>'Atendido con aviso'];}});
check(mails('expedientes',$exp)===$before+1,'Sin la marca, el cambio de estado sigue notificando al solicitante');

// 5. Envío diferido: en CLI se ejecuta de inmediato y los errores no interrumpen la respuesta.
$ran=0;portal_defer(function()use(&$ran){$ran++;});portal_defer(function(){throw new RuntimeException('fallo simulado');});
check($ran===1,'portal_defer ejecuta la tarea en CLI y aísla los errores');
echo "$count comprobaciones de correcciones QA superadas.\n";
