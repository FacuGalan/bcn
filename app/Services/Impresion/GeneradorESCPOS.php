<?php

namespace App\Services\Impresion;

use App\Models\Venta;
use App\Models\ComprobanteFiscal;
use App\Models\Impresora;
use App\Models\ConfiguracionImpresion;

/**
 * Generador de comandos ESC/POS para impresoras térmicas
 */
class GeneradorESCPOS
{
    // Comandos ESC/POS comunes
    private const ESC = "\x1B";
    private const GS = "\x1D";
    private const INIT = "\x1B\x40";
    private const BOLD_ON = "\x1B\x45\x01";
    private const BOLD_OFF = "\x1B\x45\x00";
    private const ALIGN_LEFT = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const ALIGN_RIGHT = "\x1B\x61\x02";
    private const FONT_NORMAL = "\x1B\x21\x00";
    private const FONT_DOUBLE_HEIGHT = "\x1B\x21\x10";
    private const FONT_DOUBLE_WIDTH = "\x1B\x21\x20";
    private const FONT_DOUBLE = "\x1B\x21\x30";
    private const CUT_PARTIAL = "\x1D\x56\x01";
    private const CUT_FULL = "\x1D\x56\x00";
    private const OPEN_DRAWER = "\x1B\x70\x00\x19\xFA";
    private const FEED_LINES = "\x1B\x64";
    // Code Page para caracteres espanoles (CP850 - Latin-1)
    private const CODE_PAGE_850 = "\x1B\x74\x02";
    // Alternativa: Windows-1252
    private const CODE_PAGE_1252 = "\x1B\x74\x10";

    protected int $ancho = 48;

    /**
     * Genera comandos ESC/POS para ticket de venta
     */
    public function generarTicketVenta(Venta $venta, Impresora $impresora, ?ConfiguracionImpresion $config): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        // Inicializar impresora y configurar code page para caracteres espanoles
        $comandos[] = $this->raw(self::INIT);
        $comandos[] = $this->raw(self::CODE_PAGE_850);

        // ========== ENCABEZADO ==========
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto($this->convertirTexto($venta->sucursal->nombre_publico ?? $venta->sucursal->nombre));
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // Direccion y telefono
        if ($venta->sucursal->direccion) {
            $comandos[] = $this->texto($this->convertirTexto($venta->sucursal->direccion));
        }
        if ($venta->sucursal->telefono) {
            $comandos[] = $this->texto("Tel: " . $venta->sucursal->telefono);
        }

        // ========== NUMERO DE TICKET DESTACADO ==========
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto("TICKET");
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("#" . $venta->numero);
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->linea();

        // ========== DATOS DE LA VENTA ==========
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->texto("Fecha: " . $venta->fecha->format('d/m/Y'));
        $comandos[] = $this->texto("Hora: " . $venta->fecha->format('H:i'));
        if ($venta->caja) {
            $comandos[] = $this->texto("Caja: " . $this->convertirTexto($venta->caja->nombre));
        }
        $comandos[] = $this->texto("Atendido por: " . $this->convertirTexto($venta->usuario?->name ?? '-'));

        // ========== CLIENTE ==========
        if ($venta->cliente) {
            $comandos[] = $this->linea('-');
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto("Cliente: " . $this->convertirTexto($venta->cliente->nombre));
            $comandos[] = $this->negrita(false);
            if ($venta->cliente->documento) {
                $tipoDoc = $venta->cliente->tipo_documento ?? 'DNI';
                $comandos[] = $this->texto("{$tipoDoc}: " . $venta->cliente->documento);
            }
            if ($venta->cliente->telefono) {
                $comandos[] = $this->texto("Tel: " . $venta->cliente->telefono);
            }
            if ($venta->cliente->direccion) {
                $comandos[] = $this->texto($this->truncar($this->convertirTexto($venta->cliente->direccion), $this->ancho));
            }
        }

        // ========== DETALLE DE ITEMS ==========
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto("DETALLE DE COMPRA");
        $comandos[] = $this->linea('-');
        $comandos[] = $this->alinear('izquierda');

