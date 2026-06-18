<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Models\Cuit;
use App\Services\ARCA\ARCAService;
use App\Services\TenantService;
use Illuminate\Console\Command;

/**
 * Consulta los tipos de tributo válidos del WSFEv1 de AFIP/ARCA
 * (FEParamGetTiposTributos) para un CUIT del comercio.
 *
 * Sirve para obtener los códigos (Id) que AFIP acepta en el array `Tributos`
 * de un comprobante → es la fuente del `codigo_arca` del catálogo de impuestos
 * (sistema impositivo, Fase 5b).
 *
 * Uso: php artisan arca:tipos-tributos --comercio=1 [--cuit=ID]
 */
class ArcaTiposTributosCommand extends Command
{
    protected $signature = 'arca:tipos-tributos
        {--comercio=1 : ID del comercio (tenant)}
        {--cuit= : ID del CUIT a usar; por defecto el primero en testing con certificados}';

    protected $description = 'Lista los tipos de tributo de AFIP (FEParamGetTiposTributos) para definir codigo_arca';

    public function handle(TenantService $tenantService): int
    {
        $comercio = Comercio::find((int) $this->option('comercio'));
        if (! $comercio) {
            $this->error('Comercio no encontrado.');

            return self::FAILURE;
        }

        // CLI no tiene sesión → configurar el contexto tenant manualmente.
        $tenantService->setComercio($comercio);

        $cuitId = $this->option('cuit');
        $cuit = $cuitId
            ? Cuit::find((int) $cuitId)
            : Cuit::query()->whereNotNull('certificado_path')->whereNotNull('clave_path')->first();

        if (! $cuit) {
            $this->error('No se encontró un CUIT con certificados. Pasá --cuit=ID.');

            return self::FAILURE;
        }

        if (! $cuit->tieneCertificados()) {
            $this->error("El CUIT {$cuit->numero_cuit} no tiene certificados configurados.");

            return self::FAILURE;
        }

        $this->info("Comercio {$comercio->id} · CUIT {$cuit->cuit_formateado} · entorno: {$cuit->entorno_afip}");
        $this->line('Consultando FEParamGetTiposTributos...');

        try {
            $tributos = (new ARCAService($cuit))->obtenerTiposTributos();
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($tributos)) {
            $this->warn('AFIP no devolvió tipos de tributo.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id (codigo_arca)', 'Descripción', 'Vigente desde', 'Vigente hasta'],
            array_map(fn ($t) => [$t['id'], $t['desc'], $t['desde'] ?? '-', $t['hasta'] ?? '-'], $tributos)
        );

        $this->info(count($tributos).' tipos de tributo. Usá la columna Id para el codigo_arca del catálogo.');

        return self::SUCCESS;
    }
}
