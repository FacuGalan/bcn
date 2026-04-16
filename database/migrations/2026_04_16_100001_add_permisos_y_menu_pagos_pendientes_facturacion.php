<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 2 permisos funcionales y el menu_item "Pagos pendientes de facturar"
 * para el feature de reintento de FC nueva cuando falla ARCA durante
 * un cambio de forma de pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pymes');

        $permisos = [
            [
                'codigo' => 'reintentar_facturacion',
                'etiqueta' => 'Reintentar emisión de factura pendiente',
                'descripcion' => 'Permite reintentar la emisión de factura sobre pagos que quedaron pendientes de facturar por falla de ARCA',
                'grupo' => 'Facturación',
                'orden' => 6,
            ],
            [
                'codigo' => 'ver_pagos_pendientes_facturacion',
                'etiqueta' => 'Ver reporte de pagos pendientes de facturar',
                'descripcion' => 'Permite acceder al reporte de pagos que quedaron pendientes de facturación por falla de ARCA',
                'grupo' => 'Caja',
                'orden' => 8,
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

        if (class_exists(\App\Models\PermisoFuncional::class)) {
            \App\Models\PermisoFuncional::syncAllToSpatie();
        }

        // ── Menu Item ──

        $cajasParent = $conn->table('menu_items')
            ->where('slug', 'cajas')
            ->whereNull('parent_id')
            ->first();

        if (! $cajasParent) {
            return;
        }

        if (! $conn->table('menu_items')->where('slug', 'pagos-pendientes-facturacion')->exists()) {
            $maxOrden = (int) $conn->table('menu_items')
                ->where('parent_id', $cajasParent->id)
                ->max('orden');

            $conn->table('menu_items')->insert([
                'parent_id' => $cajasParent->id,
                'nombre' => 'Pagos pendientes de facturar',
                'slug' => 'pagos-pendientes-facturacion',
                'icono' => 'heroicon-o-document-minus',
                'route_type' => 'route',
                'route_value' => 'cajas.pagos-pendientes-facturacion',
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
            'reintentar_facturacion',
            'ver_pagos_pendientes_facturacion',
        ];

        $conn->table('permisos_funcionales')->whereIn('codigo', $codigos)->delete();
        $conn->table('permissions')->whereIn('name', array_map(fn ($c) => "func.{$c}", $codigos))->delete();
        $conn->table('menu_items')->where('slug', 'pagos-pendientes-facturacion')->delete();
    }
};
