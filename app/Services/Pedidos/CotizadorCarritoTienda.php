<?php

namespace App\Services\Pedidos;

use App\Livewire\Concerns\Carrito\WithAjusteFormaPago;
use App\Livewire\Concerns\Carrito\WithCalculoVenta;
use App\Models\Articulo;
use App\Models\CanalVenta;
use App\Models\FormaPago;
use App\Models\FormaVenta;
use App\Models\ListaPrecio;
use App\Models\Opcional;
use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use App\Services\CuponService;
use Exception;

/**
 * Cotizador server-side del carrito de la TIENDA (RF-11/D12).
 *
 * Harness HEADLESS del MISMO motor de cálculo del sistema
 * (`WithCalculoVenta`: 4 niveles de precio, promociones comunes y
 * especiales, desglose de IVA, cupones) — la tienda NUNCA calcula precios
 * localmente. No es un componente Livewire: define las propiedades que el
 * trait espera y stubea sus hooks de UI (dispatch, ajuste de FP).
 *
 * Bloqueos de API pública (RF-16/RF-17 — el panel advierte, la API bloquea):
 * artículo inexistente/inactivo/no vendible/no visible en tienda/no
 * disponible para el tipo, y agotado sin `permite_venta_sin_stock`.
 *
 * Selección de contexto: forma de venta AUTOMÁTICA por tipo
 * (DELIVERY/TAKEAWAY), canal TIENDA y lista de precios resuelta por
 * `ListaPrecio::buscarListaAplicable` con ese contexto (así aplican las
 * listas condicionadas tipo "Precios Delivery" sin operador que las elija).
 *
 * Forma de pago: si el consumidor la declara, participa del precio con los
 * MISMOS cálculos del panel — promociones y listas condicionadas por FP
 * (contexto del motor) y descuento/recargo por FP (WithAjusteFormaPago, la
 * fuente única compartida con los componentes). `total_a_pagar` = total_final
 * + ajuste FP. Cuotas quedan fuera (pago contra entrega, sin financiación).
 *
 * Puntos: NO participan de la cotización pública v1 (requieren cliente
 * materializado y sesión de consumidor — proyecto tienda).
 */
class CotizadorCarritoTienda
{
    use WithAjusteFormaPago;
    use WithCalculoVenta {
        WithCalculoVenta::calcularVenta as calcularVentaCarrito;
    }

    // ==================== PROPIEDADES QUE EL TRAIT ESPERA ====================

    public array $items = [];

    public ?int $sucursalId = null;

    public ?int $listaPrecioId = null;

    public ?int $formaVentaId = null;

    public ?int $canalVentaId = null;

    public ?int $formaPagoId = null;

    public bool $formaPagoPermiteCuotas = false;

    public ?int $clienteSeleccionado = null;

    public array $listasPreciosDisponibles = [];

    public ?array $resultado = null;

    public ?array $cuponAplicado = null;

    public ?array $cuponInfo = null;

    public array $cuponArticulosBonificados = [];

    public float $cuponMontoDescuento = 0;

    public bool $cuponRecortadoPorCap = false;

    public bool $canjePuntosActivo = false;

    public float $canjePuntosMonto = 0;

    public bool $descuentoGeneralActivo = false;

    public string $descuentoGeneralTipo = 'porcentaje';

    public float $descuentoGeneralValor = 0;

    public float $descuentoGeneralMonto = 0;

    public ?float $descuentoGeneral = null;

    // Propiedades que WithAjusteFormaPago espera (cuotas siempre vacías: la
    // tienda no financia — el pago declarado es contra entrega/retiro).
    public array $ajusteFormaPagoInfo = [];

    public ?int $cuotaSeleccionadaId = null;

    public array $cuotasFormaPagoDisponibles = [];

    public array $infoCuotaSeleccionada = [];

    public array $formasPagoSucursal = [];

    protected CuponService $cuponService;

    public function __construct(CuponService $cuponService)
    {
        $this->cuponService = $cuponService;
    }

