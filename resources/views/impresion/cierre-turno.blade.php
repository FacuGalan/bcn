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

        .titulo {
            font-size: {{ $impresora->formato_papel === '58mm' ? '14px' : '18px' }};
            font-weight: bold;
        }

        .seccion-titulo {
            font-size: {{ $impresora->formato_papel === '58mm' ? '10px' : '12px' }};
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 6px;
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

        .positivo {
            font-weight: bold;
        }

        .negativo {
            font-weight: bold;
        }

        .resaltado {
            padding: 4px;
            border: 1px solid #000;
            margin: 4px 0;
        }
    </style>
</head>
<body>
    {{-- ========== ENCABEZADO EMPRESA ========== --}}
    <div class="center">
        <div class="empresa-nombre">{{ $datos['sucursal']['nombre_publico'] ?? $datos['sucursal']['nombre'] }}</div>
        @if(!empty($datos['sucursal']['direccion']))
            <div class="small">{{ $datos['sucursal']['direccion'] }}</div>
        @endif
        @if(!empty($datos['sucursal']['telefono']))
            <div class="small">Tel: {{ $datos['sucursal']['telefono'] }}</div>
        @endif
    </div>

    <div class="linea"></div>

    {{-- ========== TITULO CIERRE ========== --}}
    <div class="center">
        <div class="titulo">CIERRE DE TURNO</div>
        <div class="bold">#{{ $datos['cierre']['id'] }}</div>
    </div>

    <div class="fila" style="margin-top: 4px;">
        <span class="bold">Fecha: {{ $datos['cierre']['fecha_cierre'] }}</span>
    </div>
    @if(!empty($datos['cierre']['fecha_apertura']))
    <div class="fila">
        <span>Apertura: {{ $datos['cierre']['fecha_apertura'] }}</span>
    </div>
    @endif
    <div>Usuario: {{ $datos['cierre']['usuario'] }}</div>
    @if($datos['cierre']['es_grupal'])
        <div class="bold">Grupo: {{ $datos['cierre']['grupo_nombre'] ?? 'Grupal' }}</div>
    @else
        <div>Caja: {{ $datos['cajas'][0]['nombre'] ?? '-' }}</div>
    @endif

    <div class="linea"></div>

    {{-- ========== RESUMEN PRINCIPAL ========== --}}
    <div class="seccion-titulo center">RESUMEN DEL TURNO</div>
    <div class="linea-punteada"></div>

    <div class="fila bold">
        <span>Fondo Inicial:</span>
        <span>${{ number_format($datos['cierre']['saldo_inicial'], 2, ',', '.') }}</span>
    </div>

    <div class="linea-punteada"></div>

    <div class="fila">
        <span>Total Ingresos:</span>
        <span class="positivo">+${{ number_format($datos['cierre']['total_ingresos'], 2, ',', '.') }}</span>
    </div>
    <div class="fila">
        <span>Total Egresos:</span>
        <span class="negativo">-${{ number_format($datos['cierre']['total_egresos'], 2, ',', '.') }}</span>
    </div>

    <div class="linea-doble"></div>

    <div class="fila bold large">
        <span>SALDO SISTEMA:</span>
        <span>${{ number_format($datos['cierre']['saldo_sistema'], 2, ',', '.') }}</span>
    </div>
    <div class="fila bold large">
        <span>SALDO DECLARADO:</span>
        <span>${{ number_format($datos['cierre']['saldo_declarado'], 2, ',', '.') }}</span>
    </div>

    <div class="linea-doble"></div>

    {{-- ========== DIFERENCIA ========== --}}
    @php
        $diferencia = $datos['cierre']['diferencia'];
        $tieneDiferencia = $diferencia != 0;
    @endphp
    <div class="resaltado center">
        @if($tieneDiferencia)
            @if($diferencia > 0)
                <div class="bold">SOBRANTE</div>
                <div class="total-final">+${{ number_format($diferencia, 2, ',', '.') }}</div>
            @else
                <div class="bold">FALTANTE</div>
                <div class="total-final">-${{ number_format(abs($diferencia), 2, ',', '.') }}</div>
            @endif
        @else
            <div class="bold">CUADRADO</div>
            <div class="large">OK</div>
        @endif
    </div>

    {{-- ========== DETALLE POR CAJA (si es grupal) ========== --}}
    @if($datos['cierre']['es_grupal'] && count($datos['cajas']) > 1)
    <div class="linea"></div>
    <div class="seccion-titulo center">DETALLE POR CAJA</div>
    <div class="linea-punteada"></div>

    @foreach($datos['cajas'] as $caja)
    @php
        // En fondo común, saldo_sistema puede ser 0, calcular el real
        $saldoFinalCaja = $caja['saldo_sistema'];
        if ($saldoFinalCaja == 0 && ($caja['ingresos'] > 0 || $caja['egresos'] > 0)) {
            $saldoFinalCaja = $caja['saldo_inicial'] + $caja['ingresos'] - $caja['egresos'];
        }
    @endphp
    <div class="item-linea">
        <div class="bold">{{ $caja['nombre'] }}</div>
        <div class="indent fila">
            <span>Inicial:</span>
            <span>${{ number_format($caja['saldo_inicial'], 2, ',', '.') }}</span>
        </div>
        <div class="indent fila">
            <span>Ingresos:</span>
            <span>+${{ number_format($caja['ingresos'], 2, ',', '.') }}</span>
        </div>
        <div class="indent fila">
            <span>Egresos:</span>
            <span>-${{ number_format($caja['egresos'], 2, ',', '.') }}</span>
        </div>
        <div class="indent fila bold">
            <span>Final:</span>
            <span>${{ number_format($saldoFinalCaja, 2, ',', '.') }}</span>
        </div>
        @if($caja['diferencia'] != 0)
        <div class="indent fila bold">
            <span>{{ $caja['diferencia'] > 0 ? 'Sobrante:' : 'Faltante:' }}</span>
            <span>{{ $caja['diferencia'] > 0 ? '+' : '' }}${{ number_format($caja['diferencia'], 2, ',', '.') }}</span>
        </div>
        @endif
    </div>
    @endforeach
    @endif

    {{-- ========== MOVIMIENTOS DE CAJA (MANUALES) ========== --}}
    @if(!empty($datos['movimientos']) && count($datos['movimientos']) > 0)
    <div class="linea"></div>
    <div class="seccion-titulo center">MOVIMIENTOS MANUALES</div>
    <div class="linea-punteada"></div>

    @foreach($datos['movimientos'] as $mov)
    <div class="item-linea">
        <div style="word-wrap: break-word;">
            <span class="small">{{ $mov['hora'] }} {{ $mov['concepto'] }}</span>
        </div>
        <div class="right {{ $mov['tipo'] === 'ingreso' ? 'positivo' : 'negativo' }}">
            {{ $mov['tipo'] === 'ingreso' ? '+' : '-' }}${{ number_format($mov['monto'], 2, ',', '.') }}
        </div>
    </div>
    @endforeach

    <div class="linea-punteada"></div>
    @php
        $totalIngMov = collect($datos['movimientos'])->where('tipo', 'ingreso')->sum('monto');
        $totalEgrMov = collect($datos['movimientos'])->where('tipo', 'egreso')->sum('monto');
    @endphp
    <div class="fila bold">
        <span>Total Movimientos:</span>
        <span>{{ count($datos['movimientos']) }}</span>
    </div>
    <div class="fila">
        <span>Ingresos manuales:</span>
        <span class="positivo">+${{ number_format($totalIngMov, 2, ',', '.') }}</span>
    </div>
    <div class="fila">
        <span>Egresos manuales:</span>
        <span class="negativo">-${{ number_format($totalEgrMov, 2, ',', '.') }}</span>
    </div>
    @endif

    {{-- ========== COMPROBANTES EMITIDOS ========== --}}
    @if(!empty($datos['comprobantes']) && count($datos['comprobantes']) > 0)
    <div class="linea"></div>
    <div class="seccion-titulo center">COMPROBANTES EMITIDOS</div>
    <div class="linea-punteada"></div>

    @foreach($datos['comprobantes'] as $tipo => $comp)
    <div class="fila">
        <span>{{ $tipo }}:</span>
        <span>{{ $comp['cantidad'] }} - ${{ number_format($comp['total'], 2, ',', '.') }}</span>
    </div>
    @endforeach

    <div class="linea-punteada"></div>
    <div class="fila bold">
        <span>Total Comprobantes:</span>
        <span>{{ array_sum(array_column($datos['comprobantes'], 'cantidad')) }}</span>
    </div>
    @endif

    {{-- ========== TOTALES POR FORMA DE PAGO ========== --}}
    @if(!empty($datos['formas_pago']) && count($datos['formas_pago']) > 0)
    <div class="linea"></div>
    <div class="seccion-titulo center">TOTALES POR FORMA DE PAGO</div>
    <div class="linea-punteada"></div>

    @foreach($datos['formas_pago'] as $forma => $info)
    <div class="fila">
        <span>{{ $forma }}:</span>
        <span>{{ $info['cantidad'] }} op. - ${{ number_format($info['total'], 2, ',', '.') }}</span>
    </div>
    @endforeach
    @endif

    {{-- ========== TOTALES POR CONCEPTO ========== --}}
    @if(!empty($datos['conceptos']) && count($datos['conceptos']) > 0)
    <div class="linea"></div>
    <div class="seccion-titulo center">TOTALES POR CONCEPTO</div>
    <div class="linea-punteada"></div>

    @foreach($datos['conceptos'] as $concepto => $info)
    <div class="fila">
        <span>{{ $concepto }}:</span>
        <span>{{ $info['cantidad'] }} op. - ${{ number_format($info['total'], 2, ',', '.') }}</span>
    </div>
    @endforeach
    @endif

    {{-- ========== OPERACIONES DEL TURNO ========== --}}
    @if(!empty($datos['operaciones']))
    <div class="linea"></div>
    <div class="seccion-titulo center">OPERACIONES DEL TURNO</div>
    <div class="linea-punteada"></div>

    @if(!empty($datos['operaciones']['ventas']))
    <div class="fila">
        <span>Ventas:</span>
        <span>{{ $datos['operaciones']['ventas']['cantidad'] }} - ${{ number_format($datos['operaciones']['ventas']['total'], 2, ',', '.') }}</span>
    </div>
    @endif
    @if(!empty($datos['operaciones']['cobros']))
    <div class="fila">
        <span>Cobros Cta. Cte.:</span>
        <span>{{ $datos['operaciones']['cobros']['cantidad'] }} - ${{ number_format($datos['operaciones']['cobros']['total'], 2, ',', '.') }}</span>
    </div>
    @endif
    @endif

    {{-- ========== OBSERVACIONES ========== --}}
    @if(!empty($datos['cierre']['observaciones']))
    <div class="linea"></div>
    <div class="seccion-titulo">OBSERVACIONES</div>
    <div class="small">{{ $datos['cierre']['observaciones'] }}</div>
    @endif

    <div class="linea"></div>

    {{-- ========== PIE ========== --}}
    <div class="center bold">
        {{ $config?->texto_pie_cierre ?? 'Cierre de turno finalizado' }}
    </div>

    {{-- ========== FIRMAS ========== --}}
    <div style="margin-top: 20px; display: flex; gap: 4px;">
        <div style="flex: 1;">
            <div style="border-bottom: 1px solid #000; margin-bottom: 3px; height: 25px;"></div>
            <div class="center small">Operador</div>
        </div>
        <div style="flex: 1;">
            <div style="border-bottom: 1px solid #000; margin-bottom: 3px; height: 25px;"></div>
            <div class="center small">Encargado</div>
        </div>
    </div>

    {{-- Fecha impresion --}}
    <div class="center small" style="margin-top: 10px;">
        Impreso: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
