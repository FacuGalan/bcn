<?php

namespace App\Services\ARCA;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ComprobanteFiscal;
use App\Models\ComprobanteFiscalIva;
use App\Models\ComprobanteFiscalItem;
use App\Models\ComprobanteFiscalVenta;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\FormaPago;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestión de Comprobantes Fiscales
 *
 * Maneja la lógica de negocio para:
 * - Determinar si una venta requiere factura fiscal
 * - Determinar el tipo de comprobante (A, B, C)
 * - Crear comprobantes fiscales con integración ARCA
 */
class ComprobanteFiscalService
{
    protected ARCAService $arcaService;

    /**
     * Determina si una venta debe generar factura fiscal automáticamente
     *
     * Regla: sucursal.facturacion_fiscal_automatica = true
     *        AND alguna forma de pago tiene factura_fiscal = true
     */
    public function debeGenerarFacturaFiscal(Sucursal $sucursal, array $pagos): bool
    {
        // Verificar configuración de sucursal
        if (!$sucursal->facturacion_fiscal_automatica) {
            return false;
        }

        // Verificar si alguna forma de pago requiere factura fiscal
        foreach ($pagos as $pago) {
            $formaPago = FormaPago::find($pago['forma_pago_id']);
            if ($formaPago && $formaPago->factura_fiscal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina el tipo de comprobante según las condiciones de IVA
     *
     * Reglas AFIP (validación de campo CondicionIVAReceptorId):
     * - Emisor RI → Receptor RI/Monotributista = Factura A (códigos 1, 6, 13, 16)
     * - Emisor RI → Receptor CF/Exento/Otros = Factura B (códigos 4, 5, 7, 8-10, 15)
     * - Emisor Mono/Exento → Cualquier receptor = Factura C
     *
     * @return string factura_a, factura_b, factura_c
     */
    public function determinarTipoComprobante(Cuit $cuitEmisor, ?Cliente $cliente): string
    {
        $condicionEmisor = $cuitEmisor->condicionIva;

        if (!$condicionEmisor) {
            throw new Exception('El CUIT emisor no tiene condición de IVA configurada');
        }

        // Si el emisor es Monotributista o Exento, siempre es C
        if ($condicionEmisor->esMonotributista() || $condicionEmisor->esExento()) {
            return 'factura_c';
        }

        // Emisor es Responsable Inscripto
        if ($condicionEmisor->esResponsableInscripto()) {
            // Si no hay cliente o no tiene condición IVA, es B (consumidor final)
            if (!$cliente || !$cliente->condicion_iva_id) {
                return 'factura_b';
            }

            // Obtener condición del cliente a través de la relación
            $condicionCliente = $cliente->condicionIva;

            if (!$condicionCliente) {
                return 'factura_b';
            }

            // Factura A: Cliente RI o Monotributista (códigos AFIP 1, 6, 13, 16)
            // Según validación de AFIP, estos son los valores válidos para Factura A
            if ($condicionCliente->esResponsableInscripto() || $condicionCliente->esMonotributista()) {
                return 'factura_a';
            }

            // Factura B: Cliente CF, Exento u otros (códigos AFIP 4, 5, 7, etc.)
            return 'factura_b';
        }

        // Default: Factura C
        return 'factura_c';
    }

    /**
     * Crea un comprobante fiscal para una venta
     *
     * @param Venta $venta La venta a facturar
     * @param array $opciones Opciones adicionales:
     *   - punto_venta: PuntoVenta específico
     *   - tipo_comprobante: Tipo de comprobante forzado
     *   - pagos_facturar: Array de pagos a facturar (para facturación parcial)
     * @return ComprobanteFiscal
     */
    public function crearComprobanteFiscal(Venta $venta, array $opciones = []): ComprobanteFiscal
    {
        $sucursal = $venta->sucursal;

        // Obtener punto de venta y CUIT desde la caja de la venta
        // Estructura: Caja → Punto de Venta (defecto) → CUIT
        $caja = $venta->caja;

        if (!$caja) {
            throw new Exception('La venta no tiene caja asignada');
        }

        // Obtener punto de venta por defecto de la caja
        $puntoVenta = $opciones['punto_venta'] ?? $caja->puntoVentaDefecto();

        if (!$puntoVenta) {
            throw new Exception("La caja '{$caja->nombre}' no tiene punto de venta asignado. Configure los puntos de venta en Configuración → Empresa → Cajas.");
        }

        // Obtener CUIT desde el punto de venta
        $cuit = $puntoVenta->cuit;

        if (!$cuit) {
            throw new Exception("El punto de venta {$puntoVenta->numero_formateado} no tiene CUIT asociado");
        }

        if (!$cuit->activo) {
            throw new Exception("El CUIT {$cuit->numero_formateado} está inactivo");
        }

        // Determinar tipo de comprobante
        $tipoComprobante = $opciones['tipo_comprobante']
            ?? $this->determinarTipoComprobante($cuit, $venta->cliente);

        // Preparar datos del receptor
        $datosReceptor = $this->prepararDatosReceptor($venta->cliente, $tipoComprobante);

        // Determinar el total a facturar
        $totalAFacturar = $opciones['total_a_facturar'] ?? $venta->total_final;

        // Si hay facturación parcial con pagos específicos, calcular el total desde los pagos
        if (!empty($opciones['pagos_facturar']) && empty($opciones['total_a_facturar'])) {
            $totalAFacturar = array_sum(array_column($opciones['pagos_facturar'], 'monto_final'));

            Log::info('Facturación parcial', [
                'venta_id' => $venta->id,
                'total_venta' => $venta->total_final,
                'total_a_facturar' => $totalAFacturar,
            ]);
        }

        // Para Factura A/B: usar el desglose de IVA ya calculado si viene del frontend
        // Para Factura C: calcular directamente (no discrimina IVA)
        $esFacturaC = str_ends_with($tipoComprobante, '_c');

        if (!$esFacturaC && !empty($opciones['desglose_iva'])) {
            // Usar el desglose ya calculado por el frontend DIRECTAMENTE
            // El frontend ya calcula valores que cumplen AFIP: iva = neto * porcentaje
            $desgloseRecibido = $opciones['desglose_iva'];
            $alicuotasArray = $desgloseRecibido['por_alicuota'] ?? [];

            $detallesIva = [
                'neto_gravado' => 0,
                'neto_no_gravado' => 0,
                'neto_exento' => 0,
                'iva_total' => 0,
                'alicuotas' => [],
            ];

            $netoTotal = 0;
            $ivaTotal = 0;

            foreach ($alicuotasArray as $alicuota) {
                $porcentaje = $alicuota['alicuota'] ?? $alicuota['porcentaje'] ?? 21;
                $baseImponible = round($alicuota['neto'] ?? 0, 2);
                $importe = round($alicuota['iva'] ?? 0, 2);

                $netoTotal += $baseImponible;
                $ivaTotal += $importe;

                $detallesIva['alicuotas'][] = [
                    'codigo_afip' => ARCAService::getAlicuotaIVA($porcentaje),
                    'porcentaje' => $porcentaje,
                    'base_imponible' => $baseImponible,
                    'importe' => $importe,
                ];
            }

            $detallesIva['neto_gravado'] = round($netoTotal, 2);
            $detallesIva['iva_total'] = round($ivaTotal, 2);

            Log::info('Usando desglose de IVA del frontend (sin recálculo)', [
                'venta_id' => $venta->id,
                'neto_gravado' => $detallesIva['neto_gravado'],
                'iva_total' => $detallesIva['iva_total'],
                'total_a_facturar' => $totalAFacturar,
                'suma_neto_iva' => $netoTotal + $ivaTotal,
            ]);
        } else {
            // Calcular el desglose de IVA (para Factura C o cuando no viene del frontend)
            $detallesIva = $this->calcularDetallesIva($venta, $tipoComprobante, $totalAFacturar);
        }

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Determinar si es por el total de la venta o parcial (mixto)
            $esTotalVenta = abs($totalAFacturar - $venta->total_final) < 0.01;

            // Crear el comprobante en la BD (estado pendiente)
            $comprobante = ComprobanteFiscal::create([
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'cuit_id' => $cuit->id,
                'tipo' => $tipoComprobante,
                'letra' => $this->extraerLetra($tipoComprobante),
                'punto_venta_numero' => $puntoVenta->numero,
                'numero_comprobante' => 0, // Se actualizará con respuesta de AFIP
                'fecha_emision' => now()->toDateString(),
                'cliente_id' => $venta->cliente_id,
                'condicion_iva_id' => $datosReceptor['condicion_iva_id'],
                'receptor_nombre' => $datosReceptor['nombre'],
                'receptor_documento_tipo' => $datosReceptor['doc_tipo'],
                'receptor_documento_numero' => $datosReceptor['doc_nro'],
                'receptor_domicilio' => $datosReceptor['domicilio'],
                'neto_gravado' => $detallesIva['neto_gravado'],
                'neto_no_gravado' => $detallesIva['neto_no_gravado'],
                'neto_exento' => $detallesIva['neto_exento'],
                'iva_total' => $detallesIva['iva_total'],
                'tributos' => 0,
                'total' => $totalAFacturar,
                'estado' => 'pendiente',
                'usuario_id' => $venta->usuario_id,
                'es_total_venta' => $esTotalVenta,
            ]);

            // Guardar desglose de IVA
            foreach ($detallesIva['alicuotas'] as $alicuota) {
                ComprobanteFiscalIva::create([
                    'comprobante_fiscal_id' => $comprobante->id,
                    'codigo_afip' => $alicuota['codigo_afip'],
                    'alicuota' => $alicuota['porcentaje'],
                    'base_imponible' => $alicuota['base_imponible'],
                    'importe' => $alicuota['importe'],
                ]);
            }

            // Guardar items del comprobante
            foreach ($venta->detalles as $detalle) {
                ComprobanteFiscalItem::create([
                    'comprobante_fiscal_id' => $comprobante->id,
                    'venta_detalle_id' => $detalle->id,
                    'codigo' => $detalle->articulo->codigo ?? null,
                    'descripcion' => $detalle->articulo->nombre,
                    'cantidad' => $detalle->cantidad,
                    'unidad_medida' => 'u',
                    'precio_unitario' => $detalle->precio_unitario,
                    'bonificacion' => $detalle->descuento_monto ?? 0,
                    'subtotal' => $detalle->subtotal,
                    'iva_codigo_afip' => ARCAService::getAlicuotaIVA($detalle->iva_porcentaje ?? 21),
                    'iva_alicuota' => $detalle->iva_porcentaje ?? 21,
                    'iva_importe' => $detalle->iva_monto ?? 0,
                ]);
            }

            // Guardar relación con la venta
            ComprobanteFiscalVenta::create([
                'comprobante_fiscal_id' => $comprobante->id,
                'venta_id' => $venta->id,
                'monto' => $venta->total_final,
                'es_anulacion' => false,
            ]);

            // Solicitar CAE a AFIP
            $this->arcaService = new ARCAService($cuit);

            $datosAFIP = $this->prepararDatosParaAFIP($comprobante, $detallesIva, $datosReceptor, $puntoVenta);
            $respuestaCAE = $this->arcaService->solicitarCAE($datosAFIP);

            // Actualizar comprobante con respuesta de AFIP
            $comprobante->update([
                'numero_comprobante' => $respuestaCAE['numero'],
                'cae' => $respuestaCAE['cae'],
                'cae_vencimiento' => Carbon::createFromFormat('Ymd', $respuestaCAE['vencimiento'])->toDateString(),
                'estado' => 'autorizado',
                'afip_response' => $respuestaCAE['response_raw'],
            ]);

            // Actualizar cache de monto fiscal en la venta
            $venta->update([
                'monto_fiscal_cache' => $venta->total_final,
                'monto_no_fiscal_cache' => 0,
            ]);

            // Marcar los pagos como facturados
            if ($esTotalVenta) {
                // Factura por el total: marcar todos los pagos
                foreach ($venta->pagos as $pago) {
                    $pago->update([
                        'comprobante_fiscal_id' => $comprobante->id,
                        'monto_facturado' => $pago->monto_final,
                    ]);
                }
            } elseif (!empty($opciones['pagos_facturar'])) {
                // Factura parcial: marcar solo los pagos especificados
                Log::info('Marcando pagos como facturados (parcial)', [
                    'comprobante_id' => $comprobante->id,
                    'pagos_facturar' => $opciones['pagos_facturar'],
                ]);

                foreach ($opciones['pagos_facturar'] as $pagoData) {
                    $pagoId = $pagoData['id'] ?? null;
                    if ($pagoId) {
                        $pago = VentaPago::find($pagoId);
                        if ($pago) {
                            $montoFacturado = $pagoData['monto_facturado'] ?? $pagoData['monto_final'] ?? $pago->monto_final;
                            $pago->update([
                                'comprobante_fiscal_id' => $comprobante->id,
                                'monto_facturado' => $montoFacturado,
                            ]);

                            Log::info('Pago marcado como facturado', [
                                'pago_id' => $pagoId,
                                'monto_facturado' => $montoFacturado,
                            ]);
                        }
                    }
                }
            }

            DB::connection('pymes_tenant')->commit();

            Log::info('Comprobante fiscal creado exitosamente', [
                'comprobante_id' => $comprobante->id,
                'cae' => $comprobante->cae,
                'venta_id' => $venta->id,
            ]);

            return $comprobante->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error al crear comprobante fiscal', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Prepara los datos del receptor para el comprobante
     *
     * Códigos de documento AFIP:
     * - 80: CUIT
     * - 86: CUIL
     * - 96: DNI
     * - 99: Sin identificar (Consumidor Final)
     */
    protected function prepararDatosReceptor(?Cliente $cliente, string $tipoComprobante): array
    {
        // Si no hay cliente, usar consumidor final genérico
        if (!$cliente) {
            return [
                'nombre' => 'CONSUMIDOR FINAL',
                'doc_tipo' => '99',  // Código AFIP: Sin identificar
                'doc_nro' => '0',
                'domicilio' => null,
                'condicion_iva_id' => CondicionIva::CONSUMIDOR_FINAL,
                'condicion_iva_codigo_afip' => CondicionIva::CONSUMIDOR_FINAL, // RG 5616
            ];
        }

        // Determinar tipo de documento (códigos AFIP)
        $docTipo = '96';  // DNI por defecto
        $docNro = '0';

        if ($cliente->cuit) {
            $docTipo = '80';  // CUIT
            $docNro = preg_replace('/\D/', '', $cliente->cuit);
        } elseif ($cliente->dni) {
            $docTipo = '96';  // DNI
            $docNro = preg_replace('/\D/', '', $cliente->dni);
        }

        // Obtener condición IVA del cliente a través de la relación
        $condicionCliente = $cliente->condicionIva;
        $condicionIvaId = $condicionCliente?->id ?? null;
        $condicionIvaCodigoAfip = $condicionCliente?->codigo ?? CondicionIva::CONSUMIDOR_FINAL;

        // Para factura A se requiere CUIT
        if (str_contains($tipoComprobante, '_a') && $docTipo !== '80') {
            throw new Exception('Se requiere CUIT del cliente para emitir Factura A');
        }

        return [
            'nombre' => $cliente->razon_social ?? $cliente->nombre,
            'doc_tipo' => $docTipo,
            'doc_nro' => $docNro,
            'domicilio' => $cliente->direccion,
            'condicion_iva_id' => $condicionIvaId,
            'condicion_iva_codigo_afip' => $condicionIvaCodigoAfip, // RG 5616
        ];
    }

    /**
     * Calcula los detalles de IVA agrupados por alícuota
     *
     * Siempre calcula las alícuotas para registro interno,
     * aunque para Factura C no se envíen a AFIP.
     *
     * @param Venta $venta La venta
     * @param string $tipoComprobante Tipo de comprobante (factura_a, factura_b, factura_c)
     * @param float|null $totalAFacturar Total a facturar (para facturación parcial)
     */
    protected function calcularDetallesIva(Venta $venta, string $tipoComprobante, ?float $totalAFacturar = null): array
    {
        $alicuotas = [];
        $netoGravado = 0;
        $netoNoGravado = 0;
        $netoExento = 0;
        $ivaTotal = 0;
        $esFacturaC = str_ends_with($tipoComprobante, '_c');

        // Para Factura C: no se discrimina IVA, todo va como neto
        if ($esFacturaC) {
            // Usar el totalAFacturar si viene (facturación parcial), sino el total_final
            $total = $totalAFacturar ?? floatval($venta->total_final ?? $venta->total);

            return [
                'neto_gravado' => round($total, 2),
                'neto_no_gravado' => 0,
                'neto_exento' => 0,
                'iva_total' => 0,
                'alicuotas' => [
                    [
                        'codigo_afip' => 5, // 21% por defecto
                        'porcentaje' => 21,
                        'base_imponible' => round($total, 2),
                        'importe' => 0,
                    ]
                ],
            ];
        }

        // Para Factura A/B: discriminar IVA
        foreach ($venta->detalles as $detalle) {
            $porcentaje = $detalle->iva_porcentaje ?? 21;

            // El total del detalle ya incluye descuentos de promoción
            // Debemos recalcular neto e IVA sobre ese total (que tiene IVA incluido)
            $totalConIvaDetalle = floatval($detalle->total ?? $detalle->subtotal);
            $baseImponible = round($totalConIvaDetalle / (1 + $porcentaje / 100), 2);
            $ivaImporte = round($totalConIvaDetalle - $baseImponible, 2);

            if ($porcentaje == 0) {
                $netoExento += $baseImponible;
            } else {
                $netoGravado += $baseImponible;
                $ivaTotal += $ivaImporte;

                // Agrupar por alícuota
                $codigoAfip = ARCAService::getAlicuotaIVA($porcentaje);
                if (!isset($alicuotas[$codigoAfip])) {
                    $alicuotas[$codigoAfip] = [
                        'codigo_afip' => $codigoAfip,
                        'porcentaje' => $porcentaje,
                        'base_imponible' => 0,
                        'importe' => 0,
                    ];
                }
                $alicuotas[$codigoAfip]['base_imponible'] += $baseImponible;
                $alicuotas[$codigoAfip]['importe'] += $ivaImporte;
            }
        }

        // Incluir ajuste de forma de pago (recargos/descuentos) si existe
        $ajusteFormaPago = floatval($venta->ajuste_forma_pago ?? 0);

        if ($ajusteFormaPago != 0) {
            // Para Factura A/B: extraer neto e IVA del ajuste
            // Distribuir proporcionalmente según el peso de cada alícuota
            $totalConIva = $netoGravado + $ivaTotal;

            if ($totalConIva > 0) {
                foreach ($alicuotas as $codigoAfip => &$alicuota) {
                    $porcentaje = $alicuota['porcentaje'];
                    $pesoAlicuota = ($alicuota['base_imponible'] + $alicuota['importe']) / $totalConIva;

                    // Parte del ajuste que corresponde a esta alícuota
                    $ajusteAlicuota = $ajusteFormaPago * $pesoAlicuota;

                    // Extraer neto e IVA del ajuste (el ajuste viene con IVA incluido)
                    $netoAjuste = round($ajusteAlicuota / (1 + $porcentaje / 100), 2);
                    $ivaAjuste = round($ajusteAlicuota - $netoAjuste, 2);

                    $alicuota['base_imponible'] += $netoAjuste;
                    $alicuota['importe'] += $ivaAjuste;

                    $netoGravado += $netoAjuste;
                    $ivaTotal += $ivaAjuste;
                }
                unset($alicuota);
            }

            // Ajustar también neto exento si hay productos exentos
            if ($netoExento > 0 && ($netoGravado + $netoExento + $ivaTotal) > 0) {
                $totalBase = $netoGravado + $netoExento + $ivaTotal - $ajusteFormaPago;
                $pesoExento = $netoExento / $totalBase;
                $ajusteExento = round($ajusteFormaPago * $pesoExento, 2);
                $netoExento += $ajusteExento;
            }
        }

        return [
            'neto_gravado' => round($netoGravado, 2),
            'neto_no_gravado' => round($netoNoGravado, 2),
            'neto_exento' => round($netoExento, 2),
            'iva_total' => round($ivaTotal, 2),
            'alicuotas' => array_values($alicuotas),
        ];
    }

    /**
     * Prepara los datos en el formato requerido por AFIP
     *
     * Para Factura C (monotributistas/exentos):
     * - No se discrimina IVA
     * - ImpTotal = ImpNeto + ImpTrib
     * - ImpIVA, ImpTotConc, ImpOpEx = 0
     */
    protected function prepararDatosParaAFIP(
        ComprobanteFiscal $comprobante,
        array $detallesIva,
        array $datosReceptor,
        PuntoVenta $puntoVenta
    ): array {
        $tipoComprobanteAFIP = ARCAService::getTipoComprobanteAFIP($comprobante->tipo);
        $esFacturaC = str_ends_with($comprobante->tipo, '_c');

        // Para Factura C: todo va como neto, sin discriminar IVA
        if ($esFacturaC) {
            $datos = [
                'punto_venta' => $puntoVenta->numero,
                'tipo_comprobante' => $tipoComprobanteAFIP,
                'concepto' => 1, // Productos
                'doc_tipo' => ARCAService::getTipoDocumentoAFIP($datosReceptor['doc_tipo']),
                'doc_nro' => $datosReceptor['doc_nro'],
                'condicion_iva_receptor' => $datosReceptor['condicion_iva_codigo_afip'], // RG 5616
                'fecha' => now()->format('Ymd'),
                'imp_total' => $comprobante->total,
                'imp_tot_conc' => 0,
                'imp_neto' => $comprobante->total, // Todo el monto va como neto
                'imp_op_ex' => 0,
                'imp_iva' => 0,
                'imp_trib' => 0,
            ];
        } else {
            // Para Factura A/B: se discrimina IVA
            $datos = [
                'punto_venta' => $puntoVenta->numero,
                'tipo_comprobante' => $tipoComprobanteAFIP,
                'concepto' => 1, // Productos
                'doc_tipo' => ARCAService::getTipoDocumentoAFIP($datosReceptor['doc_tipo']),
                'doc_nro' => $datosReceptor['doc_nro'],
                'condicion_iva_receptor' => $datosReceptor['condicion_iva_codigo_afip'], // RG 5616
                'fecha' => now()->format('Ymd'),
                'imp_total' => $comprobante->total,
                'imp_tot_conc' => $detallesIva['neto_no_gravado'],
                'imp_neto' => $detallesIva['neto_gravado'],
                'imp_op_ex' => $detallesIva['neto_exento'],
                'imp_iva' => $detallesIva['iva_total'],
                'imp_trib' => 0,
            ];

            // Agregar alícuotas de IVA
            if (!empty($detallesIva['alicuotas'])) {
                $datos['iva'] = array_map(function ($alicuota) {
                    return [
                        'Id' => $alicuota['codigo_afip'],
                        'BaseImp' => $alicuota['base_imponible'],
                        'Importe' => $alicuota['importe'],
                    ];
                }, $detallesIva['alicuotas']);
            }
        }

        return $datos;
    }

    /**
     * Extrae la letra del tipo de comprobante
     */
    protected function extraerLetra(string $tipoComprobante): string
    {
        $partes = explode('_', $tipoComprobante);
        return strtoupper(end($partes));
    }

    /**
     * Prorratear los detalles de IVA según una proporción
     *
     * Aplica la proporción a cada alícuota de IVA por separado,
     * manteniendo la distribución correcta entre diferentes alícuotas (21%, 10.5%, etc.)
     */
    protected function prorratearDetallesIva(array $detallesIva, float $proporcion): array
    {
        return [
            'neto_gravado' => round($detallesIva['neto_gravado'] * $proporcion, 2),
            'neto_no_gravado' => round($detallesIva['neto_no_gravado'] * $proporcion, 2),
            'neto_exento' => round($detallesIva['neto_exento'] * $proporcion, 2),
            'iva_total' => round($detallesIva['iva_total'] * $proporcion, 2),
            'alicuotas' => array_map(function ($alicuota) use ($proporcion) {
                return [
                    'codigo_afip' => $alicuota['codigo_afip'],
                    'porcentaje' => $alicuota['porcentaje'],
                    'base_imponible' => round($alicuota['base_imponible'] * $proporcion, 2),
                    'importe' => round($alicuota['importe'] * $proporcion, 2),
                ];
            }, $detallesIva['alicuotas']),
        ];
    }

    /**
     * Crea una nota de crédito para anular un comprobante fiscal
     *
     * @param ComprobanteFiscal $comprobanteOriginal Comprobante a anular
     * @param Venta $venta La venta asociada
     * @param string|null $motivo Motivo de la anulación
     * @param int|null $usuarioId ID del usuario que realiza la operación
     * @return ComprobanteFiscal
     */
    public function crearNotaCredito(
        ComprobanteFiscal $comprobanteOriginal,
        Venta $venta,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): ComprobanteFiscal {
        if (!$comprobanteOriginal->esFactura()) {
            throw new Exception('Solo se pueden anular facturas');
        }

        if (!$comprobanteOriginal->estaAutorizado()) {
            throw new Exception('Solo se pueden anular comprobantes autorizados');
        }

        // Cargar relaciones necesarias del comprobante original
        $comprobanteOriginal->load(['detallesIva', 'items', 'sucursal', 'puntoVenta', 'cuit', 'cliente.condicionIva']);

        // Determinar tipo de nota de crédito según el comprobante original
        $tipoNC = str_replace('factura_', 'nota_credito_', $comprobanteOriginal->tipo);

        $sucursal = $comprobanteOriginal->sucursal;
        $puntoVenta = $comprobanteOriginal->puntoVenta;
        $cuit = $comprobanteOriginal->cuit;

        if (!$cuit->activo) {
            throw new Exception("El CUIT {$cuit->numero_formateado} está inactivo");
        }

        // Preparar datos del receptor (los mismos que la factura original)
        $datosReceptor = [
            'nombre' => $comprobanteOriginal->receptor_nombre,
            'doc_tipo' => $comprobanteOriginal->receptor_documento_tipo,
            'doc_nro' => $comprobanteOriginal->receptor_documento_numero,
            'domicilio' => $comprobanteOriginal->receptor_domicilio,
            'condicion_iva_id' => $comprobanteOriginal->condicion_iva_id,
            'condicion_iva_codigo_afip' => $comprobanteOriginal->cliente?->condicionIva?->codigo
                ?? CondicionIva::CONSUMIDOR_FINAL,
        ];

        // Determinar si es Factura C (no discrimina IVA)
        $esFacturaC = str_ends_with($comprobanteOriginal->tipo, '_c');

        // Obtener desglose de IVA del comprobante original
        $detallesIva = [
            'neto_gravado' => floatval($comprobanteOriginal->neto_gravado),
            'neto_no_gravado' => floatval($comprobanteOriginal->neto_no_gravado),
            'neto_exento' => floatval($comprobanteOriginal->neto_exento),
            'iva_total' => floatval($comprobanteOriginal->iva_total),
            'alicuotas' => [],
        ];

        // Recuperar alícuotas de IVA del comprobante original
        foreach ($comprobanteOriginal->detallesIva as $iva) {
            $detallesIva['alicuotas'][] = [
                'codigo_afip' => $iva->codigo_afip,
                'porcentaje' => $iva->alicuota,
                'base_imponible' => floatval($iva->base_imponible),
                'importe' => floatval($iva->importe),
            ];
        }

        // Si no hay alícuotas guardadas pero hay neto gravado, crear una alícuota por defecto
        // Esto puede pasar en comprobantes antiguos que no tenían desglose de IVA guardado
        // AFIP requiere IVA si ImpNeto > 0 para Facturas A/B
        if (empty($detallesIva['alicuotas']) && !$esFacturaC && $detallesIva['neto_gravado'] > 0) {
            // Si no hay IVA guardado, recalcular asumiendo 21%
            $ivaImporte = $detallesIva['iva_total'];
            if ($ivaImporte <= 0) {
                $ivaImporte = round($detallesIva['neto_gravado'] * 0.21, 2);
                $detallesIva['iva_total'] = $ivaImporte;
            }
            $detallesIva['alicuotas'][] = [
                'codigo_afip' => 5, // 21%
                'porcentaje' => 21,
                'base_imponible' => $detallesIva['neto_gravado'],
                'importe' => $ivaImporte,
            ];

            Log::warning('Usando alícuota de IVA por defecto (21%) para NC', [
                'comprobante_original_id' => $comprobanteOriginal->id,
                'neto_gravado' => $detallesIva['neto_gravado'],
                'iva_importe' => $ivaImporte,
            ]);
        }

        Log::info('Creando Nota de Crédito', [
            'comprobante_original_id' => $comprobanteOriginal->id,
            'tipo_nc' => $tipoNC,
            'es_factura_c' => $esFacturaC,
            'neto_gravado' => $detallesIva['neto_gravado'],
            'iva_total' => $detallesIva['iva_total'],
            'alicuotas_count' => count($detallesIva['alicuotas']),
            'alicuotas' => $detallesIva['alicuotas'],
        ]);

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Crear el comprobante de nota de crédito en la BD (estado pendiente)
            $notaCredito = ComprobanteFiscal::create([
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'cuit_id' => $cuit->id,
                'tipo' => $tipoNC,
                'letra' => $this->extraerLetra($tipoNC),
                'punto_venta_numero' => $puntoVenta->numero,
                'numero_comprobante' => 0, // Se actualizará con respuesta de AFIP
                'fecha_emision' => now()->toDateString(),
                'cliente_id' => $comprobanteOriginal->cliente_id,
                'condicion_iva_id' => $datosReceptor['condicion_iva_id'],
                'receptor_nombre' => $datosReceptor['nombre'],
                'receptor_documento_tipo' => $datosReceptor['doc_tipo'],
                'receptor_documento_numero' => $datosReceptor['doc_nro'],
                'receptor_domicilio' => $datosReceptor['domicilio'],
                'neto_gravado' => $detallesIva['neto_gravado'],
                'neto_no_gravado' => $detallesIva['neto_no_gravado'],
                'neto_exento' => $detallesIva['neto_exento'],
                'iva_total' => $detallesIva['iva_total'],
                'tributos' => 0,
                'total' => floatval($comprobanteOriginal->total),
                'estado' => 'pendiente',
                'comprobante_asociado_id' => $comprobanteOriginal->id,
                'usuario_id' => $usuarioId ?? $venta->usuario_id,
                'observaciones' => $motivo ?? 'Anulación de comprobante',
                'es_total_venta' => $comprobanteOriginal->es_total_venta, // Copiar del comprobante original
            ]);

            // Guardar desglose de IVA
            foreach ($detallesIva['alicuotas'] as $alicuota) {
                ComprobanteFiscalIva::create([
                    'comprobante_fiscal_id' => $notaCredito->id,
                    'codigo_afip' => $alicuota['codigo_afip'],
                    'alicuota' => $alicuota['porcentaje'],
                    'base_imponible' => $alicuota['base_imponible'],
                    'importe' => $alicuota['importe'],
                ]);
            }

            // Guardar items (copiados del comprobante original)
            foreach ($comprobanteOriginal->items as $item) {
                ComprobanteFiscalItem::create([
                    'comprobante_fiscal_id' => $notaCredito->id,
                    'venta_detalle_id' => $item->venta_detalle_id,
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'cantidad' => $item->cantidad,
                    'unidad_medida' => $item->unidad_medida,
                    'precio_unitario' => $item->precio_unitario,
                    'bonificacion' => $item->bonificacion,
                    'subtotal' => $item->subtotal,
                    'iva_codigo_afip' => $item->iva_codigo_afip,
                    'iva_alicuota' => $item->iva_alicuota,
                    'iva_importe' => $item->iva_importe,
                ]);
            }

            // Guardar relación con la venta (marcando como anulación)
            ComprobanteFiscalVenta::create([
                'comprobante_fiscal_id' => $notaCredito->id,
                'venta_id' => $venta->id,
                'monto' => floatval($comprobanteOriginal->total),
                'es_anulacion' => true,
            ]);

            // Solicitar CAE a AFIP
            $this->arcaService = new ARCAService($cuit);

            // DEBUG: Mostrar datos antes de preparar para AFIP
            Log::info('=== DEBUG NC: Datos antes de preparar para AFIP ===', [
                'comprobante_original' => [
                    'id' => $comprobanteOriginal->id,
                    'tipo' => $comprobanteOriginal->tipo,
                    'total' => $comprobanteOriginal->total,
                    'neto_gravado' => $comprobanteOriginal->neto_gravado,
                    'iva_total' => $comprobanteOriginal->iva_total,
                    'detallesIva_count' => $comprobanteOriginal->detallesIva->count(),
                    'detallesIva_raw' => $comprobanteOriginal->detallesIva->toArray(),
                ],
                'detallesIva_calculado' => $detallesIva,
                'nota_credito' => [
                    'id' => $notaCredito->id,
                    'tipo' => $notaCredito->tipo,
                    'total' => $notaCredito->total,
                    'neto_gravado' => $notaCredito->neto_gravado,
                    'iva_total' => $notaCredito->iva_total,
                ],
            ]);

            $datosAFIP = $this->prepararDatosParaAFIPNotaCredito(
                $notaCredito,
                $comprobanteOriginal,
                $detallesIva,
                $datosReceptor,
                $puntoVenta
            );

            // DEBUG: Mostrar datos que se enviarán a AFIP
            Log::info('=== DEBUG NC: Datos a enviar a AFIP ===', [
                'datosAFIP' => $datosAFIP,
            ]);

            $respuestaCAE = $this->arcaService->solicitarCAE($datosAFIP);

            // Actualizar nota de crédito con respuesta de AFIP
            $notaCredito->update([
                'numero_comprobante' => $respuestaCAE['numero'],
                'cae' => $respuestaCAE['cae'],
                'cae_vencimiento' => Carbon::createFromFormat('Ymd', $respuestaCAE['vencimiento'])->toDateString(),
                'estado' => 'autorizado',
                'afip_response' => $respuestaCAE['response_raw'],
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Nota de crédito creada exitosamente', [
                'nota_credito_id' => $notaCredito->id,
                'factura_original_id' => $comprobanteOriginal->id,
                'cae' => $notaCredito->cae,
                'venta_id' => $venta->id,
            ]);

            return $notaCredito->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error al crear nota de crédito', [
                'comprobante_original_id' => $comprobanteOriginal->id,
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Prepara los datos para AFIP específicamente para Nota de Crédito
     */
    protected function prepararDatosParaAFIPNotaCredito(
        ComprobanteFiscal $notaCredito,
        ComprobanteFiscal $comprobanteOriginal,
        array $detallesIva,
        array $datosReceptor,
        PuntoVenta $puntoVenta
    ): array {
        $tipoComprobanteAFIP = ARCAService::getTipoComprobanteAFIP($notaCredito->tipo);
        // Usar str_ends_with para verificar si termina en _c (no str_contains que matchea _credito)
        $esFacturaC = str_ends_with($notaCredito->tipo, '_c');

        // Datos base del comprobante
        if ($esFacturaC) {
            $datos = [
                'punto_venta' => $puntoVenta->numero,
                'tipo_comprobante' => $tipoComprobanteAFIP,
                'concepto' => 1, // Productos
                'doc_tipo' => ARCAService::getTipoDocumentoAFIP($datosReceptor['doc_tipo']),
                'doc_nro' => $datosReceptor['doc_nro'],
                'condicion_iva_receptor' => $datosReceptor['condicion_iva_codigo_afip'],
                'fecha' => now()->format('Ymd'),
                'imp_total' => $notaCredito->total,
                'imp_tot_conc' => 0,
                'imp_neto' => $notaCredito->total,
                'imp_op_ex' => 0,
                'imp_iva' => 0,
                'imp_trib' => 0,
            ];
        } else {
            // Para Factura A/B: se discrimina IVA
            // AFIP requiere que si ImpNeto > 0, debe haber alícuotas de IVA
            if ($detallesIva['neto_gravado'] > 0 && empty($detallesIva['alicuotas'])) {
                throw new Exception('Se requieren alícuotas de IVA para nota de crédito A/B con neto gravado > 0');
            }

            $datos = [
                'punto_venta' => $puntoVenta->numero,
                'tipo_comprobante' => $tipoComprobanteAFIP,
                'concepto' => 1, // Productos
                'doc_tipo' => ARCAService::getTipoDocumentoAFIP($datosReceptor['doc_tipo']),
                'doc_nro' => $datosReceptor['doc_nro'],
                'condicion_iva_receptor' => $datosReceptor['condicion_iva_codigo_afip'],
                'fecha' => now()->format('Ymd'),
                'imp_total' => $notaCredito->total,
                'imp_tot_conc' => $detallesIva['neto_no_gravado'],
                'imp_neto' => $detallesIva['neto_gravado'],
                'imp_op_ex' => $detallesIva['neto_exento'],
                'imp_iva' => $detallesIva['iva_total'],
                'imp_trib' => 0,
            ];

            // Agregar alícuotas de IVA (obligatorio si hay neto gravado)
            if (!empty($detallesIva['alicuotas'])) {
                $datos['iva'] = array_map(function ($alicuota) {
                    return [
                        'Id' => $alicuota['codigo_afip'],
                        'BaseImp' => $alicuota['base_imponible'],
                        'Importe' => $alicuota['importe'],
                    ];
                }, $detallesIva['alicuotas']);
            }

            // Validación final: si imp_neto > 0 debe haber IVA
            if ($datos['imp_neto'] > 0 && empty($datos['iva'])) {
                Log::error('Error: NC A/B con imp_neto > 0 pero sin alícuotas de IVA', [
                    'tipo_comprobante' => $tipoComprobanteAFIP,
                    'imp_neto' => $datos['imp_neto'],
                    'detalles_iva' => $detallesIva,
                    'comprobante_original' => [
                        'id' => $comprobanteOriginal->id,
                        'neto_gravado' => $comprobanteOriginal->neto_gravado,
                        'iva_total' => $comprobanteOriginal->iva_total,
                    ],
                ]);
                throw new Exception('Error interno: NC A/B requiere alícuotas de IVA cuando imp_neto > 0');
            }

            Log::info('Datos AFIP para Nota de Crédito A/B', [
                'tipo_comprobante' => $tipoComprobanteAFIP,
                'imp_neto' => $datos['imp_neto'],
                'imp_iva' => $datos['imp_iva'],
                'alicuotas_count' => count($datos['iva'] ?? []),
                'alicuotas' => $datos['iva'] ?? [],
            ]);
        }

        // Agregar comprobante asociado (OBLIGATORIO para NC)
        $tipoComprobanteOriginalAFIP = ARCAService::getTipoComprobanteAFIP($comprobanteOriginal->tipo);
        $datos['cbtes_asoc'] = [
            [
                'Tipo' => $tipoComprobanteOriginalAFIP,
                'PtoVta' => $comprobanteOriginal->punto_venta_numero,
                'Nro' => $comprobanteOriginal->numero_comprobante,
                'Cuit' => $comprobanteOriginal->cuit->numero_cuit,
                'CbteFch' => Carbon::parse($comprobanteOriginal->fecha_emision)->format('Ymd'),
            ],
        ];

        return $datos;
    }
}
