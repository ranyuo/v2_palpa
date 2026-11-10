# Intranet institucional del IES Público Palpa

`ranyuo/app` contiene la aplicación de **gestión académica y administrativa complementaria**. La institución conserva su [web pública](https://www.istpalpa.edu.pe/), desde la que puede enlazar a esta intranet y a sus formularios de servicios.

La entrada `/` abre el inicio de sesión. Cada usuario ve únicamente los módulos de su perfil. La aplicación no administra noticias, banners, admisión, TUPA ni las páginas de la web institucional. Las rutas de esos componentes están deshabilitadas y la API rechaza sus modificaciones, incluido el administrador. Los datos heredados se conservan en la base para evitar pérdidas.

## Módulos

| Área | Funciones |
| --- | --- |
| Horarios | Catálogos académicos, programación, validación de cruces, publicación y consulta por perfil |
| Constancias y certificados | Estudiantes, notas, certificaciones modulares y validación de documentos |
| Grados y títulos | Registro y consulta institucional de titulados |
| Mesa de partes | Recepción de documentos, adjuntos privados, cargo, derivación y seguimiento |
| Libro de reclamaciones | Registro, atención, respuesta e historial |
| Bolsa laboral | Empresas, validación de ofertas, perfiles, CV, postulaciones y seguimiento |
| Portafolio docente | Formación, experiencia, certificaciones, proyectos y publicación autorizada |

Programas, alumnos, docentes, unidades didácticas, ambientes y módulos formativos son catálogos de soporte. Usuarios, permisos, configuración, auditoría y respaldos son herramientas comunes.

Consulta [ALCANCE-INTRANET.md](ALCANCE-INTRANET.md) para la separación respecto a la web actual y los accesos que deben enlazarse desde ella, y [OPERACION-INTRANET.md](OPERACION-INTRANET.md) para el uso básico.

## Requisitos e instalación

PHP 8.2 o posterior, PDO MySQL, sesiones, Fileinfo, Zlib y MySQL/MariaDB. La cuadrícula responsive de Bootstrap 5.3.8 y las bibliotecas del cliente se sirven localmente; no se requiere Composer ni compilación.

1. Mantén `portal-private/` y `public_html/` al mismo nivel; la raíz pública debe ser `public_html/`.
2. Copia `portal-private/config.example.php` a `config.php`. Configura una base propia y una `install_key` privada aleatoria de al menos 32 caracteres.
3. Abre `/install.php` mediante HTTPS y crea tu administrador. No hay cuentas ni estudiantes de demostración en la carga inicial.
4. Asigna responsables, áreas, usuarios y permisos; registra progresivamente la información académica real.
5. Retira instaladores y recuperadores del hosting cuando dejen de ser necesarios. No publiques configuración, pruebas ni respaldos privados.

El despliegue y la vinculación desde la web oficial se coordinan por separado. La aplicación usa rutas desde la raíz: debe instalarse como aplicación independiente con su propia raíz web; su alojamiento concreto aún no se ha configurado. No requiere editar ni sustituir la web de Palpa.

La auditoría se conserva 24 meses por defecto: el cron (`cron.php` o `mail-worker.php`) borra una vez al día los registros más antiguos y deja constancia de la depuración. Se ajusta con `audit_retention_months` en `config.php` (mínimo 6; `0` conserva todo). Los textos iniciales más largos que el límite de su formulario se recortan al cargarse para que el registro pueda volver a guardarse. Si la base existe pero está vacía, la intranet indica abrir `/install.php`.

Los PDF se generan en el navegador con jsPDF 4.2.1 y jsPDF-AutoTable 5.0.9, servidos localmente; sus versiones, hashes y licencias están en `public_html/assets/vendor/` (`versions.json`, `FUENTES.md`) y `tests/vendor-audit.py` los verifica.

En una instalación existente, respalda archivos y base antes de actualizar el código. `/actualizar.php` conserva los datos y actualiza el esquema; la opción de reemplazar contenido público está deshabilitada en esta intranet.

## Pruebas

Solo sobre una base desechable terminada en `_test`, con `PORTAL_CONFIG_FILE` apuntando a `config.test.php`. La suite PHP **recrea tablas** y carga sus propios datos y usuarios QA.

```sh
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/intranet.php
PORTAL_CONFIG_FILE=/ruta/config.test.php php -S 127.0.0.1:8084 -t public_html
PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-intranet.cjs
```

Para correr todas las suites en el orden correcto (cada una sobre el estado que preparan `tests/intranet.php` y `tests/mail.php`):

```sh
PORTAL_CONFIG_FILE=/ruta/config.test.php bash tests/run.sh
PORTAL_CONFIG_FILE=/ruta/config.test.php CHROMIUM_PATH=/ruta/chromium bash tests/run.sh --browser
python3 tests/vendor-audit.py --advisories
```

GitHub Actions (`.github/workflows/qa.yml`) ejecuta lo mismo en cada push y pull request: integridad y avisos de seguridad de las bibliotecas del navegador, sintaxis, todas las pruebas PHP con MariaDB 10.11 en PHP 8.2 y 8.3, y todas las pruebas de navegador con Chromium. Usa una base y credenciales desechables propias del CI; no necesita secretos.

Las pruebas web requieren Playwright y Chromium externos; `CHROMIUM_PATH` indica el ejecutable (por defecto, `/usr/bin/chromium`). El servidor debe usar la misma base que la prueba PHP. En este entorno se dispone de los contenedores `app-php` y `app-db`, configuración privada en `/workspace/app-dev/config.test.php`, base `app_test` y Playwright en `/workspace/nasca-dev/tools/node_modules`.

```sh
docker exec -w /workspace/app app-php php tests/intranet.php
NODE_PATH=/workspace/nasca-dev/tools/node_modules PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-intranet.cjs
docker exec -w /workspace/app app-php php tests/area-compatibility.php
NODE_PATH=/workspace/nasca-dev/tools/node_modules PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-areas.cjs
docker exec -w /workspace/app app-php php tests/inactive-routing.php
docker exec -w /workspace/app app-php php tests/legacy-migration.php
docker exec -w /workspace/app app-php php tests/intranet.php && docker exec -w /workspace/app app-php php tests/qa-correcciones.php
docker exec -w /workspace/app app-php php tests/intranet.php && NODE_PATH=/workspace/nasca-dev/tools/node_modules PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-qa-correcciones.cjs
```

Se retiraron los informes, paquetes y suites históricas del portal completo; se conservan las pruebas vigentes de esta intranet. `tests/legacy-migration.php` comprueba el paso de las tablas heredadas `pisco_*` a `intranet_*` y la restauración de respaldos `.piscobak`. Las pruebas actuales evalúan el alcance reducido, los permisos y los recorridos conservados. El correo saliente para nuevos trámites requiere habilitar SMTP y cron según [NOTIFICACIONES-CORREO.md](NOTIFICACIONES-CORREO.md). La carga de información académica real sigue pendiente de configuración institucional.

## Paquete para Hostinger

Las instrucciones de subida y configuración están en [HOSTINGER-INSTALACION.md](HOSTINGER-INSTALACION.md). Para generar el paquete de instalación nueva desde un commit limpio:

```sh
python3 scripts/package-hostinger.py /ruta/fuera-del-repositorio/intranet-palpa-hostinger.zip
```

El empaquetador incluye solo archivos versionados necesarios para la instalación, los recursos activos de Palpa, las bibliotecas y las instrucciones. Excluye configuraciones privadas, respaldos, pruebas y recursos históricos ajenos a la instalación nueva. Añade versión y hashes de integridad.

Para aplicar las correcciones del QA a una instalación existente, consulta [ACTUALIZACION-QA-HOSTINGER.md](ACTUALIZACION-QA-HOSTINGER.md); para las mejoras posteriores al plan de pruebas y el cambio de nombres internos, [ACTUALIZACION-MEJORAS.md](ACTUALIZACION-MEJORAS.md). El resultado verificado y sus límites están en [QA-CORRECCIONES-INTRANET.md](QA-CORRECCIONES-INTRANET.md).

La limpieza de archivos y las mediciones de tamaño se documentan en [LIMPIEZA-REPOSITORIO.md](LIMPIEZA-REPOSITORIO.md).

## Configuración académica y documentos

La configuración compartida y los tres módulos documentales se describen en
[OPERACION-INTRANET.md](OPERACION-INTRANET.md#configuración-académica-compartida-y-documentos).
Además de la suite base, las regresiones de esta funcionalidad se ejecutan sobre la misma
base desechable, en este orden:

```sh
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/intranet.php
PORTAL_CONFIG_FILE=/ruta/config.test.php php tests/academic-documents.php
PORTAL_BASE_URL=http://127.0.0.1:8084 node tests/browser-academic-documents.cjs
```

El segundo comando conserva los datos de la suite base y agrega sus propios documentos QA.
No debe ejecutarse sobre una instalación real. Las unidades heredadas permanecen pendientes
de asociación a módulos hasta que el personal complete esa configuración.

Las cuatro páginas públicas tienen entradas independientes: `/mesa-de-partes.php`,
`/libro-de-reclamaciones.php`, `/bolsa-laboral.php` y `/certificados.php`. Sus funciones y
accesos se describen en [Operación](OPERACION-INTRANET.md#páginas-públicas-independientes).
La regresión `node tests/browser-standalone-services.cjs` se ejecuta sobre los datos de
`tests/intranet.php` y verifica aislamiento de páginas, móvil, privacidad y acceso laboral
con guardado real. `tests/browser-areas.cjs` prueba envíos y derivaciones usando las nuevas
entradas públicas.
