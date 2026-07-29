<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RF-T29 (ronda crossmap 2): monto/cantidad MÁXIMOS en condiciones de promos
 * comunes. Las columnas `cantidad_maxima` y `monto_maximo` ya están en el
 * template tenant_tables.sql (todo tenant nuevo las tiene) pero NUNCA tuvieron
 * migración propia: tenants provisionados antes de que entraran al template
 * pueden no tenerlas. Migración DEFENSIVA idempotente: el ADD COLUMN de un
 * tenant que ya las tiene falla y el try/catch lo saltea.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            foreach ([
                "ALTER TABLE `{$prefix}promociones_condiciones` ADD COLUMN `cantidad_maxima` DECIMAL(12,3) DEFAULT NULL AFTER `cantidad_minima`",
                "ALTER TABLE `{$prefix}promociones_condiciones` ADD COLUMN `monto_maximo` DECIMAL(12,2) DEFAULT NULL AFTER `monto_minimo`",
            ] as $sql) {
                try {
                    DB::connection('pymes')->statement($sql);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    /**
     * Sin inversa a propósito: las columnas pertenecen al template
     * tenant_tables.sql (los tenants nuevos las traen de fábrica) — dropearlas
     * en un rollback rompería tenants que nunca pasaron por esta migración.
     */
    public function down(): void
    {
        // no-op
    }
};
