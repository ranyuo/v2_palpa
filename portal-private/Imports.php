<?php
declare(strict_types=1);

require_once __DIR__.'/ImportCatalog.php';
require_once __DIR__.'/ImportWorkbook.php';

final class ImportColumnError extends RuntimeException
{
    public function __construct(public readonly string $column, string $message)
    {
        parent::__construct($message);
    }
}

trait PortalImports
{
    private function importUser(): array
    {
        $actor = $this->requireUser();
        if (!empty($actor['cambioClave']) || !PortalPolicy::importPermissions($actor)) {
            throw new PortalError(403, 'Tu cuenta no tiene permisos de importación.');
        }
        return $actor;
    }

    public function importModules(): array
    {
        return array_intersect_key(ImportCatalog::MODULES, PortalPolicy::importPermissions($this->importUser()));
    }

    public function importCatalogPage(string $module, int $page): array
    {
        $actor = $this->importUser();
        $this->importPermission($actor, $module);
        if (!in_array($module, ImportCatalog::EXTRA, true)) throw new PortalError(404, 'Catálogo no disponible.');
        $data = json_decode($this->state()['data'], true, flags: JSON_THROW_ON_ERROR)['academico'];
        $programs = array_column($data['programas'], 'cod', 'id');
        $students = array_column($data['estudiantes'], 'dni', 'id');
        $own = $data['progMap'][$actor['programaId'] ?? ''] ?? null;
        $rows = $data[$module];
        if (PortalPolicy::scope($actor) === 'programa') {
            $rows = array_values(array_filter($rows, fn($row)=>$own !== null && (int)$row['prog'] === (int)$own));
        }
        usort($rows, fn($left,$right)=>strcmp($right['periodo'] ?? '', $left['periodo'] ?? '') ?: $right['id'] <=> $left['id']);
        $pages = max(1, (int)ceil(count($rows)/100));
        $page = max(1,min($page,$pages));
        $result = [];
        foreach (array_slice($rows, ($page-1)*100, 100) as $row) {
            $row['programa'] = $programs[$row['prog'] ?? 0] ?? '';
            $row['dni'] = $students[$row['est'] ?? 0] ?? '';
            $result[] = $row;
        }
        return ['rows'=>$result,'page'=>$page,'pages'=>$pages];
    }

    private function importPermission(array $actor, string $module): int
    {
        return PortalPolicy::importPermissions($actor)[$module] ?? throw new PortalError(403, 'Tu rol no puede importar este módulo.');
    }

    public function previewImport(string $module, string $name, string $file): array
    {
        $actor = $this->importUser();
        $permission = $this->importPermission($actor, $module);
        $this->rate('import-preview-'.$actor['server_id'], 30, 60);
        $name = substr(basename(str_replace('\\', '/', $name)), 0, 200);
        if (!preg_match('/\.xlsx$/iD', $name) || !preg_match('//u', $name)) {
            throw new PortalError(422, 'Selecciona un libro de Excel .xlsx.');
        }
        $hash = hash_file('sha256', $file);
        try {
            $rows = ImportWorkbook::read($file, ImportCatalog::columns($module));
            if ($module === 'cuentas' && count($rows) > 200) {
                throw new PortalError(422, 'Divide la creación de cuentas en archivos de hasta 200 filas.');
            }
            $state = $this->state();
            $data = json_decode($state['data'], true, flags: JSON_THROW_ON_ERROR);
            $plan = $this->planImport($data, $rows, $module, $actor, $permission);
        } catch (PortalError $error) {
            PortalAudit::write($this, 'Rechazar archivo', 'Importaciones', $module, ['archivo'=>$name,'sha256'=>$hash,'motivo'=>$error->getMessage()], $actor);
            throw $error;
        }
        $token = bin2hex(random_bytes(24));
        $_SESSION['import_preview'] = [
            'actor'=>$actor['server_id'], 'module'=>$module, 'name'=>$name, 'hash'=>$hash,
            'token'=>$token, 'rows'=>$rows, 'revision'=>(int)$state['revision'], 'expires'=>time()+1200,
            'preview'=>hash('sha256', serialize($plan['report'])),
            'errors'=>array_filter(array_map(fn($row)=>['errors'=>$row['errors']], $plan['report']), fn($row)=>$row['errors']),
        ];
        PortalAudit::write($this, 'Validar archivo', 'Importaciones', $module, ['archivo'=>$name,'sha256'=>$hash,'filas'=>count($rows),'resultado'=>$plan['counts']], $actor);
        return ['token'=>$token, 'report'=>$plan['report'], 'counts'=>$plan['counts']];
    }

