<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {{ $impresora->formato_papel === '58mm' ? '58mm' : '80mm' }} auto;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            font-size: 13px;
            width: {{ $impresora->formato_papel === '58mm' ? '165px' : '220px' }};
            max-width: {{ $impresora->formato_papel === '58mm' ? '165px' : '220px' }};
            margin: 0 auto;
            padding: 10px;
            line-height: 1.4;
        }

        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .empresa-nombre {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .empresa-datos {
            font-size: 11px;
            color: #444;
        }

        /* Numero de ticket destacado */
        .ticket-numero {
            text-align: center;
            margin: 10px 0;
            padding: 8px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        .ticket-numero-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .ticket-numero-valor {
            font-size: 24px;
            font-weight: bold;
            margin: 3px 0;
        }

        /* Datos generales */
        .datos-seccion {
            font-size: 12px;
            margin-bottom: 8px;
        }
        .datos-seccion p {
            margin: 2px 0;
        }
        .datos-label {
            color: #666;
        }

        /* Cliente */
        .cliente-box {
            background: #f5f5f5;
            padding: 8px;
            margin: 8px 0;
            border-radius: 3px;
        }
        .cliente-nombre {
            font-weight: bold;
            font-size: 13px;
        }
        .cliente-datos {
            font-size: 11px;
            color: #555;
        }

        /* Separadores */
        hr {
            border: none;
            border-top: 1px dashed #999;
            margin: 8px 0;
        }
        hr.doble {
            border-top: 1px solid #000;
            margin: 10px 0;
        }

        /* Items */
        .items-header {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            padding-bottom: 4px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 6px;
        }
        .item {
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dotted #ddd;
        }
        .item:last-child {
            border-bottom: none;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 13px;
        }
        .item-nombre {
            flex: 1;
        }
        .item-info {
            font-size: 12px;
            color: #555;
            margin: 2px 0;
        }
        .item-promo {
            font-size: 11px;
            color: #2e7d32;
            margin: 2px 0;
        }
        .item-descuento {
            font-size: 11px;
            color: #c62828;
            margin: 2px 0;
        }
        .item-total-linea {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 13px;
            margin-top: 3px;
        }

        /* Totales */
        .totales {
            margin-top: 10px;
        }
        .total-linea {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 12px;
        }
        .total-linea.descuento {
            color: #c62828;
        }
        .total-linea.recargo {
            color: #1565c0;
        }
        .promos-aplicadas {
            margin: 8px 0;
            padding: 8px;
            background: #e8f5e9;
            border-radius: 3px;
            font-size: 11px;
        }
        .promos-aplicadas-titulo {
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 4px;
            font-size: 12px;
        }
        .promo-linea {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            color: #2e7d32;
        }
        .promo-nombre {
            flex: 1;
        }
        .promo-monto {
            font-weight: bold;
            margin-left: 8px;
        }
        .total-final {
            margin-top: 8px;
            padding: 10px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            text-align: center;
        }
        .total-label {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .total-valor {
            font-size: 26px;
            font-weight: bold;
            margin-top: 3px;
        }

        /* Pagos */
        .pagos-seccion {
            margin-top: 10px;
        }
        .pagos-titulo {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }
        .pago-item {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 3px 0;
        }
        .pago-nombre {
            font-weight: bold;
        }
        .pago-detalle {
            font-size: 11px;
            color: #666;
            padding-left: 10px;
        }
        .pago-vuelto {
            background: #e3f2fd;
            padding: 6px 8px;
            margin-top: 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .pago-vuelto-label {
            color: #666;
        }
        .pago-vuelto-valor {
            font-weight: bold;
            float: right;
        }

        /* Pie */
        .pie {
            margin-top: 12px;
            text-align: center;
            font-size: 12px;
        }
        .pie-mensaje {
            font-style: italic;
            color: #555;
            margin-bottom: 5px;
        }
        .pie-observaciones {
            font-size: 11px;
            color: #777;
            text-align: left;
            margin-top: 8px;
            padding: 6px;
            background: #fff9c4;
            border-radius: 2px;
        }
        .pie-fecha {
            font-size: 10px;
            color: #999;
            margin-top: 8px;
        }

        /* Estado cuenta corriente */
        .cta-cte-box {
            background: #fff3e0;
            border: 1px solid #ffb74d;
            padding: 8px;
            margin: 8px 0;
            border-radius: 3px;
            text-align: center;
        }
        .cta-cte-label {
            font-size: 12px;
            color: #e65100;
            font-weight: bold;
        }
        .cta-cte-saldo {
            font-size: 14px;
            font-weight: bold;
            color: #bf360c;
        }
    </style>
</head>
<body>
    {{-- Encabezado de la empresa --}}
    <div class="header">
        <div class="empresa-nombre">{{ $venta->sucursal->nombre_publico ?? $venta->sucursal->nombre }}</div>
        @if($venta->sucursal->direccion)
            <div class="empresa-datos">{{ $venta->sucursal->direccion }}</div>
        @endif
        @if($venta->sucursal->telefono)
            <div class="empresa-datos">Tel: {{ $venta->sucursal->telefono }}</div>
        @endif
    </div>

    {{-- Numero de ticket destacado --}}
    <div class="ticket-numero">
        <div class="ticket-numero-label">Ticket</div>
        <div class="ticket-numero-valor">#{{ $venta->numero }}</div>
    </div>

    {{-- Datos de la venta --}}
    <div class="datos-seccion">
        <p><span class="datos-label">Fecha:</span> {{ $venta->fecha->format('d/m/Y') }}</p>
        <p><span class="datos-label">Hora:</span> {{ $venta->fecha->format('H:i') }}</p>
        @if($venta->caja)
            <p><span class="datos-label">Caja:</span> {{ $venta->caja->nombre }}</p>
        @endif
        <p><span class="datos-label">Atendido por:</span> {{ $venta->usuario?->name ?? '-' }}</p>
    </div>

    {{-- Cliente --}}
    @if($venta->cliente)
        <div class="cliente-box">
            <div class="cliente-nombre">{{ $venta->cliente->nombre }}</div>
            @if($venta->cliente->documento)
                <div class="cliente-datos">{{ $venta->cliente->tipo_documento ?? 'DNI' }}: {{ $venta->cliente->documento }}</div>
            @endif
            @if($venta->cliente->telefono)
                <div class="cliente-datos">Tel: {{ $venta->cliente->telefono }}</div>
            @endif
            @if($venta->cliente->direccion)
                <div class="cliente-datos">{{ $venta->cliente->direccion }}</div>
            @endif
        </div>
    @endif

    <hr>

    {{-- Detalle de items --}}
    <div class="items-header">Detalle de compra</div>

    @foreach($venta->detalles as $detalle)
        @php
            // Obtener nombre de promocion si existe
            $nombrePromo = null;
            if ($detalle->tiene_promocion && $detalle->descuento_promocion > 0) {
                $promoAplicada = $detalle->promocionesAplicadas->first();
                $nombrePromo = $promoAplicada?->descripcion_promocion ?? 'Promocion';
            }
            $cantFormateada = number_format($detalle->cantidad, $detalle->cantidad == intval($detalle->cantidad) ? 0 : 2, ',', '.');
        @endphp
        <div class="item">
            {{-- Linea 1: Nombre del articulo --}}
            <div class="item-header">
                <span class="item-nombre">{{ $detalle->articulo->nombre }}</span>
            </div>

            {{-- Linea 2: Cantidad x Precio unitario --}}
            <div class="item-info">
                {{ $cantFormateada }} x ${{ number_format($detalle->precio_unitario, 2, ',', '.') }}
            </div>

            {{-- Linea 3: Promocion aplicada (si existe) --}}
            @if($detalle->tiene_promocion && $detalle->descuento_promocion > 0)
                <div class="item-promo">
                    {{ $nombrePromo }}: -${{ number_format($detalle->descuento_promocion, 2, ',', '.') }}
                </div>
            @endif

            {{-- Descuento manual (si no es promocion) --}}
            @if($detalle->descuento > 0 && !$detalle->tiene_promocion)
                <div class="item-descuento">
                    Descuento: -${{ number_format($detalle->descuento, 2, ',', '.') }}
                </div>
            @endif

            {{-- Ajuste manual de precio --}}
            @if($detalle->ajuste_manual_tipo && $detalle->ajuste_manual_valor)
                <div class="item-info" style="color: #1565c0;">
                    @if($detalle->ajuste_manual_tipo === 'descuento')
                        Ajuste: -{{ $detalle->ajuste_manual_valor }}%
                    @else
                        Ajuste: +{{ $detalle->ajuste_manual_valor }}%
                    @endif
                </div>
            @endif

            {{-- Linea final: Total del item --}}
            <div class="item-total-linea">
                <span>Total:</span>
                <span>${{ number_format($detalle->total, 2, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <hr class="doble">

    {{-- Totales --}}
    <div class="totales">
        {{-- Subtotal (solo si hay descuentos o ajustes) --}}
        @php
            $mostrarSubtotal = $venta->descuento > 0 || $venta->ajuste_forma_pago != 0;
            $promocionesVenta = $venta->promociones ?? collect();
            $tienePromosEspeciales = $promocionesVenta->where('tipo_promocion', 'promocion_especial')->count() > 0;
            $tienePromosComunes = $promocionesVenta->where('tipo_promocion', 'promocion')->count() > 0;
        @endphp

        @if($mostrarSubtotal)
            <div class="total-linea">
                <span>Subtotal:</span>
                <span>${{ number_format($venta->subtotal, 2, ',', '.') }}</span>
            </div>
        @endif

        {{-- Detalle de promociones aplicadas --}}
        @if($tienePromosEspeciales || $tienePromosComunes)
            <div class="promos-aplicadas">
                <div class="promos-aplicadas-titulo">Promociones aplicadas:</div>
                @foreach($promocionesVenta as $promo)
                    <div class="promo-linea">
                        <span class="promo-nombre">{{ $promo->descripcion_promocion }}</span>
                        <span class="promo-monto">-${{ number_format($promo->descuento_aplicado, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Descuento general (solo si no hay detalle de promos) --}}
        @if($venta->descuento > 0 && !$tienePromosEspeciales && !$tienePromosComunes)
            <div class="total-linea descuento">
                <span>Descuento:</span>
                <span>-${{ number_format($venta->descuento, 2, ',', '.') }}</span>
            </div>
        @endif

        {{-- Ajuste por forma de pago --}}
        @if($venta->ajuste_forma_pago != 0)
            <div class="total-linea {{ $venta->ajuste_forma_pago > 0 ? 'recargo' : 'descuento' }}">
                <span>{{ $venta->ajuste_forma_pago > 0 ? 'Recargo:' : 'Descuento F.P.:' }}</span>
                <span>{{ $venta->ajuste_forma_pago > 0 ? '+' : '-' }}${{ number_format(abs($venta->ajuste_forma_pago), 2, ',', '.') }}</span>
            </div>
        @endif

        {{-- Total final destacado --}}
        <div class="total-final">
            <div class="total-label">T O T A L</div>
            <div class="total-valor">${{ number_format($venta->total_final, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Formas de pago --}}
    <div class="pagos-seccion">
        <div class="pagos-titulo">Forma de Pago</div>

        @foreach($venta->pagos as $pago)
            <div class="pago-item">
                <span class="pago-nombre">{{ $pago->formaPago?->nombre ?? 'Efectivo' }}</span>
                <span>${{ number_format($pago->monto_final, 2, ',', '.') }}</span>
            </div>

            {{-- Detalle de cuotas --}}
            @if($pago->cuotas && $pago->cuotas > 1)
                <div class="pago-detalle">
                    {{ $pago->cuotas }} cuotas de ${{ number_format($pago->monto_cuota, 2, ',', '.') }}
                    @if($pago->recargo_cuotas_porcentaje > 0)
                        (+{{ number_format($pago->recargo_cuotas_porcentaje, 0) }}%)
                    @endif
                </div>
            @endif

            {{-- Ajuste de forma de pago --}}
            @if($pago->monto_ajuste != 0)
                <div class="pago-detalle">
                    {{ $pago->ajuste_porcentaje > 0 ? 'Recargo' : 'Descuento' }}:
                    {{ $pago->ajuste_porcentaje > 0 ? '+' : '' }}{{ number_format($pago->ajuste_porcentaje, 1) }}%
                    ({{ $pago->monto_ajuste > 0 ? '+' : '' }}${{ number_format($pago->monto_ajuste, 2, ',', '.') }})
                </div>
            @endif

            {{-- Referencia (ej: numero de tarjeta, transferencia, etc) --}}
            @if($pago->referencia)
                <div class="pago-detalle">
                    Ref: {{ $pago->referencia }}
                </div>
            @endif
        @endforeach

        {{-- Vuelto --}}
        @php
            $vueltoTotal = $venta->pagos->sum('vuelto');
            $montoRecibido = $venta->pagos->sum('monto_recibido');
        @endphp

        @if($vueltoTotal > 0)
            <div class="pago-vuelto">
                <span class="pago-vuelto-label">Recibido: ${{ number_format($montoRecibido, 2, ',', '.') }}</span>
                <span class="pago-vuelto-valor">Vuelto: ${{ number_format($vueltoTotal, 2, ',', '.') }}</span>
            </div>
        @endif
    </div>

    {{-- Cuenta corriente --}}
    @if($venta->es_cuenta_corriente && $venta->saldo_pendiente_cache > 0)
        <div class="cta-cte-box">
            <div class="cta-cte-label">CUENTA CORRIENTE</div>
            <div class="cta-cte-saldo">Saldo pendiente: ${{ number_format($venta->saldo_pendiente_cache, 2, ',', '.') }}</div>
            @if($venta->fecha_vencimiento)
                <div style="font-size: 9px; color: #666;">
                    Vence: {{ $venta->fecha_vencimiento->format('d/m/Y') }}
                </div>
            @endif
        </div>
    @endif

    <hr>

    {{-- Pie del ticket --}}
    <div class="pie">
        <div class="pie-mensaje">{{ $config?->texto_pie_ticket ?? 'Gracias por su compra!' }}</div>

        @if($venta->observaciones)
            <div class="pie-observaciones">
                <strong>Obs:</strong> {{ $venta->observaciones }}
            </div>
        @endif

        <div class="pie-fecha">
            Impreso: {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
