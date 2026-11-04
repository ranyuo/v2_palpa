<?php
declare(strict_types=1);
// Cliente independiente para no acumular cuotas entre suites distintas.
$_SERVER['REMOTE_ADDR']='127.0.0.25';
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
$app=portal_app();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function edit(callable $fn):void{global $app;$b=$app->bootstrap();$d=$b['data'];$fn($d);$app->save(['data'=>$d,'revision'=>$b['revision']]);}
function rejects(callable $fn,int $status,string $message):void{try{$fn();}catch(PortalError $e){check($e->status===$status,$message.' ('.$e->getMessage().')');return;}throw new RuntimeException($message);}
function proof():array{global $app;$c=$app->challenge(['purpose'=>'certificados']);return ['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer']];}
$b=$app->bootstrap();$a=$b['data']['academico'];check(empty(PortalPolicy::find($a['uds'],'id',9001)['mod']),'Las unidades heredadas permanecen pendientes de asociación');
edit(function(&$d){foreach($d['academico']['uds'] as &$u)if($u['id']===9001)$u['mod']=9001;});
check(PortalPolicy::find($app->bootstrap()['data']['academico']['uds'],'id',9001)['mod']===9001,'Unidad asociada a su módulo del mismo programa');
rejects(fn()=>edit(function(&$d){foreach($d['academico']['uds'] as &$u)if($u['id']===9001)$u['prog']=$d['academico']['progMap']['enf'];}),422,'Se rechaza la asociación entre programas distintos');
rejects(fn()=>edit(function(&$d){$d['academico']['modulos']=array_values(array_filter($d['academico']['modulos'],fn($m)=>$m['id']!==9001));}),422,'No se elimina un módulo con unidades o documentos');
$before=PortalPolicy::find($app->bootstrap()['data']['certificados'],'codigo','INTRA-CERT-2026');
edit(function(&$d){foreach($d['academico']['modulos'] as &$m)if($m['id']===9001)$m['nombre']='Nombre actualizado del maestro';});
$after=PortalPolicy::find($app->bootstrap()['data']['certificados'],'codigo','INTRA-CERT-2026');
check($before['modulo']===$after['modulo'],'Renombrar un módulo no modifica el documento emitido');
rejects(fn()=>edit(function(&$d){foreach($d['academico']['certs'] as &$c)if($c['id']===9001)$c['num']='CODIGO-ALTERADO';}),422,'No se altera el código de una emisión histórica');
edit(function(&$d){$d['certificados'][]=['id'=>'qa-course-document','codigo'=>'QA-CURSO-2026','dni'=>'70001001','nombres'=>'Estudiante de prueba','tipo'=>'Constancia','detalle'=>'Curso de prueba independiente','fecha'=>'2026-01-30','horas'=>45,'tipoHoras'=>'Académicas','institucion'=>'IES Público Palpa','estado'=>'Válido'];});
check(PortalPolicy::find($app->bootstrap()['data']['certificados'],'codigo','QA-CURSO-2026')['grupo']==='documentos','Constancia independiente sin exigir programa ni módulo');
rejects(fn()=>edit(function(&$d){$d['certificados'][]=['id'=>'qa-bad-hours','codigo'=>'QA-HORAS-2026','dni'=>'70001001','nombres'=>'Estudiante de prueba','tipo'=>'Constancia','detalle'=>'Curso','fecha'=>'2026-01-30','horas'=>-1,'institucion'=>'IES Palpa','estado'=>'Válido'];}),422,'Se rechazan horas negativas en documentos');
rejects(fn()=>edit(function(&$d){foreach($d['certificados'] as &$r)if($r['codigo']==='QA-CURSO-2026')$r['detalle']='Cambiar la emisión anterior';}),422,'Los documentos independientes también conservan su contenido');
edit(function(&$d){foreach($d['academico']['certs'] as &$c)if($c['id']===9001)$c['estadoDoc']='Anulado';});
check(PortalPolicy::find($app->bootstrap()['data']['certificados'],'codigo','INTRA-CERT-2026')['estado']==='Anulado','La anulación de origen llega a la consulta');
edit(function(&$d){$d['academico']['certs'][]=['id'=>9101,'est'=>1001,'mod'=>9001,'fecha'=>'2026-01-30','num'=>'QA-REEMISION-2026','estadoDoc'=>'Válido'];});
check(PortalPolicy::find($app->bootstrap()['data']['certificados'],'codigo','QA-REEMISION-2026')['estado']==='Válido','Se permite reemitir con otro código conservando el documento anulado');
$app->logout();rejects(fn()=>$app->lookup('certificados',['reference'=>'70001001','mode'=>'dni']),422,'Consulta por persona requiere verificación de seguridad');
$result=$app->lookup('certificados',['reference'=>'70001001','mode'=>'dni','proof'=>proof()]);
check(count($result['records'])>=2,'La consulta reúne los documentos académicos e independientes');
$result=$app->lookup('certificados',['reference'=>'prueba estudiante','mode'=>'nombre','proof'=>proof()]);
check(count($result['records'])>=2,'Búsqueda por palabras del nombre sin depender del orden');
check(!isset($result['records'][0]['correo'],$result['records'][0]['estudianteId']),'La consulta publica únicamente los campos del reporte');
$app->login(['username'=>'secretaria','password'=>'PruebaIntranet-2026!']);
check(PortalPolicy::can($app->user(),'ix-config'),'Secretaría accede a configuración general');
edit(function(&$d){foreach($d['academico']['uds'] as &$u)if($u['id']===9001)$u['cred']=2;});
check(PortalPolicy::find($app->bootstrap()['data']['academico']['uds'],'id',9001)['cred']===2,'Secretaría mantiene maestros con el permiso compartido');
$app->logout();$app->login(['username'=>'docente','password'=>'PruebaIntranet-2026!']);
rejects(fn()=>edit(function(&$d){foreach($d['academico']['uds'] as &$u)if($u['id']===9001)$u['cred']=3;}),403,'Docente no puede modificar maestros compartidos');
$app->logout();$app->login(['username'=>'coordinador','password'=>'PruebaIntranet-2026!']);
rejects(fn()=>edit(function(&$d){$d['academico']['modulos'][]=['id'=>9201,'prog'=>$d['academico']['progMap']['enf'],'cod'=>'QA-AJENO','nombre'=>'Módulo ajeno','ciclos'=>'I'];}),403,'Coordinación no modifica maestros de otro programa');
$app->logout();echo "$count comprobaciones de configuración y documentos aprobadas.\n";