    public function importErrorReport(string $module, string $token): array
    {
        $actor = $this->importUser();
        $this->importPermission($actor, $module);
        $preview = $_SESSION['import_preview'] ?? [];
        if (($preview['actor'] ?? null) !== $actor['server_id'] || $preview['expires'] < time()
            || $preview['module'] !== $module || !hash_equals($preview['token'], $token)) {
            throw new PortalError(409, 'La vista previa venció. Valida el archivo nuevamente.');
        }
        return $preview['errors'];
    }

    public function confirmImport(string $token, bool $updates): array
    {
        $actor = $this->importUser();
        $preview = $_SESSION['import_preview'] ?? [];
        if (($preview['actor'] ?? null) !== $actor['server_id'] || $preview['expires'] < time() || !hash_equals($preview['token'], $token)) {
            throw new PortalError(409, 'La vista previa venció. Valida el archivo nuevamente.');
        }
        $module = $preview['module'];
        $this->rate('import-confirm-'.$actor['server_id'], 30, 60);
        try {
            $result = $this->transaction(function () use ($preview, $module, $updates) {
                $state = $this->state(true);
                $actor = $this->importUser();
                $permission = $this->importPermission($actor, $module);
                if ((int)$state['revision'] !== $preview['revision']) {
                    throw new PortalError(409, 'Los datos cambiaron después de la vista previa. Valida nuevamente.');
                }
                if ($updates && $permission !== 2) {
                    throw new PortalError(403, 'Tu rol no tiene permiso para actualizar registros mediante Excel.');
                }
                $old = json_decode($state['data'], true, flags: JSON_THROW_ON_ERROR);
                $plan = $this->planImport($old, $preview['rows'], $module, $actor, $permission);
                if (!hash_equals($preview['preview'], hash('sha256', serialize($plan['report'])))) {
                    throw new PortalError(409, 'La clasificación cambió. Valida el archivo nuevamente.');
                }
                if (!$updates && $plan['counts']['Actualizable']) {
                    $plan = $this->planImport($old, $preview['rows'], $module, $actor, 1);
                }
                $credentials = [];
                foreach (array_chunk($plan['accounts'], 100) as $batch) {
                    foreach ($batch as $account) {
                        $password = self::temporaryPassword();
                        $this->createAccount([...$account,'password'=>$password], false, true);
                        $credentials[] = ['usuario'=>$account['username'],'clave'=>$password];
                    }
                }
                $applied = $plan['counts']['Nuevo'] + ($updates ? $plan['counts']['Actualizable'] : 0);
                if ($applied) {
                    $this->validateBindings($plan['data']);
                    PortalAudit::changes($this, $old, $plan['data'], $actor);
                    $this->updateState($plan['data'], (int)$state['revision'] + 1);
                }
                PortalAudit::write($this, 'Confirmar importación', 'Importaciones', $module, [
                    'archivo'=>$preview['name'],'sha256'=>$preview['hash'],'aplicados'=>$applied,
                    'lotes'=>(int)ceil($applied/100),'resultado'=>$plan['counts'],
                ], $actor);
                return ['applied'=>$applied, 'credentials'=>$credentials,'report'=>$plan['report']];
            });
        } catch (Throwable $error) {
            $reason = $error instanceof PortalError ? $error->getMessage() : 'Error al almacenar la importación.';
            PortalAudit::write($this, 'Revertir importación', 'Importaciones', $module, ['archivo'=>$preview['name'],'sha256'=>$preview['hash'],'motivo'=>$reason], $actor);
            throw $error;
        }
        unset($_SESSION['import_preview']);
        return $result;
    }

