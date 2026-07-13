<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorrienteProveedor;
use App\Models\MovimientoTesoreria;
use App\Models\PagoProveedor;
use App\Models\PagoProveedorCompra;
use App\Models\PagoProveedorPago;
use App\Models\Proveedor;
use App\Models\Tesoreria;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pagos a proveedores (RF-19, D12/D14/D16) — espejo de CobroService.
 *
 * TODO pago pasa por acá (incluido el pago inicial al confirmar una compra):
 * orden de pago + aplicaciones a compras (parcial, a varias, FIFO o manual) +
 * desglose de formas de pago con ORIGEN de fondos por renglón (D14):
 *   caja (default; valida abierta + saldo de efectivo) / efectivo de
 *   Tesorería (egreso externo) / cuenta de empresa (egreso automático).
 *
 * El permiso `func.compras.pagar_avanzado` (orígenes ≠ caja activa) se
 * autoriza en el COMPONENTE (convención del proyecto: el service es
 * API-first y corre sin sesión); acá se validan los saldos de cada origen.
 *
 * Anulación (D16): contraasientos de ledger + reversa por origen; el bloqueo
 * por turno cerrado aplica SOLO a renglones de caja con cierre_turno_id.
 */
class PagoProveedorService
{
    public function __construct(
        protected CuentaCorrienteProveedorService $ccService,
    ) {}

