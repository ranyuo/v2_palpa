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
        return $data;
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
