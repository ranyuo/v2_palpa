# Revisión previa de notificaciones institucionales

## Correcciones encontradas

| Caso | Problema anterior | Corrección |
| --- | --- | --- |
| Alta de empresa desde administración | Solo el registro público generaba el aviso de alta | Ambos caminos generan el aviso; si nace aprobada, el mensaje muestra su estado real y no promete acceso sin cuenta |
| Postulación retirada o eliminada | Podía incluirse entre los destinatarios al cerrar una oferta | Se excluye del aviso de cierre |
| Cuenta de estudiante inactiva | Era necesario comprobar que no se usara el correo del maestro como sustituto | Se prueba el caso real: no se generan nuevos avisos personales para esa cuenta |
| Solicitante y receptor institucional con el mismo correo | Mesa de Partes podía enviar dos mensajes | Se conserva un solo cargo con su código de verificación, comparando las direcciones sin distinguir mayúsculas |
| Vínculo residual en una cuenta de otro perfil | Podía elegirse antes que la cuenta del estudiante | Se exige perfil de estudiante o egresado, cuenta activa y vínculo personal correcto |
| Registro reasignado antes de entregar un correo | La existencia del ID no bastaba para comprobar que seguía siendo el mismo registro | Los nuevos avisos incluyen una huella de identidad; el trabajador cancela los que ya no corresponden al origen |
| Guardados con muchos registros | Cada búsqueda repetía recorridos completos por las colecciones | Se indexan las referencias por ID una vez por guardado |
| Archivos estáticos después de desplegar | El navegador podía conservar JavaScript o estilos anteriores | Las cinco URLs de recursos propios incluyen una versión derivada del contenido |
| Reejecución de la actualización | Se alteraba siempre la precisión de la fecha de la cola | Se modifica únicamente cuando es necesario; una migración parcial puede completarse sin perder mensajes |
| Indicador de progreso accesible | Un mensaje dentro de un formulario `aria-busy` podía anunciarse tarde | Se agrega una región de estado externa al formulario para lectores de pantalla |
| Salida durante registro de reclamo o empresa | Faltaba la misma advertencia de salida que ya tenía Mesa de Partes | Se protege la salida mientras se envía y se restauran controles y navegación al terminar |
| Respuesta de registro perdida | Era necesario verificar la recuperación, además del registro normal | Reclamos recupera el mismo cargo con su clave; empresas informa cómo ingresar a la cuenta ya creada |

## Flujo revisado

1. El servidor valida sesión, permisos, datos, referencias y revisión antes de crear eventos.
2. El cambio de negocio y sus avisos se guardan en la misma transacción. Un rechazo o una reversión no deja correos nuevos.
3. Los destinatarios se resuelven desde registros y configuración del servidor. Las notas internas, contraseñas y currículos no se incluyen en los avisos.
4. Una repetición del formulario conserva la clave de la solicitud. Guardar un registro sin cambiar un evento no crea otro mensaje.
5. La cola conserva el vínculo con su origen. Los nuevos avisos de empresas, ofertas, postulaciones y seguimientos se comprueban también mediante su huella antes de enviarse.
6. Solo un trabajador por base puede entregar mensajes a la vez. Los mensajes entregados no se pueden reenviar desde el botón de reintento.
7. TLS verifica el certificado del servidor SMTP. Los errores visibles no incluyen la contraseña ni el diálogo de autenticación.
8. Los formularios muestran proceso, bloquean envíos repetidos, conservan datos si falla la respuesta y recuperan los controles al finalizar.
9. La migración conserva contenido, destinatarios, estados e intentos de la cola de versión 6. Ejecutarla de nuevo no altera el mensaje existente.
10. El paquete de publicación excluye configuraciones privadas, credenciales y artefactos de pruebas.

## Condiciones de despliegue

- Conservar `config.php`, su `install_key` y `.smtp-settings.json`.
- Ejecutar `/actualizar.php` con esta versión, incluso si se utilizó un paquete preliminar del esquema 7.
- Revisar los receptores de Mesa de Partes, Reclamos, Bolsa Laboral y las áreas receptoras.
- Mantener `portal-private/mail-worker.php` por cron cada minuto: los avisos de gestión se entregan desde esa cola.
- Verificar después en Hostinger con correos bajo control del administrador. Las pruebas de esta revisión usan SMTP local y credenciales ficticias.

SMTP puede aceptar un mensaje y una caída puede impedir registrar localmente esa aceptación; sigue siendo posible un duplicado en ese caso extremo. La etiqueta «Enviado» tampoco confirma lectura ni bandeja principal. Estas limitaciones se mantienen documentadas en NOTIFICACIONES-CORREO.md.

Los recordatorios por vencimiento, los avisos académicos, los resúmenes de ofertas y la recuperación por token siguen siendo recomendaciones pendientes, no funciones activadas por esta revisión.

## Validación final

| Comprobación | Resultado |
| --- | --- |
| Suite PHP de intranet | 49 comprobaciones aprobadas |
| Eventos, destinatarios, permisos y privacidad | 34 comprobaciones aprobadas |
| Transporte SMTP local, TLS, reintentos, cancelaciones y concurrencia | 41 comprobaciones aprobadas |
| Migración de cola versión 6 y actualización parcial | 8 comprobaciones aprobadas |
| Migración y restauración de respaldos anteriores | 8 comprobaciones aprobadas, sin advertencias de sesión |
| Configuración SMTP cifrada | 23 comprobaciones aprobadas |
| Numeración de expedientes | 15 comprobaciones aprobadas |
| Intranet en navegador | 62 comprobaciones aprobadas |
| Áreas, recepción y derivación | 31 comprobaciones aprobadas |
| Rechazo de destinos inactivos | 6 comprobaciones aprobadas |
| Páginas independientes y Bolsa Laboral | 32 comprobaciones aprobadas |
| Progreso y recuperación del envío de trámites | 13 comprobaciones aprobadas |
| Configuración y documentos académicos | 19 comprobaciones PHP y 9 web aprobadas |
| Registro normal y respuesta perdida de Reclamos y empresas | Recorridos de navegador aprobados |
| Formularios SMTP, receptores y numeración en móvil | Recorridos de navegador aprobados |
| Caché de recursos de la aplicación | 5 recursos con versión correcta, respuesta íntegra y sin errores JavaScript |
| Volumen del análisis de notificaciones | 10000 expedientes: cero avisos sin cambios y un aviso para un único cambio |
| Sintaxis | 52 archivos PHP sin errores; JavaScript y empaquetador comprobados |

Los casos de contraseña incorrecta, TLS sin confianza, permisos insuficientes y
respuesta de red perdida se provocaron intencionalmente y produjeron el rechazo
o la recuperación esperados. Las pruebas se ejecutaron secuencialmente sobre una
base desechable `_test`, con clientes locales independientes y SMTP de prueba.
No se enviaron correos reales ni se publicó esta revisión en `main`.
