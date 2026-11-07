# Ajustes del ciclo y la vista del ticket

## Actualización: modales, eliminación y solución después de reabrir

- Confirmaciones y visor de adjuntos se centran respecto de toda la ventana, en ambos ejes, incluso al cambiar el tamaño de pantalla.
- El botón de asignación/reasignación incluye icono, indicador desplegable, espaciado y foco de teclado. Si solo hay un grupo y aún no existe técnico, indica **Asignar técnico**, no Reasignar.
- **Eliminar solución** requiere confirmación y permiso técnico. Devuelve el ticket a En proceso; conserva la versión anterior en el historial, las evidencias y los movimientos/reservas de inventario. No está disponible en tickets cerrados ni para el solicitante sin permiso.
- Al reabrir, el estado es **En proceso (atención)**, la fecha de solución vigente se invalida y la solución anterior queda como borrador pendiente de actualización. El editor se abre y recibe el foco para quien tenga permiso; no se elimina el texto anterior. Si reabre el solicitante, el equipo técnico verá el editor abierto en su siguiente consulta.
- Una solución anterior no permite calificar ni cerrar otra vez, incluso si llega una petición con el estado desactualizado. Debe actualizarse y enviarse de nuevo.

### Flujo de inventario aplicado

**Reabrir → En proceso → Revisar/reservar materiales → Enviar solución actualizada → Aceptar y cerrar → Consumir las nuevas reservas.**

Al elegir **Devolver al inventario**, el consumo anterior se compensa una sola vez en el kardex. Si se vuelve a utilizar material, **Reservar de nuevo** consulta la disponibilidad actual y rellena el selector y la cantidad anterior: hay que revisar la cantidad y pulsar **Reservar insumo**. No crea una reserva silenciosamente. El aviso explica que editar el texto de una solución no descuenta existencias; el descuento ocurre al aceptar el nuevo cierre.

Ejemplo: se consumieron 2 conectores, se devolvieron al reabrir y en la nueva intervención solo se necesita 1. Se reserva 1, se actualiza la solución y al aceptar se descuenta **solo 1**. Si no se necesita material, no se debe volver a reservar ni descontar lo devuelto. Si se eligió **Mantener consumidos**, esos consumos se conservan y no se descuentan de nuevo.

La versión anterior de la solución y su fecha permanecen en el evento de reapertura del historial. Estas medidas no cambian asignaciones ni inventario de otros tickets.

### Evidencia adicional

Pruebas del servidor: `tests/Feature/TicketReopenSolutionTest.php`. Revisión de navegador: `scripts/qa/check-ticket-reopen-solution.cjs`, ejecutada con la base SQLite aislada y un caso QA nuevo, sin modificar el ticket 19 ni la base operativa.

Validación actual: **140 pruebas y 1260 aserciones correctas**, sin errores ni pruebas omitidas. Se comprobó el centrado horizontal y vertical del visor y de las confirmaciones de eliminar/reabrir en 1440×1000, 1280×720, 768×1024, 390×844 y 320×568. El caso utilizado fue el ticket QA **23**; terminó reabierto, con el editor abierto y una unidad mantenida como consumida después de validar ambas opciones de inventario.

- [Resultado de reapertura y centrado](pruebas-tickets/resultado-reapertura-solucion.json)
- [Visor centrado en escritorio](pruebas-tickets/revision-modal-adjunto-1440.png)
- [Visor centrado en móvil](pruebas-tickets/revision-modal-adjunto-390.png)
- [Confirmación de eliminar solución](pruebas-tickets/revision-modal-eliminar-1440.png)
- [Confirmación de reapertura](pruebas-tickets/revision-modal-reabrir-1440.png)
- [Editor después de reabrir](pruebas-tickets/revision-reabierto-editor.png)
- [Volver a reservar materiales devueltos](pruebas-tickets/revision-reservar-devueltos.png)
- [Botón de reasignación](pruebas-tickets/revision-boton-reasignar.png)

## Comportamiento actualizado

1. **Registro:** el solicitante identifica a quien pide el servicio; no se copia como técnico responsable. Al enviar un ticket normal, se calculan prioridad, SLA y aprobaciones, pero queda sin asignación automática, incluso si existe una regla de direccionamiento. Al terminar las aprobaciones también espera asignación manual.
2. **Asignación:** desde Asignación actual, una persona autorizada abre Asignar técnico y selecciona un perfil técnico activo. El grupo y el área deben ser compatibles. Cuando ya existe asignación, el mismo cuadro ofrece Reasignar técnico; no hay un segundo formulario lateral duplicado. El técnico mantiene la aceptación/rechazo de su asignación.
3. **Conversación:** hay un solo editor con interruptor Nota interna. Apagado publica una comunicación visible; encendido guarda una nota y adjuntos privados. El servidor valida el permiso interno aunque se intente manipular la petición. El solicitante no ve ese interruptor ni notas privadas.
4. **Insumos:** formulario compacto con buscador, cantidad entera, uso previsto y reserva, sin el espacio vacío anterior. Las reservas siguen sin descontar existencias hasta el cierre aceptado.
5. **Solución:** el formulario está en el área principal. Sin solución registrada no aparece la calificación, ni siquiera al administrador. Después de registrarla, el personal autorizado dispone de Editar solución en la esquina superior derecha de esa tarjeta. Los cambios quedan en el historial.
6. **Cierre:** tras aceptar una solución válida, el ticket es de solo lectura. No se permite editar, comentar, asignar, cambiar estado, reservar insumos ni ejecutar otras modificaciones, tampoco enviando peticiones directas como administrador. Los adjuntos y el historial pueden consultarse.
7. **Reapertura:** solo aparece en tickets cerrados y con permiso. Usa un modal institucional, no la confirmación nativa del navegador. Si hubo consumos, permite mantenerlos o devolverlos al inventario; la devolución exige `inventory.consume`. Un solicitante sin ese permiso puede reabrir conservando los consumos, pero no alterar existencias.
8. **Devolución:** confirma únicamente material físicamente recibido y no utilizado. Se registra un movimiento positivo de devolución en el kardex y el uso pasa a Devuelto al inventario. Repetir la petición no devuelve otra vez. El material devuelto no se consume de nuevo salvo que se reserve expresamente para una nueva intervención.
9. **Relaciones y archivos:** Relacionar ticket busca por número o asunto, con resultados limitados y respetando la visibilidad. Archivos ocupa la posición anterior de Dependencias con terceros; esta última pasa al lugar anterior de Archivos.

