# PWA Scope + Soporte Multi-PWA — Especificación

## Estado: IMPLEMENTADO — pendiente verificación visual del usuario (instalar ambas PWAs) + commit/PR

> Refactor para que la PWA principal tenga scope acotado (`/app`, incluyendo el
> login) y deje de capturar páginas públicas y pantallas auxiliares. Habilita
> instalar la PWA principal y la(s) pantalla(s) auxiliar(es) — pantalla cliente,
> futuro monitor llamador — como apps separadas, coexistiendo. Diseñado el
> 2026-06-03 tras `/sdd-explore`. Rama: `refactor/pwa-scope-multiapp`.

---

## Contexto y Motivación

Hoy la PWA principal declara `scope: "/"` en `public/manifest.json`. Eso causa:

1. **No se pueden instalar PWAs auxiliares por separado.** La pantalla cliente
   (`/pantalla-cliente`) tiene su propio `manifest-pantalla-cliente.json` con
   `scope: "/pantalla-cliente"`, pero como queda **dentro** del scope `/` de la
   app principal instalada, el navegador la considera "parte de" la app principal
   y **no ofrece instalarla aparte** (confirmado en PR #123: el `beforeinstallprompt`
   no dispara, ni en pestaña ni dentro de la app instalada).
2. **La PWA principal captura páginas públicas** (landing `/`, y cualquier ruta
   del origen), cuando debería abarcar solo la aplicación autenticada + su login.

Además, a futuro habrá **más pantallas auxiliares** (ej. un "monitor llamador"
de pedidos de mostrador) que deben poder instalarse como apps independientes.

La solución es acotar el scope de la PWA principal a `/app` (moviendo allí las
rutas autenticadas **y el login**), dejando las páginas públicas y las pantallas
auxiliares **fuera** de ese scope, cada una con su propio manifest/scope.

---

## Principios de Diseño

1. **Scopes que no se solapan.** Cada PWA instalable vive en un prefijo de path
   propio y disjunto: app principal en `/app`, pantallas auxiliares en su path
   raíz (`/pantalla-cliente`, futuro `/monitor-llamador`). Ninguno es prefijo de
   otro → el navegador las trata como apps distintas e instalables a la vez.
2. **Login dentro del scope de la app (Opción B, requisito del usuario).** El
   flujo de autenticación guest (login, recuperar/reset de contraseña) se mueve
   bajo `/app`, para que al abrir la PWA (`start_url: /app`) el redirect a
   `route('login')` caiga **dentro** del scope y se vea como parte de la app
   ("abrir app → login; si ya logueado → app"). Si el login quedara fuera de
   `/app`, ese redirect saldría del scope y abriría una pestaña del navegador.
3. **Nombres de ruta estables.** Solo cambian las **URLs** (`/dashboard` →
   `/app/dashboard`), NUNCA los **nombres** de ruta (`dashboard`, `login`,
   `ventas.index`, …). Todo lo que usa `route('...')` (menú dinámico, redirects,
   vistas) sigue funcionando sin tocarse. Solo se corrigen las pocas URLs
   hardcodeadas.
4. **No romper deep-links existentes.** Redirects de cortesía 301/302 desde las
   URLs viejas (`/dashboard`, `/login`, `/ventas`, …) a las nuevas `/app/*`.
5. **Multi-tenant intacto.** `ConfigureTenantMiddleware` + `EnsureSucursalSelected`
   siguen corriendo en el grupo `web` (todos los requests). No se toca la lógica
   de tenant; solo se reubican rutas.
6. **Extensibilidad mecánica.** Una convención clara para registrar futuras
   pantallas auxiliares (grupo de rutas "pantallas", manifest por pantalla,
   naming de scope) para que sumar el monitor llamador sea repetir el patrón.

---

## Requisitos Funcionales

### RF-01: Mover la aplicación autenticada bajo `/app`
- Todas las rutas del grupo `['auth','verified','tenant']` pasan a `/app/*`
  (dashboard, ventas, stock, cajas, tesoreria, bancos, articulos, clientes,
  configuracion, pedidos, compras, etc.).
- `/comercio/selector` (middleware `auth`) pasa a `/app/comercio/selector`.
- Los nombres de ruta se conservan idénticos.

