<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 4 permisos funcionales y el menu_item "Ajustes post-cierre"
 * para el feature de cambio de forma de pago en ventas registradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        // ── Permisos Funcionales ──

        $permisos = [
            [
                'codigo' => 'cambiar_forma_pago_venta',
                'etiqueta' => 'Cambiar forma de pago en ventas registradas',
                'descripcion' => 'Permite modificar, agregar o eliminar formas de pago en ventas ya registradas',
                'grupo' => 'Ventas',
                'orden' => 8,
            ],
            [
                'codigo' => 'cambiar_forma_pago_turno_cerrado',
                'etiqueta' => 'Cambiar forma de pago sobre turnos cerrados',
                'descripcion' => 'Permite modificar pagos de ventas pertenecientes a turnos ya cerrados (se registra como ajuste post-cierre)',
                'grupo' => 'Ventas',
                'orden' => 9,
            ],
            [
                'codigo' => 'modificar_pagos_sin_nc',
                'etiqueta' => 'Modificar pagos fiscales sin emitir NC',
                'descripcion' => 'Permite saltar la emisión de Nota de Crédito cuando la configuración lo permite (solo casos de preguntar)',
                'grupo' => 'Facturación',
                'orden' => 5,
            ],
            [
                'codigo' => 'ver_ajustes_post_cierre',
                'etiqueta' => 'Ver reporte de ajustes post-cierre',
                'descripcion' => 'Permite acceder al reporte de cambios de pago aplicados sobre turnos ya cerrados',
                'grupo' => 'Caja',
                'orden' => 7,
            ],
        ];

        foreach ($permisos as $p) {
            if (! $conn->table('permisos_funcionales')->where('codigo', $p['codigo'])->exists()) {
                $conn->table('permisos_funcionales')->insert([
                    'codigo' => $p['codigo'],
                    'etiqueta' => $p['etiqueta'],
                    'descripcion' => $p['descripcion'],
                    'grupo' => $p['grupo'],
                    'orden' => $p['orden'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Sincronizar con Spatie
        if (class_exists(\App\Models\PermisoFuncional::class)) {
            \App\Models\PermisoFuncional::syncAllToSpatie();
        }

        // ── Menu Item ──

        $cajasParent = $conn->table('menu_items')
            ->where('slug', 'cajas')
            ->whereNull('parent_id')
            ->first();

        if (! $cajasParent) {
            // En entorno de test las tablas de menú pueden no estar seeded
            return;
        }

        if (! $conn->table('menu_items')->where('slug', 'ajustes-post-cierre')->exists()) {
            $maxOrden = (int) $conn->table('menu_items')
                ->where('parent_id', $cajasParent->id)
                ->max('orden');

            $conn->table('menu_items')->insert([
                'parent_id' => $cajasParent->id,
                'nombre' => 'Ajustes post-cierre',
                'slug' => 'ajustes-post-cierre',
                'icono' => 'heroicon-o-adjustments-horizontal',
                'route_type' => 'route',
                'route_value' => 'cajas.ajustes-post-cierre',
                'orden' => $maxOrden + 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pymes');

        $codigos = [
            'cambiar_forma_pago_venta',
            'cambiar_forma_pago_turno_cerrado',
            'modificar_pagos_sin_nc',
            'ver_ajustes_post_cierre',
        ];

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', array_map(fn ($c) => "func.{$c}", $codigos))->delete();
        $conn->table('menu_items')->where('slug', 'ajustes-post-cierre')->delete();
    }
};
