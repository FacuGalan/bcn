<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ConceptoPago;
use App\Models\CuentaEmpresa;
use App\Models\Cupon;
use App\Models\FormaPago;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\TipoCambio;
use App\Models\VentaPago;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Services\CuentaEmpresaService;
use App\Services\Fiscal\ImpuestoService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * Sistema de pagos con desglose, cuotas, ajustes por forma de pago, fiscal,
 * moneda extranjera y vuelto en NuevaVenta.
 *
 * Encapsula:
 * - Carga de formas de pago de sucursal con sus ajustes especificos.
 * - Cuotas (selector principal + selector dentro del desglose).
 * - Desglose multi-pago con monto pendiente, recalculo de IVA mixto.
 * - Modal de moneda extranjera (cotizacion + monto extranjera + equivalente).
 * - Modal de vuelto (monto recibido + calculo de vuelto).
 * - Factura fiscal por forma de pago (toggle, calculo de monto fiscal/no fiscal).
 * - Punto de venta (seleccion + validaciones).
 * - Iniciar cobro y procesar venta con desglose (insert + ledgers).
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->items, resultado          (WithCarritoItems / NuevaVenta)
 * - $this->formaPagoId, sucursalId   (NuevaVenta / SucursalAware)
 * - $this->cliente*                  (WithBusquedaClientes)
 * - $this->descuentoGeneral*         (WithDescuentos)
 * - $this->cupon*, cuponService      (WithCupones)
 * - $this->canjePuntos*, puntosService, acumularPuntosPostVenta(),
 *   calcularDescuentoCuponPorItem(), cargarSaldoPuntosCliente(),
 *   calcularPuntosUsadosEnArticulos() (WithPuntos / WithCupones)
 * - $this->calcularVenta(), resolverTipoIvaId() (WithCalculoVenta)
 * - $this->ventaService              (NuevaVenta — inyectado en boot())
 * - $this->limpiarCarrito(), dispararEventoImpresion() (NuevaVenta)
 */
trait WithPagosDesglose
{
    // Maquinaria de cobro por QR (props, iniciar/pollear/cancelar/asociar,
    // pantalla cliente). Única fuente de verdad del cobro por integración,
    // compartida con el listado de Pedidos Mostrador.
    use WithCobroIntegracion;

    // =========================================
    // PROPIEDADES DEL SISTEMA DE PAGOS
    // =========================================

    /** @var bool Modal de pago visible */
    public $mostrarModalPago = false;

    /** @var array Desglose de pagos para venta mixta */
    public $desglosePagos = [];

    /** @var float Monto pendiente por asignar en desglose */
    public $montoPendienteDesglose = 0;

    /** @var float Total con ajustes aplicados */
    public $totalConAjustes = 0;

    /** @var array Forma de pago temporal para agregar al desglose */
    public $nuevoPago = [
        'forma_pago_id' => null,
        'monto' => null,
        'cuotas' => 1,
        'monto_recibido' => 0,
        'tipo_cambio_tasa' => null,
        'tipo_cambio_id' => null,
        'monto_moneda_extranjera' => null,
    ];

    /** @var bool Modal simple de pago en moneda extranjera */
    public $mostrarModalMonedaExtranjera = false;

    /** @var array Datos del pago en moneda extranjera (modal simple) */
    public $pagoMonedaExtranjera = [
        'forma_pago_id' => null,
        'nombre' => '',
        'moneda_codigo' => '',
        'moneda_simbolo' => '',
        'moneda_id' => null,
        'cotizacion' => 0,
        'tipo_cambio_id' => null,
        'monto_extranjera' => null,
        'total_venta' => 0,
        'equivalente_principal' => 0,
        'vuelto' => 0,
    ];

    /** @var bool Modal de cobro con vuelto (pago simple en moneda local) */
    public $mostrarModalVuelto = false;

    /** @var array Datos del pago con vuelto */
    public $pagoConVuelto = [
        'forma_pago_id' => null,
        'nombre' => '',
        'total_a_pagar' => 0,
        'monto_recibido' => 0,
        'vuelto' => 0,
    ];

    /** @var array Formas de pago disponibles para la sucursal actual (con ajustes) */
    public $formasPagoSucursal = [];

    /** @var array Cuotas disponibles para la forma de pago seleccionada */
    public $cuotasDisponibles = [];

    /** @var array Información del ajuste de la forma de pago seleccionada */
    public $ajusteFormaPagoInfo = [
        'nombre' => '',
        'porcentaje' => 0,
        'monto' => 0,
        'total_con_ajuste' => 0,
        'es_mixta' => false,
    ];

    // =========================================
    // PROPIEDADES DE CUOTAS (SELECTOR PRINCIPAL)
    // =========================================

    /** @var array Cuotas disponibles para la forma de pago seleccionada en el selector principal */
    public $cuotasFormaPagoDisponibles = [];

    /** @var int|null ID de la cuota seleccionada (null = 1 pago sin cuotas) */
    public $cuotaSeleccionadaId = null;

    /** @var bool Indica si la forma de pago seleccionada permite cuotas */
    public $formaPagoPermiteCuotas = false;

    /** @var array Información de la cuota seleccionada */
    public $infoCuotaSeleccionada = [
        'cantidad_cuotas' => 1,
        'recargo_porcentaje' => 0,
        'recargo_monto' => 0,
        'valor_cuota' => 0,
        'total_con_recargo' => 0,
        'descripcion' => '1 pago',
    ];

    /** @var bool Indica si el selector de cuotas está desplegado */
    public $cuotasSelectorAbierto = false;

    /** @var bool Indica si el selector de cuotas del desglose está desplegado */
    public $cuotasDesgloseSelectorAbierto = false;

    /** @var array Cuotas del desglose con montos calculados */
    public $cuotasDesgloseConMontos = [];

    protected function cargarFormasPagoSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->formasPagoSucursal = [];

