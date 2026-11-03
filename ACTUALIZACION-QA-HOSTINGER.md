# Actualización QA de la intranet Palpa

El ZIP de actualización se aplica a la instalación existente de `ranyuo/app` basada en la versión `615a32f`. Conserva cuentas, configuración privada, adjuntos, respaldos y base de datos.

## Qué se corrige

- Mesa de Partes, Reclamaciones y el directorio de áreas muestran clasificaciones heredadas y personalizadas.
- Las instalaciones anteriores reconocen los niveles Oficina, Área, Unidad y Programa sin cambiar códigos ni relaciones. La carga inicial ya incluye niveles compatibles.
- Configuración académica ofrece las pestañas Áreas receptoras y Tipos de trámite; sus permisos coinciden con los del servidor.
- El servidor rechaza nuevos envíos o derivaciones a destinos inactivos, conservando el historial de expedientes antiguos.
- Reclamaciones muestra el contacto institucional cuando no hay correo específico; guardar el correo de reclamos en la configuración general actualiza también el módulo.

## Subida a Hostinger

1. Conserva una copia del código anterior y una copia completa de base de datos y adjuntos. En el sitio publicado ya se creó un respaldo antes de corregir el catálogo de áreas.
2. Descomprime el ZIP de **actualización**. Copia `public_html/intranet.js` sobre el archivo existente y el contenido de `portal-private/` sobre la carpeta privada hermana de `public_html/`.
3. Reemplaza únicamente los archivos incluidos. No borres otras carpetas ni subas `portal-private` dentro de la carpeta pública.
4. No sustituyas `portal-private/config.php` y no importes ninguna base de datos. El ZIP no incluye credenciales ni configuraciones privadas.
5. **No ejecutes `install.php`**. Esta corrección no requiere reinstalar ni ejecutar el actualizador de esquema: la compatibilidad se aplica al leer los datos.
6. Recarga la intranet con Ctrl+F5. Abre Mesa de Partes, selecciona un destino, adjunta un PDF y verifica el cargo. Desde la intranet, comprueba la derivación y el seguimiento.

El archivo `portal-private/data/portal.json` es la plantilla para futuras instalaciones: reemplazar ese archivo no importa ni reemplaza los registros de una instalación existente.

## Estado del sitio publicado

Se normalizaron las clasificaciones de nueve áreas institucionales y tres áreas QA mediante la sesión autorizada, con respaldo previo y auditoría. Se conservaron los códigos, los responsables existentes y los expedientes. Esto desbloqueó el formulario público del sitio sin necesidad de reinstalar.

Tres expedientes QAFIX nuevos se enviaron íntegramente por el formulario público, se derivaron por la interfaz administrativa y terminaron Atendidos, con seguimiento público correcto:

- MPV-IESPALPA-2026-000004
- MPV-IESPALPA-2026-000005
- MPV-IESPALPA-2026-000006

Las mejoras de código y del editor de áreas incluidas en este paquete todavía deben subirse a Hostinger. No hay acceso de transferencia de archivos o despliegue de Hostinger disponible en esta sesión.

## Responsable y correo

El responsable de Reclamaciones indicado por el administrador ya se configuró y verificó en el sitio publicado. Se guarda en la base, sin incorporar datos personales al código de instalación. Se admiten nombres válidos de dos o más caracteres. La entrega de correo requiere configurar el servicio de envío; esta actualización no crea credenciales SMTP ni habilita notificaciones salientes.
