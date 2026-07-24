<?php

namespace App\Services\Pedidos;

/**
 * Reparte los BIENES de una operación entre los pagos de un desglose para
 * calcular la base del ajuste por forma de pago (RF-03 del spec
 * multi-pago-consistente-y-panel-delivery).
 *
 * Regla "bienes-primero con tope" (reemplaza al prorrateo proporcional del
 * envío, D17): el envío es un valor fijo sin descuentos ni recargos, así que
 * los bienes (total post-promos/cupón, SIN envío) se asignan a los pagos y
 * el ajuste % de cada pago se calcula SOLO sobre su porción de bienes:
 *
 * - Greedy por `ajuste_porcentaje` ASCENDENTE (mayor descuento primero, el
 *   recargo al final): pro-cliente y determinística — el resultado NO
 *   depende del orden en que se declaren los pagos.
 * - `base_i = min(monto_i, bienes_restantes)` ⇒ Σ bases ≤ bienes: dos FP
 *   con descuento nunca descuentan dos veces sobre la misma mercadería.
 * - Con un pago único que cubre todo, base = bienes (idéntico al single-FP
 *   histórico: el ajuste se calcula sobre el total de bienes).
 *
 * Calculadora PURA: sin BD, sin transacciones, sin estado — compartida por
 * el cotizador de la tienda (CotizadorCarritoTienda::desglosarPagos) y el
 * panel delivery (WithPagosDesglose) para que ambos canales den EXACTAMENTE
 * el mismo total (principio de paridad del spec).
 */
class AsignadorBasesAjustePagos
{
    /**
     * Asigna a cada pago su base de ajuste.
     *
     * @param  list<array{monto: float, ajuste_porcentaje: float}>  $pagos
     *                                                                      (las claves extra de cada pago se preservan)
     * @param  float  $bienes  total de bienes a repartir (sin envío)
     * @return list<array> los mismos pagos, en el MISMO orden, con
     *                     `base_ajuste` (float, 2 decimales) agregada
     */
    public static function asignar(array $pagos, float $bienes): array
    {
        $pagos = array_values($pagos);
        $restante = round(max(0, $bienes), 2);

        // Orden de asignación: ajuste ascendente (−10% antes que 0% antes
        // que +5%); a igual ajuste, orden de declaración (indistinto para el
        // total, pero determinístico).
        $indices = array_keys($pagos);
        usort($indices, function (int $a, int $b) use ($pagos) {
            $ajusteA = (float) ($pagos[$a]['ajuste_porcentaje'] ?? 0);
            $ajusteB = (float) ($pagos[$b]['ajuste_porcentaje'] ?? 0);

            return $ajusteA <=> $ajusteB ?: $a <=> $b;
        });

        foreach ($indices as $i) {
            $base = round(min((float) ($pagos[$i]['monto'] ?? 0), $restante), 2);
            $pagos[$i]['base_ajuste'] = max(0.0, $base);
            $restante = round($restante - $pagos[$i]['base_ajuste'], 2);
        }

        return $pagos;
    }
}
