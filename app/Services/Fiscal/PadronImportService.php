<?php

namespace App\Services\Fiscal;

use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\Impuesto;
use App\Services\Fiscal\Padron\AgipPadronParser;
use App\Services\Fiscal\Padron\ArbaPadronParser;
use App\Services\Fiscal\Padron\PadronFila;
use App\Services\Fiscal\Padron\PadronParser;
use App\Services\Fiscal\Padron\ResumenImportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importador de padrones de percepción IIBB ARBA/AGIP (Fase 10b, RF-14).
 *
 * Lee un archivo de padrón (subido por el usuario), lo filtra contra los CUIT de
 * los clientes del comercio y upsertea el perfil fiscal por sujeto en
 * `cliente_impuesto_configs` con `origen_alicuota='padron'`.
 *
 * Reglas (spec RF-14):
 *  - El padrón trae TODA la provincia/CABA → se hace STREAMING (fgets) y se
 *    descartan al vuelo las filas cuyo CUIT no sea cliente (memoria acotada).
 *  - **Precedencia: el override manual gana.** No se pisan filas
 *    `origen_alicuota='manual'`.
 *  - Idempotente por (cliente_id, impuesto_id, vigente_desde).
 *  - Exención conservadora (decisión usuario): alícuota 0,00 o baja ⇒ exento.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b),
 *      [[reference_padron_arba_agip_formato]].
 */
class PadronImportService
{
    public const AGENCIA_ARBA = 'arba';

    public const AGENCIA_AGIP = 'agip';

    /**
     * Importa el archivo de padrón de una agencia.
     *
     * @param  string  $rutaArchivo  Path local del archivo (.txt) a leer.
     * @param  string  $agencia  self::AGENCIA_ARBA | self::AGENCIA_AGIP.
     *
     * @throws \InvalidArgumentException agencia desconocida o archivo ilegible.
     * @throws \RuntimeException el impuesto de la jurisdicción no está en el catálogo.
     */
    public function importar(string $rutaArchivo, string $agencia): ResumenImportacion
    {
        $parser = $this->parserPara($agencia);

        $impuesto = Impuesto::porCodigo($parser->impuestoCodigo())->first();

        if (! $impuesto) {
            throw new \RuntimeException("No existe el impuesto '{$parser->impuestoCodigo()}' en el catálogo.");
        }

        if (! is_readable($rutaArchivo)) {
            throw new \InvalidArgumentException("No se puede leer el archivo de padrón: {$rutaArchivo}");
        }

        $mapaClientes = $this->mapaClientesPorCuit();
        $resumen = new ResumenImportacion;

        $handle = fopen($rutaArchivo, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException("No se pudo abrir el archivo de padrón: {$rutaArchivo}");
        }

        try {
            DB::connection('pymes_tenant')->transaction(function () use ($handle, $parser, $impuesto, $mapaClientes, $resumen) {
                while (($linea = fgets($handle)) !== false) {
                    $resumen->totalFilas++;

                    $fila = $parser->parseLinea($linea);

                    if ($fila === null) {
                        continue;
                    }

                    $resumen->filasPadron++;

                    if (! isset($mapaClientes[$fila->cuit])) {
                        $resumen->sinMatch++;

                        continue;
                    }

                    $this->upsertConfig($mapaClientes[$fila->cuit], (int) $impuesto->id, $parser, $fila, $resumen);
                }
            });
        } finally {
            fclose($handle);
        }

        Log::info('Padrón importado', ['agencia' => $agencia] + $resumen->toArray());

        return $resumen;
    }

    /** [cuitNormalizado(11 díg) => clienteId] de los clientes del comercio con CUIT. */
    private function mapaClientesPorCuit(): array
    {
        $mapa = [];

        Cliente::query()
            ->whereNotNull('cuit')
            ->where('cuit', '!=', '')
            ->select('id', 'cuit')
            ->chunk(500, function ($clientes) use (&$mapa) {
                foreach ($clientes as $cliente) {
                    $norm = preg_replace('/\D/', '', (string) $cliente->cuit);

                    if (strlen((string) $norm) === 11) {
                        $mapa[$norm] = $cliente->id;
                    }
                }
            });

        return $mapa;
    }

    private function upsertConfig(int $clienteId, int $impuestoId, PadronParser $parser, PadronFila $fila, ResumenImportacion $resumen): void
    {
        $config = ClienteImpuestoConfig::query()
            ->where('cliente_id', $clienteId)
            ->where('impuesto_id', $impuestoId)
            ->where('vigente_desde', $fila->vigenteDesde)
            ->first();

        // Precedencia: el override manual gana, no se pisa.
        if ($config && $config->origen_alicuota === ClienteImpuestoConfig::ORIGEN_MANUAL) {
            $resumen->omitidasManual++;

            return;
        }

        $datos = [
            'exento' => $fila->exento,
            'alicuota' => $fila->alicuota,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_PADRON,
            'vigente_desde' => $fila->vigenteDesde,
            'vigente_hasta' => $fila->vigenteHasta,
            'datos_extra' => [
                'agencia' => $parser->agencia(),
                'linea' => $fila->lineaCruda,
            ],
        ];

        if ($config) {
            $config->update($datos);
            $resumen->actualizadas++;

            return;
        }

        ClienteImpuestoConfig::create([
            'cliente_id' => $clienteId,
            'impuesto_id' => $impuestoId,
        ] + $datos);

        $resumen->creadas++;
    }

    private function parserPara(string $agencia): PadronParser
    {
        return match ($agencia) {
            self::AGENCIA_ARBA => new ArbaPadronParser,
            self::AGENCIA_AGIP => new AgipPadronParser,
            default => throw new \InvalidArgumentException("Agencia de padrón desconocida: {$agencia}"),
        };
    }
}
