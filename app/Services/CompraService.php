<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraConcepto;
use App\Models\CompraDetalle;
use App\Models\CompraIva;
use App\Models\CompraPercepcion;
use App\Models\CondicionIva;
use App\Models\MovimientoStock;
use App\Models\Proveedor;
use App\Models\Stock;
use App\Services\Fiscal\ImpuestoService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compras (spec compras-costos-precios, Fase 4 — REESCRITURA RF-12).
 *
 * Ciclo de vida D10/D11: borrador (editable, SIN efectos) → completada
 * (transacción única: stock + costos + fiscal) → cancelada (reversas por
 * contraasiento). Lo impago se deriva de saldo_pendiente, nunca del estado.
 *
 * Circuito fiscal (quinta ronda del spec):
 * - El crédito de IVA sale de `compra_ivas` (la factura física — fuente
 *   canónica) y se envía SOLO si fiscal AND discrimina AND CUIT comprador RI.
 * - El período del crédito lo rige `fecha_comprobante` (lo aplica
 *   ImpuestoService::registrarDesdeCompra).
 * - Factura A/M con comprador NO-RI (RG 5003): se puede cargar, sin crédito;
 *   el IVA no recuperable se suma al costo computable por renglón.
 * - La NC de proveedor (RF-21) registra la reversa con SU desglose, en
 *   negativo y en el período de la NC; costos NO se recalculan.
 *
 * Pendiente de fases posteriores (hooks documentados en confirmar/cancelar):
 * cta cte de proveedores + pago inicial (Fase 5, RF-18/19), D17 (cancelar con
 * pagos aplicados) y repricing automático (Fase 8, RF-11).
 */
class CompraService
{
    public function __construct(
        protected CostoService $costoService,
        protected ImpuestoService $impuestoService,
    ) {}

    // ==================== Borradores (RF-17) ====================

