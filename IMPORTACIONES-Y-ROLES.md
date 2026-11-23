# Importaciones y roles de la intranet de Palpa

## Actualizar una instalación existente

Copia `public_html/` y `portal-private/` del paquete sobre los archivos de la intranet y conserva la configuración local, las credenciales y la base de datos. Prepara una copia previa de archivos y MySQL. Abre `/actualizar.php` y ejecuta la actualización con la clave de instalación. Este paso usa `PortalSchema::apply()` para conservar los datos y cuentas existentes y registrar la versión de esquema 8. El procedimiento añade a `intranet_state` cuatro catálogos vacíos cuando aún no existen. Los roles se guardan en `intranet_settings` y la auditoría en `intranet_audit`. La actualización no requiere ejecutar un SQL adicional a mano.

El lector de Excel requiere PHP 8.1 o posterior, Phar con soporte ZIP, SimpleXML y zlib. Habilita `file_uploads` y configura `upload_max_filesize` y `post_max_size` con margen para recibir archivos de 2 MB. Verifica que PHP pueda escribir en su directorio temporal.

## Operar la carga

En **Mi panel → Importar desde Excel**, selecciona el catálogo y descarga su plantilla. Conserva una sola hoja con la primera fila de encabezados. Usa los códigos de catálogo para los vínculos. Las fechas y horas se escriben como texto, en formato `AAAA-MM-DD` y `HH:MM`. Las listas de días, ciclos y unidades se separan por comas. Las celdas opcionales vacías mantienen el valor anterior si se autoriza una actualización. Para programas nuevos sin modalidad indicada se usa Presencial.

| Módulo | Clave y referencias |
| --- | --- |
| Maestro de alumnos | DNI de ocho dígitos, código del programa, periodo de ingreso y grupo. El alta y los cambios de grupo requieren periodo, ciclo, sección y turno previamente registrados. |
| Matrículas y grupos | DNI y periodo. Requiere programa, ciclo, sección y turno existentes. Actualiza el grupo operativo cuando el periodo coincide con el vigente. Conserva el periodo de ingreso. Un usuario institucional autorizado puede cargar una matrícula de un periodo anterior en otro programa sin alterar la ficha actual. |
| Docentes | DNI de la ficha personal. Si se indican unidades, usa sus códigos. La ficha nueva queda en Borrador. Si ya existe la ficha personal y falta la carga docente, usa los datos vigentes de esa ficha para vincularla sin cambiarla. |
| Certificaciones modulares | Número de certificación, DNI del alumno y módulo del mismo programa. |
| Constancias y certificados | Código institucional del documento. Registra la denominación del curso o actividad, datos del titular y de la emisión. |
| Grados y títulos | Código de registro, DNI de un egresado o titulado, fechas y resolución. |
| Configuración académica | Plantillas independientes para programas, ciclos, módulos, unidades, periodos y secciones. Carga programas y periodos primero, luego ciclos, módulos, unidades y secciones. |
| Ambientes y recursos | Código del ambiente. El programa es opcional cuando el uso es compartido. |
| Usuarios y cuentas | Usuario único y DNI de una ficha de alumno o docente. Usa `estudiante`, `egresado` o `docente` según corresponda. Entrega cada clave temporal a su titular. La aplicación exige cambiarla en el primer ingreso. |

La vista previa identifica filas nuevas, existentes, actualizables y observadas. El CSV de errores detalla la fila, la columna y el motivo. La confirmación omite las filas observadas y conserva los registros existentes por defecto. Las actualizaciones requieren un permiso y una confirmación expresa. Los registros de documentos históricos y las cuentas existentes conservan sus datos en una recarga.

Cada confirmación procesa las filas aceptadas en grupos de 100 dentro de una única transacción MySQL. Si falla el guardado, la transacción revierte todos sus cambios. Si cambió el estado académico después de la vista previa, hay que validarla nuevamente. La auditoría registra el usuario, la fecha, el nombre del archivo y el resultado. Incluye el SHA-256 cuando se lee el archivo y los totales al validar o confirmar. Los rechazos por extensión o por carga incompleta también se registran. Las claves temporales se muestran una vez y se guardan como hashes en MySQL.

Se admiten hasta 2 000 filas por archivo, con un límite de 200 para cuentas por el costo de generar las contraseñas. En matrículas, ciclos, periodos y secciones, **Consultar registros del catálogo** permite verificar los datos guardados. Los periodos históricos que se importen siguen disponibles para vincular secciones y matrículas. El periodo vigente se elige en la configuración académica habitual.

## Administrar perfiles

En **Usuarios y roles → Editar roles y permisos**, crea el perfil con un código único que empiece por `rol-`. El ámbito base define qué información puede abarcar el perfil. Activa los módulos que debe consultar, selecciona los que puede gestionar y asigna por catálogo los permisos de importar nuevos y de actualizar por Excel. Se mantienen las reglas de cada módulo, incluida la protección de documentos emitidos.

Los perfiles de programa requieren asignar un programa en la ficha de la cuenta. Los perfiles de área requieren un área activa y pueden consultar o gestionar expedientes y reclamos de esa área según los permisos concedidos. Cuando cambia la configuración de un rol, los usuarios afectados deben volver a iniciar sesión.

## Verificación técnica

La prueba `tests/bulk-import.php` usa una base desechable con sufijo `_test`, preparada por `tests/intranet.php`. Verifica las plantillas, altas, duplicados, permisos, restricciones por programa, reinicios de la vista previa, protección de fichas docentes, matrícula histórica de otro programa, auditoría de rechazos y verificación pública de un título histórico.

```sh
PORTAL_CONFIG_FILE=/ruta/config.test.php bash tests/run.sh
```
