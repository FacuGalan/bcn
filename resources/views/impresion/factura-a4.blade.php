<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            font-weight: 500;
        }
        .header {
            display: flex;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-left {
            width: 40%;
        }
        .header-center {
            width: 20%;
            text-align: center;
        }
        .header-right {
            width: 40%;
            text-align: right;
        }
        .letra-box {
            display: inline-block;
            border: 2px solid #000;
            padding: 10px 20px;
            font-size: 28px;
            font-weight: bold;
        }
        .codigo-box {
            font-size: 10px;
            margin-top: 5px;
        }
        h1 {
            font-size: 16px;
            margin: 0 0 5px 0;
        }
        h2 {
            font-size: 14px;
            margin: 5px 0;
        }
        .datos-section {
            margin: 15px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .datos-section strong {
            display: block;
            margin-bottom: 5px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table.items th,
        table.items td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.items th {
            background: #222036;
            color: white;
        }
        table.items td.number {
            text-align: right;
        }
        .totales {
            width: 300px;
            margin-left: auto;
        }
        .totales td {
            padding: 5px 10px;
            font-weight: 600;
        }
        .totales td:last-child {
            text-align: right;
        }
        .total-final {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        .datos-section {
            font-weight: 600;
        }
        .cae-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cae-data {
            flex: 1;
        }
        .qr-placeholder {
            width: 100px;
            height: 100px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <div class="header">
        <div class="header-left">
            <h1>{{ $comprobante->cuit->razon_social }}</h1>
            @if($comprobante->cuit->nombre_fantasia)
                <div>{{ $comprobante->cuit->nombre_fantasia }}</div>
            @endif
            <div>{{ $comprobante->sucursal->direccion ?? $comprobante->cuit->direccion }}</div>
            <div>{{ $comprobante->cuit->numero_cuit }}</div>
            <div>Condicion IVA: {{ $comprobante->cuit->condicionIva?->nombre ?? 'Responsable Inscripto' }}</div>
            @if($comprobante->cuit->numero_iibb)
                <div>IIBB: {{ $comprobante->cuit->numero_iibb }}</div>
            @endif
            @if($comprobante->cuit->inicio_actividades)
                <div>Inicio Act.: {{ $comprobante->cuit->inicio_actividades->format('d/m/Y') }}</div>
            @endif
        </div>
        <div class="header-center">
            <div class="letra-box">{{ $comprobante->letra }}</div>
            <div class="codigo-box">Cod: {{ $comprobante->tipo_afip ?? '001' }}</div>
        </div>
        <div class="header-right">
            <h1>{{ $comprobante->tipo_legible }}</h1>
            <h2>Nro: {{ $comprobante->numero_formateado }}</h2>
            <div>Fecha: {{ $comprobante->fecha_emision->format('d/m/Y') }}</div>
            <div>Hora: {{ $comprobante->created_at->format('H:i') }}</div>
            <div>Punto de Venta: {{ str_pad($comprobante->puntoVenta->numero ?? $comprobante->punto_venta_numero, 4, '0', STR_PAD_LEFT) }}</div>
        </div>
    </div>

    {{-- Datos del cliente --}}
    @php
        // Convertir código de documento AFIP a nombre legible
        $tiposDocumento = [
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
        $tipoDocLegible = $tiposDocumento[$comprobante->receptor_documento_tipo] ?? 'Doc.';
    @endphp
    <div class="datos-section">
        <strong>DATOS DEL CLIENTE:</strong>
        <div>{{ $comprobante->receptor_nombre }}</div>
        @if($comprobante->receptor_documento_numero && $comprobante->receptor_documento_numero != '0')
            <div>{{ $tipoDocLegible }}: {{ $comprobante->receptor_documento_numero }}</div>
        @endif
        @if($comprobante->receptor_domicilio)
            <div>Domicilio: {{ $comprobante->receptor_domicilio }}</div>
        @endif
        @if($comprobante->cliente?->condicionIva)
            <div>Condicion IVA: {{ $comprobante->cliente->condicionIva->nombre }}</div>
        @endif
    </div>

    {{-- Items --}}
    @php
        $venta = $comprobante->ventas->first();
    @endphp

    @if($comprobante->es_total_venta && $venta)
        {{-- FACTURA POR EL TOTAL: Mostrar exactamente igual que el ticket --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 60px;">Cant.</th>
                    <th>Descripcion</th>
                    <th style="width: 80px;">P. Unit.</th>
                    @if($comprobante->letra === 'A')
                        <th style="width: 50px;">IVA</th>
                    @endif
                    <th style="width: 90px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                    @php
                        $cantFormateada = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
                        $nombrePromo = null;
                        if ($detalle->tiene_promocion && $detalle->descuento_promocion > 0) {
                            $promoAplicada = $detalle->promocionesAplicadas->first();
                            $nombrePromo = $promoAplicada?->descripcion_promocion ?? 'Promocion';
                        }
                    @endphp
                    <tr>
                        <td class="number">{{ $cantFormateada }}</td>
                        <td>
                            {{ $detalle->articulo->nombre }}
                            @if($detalle->tiene_promocion && $detalle->descuento_promocion > 0)
                                <br><small style="color: #2e7d32;">{{ $nombrePromo }}: -${{ number_format($detalle->descuento_promocion, 2, ',', '.') }}</small>
                            @endif
                            @if($detalle->descuento > 0 && !$detalle->tiene_promocion)
                                <br><small style="color: #c62828;">Descuento: -${{ number_format($detalle->descuento, 2, ',', '.') }}</small>
                            @endif
                            @if($detalle->ajuste_manual_tipo && $detalle->ajuste_manual_valor)
                                <br><small style="color: #1565c0;">
                                    Ajuste: {{ $detalle->ajuste_manual_tipo === 'descuento' ? '-' : '+' }}{{ $detalle->ajuste_manual_valor }}%
                                </small>
                            @endif
                        </td>
                        <td class="number">${{ number_format($detalle->precio_unitario, 2, ',', '.') }}</td>
                        @if($comprobante->letra === 'A')
                            <td class="number">{{ number_format($detalle->iva_porcentaje, $detalle->iva_porcentaje == intval($detalle->iva_porcentaje) ? 0 : 1) }}%</td>
                        @endif
                        <td class="number">${{ number_format($detalle->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Promociones aplicadas a la venta --}}
        @php
            $promocionesVenta = $venta->promociones ?? collect();
        @endphp
        @if($promocionesVenta->count() > 0)
            <div style="background: #e8f5e9; padding: 8px; margin: 10px 0; border-radius: 4px;">
                <strong style="color: #2e7d32;">Promociones aplicadas:</strong>
                @foreach($promocionesVenta as $promo)
                    <div style="display: flex; justify-content: space-between; color: #2e7d32; font-size: 10px;">
                        <span>{{ $promo->descripcion_promocion }}</span>
                        <span>-${{ number_format($promo->descuento_aplicado, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

    @else
        {{-- FACTURA PARCIAL (MIXTA): Mostrar articulos agrupados por alicuota --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 60px;">Cant.</th>
                    <th>Descripcion</th>
                    @if($comprobante->letra === 'A')
                        <th style="width: 50px;">IVA</th>
                    @endif
                    <th style="width: 100px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comprobante->detallesIva as $alicuota)
                    @php
                        $subtotalConIva = $alicuota->base_imponible + $alicuota->importe;
                        // Formatear alicuota sin redondear (10.5 no debe ser 11)
                        $alicuotaFormateada = $alicuota->alicuota == intval($alicuota->alicuota)
                            ? number_format($alicuota->alicuota, 0)
                            : number_format($alicuota->alicuota, 1);
                    @endphp
                    <tr>
                        <td class="number">1</td>
                        <td>Articulos varios (IVA {{ $alicuotaFormateada }}%)</td>
                        @if($comprobante->letra === 'A')
                            <td class="number">{{ $alicuotaFormateada }}%</td>
                        @endif
                        <td class="number">${{ number_format($subtotalConIva, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                {{-- Si hay neto no gravado --}}
                @if($comprobante->neto_no_gravado > 0)
                    <tr>
                        <td class="number">1</td>
                        <td>Articulos varios (No gravado)</td>
                        @if($comprobante->letra === 'A')
                            <td class="number">0%</td>
                        @endif
                        <td class="number">${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</td>
                    </tr>
                @endif
                {{-- Si hay neto exento --}}
                @if($comprobante->neto_exento > 0)
                    <tr>
                        <td class="number">1</td>
                        <td>Articulos varios (Exento)</td>
                        @if($comprobante->letra === 'A')
                            <td class="number">Ex.</td>
                        @endif
                        <td class="number">${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    {{-- Totales --}}
    <table class="totales">
        @if($comprobante->letra === 'A')
            @if($comprobante->neto_gravado > 0)
                <tr>
                    <td>Neto Gravado:</td>
                    <td>${{ number_format($comprobante->neto_gravado, 2, ',', '.') }}</td>
                </tr>
            @endif
            @if($comprobante->neto_no_gravado > 0)
                <tr>
                    <td>No Gravado:</td>
                    <td>${{ number_format($comprobante->neto_no_gravado, 2, ',', '.') }}</td>
                </tr>
            @endif
            @if($comprobante->neto_exento > 0)
                <tr>
                    <td>Exento:</td>
                    <td>${{ number_format($comprobante->neto_exento, 2, ',', '.') }}</td>
                </tr>
            @endif
            @foreach($comprobante->detallesIva ?? [] as $iva)
                @php
                    // Formatear alicuota sin redondear (10.5 no debe ser 11)
                    $ivaAlicuota = $iva->alicuota ?? $iva->porcentaje ?? 21;
                    $ivaFormateado = $ivaAlicuota == intval($ivaAlicuota)
                        ? number_format($ivaAlicuota, 0)
                        : number_format($ivaAlicuota, 1);
                @endphp
                <tr>
                    <td>IVA {{ $ivaFormateado }}%:</td>
                    <td>${{ number_format($iva->importe, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            @if($comprobante->tributos > 0)
                <tr>
                    <td>Otros Tributos:</td>
                    <td>${{ number_format($comprobante->tributos, 2, ',', '.') }}</td>
                </tr>
            @endif
        @endif
        <tr class="total-final">
            <td>TOTAL:</td>
            <td>${{ number_format($comprobante->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Formas de Pago --}}
    @php
        // Determinar qué pagos mostrar
        if ($comprobante->es_total_venta && $venta) {
            $pagosAMostrar = $venta->pagos;
        } else {
            // Factura parcial: mostrar solo los pagos facturados con este comprobante
            $pagosAMostrar = $comprobante->pagosFacturados ?? collect();
        }
    @endphp

    @if($pagosAMostrar->count() > 0)
        <div style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-radius: 4px;">
            <strong style="font-size: 11px; color: #666; text-transform: uppercase;">Forma de Pago</strong>
            @foreach($pagosAMostrar as $pago)
                <div style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 11px;">
                    <span>{{ $pago->formaPago?->nombre ?? 'Efectivo' }}</span>
                    <span>${{ number_format($pago->monto_facturado ?? $pago->monto_final, 2, ',', '.') }}</span>
                </div>
                @if($pago->cuotas && $pago->cuotas > 1)
                    <div style="font-size: 10px; color: #666; padding-left: 10px;">
                        {{ $pago->cuotas }} cuotas de ${{ number_format($pago->monto_cuota, 2, ',', '.') }}
                        @if($pago->recargo_cuotas_porcentaje > 0)
                            (+{{ number_format($pago->recargo_cuotas_porcentaje, 0) }}%)
                        @endif
                    </div>
                @endif
                @if($pago->monto_ajuste != 0)
                    <div style="font-size: 10px; color: #666; padding-left: 10px;">
                        {{ $pago->ajuste_porcentaje > 0 ? 'Recargo' : 'Descuento' }}:
                        {{ $pago->ajuste_porcentaje > 0 ? '+' : '' }}{{ number_format($pago->ajuste_porcentaje, 1) }}%
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- CAE --}}
    <div class="cae-section">
        <div class="cae-data">
            <strong>CAE:</strong> {{ $comprobante->cae }}<br>
            <strong>Vencimiento CAE:</strong> {{ $comprobante->cae_vencimiento->format('d/m/Y') }}
        </div>
        <div class="qr-placeholder">
            [QR AFIP]
        </div>
    </div>

    {{-- Leyenda fiscal obligatoria --}}
    @php
        $esMonotributista = $comprobante->cliente?->esMonotributista() ?? false;
    @endphp
    <div style="margin-top: 15px; padding: 10px; border: 1px solid #ddd; font-size: 9px; text-align: justify; line-height: 1.3; font-weight: bold;">
        @if($comprobante->letra === 'A' && $esMonotributista)
            El crédito fiscal discriminado en el presente comprobante, sólo podrá ser computado a efectos del Régimen de Sostenimiento e Inclusión Fiscal para Pequeños Contribuyentes de la Ley Nº 27.618
        @else
            Régimen de Transparencia Fiscal (Ley 27.743)<br>
            IVA contenido: ${{ number_format($comprobante->iva_total, 2, ',', '.') }}
        @endif
    </div>

    {{-- Pie --}}
    @if($config?->texto_legal_factura)
        <div class="footer">
            {{ $config->texto_legal_factura }}
        </div>
    @endif
</body>
</html>
