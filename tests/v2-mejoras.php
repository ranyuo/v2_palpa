<?php
declare(strict_types=1);
// Mejoras llevadas desde el sistema v2: retención de la auditoría, recorte de textos largos, validaciones de tipo y forma.
// Ejecutar después de tests/intranet.php, sobre la misma base desechable _test.
$_SERVER['REMOTE_ADDR']='127.0.0.31';
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
$app=portal_app();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$db=$app->db();$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function rejects(callable $fn,int $status,string $message,string $text=''):void{try{$fn();}catch(PortalError $e){check($e->status===$status&&($text===''||str_contains($e->getMessage(),$text)),$message.' ('.$e->getMessage().')');return;}throw new RuntimeException($message.': no se rechazó');}
function edit(callable $fn):void{global $app;$b=$app->bootstrap();$d=$b['data'];$fn($d);$app->save(['data'=>$d,'revision'=>$b['revision']]);}

// Retención de la auditoría: solo se borra lo anterior a la ventana configurada, como máximo una vez al día.
$db->exec("DELETE FROM intranet_settings WHERE setting_key='audit_purged_day'");
$insert=$db->prepare("INSERT INTO intranet_audit (occurred_at,user_id,username,role,action,entity,record_key,details) VALUES (?,NULL,'qa','publico','QA retención','QA','',?)");
$now=new DateTimeImmutable('2026-11-09 12:00:00');
foreach(['2024-11-08 11:59:59','2024-11-09 12:00:01','2026-11-01 00:00:00'] as $stamp)$insert->execute([$stamp,'{}']);
$kept=fn()=>$db->query("SELECT occurred_at FROM intranet_audit WHERE action='QA retención' ORDER BY occurred_at")->fetchAll(PDO::FETCH_COLUMN);
$before=(int)$db->query('SELECT COUNT(*) FROM intranet_audit')->fetchColumn();
check(PortalAudit::purge($app,$now)>=1,'La depuración borra la auditoría de más de 24 meses');
check(count($kept())===2&&str_starts_with($kept()[0],'2024-11-09'),'La depuración conserva lo que está dentro de la ventana');
check((int)$db->query("SELECT COUNT(*) FROM intranet_audit WHERE action='Depurar auditoría'")->fetchColumn()===1,'La depuración queda registrada en la propia auditoría');
$insert->execute(['2020-01-01 00:00:00','{}']);
check(PortalAudit::purge($app,$now)===null&&count($kept())===3,'La depuración corre como máximo una vez al día');
check(PortalAudit::purge($app,$now->modify('+1 day'))>=2&&count($kept())===1,'Al día siguiente vuelve a correr con el corte desplazado');
check((int)$db->query('SELECT COUNT(*) FROM intranet_audit')->fetchColumn()<=$before+1,'La depuración no hace crecer la tabla');
foreach([[0,null],[3,6],[36,36]] as [$months,$expected]){
    $probe=new PortalApp([...$config,'audit_retention_months'=>$months]);
    if($expected===null)check(PortalAudit::purge($probe,$now->modify('+5 days'))===null,'Retención 0 conserva toda la auditoría');
    else check($probe->auditRetentionMonths()===$expected,"Retención de $months meses se interpreta como $expected");
}
rejects(fn()=>(new PortalApp(['audit_retention_months'=>'24']))->auditRetentionMonths(),503,'Retención no entera rechazada');

// Textos iniciales más largos que el formulario: se recortan al normalizar para que el registro pueda volver a guardarse.
$limit=null;foreach(PortalPolicy::defaults()['specs'] as $spec)if($spec['collection']==='programas')foreach($spec['fields'] as $f)if($f['name']==='resumen')$limit=$f['max'];
check(is_int($limit),'El formulario de programas declara un límite para el resumen');
$long=['programas'=>[['id'=>'qa-largo','resumen'=>str_repeat('Formación técnica profesional ',40)]]];
$fit=PortalContent::normalize($long)['programas'][0]['resumen'];
check(mb_strlen($fit)<=$limit&&str_ends_with($fit,'…'),'El resumen largo se recorta al límite y termina en «…»');
$html=['programas'=>[['id'=>'qa-html','resumen'=>'<p>'.str_repeat('x',$limit+20).'</p>']]];
check(PortalContent::normalize($html)['programas'][0]['resumen']===$html['programas'][0]['resumen'],'Los textos con HTML no se recortan');

// Forma de las colecciones: listas con identificadores únicos.
$state=$app->bootstrap()['data'];
rejects(fn()=>PortalRules::stateShape($state,[...$state,'programas'=>['a'=>['id'=>'x']]]),422,'Una colección que deja de ser lista se rechaza','lista');
$dup=$state;$dup['personas'][]=$dup['personas'][0];
rejects(fn()=>PortalRules::stateShape($state,$dup),422,'Un identificador duplicado se rechaza','duplicado');
rejects(fn()=>edit(function(&$d){$d['academico']['estudiantes']=['x'=>$d['academico']['estudiantes'][0]];}),422,'El servidor rechaza un catálogo académico que no es lista');

// Tipos de dato y grupos académicos.
rejects(fn()=>edit(function(&$d){$d['academico']['estudiantes'][0]['nom']=12345;}),422,'Un nombre numérico se rechaza como tipo de dato no válido','Tipo de dato');
rejects(fn()=>edit(function(&$d){$d['academico']['estudiantes'][0]['sec']='b';}),422,'Una sección en minúscula se rechaza','sección');
rejects(fn()=>edit(function(&$d){$d['academico']['estudiantes'][0]['ciclo']='VII';}),422,'Un ciclo fuera de I a VI se rechaza','ciclo');
rejects(fn()=>edit(function(&$d){$d['academico']['estudiantes'][0]['periodo']='2026-3';}),422,'Un periodo de ingreso mal escrito se rechaza','periodo');
edit(function(&$d){$d['academico']['estudiantes'][0]['sec']='C';});
check($app->bootstrap()['data']['academico']['estudiantes'][0]['sec']==='C','Palpa admite secciones con otras letras (C)');

echo "$count comprobaciones de mejoras v2 aprobadas.\n";
