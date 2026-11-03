# Limpieza del repositorio app

Se retiraron 467 archivos que pertenecían al portal anterior y no son necesarios para la intranet Palpa.

| Medición de archivos versionados | Tamaño |
|---|---:|
| Antes | 246.535.878 bytes (246,5 MB) |
| Después de retirar los archivos | 19.516.393 bytes (19,5 MB) |
| Retirado | 227.019.485 bytes (227,0 MB), aproximadamente 92 % |

Las cifras posteriores a la documentación de esta limpieza pueden variar unos pocos KB. No incluyen `.git`, configuración local ni resultados generados.

## Material retirado

- Activos públicos de Chincha y Nasca.
- Capturas, PDF, exportaciones y reportes históricos de `docs/qa/` y `evidencias/`.
- ZIP antiguos de `entregables/`.
- Pruebas del portal completo y de importaciones de otros institutos.
- Importadores retirados `NascaContent.php` y `PalpaContent.php`, sus inventarios históricos de Chincha/Nasca y documentos obsoletos.

## Material conservado

- Recursos oficiales de Palpa y bibliotecas locales.
- Aplicación PHP/JavaScript, esquema SQL, plantilla de instalación y configuración de ejemplo.
- Datos fuente y trazabilidad de Palpa.
- Guías vigentes de instalación, operación, alcance y actualización.
- Las cinco pruebas actuales: `intranet.php`, `browser-intranet.cjs`, `area-compatibility.php`, `browser-areas.cjs` e `inactive-routing.php`.

Las configuraciones privadas, los datos del hosting, las cuentas y los adjuntos de usuarios no se modificaron. La limpieza se realiza en el repositorio app; no elimina archivos del sitio publicado ni del repositorio palpa.

## Recuperación y tamaño de Git

El material retirado tiene una copia verificada por SHA-256 fuera del checkout, en `/workspace/archivo-app-limpieza`, con un manifiesto. También permanece recuperable desde los commits anteriores.

No se reescribió el historial de Git. Por eso `.git` puede seguir ocupando espacio aunque el código actual y las descargas ZIP sean mucho menores. La carpeta `.git` no se sube a Hostinger. Los ZIP y evidencias nuevos se generan fuera del checkout; sus carpetas históricas se añadieron a `.gitignore` para evitar incorporarlas otra vez.

## Validación

Se comprobaron 53 referencias estáticas de recursos sin archivos ausentes. La regresión usa la base local desechable `app_test`, con las mismas cinco pruebas documentadas en README. Las cinco suites completaron 157 comprobaciones aprobadas (45 + 61 + 14 + 31 + 6), sin errores JavaScript en las suites web. Los resultados completos de esta ejecución se guardan junto al archivo externo de limpieza.
