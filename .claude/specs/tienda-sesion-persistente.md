# Tienda — Sesión Persistente del Consumidor y Login sin Fricción - Especificación

## Estado: EN REVISIÓN

> Spec creado 2026-08-06. Exploración completa (core + tienda). Esperando aprobación de Facu para iniciar Fase 1.
> Continúa la numeración RF-T desde RF-T66 (RF-T65 lo usó el header de seguimiento, bcn-tienda commit ad035cc).

---

## Contexto y Motivación

Hoy el consumidor se desloguea a las 2 horas: el Bearer de Sanctum **no expira nunca** (`sanctum.expiration = null`) pero vive únicamente en la sesión server-side de la tienda (`SESSION_LIFETIME=120`). Muerta la sesión, el token queda huérfano y el usuario reloguea.

El flujo real del negocio agrava el problema: los links de las tiendas están en los perfiles de Instagram de cada comercio → el usuario entra SIEMPRE por el **webview de IG**. Para loguearse con Google debe escapar al navegador real (Google bloquea webviews, `disallowed_useragent`), con lo que su sesión queda en Chrome/Safari… pero su próxima visita vuelve a ser por el webview de IG, **sin sesión**. Riesgo concreto: concreta el pedido entero sin darse cuenta de que no estaba logueado y pierde puntos/beneficios. No queremos resolverlo con banners de "no estás logueado" en todas las vistas.

Dato clave que habilita la solución: **el cookie jar del webview de IG persiste entre visitas**. Si la sesión se establece una vez dentro del webview, un remember-token la mantiene viva ahí indefinidamente.

Se suma al alcance (pedido de Facu): refactor visual del login del CORE en mobile (hoy: mucho espacio arriba, logo solo, formulario abajo) + traducciones de los mensajes de auth que hoy salen en inglés.

---

## Principios de Diseño

1. **El Bearer nunca llega al navegador** (regla 2 del contrato). La cookie de dispositivo lleva un par `selector/validator` opaco, jamás el token Sanctum.
2. **La tienda no tiene BD**: toda persistencia nueva vive en el core (BD `config`) y se expone por API v1. La tienda solo guarda cookies cifradas y cache.
3. **Contrato primero**: todo endpoint nuevo se documenta en `docs/api-v1-delivery.md` antes de consumirse. Core primero, tienda después.
4. **Rotación detecta robo**: cada canje de remember-token rota el validator; el reuso de un validator viejo revoca la familia completa de dispositivos del consumidor.
5. **Degradación honesta**: feature no configurado (Turnstile sin secret, Google sin client_id) = feature apagado, nunca roto (patrón `GoogleIdTokenService::configurado()`).
6. **Anti-enumeración se preserva**: ningún endpoint nuevo revela si un email tiene cuenta (respuestas neutras, patrón `recuperar()` → siempre 200).
7. **Un solo recordatorio, accionable**: la detección de cuenta en checkout es el único aviso de "no estás logueado", en el momento exacto y con solución inline.
8. **Patrones existentes como molde**: `ContinuidadService` (token opaco single-use en cache) para pairing; `PedidoEnCursoCookie`/`DatosCompradorCookie` para cookies cifradas; `ConsumidorTokenService` (HMAC) para el magic link.

---

## Requisitos Funcionales

