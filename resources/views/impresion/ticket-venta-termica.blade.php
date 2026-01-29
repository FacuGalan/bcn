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

        .ticket-tipo {
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
        <div class="empresa-nombre">{{ $venta->sucursal->nombre_publico ?? $venta->sucursal->nombre }}</div>
        @if($venta->sucursal->direccion)
            <div class="small">{{ $venta->sucursal->direccion }}</div>
        @endif
        @if($venta->sucursal->telefono)
            <div class="small">Tel: {{ $venta->sucursal->telefono }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== TICKET ========== --}}
    <div class="center">
        <div class="ticket-tipo">TICKET DE VENTA</div>
        <div class="bold">#{{ $venta->numero }}</div>
    </div>

    <div class="fila" style="margin-top: 4px;">
        <span class="bold">Fecha: {{ $venta->fecha->format('d/m/Y') }}</span>
        <span>{{ $venta->fecha->format('H:i') }}</span>
    </div>
    @if($venta->caja)
        <div>Caja: {{ $venta->caja->nombre }}</div>
    @endif
    <div>Atendido por: {{ $venta->usuario?->name ?? '-' }}</div>

    <div class="linea"></div>

    {{-- ========== CLIENTE ========== --}}
    @if($venta->cliente)
        <div class="bold">
            <div>CLIENTE:</div>
            <div>{{ $venta->cliente->nombre }}</div>
            @if($venta->cliente->documento)
                <div>{{ $venta->cliente->tipo_documento ?? 'DNI' }}: {{ $venta->cliente->documento }}</div>
            @endif
            @if($venta->cliente->telefono)
                <div>Tel: {{ $venta->cliente->telefono }}</div>
            @endif
            @if($venta->cliente->direccion)
                <div>{{ $venta->cliente->direccion }}</div>
            @endif
        </div>

        <div class="linea"></div>
    @endif

    {{-- ========== DETALLE DE COMPRA ========== --}}
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
            $sumatoriaSubtotales += $detalle->subtotal;
        @endphp
        <div class="item-linea">
            <div class="bold">{{ $detalle->articulo->nombre }}</div>
            <div class="indent fila">
                <span>{{ $cantFormateada }} x ${{ number_format($detalle->precio_unitario, 2, ',', '.') }}</span>
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
                $sumatoriaSubtotales += $detalle->subtotal;
            @endphp
            <div class="item-linea">
                <div class="bold">{{ $detalle->articulo->nombre }}</div>
                <div class="indent fila">
                    <span>{{ $cantFormateada }} x ${{ number_format($detalle->precio_unitario, 2, ',', '.') }}</span>
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

    <div class="linea"></div>

    {{-- ========== TOTAL ========== --}}
    <div class="center">
        <div class="total-final">TOTAL</div>
        <div class="total-final">${{ number_format($venta->total_final, 2, ',', '.') }}</div>
    </div>

    {{-- ========== FORMAS DE PAGO ========== --}}
    @if($venta->pagos->count() > 0)
        <div class="linea-punteada"></div>
        <div class="bold">FORMA DE PAGO:</div>
        @foreach($venta->pagos as $pago)
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
        @endforeach

        {{-- Vuelto --}}
        @php
            $vueltoTotal = $venta->pagos->sum('vuelto');
            $montoRecibido = $venta->pagos->sum('monto_recibido');
        @endphp
        @if($vueltoTotal > 0)
            <div class="linea-punteada"></div>
            <div class="fila bold">
                <span>Recibido</span>
                <span>${{ number_format($montoRecibido, 2, ',', '.') }}</span>
            </div>
            <div class="fila bold">
                <span>Vuelto</span>
                <span>${{ number_format($vueltoTotal, 2, ',', '.') }}</span>
            </div>
        @endif
    @endif

    {{-- ========== CUENTA CORRIENTE ========== --}}
    @if($venta->es_cuenta_corriente && $venta->saldo_pendiente_cache > 0)
        <div class="linea"></div>
        <div class="center bold">
            <div>CUENTA CORRIENTE</div>
            <div>Saldo pendiente: ${{ number_format($venta->saldo_pendiente_cache, 2, ',', '.') }}</div>
            @if($venta->fecha_vencimiento)
                <div class="small">Vence: {{ $venta->fecha_vencimiento->format('d/m/Y') }}</div>
            @endif
        </div>
    @endif

    <div class="linea"></div>

    {{-- ========== PIE ========== --}}
    <div class="center bold">
        {{ $config?->texto_pie_ticket ?? 'Gracias por su compra!' }}
    </div>

    @if($venta->observaciones)
        <div class="linea-punteada"></div>
        <div class="small">
            <span class="bold">Obs:</span> {{ $venta->observaciones }}
        </div>
    @endif

    {{-- Fecha impresion --}}
    <div class="center small" style="margin-top: 6px;">
        Impreso: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