### RF-02: Mover el login (y flujo auth) bajo `/app`
- `routes/auth.php`: las rutas guest (`login`, `password.request`,
  `password.reset`) y las auth-sin-tenant (`verification.notice`,
  `verification.verify`, `password.confirm`, `logout`) pasan a `/app/*`.
- Nombres conservados (`login`, `logout`, etc.).
- `logout` redirige a `route('login')` (= `/app/login`), NO a `/`.

### RF-03: Scope de la PWA principal acotado a `/app`
- `public/manifest.json`: `scope: "/app"`, `start_url: "/app"`, `id: "/app"`.
- `/app` (sin sub-path) resuelve a una redirección server-side: a
  `route('dashboard')` si hay sesión+tenant, o a `route('login')` si no.
- `public/sw.js`: `PRECACHE_ASSETS` apunta a `/app/*` y `offline.html`. El SW
  sigue registrándose en scope `/` (un solo SW sirve a todas las PWAs del origen).

### RF-04: Páginas públicas fuera del scope
- Landing `/` (welcome) queda fuera de `/app` (público, sin PWA principal).
- Redirects de cortesía desde URLs viejas a `/app/*`.

### RF-05: Pantalla cliente como PWA auxiliar independiente
- `/pantalla-cliente` permanece **fuera** de `/app` (ya tiene su manifest/scope
  propio). Se la saca del grupo `['auth','verified','tenant']`.
- Conserva contexto de tenant vía `ConfigureTenantMiddleware` (global). Se decide
  su middleware (ver RF-07 / Notas).
- Resultado: instalable como app aparte aunque la principal esté instalada.

### RF-06: Convención para pantallas auxiliares futuras (dos modos)
Las pantallas auxiliares se dividen en **dos clases** según dónde corren:

- **Clase A — "co-ubicada" (mismo CPU/navegador que el POS).** Ej.: **pantalla
  cliente** (la actual). Acceso con `auth` (comparte sesión del perfil). Comunicación
  por **BroadcastChannel** (same-origin/same-profile). Es lo que se implementa/ajusta
  en este PR.
- **Clase B — "dispositivo dedicado remoto" (otra máquina, sin sesión).** Ej.
  futuros **monitor llamador** de pedidos y **consultor de precios**. Acceso
  **público** (sin login) con **resolución de tenant por token** en la URL (ej.
  `/llamador/{token}`), y comunicación en tiempo real por **Reverb/WebSockets**
  (NO BroadcastChannel, porque están en otro dispositivo). **Fuera de alcance de
  este PR**, pero la convención de rutas debe dejar lugar para ambas clases.

Convención común: cada pantalla vive en su **path raíz propio** fuera de `/app`,
con `manifest-{pantalla}.json` (`scope`/`id`/`start_url` = `/{pantalla}`,
`display: fullscreen`, íconos propios) y su `<link rel="manifest">`. Checklist para
sumar una: ruta + vista + JS + manifest + íconos + (Clase A: auth+BroadcastChannel |
Clase B: público+token+Reverb).

### RF-07: Acceso y resolución de tenant de la pantalla cliente (Clase A)
- `/pantalla-cliente` necesita el comercio activo (ej. `EmpresaConfig`).
  `ConfigureTenantMiddleware` (global, grupo `web`) ya lo resuelve **desde la
  sesión**. Como la pantalla cliente convive en el mismo navegador/perfil que el
  POS (requisito de BroadcastChannel), la sesión está disponible.
- **Decisión (confirmada con el usuario)**: la pantalla cliente actual mantiene
  middleware `auth` (sin `tenant`/`verified`). Un dispositivo sin sesión redirige
  a `/app/login` — aceptable porque esta pantalla corre en el CPU del POS.
- Las pantallas **Clase B** (futuras, dispositivos remotos) NO usarán `auth`:
  resolverán el comercio por token público. Eso se diseña/implementa en su PR.

---

## Modelo de Datos

**Sin cambios.** Este refactor no toca base de datos ni modelos. (No hay migración.)

---

## Pantallas UI

**Sin componentes nuevos.** Cambian URLs, no vistas Livewire. Ajustes puntuales:

- `resources/views/welcome.blade.php` y
  `resources/views/livewire/welcome/navigation.blade.php`: reemplazar
  `url('/dashboard')` hardcodeado por `route('dashboard')` (o `route('login')`
  según corresponda al CTA).
