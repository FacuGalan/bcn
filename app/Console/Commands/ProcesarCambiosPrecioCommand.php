<?php

namespace App\Console\Commands;

use App\Models\CambioPrecioProgramado;
use App\Models\Comercio;
use App\Services\CambioPrecioProgramadoService;
use App\Services\TenantService;
use Illuminate\Console\Command;

class ProcesarCambiosPrecioCommand extends Command
{
    protected $signature = 'precios:procesar-programados';
    protected $description = 'Procesa los cambios de precio programados cuya fecha ya pasó';

    protected TenantService $tenantService;
    protected CambioPrecioProgramadoService $service;

    public function __construct(TenantService $tenantService, CambioPrecioProgramadoService $service)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
        $this->service = $service;
    }

    public function handle(): int
    {
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            return Command::SUCCESS;
        }

        $totalProcesados = 0;

        foreach ($comercios as $comercio) {
            try {
                $this->tenantService->setComercio($comercio);

                $cambiosPendientes = CambioPrecioProgramado::listos()->get();

                if ($cambiosPendientes->isEmpty()) {
                    continue;
                }

                $this->info("Comercio {$comercio->nombre}: {$cambiosPendientes->count()} cambio(s) pendiente(s)");

                foreach ($cambiosPendientes as $cambio) {
                    $this->service->ejecutarCambioProgramado($cambio);

                    $estado = $cambio->fresh()->estado;
                    if ($estado === 'procesado') {
                        $this->info("  OK: {$cambio->descripcion_ajuste} ({$cambio->total_articulos} artículos)");
                        $totalProcesados++;
                    } else {
                        $this->error("  Error: {$cambio->resultado}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error en comercio {$comercio->id}: {$e->getMessage()}");
                continue;
            }
        }

        if ($totalProcesados > 0) {
            $this->info("Total procesados: {$totalProcesados}");
        }

        return Command::SUCCESS;
    }
}
