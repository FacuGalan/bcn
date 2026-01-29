<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuracion de Impresion BCN Pymes
    |--------------------------------------------------------------------------
    |
    | Este archivo contiene la configuracion global del modulo de impresion.
    | Incluye configuraciones para QZ Tray y valores por defecto.
    |
    */

    // Impresion automatica despues de venta (puede sobreescribirse por sucursal)
    'impresion_automatica' => env('IMPRESION_AUTOMATICA', true),

    // Formatos de papel disponibles con sus anchos
    'formatos_papel' => [
        '80mm' => ['ancho' => 80, 'caracteres' => 48, 'tipo' => 'termica'],
        '58mm' => ['ancho' => 58, 'caracteres' => 32, 'tipo' => 'termica'],
        'a4' => ['ancho' => 210, 'caracteres' => 80, 'tipo' => 'laser_inkjet'],
        'carta' => ['ancho' => 216, 'caracteres' => 80, 'tipo' => 'laser_inkjet'],
    ],

    // Configuracion de QZ Tray
    'qz' => [
        // Rutas de certificados (para firma de mensajes)
        'certificado_path' => storage_path('app/qz/certificate.pem'),
        'clave_privada_path' => storage_path('app/qz/private-key.pem'),

        // URL del CDN de QZ Tray
        'cdn_url' => 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.min.js',

        // Puerto WebSocket de QZ Tray
        'websocket_port' => 9083,
    ],

    // Textos por defecto para tickets
    'texto_pie_defecto' => 'Gracias por su compra!',

    // Texto legal para facturas
    'texto_legal_defecto' => '',

    // Tipos de documento disponibles
    'tipos_documento' => [
        'ticket_venta' => 'Ticket de Venta',
        'factura_a' => 'Factura A',
        'factura_b' => 'Factura B',
        'factura_c' => 'Factura C',
        'comanda' => 'Comanda',
        'precuenta' => 'Precuenta',
        'cierre_turno' => 'Cierre de Turno',
        'cierre_caja' => 'Cierre de Caja',
        'arqueo' => 'Arqueo',
        'recibo' => 'Recibo',
    ],

    // Comandos ESC/POS (para referencia)
    'escpos' => [
        'init' => "\x1B\x40",
        'bold_on' => "\x1B\x45\x01",
        'bold_off' => "\x1B\x45\x00",
        'align_left' => "\x1B\x61\x00",
        'align_center' => "\x1B\x61\x01",
        'align_right' => "\x1B\x61\x02",
        'cut_partial' => "\x1D\x56\x01",
        'cut_full' => "\x1D\x56\x00",
        'open_drawer' => "\x1B\x70\x00\x19\xFA",
    ],
];
