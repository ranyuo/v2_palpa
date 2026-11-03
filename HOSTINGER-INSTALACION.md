# Instalar la intranet Palpa en Hostinger

Este paquete corresponde a la aplicación independiente `ranyuo/app`. Es para una **instalación nueva** y no incluye cuentas, contraseñas ni datos operativos de prueba.

## 1. Preparar el alojamiento

Usa un sitio independiente para la intranet en el hosting PHP, con su propio dominio o dirección temporal asignada por Hostinger. La web actual de Palpa conserva su sitio y sus archivos.

En hPanel, selecciona PHP 8.2 o posterior y comprueba PDO MySQL, sesiones, Fileinfo, Mbstring y Zlib. Crea una base MySQL/MariaDB y un usuario con permisos sobre esa base. Guarda sus datos en privado.

La aplicación usa rutas desde la raíz del sitio. Este paquete está preparado para su propia raíz web, no para copiarlo sin adaptación dentro de una subcarpeta de la web existente.

## 2. Subir los archivos

Extrae el ZIP. Conserva esta distribución en el directorio del sitio:

```text
[directorio del sitio de la intranet]/
├── portal-private/
│   ├── config.example.php
│   ├── schema.sql
│   ├── data/
│   └── ...
└── public_html/                 ← raíz pública del sitio
    ├── .htaccess
    ├── index.php
    ├── install.php
    ├── intranet.js
    └── ...
```

Sube `portal-private/` junto a `public_html/`, al mismo nivel. Sube el contenido de `public_html/` a la carpeta pública de ese sitio, incluidos los archivos ocultos. `portal-private/` debe quedar fuera de la carpeta pública y conservar su propio `.htaccess`.

El paquete incluye los recursos activos de Palpa y las bibliotecas necesarias. Los recursos históricos de Nasca y Chincha no son necesarios para una instalación nueva y se excluyeron del ZIP. Para actualizar una base anterior que use esos archivos, conserva también sus recursos, adjuntos, configuración y respaldos existentes.

## 3. Configurar la base

Copia `portal-private/config.example.php` como `portal-private/config.php`. Edita:

- `db_host`: el servidor de MySQL indicado por hPanel.
- `db_port`: el puerto indicado por el proveedor, normalmente 3306.
- `db_name`, `db_user`, `db_password`: los datos de la base propia de la intranet.
- `install_key`: una clave privada aleatoria de al menos 32 caracteres.
- `secure_cookies`: `true`, con HTTPS habilitado.
- `timezone`: `America/Lima`.

Puedes generar la clave en una terminal PHP con `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Guárdala de forma privada y no la publiques ni la incluyas en Git.

No hace falta importar un volcado de datos: el instalador ejecuta el esquema y carga la configuración inicial. `schema.sql` forma parte del paquete para instalación y mantenimiento.

## 4. Instalar

Activa HTTPS para la dirección de la intranet. Abre `/install.php` en ese sitio, introduce la clave privada y crea tu cuenta de administrador. La primera cuenta la defines tú; no hay una contraseña predeterminada.

Comprueba que `/` muestre el inicio de sesión, entra a la intranet y revisa los siete módulos. La configuración de base debe permitir escritura desde PHP. El directorio privado debe permitir crear respaldos y los archivos de bloqueo de instalación cuando sean necesarios.

## 5. Configuración institucional

Completa programas, unidades, módulos, ambientes, periodos, alumnos, responsables, áreas y usuarios. Revisa los permisos y vínculos de cada cuenta. La carga inicial conserva la identidad verificada de Palpa; no contiene estudiantes, notas ni expedientes inventados.

Los formularios de recepción y seguimiento funcionan en la aplicación. El envío de correo requiere una configuración posterior; no se incluye una cuenta SMTP ni se afirma que las notificaciones de correo estén habilitadas.

Si se necesitan adjuntos grandes, ajusta los límites PHP de subida y de POST según el tamaño admitido por la aplicación y el plan, y verifica una carga real antes de habilitar el servicio institucional.

Después de comprobar el acceso, retira `install.php`, `actualizar.php` y `recuperar.php` del sitio cuando no sean necesarios.

## 6. Vincular desde la web existente

El enlace «Intranet» de la web oficial debe apuntar a la dirección de esta aplicación con el fragmento `#/admin`. Los enlaces directos de trámites y consultas están en `ALCANCE-INTRANET.md`.

## Actualizaciones futuras

Respalda archivos y base, conserva `config.php` y los archivos privados, y actualiza el código de la intranet. No ejecutes pruebas sobre la base real. Los respaldos se administran desde el módulo de seguridad; el script privado `cron.php` permite ejecutar respaldos programados mediante una tarea CLI configurada en hPanel.

`VERSION.txt` identifica el commit incluido. `SHA256SUMS.txt` permite verificar los archivos del paquete. Este documento no implica que se haya desplegado o configurado tu cuenta de Hostinger.
