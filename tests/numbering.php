<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base _test.');
portal_app();$app=new PortalApp([...$config,'smtp'=>['enabled'=>false]]);$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$count=0;
function check(bool $v,string $m):void{global $count;if(!$v)throw new RuntimeException($m);$count++;echo "OK $m\n";}
function edit(callable $f):void{global $app;$b=$app->bootstrap();$d=$b['data'];$f($d);$app->save(['data'=>$d,'revision'=>$b['revision']]);}
function reject(callable $f,int $code=422):void{try{$f();throw new RuntimeException('Se aceptó un cambio inválido.');}catch(PortalError $e){check($e->status===$code,'Cambio no permitido rechazado con '.$code);}}
function submission():array{global $app;$c=$app->challenge(['purpose'=>'expedientes']);return ['record'=>['nombres'=>'Numeración QA','tipoPersona'=>'Persona natural','tipoDoc'=>'DNI','numDoc'=>'12345678','correo'=>'numeracion@example.test','telefono'=>'999888777','tipoTramite'=>'Solicitud','asunto'=>'Prueba de numeración configurable','folios'=>1,'areaDestino'=>'MP','archivos'=>[['original'=>'solicitud.pdf','data'=>'data:application/pdf;base64,'.base64_encode("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")]]],'idempotency_key'=>implode('-',array_map('bin2hex',[random_bytes(4),random_bytes(2),random_bytes(2),random_bytes(2),random_bytes(6)])),'proof'=>['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true]];}
$year=date('Y');$before=$app->bootstrap()['data'];$old=$before['expedientes'][0];$start=PortalNumbering::last($before,$year)+1200;
edit(function(&$d)use($year,$start){$d['config']['mpv']['prefijo']='EXP-PALPA';$d['config']['mpv']['digitos']=4;$d['config']['contadores']['MPV'][$year]=$start-1;});
$in=submission();$first=$app->submit('expedientes',$in);check($first['record']['numero']==='EXP-PALPA-'.$year.'-'.str_pad((string)$start,4,'0',STR_PAD_LEFT),'Registro usa el código y el siguiente correlativo configurados');
check($app->submit('expedientes',$in)['record']['numero']===$first['record']['numero'],'Repetir formulario no consume otro correlativo');
$second=$app->submit('expedientes',submission())['record'];check($second['numero']==='EXP-PALPA-'.$year.'-'.str_pad((string)($start+1),4,'0',STR_PAD_LEFT),'Siguiente trámite incrementa la secuencia');
check($app->lookup('expedientes',['reference'=>$old['numero'],'document'=>$old['cargoCod']])['record']['numero']===$old['numero'],'Expediente anterior mantiene número y seguimiento');
reject(fn()=>edit(function(&$d)use($year){$d['config']['contadores']['MPV'][$year]=0;}));
reject(fn()=>edit(function(&$d){$d['config']['mpv']['prefijo']='codigo con espacios';}));
reject(fn()=>edit(function(&$d){$d['config']['mpv']['digitos']=0;}));
reject(fn()=>edit(function(&$d){$d['config']['mpv']['digitos']=10;}));
reject(fn()=>edit(function(&$d)use($year){$d['config']['contadores']['MPV'][$year]=1.5;}));
reject(fn()=>edit(function(&$d)use($year){$d['config']['contadores']['MPV'][(string)((int)$year+1)]=50;}));
$example=$app->bootstrap()['data'];$example['config']['contadores']['MPV'][$year]=0;check(PortalNumbering::last($example,$year)===$start+1,'Contador recupera el máximo de expedientes existentes');
$nextYear=(string)((int)$year+1);check(str_ends_with(PortalNumbering::next($example,$nextYear),'-0001'),'Nuevo año inicia una secuencia independiente');
edit(function(&$d){$d['config']['mpv']['prefijo']='OTRO';$d['config']['mpv']['digitos']=1;});
$stale=$app->bootstrap();$third=$app->submit('expedientes',submission())['record'];check($third['numero']==='OTRO-'.$year.'-'.($start+2),'Cambiar código no reinicia ni trunca correlativos');
reject(fn()=>$app->save(['data'=>$stale['data'],'revision'=>$stale['revision']]),409);
$app->logout();$app->login(['username'=>'atencion','password'=>'PruebaIntranet-2026!']);reject(fn()=>edit(function(&$d){$d['config']['mpv']['prefijo']='INTRUSION';}),403);
$app->logout();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);edit(function(&$d)use($before){$d['config']['mpv']['prefijo']=$before['config']['mpv']['prefijo'];$d['config']['mpv']['digitos']=$before['config']['mpv']['digitos']??6;});$app->logout();echo "$count comprobaciones de numeración aprobadas.\n";ob_end_flush();
