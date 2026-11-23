<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
portal_headers();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = '';
$preview = null;
$result = null;
$catalog = null;
try {
    $app = portal_app();
    $modules = $app->importModules();
    $actor = $app->requireUser();
    $module = $_POST['module'] ?? $_GET['module'] ?? array_key_first($modules);
    if (!is_string($module) || !isset($modules[$module])) throw new PortalError(403, 'No tienes acceso a ese módulo.');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'plantilla' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $filename = 'palpa-'.$module.'.xlsx';
        $path = dirname(__DIR__).'/portal-private/plantillas/'.$filename;
        $template = is_readable($path) ? file_get_contents($path) : false;
        if ($template === false || !str_starts_with($template, "PK\x03\x04")) {
            throw new PortalError(503, 'La plantilla no está disponible. Comunícate con el administrador de la intranet.');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $template;
        exit;
    }
    if ($action === 'catalogo' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $catalog = $app->importCatalogPage($module, (int)($_GET['page'] ?? 1));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $app->checkCsrf(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : '');
        if ($action === 'validar') {
            $file = $_FILES['excel'] ?? [];
            if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) ||
                !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                $app->rejectImportUpload($module, is_string($file['name'] ?? null) ? $file['name'] : '');
            }
            $preview = $app->previewImport($module, $file['name'], $file['tmp_name']);
        } elseif ($action === 'confirmar') {
            $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
            if (($_SESSION['import_preview']['module'] ?? '') !== $module) throw new PortalError(409, 'Valida el archivo antes de confirmar.');
            $result = $app->confirmImport($token, ($_POST['updates'] ?? '') === '1');
        } elseif ($action === 'errores') {
            $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
            $report = $app->importErrorReport($module, $token);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="palpa-errores-'.$module.'.csv"');
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fila','Columna','Motivo'], ',', '"', '');
            foreach ($report as $number => $row) {
                foreach ($row['errors'] as $column => $reason) fputcsv($out, [$number,$column,$reason], ',', '"', '');
            }
            fclose($out);
            exit;
        }
    }
} catch (PortalError $exception) {
    if (!isset($modules, $module) || !in_array($exception->status,[409,422,429],true)) portal_error($exception);
    http_response_code($exception->status);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    portal_error($exception);
}

