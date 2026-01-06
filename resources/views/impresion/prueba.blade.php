<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {{ $impresora->formato_papel === 'a4' || $impresora->formato_papel === 'carta' ? 'A4' : '80mm auto' }};
            margin: {{ $impresora->esTermica() ? '0' : '20mm' }};
        }
        body {
            font-family: {{ $impresora->esTermica() ? "'Courier New', monospace" : "Arial, sans-serif" }};
            text-align: center;
            padding: {{ $impresora->esTermica() ? '5mm' : '50px' }};
        }
        h1 {
            color: #222036;
            font-size: {{ $impresora->esTermica() ? '16px' : '24px' }};
        }
        .line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <h1>PRUEBA DE IMPRESION</h1>

    @if($impresora->esTermica())
        <div class="line"></div>
    @else
        <hr>
    @endif

    <p><strong>BCN Pymes</strong></p>
    <p>Impresora: {{ $impresora->nombre }}</p>
    <p>Tipo: {{ $impresora->tipo_legible }}</p>
    <p>Formato: {{ $impresora->formato_papel_legible }}</p>

    @if($impresora->esTermica())
        <div class="line"></div>
    @else
        <hr>
    @endif

    <p>Impresora configurada correctamente.</p>
    <p>{{ now()->format('d/m/Y H:i:s') }}</p>
</body>
</html>
