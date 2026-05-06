<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Cupon;

/**
 * Cupon de descuento en NuevaVenta.
 *
 * Encapsula:
 * - Validacion del cupon por codigo (vigencia, usos, cliente, articulos elegibles).
 * - Aplicacion del cupon a la venta (calculo de monto descuento, articulos bonificados).
 * - Quitar cupon (restaura estado).
 * - Distribucion del descuento por item para trazabilidad fiscal.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->items                  (WithCarritoItems)
 * - $this->resultado              (NuevaVenta)
 * - $this->clienteSeleccionado    (WithBusquedaClientes)
 * - $this->cuponService           (NuevaVenta — inyectado en boot())
 * - $this->calcularVenta()        (NuevaVenta — ira a WithCalculoVenta)
 */
trait WithCupones
{
    // =========================================
    // PROPIEDADES DE CUPÓN
    // =========================================

    /** @var string Código de cupón ingresado en el modal */
    public string $cuponCodigoInput = '';

    /** @var bool Si hay un cupón aplicado en la venta */
    public bool $cuponAplicado = false;

    /** @var array|null Info del cupón validado para mostrar en UI */
    public ?array $cuponInfo = null;

    /** @var float Monto de descuento del cupón (calculado) */
    public float $cuponMontoDescuento = 0;

    /** @var array IDs de artículos bonificados por el cupón */
    public array $cuponArticulosBonificados = [];

    // =========================================
    // VALIDAR / APLICAR / QUITAR CUPON
    // =========================================

    /**
     * Valida un cupón por código ingresado en el modal.
     */
    public function validarCupon(): void
    {
        $codigo = trim($this->cuponCodigoInput);
        if (empty($codigo)) {
            $this->dispatch('toast-error', message: __('Ingrese un código de cupón'));

            return;
        }

        $resultado = $this->cuponService->validarCupon($codigo, $this->clienteSeleccionado);

        if (! $resultado['valid']) {
            $this->cuponInfo = null;
            $this->dispatch('toast-error', message: $resultado['message']);

            return;
        }

        $cupon = $resultado['cupon'];

        // Calcular descuento preview
        $totalParaCupon = $this->resultado['total_final'] ?? 0;
        $articuloIdsEnCarrito = collect($this->items)->pluck('articulo_id')->filter()->values()->toArray();
        $descuento = $this->cuponService->calcularDescuento($cupon, $totalParaCupon, $articuloIdsEnCarrito);

        // Guardar info para mostrar en UI
        $formasPagoPermitidas = $cupon->formasPago()->pluck('nombre', 'formas_pago.id')->toArray();

        $this->cuponInfo = [
            'id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'tipo' => $cupon->tipo,
            'descripcion' => $cupon->descripcion,
            'modo_descuento' => $cupon->modo_descuento,
            'valor_descuento' => (float) $cupon->valor_descuento,
            'aplica_a' => $cupon->aplica_a,
            'uso_actual' => $cupon->uso_actual,
            'uso_maximo' => $cupon->uso_maximo,
            'fecha_vencimiento' => $cupon->fecha_vencimiento?->format('d/m/Y'),
            'monto_descuento' => $descuento['monto_descuento'],
            'articulos_bonificados' => $descuento['articulos_bonificados'],
            'formas_pago_permitidas' => $formasPagoPermitidas,
        ];

        $this->dispatch('toast-success', message: __('Cupón válido'));
    }

    /**
     * Aplica el cupón validado a la venta actual.
     */
    public function aplicarCupon(): void
    {
        if (! $this->cuponInfo) {
            $this->dispatch('toast-error', message: __('Primero valide un cupón'));

            return;
        }

        // Re-validar por seguridad
        $cupon = Cupon::find($this->cuponInfo['id']);
        if (! $cupon || ! $cupon->estaVigente() || ! $cupon->tieneUsosDisponibles()) {
            $this->cuponInfo = null;
            $this->dispatch('toast-error', message: __('Cupón inválido'));

            return;
        }

        if (! $cupon->puedeSerUsadoPor($this->clienteSeleccionado)) {
            $this->dispatch('toast-error', message: __('Este cupón pertenece a otro cliente'));

            return;
        }

        $this->cuponAplicado = true;

        // Recalcular descuento con artículos bonificados (respetando cantidad del pivot)
        if ($cupon->aplicaAArticulos()) {
            $articulosCupon = $cupon->articulos()->get()->keyBy('id');
            $bonificados = [];
            $itemsParaCalculo = [];

            foreach ($this->items as $item) {
                $articuloId = $item['articulo_id'] ?? null;
                if ($articuloId && $articulosCupon->has($articuloId)) {
                    $bonificados[] = $articuloId;
                    $itemsParaCalculo[] = $item;
                }
            }
            $this->cuponArticulosBonificados = $bonificados;

            $totalParaCupon = $this->resultado['total_final'] ?? 0;
            $descuento = $this->cuponService->calcularDescuento($cupon, $totalParaCupon, $bonificados, $itemsParaCalculo);
            $this->cuponMontoDescuento = $descuento['monto_descuento'];
            $this->cuponInfo['monto_descuento'] = $this->cuponMontoDescuento;
        } else {
            $this->cuponArticulosBonificados = [];
        }

        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Cupón aplicado').": {$cupon->codigo}");
    }

