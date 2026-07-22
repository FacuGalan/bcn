# Playbook de Deploy (producción)

Guía para subir `master` al servidor oficial sin romper performance ni servir
vistas viejas. Complementa `.claude/docs/server-config.md` (config de PHP-FPM /
OPcache / FPM).

---

## Flujo de deploy completo

El hook `post-merge` (`.githooks/post-merge`) solo corre `optimize:clear` (a
propósito: NO cachea config para no envenenar los tests). El flujo completo
está automatizado en **`./deploy.sh`** (raíz del repo) — preferirlo a correr
los pasos a mano. Paso a paso equivalente:

```bash
cd /var/www/html/bcn
git pull origin master

# 0) Composer SIN scripts + discover manual (ver Gotcha 4: el hook
#    post-autoload-dump bootea Laravel ANTES de terminar de instalar y revienta
#    si un paquete nuevo está referenciado en config/ — caso Sanctum #161)
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-scripts
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
COMPOSER_ALLOW_SUPERUSER=1 php artisan package:discover --ansi

# 1) Migraciones (incluye tenant: iteran TODOS los comercios)
php artisan migrate --force

# 1b) Invalidar caché de permisos/menú (ver Gotcha 5: sin esto los ítems de
#     menú nuevos no aparecen hasta 5 min después, ni siquiera como admin)
php artisan permission:cache-reset || true
php artisan cache:clear

# 2) Build del front (public/build está gitignored → se compila en el server).
#    Los VITE_* (REVERB, etc.) se hornean acá: el .env debe estar correcto ANTES.
npm ci && npm run build

# 3) Warm de caches SEGURAS (NO config:cache). Un solo comando que bundlea
#    view + route + event + icons (imposible olvidarse icons:cache → ver Gotcha 3).
php artisan deploy:warm

# 4) Recargar FPM. En PRODUCCIÓN es OBLIGATORIO y NO opcional:
#    el server tiene opcache.validate_timestamps=0, así que OPcache NO relee
#    los .php cambiados hasta este reload. Sin esto, un fix de CÓDIGO (p.ej. el
#    VoltServiceProvider) deployás pero NO surte efecto: OPcache sigue corriendo
#    la versión vieja compilada. → "deployé y sigue igual de lento" = falta este paso.
sudo systemctl reload php*-fpm
```

> ### ⚠️ Si deployaste un fix de performance y "no mejoró nada"
> Casi siempre es el **paso 4 omitido**. Con `validate_timestamps=0` (producción),
> OPcache cachea el bytecode compilado y lo sirve hasta que reciba un `reload`/`restart`
> de FPM. Mientras tanto el código nuevo está en disco pero **no se ejecuta**.
> Verificá que el reload corrió: `sudo systemctl status php*-fpm` (uptime reciente) o
> `php -r 'print_r(opcache_get_status()["opcache_statistics"]["start_time"]);'` desde el
> SAPI web (no CLI). Esto explica el viejo "lo arregló el optimize": el lever real no era
> `config:cache` (mide ~8 ms), era que el ciclo de deploy refrescaba los workers de FPM.

### Sobre `php artisan optimize` / `config:cache`

- **NO correr `php artisan optimize` a ciegas**: incluye `config:cache`, que el
  proyecto evita porque serializa el `.env` real y puede envenenar la suite de
  tests (incidente 2026-05-04). En un server **solo-prod** `config:cache` es
  técnicamente inocuo, pero **no aporta a la velocidad** (medido: `LoadConfiguration`
  ~8 ms). Usá las tres caches seguras (`view`, `route`, `event`).
- Reflejo típico "está lento → corré optimize": **a veces es la respuesta y a
  veces no.** Solo aplica si el cuello eran rutas/vistas sin cachear. Antes de
  asumirlo, **medí** (ver Diagnóstico).