        foreach ($venta->detalles as $detalle) {
            // Linea 1: Nombre del articulo
            $nombre = $this->truncar($this->convertirTexto($detalle->articulo->nombre), $this->ancho);
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto($nombre);
            $comandos[] = $this->negrita(false);

            // Linea 2: Cantidad x Precio unitario
            $cant = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
            $comandos[] = $this->texto("  {$cant} x \${$this->formatoNumero($detalle->precio_unitario)}");

            // Linea 3: Promocion aplicada (si existe)
            if ($detalle->tiene_promocion && $detalle->descuento_promocion > 0) {
                $nombrePromo = 'Promocion';
                if ($detalle->relationLoaded('promocionesAplicadas') && $detalle->promocionesAplicadas->count() > 0) {
                    $nombrePromo = $this->convertirTexto($detalle->promocionesAplicadas->first()->descripcion_promocion ?? 'Promocion');
                }
                $nombrePromo = $this->truncar($nombrePromo, 25);
                $comandos[] = $this->texto("  {$nombrePromo}: -\${$this->formatoNumero($detalle->descuento_promocion)}");
            }

            // Descuento manual (si no es promocion)
            if ($detalle->descuento > 0 && !$detalle->tiene_promocion) {
                $comandos[] = $this->texto("  Descuento: -\${$this->formatoNumero($detalle->descuento)}");
            }

            // Ajuste manual de precio
            if ($detalle->ajuste_manual_tipo && $detalle->ajuste_manual_valor) {
                $signo = $detalle->ajuste_manual_tipo === 'descuento' ? '-' : '+';
                $comandos[] = $this->texto("  Ajuste: {$signo}{$detalle->ajuste_manual_valor}%");
            }

            // Linea final: Total del item
            $totalLinea = $this->formatearDosColumnas("  Total:", "\${$this->formatoNumero($detalle->total)}");
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto($totalLinea);
            $comandos[] = $this->negrita(false);

            // Separador entre items
            $comandos[] = $this->texto("");
        }

        // ========== TOTALES ==========
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('derecha');

        // Subtotal (solo si hay descuentos o ajustes)
        $mostrarSubtotal = $venta->descuento > 0 || $venta->ajuste_forma_pago != 0;
        if ($mostrarSubtotal) {
            $comandos[] = $this->texto("Subtotal: \${$this->formatoNumero($venta->subtotal)}");
        }

        // Detalle de promociones aplicadas a nivel venta
        $promocionesVenta = $venta->relationLoaded('promociones') ? $venta->promociones : collect();
        if ($promocionesVenta->count() > 0) {
            $comandos[] = $this->linea('-');
            $comandos[] = $this->alinear('izquierda');
            $comandos[] = $this->texto("PROMOCIONES APLICADAS:");
            foreach ($promocionesVenta as $promo) {
                $nombrePromo = $this->truncar($this->convertirTexto($promo->descripcion_promocion), $this->ancho - 18);
                $montoPromo = "-\${$this->formatoNumero($promo->descuento_aplicado)}";
                // Nombre y monto en el mismo renglon
                $comandos[] = $this->texto($this->formatearDosColumnas("  " . $nombrePromo, $montoPromo));
            }
            $comandos[] = $this->alinear('derecha');
        } elseif ($venta->descuento > 0) {
            // Descuento general (si no hay detalle de promos)
            $comandos[] = $this->texto("Descuento: -\${$this->formatoNumero($venta->descuento)}");
        }

        // Ajuste por forma de pago
        if ($venta->ajuste_forma_pago != 0) {
            $signo = $venta->ajuste_forma_pago > 0 ? '+' : '-';
            $label = $venta->ajuste_forma_pago > 0 ? 'Recargo' : 'Desc. F.P.';
            $comandos[] = $this->texto("{$label}: {$signo}\${$this->formatoNumero(abs($venta->ajuste_forma_pago))}");
        }

        // Total final destacado
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto("T O T A L");
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("\${$this->formatoNumero($venta->total_final)}");
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->linea();

        // ========== FORMAS DE PAGO ==========
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->texto("FORMA DE PAGO:");