    /**
     * Crea un borrador: persiste encabezado + renglones + desglose de IVA +
     * conceptos + percepciones, SIN efectos (no toca stock/costos/ledger).
     *
     * $renglones: [{articulo_id, cantidad_comprada, factor_conversion,
     *   precio_unitario, descuentos: [%...], codigo_proveedor_usado?, tipo_iva_id?}]
     * $extras: ['ivas' => [{alicuota, base_imponible, importe}],
     *           'conceptos' => [{tipo, descripcion?, monto, tipo_iva_id?, computa_costo}],
     *           'percepciones' => [{impuesto_id, base_imponible?, alicuota?, monto, certificado_numero?}]]
     */
    public function crearBorrador(array $data, array $renglones = [], array $extras = []): Compra
    {
        $this->validarEncabezado($data);

        return DB::connection('pymes_tenant')->transaction(function () use ($data, $renglones, $extras) {
            $compra = Compra::create([
                'sucursal_id' => $data['sucursal_id'],
                'proveedor_id' => $data['proveedor_id'],
                'compra_origen_id' => $data['compra_origen_id'] ?? null,
                'cuit_id' => $data['cuit_id'] ?? null,
                'cuenta_compra_id' => $data['cuenta_compra_id']
                    ?? Proveedor::find($data['proveedor_id'])?->cuenta_compra_id,
                'usuario_id' => $data['usuario_id'],
                'numero_comprobante' => $this->generarNumeroComprobante($data['sucursal_id'], $data['tipo_comprobante']),
                'numero_comprobante_proveedor' => $data['numero_comprobante_proveedor'] ?? null,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'fecha_comprobante' => $data['fecha_comprobante'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'tipo_comprobante' => $data['tipo_comprobante'],
                'descuento_global_porcentaje' => $data['descuento_global_porcentaje'] ?? null,
                'forma_pago' => $data['forma_pago'] ?? 'cta_cte',
                'estado' => Compra::ESTADO_BORRADOR,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            $this->persistirDetalle($compra, $renglones, $extras);
            $this->recalcularTotales($compra);

            return $compra->fresh(['detalles', 'ivas', 'conceptos', 'percepciones']);
        });
    }

    /**
     * Reemplaza el contenido completo de un borrador (renglones/ivas/
     * conceptos/percepciones) y actualiza el encabezado.
     */
    public function actualizarBorrador(Compra $compra, array $data, array $renglones = [], array $extras = []): Compra
    {
        if (! $compra->esBorrador()) {
            throw new Exception(__('Solo se puede editar un borrador (una compra completada es inmutable: cancelar y recargar)'));
        }

        // Sucursal y usuario no cambian en la edición: se heredan para validar.
        $this->validarEncabezado(array_merge([
            'sucursal_id' => $compra->sucursal_id,
            'usuario_id' => $compra->usuario_id,
        ], $data, ['exceptuar_compra_id' => $compra->id]));

        return DB::connection('pymes_tenant')->transaction(function () use ($compra, $data, $renglones, $extras) {
            $compra->update([
                'proveedor_id' => $data['proveedor_id'],
                'compra_origen_id' => $data['compra_origen_id'] ?? null,
                'cuit_id' => $data['cuit_id'] ?? null,
                'cuenta_compra_id' => $data['cuenta_compra_id'] ?? $compra->cuenta_compra_id,
                'numero_comprobante_proveedor' => $data['numero_comprobante_proveedor'] ?? null,
                'fecha' => $data['fecha'] ?? $compra->fecha,
                'fecha_comprobante' => $data['fecha_comprobante'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'tipo_comprobante' => $data['tipo_comprobante'],
                'descuento_global_porcentaje' => $data['descuento_global_porcentaje'] ?? null,
                'forma_pago' => $data['forma_pago'] ?? $compra->forma_pago,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            $compra->detalles()->delete();
            $compra->ivas()->delete();
            $compra->conceptos()->delete();
            $compra->percepciones()->delete();

            $this->persistirDetalle($compra, $renglones, $extras);
            $this->recalcularTotales($compra);

            return $compra->fresh(['detalles', 'ivas', 'conceptos', 'percepciones']);
        });
    }

    /**
     * Un borrador se elimina sin reversas (nunca tuvo efectos).
     */
    public function eliminarBorrador(Compra $compra): void
    {
        if (! $compra->esBorrador()) {
            throw new Exception(__('Solo se puede eliminar un borrador'));
        }

        $compra->delete(); // detalles/ivas/conceptos/percepciones caen por CASCADE
    }

    // ==================== Confirmación (RF-17) ====================

    /**
     * Confirma la compra en UNA transacción: prorrateos → costo computable por
     * renglón → stock → costos (CostoService) → fiscal (ImpuestoService).
     *
     * Hooks pendientes: cta cte proveedor + pago inicial (Fase 5), repricing
     * automático (Fase 8) — se agregan al final de esta misma transacción.
     */
    public function confirmarCompra(Compra $compra, ?int $usuarioId = null): Compra
    {
        if (! $compra->esBorrador()) {
            throw new Exception(__('Solo se puede confirmar un borrador'));
        }

        $compra->load(['detalles', 'ivas', 'conceptos', 'percepciones', 'cuit.condicionIva', 'compraOrigen']);

        $this->validarConfirmacion($compra);

        $usuarioId ??= (int) $compra->usuario_id;

        DB::connection('pymes_tenant')->transaction(function () use ($compra, $usuarioId) {
            // 1. Prorrateos + costo computable por renglón (persisten en el detalle).
            $this->resolverProrrateosYComputables($compra);

            // 2. Totales finales + saldo (la deuda la baja el pago, Fase 5).
            $this->recalcularTotales($compra);
            $compra->update(['saldo_pendiente' => $compra->total]);

            // 3. Stock (la NC devuelve: egreso).
            $this->moverStock($compra, $usuarioId, reversa: $compra->esNotaCredito());

            // 4. Costos — solo compras (RF-21: una NC parcial NO recalcula costos).
            if (! $compra->esNotaCredito()) {
                $this->costoService->registrarDesdeCompra($compra->fresh(['detalles']), $usuarioId);
            }

            // 5. Fiscal: crédito desde compra_ivas con el GATE del caller
            //    (fiscal AND discrimina AND comprador RI) + percepciones.
            if ($compra->esFiscal() && $this->compradorEsRI($compra)) {
                $this->impuestoService->registrarDesdeCompra(
                    $compra,
                    $compra->discriminaIva() ? $this->armarIvaCredito($compra) : [],
                    $usuarioId,
                    esNotaCredito: $compra->esNotaCredito(),
                );
            }

            $compra->update(['estado' => Compra::ESTADO_COMPLETADA]);
        });

        Log::info('Compra confirmada', [
            'compra_id' => $compra->id,
            'tipo_comprobante' => $compra->tipo_comprobante,
            'total' => (float) $compra->total,
            'es_nc' => $compra->esNotaCredito(),
        ]);

        return $compra->fresh(['detalles', 'ivas', 'conceptos', 'percepciones']);
    }

    // ==================== Cancelación (RF-17/D17) ====================

    /**
     * Cancela una compra completada: reversas de stock, costos (RF-07) y
     * fiscal (patrón NC cross-período), todas por contraasiento.
     *
     * D17 (pagos aplicados: cascada o saldo a favor) llega con la Fase 5 —
     * hasta entonces una compra con pagos no se puede cancelar.
     */
    public function cancelarCompra(Compra $compra, int $usuarioId, ?string $motivo = null): Compra
    {
        if (! $compra->estaCompletada()) {
            throw new Exception(__('Solo se puede cancelar una compra completada'));
        }

        if (round((float) $compra->saldo_pendiente, 2) !== round((float) $compra->total, 2)) {
            // Hook D17 (Fase 5): elegir cascada de anulación de pagos o saldo a favor.
            throw new Exception(__('La compra tiene pagos aplicados: la cancelación con pagos llega con la cuenta corriente de proveedores'));
        }

        $compra->load(['detalles']);

        DB::connection('pymes_tenant')->transaction(function () use ($compra, $usuarioId, $motivo) {
            // Reversa de stock (la cancelación de una NC repone lo devuelto).
            $this->moverStock($compra, $usuarioId, reversa: ! $compra->esNotaCredito(), cancelacion: true);

            if (! $compra->esNotaCredito()) {
                $this->costoService->revertirCostoUltimoSiCorresponde($compra, $usuarioId);
            }

            $this->impuestoService->anularDesdeCompra($compra, $usuarioId);

            $compra->update([
                'estado' => Compra::ESTADO_CANCELADA,
                'saldo_pendiente' => 0,
                'observaciones' => trim(($compra->observaciones ?? '')."\n".__('Cancelada').': '.($motivo ?? '—')),
            ]);
        });

        Log::info('Compra cancelada', ['compra_id' => $compra->id, 'motivo' => $motivo]);

        return $compra->fresh();
    }

    // ==================== Validaciones expuestas (UI Fase 6) ====================

    /**
     * Anti-duplicado (RF-13): misma factura del mismo proveedor ACTIVA
     * (borrador o completada — las canceladas se excluyen para poder recargar).
     */
    public function esComprobanteDuplicado(int $proveedorId, ?string $tipo, ?string $numero, ?int $exceptoCompraId = null): bool
    {
        if ($numero === null || $numero === '') {
            return false;
        }

        return Compra::activas()
            ->where('proveedor_id', $proveedorId)
            ->where('tipo_comprobante', $tipo)
            ->where('numero_comprobante_proveedor', $numero)
            ->when($exceptoCompraId, fn ($q) => $q->where('id', '!=', $exceptoCompraId))
            ->exists();
    }

    /**
     * Advertencia comprobante×CUIT (NO bloqueante, corregido 2026-07-09 por
     * RG 5003/2021: un monotributista SÍ recibe factura A). Devuelve el texto
     * a mostrar o NULL si la combinación no amerita aviso.
     */
    public function advertenciaComprobanteCuit(?CondicionIva $condicion, ?string $tipoComprobante): ?string
    {
        if ($condicion === null || $tipoComprobante === null) {
            return null;
        }

        $discrimina = in_array($tipoComprobante, Compra::TIPOS_DISCRIMINAN_IVA, true);

        if ($discrimina && ! $condicion->esResponsableInscripto()) {
            return __('El CUIT comprador no es Responsable Inscripto: el IVA de esta factura no genera crédito fiscal y todo lo pagado integra el costo.');
        }

        if (! $discrimina && $condicion->esResponsableInscripto()
            && in_array($tipoComprobante, [Compra::TIPO_FACTURA_B, Compra::TIPO_NC_B], true)) {
            return __('Factura B a un Responsable Inscripto: no discrimina IVA — sin crédito fiscal, todo lo pagado es costo. Verificá si corresponde pedir factura A.');
        }

        return null;
    }

    // ==================== Internos ====================

    private function validarEncabezado(array $data): void
    {
        if (empty($data['proveedor_id']) || empty($data['sucursal_id']) || empty($data['usuario_id'])) {
            throw new Exception(__('La compra requiere proveedor, sucursal y usuario'));
        }

        if (empty($data['tipo_comprobante'])) {
            throw new Exception(__('Seleccioná el tipo de comprobante'));
        }

        if ($this->esComprobanteDuplicado(
            (int) $data['proveedor_id'],
            $data['tipo_comprobante'],
            $data['numero_comprobante_proveedor'] ?? null,
            $data['exceptuar_compra_id'] ?? null,
        )) {
            throw new Exception(__('Ya existe una compra activa de este proveedor con ese tipo y número de comprobante'));
        }
    }

    private function validarConfirmacion(Compra $compra): void
    {
        if ($compra->esFiscal() && $compra->fecha_comprobante === null) {
            throw new Exception(__('La fecha del comprobante es obligatoria en compras fiscales (rige el período del crédito)'));
        }

        if ($this->esComprobanteDuplicado($compra->proveedor_id, $compra->tipo_comprobante, $compra->numero_comprobante_proveedor, $compra->id)) {
            throw new Exception(__('Ya existe una compra activa de este proveedor con ese tipo y número de comprobante'));
        }

        if (! $compra->esNotaCredito() && $compra->detalles->isEmpty()) {
            throw new Exception(__('La compra no tiene renglones'));
        }

        if ($compra->esNotaCredito() && $compra->compra_origen_id !== null) {
            $origen = $compra->compraOrigen;

            if ($origen === null || ! $origen->estaCompletada() || $origen->proveedor_id !== $compra->proveedor_id) {
                throw new Exception(__('La compra origen de la nota de crédito debe estar completada y ser del mismo proveedor'));
            }
        }

        // D15/coherencia: un comprobante que no discrimina no lleva desglose de IVA.
        if (! $compra->discriminaIva() && $compra->ivas->isNotEmpty()) {
            throw new Exception(__('Un comprobante que no discrimina IVA no lleva desglose por alícuota'));
        }

        if (! $compra->esFiscal() && $compra->percepciones->isNotEmpty()) {
            throw new Exception(__('Una compra no fiscal no lleva percepciones'));
        }
    }

    /**
     * Asigna a cada renglón el prorrateo POR IMPORTE del descuento global y de
     * los conceptos que computan costo, y calcula su costo unitario computable
     * (incluyendo el IVA no recuperable del caso RG 5003).
     */
    private function resolverProrrateosYComputables(Compra $compra): void
    {
        if ($compra->detalles->isEmpty()) {
            return;
        }

        // Importe de cada renglón con su cascada aplicada (base del prorrateo).
        $importes = [];
        foreach ($compra->detalles as $detalle) {
            $unitario = (float) $detalle->precio_unitario;
            foreach ((array) $detalle->descuentos as $d) {
                $unitario *= (1 - ((float) $d) / 100);
            }
            $importes[$detalle->id] = round($unitario * (float) $detalle->cantidad_comprada, 4);
        }

        $descuentoGlobal = $this->montoDescuentoGlobal($compra, array_sum($importes));
        $conceptosCosto = (float) $compra->conceptos->where('computa_costo', true)->sum('monto');

        $globalPorRenglon = $this->costoService->prorratearPorImporte($importes, $descuentoGlobal);
        $conceptosPorRenglon = $this->costoService->prorratearPorImporte($importes, $conceptosCosto);

        // RG 5003: discrimina pero el comprador no computa crédito ⇒ el IVA del
        // renglón integra el costo.
        $ivaNoRecuperable = $compra->discriminaIva() && ! $this->compradorEsRI($compra);

        foreach ($compra->detalles as $detalle) {
            $renglon = [
                'precio_unitario' => (float) $detalle->precio_unitario,
                'descuentos' => (array) $detalle->descuentos,
                'cantidad_comprada' => (float) $detalle->cantidad_comprada,
                'factor_conversion' => (float) $detalle->factor_conversion,
                'descuento_global_monto' => $globalPorRenglon[$detalle->id] ?? 0,
                'conceptos_costo_monto' => $conceptosPorRenglon[$detalle->id] ?? 0,
                'alicuota_no_recuperable' => $ivaNoRecuperable
                    ? (float) ($detalle->tipoIva?->porcentaje ?? 0)
                    : 0,
            ];

            $bruto = (float) $detalle->precio_unitario * (float) $detalle->cantidad_comprada;

            $detalle->update([
                'descuento_monto' => round($bruto - $importes[$detalle->id], 2),
                'descuento_global_monto' => $globalPorRenglon[$detalle->id] ?? 0,
                'conceptos_costo_monto' => $conceptosPorRenglon[$detalle->id] ?? 0,
                'costo_unitario_computable' => $this->costoService->costoComputableRenglon($renglon, $compra),
                'subtotal' => round($importes[$detalle->id], 2),
            ]);
        }
    }

    /**
     * Totales del comprobante: subtotal (renglones con cascada, pre global)
     * − descuento global + conceptos + IVA (Σ compra_ivas) + percepciones.
     */
    private function recalcularTotales(Compra $compra): void
    {
        $compra->load(['detalles', 'ivas', 'conceptos', 'percepciones']);

        $subtotal = 0.0;
        foreach ($compra->detalles as $detalle) {
            $unitario = (float) $detalle->precio_unitario;
            foreach ((array) $detalle->descuentos as $d) {
                $unitario *= (1 - ((float) $d) / 100);
            }
            $subtotal += $unitario * (float) $detalle->cantidad_comprada;
        }

        $descuentoGlobal = $this->montoDescuentoGlobal($compra, $subtotal);
        $totalIva = (float) $compra->ivas->sum('importe');
        $conceptos = (float) $compra->conceptos->sum('monto');
        $percepciones = (float) $compra->percepciones->sum('monto');

        $compra->update([
            'subtotal' => round($subtotal, 2),
            'descuento_global_monto' => round($descuentoGlobal, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($subtotal - $descuentoGlobal + $conceptos + $totalIva + $percepciones, 2),
        ]);
    }

    private function montoDescuentoGlobal(Compra $compra, float $subtotal): float
    {
        if ($compra->descuento_global_porcentaje !== null) {
            return round($subtotal * (float) $compra->descuento_global_porcentaje / 100, 2);
        }

        return (float) $compra->descuento_global_monto;
    }

    /**
     * Movimientos + caché de stock. Compra = ingreso; NC o cancelación de
     * compra = egreso (validando stock suficiente); cancelación de NC = ingreso.
     */
    private function moverStock(Compra $compra, int $usuarioId, bool $reversa, bool $cancelacion = false): void
    {
        foreach ($compra->detalles as $detalle) {
            $articulo = $detalle->articulo;

            if ($articulo === null || ! $articulo->controlaStock($compra->sucursal_id)) {
                continue;
            }

            $cantidad = (float) $detalle->cantidad;

            $stock = Stock::firstOrCreate(
                ['sucursal_id' => $compra->sucursal_id, 'articulo_id' => $detalle->articulo_id],
                ['cantidad' => 0, 'ultima_actualizacion' => now()],
            );

            if ($reversa) {
                if ((float) $stock->cantidad < $cantidad) {
                    throw new Exception(__('Stock insuficiente de ":articulo" para registrar la devolución (:actual disponibles, se necesitan :cantidad)', [
                        'articulo' => $articulo->nombre,
                        'actual' => $stock->cantidad,
                        'cantidad' => $cantidad,
                    ]));
                }

                $stock->disminuir($cantidad);

                MovimientoStock::crearMovimientoAnulacionCompra(
                    $detalle->articulo_id,
                    $compra->sucursal_id,
                    $cantidad,
                    $compra->id,
                    $detalle->id,
                    $cancelacion
                        ? __('Cancelación compra :numero', ['numero' => $compra->numero_comprobante])
                        : __('Devolución NC :numero', ['numero' => $compra->numero_comprobante]),
                    $usuarioId,
                );
            } else {
                $stock->aumentar($cantidad);

                // Fix del spec: el movimiento lleva el costo COMPUTABLE (el viejo
                // usaba precio_sin_iva, incorrecto para compras que no discriminan).
                MovimientoStock::crearMovimientoCompra(
                    $detalle->articulo_id,
                    $compra->sucursal_id,
                    $cantidad,
                    $compra->id,
                    $detalle->id,
                    $cancelacion
                        ? __('Cancelación NC :numero', ['numero' => $compra->numero_comprobante])
                        : __('Compra :numero', ['numero' => $compra->numero_comprobante]),
                    $usuarioId,
                    $detalle->costo_unitario_computable !== null ? (float) $detalle->costo_unitario_computable : null,
                );
            }
        }
    }

    /**
     * Crédito de IVA por alícuota desde `compra_ivas` — la fuente canónica
     * (RF-14), nunca la suma de renglones.
     */
    private function armarIvaCredito(Compra $compra): array
    {
        return $compra->ivas->map(fn (CompraIva $iva) => [
            'base_imponible' => (float) $iva->base_imponible,
            'alicuota' => (float) $iva->alicuota,
            'monto' => (float) $iva->importe,
        ])->all();
    }

    private function compradorEsRI(Compra $compra): bool
    {
        return $compra->cuit?->condicionIva?->esResponsableInscripto() ?? false;
    }

    private function persistirDetalle(Compra $compra, array $renglones, array $extras): void
    {
        foreach ($renglones as $renglon) {
            $cantidadComprada = (float) ($renglon['cantidad_comprada'] ?? 0);
            $factor = (float) ($renglon['factor_conversion'] ?? 1);

            if (empty($renglon['articulo_id']) || $cantidadComprada <= 0 || $factor <= 0) {
                throw new Exception(__('Renglón inválido: requiere artículo, cantidad y factor positivos'));
            }

            CompraDetalle::create([
                'compra_id' => $compra->id,
                'articulo_id' => $renglon['articulo_id'],
                'tipo_iva_id' => $renglon['tipo_iva_id'] ?? null,
                'cantidad' => round($cantidadComprada * $factor, 3), // SIEMPRE en unidades de stock (D8)
                'cantidad_comprada' => $cantidadComprada,
                'factor_conversion' => $factor,
                'codigo_proveedor_usado' => $renglon['codigo_proveedor_usado'] ?? null,
                'precio_unitario' => (float) ($renglon['precio_unitario'] ?? 0),
                'descuentos' => array_values(array_filter(
                    (array) ($renglon['descuentos'] ?? []),
                    fn ($d) => (float) $d > 0,
                )),
                'subtotal' => 0, // se fija al recalcular
            ]);
        }

        foreach ($extras['ivas'] ?? [] as $iva) {
            CompraIva::create([
                'compra_id' => $compra->id,
                'alicuota' => $iva['alicuota'],
                'base_imponible' => $iva['base_imponible'],
                'importe' => $iva['importe'],
            ]);
        }

        foreach ($extras['conceptos'] ?? [] as $concepto) {
            CompraConcepto::create([
                'compra_id' => $compra->id,
                'tipo' => $concepto['tipo'] ?? 'otro',
                'descripcion' => $concepto['descripcion'] ?? null,
                'monto' => $concepto['monto'],
                'tipo_iva_id' => $concepto['tipo_iva_id'] ?? null,
                'computa_costo' => (bool) ($concepto['computa_costo'] ?? false),
            ]);
        }

        foreach ($extras['percepciones'] ?? [] as $percepcion) {
            CompraPercepcion::create([
                'compra_id' => $compra->id,
                'impuesto_id' => $percepcion['impuesto_id'],
                'base_imponible' => $percepcion['base_imponible'] ?? null,
                'alicuota' => $percepcion['alicuota'] ?? null,
                'monto' => $percepcion['monto'],
                'certificado_numero' => $percepcion['certificado_numero'] ?? null,
            ]);
        }
    }

    /**
     * Número INTERNO autogenerado (el real del proveedor viaja en
     * numero_comprobante_proveedor, RF-13).
     */
    private function generarNumeroComprobante(int $sucursalId, ?string $tipo): string
    {
        $prefijo = match (true) {
            $tipo !== null && str_starts_with($tipo, 'nota_credito') => 'NCP',
            default => 'COM',
        };

        $ultimo = Compra::where('sucursal_id', $sucursalId)
            ->where('numero_comprobante', 'like', "{$prefijo}-{$sucursalId}-%")
            ->orderByDesc('id')
            ->value('numero_comprobante');

        $numero = $ultimo !== null ? ((int) substr($ultimo, strrpos($ultimo, '-') + 1)) + 1 : 1;

        return sprintf('%s-%d-%08d', $prefijo, $sucursalId, $numero);
    }
}
