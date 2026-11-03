<?php
declare(strict_types=1);

/** Alcance de app: gestión complementaria; la web pública se administra por separado. */
final class IntranetScope
{
    public const MODULES = ['dashboard','perfil','programas','docentes','cvdocente','ix-alumnos','ix-horarios','ix-certificados','ix-titulos','ix-bolsa','ix-config','certificados','reclamos','expedientes','config','usuarios','auditoria','respaldo'];
    private const EDITORIAL = ['slides','enlaces','reconocimientos','jerarquica','egresados','categoriasNoticia','noticias','eventos','tiposDocumento','documentos','tupa','tupaDocs','estadisticas','licenciamiento','inversiones','convocatorias','admisiones','matriculas','tiposBeca','becasPeriodos','becarios','locales','servicios','mensajes','suscriptores'];
    private const SETTINGS = ['inst','mpv','lr','bolsa','limites','contadores','correoReclamos','enlacesV2'];

    public static function project(array $data): array
    {
        foreach(self::EDITORIAL as $key)if(isset($data[$key]))$data[$key]=[];
        // Textos legales y descripción del directorio respaldan únicamente servicios complementarios.
        $data['paginas']=array_values(array_filter($data['paginas']??[],fn($p)=>in_array($p['clave']??'',['privacidad','terminos','docentes'],true)));
        return $data;
    }
    public static function assertChanges(array $visible,array $incoming): void
    {
        foreach($incoming as $key=>$value)if(!in_array($key,['programas','personas','academico','reclamos','expedientes','certificados','config','usuarios','auditoria','visitas','correos','__v'],true)&&!PortalPolicy::equal($value,$visible[$key]??null))throw new PortalError(403,'Colección fuera del alcance de la intranet.');
        foreach([...self::EDITORIAL,'paginas'] as $key)if(!PortalPolicy::equal($incoming[$key]??null,$visible[$key]??null))throw new PortalError(403,'La web institucional se administra por separado. Este módulo no pertenece a la intranet.');
        foreach($incoming['config']??[] as $key=>$value)if(!in_array($key,self::SETTINGS,true)&&!PortalPolicy::equal($value,$visible['config'][$key]??null))throw new PortalError(403,'Ese parámetro pertenece al portal web y no se administra desde la intranet.');
    }
    public static function roles(array $roles): array
    {
        foreach($roles as &$role)$role['mods']=array_values(array_intersect($role['mods'],self::MODULES));
        unset($role);return $roles;
    }
}