        foreach ($venta->pagos as $pago) {
            $fp = $this->convertirTexto($pago->formaPago?->nombre ?? 'Efectivo');
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto("  {$fp}: \${$this->formatoNumero($pago->monto_final)}");
            $comandos[] = $this->negrita(false);

            // Detalle de cuotas
            if ($pago->cuotas && $pago->cuotas > 1) {
                $cuotaInfo = "  {$pago->cuotas} cuotas de \${$this->formatoNumero($pago->monto_cuota)}";
                if ($pago->recargo_cuotas_porcentaje > 0) {
                    $cuotaInfo .= " (+{$pago->recargo_cuotas_porcentaje}%)";
                }
                $comandos[] = $this->texto($cuotaInfo);
            }

            // Ajuste de forma de pago
            if ($pago->monto_ajuste != 0) {
                $tipoAjuste = $pago->ajuste_porcentaje > 0 ? 'Recargo' : 'Descuento';
                $signo = $pago->ajuste_porcentaje > 0 ? '+' : '';
                $comandos[] = $this->texto("  {$tipoAjuste}: {$signo}{$pago->ajuste_porcentaje}%");
            }

            // Referencia
            if ($pago->referencia) {
                $comandos[] = $this->texto("  Ref: " . $this->truncar($this->convertirTexto($pago->referencia), $this->ancho - 8));
            }
        }

        // Vuelto
        $vueltoTotal = $venta->pagos->sum('vuelto');
        $montoRecibido = $venta->pagos->sum('monto_recibido');

        if ($vueltoTotal > 0) {
            $comandos[] = $this->linea('-');
            $comandos[] = $this->texto("Recibido: \${$this->formatoNumero($montoRecibido)}");
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto("Vuelto: \${$this->formatoNumero($vueltoTotal)}");
            $comandos[] = $this->negrita(false);
        }

        // ========== CUENTA CORRIENTE ==========
        if ($venta->es_cuenta_corriente && $venta->saldo_pendiente_cache > 0) {
            $comandos[] = $this->linea();
            $comandos[] = $this->alinear('centro');
            $comandos[] = $this->negrita(true);
            $comandos[] = $this->texto("** CUENTA CORRIENTE **");
            $comandos[] = $this->texto("Saldo: \${$this->formatoNumero($venta->saldo_pendiente_cache)}");
            $comandos[] = $this->negrita(false);
            if ($venta->fecha_vencimiento) {
                $comandos[] = $this->texto("Vence: " . $venta->fecha_vencimiento->format('d/m/Y'));
            }
        }

        // ========== PIE DE TICKET ==========
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto($this->convertirTexto($config?->texto_pie_ticket ?? "Gracias por su compra!"));

        // Observaciones
        if ($venta->observaciones) {
            $comandos[] = $this->linea('-');
            $comandos[] = $this->alinear('izquierda');
            $comandos[] = $this->texto("Obs: " . $this->truncar($this->convertirTexto($venta->observaciones), $this->ancho - 5));
        }

        // Fecha de impresion
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto(now()->format('d/m/Y H:i:s'));

        // Avanzar papel
        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    /**
     * Formatea un numero con separador de miles
     */
    protected function formatoNumero(float $numero, int $decimales = 2): string
    {
        return number_format($numero, $decimales, ',', '.');
    }

    /**
     * Convierte código de documento AFIP a nombre legible
     */
    protected function nombreTipoDocumento($codigo): string
    {
        $tipos = [
            '80' => 'CUIT',
            '86' => 'CUIL',
            '96' => 'DNI',
            '99' => 'Doc.',
            '0' => 'CI',
            '1' => 'CI',
            '2' => 'CI',
            '3' => 'Pasaporte',
            '4' => 'CI',
        ];

        return $tipos[$codigo] ?? 'Doc.';
    }

    /**
     * Genera comandos ESC/POS para factura
     */
    public function generarFactura(ComprobanteFiscal $comprobante, Impresora $impresora, ?ConfiguracionImpresion $config): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        $comandos[] = $this->raw(self::INIT);
        $comandos[] = $this->raw(self::CODE_PAGE_850);

        // Datos del emisor (empresa)
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto($this->convertirTexto($comprobante->cuit->razon_social));
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // Dirección y datos fiscales del emisor (en negrita para mejor legibilidad)
        $comandos[] = $this->negrita(true);
        if ($comprobante->cuit->domicilio_fiscal) {
            $comandos[] = $this->texto($this->convertirTexto($comprobante->cuit->domicilio_fiscal));
        } elseif ($comprobante->sucursal?->direccion) {
            $comandos[] = $this->texto($this->convertirTexto($comprobante->sucursal->direccion));
        }

        $comandos[] = $this->texto($comprobante->cuit->numero_cuit);

        // Condición IVA del emisor
        if ($comprobante->cuit->condicionIva) {
            $comandos[] = $this->texto($this->convertirTexto($comprobante->cuit->condicionIva->nombre));
        }

