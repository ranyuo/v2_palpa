<?php
declare(strict_types=1);
ob_start();require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base _test.');
$app=portal_app();$db=$app->db();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$data=$app->bootstrap()['data'];$template=$data['expedientes'][0];$data['expedientes']=[];
for($i=0;$i<10000;$i++)$data['expedientes'][]=[...$template,'id'=>hash('sha256','volumen-notificaciones-'.$i),'numero'=>'QA-2026-'.str_pad((string)($i+1),6,'0',STR_PAD_LEFT)];
$before=(int)$db->query('SELECT COUNT(*) FROM intranet_mail')->fetchColumn();$db->beginTransaction();
try{
 $start=microtime(true);PortalNotifications::changes($db,$data,$data,999990,[]);$elapsed=microtime(true)-$start;
 if((int)$db->query('SELECT COUNT(*) FROM intranet_mail')->fetchColumn()!==$before)throw new RuntimeException('Se generaron correos sin cambios.');
 $next=$data;$next['expedientes'][9999]['estado']='Cerrado';PortalNotifications::changes($db,$data,$next,999991,[]);
 if((int)$db->query('SELECT COUNT(*) FROM intranet_mail')->fetchColumn()!==$before+1)throw new RuntimeException('No se identificó el único cambio.');
 echo 'Análisis de 10000 expedientes sin cambios: '.round($elapsed*1000).' ms; cero avisos, y un aviso para un único cambio. Comprobaciones aprobadas.'."\n";
}finally{$db->rollBack();$app->logout();}
ob_end_flush();