    /**
     * Quita el cupón aplicado de la venta.
     */
    public function quitarCupon(): void
    {
        $this->cuponAplicado = false;
        $this->cuponInfo = null;
        $this->cuponMontoDescuento = 0;
        $this->cuponArticulosBonificados = [];
        $this->cuponCodigoInput = '';

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Cupón eliminado'));
    }

    /**
     * Calcula el descuento del cupón por cada item del carrito para trazabilidad.
     * Retorna array indexado por posición del item.
     */
    private function calcularDescuentoCuponPorItem(): array
    {
        $resultado = [];

        if (! $this->cuponAplicado || ! $this->cuponInfo || $this->cuponMontoDescuento <= 0) {
            return $resultado;
        }

        $cupon = Cupon::find($this->cuponInfo['id']);
        if (! $cupon) {
            return $resultado;
        }

        if ($cupon->aplicaAArticulos()) {
            $articulosCupon = $cupon->articulos()->get()->keyBy('id');
            $montoElegibleTotal = 0;
            $elegiblesPorItem = [];

            // Agrupar índices por articulo_id para respetar cantidad global
            $indicesPorArticulo = [];
            foreach ($this->items as $index => $item) {
                $articuloId = (int) ($item['articulo_id'] ?? 0);
                if (! $articulosCupon->has($articuloId)) {
                    continue;
                }
                $indicesPorArticulo[$articuloId][] = $index;
            }

            // Calcular monto elegible respetando cantidad global por artículo
            foreach ($indicesPorArticulo as $articuloId => $indices) {
                $pivotCantidad = $articulosCupon->get($articuloId)->pivot->cantidad;

                if ($pivotCantidad === null) {
                    // Sin límite: todas las unidades elegibles
                    foreach ($indices as $index) {
                        $item = $this->items[$index];
                        $montoElegible = (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
                        $elegiblesPorItem[$index] = $montoElegible;
                        $montoElegibleTotal += $montoElegible;
                    }
                } else {
                    // Con límite: ordenar por precio DESC para priorizar más caros
                    $indicesOrdenados = $indices;
                    usort($indicesOrdenados, fn ($a, $b) => ((float) ($this->items[$b]['precio'] ?? 0)) <=> ((float) ($this->items[$a]['precio'] ?? 0)));

                    $cantidadRestante = $pivotCantidad;
                    foreach ($indicesOrdenados as $index) {
                        if ($cantidadRestante <= 0) {
                            break;
                        }
                        $item = $this->items[$index];
                        $cantidadEnCarrito = (float) ($item['cantidad'] ?? 1);
                        $cantidadElegible = min($cantidadEnCarrito, $cantidadRestante);
                        $montoElegible = (float) ($item['precio'] ?? 0) * $cantidadElegible;
                        $elegiblesPorItem[$index] = $montoElegible;
                        $montoElegibleTotal += $montoElegible;
                        $cantidadRestante -= $cantidadElegible;
                    }
                }
            }

            if ($montoElegibleTotal <= 0) {
                return $resultado;
            }

            // Distribuir el descuento total proporcionalmente
            foreach ($elegiblesPorItem as $index => $montoElegible) {
                if ($cupon->esPorcentaje()) {
                    $resultado[$index] = round($montoElegible * ((float) $cupon->valor_descuento / 100), 2);
                } else {
                    // Monto fijo: prorratear proporcionalmente
                    $proporcion = $montoElegible / $montoElegibleTotal;
                    $resultado[$index] = round(min((float) $cupon->valor_descuento, $montoElegibleTotal) * $proporcion, 2);
                }
            }
        }
        // Para aplica_a = 'total', no se desglosa por item (queda en ventas.monto_cupon)

        return $resultado;
    }
}
