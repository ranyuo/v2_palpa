# Notificaciones institucionales por Gmail Workspace

Al registrar un **nuevo trámite**, el servidor guarda dos mensajes junto al
expediente: una confirmación al correo del solicitante y un aviso al correo
receptor de Mesa de Partes. El remitente central predeterminado es
`notificaciones@proyectos.pe`. No se adjuntan los documentos privados a los correos.

El destinatario institucional se cambia en **Configuración → Correo receptor de
Mesa de Partes**, o en **Mesa de Partes → Configuración**. Ambas pantallas usan
`config.mpv.correo`. Las instalaciones nuevas usan `ranjoumisael@gmail.com`; las
existentes conservan su valor hasta que el administrador lo cambie. Cambiar el
receptor afecta a los nuevos registros, no redirige mensajes ya encolados.

## Preparar Hostinger

1. Respalda la instalación y la base. Sube los archivos actualizados de
   `public_html/` y `portal-private/`, incluido `portal-private/vendor/phpmailer/`.
   Conserva tu `portal-private/config.php` y los archivos privados.
2. En instalaciones existentes, ejecuta `/actualizar.php` con la clave privada.
   La versión 7 del esquema amplía `intranet_mail` con módulo, origen y evento, sin borrar los expedientes ni los mensajes anteriores.
   En instalaciones nuevas, `/install.php` crea también esta tabla.
3. Revoca la contraseña de aplicación que se compartió en la captura. Genera una
   nueva desde la cuenta **notificaciones@proyectos.pe**. No uses la contraseña
   normal de Google ni publiques la contraseña de aplicación en Git o en el chat.
4. Entra como administrador a **Configuración de la intranet → Configurar correo
   notificador (SMTP)**. Pulsa **Usar Gmail Workspace**, introduce la nueva contraseña
   de aplicación, marca **Activar envío de notificaciones por correo** y pulsa
   **Guardar configuración SMTP**. El formulario contiene:

   | Campo | Valor para Gmail Workspace |
   | --- | --- |
   | Servidor SMTP | `smtp.gmail.com` |
   | Puerto | `587` |
   | Seguridad | STARTTLS |
   | Correo de acceso SMTP | `notificaciones@proyectos.pe` |
   | Correo remitente | `notificaciones@proyectos.pe` |
   | Nombre visible | Nombre de la institución |
   | Contraseña | Nueva contraseña de aplicación, sin espacios de presentación |

   La contraseña no vuelve al navegador: el campo queda vacío al guardar. Dejarlo
   vacío conserva la contraseña anterior si se mantienen el servidor y el usuario.
   Para cambiar de servidor o de cuenta y activar el servicio debes introducir
   una nueva contraseña. Desmarcar la activación pausa los envíos y conserva la cola.

   No necesitas editar `config.php` para configurar SMTP. El formulario guarda un
   archivo privado `.smtp-settings.json` al lado del archivo de configuración de
   la aplicación, cifrado con AES-256-GCM y permisos 0600. La clave se deriva de
   `install_key`: conserva esa clave y el archivo cifrado al actualizar o trasladar
   la instalación. Cambiar o perder la clave impide leer los ajustes guardados.
   La carpeta privada debe permitir escritura al usuario PHP. El cron y la web deben
   usar el mismo `config.php` (o el mismo `PORTAL_CONFIG_FILE`).

   El bloque `smtp` de `config.php` sigue siendo una alternativa para despliegues
   administrados; los ajustes guardados desde el formulario tienen prioridad.
   Si eliges esa alternativa, inyecta `PALPA_SMTP_PASSWORD` tanto en PHP web como
   en cron, o guarda la contraseña únicamente en el archivo privado excluido de Git.
5. En hPanel, configura una tarea cron cada minuto con el ejecutable PHP 8.2 o
   posterior de tu plan y la ruta absoluta de `portal-private/mail-worker.php`.
   Ejemplo orientativo (sustituye usuario, dominio y ejecutable reales):

   ```sh
   /usr/bin/php /home/USUARIO/domains/DOMINIO/portal-private/mail-worker.php
   ```

   No uses una URL pública para ejecutar el trabajador. Si usas `PORTAL_CONFIG_FILE`,
   configúralo también en la tarea cron. El envío inicial se intenta justo después de responder al registrar trámites, reclamos y empresas, y al guardar derivaciones o cambios de estado (la persona no espera al servidor SMTP); el cron
   procesa los avisos restantes, los seguimientos y los eventos de Bolsa Laboral.
   **El cron cada minuto es necesario para estos eventos y sus reintentos.**
6. Comprueba que PHP tenga OpenSSL, PDO MySQL, Ctype y las extensiones habituales
   de la aplicación. Hostinger debe permitir la conexión SMTP saliente. Si el
   puerto 587 está bloqueado, consulta las restricciones del plan; también se
   admite `encryption = ssl` con `port = 465`, manteniendo la validación TLS.

