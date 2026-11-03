<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
$count=0;
function ok(bool $v,string $m):void{global $count;if(!$v)throw new RuntimeException($m);$count++;echo "OK $m\n";}
function tables(PDO $db,string $prefix):array{return $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '".$prefix."\\_%'")->fetchAll(PDO::FETCH_COLUMN);}
function snapshot(PDO $db):array{return ['users'=>(int)$db->query('SELECT COUNT(*) FROM intranet_users')->fetchColumn(),'files'=>(int)$db->query('SELECT COUNT(*) FROM intranet_files')->fetchColumn(),'audit'=>(int)$db->query('SELECT COUNT(*) FROM intranet_audit')->fetchColumn(),'state'=>hash('sha256',(string)$db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn())];}

$app=portal_app();$db=$app->db();
if(!$db->query("SHOW TABLES LIKE 'intranet\\_state'")->fetchColumn())throw new RuntimeException('Ejecuta antes tests/intranet.php.');
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);

// Convierte un respaldo actual al formato anterior (pisco-backup, tablas pisco_*) con su hash recalculado.
$backup=PortalBackups::create($app,'manual');$current=PortalBackups::path($backup['id']);
$legacyId=bin2hex(random_bytes(16));$legacy=PortalBackups::directory().'/'.$legacyId.'.piscobak';
$in=gzopen($current,'rb');$out=gzopen($legacy,'wb6');$hash=hash_init('sha256');
while(($line=gzgets($in,67108866))!==false){$item=json_decode($line,true,flags:JSON_THROW_ON_ERROR);
    if(isset($item['format']))$item['format']='pisco-backup';
    if(isset($item['table']))$item['table']=str_replace('intranet_','pisco_',$item['table']);
    if(isset($item['end'])){$item['counts']=array_combine(array_map(fn($k)=>str_replace('intranet_','pisco_',$k),array_keys($item['counts'])),array_values($item['counts']));$item['sha256']=hash_final($hash);}
    $line=json_encode($item,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";if(!isset($item['end']))hash_update($hash,$line);gzwrite($out,$line);}
gzclose($in);gzclose($out);unlink($current);
$before=snapshot($db);

// Simula una instalación anterior: tablas pisco_*.
$db->exec('RENAME TABLE '.implode(', ',array_map(fn($t)=>$t.' TO '.str_replace('intranet_','pisco_',$t),tables($db,'intranet'))));
ok(tables($db,'intranet')===[]&&in_array('pisco_state',tables($db,'pisco'),true),'Base preparada con tablas heredadas pisco_*');

$app=portal_app();$db=$app->db();
ok(tables($db,'pisco')===[],'La primera conexión renombra todas las tablas pisco_*');
ok(snapshot($db)===$before,'Cuentas, contenido, archivos y auditoría se conservan tras renombrar');
ok(PortalSchema::migrateLegacyTables($db)===false,'La migración no se repite cuando ya existen las tablas nuevas');
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);ok($app->bootstrap()['user']['rol']==='admin','El administrador inicia sesión después de la migración');

ok(in_array($legacyId,array_column(PortalBackups::listing(),'id'),true),'El respaldo .piscobak anterior aparece en la lista');
PortalBackups::restore($app,PortalBackups::path($legacyId));
ok(snapshot($db)['users']===$before['users']&&snapshot($db)['files']===$before['files'],'El respaldo con formato anterior se restaura en las tablas nuevas');
unlink(PortalBackups::path($legacyId));
$fresh=PortalBackups::create($app,'manual');ok(str_ends_with(PortalBackups::path($fresh['id']),'.intranetbak')&&$fresh['format']==='intranet-backup','Los respaldos nuevos usan el formato intranet');
unlink(PortalBackups::path($fresh['id']));
echo "$count comprobaciones de migración de nombres aprobadas.\n";
