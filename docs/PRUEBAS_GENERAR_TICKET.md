# Generar ticket: cambios y guía de pruebas

Revisión: 6 de noviembre de 2026, Guatemala.

Actualización del ciclo: la asignación normal ahora es manual y los tickets cerrados son de solo lectura. Consulta [Ajustes del flujo del ticket](AJUSTES_FLUJO_TICKET.md) para las reglas actuales de solución, calificación, reapertura y devolución de insumos.

## Qué cambió

Administradores y técnicos tienen una captura de IT propia. Al crear un ticket no se carga debajo el formulario configurable del usuario solicitante. El servidor elige la captura mediante el permiso `tickets.create_internal`; un cambio en el navegador no permite a un usuario obtener esa capacidad.

El formulario de IT permite seleccionar **Solicitud de atención** o **Actividad interna de IT**, indicar el solicitante y registrar el detalle técnico. La solicitud de atención es la opción inicial y conserva la aceptación y calificación por el solicitante que tenga ese permiso. La actividad interna conserva su tratamiento anterior; ver el pendiente de cierre más abajo.

El usuario conserva su solicitud normal y los campos adicionales que correspondan a la categoría. Asunto, descripción y adjuntos aparecen una sola vez. Los campos configurables `asunto`/`title` y `descripcion`/`description`, con sus tipos de texto correspondientes y editables, toman su respuesta del campo principal. Las definiciones y las respuestas históricas no se eliminan.

Los borradores anteriores conservan su origen y sus respuestas. Un borrador antiguo que sí estaba vinculado al formulario del solicitante mantiene esa clasificación, en lugar de convertirlo y perder información. Por eso puede diferir de una captura nueva.

Actualización de los selectores: área y departamento son editables y se guarda la elección del ticket, sin modificar el perfil de la persona. El solicitante se busca por nombre, correo o código; el listado muestra hasta 20 coincidencias y se filtra al escribir. Los equipos se buscan por identificación o nombre y se agregan como selecciones múltiples del inventario, hasta un máximo de 10.

## Problemas encontrados y corregidos

| Hallazgo | Corrección y comprobación |
| --- | --- |
| Asunto y descripción se solicitaban en la sección general y nuevamente en el formulario de soporte. | Un único control para cada dato; las respuestas configurables compatibles se sincronizan al guardar. |
| Admin y técnico recibían el formulario del solicitante junto con el registro de IT. | Captura de IT y validación separadas; no se exigen preguntas del usuario que no se muestran. |
| «Registrar a nombre de» podía no surtir efecto si el interruptor interno no estaba marcado. | El formulario de IT identifica explícitamente al solicitante y conserva por separado al creador. |
| Área y departamento estaban bloqueados por el perfil. | El perfil solo propone los valores iniciales. Se permite cambiarlos y el servidor conserva la selección, validando que el departamento pertenezca al área. |
| Equipos relacionados requería ingresar códigos manualmente. | Buscador con selección múltiple de equipos registrados. Se validan identificadores, duplicados, disponibilidad para nuevas vinculaciones y el límite de 10. Las referencias antiguas se conservan y pueden quitarse. |
| El selector de solicitantes cargaba todos los usuarios. | Búsqueda filtrada por nombre, correo o código, con un máximo de 20 resultados por consulta. |
| Un error en la matriz de prioridad dejaba un borrador aunque el envío fallara. | Creación, envío e historial comparten una transacción; el reintento no deja un ticket parcial por ese error. Los archivos se almacenan después de validar el envío. |
| Un reenvío fallido podía guardar parte de los cambios del borrador. | Se conserva el borrador original cuando falla la operación. |
| La barra flotante «Guardar / Enviar» tapaba campos, especialmente en móvil. | Ahora se encuentra al final del formulario. La comprobación de geometría verifica que no se superponga a los campos. |
| Los pasos del formulario podían desbordar pantallas pequeñas. | Ajuste de anchuras y salto de texto; se comprueban 390 y 320 píxeles. |
| Algunas etiquetas de opciones de radio y de archivos apuntaban a controles inexistentes. | Grupos de radio con nombre accesible y un único control real para evidencias. |
| Los obligatorios aparecían en inglés, el texto de carga de evidencias aparecía rojo y un archivo pequeño figuraba como «0.0 MB». | Mensajes obligatorios del formulario en español, color institucional y tamaño en bytes, KB o MB. |
| Las pruebas de autenticación heredaban Turnstile de `.env` y fallaban sin un token real. | PHPUnit fija su configuración de pruebas. Las pruebas específicas de Turnstile lo activan de manera controlada; no se modifica la protección del servidor habitual. |

