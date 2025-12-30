<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#222036">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="BCN Pymes">
        <meta name="application-name" content="BCN Pymes">
        <meta name="msapplication-TileColor" content="#222036">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">

        <!-- PWA Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/icons/icon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-bcn-secondary">
            {{-- Logo centrado en la parte superior --}}
            <div class="flex-1 sm:flex-none flex items-center justify-center py-6 sm:pt-12 sm:pb-6">
                <a href="/" wire:navigate>
                    <x-application-logo class="h-24 sm:h-24 w-auto" />
                </a>
            </div>

            {{-- Contenedor del formulario --}}
            <div class="sm:flex-1 flex flex-col sm:items-center sm:justify-center px-4 sm:px-0">
                <div class="w-full sm:max-w-md px-6 py-6 sm:py-8 bg-white shadow-xl rounded-2xl mb-6 sm:mb-8">
                    {{ $slot }}
                </div>
            </div>
        </div>

        {{-- PWA Service Worker Registration --}}
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((registration) => {
                            console.log('SW registrado:', registration.scope);
                        })
                        .catch((error) => {
                            console.log('SW registro fallido:', error);
                        });
                });
            }
        </script>
    </body>
</html>
