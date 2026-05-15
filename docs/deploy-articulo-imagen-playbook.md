# Playbook — Deploy de imagen de artículo (PR2.E) a producción

> **Instrucciones para Claude Code corriendo en el server.**
> Versión ejecutable con la ruta real del server (`/var/www/html/bcn`) y comandos listos para copiar.
>
> **Objetivo:** habilitar el upload de foto del artículo en producción de forma segura, asegurando:
> - extensión `gd` de PHP presente (necesaria para Intervention Image),
> - migración tenant aplicada en todos los comercios,
> - symlink `public/storage` funcional,
> - permisos correctos en `storage/app/public/articulos/`.
>
> Si algo falla en algún paso, **PARÁ**, reportá al usuario qué pasó y no sigas hasta tener confirmación.

## Contexto previo

Lo mergeado en master que se está desplegando:

- **#94** — feat(articulos): imagen del artículo con upload seguro y render en panel táctil

Cambios introducidos:
- Nueva dependencia composer: `intervention/image: ^3.11`
- Nueva columna tenant: `articulos.imagen_path` (varchar nullable)
- Nuevo storage path: `storage/app/public/articulos/{comercio_id}/{uuid}.webp`

---

## Pre-flight (verificar antes de tocar nada)

```bash
cd /var/www/html/bcn
pwd                                    # debe imprimir /var/www/html/bcn

# PHP version + extensiones críticas
php --version                          # >= 8.2

# Extensión GD: imprescindible para Intervention Image.
# Si NO aparece, instalar antes de continuar (ver "Si falta GD" abajo).
php -m | grep -i "^gd$"                # debe imprimir "gd"

# finfo (debería venir built-in, lo usamos para detectar MIME real)
php -m | grep -i "^fileinfo$"          # debe imprimir "fileinfo"

# Composer + git en estado limpio
composer --version
git status                              # working tree clean en master
```

### Si falta GD

```bash
# Debian/Ubuntu
sudo apt-get update
sudo apt-get install -y php8.2-gd      # ajustar a la versión de PHP instalada
sudo systemctl reload apache2          # o php-fpm según el setup
```

Después de instalar, reverificar con `php -m | grep gd`.

---

## Deploy

### 1. Bajar el código

```bash
cd /var/www/html/bcn
git fetch origin
git checkout master
git pull --ff-only origin master

# Verificar que el último commit sea el del PR #94 o posterior
git log --oneline -3
```

### 2. Instalar dependencias

```bash
# --no-dev: no instala faker, pint, phpunit, etc en producción
# --optimize-autoloader: clase resolution rápida
composer install --no-dev --optimize-autoloader --no-interaction
```

Esto baja `intervention/image` (~200KB) y sus dependencias.

### 3. Migración tenant (agrega `imagen_path` a `articulos` de cada comercio)

```bash
php artisan migrate --force
```

La migración itera todos los comercios y agrega la columna con prefijo correcto (`{NNNNNN}_articulos`). Si un comercio ya tiene la columna (deploys previos parciales), el `try/catch` por comercio la saltea sin romper.

### 4. Verificar symlink de storage

```bash
# Idempotente: si ya existía, no rompe.
php artisan storage:link
ls -la public/storage                   # debe apuntar a /var/www/html/bcn/storage/app/public
```

### 5. Permisos del directorio de uploads

El web server (Apache/nginx con user `www-data`) tiene que poder escribir en `storage/app/public/articulos/`. El directorio no existe aún (se crea on-demand al primer upload), pero su parent sí.

```bash
# Asegurar que storage/app/public sea escribible por www-data
sudo chown -R www-data:www-data storage/app/public
sudo chmod -R u+rwX,g+rwX storage/app/public

# Crear el subdir de articulos con permisos correctos (no estrictamente
# necesario, Laravel lo crea solo, pero pre-crearlo evita un edge case)
sudo -u www-data mkdir -p storage/app/public/articulos
```

### 6. Cache de configuración (opcional pero recomendado)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ Si la app usa `env()` fuera de archivos `config/*.php`, `config:cache` rompe. Si no estás seguro, **saltá este paso** y dejá la app sin cache.

### 7. Restart web server (si usás php-fpm con OPcache)

```bash
# Si usás php-fpm
sudo systemctl reload php8.2-fpm

# Si usás Apache con mod_php
sudo systemctl reload apache2
```

---

## Verificación post-deploy

### 1. Smoke en logs

```bash
# Tail de logs por 30 segundos para ver si algo explota al primer hit
tail -f storage/logs/laravel.log &
TAIL_PID=$!
sleep 30
kill $TAIL_PID
```

### 2. Test de upload manual

1. Entrar a `https://bcn.bcnsoft.com.ar/articulos`
2. Editar cualquier artículo activo
3. Subir una imagen (JPG/PNG/WebP, hasta 5MB)
4. Guardar
5. Reabrir el artículo: la imagen debe seguir mostrándose
6. Ir a Pedidos Mostrador → Nuevo pedido → Panel táctil: ese artículo debe tener foto, los demás siguen mostrando el ícono de la categoría

### 3. Verificar archivos generados

```bash
# Debe existir el directorio con el comercio_id correspondiente
ls -la storage/app/public/articulos/

# Cada archivo subido debe ser un .webp pequeño (40-100KB típico)
ls -lah storage/app/public/articulos/*/
```

### 4. Test negativo (seguridad)

Intentar subir un archivo NO imagen (renombrar un .txt a .jpg) → debe mostrar mensaje de error \"Formato de imagen no permitido\" y NO debe quedar ningún archivo en `storage/app/public/articulos/`.

---

## Rollback

Si algo sale mal después del deploy y necesitás volver atrás:

```bash
# Volver al commit previo al PR #94
git log --oneline -5                    # identificar el SHA anterior a #94
git checkout <sha-anterior>
composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.2-fpm        # o apache2
```

La columna `imagen_path` queda en BD (no rompe nada porque queda nullable). Si querés removerla:

```bash
git checkout master                     # solo para tener la migración
php artisan migrate:rollback --step=1 --force
git checkout <sha-anterior>
```

Imágenes ya subidas en `storage/app/public/articulos/` no se borran automáticamente. Si querés limpiar:

```bash
sudo rm -rf storage/app/public/articulos/
```

---

## Configuración futura (opcional, no bloquea el deploy)

### CDN / Cloudflare cache

Si el catálogo crece y la latencia de imágenes molesta, considerar:
- Cache HTTP en `/storage/articulos/*` (Cache-Control: public, max-age=2592000)
- CDN externo (Cloudflare/Bunny) con origin `bcn.bcnsoft.com.ar/storage/`

Las imágenes son inmutables (UUID en el nombre), así que un TTL alto es seguro: cuando un artículo cambia su imagen, el nombre cambia y el CDN ve una URL distinta.

### Backup de imágenes

El directorio `storage/app/public/articulos/` no está en git (es runtime data). Asegurate de que tu backup del server incluya ese path.

---

## Referencias

- PR del feature: https://github.com/FacuGalan/bcn/pull/94
- Spec de pedidos mostrador: `.claude/specs/pedidos-mostrador.md`
- Manual de usuario: `docs/manual-usuario.md` (sección Artículos)
