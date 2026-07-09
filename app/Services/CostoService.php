<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\ArticuloCosto;
use App\Models\ArticuloProveedor;
use App\Models\Compra;
use App\Models\CondicionIva;
use App\Models\ConfiguracionCostos;
use App\Models\Cuit;
use App\Models\HistorialCosto;
use App\Models\Stock;
use App\Models\Sucursal;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Núcleo de costos (spec compras-costos-precios, Fase 3): ÚNICA puerta de
 * escritura de articulo_costos / historial_costos / articulo_proveedor.
 *
 * Conceptos (ver "Fórmulas canónicas" del spec):
 * - Costo COMPUTABLE: lo que la compra realmente costó a efectos de pricing —
 *   neto cuando el IVA fue crédito fiscal recuperable (factura A de un RI);
 *   total pagado cuando no (factura B/C/no fiscal, comprador no RI). La
 *   MATEMÁTICA de la cadena es idéntica en ambos casos: el precio cargado ya
 *   viene en la base correcta (neto si discrimina, final si no).
 * - El costo se actualiza SOLO con compras (o edición manual), nunca con
 *   ventas. Tres valores por artículo: último (rector para pricing, D1),
 *   promedio PPP (valuación) y reposición (manual).
 * - alicuotaEfectiva (D21): las fórmulas de precio/margen solo aplican IVA si
 *   el comercio computa IVA (CUIT RI) y el precio del artículo lo incluye.
 */
class CostoService
{
    /**
     * Cadena completa del costo de un renglón (RF-01, fórmula canónica):
     * precio unitario de factura × cascada de descuentos − prorrateo del
     * descuento global + prorrateo de conceptos que computan costo, dividido
     * por el factor de conversión ⇒ costo unitario computable por unidad de
     * STOCK (4 decimales).
     *
     * $renglon: precio_unitario, descuentos (lista de % en cascada),
     * cantidad_comprada (unidades de compra), factor_conversion,
     * descuento_global_monto y conceptos_costo_monto (importes TOTALES ya
     * prorrateados al renglón — el prorrateo lo hace el caller con
     * prorratearPorImporte()).
     */
    public function costoComputableRenglon(array $renglon, ?Compra $compra = null): float
    {
        $cantidadComprada = (float) ($renglon['cantidad_comprada'] ?? 0);
        $factor = (float) ($renglon['factor_conversion'] ?? 1);

        if ($cantidadComprada <= 0 || $factor <= 0) {
            throw new Exception(__('El renglón debe tener cantidad y factor de conversión positivos'));
        }

        $unitario = (float) ($renglon['precio_unitario'] ?? 0);

        foreach ((array) ($renglon['descuentos'] ?? []) as $descuento) {
            $unitario *= (1 - ((float) $descuento) / 100);
        }

        $importeRenglon = $unitario * $cantidadComprada
            - (float) ($renglon['descuento_global_monto'] ?? 0)
            + (float) ($renglon['conceptos_costo_monto'] ?? 0);

        // Por unidad de compra → por unidad de stock (D8).
        return round($importeRenglon / $cantidadComprada / $factor, 4);
    }

    /**
     * Prorratea un monto entre renglones POR IMPORTE (criterio único del
     * spec). Devuelve los montos asignados en el mismo orden; el residuo de
     * redondeo va al último renglón para preservar la suma exacta.
     *
     * @param  array<int|string, float>  $importes
     * @return array<int|string, float>
     */
    public function prorratearPorImporte(array $importes, float $monto): array
    {
        $total = array_sum($importes);

        if ($total <= 0 || count($importes) === 0) {
            return array_map(fn () => 0.0, $importes);
        }

        $asignados = [];
        $acumulado = 0.0;
        $keys = array_keys($importes);
        $ultima = end($keys);

        foreach ($importes as $key => $importe) {
            if ($key === $ultima) {
                $asignados[$key] = round($monto - $acumulado, 4);
            } else {
                $asignados[$key] = round($monto * $importe / $total, 4);
                $acumulado += $asignados[$key];
            }
        }

        return $asignados;
    }

