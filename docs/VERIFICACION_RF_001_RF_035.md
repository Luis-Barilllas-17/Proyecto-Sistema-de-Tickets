# Trazabilidad temporal del sprint — RF-001 a RF-035

**Sistema:** Sistema de Gestión de Tickets ZOLIC  
**Fecha de revisión:** 10 de septiembre de 2026  
**URL local:** `http://sistema-tickets.test`  
**Alcance:** autenticación, administración organizacional, parametrización y gestión integral de tickets.

Este documento contiene únicamente los **35 requisitos vigentes del sprint**. La numeración es correlativa del RF-001 al RF-035.

## Resultado general

| Resultado | Cantidad | Porcentaje |
|---|---:|---:|
| Cumple | 34 | 97.1 % |
| Cumple parcialmente | 1 | 2.9 % |
| Pendiente | 0 | 0 % |

La única brecha funcional detectada corresponde al RF-014: existen especialidades, área, departamento y grupos técnicos, pero aún faltan controles específicos de disponibilidad, ubicación habitual y carga actual o máxima de tickets.

## Ubicación de los requisitos en orden

Las rutas con `{id}` requieren sustituir el marcador por el identificador de un registro existente. Algunas opciones solo aparecen si el usuario posee el permiso indicado.

| RF | Requisito | Estado | Pantalla o dirección principal | Dónde se verifica |
|---|---|---|---|---|
| RF-001 | Inicio de sesión | **Cumple** | `/login` | Formulario de acceso institucional y registro de acceso exitoso. |
| RF-002 | Recuperación de contraseña | **Cumple** | `/recuperar-contrasena` y `/restablecer-contrasena/{token}` | Solicitud del enlace, validación del token y creación de una contraseña nueva. |
| RF-003 | Restablecimiento administrativo | **Cumple** | `/seguridad/usuarios/{id}/contrasena` | Super Administrador asigna clave temporal, obliga el cambio y cierra sesiones. |
| RF-004 | Bloqueo por intentos fallidos | **Cumple** | `/login`; resultado administrativo en `/seguridad` | Control automático después de cinco intentos fallidos; no tiene formulario propio. |
| RF-005 | Gestión de sesiones | **Cumple** | `/panel` y `/seguridad` | Lista de dispositivos, cierre propio, cierre administrativo y expiración por inactividad. |
| RF-006 | Activación y desactivación de cuentas | **Cumple** | `/administracion/usuarios` y `/seguridad` | Estados activa, suspendida e inactiva, sin eliminar el historial. |
| RF-007 | Registro de accesos | **Cumple** | `/seguridad` | Bitácora de accesos exitosos, fallidos, bloqueos, cierres y cambios de contraseña. |
| RF-008 | Administración de usuarios | **Cumple** | `/administracion/usuarios` y `/administracion/usuarios/crear` | Crear, consultar, editar y cambiar estado. |
| RF-009 | Gestión de perfiles de usuario | **Cumple** | `/administracion/usuarios/{id}/editar` y `/administracion/roles` | Asignación de uno o varios perfiles institucionales. |
| RF-010 | Gestión de permisos | **Cumple** | `/administracion/roles/{id}/editar` | Matriz por sección y acción; el servidor vuelve a validar cada permiso. |
| RF-011 | Administración de áreas y departamentos | **Cumple** | `/administracion/organizacion` | Altas, ediciones, jerarquía y jefaturas. |
| RF-012 | Administración de áreas de IT | **Cumple** | `/administracion/organizacion` | Áreas internas como Desarrollo, Infraestructura y Soporte Técnico. |
| RF-013 | Gestión de grupos técnicos | **Cumple** | `/administracion/grupos-tecnicos` | Grupos, coordinación, integrantes y asociación con áreas internas. |
| RF-014 | Perfil técnico y disponibilidad | **Parcial** | `/administracion/grupos-tecnicos` y `/administracion/usuarios/{id}/editar` | Especialidades, grupos, área y departamento sí existen; faltan disponibilidad, ubicación habitual y carga máxima/actual. |
| RF-015 | Administración de categorías | **Cumple** | `/configuracion/categorias` | Crear, editar, activar y desactivar categorías. |
| RF-016 | Administración de subcategorías | **Cumple** | `/configuracion/categorias` | Subcategorías dependientes de una categoría válida. |
| RF-017 | Diseñador de formularios | **Cumple** | `/configuracion/formularios` y `/configuracion/formularios/crear` | Creación, edición, publicación, versionado y vista previa. |
| RF-018 | Campos personalizados | **Cumple** | `/configuracion/formularios/{id}/editar` | Texto, lista, fecha, número, archivo, múltiple y casilla. |
| RF-019 | Configuración de campos obligatorios | **Cumple** | `/configuracion/formularios/{id}/editar` | Controles obligatorio, visible, solo lectura y activo. |
| RF-020 | Asociación de formularios | **Cumple** | `/configuracion/formularios/crear` | Asociación por categoría y subcategoría con validación cruzada. |
| RF-021 | Reglas de aprobación | **Cumple** | `/configuracion/aprobaciones` | Condiciones que determinan si el ticket necesita autorización. |
| RF-022 | Aprobaciones multinivel | **Cumple** | `/configuracion/aprobaciones/{id}/editar` | Niveles ordenados, responsables, suplencias y delegaciones temporales. |
| RF-023 | Reglas de direccionamiento | **Cumple** | `/configuracion/direccionamiento` | Destino por área de IT, grupo técnico o responsable. |
| RF-024 | Estados, prioridades y SLA | **Cumple** | `/configuracion/niveles-servicio` | Estados, impacto, urgencia, matriz de prioridad y tiempos objetivo. |
| RF-025 | Creación de tickets por solicitantes | **Cumple** | `/tickets/crear` | Registro y envío de solicitudes, incidentes y requerimientos. |
| RF-026 | Creación de tickets internos de IT | **Cumple** | `/tickets/crear` | Opción condicional para personal con `tickets.create_internal`. |
| RF-027 | Número correlativo y borrador | **Cumple** | `/tickets/crear`, `/tickets?scope=drafts` y `/tickets/{id}/editar` | Correlativo anual único, guardar y editar borrador antes de enviar. |
| RF-028 | Adjuntos y evidencias | **Cumple** | `/tickets/crear` y `/tickets/{id}` | Archivos privados, tipos permitidos, límite de tamaño, hash y descarga autorizada. |
| RF-029 | Vinculación con equipos | **Cumple** | `/tickets/crear` y `/tickets/{id}` | Asociación de uno o varios códigos de inventario. |
| RF-030 | Clasificación del ticket | **Cumple** | `/tickets/crear` y `/tickets/{id}` | Categoría, subcategoría, área, ubicación y tipo de atención. |
| RF-031 | Evaluación de impacto, urgencia y prioridad | **Cumple** | `/tickets/crear` y `/tickets/{id}` | La prioridad se calcula en servidor mediante la matriz configurada. |
| RF-032 | Seguimiento de aprobaciones | **Cumple** | `/tickets/{id}` y `/tickets?scope=approvals` | Estado general y detalle por nivel: pendiente, aprobado, rechazado o devuelto. |
| RF-033 | Asignación de tickets | **Cumple** | `/tickets/{id}` y `/tickets?scope=assigned` | Asignación manual y automática según direccionamiento. |
| RF-034 | Reasignación y transferencia | **Cumple** | `/tickets/{id}` | Traslado entre área, grupo y técnico con motivo e historial. |
| RF-035 | Aceptación de asignación | **Cumple** | `/tickets/{id}` | El técnico asignado acepta o rechaza; el rechazo exige justificación. |