    private function planImport(array $data, array $rows, string $module, array $actor, int $permission): array
    {
        $report = [];
        $accounts = [];
        $counts = ['Nuevo'=>0,'Existente'=>0,'Actualizable'=>0,'Observado'=>0];
        $keys = [];
        foreach ($rows as $row) {
            $key = ImportCatalog::key($module, $row['values']);
            $keys[$key] = ($keys[$key] ?? 0) + 1;
        }
        $knownAccounts = $module === 'cuentas'
            ? $this->db()->query('SELECT username,email,role,profile FROM intranet_users')->fetchAll() : [];
        foreach (array_chunk($rows, 100, true) as $batch) {
            foreach ($batch as $number => $row) {
                [$values, $errors] = ImportCatalog::fields($module, $row['values']);
                $errors = [...$errors, ...$row['errors']];
                $status = 'Observado';
                $changes = [];
                if ($keys[ImportCatalog::key($module, $row['values'])] > 1 && !$errors) {
                    $errors[ImportCatalog::MODULES[$module][1]] = 'La clave aparece más de una vez en el archivo.';
                }
                if (!$errors) {
                    try {
                        if ($module === 'cuentas') {
                            [$status, $account] = $this->importAccount($data, $actor, $values, [...$knownAccounts, ...$accounts]);
                            if ($status === 'Nuevo') $accounts[] = $account;
                        } else {
                            [$status, $next, $changes] = $this->planAcademicRow($data, $module, $values, $actor, $permission);
                            if (in_array($status, ['Nuevo','Actualizable'], true)) $data = $next;
                        }
                    } catch (ImportColumnError $error) {
                        $errors[$error->column] = $error->getMessage();
                    } catch (PortalError $error) {
                        if (!in_array($error->status, [403,422], true)) throw $error;
                        $errors['Registro'] = $error->getMessage();
                    }
                }
                $counts[$status]++;
                $report[$number] = ['status'=>$status,'values'=>$row['values'],'changes'=>$changes,'errors'=>$errors];
            }
        }
        return compact('data', 'report', 'accounts', 'counts');
    }

    private function importReference(array $rows, string $key, string $value, string $field): array
    {
        return ImportCatalog::reference($rows, $key, $value)
            ?? throw new ImportColumnError($field, 'La referencia no existe, está eliminada o coincide con varios registros.');
    }

    private function assertImportScope(array $data, array $actor, ?int $program): void
    {
        if (PortalPolicy::scope($actor) !== 'programa') return;
        $own = $data['academico']['progMap'][$actor['programaId'] ?? ''] ?? null;
        if (!$program || !$own || $program !== (int)$own) {
            throw new ImportColumnError('programa', 'El registro está fuera del programa autorizado.');
        }
    }

