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