- Verificar que `layouts.app` / `layouts.guest` y el menú dinámico (`MenuItem`
  por `route_name`) no tengan URLs hardcodeadas (usan nombres → OK).

---

## Servicios

**Sin services nuevos.** Opcional (mejora de extensibilidad, no bloqueante):

- `App\Support\PantallaAuxiliar` (helper liviano) o un controlador de redirección
  `/app` → login|dashboard. Mínimo viable: un closure en `web.php`.

---

## Migraciones Necesarias

**Ninguna.**

---

## Estructura de rutas resultante

```
PÚBLICO (fuera de cualquier scope de PWA principal)
  /                          welcome (landing)
  /pantalla-cliente          PWA auxiliar Clase A — co-ubicada (scope propio) [auth]
  /llamador/{token}          (FUTURO) PWA auxiliar Clase B — remota, pública+token+Reverb
  /consultor-precios/{token} (FUTURO) PWA auxiliar Clase B — remota, pública+token+Reverb

PWA PRINCIPAL (scope /app)
  /app                       redirect → dashboard (con sesión) | login (sin sesión)
  /app/login                 guest
  /app/forgot-password       guest
  /app/reset-password/{t}    guest
  /app/verify-email          auth
  /app/confirm-password      auth
  /app/logout                auth (POST) → redirect route('login')
  /app/comercio/selector     auth
  /app/dashboard             auth+verified+tenant
  /app/ventas, /app/stock, /app/cajas, /app/configuracion, ...  (todo el grupo)

REDIRECTS DE CORTESÍA (302)
  /login → /app/login | /dashboard → /app/dashboard | /ventas → /app/ventas | ...
```

---

## Matriz de Impacto

| Archivo | Cambio | Riesgo |
|---------|--------|--------|
| `routes/web.php` | Envolver grupo protegido y `/comercio/selector` en `Route::prefix('app')`. Sacar `/pantalla-cliente` del grupo tenant → bloque "pantallas auxiliares". Agregar `/app` (redirect) y redirects de cortesía. | Alto |
| `routes/auth.php` | Mover guest+auth bajo `/app` (envolver en `prefix('app')`). `logout` → `route('login')`. | Medio |
| `public/manifest.json` | `scope`/`start_url`/`id` → `/app`. | Bajo |
| `public/sw.js` | `PRECACHE_ASSETS` → `/app/*`. | Bajo |
| `resources/views/welcome.blade.php` | `url('/dashboard')` → `route('dashboard')`/`route('login')`. | Bajo |
| `resources/views/livewire/welcome/navigation.blade.php` | idem hardcoded. | Bajo |
| `app/Http/Middleware/TenantMiddleware.php` | Usa `route('login')`/`route('comercio.selector')` (por nombre) → **no cambia**, solo verificar. | Bajo |
| `resources/views/livewire/pages/auth/login.blade.php` | Usa `route('comercio.selector')`/`redirectIntended(route('dashboard'))` (por nombre) → verificar. | Bajo |
| `app/Livewire/ComercioSelector.php` | `redirect(route('dashboard'))` (por nombre) → verificar. | Bajo |
| `app/Http/Controllers/Auth/VerifyEmailController.php` | `route('dashboard')` (por nombre) → verificar. | Bajo |
| `bootstrap/app.php` | Verificar `EnsureSucursalSelected`/`ConfigureTenantMiddleware` no rompan `/`, `/app/login`, `/pantalla-cliente`. Posible allowlist de rutas públicas. | Medio |
| `tests/Feature/Auth/AuthenticationTest.php` | URLs literales `/login`, `/dashboard` → `/app/...`. | Medio |
| Otros tests con URL literal | Buscar `$this->get('/...')` y `assertRedirect('/...')`. | Medio |

---

## Riesgos

1. **Redirect loops.** Si `/app` redirige a `route('login')` y el login (guest)
   redirige a `route('dashboard')` mal configurado. Mitigar con la lógica clásica
   guest/auth y probar ambos estados (con y sin sesión).
2. **`EnsureSucursalSelected` en rutas públicas.** Corre en `web`; si intenta
   forzar sucursal en `/`, `/app/login` o `/pantalla-cliente` puede romper o
   loopear. Revisar su allowlist/condiciones.