    /**
     * Registra una orden de pago completa.
     *
     * @param  array  $data  {sucursal_id, proveedor_id, usuario_id, caja_id?,
     *                       saldo_favor_usado?, observaciones?, fecha?}
     * @param  array  $comprasAAplicar  [{compra_id, monto_aplicado}]
     * @param  array  $pagos  [{forma_pago_id, monto, origen?, caja_id?, cuenta_empresa_id?}]
     */
    public function registrarPago(array $data, array $comprasAAplicar, array $pagos): PagoProveedor
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($data, $comprasAAplicar, $pagos) {
            $usuarioId = (int) $data['usuario_id'];
            $proveedor = Proveedor::findOrFail($data['proveedor_id']);

            $tipo = empty($comprasAAplicar) ? 'anticipo' : 'pago';

            $totalAplicado = round(collect($comprasAAplicar)->sum('monto_aplicado'), 2);
            $totalPagos = round(collect($pagos)->sum('monto'), 2);
            $saldoFavorUsado = round((float) ($data['saldo_favor_usado'] ?? 0), 2);

            if ($totalPagos <= 0 && $saldoFavorUsado <= 0) {
                throw new Exception(__('El pago no tiene fondos: cargá al menos una forma de pago o usá saldo a favor'));
            }

            if ($saldoFavorUsado > 0) {
                $disponible = MovimientoCuentaCorrienteProveedor::calcularSaldoFavor($proveedor->id);

                if ($saldoFavorUsado > round($disponible, 2)) {
                    throw new Exception(__('El saldo a favor a usar excede el disponible (:disponible)', [
                        'disponible' => number_format($disponible, 2, ',', '.'),
                    ]));
                }
            }

            $fondos = round($totalPagos + $saldoFavorUsado, 2);

            if ($tipo === 'pago' && $fondos + 0.001 < $totalAplicado) {
                throw new Exception(__('Los fondos del pago (:fondos) no cubren el total aplicado a compras (:aplicado)', [
                    'fondos' => number_format($fondos, 2, ',', '.'),
                    'aplicado' => number_format($totalAplicado, 2, ',', '.'),
                ]));
            }

            // Excedente pagado de más → saldo a favor nuestro; anticipo = todo.
            $montoAFavor = $tipo === 'anticipo' ? $fondos : round($fondos - $totalAplicado, 2);

            $pagoProveedor = PagoProveedor::create([
                'numero' => $this->generarNumeroOrdenPago((int) $data['sucursal_id']),
                'proveedor_id' => $proveedor->id,
                'sucursal_id' => $data['sucursal_id'],
                'caja_id' => $data['caja_id'] ?? null,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'monto_total' => $totalPagos,
                'saldo_favor_usado' => $saldoFavorUsado,
                'monto_a_favor' => max(0, $montoAFavor),
                'tipo' => $tipo,
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'activo',
                'usuario_id' => $usuarioId,
            ]);

            $aplicaciones = $this->aplicarACompras($pagoProveedor, $comprasAAplicar);
            $this->registrarDesglose($pagoProveedor, $pagos, $usuarioId);

            $this->ccService->registrarMovimientosPago($pagoProveedor, $aplicaciones, $usuarioId);

            Log::info('Pago a proveedor registrado', [
                'pago_proveedor_id' => $pagoProveedor->id,
                'numero' => $pagoProveedor->numero,
                'tipo' => $tipo,
                'monto_total' => $totalPagos,
                'aplicado' => $totalAplicado,
            ]);

            return $pagoProveedor->fresh(['compras', 'pagos']);
        });
    }

    /**
     * Anticipo puro (sin compras aplicadas): genera saldo a favor nuestro.
     */
    public function registrarAnticipo(array $data, array $pagos): PagoProveedor
    {
        return $this->registrarPago($data, [], $pagos);
    }

    /**
     * Anula una orden de pago: contraasientos de ledger + reversa por ORIGEN
     * (caja / tesorería / cuenta de empresa) + restaura saldo_pendiente.
     *
     * D16: el bloqueo por turno cerrado aplica SOLO si algún renglón de caja
     * tiene cierre_turno_id — una OP 100% tesorería/cuenta se anula siempre.
     */
    public function anularPago(int $pagoProveedorId, string $motivo, int $usuarioId): array
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($pagoProveedorId, $motivo, $usuarioId) {
            $pago = PagoProveedor::with(['compras.compra', 'pagos'])->lockForUpdate()->findOrFail($pagoProveedorId);

            if ($pago->estaAnulado()) {
                throw new Exception(__('La orden de pago ya está anulada'));
            }

            $renglonCajaCerrado = $pago->pagos->first(
                fn (PagoProveedorPago $p) => $p->origen === PagoProveedorPago::ORIGEN_CAJA && $p->cierre_turno_id !== null
            );

            if ($renglonCajaCerrado !== null) {
                throw new Exception(__('No se puede anular: un renglón de caja ya fue cerrado en un turno (D16)'));
            }

            // Ledger (incluye ajuste por anticipo ya consumido).
            $resultadoCC = $this->ccService->anularMovimientosPago($pago, $motivo, $usuarioId);

            // Restaurar saldo pendiente de las compras aplicadas.
            foreach ($pago->compras as $aplicacion) {
                $compra = $aplicacion->compra;

                if ($compra !== null) {
                    $compra->update([
                        'saldo_pendiente' => round((float) $compra->saldo_pendiente + (float) $aplicacion->monto_aplicado, 2),
                    ]);
                }
            }

            // Reversa por origen de fondos (el saldo vuelve a su origen).
            foreach ($pago->pagos as $renglon) {
                $this->revertirRenglon($pago, $renglon, $motivo, $usuarioId);
                $renglon->update(['estado' => 'anulado']);
            }

            $pago->update([
                'estado' => 'anulado',
                'motivo_anulacion' => $motivo,
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
            ]);

            Log::info('Pago a proveedor anulado', ['pago_proveedor_id' => $pago->id, 'motivo' => $motivo]);

            return [
                'pago' => $pago->fresh(),
                'deuda_generada' => $resultadoCC['deuda_generada'],
            ];
        });
    }

    /**
     * Distribuye un monto entre compras pendientes por FIFO (RF-19).
     *
     * @param  Collection  $comprasPendientes  salida de obtenerComprasPendientes()
     */
    public function distribuirMontoFIFO(float $monto, Collection $comprasPendientes): array
    {
        $distribucion = [];
        $restante = $monto;

        foreach ($comprasPendientes as $compra) {
            if ($restante <= 0) {
                break;
            }

            $aplicar = min($restante, (float) $compra['saldo_pendiente']);

            $distribucion[] = [
                'compra_id' => $compra['compra_id'],
                'numero' => $compra['numero'],
                'saldo_pendiente' => (float) $compra['saldo_pendiente'],
                'monto_aplicado' => round($aplicar, 2),
            ];

            $restante = round($restante - $aplicar, 2);
        }

        return $distribucion;
    }

    /**
     * OP-{suc}-{8 dígitos} (patrón generarNumeroRecibo de cobros).
     */
    public function generarNumeroOrdenPago(int $sucursalId): string
    {
        $prefijo = 'OP-'.str_pad((string) $sucursalId, 2, '0', STR_PAD_LEFT).'-';

        $ultimo = PagoProveedor::where('sucursal_id', $sucursalId)
            ->where('numero', 'like', $prefijo.'%')
            ->orderByDesc('id')
            ->value('numero');

        $numero = $ultimo !== null ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return $prefijo.str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    // ==================== Internos ====================

    /**
     * @return array<PagoProveedorCompra>
     */
    private function aplicarACompras(PagoProveedor $pago, array $comprasAAplicar): array
    {
        $aplicaciones = [];

        foreach ($comprasAAplicar as $item) {
            $monto = round((float) ($item['monto_aplicado'] ?? 0), 2);

            if ($monto <= 0) {
                continue;
            }

            $compra = Compra::lockForUpdate()->findOrFail($item['compra_id']);

            if (! $compra->estaCompletada()) {
                throw new Exception(__('Solo se pueden pagar compras completadas (:numero)', ['numero' => $compra->numero_comprobante]));
            }

            if ($compra->proveedor_id !== $pago->proveedor_id) {
                throw new Exception(__('La compra :numero no es del proveedor del pago', ['numero' => $compra->numero_comprobante]));
            }

            $saldoAnterior = (float) $compra->saldo_pendiente;

            if ($monto > round($saldoAnterior + 0.001, 2)) {
                throw new Exception(__('El monto aplicado a :numero excede su saldo pendiente', ['numero' => $compra->numero_comprobante]));
            }

            $saldoPosterior = round($saldoAnterior - $monto, 2);

            $aplicaciones[] = PagoProveedorCompra::create([
                'pago_proveedor_id' => $pago->id,
                'compra_id' => $compra->id,
                'monto_aplicado' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
            ]);

            $compra->update(['saldo_pendiente' => $saldoPosterior]);
        }

        return $aplicaciones;
    }

    /**
     * Desglose de formas de pago: cada renglón egresa de SU origen (D14) y
     * guarda la FK del movimiento generado (contraasiento exacto al anular).
     */
    private function registrarDesglose(PagoProveedor $pago, array $pagos, int $usuarioId): void
    {
        foreach ($pagos as $item) {
            $monto = round((float) ($item['monto'] ?? 0), 2);

            if ($monto <= 0) {
                continue;
            }

            $origen = $item['origen'] ?? PagoProveedorPago::ORIGEN_CAJA;
            $formaPago = FormaPago::findOrFail($item['forma_pago_id']);

            $renglon = PagoProveedorPago::create([
                'pago_proveedor_id' => $pago->id,
                'forma_pago_id' => $formaPago->id,
                'monto' => $monto,
                'origen' => $origen,
                'caja_id' => $origen === PagoProveedorPago::ORIGEN_CAJA ? ($item['caja_id'] ?? $pago->caja_id) : null,
                'cuenta_empresa_id' => $origen === PagoProveedorPago::ORIGEN_CUENTA_EMPRESA ? ($item['cuenta_empresa_id'] ?? null) : null,
                'estado' => 'activo',
            ]);

            match ($origen) {
                PagoProveedorPago::ORIGEN_CAJA => $this->egresarDeCaja($pago, $renglon, $formaPago, $monto, $usuarioId),
                PagoProveedorPago::ORIGEN_TESORERIA => $this->egresarDeTesoreria($pago, $renglon, $monto, $usuarioId),
                PagoProveedorPago::ORIGEN_CUENTA_EMPRESA => $this->egresarDeCuentaEmpresa($pago, $renglon, $formaPago, $monto, $usuarioId),
                default => throw new Exception(__('Origen de fondos inválido')),
            };
        }
    }

    private function egresarDeCaja(PagoProveedor $pago, PagoProveedorPago $renglon, FormaPago $formaPago, float $monto, int $usuarioId): void
    {
        if ($renglon->caja_id === null) {
            throw new Exception(__('El pago desde caja requiere una caja'));
        }

        $caja = Caja::lockForUpdate()->findOrFail($renglon->caja_id);

        if (! $caja->estaAbierta()) {
            throw new Exception(__('La caja ":caja" no está abierta', ['caja' => $caja->nombre]));
        }

        // El efectivo de la caja no puede quedar negativo (regla existente).
        if ($formaPago->esEfectivo() && (float) $caja->saldo_actual < $monto) {
            throw new Exception(__('Saldo insuficiente en la caja ":caja" (:saldo disponibles)', [
                'caja' => $caja->nombre,
                'saldo' => number_format((float) $caja->saldo_actual, 2, ',', '.'),
            ]));
        }

        // Movimiento de caja: solo el efectivo mueve el arqueo físico (espejo
        // del criterio de cobros, donde afecta_caja = efectivo). El saldo lo
        // actualiza el caller (convención del proyecto); el contraasiento de
        // la anulación lo restaura solo.
        if ($formaPago->esEfectivo()) {
            $movimiento = MovimientoCaja::crearEgresoPagoProveedor($caja, $pago, $monto, $usuarioId);
            $caja->disminuirSaldo($monto);
            $renglon->update(['movimiento_caja_id' => $movimiento->id]);
        }

        // FP con cuenta de empresa vinculada (transferencia, etc.): egreso en
        // la cuenta, espejo exacto del ingreso automático de cobros.
        if ($formaPago->cuenta_empresa_id) {
            $movCuenta = CuentaEmpresaService::registrarMovimientoAutomatico(
                CuentaEmpresa::findOrFail($formaPago->cuenta_empresa_id),
                'egreso',
                $monto,
                'pago_proveedor',
                'PagoProveedorPago',
                $renglon->id,
                __('Pago a proveedor — OP :numero (:fp)', ['numero' => $pago->numero, 'fp' => $formaPago->nombre]),
                $usuarioId,
                $pago->sucursal_id,
            );
            $renglon->update(['movimiento_cuenta_empresa_id' => $movCuenta->id]);
        }
    }

    private function egresarDeTesoreria(PagoProveedor $pago, PagoProveedorPago $renglon, float $monto, int $usuarioId): void
    {
        $tesoreria = Tesoreria::porSucursal($pago->sucursal_id)->first();

        if ($tesoreria === null) {
            throw new Exception(__('La sucursal no tiene Tesorería configurada'));
        }

        $movimiento = TesoreriaService::registrarEgresoExterno(
            $tesoreria,
            $monto,
            $usuarioId,
            __('Pago a proveedor — OP :numero', ['numero' => $pago->numero]),
            MovimientoTesoreria::REFERENCIA_PAGO_PROVEEDOR,
            $pago->id,
        );

        $renglon->update(['movimiento_tesoreria_id' => $movimiento->id]);
    }

    private function egresarDeCuentaEmpresa(PagoProveedor $pago, PagoProveedorPago $renglon, FormaPago $formaPago, float $monto, int $usuarioId): void
    {
        if ($renglon->cuenta_empresa_id === null) {
            throw new Exception(__('El pago desde cuenta de empresa requiere elegir la cuenta'));
        }

        $cuenta = CuentaEmpresa::lockForUpdate()->findOrFail($renglon->cuenta_empresa_id);

        if ((float) $cuenta->saldo_actual < $monto) {
            throw new Exception(__('Saldo insuficiente en la cuenta ":cuenta" (:saldo disponibles)', [
                'cuenta' => $cuenta->nombre,
                'saldo' => number_format((float) $cuenta->saldo_actual, 2, ',', '.'),
            ]));
        }

        $movimiento = CuentaEmpresaService::registrarMovimientoAutomatico(
            $cuenta,
            'egreso',
            $monto,
            'pago_proveedor',
            'PagoProveedorPago',
            $renglon->id,
            __('Pago a proveedor — OP :numero (:fp)', ['numero' => $pago->numero, 'fp' => $formaPago->nombre]),
            $usuarioId,
            $pago->sucursal_id,
        );

        $renglon->update(['movimiento_cuenta_empresa_id' => $movimiento->id]);
    }

    private function revertirRenglon(PagoProveedor $pago, PagoProveedorPago $renglon, string $motivo, int $usuarioId): void
    {
        if ($renglon->movimiento_caja_id !== null) {
            $movimiento = MovimientoCaja::find($renglon->movimiento_caja_id);

            if ($movimiento !== null) {
                MovimientoCaja::crearContraasiento(
                    $movimiento,
                    $usuarioId,
                    MovimientoCaja::REF_PAGO_PROVEEDOR,
                    $pago->id,
                    __('Anulación pago a proveedor — OP :numero', ['numero' => $pago->numero]),
                );
            }
        }

        if ($renglon->movimiento_tesoreria_id !== null) {
            $tesoreria = Tesoreria::porSucursal($pago->sucursal_id)->first();

            if ($tesoreria !== null) {
                TesoreriaService::registrarIngresoExterno(
                    $tesoreria,
                    (float) $renglon->monto,
                    $usuarioId,
                    __('Anulación pago a proveedor — OP :numero', ['numero' => $pago->numero]),
                    $motivo,
                );
            }
        }

        if ($renglon->movimiento_cuenta_empresa_id !== null) {
            CuentaEmpresaService::revertirMovimiento($renglon->movimiento_cuenta_empresa_id, $motivo, $usuarioId);
        }
    }
}
