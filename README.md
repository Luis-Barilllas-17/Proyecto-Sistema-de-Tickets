# Sistema de Gestión de Tickets ZOLIC

Sistema institucional de tickets desarrollado con Laravel 12, PHP 8.2, MySQL y Vite.

## Alcance implementado

El primer apartado cubre autenticación, recuperación de contraseña y los requisitos vigentes RF-001 a RF-007:

- Inicio y cierre de sesión con correo institucional.
- Validación de cuentas activas, suspendidas e inactivas.
- Recuperación de contraseña mediante enlace seguro.
- Restablecimiento administrativo con cambio obligatorio al ingresar.
- Bloqueo temporal después de cinco intentos fallidos.
- Desbloqueo administrativo de cuentas con bloqueo temporal.
- Cierre automático después de 30 minutos de inactividad.
- Consulta y finalización de sesiones abiertas.
- Activación, suspensión y desactivación administrativa de cuentas.
- Bitácora de accesos, intentos fallidos, cierres y cambios de contraseña.

## Acceso local

Con Laragon iniciado, abre:

`http://sistema-tickets.test`

Si todavía no has recargado los hosts automáticos de Laragon, también puedes usar:

`http://localhost/sistema-tickets/public/login`

Cuenta de Super Administrador para desarrollo:

- Correo: `admin@zolicguate.com`
- Contraseña: `Zolic#2026`

Cuenta institucional:

- Correo: `soporte@zolicguate.com`
- Contraseña: `Soporte#2026`

El sembrado incluye cinco cuentas adicionales y una bitácora demostrativa para probar los estados activo, suspendido e inactivo desde la consola administrativa.

Estas credenciales son exclusivamente para el entorno local y deben reemplazarse antes de publicar el sistema.

## Base de datos

La aplicación utiliza la base MySQL `sistema_tickets`. Las migraciones y los datos iniciales ya están aplicados.

## Módulo 2: usuarios y estructura organizacional

La segunda entrega incorpora:

- Creación, consulta y edición de usuarios institucionales.
- Asignación de código de empleado, puesto, extensión, área y departamento.
- Siete perfiles iniciales y soporte para perfiles personalizados.
- Matriz de permisos por módulo y acción.
- Autorización validada en el servidor para cada operación administrativa.
- Registro y edición de áreas, departamentos, jefaturas y áreas internas de IT.
- Creación de grupos técnicos con coordinador e integrantes.
- Asociación de técnicos y programadores con grupos de trabajo.
- Administración y asignación de especialidades técnicas y de desarrollo.
- Bitácora de creación y modificación de usuarios, roles y estructura.

Los datos de demostración incluyen 7 perfiles, 19 permisos, 8 áreas, 6 departamentos, 3 grupos de IT y 6 especialidades.

## Módulo 3: parametrización y reglas de servicio

La tercera entrega incorpora los requisitos RF-015 a RF-024:

- Administración de categorías y subcategorías con tipo, orden, descripción y estado.
- Diseñador de formularios asociado a una categoría o subcategoría específica.
- Campos de texto, texto largo, números, fechas, listas, opciones múltiples, casillas y archivos.
- Configuración de campos obligatorios, visibles, de solo lectura, activos y ordenados.
- Reglas de aprobación condicionadas por clasificación, área solicitante y prioridad mínima.
- Flujos de aprobación multinivel con responsables por jefatura, usuario o perfil.
- Suplencias y delegaciones temporales con vigencia y prevención de períodos superpuestos.
- Reglas de direccionamiento hacia áreas internas de IT, grupos técnicos o responsables directos.
- Estados configurables con etapa inicial, etapas finales, orden y color.
- Escalas de impacto y urgencia con matriz automática de prioridad.
- Objetivos SLA generales o específicos por categoría y subcategoría.
- Bitácora de todos los cambios administrativos del módulo.

Los datos iniciales incluyen 4 categorías, 11 subcategorías, 2 formularios, 9 campos, un flujo de aprobación con 2 niveles, 4 reglas de direccionamiento, 9 estados, 4 prioridades, una matriz de 9 combinaciones y 4 objetivos SLA.

## Módulo 4: gestión integral de tickets

La cuarta entrega implementa los requisitos RF-025 a RF-044:

- Creación de solicitudes por usuarios y tickets internos registrados por personal de IT.
- Número correlativo anual, borradores editables y control de concurrencia al guardar.
- Clasificación por categoría, subcategoría, área, ubicación y tipo de atención.
- Formularios dinámicos consumidos directamente desde la parametrización del Módulo 3.
- Cálculo automático de prioridad por impacto y urgencia, con fechas objetivo SLA.
- Adjuntos privados con nombres aleatorios, lista de tipos permitidos, límite de tamaño y huella SHA-256.
- Vinculación preliminar de uno o varios equipos mediante su código de inventario.
- Aprobaciones secuenciales multinivel, rechazo, devolución para corrección y delegación vigente.
- Direccionamiento automático, asignación manual, transferencia y aceptación o rechazo del técnico.
- Comentarios visibles, notas internas restringidas y registro de actividades, diagnóstico, tiempo y avance.
- Estados operativos protegidos y un historial inmutable de cambios, responsables, IP y agente de usuario.
- Relaciones entre tickets, identificación de duplicados y asociación con un caso principal.
- Registro de solución y evidencias antes de remitir el resultado al solicitante.
- Aceptación electrónica, calificación del servicio, cierre, reapertura y cancelación justificada.

