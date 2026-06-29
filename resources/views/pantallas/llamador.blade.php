<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>{{ __('Llamador de pedidos') }}</title>

    {{-- PWA dedicada (scope /llamador), íconos propios. --}}
    <link rel="manifest" href="{{ asset('manifest-llamador.json') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa-icons/llamador-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('pwa-icons/llamador-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa-icons/llamador-192x192.png') }}">

    <script>
        window.__LLAMADOR__ = {{ Illuminate\Support\Js::from(['token' => $bootstrapToken, 'codigo' => $bootstrapCodigo]) }};
    </script>
    @vite(['resources/js/llamador.js'])

    <style>
        :root {
            --llm-bg: #0f172a;
            --llm-prep: #f59e0b;
            --llm-listo: #22c55e;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; height: 100%;
            background: var(--llm-bg);
            color: #fff;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
        }

        /* ===== Capa de desbloqueo de audio =====
           Arranca oculta; el JS la muestra solo con el monitor activo (no debe
           tapar la pantalla de vinculación). */
        .llm-overlay {
            position: fixed; inset: 0; z-index: 50;
            display: none; align-items: center; justify-content: center;
            background: rgba(0,0,0,.78); cursor: pointer; text-align: center;
        }
        .llm-overlay span { font-size: clamp(1.5rem, 4vw, 2.75rem); font-weight: 700; opacity: .9; }

        /* ===== Vinculación ===== */
        .llm-vincular {
            position: fixed; inset: 0; z-index: 40;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            gap: 1.25rem; padding: 2rem; text-align: center; background: var(--llm-bg);
        }
        .llm-vincular h1 { font-size: clamp(1.5rem, 4vw, 2.5rem); margin: 0; }
        .llm-vincular p { opacity: .7; max-width: 32rem; font-size: clamp(1rem, 2vw, 1.25rem); }
        .llm-vincular input {
            font-size: 2rem; letter-spacing: .35em; text-align: center; text-transform: uppercase;
            padding: .6rem 1rem; border-radius: .75rem; border: 2px solid rgba(255,255,255,.25);
            background: rgba(255,255,255,.06); color: #fff; width: min(90vw, 18rem);
        }
        .llm-vincular button {
            font-size: 1.25rem; font-weight: 700; padding: .7rem 2rem; border: 0; border-radius: .75rem;
            background: var(--llm-listo); color: #062a12; cursor: pointer;
        }
        .llm-error { color: #fca5a5; min-height: 1.25rem; font-weight: 600; }

        /* ===== Pantalla principal ===== */
        .llm-pantalla { display: none; height: 100%; flex-direction: column; }
        .llm-header {
            display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .llm-header img { max-height: 3.5rem; display: none; }
        .llm-header .llm-titulo { font-size: clamp(1.25rem, 3vw, 2rem); font-weight: 800; }
        .llm-header .llm-sucursal { margin-left: auto; opacity: .6; font-size: clamp(.9rem, 1.6vw, 1.25rem); }

        .llm-cols { flex: 1; display: flex; min-height: 0; }
        .llm-col { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .llm-col + .llm-col { border-left: 1px solid rgba(255,255,255,.08); }
        .llm-col-head {
            text-align: center; font-size: clamp(1.1rem, 2.4vw, 1.9rem); font-weight: 800;
            text-transform: uppercase; letter-spacing: .05em; padding: .75rem; color: #0b1220;
        }
        .llm-col-prep .llm-col-head { background: var(--llm-prep); }
        .llm-col-listo .llm-col-head { background: var(--llm-listo); }
        /* Escala combinada: densidad elegida en config (--llm-base) × reducción
           automática para que entren todos sin scroll (--llm-fit, lo setea el JS
           por columna). En una TV no se puede scrollear, así que NO hay overflow. */
        .llm-list {
            --llm-base: 1; --llm-fit: 1;
            flex: 1; overflow: hidden; padding: calc(1rem * var(--llm-fit));
            display: flex; flex-wrap: wrap; gap: calc(1rem * var(--llm-base) * var(--llm-fit));
            align-content: flex-start; justify-content: center;
        }

        /* Tarjeta de pedido */
        .llm-card {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-width: calc(clamp(7rem, 14vw, 12rem) * var(--llm-base) * var(--llm-fit));
            padding: calc(1rem * var(--llm-base) * var(--llm-fit)) calc(1.5rem * var(--llm-base) * var(--llm-fit));
            border-radius: 1rem;
            background: rgba(255,255,255,.06); border: 2px solid rgba(255,255,255,.1);
        }
        .llm-num { font-size: calc(clamp(3rem, 9vw, 7rem) * var(--llm-base) * var(--llm-fit)); font-weight: 900; line-height: 1; }
        .llm-nombre { margin-top: .5rem; font-size: calc(clamp(1rem, 2.6vw, 2rem) * var(--llm-base) * var(--llm-fit)); opacity: .85; }

        /* La columna "Listo" resalta sus tarjetas */
        .llm-col-listo .llm-card {
            background: color-mix(in srgb, var(--llm-listo) 22%, transparent);
            border-color: var(--llm-listo);
            animation: llm-pop .4s ease;
        }
        @keyframes llm-pop { from { transform: scale(.85); } to { transform: scale(1); } }

        /* Footer: Powered by BCNSOFT (sutil, igual que la pantalla cliente) */
        .llm-footer {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .4rem; opacity: .4; pointer-events: none;
        }
        .llm-footer span { font-size: .8rem; }
        .llm-footer img { height: 1rem; object-fit: contain; }
    </style>
</head>
<body>
    {{-- Desbloqueo de audio (autoplay): se oculta al primer toque. --}}
    <div id="audio-unlock" class="llm-overlay">
        <span>{{ __('Tocá para activar el sonido') }}</span>
    </div>

    {{-- Vinculación del dispositivo --}}
    <div id="vincular" class="llm-vincular">
        <h1>{{ __('Vincular dispositivo') }}</h1>
        <p>{{ __('Escaneá el QR de Configuración o ingresá el código de vinculación de la sucursal.') }}</p>
        <form id="vincular-form" autocomplete="off">
            <input id="vincular-codigo" maxlength="8" placeholder="------" aria-label="{{ __('Código de vinculación') }}">
            <div class="llm-error" id="vincular-error"></div>
            <button type="submit">{{ __('Vincular') }}</button>
        </form>
    </div>

    {{-- Monitor --}}
    <div id="pantalla" class="llm-pantalla">
        <header class="llm-header">
            <img id="llm-logo" alt="">
            <span class="llm-titulo" id="llm-titulo">{{ __('Pedidos') }}</span>
            <span class="llm-sucursal" id="llm-sucursal"></span>
        </header>
        <div class="llm-cols">
            <section class="llm-col llm-col-prep">
                <div class="llm-col-head">{{ __('En preparación') }}</div>
                <div class="llm-list" id="col-preparacion"></div>
            </section>
            <section class="llm-col llm-col-listo">
                <div class="llm-col-head">{{ __('Listo / Retirar') }}</div>
                <div class="llm-list" id="col-listo"></div>
            </section>
        </div>
        {{-- Footer: Powered by BCNSOFT (sutil, siempre presente) --}}
        <div class="llm-footer">
            <span>{{ __('Powered by') }}</span>
            <img src="{{ asset('banner_bcn.png') }}" alt="BCNSOFT">
        </div>
    </div>
</body>
</html>
