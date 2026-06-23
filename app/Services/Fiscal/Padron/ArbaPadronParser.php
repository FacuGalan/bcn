<?php

namespace App\Services\Fiscal\Padron;

/**
 * Parser del padrón "Régimen de recaudación por sujeto" de ARBA (Pcia Bs As, AR-B).
 *
 * Layout oficial (archivo PadronRGSPerMMAAAA.txt, percepción), campos separados
 * por ';' (ver [[reference_padron_arba_agip_formato]]):
 *   0 Régimen (R/P)  1 FechaPubl  2 VigDesde  3 VigHasta  4 CUIT(11)
 *   5 Tipo(C/D)  6 MarcaAltaBaja(S/B)  7 MarcaCambioAlic(S/N)  8 Alícuota(9,99)  9 NroGrupo
 *
 * Solo se procesan las filas del régimen de PERCEPCIÓN (P); la retención es del
 * lado compras, fuera de alcance (RF-14).
 */
class ArbaPadronParser extends AbstractPadronParser
{
    public function agencia(): string
    {
        return 'arba';
    }

    public function impuestoCodigo(): string
    {
        return 'perc_iibb_ar_b';
    }

    public function parseLinea(string $linea): ?PadronFila
    {
        $linea = trim($linea);

        if ($linea === '') {
            return null;
        }

        $campos = explode(';', $linea);

        if (count($campos) < 9) {
            return null;
        }

        // Solo régimen de percepción.
        if (strtoupper(trim($campos[0])) !== 'P') {
            return null;
        }

        $cuit = $this->normalizarCuit($campos[4]);

        if ($cuit === null) {
            return null;
        }

        return $this->armarFila(
            cuit: $cuit,
            alicuota: $this->parseAlicuota($campos[8]),
            marcaBaja: strtoupper(trim($campos[6])) === 'B',
            desde: $this->parseFecha($campos[2]),
            hasta: $this->parseFecha($campos[3]),
            linea: $linea,
        );
    }
}
