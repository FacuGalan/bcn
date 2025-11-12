<?php

namespace App\Livewire;

use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Componente Livewire de Selector de Comercio
 *
 * Permite a los usuarios que tienen acceso a múltiples comercios
 * seleccionar con cuál comercio desean trabajar en su sesión actual.
 *
 * Funcionalidades:
 * - Lista todos los comercios a los que tiene acceso el usuario
 * - Permite seleccionar un comercio
 * - Establece el comercio activo en la sesión
 * - Redirecciona al dashboard
 *
 * @package App\Livewire
 * @author BCN Pymes
 * @version 1.0.0
 */
class ComercioSelector extends Component
{
    /**
     * Servicio de gestión de tenants
     *
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * Inicializa el componente
     *
     * @return void
     */
    public function boot(TenantService $tenantService): void
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Selecciona un comercio y lo establece como activo en la sesión
     *
     * @param int $comercioId ID del comercio a seleccionar
     * @return void
     */
    public function selectComercio(int $comercioId): void
    {
        $user = Auth::user();

        // Verificar que el usuario tenga acceso al comercio
        if (!$user->hasAccessToComercio($comercioId)) {
            session()->flash('error', 'No tienes acceso a este comercio.');
            return;
        }

        // Establecer el comercio activo
        if ($this->tenantService->switchComercio($comercioId, $user->id)) {
            session()->flash('success', 'Comercio seleccionado correctamente.');
            $this->redirect(route('dashboard'), navigate: true);
        } else {
            session()->flash('error', 'Error al seleccionar el comercio.');
        }
    }

    /**
     * Renderiza el componente
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $user = Auth::user();
        $comercios = $user->comercios()->get();
        $comercioActual = $this->tenantService->getComercio();

        return view('livewire.comercio-selector', [
            'comercios' => $comercios,
            'comercioActual' => $comercioActual,
        ])->layout('layouts.guest');
    }
}