## Consideraciones

- No se modificaron asignaciones, soluciones ni consumos históricos existentes.
- Las asignaciones expresamente configuradas en planes preventivos se conservan como parte del flujo de mantenimiento; se retiró la asignación automática del envío normal de tickets.
- Las reglas de direccionamiento existentes no se eliminaron. Se conservan como contexto de aprobaciones; no asignan automáticamente al técnico al enviar un ticket normal.
- No se requiere migración ni se modifica `.env`. En despliegue debe ejecutarse `npm run build`.
- La devolución es un movimiento nuevo, no una eliminación del consumo original, para conservar la auditoría.
- Las confirmaciones de los formularios que usan `data-confirm` comparten ahora el modal institucional, también fuera de esta vista.

## Cómo probar

1. Crea y envía un ticket. Comprueba que el solicitante esté identificado y Asignación actual muestre Sin técnico asignado.
2. Asigna un técnico, acepta la asignación con su cuenta y comprueba que Reasignar técnico aparezca solo después de asignarlo.
3. Alterna el interruptor del editor. Publica un comentario y una nota interna con un adjunto; entra como solicitante y confirma que solo vea el comentario público.
4. Antes de registrar solución, verifica que no existan estrellas. Registra la solución, edítala desde su tarjeta y verifica el historial.
5. Reserva dos unidades, acepta la solución con el solicitante y comprueba el cierre y consumo. Como administrador, verifica que los formularios de modificación desaparezcan.
6. Abre Reabrir ticket, cancela el modal y comprueba que no cambie el estado. Repite y selecciona Mantener consumidos; la existencia no debe aumentar.
7. En otro ciclo cerrado, reabre seleccionando Devolver al inventario con una cuenta autorizada. Revisa el movimiento positivo y el estado Devuelto al inventario. Intentar devolver como solicitante sin permiso debe rechazarse.
8. Reserva nuevamente solo lo necesario, soluciona y cierra otra vez. La cantidad no debe descontarse dos veces si se mantuvo consumida; si fue devuelta, solo se consume una nueva reserva explícita.
9. Busca un ticket por número y por asunto. No deben aparecer el mismo ticket, borradores ni casos que la cuenta no pueda consultar.

## Pruebas reproducibles

Todo el material QA se genera únicamente en la base SQLite aislada de pruebas, sin alterar la base MySQL operativa ni el ticket 19 de las capturas.

Validación de la iteración anterior: **135 pruebas y 1194 aserciones correctas** en la suite completa. El navegador verificó administrador, técnico y solicitante, con anchos de 1440, 1280, 1024, 768, 390 y 320 px, sin desbordamiento, errores de JavaScript ni confirmaciones nativas. El caso QA generado fue el **ticket 21**, que terminó reabierto y con una unidad mantenida como consumida después de comprobar ambas decisiones del modal.

- Casos de servidor: `tests/Feature/TicketLifecycleTest.php`, `tests/Feature/TicketDetailTest.php` y `tests/Feature/ModuleFourTest.php`.
- Caso visual nuevo sin asignación: `php scripts/qa/prepare-ticket-detail.php --unassigned`.
- Prueba de navegador: `node scripts/qa/check-ticket-lifecycle.cjs` (servidor QA en 8017 y Playwright).
- [Resultado visual](pruebas-tickets/resultado-flujo-ticket.json).

Capturas:

- [Insumos compactos](pruebas-tickets/flujo-insumos-compactos.png)
- [Conversación e interruptor](pruebas-tickets/flujo-comentario-nota-switch.png)
- [Comentarios en móvil](pruebas-tickets/flujo-comentarios-movil.png)
- [Asignación unificada](pruebas-tickets/flujo-asignacion-unificada.png)
- [Editar solución](pruebas-tickets/flujo-editar-solucion.png)
- [Búsqueda de tickets](pruebas-tickets/flujo-busqueda-tickets.png)
- [Ticket cerrado de solo lectura](pruebas-tickets/flujo-cerrado-solo-lectura.png)
- [Modal de devolución](pruebas-tickets/flujo-reabrir-return.png)
- [Modal para mantener consumidos](pruebas-tickets/flujo-reabrir-keep.png)
