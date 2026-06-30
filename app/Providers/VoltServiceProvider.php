<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Montar SOLO el árbol de componentes Volt reales (Breeze: layout.navigation,
        // profile.*, pages.auth.*). NO montar resources/views/livewire entero: Volt
        // escanea EAGER cada directorio montado en cada request, y ese árbol tiene
        // ~4 MB / 116 vistas Livewire clásicas → ~750 ms de boot por request en prod.
        // Los nombres Volt (layout.navigation, profile.*, pages.auth.*) se preservan
        // porque se conservan los subpaths bajo este nuevo root.
        // Ref: .claude/docs/deploy-playbook.md (Volt mount).
        Volt::mount([
            resource_path('views/volt'),
        ]);
    }
}
