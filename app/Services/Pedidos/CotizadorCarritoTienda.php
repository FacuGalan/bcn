<?php

namespace App\Services\Pedidos;

use App\Livewire\Concerns\Carrito\WithAjusteFormaPago;
use App\Livewire\Concerns\Carrito\WithCalculoVenta;
use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\CanalVenta;
use App\Models\FormaPago;
use App\Models\FormaPagoSucursal;
use App\Models\FormaVenta;
use App\Models\ListaPrecio;
use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use App\Services\CuponService;
use App\Services\PuntosService;
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
 * Puntos: el canje como PAGO (RF-T9) se resuelve FUERA de este cotizador
 * (controller/alta). El canje de ARTÍCULOS (RF-T47) sí entra acá: items con
 * `canjear_con_puntos` se marcan pagado_con_puntos y el motor los resta del
 * total (articulos_canjeados_monto) — el saldo lo valida el caller con
 * puntosUsadosEnArticulos().
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

    /**
     * Set COMPLETO de FP declaradas (multi-pago RF-T18): participa del
     * contexto de promos/listas/cupón. Con 2 FP, `formaPagoId` queda null
     * (el ajuste no sale del camino single-FP sino de desglosarPagos).
     */
    public array $formasPagoIds = [];

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

    /** Override del trait: el contexto de beneficios lleva TODAS las FP declaradas. */
    protected function formasPagoContexto(): array
    {
        return $this->formasPagoIds;
    }

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
        int|array|null $formaPago = null,
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

        // Formas de pago declaradas (una o el set del multi-pago RF-T18):
        // participan del precio con los MISMOS cálculos del panel (promos y
        // listas condicionadas por FP contra TODAS las declaradas + ajuste
        // por FP). Con una sola FP, `formaPagoId` habilita además el camino
        // single-FP del ajuste (WithAjusteFormaPago); con 2, el ajuste sale
        // exclusivamente de desglosarPagos.
        $formasPagoIds = array_values(array_unique(array_map(
            'intval',
            $formaPago === null ? [] : (array) $formaPago,
        )));
        foreach ($formasPagoIds as $fpId) {
            $fp = FormaPago::find($fpId);
            if (! $fp || ! $fp->esDeclarableEnTienda((int) $sucursal->id)) {
                throw new Exception(__('La forma de pago elegida no está disponible en esta tienda'));
            }
        }
        $this->formasPagoIds = $formasPagoIds;
        $this->formaPagoId = count($formasPagoIds) === 1 ? $formasPagoIds[0] : null;

        // Lista de precios: el resolutor automático con el contexto de la
        // tienda (aplica listas condicionadas por forma de venta / canal / FP).
        $lista = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [
                'forma_venta_id' => $this->formaVentaId,
                'canal_venta_id' => $this->canalVentaId,
                'forma_pago_id' => $this->formaPagoId,
                'formas_pago_ids' => $this->formasPagoIds,
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
        // Bloque cupón (aditivo 2026-07-22): `aplica_a` + artículos objetivo y
        // bonificados, para que la tienda pueda explicar un cupón de artículos
        // puntuales que no descuenta porque el artículo no está en el carrito.
        $resultado['cupon'] = $this->cuponAplicado ? [
            'id' => $this->cuponAplicado['id'] ?? null,
            'codigo' => $this->cuponAplicado['codigo'] ?? null,
            'descripcion' => $this->cuponAplicado['descripcion'] ?? null,
            'descuento' => $this->cuponMontoDescuento,
            'aplica_a' => $this->cuponAplicado['aplica_a'] ?? 'total',
            'articulos' => $this->cuponAplicado['articulos'] ?? [],
            'articulos_bonificados' => array_values(array_map('intval', $this->cuponArticulosBonificados)),
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

    /** Máximo de formas de pago declarables por pedido en la tienda (RF-T18 v1). */
    public const MAX_PAGOS_TIENDA = 2;

    /**
     * Desglosa el pago declarado en hasta 2 FP (RF-T18): valida declarabilidad
     * y que los montos cubran el total, y calcula el ajuste de CADA FP sobre
     * su porción de BIENES asignada bienes-primero con tope
     * (AsignadorBasesAjustePagos, RF-03) — la MISMA regla que el panel
     * delivery. El envío (valor fijo, D17) queda fuera de toda base.
     *
     * `$pagosInput` = [['forma_pago_id' => int, 'monto' => num, 'paga_con' => ?num], ...]
     * `$totalACubrir` = lo que las FP deben cubrir SIN sus ajustes
     *   (bienes + envío); los ajustes se SUMAN encima, igual que en el panel.
     * `$costoEnvio` = porción de envío incluida en `$totalACubrir` (excluida
     *   proporcionalmente de la base del ajuste de cada pago).
     *
     * Traslado del ajuste (RF-06): con un pago "resto" (sin monto), los
     * pagos declarados se cobran por su monto exacto y el ajuste que generan
     * (`ajuste_generado`) se aplica al resto (`monto_ajuste` del resto =
     * propio + trasladados). Sin pago resto, cada ajuste aplica sobre su
     * propio pago. Siempre: monto_base + monto_ajuste = monto_final y
     * Σ ajuste_generado = Σ monto_ajuste.
     *
     * @return list<array{forma_pago_id: int, nombre: string, monto_base: float,
     *   ajuste_porcentaje: float, ajuste_generado: float, monto_ajuste: float,
     *   monto_final: float, permite_vuelto: bool, paga_con: float|null, vuelto: float}>
     *
     * @throws Exception con mensaje claro (la API lo devuelve como 422)
     */
    public function desglosarPagos(Sucursal $sucursal, array $pagosInput, float $totalACubrir, float $costoEnvio = 0.0): array
    {
        $pagosInput = array_values($pagosInput);

        if (count($pagosInput) < 1 || count($pagosInput) > self::MAX_PAGOS_TIENDA) {
            throw new Exception(__('Se aceptan hasta :max formas de pago por pedido', ['max' => self::MAX_PAGOS_TIENDA]));
        }

        $ids = array_map(fn ($p) => (int) ($p['forma_pago_id'] ?? 0), $pagosInput);
        if (count(array_unique($ids)) !== count($ids)) {
            throw new Exception(__('No se puede repetir la forma de pago en el desglose'));
        }

        if ($totalACubrir <= 0) {
            throw new Exception(__('No hay monto a pagar para desglosar'));
        }

        // A lo sumo UN pago puede venir sin monto: cubre EL RESTO (así la
        // tienda nunca calcula montos localmente — regla de oro del contrato).
        $sinMonto = array_keys(array_filter($pagosInput, fn ($p) => ! isset($p['monto']) || $p['monto'] === null || $p['monto'] === ''));
        if (count($sinMonto) > 1) {
            throw new Exception(__('Solo una forma de pago puede ir sin monto (cubre el resto)'));
        }

        $sumaExplicitos = round(array_sum(array_map(
            fn ($p) => (float) ($p['monto'] ?? 0),
            $pagosInput,
        )), 2);

        if ($sinMonto !== []) {
            $resto = round($totalACubrir - $sumaExplicitos, 2);
            if ($resto <= 0) {
                throw new Exception(__('Los montos de las formas de pago no suman el total del pedido'));
            }
            $pagosInput[$sinMonto[0]]['monto'] = $resto;
        } elseif (abs($sumaExplicitos - round($totalACubrir, 2)) > 0.05) {
            throw new Exception(__('Los montos de las formas de pago no suman el total del pedido'));
        }

        // Preparación de cada pago (validación + datos de FP); el ajuste se
        // calcula después, con las bases asignadas bienes-primero.
        $pagos = [];
        foreach ($pagosInput as $input) {
            $monto = round((float) ($input['monto'] ?? 0), 2);
            if ($monto <= 0) {
                throw new Exception(__('Cada forma de pago debe tener un monto mayor a cero'));
            }

            $formaPago = FormaPago::find((int) ($input['forma_pago_id'] ?? 0));
            if (! $formaPago || ! $formaPago->esDeclarableEnTienda((int) $sucursal->id)) {
                throw new Exception(__('La forma de pago elegida no está disponible en esta tienda'));
            }

            // Ajuste efectivo: override de sucursal > general (misma regla que
            // WithAjusteFormaPago y formasPagoPublicas).
            $ajustePorcentaje = (float) (FormaPagoSucursal::where('forma_pago_id', $formaPago->id)
                ->where('sucursal_id', (int) $sucursal->id)
                ->value('ajuste_porcentaje') ?? $formaPago->ajuste_porcentaje ?? 0);

            $pagos[] = [
                'forma_pago_id' => (int) $formaPago->id,
                'nombre' => $formaPago->nombre,
                'monto' => $monto,
                'ajuste_porcentaje' => $ajustePorcentaje,
                'permite_vuelto' => (bool) ($formaPago->conceptoPago?->permite_vuelto ?? false),
                'paga_con' => isset($input['paga_con']) ? round((float) $input['paga_con'], 2) : null,
            ];
        }

        // Base del ajuste de cada pago: los BIENES (total sin envío) se
        // asignan bienes-primero con tope (RF-03 — reemplaza al prorrateo
        // proporcional): el descuento de una FP aplica sobre lo que esa FP
        // cubre de mercadería, nunca sobre el envío (valor fijo, D17) ni
        // más allá del total de bienes. Misma regla que el panel delivery.
        $pagos = AsignadorBasesAjustePagos::asignar($pagos, round($totalACubrir - $costoEnvio, 2));

        // Traslado del ajuste al pago RESTO (RF-06, decisión usuario
        // 2026-07-24): un pago con monto DECLARADO se cobra tal cual lo
        // declaró el consumidor ("pago con un billete de $1000" → paga
        // $1000) y el ajuste que GENERA (su % sobre su porción de bienes)
        // se traslada al pago sin monto, que cubre el resto ya ajustado.
        // Sin pago resto (todos declarados), cada ajuste aplica sobre su
        // propio pago (comportamiento histórico). `ajuste_generado` viaja
        // en cada pago para que la tienda explique el origen del descuento.
        $restoIndex = $sinMonto !== [] ? $sinMonto[0] : null;
        $ajusteTrasladado = 0.0;

        foreach ($pagos as $i => $pago) {
            $ajusteGenerado = round($pago['base_ajuste'] * ($pago['ajuste_porcentaje'] / 100), 2) + 0;
            $pagos[$i]['ajuste_generado'] = $ajusteGenerado;

            if ($restoIndex !== null && $i !== $restoIndex) {
                $pagos[$i]['monto_ajuste'] = 0.0;
                $ajusteTrasladado += $ajusteGenerado;
            } else {
                $pagos[$i]['monto_ajuste'] = $ajusteGenerado;
            }
        }

        if ($restoIndex !== null) {
            $ajusteResto = round($pagos[$restoIndex]['monto_ajuste'] + $ajusteTrasladado, 2) + 0;

            // Edge: si el descuento trasladado supera al resto, el resto
            // queda en $0 y el excedente vuelve al (único) pago declarado —
            // nunca un pago negativo.
            $excedente = round($pagos[$restoIndex]['monto'] + $ajusteResto, 2);
            if ($excedente < 0) {
                $ajusteResto = round(-$pagos[$restoIndex]['monto'], 2);
                foreach ($pagos as $i => $pago) {
                    if ($i !== $restoIndex) {
                        $pagos[$i]['monto_ajuste'] = round($pagos[$i]['monto_ajuste'] + $excedente, 2) + 0;
                        break;
                    }
                }
            }

            $pagos[$restoIndex]['monto_ajuste'] = $ajusteResto;
        }

        foreach ($pagos as $i => $pago) {
            $montoFinal = round($pago['monto'] + $pago['monto_ajuste'], 2);

            $pagaCon = $pago['paga_con'];
            if ($pagaCon !== null && ! $pago['permite_vuelto']) {
                $pagaCon = null; // "paga con" solo tiene sentido con efectivo
            }
            if ($pagaCon !== null && $pagaCon > 0 && $pagaCon < $montoFinal) {
                throw new Exception(__('El monto declarado no cubre lo que pagás con :fp', ['fp' => $pago['nombre']]));
            }

            $pagos[$i] = [
                'forma_pago_id' => $pago['forma_pago_id'],
                'nombre' => $pago['nombre'],
                'monto_base' => $pago['monto'],
                'ajuste_porcentaje' => $pago['ajuste_porcentaje'],
                'ajuste_generado' => $pago['ajuste_generado'],
                'monto_ajuste' => round($pago['monto_ajuste'], 2) + 0,
                'monto_final' => $montoFinal,
                'permite_vuelto' => $pago['permite_vuelto'],
                'paga_con' => $pagaCon && $pagaCon > 0 ? $pagaCon : null,
                'vuelto' => $pagaCon && $pagaCon > $montoFinal ? round($pagaCon - $montoFinal, 2) : 0,
            ];
        }

        return $pagos;
    }

    /**
     * Re-prorratea el desglose de IVA de la última cotización con el ajuste
     * COMBINADO del multi-pago (RF-T18) y lo devuelve. Reemplaza el ajuste
     * single-FP que calcularVenta dejó (el método del trait re-deriva desde
     * las bases por alícuota, así que re-llamarlo es seguro).
     */
    public function desgloseIvaConAjuste(float $montoAjuste): ?array
    {
        $this->actualizarDesgloseIvaConAjusteFormaPago($montoAjuste, 0);

        return $this->resultado['desglose_iva'] ?? null;
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

    /**
     * RF-T54: puntos comprometidos por los renglones canjeados de la última
     * cotización — el costo efectivo (matriz RF-T59) que construirItem dejó
     * en el item, por unidad. 0 sin canjes.
     */
    public function puntosUsadosEnArticulos(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            if ($item['pagado_con_puntos'] ?? false) {
                $total += (int) ($item['puntos_canje'] ?? 0) * (int) ($item['cantidad'] ?? 1);
            }
        }

        return $total;
    }

    /**
     * RF-T58: tope en $ del canje-pago cuando la restricción del programa
     * está activa — suma de los renglones HABILITADOS no canjeados (los no
     * habilitados y el envío se pagan con plata). Null = sin restricción
     * (tope = el total, comportamiento histórico).
     */
    public function montoElegibleCanjePago(): ?float
    {
        if (! app(PuntosService::class)->restringeCanjeArticulos()) {
            return null;
        }

        $total = 0.0;
        foreach ($this->items as $item) {
            if (($item['canje_habilitado'] ?? false) && ! ($item['pagado_con_puntos'] ?? false)) {
                $total += (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
            }
        }

        return round($total, 2);
    }

    /** @var float|null|false Cache del valor de canje vigente (false = sin resolver). */
    protected float|null|false $valorPuntoCanjeCache = false;

    /** Valor de canje del programa activo, resuelto UNA vez por cotización. */
    protected function valorPuntoCanjeVigente(Sucursal $sucursal): ?float
    {
        if ($this->valorPuntoCanjeCache === false) {
            $this->valorPuntoCanjeCache = app(PuntosTiendaService::class)->valorPuntoCanjeActivo($sucursal);
        }

        return $this->valorPuntoCanjeCache;
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

        // RF-T54/RF-T59: renglón canjeado por puntos. Solo artículos con el
        // toggle canje_tienda de ESTA sucursal y cantidad 1. Los opcionales
        // con precio YA NO bloquean: el modo canje_opcionales del artículo
        // decide (incluidos / en plata / en puntos). El costo se resuelve
        // más abajo (necesita el precio); el saldo lo valida el caller.
        $canjeado = ! empty($input['canjear_con_puntos']);
        if ($canjeado) {
            if (! ($pivot->canje_tienda ?? false)) {
                throw new Exception("'{$articulo->nombre}' no se puede canjear por puntos en esta tienda");
            }
            if ($cantidad != 1) {
                throw new Exception("El canje por puntos de '{$articulo->nombre}' es de a una unidad");
            }
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
        $opcionesInput = $input['opcionales'] ?? [];
        if ($opcionesInput !== []) {
            // Paridad con el panel (WithOpcionales/obtenerOpcionalesParaVenta):
            // solo valen los opcionales ASIGNADOS al artículo en ESTA sucursal
            // y el precio es el de la asignación (override por artículo), no
            // el del catálogo global. Es también lo que publica el catálogo.
            $asignaciones = ArticuloGrupoOpcional::query()
                ->where('articulo_id', $articulo->id)
                ->where('sucursal_id', $sucursal->id)
                ->where('activo', true)
                ->whereHas('grupoOpcional', fn ($q) => $q->where('activo', true))
                ->with([
                    'grupoOpcional:id,nombre,tipo',
                    'opciones' => fn ($q) => $q->where('activo', true)
                        ->where('disponible', true)
                        ->whereHas('opcional', fn ($q2) => $q2->where('activo', true))
                        ->with('opcional:id,nombre'),
                ])
                ->get();

            // opcional_id => [opción, grupo]; si un opcional está asignado en
            // dos grupos gana el último (mismo criterio que el keyBy previo).
            $opcionesValidas = [];
            foreach ($asignaciones as $asig) {
                foreach ($asig->opciones as $opcion) {
                    $opcionesValidas[$opcion->opcional_id] = ['opcion' => $opcion, 'grupo' => $asig->grupoOpcional];
                }
            }

            // Formato AGRUPADO canónico (paridad WithOpcionales): es el shape
            // que guardarOpcionalesDetalle persiste en
            // pedido_delivery_detalle_opcionales (grupo_opcional_id NOT NULL);
            // una lista plana acá se descarta en silencio al guardar.
            $grupos = [];
            foreach ($opcionesInput as $opInput) {
                $entrada = $opcionesValidas[$opInput['opcional_id'] ?? 0] ?? null;
                if (! $entrada) {
                    throw new Exception("Opcional no disponible para '{$articulo->nombre}': ".($opInput['opcional_id'] ?? '?'));
                }
                $opcion = $entrada['opcion'];
                $grupo = $entrada['grupo'];
                $cantOp = (float) ($opInput['cantidad'] ?? 1);
                $grupos[$grupo->id] ??= [
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => $grupo->tipo,
                    'selecciones' => [],
                ];
                $grupos[$grupo->id]['selecciones'][] = [
                    'opcional_id' => (int) $opcion->opcional_id,
                    'nombre' => $opcion->opcional->nombre,
                    'cantidad' => $cantOp,
                    'precio_extra' => (float) $opcion->precio_extra,
                ];
                $precioOpcionales += (float) $opcion->precio_extra * $cantOp;
            }
            $opcionales = array_values($grupos);
        }

        // RF-T59: costo del canje según la matriz (fijo/derivado × modo de
        // opcionales) — mismo helper del POS. Se resuelve para TODO renglón
        // habilitado (la respuesta lo publica aunque no se canjee, para que
        // la tienda muestre "se canjea por N pts" en el carrito); null = no
        // resoluble (habría que derivar sin programa activo) ⇒ no canjeable.
        $canjeHabilitado = (bool) ($pivot->canje_tienda ?? false);
        $costoCanje = null;
        if ($canjeHabilitado) {
            $costoCanje = app(PuntosService::class)->costoCanjeArticulo(
                (int) $articulo->puntos_canje ?: null,
                $articulo->canje_opcionales,
                (float) $precioInfo['precio'],
                round($precioOpcionales, 2),
                $this->valorPuntoCanjeVigente($sucursal),
            );
        }

        if ($canjeado && $costoCanje === null) {
            throw new Exception("'{$articulo->nombre}' no se puede canjear por puntos en esta tienda");
        }

        return [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'categoria_id' => $articulo->categoria_id,
            'categoria_nombre' => $articulo->categoriaModel?->nombre,
            'precio_base' => $precioInfo['precio_base'],
            // Paridad panel (WithOpcionales): el precio que consume el motor
            // INCLUYE los opcionales; precio_opcionales viaja como desglose.
            'precio' => round((float) $precioInfo['precio'] + $precioOpcionales, 2),
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
            // Costo EFECTIVO del canje por la matriz RF-T59: lo consumen
            // puntosUsadosEnArticulos y el puntos_usados del detalle.
            'puntos_canje' => $canjeado ? $costoCanje['puntos'] : $articulo->puntos_canje,
            // RF-T58/T59: elegibilidad + costo del renglón (aunque no se
            // canjee) para el tope del canje-pago y la respuesta por ítem.
            'canje_habilitado' => $canjeHabilitado,
            'canje_opcionales' => $articulo->canje_opcionales,
            'canje_costo' => $costoCanje,
            // RF-T47: el motor compartido (WithCalculoVenta) resta estos
            // renglones del total como articulos_canjeados_monto — mismo
            // camino del POS/panel (en 'en_plata' solo la parte artículo).
            'pagado_con_puntos' => $canjeado,
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
        // (mismo criterio que el cobro del POS) contra TODAS las FP del pago
        // (multi-pago incluido); sin FP declarada se rechaza si el cupón
        // tiene restricción — la tienda pide elegir la FP primero (no se
        // puede prometer un descuento que después no aplique).
        if ($cupon->tieneRestriccionFormasPago()) {
            if ($this->formasPagoIds === []) {
                throw new Exception(__('El cupón :code requiere elegir la forma de pago', ['code' => $cupon->codigo]));
            }

            $validacionFP = $this->cuponService->validarFormasPagoCupon($cupon, $this->formasPagoIds);
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
            'aplica_a' => $cupon->aplica_a,
            // Nombres de los artículos objetivo: la tienda los usa para avisar
            // "este cupón es para X" cuando ninguno está en el carrito.
            'articulos' => $cupon->aplicaAArticulos()
                ? $cupon->articulos()->pluck('nombre')->values()->all()
                : [],
        ];
        // El trait lee el id desde cuponInfo (paridad con WithCupones).
        $this->cuponInfo = ['id' => $cupon->id, 'codigo' => $cupon->codigo];
        // El monto exacto lo calcula el trait durante calcularVenta() vía
        // cuponService->calcularDescuento (mismo camino que el POS).
    }
}
