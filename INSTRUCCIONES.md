# Instalación y operación de la intranet Palpa

Consulta [README.md](README.md) para requisitos, instalación y pruebas; [ALCANCE-INTRANET.md](ALCANCE-INTRANET.md) para los módulos y enlaces desde la web existente; y [OPERACION-INTRANET.md](OPERACION-INTRANET.md) para el uso básico.

Usa `public_html/` como raíz pública y conserva `portal-private/` fuera de ella. La aplicación debe tener su propia raíz web. Necesita PHP 8.2+, PDO MySQL y MySQL/MariaDB; no requiere Composer ni compilación.

Configura una base propia y una clave privada en `portal-private/config.php`. Usa HTTPS, crea tu administrador mediante `/install.php` y completa usuarios, vínculos y catálogos. No subas archivos de prueba ni configuraciones locales al hosting. Retira instaladores y recuperadores cuando dejen de ser necesarios.

En una instalación existente, respalda base y archivos antes de actualizar el código. La aplicación mantiene los datos heredados y deja de exponer su gestión editorial; no requiere borrar ni importar otra carga pública. `/actualizar.php` conserva las cuentas y actualiza el esquema.

El destino de despliegue, los responsables institucionales y el correo saliente se configuran por separado. No se modifican DNS, cuentas de correo ni la web pública como parte de esta adaptación.
