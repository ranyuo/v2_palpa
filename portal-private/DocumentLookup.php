<?php
declare(strict_types=1);

/**
 * Consulta de nombres por DNI o razón social por RUC mediante un proveedor externo.
 * El token se guarda solo en config.php y nunca se envía al navegador; la respuesta se reduce a los
 * campos que necesita el formulario de Mesa de Partes.
 *
 * Proveedor por defecto: MIGO API v2 (https://docs.migo.pe/v2).
 *   GET {base_url}/dni/XXXXXXXX     Authorization: Bearer TOKEN → {success, dni, nombre}
 *   GET {base_url}/ruc/XXXXXXXXXXX  Authorization: Bearer TOKEN → {success, ruc, nombre_o_razon_social, …}
 * Alternativa: 'provider' => 'decolecta' (antes apis.net.pe), con base_url https://api.decolecta.com/v1.
 */
final class PortalDocumentLookup
{
    private const NOT_FOUND = 'No se encontraron datos para ese documento. Completa los campos manualmente.';
    /** Partículas que forman parte de un apellido compuesto (DE LA CRUZ, DEL CASTILLO…). */
    private const PARTICLES = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'SAN', 'SANTA', 'VAN', 'VON', 'DA', 'DI', 'MC'];

    /** @var callable(string,array):array{0:int,1:string} */
    private $http;

    public function __construct(private readonly array $config, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'request'];
    }

    public function enabled(): bool
    {
        return !empty($this->config['enabled']) && trim((string)($this->config['token'] ?? '')) !== '';
    }

    public function find(string $type, string $number): array
    {
        $type = strtoupper(trim($type));
        $number = trim($number);
        if ($type === 'DNI') {
            if (!preg_match('/^\d{8}$/D', $number)) throw new PortalError(422, 'El DNI debe tener 8 dígitos.');
        } elseif ($type === 'RUC') {
            if (!preg_match('/^(10|20)\d{9}$/D', $number)) throw new PortalError(422, 'El RUC debe tener 11 dígitos y empezar con 10 o 20.');
        } else {
            throw new PortalError(422, 'Solo se pueden consultar DNI o RUC.');
        }
        if (!$this->enabled()) throw new PortalError(503, 'La consulta automática de documentos no está habilitada.');

        $decolecta = ($this->config['provider'] ?? 'migo') === 'decolecta';
        $base = rtrim((string)($this->config['base_url'] ?? ($decolecta ? 'https://api.decolecta.com/v1' : 'https://api.migo.pe/api/v2')), '/');
        $url = $decolecta
            ? $base.($type === 'DNI' ? '/reniec/dni' : '/sunat/ruc').'?numero='.$number
            : $base.($type === 'DNI' ? '/dni/' : '/ruc/').$number;
        [$status, $body] = ($this->http)($url, [
            'Accept: application/json',
            'Authorization: Bearer '.trim((string)$this->config['token']),
        ]);
        if ($status === 404 || $status === 422) throw new PortalError(404, self::NOT_FOUND);
        if ($status === 401 || $status === 403) throw new PortalError(503, 'El servicio de consulta de documentos rechazó la credencial configurada.');
        if ($status === 429) throw new PortalError(503, 'El servicio de consulta de documentos alcanzó su límite. Completa los campos manualmente.');
        if ($status < 200 || $status >= 300) throw new PortalError(503, 'El servicio de consulta de documentos no está disponible. Completa los campos manualmente.');
        $data = json_decode($body, true);
        if (!is_array($data)) throw new PortalError(503, 'El servicio de consulta de documentos devolvió una respuesta no válida.');
        if (($data['success'] ?? true) === false) throw new PortalError(404, self::NOT_FOUND);
        if (isset($data['data']) && is_array($data['data'])) $data = $data['data'];

        return $type === 'DNI' ? self::person($number, $data) : self::company($number, $data);
    }

    private static function person(string $number, array $d): array
    {
        $names = self::text($d['first_name'] ?? $d['nombres'] ?? '');
        $paternal = self::text($d['first_last_name'] ?? $d['apellidoPaterno'] ?? $d['apellido_paterno'] ?? '');
        $maternal = self::text($d['second_last_name'] ?? $d['apellidoMaterno'] ?? $d['apellido_materno'] ?? '');
        $surnames = trim($paternal.' '.$maternal);
        if ($names === '' && $surnames === '') {
            // MIGO devuelve el nombre completo en un solo campo, con el orden del padrón SUNAT:
            // APELLIDO PATERNO, APELLIDO MATERNO y NOMBRES.
            [$surnames, $names] = self::splitFullName(self::text($d['nombre'] ?? $d['full_name'] ?? ''));
        }
        if ($names === '' && $surnames === '') throw new PortalError(404, self::NOT_FOUND);
        return ['tipoDoc' => 'DNI', 'numDoc' => $number, 'nombres' => $names, 'apellidos' => $surnames];
    }

    /** @return array{0:string,1:string} [apellidos, nombres] */
    public static function splitFullName(string $full): array
    {
        $words = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 2) return [implode(' ', $words), ''];
        $surnames = [];
        for ($taken = 0; $taken < 2 && count($words) > 1; $taken++) {
            $surname = [];
            while (count($words) > 1 && in_array(mb_strtoupper($words[0]), self::PARTICLES, true)) $surname[] = array_shift($words);
            $surname[] = array_shift($words);
            $surnames[] = implode(' ', $surname);
        }
        return [implode(' ', $surnames), implode(' ', $words)];
    }

    private static function company(string $number, array $d): array
    {
        $name = self::text($d['nombre_o_razon_social'] ?? $d['razon_social'] ?? $d['razonSocial'] ?? $d['nombre'] ?? '');
        if ($name === '') throw new PortalError(404, self::NOT_FOUND);
        $address = self::text($d['direccion'] ?? '');
        if ($address === '-') $address = '';
        if (!isset($d['nombre_o_razon_social'])) {
            // Decolecta separa distrito, provincia y departamento; MIGO ya los incluye en «direccion».
            $place = array_filter(array_map(self::text(...), [$d['distrito'] ?? '', $d['provincia'] ?? '', $d['departamento'] ?? '']));
            if ($address !== '' && $place) $address .= ', '.implode(', ', $place);
        }
        return ['tipoDoc' => 'RUC', 'numDoc' => $number, 'razon' => $name, 'direccion' => mb_substr($address, 0, 160),
            'estado' => self::text($d['estado_del_contribuyente'] ?? $d['estado'] ?? ''),
            'condicion' => self::text($d['condicion_de_domicilio'] ?? $d['condicion'] ?? '')];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '') : '';
    }

    /** @return array{0:int,1:string} */
    public static function request(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) throw new PortalError(503, 'El servidor no tiene la extensión cURL para consultar documentos.');
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body === false) return [0, ''];
        return [$status, (string)$body];
    }
}