- **Hook de `composer`** (`composer.json` → `post-install-cmd`/`post-update-cmd`):
  corría `php artisan optimize` (= `config:cache`) en cada `composer install/update`
  sin `CI=1` → todo deploy horneaba config en silencio (lo opuesto a la regla). Se
  cambió a `optimize:clear` (alineado con el hook `post-merge`). Verificación tras
  deploy: `php artisan config:clear` debería ser no-op; si `bootstrap/cache/config.php`
  existe, algo volvió a cachear config.

### Detalle del servicio FPM (server oficial)

El SAPI web corre **`php8.2-fpm`** aunque el **CLI es PHP 8.3**. El `reload php*-fpm`
del flujo igual lo agarra (wildcard), pero si reloadeás el servicio explícito es
`sudo systemctl reload php8.2-fpm`. No confundir la versión del CLI (`php -v`) con la
que sirve las requests.

---

## Checklist pre-deploy (correr en local/CI antes de subir)

```bash
# 1) No quedaron componentes Volt sueltos en livewire/ (deben estar en views/volt/).
#    Un Volt huérfano = 500 'Unable to find component' en la página que lo use.
#    Bug real 2026-06-30: welcome.navigation quedó en livewire/ tras mover los otros 9.
find resources/views/livewire -name "*.blade.php" \
  -exec sh -c 'head -c5 "$1" | grep -q "<?php" && echo "VOLT SUELTO: $1"' _ {} \;
#    ⚠️ Ojo: welcome/navigation NO arranca con <?php (es view-only) pero IGUAL es Volt.
#    Detector complementario: toda ref <livewire:X> cuyo componente sólo exista bajo
#    views/ (no en app/Livewire/) tiene que vivir en views/volt/.

# 2) Smoke de las rutas públicas que no requieren login (se rompen sin que nadie las note):
#    abrir '/' (welcome) y '/login' — ambas deben dar 200, no 500.
for p in / app/login; do
  curl -s -o /dev/null -w "$p -> %{http_code}\n" http://127.0.0.1:8000/$p
done
```

## Verificación post-deploy (en el server, después del paso 4)

```bash
DOMINIO=<tu-dominio>
# '/' y '/login' deben dar 200 (no 500 por Volt huérfano)
for p in "" app/login; do
  curl -s -o /dev/null -w "/$p -> %{http_code} %{time_total}s\n" https://$DOMINIO/$p
done
# Tiempo de una página real ya con OPcache caliente (2ª+ corrida): debe bajar
# muy por debajo del ~750 ms del bug de Volt. Si sigue alto → revisar paso 4 (reload FPM).
for i in 1 2 3 4; do curl -s -o /dev/null -w "%{time_total}s\n" -L https://$DOMINIO/app/login; done
```

## Gotcha 1 — Volt monta solo `resources/views/volt/` (NUNCA todo `livewire/`)

**Qué pasó:** `VoltServiceProvider` montaba `resources/views/livewire` (árbol
completo, ~4 MB / 116 vistas Livewire **clásicas**). Volt escanea EAGER cada
directorio montado **en cada request**, así que el boot del provider llegó a
**~750 ms por request** cuando entraron blades grandes (fiscal, conciliaciones,
`pedidos-mostrador.blade.php` de 119 KB). El proyecto usa Livewire clásico; Volt
solo se usa para 9 componentes de Breeze.

**Fix (ya aplicado):** los 9 componentes Volt viven en `resources/views/volt/`
(`layout/`, `profile/`, `pages/auth/`) y `Volt::mount()` monta **solo** ese árbol.
Los nombres (`layout.navigation`, `profile.*`, `pages.auth.*`) se preservan por
los subpaths.

**Regla:** un componente Volt va en `resources/views/volt/`, **nunca** mezclado con
las vistas Livewire clásicas de `resources/views/livewire/`. Detector rápido:

```bash
# debe devolver 0
find resources/views/livewire -name "*.blade.php" \
  -exec sh -c 'head -c5 "$1" | grep -q "<?php" && echo "$1"' _ {} \;
```

