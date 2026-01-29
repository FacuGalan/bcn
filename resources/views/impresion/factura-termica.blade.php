<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 2mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            font-size: {{ $impresora->formato_papel === '58mm' ? '9px' : '11px' }};
            line-height: 1.3;
            color: #000;
            width: {{ $impresora->formato_papel === '58mm' ? '48mm' : '72mm' }};
            image-rendering: pixelated;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            -webkit-font-smoothing: none;
            -moz-osx-font-smoothing: unset;
            font-smooth: never;
            text-rendering: geometricPrecision;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .left { text-align: left; }
        .bold { font-weight: bold; }
        .normal { font-weight: normal; }
        .large { font-size: {{ $impresora->formato_papel === '58mm' ? '12px' : '14px' }}; }
        .xlarge { font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '18px' }}; }
        .small { font-size: {{ $impresora->formato_papel === '58mm' ? '8px' : '10px' }}; }

        .linea {
            border: none;
            border-top: 1px solid #000;
            height: 0;
            margin: 4px 0;
        }
        .linea-punteada {
            border: none;
            border-top: 1px dashed #000;
            height: 0;
            margin: 4px 0;
        }
        .linea-doble {
            border: none;
            border-top: 3px double #000;
            height: 0;
            margin: 4px 0;
        }

        .empresa-nombre {
            font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '18px' }};
            font-weight: bold;
        }

        .comprobante-tipo {
            font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '18px' }};
            font-weight: bold;
        }

        .total-final {
            font-size: {{ $impresora->formato_papel === '58mm' ? '16px' : '20px' }};
            font-weight: bold;
        }


        .fila {
            display: flex;
            justify-content: space-between;
        }

        .item-linea {
            margin-bottom: 6px;
        }

        .indent {
            padding-left: 8px;
        }
    </style>
