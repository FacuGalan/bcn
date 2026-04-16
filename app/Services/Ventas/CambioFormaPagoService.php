<?php

namespace App\Services\Ventas;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ComprobanteFiscal;
use App\Models\FormaPago;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\VentaPagoAjuste;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Services\CuentaCorrienteService;
use App\Services\CuentaEmpresaService;
use App\Services\VentaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service: Cambio de Forma de Pago en Ventas Registradas
 *
 * Maneja las operaciones de ajuste de pagos sobre ventas ya registradas:
 * - Cambiar la forma de pago de un venta_pago (anular + crear)
 * - Agregar un nuevo venta_pago a una venta existente
 * - Eliminar (anular) un venta_pago sin reemplazo
 *
 * Arquitectura: append-only ledger — NO se modifican registros existentes.
 * Los venta_pagos se marcan como anulados y se crean nuevos registros.
 *
 * Gestión fiscal (regla binaria, 2026-04-16):
 * - Si monto_facturado_viejo == monto_facturado_nuevo → nada fiscal (aunque cambie la FP).
 * - Si son distintos → NC por monto_facturado del pago viejo + FC nueva por monto_facturado_nuevo.
 *
 * La operación se divide en 2 fases para tolerar fallos ARCA:
 * - Fase A (atómica): anular + crear pagos nuevos + reversiones contables + NC. Si falla → rollback total.
 * - Fase B (post-commit): emitir FC nueva. Si falla → pagos quedan en 'pendiente_de_facturar'
 *   y se pueden reintentar desde el reporte de pagos pendientes.
 */
class CambioFormaPagoService
{
    /**
     * Cambia la forma de pago de un venta_pago dividiéndolo en N pagos nuevos (mixto).
     * La suma de monto_final de los pagos nuevos DEBE ser igual al monto_final del pago viejo
     * (tolerancia ±0.01) — el total de la venta NO cambia.
     *
     * Cada pago nuevo lleva un flag `facturar` (bool). La emisión de NC/FC se decide
     * comparando el monto facturado viejo vs el monto facturado de los pagos nuevos.
     *
     * @param  int  $ventaPagoId  ID del pago a reemplazar
     * @param  array  $pagosNuevos  Array de pagos nuevos: [['forma_pago_id', 'monto_base', 'aplicar_ajuste', 'cuotas', 'recargo_cuotas_porcentaje', 'facturar', 'referencia', 'observaciones'], ...]
     * @param  string  $motivo  Motivo obligatorio (mín. 10 chars)
     * @param  int  $usuarioId  Usuario que realiza la operación
     * @param  array  $opcionesFiscales  ['emitir_nc' => bool|null, 'emitir_fc_nueva' => bool|null]
     * @return array ['venta_pago_anulado' => VentaPago, 'venta_pagos_nuevos' => array<VentaPago>, 'ajuste' => VentaPagoAjuste, 'nc_emitida' => ?ComprobanteFiscal, 'fc_nueva' => ?ComprobanteFiscal, 'fc_nueva_error' => ?string]
     *
     * @throws Exception
     */
    public function cambiarFormaPago(
        int $ventaPagoId,
        array $pagosNuevos,
        string $motivo,
        int $usuarioId,
        array $opcionesFiscales = []
    ): array {
        $this->validarMotivo($motivo);

        if (empty($pagosNuevos)) {
            throw new Exception(__('Debe agregar al menos un pago nuevo'));
        }

        // ── FASE A (atómica) ──
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            /** @var VentaPago $pagoViejo */
            $pagoViejo = VentaPago::with(['venta', 'formaPago', 'comprobanteFiscal'])
                ->findOrFail($ventaPagoId);

            $validacion = $this->puedeModificarVentaPago($ventaPagoId, $usuarioId);
            if (! $validacion['puede']) {
                throw new Exception($validacion['razon']);
            }

            $venta = $pagoViejo->venta;

            // Calcular monto_final de cada pago nuevo (con/sin ajuste FP) y validar suma
            $pagosCalculados = $this->calcularPagosNuevos($pagosNuevos);
            $sumaNueva = array_sum(array_column($pagosCalculados, 'monto_final'));
            $montoViejo = (float) $pagoViejo->monto_final;

            if (abs($sumaNueva - $montoViejo) > 0.01) {
                throw new Exception(__(
                    'La suma de los pagos nuevos (:nuevo) debe ser igual al monto del pago a modificar (:viejo)',
                    ['nuevo' => '$'.number_format($sumaNueva, 2, ',', '.'), 'viejo' => '$'.number_format($montoViejo, 2, ',', '.')]
                ));
            }

            // Calcular fiscalidad: comparar monto facturado viejo vs nuevo
            $montoFacturadoViejo = $pagoViejo->comprobante_fiscal_id ? $montoViejo : 0;
            $montoFacturadoNuevo = array_sum(array_map(
                fn ($p) => ($p['facturar'] ?? false) ? $p['monto_final'] : 0,
                $pagosCalculados
            ));
            $deltaFacturado = round($montoFacturadoNuevo - $montoFacturadoViejo, 2);

            $matriz = $this->calcularFiscalidadDesdeDelta(
                $pagoViejo,
                $deltaFacturado,
                $montoFacturadoViejo,
                $montoFacturadoNuevo
            );

            $this->validarOpcionesFiscales($matriz, $opcionesFiscales, $usuarioId);

            $snapshot = $this->snapshotPago($pagoViejo);

            $turnoOriginalId = $pagoViejo->cierre_turno_id;
            $esPostCierre = $turnoOriginalId !== null;

            // 1. Anular pago viejo y revertir movimientos vinculados
            $this->revertirMovimientosVentaPago($pagoViejo, $usuarioId, $motivo);

            $pagoViejo->update([
                'estado' => VentaPago::ESTADO_ANULADO,
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
                'datos_snapshot_json' => $snapshot,
            ]);

            // 2. Emitir NC si corresponde (delta facturado negativo y/o decisión usuario)
            $ncEmitida = null;
            if ($this->debeEmitirNC($matriz, $opcionesFiscales) && $pagoViejo->comprobante_fiscal_id) {
                $ncEmitida = $this->emitirNotaCredito($pagoViejo, $venta, $motivo, $usuarioId);
                $pagoViejo->update(['nota_credito_generada_id' => $ncEmitida->id]);
            }

            // 3. Crear los pagos nuevos
            $pagosCreados = [];
            foreach ($pagosCalculados as $datos) {
                $pagosCreados[] = $this->crearNuevoVentaPago(
                    $venta,
                    $datos,
                    $usuarioId,
                    VentaPago::ORIGEN_CAMBIO_PAGO,
                    $pagoViejo->id
                );
            }

            // 3.1. Si la regla binaria dice "nada fiscal" (mismo monto facturado) y el pago viejo
            // era fiscal, los pagos nuevos facturables heredan la FC del viejo (no hace falta emitir).
            if (
                ! $this->debeEmitirFcNueva($matriz, $opcionesFiscales)
                && $pagoViejo->comprobante_fiscal_id
                && (float) $pagoViejo->monto_facturado > 0
            ) {
                foreach ($pagosCreados as $pn) {
                    if ($pn->estado_facturacion === VentaPago::ESTADO_FACT_PENDIENTE) {
                        $pn->update([
                            'comprobante_fiscal_id' => $pagoViejo->comprobante_fiscal_id,
                            'monto_facturado' => (float) $pn->monto_final,
                            'comprobante_fiscal_nuevo_id' => $pagoViejo->comprobante_fiscal_id,
                            'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
                        ]);
                    }
                }
            }

            // 4. Actualizar solo los flags de CC (total/ajuste_forma_pago NO cambian
            // porque la regla del pivot mixto garantiza suma nuevos == monto viejo)
            $this->actualizarFlagsCCVenta($venta);

            // 5. Audit log: usar el primer pago nuevo como representativo en el row
            $ajuste = $this->registrarAjuste([
                'venta' => $venta,
                'tipo_operacion' => VentaPagoAjuste::TIPO_CAMBIO,
                'pago_anulado' => $pagoViejo,
                'pago_nuevo' => $pagosCreados[0] ?? null,
                'pagos_nuevos_todos' => $pagosCreados,
                'matriz' => $matriz,
                'opciones_fiscales' => $opcionesFiscales,
                'nc_emitida' => $ncEmitida,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'turno_original_id' => $turnoOriginalId,
                'es_post_cierre' => $esPostCierre,
            ]);

            DB::connection('pymes_tenant')->commit();

            // 6. Registrar CC para los pagos nuevos que sean cuenta corriente
            $ventaService = new VentaService;
            foreach ($pagosCreados as $pagoNuevo) {
                if ($pagoNuevo->es_cuenta_corriente) {
                    $ventaService->registrarMovimientoCCPago($pagoNuevo, $usuarioId);
                }
            }

            Log::info('Cambio de forma de pago ejecutado (mixto) — Fase A OK', [
                'venta_id' => $venta->id,
                'venta_pago_anulado_id' => $pagoViejo->id,
                'venta_pagos_nuevos_ids' => array_map(fn ($p) => $p->id, $pagosCreados),
                'ajuste_id' => $ajuste->id,
                'usuario_id' => $usuarioId,
                'nc_emitida' => $ncEmitida?->id,
                'es_post_cierre' => $esPostCierre,
                'monto_facturado_delta' => $deltaFacturado,
            ]);
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cambiar forma de pago (Fase A)', [
                'venta_pago_id' => $ventaPagoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        // ── FASE B (post-commit): emitir FC nueva si corresponde ──
        $fcNueva = null;
        $fcNuevaError = null;

        if ($this->debeEmitirFcNueva($matriz, $opcionesFiscales)) {
            $resultado = $this->emitirFcNuevaPostCommit($venta, $pagosCreados, $ajuste, $usuarioId);
            $fcNueva = $resultado['fc_nueva'];
            $fcNuevaError = $resultado['error'];
        }

        return [
            'venta_pago_anulado' => $pagoViejo->fresh(),
            'venta_pagos_nuevos' => array_map(fn ($p) => $p->fresh(), $pagosCreados),
            'ajuste' => $ajuste->fresh(),
            'nc_emitida' => $ncEmitida,
            'fc_nueva' => $fcNueva,
            'fc_nueva_error' => $fcNuevaError,
        ];
    }