    // ==================== STUBS DE HOOKS DEL HOST ====================

    /** Stub Livewire: la cotización no emite eventos de UI. */
    protected function dispatch(...$args): void {}

    /** Stub NuevaVenta: la tienda no factura en la cotización. */
    protected function calcularMontoFacturaFiscal(): void {}

    /**
     * Stub: sin cache de FP de sucursal, WithAjusteFormaPago cae a su fallback
     * de BD (FormaPago + override de FormaPagoSucursal) — el camino headless.
     */
    protected function cargarFormasPagoSucursal(): void {}

    /** Stub: la tienda no ofrece cuotas (pago contra entrega). */
    protected function cargarCuotasFormaPago(): void {}

    // ==================== API ====================

    /**
     * Cotiza el carrito completo para una sucursal/tienda.
     *
     * `$itemsInput` = [['articulo_id' => int, 'cantidad' => num,
     *   'opcionales' => [['opcional_id' => int, 'cantidad' => num], ...]], ...]
     *
     * Devuelve el resultado del motor (items con promos atribuidas, desglose
     * de IVA, totales) + el detalle del cupón aplicado. El costo de envío se
     * cotiza aparte (`/envios/cotizar`) y lo suma el alta del pedido (D17).
     *
     * @throws Exception con mensaje claro ante artículos no pedibles (bloqueo API)
     */
    public function cotizar(
        Sucursal $sucursal,
        string $tipo,
        array $itemsInput,
        ?string $cuponCodigo = null,
        ?int $clienteId = null,
        ?int $formaPagoId = null,
    ): array {
        if (! in_array($tipo, [PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY], true)) {
            throw new Exception("Tipo de pedido inválido: '{$tipo}'");
        }

        if (empty($itemsInput)) {
            throw new Exception('El carrito está vacío');
        }

        $this->sucursalId = (int) $sucursal->id;
        $this->clienteSeleccionado = $clienteId;
        $this->formaVentaId = $this->resolverFormaVentaId($tipo);
        $this->canalVentaId = $this->resolverCanalVentaId();

        // Forma de pago declarada: participa del precio con los MISMOS cálculos
        // del panel (promos/listas condicionadas por FP + ajuste por FP).
        if ($formaPagoId !== null) {
            $formaPago = FormaPago::find($formaPagoId);
            if (! $formaPago || ! $formaPago->esDeclarableEnTienda((int) $sucursal->id)) {
                throw new Exception(__('La forma de pago elegida no está disponible en esta tienda'));
            }
            $this->formaPagoId = (int) $formaPago->id;
        }

        // Lista de precios: el resolutor automático con el contexto de la
        // tienda (aplica listas condicionadas por forma de venta / canal / FP).
        $lista = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [
                'forma_venta_id' => $this->formaVentaId,
                'canal_venta_id' => $this->canalVentaId,
                'forma_pago_id' => $this->formaPagoId,
            ],
            null,
            $clienteId,
        );
        $this->listaPrecioId = $lista?->id;

        $this->items = array_map(
            fn ($item) => $this->construirItem($sucursal, $tipo, $item),
            array_values($itemsInput),
        );

        if ($cuponCodigo !== null && trim($cuponCodigo) !== '') {
            $this->aplicarCuponServerSide(trim($cuponCodigo), $clienteId);
        }

        // calcularVenta invoca calcularAjusteFormaPago() (trait compartido) si
        // hay formaPagoId → el ajuste y el desglose *_con_ajuste_fp quedan en
        // el resultado, igual que en el panel.
        $this->calcularVentaCarrito();

        if (! $this->resultado) {
            throw new Exception('No se pudo calcular el carrito');
        }

        $ajusteMonto = round((float) ($this->ajusteFormaPagoInfo['monto'] ?? 0), 2);

