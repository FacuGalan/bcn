<?php

namespace App\Livewire\Configuracion;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\IntegracionPagoSucursalService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Pantalla de gestión de configuraciones de integraciones de pago por sucursal.
 *
 * NO es SucursalAware: gestiona TODAS las sucursales desde un solo lugar
 * (es un módulo de configuración general del comercio).
 *
 * Permiso requerido: `func.integraciones_pago.administrar`.
 */
#[Lazy]
#[Layout('layouts.app')]
class IntegracionesPago extends Component
{
    // ==================== Modal ====================

    public bool $mostrarModal = false;

    public bool $editMode = false;

    public ?int $configId = null;

    public ?int $integracionPagoId = null;

    public ?int $sucursalId = null;

    // ==================== Campos del formulario ====================

    public string $modo = IntegracionPagoSucursal::MODO_TEST;

    public ?string $access_token_produccion = null;

    public ?string $access_token_test = null;

    public ?string $public_key_produccion = null;

    public ?string $public_key_test = null;

    public ?string $user_id_externo = null;

    public ?string $webhook_secret = null;

    public int $timeout_segundos = 300;

    public bool $activo = true;

    // ==================== Lifecycle ====================

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            abort(403, __('No tiene permiso para administrar integraciones de pago'));
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="0" :columns="3" :rows="4" />
        HTML;
    }

    // ==================== Validación ====================

    protected function rules(): array
    {
        return [
            'modo' => 'required|in:test,produccion',
            'access_token_produccion' => 'nullable|string|max:500',
            'access_token_test' => 'nullable|string|max:500',
            'public_key_produccion' => 'nullable|string|max:255',
            'public_key_test' => 'nullable|string|max:255',
            'user_id_externo' => 'nullable|string|max:100',
            'webhook_secret' => 'nullable|string|max:500',
            'timeout_segundos' => 'required|integer|min:30|max:3600',
            'activo' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'modo.required' => __('Seleccione el modo (test o producción)'),
            'timeout_segundos.required' => __('Ingrese el timeout en segundos'),
            'timeout_segundos.min' => __('El timeout mínimo es 30 segundos'),
            'timeout_segundos.max' => __('El timeout máximo es 3600 segundos'),
        ];
    }

    // ==================== Acciones ====================

    public function abrirConfig(int $integracionPagoId, int $sucursalId): void
    {
        $this->resetForm();
        $this->resetValidation();

        $this->integracionPagoId = $integracionPagoId;
        $this->sucursalId = $sucursalId;

        $existente = IntegracionPagoSucursal::where('integracion_pago_id', $integracionPagoId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        if ($existente) {
            $this->editMode = true;
            $this->configId = $existente->id;
            $this->modo = $existente->modo;
            $this->access_token_produccion = $existente->access_token_produccion;
            $this->access_token_test = $existente->access_token_test;
            $this->public_key_produccion = $existente->public_key_produccion;
            $this->public_key_test = $existente->public_key_test;
            $this->user_id_externo = $existente->user_id_externo;
            $this->webhook_secret = $existente->webhook_secret;
            $this->timeout_segundos = $existente->timeout_segundos;
            $this->activo = $existente->activo;
        } else {
            $this->editMode = false;
        }

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        $this->validate();

        // Validación condicional: el access_token del modo activo es obligatorio.
        if ($this->modo === IntegracionPagoSucursal::MODO_PRODUCCION && empty($this->access_token_produccion)) {
            $this->addError('access_token_produccion', __('El Access Token de producción es obligatorio cuando el modo es producción'));

            return;
        }

        if ($this->modo === IntegracionPagoSucursal::MODO_TEST && empty($this->access_token_test)) {
            $this->addError('access_token_test', __('El Access Token de test es obligatorio cuando el modo es test'));

            return;
        }

        $data = [
            'integracion_pago_id' => $this->integracionPagoId,
            'sucursal_id' => $this->sucursalId,
            'modo' => $this->modo,
            'access_token_produccion' => $this->access_token_produccion,
            'access_token_test' => $this->access_token_test,
            'public_key_produccion' => $this->public_key_produccion,
            'public_key_test' => $this->public_key_test,
            'user_id_externo' => $this->user_id_externo,
            'webhook_secret' => $this->webhook_secret,
            'timeout_segundos' => $this->timeout_segundos,
            'activo' => $this->activo,
        ];

        try {
            if ($this->editMode && $this->configId) {
                $config = IntegracionPagoSucursal::findOrFail($this->configId);
                IntegracionPagoSucursalService::actualizar($config, $data);
                $this->dispatch('notify', message: __('Configuración actualizada correctamente'), type: 'success');
            } else {
                IntegracionPagoSucursalService::crear($data);
                $this->dispatch('notify', message: __('Configuración creada correctamente'), type: 'success');
            }

            $this->cerrarModal();
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: __('Error al guardar: ').$e->getMessage(), type: 'error');
        }
    }

    public function eliminar(int $configId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::findOrFail($configId);
            IntegracionPagoSucursalService::eliminar($config);

            $this->dispatch('notify', message: __('Configuración eliminada correctamente'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: __('Error al eliminar: ').$e->getMessage(), type: 'error');
        }
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->editMode = false;
        $this->configId = null;
        $this->integracionPagoId = null;
        $this->sucursalId = null;
        $this->modo = IntegracionPagoSucursal::MODO_TEST;
        $this->access_token_produccion = null;
        $this->access_token_test = null;
        $this->public_key_produccion = null;
        $this->public_key_test = null;
        $this->user_id_externo = null;
        $this->webhook_secret = null;
        $this->timeout_segundos = 300;
        $this->activo = true;
    }

    // ==================== Render ====================

    public function render()
    {
        $integraciones = IntegracionPago::activas()->orderBy('orden')->get();
        $sucursales = Sucursal::orderBy('nombre')->get();

        // Mapa de configs existentes indexado por "integracion_id:sucursal_id"
        $configs = IntegracionPagoSucursal::all()
            ->keyBy(fn ($c) => $c->integracion_pago_id.':'.$c->sucursal_id);

        return view('livewire.configuracion.integraciones-pago', [
            'integraciones' => $integraciones,
            'sucursales' => $sucursales,
            'configs' => $configs,
        ]);
    }
}