        // Inicio de actividades
        if ($comprobante->cuit->inicio_actividades) {
            $comandos[] = $this->texto("Inicio Act.: " . $comprobante->cuit->inicio_actividades->format('d/m/Y'));
        }
        $comandos[] = $this->negrita(false);

        // Tipo de comprobante y número
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto($this->convertirTexto($comprobante->tipo_legible));
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->texto("Nro: " . $comprobante->numero_formateado);
        $comandos[] = $this->negrita(false);

        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->texto("Fecha: " . $comprobante->fecha_emision->format('d/m/Y'));
        $comandos[] = $this->texto("Hora: " . $comprobante->created_at->format('H:i'));
        $comandos[] = $this->texto("Pto. Venta: " . str_pad($comprobante->punto_venta_numero, 4, '0', STR_PAD_LEFT));
        $comandos[] = $this->negrita(false);

        // Datos del receptor (cliente)
        $comandos[] = $this->linea();
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->texto("CLIENTE:");
        $comandos[] = $this->texto($this->convertirTexto($comprobante->receptor_nombre));

        // Tipo de documento legible (CUIT, DNI, etc. en vez de código 80, 96, etc.)
        // No mostrar si el documento es 0
        if ($comprobante->receptor_documento_numero && $comprobante->receptor_documento_numero != '0') {
            $tipoDocLegible = $this->nombreTipoDocumento($comprobante->receptor_documento_tipo);
            $comandos[] = $this->texto("{$tipoDocLegible}: " . $comprobante->receptor_documento_numero);
        }

        // Domicilio del cliente si existe
        if ($comprobante->receptor_domicilio) {
            $comandos[] = $this->texto($this->truncar($this->convertirTexto($comprobante->receptor_domicilio), $this->ancho));
        }

        // Condición IVA del cliente
        if ($comprobante->cliente?->condicionIva) {
            $comandos[] = $this->texto($this->convertirTexto($comprobante->cliente->condicionIva->nombre));
        }
        $comandos[] = $this->negrita(false);

        // Items
        $comandos[] = $this->linea();

        // Obtener la venta asociada
        $venta = $comprobante->ventas->first();