> **Caveat (regresión 2026-06-30):** el detector de arriba sólo encuentra Volts que
> arrancan con `<?php`. Un Volt **view-only** (puro markup, sin bloque `<?php`) como
> `welcome/navigation.blade.php` **NO lo atrapa** y aun así se rompe al moverse el mount.
> Por eso el paso 2 del checklist pre-deploy (abrir `/` y `/login`) es obligatorio:
> un Volt huérfano da 500 sólo al renderizar la página que lo referencia.

**`optimize` NO arregla esto** (no cachea el escaneo de directorios de Volt).
Lo confirmamos midiendo: `config:cache` + `view:cache` no movieron el tiempo.

---

## Gotcha 2 — La vista "se ve mal" después del deploy (caché del cliente)

**Síntoma:** tras el deploy, una vista (típico: listado de mostrador) renderiza
mal, "siempre pasa en el deploy", "es algo cacheado", y en otro equipo / server
de prueba se ve bien. **No es un bug de código ni de datos** (mismo código
funciona en otro lado).

**Causa:** el service worker (`public/sw.js`) y/o la pestaña/PWA ya abierta
servían el JS/HTML viejo contra el server nuevo. Antes `CACHE_NAME` era fijo
(`bcn-pymes-v5`) y no se purgaba en cada deploy.

**Fix (ya aplicado):** el SW se registra como `/sw.js?v=<hash del manifest de
Vite>` y deriva `CACHE_NAME` de ese `?v=`. Cada `npm run build` cambia el hash →
el navegador instala un SW nuevo → `activate` purga TODOS los caches viejos →
`skipWaiting()` + `clients.claim()` toman control al instante. **Requiere que el
deploy corra `npm run build`** (paso 2 del flujo) para que el hash cambie.

**Verificación / si un usuario sigue viéndolo viejo:**
- Abrir la prod en **incógnito** (sin SW): si ahí se ve bien, era caché de cliente.
- En el equipo afectado: hard-reload (Ctrl+Shift+R) o "borrar datos del sitio".
  Con el fix, se auto-sana al primer load tras el deploy.

---

## Gotcha 3 — blade-icons escanea ~1200 SVGs por request si no hay `icons:cache`

**Qué pasó:** el paquete `blade-ui-kit/blade-heroicons` registra el set de Heroicons
(~1200 SVGs). Sin el manifest cacheado, blade-icons **escanea el filesystem en CADA
request** durante el boot de providers → **~600 ms por request**, en TODO el sistema
(páginas y acciones Livewire por igual). Mismo patrón que el Volt mount, en otro paquete.

**Cómo se detectó (2026-06-30):** probe que parte el request en `boot` vs `handle`.
Con `icons:cache`: `boot_ms` ~50 ms. Sin él: `boot_ms` ~650 ms. DB era ~40 ms en
ambos (NO era la base). Aislado limpiando solo el manifest de icons.

**Fix:** `php artisan icons:cache` en el warm de cada deploy (paso 3 del flujo). Genera
`storage/framework/cache/...`/manifest que blade-icons lee en vez de escanear. Es una
**caché segura** (no toca config). Alivio inmediato en prod sin re-deploy completo:

```bash
php artisan icons:cache
sudo systemctl reload php*-fpm   # para que OPcache tome el cambio (validate_timestamps=0)
```

**Importante:** `optimize:clear` (hook post-merge) NO borra el manifest de icons, pero
un deploy en server nuevo —o tras `icons:clear`— sí lo necesita. Por eso va explícito
en el flujo. Detector: si `boot` domina el request y DB es bajo, sospechar de un
provider que escanea FS (icons, Volt) — medir, no asumir.

## Gotcha 4 — `composer install --no-dev` aborta con "Class not found" al agregar un paquete referenciado en `config/`

