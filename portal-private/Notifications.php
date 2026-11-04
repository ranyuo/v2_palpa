<?php
declare(strict_types=1);

/** Eventos confirmados del servidor; nunca acepta destinatarios enviados por el navegador. */
final class PortalNotifications
{
    private static function emit(PDO $db,string $module,array $row,string $event,string $version,array $recipients,string $reference,string $subject,string $body): void
    {
        foreach(array_unique(array_map(fn($s)=>strtolower(trim((string)$s)),$recipients)) as $to){
            if($to===''||strlen($to)>254||!filter_var($to,FILTER_VALIDATE_EMAIL))continue;
            PortalMail::queue($db,$module,(string)$row['id'],$event,$version,$to,$reference,$subject,$body);
        }
    }
    public static function received(PDO $db,string $module,array $row,array $data): void
    {
        $inst=$data['config']['inst']['nombre'];
        if($module==='reclamos'){
            $ref=$row['codigo'];
            self::emit($db,$module,$row,'registro','registro',[$row['correo']],$ref,'Constancia de registro '.$ref,
                "Su reclamo o queja fue registrado en $inst.\nCódigo: $ref\nFecha: {$row['fecha']}\nCódigo de verificación: {$row['cargoCod']}\nPlazo: {$row['plazoDias']} días hábiles.\nVencimiento: {$row['vence']}\nConserve su constancia y consulte el seguimiento en el Libro de Reclamaciones.\n");
            $staff=[$data['config']['lr']['correo']??'',$data['config']['correoReclamos']??''];
            // Si coincide con el reclamante, la constancia ya cubre ese destinatario.
            $staff=array_filter($staff,fn($to)=>strcasecmp(trim($to),trim($row['correo']))!==0);
            self::emit($db,$module,$row,'registro','registro',$staff,$ref,'Nuevo reclamo o queja '.$ref,
                "Se registró un reclamo o queja en $inst.\nCódigo: $ref\nVencimiento: {$row['vence']}\nIngrese a la intranet para revisar el registro y sus adjuntos privados.\n");
        }
        if($module==='empresas')self::emit($db,$module,$row,'registro','registro',[$row['correo'],$data['config']['bolsa']['correo']??''],'BOL-EMP-'.$row['id'],'Empresa registrada: pendiente de validación',
            "La empresa {$row['nom']} fue registrada en la Bolsa Laboral de $inst.\nEstado: Pendiente de validación institucional.\nIngrese a Bolsa Laboral para consultar el resultado. Este mensaje no habilita la publicación de ofertas.\n");
    }
    private static function student(array $data,mixed $id,array $accounts): string
    {
        // Se prioriza el correo de la cuenta activa vinculada: el alumno no puede redirigirlo editando el perfil.
        $bound=false;foreach($accounts as $u)if(($u['vin']??'')==='est:'.$id){$bound=true;if(($u['estado']??'Activo')==='Activo'&&!empty($u['correo']))return $u['correo'];}
        if($bound)return '';
        return PortalPolicy::find($data['academico']['estudiantes'],'id',$id)['correo']??'';
    }
    public static function changes(PDO $db,array $old,array $data,int $revision,array $accounts): void
    {
        $version='revision:'.$revision;$a=$data['academico'];
        foreach(['expedientes','reclamos'] as $module)foreach($data[$module] as $row){
            $before=PortalPolicy::find($old[$module],'id',$row['id']);if(!$before||($row['deleted']??false))continue;
            $stateChanged=($before['estado']??'')!==($row['estado']??'')||($before['anulado']??false)!==($row['anulado']??false);
            $areaChanged=($before['area']??'')!==($row['area']??'');
            $answered=($before['respuesta']??'')!==($row['respuesta']??'')&&trim($row['respuesta']??'')!=='';
            if(!$stateChanged&&!$areaChanged&&!$answered)continue;
            $ref=$row['numero']??$row['codigo'];$state=($row['anulado']??false)?'Anulado':$row['estado'];
            $area=PortalPolicy::find($a['areas'],'cod',$row['area']??'');
            $body="Referencia: $ref\nEstado: $state\n".($areaChanged?'Área de atención: '.($area['nombre']??$row['area'])."\n":'');
            self::emit($db,$module,$row,'seguimiento',$version,[$row['correo']],$ref,'Actualización de su solicitud '.$ref,
                $body.($answered?"Respuesta institucional:\n{$row['respuesta']}\n":'')."Consulte el seguimiento en el servicio correspondiente.\n");
            if($areaChanged&&!empty($area['correo'])&&strcasecmp($area['correo'],$row['correo'])!==0)self::emit($db,$module,$row,'seguimiento',$version,[$area['correo']],$ref,'Solicitud derivada '.$ref,$body."Ingrese a la intranet para revisar los documentos y la información privada.\n");
        }
        foreach($a['empresas'] as $row){
            $before=PortalPolicy::find($old['academico']['empresas'],'id',$row['id']);
            if(!$before||($row['deleted']??false))continue;
            if(($row['validada']??false)===($before['validada']??false)&&($row['estadoVal']??'Pendiente')===($before['estadoVal']??'Pendiente'))continue;
            $state=($row['validada']??false)?'Aprobada':($row['estadoVal']??'Pendiente');
            self::emit($db,'empresas',$row,'validacion',$version,[$row['correo']],'BOL-EMP-'.$row['id'],'Validación de empresa: '.$state,
                "Empresa: {$row['nom']}\nEstado: $state\n".(!empty($row['motivoRech'])?"Motivo: {$row['motivoRech']}\n":'')."Ingrese a Bolsa Laboral para consultar su cuenta. No comparta su contraseña.\n");
        }
        foreach($a['ofertas'] as $row){
            $before=PortalPolicy::find($old['academico']['ofertas'],'id',$row['id']);
            if(($row['deleted']??false)||($before&&($row['estado']??'')===($before['estado']??'')))continue;
            $company=PortalPolicy::find($a['empresas'],'id',$row['emp']);
            $to=[$company['correo']??''];if(($row['estado']??'')==='Por validar')$to[]=$data['config']['bolsa']['correo']??'';
            if(in_array($row['estado']??'',['Cerrada','Rechazada'],true))foreach($a['postulaciones'] as $post)if((string)$post['of']===(string)$row['id'])$to[]=self::student($data,$post['est'],$accounts);
            self::emit($db,'ofertas',$row,'estado',$version,$to,'BOL-OF-'.$row['id'],'Oferta laboral: '.$row['estado'],
                "Puesto: {$row['puesto']}\nEstado de la oferta: {$row['estado']}\nFecha límite: {$row['limite']}\nReferencia: BOL-OF-{$row['id']}\nConsulte los detalles y las observaciones en Bolsa Laboral.\n");
        }
        foreach($a['postulaciones'] as $row){
            $before=PortalPolicy::find($old['academico']['postulaciones'],'id',$row['id']);
            if(($row['deleted']??false)||($before&&($row['estado']??'')===($before['estado']??'')))continue;
            $offer=PortalPolicy::find($a['ofertas'],'id',$row['of']);$company=PortalPolicy::find($a['empresas'],'id',$offer['emp']??null);
            $to=[self::student($data,$row['est'],$accounts),$company['correo']??''];
            if(!$before)$to[]=$data['config']['bolsa']['correo']??'';
            self::emit($db,'postulaciones',$row,$before?'estado':'registro',$version,$to,'BOL-POST-'.$row['id'],$before?'Estado de postulación: '.$row['estado']:'Postulación registrada',
                'Puesto: '.($offer['puesto']??'Oferta laboral')."\nReferencia: BOL-POST-{$row['id']}\nEstado: {$row['estado']}\nIngrese a Bolsa Laboral para consultar su postulación o revisar candidatos según sus permisos. No se adjuntan currículos por correo.\n");
        }
    }
    public static function exists(array $data,array $mail): bool
    {
        $module=$mail['source_type']??'expedientes';$id=$mail['source_id']?:$mail['record_id'];
        $rows=in_array($module,['expedientes','reclamos'],true)?($data[$module]??[]):($data['academico'][$module]??[]);
        $row=PortalPolicy::find($rows,'id',$id);return $row!==null&&!($row['deleted']??false);
    }
}
