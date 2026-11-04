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
4. Copia el bloque `smtp` de `portal-private/config.example.php` a tu configuración
   privada. Conserva los datos de base, `install_key` y demás valores actuales.
   Configura `enabled` como `true`. Usa:

   | Parámetro | Valor |
   | --- | --- |
   | `host` | `smtp.gmail.com` |
   | `port` | `587` (entero) |
   | `encryption` | `tls` (STARTTLS) |
   | `username` | `notificaciones@proyectos.pe` |
   | `from_email` | `notificaciones@proyectos.pe` |
   | `from_name` | Nombre visible de la institución |
   | `password` | Nueva contraseña de aplicación, sin los espacios de presentación |

   El ejemplo lee `PALPA_SMTP_PASSWORD` del entorno. Si tu plan no permite inyectar
   esa variable tanto en PHP web como en cron, asigna la nueva contraseña al campo
   `password` **solo en `portal-private/config.php`**, fuera de `public_html/` y
   excluido de Git. No se introduce desde la interfaz ni se entrega al navegador.
   Aplica permisos privados de lectura compatibles con el usuario PHP de tu plan.
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
2. En el panel **Notificaciones de trámites**, comprueba el remitente y el estado
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
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/mail.php
PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-mail-settings.cjs
```

`tests/mail.php` requiere Python 3 y el ejecutable OpenSSL. Inicia un SMTP local con
un certificado de prueba confiado explícitamente para ese servidor, usa credenciales
ficticias y comprueba entregas, autenticación fallida, certificado no confiable,
idempotencia, reintentos, permisos y reversión transaccional. No contacta Gmail.
Los artefactos se guardan en la carpeta ignorada `tests/.results/`.