No se modificaron las reglas de prioridad, aprobación, SLA o direccionamiento. Se conserva la paleta institucional.

## Cómo abrir la copia para tus pruebas

Los ejemplos se preparan en una base SQLite independiente:

`storage/framework/testing/ticket-forms/qa.sqlite`

La preparación y el servidor de QA no editan `.env` ni utilizan la base MySQL habitual. El correo se captura en memoria. Turnstile se desactiva exclusivamente en este servidor de pruebas de acceso local.

Abre PowerShell y ejecuta:

```powershell
Set-Location C:\laragon\www\sistema-tickets
$qaPhp = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
& $qaPhp scripts/qa/prepare-ticket-forms.php
& $qaPhp -S 127.0.0.1:8017 -t public scripts/qa/serve-ticket-forms.php
```

Mantén esa consola abierta y entra en [Inicio de sesión QA](http://127.0.0.1:8017/login). Si ya está iniciado el servidor de QA en ese puerto, utiliza el que está abierto. Para detenerlo, pulsa `Ctrl+C` en su consola.

Las cuentas son ficticias y solo existen en la base de QA:

| Perfil | Correo | Contraseña de QA |
| --- | --- | --- |
| Administrador | `qa.tickets.admin@zolicguate.com` | `PruebasTicket#2026` |
| Técnico | `qa.tickets.tecnico@zolicguate.com` | `PruebasTicket#2026` |
| Usuario | `qa.tickets.usuario@zolicguate.com` | `PruebasTicket#2026` |

Usa sesiones separadas o cierra sesión antes de cambiar de cuenta. Los ejemplos creados por el navegador tienen asuntos que comienzan con `QA FORM`. Los números y enlaces de la última ejecución están en [resultado-navegador.json](pruebas-tickets/resultado-navegador.json).

Últimos ejemplos comprobados:

| Creado por | Ticket | Enlace en el servidor QA |
| --- | --- | --- |
| Administrador QA | `ZT-2026-000016` | [Abrir ejemplo](http://127.0.0.1:8017/tickets/16) |
| Técnico QA | `ZT-2026-000017` | [Abrir ejemplo](http://127.0.0.1:8017/tickets/17) |
| Usuario QA | `ZT-2026-000018` | [Abrir ejemplo](http://127.0.0.1:8017/tickets/18) |

Las ejecuciones anteriores dejaron otros ejemplos ficticios. Repetir el script genera una solicitud nueva por perfil; no significa que un solo envío esté creando dos tickets.

La preparación puede repetirse: actualiza las cuentas y la configuración ficticias, pero no borra los tickets QA ya creados. No ejecutes el seeder de QA sobre el entorno habitual; está restringido al archivo de pruebas.

## Datos para rellenar

Para los tres perfiles selecciona **Soporte técnico → Software institucional** y pulsa **Continuar**.

| Campo | Valor de ejemplo |
| --- | --- |
| Asunto, administrador | `QA ADMIN - revisión de configuración del cliente` |
| Asunto, técnico | `QA TECNICO - error de guardado en estación de trabajo` |
| Asunto, usuario | `QA USUARIO - no puedo guardar una operación` |
| Ubicación | `Edificio de Operaciones, oficina de pruebas QA` |
| Tipo de atención | `Atención remota` |
| Impacto | `Medio` |
| Urgencia | `Media` |
| Equipos relacionados | Buscar y seleccionar `ZOLIC-PC-001` y `ZOLIC-PRN-003` |
| Evidencia | Archivo `scripts/qa/fixtures/evidencia-ticket.txt` del proyecto |

Descripción para admin/técnico:

> PRUEBA FICTICIA: la aplicación de operaciones muestra un error al guardar un registro. Ocurre en una estación de trabajo desde las 09:15. Se verificó la conexión y se solicita revisar la configuración del cliente.

Descripción para usuario:

> PRUEBA FICTICIA: al guardar una operación aparece un mensaje de error y los datos no se guardan. El problema comenzó a las 09:15 y ocurre únicamente en mi computadora. Puedo ingresar al sistema, pero no finalizar el registro.

En admin/técnico selecciona **Solicitud de atención**. En el buscador de solicitante escribe `qa.tickets.usuario` y selecciona **Usuario QA Tickets** en los resultados. Deben proponerse **Operaciones** y **Operaciones Portuarias**, pero puedes cambiarlos: prueba **Soporte Técnico → Mesa de Ayuda**, que debe mantenerse al guardar y editar. No debe aparecer la sección «Solicitud general de soporte» ni las preguntas adicionales de ese formulario de usuario.

En usuario completa además **Cantidad de usuarios afectados: 3** y **Canal de contacto (QA): Correo**. En la base QA se añadió esa pregunta de radio y se hizo obligatoria la evidencia para probar sus validaciones. Esto no cambia la configuración de formularios de tu base habitual.

Los códigos `ZOLIC-PC-001` y `ZOLIC-PRN-003` pertenecen a equipos de ejemplo ya registrados en la base QA. Seleccionarlos vincula el ticket; no transfiere la custodia ni modifica sus existencias o estado. No se pueden crear referencias nuevas ingresando un código manualmente. Los tickets anteriores que tenían referencias sin inventario las conservan identificadas como históricas.

## Pruebas que puedes repetir

### 1. Separación y duplicados

1. Inicia sesión con cada cuenta y abre **Tickets → Nuevo ticket**.
2. Selecciona la clasificación del ejemplo.
3. En admin/técnico verifica que solo exista la captura de IT. En usuario debe aparecer la solicitud de usuario.
4. Comprueba que haya un asunto, una descripción y un selector de archivos. No debe existir otro bloque que vuelva a pedir esos mismos datos.
5. En usuario sí deben aparecer las preguntas adicionales de la clasificación, como usuarios afectados y canal de contacto.

### 2. Validación sin perder el formulario

1. Rellena el ejemplo, pero deja vacío **Asunto**.
2. Pulsa **Enviar ticket**. Debe regresar al mismo formulario e indicar «Ingresa el asunto del ticket».
3. Comprueba que descripción, ubicación, clasificación y selecciones sigan presentes.
4. Rellena el asunto y vuelve a seleccionar la evidencia. Los navegadores no permiten recuperar automáticamente un archivo después de recargar una página.
5. Como usuario, prueba también a omitir el canal o la evidencia obligatoria de QA: debe indicar qué falta sin generar el ticket.

### 3. Borrador, edición y envío

1. Guarda un ejemplo con **Guardar borrador**.
2. Abre **Editar borrador** y comprueba los valores.
3. Modifica la descripción y pulsa **Enviar ticket**.
4. Debe utilizar el mismo número de ticket; editar y enviar un borrador no crea otro ticket.
5. El solicitante, el creador y el tipo de registro originales se conservan. Los campos de identificación quedan bloqueados al editar.

6. Comprueba que el área/departamento elegidos y los dos equipos seleccionados se conserven. Quita un equipo con **×**, guarda de nuevo y verifica que solo se desvincule el que quitaste.

Guardar borrador admite información incompleta; enviar exige los campos obligatorios. No se puede enviar una clasificación sin subcategoría. Acceder a la creación con solo una categoría mantiene la pantalla de clasificación para completarla.

### 4. Atención y cierre de una solicitud registrada por IT

1. Como admin o técnico, crea **Solicitud de atención** para **Usuario QA Tickets**.
2. El ejemplo de soporte debe calcular prioridad **Media / P3** y dirigirse a **Mesa de Ayuda**, según la configuración de QA. La asignación inicial es al grupo, no necesariamente a un técnico individual.
3. Como técnico QA, abre el ticket y documenta la solución: «Se corrigió la configuración del cliente y se validó el guardado de registros con el usuario».
4. Como usuario QA, abre el ticket solucionado, acepta, califica con **5** y confirma. El estado debe pasar a **Cerrado**.
5. En otro ejemplo, rechaza la solución indicando «El error de guardado continúa al finalizar la operación». Debe volver a **En proceso**.

### 5. Revisión visual

1. Comprueba el formulario en escritorio y con un ancho de móvil de 390 y 320 píxeles.
2. Los pasos no deben obligar a desplazar toda la página horizontalmente.
3. Los botones deben estar al final, sin tapar campos o textos. Los menús y las opciones deben poder utilizarse.
4. Confirma que asunto, descripción, área, ubicación, niveles y evidencias conserven etiquetas legibles.

### 6. Búsquedas y selección de equipos

1. Como admin/técnico, busca un solicitante por parte de su nombre, correo o código. Las coincidencias deben aparecer debajo sin desplegar todo el directorio.
2. Escribe un término sin coincidencias: debe indicarse que no hay resultados. Puedes navegar con flechas y confirmar con **Enter**.
3. Busca `ZOLIC-PC-001`, selecciónalo y después busca `ZOLIC-PRN-003`. Ambos deben aparecer como selecciones independientes.
4. El mismo equipo no debe agregarse dos veces. El buscador no ofrece equipos inactivos o dados de baja para nuevas vinculaciones.
5. Cambia el área y comprueba que los departamentos se filtren para esa área. Al dejar el área vacía, el departamento queda pendiente de escoger un área.
6. Un usuario solicitante no tiene acceso al buscador de otras personas; conserva su identidad. Sí puede buscar la identificación mínima de equipos para vincularlos a su propio ticket.

Capturas de los formularios rellenados:

| Perfil | Escritorio | Móvil |
| --- | --- | --- |
| Administrador | [Ver captura](pruebas-tickets/admin-escritorio.png) | [Ver captura](pruebas-tickets/admin-movil.png) |
| Técnico | [Ver captura](pruebas-tickets/tecnico-escritorio.png) | [Ver captura](pruebas-tickets/tecnico-movil.png) |
| Usuario | [Ver captura](pruebas-tickets/usuario-escritorio.png) | [Ver captura](pruebas-tickets/usuario-movil.png) |

Detalle de los nuevos controles: [búsqueda de solicitantes](pruebas-tickets/solicitante-busqueda.png) y [selección múltiple de equipos](pruebas-tickets/equipos-seleccion.png).

## Pendientes que conviene revisar

**Cierre de actividades internas de IT.** En el comportamiento existente, el técnico puede registrar la solución de una actividad interna, pero esa actividad no usa la aceptación del solicitante. El cambio de estado ordinario tampoco ofrece **Cerrado**. El superadministrador tiene excepciones amplias de permisos; no representan el recorrido habitual del técnico.

Para verlo, crea un ejemplo como técnico con tipo **Actividad interna de IT**, envíalo y registra su solución. Quedará **Solucionado** sin la aceptación ordinaria del usuario. Hace falta decidir quién autoriza el cierre interno: creador, encargado o jefatura. No se añadió una autorización de cierre nueva sin esa definición. Los tickets de desarrollo tienen además su cierre especial por autorización de despliegue.

Para solicitudes que sí deben ser aceptadas por una persona, utiliza **Solicitud de atención** y selecciona un solicitante con permiso de aceptar servicio. El perfil técnico, cuando se pone a sí mismo como solicitante, no obtiene por ello el permiso `tickets.review`.

**Fecha de la cabecera.** La configuración actual de la aplicación usa UTC. Durante esta revisión, realizada la noche del 5 de noviembre en Guatemala, la cabecera del escritorio mostraba 6 de noviembre. Se puede observar en las capturas. No se cambió el huso horario global porque también afecta fechas operativas y cálculos de plazos; conviene definirlo para el sistema completo.

## Verificación técnica

La regresión general pasó **115 pruebas y 946 comprobaciones**. El módulo de tickets incluye **25 pruebas y 221 comprobaciones**: creación por perfil, campos no duplicados, validaciones, permisos, archivos privados, aprobaciones, asignación, borradores, errores de envío y aceptación/cierre. También cubre cambio de área, relación de departamento, selección de varios equipos, identificadores inválidos/duplicados, referencias históricas y los permisos/límites de las búsquedas. La compilación de recursos se completó correctamente.

El navegador comprueba admin, técnico y usuario en escritorio, 390 px y 320 px; también guarda, abre y envía borradores. Comprueba controles únicos, identificadores repetidos, etiquetas sin destino, desbordamiento, superposición de la barra, errores de JavaScript y respuestas HTTP fallidas. El detalle actualizado se guarda en [resultado-navegador.json](pruebas-tickets/resultado-navegador.json).

La última ejecución pasó para los tres perfiles: sin controles duplicados, sin etiquetas rotas, sin desbordamiento, sin superposición de la barra, sin errores de JavaScript y sin respuestas HTTP fallidas. Además, se conservaron los datos tras una validación fallida y se editó/envió el mismo borrador en cada perfil, manteniendo el área/departamento elegidos y los dos equipos del inventario.

También se comprobó el caso sin coincidencias, la selección con flechas y Enter, el bloqueo de un equipo repetido, la eliminación de una selección y los listados desplegados en móvil.

Para ejecutar las pruebas de PHP:

```powershell
Set-Location C:\laragon\www\sistema-tickets
& C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe vendor/bin/phpunit
```

Para repetir el navegador con el servidor QA abierto y Playwright del entorno Codex instalado:

```powershell
Set-Location C:\laragon\www\sistema-tickets
$env:NODE_PATH = 'C:\Users\luisb\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
node scripts/qa/check-ticket-forms.cjs
```

Cada ejecución del navegador genera ejemplos adicionales y actualiza las capturas. Las comprobaciones de creación son del flujo de soporte; no equivalen a una revisión visual exhaustiva de todos los módulos ni a una prueba de producción.
