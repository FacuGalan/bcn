<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {{ $impresora->formato_papel === '58mm' ? '58mm' : '80mm' }} auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: {{ $impresora->formato_papel === '58mm' ? '58mm' : '80mm' }};
            margin: 0;
            padding: 5mm;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .big { font-size: 16px; }
        .small { font-size: 10px; }
        .line { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .precio { text-align: right; white-space: nowrap; }
        .cantidad { width: 25px; }
        .descripcion { word-break: break-word; }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <div class="center bold big">{{ $venta->sucursal->nombre_publico ?? $venta->sucursal->nombre }}</div>

    @if($venta->sucursal->direccion)
        <div class="center small">{{ $venta->sucursal->direccion }}</div>
    @endif

    @if($venta->sucursal->telefono)
        <div class="center small">Tel: {{ $venta->sucursal->telefono }}</div>
    @endif

    <div class="line"></div>

    {{-- Datos del ticket --}}
    <div>
        <strong>Ticket:</strong> {{ $venta->numero }}<br>
        <strong>Fecha:</strong> {{ $venta->fecha->format('d/m/Y H:i') }}<br>
        <strong>Caja:</strong> {{ $venta->caja?->nombre ?? '-' }}<br>
        <strong>Vendedor:</strong> {{ $venta->usuario?->name ?? '-' }}
    </div>

    @if($venta->cliente)
        <div>
            <strong>Cliente:</strong> {{ $venta->cliente->nombre }}
            @if($venta->cliente->documento)
                <br><small>{{ $venta->cliente->tipo_documento ?? 'DNI' }}: {{ $venta->cliente->documento }}</small>
            @endif
        </div>
    @endif

    <div class="line"></div>

    {{-- Detalle de items --}}
    <table>
        <thead>
            <tr class="bold">
                <td class="cantidad">Cant</td>
                <td class="descripcion">Descripcion</td>
                <td class="precio">Precio</td>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="cantidad">{{ number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2) }}</td>
                    <td class="descripcion">{{ Str::limit($detalle->articulo->nombre, 25) }}</td>
                    <td class="precio">${{ number_format($detalle->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    {{-- Totales --}}
    <div class="right">
        @if($venta->descuento > 0)
            <div>Subtotal: ${{ number_format($venta->subtotal + $venta->descuento, 2) }}</div>
            <div>Descuento: -${{ number_format($venta->descuento, 2) }}</div>
        @endif
        <div class="bold big">TOTAL: ${{ number_format($venta->total_final, 2) }}</div>
    </div>

    {{-- Formas de pago --}}
    <div class="line"></div>
    <div>
        <strong>FORMAS DE PAGO:</strong>
        @foreach($venta->pagos as $pago)
            <div style="padding-left: 10px;">
                {{ $pago->formaPago?->nombre ?? 'Efectivo' }}: ${{ number_format($pago->monto_final, 2) }}
                @if($pago->vuelto > 0)
                    <br><small style="padding-left: 10px;">Vuelto: ${{ number_format($pago->vuelto, 2) }}</small>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Pie --}}
    <div class="line"></div>
    <div class="center">
        {{ $config?->texto_pie_ticket ?? 'Gracias por su compra!' }}
    </div>

    @if($venta->observaciones)
        <div class="small" style="margin-top: 5px;">
            <strong>Obs:</strong> {{ $venta->observaciones }}
        </div>
    @endif
</body>
</html>
