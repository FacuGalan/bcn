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

Tras cada `git pull`, el hook `post-merge` ejecuta `php artisan optimize` automáticamente.
Si el hook no está configurado, ejecutar manualmente:

```bash
git config core.hooksPath .githooks
php artisan optimize
```

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

**Importante:** Con `validate_timestamps=0`, OPcache no detecta cambios en archivos. Después de cada deploy ejecutar:

```bash
sudo systemctl reload php*-fpm
```

O agregar al script de deploy:
```bash
php artisan optimize
sudo systemctl reload php*-fpm
```

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

El flujo de deploy en producción debería ser:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan optimize
php artisan view:cache
sudo systemctl reload php*-fpm
```

El hook `post-merge` ejecuta `php artisan optimize` automáticamente tras `git pull`. El reload de FPM debe hacerse manualmente (requiere sudo).

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

En XAMPP, OPcache viene deshabilitado por defecto. Activar en `C:\xampp\php\php.ini`:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.validate_timestamps=1
opcache.interned_strings_buffer=8
```

Reiniciar Apache desde el panel de XAMPP después de cambiar `php.ini`.
