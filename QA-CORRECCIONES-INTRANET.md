# Verificación de las correcciones de QA

La incidencia QA-01 tenía una misma causa para los cuatro fallos del informe anterior: las áreas existentes usaban Oficina, mientras el selector solo admitía una lista fija de clasificaciones.

## Resultado en el sitio publicado

- Respaldo completo previo y normalización de 12 clasificaciones (9 institucionales y 3 QA), sin cambiar códigos ni referencias.
- 9 comprobaciones aprobadas: tres envíos por formulario público con PDF, tres derivaciones por interfaz y tres comprobaciones de atención/consulta pública.
- Expedientes QAFIX: MPV-IESPALPA-2026-000004, 000005 y 000006, todos Atendidos.
- Sin errores JavaScript en esos recorridos. No se reinstaló ni se restauró la base publicada.

La corrección de datos del sitio ya está aplicada. Las mejoras del código se entregan en GitHub y en el ZIP para subirlas a Hostinger; no se afirma que esos archivos se hayan desplegado.

## Verificación local del código

| Prueba | Resultado |
|---|---:|
| `tests/intranet.php` | 45 comprobaciones aprobadas |
| `tests/browser-intranet.cjs` | 61 comprobaciones aprobadas |
| `tests/area-compatibility.php` | 14 comprobaciones aprobadas |
| `tests/browser-areas.cjs` | 31 comprobaciones aprobadas |
| `tests/inactive-routing.php` | 6 comprobaciones aprobadas |

Se cubren la normalización idempotente sin perder referencias, áreas personalizadas y desactivadas, edición del catálogo, tres envíos públicos con PDF y sus derivaciones, contactos del Libro de Reclamaciones y sincronización del correo. La regresión general cubre módulos, perfiles, restricciones y tamaños móvil/escritorio. La sintaxis PHP/JavaScript y `git diff --check` también se verificaron.

Las suites locales usan exclusivamente `app_test`. El responsable y su correo se configuraron en el sitio publicado con los datos proporcionados por el administrador y se verificó su visualización pública. La regresión incluye un nombre válido de cuatro caracteres. No se habilitaron ni se probaron envíos de correo.
