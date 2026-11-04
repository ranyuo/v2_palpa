# Operación básica de la intranet

## Administración

Entra con la cuenta creada en la instalación. En «Mi panel» encontrarás los siete módulos funcionales y las herramientas de soporte. El enlace «Web institucional» abre la web pública existente de Palpa.

1. Revisa identidad y contacto en «Configuración de la intranet».
2. Completa programas, personal docente y la configuración académica: unidades, módulos, ambientes y periodos.
3. Configura áreas y tipos de trámite desde las herramientas de Mesa de Partes.
4. Registra alumnos y egresados en el maestro correspondiente.
5. Crea las cuentas, asígnales rol y vincúlalas con el alumno, docente, programa o empresa que les corresponda.
6. Revisa los permisos, la auditoría y los respaldos. No compartas cuentas administrativas.

Los perfiles del antiguo editor de imagen y transparencia no se ofrecen para crear nuevas cuentas. Las cuentas antiguas se conservan; el administrador puede reasignarlas a un perfil operativo apropiado.

## Usuarios académicos

- Secretaría gestiona los registros académicos que tiene asignados.
- Coordinación trabaja dentro del programa al que está vinculada.
- Docentes consultan horarios y actualizan su portafolio personal.
- Estudiantes y egresados consultan sus propios registros y utilizan empleabilidad según sus permisos.
- Dirección consulta los módulos autorizados.

La institución puede restringir los permisos de los roles desde «Usuarios y roles». La vinculación personal y la limitación al programa no se eliminan mediante una asignación de menús.

## Atención y empleabilidad

El personal autorizado recibe y deriva expedientes, registra acciones y responde reclamos. El ciudadano utiliza los formularios y códigos de seguimiento externos. Los adjuntos privados no se vuelven públicos por consultar un expediente.

Las empresas se registran desde Bolsa Laboral. Un responsable valida su registro y las ofertas antes de su publicación. Estudiantes y egresados completan su perfil y CV para postular; las empresas destinatarias acceden a las postulaciones que les corresponden.

## Acceso y soporte

«Mi cuenta y contraseña» permite cambiar la contraseña. Si una persona pierde su acceso o necesita una cuenta, debe contactar al administrador institucional. Las notificaciones de correo saliente requieren configuración posterior; su envío no se da por habilitado.

La web institucional conserva su administración independiente. Desde la intranet no se editan páginas, noticias, banners, TUPA, admisión ni matrícula de esa web.

## Configuración académica compartida y documentos

«Configuración académica general» reúne el catálogo único de programas, módulos formativos,
unidades didácticas, asignaciones docentes, ambientes, periodo y bloques de horarios.
Horarios conserva programación, validación y publicación; ya no mantiene maestros propios.
Los programas se editan en su catálogo institucional, integrado en la configuración. La malla
heredada que figuraba dentro de cada programa se conserva, pero no se mantiene como un
segundo maestro editable.

Cada unidad puede vincularse a un módulo del mismo programa. Las unidades existentes sin
vínculo aparecen como «Pendiente»; no se asignan por coincidencia de nombres ni ciclos.
Se conservan sus identificadores, ciclo, horas semanales y créditos. Las horas semanales de
programación se distinguen de las horas totales del módulo y las horas del documento.

Certificación modular, Constancias / Certificados y Grados y títulos tienen accesos separados.
Las certificaciones y títulos utilizan alumnos y maestros compartidos. Las constancias y
certificados de cursos pueden registrarse sin programa o módulo; cuando el titular ya es
alumno se puede seleccionar para recuperar sus datos.

Los documentos emitidos conservan sus datos aunque se actualice un maestro. Para corregir
una emisión, anula el registro de origen y crea uno nuevo con otro código. El documento
anulado permanece consultable con su estado. La consulta pública permite buscar por DNI,
nombre o código y presenta tres secciones imprimibles. Las búsquedas por persona requieren
la verificación de seguridad del servidor. No existe conexión con registros externos.

La actualización de compatibilidad es automática al cargar/guardar los datos: no recrea tablas
ni elimina unidades. En instalaciones anteriores a la versión de datos 14 se habilita gestión
de configuración académica para Secretaría y Coordinación, y consulta para Dirección;
Coordinación continúa limitada a su programa. Las restricciones explícitas de módulos de
cada rol siguen vigentes. Revisa la asignación institucional desde Usuarios y roles.

## Páginas públicas independientes

Cada servicio puede enlazarse directamente desde la web institucional:

| Servicio | Ruta pública |
| --- | --- |
| Mesa de partes virtual | `/mesa-de-partes.php` |
| Libro de reclamaciones | `/libro-de-reclamaciones.php` |
| Bolsa laboral | `/bolsa-laboral.php` |
| Consulta de documentos | `/certificados.php` |

Estas páginas muestran únicamente el servicio correspondiente: no incluyen el menú de la
intranet, la barra de otros servicios ni enlaces de navegación a los demás módulos.
Privacidad y términos se consultan en un diálogo dentro de la misma página. Los enlaces
anteriores por hash continúan funcionando con la presentación independiente.

Mesa de partes y Reclamaciones conservan registro, adjuntos, constancias y seguimiento.
Certificados reúne grados y títulos, constancias/certificados y certificación modular en una
sola consulta, con impresión. Los códigos QR apuntan a la página de verificación.

