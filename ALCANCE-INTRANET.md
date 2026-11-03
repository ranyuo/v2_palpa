# Alcance de la intranet Palpa

La separación corresponde a la solicitud del usuario y a los módulos descritos en la sección VIII del documento «Solicitud de información Palpa 2026», aportado como referencia. El documento es una fuente de requisitos: sus menciones a entregas, comunicaciones, accesos o responsables no autorizan envíos, despliegues ni cambios en infraestructura.

## Web y aplicación

- La web institucional vigente conserva comunicación, historia, autoridades, oferta académica pública, noticias, admisión, matrícula, becas, transparencia, TUPA y documentos de gestión.
- `app` abre un acceso privado y muestra las herramientas según el usuario autenticado.
- No se modificó el repositorio `palpa` durante esta separación.
- No se editaron DNS, correo ni la web oficial. Tampoco se ha publicado o desplegado esta aplicación.

## Módulos y soporte

| Solicitud | Ruta de gestión | Dependencias |
| --- | --- | --- |
| 1.1 Horarios | `#/admin/ix-horarios` | Programas, unidades, docentes, disponibilidad, ambientes, ciclos y periodos |
| 1.2 Constancias y certificados | `#/admin/ix-certificados` | Maestro de alumnos, módulos formativos, notas y certificaciones |
| 1.3 Grados y títulos | `#/admin/ix-titulos` | Alumnos y egresados, registros institucionales de títulos |
| 2.1 Libro de reclamaciones | `#/admin/reclamos` | Usuarios responsables, áreas, parámetros e historial |
| 2.2 Mesa de partes | `#/admin/expedientes` | Áreas, trámites, responsables, adjuntos, cargos y estados |
| 2.3 Bolsa laboral | `#/admin/ix-bolsa` | Empresas, estudiantes, egresados, perfiles, CV y postulaciones |
| 2.4 Branding docente | `#/admin/cvdocente` | Personal docente, formación, experiencia, certificados y proyectos |

Usuarios, roles, auditoría, respaldos y configuración son herramientas transversales. La gestión de programas y de personal docente corresponde a catálogos operativos; no incluye los editores promocionales del portal.

## Accesos desde la web existente

La dirección final del alojamiento se decidirá durante el despliegue. A esa dirección se agregan estos fragmentos:

| Enlace | Fragmento |
| --- | --- |
| Intranet / iniciar sesión | `#/admin` |
| Directorio de servicios | `#/servicios` |
| Mesa de partes y seguimiento | `#/servicios/mesa-de-partes` |
| Reclamos y seguimiento | `#/servicios/libro-de-reclamaciones` |
| Validación de certificados y títulos | `#/servicios/consulta-certificados` |
| Bolsa laboral y registro de empresas | `#/servicios/bolsa-laboral` |
| Directorio profesional docente | `#/nosotros/plana-docente` |

La recepción externa no concede permisos de gestión: los registros se atienden dentro de la intranet. Estudiantes, egresados y empresas usan sus propias cuentas para las operaciones que requieren identificación. La validación de documentos y el seguimiento mantienen los controles de consulta y privacidad del servidor.

## Separación técnica

- `IntranetScope.php` define los módulos y restringe cambios de datos en el servidor, incluso con solicitudes manuales a la API.
- El bootstrap excluye las colecciones editoriales y conserva solo los textos legales y la descripción del directorio necesarios para los servicios.
- `intranet.js` configura el acceso, panel, navegación y rutas permitidas.
- El permiso de cada usuario sigue verificándose en PHP: el menú no constituye autorización.
- Las cuentas, los registros operativos, los adjuntos y los respaldos conservan las implementaciones existentes. Los datos editoriales heredados no se borran.
- Los formularios de contacto general y boletín del portal quedan deshabilitados.
- Bootstrap Grid 5.3.8 se sirve desde `assets/vendor/bootstrap/`; los componentes internos conservan sus estilos existentes.

## Límites de esta entrega

Esta modificación separa y prueba los módulos existentes; no acredita por sí sola el cumplimiento íntegro de cada literal del TDR. Antes de la entrega institucional deben revisarse el modelo de datos heredado (combina tablas SQL con estado JSON), las exportaciones y formatos exigidos, los permisos concretos de cada responsable, la información real, las notificaciones por correo y los procedimientos operativos. Capacitación, manuales institucionales definitivos y despliegue requieren coordinación posterior.

No se solicitaron ni configuraron credenciales de dominio, correo o hosting. No se enviaron comunicaciones a los contactos mencionados en el PDF.