    private function importAccount(array $data, array $actor, array $values, array $accounts): array
    {
        $a = $data['academico'];
        $role = $values['rol'];
        if ($role === 'docente') {
            $person = $this->importReference($data['personas'], 'dni', $values['dni'], 'dni');
            $holder = $this->importReference($a['docentes'], 'per', (string)$person['id'], 'dni');
            $this->assertImportScope($data, $actor, null);
            $name = trim($person['nombres'].' '.$person['apellidos']);
            $binding = 'doc:'.$holder['id'];
        } else {
            $holder = $this->importReference($a['estudiantes'], 'dni', $values['dni'], 'dni');
            $this->assertImportScope($data, $actor, (int)$holder['prog']);
            if (($role === 'egresado') !== in_array($holder['estado'], ['Egresado','Titulado'], true)) {
                throw new ImportColumnError('rol', 'El rol debe corresponder al estado académico del alumno.');
            }
            $name = $holder['nom'];
            $binding = 'est:'.$holder['id'];
        }
        $email = $values['correo'] ?? $holder['correo'] ?? $person['correo'] ?? '';
        $email = strtolower(trim($email ?: $values['usuario'].'@portal.invalid'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>254) throw new ImportColumnError('correo','Correo no válido.');
        if (preg_match_all('/./us', $name) > 150) throw new ImportColumnError('dni','El nombre de la persona supera el límite de la cuenta.');
        foreach ($accounts as $account) {
            $storedBinding = $account['vin'] ?? (json_decode($account['profile'] ?? '{}', true)['vin'] ?? '');
            if (strcasecmp($account['username'], $values['usuario']) === 0) {
                if ($storedBinding === $binding && $account['role'] === $role) return ['Existente', []];
                throw new ImportColumnError('usuario', 'El nombre de usuario pertenece a otra cuenta o rol.');
            }
            if ($storedBinding === $binding) throw new ImportColumnError('dni', 'La persona ya tiene una cuenta vinculada.');
            if (strcasecmp($account['email'], $email) === 0) throw new ImportColumnError('correo', 'El correo pertenece a otra cuenta.');
        }
        return ['Nuevo', ['username'=>$values['usuario'],'email'=>$email,'name'=>$name,'role'=>$role,'vin'=>$binding]];
    }

    private function planAcademicRow(array $data, string $module, array $values, array $actor, int $permission): array
    {
        $next = $data;
        $a = &$next['academico'];
        $row = $values;
        $program = isset($values['programa'])
            ? $this->importReference($a['programas'], 'cod', $values['programa'], 'programa') : null;
        if ($program) $row['prog'] = (int)$program['id'];
        unset($row['programa'], $row['modalidad']);
        $student = null;
        if (in_array($module, ['matriculas','certs','titulos'], true)) {
            $student = $this->importReference($a['estudiantes'], 'dni', $values['dni'], 'dni');
            if ($program && (int)$student['prog'] !== (int)$program['id']) {
                throw new ImportColumnError('programa', 'El programa no coincide con el del alumno.');
            }
            $row['est'] = $student['id'];
            unset($row['dni']);
        }
        $this->assertImportScope($data, $actor, $program ? (int)$program['id'] : ($student ? (int)$student['prog'] : null));

        if (isset($values['modulo'])) {
            $modules = array_filter($a['modulos'], fn($item) => (int)$item['prog'] === (int)$program['id']);
            $row['mod'] = $this->importReference($modules, 'cod', $values['modulo'], 'modulo')['id'];
            unset($row['modulo']);
        }
        if ($module === 'docentes') {
            $people = ImportCatalog::matches($next['personas'], 'dni', $values['dni']);
            if (count($people)>1) throw new ImportColumnError('dni','Hay más de una ficha de persona con este DNI.');
            $personIndex = $people[0] ?? count($next['personas']);
            $oldPerson = $next['personas'][$personIndex] ?? [];
            if ($oldPerson && (($oldPerson['tipo'] ?? '') !== 'Docente' || !empty($oldPerson['deleted']))) {
                throw new ImportColumnError('dni', 'El DNI pertenece a una ficha eliminada o de otro tipo.');
            }
            $person = array_replace($oldPerson ?: ['id'=>'import-doc-'.$values['dni'],'tipo'=>'Docente','estado'=>'Borrador','programas'=>[]],
                array_intersect_key($values, array_flip(['dni','nombres','apellidos','especialidad','correo'])));
            $next['personas'][$personIndex] = $person;
            $row = ['per'=>$person['id'],'dias'=>$values['dias'],'desde'=>$values['desde'],'hasta'=>$values['hasta']];
            if (isset($values['unidades']) || !$oldPerson) {
                $row['uds'] = array_map(fn($code)=>$this->importReference($a['uds'],'cod',$code,'unidades')['id'], $values['unidades'] ?? []);
            }
        }
        if ($module === 'certificados') {
            $linkedStudent = ImportCatalog::reference($a['estudiantes'], 'dni', $values['dni']);
            if ($linkedStudent && $program && (int)$linkedStudent['prog'] !== (int)$program['id']) {
                throw new ImportColumnError('programa', 'El programa no coincide con el del alumno registrado.');
            }
            unset($row['prog']);
            if ($program) {
                $row['programa'] = $program['nombre'];
                if (!empty($program['pid'])) $row['programaId'] = $program['pid'];
            }
            $row['codigo'] = strtoupper($row['codigo']);
            $row['origen'] = 'Importación histórica';
        }
        if ($module === 'periodos' && isset($row['inicio'], $row['fin']) && $row['inicio'] > $row['fin']) {
            throw new ImportColumnError('fin', 'La fecha final es anterior a la inicial.');
        }
        if ($module === 'secciones') {
            $this->importReference($a['periodos'], 'periodo', $row['periodo'], 'periodo');
            $this->importReference(array_filter($a['ciclos'], fn($r)=>$r['prog']===$row['prog']), 'ciclo', $row['ciclo'], 'ciclo');
        }
        if ($module === 'matriculas') {
            $matches = array_filter($a['secciones'], fn($group) =>
                !array_diff_assoc(array_intersect_key($row, array_flip(['prog','periodo','ciclo','sec','turno'])), $group));
            if (!$matches) throw new ImportColumnError('sec', 'El grupo no existe. Importa primero el periodo, el ciclo y la sección.');
        }
        $identity = match ($module) {
            'matriculas' => ['est','periodo'],
            'docentes' => ['per'],
            'ciclos' => ['prog','ciclo'],
            'modulos' => ['prog','cod'],
            'secciones' => ['prog','periodo','ciclo','sec','turno'],
            default => explode(' ', ImportCatalog::MODULES[$module][1]),
        };
        if ($module === 'certificados') $target = &$next['certificados'];
        else $target = &$a[$module];
        $matches = array_keys(array_filter($target, function ($stored) use ($identity, $row) {
            foreach ($identity as $field) {
                if (PortalRules::normal((string)($stored[$field] ?? '')) !== PortalRules::normal((string)$row[$field])) return false;
            }
            return true;
        }));
        if (count($matches) > 1) throw new ImportColumnError(implode(' ', $identity), 'La clave coincide con varios registros existentes.');
        $index = $matches[0] ?? count($target);
        $prior = $target[$index] ?? [];
        if ($prior) {
            $this->assertImportScope($data, $actor, isset($prior['prog']) ? (int)$prior['prog'] : ($program ? (int)$program['id'] : ($student ? (int)$student['prog'] : null)));
            if (!empty($prior['deleted'])) throw new ImportColumnError(implode(' ', $identity), 'La clave pertenece a un registro eliminado.');
            if ($module === 'certificados' && PortalPolicy::scope($actor) === 'programa' && ($prior['programaId'] ?? '') !== ($actor['programaId'] ?? '')) {
                throw new ImportColumnError('programa', 'El documento está fuera del programa autorizado.');
            }
        }
        if (!$prior && in_array($module, ['certs','titulos'], true)) {
            $codeColumn = $module === 'certs' ? 'num' : 'cod';
            if (ImportCatalog::matches($next['certificados'], 'codigo', $row[$codeColumn])) {
                throw new ImportColumnError($codeColumn, 'El código ya está registrado en el catálogo de documentos emitidos.');
            }
        }
        $candidate = array_replace($prior, $row);
        $changes = [];
        foreach ($row as $field => $value) {
            if (!PortalPolicy::equal($prior[$field] ?? null, $value)) $changes[] = $field;
        }
        if ($module === 'programas' && isset($values['modalidad']) && !empty($prior['pid'])) {
            $previousModality = PortalPolicy::find($data['programas'],'id',$prior['pid'])['modalidad'] ?? '';
            if ($previousModality !== $values['modalidad']) $changes[] = 'modalidad';
        }
        if ($module === 'docentes' && !PortalPolicy::equal($oldPerson, $person)) $changes[] = 'ficha docente';
        if ($prior && (!$changes || $permission < 2 || !in_array($module, ImportCatalog::MUTABLE, true))) {
            return ['Existente', $data, []];
        }
        if (!$prior) {
            $candidate['id'] = $module === 'certificados' ? 'import-'.hash('sha256', $row['codigo'])
                : max([0, ...array_column($target, 'id')]) + 1;
        }
        $target[$index] = $candidate;
        if ($module === 'estudiantes' && $prior && (int)$prior['prog'] !== (int)$candidate['prog']) {
            foreach ($a['matriculas'] as $enrollment) {
                if ((int)$enrollment['est'] === (int)$prior['id']) {
                    throw new ImportColumnError('programa', 'El alumno tiene matrículas históricas vinculadas.');
                }
            }
            foreach ($a['certs'] as $cert) {
                if ((int)$cert['est'] === (int)$prior['id']) {
                    throw new ImportColumnError('programa', 'El alumno tiene certificaciones modulares vinculadas.');
                }
            }
        }
        if ($module === 'matriculas' && $row['periodo'] === $a['cfg']['periodo']) {
            $studentIndex = array_search($student['id'], array_column($a['estudiantes'], 'id'), true);
            $a['estudiantes'][$studentIndex] = array_replace($student, array_intersect_key($row, array_flip(['ciclo','sec','turno'])));
        }
        if ($module === 'programas' && !empty($prior['pid'])) {
            $programIndex = array_search($prior['pid'], array_column($next['programas'], 'id'), true);
            if ($programIndex === false) throw new ImportColumnError('cod', 'El programa vinculado no existe.');
            $next['programas'][$programIndex]['nombre'] = $row['nombre'];
            $next['programas'][$programIndex]['duracion'] = $row['dur'];
            if (isset($values['modalidad'])) $next['programas'][$programIndex]['modalidad'] = $values['modalidad'];
            $next['programas'][$programIndex]['estado'] = $row['activo'] ? 'Publicado' : 'Borrador';
        }
        if ($module === 'programas' && !$prior) {
            $pid = 'import-prog-'.$candidate['id'];
            $target[$index]['pid'] = $pid;
            $a['progMap'][$pid] = $candidate['id'];
            $next['programas'][] = ['id'=>$pid,'codigo'=>$row['cod'],'nombre'=>$row['nombre'],'duracion'=>$row['dur'],
                'estado'=>$row['activo'] ? 'Publicado' : 'Borrador','modulos'=>[],
                'modalidad'=>$values['modalidad'] ?? 'Presencial','orden'=>count($next['programas'])+1];
        }
        if ($module === 'periodos' && isset($candidate['inicio'], $candidate['fin']) && $candidate['inicio'] > $candidate['fin']) {
            throw new ImportColumnError('fin', 'La fecha final es anterior a la inicial.');
        }
        PortalRules::apply($data, $next, $actor);
        PortalAcademic::validate($data, $next);
        AcademicDocuments::validate($data, $next);
        PortalDomain::validate($data, $next, $actor);
        if (in_array($module, ['certs','titulos'], true)) {
            PortalDomain::syncCertificates($next);
            AcademicDocuments::sync($next);
        }
        return [$prior ? 'Actualizable' : 'Nuevo', $next, $prior ? $changes : []];
    }

    public static function validateImportRelations(array $data): void
    {
        $a = $data['academico'];
        foreach ([...$a['ciclos'], ...$a['secciones'], ...$a['matriculas']] as $record) {
            if (!PortalPolicy::find($a['programas'], 'id', $record['prog'])) {
                throw new PortalError(422, 'El programa tiene ciclos, secciones o matrículas vinculadas.');
            }
        }
        foreach ($a['matriculas'] as $record) {
            if (!PortalPolicy::find($a['estudiantes'], 'id', $record['est'])) {
                throw new PortalError(422, 'El alumno tiene matrículas vinculadas.');
            }
        }
    }
}
