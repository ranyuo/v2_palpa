<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
try {
    $result=portal_app()->processMail(10);
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
    if(($result['configured']??true)===false||$result['failed']>0)exit(1);
} catch(Throwable $error) {
    fwrite(STDERR,"No se pudo procesar la cola de correo. Revisa configuración y actualización de la base.\n");exit(1);
}
