# Revisión conjunta de notificaciones de la intranet

La cuenta central SMTP y la cola persistente se comparten. El correo comunica
un evento confirmado en el servidor y remite a la plataforma para acceder a
información privada. No sustituye la autorización de los módulos ni su seguimiento.

| Proceso | Situación | Acción o recomendación |
| --- | --- | --- |
| Mesa de Partes: registro, cambios de estado, derivación y respuesta | Implementado | Solicitante y área receptora según el evento; conserva cargo y código |
| Libro de Reclamaciones: constancia, atención, respuesta y derivación | Implementado | Responsable configurable, ciudadano y área receptora; no enviar notas internas |
| Bolsa: registro y validación de empresas | Implementado | Empresa y responsable institucional; nunca enviar la contraseña elegida |
| Bolsa: revisión, publicación y cierre de ofertas | Implementado | Empresa y responsable de revisión; el cierre avisa a los postulantes existentes, sin enviar a todos los alumnos |
| Bolsa: registro y estado de postulaciones | Implementado | Postulante, empresa y responsable según el evento; sin adjuntar CV |
| Reclamos y trámites cerca del vencimiento | Pendiente, prioridad alta | Avisos programados al responsable; acordar anticipación, frecuencia y calendario hábil antes de implementarlos |
| Certificados y títulos emitidos o anulados | Pendiente, prioridad alta | Avisar al titular con referencia verificable; evitar DNI y notas en el mensaje |
| Cambios o publicación de horarios | Pendiente, prioridad media | Avisar solo a los alumnos y docentes afectados, agrupando cambios para evitar repetición |
| Nuevas ofertas para estudiantes y egresados | Pendiente, prioridad media | Suscripción voluntaria por programa; preferir un resumen periódico y opción de dejar de recibirlo |
| Creación, bloqueo y recuperación de cuentas | Pendiente, prioridad alta en seguridad | Avisos sin contraseñas; la recuperación necesita enlaces con token de un solo uso y caducidad, no enviar claves existentes |
| Portafolio docente y cambios de perfil | Pendiente, prioridad baja | Notificar únicamente solicitudes de revisión o decisiones de publicación, no cada edición |
| Fallos persistentes del correo y respaldos | Pendiente, prioridad operativa | Aviso al administrador; complementar con monitorización externa porque el propio SMTP puede estar caído |
| Noticias, admisión, campañas y newsletter del antiguo portal | Fuera del alcance de la intranet | Se mantienen deshabilitados; no reactivar ni crear envíos masivos desde estos módulos |

## Condiciones para operación

- Ejecutar `/actualizar.php` para el esquema 7 y conservar los ajustes privados.
- Revisar los correos de Reclamos, Bolsa y cada área receptora, además de la cuenta central.
- Ejecutar el trabajador de correo por cron cada minuto. Los registros iniciales
  intentan la entrega, pero las acciones de gestión se guardan en cola para que el
  usuario no espere a Gmail durante cada actualización.
- Consultar errores y reintentos desde el panel administrativo. «Enviado» indica
  aceptación SMTP, no confirmación de lectura ni llegada a la bandeja principal.
- La cola tiene identificadores por evento y destinatario; cambios repetidos sin
  evento no generan correos. Un evento posterior válido con otro estado sí genera
  una notificación nueva. Un fallo de correo no revierte la gestión confirmada.

Las recomendaciones pendientes no se han activado. Requieren las reglas de
negocio indicadas; esta revisión no configura recordatorios ni suscripciones.

## Reglas de Bolsa Laboral por acordar

El cierre notificado corresponde a un cambio de estado guardado. El vencimiento
por fecha y la suspensión de una empresa no cambian automáticamente el estado de
sus ofertas en esta implementación. Conviene definir si deben cerrar convocatorias
y con qué mensaje, antes de automatizar esos efectos y sus avisos.
