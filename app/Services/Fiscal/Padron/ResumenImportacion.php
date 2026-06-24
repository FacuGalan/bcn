<?php

namespace App\Services\Fiscal\Padron;

/**
 * Resultado de una corrida de importación de padrón (Fase 10b, RF-14).
 *
 * Contadores para el resumen que ve el usuario tras importar: cuánto se leyó,
 * cuánto matcheó contra sus clientes, qué se creó/actualizó y qué se respetó.
 */
class ResumenImportacion
{
    public int $totalFilas = 0;       // líneas leídas del archivo

    public int $filasPadron = 0;      // líneas válidas de percepción parseadas

    public int $creadas = 0;          // configs nuevas (origen padrón)

    public int $actualizadas = 0;     // configs de padrón actualizadas

    public int $omitidasManual = 0;   // no pisadas por tener override manual

    public int $sinMatch = 0;         // CUIT del padrón que no es cliente del comercio

    /** Total efectivamente impactado en clientes del comercio. */
    public function impactadas(): int
    {
        return $this->creadas + $this->actualizadas;
    }

    public function toArray(): array
    {
        return [
            'total_filas' => $this->totalFilas,
            'filas_padron' => $this->filasPadron,
            'creadas' => $this->creadas,
            'actualizadas' => $this->actualizadas,
            'omitidas_manual' => $this->omitidasManual,
            'sin_match' => $this->sinMatch,
            'impactadas' => $this->impactadas(),
        ];
    }
}
