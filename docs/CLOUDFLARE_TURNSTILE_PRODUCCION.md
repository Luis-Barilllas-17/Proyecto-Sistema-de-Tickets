# Configuración de Cloudflare Turnstile en producción

Esta guía completa la integración de Cloudflare Turnstile incluida en el formulario de inicio de sesión del Sistema de Tickets ZOLIC.

## Comportamiento implementado

- En producción, Turnstile puede mostrarse en el inicio de sesión y su token se valida en el servidor antes de comprobar las credenciales.
- En desarrollo y pruebas permanece desactivado mediante `TURNSTILE_ENABLED=false`.
- La validación comprueba el resultado de Cloudflare, la acción `login` y el dominio esperado.
- Si Cloudflare no responde, el token es inválido, está vencido o ya fue utilizado, el acceso se rechaza de forma segura.
- La clave secreta nunca se envía al navegador ni debe guardarse en Git.

## 1. Crear el widget en Cloudflare

1. Inicia sesión en [Cloudflare Dashboard](https://dash.cloudflare.com/).
2. Abre **Turnstile** y selecciona **Add widget**.
3. Asigna un nombre identificable, por ejemplo `Sistema Tickets ZOLIC - Producción`.
4. Selecciona el modo **Managed**.
5. Agrega únicamente el dominio real de producción, por ejemplo `tickets.zolicguate.com`.
6. No agregues `localhost`, `127.0.0.1`, protocolos, rutas ni puertos al widget de producción.
7. Guarda el widget y copia la **sitekey** y la **secret key**.

La sitekey es pública y se utiliza en el HTML. La secret key es privada y se utiliza únicamente en el servidor.

## 2. Configurar el archivo `.env` de producción

Edita el `.env` del servidor de producción y agrega:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tickets.zolicguate.com

TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=COLOCA_AQUI_LA_SITEKEY
TURNSTILE_SECRET_KEY=COLOCA_AQUI_LA_SECRET_KEY
TURNSTILE_EXPECTED_HOSTNAME=tickets.zolicguate.com
TURNSTILE_EXPECTED_ACTION=login
TURNSTILE_TIMEOUT_SECONDS=7
```

Reglas importantes:

- `TURNSTILE_EXPECTED_HOSTNAME` debe contener solo el dominio, sin `https://`, rutas ni `/` final.
- `TURNSTILE_EXPECTED_ACTION` debe conservar el valor `login`.
- No compartas la secret key por correo, capturas de pantalla ni repositorios.
- El `.env` no debe subirse a Git.

En el equipo de desarrollo conserva:

```dotenv
APP_ENV=local
TURNSTILE_ENABLED=false
```

Así el formulario local no solicita CAPTCHA ni realiza llamadas a Cloudflare.

## 3. Aplicar la configuración de Laravel

Desde la carpeta del proyecto en el servidor ejecuta:

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Si se despliega una compilación nueva del frontend, ejecuta también:

```powershell
npm ci
npm run build
```

## 4. Requisitos de red y seguridad

El servidor debe poder realizar conexiones HTTPS salientes hacia:

```text
https://challenges.cloudflare.com
```

La aplicación ya permite en su Content Security Policy únicamente los scripts e iframes de ese origen cuando Turnstile está habilitado.

El sitio de producción debe utilizar HTTPS. Si existe un proxy inverso o balanceador, configura correctamente los proxies confiables de Laravel para que `$request->ip()` represente al visitante real antes de enviar su IP opcional a Cloudflare.

## 5. Verificar el funcionamiento

1. Abre el sitio de producción en una ventana privada.
2. Comprueba que aparezca **Verificación de seguridad** antes del botón de ingreso.
3. Completa la verificación y accede con un usuario válido.
4. Confirma que el acceso llegue al panel.
5. Intenta enviar el formulario sin completar Turnstile: debe mostrarse un error y no debe iniciar sesión.
6. Revisa `storage/logs/laravel.log` si Cloudflare rechaza una validación.
7. Revisa las estadísticas del widget en Cloudflare Turnstile Analytics.

## 6. Problemas comunes

### El widget no aparece

- Confirma que `TURNSTILE_ENABLED=true`.
- Confirma que la sitekey sea correcta.
- Ejecuta nuevamente `php artisan optimize:clear` y `php artisan config:cache`.
- Verifica que el navegador pueda acceder a `challenges.cloudflare.com`.

### Cloudflare indica dominio no autorizado

Agrega el dominio exacto en **Hostname Management** del widget y verifica que `TURNSTILE_EXPECTED_HOSTNAME` coincida.

### Aparece `timeout-or-duplicate`

El token venció o ya fue utilizado. Los tokens duran cinco minutos y son de un solo uso. Recarga el formulario para generar uno nuevo.

### El servidor no puede validar

Verifica DNS, firewall, proxy corporativo, certificados TLS y acceso HTTPS saliente a Cloudflare. La aplicación rechaza el ingreso cuando no puede confirmar el token.

## 7. Desactivación de emergencia

Solo si Cloudflare presenta una interrupción confirmada y existe autorización administrativa, cambia temporalmente:

```dotenv
TURNSTILE_ENABLED=false
```

Después ejecuta:

```powershell
php artisan optimize:clear
php artisan config:cache
```

Esta medida elimina la protección anti-bots del inicio de sesión. Reactívala en cuanto finalice la incidencia.

## 8. Rotación de la clave secreta

Rota la secret key periódicamente o inmediatamente si existe sospecha de exposición. Actualiza `TURNSTILE_SECRET_KEY` en producción y reconstruye la caché de configuración. Nunca reemplaces la sitekey por la secret key en el HTML.

## Referencias oficiales

- [Introducción a Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/get-started/)
- [Incrustar el widget](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/)
- [Validación del token con Siteverify](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)
- [Content Security Policy para Turnstile](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)
- [Claves oficiales para pruebas](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)
