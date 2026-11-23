<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
portal_headers();
$error = '';
$input = [];
$baseLabels = [
    'secretaria'=>'Secretaría académica',
    'directivo'=>'Dirección',
    'coordinador'=>'Programa de estudios',
    'atencion'=>'Atención institucional',
    'area'=>'Área o unidad administrativa',
];

try {
    $app = portal_app();
    $actor = $app->requireAdmin();
    if (!empty($actor['cambioClave'])) throw new PortalError(403, 'Cambia tu contraseña temporal para administrar los roles.');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $app->checkCsrf(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : '');
        $input = [
            'role'=>$_POST['role'] ?? '',
            'label'=>$_POST['label'] ?? '',
            'base'=>$_POST['base'] ?? '',
            'mods'=>$_POST['mods'] ?? [],
            'write'=>$_POST['write'] ?? [],
            'imports'=>[],
            'expected'=>json_decode(is_string($_POST['expected'] ?? null) ? $_POST['expected'] : 'null',true),
            'reset'=>($_POST['action'] ?? '') === 'reset',
        ];
        if (!is_array($_POST['imports'] ?? [])) throw new PortalError(422, 'Permisos de importación no válidos.');
        foreach ($_POST['imports'] ?? [] as $module=>$level) {
            if (!in_array($level, ['0','1','2'], true)) throw new PortalError(422, 'Permiso de importación no válido.');
            if ($level !== '0') $input['imports'][$module] = (int)$level;
        }
        $app->saveRole($input);
        $_SESSION['roles_notice'] = 'Rol guardado. Sus usuarios deberán iniciar sesión de nuevo.';
        header('Location: /roles.php?role='.rawurlencode($input['role']),true,303);
        exit;
    }
} catch (Throwable $exception) {
    if (!$exception instanceof PortalError || !in_array($exception->status,[409,422],true)) portal_error($exception);
    http_response_code($exception->status);
    $error = $exception->getMessage();
}

$notice = $_SESSION['roles_notice'] ?? '';
unset($_SESSION['roles_notice']);
$overrides = $app->roleSettings();
$definitions = PortalPolicy::definitions();
$roles = $definitions['roles'];
$key = $input['role'] ?? $_GET['role'] ?? 'secretaria';
$key = is_string($key) && isset($roles[$key]) ? $key : 'secretaria';
$requestedBase = ($input['base'] ?? '') ?: ($_GET['base'] ?? 'secretaria');
$creating = isset($_GET['nuevo']);
$base = $creating
    ? (is_string($requestedBase) && isset($baseLabels[$requestedBase]) ? $requestedBase : 'secretaria')
    : ($roles[$key]['base'] ?? $key);