### RF-T66: Dispositivos recordados (core) — remember-token rotativo selector/validator
- Tabla `config.consumidor_dispositivos` (ver Modelo de Datos).
- Al hacer login/registro/google/magic-link con `recordarme=true` (default de la tienda: siempre true), el core emite un par `{selector, validator}` junto con el Bearer. Se persiste `validator` **hasheado** (sha256).
- `POST /v1/consumidores/auth/recordar` (público, throttle `10,1,c-recordar`): recibe `{selector, validator}`; si es válido y no venció → **rota el validator**, actualiza `ultimo_uso_at`/`ip_ultima`, emite Bearer nuevo y responde `{token, consumidor, dispositivo: {selector, validator}}`.
- **Detección de reuso**: selector válido + validator inválido ⇒ revocar TODOS los dispositivos del consumidor (familia comprometida) + log warning. Respuesta 401 genérica.
- Vencimiento deslizante: `expira_at = ahora + 365 días` se renueva en cada canje. Máximo **10 dispositivos** por consumidor: al emitir el 11°, se elimina el de `ultimo_uso_at` más viejo.
- `GET /v1/consumidores/dispositivos` (auth): lista `{id, nombre, ip_ultima, ultimo_uso_at, actual}` — `actual` compara con el selector enviado en header opcional `X-Dispositivo`.
- `DELETE /v1/consumidores/dispositivos/{id}` (auth) y `DELETE /v1/consumidores/dispositivos` (auth, todos menos el actual).
- `restablecer()` (cambio de contraseña) pasa a revocar también todos los dispositivos, además del `tokens()->delete()` actual.
- **Opción descartada**: Sanctum con `expires_at` largo + refresh (Opción B de la exploración) — no da rotación por familia, ni detección de reuso, ni listado de dispositivos.

### RF-T67: Cookie de dispositivo + re-login silencioso (tienda)
- `DispositivoCookie` (patrón `PedidoEnCursoCookie`): cookie cifrada, httpOnly, Secure, SameSite=Lax, **525600 min (1 año)**, payload `{selector, validator}`.
- Middleware `RecordarConsumidor` (grupo web, después de session): si `!ConsumidorService::logueado()` y hay cookie → canje contra `auth/recordar`; éxito ⇒ `iniciarSesion()` + re-encolar cookie con el validator rotado; 401 ⇒ borrar cookie (sin reintentos). Fallo de red del core ⇒ no-op silencioso (se reintenta en el próximo request).
- `ConsumidorService::iniciarSesion()` guarda el par de dispositivo cuando la respuesta del core lo incluya; `logout()` revoca el dispositivo actual en el core (best-effort) y borra la cookie.
- Complemento: subir `SESSION_LIFETIME` de la tienda a **20160 (2 semanas)** — abarata el caso común; el remember-token cubre el resto.

### RF-T68: Pairing webview↔navegador (tienda, sin endpoint nuevo)
- Decisión de diseño: ambos "contextos" (webview e navegador) son sesiones del MISMO backend de la tienda ⇒ el pairing se resuelve tienda-side con cache (molde `ContinuidadService`), reutilizando los endpoints de RF-T66. El core no necesita endpoint de pairing.
- Flujo: al armar la URL de escape de Google (`ConGoogleSignIn::googleContexto()`), la tienda genera `pairing_id` (`Str::random(40)`), lo guarda en **cookie del webview** (cifrada, 30 días) y lo agrega a la URL de escape junto al `cont` del carrito (parámetros independientes, payloads separados).
- En el navegador real, tras CUALQUIER login exitoso con `pairing_id` presente (Google, password o magic link): la tienda pide al core un **segundo par de dispositivo** (`POST /v1/consumidores/dispositivos`, auth Bearer) y lo deja en cache bajo `pairing:{id}` (TTL 30 días, single-use).
- De vuelta en el webview: el middleware `RecordarConsumidor`, si no hay sesión ni cookie de dispositivo pero SÍ cookie de pairing → `Cache::pull('pairing:{id}')`; si hay par ⇒ canje normal RF-T66 ⇒ sesión iniciada + cookie de dispositivo propia. La cookie de pairing se borra siempre tras el intento.
- Seguridad: el `pairing_id` solo vive en la cookie de ese webview y en la URL de escape; single-use; el par cacheado ya nació rotable (robo detectable por RF-T66).

