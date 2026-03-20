<?php

namespace App\Services\Impresion;

use App\Models\ComprobanteFiscal;
use App\Models\ConfiguracionImpresion;
use App\Models\Impresora;
use App\Models\Venta;
use Illuminate\Support\Facades\View;

/**
 * Generador de contenido HTML para impresoras A4/Carta
 */
class GeneradorHTML
{
    /**
     * Genera HTML para ticket de venta
     */
    public function generarTicketVenta(Venta $venta, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.ticket-venta', [
            'venta' => $venta,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }

    /**
     * Genera HTML para ticket de venta en impresora térmica
     */
    public function generarTicketVentaTermica(Venta $venta, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.ticket-venta-termica', [
            'venta' => $venta,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }

    /**
     * Genera HTML para factura A4
     */
    public function generarFactura(ComprobanteFiscal $comprobante, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.factura-a4', [
            'comprobante' => $comprobante,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }

    /**
     * Genera HTML para factura en impresora térmica (con fuente Arial)
     */
    public function generarFacturaTermica(ComprobanteFiscal $comprobante, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.factura-termica', [
            'comprobante' => $comprobante,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }

    /**
     * Genera HTML para prueba de impresión
     */
    public function generarPrueba(Impresora $impresora): string
    {
        return View::make('impresion.prueba', [
            'impresora' => $impresora,
        ])->render();
    }

    /**
     * Genera HTML para cierre de caja
     */
    public function generarCierreCaja(array $datos, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.cierre-caja', [
            'datos' => $datos,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }

    /**
     * Genera HTML para cierre de turno
     */
    public function generarCierreTurno(array $datos, Impresora $impresora, ?ConfiguracionImpresion $config): string
    {
        return View::make('impresion.cierre-turno', [
            'datos' => $datos,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();
    }
}
