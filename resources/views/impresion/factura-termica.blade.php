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
            font-weight: 700;
            image-rendering: pixelated;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            /* Desactivar suavizado de fuentes para texto mas nitido */
            -webkit-font-smoothing: none;
            -moz-osx-font-smoothing: unset;
            font-smooth: never;
            text-rendering: geometricPrecision;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: {{ $impresora->formato_papel === '58mm' ? '9px' : '12px' }}; font-weight: normal; }
        .large { font-size: {{ $impresora->formato_papel === '58mm' ? '12px' : '14px' }}; }
        .xlarge { font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '18px' }}; }

        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .divider-solid {
            border-top: 1px solid #000;
            margin: 4px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        .empresa-nombre {
            font-size: {{ $impresora->formato_papel === '58mm' ? '13px' : '16px' }};
            font-weight: bold;
        }

        .comprobante-tipo {
            text-align: center;
            margin: 8px 0;
            padding: 5px 0;
        }
        .comprobante-nombre {
            font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '16px' }};
            font-weight: bold;
        }
        .comprobante-numero {
            font-size: {{ $impresora->formato_papel === '58mm' ? '12px' : '14px' }};
            font-weight: bold;
        }

        .datos-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        .datos-row .label {
            font-weight: bold;
        }

        .cliente-section {
            margin: 5px 0;
            padding: 5px;
            background: #f5f5f5;
        }

        .items-table {
            width: 100%;
            margin: 5px 0;
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '13px' }};
        }
        .items-table th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 2px 0;
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '11px' }};
            font-weight: bold;
        }
        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .items-table .cantidad { width: 10%; text-align: right; padding-right: 3px; }
        .items-table .descripcion { width: {{ $comprobante->letra === 'A' ? '50%' : '60%' }}; }
        .items-table .iva { width: 12%; text-align: right; }
        .items-table .precio { width: {{ $comprobante->letra === 'A' ? '28%' : '30%' }}; text-align: right; }

        .item-promo {
            color: #000;
            font-size: {{ $impresora->formato_papel === '58mm' ? '9px' : '11px' }};
            font-weight: normal;
        }

        .totales {
            margin-top: 5px;
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '13px' }};
        }
        .totales-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-weight: bold;
        }
        .total-final {
            text-align: center;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        .total-final .total-label {
            font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '16px' }};
            font-weight: bold;
        }
        .total-final .total-amount {
            font-size: {{ $impresora->formato_papel === '58mm' ? '18px' : '22px' }};
            font-weight: bold;
        }

        .pagos-section {
            margin: 5px 0;
            padding: 5px;
            background: #f0f0f0;
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '13px' }};
        }

        .cae-section {
            margin-top: 8px;
            padding: 5px;
            border: 1px solid #000;
            text-align: center;
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '12px' }};
        }

        .leyenda-fiscal {
            margin-top: 5px;
            padding: 5px;
            border: 1px solid #000;
            font-size: {{ $impresora->formato_papel === '58mm' ? '9px' : '12px' }};
            text-align: left;
            font-weight: bold;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: {{ $impresora->formato_papel === '58mm' ? '9px' : '12px' }};
            font-weight: normal;
        }
    </style>
