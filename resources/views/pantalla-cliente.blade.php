<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pantalla cliente') }}</title>
    @vite(['resources/js/pantalla-cliente.js'])
</head>
<body class="h-full bg-gray-900 text-white overflow-hidden">
    <div class="h-screen w-screen flex flex-col items-center justify-center select-none">

        {{-- Estado de espera (sin cobro en curso) --}}
        <div id="pc-idle" class="flex flex-col items-center justify-center text-center px-8">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $empresaNombre }}" class="max-h-48 max-w-[70vw] object-contain mb-8">
            @elseif(!empty($empresaNombre))
                <p class="text-4xl font-bold text-gray-200 mb-8">{{ $empresaNombre }}</p>
            @endif
            <p class="text-2xl font-light text-gray-400">{{ __('Listo para cobrar') }}</p>
        </div>

        {{-- Cobro en curso: QR --}}
        <div id="pc-qr" class="hidden flex-col items-center justify-center text-center px-8">
            <p class="text-xl font-light text-gray-300 mb-2">{{ __('Total a pagar') }}</p>
            <p id="pc-monto" class="text-6xl font-extrabold mb-8 tracking-tight"></p>

            <div class="bg-white p-6 rounded-3xl shadow-2xl">
                <div id="pc-qr-svg" class="w-[360px] h-[360px] flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"></div>
            </div>

            <p id="pc-leyenda" class="mt-8 text-2xl font-medium text-gray-200"></p>
            <p class="mt-2 text-base text-gray-500">{{ __('Abrí la app de pago y escaneá el código') }}</p>
        </div>

    </div>

    {{-- Hint de pantalla completa (se oculta al entrar en fullscreen) --}}
    <button id="pc-fullscreen-hint"
        class="fixed bottom-4 right-4 z-50 inline-flex items-center gap-2 rounded-full bg-gray-800/80 hover:bg-gray-700 text-gray-200 text-sm px-4 py-2 shadow-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
        </svg>
        {{ __('Pantalla completa') }}
    </button>
</body>
</html>
