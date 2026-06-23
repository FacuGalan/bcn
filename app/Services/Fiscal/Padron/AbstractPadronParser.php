<?php

namespace App\Services\Fiscal\Padron;

/**
 * Base común de los parsers de padrón (Fase 10b, RF-14).
 *
 * Concentra el parseo de los formatos compartidos por ARBA y AGIP: alícuota
 * `9,99` (coma decimal), fecha `DDMMAAAA`, CUIT de 11 dígitos. Cada agencia
 * implementa solo el mapeo posicional de sus columnas en parseLinea().
 */
abstract class AbstractPadronParser implements PadronParser
{
    /** "1,50" → 1.5 ; "" o no numérico → null. */
    protected function parseAlicuota(string $raw): ?float
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        return (float) str_replace(',', '.', $raw);
    }

    /** "DDMMAAAA" → "AAAA-MM-DD" ; formato inválido → null. */
    protected function parseFecha(string $raw): ?string
    {
        $raw = trim($raw);

        if (! preg_match('/^\d{8}$/', $raw)) {
            return null;
        }

        $dia = substr($raw, 0, 2);
        $mes = substr($raw, 2, 2);
        $anio = substr($raw, 4, 4);

        if (! checkdate((int) $mes, (int) $dia, (int) $anio)) {
            return null;
        }

        return "{$anio}-{$mes}-{$dia}";
    }

    /** Deja solo dígitos; devuelve null si no quedan 11 (CUIT inválido). */
    protected function normalizarCuit(string $raw): ?string
    {
        $cuit = preg_replace('/\D/', '', $raw);

        return strlen((string) $cuit) === 11 ? $cuit : null;
    }

    /**
     * Construye la fila con el criterio de exención conservador (decisión usuario):
     * alícuota 0,00 o marca de baja ⇒ exento (no se percibe).
     */
    protected function armarFila(string $cuit, ?float $alicuota, bool $marcaBaja, ?string $desde, ?string $hasta, string $linea): PadronFila
    {
        $exento = $marcaBaja || $alicuota === null || $alicuota <= 0.0;

        return new PadronFila(
            cuit: $cuit,
            exento: $exento,
            alicuota: $exento ? null : $alicuota,
            vigenteDesde: $desde,
            vigenteHasta: $hasta,
            lineaCruda: rtrim($linea, "\r\n"),
        );
    }
}