        $resultado = $this->resultado;
        $resultado['lista_precio_id'] = $this->listaPrecioId;
        $resultado['forma_venta_id'] = $this->formaVentaId;
        $resultado['canal_venta_id'] = $this->canalVentaId;
        $resultado['cupon'] = $this->cuponAplicado ? [
            'id' => $this->cuponAplicado['id'] ?? null,
            'codigo' => $this->cuponAplicado['codigo'] ?? null,
            'descripcion' => $this->cuponAplicado['descripcion'] ?? null,
            'descuento' => $this->cuponMontoDescuento,
        ] : null;
        // Contrato aditivo: total_final sigue siendo el total de bienes (sin
        // ajuste FP, paridad con el resultado del panel); total_a_pagar es lo
        // que el consumidor paga con la FP declarada (sin envío, que va aparte).
        $resultado['forma_pago'] = $this->formaPagoId ? [
            'id' => $this->formaPagoId,
            'nombre' => $this->ajusteFormaPagoInfo['nombre'] ?? null,
            'ajuste_porcentaje' => (float) ($this->ajusteFormaPagoInfo['porcentaje'] ?? 0),
            'ajuste_monto' => $ajusteMonto,
        ] : null;
        $resultado['total_a_pagar'] = round((float) ($resultado['total_final'] ?? 0) + $ajusteMonto, 2);

