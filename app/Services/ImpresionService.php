<?php

namespace App\Services;

use App\Models\Impresora;
use App\Models\ImpresoraSucursalCaja;
use App\Models\ImpresoraTipoDocumento;
use App\Models\ConfiguracionImpresion;
use App\Models\Venta;
use App\Models\ComprobanteFiscal;
use App\Models\CierreTurno;
use App\Models\Cobro;
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
        $venta->load([
            'detalles.articulo',
            'detalles.promocionesAplicadas',
            'cliente',
            'sucursal',
            'caja',
            'pagos.formaPago',
            'usuario',
            'promociones',
        ]);

        if ($impresora->esTermica()) {
            return [
                'tipo' => 'html',
                'impresora' => $impresora->nombre_sistema,
                'datos' => $this->html->generarTicketVentaTermica($venta, $impresora, $config),
                'opciones' => [
                    'formato' => $impresora->formato_papel,
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
        // Cargar todas las relaciones necesarias para la impresión PRIMERO
        $comprobante->load([
            'items',
            'detallesIva',
            'cuit',
            'puntoVenta',
            'cliente',
            'sucursal',
            'pagosFacturados.formaPago', // Para mostrar pagos facturados en factura parcial
            'ventas.detalles.articulo',
            'ventas.detalles.promocionesAplicadas',
            'ventas.promociones',
            'ventas.pagos.formaPago',
        ]);

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

        // Usar HTML para todas las impresoras (térmicas y láser/inkjet)
        // Esto permite usar fuentes del sistema como Arial
        if ($impresora->esTermica()) {
            return [
                'tipo' => 'html',
                'impresora' => $impresora->nombre_sistema,
                'datos' => $this->html->generarFacturaTermica($comprobante, $impresora, $config),
                'opciones' => [
                    'formato' => $impresora->formato_papel,
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

    /**
     * Genera el contenido de impresión para un cierre de turno
     */
    public function generarCierreTurno(CierreTurno $cierre): array
    {
        // Cargar relaciones necesarias
        $cierre->load([
            'sucursal',
            'usuario:id,name',
            'grupoCierre:id,nombre,fondo_comun',
            'detalleCajas.caja:id,nombre,numero',
            'movimientos' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'ventas',
            'ventaPagos.formaPago:id,nombre',
            'ventaPagos.conceptoPago:id,nombre',
            'cobros',
            'cobroPagos.formaPago:id,nombre',
            'cobroPagos.conceptoPago:id,nombre',
        ]);

        // Obtener la primera caja para determinar la impresora
        $primeraCaja = $cierre->detalleCajas->first();
        $cajaId = $primeraCaja?->caja_id;

        $impresora = $this->obtenerImpresora($cierre->sucursal_id, $cajaId, 'cierre_turno');

        if (!$impresora) {
            // Fallback a impresora de tickets
            $impresora = $this->obtenerImpresora($cierre->sucursal_id, $cajaId, 'ticket_venta');
        }

        if (!$impresora) {
            throw new Exception('No hay impresora configurada para cierres de turno en esta sucursal/caja');
        }

        $config = ConfiguracionImpresion::where('sucursal_id', $cierre->sucursal_id)->first();

        // Preparar datos para la vista
        $datos = $this->prepararDatosCierreTurno($cierre);

        return [
            'tipo' => 'html',
            'impresora' => $impresora->nombre_sistema,
            'datos' => $this->html->generarCierreTurno($datos, $impresora, $config),
            'opciones' => [
                'formato' => $impresora->formato_papel,
                'cortar' => $config?->cortar_papel_automatico ?? true,
            ]
        ];
    }

    /**
     * Prepara los datos del cierre de turno para la vista de impresión
     */
    protected function prepararDatosCierreTurno(CierreTurno $cierre): array
    {
        // Datos de la sucursal
        $sucursal = [
            'nombre' => $cierre->sucursal->nombre,
            'nombre_publico' => $cierre->sucursal->nombre_publico ?? $cierre->sucursal->nombre,
            'direccion' => $cierre->sucursal->direccion,
            'telefono' => $cierre->sucursal->telefono,
        ];

        // Calcular saldo sistema y declarado totales
        // Para cierres con fondo común, los saldos individuales son 0, usamos los totales del cierre
        $saldoSistema = $cierre->detalleCajas->sum('saldo_sistema');
        $saldoDeclarado = $cierre->detalleCajas->sum('saldo_declarado');

        // Si los saldos por caja son 0 (fondo común), calcular desde totales del cierre
        if ($saldoSistema == 0 && $cierre->total_saldo_inicial > 0) {
            $saldoSistema = $cierre->total_saldo_inicial + $cierre->total_ingresos - $cierre->total_egresos;
            $saldoDeclarado = (float) $cierre->total_saldo_final;
        }

        // Datos del cierre
        $datosBasicos = [
            'id' => $cierre->id,
            'fecha_cierre' => $cierre->fecha_cierre->format('d/m/Y H:i'),
            'fecha_apertura' => $cierre->fecha_apertura?->format('d/m/Y H:i'),
            'usuario' => $cierre->usuario?->name ?? '-',
            'es_grupal' => $cierre->esGrupal(),
            'grupo_nombre' => $cierre->grupoCierre?->nombre,
            'saldo_inicial' => (float) $cierre->total_saldo_inicial,
            'total_ingresos' => (float) $cierre->total_ingresos,
            'total_egresos' => (float) $cierre->total_egresos,
            'saldo_sistema' => (float) $saldoSistema,
            'saldo_declarado' => (float) $saldoDeclarado,
            'diferencia' => (float) $cierre->total_diferencia,
            'observaciones' => $cierre->observaciones,
        ];

        // Detalle por caja
        $cajas = [];
        foreach ($cierre->detalleCajas as $detalle) {
            $cajas[] = [
                'nombre' => $detalle->caja_nombre,
                'saldo_inicial' => (float) $detalle->saldo_inicial,
                'ingresos' => (float) $detalle->total_ingresos,
                'egresos' => (float) $detalle->total_egresos,
                'saldo_sistema' => (float) $detalle->saldo_sistema,
                'saldo_declarado' => (float) $detalle->saldo_declarado,
                'diferencia' => (float) $detalle->diferencia,
            ];
        }

        // Movimientos de caja MANUALES (ordenados por hora)
        // Excluir: apertura, venta, cobro (solo mostrar ajustes, retiros, transferencias, ingresos/egresos manuales)
        $tiposExcluidos = ['apertura', 'venta', 'cobro'];
        $movimientos = [];
        foreach ($cierre->movimientos as $mov) {
            // Excluir movimientos automáticos (apertura, ventas, cobros)
            if (in_array($mov->referencia_tipo, $tiposExcluidos)) {
                continue;
            }
            $movimientos[] = [
                'hora' => $mov->created_at->format('H:i'),
                'concepto' => $mov->concepto, // Texto completo sin abreviar
                'tipo' => $mov->tipo,
                'monto' => (float) $mov->monto,
                'referencia_tipo' => $mov->referencia_tipo,
            ];
        }

        // Consolidar formas de pago
        $formasPago = [];
        $conceptos = [];

        // Pagos de ventas
        foreach ($cierre->ventaPagos as $pago) {
            $forma = $pago->formaPago?->nombre ?? 'Otro';
            if (!isset($formasPago[$forma])) {
                $formasPago[$forma] = ['cantidad' => 0, 'total' => 0];
            }
            $formasPago[$forma]['cantidad']++;
            $formasPago[$forma]['total'] += $pago->monto_final;

            $concepto = $pago->conceptoPago?->nombre ?? 'Ventas';
            if (!isset($conceptos[$concepto])) {
                $conceptos[$concepto] = ['cantidad' => 0, 'total' => 0];
            }
            $conceptos[$concepto]['cantidad']++;
            $conceptos[$concepto]['total'] += $pago->monto_final;
        }

        // Pagos de cobros
        foreach ($cierre->cobroPagos as $pago) {
            $forma = $pago->formaPago?->nombre ?? 'Otro';
            if (!isset($formasPago[$forma])) {
                $formasPago[$forma] = ['cantidad' => 0, 'total' => 0];
            }
            $formasPago[$forma]['cantidad']++;
            $formasPago[$forma]['total'] += $pago->monto_final;

            $concepto = $pago->conceptoPago?->nombre ?? 'Cobros Cta. Cte.';
            if (!isset($conceptos[$concepto])) {
                $conceptos[$concepto] = ['cantidad' => 0, 'total' => 0];
            }
            $conceptos[$concepto]['cantidad']++;
            $conceptos[$concepto]['total'] += $pago->monto_final;
        }

        // Ordenar por total descendente
        uasort($formasPago, fn($a, $b) => $b['total'] <=> $a['total']);
        uasort($conceptos, fn($a, $b) => $b['total'] <=> $a['total']);

        // Comprobantes emitidos
        $comprobantes = [];
        $ventasConComprobante = $cierre->ventas->filter(fn($v) => $v->comprobante_fiscal_id);
        foreach ($ventasConComprobante as $venta) {
            $tipo = 'Ticket';
            if ($venta->comprobanteFiscal) {
                $tipo = 'Factura ' . ($venta->comprobanteFiscal->letra ?? '');
            }
            if (!isset($comprobantes[$tipo])) {
                $comprobantes[$tipo] = ['cantidad' => 0, 'total' => 0];
            }
            $comprobantes[$tipo]['cantidad']++;
            $comprobantes[$tipo]['total'] += $venta->total_final;
        }

        // Tickets (ventas sin comprobante fiscal)
        $ticketsSinFactura = $cierre->ventas->filter(fn($v) => !$v->comprobante_fiscal_id);
        if ($ticketsSinFactura->count() > 0) {
            $comprobantes['Tickets'] = [
                'cantidad' => $ticketsSinFactura->count(),
                'total' => $ticketsSinFactura->sum('total_final'),
            ];
        }

        // Operaciones resumen
        $operaciones = [];
        if ($cierre->ventas->count() > 0) {
            $operaciones['ventas'] = [
                'cantidad' => $cierre->ventas->count(),
                'total' => $cierre->ventas->sum('total_final'),
            ];
        }
        if ($cierre->cobros->count() > 0) {
            $operaciones['cobros'] = [
                'cantidad' => $cierre->cobros->count(),
                'total' => $cierre->cobros->sum('monto_total'),
            ];
        }

        return [
            'sucursal' => $sucursal,
            'cierre' => $datosBasicos,
            'cajas' => $cajas,
            'movimientos' => $movimientos,
            'formas_pago' => $formasPago,
            'conceptos' => $conceptos,
            'comprobantes' => $comprobantes,
            'operaciones' => $operaciones,
        ];
    }

    /**
     * Abrevia el concepto para que quepa en el ticket
     */
    protected function abreviarConcepto(string $concepto): string
    {
        $maxLen = 25;
        if (strlen($concepto) <= $maxLen) {
            return $concepto;
        }
        return substr($concepto, 0, $maxLen - 3) . '...';
    }

    /**
     * Genera el contenido de impresión para un recibo de cobro
     */
    public function generarReciboCobro(Cobro $cobro): array
    {
        // Cargar relaciones necesarias
        $cobro->load([
            'sucursal',
            'cliente',
            'caja',
            'cobroVentas.venta',
            'pagos.formaPago',
        ]);

        $impresora = $this->obtenerImpresora($cobro->sucursal_id, $cobro->caja_id, 'recibo_cobro');

        if (!$impresora) {
            // Fallback a impresora de tickets
            $impresora = $this->obtenerImpresora($cobro->sucursal_id, $cobro->caja_id, 'ticket_venta');
        }

        if (!$impresora) {
            throw new Exception('No hay impresora configurada para recibos de cobro en esta sucursal/caja');
        }

        $config = ConfiguracionImpresion::where('sucursal_id', $cobro->sucursal_id)->first();

        // Generar HTML usando la vista blade
        $html = view('impresion.recibo-cobro', [
            'cobro' => $cobro,
            'impresora' => $impresora,
            'config' => $config,
        ])->render();

        return [
            'tipo' => 'html',
            'impresora' => $impresora->nombre_sistema,
            'datos' => $html,
            'opciones' => [
                'formato' => $impresora->formato_papel,
                'cortar' => $config?->cortar_papel_automatico ?? true,
            ]
        ];
    }
}
