<?php

namespace App\Services\Impresion;

use App\Models\PedidoDelivery;
use App\Models\PedidoMostrador;
use App\Models\Sucursal;

/**
 * Plantillas de impresión para Pedidos por Mostrador.
 *
 * Genera comanda (cocina) y precuenta (cliente) en formato ESC/POS (bytes
 * crudos para QZ Tray) y HTML (preview en pantalla / impresoras no térmicas).
 *
 * Si la sucursal tiene `usa_beepers=true` y el pedido tiene `numero_beeper`,
 * el encabezado de la comanda lo muestra en tamaño grande para que la cocina
 * lo cante en voz alta o lo busque en el llamador.
 */
class PlantillasComanda
{
    // ESC/POS control bytes
    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const LF = "\x0A";

    private const INIT = "\x1B\x40";              // ESC @ — initialize printer

    private const ALIGN_LEFT = "\x1B\x61\x00";    // ESC a 0

    private const ALIGN_CENTER = "\x1B\x61\x01";  // ESC a 1

    private const BOLD_ON = "\x1B\x45\x01";       // ESC E 1

    private const BOLD_OFF = "\x1B\x45\x00";      // ESC E 0

    private const SIZE_NORMAL = "\x1B\x21\x00";   // ESC ! 0

    private const SIZE_DOUBLE = "\x1B\x21\x30";   // ESC ! 0x30 (double w + h)

    private const CUT_FULL = "\x1D\x56\x00";      // GS V 0

    // ==================== COMANDA ====================

    public function generarComandaESCPOS(PedidoMostrador|PedidoDelivery $pedido, ?array $detalleIds = null, bool $esParcial = false): string
    {
        $pedido->loadMissing(['detalles.articulo', 'detalles.opcionales', 'sucursal']);
        $sucursal = $pedido->sucursal ?? Sucursal::find($pedido->sucursal_id);

        $out = self::INIT;
        $out .= self::ALIGN_CENTER.self::BOLD_ON.self::SIZE_DOUBLE;
        $out .= mb_strtoupper((string) ($sucursal->nombre_publico ?? $sucursal->nombre ?? 'COMANDA')).self::LF;
        $out .= self::SIZE_NORMAL.self::BOLD_OFF;
        $out .= 'Pedido #'.($pedido->numero_visible ?? '-').self::LF;
        $out .= optional($pedido->fecha)->format('d/m/Y H:i').self::LF;
        $out .= str_repeat('-', 32).self::LF;

        // Header parcial: comanda solo de items nuevos agregados a un pedido
        // ya en preparacion. Doble alto + centrado para que cocina lo capte
        // de inmediato y no produzca el ticket completo.
        if ($esParcial) {
            $out .= self::ALIGN_CENTER.self::BOLD_ON.self::SIZE_DOUBLE;
            $out .= '*** AGREGADO ***'.self::LF;
            $out .= self::SIZE_NORMAL.self::BOLD_OFF;
            $out .= str_repeat('-', 32).self::LF;
        }

        if (! empty($sucursal?->usa_beepers) && ! empty($pedido->numero_beeper)) {
            $out .= self::ALIGN_CENTER.self::BOLD_ON.self::SIZE_DOUBLE;
            $out .= 'BEEPER '.$pedido->numero_beeper.self::LF;
            $out .= self::SIZE_NORMAL.self::BOLD_OFF;
            $out .= str_repeat('-', 32).self::LF;
        }

        $out .= self::ALIGN_LEFT;

        $clienteNombre = $pedido->nombre_cliente_final;
        if ($clienteNombre) {
            $out .= 'Cliente: '.$clienteNombre.self::LF;
        }
        if (! empty($pedido->identificador)) {
            $out .= 'Identificador: '.$pedido->identificador.self::LF;
        }
        $out .= str_repeat('-', 32).self::LF;

        $detallesAImprimir = $detalleIds !== null
            ? $pedido->detalles->whereIn('id', $detalleIds)
            : $pedido->detalles;

        foreach ($detallesAImprimir as $detalle) {
            $nombre = $detalle->es_concepto
                ? ($detalle->concepto_descripcion ?? 'Concepto')
                : ($detalle->articulo?->nombre ?? "Artículo #{$detalle->articulo_id}");

            $out .= self::BOLD_ON.rtrim($this->formatoCantidad($detalle->cantidad)).' x '.$nombre.self::LF.self::BOLD_OFF;

            foreach ($detalle->opcionales ?? [] as $opc) {
                $cant = $opc->cantidad ?? 1;
                $nom = $opc->nombre_opcional ?? 'Opcional';
                $out .= '  + '.$cant.'x '.$nom.self::LF;
            }
        }

        if (! empty($pedido->observaciones)) {
            $out .= str_repeat('-', 32).self::LF;
            $out .= 'Obs: '.$pedido->observaciones.self::LF;
        }

        $out .= self::LF.self::LF.self::LF;
        $out .= self::CUT_FULL;

        return $out;
    }