3. **Deep-links / bookmarks.** Usuarios con `/dashboard` guardado. Mitigar con
   redirects de cortesía.
4. **Menú dinámico (`MenuItem.route_name`).** Usa nombres → debería estar OK;
   verificar que ningún `menu_items.url` guarde paths absolutos.
5. **Sesiones activas durante el deploy.** Cookies de sesión siguen válidas; el
   usuario logueado al navegar a `/app/...` sigue logueado (mismo dominio).
6. **SW cacheando rutas viejas.** Bump de `CACHE_NAME` (`bcn-pymes-v5`) para
   invalidar el precache anterior con `/dashboard`.
7. **Pantalla cliente sin sesión.** Un dispositivo dedicado sin login redirige a
   `/app/login`. Documentado como no soportado en esta fase (RF-07).

---

## Criterios de Aceptación

- [ ] La PWA principal declara `scope: "/app"` y NO captura `/` ni `/pantalla-cliente`.
- [ ] Al abrir la PWA principal sin sesión, se ve el **login dentro de la app**
      (no abre pestaña del navegador). Con sesión, entra directo al dashboard.
- [ ] La pantalla cliente se puede **instalar como app separada** aunque la
      principal esté instalada (scopes disjuntos).
- [ ] Todos los módulos siguen navegables bajo `/app/*` (dashboard, ventas, stock,
      cajas, tesorería, bancos, artículos, clientes, configuración, pedidos, compras).
- [ ] El menú lateral/navegación funciona (links por `route()`).
- [ ] Login, logout, recuperar/reset de contraseña y verificación funcionan bajo `/app`.
- [ ] URLs viejas (`/dashboard`, `/login`, …) redirigen a `/app/*` (cortesía).
- [ ] `/pantalla-cliente` sigue funcionando (config por BroadcastChannel + QR de cobro).
- [ ] Convención documentada para agregar el futuro monitor llamador.
- [ ] `php artisan test` verde (auth + smokes). Pint OK. `npm run build` OK.

---

## Plan de Implementación

### Fase 1: Rutas de la app bajo `/app` [COMPLETO]
1. En `routes/web.php`, envolver el grupo `['auth','verified','tenant']` y la ruta
   `/comercio/selector` dentro de `Route::prefix('app')->group(...)`.
2. Mover la definición de `/pantalla-cliente` FUERA de ese grupo, a un bloque
   nuevo "Pantallas auxiliares" (público respecto de `/app`), con middleware
   `auth` (sin `tenant`/`verified`) — RF-07.
3. Agregar ruta `GET /app` → redirect a `route('dashboard')` o `route('login')`
   según `Auth::check()`.
4. Verificar que los nombres de ruta no cambiaron (`php artisan route:list`).

### Fase 2: Auth bajo `/app` [COMPLETO]
1. En `routes/auth.php`, envolver los grupos guest y auth en `Route::prefix('app')`.
2. `logout` → `redirect(route('login'))`.
3. Verificar `route('login')` = `/app/login` y `route('logout')` = `/app/logout`.

### Fase 3: Middleware y redirects de cortesía [COMPLETO]
1. Revisar `EnsureSucursalSelected` y `ConfigureTenantMiddleware`: que no rompan
   `/`, `/app/login`, `/pantalla-cliente` (allowlist si hace falta).
2. Agregar redirects 302 de URLs viejas → `/app/*` (al menos las de primer nivel
   más usadas: dashboard, login, ventas, cajas, configuracion, etc.).

### Fase 4: Manifest + Service Worker [COMPLETO]
1. `public/manifest.json`: `scope`/`start_url`/`id` → `/app`.
2. `public/sw.js`: `PRECACHE_ASSETS` → `/app`, `/app/dashboard`, `/offline.html`;
   bump `CACHE_NAME` a `bcn-pymes-v5`.
3. Verificar que el `<link rel="manifest">` de `layouts.app`/`guest` apunte al
   manifest principal y el de `/pantalla-cliente` al suyo.

### Fase 5: Vistas hardcodeadas [COMPLETO]
1. `welcome.blade.php` y `welcome/navigation.blade.php`: `url('/dashboard')` →
   `route('dashboard')`/`route('login')`.
