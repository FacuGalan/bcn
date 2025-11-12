<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Caja;
use App\Models\Venta;

class CleanupCajas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:cajas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina cajas 4-9 y reasigna ventas a cajas 1, 2, 3 y 10';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Iniciando limpieza de cajas ===');

        // Establecer el prefijo del comercio 1
        $prefix = '000001';
        config(['database.connections.pymes_tenant.prefix' => $prefix]);
        DB::purge('pymes_tenant');

        // Cajas que queremos mantener
        $cajasValidas = [1, 2, 3, 10];

        // Cajas a eliminar
        $cajasAEliminar = [4, 5, 6, 7, 8, 9];

        // 1. Contar ventas por caja antes
        $this->info("\n1. Ventas actuales por caja:");
        $ventasPorCaja = DB::connection('pymes_tenant')
            ->table('ventas')
            ->select('caja_id', DB::raw('count(*) as total'))
            ->groupBy('caja_id')
            ->get();

        foreach ($ventasPorCaja as $row) {
            $this->line("   Caja {$row->caja_id}: {$row->total} ventas");
        }

        // 2. Obtener ventas de cajas a eliminar
        $ventasAReasignar = DB::connection('pymes_tenant')
            ->table('ventas')
            ->whereIn('caja_id', $cajasAEliminar)
            ->orWhereNull('caja_id')
            ->orWhereNotIn('caja_id', array_merge($cajasValidas, $cajasAEliminar))
            ->get();

        $this->info("\n2. Ventas a reasignar: {$ventasAReasignar->count()}");

        // 3. Reasignar ventas de forma equitativa entre las cajas válidas
        if ($ventasAReasignar->count() > 0) {
            $this->info("\n3. Reasignando ventas...");
            $indexCaja = 0;
            $totalReasignadas = 0;

            foreach ($ventasAReasignar as $venta) {
                $nuevaCajaId = $cajasValidas[$indexCaja];

                DB::connection('pymes_tenant')
                    ->table('ventas')
                    ->where('id', $venta->id)
                    ->update(['caja_id' => $nuevaCajaId]);

                $totalReasignadas++;

                // Rotar entre las cajas válidas
                $indexCaja = ($indexCaja + 1) % count($cajasValidas);
            }

            $this->info("   ✓ {$totalReasignadas} ventas reasignadas");
        }

        // 4. Eliminar cajas 4-9
        $this->info("\n4. Eliminando cajas 4-9...");

        // Primero eliminar relaciones en user_cajas
        $deletedUserCajas = DB::connection('pymes_tenant')
            ->table('user_cajas')
            ->whereIn('caja_id', $cajasAEliminar)
            ->delete();
        $this->line("   ✓ {$deletedUserCajas} relaciones user_cajas eliminadas");

        // Luego eliminar las cajas
        $deletedCajas = DB::connection('pymes_tenant')
            ->table('cajas')
            ->whereIn('id', $cajasAEliminar)
            ->delete();
        $this->line("   ✓ {$deletedCajas} cajas eliminadas");

        // 5. Mostrar resumen final
        $this->info("\n5. Distribución final de ventas:");
        $ventasPorCajaFinal = DB::connection('pymes_tenant')
            ->table('ventas')
            ->select('caja_id', DB::raw('count(*) as total'))
            ->groupBy('caja_id')
            ->get();

        foreach ($ventasPorCajaFinal as $row) {
            $this->line("   Caja {$row->caja_id}: {$row->total} ventas");
        }

        $totalVentas = DB::connection('pymes_tenant')->table('ventas')->count();
        $this->info("\n✓ Total de ventas: {$totalVentas}");

        $this->info("\n=== Limpieza completada exitosamente ===");

        return 0;
    }
}