        if ($comprobante->es_total_venta && $venta) {
            // FACTURA POR EL TOTAL: mostrar detalle igual que el ticket
            $comandos[] = $this->alinear('centro');
            $comandos[] = $this->texto("DETALLE DE COMPRA");
            $comandos[] = $this->linea('-');
            $comandos[] = $this->alinear('izquierda');

            foreach ($venta->detalles as $detalle) {
                // Nombre del articulo
                $nombre = $this->truncar($this->convertirTexto($detalle->articulo->nombre), $this->ancho);
                $comandos[] = $this->negrita(true);
                $comandos[] = $this->texto($nombre);
                $comandos[] = $this->negrita(false);

                // Cantidad x Precio unitario
                $cant = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
                $ivaFormateado = $detalle->iva_porcentaje == intval($detalle->iva_porcentaje)
                    ? number_format($detalle->iva_porcentaje, 0)
                    : number_format($detalle->iva_porcentaje, 1);
                $comandos[] = $this->texto("  {$cant} x \${$this->formatoNumero($detalle->precio_unitario)} (IVA {$ivaFormateado}%)");

                // Promocion aplicada (si existe)
                if ($detalle->tiene_promocion && $detalle->descuento_promocion > 0) {
                    $nombrePromo = 'Promocion';
                    if ($detalle->relationLoaded('promocionesAplicadas') && $detalle->promocionesAplicadas->count() > 0) {
                        $nombrePromo = $this->convertirTexto($detalle->promocionesAplicadas->first()->descripcion_promocion ?? 'Promocion');
                    }
                    $nombrePromo = $this->truncar($nombrePromo, 25);
                    $comandos[] = $this->texto("  {$nombrePromo}: -\${$this->formatoNumero($detalle->descuento_promocion)}");
                }

                // Descuento manual (si no es promocion)
                if ($detalle->descuento > 0 && !$detalle->tiene_promocion) {
                    $comandos[] = $this->texto("  Descuento: -\${$this->formatoNumero($detalle->descuento)}");
                }

                // Ajuste manual de precio
                if ($detalle->ajuste_manual_tipo && $detalle->ajuste_manual_valor) {
                    $signo = $detalle->ajuste_manual_tipo === 'descuento' ? '-' : '+';
                    $comandos[] = $this->texto("  Ajuste: {$signo}{$detalle->ajuste_manual_valor}%");
                }

                // Total del item
                $totalLinea = $this->formatearDosColumnas("  Total:", "\${$this->formatoNumero($detalle->total)}");
                $comandos[] = $this->negrita(true);
                $comandos[] = $this->texto($totalLinea);
                $comandos[] = $this->negrita(false);
                $comandos[] = $this->texto("");
            }

            // Promociones aplicadas a nivel venta
            $promocionesVenta = $venta->relationLoaded('promociones') ? $venta->promociones : $venta->promociones;
            if ($promocionesVenta->count() > 0) {
                $comandos[] = $this->linea('-');
                $comandos[] = $this->alinear('izquierda');
                $comandos[] = $this->texto("PROMOCIONES APLICADAS:");
                foreach ($promocionesVenta as $promo) {
                    $nombrePromo = $this->truncar($this->convertirTexto($promo->descripcion_promocion), $this->ancho - 18);
                    $montoPromo = "-\${$this->formatoNumero($promo->descuento_aplicado)}";
                    $comandos[] = $this->texto($this->formatearDosColumnas("  " . $nombrePromo, $montoPromo));
                }
            }
        } else {
            // FACTURA PARCIAL (MIXTA): mostrar articulos agrupados por alicuota
            // Solo mostrar el total (neto+iva), no separar
            foreach ($comprobante->detallesIva as $alicuota) {
                $subtotalConIva = $alicuota->base_imponible + $alicuota->importe;
                // Formatear alicuota sin redondear (10.5 no debe ser 11)
                $alicuotaFormateada = $alicuota->alicuota == intval($alicuota->alicuota)
                    ? number_format($alicuota->alicuota, 0)
                    : number_format($alicuota->alicuota, 1);
                $descripcion = "Articulos varios (IVA {$alicuotaFormateada}%)";
                $comandos[] = $this->texto($this->truncar($descripcion, $this->ancho));
                $comandos[] = $this->texto($this->formatearDosColumnas("  Total:", "\${$this->formatoNumero($subtotalConIva)}"));
            }
            // Neto no gravado
            if ($comprobante->neto_no_gravado > 0) {
                $comandos[] = $this->texto("Articulos varios (No gravado)");
                $comandos[] = $this->texto($this->formatearDosColumnas("  Total:", "\${$this->formatoNumero($comprobante->neto_no_gravado)}"));
            }
            // Neto exento
            if ($comprobante->neto_exento > 0) {
                $comandos[] = $this->texto("Articulos varios (Exento)");
                $comandos[] = $this->texto($this->formatearDosColumnas("  Total:", "\${$this->formatoNumero($comprobante->neto_exento)}"));
            }
        }

        // Totales
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('derecha');

        // Solo mostrar desglose neto/IVA en Factura A
        if ($comprobante->letra === 'A') {
            $comandos[] = $this->negrita(true);
            if ($comprobante->neto_gravado > 0) {
                $comandos[] = $this->texto("Neto Gravado: \${$this->formatoNumero($comprobante->neto_gravado)}");
            }
            if ($comprobante->neto_no_gravado > 0) {
                $comandos[] = $this->texto("No Gravado: \${$this->formatoNumero($comprobante->neto_no_gravado)}");
            }
            if ($comprobante->neto_exento > 0) {
                $comandos[] = $this->texto("Exento: \${$this->formatoNumero($comprobante->neto_exento)}");
            }
            // Desglose de IVA por alicuota
            foreach ($comprobante->detallesIva ?? [] as $iva) {
                $ivaFormateado = $iva->alicuota == intval($iva->alicuota)
                    ? number_format($iva->alicuota, 0)
                    : number_format($iva->alicuota, 1);
                $comandos[] = $this->texto("IVA {$ivaFormateado}%: \${$this->formatoNumero($iva->importe)}");
            }
            $comandos[] = $this->negrita(false);
        }

        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("TOTAL: \${$this->formatoNumero($comprobante->total)}");
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // Formas de pago
        // Para factura total: mostrar todos los pagos de la venta
        // Para factura parcial: mostrar solo los pagos facturados con este comprobante
        $pagosAMostrar = collect();
        if ($comprobante->es_total_venta && $venta) {
            $pagosAMostrar = $venta->pagos;
        } else {
            $pagosAMostrar = $comprobante->pagosFacturados ?? collect();
        }

