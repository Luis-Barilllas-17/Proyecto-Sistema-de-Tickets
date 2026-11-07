# Ajustes y pruebas del detalle del ticket

## Cambios

- **Insumos:** búsqueda por código o nombre, lista limitada a 20 coincidencias y solo materiales activos con unidades enteras disponibles. Se descuentan las reservas de otros tickets; la reserva actual puede actualizarse sin contarse dos veces.
- **Cantidad:** números enteros positivos, validados tanto en el navegador como en el servidor. El formulario ahora cabe dentro de su tarjeta. No se cambió la precisión de la base de datos ni se alteraron existencias o movimientos históricos con fracciones.
- **Calificación:** cinco estrellas seleccionables con ratón, pantalla táctil y teclado. El valor se conserva si falta algún dato al enviar. Aceptar exige calificación y confirmación; rechazar exige un comentario de al menos 10 caracteres, sin exigir aceptación.
- **Adjuntos:** clic normal abre un modal; conserva un botón opcional para descargar el original. Se aplica también a los enlaces de archivos de los comentarios. Cerrar con ×, Escape o clic en el fondo devuelve el foco al enlace.

## Formatos de vista previa

| Formato | Vista dentro del modal |
| --- | --- |
| JPG, PNG y WEBP | Imagen |
| PDF | Lector integrado del navegador; descarga alternativa si el dispositivo no lo soporta |
| TXT y CSV | Texto, sin ejecutar contenido; hasta los primeros 100 KB |
| XLSX | Tabla de datos de hasta 3 hojas, 100 filas y 30 columnas por hoja |
| DOCX | Texto de hasta 300 párrafos y 100 000 caracteres |
| XLS, DOC, ZIP u otros no compatibles | Aviso y descarga opcional |

Las vistas de Office no reproducen estilos, gráficos ni imágenes del documento. Las fórmulas XLSX muestran únicamente el resultado guardado, sin recalcularse. Los archivos dañados, cifrados o que exceden los límites de lectura muestran un aviso y mantienen disponible el original.

## Privacidad y seguridad

- No se usan visores de Google, Microsoft ni servicios externos para leer documentos privados.
- Tanto la vista previa como el contenido y la descarga verifican la pertenencia al ticket, la autorización del usuario y la visibilidad de adjuntos internos.
- Las respuestas de archivos son privadas y no se almacenan en caché. Solo el contenido autorizado de imágenes/PDF permite incrustación desde el mismo origen; el resto de la aplicación mantiene la protección contra marcos.
- No se extraen archivos ZIP, no se ejecutan fórmulas, HTML ni macros. La lectura Office limita tamaño descomprimido, complejidad XML y contenido visible, y rechaza declaraciones de entidades.
- No hay migraciones ni cambios en `.env`; en producción se deben publicar los recursos compilados con `npm run build`. XLSX/DOCX requieren las extensiones PHP ZIP, DOM y mbstring; sin ZIP se muestra la alternativa de descarga.

## Prueba manual

1. Como administrador o técnico, abre un ticket en atención y busca un insumo por código y después por nombre. Prueba una búsqueda sin coincidencias.
2. Selecciona un insumo. Intenta reservar `1.5`, `0`, un negativo y una cantidad mayor que la disponible: deben rechazarse. Reserva `2`: no debe descontarse aún la existencia.
3. Abre los adjuntos desde Archivos y desde un comentario. Comprueba el modal, Escape, × y Descargar original. Prueba XLSX, PDF, imagen, TXT y ZIP.
4. Registra la solución como técnico. Entra con el solicitante y elige estrellas; también puedes moverte con las flechas del teclado.
5. Envía sin marcar la confirmación: debe aparecer el error y conservarse la calificación. Marca la confirmación y acepta: el ticket se cierra y las reservas se consumen una sola vez.
6. En otro ticket solucionado, pulsa No resolvió con un comentario explicativo y sin estrellas ni confirmación: debe regresar a En proceso.
7. Como solicitante, confirma que los adjuntos internos no aparecen y que tampoco pueden abrirse pegando su URL. Un usuario ajeno al ticket tampoco debe poder leer sus archivos.

## Evidencia de QA

Las pruebas usan exclusivamente SQLite aislado en `storage/framework/testing/ticket-forms/qa.sqlite`; no se utilizó la base operativa de MySQL. Se creó el ticket **20**, `QA Detalle: conexión de red intermitente`, sin modificar el ticket 19 de las capturas. Después de la prueba completa, el ticket 20 queda cerrado con 4 estrellas y dos conectores consumidos.

- Perfiles comprobados: administrador, técnico y solicitante.
- Resoluciones sin desbordamiento en el formulario: 1440, 1280, 1024, 768, 390 y 320 px.
- Pruebas específicas: `tests/Feature/TicketDetailTest.php`.
- Resultado: **125 pruebas automáticas y 1067 aserciones correctas** en la suite completa; las 10 pruebas específicas del detalle se repitieron tras los ajustes finales y también pasaron.
- Preparación de un caso nuevo: `php scripts/qa/prepare-ticket-detail.php` (solo entorno QA aislado).
- Revisión en navegador: `node scripts/qa/check-ticket-detail.cjs` (requiere Playwright y servidor QA en el puerto 8017).
- Resultado del navegador: [resultado-detalle-ticket.json](pruebas-tickets/resultado-detalle-ticket.json).

Capturas:

- [Insumos y cantidades](pruebas-tickets/detalle-insumos-desktop.png)
- [Calificación con estrellas](pruebas-tickets/detalle-calificacion-estrellas.png)
- [Estrellas en móvil](pruebas-tickets/detalle-estrellas-movil.png)
- [Modal de XLSX](pruebas-tickets/detalle-adjunto-xlsx.png)
- [PDF abierto dentro del modal](pruebas-tickets/detalle-adjunto-pdf.png)
- [Modal en móvil](pruebas-tickets/detalle-adjunto-movil.png)

La prueba de rechazo también corrigió un fallo previo: la regla de confirmación electrónica se aplicaba indebidamente al rechazo de la solución. No se modificaron las reglas de aprobación, asignación, SLA ni consumo al cierre.
