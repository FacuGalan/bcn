<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConfiguracionPuntos;
use App\Models\ConfiguracionPuntosSucursal;
use App\Models\MovimientoPunto;
use App\Models\Venta;
use App\Models\VentaPago;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PuntosService
{
    /**
     * Obtiene la configuración del programa de puntos del comercio actual.
     */
    public function getConfiguracion(): ?ConfiguracionPuntos
    {
        return ConfiguracionPuntos::first();
    }

    /**
     * Verifica si el programa de puntos está activo (globalmente y en la sucursal).
     */
    public function isProgramaActivo(?int $sucursalId = null): bool
    {
        $config = $this->getConfiguracion();

        if (! $config || ! $config->activo) {
            return false;
        }

        if ($sucursalId !== null) {
            $configSucursal = ConfiguracionPuntosSucursal::where('sucursal_id', $sucursalId)->first();
            if ($configSucursal && ! $configSucursal->activo) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcula cuántos puntos generaría una venta (preview, sin registrar).
     * Se calcula sobre cada VentaPago (excepto pagos con puntos y cupones).
     * Fórmula: SUM(monto_pago × multiplicador_forma_pago) / monto_por_punto
     */
    public function calcularPuntosVenta(Collection $pagos): int
    {
        $config = $this->getConfiguracion();

        if (! $config || ! $config->activo || $config->monto_por_punto <= 0) {
            return 0;
        }

        $montoEfectivo = 0;

        foreach ($pagos as $pago) {
            // Los pagos con puntos no generan nuevos puntos
            if (($pago['es_pago_puntos'] ?? false) || ($pago['es_cuenta_corriente'] ?? false)) {
                continue;
            }

            $montoBase = (float) ($pago['monto_final'] ?? $pago['monto_base'] ?? 0);
            $multiplicador = (float) ($pago['multiplicador_puntos'] ?? 1.00);
            $montoEfectivo += $montoBase * $multiplicador;
        }

        if ($montoEfectivo <= 0) {
            return 0;
        }

        $puntosRaw = $montoEfectivo / (float) $config->monto_por_punto;

        return $this->redondear($puntosRaw, $config->redondeo);
    }

    /**
     * Acumula puntos tras completar una venta.
     * Se llama DESPUÉS del commit de la venta (fuera de la transacción principal).
     */
    public function acumularPuntosPorVenta(Venta $venta, Collection $pagos, int $usuarioId): ?MovimientoPunto
    {
        // Validar que aplica
        if (! $venta->cliente_id) {
            return null;
        }

        if (! $this->isProgramaActivo($venta->sucursal_id)) {
            return null;
        }

        $cliente = Cliente::find($venta->cliente_id);
        if (! $cliente || ! $cliente->programa_puntos_activo) {
            return null;
        }

        $puntos = $this->calcularPuntosVenta($pagos);

        if ($puntos <= 0) {
            return null;
        }

        $montoTotal = $pagos->sum(fn ($p) => (float) ($p['monto_final'] ?? $p['monto_base'] ?? 0));

        try {
            $movimiento = MovimientoPunto::crearMovimientoAcumulacion(
                $venta->cliente_id,
                $venta->sucursal_id,
                $puntos,
                $montoTotal,
                $venta->id,
                $usuarioId
            );

            // Actualizar venta con puntos ganados
            $venta->update(['puntos_ganados' => $puntos]);

            // Actualizar cache del cliente
            $this->actualizarCacheCliente($venta->cliente_id);

            Log::info('Puntos acumulados por venta', [
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'puntos' => $puntos,
                'monto_total' => $montoTotal,
            ]);

            return $movimiento;
        } catch (Exception $e) {
            Log::error('Error al acumular puntos por venta', [
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'error' => $e->getMessage(),
            ]);

            // No relanzamos — la venta ya se creó, los puntos son secundarios
            return null;
        }
    }

    /**
     * Registra el canje de puntos como forma de pago (descuento directo).
     * Se llama desde VentaService dentro de su transacción.
     */
    public function canjearPuntosComoDescuento(
        int $clienteId,
        int $sucursalId,
        float $montoDescuento,
        int $ventaPagoId,
        int $ventaId,
        int $usuarioId
    ): array {
        $config = $this->getConfiguracion();

        if (! $config || $config->valor_punto_canje <= 0) {
            throw new Exception('Programa de puntos no configurado correctamente');
        }

        $puntosNecesarios = (int) ceil($montoDescuento / (float) $config->valor_punto_canje);
        $saldoActual = $this->obtenerSaldo($clienteId, $config->esPorSucursal() ? $sucursalId : null);

        if ($puntosNecesarios > $saldoActual) {
            throw new Exception("Puntos insuficientes. Necesarios: {$puntosNecesarios}, Disponibles: {$saldoActual}");
        }

        $movimiento = MovimientoPunto::crearMovimientoCanjeDescuento(
            $clienteId,
            $sucursalId,
            $puntosNecesarios,
            $montoDescuento,
            $ventaPagoId,
            $ventaId,
            $usuarioId
        );

        Log::info('Puntos canjeados como descuento', [
            'cliente_id' => $clienteId,
            'puntos_usados' => $puntosNecesarios,
            'monto_descuento' => $montoDescuento,
            'venta_id' => $ventaId,
        ]);

        return [
            'puntos_usados' => $puntosNecesarios,
            'monto_equivalente' => $montoDescuento,
            'movimiento' => $movimiento,
        ];
    }

    /**
     * Registra el canje de un artículo por puntos.
     * Se llama desde VentaService dentro de su transacción.
     */
    public function canjearArticuloConPuntos(
        int $clienteId,
        int $articuloId,
        int $sucursalId,
        int $puntosNecesarios,
        int $ventaId,
        int $usuarioId
    ): MovimientoPunto {
        $config = $this->getConfiguracion();
        $saldoActual = $this->obtenerSaldo($clienteId, $config?->esPorSucursal() ? $sucursalId : null);

        if ($puntosNecesarios > $saldoActual) {
            throw new Exception("Puntos insuficientes para canjear artículo. Necesarios: {$puntosNecesarios}, Disponibles: {$saldoActual}");
        }

        $movimiento = MovimientoPunto::crearMovimientoCanjeArticulo(
            $clienteId,
            $sucursalId,
            $puntosNecesarios,
            $articuloId,
            $ventaId,
            $usuarioId
        );

        Log::info('Artículo canjeado con puntos', [
            'cliente_id' => $clienteId,
            'articulo_id' => $articuloId,
            'puntos_usados' => $puntosNecesarios,
            'venta_id' => $ventaId,
        ]);

        return $movimiento;
    }

    /**
     * Crea contraasientos para revertir todos los movimientos de puntos de una venta.
     * Patrón append-only: no se borran, se crean movimientos inversos.
     */
    public function crearContraasientosVenta(Venta $venta, int $usuarioId): array
    {
        $contraasientos = [];

        $movimientos = MovimientoPunto::where('venta_id', $venta->id)
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        foreach ($movimientos as $movimiento) {
            $contraasientos[] = MovimientoPunto::crearContraasiento(
                $movimiento,
                "Anulación de venta #{$venta->numero}",
                $usuarioId
            );
        }

        // Actualizar cache del cliente
        if ($venta->cliente_id) {
            $this->actualizarCacheCliente($venta->cliente_id);
        }

        Log::info('Contraasientos de puntos creados por anulación de venta', [
            'venta_id' => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'contraasientos' => count($contraasientos),
        ]);

        return $contraasientos;
    }

    /**
     * Verifica si una venta puede anularse sin dejar puntos negativos.
     * RF-14: Si al revertir acumulación el saldo quedaría negativo, bloquear.
     */
    public function validarAnulacionVenta(Venta $venta): bool
    {
        if (! $venta->cliente_id || $venta->puntos_ganados <= 0) {
            return true; // No hay puntos que revertir
        }

        $config = $this->getConfiguracion();
        $sucursalId = $config?->esPorSucursal() ? $venta->sucursal_id : null;
        $saldoActual = $this->obtenerSaldo($venta->cliente_id, $sucursalId);

        // El contraasiento de la acumulación restaría los puntos ganados.
        // Si el cliente ya canjeó puntos de esta venta, el saldo podría quedar negativo.
        $puntosARevertir = $venta->puntos_ganados;

        // También se devolverían los puntos canjeados en esta venta
        $puntosCanjeadosEnVenta = $venta->puntos_usados;

        // Saldo resultante = actual - acumulados_revertidos + canjeados_devueltos
        $saldoResultante = $saldoActual - $puntosARevertir + $puntosCanjeadosEnVenta;

        return $saldoResultante >= 0;
    }

    /**
     * Ajuste manual de puntos (positivo o negativo).
     * Requiere permiso func.puntos.ajuste_manual.
     */
    public function ajustarPuntos(
        int $clienteId,
        int $sucursalId,
        int $puntos,
        string $concepto,
        int $usuarioId,
        ?string $observaciones = null
    ): MovimientoPunto {
        return DB::connection('pymes_tenant')->transaction(function () use ($clienteId, $sucursalId, $puntos, $concepto, $usuarioId, $observaciones) {
            if ($puntos === 0) {
                throw new Exception('El ajuste debe ser diferente de cero');
            }

            // Validar que no deje saldo negativo
            if ($puntos < 0) {
                $saldoActual = $this->obtenerSaldo($clienteId);
                if ($saldoActual + $puntos < 0) {
                    throw new Exception("El ajuste dejaría el saldo en negativo. Saldo actual: {$saldoActual}, Ajuste: {$puntos}");
                }
            }

            $movimiento = MovimientoPunto::crearMovimientoAjusteManual(
                $clienteId,
                $sucursalId,
                $puntos,
                $concepto,
                $observaciones,
                $usuarioId
            );

            $this->actualizarCacheCliente($clienteId);

            Log::info('Ajuste manual de puntos', [
                'cliente_id' => $clienteId,
                'puntos' => $puntos,
                'concepto' => $concepto,
                'usuario_id' => $usuarioId,
            ]);

            return $movimiento;
        });
    }

    /**
     * Obtiene el saldo actual de puntos de un cliente.
     * Calcula sumando todos los movimientos activos (patrón ledger).
     */
    public function obtenerSaldo(int $clienteId, ?int $sucursalId = null): int
    {
        return MovimientoPunto::calcularSaldo($clienteId, $sucursalId);
    }

    /**
     * Recalcula y actualiza los campos cache de puntos en la tabla clientes.
     * Usa lockForUpdate para evitar race conditions (patrón CuentaCorrienteService).
     */
    public function actualizarCacheCliente(int $clienteId): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($clienteId) {
            $cliente = Cliente::lockForUpdate()->find($clienteId);

            if (! $cliente) {
                return;
            }

            $totales = MovimientoPunto::calcularTotales($clienteId);

            $cliente->update([
                'puntos_acumulados_cache' => $totales['acumulados'],
                'puntos_canjeados_cache' => $totales['canjeados'],
                'puntos_saldo_cache' => $totales['saldo'],
                'ultimo_movimiento_puntos_at' => now(),
            ]);
        });
    }

    /**
     * Aplica el redondeo configurado.
     */
    private function redondear(float $valor, string $modo): int
    {
        return match ($modo) {
            'floor' => (int) floor($valor),
            'ceil' => (int) ceil($valor),
            'round' => (int) round($valor),
            default => (int) floor($valor),
        };
    }
}