            return;
        }

        $formasPago = FormaPago::with(['conceptoPago', 'conceptosPermitidos', 'cuotas'])
            ->where('activo', true)
            ->orderBy('orden')->orderBy('id')
            ->get();

        $this->formasPagoSucursal = $formasPago->map(function ($fp) {
            // Obtener configuración específica de sucursal
            $configSucursal = FormaPagoSucursal::where('forma_pago_id', $fp->id)
                ->where('sucursal_id', $this->sucursalId)
                ->first();

            // Verificar si está activa en la sucursal
            $activaEnSucursal = $configSucursal ? $configSucursal->activo : true;

            if (! $activaEnSucursal) {
                return null;
            }

            // Obtener ajuste (específico de sucursal o general)
            $ajustePorcentaje = $configSucursal && $configSucursal->ajuste_porcentaje !== null
                ? $configSucursal->ajuste_porcentaje
                : $fp->ajuste_porcentaje;

            // Obtener configuración de factura fiscal (específico de sucursal o general)
            $facturaFiscal = $configSucursal && $configSucursal->factura_fiscal !== null
                ? $configSucursal->factura_fiscal
                : ($fp->factura_fiscal ?? false);

            // Obtener cuotas disponibles para la sucursal
            $cuotasDisponibles = [];
            if ($fp->permite_cuotas && ! $fp->es_mixta) {
                foreach ($fp->cuotas as $cuota) {
                    $cuotaSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                        ->where('sucursal_id', $this->sucursalId)
                        ->first();

                    $activa = $cuotaSucursal ? $cuotaSucursal->activo : true;
                    if (! $activa) {
                        continue;
                    }

                    $recargo = $cuotaSucursal && $cuotaSucursal->recargo_porcentaje !== null
                        ? $cuotaSucursal->recargo_porcentaje
                        : $cuota->recargo_porcentaje;

                    $cuotasDisponibles[] = [
                        'id' => $cuota->id,
                        'cantidad' => $cuota->cantidad_cuotas,
                        'recargo' => $recargo,
                        'descripcion' => $cuota->descripcion,
                    ];
                }
            }

            // Datos de moneda para multi-moneda
            $monedaPrincipal = Moneda::obtenerPrincipal();
            $monedaId = $fp->moneda_id ?? $monedaPrincipal?->id;
            $esMonedaExtranjera = $monedaId && $monedaPrincipal && $monedaId != $monedaPrincipal->id;
            $monedaInfo = null;
            $ultimaTasa = null;
            $ultimaTasaId = null;

            if ($esMonedaExtranjera) {
                $monedaObj = Moneda::find($monedaId);
                $monedaInfo = $monedaObj ? [
                    'id' => $monedaObj->id,
                    'codigo' => $monedaObj->codigo,
                    'simbolo' => $monedaObj->simbolo,
                    'nombre' => $monedaObj->nombre,
                ] : null;
                // Snapshot id+tasa: el id permite trazabilidad (quien cargo la cotizacion,
                // cuando) y la tasa es inmutable (sobrevive a edits/borrados del record).
                $snapshot = TipoCambio::obtenerTasaVentaConId($monedaId, $monedaPrincipal->id);
                $ultimaTasa = $snapshot['tasa'] ?? null;
                $ultimaTasaId = $snapshot['id'] ?? null;
            }

            return [
                'id' => $fp->id,
                'nombre' => $fp->nombre,
                'codigo' => $fp->codigo,
                'concepto' => $fp->concepto,
                'concepto_pago_id' => $fp->concepto_pago_id,
                'concepto_nombre' => $fp->conceptoPago?->nombre,
                'es_mixta' => $fp->es_mixta ?? false,
                'permite_cuotas' => $fp->permite_cuotas && ! $fp->es_mixta,
                'ajuste_porcentaje' => $ajustePorcentaje ?? 0,
                'factura_fiscal' => $facturaFiscal,
                'permite_vuelto' => $fp->conceptoPago?->permite_vuelto ?? false,
                'cuotas' => $cuotasDisponibles,
                'conceptos_permitidos' => $fp->es_mixta
                    ? $fp->conceptosPermitidos->map(fn ($c) => [
                        'id' => $c->id,
                        'codigo' => $c->codigo,
                        'nombre' => $c->nombre,
                    ])->toArray()
                    : [],
                'moneda_id' => $monedaId,
                'es_moneda_extranjera' => $esMonedaExtranjera,
                'moneda_info' => $monedaInfo,
                'ultima_tasa' => $ultimaTasa,
                'ultima_tasa_id' => $ultimaTasaId,
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Obtiene el ajuste efectivo para una forma de pago en la sucursal actual
     */
    public function obtenerAjusteFormaPago(int $formaPagoId): float
    {
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', $formaPagoId);

        return $fp ? (float) $fp['ajuste_porcentaje'] : 0;
    }

    // =========================================
    // FACTURACIÓN FISCAL
    // =========================================

    /**
     * Carga la configuración de facturación fiscal de la sucursal actual
     */
    protected function cargarConfiguracionFiscalSucursal(): void
    {
        if (! $this->sucursalId) {
            $this->sucursalFacturaAutomatica = false;

            return;
        }

        $sucursal = Sucursal::find($this->sucursalId);
        $this->sucursalFacturaAutomatica = $sucursal?->facturacion_fiscal_automatica ?? false;
    }

    /**
     * Percepciones fiscales aplicadas a la venta (Fase 5b). Se calcula ANTES de
     * cobrar para que el cliente pague el total con la percepción incluida y el
     * comprobante se emita con el mismo monto (cobrado == facturado). Sólo aplica
     * si la venta va a emitir factura fiscal (gateado por el caller) y el CUIT del
     * PV es agente de percepción frente a un cliente RI; en cualquier otro caso
     * devuelve [] (pyme típica: sin cambios).
     *
     * @param  float  $netoGravado  base gravada (sin IVA) sobre la que se percibe
     * @return array<int,array<string,mixed>> tributos (impuesto_id, codigo_arca, base_imponible, alicuota, monto)
     */
    protected function calcularTributosFiscales(float $netoGravado, ?int $cajaId): array
    {
        if ($netoGravado <= 0 || ! $this->clienteSeleccionado) {
            return [];
        }

        $cliente = Cliente::find($this->clienteSeleccionado);

        if (! $cliente) {
            return [];
        }

        // CUIT y jurisdicción de la operación salen del punto de venta de la caja
        // (el mismo que usará ComprobanteFiscalService al emitir).
        $puntoVenta = $this->puntoVentaSeleccionadoId
            ? PuntoVenta::find($this->puntoVentaSeleccionadoId)
            : Caja::find($cajaId)?->puntoVentaDefecto();

        if (! $puntoVenta || ! $puntoVenta->cuit) {
            return [];
        }

        return app(ImpuestoService::class)->calcularPercepcionesComprobante(
            $puntoVenta->cuit,
            $cliente,
            $netoGravado,
            $puntoVenta->jurisdiccionFiscal(),
            now(),
        );
    }

    /**
     * Actualiza el checkbox de factura fiscal según la forma de pago seleccionada
     * Solo aplica si la sucursal NO tiene facturación automática
     */
    public function actualizarFacturaFiscalSegunFP(): void
    {
        if ($this->sucursalFacturaAutomatica) {
            // Si es automática, el checkbox no se usa (se decide internamente)
            return;
        }

        // Cargar formas de pago si no están cargadas
        if (empty($this->formasPagoSucursal)) {
            $this->cargarFormasPagoSucursal();
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        // Si es mixta, no se puede determinar aún (se decide en el desglose)
        if ($fp && ! $fp['es_mixta']) {
            $this->emitirFacturaFiscal = $fp['factura_fiscal'] ?? false;
        }
    }

    /**
     * Obtiene la configuración de factura fiscal de una forma de pago
     */
    public function obtenerFacturaFiscalFP(int $formaPagoId): bool
    {
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', $formaPagoId);

        return $fp ? (bool) ($fp['factura_fiscal'] ?? false) : false;
    }

    /**
     * Toggle del checkbox de factura fiscal en un pago del desglose
     */
    public function toggleFacturaFiscalDesglose(int $index): void
    {
        if (isset($this->desglosePagos[$index])) {
            $this->desglosePagos[$index]['factura_fiscal'] = ! $this->desglosePagos[$index]['factura_fiscal'];
            $this->calcularMontoFacturaFiscal();
        }
    }

    /**
     * Calcula el monto total que se facturará fiscalmente
     * y recalcula el desglose de IVA correspondiente
     */
    public function calcularMontoFacturaFiscal(): void
    {
        // Si es pago simple (no mixto)
        if (empty($this->desglosePagos) || count($this->desglosePagos) <= 1) {
            if ($this->sucursalFacturaAutomatica) {
                // Automática: usar la config de la FP
                $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);
                $emitir = $fp ? ($fp['factura_fiscal'] ?? false) : false;
            } else {
                // Manual: usar el checkbox
                $emitir = $this->emitirFacturaFiscal;
            }

            if ($emitir && $this->resultado) {
                $this->montoFacturaFiscal = $this->resultado['desglose_iva']['total_con_ajuste_fp']
                    ?? $this->resultado['desglose_iva']['total']
                    ?? $this->resultado['total_final']
                    ?? 0;
                // Formatear el desglose para AFIP (2 decimales, iva = neto * porcentaje)
                $this->formatearDesgloseParaAFIP();
            } else {
                $this->montoFacturaFiscal = 0;
                $this->desgloseIvaFiscal = [];
            }

            $this->aplicarPercepcionFiscal();

            return;
        }

        // Para pagos mixtos: sumar los BIENES (monto_final menos la percepción ya
        // distribuida) de las FP con factura_fiscal = true. La base de la percepción
        // son los bienes, nunca el monto que ya la incluye (evita recursión).
        $montoFiscal = 0;
        foreach ($this->desglosePagos as $pago) {
            if ($pago['factura_fiscal'] ?? false) {
                $montoFiscal += (float) ($pago['monto_final'] ?? $pago['monto_base'] ?? 0) - (float) ($pago['percepcion'] ?? 0);
            }
        }

        $this->montoFacturaFiscal = round($montoFiscal, 2);

        // Recalcular el desglose de IVA proporcionalmente
        if ($this->montoFacturaFiscal > 0 && $this->resultado) {
            $this->recalcularDesgloseIvaFiscal();
        } else {
            $this->desgloseIvaFiscal = [];
        }

        $this->aplicarPercepcionFiscal();
    }

    /**
     * Calcula la percepción fiscal (Fase 5b) sobre la base gravada de la porción
     * que se va a facturar y la suma a `montoFacturaFiscal`. Deja el monto y el
     * detalle en `percepcionMonto`/`percepcionTributos` para mostrarlos en el
     * resumen y el modal de cobro (el cliente paga el total con la percepción) y
     * para pasarlos al comprobante (cobrado == facturado). Sin factura / sin
     * agente / cliente no-RI → percepción 0 (pyme típica: sin cambios).
     */
    protected function aplicarPercepcionFiscal(): void
    {
        $this->percepcionTributos = [];
        $this->percepcionMonto = 0;

        if (empty($this->desgloseIvaFiscal) || ($this->montoFacturaFiscal ?? 0) <= 0) {
            return;
        }

        $netoGravado = (float) ($this->desgloseIvaFiscal['total_neto'] ?? 0);
        $cajaId = $this->cajaSeleccionada ?? caja_activa();

        $this->percepcionTributos = $this->calcularTributosFiscales($netoGravado, $cajaId);
        $this->percepcionMonto = round(array_sum(array_column($this->percepcionTributos, 'monto')), 2);

        // La percepción se suma al monto a facturar (y por ende al total cobrado):
        // ImpTotal = neto + IVA (bienes) + ImpTrib (percepción).
        if ($this->percepcionMonto > 0) {
            $this->montoFacturaFiscal = round($this->montoFacturaFiscal + $this->percepcionMonto, 2);
        }

        // Distribuir la percepción en el monto_final de los pagos fiscales del
        // desglose, para que cada medio cobre el monto correcto (con percepción).
        $this->distribuirPercepcionEnDesglose();
    }

    /**
     * Distribuye la percepción fiscal (Fase 5b) en el `monto_final` de los pagos
     * marcados como fiscales, EN EL DESGLOSE (antes de cobrar). Así el monto que
     * se cobra por cada medio (efectivo, MercadoPago, QR…) YA incluye la percepción
     * que le corresponde: lo que se envía al proveedor == lo que registra el
     * sistema == lo que se factura. Reparte proporcional a los bienes de cada pago.
     *
     * Idempotente: primero descuenta la percepción previa (`percepcion` por pago)
     * para reconstruir la base de bienes, luego reparte la percepción actual. Se
     * llama cada vez que cambia la composición fiscal del desglose.
     */
    protected function distribuirPercepcionEnDesglose(): void
    {
        if (empty($this->desglosePagos)) {
            return;
        }

        // 1) Revertir la percepción previa → monto_final vuelve a bienes+ajuste.
        foreach ($this->desglosePagos as $i => $pago) {
            $previa = (float) ($pago['percepcion'] ?? 0);
            if ($previa !== 0.0) {
                $this->desglosePagos[$i]['monto_final'] = round((float) ($pago['monto_final'] ?? 0) - $previa, 2);
            }
            $this->desglosePagos[$i]['percepcion'] = 0.0;
        }

        $percepcion = round($this->percepcionMonto ?? 0, 2);
        if ($percepcion <= 0) {
            return;
        }

        // 2) Repartir la percepción entre los pagos fiscales (proporcional a bienes).
        $fiscales = array_values(array_keys(array_filter(
            $this->desglosePagos,
            fn ($p) => (bool) ($p['factura_fiscal'] ?? false)
        )));

        if (empty($fiscales)) {
            return; // defensivo: no hay pago fiscal donde imputar
        }

        $baseTotal = array_sum(array_map(fn ($i) => (float) ($this->desglosePagos[$i]['monto_final'] ?? 0), $fiscales));
        $asignado = 0.0;
        $ultimo = count($fiscales) - 1;

        foreach ($fiscales as $pos => $i) {
            if ($pos === $ultimo) {
                $cuota = round($percepcion - $asignado, 2); // el último absorbe el redondeo
            } else {
                $cuota = $baseTotal > 0
                    ? round($percepcion * ((float) $this->desglosePagos[$i]['monto_final'] / $baseTotal), 2)
                    : 0.0;
                $asignado += $cuota;
            }

            $this->desglosePagos[$i]['percepcion'] = $cuota;
            $this->desglosePagos[$i]['monto_final'] = round((float) ($this->desglosePagos[$i]['monto_final'] ?? 0) + $cuota, 2);
        }
    }

    /**
     * Formatea el desglose de IVA para cumplir con AFIP
     * Para pagos simples donde se factura el total (sin prorrateo)
     */
    protected function formatearDesgloseParaAFIP(): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        $desgloseOriginal = $this->resultado['desglose_iva'];

        // Formatear cada alícuota para AFIP
        // IMPORTANTE: IVA debe ser = neto * porcentaje / 100 exactamente
        $porAlicuota = [];
        $totalNeto = 0;
        $totalIva = 0;

        foreach ($desgloseOriginal['por_alicuota'] ?? [] as $alicuota) {
            if (! is_array($alicuota)) {
                continue;
            }

            $porcentaje = $alicuota['alicuota'] ?? $alicuota['porcentaje'] ?? 21;

            // Redondear neto a 2 decimales
            $netoAlicuota = round($alicuota['neto'] ?? 0, 2);

            // AFIP requiere que IVA = neto * porcentaje / 100 exactamente
            $ivaAlicuota = round($netoAlicuota * ($porcentaje / 100), 2);

            $porAlicuota[] = [
                'alicuota' => $porcentaje,
                'neto' => $netoAlicuota,
                'iva' => $ivaAlicuota,
                'subtotal' => round($netoAlicuota + $ivaAlicuota, 2),
            ];

            $totalNeto += $netoAlicuota;
            $totalIva += $ivaAlicuota;
        }

        // Verificar si hay diferencia por redondeo con el total a facturar
        $sumaCalculada = round($totalNeto + $totalIva, 2);
        $diferencia = round($this->montoFacturaFiscal - $sumaCalculada, 2);

        // Si hay diferencia, ajustar el neto de la última alícuota
        if ($diferencia != 0 && ! empty($porAlicuota)) {
            $lastIndex = count($porAlicuota) - 1;
            $porcentajeUltimo = $porAlicuota[$lastIndex]['alicuota'];

            // Ajustar el neto para que neto + iva = total
            $nuevoSubtotal = $porAlicuota[$lastIndex]['subtotal'] + $diferencia;
            $nuevoNeto = round($nuevoSubtotal / (1 + $porcentajeUltimo / 100), 2);
            $nuevoIva = round($nuevoNeto * ($porcentajeUltimo / 100), 2);

            // Recalcular totales
            $totalNeto = $totalNeto - $porAlicuota[$lastIndex]['neto'] + $nuevoNeto;
            $totalIva = $totalIva - $porAlicuota[$lastIndex]['iva'] + $nuevoIva;

            $porAlicuota[$lastIndex]['neto'] = $nuevoNeto;
            $porAlicuota[$lastIndex]['iva'] = $nuevoIva;
            $porAlicuota[$lastIndex]['subtotal'] = round($nuevoNeto + $nuevoIva, 2);
        }

        $this->desgloseIvaFiscal = [
            'por_alicuota' => $porAlicuota,
            'total_neto' => round($totalNeto, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($this->montoFacturaFiscal, 2),
        ];
    }

    /**
     * Recalcula el desglose de IVA para la factura fiscal (proporcional al monto fiscal)
     */
    protected function recalcularDesgloseIvaFiscal(): void
    {
        if (! $this->resultado || ! isset($this->resultado['desglose_iva'])) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        $desgloseOriginal = $this->resultado['desglose_iva'];
        $totalOriginal = $desgloseOriginal['total_con_ajuste_fp']
            ?? $desgloseOriginal['total']
            ?? $this->resultado['total_final']
            ?? 0;

        if ($totalOriginal <= 0) {
            $this->desgloseIvaFiscal = [];

            return;
        }

        // Proporción del monto fiscal sobre el total
        $proporcion = $this->montoFacturaFiscal / $totalOriginal;

        // Recalcular cada alícuota proporcionalmente
        // IMPORTANTE: Para cumplir con AFIP, IVA debe ser = neto * porcentaje / 100
        $porAlicuota = [];
        $totalNeto = 0;
        $totalIva = 0;

        foreach ($desgloseOriginal['por_alicuota'] ?? [] as $alicuota) {
            // Verificar que el array tenga la estructura esperada
            if (! is_array($alicuota)) {
                continue;
            }

            $porcentaje = $alicuota['alicuota'] ?? $alicuota['porcentaje'] ?? 21;

            // Calcular neto proporcional (redondeado a 2 decimales)
            $netoAlicuota = round(($alicuota['neto'] ?? 0) * $proporcion, 2);

            // AFIP requiere que IVA = neto * porcentaje / 100 exactamente
            $ivaAlicuota = round($netoAlicuota * ($porcentaje / 100), 2);

            $porAlicuota[] = [
                'alicuota' => $porcentaje,
                'neto' => $netoAlicuota,
                'iva' => $ivaAlicuota,
                'subtotal' => round($netoAlicuota + $ivaAlicuota, 2),
            ];

            $totalNeto += $netoAlicuota;
            $totalIva += $ivaAlicuota;
        }

        // Verificar si hay diferencia por redondeo con el total a facturar
        $sumaCalculada = round($totalNeto + $totalIva, 2);
        $diferencia = round($this->montoFacturaFiscal - $sumaCalculada, 2);

        // Si hay diferencia, ajustar el neto de la última alícuota
        if ($diferencia != 0 && ! empty($porAlicuota)) {
            $lastIndex = count($porAlicuota) - 1;
            $porcentajeUltimo = $porAlicuota[$lastIndex]['alicuota'];

            // Ajustar el neto para que neto + iva = total
            // Si hay diferencia D, y tenemos neto + iva = subtotal
            // Necesitamos nuevo_neto + nuevo_iva = subtotal + D
            // Con nuevo_iva = nuevo_neto * p/100
            // nuevo_neto * (1 + p/100) = subtotal + D
            // nuevo_neto = (subtotal + D) / (1 + p/100)
            $nuevoSubtotal = $porAlicuota[$lastIndex]['subtotal'] + $diferencia;
            $nuevoNeto = round($nuevoSubtotal / (1 + $porcentajeUltimo / 100), 2);
            $nuevoIva = round($nuevoNeto * ($porcentajeUltimo / 100), 2);

            // Recalcular totales
            $totalNeto = $totalNeto - $porAlicuota[$lastIndex]['neto'] + $nuevoNeto;
            $totalIva = $totalIva - $porAlicuota[$lastIndex]['iva'] + $nuevoIva;

            $porAlicuota[$lastIndex]['neto'] = $nuevoNeto;
            $porAlicuota[$lastIndex]['iva'] = $nuevoIva;
            $porAlicuota[$lastIndex]['subtotal'] = round($nuevoNeto + $nuevoIva, 2);
        }

        $this->desgloseIvaFiscal = [
            'por_alicuota' => $porAlicuota,
            'total_neto' => round($totalNeto, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($this->montoFacturaFiscal, 2),
        ];
    }

    // =========================================
    // SELECCIÓN DE PUNTO DE VENTA FISCAL
    // =========================================

    /**
     * Verifica si el usuario puede y debe seleccionar un punto de venta para facturación
     * Retorna true si:
     * - El usuario tiene el permiso 'func.seleccion_cuit'
     * - La caja actual tiene más de un punto de venta configurado
     */
    protected function debeSeleccionarPuntoVenta(): bool
    {
        $cajaId = $this->cajaSeleccionada ?? caja_activa();
        if (! $cajaId) {
            return false;
        }

        // Verificar permiso del usuario
        $user = Auth::user();
        if (! $user || ! $user->hasPermissionTo('func.seleccion_cuit')) {
            return false;
        }

        // Verificar si la caja tiene múltiples puntos de venta
        $caja = Caja::find($cajaId);
        if (! $caja) {
            return false;
        }

        $cantidadPV = $caja->puntosVenta()->count();

        return $cantidadPV > 1;
    }

    /**
     * Carga los puntos de venta disponibles para la caja actual
     */
    protected function cargarPuntosVentaDisponibles(): void
    {
        $cajaId = $this->cajaSeleccionada ?? caja_activa();
        if (! $cajaId) {
            $this->puntosVentaDisponibles = [];

            return;
        }

        $caja = Caja::find($cajaId);
        if (! $caja) {
            $this->puntosVentaDisponibles = [];

            return;
        }

        // Obtener puntos de venta con información del CUIT
        $puntosVenta = $caja->puntosVenta()
            ->with('cuit')
            ->get()
            ->map(function ($pv) {
                return [
                    'id' => $pv->id,
                    'numero' => $pv->numero,
                    'nombre' => $pv->nombre,
                    'numero_formateado' => str_pad($pv->numero, 5, '0', STR_PAD_LEFT),
                    'cuit_numero' => $pv->cuit?->numero_cuit ?? 'Sin CUIT',
                    'cuit_razon_social' => $pv->cuit?->razon_social ?? '',
                    'es_defecto' => $pv->pivot->es_defecto ?? false,
                ];
            })
            ->toArray();

        $this->puntosVentaDisponibles = $puntosVenta;

        // Preseleccionar el punto de venta por defecto
        $pvDefecto = collect($puntosVenta)->firstWhere('es_defecto', true);
        $this->puntoVentaSeleccionadoId = $pvDefecto['id'] ?? ($puntosVenta[0]['id'] ?? null);
    }

    /**
     * Muestra el modal de selección de punto de venta
     */
    public function mostrarSeleccionPuntoVenta(): void
    {
        $this->cargarPuntosVentaDisponibles();
        $this->showPuntoVentaModal = true;
    }

    /**
     * Confirma la selección del punto de venta y continúa con la venta
     */
    public function confirmarPuntoVenta(): void
    {
        if (! $this->puntoVentaSeleccionadoId) {
            $this->dispatch('toast-error', message: 'Seleccione un punto de venta');

            return;
        }

        $this->showPuntoVentaModal = false;

        // Continuar con el procesamiento de la venta
        $this->procesarVentaConDesglose();
    }

    /**
     * Cancela la selección de punto de venta y vuelve a la venta
     */
    public function cancelarSeleccionPuntoVenta(): void
    {
        // Solo cerrar el modal sin procesar nada
        $this->showPuntoVentaModal = false;
        $this->puntoVentaSeleccionadoId = null;
    }

    /**
     * Inicia el proceso de cobro
     * Para pagos simples: procesa directamente
     * Para pagos mixtos: si hay desglose completo procesa, sino abre modal
     */
    public function iniciarCobro(): void
    {
        if (empty($this->items) || ! $this->resultado) {
            $this->dispatch('toast-error', message: 'El carrito está vacío');

            return;
        }

        // Venta/pedido totalmente invitado (cortesia): no hay nada que cobrar,
        // saltamos validacion de FP, modal de vuelto, modal de desglose y modal
        // de moneda extranjera. El host (NuevaVenta o NuevoPedidoMostrador) sabe
        // como persistir sin pagos a través de `confirmarInvitacionTotal`.
        // `esInvitacionTotal` es un computed property (getter) del trait
        // WithInvitaciones — no se puede verificar con property_exists, se chequea
        // con method_exists sobre getEsInvitacionTotalProperty.
        $totalFinalActual = (float) ($this->resultado['total_final'] ?? 0);
        if (method_exists($this, 'getEsInvitacionTotalProperty')
            && $this->esInvitacionTotal
            && $totalFinalActual <= 0.005
            && method_exists($this, 'confirmarInvitacionTotal')) {
            $this->confirmarInvitacionTotal();

            return;
        }

        if (! $this->formaPagoId) {
            $this->dispatch('toast-error', message: 'Seleccione una forma de pago');

            return;
        }

        // Si es mixta
        if ($this->ajusteFormaPagoInfo['es_mixta']) {
            // Si ya hay un desglose completo, verificar y procesar
            if ($this->desgloseCompleto()) {
                $this->verificarPuntoVentaYProcesar();

                return;
            }
            // Si no, abrir modal para desglosar
            if (! $this->mostrarModalPago) {
                $this->abrirModalDesglose();
            }

            return;
        }

        // Para pagos simples: preparar el desglose y procesar directamente
        $this->cargarFormasPagoSucursal();
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->formaPagoId);

        if (! $fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');

            return;
        }

        // Si es moneda extranjera, abrir modal simple dedicado
        if ($fp['es_moneda_extranjera'] ?? false) {
            $totalVenta = $this->resultado['total_final'] ?? 0;
            $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
            $montoAjuste = round($totalVenta * ($ajuste / 100), 2);
            // El total a pagar incluye la percepción fiscal (Fase 5b): se inyecta en
            // el pago al procesar, pero el cliente la paga ahora → mostrarla en el modal.
            $totalConAjuste = round($totalVenta + $montoAjuste + ($this->percepcionMonto ?? 0), 2);

            $this->pagoMonedaExtranjera = [
                'forma_pago_id' => $fp['id'],
                'nombre' => $fp['nombre'],
                'moneda_codigo' => $fp['moneda_info']['codigo'] ?? '',
                'moneda_simbolo' => $fp['moneda_info']['simbolo'] ?? '',
                'moneda_id' => $fp['moneda_id'],
                'cotizacion' => $fp['ultima_tasa'] ?? 0,
                // Snapshot id+tasa al abrir el modal: si entre abrir y confirmar
                // se carga una cotizacion nueva, el pago igual queda atado a la
                // que el cajero efectivamente vio en pantalla.
                'tipo_cambio_id' => $fp['ultima_tasa_id'] ?? null,
                'monto_extranjera' => null,
                'total_venta' => $totalConAjuste,
                'ajuste_porcentaje' => $ajuste,
                'equivalente_principal' => 0,
                'vuelto' => 0,
            ];
            $this->mostrarModalMonedaExtranjera = true;

            return;
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
        $montoAjuste = $this->ajusteFormaPagoInfo['monto'];
        // monto_final del PAGO = bienes + ajuste FP (la percepción se inyecta en los
        // pagos fiscales al procesar). El TOTAL A PAGAR que ve/paga el cliente sí
        // incluye la percepción (Fase 5b) → sobre él se calcula el vuelto.
        $montoFinal = $this->ajusteFormaPagoInfo['total_con_ajuste'] ?? 0;
        $totalAPagar = round($montoFinal + ($this->percepcionMonto ?? 0), 2);

        // Si permite vuelto y NO es cuenta corriente, abrir modal de cobro con vuelto
        $permiteVuelto = $fp['permite_vuelto'] ?? false;
        $esCuentaCorriente = isset($fp['codigo']) && strtoupper($fp['codigo']) === 'CTA_CTE';

        if ($permiteVuelto && ! $esCuentaCorriente) {
            $this->pagoConVuelto = [
                'forma_pago_id' => $fp['id'],
                'nombre' => $fp['nombre'],
                'total_a_pagar' => $totalAPagar,
                'monto_recibido' => $totalAPagar,
                'vuelto' => 0,
            ];
            $this->mostrarModalVuelto = true;

            return;
        }

        // Obtener información de cuotas si hay seleccionada
        $cantidadCuotas = $this->ajusteFormaPagoInfo['cuotas'] ?? 1;
        $recargoCuotas = $this->ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0;

        // Crear desglose con un solo pago (incluyendo info de cuotas)
        $this->crearDesglosePagoSimple($fp, $totalBase, $ajuste, $montoAjuste, $montoFinal, $cantidadCuotas, $recargoCuotas, $esCuentaCorriente);
    }

    // =========================================
    // COBRO CON INTEGRACIÓN (QR presencial — Fase 5)
    // La maquinaria del QR vive en WithCobroIntegracion. Acá quedan solo las
    // piezas específicas del desglose: detección del pago integrado, enganche y
    // los hooks que el concern delega en el host.
    // =========================================

    /**
     * La pantalla cliente usa la caja del puesto. En los flujos con desglose la
     * caja puede estar seleccionada explícitamente (NuevaVenta / pedido).
     */
    protected function cajaIdParaPantallaCliente(): ?int
    {
        return $this->cajaSeleccionada ?? caja_activa();
    }

    /**
     * Hook del concern: al aprobarse el cobro QR, retomamos el flujo común de
     * procesamiento. Con cobroIntegracionConfirmado=true el guard ya deja pasar
     * y se materializa la venta/pedido.
     */
    protected function alConfirmarCobroIntegracion(): void
    {
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Devuelve el primer pago del desglose cuya forma de pago tiene una
     * integración configurada (QR), o null si ninguno la tiene.
     *
     * Es el disparador del flujo de cobro QR: lo consulta el enganche del
     * procesamiento (verificarPuntoVentaYProcesar) — pendiente de conectar —
     * para decidir si hay que esperar un pago externo antes de materializar.
     */
    protected function desglosePagoConIntegracion(): ?array
    {
        foreach ($this->desglosePagos as $pago) {
            $formaPago = FormaPago::find($pago['forma_pago_id'] ?? null);
            if ($formaPago && $formaPago->tieneIntegracion()) {
                return $pago;
            }
        }

        return null;
    }

    /**
     * Punto ÚNICO del enganche de cobro por integración (QR), compartido por
     * todos los flujos de cobro con desglose. Si algún pago usa una FP con
     * integración y todavía no se confirmó, dispara el cobro por QR y devuelve
     * true para que el caller aborte su flujo y espere al polling.
     *
     * Lo invocan:
     * - verificarPuntoVentaYProcesar() → NuevaVenta y la reanudación post-pago.
     * - NuevoPedidoMostrador::confirmarPago() → cobro del pedido y cobro rápido
     *   desde el listado (su modal procesa directo, sin pasar por iniciarCobro).
     *
     * Cualquier cambio a la lógica de cobro por integración debe vivir acá para
     * que impacte en TODOS los puntos de cobro por igual.
     */
    protected function interceptarCobroPorIntegracion(): bool
    {
        if ($this->cobroIntegracionConfirmado) {
            return false;
        }

        $pagoIntegracion = $this->desglosePagoConIntegracion();
        if ($pagoIntegracion === null) {
            return false;
        }

        // Cerrar el modal de desglose para que no quede detrás del de espera.
        // En NuevaVenta ya está cerrado (se confirma antes de Cobrar); en el
        // cobro rápido de pedidos el desglose es el punto de entrada y sigue
        // abierto, así que lo cerramos acá para no superponer dos modales.
        $this->mostrarModalPago = false;

        // El concern resuelve la integración por sucursal; le pasamos el contexto
        // (sucursal/caja) explícito porque el cobro nace acá, en el desglose.
        $this->iniciarCobroIntegracion(array_merge($pagoIntegracion, [
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaSeleccionada ?? caja_activa(),
        ]));

        return true;
    }

    /**
     * Verifica si se debe mostrar el modal de selección de punto de venta
     * antes de procesar la venta. Si no es necesario, procesa directamente.
     */
    protected function verificarPuntoVentaYProcesar(): void
    {
        // Enganche cobro QR (Fase 5): si algún pago del desglose usa una forma
        // de pago con integración y todavía no se confirmó, disparamos el cobro
        // por QR y esperamos (modelo "cobro primero, venta después"). Cuando el
        // polling confirma, vuelve a entrar acá con cobroIntegracionConfirmado=true
        // y este guard ya no aplica → se materializa la venta/pedido normalmente.
        if ($this->interceptarCobroPorIntegracion()) {
            return;
        }

        // Determinar si se va a generar factura fiscal
        $sucursal = Sucursal::find($this->sucursalId);
        if (! $sucursal) {
            $this->procesarVentaConDesglose();

            return;
        }

        $comprobanteFiscalService = new ComprobanteFiscalService;
        $debeFacturarAutomatico = $comprobanteFiscalService->debeGenerarFacturaFiscal($sucursal, $this->desglosePagos);
        $debeFacturarManual = $this->emitirFacturaFiscal;
        $debeFacturarDesglose = collect($this->desglosePagos)->contains('factura_fiscal', true);
        $debeFacturar = $debeFacturarAutomatico || $debeFacturarManual || $debeFacturarDesglose;

        // Si se va a facturar Y el usuario puede seleccionar punto de venta → mostrar modal
        if ($debeFacturar && $this->debeSeleccionarPuntoVenta()) {
            $this->mostrarSeleccionPuntoVenta();

            return;
        }

        // Si no se necesita selección, procesar directamente
        $this->procesarVentaConDesglose();
    }

    /**
     * Resetea el formulario de nuevo pago
     */
    protected function resetNuevoPago(): void
    {
        $this->nuevoPago = [
            'forma_pago_id' => null,
            'monto' => null,
            'cuotas' => 1,
            'monto_recibido' => 0,
            'tipo_cambio_tasa' => null,
            'tipo_cambio_id' => null,
            'monto_moneda_extranjera' => null,
        ];
        $this->cuotasDisponibles = [];
        $this->cuotasDesgloseConMontos = [];
        $this->cuotasDesgloseSelectorAbierto = false;
    }

    /**
     * Cuando cambia la forma de pago en el nuevo pago
     */
    public function updatedNuevoPagoFormaPagoId($value): void
    {
        if (! $value) {
            $this->cuotasDisponibles = [];
            $this->cuotasDesgloseConMontos = [];
            $this->cuotasDesgloseSelectorAbierto = false;
            $this->nuevoPago['tipo_cambio_tasa'] = null;
            $this->nuevoPago['monto_moneda_extranjera'] = null;

            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $value);
        $this->cuotasDisponibles = $fp ? $fp['cuotas'] : [];
        $this->nuevoPago['cuotas'] = 1;
        $this->cuotasDesgloseSelectorAbierto = false;

        // Pre-cargar tipo de cambio si es moneda extranjera
        if ($fp && ($fp['es_moneda_extranjera'] ?? false)) {
            $this->nuevoPago['tipo_cambio_tasa'] = $fp['ultima_tasa'];
            $this->nuevoPago['tipo_cambio_id'] = $fp['ultima_tasa_id'] ?? null;
            $this->nuevoPago['monto_moneda_extranjera'] = null;
        } else {
            $this->nuevoPago['tipo_cambio_tasa'] = null;
            $this->nuevoPago['tipo_cambio_id'] = null;
            $this->nuevoPago['monto_moneda_extranjera'] = null;
        }

        $this->calcularCuotasDesglose();
    }

    /**
     * Cuando cambia el monto en el nuevo pago, recalcular cuotas
     */
    public function updatedNuevoPagoMonto($value): void
    {
        $this->calcularCuotasDesglose();
    }

    /**
     * Toggle del selector de cuotas del desglose
     */
    public function toggleCuotasDesgloseSelector(): void
    {
        $this->cuotasDesgloseSelectorAbierto = ! $this->cuotasDesgloseSelectorAbierto;
    }

    /**
     * Selecciona una cuota en el desglose
     */
    public function seleccionarCuotaDesglose($cantidadCuotas): void
    {
        $this->nuevoPago['cuotas'] = (int) $cantidadCuotas;
        $this->cuotasDesgloseSelectorAbierto = false;
    }

    /**
     * Calcula las cuotas del desglose con montos basados en el monto ingresado
     */
    protected function calcularCuotasDesglose(): void
    {
        $this->cuotasDesgloseConMontos = [];

        if (empty($this->cuotasDisponibles)) {
            return;
        }

        $monto = (float) ($this->nuevoPago['monto'] ?? 0);
        if ($monto <= 0) {
            $monto = $this->montoPendienteDesglose;
        }

        // Obtener ajuste de la forma de pago
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        $ajusteFp = $fp ? ($fp['ajuste_porcentaje'] ?? 0) : 0;
        $montoConAjusteFp = round($monto + ($monto * $ajusteFp / 100), 2);

        foreach ($this->cuotasDisponibles as $cuota) {
            $cantCuotas = $cuota['cantidad'];
            $recargo = $cuota['recargo'] ?? 0;

            // Calcular recargo sobre el monto con ajuste de forma de pago
            $recargoMonto = round($montoConAjusteFp * ($recargo / 100), 2);
            $totalConRecargo = round($montoConAjusteFp + $recargoMonto, 2);
            $valorCuota = $cantCuotas > 0 ? round($totalConRecargo / $cantCuotas, 2) : 0;

            $this->cuotasDesgloseConMontos[] = [
                'cantidad' => $cantCuotas,
                'recargo' => $recargo,
                'recargo_monto' => $recargoMonto,
                'valor_cuota' => $valorCuota,
                'total_con_recargo' => $totalConRecargo,
                'descripcion' => $cuota['descripcion'] ?? null,
            ];
        }
    }

    /**
     * Agrega una forma de pago al desglose
     */
    public function agregarAlDesglose(): void
    {
        if (! $this->nuevoPago['forma_pago_id']) {
            $this->dispatch('toast-error', message: 'Seleccione una forma de pago');

            return;
        }

        // Si el monto está vacío o es 0, usar el monto pendiente
        $monto = $this->nuevoPago['monto'];
        if ($monto === null || $monto === '' || (float) $monto <= 0) {
            $monto = $this->montoPendienteDesglose;
        }
        $monto = (float) $monto;

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: 'No hay monto pendiente para agregar');

            return;
        }

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->nuevoPago['forma_pago_id']);
        if (! $fp) {
            $this->dispatch('toast-error', message: 'Forma de pago no válida');

            return;
        }

        $permiteVuelto = $fp['permite_vuelto'] ?? false;

        // Multi-moneda: si es moneda extranjera, convertir monto a moneda principal
        $esMonedaExtranjera = $fp['es_moneda_extranjera'] ?? false;
        $tipoCambioTasa = null;
        $tipoCambioId = null;
        $montoMonedaOriginal = null;
        $monedaId = $fp['moneda_id'] ?? null;

        if ($esMonedaExtranjera) {
            $tipoCambioTasa = (float) ($this->nuevoPago['tipo_cambio_tasa'] ?? 0);
            $tipoCambioId = $this->nuevoPago['tipo_cambio_id'] ?? null;
            if ($tipoCambioTasa <= 0) {
                $this->dispatch('toast-error', message: __('Ingrese la cotización para esta moneda'));

                return;
            }
            // El monto ingresado es en moneda extranjera, convertimos a principal
            $montoMonedaOriginal = $monto;
            $monto = round($monto * $tipoCambioTasa, 2);

            // Defensa: si después de convertir por una cotización muy chica el monto
            // se redondea a 0 (o queda negativo por dato basura), abortar acá. No
            // dejamos que se sume un pago de monto 0 al desglose porque después
            // rompe el cálculo de IVA mixto y no aporta nada al cobro.
            if ($monto <= 0) {
                $this->dispatch('toast-error', message: __('El monto convertido es cero o negativo. Verifique la cotización.'));

                return;
            }
        }

        // Validar que no exceda el pendiente (salvo que permita vuelto)
        if ($monto > $this->montoPendienteDesglose + 0.01 && ! $permiteVuelto) {
            $this->dispatch('toast-error', message: __('El monto excede el pendiente'));

            return;
        }

        // Validar que solo haya un pago en Cuenta Corriente
        $esCuentaCorriente = isset($fp['codigo']) && strtoupper($fp['codigo']) === 'CTA_CTE';
        if ($esCuentaCorriente) {
            $yaExisteCC = collect($this->desglosePagos)->contains(function ($pago) {
                $fpExistente = collect($this->formasPagoSucursal)->firstWhere('id', $pago['forma_pago_id']);

                return $fpExistente && isset($fpExistente['codigo']) && strtoupper($fpExistente['codigo']) === 'CTA_CTE';
            });

            if ($yaExisteCC) {
                $this->dispatch('toast-error', message: __('Solo se permite un pago en Cuenta Corriente por venta'));

                return;
            }
        }

        // Si el monto excede el pendiente y permite vuelto, calcular vuelto
        $montoRecibido = null;
        $vuelto = 0;
        $montoParaBase = $monto;

        if ($permiteVuelto && $monto > $this->montoPendienteDesglose + 0.01) {
            // El cliente paga de más: base = pendiente, recibido = lo que paga, vuelto = diferencia
            $montoRecibido = $monto;
            $montoParaBase = $this->montoPendienteDesglose;
            // Si es moneda extranjera, ajustar monto_moneda_original proporcionalmente
            if ($esMonedaExtranjera && $tipoCambioTasa > 0) {
                $montoMonedaOriginal = $montoMonedaOriginal; // mantener lo que entregó en USD
            }
        }

        // Calcular ajuste de forma de pago (sobre monto base en moneda principal).
        //
        // Criterio de negocio (decision usuario, repaso 1 — 2026-05-07):
        //   El ajuste de forma de pago se calcula sobre el monto que esa FP cobra,
        //   que es la porción del total_final (post desc gral, post promos, post
        //   cupón). NO sobre el subtotal sin descuentos. Es decir: si la venta es
        //   $1000 con 5% desc gral → total $950, y se paga 100% con tarjeta +10%,
        //   el ajuste FP es $95 (10% sobre $950), no $100. El cliente percibe
        //   primero el desc gral y luego el recargo de la tarjeta sobre el neto.
        $ajuste = $fp['ajuste_porcentaje'];
        $montoAjuste = round($montoParaBase * ($ajuste / 100), 2);
        $montoConAjuste = round($montoParaBase + $montoAjuste, 2);

        // Calcular cuotas si aplica
        $cuotas = (int) ($this->nuevoPago['cuotas'] ?? 1);
        $recargoCuotas = 0;
        $montoFinal = $montoConAjuste;

        if ($cuotas > 1 && $fp['permite_cuotas']) {
            $cuotaConfig = collect($fp['cuotas'])->firstWhere('cantidad', $cuotas);
            if ($cuotaConfig) {
                $recargoCuotas = $cuotaConfig['recargo'];
                $montoRecargoCuotas = round($montoConAjuste * ($recargoCuotas / 100), 2);
                $montoFinal = round($montoConAjuste + $montoRecargoCuotas, 2);
            }
        }

        // Calcular vuelto si pagó de más
        if ($montoRecibido !== null) {
            $vuelto = round($montoRecibido - $montoFinal, 2);
            if ($vuelto < 0) {
                $vuelto = 0;
            }
        } elseif ($permiteVuelto) {
            $montoRecibido = $montoFinal;
        }

        $this->desglosePagos[] = [
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'],
            'monto_base' => $montoParaBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $montoRecibido,
            'vuelto' => $vuelto,
            'permite_vuelto' => $fp['permite_vuelto'],
            'permite_cuotas' => $fp['permite_cuotas'],
            'cuotas_disponibles' => $fp['cuotas'],
            'factura_fiscal' => $fp['factura_fiscal'] ?? false,
            'es_cuenta_corriente' => $esCuentaCorriente,
            'moneda_id' => $monedaId,
            'es_moneda_extranjera' => $esMonedaExtranjera,
            'moneda_info' => $fp['moneda_info'] ?? null,
            'tipo_cambio_tasa' => $tipoCambioTasa,
            'tipo_cambio_id' => $tipoCambioId,
            'monto_moneda_original' => $montoMonedaOriginal,
        ];

        $this->montoPendienteDesglose = round($this->montoPendienteDesglose - $montoParaBase, 2);
        if ($this->montoPendienteDesglose < 0) {
            $this->montoPendienteDesglose = 0;
        }

        // Recalcular el monto fiscal
        $this->calcularMontoFacturaFiscal();
        $this->recalcularTotalConAjustes();
        $this->resetNuevoPago();

        // Devolver el foco al selector de formas de pago si hay pendiente
        if ($this->montoPendienteDesglose > 0.01) {
            $this->dispatch('focus-busqueda-fp');
        }
    }

    /**
     * Asigna el monto pendiente al nuevo pago
     */
    public function asignarMontoPendiente(): void
    {
        $this->nuevoPago['monto'] = $this->montoPendienteDesglose;
    }

    /**
     * Elimina un pago del desglose
     */
    public function eliminarDelDesglose(int $index): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = $this->desglosePagos[$index];
        $this->montoPendienteDesglose = round($this->montoPendienteDesglose + $pago['monto_base'], 2);

        unset($this->desglosePagos[$index]);
        $this->desglosePagos = array_values($this->desglosePagos);
        $this->recalcularTotalConAjustes();

        // Recalcular el monto fiscal
        $this->calcularMontoFacturaFiscal();
    }

    /**
     * Recalcula el total con todos los ajustes del desglose
     */
    protected function recalcularTotalConAjustes(): void
    {
        // totalConAjustes = bienes + ajustes FP/cuotas, SIN la percepción (que se
        // muestra como línea aparte y se suma en el "total a pagar"). Por eso se
        // descuenta la percepción ya distribuida en cada monto_final.
        $this->totalConAjustes = array_sum(array_map(
            fn ($p) => (float) ($p['monto_final'] ?? 0) - (float) ($p['percepcion'] ?? 0),
            $this->desglosePagos
        ));

        // Recalcular el desglose de IVA con los ajustes de pagos mixtos
        $this->recalcularDesgloseIvaMixto();
    }

    /**
     * Recalcula el desglose de IVA basándose en los pagos mixtos
     *
     * Cuando hay pagos mixtos, cada pago puede tener diferente ajuste (descuento/recargo).
     * Esta función distribuye proporcionalmente los ajustes entre las alícuotas de IVA.
     */
    protected function recalcularDesgloseIvaMixto(): void
    {
        // Si no hay desglose de IVA, no hacer nada
        if (! isset($this->resultado['desglose_iva'])) {
            return;
        }

        // Si no hay pagos en el desglose, limpiar valores mixtos existentes
        if (empty($this->desglosePagos)) {
            $this->limpiarDesgloseIvaMixto();

            return;
        }

        $desglose = $this->resultado['desglose_iva'];

        // El total base es la suma de monto_base de todos los pagos (sin ajustes de FP)
        $totalBase = array_sum(array_column($this->desglosePagos, 'monto_base'));

        // El total final es la suma de monto_final (con ajustes de FP y recargos de
        // cuotas) EXCLUYENDO la percepción fiscal: la percepción no es un ajuste
        // sobre los bienes y no debe prorratearse entre las alícuotas de IVA.
        $totalFinal = array_sum(array_map(
            fn ($p) => (float) ($p['monto_final'] ?? 0) - (float) ($p['percepcion'] ?? 0),
            $this->desglosePagos
        ));

        if ($totalBase <= 0) {
            return;
        }

        // El ajuste total de forma de pago + recargos de cuotas
        $ajusteTotal = $totalFinal - $totalBase;

        // Separar el ajuste de forma de pago del recargo de cuotas para mostrar en el desglose
        $totalAjusteFP = array_sum(array_column($this->desglosePagos, 'monto_ajuste'));
        $totalRecargoCuotas = 0;
        foreach ($this->desglosePagos as $pago) {
            if ($pago['recargo_cuotas'] > 0) {
                $montoConAjuste = $pago['monto_base'] + $pago['monto_ajuste'];
                $totalRecargoCuotas += round($montoConAjuste * ($pago['recargo_cuotas'] / 100), 3);
            }
        }

        // Recalcular cada alícuota con el ajuste proporcional
        $nuevoPorAlicuota = [];
        $totalNetoMixto = 0;
        $totalIvaMixto = 0;

        foreach ($desglose['por_alicuota'] as $alicuota) {
            // La proporción de esta alícuota respecto al total (usando subtotal que incluye IVA)
            // Usamos el subtotal sin descuento de promociones como base proporcional
            $subtotalAlicuota = $alicuota['subtotal'] ?? ($alicuota['neto'] + $alicuota['iva']);
            $proporcion = $subtotalAlicuota / $totalBase;

            // El ajuste proporcional para esta alícuota (el ajuste es sobre montos CON IVA)
            $ajusteAlicuota = round($ajusteTotal * $proporcion, 3);

            // Nuevo subtotal de esta alícuota
            $nuevoSubtotal = round($subtotalAlicuota + $ajusteAlicuota, 3);

            // Calcular nuevo neto e IVA
            // El ajuste "incluye" IVA proporcionalmente, así que dividimos para obtener neto
            if ($alicuota['porcentaje'] > 0) {
                $nuevoNeto = round($nuevoSubtotal / (1 + $alicuota['porcentaje'] / 100), 3);
                $nuevoIva = round($nuevoNeto * ($alicuota['porcentaje'] / 100), 3);
            } else {
                // Para exentos o no gravados
                $nuevoNeto = $nuevoSubtotal;
                $nuevoIva = 0;
            }

            $totalNetoMixto += $nuevoNeto;
            $totalIvaMixto += $nuevoIva;

            // Mantener los valores originales y agregar los de pago mixto
            $nuevaAlicuota = $alicuota;
            $nuevaAlicuota['neto_mixto'] = $nuevoNeto;
            $nuevaAlicuota['iva_mixto'] = $nuevoIva;
            $nuevaAlicuota['subtotal_mixto'] = round($nuevoNeto + $nuevoIva, 3);

            $nuevoPorAlicuota[] = $nuevaAlicuota;
        }

        // Actualizar el desglose con los nuevos valores de pago mixto
        $this->resultado['desglose_iva']['por_alicuota'] = $nuevoPorAlicuota;
        $this->resultado['desglose_iva']['ajuste_forma_pago_mixto'] = round($totalAjusteFP, 3);
        $this->resultado['desglose_iva']['recargo_cuotas_mixto'] = round($totalRecargoCuotas, 3);
        $this->resultado['desglose_iva']['total_neto_mixto'] = round($totalNetoMixto, 3);
        $this->resultado['desglose_iva']['total_iva_mixto'] = round($totalIvaMixto, 3);
        $this->resultado['desglose_iva']['total_mixto'] = round($totalNetoMixto + $totalIvaMixto, 3);
    }

    /**
     * Actualiza las cuotas de un pago en el desglose
     */
    public function actualizarCuotasDesglose(int $index, int $cuotas): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = &$this->desglosePagos[$index];

        if (! $pago['permite_cuotas'] || $cuotas < 1) {
            return;
        }

        $pago['cuotas'] = $cuotas;
        $montoConAjuste = $pago['monto_base'] + $pago['monto_ajuste'];

        if ($cuotas > 1) {
            $cuotaConfig = collect($pago['cuotas_disponibles'])->firstWhere('cantidad', $cuotas);
            if ($cuotaConfig) {
                $pago['recargo_cuotas'] = $cuotaConfig['recargo'];
                $montoRecargo = round($montoConAjuste * ($cuotaConfig['recargo'] / 100), 2);
                $pago['monto_recargo_cuotas'] = $montoRecargo;
                $pago['monto_final'] = round($montoConAjuste + $montoRecargo, 2);
            }
        } else {
            $pago['recargo_cuotas'] = 0;
            $pago['monto_recargo_cuotas'] = 0;
            $pago['monto_final'] = $montoConAjuste;
        }

        if ($pago['permite_vuelto']) {
            $pago['monto_recibido'] = $pago['monto_final'];
            $pago['vuelto'] = 0;
        }

        $this->recalcularTotalConAjustes();
    }

    /**
     * Actualiza el monto recibido y calcula el vuelto
     */
    public function actualizarMontoRecibido(int $index, $monto): void
    {
        if (! isset($this->desglosePagos[$index])) {
            return;
        }

        $pago = &$this->desglosePagos[$index];
        $montoRecibido = (float) $monto;
        $pago['monto_recibido'] = $montoRecibido;
        $pago['vuelto'] = max(0, round($montoRecibido - $pago['monto_final'], 2));
    }

    /**
     * Cierra el modal de pago
     * Si el desglose está completo, mantiene los totales calculados
     */
    public function cerrarModalPago(): void
    {
        // Si el desglose está completo, actualizar el ajusteFormaPagoInfo con los totales
        if ($this->desgloseCompleto() && ! empty($this->desglosePagos)) {
            $totalBase = $this->resultado['total_final'] ?? 0;
            $totalConAjustes = $this->totalConAjustes;
            $montoAjuste = $totalConAjustes - $totalBase;

            $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
            $this->ajusteFormaPagoInfo['total_con_ajuste'] = $totalConAjustes;
        }

        $this->mostrarModalPago = false;
        // No limpiar el desglose si está completo (para poder procesar después)
        if (! $this->desgloseCompleto()) {
            $this->desglosePagos = [];
            $this->montoPendienteDesglose = 0;
            $this->totalConAjustes = 0;
            // Limpiar valores mixtos del desglose de IVA
            $this->limpiarDesgloseIvaMixto();
        }
        $this->resetNuevoPago();
    }

    // =========================================
    // MODAL SIMPLE DE MONEDA EXTRANJERA
    // =========================================

    /**
     * Actualiza el cálculo en vivo del modal de moneda extranjera
     */
    public function updatedPagoMonedaExtranjeraMontoExtranjera($value): void
    {
        $this->calcularEquivalenteMonedaExtranjera();
    }

    public function updatedPagoMonedaExtranjeraCotizacion($value): void
    {
        $this->calcularEquivalenteMonedaExtranjera();
    }

    protected function calcularEquivalenteMonedaExtranjera(): void
    {
        $monto = (float) ($this->pagoMonedaExtranjera['monto_extranjera'] ?? 0);
        $cotizacion = (float) ($this->pagoMonedaExtranjera['cotizacion'] ?? 0);
        $totalVenta = (float) ($this->pagoMonedaExtranjera['total_venta'] ?? 0);

        if ($monto > 0 && $cotizacion > 0) {
            $equivalente = round($monto * $cotizacion, 2);
            $this->pagoMonedaExtranjera['equivalente_principal'] = $equivalente;
            $this->pagoMonedaExtranjera['vuelto'] = max(0, round($equivalente - $totalVenta, 2));
        } else {
            $this->pagoMonedaExtranjera['equivalente_principal'] = 0;
            $this->pagoMonedaExtranjera['vuelto'] = 0;
        }
    }

    /**
     * Confirma el pago en moneda extranjera y crea el desglose
     */
    public function confirmarPagoMonedaExtranjera(): void
    {
        $monto = (float) ($this->pagoMonedaExtranjera['monto_extranjera'] ?? 0);
        $cotizacion = (float) ($this->pagoMonedaExtranjera['cotizacion'] ?? 0);
        $totalVenta = (float) ($this->pagoMonedaExtranjera['total_venta'] ?? 0);
        $ajuste = (float) ($this->pagoMonedaExtranjera['ajuste_porcentaje'] ?? 0);

        if ($monto <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese el monto en moneda extranjera'));

            return;
        }
        if ($cotizacion <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese la cotización'));

            return;
        }

        $equivalente = round($monto * $cotizacion, 2);
        if ($equivalente < $totalVenta - 0.01) {
            $this->dispatch('toast-error', message: __('El monto es insuficiente para cubrir la venta'));

            return;
        }

        $vuelto = max(0, round($equivalente - $totalVenta, 2));
        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->pagoMonedaExtranjera['forma_pago_id']);

        if (! $fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));

            return;
        }

        // Calcular base sin ajuste para registro correcto
        $totalBase = $this->resultado['total_final'] ?? 0;
        $montoAjuste = round($totalBase * ($ajuste / 100), 2);

        $this->desglosePagos = [[
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'] ?? null,
            'monto_base' => $totalBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            // bienes + ajuste; la percepción (incluida en total_venta) se inyecta al procesar.
            'monto_final' => round($totalBase + $montoAjuste, 2),
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'monto_recibido' => $equivalente,
            'vuelto' => $vuelto,
            'factura_fiscal' => $this->sucursalFacturaAutomatica
                ? ($fp['factura_fiscal'] ?? false)
                : $this->emitirFacturaFiscal,
            'es_cuenta_corriente' => false,
            'moneda_id' => $this->pagoMonedaExtranjera['moneda_id'],
            'es_moneda_extranjera' => true,
            'moneda_info' => $fp['moneda_info'] ?? null,
            'tipo_cambio_tasa' => $cotizacion,
            'tipo_cambio_id' => $this->pagoMonedaExtranjera['tipo_cambio_id'] ?? null,
            'monto_moneda_original' => $monto,
        ]];

        $this->totalConAjustes = $totalVenta;
        $this->montoPendienteDesglose = 0;
        $this->mostrarModalMonedaExtranjera = false;

        $this->calcularMontoFacturaFiscal();
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Cierra el modal de moneda extranjera sin confirmar
     */
    public function cerrarModalMonedaExtranjera(): void
    {
        $this->mostrarModalMonedaExtranjera = false;
    }

    // =========================================
    // MODAL DE COBRO CON VUELTO (MONEDA LOCAL)
    // =========================================

    /**
     * Actualiza el cálculo de vuelto en vivo
     */
    public function updatedPagoConVueltoMontoRecibido($value): void
    {
        $monto = (float) ($value ?? 0);
        $total = (float) ($this->pagoConVuelto['total_a_pagar'] ?? 0);
        $this->pagoConVuelto['vuelto'] = max(0, round($monto - $total, 2));
    }

    /**
     * Confirma el pago con vuelto y procesa la venta
     */
    public function confirmarPagoConVuelto(): void
    {
        $montoRecibido = (float) ($this->pagoConVuelto['monto_recibido'] ?? 0);
        $totalAPagar = (float) ($this->pagoConVuelto['total_a_pagar'] ?? 0);

        if ($montoRecibido < $totalAPagar - 0.01) {
            $this->dispatch('toast-error', message: __('El monto recibido es insuficiente'));

            return;
        }

        $vuelto = max(0, round($montoRecibido - $totalAPagar, 2));

        $fp = collect($this->formasPagoSucursal)->firstWhere('id', (int) $this->pagoConVuelto['forma_pago_id']);
        if (! $fp) {
            $this->dispatch('toast-error', message: __('Forma de pago no válida'));

            return;
        }

        $totalBase = $this->resultado['total_final'] ?? 0;
        $ajuste = $this->ajusteFormaPagoInfo['porcentaje'];
        $montoAjuste = $this->ajusteFormaPagoInfo['monto'];
        $cantidadCuotas = $this->ajusteFormaPagoInfo['cuotas'] ?? 1;
        $recargoCuotas = $this->ajusteFormaPagoInfo['recargo_cuotas_porcentaje'] ?? 0;

        // monto_final del pago = bienes + ajuste (sin percepción); la percepción se
        // inyecta en el pago fiscal al procesar. El vuelto ya se calculó sobre el
        // total_a_pagar que incluye la percepción.
        $montoFinalPago = round($totalAPagar - ($this->percepcionMonto ?? 0), 2);

        $this->crearDesglosePagoSimple($fp, $totalBase, $ajuste, $montoAjuste, $montoFinalPago, $cantidadCuotas, $recargoCuotas, false, $montoRecibido, $vuelto);
        $this->mostrarModalVuelto = false;
    }

    /**
     * Cierra el modal de vuelto sin confirmar
     */
    public function cerrarModalVuelto(): void
    {
        $this->mostrarModalVuelto = false;
    }

    /**
     * Crea el desglose de pago simple y procesa la venta
     */
    protected function crearDesglosePagoSimple(
        array $fp,
        float $totalBase,
        float $ajuste,
        float $montoAjuste,
        float $montoFinal,
        int $cantidadCuotas,
        float $recargoCuotas,
        bool $esCuentaCorriente,
        ?float $montoRecibido = null,
        float $vuelto = 0
    ): void {
        $this->desglosePagos = [[
            'forma_pago_id' => $fp['id'],
            'nombre' => $fp['nombre'],
            'codigo' => $fp['codigo'] ?? null,
            'concepto_pago_id' => $fp['concepto_pago_id'] ?? null,
            'monto_base' => $totalBase,
            'ajuste_porcentaje' => $ajuste,
            'monto_ajuste' => $montoAjuste,
            'monto_final' => $montoFinal,
            'cuotas' => $cantidadCuotas,
            'recargo_cuotas' => $recargoCuotas,
            'monto_recibido' => $montoRecibido,
            'vuelto' => $vuelto,
            'factura_fiscal' => $this->sucursalFacturaAutomatica
                ? ($fp['factura_fiscal'] ?? false)
                : $this->emitirFacturaFiscal,
            'es_cuenta_corriente' => $esCuentaCorriente,
        ]];

        $this->totalConAjustes = $montoFinal;
        $this->montoPendienteDesglose = 0;

        $this->calcularMontoFacturaFiscal();
        $this->verificarPuntoVentaYProcesar();
    }

    /**
     * Limpia los valores de pago mixto del desglose de IVA
     */
    protected function limpiarDesgloseIvaMixto(): void
    {
        if (! isset($this->resultado['desglose_iva'])) {
            return;
        }

        // Eliminar valores mixtos de cada alícuota
        if (isset($this->resultado['desglose_iva']['por_alicuota'])) {
            foreach ($this->resultado['desglose_iva']['por_alicuota'] as &$alicuota) {
                unset($alicuota['neto_mixto']);
                unset($alicuota['iva_mixto']);
                unset($alicuota['subtotal_mixto']);
            }
        }

        // Eliminar totales mixtos
        unset($this->resultado['desglose_iva']['ajuste_forma_pago_mixto']);
        unset($this->resultado['desglose_iva']['recargo_cuotas_mixto']);
        unset($this->resultado['desglose_iva']['total_neto_mixto']);
        unset($this->resultado['desglose_iva']['total_iva_mixto']);
        unset($this->resultado['desglose_iva']['total_mixto']);
    }

    /**
     * Verifica si el desglose está completo y listo para procesar.
     *
     * Tres condiciones:
     *   1. Hay al menos un pago en el desglose.
     *   2. No queda monto pendiente (tolerancia 0.01).
     *   3. ASSERT defensivo: la suma de monto_base de los pagos cuadra con el total
     *      esperado de la venta. La lógica de agregarAlDesglose debería garantizar
     *      esto siempre, pero validamos contra dato manipulado o estado corrupto.
     */
    public function desgloseCompleto(): bool
    {
        // 1. Debe haber al menos un pago
        if (empty($this->desglosePagos)) {
            return false;
        }

        // 2. No debe quedar monto pendiente (tolerancia de 0.01)
        if ($this->montoPendienteDesglose > 0.01) {
            return false;
        }

        // 3. Defensa: Σ monto_base ≈ total_final esperado.
        // Tolerancia 0.05 para acumulación de redondeos en muchos pagos.
        $totalEsperado = (float) ($this->resultado['total_final'] ?? 0);
        if ($totalEsperado > 0) {
            $sumaPagos = (float) array_sum(array_column($this->desglosePagos, 'monto_base'));
            if (abs($sumaPagos - $totalEsperado) > 0.05) {
                Log::warning('desgloseCompleto invariante roto: suma de pagos no cuadra con total', [
                    'suma_pagos' => $sumaPagos,
                    'total_esperado' => $totalEsperado,
                    'diferencia' => $sumaPagos - $totalEsperado,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Confirma el desglose y cierra el modal (NO procesa la venta)
     * La venta se procesa con el botón "Cobrar" de la vista principal
     */
    public function confirmarPago(): void
    {
        if (! $this->desgloseCompleto()) {
            $this->dispatch('toast-error', message: 'Complete el desglose de pagos');

            return;
        }

        // Actualizar ajusteFormaPagoInfo con los totales del desglose
        $totalBase = $this->resultado['total_final'] ?? 0;
        $montoAjuste = $this->totalConAjustes - $totalBase;

        $this->ajusteFormaPagoInfo['monto'] = $montoAjuste;
        $this->ajusteFormaPagoInfo['total_con_ajuste'] = $this->totalConAjustes;

        // Cerrar modal manteniendo el desglose
        $this->mostrarModalPago = false;

        $this->dispatch('toast-success', message: 'Desglose confirmado. Haga clic en Cobrar para finalizar.');
    }

    // =========================================
    // PROCESAMIENTO DE VENTA
    // =========================================

    /**
     * Procesa la venta con el desglose de pagos
     *
     * Flujo completo:
     * 1. Validaciones previas
     * 2. Crear la venta con todos los campos de contexto
     * 3. Guardar desglose de pagos con nuevos campos
     * 4. Registrar movimientos de caja
     * 5. Verificar si debe generar factura fiscal
     * 6. Si corresponde, emitir comprobante fiscal via ARCA
     * 7. Actualizar campos de cuenta corriente si aplica
     */
    protected function procesarVentaConDesglose(): void
    {
        try {
            if (empty($this->items)) {
                $this->dispatch('toast-error', message: 'El carrito está vacío');

                return;
            }

            $sucursal = Sucursal::find($this->sucursalId);
            if (! $sucursal) {
                $this->dispatch('toast-error', message: 'Sucursal no encontrada');

                return;
            }

            $cajaId = $this->cajaSeleccionada ?? caja_activa();

            // Validar formas de pago del cupón (Opción C: 100% formas válidas)
            if ($this->cuponAplicado && $this->cuponInfo) {
                $cuponValidar = Cupon::find($this->cuponInfo['id']);
                if ($cuponValidar && $cuponValidar->tieneRestriccionFormasPago()) {
                    $fpIds = collect($this->desglosePagos)->pluck('forma_pago_id')->unique()->toArray();
                    $validacionFP = $this->cuponService->validarFormasPagoCupon($cuponValidar, $fpIds);
                    if (! $validacionFP['valid']) {
                        $this->dispatch('toast-error', message: $validacionFP['message']);

                        return;
                    }
                }
            }

            // Verificar si hay pagos a cuenta corriente (usar el flag o verificar código)
            $tieneCuentaCorriente = false;
            $montoCuentaCorriente = 0;

            foreach ($this->desglosePagos as $pago) {
                // Usar el flag si existe, sino verificar por código
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }

                if ($esCC) {
                    $tieneCuentaCorriente = true;
                    $montoCuentaCorriente += $pago['monto_final'];

                    // Cuenta corriente requiere cliente
                    if (! $this->clienteSeleccionado) {
                        $this->dispatch('toast-error', message: 'Debe seleccionar un cliente para ventas a cuenta corriente');

                        return;
                    }

                    // Verificar que el cliente tiene cuenta corriente habilitada
                    $cliente = Cliente::find($this->clienteSeleccionado);
                    if (! $cliente || ! $cliente->tiene_cuenta_corriente) {
                        $this->dispatch('toast-error', message: 'El cliente no tiene cuenta corriente habilitada');

                        return;
                    }

                    // Verificar límite de crédito
                    $nuevoSaldo = $cliente->saldo_deudor_cache + $montoCuentaCorriente;
                    if ($cliente->limite_credito > 0 && $nuevoSaldo > $cliente->limite_credito) {
                        $this->dispatch('toast-error', message: 'El cliente excede su límite de crédito');

                        return;
                    }
                }
            }

            // Verificar caja para pagos que la requieren (CC no requiere caja)
            $requiereCaja = false;
            foreach ($this->desglosePagos as $pago) {
                $esCC = $pago['es_cuenta_corriente'] ?? false;
                if (! $esCC && isset($pago['codigo'])) {
                    $esCC = strtoupper($pago['codigo']) === 'CTA_CTE';
                }
                if (! $esCC) {
                    $requiereCaja = true;
                    break;
                }
            }

            if ($requiereCaja && ! $cajaId) {
                $this->dispatch('toast-error', message: 'Debe seleccionar una caja');

                return;
            }

            // Verificar caja abierta
            if ($cajaId) {
                $caja = Caja::find($cajaId);
                if (! $caja || ! $caja->estaAbierta()) {
                    $this->dispatch('toast-error', message: 'La caja debe estar abierta');

                    return;
                }
            }

            // Revalidar tope de descuento del usuario contra dato fresco de BD: el tope
            // se cargó en mount(); si entre ese momento y el cobro un admin cambió el
            // rol del cajero, el descuento ya aplicado podría superar el nuevo tope.
            try {
                $this->revalidarTopeDescuentoAlCobrar();
            } catch (Exception $e) {
                $this->dispatch('toast-error', message: $e->getMessage());

                return;
            }

            // Revalidar saldo de puntos con dato fresco de BD: el saldo en memoria
            // se cargó al seleccionar cliente; entre ese momento y el cobro pudo
            // haber cambiado (otra caja del mismo comercio consumió puntos del
            // mismo cliente). Mejor abortar acá con mensaje claro que dejar que
            // la transacción falle y haga rollback con un error oscuro.
            $puntosRequeridosTotal = 0;
            if ($this->canjePuntosActivo && $this->canjePuntosUnidades > 0) {
                $puntosRequeridosTotal += (int) $this->canjePuntosUnidades;
            }
            $puntosRequeridosTotal += (int) $this->calcularPuntosUsadosEnArticulos();
            if ($puntosRequeridosTotal > 0 && $this->clienteSeleccionado) {
                $saldoFresco = $this->puntosService->obtenerSaldo(
                    $this->clienteSeleccionado,
                    $this->sucursalId
                );
                if ($puntosRequeridosTotal > $saldoFresco) {
                    $this->dispatch(
                        'toast-error',
                        message: "Saldo de puntos insuficiente. Disponible: {$saldoFresco}, requerido: {$puntosRequeridosTotal}. Posiblemente se consumieron puntos en otra caja."
                    );

                    return;
                }
            }

            // Determinar forma de pago principal
            $formaPagoPrincipalId = $this->formaPagoId;
            if (count($this->desglosePagos) > 1) {
                $formaMixta = FormaPago::where('es_mixta', true)->where('activo', true)->first();
                $formaPagoPrincipalId = $formaMixta?->id ?? $this->desglosePagos[0]['forma_pago_id'];
            }

            $formaPagoPrincipal = FormaPago::find($formaPagoPrincipalId);
            $formaPagoCodigo = $formaPagoPrincipal?->concepto ?? 'efectivo';
            if ($formaPagoPrincipal?->es_mixta) {
                $formaPagoCodigo = 'mixto';
            }

            // Verificar si debe generar factura fiscal
            // Se factura si:
            // 1. Automático: sucursal.facturacion_fiscal_automatica = true Y alguna forma de pago tiene factura_fiscal = true
            // 2. Manual: el usuario marcó el checkbox emitirFacturaFiscal
            // 3. Desglose: algún pago en el desglose tiene factura_fiscal = true
            $comprobanteFiscalService = new ComprobanteFiscalService;
            $debeFacturarAutomatico = $comprobanteFiscalService->debeGenerarFacturaFiscal($sucursal, $this->desglosePagos);
            $debeFacturarManual = $this->emitirFacturaFiscal;
            $debeFacturarDesglose = collect($this->desglosePagos)->contains('factura_fiscal', true);
            $debeFacturar = $debeFacturarAutomatico || $debeFacturarManual || $debeFacturarDesglose;

            DB::connection('pymes_tenant')->beginTransaction();

            try {
                // La percepción fiscal (Fase 5b) ya está distribuida en el monto_final
                // de los pagos fiscales (distribuirPercepcionEnDesglose, al armar el
                // desglose), así el monto cobrado por cada medio ya la incluye.

                // Obtener desglose de IVA calculado
                $desgloseIva = $this->resultado['desglose_iva'] ?? [];

                // Calcular totales incluyendo ajuste de forma de pago
                $subtotal = $this->resultado['subtotal'] ?? 0;
                $descuentoPromociones = $this->resultado['total_descuentos'] ?? 0;
                $totalAntesAjusteFP = $this->resultado['total_final'] ?? 0;

                // Calcular ajuste de forma de pago (suma de monto_ajuste de todos los pagos)
                $totalAjusteFP = array_sum(array_column($this->desglosePagos, 'monto_ajuste'));
                $totalFinal = array_sum(array_column($this->desglosePagos, 'monto_final'));

                // Preparar datos de la venta con totales ya calculados
                $datosVenta = [
                    'sucursal_id' => $this->sucursalId,
                    'cliente_id' => $this->clienteSeleccionado,
                    'caja_id' => $cajaId,
                    'usuario_id' => Auth::id(),
                    'forma_pago_id' => $formaPagoPrincipalId,
                    'forma_venta_id' => $this->formaVentaId,
                    'canal_venta_id' => $this->canalVentaId,
                    'lista_precio_id' => $this->listaPrecioId,
                    'observaciones' => $this->observaciones,
                    // Totales ya calculados (no recalcular)
                    'subtotal' => $subtotal,
                    'descuento' => $descuentoPromociones, // Solo descuentos de promociones
                    'total' => $totalAntesAjusteFP, // Total después de promociones, antes de ajuste FP
                    'ajuste_forma_pago' => $totalAjusteFP, // Suma de ajustes de formas de pago
                    'total_final' => $totalFinal,   // Total real cobrado (con ajuste FP)
                    'iva' => $desgloseIva['total_iva'] ?? 0,
                    // Campos de cuenta corriente
                    'es_cuenta_corriente' => $tieneCuentaCorriente,
                    'saldo_pendiente_cache' => $montoCuentaCorriente,
                    'fecha_vencimiento' => $tieneCuentaCorriente
                        ? now()->addDays($cliente->dias_credito ?? 30)->toDateString()
                        : null,
                    // Flag para indicar que no debe recalcular
                    '_usar_totales_proporcionados' => true,
                    // Promociones aplicadas para guardar en tablas de promociones
                    '_promociones_comunes' => $this->resultado['promociones_comunes_aplicadas'] ?? [],
                    '_promociones_especiales' => $this->resultado['promociones_especiales_aplicadas'] ?? [],
                    // Descuento general (RF-38)
                    'descuento_general_tipo' => $this->descuentoGeneralActivo ? $this->descuentoGeneralTipo : null,
                    'descuento_general_valor' => $this->descuentoGeneralActivo ? $this->descuentoGeneralValor : null,
                    'descuento_general_monto' => $this->descuentoGeneralMonto,
                    'descuento_general_aplicado_por' => $this->descuentoGeneralActivo ? $this->descuentoGeneralAplicadoPor : null,
                    // Cupón (RF-19)
                    'cupon_id' => $this->cuponAplicado && $this->cuponInfo ? $this->cuponInfo['id'] : null,
                    'monto_cupon' => $this->cuponMontoDescuento,
                    // Puntos (RF-09)
                    'puntos_usados' => $this->canjePuntosActivo ? $this->canjePuntosUnidades : 0,
                    'puntos_usados_monto' => (float) ($this->resultado['puntos_usados_monto'] ?? 0),
                ];

                // Calcular descuento cupón por item para trazabilidad
                $descuentoCuponPorItem = $this->calcularDescuentoCuponPorItem();

                // Construir detalles con información de promociones
                $detalles = [];
                foreach ($this->items as $index => $item) {
                    $itemResultado = $this->resultado['items'][$index] ?? [];
                    $descuentoPromocion = $itemResultado['descuento_comun'] ?? 0;
                    $promocionesComunes = $itemResultado['promociones_comunes'] ?? [];
                    $promocionesEspeciales = $itemResultado['promociones_especiales'] ?? [];
                    $tienePromocion = ! empty($promocionesComunes) || ! empty($promocionesEspeciales);
                    $esConcepto = (bool) ($item['es_concepto'] ?? false);

                    // Atribución por item del descuento por promociones especiales: suma de los
                    // descuentos atribuidos al item por cada promo especial que lo bonifica.
                    // Ya viene calculado correctamente desde WithCalculoVenta (PR #55).
                    $descuentoPromocionEspecial = 0.0;
                    foreach ($promocionesEspeciales as $promoEsp) {
                        $descuentoPromocionEspecial += (float) ($promoEsp['descuento'] ?? 0);
                    }

                    $detalles[] = [
                        'articulo_id' => $item['articulo_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'precio_lista' => $item['precio_base'] ?? $item['precio'],
                        'lista_precio_id' => $esConcepto ? null : $this->listaPrecioId, // Conceptos no usan lista de precios
                        'descuento' => 0, // Descuento manual (no promoción)
                        'descuento_promocion' => $esConcepto ? 0 : $descuentoPromocion, // Conceptos no tienen promociones
                        'descuento_promocion_especial' => $esConcepto ? 0 : round($descuentoPromocionEspecial, 2),
                        'descuento_cupon' => $descuentoCuponPorItem[$index] ?? 0,
                        'tiene_promocion' => $esConcepto ? false : $tienePromocion,
                        // Info de IVA del item
                        'tipo_iva_id' => $this->resolverTipoIvaId($item),
                        'iva_porcentaje' => $item['iva_porcentaje'] ?? 21,
                        'precio_iva_incluido' => $item['precio_iva_incluido'] ?? true,
                        // Info de ajuste manual si existe
                        'ajuste_manual_tipo' => $item['ajuste_manual_tipo'] ?? null,
                        'ajuste_manual_valor' => $item['ajuste_manual_valor'] ?? null,
                        'ajuste_manual_origen' => $item['ajuste_manual_origen'] ?? null,
                        'ajuste_manual_aplicado_por' => $item['ajuste_manual_aplicado_por'] ?? null,
                        'precio_sin_ajuste_manual' => $item['precio_sin_ajuste_manual'] ?? null,
                        // Opcionales seleccionados (conceptos no tienen opcionales)
                        'opcionales' => $esConcepto ? [] : ($item['opcionales'] ?? []),
                        'precio_opcionales' => $esConcepto ? 0 : ($item['precio_opcionales'] ?? 0),
                        // Info de promociones para guardar en venta_detalle_promociones
                        '_promociones_item' => $esConcepto ? [] : [
                            'promociones_comunes' => $promocionesComunes,
                            'promociones_especiales' => $promocionesEspeciales,
                        ],
                        // Canje por puntos (RF-10) — conceptos no se pagan con puntos
                        'pagado_con_puntos' => $esConcepto ? false : ($item['pagado_con_puntos'] ?? false),
                        'puntos_usados' => $esConcepto || ! ($item['pagado_con_puntos'] ?? false)
                            ? 0
                            : $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0)) * (float) ($item['cantidad'] ?? 1),
                        // Concepto libre
                        'es_concepto' => $esConcepto,
                        'concepto_descripcion' => $esConcepto ? ($item['nombre'] ?? null) : null,
                        'concepto_categoria_id' => $esConcepto ? ($item['categoria_id'] ?? null) : null,
                    ];
                }

                // Crear la venta
                $venta = $this->ventaService->crearVenta($datosVenta, $detalles);

                // Asociar el cobro QR confirmado (Fase 5) a la venta recién creada.
                // No-op si la venta no se cobró por integración. Va dentro de la
                // transacción: si algo falla después (ej. fiscal) y se hace rollback,
                // la asociación se revierte junto con la venta.
                $this->asociarCobroIntegracionAlCobrable($venta);

                // Guardar desglose de pagos con nuevos campos
                $pagosCreados = []; // Mapeo de índice => VentaPago ID para facturación parcial
                $txIntegracionAsignada = false; // El cobro QR se vincula a un único venta_pago
                foreach ($this->desglosePagos as $index => $pago) {
                    $fp = FormaPago::find($pago['forma_pago_id']);
                    $esCuentaCorriente = $fp && strtoupper($fp->codigo) === 'CTA_CTE';

                    // Trazabilidad (Fase 9): vincular el pago cobrado por integración
                    // (QR MP) con su transacción confirmada. Modo único por FP → se
                    // asigna a un solo venta_pago. Habilita el bloqueo posterior de
                    // modificación/anulación (no hay refund real todavía).
                    $integracionTransaccionId = null;
                    if (! $txIntegracionAsignada
                        && $this->cobroIntegracionConfirmado
                        && $this->cobroIntegracionTransaccionId
                        && $fp && $fp->tieneIntegracion()) {
                        $integracionTransaccionId = $this->cobroIntegracionTransaccionId;
                        $txIntegracionAsignada = true;
                    }

                    // Determinar si es pago en efectivo (solo efectivo afecta la caja física)
                    $conceptoPago = null;
                    if (! empty($pago['concepto_pago_id'])) {
                        $conceptoPago = ConceptoPago::find($pago['concepto_pago_id']);
                    } elseif ($fp && $fp->concepto_pago_id) {
                        $conceptoPago = $fp->conceptoPago;
                    }
                    $esEfectivo = $conceptoPago && $conceptoPago->esEfectivo();

                    // Solo afecta la caja física si es efectivo
                    $afectaCaja = $esEfectivo && $cajaId && ! $esCuentaCorriente;

                    // Crear movimiento de caja SOLO si es efectivo
                    $movimientoCajaId = null;
                    if ($afectaCaja) {
                        $caja = Caja::find($cajaId);
                        $vuelto = (float) ($pago['vuelto'] ?? 0);
                        $esMonedaExtranjera = ! empty($pago['es_moneda_extranjera']) && ! empty($pago['tipo_cambio_tasa']);

                        // Snapshot de cotizacion: usar el id+tasa capturados al abrir el
                        // modal de cobro (no `TipoCambio::ultimaTasa()` ahora), para evitar
                        // drift si entre apertura del modal y persistencia se carga una
                        // cotizacion nueva. Si no vino del flow (caso defensivo), caer a
                        // ultimaTasa actual como antes.
                        $tcIdSnapshot = $pago['tipo_cambio_id'] ?? null;
                        $tcTasaSnapshot = $pago['tipo_cambio_tasa'] ?? null;
                        if ($esMonedaExtranjera && $tcIdSnapshot === null) {
                            $tcRecord = TipoCambio::ultimaTasa($pago['moneda_id'], Moneda::obtenerPrincipal()?->id);
                            $tcIdSnapshot = $tcRecord?->id;
                            $tcTasaSnapshot = $tcRecord ? (float) $tcRecord->tasa_venta : $tcTasaSnapshot;
                        }

                        if ($esMonedaExtranjera && $vuelto > 0) {
                            // Moneda extranjera con vuelto: ingreso por el TOTAL recibido + egreso por vuelto
                            $montoRecibido = (float) ($pago['monto_recibido'] ?? $pago['monto_final']);
                            $movimiento = MovimientoCaja::crearIngresoVenta($caja, $venta, $montoRecibido, Auth::id());

                            $movimiento->update([
                                'moneda_id' => $pago['moneda_id'],
                                'monto_moneda_original' => $pago['monto_moneda_original'],
                                'tipo_cambio_id' => $tcIdSnapshot,
                                'tipo_cambio_tasa' => $tcTasaSnapshot,
                            ]);

                            // Egreso por el vuelto entregado (siempre en moneda principal)
                            MovimientoCaja::create([
                                'caja_id' => $caja->id,
                                'tipo' => MovimientoCaja::TIPO_EGRESO,
                                'concepto' => "Vuelto Venta #{$venta->numero}",
                                'monto' => $vuelto,
                                'usuario_id' => Auth::id(),
                                'referencia_tipo' => MovimientoCaja::REF_VUELTO_VENTA,
                                'referencia_id' => $venta->id,
                            ]);
                        } else {
                            // Caso normal: sin vuelto o misma moneda
                            $movimiento = MovimientoCaja::crearIngresoVenta($caja, $venta, $pago['monto_final'], Auth::id());

                            if ($esMonedaExtranjera) {
                                $movimiento->update([
                                    'moneda_id' => $pago['moneda_id'],
                                    'monto_moneda_original' => $pago['monto_moneda_original'],
                                    'tipo_cambio_id' => $tcIdSnapshot,
                                    'tipo_cambio_tasa' => $tcTasaSnapshot,
                                ]);
                            }
                        }

                        $movimientoCajaId = $movimiento->id;

                        // Actualizar saldo de caja (siempre neto en moneda principal)
                        $caja->aumentarSaldo($pago['monto_final']);
                    }

                    // Obtener moneda de la forma de pago
                    $fpMonedaId = null;
                    $fpObj = FormaPago::find($pago['forma_pago_id']);
                    if ($fpObj) {
                        $fpMonedaId = $fpObj->moneda_id;
                    }

                    $ventaPago = VentaPago::create([
                        'venta_id' => $venta->id,
                        'forma_pago_id' => $pago['forma_pago_id'],
                        'concepto_pago_id' => $pago['concepto_pago_id'],
                        'monto_base' => $pago['monto_base'],
                        'ajuste_porcentaje' => $pago['ajuste_porcentaje'],
                        'monto_ajuste' => $pago['monto_ajuste'],
                        'monto_final' => $pago['monto_final'],
                        'monto_recibido' => $pago['monto_recibido'],
                        'vuelto' => $pago['vuelto'] ?? 0,
                        'cuotas' => $pago['cuotas'] > 1 ? $pago['cuotas'] : null,
                        'recargo_cuotas_porcentaje' => $pago['cuotas'] > 1 ? $pago['recargo_cuotas'] : null,
                        'recargo_cuotas_monto' => $pago['cuotas'] > 1
                            ? round(($pago['monto_base'] + $pago['monto_ajuste']) * ($pago['recargo_cuotas'] / 100), 2)
                            : null,
                        'monto_cuota' => $pago['cuotas'] > 1
                            ? round($pago['monto_final'] / $pago['cuotas'], 2)
                            : null,
                        'es_cuenta_corriente' => $esCuentaCorriente,
                        'afecta_caja' => $afectaCaja,
                        'estado' => 'activo',
                        'movimiento_caja_id' => $movimientoCajaId,
                        'integracion_pago_transaccion_id' => $integracionTransaccionId,
                        'moneda_id' => $pago['moneda_id'] ?? $fpMonedaId ?? Moneda::obtenerPrincipal()?->id,
                        'monto_moneda_original' => $pago['monto_moneda_original'] ?? null,
                        'tipo_cambio_tasa' => $pago['tipo_cambio_tasa'] ?? null,
                        'tipo_cambio_id' => $pago['tipo_cambio_id'] ?? null,
                    ]);

                    // Si la forma de pago tiene cuenta empresa vinculada, registrar movimiento.
                    // EXCEPTO pagos cobrados por integración: su ingreso ya lo registró
                    // CobroIntegracionService al confirmar la transacción, en la cuenta
                    // REAL del proveedor (origen IntegracionPagoTransaccion). Registrar
                    // acá también lo duplicaría (D6 del spec de vínculo de cuentas).
                    if (! $esCuentaCorriente && $integracionTransaccionId === null) {
                        $fpVinculada = FormaPago::find($pago['forma_pago_id']);
                        if ($fpVinculada && $fpVinculada->cuenta_empresa_id) {
                            try {
                                $movCuenta = CuentaEmpresaService::registrarMovimientoAutomatico(
                                    CuentaEmpresa::find($fpVinculada->cuenta_empresa_id),
                                    'ingreso', $pago['monto_final'], 'venta',
                                    'VentaPago', $ventaPago->id,
                                    "Venta #{$venta->numero} - {$fpVinculada->nombre}",
                                    Auth::id(), sucursal_activa()
                                );
                                $ventaPago->update(['movimiento_cuenta_empresa_id' => $movCuenta->id]);
                            } catch (\Exception $e) {
                                Log::warning('Error al registrar movimiento en cuenta empresa', ['error' => $e->getMessage()]);
                            }
                        }
                    }

                    // Guardar ID y si requiere factura fiscal para usarlo después
                    $pagosCreados[$index] = [
                        'id' => $ventaPago->id,
                        'monto_final' => $ventaPago->monto_final,
                        'factura_fiscal' => $pago['factura_fiscal'] ?? false,
                    ];
                }

                // Generar comprobante fiscal si corresponde.
                $comprobanteFiscal = null;
                $facturacionPendiente = false;
                $emitirFiscalPostCommit = false;
                $opcionesFiscal = [];
                if ($debeFacturar) {
                    // Construir opciones de facturación (válidas tanto para emisión
                    // dentro de la transacción como post-commit).
                    $pagosConFactura = array_filter($pagosCreados, fn ($p) => $p['factura_fiscal'] ?? false);
                    if (! empty($pagosConFactura)) {
                        $opcionesFiscal['pagos_facturar'] = array_values($pagosConFactura);
                    }
                    if (! empty($this->desgloseIvaFiscal)) {
                        $opcionesFiscal['desglose_iva'] = $this->desgloseIvaFiscal;
                        $opcionesFiscal['total_a_facturar'] = $this->montoFacturaFiscal;
                    }
                    // Percepciones aplicadas (Fase 5b): ya cobradas en el total → se
                    // pasan al comprobante para que cobrado == facturado (ImpTrib).
                    if (! empty($this->percepcionTributos)) {
                        $opcionesFiscal['tributos'] = $this->percepcionTributos;
                    }
                    if ($this->puntoVentaSeleccionadoId) {
                        $puntoVentaSeleccionado = PuntoVenta::with('cuit')->find($this->puntoVentaSeleccionadoId);
                        if ($puntoVentaSeleccionado) {
                            $opcionesFiscal['punto_venta'] = $puntoVentaSeleccionado;
                        }
                    }

                    if ($this->cobroIntegracionConfirmado) {
                        // El cobro QR ya entró: la venta NO puede revertirse. Se difiere la
                        // emisión de la FC a DESPUÉS del commit (mismo patrón que
                        // CambioFormaPagoService, evita la transacción anidada de
                        // crearComprobanteFiscal). Se pre-marcan los pagos como
                        // pendiente_de_facturar; si la emisión post-commit anda, el service
                        // los marca facturados; si no, quedan pendientes (reintentables
                        // desde Cajas → Pagos Pendientes de Facturación).
                        $idsPendientes = ! empty($pagosConFactura)
                            ? array_column($pagosConFactura, 'id')
                            : array_column($pagosCreados, 'id');
                        VentaPago::whereIn('id', $idsPendientes)
                            ->update(['estado_facturacion' => VentaPago::ESTADO_FACT_PENDIENTE]);
                        $emitirFiscalPostCommit = true;
                    } else {
                        // Venta sin cobro irreversible: emitir dentro de la transacción;
                        // si la FC falla, NO grabar la venta (rollback).
                        try {
                            $comprobanteFiscal = $comprobanteFiscalService->crearComprobanteFiscal($venta, $opcionesFiscal);
                            Log::info('Comprobante fiscal emitido', [
                                'venta_id' => $venta->id,
                                'comprobante_id' => $comprobanteFiscal->id,
                                'cae' => $comprobanteFiscal->cae,
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error al emitir comprobante fiscal - cancelando venta', [
                                'error' => $e->getMessage(),
                            ]);
                            DB::connection('pymes_tenant')->rollBack();
                            $this->dispatch('toast-error', message: 'Error al emitir factura fiscal: '.$e->getMessage());

                            return;
                        }
                    }
                }

                // Registrar uso de cupón si se aplicó uno (RF-19)
                if ($this->cuponAplicado && $this->cuponInfo && $this->cuponMontoDescuento > 0) {
                    $cuponObj = Cupon::find($this->cuponInfo['id']);
                    if ($cuponObj) {
                        $this->cuponService->aplicarCuponEnVenta(
                            $cuponObj,
                            $venta,
                            $this->cuponMontoDescuento,
                            Auth::id()
                        );
                    }
                }

                // Resolver el id de la FP "Canje Puntos" (creada por la migración 2026_05_07_140000).
                // Se usa para los VentaPago de canje de puntos para que en reportes por forma de pago
                // queden correctamente bajo "Canje Puntos" en lugar de mezclarse con la FP principal.
                $idFpCanjePuntos = FormaPago::where('codigo', 'CANJE_PUNTOS')->value('id');

                // Registrar canje de puntos como pago (RF-09)
                if ($this->canjePuntosActivo && $this->canjePuntosMonto > 0 && $this->clienteSeleccionado) {
                    $ventaPagoPuntos = VentaPago::create([
                        'venta_id' => $venta->id,
                        'forma_pago_id' => $idFpCanjePuntos ?? $this->formaPagoId,
                        'monto_base' => $this->canjePuntosMonto,
                        'ajuste_porcentaje' => 0,
                        'monto_ajuste' => 0,
                        'monto_final' => $this->canjePuntosMonto,
                        'es_pago_puntos' => true,
                        'puntos_usados' => $this->canjePuntosUnidades,
                        'afecta_caja' => false,
                        'estado' => 'activo',
                    ]);

                    $this->puntosService->canjearPuntosComoDescuento(
                        $this->clienteSeleccionado,
                        $this->sucursalId,
                        $this->canjePuntosMonto,
                        $ventaPagoPuntos->id,
                        $venta->id,
                        Auth::id()
                    );

                    $this->puntosService->actualizarCacheCliente($this->clienteSeleccionado);
                    $venta->update([
                        'puntos_usados' => $this->canjePuntosUnidades,
                        'puntos_canjeados_pago' => $this->canjePuntosUnidades,
                    ]);
                }

                // Registrar canjes de artículos por puntos (RF-10)
                $puntosArticulosCanjeados = $this->calcularPuntosUsadosEnArticulos();
                if ($puntosArticulosCanjeados > 0 && $this->clienteSeleccionado) {
                    $montoArticulosCanjeados = 0.0;

                    foreach ($this->items as $item) {
                        if ($item['pagado_con_puntos'] ?? false) {
                            $puntosItem = $this->calcularPuntosCanjePorPrecio((float) ($item['precio'] ?? 0)) * (float) ($item['cantidad'] ?? 1);
                            $this->puntosService->canjearArticuloConPuntos(
                                $this->clienteSeleccionado,
                                $item['articulo_id'],
                                $this->sucursalId,
                                $puntosItem,
                                $venta->id,
                                Auth::id()
                            );
                            $montoArticulosCanjeados += (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
                        }
                    }
                    $this->puntosService->actualizarCacheCliente($this->clienteSeleccionado);

                    // Crear VentaPago consolidado de canje de artículos. Va bajo la FP "Canje Puntos"
                    // así "todo lo pagado con puntos" en reportes = Σ VentaPago WHERE forma_pago_id = canje_puntos.
                    if ($montoArticulosCanjeados > 0) {
                        VentaPago::create([
                            'venta_id' => $venta->id,
                            'forma_pago_id' => $idFpCanjePuntos ?? $this->formaPagoId,
                            'monto_base' => $montoArticulosCanjeados,
                            'ajuste_porcentaje' => 0,
                            'monto_ajuste' => 0,
                            'monto_final' => $montoArticulosCanjeados,
                            'es_pago_puntos' => true,
                            'puntos_usados' => $puntosArticulosCanjeados,
                            'afecta_caja' => false,
                            'estado' => 'activo',
                        ]);
                    }

                    // Sumar puntos de artículos al contador de cabecera
                    $puntosUsadosTotal = ($venta->puntos_usados ?? 0) + $puntosArticulosCanjeados;
                    $venta->update([
                        'puntos_usados' => $puntosUsadosTotal,
                        'puntos_canjeados_articulos' => $puntosArticulosCanjeados,
                        'articulos_canjeados_monto' => round($montoArticulosCanjeados, 2),
                    ]);
                }

                // Registrar movimientos de cuenta corriente si el cliente tiene CC habilitada
                // Se hace DESPUÉS de la facturación para que los comprobantes fiscales ya existan
                if ($this->clienteSeleccionado) {
                    $clienteCC = Cliente::find($this->clienteSeleccionado);
                    if ($clienteCC && $clienteCC->tiene_cuenta_corriente) {
                        $ventaService = new \App\Services\VentaService;
                        $ventaService->procesarPagosCuentaCorriente($venta, auth()->id());
                    }
                }

                DB::connection('pymes_tenant')->commit();

                // Emisión fiscal diferida (caso cobro QR confirmado): la venta ya está
                // grabada. Si la FC falla, los pagos quedan en pendiente_de_facturar.
                if ($emitirFiscalPostCommit) {
                    try {
                        $comprobanteFiscal = $comprobanteFiscalService->crearComprobanteFiscal($venta, $opcionesFiscal);
                        Log::info('Comprobante fiscal emitido (post-commit)', [
                            'venta_id' => $venta->id,
                            'comprobante_id' => $comprobanteFiscal->id,
                        ]);
                    } catch (Exception $e) {
                        $facturacionPendiente = true;
                        Log::warning('FC post-commit falló (cobro confirmado) - pagos quedan pendiente_de_facturar', [
                            'venta_id' => $venta->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Mensaje de éxito
                $mensaje = "Venta #{$venta->numero} creada exitosamente";
                if ($comprobanteFiscal && $comprobanteFiscal->cae) {
                    $mensaje .= " - Factura {$comprobanteFiscal->numero_formateado} CAE: {$comprobanteFiscal->cae}";
                }

                $this->dispatch('toast-success', message: $mensaje);

                // El cobro entró pero la factura no se pudo emitir: avisar que quedó
                // pendiente (reintentable desde Cajas → Pagos Pendientes de Facturación).
                if ($facturacionPendiente) {
                    $this->dispatch('toast-warning', message: __('El cobro se registró, pero la facturación quedó pendiente. Reintentala desde Cajas → Pagos Pendientes de Facturación.'));
                }

                // Acumular puntos de fidelización (post-commit, no crítico)
                $this->acumularPuntosPostVenta($venta);

                // Mostrar advertencias de stock si las hay (modo 'advierte')
                if (! empty($this->ventaService->advertenciasStock)) {
                    foreach ($this->ventaService->advertenciasStock as $adv) {
                        $this->dispatch('toast-warning', message: __('Advertencia de stock').': '.$adv);
                    }
                }

                // Disparar evento para impresion automatica
                $this->dispararEventoImpresion($venta, $comprobanteFiscal);

                // Limpiar el estado del cobro QR para no arrastrarlo a la próxima venta.
                $this->resetCobroIntegracion();

                $this->limpiarCarrito(false); // Sin mensaje, ya mostramos toast-success

            } catch (Exception $e) {
                DB::connection('pymes_tenant')->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('Error al procesar venta con desglose', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('toast-error', message: 'Error al procesar venta: '.$e->getMessage());
        }
    }
}
