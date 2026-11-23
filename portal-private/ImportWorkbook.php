<?php
declare(strict_types=1);

final class ImportWorkbook
{
    private const SHEET = 'xl/worksheets/sheet1.xml';
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public static function template(array $columns): string
    {
        $cells = '';
        foreach ($columns as $i => $column) {
            $letter = chr(65 + $i);
            $cells .= '<c r="'.$letter.'1" t="inlineStr"><is><t>'.$column.'</t></is></c>';
        }
        $path = self::temporaryZip();
        try {
            $zip = new PharData($path, 0, null, Phar::ZIP);
            $zip['[Content_Types].xml'] = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/'.self::SHEET.'" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
            $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $relNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
            $zip['_rels/.rels'] = '<Relationships xmlns="'.$relNs.'"><Relationship Id="rId1" Type="'.$rel.'/officeDocument" Target="xl/workbook.xml"/></Relationships>';
            $zip['xl/_rels/workbook.xml.rels'] = '<Relationships xmlns="'.$relNs.'"><Relationship Id="rId1" Type="'.$rel.'/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.$rel.'/styles" Target="styles.xml"/></Relationships>';
            $zip['xl/styles.xml'] = '<styleSheet xmlns="'.self::NS.'"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
            $zip['xl/workbook.xml'] = '<workbook xmlns="'.self::NS.'" xmlns:r="'.$rel.'"><sheets><sheet name="Datos" sheetId="1" r:id="rId1"/></sheets></workbook>';
            $zip[self::SHEET] = '<worksheet xmlns="'.self::NS.'"><cols><col min="1" max="'.count($columns).'" width="20" style="1" customWidth="1"/></cols><sheetData><row r="1">'.$cells.'</row></sheetData></worksheet>';
            return file_get_contents($zip->getPath());
        } finally {
            self::removeTemporaryZip($path);
        }
    }

    private static function temporaryZip(): string
    {
        $directory = sys_get_temp_dir().'/palpa-'.bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('No se pudo preparar el archivo temporal.');
        }
        return $directory.'/workbook.zip';
    }

    private static function removeTemporaryZip(string $path): void
    {
        if (is_file($path)) unlink($path);
        rmdir(dirname($path));
    }

    private static function xml(PharData $zip, string $entry, int $max): SimpleXMLElement
    {
        if (!isset($zip[$entry]) || $zip[$entry]->getSize() > $max) {
            throw new PortalError(422, 'Falta una hoja válida o supera el tamaño permitido.');
        }
        $content = $zip[$entry]->getContent();
        if (strlen($content) > $max || str_contains($content, '<!') || str_contains($content, "\0")) {
            throw new PortalError(422, 'Contenido Excel no válido.');
        }
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            throw new PortalError(422, 'El contenido Excel no es válido.');
        }
        return $xml->children(self::NS);
    }

    public static function read(string $file, array $columns): array
    {
        $path = self::temporaryZip();
        try {
            if (filesize($file) > 2_000_000 || file_get_contents($file, false, null, 0, 4) !== "PK\x03\x04" || !copy($file, $path)) {
                throw new PortalError(422, 'El archivo supera los 2 MB o no se pudo leer.');
            }
            $zip = new PharData($path);
            $workbook = self::xml($zip, 'xl/workbook.xml', 100_000);
            if (count($workbook->sheets->sheet) !== 1) throw new PortalError(422, 'Usa un libro con una sola hoja.');
            $shared = [];
            if (isset($zip['xl/sharedStrings.xml'])) {
                foreach (self::xml($zip, 'xl/sharedStrings.xml', 4_000_000)->si as $item) {
                    $shared[] = self::string($item);
                }
            }
            $rows = [];
            $header = [];
            $last = 0;
            foreach (self::xml($zip, self::SHEET, 5_000_000)->sheetData->row as $row) {
                $number = (int)$row->attributes()['r'];
                if ($number <= $last || $number > 2001) {
                    throw new PortalError(422, 'Se permiten hasta 2000 filas de datos por archivo.');
                }
                $last = $number;
                $cells = [];
                $errors = [];
                foreach ($row->c as $cell) {
                    if (!preg_match('/^([A-Z]{1,3})'. $number .'$/D', (string)$cell->attributes()['r'], $match)
                        || isset($cells[$match[1]])) {
                        throw new PortalError(422, 'Identificador de celda incoherente en la fila '.$number.'.');
                    }
                    $letter = $match[1];
                    $type = (string)$cell->attributes()['t'];
                    $raw = (string)$cell->v;
                    $value = match ($type) {
                        'inlineStr' => self::string($cell->is),
                        's' => $shared[$raw] ?? '',
                        default => $raw,
                    };
                    if (isset($cell->f) || $type === 'e' || ($type === 's' && !isset($shared[$raw]))) {
                        $errors[$header[$letter] ?? $letter] = 'Reemplaza la fórmula o el error de Excel por un valor.';
                    } elseif (strlen($value) > 4000) {
                        $errors[$header[$letter] ?? $letter] = 'La celda supera 4000 bytes.';
                        $value = '';
                    }
                    $cells[$letter] = trim($value);
                }
                if ($number === 1) {
                    $header = array_filter($cells, fn($value) => $value !== '');
                    if ($errors || count($header) !== count($columns) || array_diff($columns, $header)) {
                        throw new PortalError(422, 'La fila 1 debe contener las columnas de la plantilla seleccionada.');
                    }
                    continue;
                }
                foreach ($cells as $letter => $value) {
                    if ($value !== '' && !isset($header[$letter])) {
                        $errors[$letter] = 'Columna ajena a la plantilla.';
                    }
                }
                if (array_filter($cells, fn($value) => $value !== '') || $errors) {
                    $values = [];
                    foreach ($header as $letter => $column) {
                        $values[$column] = $cells[$letter] ?? '';
                    }
                    $rows[$number] = ['values' => $values, 'errors' => $errors];
                }
            }
            if (!$header || !$rows) {
                throw new PortalError(422, 'Usa la plantilla y agrega al menos una fila de datos.');
            }
            return $rows;
        } catch (UnexpectedValueException|BadMethodCallException $error) {
            throw new PortalError(422, 'No se pudo leer el libro .xlsx.');
        } finally {
            self::removeTemporaryZip($path);
        }
    }

    private static function string(SimpleXMLElement $node): string
    {
        $text = (string)$node->t;
        foreach ($node->r as $part) {
            $text .= (string)$part->t;
        }
        return $text;
    }
}
