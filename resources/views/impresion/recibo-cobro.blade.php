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

        .recibo-titulo {
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
            margin-bottom: 4px;
        }

        .indent {
            padding-left: 8px;
        }
    </style>
</head>
<body>
    {{-- ========== ENCABEZADO EMPRESA ========== --}}
    <div class="center">
        <div class="empresa-nombre">{{ $cobro->sucursal->nombre_publico ?? $cobro->sucursal->nombre }}</div>
        @if($cobro->sucursal->direccion)
            <div class="small">{{ $cobro->sucursal->direccion }}</div>
        @endif
        @if($cobro->sucursal->telefono)
            <div class="small">Tel: {{ $cobro->sucursal->telefono }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== RECIBO ========== --}}
    <div class="center">
        <div class="recibo-titulo">RECIBO DE COBRO</div>
        <div class="bold">{{ $cobro->numero_recibo }}</div>
    </div>

    <div class="fila" style="margin-top: 4px;">
        <span class="bold">Fecha: {{ $cobro->fecha->format('d/m/Y') }}</span>
        <span>{{ $cobro->hora ? \Carbon\Carbon::parse($cobro->hora)->format('H:i') : '' }}</span>
    </div>
    @if($cobro->caja)
        <div>Caja: {{ $cobro->caja->nombre }}</div>
    @endif

    <div class="linea"></div>

    {{-- ========== CLIENTE ========== --}}
    <div class="bold">
        <div>CLIENTE:</div>
        <div>{{ $cobro->cliente->nombre }}</div>
        @if($cobro->cliente->cuit)
            <div>CUIT: {{ $cobro->cliente->cuit }}</div>
        @endif
        @if($cobro->cliente->direccion)
            <div class="small">{{ $cobro->cliente->direccion }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== DETALLE DE VENTAS SALDADAS ========== --}}
    <div class="center bold">DETALLE DE APLICACION</div>
    <div class="linea-punteada"></div>

    @foreach($cobro->cobroVentas as $cobroVenta)
        <div class="item-linea">
            <div class="fila">
                <span class="bold">Venta #{{ $cobroVenta->venta->numero }}</span>
                <span>{{ $cobroVenta->venta->fecha->format('d/m/Y') }}</span>
            </div>
            <div class="fila indent small">
                <span>Saldo anterior:</span>
                <span>${{ number_format($cobroVenta->saldo_anterior, 2, ',', '.') }}</span>
            </div>
            <div class="fila indent small">
                <span>Aplicado:</span>
                <span>-${{ number_format($cobroVenta->monto_aplicado, 2, ',', '.') }}</span>
            </div>
            @if($cobroVenta->interes_aplicado > 0)
                <div class="fila indent small">
                    <span>Interes mora:</span>
                    <span>${{ number_format($cobroVenta->interes_aplicado, 2, ',', '.') }}</span>
                </div>
            @endif
            <div class="fila indent">
                <span class="bold">Saldo posterior:</span>
                <span class="bold">${{ number_format($cobroVenta->saldo_posterior, 2, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <div class="linea-doble"></div>

    {{-- ========== RESUMEN ========== --}}
    <div class="fila">
        <span>Aplicado a deuda:</span>
        <span class="bold">${{ number_format($cobro->monto_aplicado_a_deuda, 2, ',', '.') }}</span>
    </div>

    @if($cobro->interes_aplicado > 0)
        <div class="fila">
            <span>Interes por mora:</span>
            <span>${{ number_format($cobro->interes_aplicado, 2, ',', '.') }}</span>
        </div>
    @endif

    @if($cobro->descuento_aplicado > 0)
        <div class="fila">
            <span>Descuento:</span>
            <span>-${{ number_format($cobro->descuento_aplicado, 2, ',', '.') }}</span>
        </div>
    @endif

    @if($cobro->monto_a_favor > 0)
        <div class="fila">
            <span>Saldo a favor:</span>
            <span class="bold">${{ number_format($cobro->monto_a_favor, 2, ',', '.') }}</span>
        </div>
    @endif

    <div class="linea"></div>

    {{-- ========== TOTAL ========== --}}
    <div class="center">
        <div class="total-final">TOTAL COBRADO</div>
        <div class="total-final">${{ number_format($cobro->monto_cobrado, 2, ',', '.') }}</div>
    </div>

    {{-- ========== FORMAS DE PAGO ========== --}}
    @if($cobro->pagos->count() > 0)
        <div class="linea-punteada"></div>
        <div class="bold">FORMA DE PAGO:</div>
        @foreach($cobro->pagos as $pago)
            <div class="fila indent">
                <span>{{ $pago->formaPago?->nombre ?? 'Efectivo' }}@if($pago->ajuste_porcentaje != 0) ({{ $pago->ajuste_porcentaje > 0 ? '+' : '' }}{{ number_format($pago->ajuste_porcentaje, 0) }}%)@endif</span>
                <span class="bold">${{ number_format($pago->monto_final, 2, ',', '.') }}</span>
            </div>
            @if($pago->cuotas && $pago->cuotas > 1)
                <div class="indent small">
                    {{ $pago->cuotas }} cuotas de ${{ number_format($pago->monto_cuota, 2, ',', '.') }}
                    @if($pago->recargo_cuotas_porcentaje > 0)
                        (+{{ number_format($pago->recargo_cuotas_porcentaje, 0) }}%)
                    @endif
                </div>
            @endif
            @if($pago->referencia)
                <div class="indent small">Ref: {{ $pago->referencia }}</div>
            @endif
        @endforeach

        {{-- Vuelto --}}
        @php
            $vueltoTotal = $cobro->pagos->sum('vuelto');
        @endphp
        @if($vueltoTotal > 0)
            <div class="linea-punteada"></div>
            <div class="fila bold">
                <span>Vuelto</span>
                <span>${{ number_format($vueltoTotal, 2, ',', '.') }}</span>
            </div>
        @endif
    @endif

    {{-- ========== SALDO CLIENTE ========== --}}
    <div class="linea"></div>
    <div class="center">
        <div class="bold">SALDO PENDIENTE DEL CLIENTE</div>
        <div class="large bold">${{ number_format($cobro->cliente->saldo_deudor_cache ?? 0, 2, ',', '.') }}</div>
    </div>

    <div class="linea"></div>

    {{-- ========== OBSERVACIONES ========== --}}
    @if($cobro->observaciones)
        <div class="small">
            <span class="bold">Obs:</span> {{ $cobro->observaciones }}
        </div>
        <div class="linea-punteada"></div>
    @endif

    {{-- ========== PIE ========== --}}
    <div class="center bold">
        {{ $config?->texto_pie_ticket ?? 'Gracias por su pago!' }}
    </div>

    {{-- Fecha impresion --}}
    <div class="center small" style="margin-top: 6px;">
        Impreso: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
