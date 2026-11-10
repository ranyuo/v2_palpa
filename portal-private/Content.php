<?php
declare(strict_types=1);

/** Compatibilidad de catálogos; conserva identificadores y registros operativos. */
final class PortalContent
{
    public static function forJson(array $payload): array
    {
        if (isset($payload['data']['academico'])) {
            $payload['data']['academico']['publicados'] = (object)($payload['data']['academico']['publicados'] ?? []);
        }
        return $payload;
    }

    public static function normalize(array $data): array
    {
        if(($data['__v']??0)<14){
            $data['academico']['perms']['acad']['config']=2;
            $data['academico']['perms']['dir']['config']=1;
            $data['__v']=14;
        }
        foreach ($data['academico']['areas'] ?? [] as $i => $area) {
            $data['academico']['areas'][$i]['nivel'] = self::areaLevel($area);
        }
        foreach ($data['tupa'] ?? [] as $i => $row) {
            $categories = ['6.- CONSTANCIA Y CERTIFICADOS' => '6.- CONSTANCIAS Y CERTIFICADOS',
                '12.-ALQUILERES' => '12.- ALQUILERES'];
            if (isset($categories[$row['categoria'] ?? ''])) {
                $data['tupa'][$i]['categoria'] = $categories[$row['categoria']];
            }
            $known = ['4.1' => ['4.1 ATRÍCULA REGULAR', '4.1 MATRÍCULA REGULAR'],
                '10.6' => ['10.6 CURSO DE OFIRMÁTICA Y/O EXCEL (PRESENCIAL) - MES 3',
                    '10.6 CURSO DE OFIMÁTICA Y/O EXCEL (PRESENCIAL) - MES 3']];
            $fix = $known[(string)($row['codigo'] ?? '')] ?? null;
            if ($fix && ($row['denominacion'] ?? '') === $fix[0]) {
                $data['tupa'][$i]['denominacion'] = $fix[1];
            }
        }
        if(isset($data['academico'], $data['certificados'])&&class_exists(AcademicDocuments::class)){
            AcademicDocuments::sync($data);
            foreach(['certs'=>'cert','titulos'=>'tit'] as $key=>$prefix)foreach($data['academico'][$key] as &$row){
                $doc=PortalPolicy::find($data['certificados'],'ref','ix:'.$prefix.':'.$row['id']);if(!$doc)continue;
                $row+=['institucion'=>$doc['institucion']??'','estadoDoc'=>$doc['estado']??'Válido'];
                if($key==='certs')$row+=['horasDoc'=>$doc['horas']??'','tipoHoras'=>$doc['tipoHoras']??'Académicas'];
                else $row+=['denominacion'=>$doc['tipo'],'nivel'=>$doc['nivel']??'Superior tecnológico','pais'=>$doc['pais']??'Perú','region'=>$doc['region']??'Ica'];
            }unset($row);
        }
        return self::fitLimits($data);
    }

    /** Recorta los textos cargados que superan el límite del formulario; si no, el registro no se puede volver a guardar. */
    private static function fitLimits(array $data): array
    {
        // Algunas migraciones normalizan antes de cargar las reglas de formularios; sin ellas no hay límites que aplicar.
        if (!class_exists(PortalPolicy::class)) return $data;
        foreach (PortalPolicy::defaults()['specs'] as $spec) {
            $key = $spec['collection'] ?? '';
            if (!isset($data[$key]) || !is_array($data[$key]) || !array_is_list($data[$key])) continue;
            foreach ($spec['fields'] as $field) {
                $max = $field['max'] ?? null;
                if (!is_int($max) || !in_array($field['type'] ?? 'text', ['text', 'textarea'], true)) continue;
                foreach ($data[$key] as $i => $row) {
                    $value = is_array($row) ? ($row[$field['name']] ?? null) : null;
                    if (!is_string($value) || str_contains($value, '<') || preg_match_all('/./us', $value) <= $max) continue;
                    if ($key === 'personas' && $field['name'] === 'titulo') $value = preg_replace('/\bMag\./u', 'Mg.', $value);
                    $value = self::cut($value, $max);
                    if (!empty($field['unique']) && in_array(strtolower(trim($value)), array_map(fn($other) => strtolower(trim((string)($other[$field['name']] ?? ''))), $data[$key]), true)) continue;
                    $data[$key][$i][$field['name']] = $value;
                }
            }
        }
        return $data;
    }

    private static function cut(string $text, int $max): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($chars) <= $max) return $text;
        $short = rtrim(implode('', array_slice($chars, 0, $max - 1)));
        $word = preg_replace('/\s+\S*$/u', '', $short);
        if ($word !== '' && preg_match_all('/./us', $word) >= $max * 0.6) $short = $word;
        return rtrim($short, " ,;:.") . '…';
    }

    public static function areaLevel(array $area): string
    {
        $level = trim((string)($area['nivel'] ?? ''));
        return match ($level) {
            'Oficina' => match ($area['cod'] ?? '') {
                'MP' => 'Mesa de Partes',
                'DG' => 'Dirección',
                default => 'Áreas',
            },
            '', 'Área' => 'Áreas',
            'Unidad' => 'Unidades',
            'Programa' => 'Coordinaciones de programas de estudio',
            default => $level,
        };
    }
}