    public function generarComandaHTML(PedidoMostrador|PedidoDelivery $pedido, ?array $detalleIds = null, bool $esParcial = false): string
    {
        $pedido->loadMissing(['detalles.articulo', 'detalles.opcionales', 'sucursal']);
        $sucursal = $pedido->sucursal ?? Sucursal::find($pedido->sucursal_id);

        $titulo = e($sucursal->nombre_publico ?? $sucursal->nombre ?? 'Comanda');
        $numero = e((string) ($pedido->numero_visible ?? '-'));
        $fecha = e(optional($pedido->fecha)->format('d/m/Y H:i'));

        $beeperBlock = '';
        if (! empty($sucursal?->usa_beepers) && ! empty($pedido->numero_beeper)) {
            $beeperBlock = '<div class="beeper">BEEPER '.e((string) $pedido->numero_beeper).'</div>';
        }

        $clienteRow = '';
        if ($pedido->nombre_cliente_final) {
            $clienteRow = '<div>Cliente: '.e($pedido->nombre_cliente_final).'</div>';
        }
        $identRow = '';
        if (! empty($pedido->identificador)) {
            $identRow = '<div>Identificador: '.e($pedido->identificador).'</div>';
        }

        $parcialBlock = $esParcial
            ? '<div style="text-align:center;font-weight:bold;font-size:1.5em;border-top:2px solid #000;border-bottom:2px solid #000;padding:4px 0;margin:4px 0">*** AGREGADO ***</div>'
            : '';

        $detallesAImprimir = $detalleIds !== null
            ? $pedido->detalles->whereIn('id', $detalleIds)
            : $pedido->detalles;

        $items = '';
        foreach ($detallesAImprimir as $detalle) {
            $nombre = $detalle->es_concepto
                ? ($detalle->concepto_descripcion ?? 'Concepto')
                : ($detalle->articulo?->nombre ?? "Artículo #{$detalle->articulo_id}");
            $items .= '<div><strong>'.e($this->formatoCantidad($detalle->cantidad)).' x '.e($nombre).'</strong></div>';
            foreach ($detalle->opcionales ?? [] as $opc) {
                $cant = $opc->cantidad ?? 1;
                $nom = $opc->nombre_opcional ?? 'Opcional';
                $items .= '<div style="padding-left:1em">+ '.e((string) $cant).'x '.e($nom).'</div>';
            }
        }

        $obs = '';
        if (! empty($pedido->observaciones)) {
            $obs = '<div class="obs">Obs: '.e($pedido->observaciones).'</div>';
        }

        return <<<HTML
<div class="comanda" style="font-family:monospace;width:80mm">
  <div style="text-align:center;font-weight:bold;font-size:1.2em">{$titulo}</div>
  <div style="text-align:center">Pedido #{$numero}</div>
  <div style="text-align:center">{$fecha}</div>
  {$parcialBlock}
  {$beeperBlock}
  <hr>
  {$clienteRow}
  {$identRow}
  <hr>
  {$items}
  {$obs}
</div>
HTML;
    }

    // ==================== PRECUENTA ====================