$custom = $creating || isset($roles[$key]['base']);
$role = $creating ? ['label'=>'','mods'=>[]] : $roles[$key];
if ($input && $error) $role = array_replace($role, $input);
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Roles y permisos | Intranet Palpa</title><link rel="stylesheet" href="/forms.css">
<style>
main{max-width:1000px}nav{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}nav a{padding:8px 10px;border:1px solid #ccd6e5;border-radius:8px}nav a[aria-current]{background:#102447;color:white}fieldset{margin:20px 0;padding:16px;border:1px solid #ccd6e5}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #d3dce8;text-align:left}input[type=checkbox]{width:auto}select{max-width:100%;padding:6px}.scopes{display:flex;flex-wrap:wrap;gap:12px}
</style></head>
<body><main><a href="/#/admin/usuarios">← Volver a Usuarios y roles</a><h1>Roles y permisos</h1>
<?php if ($notice): ?><p class="notice success" role="status"><?=e($notice)?></p><?php endif ?>
<?php if ($error): ?><p class="notice" role="alert"><?=e($error)?></p><?php endif ?>
<nav aria-label="Seleccione un rol">
<a href="?nuevo=1" <?=$creating?'aria-current="page"':''?>>Crear perfil</a>
<?php foreach ($roles as $id=>$definition): ?>
<a href="?role=<?=e($id)?>" <?=!$creating&&$id===$key?'aria-current="page"':''?>><?=e($definition['label'])?></a>
<?php endforeach ?>
</nav>
<?php if ($creating): ?>
<h2>Crear perfil</h2>
<p>Selecciona un ámbito base. Los roles de programa o área usan el vínculo configurado en cada cuenta.</p>
<div class="scopes"><?php foreach ($baseLabels as $id=>$label): ?>
<a href="?nuevo=1&amp;base=<?=e($id)?>" <?=$id===$base?'aria-current="page"':''?>><?=e($label)?></a>
<?php endforeach ?></div>
<?php else: ?>
<h2><?=e($roles[$key]['label'])?></h2>
<?php endif ?>
<?php if (!$creating && $key === 'admin'): ?>
<p>El administrador conserva todos los permisos y la gestión de cuentas.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="base" value="<?=e($base)?>">
<input type="hidden" name="expected" value="<?=e(json_encode($creating ? null : ($overrides[$key] ?? null), JSON_THROW_ON_ERROR))?>">
<?php if ($creating): ?>
<p>Ámbito base seleccionado: <?=e($baseLabels[$base])?>.</p>
<label>Código del rol<input name="role" maxlength="30" pattern="rol-[a-z0-9\-]{2,26}" required placeholder="rol-control-academico" value="<?=e(is_string($input['role']??null)?$input['role']:'')?>"></label>
<?php else: ?><input type="hidden" name="role" value="<?=e($key)?>">
<?php if ($custom): ?><p>Ámbito base: <?=e($baseLabels[$base])?>.</p><?php endif ?>
<?php endif ?>
<label>Nombre del rol<input name="label" maxlength="100" required value="<?=e(is_string($role['label']??null)?$role['label']:'')?>"></label>
<fieldset><legend>Módulos habilitados</legend>
<table><thead><tr><th>Módulo</th><th>Acceso</th><?php if ($custom): ?><th>Gestión</th><?php endif ?></tr></thead><tbody>
<?php foreach (PortalPolicy::roleModules($base) as $module):
    $label = $definitions['modules'][$module] ?? $module;
?>
<tr><td><?=e($label)?></td>
<td><input type="checkbox" name="mods[]" value="<?=e($module)?>" aria-label="<?=e('Acceso a '.$label)?>" <?=in_array($module,(array)$role['mods'],true)||$module==='dashboard'?'checked':''?> <?=$module==='dashboard'?'disabled':''?>></td>
<?php if ($custom): ?>
<td><?php if ($module !== 'dashboard'): ?><input type="checkbox" name="write[]" value="<?=e($module)?>" aria-label="<?=e('Gestión de '.$label)?>" <?=in_array($module,(array)($role['write']??[]),true)?'checked':''?>><?php endif ?></td>
<?php endif ?></tr>
<?php endforeach ?>
</tbody></table>
<?php if ($custom): ?><p>Habilita el acceso a un módulo para conceder gestión. Las reglas operativas de cada módulo siguen aplicándose.</p><?php endif ?>
</fieldset>
<?php if (in_array($base,['secretaria','directivo','coordinador','atencion'],true)): ?>
<fieldset><legend>Importaciones desde Excel</legend>
<table><thead><tr><th>Catálogo</th><th>Permiso</th></tr></thead><tbody>
<?php foreach (ImportCatalog::MODULES as $module=>[$label]):
    if ($base==='coordinador' && !in_array($module,ImportCatalog::PROGRAM_SCOPED,true)) continue;
    $level = $role['imports'][$module] ?? 0;
?>
<tr><td><?=e($label)?></td><td><select name="imports[<?=e($module)?>]" aria-label="<?=e('Importación de '.$label)?>">
<option value="0" <?=$level===0?'selected':''?>>Sin acceso</option>
<option value="1" <?=$level===1?'selected':''?>>Importar nuevos</option>
<?php if (in_array($module,ImportCatalog::MUTABLE,true)): ?><option value="2" <?=$level===2?'selected':''?>>Importar y actualizar</option><?php endif ?>
</select></td></tr>
<?php endforeach ?>
</tbody></table>
</fieldset>
<?php endif ?>
<button name="action" value="save"><?=$creating?'Crear rol':'Guardar permisos'?></button>
<?php if (!$custom): ?><button name="action" value="reset" formnovalidate>Restaurar permisos originales</button><?php endif ?>
</form>
<?php endif ?>
</main></body></html>