    /**
     * Agrega un nuevo venta_pago a una venta existente.
     *
     * @return array ['venta_pago_nuevo' => VentaPago, 'ajuste' => VentaPagoAjuste, 'nc_emitida' => null]
     *
     * @throws Exception
     */
    public function agregarPagoAVenta(
        int $ventaId,
        array $datosNuevoPago,
        string $motivo,
        int $usuarioId,
        array $opcionesFiscales = []
    ): array {
        $this->validarMotivo($motivo);

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            /** @var Venta $venta */
            $venta = Venta::with('pagos')->findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                throw new Exception(__('No se puede modificar una venta cancelada'));
            }

            $pagoNuevo = $this->crearNuevoVentaPago(
                $venta,
                $datosNuevoPago,
                $usuarioId,
                VentaPago::ORIGEN_PAGO_AGREGADO,
                null
            );

            $this->recalcularTotalesVenta($venta);

            $esFacturable = $pagoNuevo->estado_facturacion === VentaPago::ESTADO_FACT_PENDIENTE;
            $auto = (bool) $venta->sucursal?->facturacion_fiscal_automatica;

            $matriz = [
                'delta_total' => true,
                'delta_fiscal' => (bool) $pagoNuevo->formaPago?->factura_fiscal,
                'auto' => $auto,
                'emitir_nc' => false,
                'emitir_fc_nueva' => $esFacturable ? ($auto ? true : 'preguntar') : false,
                'preview_texto' => '',
            ];

            $ajuste = $this->registrarAjuste([
                'venta' => $venta,
                'tipo_operacion' => VentaPagoAjuste::TIPO_AGREGAR,
                'pago_anulado' => null,
                'pago_nuevo' => $pagoNuevo,
                'matriz' => $matriz,
                'opciones_fiscales' => $opcionesFiscales,
                'nc_emitida' => null,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'turno_original_id' => null,
                'es_post_cierre' => false,
            ]);

            DB::connection('pymes_tenant')->commit();

            if ($pagoNuevo->es_cuenta_corriente) {
                $ventaService = new VentaService;
                $ventaService->registrarMovimientoCCPago($pagoNuevo, $usuarioId);
            }

            Log::info('Pago agregado a venta — Fase A OK', [
                'venta_id' => $venta->id,
                'venta_pago_nuevo_id' => $pagoNuevo->id,
                'ajuste_id' => $ajuste->id,
                'usuario_id' => $usuarioId,
            ]);
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al agregar pago a venta (Fase A)', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // ── FASE B: emitir FC nueva si corresponde ──
        $fcNueva = null;
        $fcNuevaError = null;

        if ($this->debeEmitirFcNueva($matriz, $opcionesFiscales)) {
            $resultado = $this->emitirFcNuevaPostCommit($venta, [$pagoNuevo], $ajuste, $usuarioId);
            $fcNueva = $resultado['fc_nueva'];
            $fcNuevaError = $resultado['error'];
        }