</head>
<body>
    {{-- Encabezado empresa --}}
    <div class="header">
        <div class="empresa-nombre">{{ $comprobante->cuit->razon_social }}</div>
        @if($comprobante->cuit->nombre_fantasia)
            <div class="bold">{{ $comprobante->cuit->nombre_fantasia }}</div>
        @endif
        <div class="bold">
            @if($comprobante->cuit->domicilio_fiscal)
                {{ $comprobante->cuit->domicilio_fiscal }}
            @elseif($comprobante->sucursal?->direccion)
                {{ $comprobante->sucursal->direccion }}
            @endif
        </div>
        <div class="bold">{{ $comprobante->cuit->numero_cuit }}</div>
        @if($comprobante->cuit->condicionIva)
            <div class="bold">{{ $comprobante->cuit->condicionIva->nombre }}</div>
        @endif
        @if($comprobante->cuit->inicio_actividades)
            <div>Inicio Act.: {{ $comprobante->cuit->inicio_actividades->format('d/m/Y') }}</div>
        @endif
    </div>

    <div class="divider-solid"></div>

    {{-- Tipo de comprobante --}}
    <div class="comprobante-tipo">
        <div class="comprobante-nombre">{{ $comprobante->tipo_legible }}</div>
        <div class="comprobante-numero">Nro: {{ $comprobante->numero_formateado }}</div>
    </div>

    {{-- Datos del comprobante --}}
    <div class="datos-row" style="font-weight: normal; font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '12px' }};">
        <span>Fecha: {{ $comprobante->fecha_emision->format('d/m/Y') }}</span>
        <span>Hora: {{ $comprobante->created_at->format('H:i') }}</span>
    </div>

    <div class="divider-solid"></div>

    {{-- Datos del cliente --}}
    @php
        $tiposDocumento = [
            '80' => 'CUIT', '86' => 'CUIL', '96' => 'DNI', '99' => 'Doc.',
            '0' => 'CI', '1' => 'CI', '2' => 'CI', '3' => 'Pasaporte', '4' => 'CI',
        ];
        $tipoDocLegible = $tiposDocumento[$comprobante->receptor_documento_tipo] ?? 'Doc.';
    @endphp
    <div class="cliente-section" style="font-weight: normal; font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '12px' }};">
        <div class="bold">CLIENTE:</div>
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

    <div class="divider-solid"></div>

    {{-- Items --}}
    @php
        $venta = $comprobante->ventas->first();
    @endphp

    @if($comprobante->es_total_venta && $venta)
        {{-- FACTURA POR EL TOTAL --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="cantidad">Un.</th>
                    <th class="descripcion">Descripcion</th>
                    @if($comprobante->letra === 'A')
                        <th class="iva">IVA</th>
                    @endif
                    <th class="precio">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                    @php
                        $cantFormateada = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
                        $ivaFormateado = $detalle->iva_porcentaje == intval($detalle->iva_porcentaje)
                            ? number_format($detalle->iva_porcentaje, 0)
                            : number_format($detalle->iva_porcentaje, 1);
                    @endphp
                    <tr>
                        <td class="cantidad">{{ $cantFormateada }}</td>
                        <td class="descripcion">
                            <strong>{{ $detalle->articulo->nombre }}</strong>
                            <br><span class="small">${{ number_format($detalle->precio_unitario, 2, ',', '.') }} c/u</span>
                            @if($detalle->tiene_promocion && $detalle->descuento_promocion > 0)
                                @php
                                    $nombrePromo = $detalle->promocionesAplicadas->first()?->descripcion_promocion ?? 'Promocion';
                                @endphp
                                <br><span class="item-promo">{{ $nombrePromo }}: -${{ number_format($detalle->descuento_promocion, 2, ',', '.') }}</span>
                            @endif
                            @if($detalle->descuento > 0 && !$detalle->tiene_promocion)
                                <br><span class="small">Desc: -${{ number_format($detalle->descuento, 2, ',', '.') }}</span>
                            @endif
                        </td>
                        @if($comprobante->letra === 'A')
                            <td class="iva">{{ $ivaFormateado }}%</td>
                        @endif
                        <td class="precio bold">${{ number_format($detalle->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Contador de items --}}
        <div class="small" style="text-align: right;">
            {{ $venta->detalles->count() }} {{ $venta->detalles->count() === 1 ? 'item' : 'items' }}
        </div>

        {{-- Promociones a nivel venta --}}
        @if($venta->promociones && $venta->promociones->count() > 0)
            <div style="padding: 3px; margin: 3px 0; border: 1px solid #000; font-weight: normal;">
                <div class="bold">PROMOCIONES:</div>
                @foreach($venta->promociones as $promo)
                    <div class="datos-row small">
                        <span>{{ $promo->descripcion_promocion }}</span>
                        <span class="bold">-${{ number_format($promo->descuento_aplicado, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- FACTURA PARCIAL (MIXTA) --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="cantidad">Un.</th>
                    <th class="descripcion">Descripcion</th>
                    @if($comprobante->letra === 'A')
                        <th class="iva">IVA</th>
                    @endif
                    <th class="precio">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comprobante->detallesIva as $alicuota)
                    @php
                        $subtotalConIva = $alicuota->base_imponible + $alicuota->importe;
                        $alicuotaFormateada = $alicuota->alicuota == intval($alicuota->alicuota)
                            ? number_format($alicuota->alicuota, 0)
                            : number_format($alicuota->alicuota, 1);
                    @endphp
                    <tr>
                        <td class="cantidad">1</td>
                        <td class="descripcion">Articulos (IVA {{ $alicuotaFormateada }}%)</td>
                        @if($comprobante->letra === 'A')
                            <td class="iva">{{ $alicuotaFormateada }}%</td>
                        @endif
                        <td class="precio bold">${{ number_format($subtotalConIva, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @if($comprobante->neto_no_gravado > 0)
                    <tr>
                        <td class="cantidad">1</td>
                        <td class="descripcion">Articulos (No gravado)</td>
                        @if($comprobante->letra === 'A')
                            <td class="iva">0%</td>
                        @endif
                        <td class="precio bold">${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</td>
                    </tr>
                @endif
                @if($comprobante->neto_exento > 0)
                    <tr>
                        <td class="cantidad">1</td>
                        <td class="descripcion">Articulos (Exento)</td>
                        @if($comprobante->letra === 'A')
                            <td class="iva">Ex.</td>
                        @endif
                        <td class="precio bold">${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <div class="divider-solid"></div>

    {{-- Totales --}}
    <div class="totales">
        @if($comprobante->letra === 'A')
            @if($comprobante->neto_gravado > 0)
                <div class="datos-row bold">
                    <span>Neto Gravado:</span>
                    <span>${{ number_format($comprobante->neto_gravado, 2, ',', '.') }}</span>
                </div>
            @endif
            @if($comprobante->neto_no_gravado > 0)
                <div class="datos-row bold">
                    <span>No Gravado:</span>
                    <span>${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</span>
                </div>
            @endif
            @if($comprobante->neto_exento > 0)
                <div class="datos-row bold">
                    <span>Exento:</span>
                    <span>${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</span>
                </div>
            @endif
            @foreach($comprobante->detallesIva ?? [] as $iva)
                @php
                    $ivaFormateado = $iva->alicuota == intval($iva->alicuota)
                        ? number_format($iva->alicuota, 0)
                        : number_format($iva->alicuota, 1);
                @endphp
                <div class="datos-row bold">
                    <span>IVA {{ $ivaFormateado }}%:</span>
                    <span>${{ number_format($iva->importe, 2, ',', '.') }}</span>
                </div>
            @endforeach
        @endif
        <div class="total-final">
            <div class="total-label">TOTAL</div>
            <div class="total-amount">${{ number_format($comprobante->total, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Formas de pago --}}
    @php
        if ($comprobante->es_total_venta && $venta) {
            $pagosAMostrar = $venta->pagos;
        } else {
            $pagosAMostrar = $comprobante->pagosFacturados ?? collect();
        }
    @endphp

    @if($pagosAMostrar->count() > 0)
        <div class="pagos-section">
            <div class="bold">FORMA DE PAGO:</div>
            @foreach($pagosAMostrar as $pago)
                <div class="datos-row">
                    <span class="bold">{{ $pago->formaPago?->nombre ?? 'Efectivo' }}</span>
                    <span class="bold">${{ number_format($pago->monto_facturado ?? $pago->monto_final, 2, ',', '.') }}</span>
                </div>
                @if($pago->cuotas && $pago->cuotas > 1)
                    <div class="small bold" style="padding-left: 5px;">
                        {{ $pago->cuotas }} cuotas de ${{ number_format($pago->monto_cuota, 2, ',', '.') }}
                        @if($pago->recargo_cuotas_porcentaje > 0)
                            (+{{ number_format($pago->recargo_cuotas_porcentaje, 0) }}%)
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- CAE --}}
    <div class="cae-section">
        <div class="bold">CAE: {{ $comprobante->cae }}</div>
        <div class="bold">Vto CAE: {{ $comprobante->cae_vencimiento->format('d/m/Y') }}</div>
    </div>

    {{-- Leyenda fiscal --}}
    @php
        $esMonotributista = $comprobante->cliente?->esMonotributista() ?? false;
    @endphp
    <div class="leyenda-fiscal">
        @if($comprobante->letra === 'A' && $esMonotributista)
            El credito fiscal discriminado en el presente comprobante, solo podra ser computado a efectos del Regimen de Sostenimiento e Inclusion Fiscal para Pequenos Contribuyentes de la Ley No 27.618
        @else
            Regimen de Transparencia Fiscal (Ley 27.743)<br>
            IVA contenido: ${{ number_format($comprobante->iva_total, 2, ',', '.') }}
        @endif
    </div>

    {{-- Texto legal adicional --}}
    @if($config?->texto_legal_factura)
        <div class="footer">
            {{ $config->texto_legal_factura }}
        </div>
    @endif

    {{-- Fecha impresion --}}
    <div class="footer">
        Impreso: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
