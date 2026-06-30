# Configuración de Servidor

## Entornos

El proyecto opera en 2 entornos. Cada uno requiere configuración específica de PHP-FPM, OPcache y `.env`.

---

## Servidor de Desarrollo

### PHP-FPM (`/etc/php/*/fpm/pool.d/www.conf`)

```ini
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.process_idle_timeout = 60s
```

**Justificación:**
- `idle_timeout=60s` evita cold starts tras breves periodos de inactividad (el default de 10s es muy agresivo)
- `min_spare_servers=2` mantiene workers calientes para respuesta inmediata
- `max_children=10` da margen para pruebas concurrentes

### OPcache (`php.ini`)

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.validate_timestamps=1
opcache.interned_strings_buffer=8
```

**Nota:** `validate_timestamps=1` es necesario en desarrollo para detectar cambios en archivos sin reiniciar PHP.

### `.env` (valores específicos de desarrollo)

```
CACHE_STORE=file
SESSION_DRIVER=file
APP_ENV=local
APP_DEBUG=true
```

### Post-deploy

Tras cada `git pull`, el hook `post-merge` ejecuta **`php artisan optimize:clear`**
(limpia caches, NO los recachea — evita envenenar los tests, incidente 2026-05-04).
Si el hook no está configurado:

```bash
git config core.hooksPath .githooks
```

El warm de caches y migraciones NO los hace el hook — correrlos a mano (ver el
flujo de Producción más abajo). **Nunca `php artisan optimize`** (incluye `config:cache`).

---

## Servidor de Producción

### PHP-FPM (`/etc/php/*/fpm/pool.d/www.conf`)

Usar `pm = static` para eliminar cold starts completamente. Calcular `max_children` según RAM disponible (~50MB por worker):

| RAM del servidor | max_children |
|---|---|
| 2GB | 20 |
| 4GB | 40 |
| 8GB | 80 |

```ini
; Para servidor de 4GB (ajustar max_children según RAM real)
pm = static
pm.max_children = 40
pm.max_requests = 500
```

**Justificación:**
- `pm=static` mantiene todos los workers vivos permanentemente, sin cold starts
- `max_requests=500` recicla workers después de 500 requests para prevenir memory leaks
- Con muchos usuarios concurrentes de múltiples comercios, cada request ocupa un worker hasta terminar

### OPcache (`php.ini`)

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60
opcache.validate_timestamps=0
opcache.interned_strings_buffer=16
opcache.save_comments=1
```

**CRÍTICO:** Con `validate_timestamps=0`, OPcache **nunca** relee los `.php` cambiados
hasta un reload de FPM. Por eso, después de CADA deploy (obligatorio, no opcional):

```bash
sudo systemctl reload php*-fpm
```

Si lo omitís, el código nuevo está en disco pero OPcache sigue ejecutando el bytecode
viejo → un fix de performance (p.ej. `VoltServiceProvider`) "deployado" pero sin efecto.
Es la causa #1 de "deployé y sigue igual de lento". **No usar `php artisan optimize`**
(incluye `config:cache`); warmear sólo `view`/`route`/`event` (ver flujo de deploy abajo).

### `.env` (valores específicos de producción)

```
CACHE_STORE=file
SESSION_DRIVER=file
APP_ENV=production
APP_DEBUG=false
```

**Futuro:** Cuando se instale Redis en el servidor, cambiar a:
```
CACHE_STORE=redis
SESSION_DRIVER=redis
```
Redis es in-memory y el store ideal para producción multi-usuario. Instalación:
```bash
sudo apt install redis-server
composer require predis/predis
```

### Post-deploy

El flujo de deploy en producción debe ser:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # migraciones tenant (iteran todos los comercios)
npm ci && npm run build              # public/build está gitignored → se compila acá
php artisan deploy:warm              # caches SEGURAS en un comando: view+route+event+icons (NO config:cache)
                                     # icons:cache es CRÍTICO: sin él blade-icons escanea ~1200 SVGs/request (~600ms)
sudo systemctl reload php*-fpm       # OBLIGATORIO en prod (validate_timestamps=0):
                                     # sin esto OPcache sigue corriendo el código viejo
```

**Importante:**
- El hook `post-merge` ejecuta `php artisan optimize:clear` (NO `optimize`): limpia
  caches sin recachear config, para no envenenar los tests (incidente 2026-05-04).
  Por eso `migrate`, `build` y el warm de caches **NO están automatizados** — corrélos a mano.
- **NO usar `php artisan optimize`** (incluye `config:cache`). Warmear solo las tres
  caches seguras: `view`, `route`, `event`.
- El reload de FPM es manual (requiere sudo) y en prod es **obligatorio**
  (`validate_timestamps=0`): es lo que hace que el código nuevo realmente se ejecute.

> **Gotchas de deploy** (Volt mount lento, PWA sirviendo vistas viejas, cómo
> diagnosticar lentitud): ver `.claude/docs/deploy-playbook.md`.

---

## Integraciones de Pago (Mercado Pago QR)

El cobro por QR de Mercado Pago necesita configuración adicional en el servidor para
funcionar end-to-end. Hay dos playbooks detallados; esta sección resume lo que el
servidor debe garantizar.

### Webhook (confirmación en tiempo real)

- **Ruta pública**: el proxy debe enrutar `POST https://<DOMINIO>/api/integraciones/mercadopago/webhook`
  a la app, sin auth ni IP-whitelist (MP llega desde sus IPs). Es una URL **única
  global**; la app resuelve la sucursal por el `user_id` MP del payload vía la tabla
  `mercadopago_collector_index` (DB `config`).
  ```bash
  # Debe responder 200 (payload vacío → {"status":"ignored"})
  curl -i -X POST https://<DOMINIO>/api/integraciones/mercadopago/webhook \
    -H "Content-Type: application/json" -d '{}'
  ```
- **Reverb corriendo** (el mismo que usa Pedidos en tiempo real): el broadcast del
  pago confirmado llega al navegador del cajero por `wss://<DOMINIO>/app/{key}`. En
  `.env`: `BROADCAST_CONNECTION=reverb` + `REVERB_*`; en el build del front:
  `VITE_REVERB_HOST/PORT/SCHEME`. Si se tocan las `VITE_*`, hace falta `npm run build`.
- **Caches tras deploy**: `php artisan route:cache` (sin esto la ruta del webhook
  puede dar 404). NO `php artisan optimize` (incluye `config:cache`, prohibido).
- **Panel de MP**: por cada sucursal/aplicación, registrar la misma URL del webhook,
  tópico **Órdenes** (Orders API, NO "Pagos"), y cargar el signing secret en
  Configuración → Integraciones de Pago (campo `webhook_secret`, se guarda encriptado).
- Sin webhook el cobro **igual funciona** por polling (cada 3s mientras el cajero
  espera); el webhook agrega confirmación instantánea y robustez.

> Playbook completo: `.claude/docs/integraciones-pago-webhook-deploy.md`
> (incluye prueba con `ngrok http <puerto>` para túnel contra localhost en dev).

### Expiración de transacciones pendientes (scheduler)

El comando `integraciones-pago:expirar-pendientes` marca como `expirado` las
transacciones QR que vencieron sin pago. Corre vía el **scheduler de Laravel**
(`bootstrap/app.php`, `everyMinute` + `withoutOverlapping`), igual que
`precios:procesar-programados`. **No requiere cron nuevo**: ambos los dispara la
única entrada de cron del servidor:

```cron
* * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1
```

Si `precios:procesar-programados` ya corre en el servidor, la expiración corre sola
tras el deploy. Verificar que el scheduler esté activo:

```bash
php artisan schedule:list   # debe listar ambos comandos
```

> Playbook completo: `.claude/docs/integraciones-pago-expiracion-deploy.md`

---

## Verificación

Para verificar que la configuración está correcta en cualquier servidor:

```bash
# OPcache activo
php -r "echo extension_loaded('Zend OPcache') ? 'OK' : 'FALTA OPCACHE';"

# Config de FPM
php-fpm -tt 2>&1 | grep -E "pm\.|process_idle_timeout"

# Caches de Laravel activos
php artisan optimize:status

# MySQL wait_timeout (debe ser alto, default 28800)
mysql -e "SHOW VARIABLES LIKE 'wait_timeout';"
```

---

## XAMPP (Windows, solo desarrollo local)

En XAMPP, OPcache viene deshabilitado por defecto (`zend_extension=opcache` comenteado).
Sin OPcache, PHP recompila TODO el proyecto en cada request → ~1 s extra por página.
Activar en `C:\xampp\php\php.ini`:

```ini
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1          ; ⚠️ IMPRESCINDIBLE: `php artisan serve` usa PHP CLI.
                              ;    Sin enable_cli, serve NO toma OPcache (medido 2026-06-30).
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1 ; detectar cambios de código sin reiniciar
opcache.revalidate_freq=0     ; revalidar en cada request → tus ediciones se ven al toque
opcache.interned_strings_buffer=8
```

**Reiniciar el SAPI tras cambiar `php.ini`:**
- Si servís con `php artisan serve`: **cortar (Ctrl+C) y volver a levantarlo** — el
  proceso CLI viejo no toma el ini nuevo. (Reiniciar Apache NO alcanza para serve.)
- Si servís con Apache (mod_php): reiniciar Apache desde el panel de XAMPP.

Verificar: `php -r "echo extension_loaded('Zend OPcache')?'OK':'FALTA';"`

Medido en este proyecto (página `/`, mismo código): **sin OPcache ~1.0 s → con OPcache ~0.27 s** (~3.7×).
