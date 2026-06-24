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
 *  - Acepta el archivo COMPRIMIDO tal cual lo bajan de la agencia: .zip (formato
 *    oficial de ARBA/AGIP) o .gz, además del .txt plano. Se detecta el formato
 *    por los bytes mágicos (no por extensión, porque el temporal de Livewire no
 *    la conserva) y se descomprime por streaming, sin volcar a disco.
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

        $handle = $this->abrirPadron($rutaArchivo);

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

    /**
     * Abre el padrón devolviendo un handle de lectura por streaming, soportando
     * .txt plano, .gz y .zip. El formato se detecta por bytes mágicos (el
     * temporal de Livewire no conserva la extensión).
     *
     * @return resource
     */
    private function abrirPadron(string $ruta)
    {
        $handle = match ($this->detectarFormato($ruta)) {
            'zip' => $this->abrirZip($ruta),
            'gzip' => @fopen('compress.zlib://'.$ruta, 'r'),
            default => @fopen($ruta, 'r'),
        };

        if ($handle === false) {
            throw new \InvalidArgumentException("No se pudo abrir el archivo de padrón: {$ruta}");
        }

        return $handle;
    }

    /** 'zip' | 'gzip' | 'texto' según los primeros bytes del archivo. */
    private function detectarFormato(string $ruta): string
    {
        $f = @fopen($ruta, 'rb');

        if ($f === false) {
            return 'texto';
        }

        $magic = (string) fread($f, 4);
        fclose($f);

        // ZIP: "PK\x03\x04" (normal) | "PK\x05\x06" (vacío) | "PK\x07\x08" (spanned).
        if (str_starts_with($magic, "PK\x03\x04") || str_starts_with($magic, "PK\x05\x06") || str_starts_with($magic, "PK\x07\x08")) {
            return 'zip';
        }

        // GZIP: "\x1f\x8b".
        if (strlen($magic) >= 2 && $magic[0] === "\x1f" && $magic[1] === "\x8b") {
            return 'gzip';
        }

        return 'texto';
    }

    /**
     * Abre por streaming la primera entrada de texto de un .zip (la primera
     * .txt; si no hay, la primera entrada de archivo). No vuelca a disco.
     *
     * @return resource
     */
    private function abrirZip(string $ruta)
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('El servidor no tiene habilitada la extensión zip de PHP.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new \InvalidArgumentException("No se pudo abrir el .zip del padrón: {$ruta}");
        }

        $entrada = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);

            // Saltar directorios y entradas vacías.
            if ($nombre === false || $nombre === '' || str_ends_with($nombre, '/')) {
                continue;
            }

            if (str_ends_with(strtolower($nombre), '.txt')) {
                $entrada = $nombre;
                break;
            }

            $entrada ??= $nombre; // fallback: primera entrada de archivo
        }

        $zip->close();

        if ($entrada === null) {
            throw new \InvalidArgumentException('El .zip del padrón no contiene ningún archivo.');
        }

        $handle = @fopen('zip://'.$ruta.'#'.$entrada, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException("No se pudo leer la entrada '{$entrada}' del .zip del padrón.");
        }

        return $handle;
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
