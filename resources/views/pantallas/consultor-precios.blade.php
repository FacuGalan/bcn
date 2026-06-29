<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>{{ __('Consultor de precios') }}</title>

    {{-- PWA dedicada (scope /precios), íconos propios. --}}
    <link rel="manifest" href="{{ asset('manifest-consultor-precios.json') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa-icons/consultor-precios-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('pwa-icons/consultor-precios-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa-icons/consultor-precios-192x192.png') }}">

    <script>
        window.__CONSULTOR__ = {{ Illuminate\Support\Js::from([
            'token' => $bootstrapToken,
            'codigo' => $bootstrapCodigo,
            'i18n' => [
                'buscando' => __('Buscando...'),
                'sinResultados' => __('Sin resultados'),
                'inicial' => __('Buscá un artículo para ver su precio'),
                'codigoInvalido' => __('Código inválido'),
                'sinPrecio' => __('Sin precio'),
            ],
        ]) }};
    </script>
    @vite(['resources/js/consultor-precios.js'])

    <style>
        :root {
            --cp-bg: #0f172a;
            --cp-acento: #22d3ee;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; height: 100%;
            background: var(--cp-bg);
            color: #fff;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
        }

        /* ===== Vinculación ===== */
        .cp-vincular {
            position: fixed; inset: 0; z-index: 40;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            gap: 1.25rem; padding: 2rem; text-align: center; background: var(--cp-bg);
        }
        .cp-vincular h1 { font-size: clamp(1.5rem, 4vw, 2.5rem); margin: 0; }
        .cp-vincular p { opacity: .7; max-width: 32rem; font-size: clamp(1rem, 2vw, 1.25rem); }
        .cp-vincular input {
            font-size: 2rem; letter-spacing: .35em; text-align: center; text-transform: uppercase;
            padding: .6rem 1rem; border-radius: .75rem; border: 2px solid rgba(255,255,255,.25);
            background: rgba(255,255,255,.06); color: #fff; width: min(90vw, 18rem);
        }
        .cp-vincular button {
            font-size: 1.25rem; font-weight: 700; padding: .7rem 2rem; border: 0; border-radius: .75rem;
            background: var(--cp-acento); color: #062a32; cursor: pointer;
        }
        .cp-error { color: #fca5a5; min-height: 1.25rem; font-weight: 600; }

        /* ===== Pantalla principal ===== */
        .cp-pantalla { display: none; height: 100%; flex-direction: column; }
        .cp-header {
            display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .cp-header img { max-height: 3rem; display: none; }
        .cp-header .cp-titulo { font-size: clamp(1.1rem, 2.6vw, 1.75rem); font-weight: 800; }
        .cp-header .cp-sucursal { margin-left: auto; opacity: .6; font-size: clamp(.85rem, 1.5vw, 1.1rem); }

        /* Buscador */
        .cp-buscador { padding: 1.5rem; }
        .cp-buscador input {
            width: 100%; font-size: clamp(1.25rem, 3vw, 2rem); font-weight: 600;
            padding: 1rem 1.25rem; border-radius: 1rem; border: 2px solid rgba(255,255,255,.18);
            background: rgba(255,255,255,.06); color: #fff;
        }
        .cp-buscador input::placeholder { color: rgba(255,255,255,.4); }
        .cp-buscador input:focus { outline: none; border-color: var(--cp-acento); }

        /* Resultados */
        .cp-resultados {
            flex: 1; overflow-y: auto; padding: 0 1.5rem 1.5rem;
            display: flex; flex-direction: column; gap: 1rem;
        }
        .cp-item {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: 1.25rem 1.5rem; border-radius: 1rem;
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
        }
        .cp-item-info { min-width: 0; }
        .cp-item-nombre { font-size: clamp(1.1rem, 2.6vw, 1.9rem); font-weight: 700; }
        .cp-item-unidad { opacity: .5; font-size: clamp(.8rem, 1.4vw, 1rem); }
        .cp-promos { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
        .cp-promo {
            font-size: clamp(.7rem, 1.3vw, .95rem); font-weight: 600; padding: .2rem .6rem;
            border-radius: 999px; background: color-mix(in srgb, var(--cp-acento) 22%, transparent);
            color: var(--cp-acento); border: 1px solid var(--cp-acento);
        }
        .cp-item-precio {
            font-size: clamp(1.6rem, 5vw, 3.5rem); font-weight: 900; line-height: 1; white-space: nowrap;
            color: var(--cp-acento);
        }
        .cp-estado {
            flex: 1; display: flex; align-items: center; justify-content: center;
            text-align: center; opacity: .5; font-size: clamp(1rem, 2.2vw, 1.5rem); padding: 2rem;
        }

        /* Footer */
        .cp-footer {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .4rem; opacity: .4; pointer-events: none;
        }
        .cp-footer span { font-size: .8rem; }
        .cp-footer img { height: 1rem; object-fit: contain; }
    </style>
</head>
<body>
    {{-- Vinculación del dispositivo --}}
    <div id="vincular" class="cp-vincular">
        <h1>{{ __('Vincular dispositivo') }}</h1>
        <p>{{ __('Escaneá el QR de Configuración o ingresá el código de vinculación de la sucursal.') }}</p>
        <form id="vincular-form" autocomplete="off">
            <input id="vincular-codigo" maxlength="8" placeholder="------" aria-label="{{ __('Código de vinculación') }}">
            <div class="cp-error" id="vincular-error"></div>
            <button type="submit">{{ __('Vincular') }}</button>
        </form>
    </div>

    {{-- Consultor --}}
    <div id="pantalla" class="cp-pantalla">
        <header class="cp-header">
            <img id="cp-logo" alt="">
            <span class="cp-titulo" id="cp-titulo">{{ __('Consultor de precios') }}</span>
            <span class="cp-sucursal" id="cp-sucursal"></span>
        </header>
        <div class="cp-buscador">
            <input id="cp-input" type="search" inputmode="search" autocomplete="off" autofocus
                placeholder="{{ __('Buscar artículo o escanear código') }}">
        </div>
        <div class="cp-resultados" id="cp-resultados">
            <div class="cp-estado" id="cp-estado">{{ __('Buscá un artículo para ver su precio') }}</div>
        </div>
        {{-- Footer: Powered by BCNSOFT (sutil, siempre presente) --}}
        <div class="cp-footer">
            <span>{{ __('Powered by') }}</span>
            <img src="{{ asset('banner_bcn.png') }}" alt="BCNSOFT">
        </div>
    </div>
</body>
</html>
