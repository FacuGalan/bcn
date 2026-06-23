<?php

namespace App\Services\Fiscal\Padron;

/**
 * Fila normalizada de un padrón de percepción IIBB (ARBA/AGIP), Fase 10b (RF-14).
 *
 * Salida común de cualquier PadronParser, agnóstica de la agencia. El criterio
 * de exención es conservador (decisión usuario 2026-06-23): alícuota 0,00 o
 * marca de baja ⇒ exento=true (no se le percibe), nunca se asume percepción
 * ante la duda.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b).
 */
class PadronFila
{
    public function __construct(
        public readonly string $cuit,          // 11 dígitos, sin guiones
        public readonly bool $exento,          // true ⇒ no se percibe
        public readonly ?float $alicuota,      // % a percibir (null si exento)
        public readonly ?string $vigenteDesde, // Y-m-d o null
        public readonly ?string $vigenteHasta, // Y-m-d o null
        public readonly string $lineaCruda,    // traza → datos_extra
    ) {}
}
