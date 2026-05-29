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
            <svg class="w-20 h-20 text-gray-600 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
            </svg>
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
</body>
</html>