$columns = ImportCatalog::columns($module);
$required = array_filter(explode(' ', $modules[$module][2]), fn($field)=>!str_ends_with($field, '?'));
$report = $preview['report'] ?? $result['report'] ?? [];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Importaciones | Intranet Palpa</title><link rel="stylesheet" href="/forms.css">
<style>
main{max-width:1160px}section{margin:24px 0}nav{display:flex;gap:12px;flex-wrap:wrap}table{border-collapse:collapse;width:100%;font-size:.9rem}th,td{padding:.55rem;border-bottom:1px solid #ccd6e5;text-align:left;vertical-align:top}th{background:#eef3fb}.table{overflow:auto;max-height:60vh}.actions{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.actions form{margin:0}.actions input{width:auto}.errors{min-width:210px}code{overflow-wrap:anywhere}
</style>
</head>
<body><main>
<a href="/#/admin/dashboard">← Volver al panel</a>
<h1>Importaciones desde Excel</h1>
<nav aria-label="Módulos de importación">
<?php foreach ($modules as $key => [$label]): ?>
<a href="?module=<?=e($key)?>" <?= $key === $module ? 'aria-current="page"' : '' ?>><?=e($label)?></a>
<?php endforeach ?>
</nav>
<section>
<h2><?=e($modules[$module][0])?></h2>
<p><a href="?module=<?=e($module)?>&amp;action=plantilla">Descargar plantilla Excel</a></p>
<p>Campos obligatorios: <?=e(implode(', ', $required))?>. Usa Texto en Excel para los DNI, los códigos, las fechas (AAAA-MM-DD) y las horas (HH:MM). Ingresa los códigos del catálogo para vincular programas, módulos y unidades. Separa listas con comas.</p>
<?php if (in_array($module, ImportCatalog::EXTRA, true)): ?>
<p><a href="?module=<?=e($module)?>&amp;action=catalogo">Consultar registros del catálogo</a></p>
<?php endif ?>
<?php if (in_array($module, ['estudiantes','matriculas','secciones'], true)): ?>
<p>Registra primero los programas, periodos y ciclos. Para importar alumnos o matrículas, la sección y el turno deben existir en el catálogo.</p>
<?php elseif ($module === 'cuentas'): ?>
<p>Utiliza el DNI de un alumno, egresado o docente registrado. La intranet genera las claves temporales al confirmar.</p>
<?php endif ?>
<?php if ($error): ?><p class="notice" role="alert"><?=e($error)?></p><?php endif ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="module" value="<?=e($module)?>">
<label>Archivo Excel (.xlsx, hasta 2 MB y <?=$module==='cuentas'?'200':'2 000'?> filas)<input type="file" name="excel" accept=".xlsx" required></label>
<button name="action" value="validar">Validar y revisar</button>
</form>
</section>
<?php if ($catalog): ?>
<section><h2>Registros del catálogo</h2>
<?php if (!$catalog['rows']): ?><p>No hay registros disponibles.</p><?php else: ?>
<div class="table" tabindex="0" aria-label="Registros del catálogo"><table>
<thead><tr><?php foreach ($columns as $column): ?><th><?=e($column)?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach ($catalog['rows'] as $row): ?><tr>
<?php foreach ($columns as $column): ?><td><?=e((string)($row[$column]??''))?></td><?php endforeach ?>
</tr><?php endforeach ?></tbody></table></div>
<nav aria-label="Páginas del catálogo">
<?php if ($catalog['page']>1): ?><a href="?module=<?=e($module)?>&amp;action=catalogo&amp;page=<?=$catalog['page']-1?>">Anterior</a><?php endif ?>
<span>Página <?=$catalog['page']?> de <?=$catalog['pages']?></span>
<?php if ($catalog['page']<$catalog['pages']): ?><a href="?module=<?=e($module)?>&amp;action=catalogo&amp;page=<?=$catalog['page']+1?>">Siguiente</a><?php endif ?>
</nav>
<?php endif ?>
</section>
<?php endif ?>
<?php if ($preview): ?>
<section>
<h2>Vista previa</h2>
<p role="status"><?=e(implode(' · ', array_map(fn($state,$count)=>$count.' '.strtolower($state), array_keys($preview['counts']), $preview['counts'])))?></p>
<p>Las filas observadas se omiten. Los registros existentes se conservan salvo que habilites expresamente las actualizaciones autorizadas.</p>
<div class="actions">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="module" value="<?=e($module)?>">
<input type="hidden" name="token" value="<?=e($preview['token'])?>">
<?php if ($preview['counts']['Actualizable']): ?>
<label><input type="checkbox" name="updates" value="1"> Aplicar las actualizaciones indicadas</label>
<?php endif ?>
<?php if ($preview['counts']['Nuevo'] || $preview['counts']['Actualizable']): ?>
<button name="action" value="confirmar">Confirmar importación</button>
<?php endif ?>
<?php if ($preview['counts']['Observado']): ?>
<button name="action" value="errores">Descargar reporte CSV</button>
<?php endif ?>
</form>
</div>
</section>
<?php endif ?>
<?php if ($result): ?>
<h2>Resultado de la importación</h2>
<p role="status">Registros aplicados: <?= (int)$result['applied'] ?>.</p>
<?php if ($result['credentials']): ?>
<h3>Claves temporales</h3>
<p>Las claves se muestran una sola vez. Entrégalas de forma individual a sus titulares. La intranet solicitará cambiarlas en el primer inicio de sesión.</p>
<div class="table"><table><thead><tr><th>Usuario</th><th>Clave temporal</th></tr></thead><tbody>
<?php foreach ($result['credentials'] as $credential): ?>
<tr><td><?=e($credential['usuario'])?></td><td><code><?=e($credential['clave'])?></code></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php endif ?>
<?php if ($report): ?>
<div class="table" tabindex="0" aria-label="Detalle de filas"><table>
<thead><tr><th>Fila</th><th>Estado</th><?php foreach ($columns as $column): ?><th><?=e($column)?></th><?php endforeach ?><th>Campos a actualizar</th><th>Observaciones</th></tr></thead>
<tbody>
<?php foreach ($report as $number => $row): ?>
<tr><td><?= (int)$number ?></td><td><?=e($row['status'])?></td>
<?php foreach ($columns as $column): ?><td><?=e($row['values'][$column])?></td><?php endforeach ?>
<td><?=e(implode(', ', $row['changes']))?></td><td class="errors">
<?php foreach ($row['errors'] as $column=>$reason): ?><div><strong><?=e($column)?></strong> <?=e($reason)?></div><?php endforeach ?>
</td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</main></body></html>
