<?php

namespace App\Services\Pedidos;

use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\FormaPagoSucursal;
use App\Models\Sucursal;
use App\Services\PuntosService;

/**
 * Puntos de fidelidad para la TIENDA pública (RF-T8/RF-T9, Fase 3).
 *
 * Capa de solo-lectura/cálculo sobre PuntosService para el consumidor
 * logueado con cliente materializado. Decisiones 2026-07-17:
 * - El canje es un PAGO por el MÁXIMO posible (toggle, sin monto libre).
 * - Solo canje como descuento sobre el total (sin artículo-gratis en v1).
 * - `a_ganar` es un ESTIMADO con la fórmula real de acumulación del panel
 *   (multiplicador de la FP, monto_por_punto, redondeo de la config); el
 *   crédito verdadero lo hace la conversión a venta, como siempre.
 */
class PuntosTiendaService
{
    public function __construct(protected PuntosService $puntos) {}

    /**
     * Saldo y reglas del programa para el cliente (RF-T8). Sin cliente,
     * programa inactivo o cliente excluido → activo:false con saldo 0
     * (degradación honesta, nunca un error).
     */
    public function info(Sucursal $sucursal, ?int $clienteId): array
    {
        $inactivo = [
            'activo' => false,
            'saldo' => 0,
            'saldo_en_pesos' => 0.0,
            'valor_punto_canje' => 0.0,
            'minimo_canje' => 0,
            'puede_canjear' => false,
        ];

        if (! $clienteId || ! $this->puntos->isProgramaActivo((int) $sucursal->id)) {
            return $inactivo;
        }

        $config = $this->puntos->getConfiguracion();
        $cliente = Cliente::find($clienteId);
        if (! $config || ! $cliente || ! $cliente->programa_puntos_activo) {
            return $inactivo;
        }

        // Saldo por sucursal solo en modo por_sucursal (semántica del panel).
        $saldo = $this->puntos->obtenerSaldo(
            $clienteId,
            $config->esPorSucursal() ? (int) $sucursal->id : null,
        );
        $valorPunto = (float) $config->valor_punto_canje;

        return [
            'activo' => true,
            'saldo' => $saldo,
            'saldo_en_pesos' => round($saldo * $valorPunto, 2),
            'valor_punto_canje' => $valorPunto,
            'minimo_canje' => (int) $config->minimo_canje,
            'puede_canjear' => $valorPunto > 0 && $saldo >= (int) $config->minimo_canje,
        ];
    }

    /**
     * Canje MÁXIMO aplicable sobre un total (RF-T9): [puntos usados, monto].
     * `null` si no corresponde canjear. Fórmula del panel:
     * puntos = ceil(monto / valor_punto_canje).
     *
     * @return array{usados: int, monto: float}|null
     */
    public function calcularCanjeMaximo(array $info, float $totalAPagar): ?array
    {
        if (! ($info['activo'] ?? false) || ! ($info['puede_canjear'] ?? false) || $totalAPagar <= 0) {
            return null;
        }

        $monto = round(min((float) $info['saldo_en_pesos'], $totalAPagar), 2);
        if ($monto <= 0) {
            return null;
        }

        $usados = (int) ceil($monto / (float) $info['valor_punto_canje']);

        return ['usados' => min($usados, (int) $info['saldo']), 'monto' => $monto];
    }

    /**
     * Puntos que el pedido va a GANAR (estimado): fórmula real de acumulación
     * (PuntosService::calcularPuntosVenta) sobre el monto que se paga SIN
     * puntos, con el multiplicador de la FP declarada (override de sucursal
     * primero, mismo criterio que acreditarPuntosGanados).
     */
    public function estimarAGanar(Sucursal $sucursal, ?int $formaPagoId, float $montoPagado): int
    {
        if ($montoPagado <= 0 || ! $this->puntos->isProgramaActivo((int) $sucursal->id)) {
            return 0;
        }

        $multiplicador = 1.0;
        if ($formaPagoId) {
            $fp = FormaPago::find($formaPagoId);
            if ($fp && $fp->multiplicador_puntos !== null) {
                $multiplicador = (float) $fp->multiplicador_puntos;
            }
            $fpSucursal = FormaPagoSucursal::where('forma_pago_id', $formaPagoId)
                ->where('sucursal_id', (int) $sucursal->id)
                ->first();
            if ($fpSucursal && $fpSucursal->multiplicador_puntos !== null) {
                $multiplicador = (float) $fpSucursal->multiplicador_puntos;
            }
        }

        return $this->puntos->calcularPuntosVenta(collect([[
            'monto_final' => $montoPagado,
            'es_pago_puntos' => false,
            'es_cuenta_corriente' => false,
            'multiplicador_puntos' => $multiplicador,
        ]]));
    }

    /**
     * Bloque `puntos` del contrato para una cotización/alta. `$montoCanje`
     * en 0 = programa visible sin canje aplicado (igual muestra a_ganar).
     */
    public function bloqueContrato(array $info, ?array $canje, Sucursal $sucursal, ?int $formaPagoId, float $totalAPagar): array
    {
        $usados = (int) ($canje['usados'] ?? 0);
        $monto = (float) ($canje['monto'] ?? 0);

        return [
            'usados' => $usados,
            'monto' => $monto,
            'saldo' => (int) $info['saldo'],
            'saldo_restante' => (int) $info['saldo'] - $usados,
            'puede_canjear' => (bool) $info['puede_canjear'],
            'a_ganar' => $this->estimarAGanar($sucursal, $formaPagoId, $totalAPagar - $monto),
        ];
    }
}