No se necesita instalar Composer: se distribuyen los componentes SMTP originales
fijados de PHPMailer 7.1.0 y su licencia. No copies archivos de `tests/` al hosting.

## Probar con tu instalación

1. Entra como administrador y revisa el destinatario institucional en Configuración.
2. En el formulario SMTP y el panel **Notificaciones de trámites**, comprueba el remitente y el estado
   de configuración SMTP. «Habilitado» confirma que hay configuración; por sí solo no prueba
   que Google acepte las credenciales ni que el cron se esté ejecutando.
3. Guarda la configuración y pulsa **Enviar correo de prueba** (debajo del formulario SMTP).
   Envía un mensaje real con la configuración guardada, sin pasar por la cola; si dejas vacío
   el destinatario se usa el correo de tu cuenta. Funciona aunque el envío aún no esté activado.
   El resultado indica si la cuenta fue rechazada (usuario o contraseña de aplicación), si no se
   pudo conectar (servidor, puerto, cifrado o red del hosting) o si se rechazó el destinatario.
   La prueba queda en la auditoría y se limita a 5 intentos cada 5 minutos.
4. Presenta un trámite de prueba con un correo bajo tu control y un adjunto de
   prueba. Comprueba el número de expediente y descarga su cargo.
5. Actualiza el panel y comprueba dos filas, una para cada destinatario. Verifica
   ambos buzones, incluida la carpeta de spam.
6. Si aparece **Error**, revisa credenciales, conexión y destinatario. El botón
   **Reintentar** vuelve a encolar ese aviso para la siguiente ejecución del cron.
   Si sigue **Pendiente**, comprueba que SMTP esté habilitado y que cron funcione.

### Alertas automáticas del panel

Con el envío activado, el panel muestra la **última ejecución del cron de correo**
(`mail-worker.php` la registra en cada corrida) y dos alertas:

- **Cron detenido:** hay avisos pendientes que debieron enviarse hace más de 15 minutos.
  La alerta indica cuántos son y la última ejecución registrada del cron (o que nunca se
  registró). Revisa la tarea cron de hPanel. Los avisos que agotaron sus 5 intentos no cuentan.
- **Cuenta SMTP rechazada:** dos o más avisos fallaron porque el servidor rechazó el usuario
  o la contraseña, y desde el último rechazo no se ha aceptado ningún envío. Suele ocurrir al
  revocar o cambiar la contraseña de aplicación de Google. Genera una nueva, guárdala y usa
  **Enviar correo de prueba**; al aceptarse un envío la alerta desaparece. Después pulsa
  **Reintentar** en los avisos con error.

El detalle de cada aviso con error distingue credenciales rechazadas, fallo de conexión y
destinatario rechazado; nunca guarda el diálogo SMTP ni la respuesta del servidor.

«Enviado» significa que SMTP aceptó el mensaje, no garantiza recepción en bandeja
principal. El fallo de uno de los destinatarios no bloquea al otro ni deshace el
trámite. Se reintenta hasta cinco veces, con espera creciente. Repetir la misma
solicitud no crea nuevos correos; cada mensaje conserva su Message-ID. Como en
otras colas SMTP, una caída entre la aceptación remota y el registro local puede
ocasionar una entrega repetida; no se garantiza entrega exactamente una vez.

Los cuerpos y destinatarios se guardan en la base privada. El panel de administración
muestra hasta 30 mensajes y no incluye contraseñas ni el diálogo SMTP. Los correos
entregados o cancelados se eliminan de la cola después de 30 días al procesarla;
los errores se conservan para revisión. La cola no se incluye en respaldos portables
ni se recrea desde expedientes anteriores; al restaurar un respaldo se cancela la
cola existente. Pausa cron durante restauraciones y mantenimiento de la base.

## Eventos implementados y destinatarios

Todos los mensajes usan la misma cuenta SMTP. Puedes usar el nombre visible
“Notificaciones · IES Público Palpa”; si ya tenías otro nombre guardado, puedes
cambiarlo desde el formulario SMTP.

| Módulo / evento | Destinatarios |
| --- | --- |
| Trámite recibido | Solicitante y Mesa de Partes |
| Trámite cambia de estado, se responde o se anula | Solicitante |
| Trámite se deriva a otra área | Solicitante y correo del área receptora |
| Reclamo o queja registrados | Ciudadano y responsables configurados de Reclamos |
| Reclamo cambia de estado, se responde o se anula | Ciudadano; la respuesta formal solo se envía a su correo |
| Reclamo derivado | Ciudadano y correo del área receptora |
| Empresa registrada desde la web o administración | Empresa y responsable de Bolsa Laboral; sin prometer acceso si todavía no hay cuenta habilitada |
| Empresa aprobada, rechazada o suspendida | Empresa |
| Oferta propuesta / por validar | Empresa y responsable de Bolsa Laboral |
| Oferta publicada o cambia de estado | Empresa |
| Oferta cerrada o rechazada con postulaciones existentes | Empresa y postulantes que no hayan retirado o eliminado su postulación |
| Postulación registrada | Postulante, empresa y responsable de Bolsa Laboral |
| Postulación cambia de estado | Postulante y empresa |