En Bolsa laboral, «Mi acceso laboral» utiliza las cuentas institucionales de estudiantes,
egresados y empresas. El perfil, CV, postulaciones y gestión empresarial se mantienen dentro
del servicio, con los permisos y validaciones del servidor. El registro de una empresa nueva
se realiza sin una sesión activa. La validación institucional de empresas y ofertas continúa
en la intranet. Las páginas públicas comparten la misma base y sesión: no crean maestros ni
cuentas duplicados.

Para actualizar una instalación existente, incluye los cuatro archivos PHP nuevos,
`standalone-services.js`, `index.php`, `portal-server.js`, `intranet.css` y la vista PHP actualizada.
No se requiere recrear la base ni modificar sus registros para habilitar estos accesos.

Para comprobar estos cambios en desarrollo, ejecuta primero la suite PHP base y luego
`node tests/browser-standalone-services.cjs`. Esta prueba utiliza exclusivamente cuentas QA
y una base desechable; verifica las cuatro entradas, enlaces anteriores, móvil, acceso y
cierre de sesión, y persistencia del perfil laboral sin abandonar Bolsa laboral.

## Correo receptor de Mesa de Partes

En **Configuración → Correo receptor de Mesa de Partes**, el administrador puede
cambiar la dirección que recibirá los avisos de nuevos trámites. El mismo ajuste
aparece en **Mesa de Partes → Configuración**; al cambiarlo en cualquiera de las
dos pantallas, se conserva un único valor.

Las instalaciones nuevas usan `ranjoumisael@gmail.com`. Las instalaciones
existentes conservan su dirección actual: el administrador debe cambiarla desde
la configuración. Este campo corresponde al destinatario institucional, no al
remitente `notificaciones@proyectos.pe` ni al correo que registra el solicitante.
El envío de la confirmación y del aviso inicial se implementa mediante una cola
SMTP privada. Consulta [Notificaciones por correo](NOTIFICACIONES-CORREO.md) para
habilitar Gmail Workspace desde **Configurar correo notificador (SMTP)**,
configurar los reintentos y probar la entrega. En ese formulario puedes guardar
el remitente y la contraseña de aplicación. Guardar únicamente el destinatario
de Mesa de Partes no activa SMTP.

Para validar el guardado, la sincronización entre pantallas y los permisos, ejecuta
`node tests/browser-mail-settings.cjs` después de `tests/intranet.php`, con el servidor
apuntando a la misma base desechable `_test`. La prueba restaura el correo original.

## Código y correlativo del expediente

En **Mesa de Partes → Configuración → Código y correlativo del expediente**, el
administrador puede cambiar el prefijo (hasta 40 mayúsculas, números y guiones),
la cantidad de dígitos (1 a 9) y el siguiente correlativo del año actual. La vista
previa muestra el resultado, por ejemplo `EXP-PALPA-2026-001250`.

El año se mantiene en el código y la secuencia es anual. El siguiente correlativo
puede adelantarse para continuar una numeración previa, pero no retroceder ni
alterar secuencias de otros años. Cambiar el prefijo no reinicia el correlativo.
Los ceros son de presentación: un número mayor a la cantidad de dígitos elegida
se muestra completo. Los expedientes existentes mantienen su número, cargo y
seguimiento; los nuevos correos y cargos utilizan el número asignado por el servidor.
El número de documento `DOC-AÑO-NÚMERO` conserva su secuencia independiente.

Pruebas en base desechable: `php tests/intranet.php`, `php tests/numbering.php` y
`node tests/browser-numbering.cjs`, con la configuración y el servidor de pruebas.

## Estado al enviar un trámite

Durante el envío de Mesa de Partes se muestra “Registrando trámite, espera un
momento…” y la etapa actual: carga de archivos o preparación del cargo. El
formulario queda bloqueado para evitar envíos repetidos y la interfaz sigue
respondiendo mientras espera al servidor, incluido su intento inicial de correo.
Al terminar aparece el cargo y se habilitan nuevamente los controles.

Si se pierde la respuesta, los datos permanecen en pantalla. Reintentar el mismo
formulario en esa página reutiliza la solicitud y recupera el cargo si ya estaba
registrado, sin crear otro expediente. El indicador no estima porcentajes ni
asegura un tiempo de respuesta de Gmail.

La regresión `node tests/browser-submission-progress.cjs` sobre el servidor de
pruebas simula espera, respuesta perdida y reintento, y comprueba el indicador,
el bloqueo de duplicados y la recuperación del cargo existente.

## Notificaciones del Libro de Reclamaciones y Bolsa Laboral

La configuración SMTP es común a todos los módulos. Completa en Configuración
el **correo responsable de reclamos** y el **correo responsable de Bolsa Laboral**.
El panel de notificaciones distingue módulo, evento y referencia. Programa
`portal-private/mail-worker.php` cada minuto para entregar seguimientos,
respuestas, aprobaciones, ofertas y postulaciones, además de los reintentos.

El registro de un reclamo presenta un indicador de proceso y permite reintentar
el mismo envío sin duplicarlo si se pierde la respuesta. Las acciones de atención
se notifican desde el servidor después de guardar; las notas internas no se envían.
El registro de empresas también muestra el estado de proceso. Las contraseñas y
los currículos permanecen privados y no se adjuntan a los avisos.

Actualiza el esquema a la versión 7 con `/actualizar.php` antes de usar el código
nuevo sobre una instalación existente. Conserva `config.php`, su `install_key` y
el archivo SMTP cifrado. Los destinatarios actuales de cada módulo se conservan.
