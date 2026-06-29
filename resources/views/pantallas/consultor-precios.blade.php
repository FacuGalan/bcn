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
                'sinResultados' => __('No se encontró el artículo'),
                'promosActivas' => __('Promociones activas'),
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

        /* Input del scanner: presente y enfocable pero invisible en pantalla. */
        .cp-scanner-input {
            position: fixed; bottom: 0; left: 0; width: 1px; height: 1px;
            opacity: 0; border: 0; padding: 0; background: transparent; color: transparent;
            pointer-events: none;
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
        }
        .cp-header img { max-height: 3rem; display: none; }
        .cp-header .cp-titulo { font-size: clamp(1rem, 2.2vw, 1.5rem); font-weight: 700; opacity: .8; }

        /* Zona central (idle / resultado / no encontrado) */
        .cp-display {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 2rem; min-height: 0; gap: .5rem;
        }

        /* Idle: frase de espera */
        .cp-idle { font-size: clamp(1.75rem, 6vw, 4.5rem); font-weight: 800; opacity: .55; }

        /* Resultado */
        .cp-result { display: none; flex-direction: column; align-items: center; gap: .75rem; animation: cp-pop .25s ease; }
        .cp-nombre { font-size: clamp(2rem, 6vw, 5rem); font-weight: 800; line-height: 1.05; }
        .cp-precio { font-size: clamp(3.5rem, 14vw, 11rem); font-weight: 900; line-height: 1; color: var(--cp-acento); }
        .cp-promos-titulo {
            margin-top: 1rem; font-size: clamp(.9rem, 2vw, 1.4rem); font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em; opacity: .65;
        }
        .cp-promos { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; margin-top: .5rem; }
        .cp-promo {
            font-size: clamp(1rem, 2.4vw, 1.75rem); font-weight: 700; padding: .35rem 1rem;
            border-radius: 999px; background: color-mix(in srgb, var(--cp-acento) 20%, transparent);
            color: var(--cp-acento); border: 2px solid var(--cp-acento);
        }

        /* No encontrado */
        .cp-notfound { display: none; flex-direction: column; align-items: center; gap: .5rem; }
        .cp-notfound .cp-nf-msg { font-size: clamp(1.5rem, 5vw, 3.5rem); font-weight: 800; opacity: .7; }
        .cp-notfound .cp-nf-cod { font-size: clamp(1rem, 2.2vw, 1.5rem); opacity: .4; font-family: ui-monospace, monospace; }

        @keyframes cp-pop { from { transform: scale(.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

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

    {{-- Consultor (orientado a scanner) --}}
    <div id="pantalla" class="cp-pantalla">
        <header class="cp-header">
            <img id="cp-logo" alt="">
            <span class="cp-titulo" id="cp-titulo">{{ __('Consultor de precios') }}</span>
        </header>

        <div class="cp-display">
            {{-- Idle: frase de espera configurable --}}
            <div class="cp-idle" id="cp-idle">{{ __('Escanee un artículo') }}</div>

            {{-- Resultado del escaneo --}}
            <div class="cp-result" id="cp-result">
                <div class="cp-nombre" id="cp-nombre"></div>
                <div class="cp-precio" id="cp-precio"></div>
                <div class="cp-promos-titulo" id="cp-promos-titulo" style="display:none;">{{ __('Promociones activas') }}</div>
                <div class="cp-promos" id="cp-promos"></div>
            </div>

            {{-- No encontrado --}}
            <div class="cp-notfound" id="cp-notfound">
                <div class="cp-nf-msg">{{ __('No se encontró el artículo') }}</div>
                <div class="cp-nf-cod" id="cp-nf-cod"></div>
            </div>
        </div>

        {{-- Footer: Powered by BCNSOFT --}}
        <div class="cp-footer">
            <span>{{ __('Powered by') }}</span>
            <img src="{{ asset('banner_bcn.png') }}" alt="BCNSOFT">
        </div>
    </div>

    {{-- Input invisible que recibe el escaneo (scanner = teclado). --}}
    <input id="cp-input" class="cp-scanner-input" type="text" autocomplete="off" autocapitalize="off"
        autocorrect="off" spellcheck="false" aria-hidden="true" tabindex="-1">
</body>
</html>