2. Grep de `url('/`, `href="/`, `'/dashboard'`, `'/ventas'`, etc. en vistas/JS.

### Fase 6: Tests [COMPLETO]
1. `AuthenticationTest`: `/login` → `/app/login`, `/dashboard` → `/app/dashboard`.
2. Grep de `->get('/`, `assertRedirect('/`, `assertLocation('/` en `tests/`.
3. Test nuevo: `GET /app` redirige a login (sin sesión) y a dashboard (con sesión).
4. Test: URL vieja `/dashboard` redirige a `/app/dashboard` (cortesía).

### Fase 7: Convención multi-PWA + verificación [COMPLETO]
1. Documentar en el spec/ai-knowledge-base el checklist para nuevas pantallas
   auxiliares (futuro monitor llamador).
2. Verificación manual: instalar PWA principal (scope `/app`) y pantalla cliente
   a la vez; abrir la principal sin/con sesión; navegar todos los módulos.
3. `php artisan test`, `pint --test`, `npm run build`.

---

## Convención para nuevas pantallas auxiliares (checklist)

Para sumar una pantalla auxiliar (ej. futuro **monitor llamador** o **consultor
de precios**), repetir este patrón — vive SIEMPRE fuera de `/app`:

1. **Ruta** en `routes/web.php`, fuera del grupo `prefix('app')`:
   - **Clase A** (co-ubicada, mismo navegador que el POS): dentro del grupo
     `Route::middleware(['auth'])` de "Pantallas auxiliares". Comparte sesión;
     tenant vía `ConfigureTenantMiddleware`. Comunica por **BroadcastChannel**.
   - **Clase B** (dispositivo remoto sin sesión): ruta **pública** con token,
     p. ej. `Route::get('llamador/{token}', ...)`. Resolver el comercio por el
     token (no por sesión). Comunica por **Reverb** (canal privado/público por
     comercio). *(Requiere diseñar el token de comercio — fuera de este PR.)*
2. **Vista** Blade liviana (sin shell de la app), con su `<link rel="manifest">`
   propio y registro del SW.
3. **JS** dedicado (entry de Vite) con la lógica de la pantalla.
4. **Manifest** `public/manifest-{pantalla}.json`: `id`/`scope`/`start_url` =
   `/{pantalla}` (o `/{pantalla}` base para Clase B con token), `display:
   fullscreen`, íconos propios (no compartir con la app principal).
5. **Íconos** propios en `public/pwa-icons/{pantalla}-*.png` (192, 512, maskable).
6. El scope `/{pantalla}` NO debe ser prefijo de `/app` ni viceversa → instalable
   de forma independiente.

## Notas y Decisiones

- **2026-06-03**: Se elige **Opción B** (login dentro de `/app`) sobre la Opción A
  del explorador (login fuera), por requisito explícito del usuario: al abrir la
  PWA debe verse el login dentro de la app. Técnicamente es necesario para que el
  redirect del auth middleware no salga del scope.
- **2026-06-03**: `/pantalla-cliente` se saca del grupo `tenant` pero conserva
  `auth`; el contexto de comercio lo da `ConfigureTenantMiddleware` (global, grupo
  `web`). Trade-off aceptado: dispositivo sin sesión → redirige a login. Válido
  porque es Clase A (co-ubicada con el POS).
- **2026-06-03**: Redirects de cortesía CON (302) confirmados por el usuario.
- **2026-06-03**: Se distinguen dos clases de pantallas auxiliares (RF-06): A
  co-ubicada (auth + BroadcastChannel, lo actual) y B remota (público + token +
  Reverb, futuro). Este PR cubre solo Clase A; la convención de rutas deja lugar
  para Clase B.
- **2026-06-03**: Sin cambios de BD. Refactor puramente de rutas/manifest/SW/vistas.
- **Futuro (otros PR)**: monitor llamador de pedidos y consultor de precios como
  PWAs auxiliares Clase B (dispositivos remotos sin sesión) — requerirán diseño de
  token público de comercio + canal Reverb. Ver [[reference]] de Reverb del proyecto.
- **Pendiente de validar en apply**: si conviene un redirect de cortesía genérico
  (catch-all de paths viejos) o una lista explícita; preferencia inicial: lista
  explícita de los primeros niveles para evitar capturar paths legítimos públicos.
