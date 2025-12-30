<?php

namespace App\Livewire\Configuracion;

use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoCuotaSucursal;
use App\Models\FormaPagoSucursal;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para configurar formas de pago por sucursal
 *
 * Permite:
 * - Activar/desactivar formas de pago por sucursal
 * - Definir ajuste porcentual (recargo/descuento) específico por sucursal
 * - Configurar recargos de cuotas específicos por sucursal
 */
#[Layout('layouts.app')]
class FormasPagoSucursal extends Component
{
    // Sucursal seleccionada
    public ?int $sucursal_id = null;

    // Modal de configuración de forma de pago
    public bool $mostrarModalConfig = false;
    public ?int $formaPagoConfigId = null;
    public ?float $ajuste_porcentaje_sucursal = null;
    public bool $usar_ajuste_general = true;
    public bool $factura_fiscal_sucursal = false;
    public bool $usar_factura_fiscal_general = true;

    // Modal de configuración de cuotas
    public bool $mostrarModalCuotas = false;
    public ?int $formaPagoCuotasId = null;
    public array $cuotasConfig = [];

    /**
     * Inicializa el componente
     */
    public function mount(): void
    {
        $firstSucursal = Sucursal::orderBy('nombre')->first();
        if ($firstSucursal) {
            $this->sucursal_id = $firstSucursal->id;
        }
    }

    /**
     * Cuando cambia la sucursal
     */
    public function updatedSucursalId(): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Verifica si una forma de pago está activa en la sucursal actual
     */
    public function estaActiva(int $formaPagoId): bool
    {
        if (!$this->sucursal_id) {
            return false;
        }

        $config = FormaPagoSucursal::where('forma_pago_id', $formaPagoId)
            ->where('sucursal_id', $this->sucursal_id)
            ->first();

        // Si no hay configuración específica, está activa por defecto
        return $config ? $config->activo : true;
    }

    /**
     * Obtiene la configuración de una forma de pago para la sucursal
     */
    public function getConfiguracion(int $formaPagoId): ?FormaPagoSucursal
    {
        if (!$this->sucursal_id) {
            return null;
        }

        return FormaPagoSucursal::where('forma_pago_id', $formaPagoId)
            ->where('sucursal_id', $this->sucursal_id)
            ->first();
    }

    /**
     * Alterna el estado activo/inactivo de una forma de pago
     */
    public function toggleFormaPago(int $formaPagoId): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $config = FormaPagoSucursal::firstOrNew([
            'forma_pago_id' => $formaPagoId,
            'sucursal_id' => $this->sucursal_id,
        ]);

        // Si es nuevo, el default es activo, así que lo ponemos inactivo
        $config->activo = $config->exists ? !$config->activo : false;
        $config->save();

