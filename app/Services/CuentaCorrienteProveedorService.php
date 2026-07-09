<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta corriente de PROVEEDORES (RF-18, D12) — espejo de
 * CuentaCorrienteService (clientes) con semántica de pasivo:
 * HABER = compra aumenta nuestra deuda, DEBE = pago la reduce.
 *
 * PRINCIPIOS (idénticos a clientes):
 * 1. Los saldos SIEMPRE se calculan sumando movimientos activos.
 * 2. Las anulaciones crean contraasientos (nunca se borra ni se pisa).
 * 3. Todo movimiento tiene trazabilidad al documento origen.
 * 4. El lock se aplica solo al actualizar el cache del proveedor.
 */
class CuentaCorrienteProveedorService
{
    // ==================== REGISTRO ====================

    /**
     * HABER por el total al confirmar la compra de un proveedor con cta cte.
     * El DEBE por lo pagado en el momento lo registra el pago inicial (que se
     * materializa como PagoProveedor — un solo camino de escritura, D12):
     * contado total ⇒ par HABER/DEBE con saldo 0 (extracto completo).
     */
    public function registrarMovimientosCompra(Compra $compra, int $usuarioId): ?MovimientoCuentaCorrienteProveedor
    {
        if (! $compra->proveedor?->tiene_cuenta_corriente) {
            return null;
        }

        $movimiento = MovimientoCuentaCorrienteProveedor::crearMovimientoCompra($compra, $usuarioId);

        $this->actualizarCacheProveedor($compra->proveedor_id);

        return $movimiento;
    }

    /**
     * NC del proveedor (RF-21): DEBE por lo aplicado contra el saldo de la
     * compra origen + saldo a favor NUESTRO por el excedente (o NC suelta).
     * Los montos los resuelve CompraService (que también baja el
     * saldo_pendiente de la origen, tenga o no cta cte el proveedor).
     */
    public function registrarMovimientosNotaCredito(Compra $nc, float $aplicado, float $aFavor, int $usuarioId): ?MovimientoCuentaCorrienteProveedor
    {
        if (! $nc->proveedor?->tiene_cuenta_corriente) {
            return null;
        }

        $movimiento = MovimientoCuentaCorrienteProveedor::crearMovimientoNotaCredito($nc, $aplicado, $aFavor, $usuarioId);

        $this->actualizarCacheProveedor($nc->proveedor_id);

        return $movimiento;
    }

    /**
     * D17 "saldo a favor": la compra se cancela pero la plata pagada salió de
     * verdad — cada DEBE de pago aplicado a la compra se contraasienta y se
     * convierte en saldo a favor nuestro (las OP quedan activas).
     */
    public function convertirPagosASaldoFavor(Compra $compra, string $motivo, int $usuarioId): array
    {
        $movimientos = [];

        $pagosDeuda = MovimientoCuentaCorrienteProveedor::where('compra_id', $compra->id)
            ->whereNotNull('pago_proveedor_id')
            ->where('tipo', MovimientoCuentaCorrienteProveedor::TIPO_PAGO)
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        foreach ($pagosDeuda as $movimientoPago) {
            MovimientoCuentaCorrienteProveedor::crearContraasiento($movimientoPago, $motivo, $usuarioId);

            $movimientos[] = MovimientoCuentaCorrienteProveedor::create([
                'proveedor_id' => $compra->proveedor_id,
                'sucursal_id' => $compra->sucursal_id,
                'fecha' => now()->toDateString(),
                'tipo' => MovimientoCuentaCorrienteProveedor::TIPO_DEVOLUCION_SALDO,
                'saldo_favor_haber' => $movimientoPago->debe,
                'documento_tipo' => MovimientoCuentaCorrienteProveedor::DOC_AJUSTE,
                'documento_id' => $compra->id,
                'compra_id' => $compra->id,
                'pago_proveedor_id' => $movimientoPago->pago_proveedor_id,
                'concepto' => __('Pago convertido en saldo a favor por cancelación de compra :numero', [
                    'numero' => $compra->numero_comprobante,
                ]),
                'observaciones' => $motivo,
                'usuario_id' => $usuarioId,
            ]);
        }

        $this->actualizarCacheProveedor($compra->proveedor_id);

        return $movimientos;
    }

