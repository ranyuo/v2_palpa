<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/Content.php';
$areas=[
 ['id'=>1,'cod'=>'MP','nivel'=>'Oficina','nombre'=>'Mesa de Partes','activo'=>true],
 ['id'=>2,'cod'=>'DG','nivel'=>'Oficina','nombre'=>'Dirección','activo'=>true],
 ['id'=>3,'cod'=>'SA','nivel'=>'Oficina','nombre'=>'Secretaría','activo'=>true],
 ['id'=>4,'cod'=>'QA-A','nivel'=>'Área','nombre'=>'Área QA','activo'=>true],
 ['id'=>5,'cod'=>'QA-U','nivel'=>'Unidad','nombre'=>'Unidad QA','activo'=>false],
 ['id'=>6,'cod'=>'QA-P','nivel'=>'Programa','nombre'=>'Programa QA','activo'=>true],
 ['id'=>7,'cod'=>'QA-C','nivel'=>'Comité especial','nombre'=>'Comité QA','activo'=>true],
 ['id'=>8,'cod'=>'QA-S','nombre'=>'Sin clasificación','activo'=>true],
];
$data=['academico'=>['areas'=>$areas,'tramites'=>[['id'=>1,'area'=>'MP']]],'expedientes'=>[['id'=>'qa','area'=>'SA','areaDestino'=>'MP','estado'=>'Derivado']]];
$next=PortalContent::normalize($data);$count=0;
function check(bool $result,string $message):void{global $count;if(!$result)throw new RuntimeException($message);$count++;}
check(array_column($next['academico']['areas'],'nivel')===['Mesa de Partes','Dirección','Áreas','Áreas','Unidades','Coordinaciones de programas de estudio','Comité especial','Áreas'],'Se normalizan niveles heredados y se conserva el personalizado');
foreach($areas as $i=>$area){unset($area['nivel']);$actual=$next['academico']['areas'][$i];unset($actual['nivel']);check($actual===$area,'Identidad, nombre y estado del área '.$i.' conservados');}
check($next['expedientes']===$data['expedientes'],'Expedientes y derivaciones no se alteran');
check($next['academico']['tramites']===$data['academico']['tramites'],'Trámites conservan sus referencias');
check(PortalContent::normalize($next)===$next,'La normalización es idempotente');
$seed=json_decode(file_get_contents(dirname(__DIR__).'/portal-private/data/portal.json'),true,flags:JSON_THROW_ON_ERROR);
check(count($seed['academico']['areas'])===9,'La instalación nueva conserva las nueve áreas institucionales');
check(PortalContent::normalize($seed)===$seed,'La instalación nueva ya usa niveles compatibles');
echo "$count comprobaciones de compatibilidad de áreas aprobadas.\n";
