<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/Portal.php';
$data=PortalPolicy::defaults()['data']??json_decode(file_get_contents(dirname(__DIR__).'/portal-private/data/portal.json'),true,flags:JSON_THROW_ON_ERROR);
$a=&$data['academico'];$a['cfg']['periodo']='2026-II';
$a['estudiantes']=[['id'=>1,'prog'=>1,'ciclo'=>'VI','sec'=>'B','turno'=>'Tarde'],['id'=>2,'prog'=>2,'ciclo'=>'VI','sec'=>'B','turno'=>'Tarde']];
$a['sesiones']=[];
foreach(['2026-II','2026-I','2025-II'] as $i=>$period)$a['sesiones'][]=['id'=>$i+1,'per'=>$period,'prog'=>1,'ciclo'=>'VI','sec'=>'B','turno'=>'Tarde','doc'=>7];
$a['sesiones'][]=['id'=>4,'per'=>'2026-II','prog'=>2,'ciclo'=>'VI','sec'=>'B','turno'=>'Tarde','doc'=>8];
$a['publicados']=['1|VI|B|Tarde|2026-II'=>['fecha'=>'2026-11-04']];
$public=['academico'=>[]];$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "OK $message\n";}
function sessions(array $user):array{global $data,$public;return array_column(PortalPolicy::project($data,$user,$public)['academico']['sesiones']??[],'id');}
foreach(['estudiante','egresado'] as $role){
    check(sessions(['rol'=>$role,'vin'=>'est:1','ixId'=>1])===($role==='estudiante'?[1]:[]),$role.': respeta período publicado y permisos de horario');
    check(sessions(['rol'=>$role,'vin'=>'est:2','ixId'=>2])===[],$role.': otro grupo sin publicación no ve sesiones');
    check(sessions(['rol'=>$role,'vin'=>'','ixId'=>3])===[],$role.': sin vínculo no ve sesiones');
}
$a['publicados']=[];
check(sessions(['rol'=>'estudiante','vin'=>'est:1','ixId'=>1])===[],'Retirar publicación oculta el horario al estudiante');
check(PortalPolicy::project($data,null,$public)===$public,'Visitante solo recibe la proyección pública');
echo "$count comprobaciones de aislamiento aprobadas.\n";
