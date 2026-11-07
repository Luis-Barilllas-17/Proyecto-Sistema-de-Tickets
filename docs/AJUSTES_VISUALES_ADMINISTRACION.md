# Ajustes visuales de inventario y administración

Revisión: 5 de noviembre de 2026, Guatemala.

## Cambios realizados

- **Inventario:** filtros adaptables al espacio disponible, mayor ancho para el catálogo en pantallas intermedias y controles superiores para desplazarse horizontalmente. Las flechas y la barra superior se sincronizan con la tabla. Aparecen cuando hay columnas fuera de la vista; todas las columnas se conservan también en móvil.
- **Control de accesos:** alineación de «Contraseña» y del selector de estado en todas las filas. La cuenta actual reserva el mismo espacio aunque no tenga botón para guardar su propio estado. Se corrigió también el desbordamiento lateral de etiquetas ocultas y el margen del avatar.
- **Notificaciones:** campana de silueta sólida, siguiendo el icono de referencia y usando el azul institucional. Se utiliza en la cabecera y en la opción del menú; mantiene el contador y el enlace a notificaciones.
- **Crear/configurar rol:** nombre y descripción alineados desde arriba, con «Rol activo» en una fila independiente. En móvil los campos se apilan. Los botones finales ya no flotan sobre los permisos.
- **Registrar/editar estados:** margen interior uniforme para campos, opciones y botón. El botón permanece dentro de su panel.
- **Menú lateral:** eliminado el recuadro «Sesión protegida». Se conservan el cierre de sesión y el control de inactividad existentes.

## Cómo comprobarlo

Puedes utilizar el [servidor QA](http://127.0.0.1:8017/login) y las cuentas descritas en [la guía de pruebas de tickets](PRUEBAS_GENERAR_TICKET.md). Si el servidor está detenido, esa guía incluye los comandos para iniciarlo.

1. Abre **Inventario**. Cuando no quepan todas las columnas, utiliza las flechas situadas sobre la tabla o su barra superior para llegar a «Acciones», sin bajar al final del catálogo. Comprueba también «Filtrar» y «Limpiar».
2. Abre **Control de acceso**. Compara la fila de tu cuenta con las demás: «Contraseña» y los selectores deben comenzar en las mismas posiciones.
3. Abre **Administración → Roles → Crear rol**. Comprueba la alineación de las etiquetas, la fila de activación y la distribución en móvil.
4. Abre **Configuración → Estados, prioridades y SLA**. El botón «Registrar estado» debe tener margen a la derecha y debajo.
5. Comprueba la campana superior y su enlace al centro de notificaciones. El menú lateral ya no debe mostrar «Sesión protegida».

## Capturas

| Pantalla | Escritorio, 1280 px | Móvil, 390 px |
| --- | --- | --- |
| Inventario | [Ver](ajustes-interfaz/inventario-1280.png) | [Ver](ajustes-interfaz/inventario-390.png) |
| Control de accesos | [Ver](ajustes-interfaz/accesos-1280.png) | [Ver](ajustes-interfaz/accesos-390.png) |
| Crear rol | [Ver](ajustes-interfaz/crear-rol-1280.png) | [Ver](ajustes-interfaz/crear-rol-390.png) |
| Registrar estados | [Ver](ajustes-interfaz/estados-1280.png) | [Ver](ajustes-interfaz/estados-390.png) |

[Campana y menú lateral sin el recuadro](ajustes-interfaz/campana-y-navegacion.png).

Las tablas anchas tienen desplazamiento propio: una captura de móvil muestra la parte inicial, y las columnas restantes se consultan desplazando la tabla.

## Verificación

- **25 pruebas de PHP, 164 comprobaciones:** administración, configuración e inventario; todas correctas.
- **30 escenarios en navegador:** cuatro pantallas en siete anchos (1600, 1440, 1280, 1024, 768, 390 y 320 px), más la comprobación de menú/campana de técnico y usuario. Sin errores de JavaScript ni respuestas HTTP fallidas.
- Desplazamiento superior y de tabla sincronizados en ambos sentidos; controles ocultos cuando la tabla cabe completa.
- Filtros de inventario operativos, acciones de cuentas alineadas, campos de roles dentro del panel y botón de estados sin desbordamiento.
- Compilación de recursos completada correctamente.

El navegador rellenó campos de ejemplo para comprobar la distribución, sin guardar roles ni estados nuevos. Los cambios visuales no requieren una migración de base de datos.

El informe completo está en [resultado.json](ajustes-interfaz/resultado.json). La comprobación puede repetirse con `scripts/qa/check-interface-layout.cjs`, usando el servidor QA y Playwright del entorno indicados en la guía anterior.
