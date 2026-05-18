<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orden persistido en la vista Kanban de Pedidos por Mostrador.
 *
 * Agrega `orden_kanban` para soportar drag&drop con persistencia dentro de la
 * misma columna. Inicializa los pedidos existentes con `orden_kanban = id` para
 * preservar el orden actual (id DESC ≡ orden_kanban DESC).
 *
 * Reglas de uso:
 * - Pedidos nuevos heredan `orden_kanban = id` via hook en el modelo.
 * - Drag dentro de la misma columna: se renumera con valores decrecientes
 *   desde MAX(orden_kanban)+1, asi el nuevo orden gana sobre el viejo.
 * - Drag entre columnas (cambio de estado): se resetea a `orden_kanban = id`
 *   para volver al orden natural en la columna destino.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    ADD COLUMN `orden_kanban` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `estado_pago`,
                    ADD INDEX `{$prefix}pedidos_mostrador_orden_kanban_idx` (`estado_pedido`, `orden_kanban`)
                ");

                // Inicializar todos los pedidos existentes con orden_kanban=id.
                // Esto preserva el orden actual (id DESC) y deja gaps grandes
                // para futuras inserciones intercaladas sin renumerar todo.
                DB::connection('pymes')->statement("
                    UPDATE `{$prefix}pedidos_mostrador`
                    SET `orden_kanban` = `id`
                    WHERE `orden_kanban` = 0
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}pedidos_mostrador`
                    DROP INDEX `{$prefix}pedidos_mostrador_orden_kanban_idx`,
                    DROP COLUMN `orden_kanban`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