## Pruebas de requisitos sin pantalla propia o de aparición condicional

### RF-002 — correo de recuperación en entorno local

1. Abre `/recuperar-contrasena` y solicita el enlace con una cuenta activa.
2. En desarrollo, revisa `storage/logs/laravel.log` y abre la URL de restablecimiento registrada.
3. Define una contraseña válida e intenta reutilizar el enlace; el segundo uso debe ser rechazado.

El formulario no debe revelar si un correo desconocido existe o no en la base de datos.

### RF-004 — bloqueo automático

1. En `/login`, utiliza cinco veces un correo activo con una contraseña incorrecta.
2. En el siguiente intento, incluso con la contraseña correcta, el acceso debe permanecer bloqueado durante el tiempo configurado.
3. Ingresa como Super Administrador a `/seguridad` y confirma el evento de bloqueo.
4. Usa “Desbloquear” y verifica que la cuenta pueda volver a iniciar sesión.

### RF-005 — expiración y cierre de sesiones

1. Inicia la misma cuenta en dos navegadores distintos.
2. En `/panel`, finaliza la otra sesión; ese navegador debe perder el acceso en su siguiente petición.
3. Para la inactividad, reduce temporalmente `SESSION_TIMEOUT_MINUTES` en `.env`, limpia configuración y espera el intervalo antes de volver a navegar.

### RF-010 — permisos protegidos en servidor

1. Retira de un perfil un permiso, por ejemplo `categories.manage`.
2. Confirma que la acción desaparezca de la interfaz.
3. Intenta enviar directamente la petición protegida: el servidor debe responder `403` aunque se escriba manualmente la URL.

### RF-019 — campos ocultos, obligatorios y de solo lectura

