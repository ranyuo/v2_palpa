# Control de calidad de la intranet Palpa

Fecha: 4 de noviembre de 2026. Base revisada: `dd73284`, más la corrección de aislamiento de horarios incluida en esta revisión.

## Dictamen

El código puede publicarse en `main`: no quedaron fallos bloqueantes en los recorridos y comprobaciones locales ejecutados. Esta revisión no publica el repositorio ni despliega el alojamiento. No constituye una garantía de ausencia de errores en todas las combinaciones posibles de datos o de carga.

## Corrección encontrada

El filtro de horarios del estudiante comprobaba la publicación del grupo actual, pero no comparaba el período de cada sesión. Una sesión histórica del mismo programa, ciclo, sección y turno podía aparecer junto al horario actual. Se exige ahora que `sesion.per` coincida con el período configurado. La prueba también cubre grupos sin publicar, cuentas sin vínculo, retiro de publicación y permisos de egresados.

Las mejoras previas de destinatarios, cola, recuperación de envíos y rendimiento están descritas en [REVISION-NOTIFICACIONES.md](REVISION-NOTIFICACIONES.md). No se realizaron cambios de rendimiento adicionales sin evidencia de un problema.

## Alcance y evidencia

Se inspeccionaron las rutas y controles de sesión, CSRF, permisos por rol y vínculo, proyección pública, archivos, escritura concurrente, instalación, actualización, recuperación administrativa y respaldos. Se recorrieron los módulos de la intranet y los servicios independientes con navegador Chromium, incluidas vistas móviles de los formularios cubiertos.

| Área | Comprobación ejecutada y resultado |
| --- | --- |
| Intranet y reglas de negocio | 49 comprobaciones PHP: catálogos, alumnos, programas, permisos, portafolio docente, títulos, certificación, publicación y conflictos de horarios, trámites, reclamos y empleabilidad |
| Navegación y roles | 62 comprobaciones web; apertura de módulos, accesos permitidos y denegados, sin errores JavaScript |
| Horarios y aislamiento | 8 comprobaciones nuevas en `tests/policy-isolation.php` |
| Archivos, seguridad y recuperación | 15 comprobaciones nuevas en `tests/platform-security.php`: CSRF, propietario y acceso ajeno/anónimo, ejecutables y extensión falsa, permisos de cuentas/SMTP/restauración, revisión obsoleta, respaldo alterado y reversión íntegra |
| Áreas y derivación | 14 comprobaciones de compatibilidad, 31 web de recepción/derivación y 6 de rechazo de destinos inactivos |
| Documentos académicos | 19 comprobaciones PHP y 9 web de configuración, guardado y consulta |
| Servicios independientes y Bolsa Laboral | 32 comprobaciones web; registro, acceso, perfil y postulación |
| Envío de trámites | 13 comprobaciones de progreso, bloqueo de duplicados y recuperación del cargo ante pérdida de respuesta |
| Reclamos y empresas | Recorridos web de registro normal, progreso, protección de salida, respuesta perdida y recuperación; sin duplicar el reclamo ni exponer contraseñas |
| Eventos de correo | 34 comprobaciones de destinatarios, privacidad, roles y transacciones |
| Transporte SMTP | 41 comprobaciones con servidor local: TLS, autenticación, entrega, fallos, reintentos, cancelación y concurrencia |
| Configuración | 23 comprobaciones de SMTP cifrado y 15 de numeración; formularios web de receptores, SMTP y numeración, incluidos móvil y permisos |
| Migraciones y copias | 8 comprobaciones de migración de cola y 8 de migración/restauración del formato anterior |
| Recursos y sintaxis | 5 recursos con hash de contenido y bytes servidos correctos; 54 archivos PHP sin errores; JavaScript de aplicación y pruebas verificado; `git diff --check` limpio |

La batería existente se ejecutó secuencialmente, reiniciando las fixtures donde correspondía. Las pruebas nuevas de seguridad se ejecutaron al finalizar para no interferir con el navegador. Todas las operaciones de datos se realizaron en una base local desechable cuyo nombre termina en `_test`. Los fallos de seguridad, TLS y red de los casos negativos fueron intencionales. No se enviaron correos externos.

## Comprobaciones necesarias al desplegar

1. Respaldar los archivos y MySQL de producción. Conservar `config.php`, `install_key` y `.smtp-settings.json`.
2. Aplicar `/actualizar.php` de esta versión para completar el esquema 7 y retirar el actualizador al terminar.
3. Revisar receptores de Mesa de Partes, Reclamos, Bolsa Laboral y áreas. Comprobar la cuenta central de Workspace en el formulario SMTP.
4. Configurar/verificar el cron privado de `mail-worker.php` cada minuto. Sin cron los avisos de gestión pendientes no se entregarán regularmente.
5. Probar un trámite, reclamo y registro/postulación con cuentas controladas; comprobar cargo, seguimiento, cola y recepción real de ambos destinatarios.
6. Verificar HTTPS, límites de carga y permisos de carpetas del alojamiento. La base y el PHP de Hostinger no se probaron durante este control local.

La recepción por Gmail, sus filtros y cuotas, las tareas cron reales y el comportamiento con el volumen de datos de producción quedan fuera de la validación local. Los recordatorios automáticos por vencimiento, avisos académicos por correo y recuperación por token continúan siendo funciones propuestas, no implementadas. El detalle de esas recomendaciones está en `ANALISIS-NOTIFICACIONES.md`.
