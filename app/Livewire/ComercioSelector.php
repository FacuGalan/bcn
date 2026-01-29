<?php

namespace App\Livewire;

use App\Models\Comercio;
use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Componente Livewire de Selector de Comercio
 *
 * Permite a los usuarios que tienen acceso a múltiples comercios
 * seleccionar con cuál comercio desean trabajar en su sesión actual.
 *
 * Para usuarios System Admin, muestra un buscador para buscar
 * entre todos los comercios del sistema.
 *
 * Funcionalidades:
 * - Lista todos los comercios a los que tiene acceso el usuario (normales)
 * - Buscador de comercios por ID o nombre (System Admin)
 * - Permite seleccionar un comercio
 * - Establece el comercio activo en la sesión
 * - Redirecciona al dashboard
 *
 * @package App\Livewire
 * @author BCN Pymes
 * @version 2.0.0
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
     * Término de búsqueda para System Admin
     *
     * @var string
     */
    public string $search = '';

    /**
     * Resultados de búsqueda
     *
     * @var array
     */
    public array $searchResults = [];

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
     * Busca comercios por ID o nombre (solo System Admin)
     *
     * @return void
     */
    public function updatedSearch(): void
    {
        $user = Auth::user();

        if (!$user->isSystemAdmin()) {
            return;
        }

        if (strlen($this->search) < 1) {
            $this->searchResults = [];
            return;
        }

        // Buscar por ID (numérico), nombre o email
        $searchTerm = $this->search;
        $searchNumeric = ltrim($searchTerm, '0'); // Quitar ceros a la izquierda para buscar ID

        $this->searchResults = Comercio::where(function ($query) use ($searchTerm, $searchNumeric) {
                $query->where('nombre', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%');

                // Si es numérico, buscar también por ID exacto
                if (is_numeric($searchNumeric)) {
                    $query->orWhere('id', '=', (int) $searchNumeric);
                }
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get()
            ->toArray();
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

        // Para System Admin, usar setComercio directamente (no tiene relación con comercios)
        // Para usuarios normales, usar switchComercio que valida la relación
        if ($user->isSystemAdmin()) {
            $this->tenantService->setComercio($comercioId);
            $this->redirect(route('dashboard'), navigate: true);
        } else {
            if ($this->tenantService->switchComercio($comercioId, $user->id)) {
                $this->redirect(route('dashboard'), navigate: true);
            } else {
                session()->flash('error', 'Error al seleccionar el comercio.');
            }
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
        $isSystemAdmin = $user->isSystemAdmin();

        // Para usuarios normales, mostrar sus comercios
        // Para System Admin, no mostrar comercios hasta que busque
        $comercios = $isSystemAdmin ? collect() : $user->comercios()->get();

        $comercioActual = $this->tenantService->getComercio();

        return view('livewire.comercio-selector', [
            'comercios' => $comercios,
            'comercioActual' => $comercioActual,
            'isSystemAdmin' => $isSystemAdmin,
        ])->layout('layouts.guest', ['title' => $isSystemAdmin ? 'Administrador de Sistema' : 'Seleccionar Comercio']);
    }
}
