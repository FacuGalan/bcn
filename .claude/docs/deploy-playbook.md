# Playbook de Deploy (producción)

Guía para subir `master` al servidor oficial sin romper performance ni servir
vistas viejas. Complementa `.claude/docs/server-config.md` (config de PHP-FPM /
OPcache / FPM).

---

## Flujo de deploy completo

El hook `post-merge` (`.githooks/post-merge`) solo corre `optimize:clear` (a
propósito: NO cachea config para no envenenar los tests). Los pasos de
migración, build y warm de caches **NO están automatizados** — hay que correrlos:

```bash
cd /var/www/html/bcn
git pull origin master
composer install --no-dev --optimize-autoloader

# 1) Migraciones (incluye tenant: iteran TODOS los comercios)
php artisan migrate --force

# 2) Build del front (public/build está gitignored → se compila en el server).
#    Los VITE_* (REVERB, etc.) se hornean acá: el .env debe estar correcto ANTES.
npm ci && npm run build

# 3) Warm de caches SEGURAS (NO config:cache — ver más abajo)
php artisan view:cache
php artisan route:cache
php artisan event:cache

# 4) Recargar FPM si OPcache tiene validate_timestamps=0 (ver server-config.md).
#    Si validate_timestamps=On (revalidate_freq bajo), no hace falta.
sudo systemctl reload php*-fpm
```

### Sobre `php artisan optimize` / `config:cache`

- **NO correr `php artisan optimize` a ciegas**: incluye `config:cache`, que el
  proyecto evita porque serializa el `.env` real y puede envenenar la suite de
  tests (incidente 2026-05-04). En un server **solo-prod** `config:cache` es
  técnicamente inocuo, pero **no aporta a la velocidad** (medido: `LoadConfiguration`
  ~8 ms). Usá las tres caches seguras (`view`, `route`, `event`).
- Reflejo típico "está lento → corré optimize": **a veces es la respuesta y a
  veces no.** Solo aplica si el cuello eran rutas/vistas sin cachear. Antes de
  asumirlo, **medí** (ver Diagnóstico).

---

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

**Regla:** un componente Volt (single-file, arranca con `<?php`) va en
`resources/views/volt/`, **nunca** mezclado con las vistas Livewire clásicas de
`resources/views/livewire/`. Detector rápido de Volt sueltos:

```bash
# debe devolver 0
find resources/views/livewire -name "*.blade.php" \
  -exec sh -c 'head -c5 "$1" | grep -q "<?php" && echo "$1"' _ {} \;
```

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
