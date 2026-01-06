<?php

namespace App\Services\Impresion;

use App\Models\Venta;
use App\Models\ComprobanteFiscal;
use App\Models\Impresora;
use App\Models\ConfiguracionImpresion;

/**
 * Generador de comandos ESC/POS para impresoras térmicas
 */
class GeneradorESCPOS
{
    // Comandos ESC/POS comunes
    private const ESC = "\x1B";
    private const GS = "\x1D";
    private const INIT = "\x1B\x40";
    private const BOLD_ON = "\x1B\x45\x01";
    private const BOLD_OFF = "\x1B\x45\x00";
    private const ALIGN_LEFT = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const ALIGN_RIGHT = "\x1B\x61\x02";
    private const FONT_NORMAL = "\x1B\x21\x00";
    private const FONT_DOUBLE_HEIGHT = "\x1B\x21\x10";
    private const FONT_DOUBLE_WIDTH = "\x1B\x21\x20";
    private const FONT_DOUBLE = "\x1B\x21\x30";
    private const CUT_PARTIAL = "\x1D\x56\x01";
    private const CUT_FULL = "\x1D\x56\x00";
    private const OPEN_DRAWER = "\x1B\x70\x00\x19\xFA";
    private const FEED_LINES = "\x1B\x64";

    protected int $ancho = 48;

    /**
     * Genera comandos ESC/POS para ticket de venta
     */
    public function generarTicketVenta(Venta $venta, Impresora $impresora, ?ConfiguracionImpresion $config): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        // Inicializar
        $comandos[] = $this->raw(self::INIT);

        // Encabezado
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto($venta->sucursal->nombre_publico ?? $venta->sucursal->nombre);
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // Dirección y datos
        if ($venta->sucursal->direccion) {
            $comandos[] = $this->texto($venta->sucursal->direccion);
        }
        if ($venta->sucursal->telefono) {
            $comandos[] = $this->texto("Tel: " . $venta->sucursal->telefono);
        }

        $comandos[] = $this->linea();

        // Número de ticket y fecha
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->texto("Ticket: " . $venta->numero);
        $comandos[] = $this->texto("Fecha: " . $venta->fecha->format('d/m/Y H:i'));
        $comandos[] = $this->texto("Caja: " . ($venta->caja?->nombre ?? '-'));
        $comandos[] = $this->texto("Vendedor: " . ($venta->usuario?->name ?? '-'));

        if ($venta->cliente) {
            $comandos[] = $this->texto("Cliente: " . $venta->cliente->nombre);
        }

        $comandos[] = $this->linea();

        // Encabezado de detalles
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->texto($this->formatearColumnas(['Cant', 'Descripcion', 'Precio'], [5, $this->ancho - 18, 12]));
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->linea('-');

        // Detalles
        foreach ($venta->detalles as $detalle) {
            $linea = $this->formatearColumnas([
                number_format($detalle->cantidad, 0),
                $this->truncar($detalle->articulo->nombre, $this->ancho - 18),
                '$' . number_format($detalle->total, 2)
            ], [5, $this->ancho - 18, 12]);
            $comandos[] = $this->texto($linea);
        }

        $comandos[] = $this->linea();

        // Totales
        $comandos[] = $this->alinear('derecha');
        $comandos[] = $this->negrita(true);

        if ($venta->descuento > 0) {
            $comandos[] = $this->texto("Subtotal: $" . number_format($venta->subtotal + $venta->descuento, 2));
            $comandos[] = $this->texto("Descuento: -$" . number_format($venta->descuento, 2));
        }

        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("TOTAL: $" . number_format($venta->total_final, 2));
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // Formas de pago
        $comandos[] = $this->linea('-');
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->texto("FORMAS DE PAGO:");

        foreach ($venta->pagos as $pago) {
            $fp = $pago->formaPago?->nombre ?? 'Efectivo';
            $comandos[] = $this->texto("  {$fp}: $" . number_format($pago->monto_final, 2));

            if ($pago->vuelto > 0) {
                $comandos[] = $this->texto("  Vuelto: $" . number_format($pago->vuelto, 2));
            }
        }

        // Pie de ticket
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto($config?->texto_pie_ticket ?? "Gracias por su compra!");

        // Avanzar papel
        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    /**
     * Genera comandos ESC/POS para factura
     */
    public function generarFactura(ComprobanteFiscal $comprobante, Impresora $impresora, ?ConfiguracionImpresion $config): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        $comandos[] = $this->raw(self::INIT);

        // Tipo de comprobante
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto($comprobante->tipo_legible);
        $comandos[] = $this->fuenteNormal();

        // Número
        $comandos[] = $this->texto("Nro: " . $comprobante->numero_formateado);
        $comandos[] = $this->negrita(false);

        // Datos del emisor
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('izquierda');
        $comandos[] = $this->texto($comprobante->cuit->razon_social);
        $comandos[] = $this->texto("CUIT: " . $comprobante->cuit->numero_formateado);
        $comandos[] = $this->texto("Fecha: " . $comprobante->fecha_emision->format('d/m/Y'));