    /**
     * Registra los costos de una compra CONFIRMADA (RF-02/03/04): actualiza
     * costo_ultimo + PPP en la fila de la sucursal Y en la consolidada
     * (sucursal_id NULL), upsertea articulo_proveedor y deja historial.
     *
     * CONTRATO: el stock YA incluye la compra (orden del pipeline de
     * confirmarCompra: stock → costos). El PPP resta la cantidad propia para
     * ponderar contra el stock PREVIO.
     *
     * Idempotente por compra (marcador: filas de historial de la compra).
     * Registra historial por CADA compra aunque el valor no cambie — es el
     * marcador de idempotencia y la trazabilidad "qué costo trajo cada compra".
     */
    public function registrarDesdeCompra(Compra $compra, ?int $usuarioId = null): void
    {
        $yaRegistrado = HistorialCosto::where('compra_id', $compra->id)
            ->where('origen', 'compra')
            ->exists();

        if ($yaRegistrado) {
            return;
        }

        $porArticulo = $this->agruparDetallesPorArticulo($compra);

        if (empty($porArticulo)) {
            return;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($compra, $porArticulo, $usuarioId) {
            foreach ($porArticulo as $articuloId => $datos) {
                // Fila de la sucursal de la compra + fila consolidada.
                foreach ([$compra->sucursal_id, null] as $sucursalId) {
                    $this->actualizarCostoCompra(
                        $articuloId,
                        $sucursalId,
                        $datos['costo'],
                        $datos['cantidad'],
                        $compra,
                        $usuarioId,
                    );
                }

                $this->upsertArticuloProveedor($articuloId, $datos, $compra);
            }
        });

        Log::info('Costos registrados desde compra', [
            'compra_id' => $compra->id,
            'articulos' => count($porArticulo),
        ]);
    }

