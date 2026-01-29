<?php

namespace App\Console\Commands;

use App\Models\Caja;
use App\Models\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalcularNumerosVentas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ventas:recalcular-numeros
                            {--dry-run : Simular sin hacer cambios}
                            {--force : Ejecutar sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula los números de venta para usar el nuevo formato por caja (NUMERO_CAJA-SECUENCIAL)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('=== Recálculo de Números de Venta ===');
        $this->info('');

        if ($dryRun) {
            $this->warn('MODO SIMULACIÓN: No se realizarán cambios');
            $this->info('');
        }

        // Obtener todas las cajas con sus números
        $cajas = Caja::orderBy('sucursal_id')->orderBy('numero')->get();

        if ($cajas->isEmpty()) {
            $this->error('No se encontraron cajas. Asegúrate de ejecutar la migración primero.');
            return 1;
        }

        $this->info("Cajas encontradas: {$cajas->count()}");
        $this->table(
            ['ID', 'Sucursal', 'Número', 'Nombre'],
            $cajas->map(fn($c) => [$c->id, $c->sucursal_id, $c->numero, $c->nombre])
        );
        $this->info('');

        // Contar ventas por caja
        $ventasPorCaja = Venta::select('caja_id', DB::raw('count(*) as total'))
            ->whereNotNull('caja_id')
            ->groupBy('caja_id')
            ->pluck('total', 'caja_id');

        $totalVentas = $ventasPorCaja->sum();

        if ($totalVentas === 0) {
            $this->info('No hay ventas para procesar.');
            return 0;
        }

        $this->info("Total de ventas a procesar: {$totalVentas}");
        $this->info('');

        // Mostrar resumen por caja
        $this->info('Ventas por caja:');
        foreach ($cajas as $caja) {
            $count = $ventasPorCaja[$caja->id] ?? 0;
            if ($count > 0) {
                $this->line("  - Caja {$caja->numero_formateado} ({$caja->nombre}): {$count} ventas");
            }
        }
        $this->info('');

        // Confirmar
        if (!$force && !$dryRun) {
            if (!$this->confirm('¿Deseas continuar con el recálculo?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        // Procesar cada caja
        $bar = $this->output->createProgressBar($totalVentas);
        $bar->start();

        $actualizadas = 0;
        $errores = 0;

        foreach ($cajas as $caja) {
            $ventas = Venta::where('caja_id', $caja->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            if ($ventas->isEmpty()) {
                continue;
            }

            $secuencial = 1;

            foreach ($ventas as $venta) {
                $nuevoNumero = sprintf('%04d-%08d', $caja->numero, $secuencial);
                $numeroAnterior = $venta->numero;

                if (!$dryRun) {
                    try {
                        $venta->update(['numero' => $nuevoNumero]);
                        $actualizadas++;

                        Log::info('Número de venta recalculado', [
                            'venta_id' => $venta->id,
                            'numero_anterior' => $numeroAnterior,
                            'numero_nuevo' => $nuevoNumero,
                            'caja_id' => $caja->id,
                            'caja_numero' => $caja->numero,
                        ]);
                    } catch (\Exception $e) {
                        $errores++;
                        Log::error('Error al recalcular número de venta', [
                            'venta_id' => $venta->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $actualizadas++;
                    $this->line(""); // Nueva línea para el log
                    $this->line("  Venta #{$venta->id}: {$numeroAnterior} → {$nuevoNumero}");
                }

                $secuencial++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->info('');
        $this->info('');

        // Resumen
        $this->info('=== Resumen ===');
        $this->info("Ventas procesadas: {$actualizadas}");
        if ($errores > 0) {
            $this->error("Errores: {$errores}");
        }

        if ($dryRun) {
            $this->warn('');
            $this->warn('Este fue un dry-run. Ejecuta sin --dry-run para aplicar los cambios.');
        } else {
            $this->info('');
            $this->info('¡Recálculo completado exitosamente!');
        }

        return 0;
    }
}