1. Configura un campo obligatorio, uno oculto y otro de solo lectura.
2. Abre `/tickets/crear` con la clasificación asociada.
3. Manipula la petición para omitir el obligatorio o modificar el campo de solo lectura; el servidor debe rechazar los valores inválidos.

### RF-028 — privacidad de adjuntos

1. Adjunta un archivo a un ticket del Usuario A.
2. Copia la dirección de descarga `/tickets/{ticket}/archivos/{attachment}`.
3. Inicia sesión como un Usuario B sin acceso al ticket e intenta abrirla; debe recibir `403`.

### RF-031 y RF-033 — cálculo y asignación automáticos

1. Configura la matriz en `/configuracion/niveles-servicio` y una regla en `/configuracion/direccionamiento`.
2. Envía un ticket que coincida con esa clasificación.
3. En `/tickets/{id}`, comprueba que prioridad, SLA y asignación coincidan con la matriz y la regla, sin aceptar valores manipulados por el navegador.

### RF-032 — aprobación condicional

1. Crea una regla activa en `/configuracion/aprobaciones` para una categoría.
2. Envía un ticket de esa categoría.
3. Confirma que quede pendiente y que únicamente el aprobador autorizado pueda decidir cada nivel.

### RF-035 — aceptación o rechazo por el técnico asignado

1. Asigna un ticket a un técnico y entra con la cuenta de ese técnico.
2. En `/tickets/{id}`, acepta la asignación.
3. Repite con otro ticket y recházalo sin motivo; debe fallar la validación.
4. Agrega una justificación y confirma que el rechazo aparezca en el historial.

## Cuentas de desarrollo

> Estas credenciales son exclusivamente para pruebas locales. Deben eliminarse o rotarse antes de producción.

| Usuario | Perfil | Correo | Contraseña | Estado |
|---|---|---|---|---|
| Administrador del sistema | Super Administrador | `admin@zolicguate.com` | `Zolic#2026` | Activa |
| Jefatura de IT | Jefatura de IT | `jefatura.it@zolicguate.com` | `Jefatura#2026` | Activa |
| Mesa de ayuda | Técnico IT | `soporte@zolicguate.com` | `Soporte#2026` | Activa |
| Técnico de infraestructura | Técnico IT | `infraestructura@zolicguate.com` | `Infra#2026` | Activa |
| Soporte de aplicaciones | Programador IT | `aplicaciones@zolicguate.com` | `Apps#2026A` | Activa |
| Usuario de tickets | Usuario Solicitante | `usuario.ticket@zolicguate.com` | `Tickets#2026` | Activa |
| Usuario solicitante | Usuario Solicitante | `solicitante@zolicguate.com` | `Usuario#2026` | Suspendida intencionalmente |
| Cuenta temporal | Usuario Solicitante | `temporal@zolicguate.com` | `Temporal#2026` | Suspendida intencionalmente |
| Cuenta fuera de servicio | Usuario Solicitante | `inactivo@zolicguate.com` | `Inactivo#2026` | Inactiva intencionalmente |
| Cuenta para bloqueo | Jefatura de Área Solicitante | `control.acceso@zolicguate.com` | `Control#2026` | Activa; puede conservar un bloqueo de prueba |

## Evidencia automatizada

| Área | Archivo de prueba |
|---|---|
| RF-001 a RF-007 | `tests/Feature/AuthenticationModuleTest.php` |
| RF-008 a RF-014 | `tests/Feature/ModuleTwoTest.php` |
| RF-015 a RF-024 | `tests/Feature/ModuleThreeTest.php` |
| RF-025 a RF-035 | `tests/Feature/ModuleFourTest.php` |

Comando general desde la terminal de Laragon:

```powershell
php artisan test
```

**Última validación:** 54 pruebas aprobadas y 371 aserciones. Esta suite también incluye funcionalidades posteriores al alcance de este documento.

## Registro de la marcación temporal

Las etiquetas agregadas para revisar el sprint están identificadas en las vistas con el comentario `TEMP-RF-TRACE` y el estilo temporal `.rf-trace-badge`. También se renumeraron los textos RF que ya existían en las vistas comprendidas entre autenticación y aceptación de asignación. Cuando se solicite retirar esta ayuda visual, se deben quitar únicamente esas etiquetas y restaurar los encabezados descriptivos, sin modificar controladores, rutas, políticas ni datos.

Archivos visuales intervenidos para esta marcación:

- `resources/views/auth/*.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/security/*.blade.php`
- `resources/views/administration/**/*.blade.php`
- `resources/views/configuration/**/*.blade.php`
- `resources/views/tickets/form.blade.php`
- `resources/views/tickets/index.blade.php`
- `resources/views/tickets/show.blade.php`
- `resources/css/app.css`
