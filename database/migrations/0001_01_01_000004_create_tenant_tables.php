<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\TenantService;

/**
 * Crea las tablas de tenant (con prefijo del comercio)
 * Esta migración debe ejecutarse con: php artisan tenant:migrate {comercio_id}
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = app(TenantService::class)->getTablePrefix();
        if (empty($prefix)) {
            // Si no hay tenant, no hacer nada (se ejecutará después con tenant:migrate)
            return;
        }

        $sqlFile = database_path('sql/tenant_tables.sql');
        if (!file_exists($sqlFile)) {
            throw new \Exception('No se encontró el archivo SQL de tablas tenant');
        }

        $sql = file_get_contents($sqlFile);
        // Reemplazar placeholder por prefijo real
        $sql = str_replace('{{PREFIX}}', $prefix, $sql);

        DB::unprepared($sql);
    }

    public function down(): void
    {
        $prefix = app(TenantService::class)->getTablePrefix();
        if (empty($prefix)) {
            return;
        }

        // Lista de tablas a eliminar (en orden inverso de dependencias)
        $tables = [
            'comprobante_fiscal_ventas', 'comprobante_fiscal_iva', 'comprobante_fiscal_items',
            'comprobantes_fiscales', 'cobro_pagos', 'cobro_ventas', 'cobros',
            'venta_detalle_promociones', 'venta_promociones', 'venta_pagos',
            'ventas_detalle', 'ventas', 'compras_detalle', 'compras',
            'transferencias_stock', 'transferencias_efectivo', 'movimientos_caja',
            'grupos_cierre', 'cierre_turno_cajas', 'cierres_turno', 'user_cajas',
            'role_has_permissions', 'model_has_permissions', 'model_has_roles', 'roles',
            'configuracion_impresion', 'impresora_tipo_documento', 'impresora_sucursal_caja', 'impresoras',
            'stock', 'punto_venta_caja', 'cuit_sucursal', 'empresa_config',
            'promocion_especial_escalas', 'promocion_especial_grupo_articulos', 'promocion_especial_grupos',
            'promociones_especiales', 'promociones_escalas', 'promociones_condiciones', 'promociones',
            'lista_precio_condiciones', 'lista_precio_articulos', 'listas_precios',
            'forma_pago_conceptos', 'conceptos_pago', 'formas_pago_cuotas_sucursales',
            'formas_pago_sucursales', 'formas_pago_cuotas', 'formas_pago',
            'formas_venta', 'canales_venta', 'proveedores', 'clientes_sucursales', 'clientes',
            'articulos_sucursales', 'articulo_etiqueta', 'grupos_etiquetas', 'etiquetas',
            'articulos', 'tipos_iva', 'categorias', 'cajas', 'puntos_venta', 'cuits', 'sucursales',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS `{$prefix}{$table}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};