### RF-T69: Magic link por email
- Core: `POST /v1/consumidores/auth/magic-link` (público, throttle `3,1,c-magic`): recibe `{email}`; **siempre 200** con mensaje neutro (anti-enumeración). Si el email existe, envía mail (patrón `VerificarEmailConsumidor`) con link a la TIENDA: `{tienda}/entrar/{token}?pairing={id?}&volver={ruta?}`.
- Token: `ConsumidorTokenService` tipo nuevo `mgc`, TTL 15 min, **single-use vía `jti` en cache del core** (`Cache::add("mgc:{jti}", 1, 15min)` al canjear; si ya existe ⇒ usado). Decisión: no se crea tabla; si el cache del core se limpia, el link muere y el usuario pide otro (aceptable para TTL 15 min).
- Core: `POST /v1/consumidores/auth/magic-login` (público, throttle `10,1,c-mglogin`): valida el token, responde igual que `login()` (token + consumidor + dispositivo si `recordarme`). Cuenta con email NO verificado: el magic link **verifica el email de paso** (probó control de la casilla, mismo criterio que Google autoritativo).
- Tienda: ruta `GET /entrar/{token}` → componente que canjea, inicia sesión, siembra cookie de dispositivo, resuelve `pairing` si vino (RF-T68) y redirige a `volver` validado (patrón `Login::destino()` anti open-redirect). Como el link SIEMPRE abre en el navegador real: tras loguear muestra **CTA de instalar la PWA** (una vez, descartable, cookie de "ya ofrecido" 90 días).
- Punto de solicitud: pantalla de login ("Enviame un link de acceso") + checkout (RF-T70). Excluido explícitamente: OTP por código (decisión de Facu 2026-08-06 — el magic link lleva tráfico al navegador real donde se ofrece la PWA, futuro canal principal).

### RF-T70: Detección de cuenta existente en checkout invitado
- En el `Checkout`, cuando el invitado completa el campo email (blur / step siguiente), la tienda consulta al core: `POST /v1/tiendas/{slug}/checkout/sugerir-cuenta` (throttle `10,1,t-sugerir`), body `{email}`.
- **Respuesta SIEMPRE neutra**: `{sugerir: bool}` donde `sugerir=true` solo si el email tiene cuenta Y la tienda participa del programa de puntos… **NO**: eso revela existencia. Resolución del conflicto anti-enumeración (Principio 6):
  - El endpoint responde siempre `{ok: true}` sin información, y es el CORE quien decide: si el email tiene cuenta, **manda el magic link directamente** (mismo mail de RF-T69, con `volver=` al checkout de esa tienda). Si no la tiene, no manda nada.
  - La tienda muestra SIEMPRE el mismo aviso discreto bajo el campo: *"Si ese email tiene cuenta, te enviamos un enlace para entrar y sumar los puntos de este pedido"* — un solo aviso, no bloqueante, el checkout invitado continúa normal.
  - Rate limit duro por email+IP en el core (máx 1 mail por email cada 10 min, silencioso) para que no sea un cañón de spam.
- Red de seguridad existente sin cambios: RF-T56/T57 (vinculación retroactiva del pedido invitado desde el seguimiento) cubre al que igual terminó sin loguearse.

### RF-T71: Google One Tap / FedCM (tienda)
- En `google.blade.php`: además de `renderButton()`, llamar `google.accounts.id.prompt()` (One Tap, usa FedCM automáticamente en Chrome) cuando: hay `GOOGLE_CLIENT_ID`, NO es webview embebido (`NavegadorEmbebido`), y `!logueado()`.
- El credential del One Tap entra por el MISMO `entrarConGoogle($credential)` existente. Tras login: sembrar dispositivo (RF-T66/67) y resolver pairing si hay (RF-T68).
- Cooldown de descarte: lo maneja GIS solo (exponential cooldown nativo). No agregar supresión propia.

### RF-T72: Cloudflare Turnstile en registro / recuperar / restablecer
- Core: `TurnstileService` (`config/services.php` → `services.turnstile.secret`): `verificar(token, ip): bool` contra `https://challenges.cloudflare.com/turnstile/v0/siteverify`. Sin secret configurado ⇒ `configurado()=false` y los endpoints NO exigen el campo (degradación honesta).
- Endpoints protegidos: `registro`, `recuperar`, `restablecer` — aceptan campo `turnstile_token`; si el service está configurado y el token falta o es inválido ⇒ 422 `turnstile_invalido`.
- Tienda: widget en las vistas de registro/recuperar/restablecer (`services.turnstile.site_key`); sin site_key ⇒ no se renderiza ni se envía. Modo *managed* (invisible para la mayoría).
- Login NO lleva Turnstile permanente (fricción): su defensa es RF-T73.