Los datos locales incluyen 17 permisos específicos, tres tickets demostrativos y un usuario solicitante activo:

- Correo: `usuario.ticket@zolicguate.com`
- Contraseña: `Tickets#2026`

Los archivos de tickets se guardan en el disco privado de Laravel y solo se entregan después de validar autenticación, permiso, alcance del ticket y visibilidad interna.

## Módulo 5: tickets de desarrollo y requisitos de seguridad

La quinta entrega implementa los requisitos RF-045 a RF-050:

- Formulario especializado para nuevos sistemas, módulos, mejoras, correcciones y automatizaciones.
- Expediente técnico con necesidad institucional, objetivo, alcance, beneficios, restricciones, sistema afectado y fecha esperada.
- Registro trazable de requisitos funcionales, historias de usuario, criterios de aceptación, restricciones técnicas y requisitos de seguridad.
- Clasificación de información pública, interna, confidencial o restringida, con identificación obligatoria de datos sensibles y terceros.
- Matriz de controles para autenticación, autorización, cifrado, bitácoras, validación de entradas y manejo seguro de errores.
- Perfiles de acceso con permisos y alcance de datos, aplicando mínimo privilegio.
- Evidencias privadas para pruebas funcionales, técnicas, de seguridad y aceptación del usuario.
- Puerta de producción que exige requisitos completos, controles verificados y las cuatro clases de prueba aprobadas con archivo.
- Autorización exclusiva de Jefatura de IT para desplegar y cerrar, con rechazo justificado y retorno a proceso.
- Políticas de alcance, bitácora de cambios y bloqueo de modificaciones mientras exista una autorización pendiente.

El sembrado agrega 9 permisos del módulo y una solicitud demostrativa asignada al grupo de Desarrollo de Sistemas.

## Bloque 6: inventario de equipos e insumos técnicos

La sexta entrega implementa los requisitos RF-052 a RF-061:

- Registro de computadoras, servidores, impresoras, equipos de red, teléfonos, periféricos y otros activos.
- Código institucional único normalizado, etiqueta imprimible y código de barras Code 39 sin dependencias externas.
- Datos de red con validación de dirección IP, MAC, nombre de dispositivo, segmento y conectividad.
- Ficha técnica con marca, modelo, serie, procesador, memoria, almacenamiento, sistema operativo, garantía, costo y especificaciones adicionales.
- Custodia por área, departamento, responsable, ubicación y estado operativo.
- Historial trazable de mantenimientos, reparaciones, inspecciones, configuraciones y cambios, con vínculo seguro a tickets visibles.
- Asignaciones, devoluciones, traslados, cambios de estado y bajas con autorización explícita.
- Licencias asociadas a los equipos y control de fechas de vencimiento.
- Catálogo de insumos con unidad de medida, ubicación, existencias y nivel mínimo.
- Kardex de entradas, salidas, ajustes y consumos, con responsable, autorización y justificación.
- Reserva de insumos por ticket, consumo automático al aceptar la solución y liberación sin descuento al cancelar.

Los datos demostrativos incluyen cinco activos tecnológicos y cinco insumos técnicos. El registro básico, correlativo, historial, adjuntos y relaciones de tickets indicados como dependencia ya se encuentran cubiertos por el Módulo 4 y se reutilizan sin duplicar estructuras.

## Bloque 7: mantenimiento preventivo de equipos

La séptima entrega implementa los requisitos RF-062 a RF-066:

- Planes preventivos configurables con código, frecuencia mensual, trimestral, semestral o anual, anticipación y duración estimada.
- Asignación de equipos, área de IT, grupo técnico y responsable, validando la pertenencia del técnico al grupo seleccionado.
- Calendario mensual, trimestral y anual con filtros por área, departamento, ubicación y agrupación configurable.
- Programación individual por equipo, activación o suspensión de la programación y registro de la próxima fecha.
- Generación manual o automática de órdenes correlativas y tickets internos antes de la fecha prevista, evitando duplicados.
- Cambio controlado del equipo a estado de mantenimiento durante la ejecución y restauración de su estado al completar el trabajo.
- Lista de verificación obligatoria, resultado, observaciones, tiempo empleado y trazabilidad del técnico responsable.
- Evidencias privadas con nombre aleatorio, huella SHA-256, tipos permitidos y descarga protegida por autorización.
- Integración del historial preventivo en la ficha del equipo y vínculo bidireccional entre orden, activo y ticket.