    /**
     * Movimientos de una orden de pago: uso de saldo a favor + DEBE por cada
     * compra aplicada + anticipo/excedente a favor.
     *
     * @param  array  $aplicaciones  PagoProveedorCompra creados
     */
    public function registrarMovimientosPago(PagoProveedor $pago, array $aplicaciones, int $usuarioId): array
    {
        // Proveedor SIN cta cte (RF-19): el contado genera igual el
        // PagoProveedor + egresos (rastro auditable); solo se omite el ledger.
        if (! $pago->proveedor?->tiene_cuenta_corriente) {
            return [];
        }

        $movimientos = [];

        if ((float) $pago->saldo_favor_usado > 0) {
            $movimientos[] = MovimientoCuentaCorrienteProveedor::crearMovimientoUsoSaldoFavor(
                $pago,
                (float) $pago->saldo_favor_usado,
                $usuarioId,
            );
        }

        foreach ($aplicaciones as $aplicacion) {
            if ((float) $aplicacion->monto_aplicado > 0) {
                $movimientos[] = MovimientoCuentaCorrienteProveedor::crearMovimientoPago($pago, $aplicacion, $usuarioId);
            }
        }

        if ((float) $pago->monto_a_favor > 0) {
            $movimientos[] = MovimientoCuentaCorrienteProveedor::crearMovimientoAnticipo(
                $pago,
                (float) $pago->monto_a_favor,
                $usuarioId,
            );
        }

        $this->actualizarCacheProveedor($pago->proveedor_id);

        return $movimientos;
    }

    // ==================== ANULACIONES ====================

    /**
     * Contraasienta los movimientos de una compra (o NC) al cancelarla.
     */
    public function anularMovimientosCompra(Compra $compra, string $motivo, int $usuarioId): array
    {
        $contraasientos = [];

        $movimientos = MovimientoCuentaCorrienteProveedor::where('compra_id', $compra->id)
            ->whereNull('pago_proveedor_id') // los de pagos se anulan con su OP
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        foreach ($movimientos as $movimiento) {
            $contraasientos[] = MovimientoCuentaCorrienteProveedor::crearContraasiento($movimiento, $motivo, $usuarioId);
        }

        $this->actualizarCacheProveedor($compra->proveedor_id);

        return $contraasientos;
    }

    /**
     * Contraasienta los movimientos de una orden de pago. Si el pago generó
     * saldo a favor que YA fue consumido, ese consumo se convierte en deuda
     * (ajuste crédito — espejo del caso clientes en pasivo).
     *
     * @return array{contraasientos: array, deuda_generada: float}
     */
    public function anularMovimientosPago(PagoProveedor $pago, string $motivo, int $usuarioId): array
    {
        $contraasientos = [];
        $deudaGenerada = 0.0;

        $movimientos = MovimientoCuentaCorrienteProveedor::where('pago_proveedor_id', $pago->id)
            ->where('estado', 'activo')
            ->whereNull('anulado_por_movimiento_id')
            ->get();

        if ((float) $pago->monto_a_favor > 0) {
            $saldoFavorActual = MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($pago->proveedor_id);
            $consumido = max(0, (float) $pago->monto_a_favor - $saldoFavorActual);

            if ($consumido > 0) {
                $deudaGenerada = $consumido;

                MovimientoCuentaCorrienteProveedor::create([
                    'proveedor_id' => $pago->proveedor_id,
                    'sucursal_id' => $pago->sucursal_id,
                    'fecha' => now()->toDateString(),
                    'tipo' => MovimientoCuentaCorrienteProveedor::TIPO_AJUSTE_CREDITO,
                    'haber' => $deudaGenerada, // pasivo: aumenta nuestra deuda
                    'documento_tipo' => MovimientoCuentaCorrienteProveedor::DOC_PAGO,
                    'documento_id' => $pago->id,
                    'pago_proveedor_id' => $pago->id,
                    'concepto' => __('Deuda por anulación de anticipo ya consumido — OP :numero', ['numero' => $pago->numero]),
                    'observaciones' => $motivo,
                    'usuario_id' => $usuarioId,
                ]);
            }
        }

        foreach ($movimientos as $movimiento) {
            $contraasientos[] = MovimientoCuentaCorrienteProveedor::crearContraasiento($movimiento, $motivo, $usuarioId);
        }

        $this->actualizarCacheProveedor($pago->proveedor_id);

        return [
            'contraasientos' => $contraasientos,
            'deuda_generada' => $deudaGenerada,
        ];
    }

