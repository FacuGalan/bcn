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
        }
        .totales td:last-child {
            text-align: right;
        }
        .total-final {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #000;
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
            <div>CUIT: {{ $comprobante->cuit->numero_formateado }}</div>
            <div>Condicion IVA: {{ $comprobante->cuit->condicionIva?->nombre ?? 'Responsable Inscripto' }}</div>
            @if($comprobante->cuit->numero_iibb)
                <div>IIBB: {{ $comprobante->cuit->numero_iibb }}</div>
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
            <div>Punto de Venta: {{ str_pad($comprobante->puntoVenta->numero ?? $comprobante->punto_venta_numero, 4, '0', STR_PAD_LEFT) }}</div>
        </div>
    </div>

    {{-- Datos del cliente --}}
    <div class="datos-section">
        <strong>DATOS DEL CLIENTE:</strong>
        <div>{{ $comprobante->receptor_nombre }}</div>
        <div>{{ $comprobante->receptor_documento_tipo }}: {{ $comprobante->receptor_documento_numero }}</div>
        @if($comprobante->receptor_domicilio)
            <div>Domicilio: {{ $comprobante->receptor_domicilio }}</div>
        @endif
        @if($comprobante->cliente?->condicionIva)
            <div>Condicion IVA: {{ $comprobante->cliente->condicionIva->nombre }}</div>
        @endif
    </div>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 60px;">Cantidad</th>
                <th>Descripcion</th>
                <th style="width: 100px;">Precio Unit.</th>
                @if($comprobante->letra === 'A')
                    <th style="width: 60px;">IVA %</th>
                @endif
                <th style="width: 100px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comprobante->items as $item)
                <tr>
                    <td class="number">{{ number_format($item->cantidad, 2) }}</td>
                    <td>{{ $item->descripcion }}</td>
                    <td class="number">${{ number_format($item->precio_unitario, 2) }}</td>
                    @if($comprobante->letra === 'A')
                        <td class="number">{{ number_format($item->alicuota_iva ?? 21, 0) }}%</td>
                    @endif
                    <td class="number">${{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totales --}}
    <table class="totales">
        @if($comprobante->letra === 'A')
            @if($comprobante->neto_gravado > 0)
                <tr>
                    <td>Neto Gravado:</td>
                    <td>${{ number_format($comprobante->neto_gravado, 2) }}</td>
                </tr>
            @endif
            @if($comprobante->neto_no_gravado > 0)
                <tr>
                    <td>No Gravado:</td>
                    <td>${{ number_format($comprobante->neto_no_gravado, 2) }}</td>
                </tr>
            @endif
            @if($comprobante->neto_exento > 0)
                <tr>
                    <td>Exento:</td>
                    <td>${{ number_format($comprobante->neto_exento, 2) }}</td>
                </tr>
            @endif
            @foreach($comprobante->detallesIva ?? [] as $iva)
                <tr>
                    <td>IVA {{ number_format($iva->porcentaje, 0) }}%:</td>
                    <td>${{ number_format($iva->importe, 2) }}</td>
                </tr>
            @endforeach
            @if($comprobante->tributos > 0)
                <tr>
                    <td>Otros Tributos:</td>
                    <td>${{ number_format($comprobante->tributos, 2) }}</td>
                </tr>
            @endif
        @endif
        <tr class="total-final">
            <td>TOTAL:</td>
            <td>${{ number_format($comprobante->total, 2) }}</td>
        </tr>
    </table>

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

    {{-- Pie --}}
    @if($config?->texto_legal_factura)
        <div class="footer">
            {{ $config->texto_legal_factura }}
        </div>
    @endif
</body>
</html>
