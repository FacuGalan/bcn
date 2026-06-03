# Personalización de la Pantalla Cliente (segunda pantalla) — Spec / Handoff

## Estado: COMPLETO — los 10 pasos implementados y verificados (tests + build OK).
Rama: `feat/pantalla-cliente-personalizacion` (creada desde master post-#122).

> Handoff escrito el 2026-06-02. Implementación terminada el 2026-06-03:
> 24 tests verdes (ConfigSucursalFlagsTest + SmokeConfiguracionTest), 62 verdes en
> Ventas/Pedidos, Pint OK, `npm run build` OK. Falta: docs (`@docs-sync` al crear PR),
> commit + PR, y validación visual en vivo del usuario.

---

## Qué se pide

Personalizar la segunda pantalla (pantalla cliente) de un punto de venta. En el
punto de venta de la sucursal, con la 2da pantalla activada (`cajas.usa_pantalla_cliente`),
aparece un botón **"Personalizar 2da pantalla"** al lado de "Configurar" que abre
un modal para configurar la apariencia. Además, footer sutil "Powered by BCNSOFT".

## Decisiones tomadas (con el usuario, NO re-preguntar)

1. **Config POR SUCURSAL** (no por caja). El botón está en cada punto, pero edita
   la config de la **sucursal** de esa caja. Todas las cajas de la sucursal heredan.
2. **PWA dedicada con `display: "fullscreen"`** para la pantalla cliente. Resuelve
   2 cosas: (a) se abre en **pantalla completa automáticamente** (el popup actual
   NO podía: la Fullscreen API exige gesto del usuario; un PWA `display:fullscreen`
   arranca fullscreen por manifest, sin gesto); (b) **desacopla** la ventana del
   POS en la barra de tareas de Windows (ícono propio). NOTA HONESTA: no se puede
   **ocultar** del todo una ventana web del taskbar (restricción del SO), pero el
   PWA separado evita que se agrupe con el POS y moleste al hacer click.
3. **Animación elegible** entre **"Respiración + glow"** y **"Aurora + flotación"**
   (más opción "ninguna"). Desarrollar ambas, suaves/smooth/modernas, leve movimiento.
4. **Extras incluidos** (confirmado): color de acento, mensaje de espera (idle)
   personalizable, color de texto con **auto-contraste** según el fondo, tamaño del logo.
5. **Logo**: usar el de la **sucursal** (`sucursales.logo_path`) si tiene; si no,
   el del **comercio** (`EmpresaConfig.logo_path`). Toggle mostrar/ocultar.
6. **Footer**: "Powered by" + `public/banner_bcn.png` (imagen BCNSOFT del header),
   centrado, sutil (baja opacidad), siempre presente en la pantalla cliente.

## Schema de `sucursales.config_pantalla_cliente` (JSON, cast array)

| key | tipo | default | nota |
|-----|------|---------|------|
| `mostrar_logo` | bool | `true` | |
| `mostrar_nombre` | bool | `true` | nombre = `nombre_publico` ?: `nombre` de la sucursal, o nombre del comercio |
| `color_fondo` | hex | `#222036` | color picker (es el theme-color de la app) |
| `animacion` | enum | `aurora` | `ninguna` \| `respiracion` \| `aurora` |
| `color_acento` | hex | `#22d3ee` | monto / leyenda / detalles |
| `color_texto` | string | `auto` | `auto` (contraste según fondo) o hex custom |
| `mensaje_idle` | string | `Listo para cobrar` | texto del estado de espera |
| `tamano_logo` | enum | `md` | `sm` \| `md` \| `lg` |

Mapeo animación elegida por el usuario: opción "Respiración + glow" → `respiracion`;
"Aurora + flotación" → `aurora`.

---

## HECHO (commiteado en la rama)

- ✅ Migración tenant `2026_06_02_180000_add_config_pantalla_cliente_to_sucursales.php`
  (columna `config_pantalla_cliente` text/JSON AFTER `configuracion`). Aplicada en
  **dev y testing**.
- ✅ `database/sql/tenant_tables.sql` regenerado (columna en `sucursales`).
- ✅ `app/Models/Sucursal.php`: `config_pantalla_cliente` en `$fillable` + cast `array`.

## PENDIENTE (pasos para la próxima sesión, en orden)

1. **Sucursal model**: helper `getConfigPantallaCliente(): array` que mergee la
   config guardada con los DEFAULTS de la tabla de arriba (para que nunca falten
   keys). Helper `logoPantallaClienteUrl(): ?string` que resuelva logo sucursal
   ?: logo empresa (`EmpresaConfig::getConfig()->logo_url`). Y `nombrePantallaCliente()`.

2. **ConfiguracionEmpresa** (`app/Livewire/Configuracion/ConfiguracionEmpresa.php`):
   - Props del modal (bindeadas): `pcSucursalId`, `pcMostrarLogo`, `pcMostrarNombre`,
     `pcColorFondo`, `pcAnimacion`, `pcColorAcento`, `pcColorTexto`, `pcMensajeIdle`,
     `pcTamanoLogo`, `mostrarModalPersonalizarPantalla`.
   - `abrirPersonalizarPantalla(int $cajaId)`: resuelve la sucursal de la caja
     (`Caja::find($cajaId)->sucursal_id`), carga `getConfigPantallaCliente()` en las props,
     abre modal. (Permiso: el mismo que ya gatea la config de empresa.)
   - `guardarPersonalizarPantalla()`: arma el array y `$sucursal->update(['config_pantalla_cliente' => [...]])`.
   - `cerrarModalPersonalizarPantalla()`.

3. **Botón** en `resources/views/livewire/configuracion/partials/caja-card.blade.php`
   (después de línea ~213, tras "Puntos Fiscales"): `<button wire:click="abrirPersonalizarPantalla({{ $caja->id }})" ...>`
   con ícono (ej. monitor/sparkles) + texto "Personalizar 2da pantalla".
   **VISIBLE SOLO si `$caja->usa_pantalla_cliente`** (`@if($caja->usa_pantalla_cliente)`).
   Seguir el patrón visual exacto de los otros botones (usar skill `/vista` / design-system).

4. **Modal** en `resources/views/livewire/configuracion/configuracion-empresa.blade.php`
   con `<x-bcn-modal>` (header color del botón que lo abre). Campos: toggles logo/nombre,
   color picker fondo (`<input type="color">` + hex), color picker acento, select animación
   (ninguna/respiracion/aurora), input mensaje idle, select tamaño logo, color texto
   (auto vs custom). Idealmente un **mini-preview** en vivo (Alpine) del aspecto.
   OJO `<x-bcn-modal>` requiere `<x-slot:body>` y `<x-slot:footer>` (ver memorias
   [[feedback-bcn-modal-slots]] / [[feedback-bcn-modal-body-footer-scope]]).

5. **Vista pantalla cliente** `resources/views/pantalla-cliente.blade.php`:
   - Aplicar config vía CSS custom properties (`--pc-bg`, `--pc-acento`, `--pc-texto`).
   - Animaciones CSS: `respiracion` (scale+opacity+glow del logo/nombre) y `aurora`
     (degradado en movimiento de fondo via `background-position` lento + logo flotando).
     Desarrollar limpio, smooth, `prefers-reduced-motion` respetado.
   - **Footer** centrado abajo: `Powered by` + `<img src="{{ asset('banner_bcn.png') }}">`
     con `opacity-40` aprox, pequeño.
   - Link al **manifest dedicado** (ver paso 8).

6. **JS pantalla cliente** `resources/js/pantalla-cliente.js`:
   - Listener nuevo `{ type: 'config', config }` → `aplicarConfig(config)` (setea CSS
     vars, muestra/oculta logo/nombre, activa clase de animación, actualiza mensaje idle).
   - **Persistir** la última config en `localStorage` (`bcn-pc-config`) y **aplicarla al
     cargar** (así la PWA se ve con la marca al instante, sin esperar al host).
   - Auto-contraste: si `color_texto = auto`, calcular blanco/negro según luminancia del fondo.

7. **Host (POS)** envía la config por BroadcastChannel:
   - `resources/js/pantalla-cliente-host.js`: método `enviarConfig(config)` (postMessage
     `{ type: 'config', config }`); llamarlo en `conectar()` y antes de cada `enviarQr`.
   - La config la rinde **server-side** el componente host (NuevaVenta / PedidosMostrador /
     el que use `_boton-pantalla-cliente.blade.php`) desde `sucursal->getConfigPantallaCliente()`
     + logo URL + nombre, y la pasa al host JS (data-attribute o Alpine `x-data`).
   - Revisar `resources/views/livewire/carrito/_boton-pantalla-cliente.blade.php` para
     el punto de enganche.

8. **PWA dedicada**:
   - Crear `public/manifest-pantalla-cliente.json` con `id`/`scope`/`start_url` = `/pantalla-cliente`,
     `display: "fullscreen"`, `name`/`short_name` "Pantalla Cliente", `theme_color`/`background_color`,
     `icons` (reutilizar `logo_bcn.png` o generar). 
   - En `pantalla-cliente.blade.php`: `<link rel="manifest" href="/manifest-pantalla-cliente.json">`
     (NO el `/manifest.json` de la app). 
   - Verificar `public/sw.js` scope ("/" cubre /pantalla-cliente) — la pantalla cliente
     puede registrar el SW para installability, o reutilizar el existente.
   - Flujo de uso: el usuario instala la PWA "Pantalla Cliente" una vez en el monitor 2;
     arranca fullscreen por manifest; escucha el BroadcastChannel.

9. **Traducciones** es/en/pt (skill `/traducir`) de todos los labels del modal.

10. **Tests + build**:
    - Smoke `Livewire::test(ConfiguracionEmpresa::class)->assertOk()` (ya debería existir;
      verificar que el nuevo modal no rompa el mount).
    - Test: `abrirPersonalizarPantalla` carga defaults y `guardarPersonalizarPantalla`
      persiste en `sucursales.config_pantalla_cliente`.
    - `npm run build` (cambios JS → public/build gitignoreado; el deploy debe buildear).

---

## Mapa de archivos (del relevamiento, con líneas de referencia)

| Qué | Archivo | Línea(s) |
|-----|---------|----------|
| Ruta pantalla cliente | `routes/web.php` | ~86-99 (Route::view, sin auth, usa EmpresaConfig) |
| Vista pantalla cliente | `resources/views/pantalla-cliente.blade.php` | 1-46 (body `bg-gray-900`; logo línea ~15; idle 13-20; qr 22-33) |
| JS cliente | `resources/js/pantalla-cliente.js` | 1-99 (BroadcastChannel `bcn-pantalla-cliente`; listener 52-65; fullscreen 70-98) |
| JS host | `resources/js/pantalla-cliente-host.js` | 1-94 (conectar/enviarQr/limpiar; Window Management API) |
| Botón conectar (host) | `resources/views/livewire/carrito/_boton-pantalla-cliente.blade.php` | — |
| Config caja (component) | `app/Livewire/Configuracion/ConfiguracionEmpresa.php` | prop 183; abrirConfigCaja 859; guardarConfigCaja 873; cerrar 912 |
| Toggle usa_pantalla_cliente | `resources/views/livewire/configuracion/configuracion-empresa.blade.php` | 254-267 |
| Botones de caja (insertar acá) | `resources/views/livewire/configuracion/partials/caja-card.blade.php` | 193-214 (Configurar 196, Puntos Fiscales 206; nuevo botón tras 213) |
| Modelo Caja | `app/Models/Caja.php` | usa_pantalla_cliente fillable 58 / cast 70; sucursal() 121 |
| Logo empresa | `app/Models/EmpresaConfig.php` | logo_path 44; getLogoUrlAttribute 67-74 |
| Logo/nombre sucursal | `app/Models/Sucursal.php` | logo_path; nombre_publico; getLogoUrlAttribute 229 |
| Imagen BCNSOFT | `public/banner_bcn.png` (header) y `public/logo_bcn.png` (isótipo) | referenciar con `asset()` |
| PWA app existente | `public/manifest.json`, `public/sw.js`, link en `resources/views/layouts/app.blade.php:21` | — |

## Riesgos / notas
- La ruta `/pantalla-cliente` no tiene auth (intencional, monitor "tonto"). La config
  viaja por BroadcastChannel (mismo origen), no por la URL → no expone datos por GET.
- `prefers-reduced-motion`: las animaciones deben degradar a estático.
- El nombre que ve el pagador en MP es aparte (cuenta MP) — esto es solo la pantalla local.
