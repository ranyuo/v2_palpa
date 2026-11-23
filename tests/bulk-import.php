<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__).'/portal-private/bootstrap.php';
$configPath = getenv('PORTAL_CONFIG_FILE');
if (!$configPath || !str_ends_with($configPath, 'config.test.php')) throw new RuntimeException('Usa config.test.php.');
$config = require $configPath;
if (!str_ends_with($config['db_name'],'_test') || !empty($config['smtp']['enabled'])) throw new RuntimeException('Usa una base de pruebas sin correo.');
$app = portal_app();
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);

function check(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

function rejected(int $status, callable $callback): void
{
    try { $callback(); }
    catch (PortalError $error) {
        if ($error->status === $status) return;
        throw $error;
    }
    throw new RuntimeException('Se esperaba un rechazo HTTP '.$status);
}

function preview(PortalApp $app, string $module, array $rows): array
{
    $columns = ImportCatalog::columns($module);
    $path = sys_get_temp_dir().'/palpa-test-'.bin2hex(random_bytes(12)).'.zip';
    file_put_contents($path, ImportWorkbook::template($columns));
    try {
        $zip = new PharData($path);
        $sheet = 'xl/worksheets/sheet1.xml';
        $xml = $zip[$sheet]->getContent();
        $body = '';
        foreach ($rows as $index=>$row) {
            $number = $index+2;
            $body .= '<row r="'.$number.'">';
            foreach ($columns as $i=>$column) {
                if (!isset($row[$column])) continue;
                $value = htmlspecialchars((string)$row[$column], ENT_XML1, 'UTF-8');
                $body .= '<c r="'.chr(65+$i).$number.'" t="inlineStr"><is><t>'.$value.'</t></is></c>';
            }
            $body .= '</row>';
        }
        $zip[$sheet] = str_replace('</sheetData>', $body.'</sheetData>', $xml);
        return $app->previewImport($module, 'prueba.xlsx', $path);
    } finally { unlink($path); }
}

function loadRow(PortalApp $app, string $module, array $row): array
{
    $plan = preview($app, $module, [$row]);
    check($plan['counts']['Nuevo'] === 1, $module.': '.json_encode($plan['report'], JSON_UNESCAPED_UNICODE));
    $result = $app->confirmImport($plan['token'], false);
    check($result['applied'] === 1, 'Confirmación fallida: '.$module);
    return $result;
}

$period = $app->bootstrap()['data']['academico']['cfg']['periodo'];
$program = ['cod'=>'CARGA-QA','nombre'=>'Programa Carga QA','dur'=>'3 años','sem'=>'6','activo'=>'1'];
loadRow($app,'programas',$program);
loadRow($app,'periodos',['periodo'=>$period,'inicio'=>'2026-01-01','fin'=>'2026-12-31']);
$group = ['programa'=>'CARGA-QA','ciclo'=>'VI','periodo'=>$period,'sec'=>'A','turno'=>'Tarde'];
loadRow($app,'ciclos',$group);
loadRow($app,'secciones',$group);
loadRow($app,'modulos',['programa'=>'CARGA-QA','cod'=>'MOD-QA','nombre'=>'Módulo QA','ciclos'=>'VI']);
loadRow($app,'uds',['programa'=>'CARGA-QA','cod'=>'UD-CARGA-QA','nombre'=>'Unidad QA','ciclo'=>'VI','horas'=>'4','cred'=>'2','modulo'=>'MOD-QA']);
loadRow($app,'ambientes',['programa'=>'CARGA-QA','cod'=>'LAB-CARGA-QA','tipo'=>'Laboratorio','cap'=>'30','ubic'=>'Sede principal']);
loadRow($app,'docentes',['dni'=>'00123450','nombres'=>'Julia','apellidos'=>'Docente QA','especialidad'=>'Informática','unidades'=>'UD-CARGA-QA','dias'=>'Lun, Mar','desde'=>'08:00','hasta'=>'16:00']);
$student = ['dni'=>'00123451','nom'=>'Alumna Carga QA','estado'=>'Egresado',...$group,'sec'=>'B'];
$bad = [...$student,'dni'=>'00123452','programa'=>'NO-EXISTE'];
$duplicate = [...$student,'dni'=>'00123453'];
$plan = preview($app,'estudiantes',[$student,$bad,$duplicate,$duplicate]);
check($plan['counts'] === ['Nuevo'=>1,'Existente'=>0,'Actualizable'=>0,'Observado'=>3], 'Clasificación de duplicados y referencias.');
check(isset($app->importErrorReport('estudiantes',$plan['token'])[3]['errors']['programa']), 'Informe por columna.');
check($app->confirmImport($plan['token'],false)['applied']===1, 'Solo se confirman filas válidas.');
rejected(409,fn()=>$app->confirmImport($plan['token'],false));
check(preview($app,'estudiantes',[$student])['counts']['Existente']===1, 'Reintento idempotente.');
$edited = [...$student,'nom'=>'Alumna QA corregida'];
$plan = preview($app,'estudiantes',[$edited]);
check($plan['report'][2]['changes']===['nom'], 'Vista previa de cambios.');
check($app->confirmImport($plan['token'],false)['applied']===0, 'Sin sobrescritura por defecto.');
$plan = preview($app,'estudiantes',[$edited]);
check($app->confirmImport($plan['token'],true)['applied']===1, 'Actualización expresamente autorizada.');
loadRow($app,'matriculas',['dni'=>$student['dni'],...$group]);
loadRow($app,'certs',['dni'=>$student['dni'],'programa'=>'CARGA-QA','modulo'=>'MOD-QA','num'=>'CERT-CARGA-QA','fecha'=>'2025-12-01']);
loadRow($app,'certificados',['codigo'=>'CONST-CARGA-QA','dni'=>$student['dni'],'nombres'=>$edited['nom'],'tipo'=>'Constancia','detalle'=>'Curso QA','fecha'=>'2025-12-01','institucion'=>'IESP Palpa','estado'=>'Válido','programa'=>'CARGA-QA']);
loadRow($app,'titulos',['dni'=>$student['dni'],'cod'=>'TIT-CARGA-QA','culm'=>'2025-11-01','tit'=>'2025-12-01','resol'=>'RES-CARGA-QA']);
$result = loadRow($app,'cuentas',['usuario'=>'alumna-carga','dni'=>$student['dni'],'rol'=>'egresado']);
check(strlen($result['credentials'][0]['clave']) > 12, 'Clave temporal generada.');
loadRow($app,'cuentas',['usuario'=>'docente-carga','dni'=>'00123450','rol'=>'docente']);
check($app->lookup('certificados',['reference'=>'TIT-CARGA-QA','document'=>$student['dni']])['record']['estado']==='Válido', 'Título histórico verificable.');
$state = $app->bootstrap();
$current = PortalPolicy::find($state['data']['academico']['estudiantes'],'dni',$student['dni']);
check($current['sec']==='A' && $current['nom']===$edited['nom'], 'La matrícula actual actualiza el grupo operativo.');
$plan = preview($app,'periodos',[['periodo'=>'2031-I']]);
loadRow($app,'periodos',['periodo'=>'2031-II']);
rejected(409,fn()=>$app->confirmImport($plan['token'],false));
$stale = preview($app,'periodos',[['periodo'=>'2032-I']]);
$app->db()->exec('UPDATE intranet_state SET revision=revision+1 WHERE id=1');
rejected(409,fn()=>$app->confirmImport($stale['token'],false));

$programId = array_search(PortalPolicy::find($state['data']['academico']['programas'],'cod','CARGA-QA')['id'], $state['data']['academico']['progMap'], true);
$app->saveRole(['role'=>'rol-carga-qa','label'=>'Carga QA','base'=>'coordinador','mods'=>['ix-alumnos'],'write'=>[],'imports'=>['estudiantes'=>1],'expected'=>null]);
$app->createAccount(['username'=>'carga-qa','name'=>'Carga QA','role'=>'rol-carga-qa','programaId'=>$programId,'password'=>'PruebaIntranet-2026!']);
$app->login(['username'=>'carga-qa','password'=>'PruebaIntranet-2026!']);
rejected(403,fn()=>preview($app,'cuentas',[['usuario'=>'otro-qa','dni'=>'00123451','rol'=>'egresado']]));
$external = [...$student,'dni'=>'00123460','programa'=>PortalPolicy::find($state['data']['academico']['programas'],'id',$state['data']['academico']['progMap']['apsti'])['cod']];
check(preview($app,'estudiantes',[$external])['counts']['Observado']===1,'El rol no importa alumnos de otro programa.');
$scoped = $app->bootstrap();
check(count($scoped['data']['academico']['estudiantes'])===1,'Consulta limitada al programa.');
$scoped['data']['academico']['estudiantes'][0]['nom'] = 'Cambio sin permiso';
rejected(403,fn()=>$app->save(['data'=>$scoped['data'],'revision'=>$scoped['revision']]));
$insert = [...$student,'dni'=>'00123461'];
$plan = preview($app,'estudiantes',[$insert]);
rejected(403,fn()=>$app->confirmImport($plan['token'],true));
check($app->confirmImport($plan['token'],false)['applied']===1,'El rol puede importar dentro del programa.');
$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);
$before = $app->bootstrap();
$stored = json_decode($app->db()->query('SELECT data FROM intranet_state WHERE id=1')->fetchColumn(), true);
foreach (ImportCatalog::EXTRA as $collection) unset($stored['academico'][$collection]);
$app->db()->prepare('UPDATE intranet_state SET data=? WHERE id=1')->execute([json_encode($stored, JSON_THROW_ON_ERROR)]);
$app->db()->exec('UPDATE intranet_schema SET version=7 WHERE id=1');
PortalSchema::apply($app->db());
$restored = $app->bootstrap();
check($restored['schema_version'] === 8 && $restored['data']['academico']['matriculas'] === [], 'Actualización de estado anterior.');
check($restored['data']['academico']['estudiantes'] === $before['data']['academico']['estudiantes'], 'La actualización conserva el maestro anterior.');
$rows = $app->db()->query("SELECT details FROM intranet_audit WHERE action='Confirmar importación'")->fetchAll(PDO::FETCH_COLUMN);
check(count($rows) >= 14 && isset(json_decode($rows[0],true)['sha256']), 'Auditoría registrada para las confirmaciones.');
echo "Importaciones y roles de Palpa verificados en MySQL.\n";
