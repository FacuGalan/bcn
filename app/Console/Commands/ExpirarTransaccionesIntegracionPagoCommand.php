<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\IntegracionesPago\CobroIntegracionService;
use App\Services\TenantService;
use Illuminate\Console\Command;

/**
 * Marca como `expirado` las transacciones de cobro por integración que quedaron
 * pendientes y cuyo `expira_en` ya pasó (RF-16). Corre cada minuto por el
 * scheduler.
 *
 * Itera TODOS los comercios (multi-tenant) seteando el contexto tenant y
 * delegando en `CobroIntegracionService::expirarPendientesVencidas()`, que
 * además broadcastea por Reverb para que el modal que aún espera cierre solo.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 8 — RF-16).
 */
class ExpirarTransaccionesIntegracionPagoCommand extends Command
{
    protected $signature = 'integraciones-pago:expirar-pendientes';

    protected $description = 'Expira las transacciones de cobro por integración pendientes cuyo timeout ya venció';

    public function __construct(
        protected TenantService $tenantService,
        protected CobroIntegracionService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            return Command::SUCCESS;
        }

        $totalExpiradas = 0;

        foreach ($comercios as $comercio) {
            try {
                $this->tenantService->setComercio($comercio);

                $expiradas = $this->service->expirarPendientesVencidas();

                if ($expiradas > 0) {
                    $this->info("Comercio {$comercio->nombre}: {$expiradas} transacción(es) expirada(s)");
                    $totalExpiradas += $expiradas;
                }
            } catch (\Throwable $e) {
                $this->error("Error en comercio {$comercio->id}: {$e->getMessage()}");

                continue;
            }
        }

        if ($totalExpiradas > 0) {
            $this->info("Total expiradas: {$totalExpiradas}");
        }

        return Command::SUCCESS;
    }
}
