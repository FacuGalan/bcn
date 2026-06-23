<?php

namespace App\Services\Fiscal\Padron;

/**
 * Parser del "Padrón unificado" de AGIP (CABA, AR-C).
 *
 * Layout oficial (un solo archivo, ambas alícuotas + razón social), campos
 * separados por ';' (ver [[reference_padron_arba_agip_formato]]):
 *   0 FechaPubl  1 VigDesde  2 VigHasta  3 CUIT(11)  4 Tipo(C/D)
 *   5 MarcaAlta(S/N/B)  6 MarcaAlic(S/N/B)  7 AlícPercep(9,99)  8 AlícReten(9,99)
 *   9 GrupoPerc  10 GrupoReten  11 RazónSocial
 *
 * Solo se usa la alícuota de PERCEPCIÓN (campo 7); la retención es del lado
 * compras, fuera de alcance (RF-14).
 */
class AgipPadronParser extends AbstractPadronParser
{
    public function agencia(): string
    {
        return 'agip';
    }

    public function impuestoCodigo(): string
    {
        return 'perc_iibb_ar_c';
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

        $cuit = $this->normalizarCuit($campos[3]);

        if ($cuit === null) {
            return null;
        }

        return $this->armarFila(
            cuit: $cuit,
            alicuota: $this->parseAlicuota($campos[7]),
            marcaBaja: strtoupper(trim($campos[5])) === 'B',
            desde: $this->parseFecha($campos[1]),
            hasta: $this->parseFecha($campos[2]),
            linea: $linea,
        );
    }
}
