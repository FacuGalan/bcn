<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#222036" id="pc-theme-color">
    <title>{{ __('Pantalla cliente') }}</title>

    {{-- PWA dedicada: manifest propio (display:standalone — abre como ventana
         normal; el botón "Enviar a la 2da pantalla" la pone fullscreen en el otro
         monitor). NO usar el manifest.json de la app. --}}
    <link rel="manifest" href="{{ asset('manifest-pantalla-cliente.json') }}">

    {{-- Ícono propio (monitor) para la ventana/pestaña y atajos — evita que caiga
         al favicon global (logo BCN). --}}
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa-icons/pantalla-cliente-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('pwa-icons/pantalla-cliente-512x512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('pwa-icons/pantalla-cliente-192x192.png') }}">

    @vite(['resources/js/pantalla-cliente.js'])

    <style>
        /* ===== Pantalla cliente: tema dinámico + animaciones ===== */
        /* El html/body comparten el color de fondo de la pantalla para que no
           asome ningún borde blanco (el --pc-bg lo setea el JS en :root). */
        html, body {
            margin: 0;
            background-color: var(--pc-bg, #222036);
        }
        .pc-root {
            background-color: var(--pc-bg, #222036);
            color: var(--pc-texto, #fff);
            transition: background-color .6s ease, color .6s ease;
        }

        /* Tamaños del logo (controlados por clase desde el JS) */
        .pc-logo { max-width: 70vw; }
        .pc-logo-sm { max-height: 8rem; }
        .pc-logo-md { max-height: 12rem; }
        .pc-logo-lg { max-height: 18rem; }

        /* ---- Animación: Respiración + glow ---- */
        .pc-anim-respiracion #pc-logo,
        .pc-anim-respiracion #pc-nombre {
            animation: pc-respira 5.5s ease-in-out infinite;
            will-change: transform, opacity, filter;
        }
        @keyframes pc-respira {
            0%, 100% { transform: scale(1); opacity: .9; filter: drop-shadow(0 0 0 transparent); }
            50%      { transform: scale(1.04); opacity: 1; filter: drop-shadow(0 0 18px var(--pc-acento)); }
        }

        /* ---- Animación: Aurora + flotación ---- */
        .pc-aurora {
            position: absolute;
            inset: -25%;
            z-index: 0;
            opacity: 0;
            background:
                radial-gradient(45% 45% at 20% 25%, var(--pc-acento) 0%, transparent 60%),
                radial-gradient(40% 40% at 80% 30%, var(--pc-acento) 0%, transparent 55%),
                radial-gradient(50% 50% at 50% 85%, var(--pc-acento) 0%, transparent 60%);
            background-size: 200% 200%;
            filter: blur(70px) saturate(140%);
            transition: opacity 1s ease;
        }
        .pc-anim-aurora .pc-aurora {
            opacity: .35;
            animation: pc-aurora-move 22s ease-in-out infinite alternate;
        }
        @keyframes pc-aurora-move {
            0%   { background-position: 0% 0%, 100% 0%, 50% 100%; }
            50%  { background-position: 40% 60%, 60% 40%, 30% 50%; }
            100% { background-position: 100% 100%, 0% 100%, 70% 0%; }
        }
        .pc-anim-aurora #pc-logo {
            animation: pc-flota 7s ease-in-out infinite;
            will-change: transform;
        }
        @keyframes pc-flota {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }

        /* Accesibilidad: degradar a estático si el usuario lo prefiere */
        @media (prefers-reduced-motion: reduce) {
            .pc-anim-respiracion #pc-logo,
            .pc-anim-respiracion #pc-nombre,
            .pc-anim-aurora #pc-logo { animation: none; }
            .pc-anim-aurora .pc-aurora { animation: none; opacity: .25; }
        }
    </style>
</head>
<body class="h-full overflow-hidden">
    {{-- Las variables CSS (--pc-bg, --pc-acento, --pc-texto) y la clase de
         animación las aplica pantalla-cliente.js según la config recibida por
         BroadcastChannel (o la persistida en localStorage al cargar). Los valores
         iniciales son los defaults / fallback de empresa renderizados server-side. --}}
    <div id="pc-root"
         class="pc-root h-screen w-screen flex flex-col items-center justify-center select-none relative"
         data-logo-fallback="{{ !empty($logoPath) ? asset('storage/' . $logoPath) : '' }}"
         data-nombre-fallback="{{ $empresaNombre ?? '' }}"
         style="--pc-bg: #222036; --pc-acento: #22d3ee; --pc-texto: #ffffff;">

        {{-- Capa de fondo para la animación aurora (degradado en movimiento) --}}
        <div id="pc-aurora" class="pc-aurora" aria-hidden="true"></div>

        {{-- Estado de espera (sin cobro en curso) --}}
        <div id="pc-idle" class="pc-stage flex flex-col items-center justify-center text-center px-8 z-10">
            <img id="pc-logo"
                 src="{{ !empty($logoPath) ? asset('storage/' . $logoPath) : '' }}"
                 alt="{{ $empresaNombre }}"
                 class="pc-logo pc-logo-md object-contain mb-8 {{ empty($logoPath) ? 'hidden' : '' }}">
            <p id="pc-nombre" class="text-4xl font-bold mb-8 {{ !empty($logoPath) ? 'hidden' : '' }}">{{ $empresaNombre }}</p>
            <p id="pc-idle-msg" class="text-2xl font-light opacity-70">{{ __('Listo para cobrar') }}</p>
        </div>

        {{-- Cobro en curso: QR --}}
        <div id="pc-qr" class="pc-stage hidden flex-col items-center justify-center text-center px-8 z-10">
            <p class="text-xl font-light opacity-70 mb-2">{{ __('Total a pagar') }}</p>
            <p id="pc-monto" class="text-6xl font-extrabold mb-8 tracking-tight" style="color: var(--pc-acento);"></p>

            <div class="bg-white p-6 rounded-3xl shadow-2xl">
                <div id="pc-qr-svg" class="w-[360px] h-[360px] flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"></div>
            </div>

            <p id="pc-leyenda" class="mt-8 text-2xl font-medium"></p>
            <p class="mt-2 text-base opacity-50">{{ __('Abrí la app de pago y escaneá el código') }}</p>
        </div>

        {{-- Footer: Powered by BCNSOFT (sutil, siempre presente) --}}
        <div class="absolute bottom-4 left-0 right-0 z-10 flex items-center justify-center gap-2 opacity-40 pointer-events-none">
            <span class="text-xs" style="color: var(--pc-texto);">{{ __('Powered by') }}</span>
            <img src="{{ asset('banner_bcn.png') }}" alt="BCNSOFT" class="h-4 object-contain">
        </div>
    </div>

    {{-- Botón Instalar app. Se oculta si ya corre como app instalada (display-mode
         standalone/fullscreen). Instalándola se abre fullscreen y con ícono propio
         en la barra de tareas. Si el navegador no ofrece el prompt nativo, el clic
         muestra instrucciones manuales. --}}
    <button id="pc-install-btn"
        class="hidden fixed bottom-4 left-4 z-50 inline-flex items-center gap-2 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm px-4 py-2 shadow-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        {{ __('Instalar pantalla cliente') }}
    </button>

    {{-- Instrucciones manuales: aparecen cuando no hay prompt nativo o cuando la
         página corre dentro de la app del sistema (no se puede instalar aparte ahí). --}}
    <div id="pc-install-help"
        class="hidden fixed bottom-16 left-4 z-50 max-w-xs rounded-xl bg-gray-900/90 text-gray-100 text-xs px-4 py-3 shadow-xl border border-gray-700">
        <p class="font-semibold mb-1">{{ __('Instalar como app separada') }}</p>
        <p class="opacity-80">{{ __('Abrí esta dirección en tu navegador Chrome o Edge (no dentro de la app del sistema) y usá el menú ⋮ → "Instalar". Luego abrila desde su ícono en el segundo monitor.') }}</p>
    </div>

    {{-- Cartel de instalación destacado: SOLO se muestra cuando la página se abrió con
         ?instalar=1 (desde el botón "Instalar pantalla cliente" del perfil de la app
         principal). Hace obvia la acción de instalar en vez del botón chico de abajo, y
         avisa si ya está instalada. Lo controla pantalla-cliente.js. Los textos
         dinámicos viajan en data-* para respetar las traducciones (__()). --}}
    <div id="pc-install-overlay"
        class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/70 backdrop-blur-sm px-6"
        data-msg-default="{{ __('Instalala como app para abrirla a pantalla completa en el segundo monitor, con su propio ícono.') }}"
        data-msg-instalada="{{ __('Parece que ya está instalada en este navegador. Buscá su ícono para abrirla, o usá el menú ⋮ del navegador → Instalar.') }}"
        data-msg-ok="{{ __('¡Listo! Ya está instalada. Abrila desde su ícono en el segundo monitor.') }}"
        data-txt-instalar="{{ __('Instalar ahora') }}"
        data-txt-entendido="{{ __('Entendido') }}">
        <div class="w-full max-w-md rounded-3xl bg-white text-gray-900 shadow-2xl p-8 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-100 text-violet-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">{{ __('Instalar pantalla cliente') }}</h2>
            <p id="pc-install-overlay-msg" class="text-gray-600 mb-6">{{ __('Instalala como app para abrirla a pantalla completa en el segundo monitor, con su propio ícono.') }}</p>
            <button id="pc-install-overlay-btn"
                class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-base font-semibold px-6 py-3 shadow-lg transition">
                <svg id="pc-install-overlay-btn-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span id="pc-install-overlay-btn-text">{{ __('Instalar ahora') }}</span>
            </button>
            <button id="pc-install-overlay-cerrar" class="mt-4 text-sm text-gray-400 hover:text-gray-600">{{ __('Ahora no') }}</button>
        </div>
    </div>

    {{-- Botones flotantes abajo a la derecha --}}
    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
        {{-- Enviar al 2do monitor y poner fullscreen ahí (Multi-Screen Window
             Placement API). Aparece solo si el SO reporta más de una pantalla.
             Evita tener que arrastrar la ventana a mano. --}}
        <button id="pc-enviar-2da"
            class="hidden inline-flex items-center gap-2 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm px-4 py-2 shadow-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            {{ __('Enviar a la 2da pantalla') }}
        </button>

        {{-- Hint de pantalla completa (se oculta al entrar en fullscreen) --}}
        <button id="pc-fullscreen-hint"
            class="inline-flex items-center gap-2 rounded-full bg-black/30 hover:bg-black/50 text-white text-sm px-4 py-2 shadow-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
            </svg>
            {{ __('Pantalla completa') }}
        </button>
    </div>

    {{-- Registro del Service Worker (instalabilidad del PWA dedicado). El sw.js
         vive en la raíz con scope "/", que cubre /pantalla-cliente. --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js?v={{ \Illuminate\Support\Facades\Vite::manifestHash() ?? 'dev' }}').catch(() => {});
            });
        }
    </script>
</body>
</html>
