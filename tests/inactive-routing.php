<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
$path=getenv('PORTAL_CONFIG_FILE');
if(!$path||!str_ends_with($path,'config.test.php'))throw new RuntimeException('Solo config.test.php.');
$config=require $path;if(!str_ends_with($config['db_name'],'_test'))throw new RuntimeException('Solo base desechable _test.');
$app=portal_app();$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);
$boot=$app->bootstrap();$data=$boot['data'];
if(!PortalPolicy::find($data['academico']['areas'],'cod','QA-INACTIVA'))$data['academico']['areas'][]=['id'=>960001,'cod'=>'QA-INACTIVA','nivel'=>'Áreas','nombre'=>'Área inactiva QA','resp'=>'Prueba QA','correo'=>'inactiva@example.test','activo'=>false];
$app->save(['data'=>$data,'revision'=>$boot['revision']]);
$ids=array_column(array_slice($data['expedientes'],0,3),'id');
if(count($ids)!==3)throw new RuntimeException('Ejecuta antes tests/browser-areas.cjs.');
$count=0;
foreach($ids as $id){
    $boot=$app->bootstrap();$next=$boot['data'];foreach($next['expedientes'] as &$row)if($row['id']===$id){$row['area']='QA-INACTIVA';$row['historial'][]=['nota'=>'Intento de derivación QA a destino inactivo.'];}unset($row);
    try{$app->save(['data'=>$next,'revision'=>$boot['revision']]);throw new RuntimeException('Se aceptó derivar a un área inactiva.');}catch(PortalError $e){if($e->status!==422)throw $e;$count++;}
}
$app->logout();
for($i=1;$i<=3;$i++){
    $c=$app->challenge(['purpose'=>'expedientes']);$proof=['challenge_id'=>$c['id'],'answer'=>$_SESSION['challenges'][$c['id']]['answer'],'accept'=>true,'declare'=>true];
    $record=['nombres'=>'QA Destino inactivo '.$i,'tipoPersona'=>'Persona natural','tipoDoc'=>'DNI','numDoc'=>'9600000'.$i,'correo'=>'inactiva'.$i.'@example.test','telefono'=>'90000000'.$i,'tipoTramite'=>'Solicitud','asunto'=>'Solicitud de prueba QA','folios'=>1,'areaDestino'=>'QA-INACTIVA','archivos'=>[['original'=>'prueba.pdf','data'=>'data:application/pdf;base64,'.base64_encode("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")]]];
    try{$app->submit('expedientes',['record'=>$record,'idempotency_key'=>vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4)),'proof'=>$proof]);throw new RuntimeException('Se aceptó un envío a un área inactiva.');}catch(PortalError $e){if($e->status!==422||!str_contains($e->getMessage(),'activa'))throw $e;$count++;}
}
echo "$count comprobaciones de rechazo de destinos inactivos aprobadas.\n";
