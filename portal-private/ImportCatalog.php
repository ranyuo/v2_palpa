<?php
declare(strict_types=1);

final class ImportCatalog
{
    public const MODULES = [
        'estudiantes' => ['Maestro de alumnos', 'dni', 'dni nom programa periodo ciclo sec turno estado correo? tel?'],
        'matriculas' => ['Matrículas y asignación de grupos', 'dni periodo', 'dni periodo programa ciclo sec turno'],
        'docentes' => ['Gestión de docentes', 'dni', 'dni nombres apellidos especialidad correo? unidades? dias desde hasta'],
        'certs' => ['Certificaciones modulares', 'num', 'num dni programa modulo fecha institucion? estadoDoc? horasDoc? tipoHoras?'],
        'certificados' => ['Constancias y certificados', 'codigo', 'codigo dni nombres tipo detalle fecha institucion estado programa? horas? tipoHoras?'],
        'titulos' => ['Grados y títulos', 'cod', 'cod dni culm tit resol institucion? estadoDoc? denominacion? nivel? pais? region?'],
        'programas' => ['Configuración académica · Programas', 'cod', 'cod nombre dur sem activo modalidad?'],
        'ciclos' => ['Configuración académica · Ciclos', 'programa ciclo', 'programa ciclo'],
        'modulos' => ['Configuración académica · Módulos', 'programa cod', 'programa cod nombre ciclos horasTotal? tipoHoras?'],
        'uds' => ['Configuración académica · Unidades didácticas', 'cod', 'cod nombre programa ciclo horas cred modulo?'],
        'periodos' => ['Configuración académica · Periodos', 'periodo', 'periodo inicio? fin?'],
        'secciones' => ['Configuración académica · Secciones', 'programa periodo ciclo sec turno', 'programa periodo ciclo sec turno'],
        'ambientes' => ['Ambientes y recursos', 'cod', 'cod tipo cap ubic caract? programa?'],
        'cuentas' => ['Usuarios y cuentas', 'usuario', 'usuario dni rol correo?'],
    ];
    public const EXTRA = ['matriculas', 'periodos', 'ciclos', 'secciones'];
    public const PROGRAM_SCOPED = ['estudiantes','matriculas','certs','certificados','titulos','ciclos','modulos','uds','secciones','ambientes','cuentas'];
    public const MUTABLE = ['estudiantes','matriculas','docentes','programas','modulos','uds','periodos','ambientes'];

    public static function columns(string $module): array
    {
        return explode(' ', str_replace('?', '', self::MODULES[$module][2]));
    }

    public static function key(string $module, array $row): string
    {
        return implode('|', array_map(
            fn($column) => PortalRules::normal((string)($row[$column] ?? '')),
            explode(' ', self::MODULES[$module][1])
        ));
    }

    public static function fields(string $module, array $values): array
    {
        $row = [];
        $errors = [];
        foreach (explode(' ', self::MODULES[$module][2]) as $spec) {
            $field = rtrim($spec, '?');
            $value = $values[$field];
            if ($value === '') {
                if ($field === $spec) $errors[$field] = 'Campo obligatorio.';
                continue;
            }
            try {
                $row[$field] = self::value($module, $field, $value);
            } catch (PortalError $error) {
                $errors[$field] = $error->getMessage();
            }
        }
        return [$row, $errors];
    }

    private static function value(string $module, string $field, string $value): mixed
    {
        $choices = match ($field) {
            'ciclo' => PortalAcademic::CYCLES,
            'turno' => ['Mañana','Tarde','Noche'],
            'estadoDoc' => ['Válido','Anulado'],
            'estado' => $module === 'estudiantes' ? ['Regular','Retirado','Egresado','Titulado'] : ['Válido','Anulado'],
            'modalidad' => ['Presencial','Semipresencial','A distancia'],
            'rol' => ['estudiante','egresado','docente'],
            'tipo' => $module === 'certificados' ? ['Constancia','Certificado'] : ['Aula','Laboratorio','Taller'],
            'tipoHoras' => ['Académicas','Lectivas'],
            default => [],
        };
        if ($choices && !in_array($value, $choices, true)) {
            throw new PortalError(422, 'Valores permitidos: '.implode(', ', $choices).'.');
        }
        if (in_array($field, ['fecha','culm','tit','inicio','fin'], true)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) throw new PortalError(422, 'Usa el formato AAAA-MM-DD como texto.');
            if (in_array($field, ['fecha','culm','tit'], true) && $value > date('Y-m-d')) {
                throw new PortalError(422, 'La fecha de un registro histórico no puede ser futura.');
            }
        }
        if (in_array($field, ['cap','horas','cred','horasDoc','horasTotal','sem'], true)) {
            if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $value) || (float)$value > 10000) throw new PortalError(422, 'Número fuera de formato o rango.');
            if ($field !== 'cred' && str_contains($value, '.')) throw new PortalError(422, 'Debe ser un número entero.');
            $range = match ($field) {
                'cap' => [1,200],
                'sem' => [1,10],
                'horas' => $module === 'uds' ? [1,12] : [1,10000],
                'cred' => [1,8],
                default => [1,10000],
            };
            if ($value < $range[0] || $value > $range[1]) {
                throw new PortalError(422, 'Usa un valor entre '.$range[0].' y '.$range[1].'.');
            }
            return $value + 0;
        }
        if (in_array($field, ['dias','unidades','ciclos'], true)) {
            $items = array_map('trim', explode(',', $value));
            $valid = match ($field) {
                'dias' => PortalAcademic::DAYS,
                'ciclos' => PortalAcademic::CYCLES,
                default => $items,
            };
            if (in_array('', $items, true) || count($items) !== count(array_unique($items)) || array_diff($items, $valid)) {
                throw new PortalError(422, 'Lista de valores no válida o repetida.');
            }
            return $field === 'ciclos' ? implode(', ', $items) : $items;
        }
        if ($field === 'activo') {
            return match ($value) {
                '1' => true, '0' => false,
                default => throw new PortalError(422, 'Usa 1 para activo o 0 para inactivo.'),
            };
        }
        $valid = match ($field) {
            'dni' => preg_match('/^[0-9]{8}$/D', $value),
            'correo' => strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL),
            'usuario' => preg_match('/^[a-zA-Z0-9._-]{3,80}$/D', $value),
            'periodo' => PortalAcademic::validPeriod($value),
            'sec' => preg_match('/^[A-Z]$/D', $value),
            'desde','hasta' => preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value),
            'codigo','cod','num','programa','modulo' => preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._:\/-]{0,79}$/uD', $value),
            default => strlen($value) <= 1000 && !str_contains($value, '<') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value),
        };
        if (!$valid) throw new PortalError(422, 'Formato o longitud no válidos.');
        return in_array($field, ['correo','usuario'], true) ? strtolower($value) : $value;
    }

    public static function matches(array $rows, string $column, string $value): array
    {
        $normalized = PortalRules::normal($value);
        return array_keys(array_filter($rows, fn($row) =>
            PortalRules::normal((string)($row[$column] ?? '')) === $normalized));
    }

    public static function reference(array $rows, string $column, string $value): ?array
    {
        $matches = self::matches($rows, $column, $value);
        return count($matches) === 1 && empty($rows[$matches[0]]['deleted']) ? $rows[$matches[0]] : null;
    }
}