        if ($pagosAMostrar->count() > 0) {
            $comandos[] = $this->linea('-');
            $comandos[] = $this->alinear('izquierda');
            $comandos[] = $this->texto("FORMA DE PAGO:");
            foreach ($pagosAMostrar as $pago) {
                $nombrePago = $this->convertirTexto($pago->formaPago?->nombre ?? 'Efectivo');
                $montoMostrar = $pago->monto_facturado ?? $pago->monto_final;
                $comandos[] = $this->texto($this->formatearDosColumnas("  " . $nombrePago, "\${$this->formatoNumero($montoMostrar)}"));
                if ($pago->cuotas && $pago->cuotas > 1) {
                    $recargoTxt = $pago->recargo_cuotas_porcentaje > 0 ? " (+{$pago->recargo_cuotas_porcentaje}%)" : '';
                    $comandos[] = $this->texto("    {$pago->cuotas} cuotas de \${$this->formatoNumero($pago->monto_cuota)}{$recargoTxt}");
                }
            }
        }

        // CAE
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto("CAE: " . $comprobante->cae);
        $comandos[] = $this->texto("Vto CAE: " . $comprobante->cae_vencimiento->format('d/m/Y'));

        // Leyenda fiscal obligatoria
        $comandos[] = $this->linea('-');
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->negrita(true);

        // Si es Factura A y el cliente es monotributista: Ley 27.618
        // En cualquier otro caso: Régimen de transparencia fiscal (Ley 27.743)
        $esMonotributista = $comprobante->cliente?->esMonotributista() ?? false;

        if ($comprobante->letra === 'A' && $esMonotributista) {
            $leyendaFiscal = "El credito fiscal discriminado en el presente comprobante, solo podra ser computado a efectos del Regimen de Sostenimiento e Inclusion Fiscal para Pequenos Contribuyentes de la Ley No 27.618";
            $palabras = explode(' ', $leyendaFiscal);
            $linea = '';
            foreach ($palabras as $palabra) {
                if (strlen($linea . ' ' . $palabra) > $this->ancho) {
                    $comandos[] = $this->texto($linea);
                    $linea = $palabra;
                } else {
                    $linea .= ($linea ? ' ' : '') . $palabra;
                }
            }
            if ($linea) {
                $comandos[] = $this->texto($linea);
            }
        } else {
            $comandos[] = $this->texto("Regimen de Transparencia Fiscal");
            $comandos[] = $this->texto("(Ley 27.743)");
            $comandos[] = $this->texto("IVA contenido: \${$this->formatoNumero($comprobante->iva_total)}");
        }
        $comandos[] = $this->negrita(false);

        // Texto legal adicional
        if ($config?->texto_legal_factura) {
            $comandos[] = $this->linea();
            $palabras = explode(' ', $this->convertirTexto($config->texto_legal_factura));
            $linea = '';
            foreach ($palabras as $palabra) {
                if (strlen($linea . ' ' . $palabra) > $this->ancho) {
                    $comandos[] = $this->texto($linea);
                    $linea = $palabra;
                } else {
                    $linea .= ($linea ? ' ' : '') . $palabra;
                }
            }
            if ($linea) {
                $comandos[] = $this->texto($linea);
            }
        }

        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    /**
     * Genera comandos para prueba de impresión
     */
    public function generarPrueba(Impresora $impresora): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        $comandos[] = $this->raw(self::INIT);
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("PRUEBA DE IMPRESION");
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->linea();
        $comandos[] = $this->texto("BCN Pymes");
        $comandos[] = $this->texto("Impresora: " . $impresora->nombre);
        $comandos[] = $this->texto("Tipo: " . $impresora->tipo_legible);
        $comandos[] = $this->texto("Ancho: " . $impresora->ancho_caracteres . " caracteres");
        $comandos[] = $this->linea();
        $comandos[] = $this->texto("Impresora configurada");
        $comandos[] = $this->texto("correctamente.");
        $comandos[] = $this->linea();
        $comandos[] = $this->texto(date('d/m/Y H:i:s'));
        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    // Métodos auxiliares
    protected function raw(string $bytes): array
    {
        return ['type' => 'raw', 'data' => base64_encode($bytes)];
    }