        // Datos del receptor
        $comandos[] = $this->linea();
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->texto("CLIENTE:");
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->texto($comprobante->receptor_nombre);
        $comandos[] = $this->texto($comprobante->receptor_documento_tipo . ": " . $comprobante->receptor_documento_numero);

        // Items
        $comandos[] = $this->linea();
        foreach ($comprobante->items as $item) {
            $comandos[] = $this->texto($this->truncar($item->descripcion, $this->ancho));
            $comandos[] = $this->texto("  " . $item->cantidad . " x $" . number_format($item->precio_unitario, 2) . " = $" . number_format($item->total, 2));
        }

        // Totales
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('derecha');

        if ($comprobante->neto_gravado > 0) {
            $comandos[] = $this->texto("Neto Gravado: $" . number_format($comprobante->neto_gravado, 2));
        }
        if ($comprobante->iva_total > 0) {
            $comandos[] = $this->texto("IVA: $" . number_format($comprobante->iva_total, 2));
        }

        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("TOTAL: $" . number_format($comprobante->total, 2));
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);

        // CAE
        $comandos[] = $this->linea();
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->texto("CAE: " . $comprobante->cae);
        $comandos[] = $this->texto("Vto CAE: " . $comprobante->cae_vencimiento->format('d/m/Y'));

        // Texto legal
        if ($config?->texto_legal_factura) {
            $comandos[] = $this->linea();
            $palabras = explode(' ', $config->texto_legal_factura);
            $linea = '';
            foreach ($palabras as $palabra) {
                if (strlen($linea . ' ' . $palabra) > $this->ancho) {
                    $comandos[] = $this->texto($linea);
                    $linea = $palabra;
                } else {
                    $linea .= ($linea ? ' ' : '') . $palabra;
                }
            }
            if ($linea) {
                $comandos[] = $this->texto($linea);
            }
        }

        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    /**
     * Genera comandos para prueba de impresión
     */
    public function generarPrueba(Impresora $impresora): array
    {
        $this->ancho = $impresora->ancho_caracteres;
        $comandos = [];

        $comandos[] = $this->raw(self::INIT);
        $comandos[] = $this->alinear('centro');
        $comandos[] = $this->negrita(true);
        $comandos[] = $this->fuenteDoble();
        $comandos[] = $this->texto("PRUEBA DE IMPRESION");
        $comandos[] = $this->fuenteNormal();
        $comandos[] = $this->negrita(false);
        $comandos[] = $this->linea();
        $comandos[] = $this->texto("BCN Pymes");
        $comandos[] = $this->texto("Impresora: " . $impresora->nombre);
        $comandos[] = $this->texto("Tipo: " . $impresora->tipo_legible);
        $comandos[] = $this->texto("Ancho: " . $impresora->ancho_caracteres . " caracteres");
        $comandos[] = $this->linea();
        $comandos[] = $this->texto("Impresora configurada");
        $comandos[] = $this->texto("correctamente.");
        $comandos[] = $this->linea();
        $comandos[] = $this->texto(date('d/m/Y H:i:s'));
        $comandos[] = $this->avanzar(4);

        return $comandos;
    }

    // Métodos auxiliares
    protected function raw(string $bytes): array
    {
        return ['type' => 'raw', 'data' => base64_encode($bytes)];
    }

    protected function texto(string $texto): array
    {
        return ['type' => 'raw', 'data' => base64_encode($texto . "\n")];
    }

    protected function alinear(string $pos): array
    {
        $cmd = match($pos) {
            'izquierda' => self::ALIGN_LEFT,
            'centro' => self::ALIGN_CENTER,
            'derecha' => self::ALIGN_RIGHT,
            default => self::ALIGN_LEFT,
        };
        return $this->raw($cmd);
    }

    protected function negrita(bool $on): array
    {
        return $this->raw($on ? self::BOLD_ON : self::BOLD_OFF);
    }

    protected function fuenteNormal(): array
    {
        return $this->raw(self::FONT_NORMAL);
    }

    protected function fuenteDoble(): array
    {
        return $this->raw(self::FONT_DOUBLE);
    }

    protected function linea(string $char = '='): array
    {
        return $this->texto(str_repeat($char, $this->ancho));
    }

    protected function avanzar(int $lineas): array
    {
        return $this->raw(self::FEED_LINES . chr($lineas));
    }

    public function cortar(bool $parcial = true): array
    {
        return $this->raw($parcial ? self::CUT_PARTIAL : self::CUT_FULL);
    }

    public function abrirCajon(): array
    {
        return $this->raw(self::OPEN_DRAWER);
    }

    protected function formatearColumnas(array $valores, array $anchos): string
    {
        $linea = '';
        foreach ($valores as $i => $valor) {
            $ancho = $anchos[$i] ?? 10;
            if ($i === count($valores) - 1) {
                $linea .= str_pad($valor, $ancho, ' ', STR_PAD_LEFT);
            } else {
                $linea .= str_pad(mb_substr($valor, 0, $ancho), $ancho);
            }
        }
        return $linea;
    }

    protected function truncar(string $texto, int $max): string
    {
        return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max - 3) . '...' : $texto;
    }
}
