<?php

namespace App\Services\Fiscal\Padron;

/**
 * Estrategia de parseo de un padrón de percepción IIBB por agencia (Fase 10b, RF-14).
 *
 * Cada agencia (ARBA, AGIP) publica un layout propio (ver
 * [[reference_padron_arba_agip_formato]] / spec D8). El parseo es POR LÍNEA para
 * que el service pueda hacer streaming (`fgets`) de archivos enormes — el padrón
 * trae toda la provincia/CABA y solo nos importan las filas de nuestros clientes.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b).
 */
interface PadronParser
{
    /**
     * Parsea UNA línea del archivo. Devuelve null si la línea es encabezado,
     * está vacía, no es del régimen de percepción o es inválida (CUIT mal formado).
     */
    public function parseLinea(string $linea): ?PadronFila;

    /** Código de la agencia ('arba' | 'agip'). */
    public function agencia(): string;

    /** Código del impuesto del catálogo al que mapea (perc_iibb_ar_b / perc_iibb_ar_c). */
    public function impuestoCodigo(): string;
}