    /**
     * RF-07: al cancelar la compra que fijó el costo_ultimo VIGENTE, restaura
     * el anterior desde el historial (fila nueva origen 'cancelacion').
     * El PPP NO se recalcula hacia atrás (la próxima compra lo corrige) y
     * articulo_proveedor no se revierte.
     */
    public function revertirCostoUltimoSiCorresponde(Compra $compra, ?int $usuarioId = null): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($compra, $usuarioId) {
            $filas = ArticuloCosto::where('compra_ultima_id', $compra->id)
                ->lockForUpdate()
                ->get();

            foreach ($filas as $fila) {
                $historialCompra = HistorialCosto::where('articulo_id', $fila->articulo_id)
                    ->where('compra_id', $compra->id)
                    ->where('tipo_costo', 'ultimo')
                    ->when(
                        $fila->sucursal_id === null,
                        fn ($q) => $q->whereNull('sucursal_id'),
                        fn ($q) => $q->where('sucursal_id', $fila->sucursal_id),
                    )
                    ->orderByDesc('id')
                    ->first();

                if ($historialCompra === null) {
                    continue;
                }

                // Origen anterior (compra/proveedor previos) desde el historial previo.
                $historialPrevio = HistorialCosto::where('articulo_id', $fila->articulo_id)
                    ->where('tipo_costo', 'ultimo')
                    ->where('id', '<', $historialCompra->id)
                    ->when(
                        $fila->sucursal_id === null,
                        fn ($q) => $q->whereNull('sucursal_id'),
                        fn ($q) => $q->where('sucursal_id', $fila->sucursal_id),
                    )
                    ->orderByDesc('id')
                    ->first();

                $restaurado = $historialCompra->costo_anterior !== null
                    ? (float) $historialCompra->costo_anterior
                    : null;

                $anterior = $fila->costo_ultimo !== null ? (float) $fila->costo_ultimo : null;

                $fila->update([
                    'costo_ultimo' => $restaurado,
                    'proveedor_ultimo_id' => $historialPrevio?->proveedor_id,
                    'compra_ultima_id' => $historialPrevio?->compra_id,
                    'fecha_costo_ultimo' => $historialPrevio?->created_at,
                ]);

                // costo_nuevo es NOT NULL: restaurar a "sin costo" se registra
                // como 0 con detalle explícito.
                HistorialCosto::create([
                    'articulo_id' => $fila->articulo_id,
                    'sucursal_id' => $fila->sucursal_id,
                    'tipo_costo' => 'ultimo',
                    'costo_anterior' => $anterior,
                    'costo_nuevo' => $restaurado ?? 0,
                    'porcentaje_cambio' => $this->porcentajeCambio($anterior, $restaurado ?? 0),
                    'origen' => 'cancelacion',
                    'compra_id' => $compra->id,
                    'usuario_id' => $usuarioId,
                    'detalle' => $restaurado === null
                        ? __('Cancelación de compra: restaurado a sin costo')
                        : __('Cancelación de compra: costo anterior restaurado'),
                ]);
            }
        });
    }

    /**
     * Edición manual de costo (RF-02/RF-03): costo_ultimo o costo_reposicion,
     * en la fila de la sucursal o la consolidada. NULL en reposición lo borra
     * (vuelve el fallback a costo_ultimo).
     */
    public function actualizarManual(
        Articulo $articulo,
        ?int $sucursalId,
        string $tipo,
        ?float $valor,
        int $usuarioId,
        string $origen = 'manual',
    ): ArticuloCosto {
        if (! in_array($tipo, ['ultimo', 'reposicion'], true)) {
            throw new Exception(__('Tipo de costo inválido'));
        }

        if ($valor !== null && $valor < 0) {
            throw new Exception(__('El costo no puede ser negativo'));
        }

        if ($valor === null && $tipo === 'ultimo') {
            throw new Exception(__('El costo último no se puede borrar'));
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($articulo, $sucursalId, $tipo, $valor, $usuarioId, $origen) {
            $fila = $this->filaCostos($articulo->id, $sucursalId, lock: true);

            $columna = $tipo === 'ultimo' ? 'costo_ultimo' : 'costo_reposicion';
            $anterior = $fila->{$columna} !== null ? (float) $fila->{$columna} : null;

            $fila->update([$columna => $valor]);

            HistorialCosto::create([
                'articulo_id' => $articulo->id,
                'sucursal_id' => $sucursalId,
                'tipo_costo' => $tipo,
                'costo_anterior' => $anterior,
                'costo_nuevo' => $valor ?? 0,
                'porcentaje_cambio' => $this->porcentajeCambio($anterior, $valor ?? 0),
                'origen' => $origen,
                'usuario_id' => $usuarioId,
                'detalle' => $valor === null ? __('Costo de reposición borrado') : null,
            ]);

            return $fila->fresh();
        });
    }

    // ==================== Lectura: utilidad, margen y precio ====================

    /**
     * Utilidad objetivo en cascada (RF-08): artículo → categoría → comercio.
     */
    public function utilidadObjetivo(Articulo $articulo): float
    {
        if ($articulo->utilidad_porcentaje !== null) {
            return (float) $articulo->utilidad_porcentaje;
        }

        $categoria = $articulo->categoriaModel;

        if ($categoria !== null && $categoria->utilidad_porcentaje !== null) {
            return (float) $categoria->utilidad_porcentaje;
        }

        return (float) ConfiguracionCostos::obtener()->utilidad_default;
    }

    /**
     * Alícuota efectiva para las fórmulas de precio/margen (D21) — ÚNICA
     * puerta: nunca leer la alícuota directo en fórmulas de pricing.
     *
     * Devuelve la alícuota del TipoIva del artículo SOLO si el comercio
     * computa IVA (CUIT emisor RI) Y el precio del artículo incluye IVA.
     * Para un comercio no-RI el costo es bruto y TODO el precio es ingreso ⇒
     * la fórmula no debe agregar ni quitar IVA (alícuota 0). Ídem cuando el
     * precio se almacena neto (precio_iva_incluido=false): el sugerido se
     * materializa neto y el margen no divide.
     */
    public function alicuotaEfectiva(Articulo $articulo, ?int $sucursalId = null): float
    {
        if (! $articulo->precio_iva_incluido) {
            return 0.0;
        }

        if (! $this->comercioComputaIva($sucursalId)) {
            return 0.0;
        }

        return (float) ($articulo->tipoIva?->porcentaje ?? 0);
    }

    /**
     * Costo rector para pricing (D1): configuracion_costos.costo_rector
     * (v1 'ultimo'; reposición cae a último si es NULL). Fila de la sucursal
     * con fallback a la consolidada (RF-09).
     */
    public function costoRector(Articulo $articulo, ?int $sucursalId = null): ?float
    {
        $fila = $this->filaCostosLectura($articulo->id, $sucursalId);

        if ($fila === null) {
            return null;
        }

        $costo = match (ConfiguracionCostos::obtener()->costo_rector) {
            'promedio' => $fila->costo_promedio,
            'reposicion' => $fila->costo_reposicion ?? $fila->costo_ultimo,
            default => $fila->costo_ultimo,
        };

        return $costo !== null ? (float) $costo : null;
    }

    /**
     * Margen real del artículo (RF-09), con la MISMA división que hace la
     * venta. NULL si no hay costo. Estructura pensada para la UI (claridad
     * conceptual obligatoria): cada paso de la cuenta viaja por separado.
     *
     * @return array{costo_rector: float, precio_final: float, alicuota: float,
     *               neto_venta: float, margen_real: float, coeficiente: ?float,
     *               margen_sobre_venta: ?float, utilidad_objetivo: float}|null
     */
    public function margenReal(Articulo $articulo, ?int $sucursalId = null): ?array
    {
        $costo = $this->costoRector($articulo, $sucursalId);

        if ($costo === null || $costo <= 0) {
            return null;
        }

        $precioFinal = $sucursalId !== null
            ? $articulo->obtenerPrecioBaseEfectivo($sucursalId)
            : (float) $articulo->precio_base;

        $alicuota = $this->alicuotaEfectiva($articulo, $sucursalId);
        $netoVenta = $precioFinal / (1 + $alicuota / 100);

        return [
            'costo_rector' => round($costo, 4),
            'precio_final' => round($precioFinal, 2),
            'alicuota' => $alicuota,
            'neto_venta' => round($netoVenta, 2),
            'margen_real' => round(($netoVenta - $costo) / $costo * 100, 2),
            'coeficiente' => $costo > 0 ? round($precioFinal / $costo, 4) : null,
            'margen_sobre_venta' => $netoVenta > 0 ? round(($netoVenta - $costo) / $netoVenta * 100, 2) : null,
            'utilidad_objetivo' => $this->utilidadObjetivo($articulo),
        ];
    }

    /**
     * Precio final sugerido (fórmula canónica D2/D21):
     * costo rector × (1 + utilidad) × (1 + alícuota efectiva) → redondeo.
     * NULL si el artículo no tiene costo.
     */
    public function precioSugerido(
        Articulo $articulo,
        ?int $sucursalId = null,
        ?float $utilidad = null,
        string $redondeo = 'ninguno',
    ): ?float {
        $costo = $this->costoRector($articulo, $sucursalId);

        if ($costo === null || $costo <= 0) {
            return null;
        }

        $utilidad ??= $this->utilidadObjetivo($articulo);
        $alicuota = $this->alicuotaEfectiva($articulo, $sucursalId);

        $precio = $costo * (1 + $utilidad / 100) * (1 + $alicuota / 100);

        return app(PrecioService::class)->aplicarRedondeo($precio, $redondeo);
    }

    // ==================== Internos ====================

    /**
     * Agrupa los renglones confirmados por artículo: cantidad total en
     * unidades de stock + costo computable PONDERADO del comprobante (si el
     * mismo artículo viene en varios renglones) + datos para el upsert de
     * articulo_proveedor.
     */
    private function agruparDetallesPorArticulo(Compra $compra): array
    {
        $porArticulo = [];

        foreach ($compra->detalles as $detalle) {
            if ($detalle->costo_unitario_computable === null || $detalle->articulo_id === null) {
                continue;
            }

            $cantidad = (float) $detalle->cantidad;
            $costo = (float) $detalle->costo_unitario_computable;

            if ($cantidad <= 0) {
                continue;
            }

            if (! isset($porArticulo[$detalle->articulo_id])) {
                $porArticulo[$detalle->articulo_id] = [
                    'cantidad' => 0.0,
                    'importe' => 0.0,
                    'costo' => 0.0,
                    'codigo_proveedor' => null,
                    'factor_conversion' => null,
                    'descuentos' => null,
                ];
            }

            $grupo = &$porArticulo[$detalle->articulo_id];
            $grupo['cantidad'] += $cantidad;
            $grupo['importe'] += $cantidad * $costo;
            $grupo['costo'] = round($grupo['importe'] / $grupo['cantidad'], 4);
            $grupo['codigo_proveedor'] = $detalle->codigo_proveedor_usado ?? $grupo['codigo_proveedor'];
            $grupo['factor_conversion'] = (float) $detalle->factor_conversion;
            $grupo['descuentos'] = $detalle->descuentos;
            unset($grupo);
        }

        return $porArticulo;
    }

    /**
     * Actualiza costo_ultimo + PPP de UNA fila (sucursal o consolidada) y
     * registra el historial. El stock ya incluye la compra ⇒ stock previo =
     * stock actual − cantidad de esta compra.
     */
    private function actualizarCostoCompra(
        int $articuloId,
        ?int $sucursalId,
        float $costo,
        float $cantidad,
        Compra $compra,
        ?int $usuarioId,
    ): void {
        $fila = $this->filaCostos($articuloId, $sucursalId, lock: true);

        $stockActual = $this->stockActual($articuloId, $sucursalId);
        $stockPrevio = $stockActual - $cantidad;

        $pppPrevio = $fila->costo_promedio !== null ? (float) $fila->costo_promedio : null;

        // Arranque documentado (RF-02): PPP NULL o stock previo sin unidades ⇒
        // la compra lo fija (el stock previo sin costo NO pondera).
        $nuevoPpp = ($pppPrevio === null || $stockPrevio <= 0)
            ? $costo
            : round(($stockPrevio * $pppPrevio + $cantidad * $costo) / ($stockPrevio + $cantidad), 4);

        $anterior = $fila->costo_ultimo !== null ? (float) $fila->costo_ultimo : null;

        $fila->update([
            'costo_ultimo' => $costo,
            'costo_promedio' => $nuevoPpp,
            'proveedor_ultimo_id' => $compra->proveedor_id,
            'compra_ultima_id' => $compra->id,
            'fecha_costo_ultimo' => now(),
        ]);

        HistorialCosto::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
            'tipo_costo' => 'ultimo',
            'costo_anterior' => $anterior,
            'costo_nuevo' => $costo,
            'porcentaje_cambio' => $this->porcentajeCambio($anterior, $costo),
            'origen' => 'compra',
            'compra_id' => $compra->id,
            'proveedor_id' => $compra->proveedor_id,
            'usuario_id' => $usuarioId,
        ]);
    }

    private function upsertArticuloProveedor(int $articuloId, array $datos, Compra $compra): void
    {
        $vinculo = ArticuloProveedor::firstOrNew([
            'articulo_id' => $articuloId,
            'proveedor_id' => $compra->proveedor_id,
        ]);

        $vinculo->fill([
            'codigo_proveedor' => $datos['codigo_proveedor'] ?? $vinculo->codigo_proveedor,
            'factor_conversion' => $datos['factor_conversion'] ?? $vinculo->factor_conversion ?? 1,
            'descuentos_habituales' => $datos['descuentos'] ?? $vinculo->descuentos_habituales,
            'costo_ultimo' => $datos['costo'],
            'fecha_ultima_compra' => now(),
            'activo' => true,
        ])->save();
    }

    /**
     * Fila de costos con creación segura del consolidado: el UNIQUE de MySQL
     * no impide duplicar (articulo_id, NULL) — por eso este service es la
     * única puerta y busca con lock antes de crear.
     */
    private function filaCostos(int $articuloId, ?int $sucursalId, bool $lock = false): ArticuloCosto
    {
        $query = ArticuloCosto::where('articulo_id', $articuloId)
            ->when(
                $sucursalId === null,
                fn ($q) => $q->whereNull('sucursal_id'),
                fn ($q) => $q->where('sucursal_id', $sucursalId),
            );

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? ArticuloCosto::create([
            'articulo_id' => $articuloId,
            'sucursal_id' => $sucursalId,
        ]);
    }

    /**
     * Lectura con fallback sucursal → consolidada (RF-09).
     */
    private function filaCostosLectura(int $articuloId, ?int $sucursalId): ?ArticuloCosto
    {
        if ($sucursalId !== null) {
            $fila = ArticuloCosto::where('articulo_id', $articuloId)
                ->where('sucursal_id', $sucursalId)
                ->first();

            if ($fila !== null && $fila->costo_ultimo !== null) {
                return $fila;
            }
        }

        return ArticuloCosto::where('articulo_id', $articuloId)
            ->whereNull('sucursal_id')
            ->first();
    }

    private function stockActual(int $articuloId, ?int $sucursalId): float
    {
        $query = Stock::where('articulo_id', $articuloId);

        if ($sucursalId !== null) {
            $query->where('sucursal_id', $sucursalId);
        }

        return (float) $query->sum('cantidad');
    }

    /**
     * D21: el comercio computa IVA si su CUIT emisor es RI. Por sucursal usa
     * el CUIT principal del pivot (fallback: el primero asignado); sin
     * sucursal (consolidado) usa el primer CUIT activo del comercio. Sin CUIT
     * configurado ⇒ NO computa (pricing informal: costo bruto, precio pleno).
     */
    private function comercioComputaIva(?int $sucursalId): bool
    {
        $cuit = null;

        if ($sucursalId !== null) {
            $sucursal = Sucursal::find($sucursalId);
            $cuit = $sucursal?->cuits()->wherePivot('es_principal', true)->first()
                ?? $sucursal?->cuits()->first();
        }

        $cuit ??= Cuit::activos()->first();

        return $cuit?->condicionIva?->codigo === CondicionIva::RESPONSABLE_INSCRIPTO;
    }

    private function porcentajeCambio(?float $anterior, float $nuevo): ?float
    {
        if ($anterior === null || $anterior == 0.0) {
            return null;
        }

        return round(($nuevo - $anterior) / $anterior * 100, 2);
    }
}
