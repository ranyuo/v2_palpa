# Correcciones del control de calidad del 04/11/2026

Pruebas funcionales en producción con los catálogos del Excel de programas y el reporte de notas
(mínimo cuatro registros por módulo). Se corrigieron los defectos de código encontrados:

| Hallazgo | Corrección | Prueba |
| --- | --- | --- |
| Al anular un reclamo quedaba en «Recibido» y la bitácora no decía «Anulado». | El servidor fija el estado `Anulado` al anular y rechaza cambios de estado, derivación o respuesta sobre registros anulados (`Services.php`); la interfaz también lo marca. | `tests/qa-correcciones.php` |
| «Acceso denegado» afirmaba que el intento se auditaba, pero no llegaba al servidor. | `api/eventos` acepta `Acceso denegado` con módulo y ruta; la interfaz lo envía y queda en `intranet_audit`. | `tests/qa-correcciones.php`, `tests/browser-qa-correcciones.cjs` |
| Contraseñas temporales con prefijo fijo `DemoICA!` y sin cambio obligatorio. | Claves aleatorias `XXXX-XXXX-XXXX-XXXX`; las cuentas creadas o restablecidas por otra persona quedan marcadas y, hasta cambiar la clave, solo pueden abrir `/accounts.php`. La nueva clave debe ser distinta. | ambas |
| El registro de trámites y reclamos esperaba al SMTP (6–15 s si el servidor tardaba). | Primer intento SMTP diferido tras la respuesta; avisos de derivación y estado intentados al guardar. Con un SMTP que no responde, el registro baja de 15 s a 0,02 s. | `tests/qa-correcciones.php` y medición manual |
| El aviso por correo al derivar o cambiar de estado no se podía omitir. | Casillas editables (marcadas por defecto); la marca `sinAviso` no se persiste. | ambas |
| Detalles de interfaz. | El rol coordinador muestra el código del programa en lugar del ID interno; las tarjetas del panel de estudiantes y egresados usan textos de consulta. | `tests/browser-intranet.cjs` |

Pendientes de configuración (no requieren código): responsable del Libro de reclamaciones,
correos propios por área y receptor institucional de Mesa de Partes (hoy un Gmail personal).

Los reclamos anulados antes de esta versión conservan su estado anterior; se muestran como anulados
porque la interfaz usa la marca `anulado`.
