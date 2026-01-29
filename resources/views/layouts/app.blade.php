<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->check() && auth()->user()->dark_mode ? 'dark' : '' }}">
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
        <meta name="msapplication-tap-highlight" content="no">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">

        <!-- PWA Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="/pwa-icons/icon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/pwa-icons/icon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/pwa-icons/icon-180x180.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen">
            {{-- Navegación principal con menú dinámico integrado --}}
            <livewire:layout.navigation />

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        {{-- Sistema de notificaciones toast --}}
        <x-toast-notifications />

        {{-- Dark Mode Toggle Script --}}
        <script>
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('theme-changed', (event) => {
                    const darkMode = event.darkMode;
                    if (darkMode) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                });
            });
        </script>

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