    public function generarPrecuentaESCPOS(PedidoMostrador|PedidoDelivery $pedido): string
    {
        $pedido->loadMissing(['detalles.articulo', 'sucursal']);
        $sucursal = $pedido->sucursal ?? Sucursal::find($pedido->sucursal_id);

        $out = self::INIT;
        $out .= self::ALIGN_CENTER.self::BOLD_ON.self::SIZE_DOUBLE;
        $out .= mb_strtoupper((string) ($sucursal->nombre_publico ?? $sucursal->nombre ?? 'PRECUENTA')).self::LF;
        $out .= self::SIZE_NORMAL.self::BOLD_OFF;
        $out .= 'PRECUENTA — NO VÁLIDO COMO FACTURA'.self::LF;
        $out .= 'Pedido #'.($pedido->numero_visible ?? '-').self::LF;
        $out .= optional($pedido->fecha)->format('d/m/Y H:i').self::LF;
        $out .= str_repeat('-', 32).self::LF;
        $out .= self::ALIGN_LEFT;

        foreach ($pedido->detalles as $detalle) {
            $nombre = $detalle->es_concepto
                ? ($detalle->concepto_descripcion ?? 'Concepto')
                : ($detalle->articulo?->nombre ?? "Artículo #{$detalle->articulo_id}");
            $linea = $this->formatoCantidad($detalle->cantidad).'x '.$nombre;
            $total = '$'.number_format((float) $detalle->total, 2, ',', '.');
            $out .= $this->lineaConTotal($linea, $total).self::LF;
        }

        $out .= str_repeat('-', 32).self::LF;
        $out .= $this->lineaConTotal('Subtotal', '$'.number_format((float) $pedido->subtotal, 2, ',', '.')).self::LF;
        if ((float) $pedido->descuento > 0) {
            $out .= $this->lineaConTotal('Descuento', '-$'.number_format((float) $pedido->descuento, 2, ',', '.')).self::LF;
        }
        if ((float) $pedido->iva > 0) {
            $out .= $this->lineaConTotal('IVA', '$'.number_format((float) $pedido->iva, 2, ',', '.')).self::LF;
        }
        if ((float) $pedido->ajuste_forma_pago != 0.0) {
            $out .= $this->lineaConTotal('Ajuste FP', '$'.number_format((float) $pedido->ajuste_forma_pago, 2, ',', '.')).self::LF;
        }
        $out .= self::BOLD_ON;
        $out .= $this->lineaConTotal('TOTAL', '$'.number_format((float) $pedido->total_final, 2, ',', '.')).self::LF;
        $out .= self::BOLD_OFF;
        $out .= self::LF.self::LF.self::LF;
        $out .= self::CUT_FULL;

        return $out;
    }

    public function generarPrecuentaHTML(PedidoMostrador|PedidoDelivery $pedido): string
    {
        $pedido->loadMissing(['detalles.articulo', 'sucursal']);
        $sucursal = $pedido->sucursal ?? Sucursal::find($pedido->sucursal_id);

        $titulo = e($sucursal->nombre_publico ?? $sucursal->nombre ?? 'Precuenta');
        $numero = e((string) ($pedido->numero_visible ?? '-'));
        $fecha = e(optional($pedido->fecha)->format('d/m/Y H:i'));

        $items = '';
        foreach ($pedido->detalles as $detalle) {
            $nombre = $detalle->es_concepto
                ? ($detalle->concepto_descripcion ?? 'Concepto')
                : ($detalle->articulo?->nombre ?? "Artículo #{$detalle->articulo_id}");
            $cant = e($this->formatoCantidad($detalle->cantidad));
            $tot = e('$'.number_format((float) $detalle->total, 2, ',', '.'));
            $items .= "<tr><td>{$cant}x {$nombre}</td><td style=\"text-align:right\">{$tot}</td></tr>";
        }

        $subtotal = e('$'.number_format((float) $pedido->subtotal, 2, ',', '.'));
        $totalFinal = e('$'.number_format((float) $pedido->total_final, 2, ',', '.'));

        return <<<HTML
<div class="precuenta" style="font-family:monospace;width:80mm">
  <div style="text-align:center;font-weight:bold;font-size:1.2em">{$titulo}</div>
  <div style="text-align:center">PRECUENTA — NO VÁLIDO COMO FACTURA</div>
  <div style="text-align:center">Pedido #{$numero}</div>
  <div style="text-align:center">{$fecha}</div>
  <hr>
  <table style="width:100%">{$items}</table>
  <hr>
  <table style="width:100%">
    <tr><td>Subtotal</td><td style="text-align:right">{$subtotal}</td></tr>
    <tr style="font-weight:bold"><td>TOTAL</td><td style="text-align:right">{$totalFinal}</td></tr>
  </table>
</div>
HTML;
    }

    // ==================== HELPERS ====================

    private function formatoCantidad(float|string $cant): string
    {
        $f = (float) $cant;
        if ($f == (int) $f) {
            return (string) (int) $f;
        }

        return rtrim(rtrim(number_format($f, 3, '.', ''), '0'), '.');
    }

    /**
     * Devuelve una línea de ~32 columnas con etiqueta a la izquierda y total a
     * la derecha, padding con espacios. Si la suma supera 32 chars, trunca.
     */
    private function lineaConTotal(string $etiqueta, string $total, int $ancho = 32): string
    {
        $disponible = $ancho - mb_strlen($total) - 1;
        if ($disponible < 1) {
            return mb_substr($etiqueta.' '.$total, 0, $ancho);
        }
        $etiquetaTrim = mb_substr($etiqueta, 0, $disponible);
        $espacios = str_repeat(' ', max(1, $ancho - mb_strlen($etiquetaTrim) - mb_strlen($total)));

        return $etiquetaTrim.$espacios.$total;
    }
}
