<?php

namespace App\Services;

use App\Models\Impresora;
use App\Models\ImpresoraSucursalCaja;
use App\Models\ImpresoraTipoDocumento;
use App\Models\ConfiguracionImpresion;
use App\Models\Venta;
use App\Models\ComprobanteFiscal;
use App\Services\Impresion\GeneradorESCPOS;
use App\Services\Impresion\GeneradorHTML;
use Illuminate\Support\Facades\Log;
use Exception;

class ImpresionService
{
    protected GeneradorESCPOS $escpos;
    protected GeneradorHTML $html;

    public function __construct()
    {
        $this->escpos = new GeneradorESCPOS();
        $this->html = new GeneradorHTML();
    }

    /**
     * Obtiene la impresora configurada para un tipo de documento
     */
    public function obtenerImpresora(int $sucursalId, ?int $cajaId, string $tipoDocumento): ?Impresora
    {
        // Buscar primero por caja específica
        if ($cajaId) {
            $asignacion = ImpresoraSucursalCaja::with(['impresora', 'tiposDocumento'])
                ->where('sucursal_id', $sucursalId)
                ->where('caja_id', $cajaId)
                ->whereHas('tiposDocumento', fn($q) => $q->where('tipo_documento', $tipoDocumento)->where('activo', true))
                ->first();

            if ($asignacion && $asignacion->impresora?->activa) {
                return $asignacion->impresora;
            }
        }

        // Si no hay por caja, buscar por sucursal (caja_id = null)
        $asignacion = ImpresoraSucursalCaja::with(['impresora', 'tiposDocumento'])
            ->where('sucursal_id', $sucursalId)
            ->whereNull('caja_id')
            ->whereHas('tiposDocumento', fn($q) => $q->where('tipo_documento', $tipoDocumento)->where('activo', true))
            ->first();

        if ($asignacion && $asignacion->impresora?->activa) {
            return $asignacion->impresora;
        }

        // Fallback: impresora por defecto de la sucursal
        $asignacion = ImpresoraSucursalCaja::with('impresora')
            ->where('sucursal_id', $sucursalId)
            ->where('es_defecto', true)
            ->first();

        return $asignacion?->impresora;
    }

    /**
     * Genera el contenido de impresión para un ticket de venta
     */
    public function generarTicketVenta(Venta $venta): array
    {
        $impresora = $this->obtenerImpresora($venta->sucursal_id, $venta->caja_id, 'ticket_venta');

        if (!$impresora) {
            throw new Exception('No hay impresora configurada para tickets de venta en esta sucursal/caja');
        }

        $config = ConfiguracionImpresion::where('sucursal_id', $venta->sucursal_id)->first();
        $venta->load(['detalles.articulo', 'cliente', 'sucursal', 'caja', 'pagos.formaPago', 'usuario']);

        if ($impresora->esTermica()) {
            return [
                'tipo' => 'escpos',
                'impresora' => $impresora->nombre_sistema,
                'datos' => $this->escpos->generarTicketVenta($venta, $impresora, $config),
                'opciones' => [
                    'cortar' => $config?->cortar_papel_automatico ?? true,
                    'abrir_cajon' => $this->debeAbrirCajon($venta, $config),
                ]
            ];
        }

        return [
            'tipo' => 'html',
            'impresora' => $impresora->nombre_sistema,
            'datos' => $this->html->generarTicketVenta($venta, $impresora, $config),
            'opciones' => [
                'formato' => $impresora->formato_papel,
            ]
        ];
    }

    /**
     * Genera el contenido de impresión para una factura fiscal
     */
    public function generarFactura(ComprobanteFiscal $comprobante): array
    {
        $venta = $comprobante->ventas->first();
        $sucursalId = $comprobante->sucursal_id;
        $cajaId = $venta?->caja_id;

        $tipoDoc = match($comprobante->letra) {
            'A' => 'factura_a',
            'B' => 'factura_b',
            'C' => 'factura_c',
            default => 'factura_b',
        };

        $impresora = $this->obtenerImpresora($sucursalId, $cajaId, $tipoDoc);

        if (!$impresora) {
            // Si no hay impresora específica para facturas, usar la de tickets
            $impresora = $this->obtenerImpresora($sucursalId, $cajaId, 'ticket_venta');
        }

        if (!$impresora) {
            throw new Exception('No hay impresora configurada para facturas en esta sucursal/caja');
        }

        $config = ConfiguracionImpresion::where('sucursal_id', $sucursalId)->first();
        $comprobante->load(['items', 'detallesIva', 'cuit', 'puntoVenta', 'cliente', 'sucursal']);

        if ($impresora->esTermica()) {
            return [
                'tipo' => 'escpos',
                'impresora' => $impresora->nombre_sistema,
                'datos' => $this->escpos->generarFactura($comprobante, $impresora, $config),
                'opciones' => [
                    'cortar' => $config?->cortar_papel_automatico ?? true,
                ]
            ];
        }

        return [
            'tipo' => 'html',
            'impresora' => $impresora->nombre_sistema,
            'datos' => $this->html->generarFactura($comprobante, $impresora, $config),
            'opciones' => [
                'formato' => $impresora->formato_papel,
            ]
        ];
    }

    /**
     * Genera datos de prueba de impresión
     */
    public function generarPrueba(Impresora $impresora): array
    {
        if ($impresora->esTermica()) {
            return [
                'tipo' => 'escpos',
                'impresora' => $impresora->nombre_sistema,
                'datos' => $this->escpos->generarPrueba($impresora),
                'opciones' => [
                    'cortar' => true,
                ]
            ];
        }

        return [
            'tipo' => 'html',
            'impresora' => $impresora->nombre_sistema,
            'datos' => $this->html->generarPrueba($impresora),
            'opciones' => [
                'formato' => $impresora->formato_papel,
            ]
        ];
    }

    /**
     * Determina si debe abrir el cajón de dinero
     */
    protected function debeAbrirCajon(Venta $venta, ?ConfiguracionImpresion $config): bool
    {
        if (!$config?->abrir_cajon_efectivo) {
            return false;
        }

        // Verificar si la venta incluye pago en efectivo
        foreach ($venta->pagos as $pago) {
            if ($pago->formaPago?->codigo === 'efectivo' || $pago->formaPago?->nombre === 'Efectivo') {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene todas las impresoras activas
     */
    public function listarImpresoras(): array
    {
        return Impresora::activas()
            ->orderBy('nombre')
            ->get()
            ->toArray();
    }

    /**
     * Obtiene impresoras de una sucursal
     */
    public function impresoresPorSucursal(int $sucursalId): array
    {
        return ImpresoraSucursalCaja::with(['impresora', 'caja', 'tiposDocumento'])
            ->where('sucursal_id', $sucursalId)
            ->whereHas('impresora', fn($q) => $q->where('activa', true))
            ->get()
            ->toArray();
    }

    /**
     * Obtiene la configuración de impresión de una sucursal
     */
    public function obtenerConfiguracion(int $sucursalId): ConfiguracionImpresion
    {
        return ConfiguracionImpresion::obtenerParaSucursal($sucursalId);
    }
}