        return $resultado;
    }

    /** Monto del ajuste por FP de la última cotización (para el alta del pedido). */
    public function ajusteFormaPagoMonto(): float
    {
        return round((float) ($this->ajusteFormaPagoInfo['monto'] ?? 0), 2);
    }

    /** Porcentaje del ajuste por FP de la última cotización. */
    public function ajusteFormaPagoPorcentaje(): float
    {
        return (float) ($this->ajusteFormaPagoInfo['porcentaje'] ?? 0);
    }

    /**
     * Items del carrito ya construidos (para que el alta del pedido reuse la
     * MISMA cotización sin recalcular por su cuenta).
     */
    public function itemsCotizados(): array
    {
        return $this->items;
    }

    // ==================== INTERNOS ====================

    protected function resolverFormaVentaId(string $tipo): ?int
    {
        $codigo = $tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'TAKEAWAY' : 'DELIVERY';

        return FormaVenta::where('activo', true)
            ->get(['id', 'codigo'])
            ->first(fn ($f) => strtoupper((string) $f->codigo) === $codigo)
            ?->id;
    }

    protected function resolverCanalVentaId(): ?int
    {
        return CanalVenta::where('activo', true)
            ->get(['id', 'codigo'])
            ->first(fn ($c) => strtoupper((string) $c->codigo) === 'TIENDA')
            ?->id;
    }

    /**
     * Construye un item del carrito validando el criterio de pedibilidad de
     * la API pública (RF-16/RF-17 — acá se BLOQUEA, no se advierte).
     */
    protected function construirItem(Sucursal $sucursal, string $tipo, array $input): array
    {
        $articulo = Articulo::with(['tipoIva', 'categoriaModel'])->find($input['articulo_id'] ?? 0);

        if (! $articulo || ! $articulo->activo) {
            throw new Exception('Artículo no disponible: '.($input['articulo_id'] ?? '?'));
        }

        $pivot = $articulo->sucursales()
            ->where('sucursales.id', $sucursal->id)
            ->first()?->pivot;

        if (! $pivot || ! $pivot->activo || ! $pivot->vendible || ! ($pivot->visible_tienda ?? true)) {
            throw new Exception("'{$articulo->nombre}' no está disponible en la tienda");
        }

        $columnaTipo = $tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'disponible_take_away' : 'disponible_delivery';
        if (! $articulo->{$columnaTipo}) {
            throw new Exception("'{$articulo->nombre}' no está disponible para ".($tipo === 'take_away' ? 'retirar' : 'delivery'));
        }

        $cantidad = (float) ($input['cantidad'] ?? 1);
        if ($cantidad <= 0) {
            throw new Exception("Cantidad inválida para '{$articulo->nombre}'");
        }

        // Agotado (RF-17): visible pero NO pedible por la API.
        if (($pivot->modo_stock ?? 'ninguno') !== 'ninguno' && ! $articulo->permite_venta_sin_stock) {
            $stock = (float) \App\Models\Stock::where('articulo_id', $articulo->id)
                ->where('sucursal_id', $sucursal->id)
                ->value('cantidad');
            if ($stock < $cantidad) {
                throw new Exception("'{$articulo->nombre}' está agotado");
            }
        }

        $precioInfo = $this->obtenerPrecioConLista($articulo);
        $tipoIva = $articulo->tipoIva;

        $opcionales = [];
        $precioOpcionales = 0.0;
        foreach ($input['opcionales'] ?? [] as $opInput) {
            $opcional = Opcional::find($opInput['opcional_id'] ?? 0);
            if (! $opcional || ! $opcional->activo) {
                throw new Exception('Opcional no disponible: '.($opInput['opcional_id'] ?? '?'));
            }
            $cantOp = (float) ($opInput['cantidad'] ?? 1);
            $opcionales[] = [
                'opcional_id' => $opcional->id,
                'descripcion' => $opcional->nombre,
                'precio' => (float) $opcional->precio_extra,
                'cantidad' => $cantOp,
            ];
            $precioOpcionales += (float) $opcional->precio_extra * $cantOp;
        }

        return [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'categoria_id' => $articulo->categoria_id,
            'categoria_nombre' => $articulo->categoriaModel?->nombre,
            'precio_base' => $precioInfo['precio_base'],
            'precio' => $precioInfo['precio'],
            'tiene_ajuste' => $precioInfo['tiene_ajuste'],
            'cantidad' => $cantidad,
            'iva_codigo' => $tipoIva?->codigo ?? 5,
            'iva_porcentaje' => (float) ($tipoIva?->porcentaje ?? 21),
            'iva_nombre' => $tipoIva?->nombre ?? 'IVA 21%',
            'precio_iva_incluido' => (bool) ($articulo->precio_iva_incluido ?? true),
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'ajuste_manual_origen' => null,
            'ajuste_manual_aplicado_por' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => $opcionales,
            'precio_opcionales' => round($precioOpcionales, 2),
            'puntos_canje' => $articulo->puntos_canje,
            'pagado_con_puntos' => false,
        ];
    }

    /**
     * Valida y aplica el cupón con el MISMO service del sistema (D12).
     * Cupón inválido lanza excepción (la tienda muestra el motivo).
     */
    protected function aplicarCuponServerSide(string $codigo, ?int $clienteId): void
    {
        $validacion = $this->cuponService->validarCupon($codigo, $clienteId);

        if (empty($validacion['valid'])) {
            throw new Exception($validacion['message'] ?? __('Cupón inválido'));
        }

        $cupon = $validacion['cupon'];

        // Cupón restringido a formas de pago: con FP declarada se valida acá
        // (mismo criterio que el cobro del POS); sin FP declarada se rechaza
        // si el cupón tiene restricción — la tienda pide elegir la FP primero
        // (no se puede prometer un descuento que después no aplique).
        if ($cupon->tieneRestriccionFormasPago()) {
            if (! $this->formaPagoId) {
                throw new Exception(__('El cupón :code requiere elegir la forma de pago', ['code' => $cupon->codigo]));
            }

            $validacionFP = $this->cuponService->validarFormasPagoCupon($cupon, [$this->formaPagoId]);
            if (empty($validacionFP['valid'])) {
                throw new Exception($validacionFP['message'] ?? __('Cupón inválido para esa forma de pago'));
            }
        }

        $this->cuponAplicado = [
            'id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'descripcion' => $cupon->descripcion,
            'modo_descuento' => $cupon->modo_descuento,
            'valor_descuento' => (float) $cupon->valor_descuento,
        ];
        // El trait lee el id desde cuponInfo (paridad con WithCupones).
        $this->cuponInfo = ['id' => $cupon->id, 'codigo' => $cupon->codigo];
        // El monto exacto lo calcula el trait durante calcularVenta() vía
        // cuponService->calcularDescuento (mismo camino que el POS).
    }
}