    protected function texto(string $texto): array
    {
        return ['type' => 'raw', 'data' => base64_encode($texto . "\n")];
    }

    protected function alinear(string $pos): array
    {
        $cmd = match($pos) {
            'izquierda' => self::ALIGN_LEFT,
            'centro' => self::ALIGN_CENTER,
            'derecha' => self::ALIGN_RIGHT,
            default => self::ALIGN_LEFT,
        };
        return $this->raw($cmd);
    }

    protected function negrita(bool $on): array
    {
        return $this->raw($on ? self::BOLD_ON : self::BOLD_OFF);
    }

    protected function fuenteNormal(): array
    {
        return $this->raw(self::FONT_NORMAL);
    }

    protected function fuenteDoble(): array
    {
        return $this->raw(self::FONT_DOUBLE);
    }

    protected function linea(string $char = '='): array
    {
        return $this->texto(str_repeat($char, $this->ancho));
    }

    protected function avanzar(int $lineas): array
    {
        return $this->raw(self::FEED_LINES . chr($lineas));
    }

    public function cortar(bool $parcial = true): array
    {
        return $this->raw($parcial ? self::CUT_PARTIAL : self::CUT_FULL);
    }

    public function abrirCajon(): array
    {
        return $this->raw(self::OPEN_DRAWER);
    }

    protected function formatearColumnas(array $valores, array $anchos): string
    {
        $linea = '';
        foreach ($valores as $i => $valor) {
            $ancho = $anchos[$i] ?? 10;
            if ($i === count($valores) - 1) {
                $linea .= str_pad($valor, $ancho, ' ', STR_PAD_LEFT);
            } else {
                $linea .= str_pad(mb_substr($valor, 0, $ancho), $ancho);
            }
        }
        return $linea;
    }

    protected function truncar(string $texto, int $max): string
    {
        return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max - 3) . '...' : $texto;
    }

    /**
     * Convierte texto UTF-8 a CP850 para impresoras termicas
     * Reemplaza caracteres especiales que no estan en CP850
     */
    protected function convertirTexto(string $texto): string
    {
        // Mapa de caracteres especiales a sus equivalentes CP850
        $reemplazos = [
            // Vocales acentuadas minusculas (UTF-8 => CP850)
            "\xC3\xA1" => "\xA0", // á
            "\xC3\xA9" => "\x82", // é
            "\xC3\xAD" => "\xA1", // í
            "\xC3\xB3" => "\xA2", // ó
            "\xC3\xBA" => "\xA3", // ú
            // Vocales acentuadas mayusculas
            "\xC3\x81" => "\xB5", // Á
            "\xC3\x89" => "\x90", // É
            "\xC3\x8D" => "\xD6", // Í
            "\xC3\x93" => "\xE0", // Ó
            "\xC3\x9A" => "\xE9", // Ú
            // Ene
            "\xC3\xB1" => "\xA4", // ñ
            "\xC3\x91" => "\xA5", // Ñ
            // Dieresis
            "\xC3\xBC" => "\x81", // ü
            "\xC3\x9C" => "\x9A", // Ü
            // Signos de apertura
            "\xC2\xBF" => "?",    // ¿
            "\xC2\xA1" => "!",    // ¡
            // Otros caracteres comunes
            "\xC2\xB0" => "\xF8", // °
            "\xC2\xBA" => "\xF8", // º
            "\xC2\xAA" => "\xA6", // ª
            "\xE2\x82\xAC" => "EUR", // €
            "\xE2\x80\x93" => "-",   // guion medio
            "\xE2\x80\x94" => "-",   // guion largo
            "\xE2\x80\x98" => "'",   // comilla simple izq
            "\xE2\x80\x99" => "'",   // comilla simple der
            "\xE2\x80\x9C" => "\"",  // comilla doble izq
            "\xE2\x80\x9D" => "\"",  // comilla doble der
        ];

        return strtr($texto, $reemplazos);
    }

    /**
     * Formatea dos textos en columnas (izquierda y derecha)
     */
    protected function formatearDosColumnas(string $izq, string $der): string
    {
        $espacios = $this->ancho - mb_strlen($izq) - mb_strlen($der);
        if ($espacios < 1) {
            $espacios = 1;
        }
        return $izq . str_repeat(' ', $espacios) . $der;
    }
}