Los correos responsables se cambian en **Configuración de la intranet**. El de
Reclamos se comparte con **Responsable y plazos** del módulo, y el de Bolsa con
la configuración de Bolsa Laboral. Los destinatarios se resuelven en el servidor;
una cuenta sin permisos no puede originar una selección, aprobación o sus correos.

Los correos coincidentes reciben un solo mensaje por evento. Guardar sin cambiar
el evento no envía otro aviso. Las notas internas, las ediciones del perfil, los
currículos y los adjuntos privados no se envían por este canal. La postulación
usa el correo de la cuenta activa vinculada al estudiante, o el del maestro si
no existe cuenta vinculada. Si la cuenta ya estaba inactiva al generar el evento, no se encola ese aviso personal.

Si falta el correo institucional de Reclamos o Bolsa, el panel muestra una
advertencia y se conserva el registro y su notificación personal cuando existe
un correo válido. Configura los destinatarios antes de habilitar el uso real.
El panel **Notificaciones por correo** muestra módulo, evento y referencia, con
los estados reales y el botón de reintento para errores. Los indicadores heredados
que dependían del correo simulado no constituyen una confirmación de entrega.

Consulta [ANALISIS-NOTIFICACIONES.md](ANALISIS-NOTIFICACIONES.md) para los demás
procesos analizados y las recomendaciones pendientes.

## Pruebas de desarrollo sin enviar correos reales

Solo con una base desechable `_test` y `PORTAL_CONFIG_FILE` terminado en
`config.test.php`, con SMTP deshabilitado en esa configuración:

```sh
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/intranet.php
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/smtp-settings.php
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/mail.php
PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-mail-settings.cjs
```

`tests/smtp-settings.php` prueba el cifrado, los permisos y la conservación de la contraseña.
`tests/browser-smtp-form.cjs` prueba el formulario y restaura el archivo privado al terminar;
requiere `PORTAL_CONFIG_FILE` y `PORTAL_BASE_URL` de pruebas.

`tests/mail-alerts.php` (después de `tests/mail.php`) usa el mismo SMTP local para probar el
correo de prueba, la clasificación de fallos, la alerta de cron detenido con el registro real de
`mail-worker.php` y la alerta de cuenta rechazada. `tests/browser-mail-alerts.cjs` prueba el
formulario de prueba en el panel.

`tests/module-notifications.php` comprueba los eventos y permisos de Reclamos y Bolsa,
la privacidad, las transacciones y los duplicados.

`tests/mail.php` requiere Python 3 y el ejecutable OpenSSL. Inicia un SMTP local con
un certificado de prueba confiado explícitamente para ese servidor, usa credenciales
ficticias y comprueba entregas, autenticación fallida, certificado no confiable,
idempotencia, reintentos, permisos y reversión transaccional. No contacta Gmail.
Los artefactos se guardan en la carpeta ignorada `tests/.results/`.

## Revisión de compatibilidad y origen de los avisos

Los nuevos mensajes incorporan una huella del registro que los originó. Si el
registro se elimina, se sustituye o se reasigna de forma que ya no coincida esa
identidad, el trabajador cancela su aviso pendiente. La cola anterior de Mesa de
Partes conserva su vinculación por ID y referencia. El historial de mensajes ya
entregados permanece disponible según la retención prevista.

La revisión detallada está en [REVISION-NOTIFICACIONES.md](REVISION-NOTIFICACIONES.md).
La migración desde la cola anterior se prueba con `php tests/mail-migration.php`.

## Envío diferido y avisos opcionales

- El primer intento SMTP se ejecuta **después** de entregar la respuesta (`fastcgi_finish_request`
  o `litespeed_finish_request` en Hostinger). Si el servidor SMTP tarda o falla, el registro ya está
  confirmado y el mensaje queda en la cola con su error para que el cron lo reintente.
- Los avisos generados al guardar en la intranet (derivaciones, estados, bolsa laboral) también se
  intentan al terminar la respuesta, sin esperar al siguiente minuto del cron.
- En Mesa de Partes, las casillas **Enviar aviso por correo** de *Derivar expediente* y *Actualizar
  estado* vienen marcadas. Desmarcarlas omite el aviso solo para ese cambio (por ejemplo, una
  corrección interna). La marca no se guarda en el expediente.