**Qué pasó (deploy #161, 2026-07-16):** el PR agregó Laravel Sanctum y
`config/sanctum.php` referencia la clase `Laravel\Sanctum\Sanctum` al cargar.
El `composer install --no-dev` normal abortó con `Class "Laravel\Sanctum\Sanctum"
not found`: composer procesa las **remociones de paquetes dev primero** y su hook
(`pre-package-uninstall` / `post-autoload-dump` → `package:discover`) **bootea
Laravel y carga toda la config** antes de que Sanctum termine de instalarse
(huevo-gallina). Puede pasar con CUALQUIER paquete nuevo que quede referenciado
en un archivo de `config/`.

**Fix (ya en `deploy.sh`, idempotente para cualquier deploy):**

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-scripts
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
COMPOSER_ALLOW_SUPERUSER=1 php artisan package:discover --ansi
```

`--no-scripts` evita el booteo prematuro; el `rm` + `package:discover` posterior
reproducen a mano lo que los scripts hacían, ya con el vendor completo.

---

## Gotcha 5 — El menú nuevo no aparece hasta 5 min después de migrar (caché de permisos)

**Qué pasó (deploy #161):** tras migrar los ítems de menú nuevos
(Compras/Delivery), **no aparecían ni siquiera como admin**. No era un error de
la migración: los permisos se cachean **por usuario** en el cache store de la
app (`user_permissions_{userId}_{comercioId}`, TTL 5 min) + la caché de Spatie.
Ni `deploy:warm` ni `optimize:clear` tocan el cache STORE de la app (eso es
`cache:clear`). Se auto-sana a los 5 minutos, pero confunde ("la migración no
contempló el ítem").

**Fix (ya en `deploy.sh`, paso post-migrate):**

```bash
php artisan permission:cache-reset || true
php artisan cache:clear
```

**Detalle de diseño (no es bug):** las migraciones asignan los permisos nuevos
solo a **Administrador / Super Administrador**; el resto de los roles se asignan
a mano en *Roles y Permisos*. Si un rol no-admin no ve el ítem nuevo, es
**esperado** — no lo arregla ningún `cache:clear`.

---

## Diagnóstico (cuándo "va lento")

Medir antes de tocar. Perfilar el boot de providers (sobre php-fpm, no CLI —
artisan no usa OPcache):

```php
// public/__profile_probe.php  (BORRAR después de usar)
<?php
$t0 = microtime(true);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Http\Kernel::class);
$reg = (function () { return $this->serviceProviders ?? []; });
// ... medir boot() por provider e imprimir el top (ver historial del incidente)
```

Atajos de medición:
```bash
# tiempo real de una ruta PHP (con OPcache, vía php-fpm/web)
for i in 1 2 3 4; do curl -s -o /dev/null -w "%{time_total}s\n" -L https://<DOMINIO>/app/login; done

# comparar contra un estático (solo Apache, sin PHP) para aislar PHP vs red/SSL
curl -s -o /dev/null -w "%{time_total}s\n" https://<DOMINIO>/build/assets/<algún>.css
```

Si el grueso del tiempo está en `BootProviders` y un solo provider domina →
ese provider hace algo caro en cada request (escaneo de FS, query, HTTP externo).

> **IMPORTANTE:** cualquier probe `.php` o endpoint temporal que dejes en
> `public/` para diagnosticar, **borralo al terminar**.

---

## Nota de tanda: "checkout operación tienda" (RF-T17..T23, mergeada 2026-07-22)

Deploy CROSS-REPO con la tienda: **este core va PRIMERO**, la tienda después
(consume campos nuevos del contrato: `cupon` enriquecido, `items[].observaciones`,
`pagos[]`). Secuencia estándar de arriba, sin pasos extra. El `migrate --force`
incluye 4 migraciones de la tanda: `disponible_en_tienda` (formas_pago_sucursales,
tenant), `fecha_nacimiento` (clientes tenant + consumidores **CONFIG**) y
`observaciones` (pedidos_delivery_detalle, tenant — aclaración por ítem que se ve
en el panel y en la comanda). El checklist completo del deploy conjunto (env de
Maps y APP_LOCALE de la tienda, validación end-to-end) vive en
`bcn-tienda/.claude/docs/deploy-playbook.md`, sección "Deploy tanda checkout
operación".
