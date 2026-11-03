# Actualización de mejoras posteriores al plan de pruebas

Estas mejoras surgen de la ejecución del plan de pruebas del 30/10/2026 en el sitio publicado. Se aplican sobre la instalación existente y conservan cuentas, configuración privada, adjuntos y base de datos. No cambian el esquema.

## Qué cambia

- **Mensajes de validación legibles.** Los rechazos indican el campo y el límite, por ejemplo «Revisa la contraseña: debe tener como mínimo 12 caracteres.», «Revisa la nota: debe estar entre 0 y 20.» o «El DNI del alumno «70001001» ya está registrado.».
- **Disponibilidad docente al guardar.** Una clase fuera de los días u horas disponibles del docente se rechaza al guardarla, no solo al publicar. Los cruces se siguen revisando al publicar. Las clases existentes no se revisan hasta que se editen.
- **Nombre del respaldo.** La copia de seguridad se descarga como `intranet-palpa-AAAAMMDD-HHMMSS.sql`.
- **Pruebas de navegador.** Aceptan `CHROMIUM_PATH` para indicar el ejecutable de Chromium; si no se define, usan `/usr/bin/chromium`.

## Subida a Hostinger

1. Respalda el código actual y descarga una copia de seguridad desde la intranet.
2. Sube estos archivos sobre los existentes, respetando sus carpetas:
   - `portal-private/Rules.php`
   - `portal-private/Portal.php`
   - `portal-private/Academic.php`
   - `portal-private/Domain.php`
   - `public_html/backup.php`
3. No sustituyas `portal-private/config.php`, no importes ninguna base de datos y **no ejecutes `install.php` ni `actualizar.php`**: no hace falta reinstalar.
4. Recarga la intranet con Ctrl+F5 y comprueba, por ejemplo, que cambiar la contraseña a una de 8 caracteres muestra el mensaje nuevo.

Los archivos de `tests/` no se suben al hosting.

## Cambio de nombres internos (`pisco_*` → `intranet_*`)

Se retiran los nombres heredados del portal de Pisco. Requiere subir el código completo, pero **no reinstalar**.

### Qué cambia

- **Tablas.** Las tablas `pisco_*` pasan a llamarse `intranet_*`. El renombrado es automático: la primera visita después de subir el código las renombra en una sola operación (`RENAME TABLE`), con todos sus datos, cuentas y archivos. No hace falta ejecutar `actualizar.php`.
- **Sesión.** La cookie pasa a ser `INTRANETSESSID`: todos los usuarios inician sesión de nuevo una vez.
- **Respaldos portables.** Los nuevos se guardan como `.intranetbak`. Los `.piscobak` anteriores siguen apareciendo en Respaldos y se pueden restaurar.
- **Navegador.** La preferencia de tema claro u oscuro se traslada sola a la clave nueva.
- **Sin cambio.** El identificador `pisco` del tema visual se conserva porque es un dato de configuración; su nombre visible es «Palpa institucional».

### Subida a Hostinger

1. En la intranet, descarga una copia de seguridad (Respaldos) y guarda también el respaldo SQL completo.
2. Sube el contenido de `portal-private/` (excepto `config.php`) y de `public_html/` sobre los existentes. No subas `tests/`.
3. No ejecutes `install.php` ni `actualizar.php` y no importes ninguna base.
4. Abre la intranet e inicia sesión. En phpMyAdmin deben verse solo tablas `intranet_*`.

El usuario de la base debe tener permiso para renombrar tablas (ALTER, CREATE y DROP), que Hostinger concede por defecto. Si no lo tuviera, la intranet muestra el error y las tablas `pisco_*` quedan intactas.

Un respaldo SQL descargado antes de este cambio contiene tablas `pisco_*`. Para restaurarlo, elimina primero las tablas `intranet_*` e importa el archivo: la intranet las renombrará en la siguiente visita.