### RF-T73: Lockout progresivo por email en login (core)
- Hoy el throttle de login es solo por IP (10/min) — un atacante distribuido puede martillar un email. Agregar en `AuthController::login()`: `RateLimiter` con clave `login-email:{sha256(email)}`, 5 intentos fallidos ⇒ bloqueo 15 min; cada lockout consecutivo duplica la ventana (máx 4 h). `clear()` en login exitoso.
- Respuesta durante lockout: el MISMO 422 `credenciales_invalidas` genérico (no revelar el lockout ⇒ no confirma que el email existe). Confía en la IP real que la tienda ya reenvía (`X-Forwarded-For`, PR #61).
- Aplica también a `magic-link` y `sugerir-cuenta` como límite por email (ya descripto en RF-T69/T70).

### RF-T74: Mis dispositivos (tienda, en Mi Cuenta)
- Sección nueva en `Cuenta`: lista de dispositivos recordados (`GET dispositivos`): nombre amigable derivado del User-Agent + IP + último uso + badge "este dispositivo".
- Acciones: cerrar sesión de un dispositivo (DELETE uno) y "cerrar sesión en todos los demás" (DELETE todos). Diseño según design system tienda.

### RF-C1: Refactor visual del login del CORE en mobile + traducciones de auth
- **Layout** (`layouts/guest.blade.php`): en móvil, el contenedor del logo tiene `flex-1` → el logo flota solo en un bloque gigante y el form queda al fondo. Rediseño móvil estilo app: logo compacto (~h-16) inmediatamente arriba de la tarjeta, conjunto centrado verticalmente en el viewport, fondo `bg-bcn-secondary` pleno. Desktop (`sm:+`) queda EXACTAMENTE como está. Aplica a las 5 vistas de auth (login, forgot, reset, confirm, verify) porque comparten layout.
- **Traducciones**: no existen `lang/{es,pt,en}/auth.php`, `passwords.php` ni `validation.php` ⇒ los mensajes de framework salen en inglés ("These credentials do not match our records.", "We have emailed your password reset link!", "The password field is required."). Crear los 3 archivos en es/pt (en usa los defaults publicados) con las traducciones estándar de laravel-lang. Verificar además que las claves literales de las vistas de auth existan en `lang/en.json` y `lang/pt.json` (en es son la clave misma).
- Sin cambios de lógica de autenticación del core (el modal de límite de sesiones queda igual, solo hereda el layout nuevo en móvil).

### Fase futura (anotada, NO en este spec)
- **Passkeys/WebAuthn** para consumidores (iCloud Keychain / Google Password Manager comparten la credencial entre navegadores del dispositivo).
- Login por QR/código en otro dispositivo (reutiliza la infraestructura de pairing).

---

## Modelo de Datos

### Tablas nuevas

#### `consumidor_dispositivos` (BD **config**, sin prefijo tenant)
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `consumidor_id` | bigint unsigned | — | FK `consumidores.id`, cascade on delete |
| `selector` | char(24) UNIQUE | — | Identificador público del par (random) |
| `validator_hash` | char(64) | — | sha256 del validator (el validator jamás se persiste plano) |
| `nombre` | varchar(120) nullable | null | Derivado del User-Agent al emitir ("Chrome · Android", "Instagram · iPhone") |
| `ip_ultima` | varchar(45) nullable | null | Última IP de canje |
| `ultimo_uso_at` | timestamp nullable | null | Último canje exitoso |
| `expira_at` | timestamp | — | Deslizante: +365 días en cada canje |
| `created_at/updated_at` | timestamps | | |

Índices: `selector` unique, `consumidor_id`, `expira_at` (para poda).

### Tablas modificadas
- Ninguna. (Magic link `jti` y pairing viven en cache, decisión documentada en RF-T68/T69.)

---

## Pantallas UI

### Tienda
1. **Login** (`Consumidor\Login`): botón secundario "Enviame un link de acceso" (input email + confirmación neutra). One Tap activo en la página (y en el resto del sitio vía layout, solo navegadores reales).
2. **Entrar por magic link** (`/entrar/{token}`, componente nuevo `Consumidor\EntrarConLink`): canje + redirect; estados de error (vencido/usado) con CTA "pedir otro link"; CTA instalar PWA post-login (una vez).
3. **Checkout**: aviso discreto bajo el email del invitado (RF-T70), no bloqueante.
4. **Mi Cuenta** (`Consumidor\Cuenta`): sección "Mis dispositivos" (RF-T74).
5. **Registro/Recuperar/Restablecer**: widget Turnstile (si configurado).

### Core
6. **Login mobile** (`layouts/guest.blade.php`): rediseño móvil estilo app (RF-C1), desktop intacto.

---

## Servicios

### Core
- **`app/Services/Consumidores/DispositivoService.php`** (nuevo): `emitir(Consumidor, ?string $userAgent, ?string $ip): array{selector,validator}` (poda al 11°), `canjear(string $selector, string $validator, ...): ?array` (rotación + detección de reuso + familia), `revocar(...)`, `revocarTodos(Consumidor)`.
- **`ConsumidorTokenService`**: tipo nuevo `mgc` (TTL 15 min) + `consumirJti()` (single-use en cache).
- **`app/Services/Consumidores/TurnstileService.php`** (nuevo): `configurado()`, `verificar(token, ip)`.
- **`AuthController`**: `recordar()`, `magicLink()`, `magicLogin()`, lockout por email en `login()`, emisión de dispositivo en login/registro/google/magic-login cuando `recordarme`, revocación de dispositivos en `restablecer()`. **`DispositivosController`** (nuevo): index/destroy/destroyAll.
- **`app/Mail/Consumidores/MagicLinkConsumidor.php`** (nuevo, patrón `VerificarEmailConsumidor`).
- Endpoint `sugerir-cuenta` en el controller de checkout/tiendas público (RF-T70).

### Tienda
- **`app/Services/DispositivoCookie.php`** (nuevo, patrón `PedidoEnCursoCookie`).
- **`app/Http/Middleware/RecordarConsumidor.php`** (nuevo) + registro en `bootstrap/app.php`.
- **`CoreApi`**: `recordar()`, `magicLink()`, `magicLogin()`, `dispositivos()`, `borrarDispositivo()`, `emitirDispositivo()`, `sugerirCuenta()`.
- **`ConsumidorService`**: integrar emisión/canje/olvido de dispositivo en `iniciarSesion()`/`logout()`.
- **`ContinuidadService`** + **`ConGoogleSignIn`**: `pairing_id` en el escape (RF-T68).

---

## Migraciones Necesarias

1. `create_consumidor_dispositivos` — BD config, `Schema::connection('config')` (patrón `add_google_id_a_consumidores`). NO es tabla tenant: no itera comercios ni toca `tenant_tables.sql`.

---

## Traducciones

Claves nuevas (es/en/pt, orden alfabético, ambos repos según corresponda). Muestra representativa — la lista completa se cierra en implementación:

| Clave (es) | en | pt |
|------------|----|----|
| Enviame un link de acceso | Email me a login link | Envie-me um link de acesso |
| Si ese email tiene cuenta, te enviamos un enlace para entrar | If that email has an account, we sent you a sign-in link | Se esse email tiver conta, enviamos um link de acesso |
| Este dispositivo | This device | Este dispositivo |
| Cerrar sesión en los demás dispositivos | Sign out on other devices | Sair dos outros dispositivos |
| El enlace venció o ya fue usado | The link expired or was already used | O link expirou ou já foi usado |
| Instalá la app para pedir más rápido | Install the app to order faster | Instale o app para pedir mais rápido |

Core además: `lang/{es,pt}/auth.php`, `passwords.php`, `validation.php` completos (RF-C1) + claves de auth faltantes en `en.json`/`pt.json`.

---

## Criterios de Aceptación

- [ ] Login en la tienda → cerrar navegador → volver a los 3 días: sesión activa sin reloguear (canje + rotación visible en `consumidor_dispositivos`).
- [ ] Canje con validator viejo (simulando robo): 401 + TODOS los dispositivos del consumidor revocados.
- [ ] Cambio de contraseña revoca Bearers Y dispositivos.
- [ ] Webview IG: escape a Chrome → login Google → volver al webview → sesión iniciada sola (pairing) y persistente (cookie propia).
- [ ] Magic link: llega por mail, single-use (segundo canje falla), verifica email no verificado, aterriza con CTA de PWA, resuelve pairing si vino del webview.
- [ ] Checkout invitado con email de cuenta existente: mail con link enviado, aviso neutro idéntico exista o no la cuenta, checkout continúa sin bloqueo, máx 1 mail/10 min por email.
- [ ] One Tap aparece en Chrome desktop/mobile no logueado; NO aparece en webview IG.
- [ ] Turnstile: con secret configurado, registro sin token ⇒ 422; sin secret, todo funciona igual que hoy.
- [ ] Lockout: 5 logins fallidos del mismo email desde IPs distintas ⇒ bloqueado 15 min con mensaje genérico; login exitoso limpia el contador.
- [ ] Mis dispositivos: lista, revoca uno, revoca todos-menos-este.
- [ ] Login del core en móvil: sin hueco superior, logo+form centrados estilo app; desktop pixel-igual al actual.
- [ ] Login fallido del core en es/pt: mensaje traducido (no "These credentials do not match our records.").
- [ ] Contrato `api-v1-delivery.md` actualizado; suites core y tienda verdes; contract tests de la tienda con fixtures nuevos.

---

## Plan de Implementación

### Fase 1 — Core: dispositivos recordados [COMPLETO]
Migración + modelo + `DispositivoService` + `recordar` + emisión en logins + revocación en `restablecer` + `DispositivosController` + lockout por email (RF-T73) + contrato + tests. **Mergeable sola** (la tienda aún no la consume).

### Fase 2 — Core: magic link + sugerir-cuenta + Turnstile [PENDIENTE]
`mgc` + jti + mailer + `magic-link`/`magic-login` + `sugerir-cuenta` + `TurnstileService` en registro/recuperar/restablecer + contrato + tests. Mergeable sola.

### Fase 3 — Tienda: persistencia + pairing [PENDIENTE]
`DispositivoCookie` + middleware `RecordarConsumidor` + integración `ConsumidorService`/`CoreApi` + `SESSION_LIFETIME=20160` + pairing en el escape (RF-T68) + fixtures/contract tests. Depende de Fase 1.

### Fase 4 — Tienda: magic link UI + checkout + One Tap + Turnstile + dispositivos [PENDIENTE]
`/entrar/{token}` + CTA PWA + aviso en checkout + One Tap + widgets Turnstile + sección Mis dispositivos + traducciones tienda. Depende de Fases 2 y 3.

### Fase 5 — Core: login mobile + traducciones auth (RF-C1) [PENDIENTE]
Independiente de las demás — puede adelantarse o hacerse en paralelo.

---

## Notas y Decisiones

- 2026-08-06: **OTP por email excluido** (Facu): el magic link abre en el navegador real, donde se ofrece instalar la PWA — canal que reemplazará a Instagram a futuro.
- 2026-08-06: **Opción B descartada** (Sanctum expires_at + refresh): sin rotación por familia ni detección de reuso ni pantalla de dispositivos.
- 2026-08-06: **Anti-enumeración resuelta** en RF-T70: el endpoint nunca responde si la cuenta existe; el core actúa (manda el link) sin decirlo. Se preserva la política de RF-T1/`recuperar()`.
- 2026-08-06: **Pairing sin endpoint nuevo**: ambos contextos son sesiones del mismo backend tienda ⇒ cache tienda-side + `POST dispositivos` (auth) alcanza. Menos contrato, misma seguridad.
- 2026-08-06: Magic link/pairing en **cache, no tabla**: TTLs cortos (15 min / 30 días single-use); si el cache se vacía, el usuario pide otro link (aceptable). Revisar si algún día el cache de la tienda deja de ser `file`.
- 2026-08-06: Numeración desde **RF-T66**: RF-T65 ya usado por el header de seguimiento (bcn-tienda `ad035cc`), aunque el spec maestro llegaba a T64.
- Gotchas heredados a respetar: throttles inline SIEMPRE con 3er parámetro (prefijo de bucket); `@php ... @endphp` en blades de la tienda (nunca `@php($expr)`); clases Tailwind nuevas ⇒ `npm run build`.
