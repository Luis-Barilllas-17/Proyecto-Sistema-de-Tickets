# Sistema de Gestión de Tickets ZOLIC

Primera entrega del sistema institucional de tickets, desarrollada con Laravel 12, PHP 8.2, MySQL y Vite.

## Alcance implementado

El Módulo 1 cubre autenticación y recuperación de contraseña, sin autenticación multifactor:

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
C:\laragon\bin\php\php-8.2.28-Win32-vs16-x64\php.exe -d extension=pdo_sqlite vendor\phpunit\phpunit\phpunit
C:\Program Files\nodejs\npm.cmd run build
```
