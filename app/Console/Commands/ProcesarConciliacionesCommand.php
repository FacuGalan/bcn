<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\IntegracionesPago\ConciliacionCuentaService;
use App\Services\TenantService;
use Illuminate\Console\Command;

/**
 * Avanza las conciliaciones de cuenta pendientes (RF-04): solicita el reporte
 * al proveedor, detecta cuando está listo, ejecuta el match y deja la corrida
 * pendiente de revisión. También crea las corridas diarias de las cuentas con
 * conciliación automática activa (RF-08). Corre cada minuto por el scheduler.
 *
 * Itera TODOS los comercios (multi-tenant) seteando el contexto tenant y
 * delegando en ConciliacionCuentaService::procesarPendientes().
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 3).
 */
class ProcesarConciliacionesCommand extends Command
{
    protected $signature = 'conciliaciones:procesar';

    protected $description = 'Avanza las conciliaciones de cuenta pendientes y crea las corridas programadas';

    public function __construct(
        protected TenantService $tenantService,
        protected ConciliacionCuentaService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            return Command::SUCCESS;
        }

        $totalAvanzadas = 0;

        foreach ($comercios as $comercio) {
            try {
                $this->tenantService->setComercio($comercio);

                $avanzadas = $this->service->procesarPendientes();

                if ($avanzadas > 0) {
                    $this->info("Comercio {$comercio->nombre}: {$avanzadas} conciliación(es) avanzada(s)");
                    $totalAvanzadas += $avanzadas;
                }
            } catch (\Throwable $e) {
                $this->error("Error en comercio {$comercio->id}: {$e->getMessage()}");

                continue;
            }
        }

        if ($totalAvanzadas > 0) {
            $this->info("Total avanzadas: {$totalAvanzadas}");
        }

        return Command::SUCCESS;
    }
}
