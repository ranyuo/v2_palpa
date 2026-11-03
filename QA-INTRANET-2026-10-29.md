# Verificación de la separación de la intranet — 29/10/2026

Validación en `/workspace/app`, con PHP 8.3, MariaDB y Playwright/Chromium, sobre la base **app_test**, independiente del portal de Palpa.

| Prueba | Resultado |
| --- | --- |
| `tests/intranet.php` | 45 comprobaciones aprobadas |
| `tests/browser-intranet.cjs` | 61 comprobaciones aprobadas; cero errores JavaScript |
| Sintaxis PHP | Archivos de `portal-private/` y `public_html/` correctos |
| Sintaxis JavaScript | Integración y aplicación de intranet correctas |
| `git diff --check` | Sin errores |

Se verificaron instalación, acceso por rol, catálogos, creación de programas sin campos promocionales obligatorios, certificación, notas, títulos, restricciones de estudiante y docente, cruces de horarios, recepción y atención de reclamos, expedientes con PDF privado, validación de empresas, ofertas, postulaciones y acceso autorizado al CV.

La API rechazó cambios editoriales del administrador, cambios de portada y permisos ajenos al alcance. Los formularios de contacto general, boletín y consulta de becas quedaron fuera de la aplicación. Se comprobó que los datos editoriales heredados permanecen en la base sin cambios.

En navegador se probaron los siete módulos y catálogos de soporte, servicios externos, acceso manual a rutas deshabilitadas, persistencia de configuración, rechazo de una solicitud editorial manual a la API y navegación por perfiles de Secretaría, Atención, Docente, Estudiante, Egresado y Empresa. Las pruebas móviles usaron 390 px y verificaron el menú y la ausencia de desbordamiento horizontal.

Capturas locales ignoradas por Git:

- `tests/.results/intranet/login-desktop.png`
- `tests/.results/intranet/login-mobile.png`
- `tests/.results/intranet/panel-admin.png`
- `tests/.results/intranet/panel-mobile.png`

Los registros, CV y cuentas QA pertenecen únicamente a la base de pruebas. La carga inicial distribuida no contiene esos usuarios ni datos operativos ficticios.

La validación no acredita cumplimiento íntegro del TDR, despliegue, correo saliente, carga de datos reales ni capacitación. Esos puntos se distinguen en `ALCANCE-INTRANET.md`. El repositorio `palpa` y la web oficial no fueron modificados.
