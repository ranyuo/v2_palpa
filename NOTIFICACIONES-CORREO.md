# Notificaciones de Mesa de Partes por Gmail Workspace

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
   La versión 6 del esquema crea `intranet_mail` sin borrar los expedientes.
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
   configúralo también en la tarea cron. El envío inicial se intenta al registrar
   el trámite; el cron procesa los avisos pendientes y sus reintentos.
6. Comprueba que PHP tenga OpenSSL, PDO MySQL, Ctype y las extensiones habituales
   de la aplicación. Hostinger debe permitir la conexión SMTP saliente. Si el
   puerto 587 está bloqueado, consulta las restricciones del plan; también se
   admite `encryption = ssl` con `port = 465`, manteniendo la validación TLS.

No se necesita instalar Composer: se distribuyen los componentes SMTP originales
fijados de PHPMailer 7.1.0 y su licencia. No copies archivos de `tests/` al hosting.

## Probar con tu instalación

1. Entra como administrador y revisa el destinatario institucional en Configuración.
2. En el formulario SMTP y el panel **Notificaciones de trámites**, comprueba el remitente y el estado
   de configuración SMTP. «Habilitado» confirma que hay configuración; no prueba
   que Google acepte las credenciales ni que el cron se esté ejecutando.
3. Presenta un trámite de prueba con un correo bajo tu control y un adjunto de
   prueba. Comprueba el número de expediente y descarga su cargo.
4. Actualiza el panel y comprueba dos filas, una para cada destinatario. Verifica
   ambos buzones, incluida la carpeta de spam.
5. Si aparece **Error**, revisa credenciales, conexión y destinatario. El botón
   **Reintentar** vuelve a encolar ese aviso para la siguiente ejecución del cron.
   Si sigue **Pendiente**, comprueba que SMTP esté habilitado y que cron funcione.

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

Esta implementación cubre el **registro inicial de trámites de Mesa de Partes**.
Los avisos por cambios de estado, derivaciones, reclamos y bolsa laboral todavía
no se envían por este transporte.

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

`tests/mail.php` requiere Python 3 y el ejecutable OpenSSL. Inicia un SMTP local con
un certificado de prueba confiado explícitamente para ese servidor, usa credenciales
ficticias y comprueba entregas, autenticación fallida, certificado no confiable,
idempotencia, reintentos, permisos y reversión transaccional. No contacta Gmail.
Los artefactos se guardan en la carpeta ignorada `tests/.results/`.