        return [
            'venta_pago_nuevo' => $pagoNuevo->fresh(),
            'ajuste' => $ajuste->fresh(),
            'nc_emitida' => null,
            'fc_nueva' => $fcNueva,
            'fc_nueva_error' => $fcNuevaError,
        ];
    }

    /**
     * Elimina (anula) un venta_pago sin reemplazo.
     *
     * @return array ['venta_pago_anulado' => VentaPago, 'ajuste' => VentaPagoAjuste, 'nc_emitida' => ?ComprobanteFiscal]
     *
     * @throws Exception
     */
    public function eliminarPagoDeVenta(
        int $ventaPagoId,
        string $motivo,
        int $usuarioId,
        array $opcionesFiscales = []
    ): array {
        $this->validarMotivo($motivo);

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            /** @var VentaPago $pagoViejo */
            $pagoViejo = VentaPago::with(['venta', 'formaPago', 'comprobanteFiscal'])
                ->findOrFail($ventaPagoId);

            $validacion = $this->puedeModificarVentaPago($ventaPagoId, $usuarioId);
            if (! $validacion['puede']) {
                throw new Exception($validacion['razon']);
            }

            $venta = $pagoViejo->venta;
            $turnoOriginalId = $pagoViejo->cierre_turno_id;
            $esPostCierre = $turnoOriginalId !== null;

            $matriz = [
                'delta_total' => true,
                'delta_fiscal' => (bool) $pagoViejo->formaPago?->factura_fiscal,
                'auto' => (bool) $venta->sucursal?->facturacion_fiscal_automatica,
                'emitir_nc' => $pagoViejo->comprobante_fiscal_id ? true : false,
                'emitir_fc_nueva' => false,
                'preview_texto' => '',
            ];

            $snapshot = $this->snapshotPago($pagoViejo);

            $this->revertirMovimientosVentaPago($pagoViejo, $usuarioId, $motivo);

            $pagoViejo->update([
                'estado' => VentaPago::ESTADO_ANULADO,
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
                'datos_snapshot_json' => $snapshot,
                'operacion_origen' => VentaPago::ORIGEN_ANULACION_SIN_REEMPLAZO,
            ]);

            $ncEmitida = null;
            if ($this->debeEmitirNC($matriz, $opcionesFiscales) && $pagoViejo->comprobante_fiscal_id) {
                $ncEmitida = $this->emitirNotaCredito($pagoViejo, $venta, $motivo, $usuarioId);
                $pagoViejo->update(['nota_credito_generada_id' => $ncEmitida->id]);
            }

            $this->recalcularTotalesVenta($venta);

            $ajuste = $this->registrarAjuste([
                'venta' => $venta,
                'tipo_operacion' => VentaPagoAjuste::TIPO_ELIMINAR,
                'pago_anulado' => $pagoViejo,
                'pago_nuevo' => null,
                'matriz' => $matriz,
                'opciones_fiscales' => $opcionesFiscales,
                'nc_emitida' => $ncEmitida,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'turno_original_id' => $turnoOriginalId,
                'es_post_cierre' => $esPostCierre,
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Pago eliminado de venta', [
                'venta_id' => $venta->id,
                'venta_pago_id' => $pagoViejo->id,
                'ajuste_id' => $ajuste->id,
                'usuario_id' => $usuarioId,
                'nc_emitida' => $ncEmitida?->id,
            ]);

            return [
                'venta_pago_anulado' => $pagoViejo->fresh(),
                'ajuste' => $ajuste,
                'nc_emitida' => $ncEmitida,
                'fc_nueva' => null,
                'fc_nueva_error' => null,
            ];
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al eliminar pago de venta', [
                'venta_pago_id' => $ventaPagoId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Valida si un venta_pago puede ser modificado por el usuario.
     *
     * @return array ['puede' => bool, 'razon' => string|null]
     */
    public function puedeModificarVentaPago(int $ventaPagoId, int $usuarioId): array
    {
        $pago = VentaPago::with('venta')->find($ventaPagoId);

        if (! $pago) {
            return ['puede' => false, 'razon' => __('Pago no encontrado')];
        }

        if ($pago->estado === VentaPago::ESTADO_ANULADO) {
            return ['puede' => false, 'razon' => __('Este pago ya fue anulado')];
        }

        if (! $pago->venta || $pago->venta->estaCancelada()) {
            return ['puede' => false, 'razon' => __('No se puede modificar una venta cancelada')];
        }

        if ($pago->venta->puntos_ganados > 0) {
            $puntosService = new \App\Services\PuntosService;
            if (! $puntosService->validarAnulacionVenta($pago->venta)) {
                return ['puede' => false, 'razon' => __('No se puede modificar: el cliente ya canjeó los puntos ganados en esta venta')];
            }
        }

        $cobrosActivos = $pago->cobrosAplicados()
            ->whereHas('cobro', function ($q) {
                $q->where('estado', '!=', 'anulado');
            })
            ->get();

        if ($cobrosActivos->isNotEmpty()) {
            $monto = $cobrosActivos->sum('monto_aplicado');

            return [
                'puede' => false,
                'razon' => __('Este pago tiene :monto aplicados en :count cobros. Anule los cobros desde Cobranzas del cliente antes de modificar este pago.', [
                    'monto' => '$'.number_format((float) $monto, 2, ',', '.'),
                    'count' => $cobrosActivos->count(),
                ]),
            ];
        }

        if ($pago->cierre_turno_id !== null) {
            $user = \App\Models\User::find($usuarioId);
            if (! $user || ! $user->hasPermissionTo('func.cambiar_forma_pago_turno_cerrado')) {
                return [
                    'puede' => false,
                    'razon' => __('No tenés permiso para modificar pagos de turnos cerrados. Solicitá el permiso a tu administrador.'),
                ];
            }
        }

        $user = \App\Models\User::find($usuarioId);
        if (! $user || ! $user->hasPermissionTo('func.cambiar_forma_pago_venta')) {
            return [
                'puede' => false,
                'razon' => __('No tenés permiso para modificar pagos de ventas'),
            ];
        }

        return ['puede' => true, 'razon' => null];
    }

    /**
     * Calcula el preview de un cambio hipotético (suma de pagos nuevos vs viejo + decisión fiscal).
     * Útil para que la UI muestre en tiempo real el estado del desglose.
     *
     * @param  array  $pagosNuevos  Array de pagos nuevos
     * @return array ['monto_viejo', 'suma_nueva', 'pendiente', 'completo' (bool),
     *               'monto_facturado_viejo', 'monto_facturado_nuevo', 'delta_facturado',
     *               'emitir_nc' (bool|'preguntar'), 'emitir_fc_nueva' (bool|'preguntar'),
     *               'preview_texto']
     */
    public function calcularPreviewCambio(VentaPago $pagoViejo, array $pagosNuevos): array
    {
        $montoViejo = (float) $pagoViejo->monto_final;

        try {
            $pagosCalculados = $this->calcularPagosNuevos($pagosNuevos);
        } catch (Exception $e) {
            $pagosCalculados = [];
        }

        $sumaNueva = array_sum(array_column($pagosCalculados, 'monto_final'));
        $pendiente = round($montoViejo - $sumaNueva, 2);
        $completo = abs($pendiente) <= 0.01;

        $montoFacturadoViejo = $pagoViejo->comprobante_fiscal_id ? $montoViejo : 0;
        $montoFacturadoNuevo = array_sum(array_map(
            fn ($p) => ($p['facturar'] ?? false) ? $p['monto_final'] : 0,
            $pagosCalculados
        ));
        $deltaFacturado = round($montoFacturadoNuevo - $montoFacturadoViejo, 2);

        $matriz = $this->calcularFiscalidadDesdeDelta(
            $pagoViejo,
            $deltaFacturado,
            $montoFacturadoViejo,
            $montoFacturadoNuevo
        );

        return array_merge($matriz, [
            'monto_viejo' => $montoViejo,
            'suma_nueva' => round($sumaNueva, 2),
            'pendiente' => $pendiente,
            'completo' => $completo,
            'monto_facturado_viejo' => round($montoFacturadoViejo, 2),
            'monto_facturado_nuevo' => round($montoFacturadoNuevo, 2),
            'delta_facturado' => $deltaFacturado,
        ]);
    }

    /**
     * Calcula la fiscalidad con regla binaria:
     * - Si monto_facturado_viejo == monto_facturado_nuevo → nada fiscal (aunque cambie la FP).
     * - Si son distintos → NC por el pago viejo + FC nueva por los pagos nuevos facturables.
     *
     * Se emite tanto si el delta es positivo (facturé más) como negativo (facturé menos).
     * Razón: simplifica la auditoría y es más legible para el humano — "cancelé lo viejo,
     * emití lo nuevo". Evita distinguir casos que operativamente son el mismo problema.
     */
    private function calcularFiscalidadDesdeDelta(
        VentaPago $pagoViejo,
        float $deltaFacturado,
        float $montoFacturadoViejo,
        float $montoFacturadoNuevo
    ): array {
        $sucursal = $pagoViejo->venta?->sucursal;
        $auto = (bool) ($sucursal->facturacion_fiscal_automatica ?? false);

        $emitirNC = false;
        $emitirFcNueva = false;
        $texto = '';

        $mismoMonto = abs($deltaFacturado) < 0.01;

        if ($mismoMonto) {
            $texto = __('No se modifica el monto facturado de la venta');
        } else {
            // Monto facturado distinto → NC del viejo (si era fiscal) + FC nuevo (si hay facturable).
            // Siempre se emite cuando corresponde — el usuario ya marcó explícitamente `facturar`
            // por cada pago del desglose, no hace falta preguntar adicionalmente por `auto`.
            if ($pagoViejo->comprobante_fiscal_id && $montoFacturadoViejo > 0) {
                $emitirNC = true;
            }
            if ($montoFacturadoNuevo > 0) {
                $emitirFcNueva = true;
            }

            $textos = [];
            if ($emitirNC) {
                $textos[] = __('Se emitirá Nota de Crédito por :monto', ['monto' => '$'.number_format($montoFacturadoViejo, 2, ',', '.')]);
            }
            if ($emitirFcNueva) {
                $textos[] = __('Se emitirá Factura nueva por :monto', ['monto' => '$'.number_format($montoFacturadoNuevo, 2, ',', '.')]);
            }
            $texto = implode(' + ', $textos);
        }

        return [
            'auto' => $auto,
            'emitir_nc' => $emitirNC,
            'emitir_fc_nueva' => $emitirFcNueva,
            'preview_texto' => $texto,
        ];
    }

    /**
     * Calcula monto_final de cada pago nuevo aplicando ajuste FP y recargo cuotas.
     * Devuelve el array enriquecido con 'monto_final' por item.
     */
    private function calcularPagosNuevos(array $pagosNuevos): array
    {
        $resultado = [];

        foreach ($pagosNuevos as $datos) {
            if (empty($datos['forma_pago_id']) || ! isset($datos['monto_base'])) {
                continue;
            }

            $fp = FormaPago::find($datos['forma_pago_id']);
            if (! $fp) {
                continue;
            }

            $aplicarAjuste = (bool) ($datos['aplicar_ajuste'] ?? ((float) $fp->ajuste_porcentaje !== 0.0));
            $montoBase = (float) $datos['monto_base'];

            $ajusteData = $aplicarAjuste
                ? VentaPago::calcularMontoConAjuste($montoBase, (float) $fp->ajuste_porcentaje)
                : ['monto_base' => $montoBase, 'ajuste_porcentaje' => 0, 'monto_ajuste' => 0, 'monto_final' => $montoBase];

            $cuotas = (int) ($datos['cuotas'] ?? 1);
            $recargoCuotasPct = (float) ($datos['recargo_cuotas_porcentaje'] ?? 0);

            if ($cuotas > 1 && $recargoCuotasPct > 0) {
                $cuotasData = VentaPago::calcularMontoConCuotas($ajusteData['monto_final'], $cuotas, $recargoCuotasPct);
                $ajusteData['monto_final'] = $cuotasData['monto_total'];
            }

            $resultado[] = array_merge($datos, [
                'monto_final' => round($ajusteData['monto_final'], 2),
                'aplicar_ajuste' => $aplicarAjuste,
                'cuotas' => $cuotas > 1 ? $cuotas : 1,
                'recargo_cuotas_porcentaje' => $cuotas > 1 ? $recargoCuotasPct : 0,
                'facturar' => (bool) ($datos['facturar'] ?? (bool) $fp->factura_fiscal),
            ]);
        }

        return $resultado;
    }

    // =========================================
    // MÉTODOS PRIVADOS AUXILIARES
    // =========================================

    private function validarMotivo(string $motivo): void
    {
        if (mb_strlen(trim($motivo)) < 10) {
            throw new Exception(__('El motivo debe tener al menos 10 caracteres'));
        }
    }

    private function validarOpcionesFiscales(array $matriz, array $opciones, int $usuarioId): void
    {
        // Si la matriz dice NC obligatoria (true estricto) y el usuario la saltea, exige permiso
        if ($matriz['emitir_nc'] === true && ($opciones['emitir_nc'] ?? true) === false) {
            $user = \App\Models\User::find($usuarioId);
            if (! $user || ! $user->hasPermissionTo('func.modificar_pagos_sin_nc')) {
                throw new Exception(__('La NC es obligatoria para esta operación. No tenés permiso para saltearla.'));
            }
        }
    }

    private function debeEmitirNC(array $matriz, array $opciones): bool
    {
        $mEmitir = $matriz['emitir_nc'];

        if ($mEmitir === true) {
            // Obligatoria salvo que usuario haya optado por saltearla con permiso (ya validado)
            return ($opciones['emitir_nc'] ?? true) === true;
        }

        if ($mEmitir === 'preguntar') {
            return ($opciones['emitir_nc'] ?? false) === true;
        }

        return false;
    }

    private function debeEmitirFcNueva(array $matriz, array $opciones): bool
    {
        $mEmitir = $matriz['emitir_fc_nueva'];

        if ($mEmitir === true) {
            return ($opciones['emitir_fc_nueva'] ?? true) === true;
        }

        if ($mEmitir === 'preguntar') {
            return ($opciones['emitir_fc_nueva'] ?? false) === true;
        }

        return false;
    }

    /**
     * Construye el desglose de IVA proporcional al monto a facturar parcial.
     * Reproduce la lógica de NuevaVenta::recalcularDesgloseIvaFiscal() para que
     * AFIP no rechace con error 10048 (ImpTotal != ImpNeto + ImpIVA).
     */
    private function calcularDesgloseIvaProporcional(Venta $venta, float $montoAFacturar): array
    {
        $venta->loadMissing('detalles');

        $totalVenta = (float) ($venta->total_final ?? $venta->total ?? 0);
        if ($totalVenta <= 0) {
            return ['por_alicuota' => []];
        }

        $proporcion = $montoAFacturar / $totalVenta;

        // Agrupar detalles por alícuota (neto + iva) sobre total_detalle CON IVA incluido
        $agrupado = [];
        foreach ($venta->detalles as $detalle) {
            $porcentaje = (float) ($detalle->iva_porcentaje ?? 21);
            $totalDet = (float) ($detalle->total ?? $detalle->subtotal ?? 0);
            if ($totalDet <= 0) {
                continue;
            }

            if (! isset($agrupado[(string) $porcentaje])) {
                $agrupado[(string) $porcentaje] = ['neto' => 0, 'iva' => 0];
            }

            $neto = round($totalDet / (1 + $porcentaje / 100), 2);
            $iva = round($totalDet - $neto, 2);
            $agrupado[(string) $porcentaje]['neto'] += $neto;
            $agrupado[(string) $porcentaje]['iva'] += $iva;
        }

        // Aplicar proporción + AFIP exige iva = neto * p/100 exactamente
        $porAlicuota = [];
        $sumaCalculada = 0;
        foreach ($agrupado as $pctStr => $vals) {
            $porcentaje = (float) $pctStr;
            $netoProp = round($vals['neto'] * $proporcion, 2);
            $ivaProp = round($netoProp * ($porcentaje / 100), 2);
            $porAlicuota[] = [
                'alicuota' => $porcentaje,
                'neto' => $netoProp,
                'iva' => $ivaProp,
            ];
            $sumaCalculada += $netoProp + $ivaProp;
        }

        // Ajustar última alícuota por diferencia de redondeo
        $diferencia = round($montoAFacturar - $sumaCalculada, 2);
        if ($diferencia != 0 && ! empty($porAlicuota)) {
            $last = count($porAlicuota) - 1;
            $p = $porAlicuota[$last]['alicuota'];
            $nuevoSubtotal = $porAlicuota[$last]['neto'] + $porAlicuota[$last]['iva'] + $diferencia;
            $nuevoNeto = round($nuevoSubtotal / (1 + $p / 100), 2);
            $nuevoIva = round($nuevoNeto * ($p / 100), 2);
            $porAlicuota[$last]['neto'] = $nuevoNeto;
            $porAlicuota[$last]['iva'] = $nuevoIva;
        }

        return ['por_alicuota' => $porAlicuota];
    }

    /**
     * Fase B: emite la FC nueva sobre los pagos facturables creados en Fase A.
     * Si falla, NO rollbackea Fase A — los pagos quedan en 'pendiente_de_facturar'
     * para reintentar desde el reporte.
     *
     * @param  VentaPago[]  $pagosCreados
     * @return array ['fc_nueva' => ?ComprobanteFiscal, 'error' => ?string]
     */
    private function emitirFcNuevaPostCommit(
        Venta $venta,
        array $pagosCreados,
        VentaPagoAjuste $ajuste,
        int $usuarioId
    ): array {
        $pagosFacturables = array_filter(
            $pagosCreados,
            fn (VentaPago $p) => $p->estado_facturacion === VentaPago::ESTADO_FACT_PENDIENTE
        );

        if (empty($pagosFacturables)) {
            return ['fc_nueva' => null, 'error' => null];
        }

        $pagosFacturar = array_map(
            fn (VentaPago $p) => [
                'id' => $p->id,
                'monto_final' => (float) $p->monto_final,
                'monto_facturado' => (float) $p->monto_final,
            ],
            $pagosFacturables
        );

        $montoAFacturar = array_sum(array_column($pagosFacturar, 'monto_final'));
        $ventaFresh = $venta->fresh(['pagos', 'detalles', 'cliente', 'caja', 'sucursal']);
        $desgloseIva = $this->calcularDesgloseIvaProporcional($ventaFresh, $montoAFacturar);

        try {
            $service = new ComprobanteFiscalService;
            $fc = $service->crearComprobanteFiscal($ventaFresh, [
                'pagos_facturar' => $pagosFacturar,
                'desglose_iva' => $desgloseIva,
                'total_a_facturar' => $montoAFacturar,
            ]);

            // El service ya seteó comprobante_fiscal_id y monto_facturado en los pagos.
            // Solo falta marcar estado_facturacion + comprobante_fiscal_nuevo_id.
            foreach ($pagosFacturables as $pago) {
                $pago->refresh();
                $pago->update([
                    'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
                    'comprobante_fiscal_nuevo_id' => $fc->id,
                ]);
            }

            $ajuste->update([
                'fc_nueva_id' => $fc->id,
                'fc_nueva_flag' => true,
            ]);

            Log::info('FC nueva emitida (Fase B OK)', [
                'venta_id' => $venta->id,
                'fc_nueva_id' => $fc->id,
                'pagos_ids' => array_map(fn ($p) => $p->id, $pagosFacturables),
            ]);

            return ['fc_nueva' => $fc, 'error' => null];
        } catch (Exception $e) {
            // Fase B falló — marcar pagos como pendientes para reintento
            foreach ($pagosFacturables as $pago) {
                try {
                    $pago->update(['estado_facturacion' => VentaPago::ESTADO_FACT_PENDIENTE]);
                } catch (Exception $inner) {
                    Log::error('Error actualizando estado_facturacion tras fallo de FC', [
                        'pago_id' => $pago->id,
                        'error' => $inner->getMessage(),
                    ]);
                }
            }

            Log::warning('FC nueva falló (Fase B) — pagos quedan pendientes de facturar', [
                'venta_id' => $venta->id,
                'pagos_ids' => array_map(fn ($p) => $p->id, $pagosFacturables),
                'error' => $e->getMessage(),
            ]);

            return ['fc_nueva' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reintenta la emisión de FC sobre un pago en estado 'pendiente_de_facturar'.
     * Diseñado para reuso en el futuro módulo general de búsqueda de pendientes.
     *
     * @throws Exception si el pago no está en estado válido o falla la emisión.
     */
    public function reintentarFacturacionPago(VentaPago $pago, int $usuarioId): ComprobanteFiscal
    {
        $pago->refresh();

        if ($pago->estado_facturacion !== VentaPago::ESTADO_FACT_PENDIENTE) {
            throw new Exception(__('Este pago no está en estado pendiente de facturar'));
        }

        $pago->loadMissing(['venta.pagos', 'venta.detalles', 'venta.cliente', 'venta.caja', 'venta.sucursal', 'formaPago']);

        if (! $pago->formaPago || ! $pago->formaPago->factura_fiscal) {
            throw new Exception(__('La forma de pago no es fiscal, no se puede emitir factura'));
        }

        $user = \App\Models\User::find($usuarioId);
        if (! $user || ! $user->hasPermissionTo('func.reintentar_facturacion')) {
            throw new Exception(__('No tenés permiso para reintentar facturación'));
        }

        try {
            $montoAFacturar = (float) $pago->monto_final;
            $desgloseIva = $this->calcularDesgloseIvaProporcional($pago->venta, $montoAFacturar);

            $service = new ComprobanteFiscalService;
            $fc = $service->crearComprobanteFiscal($pago->venta, [
                'pagos_facturar' => [[
                    'id' => $pago->id,
                    'monto_final' => $montoAFacturar,
                    'monto_facturado' => $montoAFacturar,
                ]],
                'desglose_iva' => $desgloseIva,
                'total_a_facturar' => $montoAFacturar,
            ]);

            $pago->refresh();
            $pago->update([
                'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
                'comprobante_fiscal_nuevo_id' => $fc->id,
            ]);

            Log::info('Reintento de facturación exitoso', [
                'pago_id' => $pago->id,
                'fc_id' => $fc->id,
                'usuario_id' => $usuarioId,
            ]);

            return $fc;
        } catch (Exception $e) {
            // Se mantiene en pendiente_de_facturar — el usuario decide si marcar como error
            Log::warning('Reintento de facturación falló — pago sigue pendiente', [
                'pago_id' => $pago->id,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Marca un pago pendiente como error_arca sin reintentar (decisión manual del usuario).
     *
     * @throws Exception
     */
    public function marcarErrorFacturacion(VentaPago $pago, int $usuarioId, string $motivo): void
    {
        $pago->refresh();

        if ($pago->estado_facturacion !== VentaPago::ESTADO_FACT_PENDIENTE) {
            throw new Exception(__('Solo se puede marcar como error un pago pendiente de facturar'));
        }

        $user = \App\Models\User::find($usuarioId);
        if (! $user || ! $user->hasPermissionTo('func.reintentar_facturacion')) {
            throw new Exception(__('No tenés permiso para marcar errores de facturación'));
        }

        $pago->update([
            'estado_facturacion' => VentaPago::ESTADO_FACT_ERROR,
            'observaciones' => trim(($pago->observaciones ?? '')."\n[".now()->toDateTimeString().'] Marcado error ARCA: '.$motivo),
        ]);

        Log::info('Pago marcado manualmente como error_arca', [
            'pago_id' => $pago->id,
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Revierte todos los movimientos vinculados a un venta_pago:
     * MovimientoCaja (y vuelto asociado), MovimientoCuentaEmpresa, MovimientoCuentaCorriente.
     * Los contraasientos van al turno actual (cierre_turno_id = null) para preservar historial.
     */
    private function revertirMovimientosVentaPago(VentaPago $vp, int $usuarioId, string $motivo): void
    {
        $venta = $vp->venta;

        // 1. MovimientoCaja: contraasiento de ingreso
        if ($vp->afecta_caja && $vp->movimiento_caja_id) {
            $movimiento = MovimientoCaja::find($vp->movimiento_caja_id);
            if ($movimiento && $venta?->caja) {
                MovimientoCaja::create([
                    'caja_id' => $venta->caja_id,
                    'tipo' => MovimientoCaja::TIPO_EGRESO,
                    'concepto' => "Anulación pago Venta #{$venta->numero} (cambio de FP)",
                    'monto' => $movimiento->monto,
                    'usuario_id' => $usuarioId,
                    'referencia_tipo' => MovimientoCaja::REF_ANULACION_VENTA,
                    'referencia_id' => $venta->id,
                    'moneda_id' => $movimiento->moneda_id,
                    'tipo_cambio_id' => $movimiento->tipo_cambio_id,
                    'monto_moneda_original' => $movimiento->monto_moneda_original,
                ]);
                $venta->caja->disminuirSaldo($movimiento->monto);
            }
        }

        // 2. MovimientoCuentaEmpresa: contraasiento
        if ($vp->movimiento_cuenta_empresa_id) {
            try {
                CuentaEmpresaService::revertirMovimiento(
                    $vp->movimiento_cuenta_empresa_id,
                    $motivo,
                    $usuarioId
                );
            } catch (Exception $e) {
                Log::warning('Error al revertir movimiento cuenta empresa en cambio de pago', [
                    'venta_pago_id' => $vp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. MovimientoCuentaCorriente: contraasientos
        if ($vp->es_cuenta_corriente) {
            $ccService = new CuentaCorrienteService;
            $ccService->anularMovimientosVentaPago($vp, $usuarioId, $motivo);
        }
    }

    /**
     * Actualiza únicamente los flags derivados de CC de la venta.
     * Úsalo en `cambiarFormaPago` donde total/ajuste NO deben cambiar
     * (regla pivot mixto: suma de pagos nuevos == monto del viejo).
     */
    private function actualizarFlagsCCVenta(Venta $venta): void
    {
        $pagosActivos = VentaPago::where('venta_id', $venta->id)
            ->where('estado', VentaPago::ESTADO_ACTIVO)
            ->get();

        $esCC = $pagosActivos->contains(fn ($p) => (bool) $p->es_cuenta_corriente);
        $saldoPendiente = (float) $pagosActivos->where('es_cuenta_corriente', true)->sum('saldo_pendiente');

        $venta->update([
            'es_cuenta_corriente' => $esCC,
            'saldo_pendiente_cache' => round($saldoPendiente, 2),
        ]);
    }

    /**
     * Recalcula totales derivados de la venta a partir de pagos activos.
     * Úsalo SOLO en agregarPagoAVenta / eliminarPagoDeVenta donde el total sí cambia.
     * NO usar en cambiarFormaPago (ahí el total es inmutable por la regla del pivot mixto).
     */
    private function recalcularTotalesVenta(Venta $venta): void
    {
        $pagosActivos = VentaPago::where('venta_id', $venta->id)
            ->where('estado', VentaPago::ESTADO_ACTIVO)
            ->get();

        $totalFinal = (float) $pagosActivos->sum('monto_final');
        $totalBase = (float) $venta->total;
        $totalAjuste = $totalFinal - $totalBase;
        $esCC = $pagosActivos->contains(fn ($p) => (bool) $p->es_cuenta_corriente);
        $saldoPendiente = (float) $pagosActivos->where('es_cuenta_corriente', true)->sum('saldo_pendiente');

        $totalAnterior = (float) $venta->total_final;

        $venta->update([
            'ajuste_forma_pago' => round($totalAjuste, 2),
            'total_final' => round($totalFinal, 2),
            'es_cuenta_corriente' => $esCC,
            'saldo_pendiente_cache' => round($saldoPendiente, 2),
        ]);

        // Ajustar saldo cliente si hay delta en CC
        if ($esCC && $venta->cliente_id) {
            $delta = round($totalFinal - $totalAnterior, 2);
            if (abs($delta) > 0.009) {
                $cliente = Cliente::find($venta->cliente_id);
                if ($cliente) {
                    $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, $delta);
                }
            }
        }
    }

    /**
     * Crea un nuevo VentaPago aplicando recargo/descuento y cuotas.
     * Genera los movimientos de caja + cuenta empresa si aplica.
     * Setea estado_facturacion inicial según si el pago debe facturarse:
     * - 'pendiente_de_facturar' si facturar=true y FP tiene factura_fiscal
     * - 'no_facturado' en cualquier otro caso
     */
    private function crearNuevoVentaPago(
        Venta $venta,
        array $datos,
        int $usuarioId,
        string $origen,
        ?int $reemplazadoId
    ): VentaPago {
        $fp = FormaPago::with('conceptoPago')->findOrFail($datos['forma_pago_id']);
        $aplicarAjuste = (bool) ($datos['aplicar_ajuste'] ?? ($fp->ajuste_porcentaje != 0));
        $montoBase = (float) $datos['monto_base'];

        $ajusteData = $aplicarAjuste
            ? VentaPago::calcularMontoConAjuste($montoBase, (float) $fp->ajuste_porcentaje)
            : ['monto_base' => $montoBase, 'ajuste_porcentaje' => 0, 'monto_ajuste' => 0, 'monto_final' => $montoBase];

        $cuotas = (int) ($datos['cuotas'] ?? 1);
        $recargoCuotasPct = 0;
        $recargoCuotasMonto = 0;
        $montoCuota = $ajusteData['monto_final'];

        if ($cuotas > 1 && isset($datos['recargo_cuotas_porcentaje'])) {
            $cuotasData = VentaPago::calcularMontoConCuotas(
                $ajusteData['monto_final'],
                $cuotas,
                (float) $datos['recargo_cuotas_porcentaje']
            );
            $recargoCuotasPct = $cuotasData['recargo_cuotas_porcentaje'];
            $recargoCuotasMonto = $cuotasData['recargo_cuotas_monto'];
            $montoCuota = $cuotasData['monto_cuota'];
        }

        $esCC = strtoupper((string) $fp->codigo) === 'CTA_CTE';
        $esEfectivo = $fp->conceptoPago && strtoupper((string) $fp->conceptoPago->codigo) === 'EFECTIVO';
        $afectaCaja = $esEfectivo && $venta->caja_id !== null && ! $esCC;

        $esFacturable = (bool) ($datos['facturar'] ?? false) && (bool) $fp->factura_fiscal;
        $estadoFacturacion = $esFacturable
            ? VentaPago::ESTADO_FACT_PENDIENTE
            : VentaPago::ESTADO_FACT_NO_FACTURADO;

        $pagoNuevo = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'concepto_pago_id' => $fp->concepto_pago_id,
            'monto_base' => $ajusteData['monto_base'],
            'ajuste_porcentaje' => $ajusteData['ajuste_porcentaje'],
            'monto_ajuste' => $ajusteData['monto_ajuste'],
            'monto_final' => $ajusteData['monto_final'],
            'saldo_pendiente' => $esCC ? $ajusteData['monto_final'] : 0,
            'cuotas' => $cuotas > 1 ? $cuotas : null,
            'recargo_cuotas_porcentaje' => $cuotas > 1 ? $recargoCuotasPct : null,
            'recargo_cuotas_monto' => $cuotas > 1 ? $recargoCuotasMonto : null,
            'monto_cuota' => $cuotas > 1 ? $montoCuota : null,
            'referencia' => $datos['referencia'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'es_cuenta_corriente' => $esCC,
            'afecta_caja' => $afectaCaja,
            'estado' => VentaPago::ESTADO_ACTIVO,
            'operacion_origen' => $origen,
            'venta_pago_reemplazado_id' => $reemplazadoId,
            'creado_por_usuario_id' => $usuarioId,
            'estado_facturacion' => $estadoFacturacion,
        ]);

        // Movimiento de caja
        if ($afectaCaja && $venta->caja_id) {
            $caja = Caja::find($venta->caja_id);
            if ($caja) {
                $mov = MovimientoCaja::create([
                    'caja_id' => $caja->id,
                    'tipo' => MovimientoCaja::TIPO_INGRESO,
                    'concepto' => "Pago Venta #{$venta->numero} ({$fp->nombre})",
                    'monto' => $ajusteData['monto_final'],
                    'usuario_id' => $usuarioId,
                    'referencia_tipo' => MovimientoCaja::REF_VENTA,
                    'referencia_id' => $venta->id,
                ]);
                $caja->aumentarSaldo($ajusteData['monto_final']);
                $pagoNuevo->update(['movimiento_caja_id' => $mov->id]);
            }
        }

        // Movimiento cuenta empresa
        if ($fp->cuenta_empresa_id) {
            try {
                $cuenta = \App\Models\CuentaEmpresa::find($fp->cuenta_empresa_id);
                if ($cuenta) {
                    $movCE = CuentaEmpresaService::registrarMovimientoAutomatico(
                        $cuenta,
                        'ingreso',
                        $ajusteData['monto_final'],
                        $fp->conceptoPago?->codigo ?? 'venta',
                        'venta',
                        $venta->id,
                        "Pago Venta #{$venta->numero} ({$fp->nombre})",
                        $usuarioId,
                        $venta->sucursal_id
                    );
                    $pagoNuevo->update(['movimiento_cuenta_empresa_id' => $movCE->id]);
                }
            } catch (Exception $e) {
                Log::warning('Error al registrar movimiento cuenta empresa en cambio de pago', [
                    'venta_pago_id' => $pagoNuevo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $pagoNuevo;
    }

    private function emitirNotaCredito(
        VentaPago $pagoViejo,
        Venta $venta,
        string $motivo,
        int $usuarioId
    ): ?ComprobanteFiscal {
        $cf = $pagoViejo->comprobanteFiscal;
        if (! $cf || ! $cf->esFactura()) {
            return null;
        }

        $service = new ComprobanteFiscalService;

        return $service->crearNotaCredito($cf, $venta, $motivo, $usuarioId);
    }

    /**
     * Calcula el monto final que tendrá el nuevo pago basado en datos de entrada + ajuste de FP.
     */
    private function calcularMontoFinal(array $datos, FormaPago $fp): float
    {
        $montoBase = (float) ($datos['monto_base'] ?? 0);
        $aplicar = (bool) ($datos['aplicar_ajuste'] ?? ($fp->ajuste_porcentaje != 0));

        if (! $aplicar) {
            return round($montoBase, 2);
        }

        $data = VentaPago::calcularMontoConAjuste($montoBase, (float) $fp->ajuste_porcentaje);

        return (float) $data['monto_final'];
    }

    private function snapshotPago(VentaPago $vp): array
    {
        return [
            'venta_pago_id' => $vp->id,
            'forma_pago_id' => $vp->forma_pago_id,
            'forma_pago_nombre' => $vp->formaPago?->nombre,
            'concepto_pago_id' => $vp->concepto_pago_id,
            'monto_base' => (float) $vp->monto_base,
            'ajuste_porcentaje' => (float) $vp->ajuste_porcentaje,
            'monto_ajuste' => (float) $vp->monto_ajuste,
            'monto_final' => (float) $vp->monto_final,
            'cuotas' => $vp->cuotas,
            'es_cuenta_corriente' => (bool) $vp->es_cuenta_corriente,
            'afecta_caja' => (bool) $vp->afecta_caja,
            'movimiento_caja_id' => $vp->movimiento_caja_id,
            'movimiento_cuenta_empresa_id' => $vp->movimiento_cuenta_empresa_id,
            'comprobante_fiscal_id' => $vp->comprobante_fiscal_id,
            'cierre_turno_id' => $vp->cierre_turno_id,
            'created_at' => $vp->created_at?->toIso8601String(),
        ];
    }

    private function registrarAjuste(array $data): VentaPagoAjuste
    {
        $venta = $data['venta'];
        /** @var VentaPago|null $pagoAnulado */
        $pagoAnulado = $data['pago_anulado'];
        /** @var VentaPago|null $pagoNuevo */
        $pagoNuevo = $data['pago_nuevo'];
        $matriz = $data['matriz'];
        $opciones = $data['opciones_fiscales'];
        $ncEmitida = $data['nc_emitida'];

        $montoAnterior = $pagoAnulado ? (float) $pagoAnulado->monto_final : null;
        $montoNuevo = $pagoNuevo ? (float) $pagoNuevo->monto_final : null;
        $deltaTotal = ($montoNuevo ?? 0) - ($montoAnterior ?? 0);

        $salteoAutorizado = $matriz['emitir_nc'] === true
            && ($opciones['emitir_nc'] ?? true) === false;

        $descripcionAuto = $this->generarDescripcionAuto($data['tipo_operacion'], $pagoAnulado, $pagoNuevo);

        $sucursal = $venta->sucursal;

        return VentaPagoAjuste::create([
            'venta_id' => $venta->id,
            'sucursal_id' => $venta->sucursal_id,
            'tipo_operacion' => $data['tipo_operacion'],
            'venta_pago_anulado_id' => $pagoAnulado?->id,
            'venta_pago_nuevo_id' => $pagoNuevo?->id,
            'forma_pago_anterior_id' => $pagoAnulado?->forma_pago_id,
            'forma_pago_nueva_id' => $pagoNuevo?->forma_pago_id,
            'monto_anterior' => $montoAnterior,
            'monto_nuevo' => $montoNuevo,
            'delta_total' => round($deltaTotal, 2),
            'delta_fiscal' => (bool) ($matriz['delta_fiscal'] ?? false),
            'turno_original_id' => $data['turno_original_id'],
            'es_post_cierre' => (bool) $data['es_post_cierre'],
            'nc_emitida_id' => $ncEmitida?->id,
            'fc_nueva_id' => null,
            'nc_emitida_flag' => $ncEmitida !== null,
            'fc_nueva_flag' => ($matriz['emitir_fc_nueva'] ?? false) === true
                && ($opciones['emitir_fc_nueva'] ?? true) !== false,
            'salteo_nc_autorizado' => $salteoAutorizado,
            'config_auto_al_operar' => (bool) ($sucursal->facturacion_fiscal_automatica ?? false),
            'motivo' => $data['motivo'],
            'descripcion_auto' => $descripcionAuto,
            'usuario_id' => $data['usuario_id'],
            'ip_origen' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
        ]);
    }

    private function generarDescripcionAuto(
        string $tipo,
        ?VentaPago $pagoAnulado,
        ?VentaPago $pagoNuevo
    ): string {
        $fmt = fn ($m) => '$'.number_format((float) $m, 2, ',', '.');

        return match ($tipo) {
            VentaPagoAjuste::TIPO_CAMBIO => sprintf(
                'Cambió %s %s por %s %s',
                $pagoAnulado?->formaPago?->nombre ?? 'FP',
                $fmt($pagoAnulado?->monto_final),
                $pagoNuevo?->formaPago?->nombre ?? 'FP',
                $fmt($pagoNuevo?->monto_final),
            ),
            VentaPagoAjuste::TIPO_AGREGAR => sprintf(
                'Agregó pago %s %s',
                $pagoNuevo?->formaPago?->nombre ?? 'FP',
                $fmt($pagoNuevo?->monto_final),
            ),
            VentaPagoAjuste::TIPO_ELIMINAR => sprintf(
                'Eliminó pago %s %s',
                $pagoAnulado?->formaPago?->nombre ?? 'FP',
                $fmt($pagoAnulado?->monto_final),
            ),
            default => 'Ajuste de pago',
        };
    }
}