La generación automática está registrada en el programador de Laravel para ejecutarse diariamente a las 06:00. También puede ejecutarse manualmente con:

```powershell
C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\php.exe artisan maintenance:generate-orders
```

En producción, el Programador de tareas de Windows debe ejecutar `php artisan schedule:run` cada minuto para activar esta automatización.

## Bloque 8: operación, notificaciones, indicadores y reportes

La entrega final implementa los requisitos RF-067 a RF-070 y completa la operación formal de IT:

- Bandeja general de IT, bandeja personal, consulta por grupo, técnico y tickets sin asignar.
- Filtros por estado, prioridad, condición SLA y búsqueda, con ordenamiento por fecha objetivo.
- Panel de carga de trabajo con casos abiertos, vencidos y horas registradas durante los últimos 30 días.
- Asignación automática y manual, transferencia, aceptación o rechazo, comentarios, notas internas, diagnóstico, actividades y tiempo, reutilizando el flujo trazable del bloque de tickets.
- Registro y resolución de dependencias con terceros, incluyendo referencia, fecha compromiso y cambio controlado de estado.
- Escalamiento manual o automático a Encargados y Jefatura de IT, con motivo, responsable, fecha e historial.
- Centro de notificaciones internas con avisos por correo sobre creación, aprobación, asignación, comentarios, cambios de estado, solución y alertas.
- Alertas únicas e idempotentes por vencimiento o proximidad de SLA, compromisos de terceros y mantenimientos preventivos.
- Indicadores de tickets, carga, cumplimiento de respuesta y resolución, satisfacción y reaperturas.
- Reportes filtrables de tickets, SLA, técnicos, satisfacción, recurrencias, equipos, insumos, mantenimientos y auditoría.
- Exportaciones CSV UTF-8 limitadas a 10,000 filas y protegidas contra inyección de fórmulas.
- Restricción de reportes y bandejas según permisos, grupos técnicos y alcance del usuario.

El procesador de alertas se ejecuta cada cinco minutos cuando el programador de Laravel está activo. También puede ejecutarse manualmente con:

```powershell
C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\php.exe artisan operations:process-alerts
```

## Seguridad aplicada

El proyecto toma como referencia las recomendaciones OWASP proporcionadas:

- Control de acceso y permisos del lado del servidor.
- Consultas mediante Eloquent y parámetros preparados.
- Validación estricta de datos y correos institucionales.
- Protección CSRF y escape automático en las vistas.
- Regeneración y control de identificadores de sesión.
- Bloqueo contra fuerza bruta y recuperación segura de credenciales.
- Cabeceras CSP, anti-iframe, `nosniff` y política de referencia.
- Auditoría de eventos administrativos y de autenticación.
- Validación de pertenencia entre categorías, subcategorías, áreas, grupos y responsables.
- Prevención de reglas duplicadas, delegaciones superpuestas y configuraciones SLA inconsistentes.
- Listas permitidas para tipos de campo, responsables y condiciones configurables.
- Políticas por ticket para evitar acceso horizontal entre solicitantes.
- Flujo de estados protegido para impedir asignar o solucionar tickets sin completar su aprobación.
- Nombres no predecibles y descarga forzada para evidencias privadas.
- Bloqueo optimista de edición para evitar sobrescrituras entre sesiones concurrentes.
- Verificación separada de controles de seguridad y autorización de despliegues mediante permisos privilegiados.
- Evidencias de prueba enlazadas por clave foránea, con almacenamiento privado, huella SHA-256 y lista de extensiones permitidas.
- Revalidación de toda la puerta de producción al momento de autorizar para evitar cierres con controles incompletos.
- Bloqueos transaccionales de filas para impedir sobreconsumo de existencias ante solicitudes simultáneas.
- Restricción del inventario por políticas y permisos separados para consulta, ficha técnica, custodia, historial, kardex y consumo.
- Validación cruzada de área, departamento, responsable, autorizador y visibilidad del ticket antes de guardar relaciones.
- Identificadores normalizados y únicos para inventario, series y direcciones MAC.

## Recursos visuales

El inicio de sesión y la navegación utilizan los logotipos y fotografías oficiales suministrados, almacenados en `public/images/brand`.

Para reconstruir los datos de desarrollo:

```powershell
C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\php.exe artisan migrate:fresh --seed
```

## Recuperación por correo en desarrollo

El envío está configurado con el transporte `log`, por lo que el mensaje y su enlace seguro se registran en:

`storage/logs/laravel.log`

Para un servidor institucional, configura las variables `MAIL_*` del archivo `.env` con las credenciales SMTP autorizadas.

## Validación

```powershell
C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\php.exe -d extension_dir=C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\ext -d extension=pdo_sqlite -d extension=sqlite3 vendor\phpunit\phpunit\phpunit
C:\Program Files\nodejs\npm.cmd run build
```
