<?php
declare(strict_types=1);
require_once __DIR__.'/Content.php';

final class PortalSchema
{
    public const VERSION = 5;
    private const TABLES = ['state','users','rate_limits','schema','visits','files','audit','settings','upgrade_backups'];
    /** Renombra una sola vez las tablas heredadas pisco_* a intranet_*, conservando datos e índices. */
    public static function migrateLegacyTables(PDO $db): bool
    {
        $existing=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'pisco\\_%' OR TABLE_NAME LIKE 'intranet\\_%')")->fetchAll(PDO::FETCH_COLUMN);
        if(in_array('intranet_state',$existing,true)||!in_array('pisco_state',$existing,true))return false;
        $pairs=[];foreach(self::TABLES as $table)if(in_array('pisco_'.$table,$existing,true)&&!in_array('intranet_'.$table,$existing,true))$pairs[]='pisco_'.$table.' TO intranet_'.$table;
        // RENAME TABLE es atómico; si otra petición lo completó primero, basta con que exista la tabla nueva.
        try{$db->exec('RENAME TABLE '.implode(', ',$pairs));}catch(PDOException $error){if(!$db->query("SHOW TABLES LIKE 'intranet\\_state'")->fetchColumn())throw $error;}
        return true;
    }
    public static function apply(PDO $db): void
    {
        foreach (explode(';', file_get_contents(__DIR__.'/schema.sql')) as $sql) if (trim($sql) !== '') $db->exec($sql);
        $columns = array_column($db->query('SHOW COLUMNS FROM intranet_users')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        foreach (['role' => "VARCHAR(30) NOT NULL DEFAULT 'admin'", 'profile' => 'JSON NULL',
            'auth_version' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'login_failures' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'locked_until' => 'DATETIME NULL', 'last_login' => 'DATETIME NULL'] as $name => $type) {
            if (!in_array($name, $columns, true)) $db->exec('ALTER TABLE intranet_users ADD COLUMN '.$name.' '.$type);
        }
        $imported=$db->query("SELECT setting_value FROM intranet_settings WHERE setting_key='legacy_audit_imported'")->fetchColumn();
        $state=$db->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn();
        if(!$imported&&$state){
            $legacy=json_decode($state,true,flags:JSON_THROW_ON_ERROR)['auditoria']??[];
            $insert=$db->prepare('INSERT INTO intranet_audit (occurred_at,user_id,username,role,action,entity,record_key,details) VALUES (?,NULL,?,?,?,?,?,?)');
            $ownTransaction=!$db->inTransaction();if($ownTransaction)$db->beginTransaction();
            try{foreach($legacy as $row){if(!is_array($row))continue;$stamp=strtotime((string)($row['fecha']??''));$insert->execute([date('Y-m-d H:i:s',$stamp?:time()),substr((string)($row['usuario']??'historial'),0,80),substr((string)($row['rol']??''),0,30),substr((string)($row['accion']??'Historial anterior'),0,80),substr((string)($row['entidad']??'Portal'),0,100),substr((string)($row['registro']??''),0,250),json_encode(['origen'=>'Historial previo','detalle'=>$row['detalle']??''],JSON_THROW_ON_ERROR)]);}
                $db->exec("INSERT INTO intranet_settings (setting_key,setting_value) VALUES ('legacy_audit_imported','true') ON DUPLICATE KEY UPDATE setting_value='true'");if($ownTransaction)$db->commit();
            }catch(Throwable $e){if($ownTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
        }
        // Completa códigos de verificación de reclamos anteriores sin alterar su seguimiento.
        $ownTransaction=!$db->inTransaction();if($ownTransaction)$db->beginTransaction();
        try {
            $row=$db->query('SELECT data FROM intranet_state WHERE id=1 FOR UPDATE')->fetch();
            if($row){$data=json_decode($row['data'],true,flags:JSON_THROW_ON_ERROR);$changed=false;$used=[];
                foreach($data['reclamos']??[] as $claim)if(!empty($claim['cargoCod']))$used[strtoupper($claim['cargoCod'])]=true;
                if(isset($data['reclamos']))foreach($data['reclamos'] as &$claim)if(empty($claim['cargoCod'])){do{$code=strtoupper(bin2hex(random_bytes(8)));}while(isset($used[$code]));$claim['cargoCod']=$code;$used[$code]=true;$changed=true;}unset($claim);
                $normalized=PortalContent::normalize($data);$changed=$changed||$normalized!==$data;$data=$normalized;
                if($changed)$db->prepare('UPDATE intranet_state SET data=?,revision=revision+1 WHERE id=1')->execute([json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            }
            if($ownTransaction)$db->commit();
        }catch(Throwable $error){if($ownTransaction&&$db->inTransaction())$db->rollBack();throw $error;}
        $db->prepare('INSERT INTO intranet_schema (id,version) VALUES (1,?) ON DUPLICATE KEY UPDATE version = VALUES(version)')->execute([self::VERSION]);
    }
}