    // ==================== CONSULTAS ====================

    /**
     * Extracto con saldo acumulado en memoria (más reciente primero) —
     * espejo de obtenerExtractoResumido de clientes.
     */
    public function obtenerExtractoResumido(int $proveedorId, int $sucursalId, int $limite = 50): Collection
    {
        $movimientos = MovimientoCuentaCorrienteProveedor::where('proveedor_id', $proveedorId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activo')
            ->with(['compra:id,numero_comprobante,numero_comprobante_proveedor', 'pagoProveedor:id,numero'])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $saldoDeuda = 0.0;
        $saldoFavor = 0.0;

        $conSaldo = $movimientos->map(function ($mov) use (&$saldoDeuda, &$saldoFavor) {
            $saldoDeuda += (float) $mov->haber - (float) $mov->debe;
            $saldoFavor += (float) $mov->saldo_favor_haber - (float) $mov->saldo_favor_debe;

            return [
                'id' => $mov->id,
                'fecha' => $mov->fecha,
                'hora' => $mov->created_at?->format('H:i'),
                'created_at' => $mov->created_at,
                'tipo' => $mov->tipo,
                'concepto' => $mov->concepto,
                'debe' => (float) $mov->debe,
                'haber' => (float) $mov->haber,
                'saldo_deuda' => round($saldoDeuda, 2),
                'saldo_favor' => round($saldoFavor, 2),
                'compra_id' => $mov->compra_id,
                'compra_numero' => $mov->compra?->numero_comprobante,
                'pago_proveedor_id' => $mov->pago_proveedor_id,
                'pago_numero' => $mov->pagoProveedor?->numero,
                'es_anulacion' => str_contains($mov->tipo ?? '', 'anulacion'),
                'anulado_por_movimiento_id' => $mov->anulado_por_movimiento_id,
            ];
        });

        return $conSaldo->reverse()->take($limite)->values();
    }

    public function obtenerSaldos(int $proveedorId, int $sucursalId): array
    {
        return MovimientoCuentaCorrienteProveedor::obtenerSaldos($proveedorId, $sucursalId);
    }

    public function obtenerSaldosGlobales(int $proveedorId): array
    {
        return MovimientoCuentaCorrienteProveedor::obtenerSaldosGlobales($proveedorId);
    }

    /**
     * Compras con saldo pendiente del proveedor en la SUCURSAL ACTIVA (D19),
     * FIFO por fecha de vencimiento y luego fecha (RF-19).
     */
    public function obtenerComprasPendientes(int $proveedorId, int $sucursalId): Collection
    {
        return Compra::completadas()
            ->where('proveedor_id', $proveedorId)
            ->where('sucursal_id', $sucursalId)
            ->where('saldo_pendiente', '>', 0)
            ->orderByRaw('COALESCE(fecha_vencimiento, fecha) asc')
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (Compra $compra) => [
                'compra_id' => $compra->id,
                'numero' => $compra->numero_comprobante,
                'numero_proveedor' => $compra->numero_comprobante_proveedor,
                'tipo_comprobante' => $compra->tipo_comprobante,
                'fecha' => $compra->fecha,
                'fecha_vencimiento' => $compra->fecha_vencimiento,
                'total' => (float) $compra->total,
                'saldo_pendiente' => (float) $compra->saldo_pendiente,
                'dias_vencida' => $compra->fecha_vencimiento && now()->startOfDay()->gt($compra->fecha_vencimiento)
                    ? (int) Carbon::parse($compra->fecha_vencimiento)->diffInDays(now()->startOfDay())
                    : 0,
            ]);
    }

    // ==================== CACHE ====================

    /**
     * saldo_cache global del proveedor (informativo, D19) con lockForUpdate.
     */
    public function actualizarCacheProveedor(int $proveedorId): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($proveedorId) {
            $proveedor = Proveedor::lockForUpdate()->find($proveedorId);

            if (! $proveedor) {
                return;
            }

            $saldos = MovimientoCuentaCorrienteProveedor::obtenerSaldosGlobales($proveedorId);

            $proveedor->update([
                'saldo_cache' => $saldos['saldo_deuda'],
                'ultimo_movimiento_ccp_at' => now(),
            ]);
        });
    }
}