        $estado = $config->activo ? 'activada' : 'desactivada';
        $this->dispatch('notify', message: "Forma de pago {$estado} en esta sucursal", type: 'success');
    }

    /**
     * Abre el modal para configurar ajuste de una forma de pago
     */
    public function configurarAjuste(int $formaPagoId): void
    {
        $formaPago = FormaPago::find($formaPagoId);

        // No permitir configurar ajuste en formas de pago mixtas
        if ($formaPago && $formaPago->es_mixta) {
            $this->dispatch('notify', message: 'Las formas de pago mixtas no tienen ajuste propio. El ajuste se aplica por cada forma de pago usada en el desglose.', type: 'warning');
            return;
        }

        $this->formaPagoConfigId = $formaPagoId;
        $config = $this->getConfiguracion($formaPagoId);

        // Configurar ajuste porcentaje
        if ($config && $config->ajuste_porcentaje !== null) {
            $this->ajuste_porcentaje_sucursal = $config->ajuste_porcentaje;
            $this->usar_ajuste_general = false;
        } else {
            $this->ajuste_porcentaje_sucursal = $formaPago->ajuste_porcentaje ?? 0;
            $this->usar_ajuste_general = true;
        }

        // Configurar factura fiscal
        if ($config && $config->factura_fiscal !== null) {
            $this->factura_fiscal_sucursal = $config->factura_fiscal;
            $this->usar_factura_fiscal_general = false;
        } else {
            $this->factura_fiscal_sucursal = $formaPago->factura_fiscal ?? false;
            $this->usar_factura_fiscal_general = true;
        }

        $this->mostrarModalConfig = true;
    }

    /**
     * Guarda la configuración de ajuste y factura fiscal
     */
    public function guardarAjuste(): void
    {
        if (!$this->sucursal_id || !$this->formaPagoConfigId) {
            return;
        }

        $config = FormaPagoSucursal::firstOrNew([
            'forma_pago_id' => $this->formaPagoConfigId,
            'sucursal_id' => $this->sucursal_id,
        ]);

        // Guardar ajuste porcentaje
        if ($this->usar_ajuste_general) {
            $config->ajuste_porcentaje = null;
        } else {
            $config->ajuste_porcentaje = $this->ajuste_porcentaje_sucursal;
        }

        // Guardar factura fiscal
        if ($this->usar_factura_fiscal_general) {
            $config->factura_fiscal = null;
        } else {
            $config->factura_fiscal = $this->factura_fiscal_sucursal;
        }

        if (!$config->exists) {
            $config->activo = true;
        }

        $config->save();

        $this->cerrarModalConfig();
        $this->dispatch('notify', message: 'Configuración guardada correctamente', type: 'success');
    }

    /**
     * Cierra el modal de configuración
     */
    public function cerrarModalConfig(): void
    {
        $this->mostrarModalConfig = false;
        $this->formaPagoConfigId = null;
        $this->ajuste_porcentaje_sucursal = null;
        $this->usar_ajuste_general = true;
        $this->factura_fiscal_sucursal = false;
        $this->usar_factura_fiscal_general = true;
    }

    /**
     * Abre el modal para configurar cuotas por sucursal
     */
    public function configurarCuotas(int $formaPagoId): void
    {
        $formaPago = FormaPago::with('cuotas')->find($formaPagoId);

        // No permitir configurar cuotas en formas de pago mixtas
        if ($formaPago && $formaPago->es_mixta) {
            $this->dispatch('notify', message: 'Las formas de pago mixtas no tienen cuotas propias. Las cuotas se configuran en las formas de pago individuales.', type: 'warning');
            return;
        }

        if (!$formaPago || !$formaPago->permite_cuotas) {
            $this->dispatch('notify', message: 'Esta forma de pago no permite cuotas', type: 'error');
            return;
        }

        $this->formaPagoCuotasId = $formaPagoId;

        $this->cuotasConfig = [];

        foreach ($formaPago->cuotas as $cuota) {
            $configSucursal = FormaPagoCuotaSucursal::where('forma_pago_cuota_id', $cuota->id)
                ->where('sucursal_id', $this->sucursal_id)
                ->first();

            $this->cuotasConfig[$cuota->id] = [
                'cuota_id' => $cuota->id,
                'cantidad_cuotas' => $cuota->cantidad_cuotas,
                'recargo_general' => $cuota->recargo_porcentaje,
                'descripcion' => $cuota->descripcion,
                'activo' => $configSucursal ? $configSucursal->activo : true,
                'usar_recargo_general' => $configSucursal ? ($configSucursal->recargo_porcentaje === null) : true,
                'recargo_sucursal' => $configSucursal ? ($configSucursal->recargo_porcentaje ?? $cuota->recargo_porcentaje) : $cuota->recargo_porcentaje,
            ];
        }

        $this->mostrarModalCuotas = true;
    }

    /**
     * Guarda la configuración de cuotas
     */
    public function guardarCuotas(): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        foreach ($this->cuotasConfig as $config) {
            $cuotaSucursal = FormaPagoCuotaSucursal::firstOrNew([
                'forma_pago_cuota_id' => $config['cuota_id'],
                'sucursal_id' => $this->sucursal_id,
            ]);

            $cuotaSucursal->activo = $config['activo'];
            $cuotaSucursal->recargo_porcentaje = $config['usar_recargo_general'] ? null : $config['recargo_sucursal'];
            $cuotaSucursal->save();
        }

        $this->cerrarModalCuotas();
        $this->dispatch('notify', message: 'Configuración de cuotas guardada', type: 'success');
    }

    /**
     * Cierra el modal de cuotas
     */
    public function cerrarModalCuotas(): void
    {
        $this->mostrarModalCuotas = false;
        $this->formaPagoCuotasId = null;
        $this->cuotasConfig = [];
    }

    /**
     * Activa todas las formas de pago para la sucursal
     */
    public function activarTodas(): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $formasPago = FormaPago::where('activo', true)->get();

        foreach ($formasPago as $formaPago) {
            $config = FormaPagoSucursal::firstOrNew([
                'forma_pago_id' => $formaPago->id,
                'sucursal_id' => $this->sucursal_id,
            ]);
            $config->activo = true;
            $config->save();
        }

        $this->dispatch('notify', message: 'Todas las formas de pago activadas', type: 'success');
    }

    /**
     * Desactiva todas las formas de pago para la sucursal
     */
    public function desactivarTodas(): void
    {
        if (!$this->sucursal_id) {
            return;
        }

        $formasPago = FormaPago::where('activo', true)->get();

        foreach ($formasPago as $formaPago) {
            $config = FormaPagoSucursal::firstOrNew([
                'forma_pago_id' => $formaPago->id,
                'sucursal_id' => $this->sucursal_id,
            ]);
            $config->activo = false;
            $config->save();
        }

        $this->dispatch('notify', message: 'Todas las formas de pago desactivadas', type: 'success');
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        $sucursales = Sucursal::orderBy('nombre')->get();
        $formasPago = FormaPago::with(['conceptoPago', 'conceptosPermitidos'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // Agregar información de configuración por sucursal a cada forma de pago
        $formasPagoConConfig = $formasPago->map(function ($formaPago) {
            $config = $this->getConfiguracion($formaPago->id);

            return [
                'id' => $formaPago->id,
                'nombre' => $formaPago->nombre,
                'concepto' => $formaPago->concepto,
                'concepto_nombre' => $formaPago->conceptoPago?->nombre,
                'descripcion' => $formaPago->descripcion,
                'permite_cuotas' => $formaPago->permite_cuotas,
                'es_mixta' => $formaPago->es_mixta ?? false,
                'conceptos_permitidos' => $formaPago->es_mixta
                    ? $formaPago->conceptosPermitidos->pluck('nombre')->toArray()
                    : [],
                'ajuste_general' => $formaPago->ajuste_porcentaje,
                'activo_sucursal' => $config ? $config->activo : true,
                'ajuste_sucursal' => $config ? $config->ajuste_porcentaje : null,
                'tiene_ajuste_especifico' => $config && $config->ajuste_porcentaje !== null,
                'ajuste_efectivo' => $config && $config->ajuste_porcentaje !== null
                    ? $config->ajuste_porcentaje
                    : $formaPago->ajuste_porcentaje,
                // Campos de factura fiscal
                'factura_fiscal_general' => $formaPago->factura_fiscal ?? false,
                'factura_fiscal_sucursal' => $config ? $config->factura_fiscal : null,
                'tiene_factura_fiscal_especifica' => $config && $config->factura_fiscal !== null,
                'factura_fiscal_efectivo' => $config && $config->factura_fiscal !== null
                    ? $config->factura_fiscal
                    : ($formaPago->factura_fiscal ?? false),
            ];
        });

        return view('livewire.configuracion.formas-pago-sucursal', [
            'sucursales' => $sucursales,
            'formasPago' => $formasPagoConConfig,
        ]);
    }
}
