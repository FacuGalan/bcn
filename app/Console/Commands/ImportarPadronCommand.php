<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\Fiscal\PadronImportService;
use App\Services\TenantService;
use Illuminate\Console\Command;

/**
 * Importa un padrón de percepción IIBB ARBA/AGIP desde una ruta del servidor,
 * sin pasar el archivo por el navegador (sistema-impositivo Fase 10b, RF-14).
 *
 * Vía robusta para padrones grandes (la subida web está acotada por
 * upload_max_filesize/post_max_size de PHP; el padrón completo de ARBA pesa
 * decenas/cientos de MB). El service lee el archivo por streaming (fgets) y solo
 * upsertea los CUIT que son clientes del comercio.
 *
 * Uso: php artisan fiscal:importar-padron /ruta/al/PADRON.TXT --agencia=arba --comercio=1
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b),
 *      [[reference_padron_arba_agip_formato]].
 */
class ImportarPadronCommand extends Command
{
    protected $signature = 'fiscal:importar-padron
        {archivo : Ruta local del padrón en el servidor (.txt, .zip o .gz)}
        {--agencia=arba : Agencia del padrón: arba | agip}
        {--comercio=1 : ID del comercio (tenant)}';

    protected $description = 'Importa un padrón de percepción IIBB ARBA/AGIP desde una ruta del servidor';

    public function handle(TenantService $tenantService, PadronImportService $service): int
    {
        $ruta = (string) $this->argument('archivo');
        $agencia = (string) $this->option('agencia');

        if (! in_array($agencia, [PadronImportService::AGENCIA_ARBA, PadronImportService::AGENCIA_AGIP], true)) {
            $this->error("Agencia inválida: '{$agencia}'. Usá 'arba' o 'agip'.");

            return self::FAILURE;
        }

        if (! is_readable($ruta)) {
            $this->error("No se puede leer el archivo: {$ruta}");

            return self::FAILURE;
        }

        $comercio = Comercio::find((int) $this->option('comercio'));
        if (! $comercio) {
            $this->error('Comercio no encontrado.');

            return self::FAILURE;
        }

        // CLI no tiene sesión → configurar el contexto tenant manualmente.
        $tenantService->setComercio($comercio);

        $this->info("Comercio {$comercio->id} · agencia: {$agencia}");
        $this->line('Archivo: '.$ruta.' ('.$this->formatoTamano($ruta).')');
        $this->line('Importando (streaming)... puede tardar según el tamaño del padrón.');

        try {
            $resumen = $service->importar($ruta, $agencia);
        } catch (\Throwable $e) {
            $this->error('Error al importar: '.$e->getMessage());

            return self::FAILURE;
        }

        $datos = $resumen->toArray();

        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($datos), array_values($datos)),
        );

        $this->info($resumen->impactadas().' clientes actualizados desde el padrón.');

        return self::SUCCESS;
    }

    private function formatoTamano(string $ruta): string
    {
        $bytes = @filesize($ruta);

        if ($bytes === false) {
            return '?';
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