</head>
<body>
    {{-- ========== ENCABEZADO EMPRESA ========== --}}
    <div class="center">
        <div class="empresa-nombre">{{ $comprobante->cuit->razon_social }}</div>
        @if($comprobante->cuit->nombre_fantasia)
            <div class="small">{{ $comprobante->cuit->nombre_fantasia }}</div>
        @endif
        <div class="small">
            @if($comprobante->cuit->domicilio_fiscal)
                {{ $comprobante->cuit->domicilio_fiscal }}
            @elseif($comprobante->sucursal?->direccion)
                {{ $comprobante->sucursal->direccion }}
            @endif
        </div>
        <div class="small">{{ $comprobante->cuit->numero_cuit }}</div>
        @if($comprobante->cuit->condicionIva)
            <div class="small">{{ $comprobante->cuit->condicionIva->nombre }}</div>
        @endif
        @if($comprobante->cuit->inicio_actividades)
            <div class="small">Inicio Act.: {{ $comprobante->cuit->inicio_actividades->format('d/m/Y') }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== TIPO DE COMPROBANTE ========== --}}
    <div class="center">
        <div class="comprobante-tipo">{{ $comprobante->tipo_legible }}</div>
        <div class="bold">#{{ $comprobante->numero_formateado }}</div>
    </div>

    <div class="fila" style="margin-top: 4px;">
        <span class="bold">Fecha: {{ $comprobante->fecha_emision->format('d/m/Y') }}</span>
        <span>{{ $comprobante->created_at->format('H:i') }}</span>
    </div>
    <div>Pto. Venta: {{ str_pad($comprobante->punto_venta_numero, 4, '0', STR_PAD_LEFT) }}</div>

    <div class="linea"></div>

    {{-- ========== CLIENTE ========== --}}
    @php
        $tiposDocumento = [
            '80' => 'CUIT', '86' => 'CUIL', '96' => 'DNI', '99' => 'Doc.',
            '0' => 'CI', '1' => 'CI', '2' => 'CI', '3' => 'Pasaporte', '4' => 'CI',
        ];
        $tipoDocLegible = $tiposDocumento[$comprobante->receptor_documento_tipo] ?? 'Doc.';
    @endphp
    <div class="bold">
        <div>CLIENTE:</div>
        <div>{{ $comprobante->receptor_nombre }}</div>
        @if($comprobante->receptor_documento_numero && $comprobante->receptor_documento_numero != '0')
            <div>{{ $tipoDocLegible }}: {{ $comprobante->receptor_documento_numero }}</div>
        @endif
        @if($comprobante->receptor_domicilio)
            <div>{{ $comprobante->receptor_domicilio }}</div>
        @endif
        @if($comprobante->cliente?->condicionIva)
            <div>{{ $comprobante->cliente->condicionIva->nombre }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== ITEMS ========== --}}
    @php
        $venta = $comprobante->ventas->first();
    @endphp

    @if($comprobante->es_total_venta && $venta)
        {{-- FACTURA POR EL TOTAL: mostrar detalle de articulos --}}
        <div class="center bold">DETALLE DE COMPRA</div>
        <div class="linea-punteada"></div>

        @php
            $sumatoriaSubtotales = 0;

            // Separar items individuales de los que pertenecen a promos especiales
            $itemsIndividuales = [];
            $gruposPromo = [];
            $idsEnGrupo = [];

            foreach ($venta->detalles as $detalle) {
                $promoEsp = null;
                foreach ($detalle->promocionesAplicadas as $pa) {
                    if ($pa->tipo_promocion === 'promocion_especial' && $pa->promocion_especial_id) {
                        $promoEsp = $pa;
                        break;
                    }
                }

                if ($promoEsp) {
                    $key = $promoEsp->promocion_especial_id;
                    if (!isset($gruposPromo[$key])) {
                        $gruposPromo[$key] = [
                            'nombre' => $promoEsp->descripcion_promocion,
                            'detalles' => [],
                            'descuento' => $promoEsp->descuento_aplicado,
                        ];
                    }
                    $gruposPromo[$key]['detalles'][] = $detalle;
                    $idsEnGrupo[] = $detalle->id;
                } else {
                    $itemsIndividuales[] = $detalle;
                }
            }
        @endphp

        {{-- Items individuales --}}
        @foreach($itemsIndividuales as $detalle)
            @php
                $cantFormateada = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
                $ivaFormateado = $detalle->iva_porcentaje == intval($detalle->iva_porcentaje)
                    ? number_format($detalle->iva_porcentaje, 0)
                    : number_format($detalle->iva_porcentaje, 1);
                $sumatoriaSubtotales += $detalle->subtotal;
            @endphp
            <div class="item-linea">
                <div class="bold">{{ $detalle->articulo->nombre }}</div>
                <div class="indent fila">
                    <span>{{ $cantFormateada }} x ${{ number_format($detalle->precio_unitario, 2, ',', '.') }} (IVA {{ $ivaFormateado }}%)</span>
                    <span class="bold">${{ number_format($detalle->subtotal, 2, ',', '.') }}</span>
                </div>
                @if($detalle->tiene_promocion && $detalle->descuento_promocion > 0)
                    @php
                        $nombrePromo = $detalle->promocionesAplicadas->first()?->descripcion_promocion ?? 'Promocion';
                    @endphp
                    <div class="indent fila">
                        <span>{{ $nombrePromo }}</span>
                        <span class="bold">-${{ number_format($detalle->descuento_promocion, 2, ',', '.') }}</span>
                    </div>
                @endif
                @if($detalle->descuento > 0 && !$detalle->tiene_promocion)
                    <div class="indent fila">
                        <span>Descuento</span>
                        <span class="bold">-${{ number_format($detalle->descuento, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Items agrupados por promo especial --}}
        @foreach($gruposPromo as $grupo)
            @foreach($grupo['detalles'] as $detalle)
                @php
                    $cantFormateada = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
                    $ivaFormateado = $detalle->iva_porcentaje == intval($detalle->iva_porcentaje)
                        ? number_format($detalle->iva_porcentaje, 0)
                        : number_format($detalle->iva_porcentaje, 1);
                    $sumatoriaSubtotales += $detalle->subtotal;
                @endphp
                <div class="item-linea">
                    <div class="bold">{{ $detalle->articulo->nombre }}</div>
                    <div class="indent fila">
                        <span>{{ $cantFormateada }} x ${{ number_format($detalle->precio_unitario, 2, ',', '.') }} (IVA {{ $ivaFormateado }}%)</span>
                        <span class="bold">${{ number_format($detalle->subtotal, 2, ',', '.') }}</span>
                    </div>
                </div>
            @endforeach
            @if($grupo['descuento'] > 0)
                <div class="indent fila">
                    <span>{{ $grupo['nombre'] }}</span>
                    <span class="bold">-${{ number_format($grupo['descuento'], 2, ',', '.') }}</span>
                </div>
            @endif
        @endforeach

        {{-- Subtotal sin descuentos --}}
        <div class="linea-doble"></div>
        <div class="fila bold">
            <span>Subtotal sin descuentos</span>
            <span>${{ number_format($sumatoriaSubtotales, 2, ',', '.') }}</span>
        </div>

        {{-- Promociones a nivel venta --}}
        @php
            $promocionesVenta = $venta->promociones ?? collect();
        @endphp
        @if($promocionesVenta->count() > 0)
            <div class="linea"></div>
            <div class="bold">PROMOCIONES APLICADAS:</div>
            @foreach($promocionesVenta as $promo)
                <div class="fila indent">
                    <span>{{ $promo->descripcion_promocion }}</span>
                    <span class="bold">-${{ number_format($promo->descuento_aplicado, 2, ',', '.') }}</span>
                </div>
            @endforeach
        @endif

        {{-- Ajuste por forma de pago --}}
        @php
            $totalAjusteFP = $venta->pagos->sum(fn($p) => ($p->monto_ajuste ?? 0) + ($p->recargo_cuotas_monto ?? 0));
        @endphp
        @if($totalAjusteFP != 0)
            <div class="linea"></div>
            <div class="fila bold">
                <span>{{ $totalAjusteFP > 0 ? 'Recargo F.P.' : 'Descuento F.P.' }}</span>
                <span>{{ $totalAjusteFP > 0 ? '+' : '-' }}${{ number_format(abs($totalAjusteFP), 2, ',', '.') }}</span>
            </div>
        @endif
    @else
        {{-- FACTURA PARCIAL (MIXTA): mostrar agrupado por alicuota --}}
        @foreach($comprobante->detallesIva as $alicuota)
            @php
                $subtotalConIva = $alicuota->base_imponible + $alicuota->importe;
                $alicuotaFormateada = $alicuota->alicuota == intval($alicuota->alicuota)
                    ? number_format($alicuota->alicuota, 0)
                    : number_format($alicuota->alicuota, 1);
            @endphp
            <div>Articulos varios (IVA {{ $alicuotaFormateada }}%)</div>
            <div class="fila indent">
                <span>Total:</span>
                <span class="bold">${{ number_format($subtotalConIva, 2, ',', '.') }}</span>
            </div>
        @endforeach
        @if($comprobante->neto_no_gravado > 0)
            <div>Articulos varios (No gravado)</div>
            <div class="fila indent">
                <span>Total:</span>
                <span class="bold">${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</span>
            </div>
        @endif
        @if($comprobante->neto_exento > 0)
            <div>Articulos varios (Exento)</div>
            <div class="fila indent">
                <span>Total:</span>
                <span class="bold">${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</span>
            </div>
        @endif
    @endif

    <div class="linea"></div>

    {{-- ========== TOTALES ========== --}}
    @if($comprobante->letra === 'A')
        <div class="right bold">
            @if($comprobante->neto_gravado > 0)
                <div>Neto Gravado: ${{ number_format($comprobante->neto_gravado, 2, ',', '.') }}</div>
            @endif
            @if($comprobante->neto_no_gravado > 0)
                <div>No Gravado: ${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</div>
            @endif
            @if($comprobante->neto_exento > 0)
                <div>Exento: ${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</div>
            @endif
            @foreach($comprobante->detallesIva ?? [] as $iva)
                @php
                    $ivaFormateado = $iva->alicuota == intval($iva->alicuota)
                        ? number_format($iva->alicuota, 0)
                        : number_format($iva->alicuota, 1);
                @endphp
                <div>IVA {{ $ivaFormateado }}%: ${{ number_format($iva->importe, 2, ',', '.') }}</div>
            @endforeach
        </div>
    @endif

    <div class="center">
        <div class="total-final">TOTAL</div>
        <div class="total-final">${{ number_format($comprobante->total, 2, ',', '.') }}</div>
    </div>

    {{-- ========== FORMAS DE PAGO ========== --}}
    @php
        if ($comprobante->es_total_venta && $venta) {
            $pagosAMostrar = $venta->pagos;
        } else {
            $pagosAMostrar = $comprobante->pagosFacturados ?? collect();
        }
    @endphp

    @if($pagosAMostrar->count() > 0)
        <div class="linea-punteada"></div>
        <div class="bold">FORMA DE PAGO:</div>
        @foreach($pagosAMostrar as $pago)
            <div class="fila indent">
                <span>{{ $pago->formaPago?->nombre ?? 'Efectivo' }}@if($pago->ajuste_porcentaje != 0) ({{ $pago->ajuste_porcentaje > 0 ? '+' : '' }}{{ number_format($pago->ajuste_porcentaje, 0) }}%)@endif</span>
                <span class="bold">${{ number_format($pago->monto_facturado ?? $pago->monto_final, 2, ',', '.') }}</span>
            </div>
            @if($pago->cuotas && $pago->cuotas > 1)
                <div class="indent small">
                    {{ $pago->cuotas }} cuotas de ${{ number_format($pago->monto_cuota, 2, ',', '.') }}
                    @if($pago->recargo_cuotas_porcentaje > 0)
                        (+{{ number_format($pago->recargo_cuotas_porcentaje, 0) }}%)
                    @endif
                </div>
            @endif
        @endforeach
    @endif

    <div class="linea"></div>

    {{-- ========== CAE ========== --}}
    <div class="center bold">
        <div>CAE: {{ $comprobante->cae }}</div>
        <div>Vto CAE: {{ $comprobante->cae_vencimiento->format('d/m/Y') }}</div>
    </div>

    <div class="linea-punteada"></div>

    {{-- ========== LEYENDA FISCAL ========== --}}
    @php
        $esMonotributista = $comprobante->cliente?->esMonotributista() ?? false;
    @endphp
    <div class="bold small">
        @if($comprobante->letra === 'A' && $esMonotributista)
            El credito fiscal discriminado en el presente comprobante, solo podra ser computado a efectos del Regimen de Sostenimiento e Inclusion Fiscal para Pequenos Contribuyentes de la Ley No 27.618
        @else
            Regimen de Transparencia Fiscal (Ley 27.743)<br>
            IVA contenido: ${{ number_format($comprobante->iva_total, 2, ',', '.') }}
        @endif
    </div>

    {{-- Texto legal adicional --}}
    @if($config?->texto_legal_factura)
        <div class="linea"></div>
        <div class="center small">{{ $config->texto_legal_factura }}</div>
    @endif

    {{-- Fecha impresion --}}
    <div class="center small" style="margin-top: 6px;">
        Impreso: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
