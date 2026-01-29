<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Sucursal;
use App\Services\SucursalService;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;

/**
 * Componente Livewire: Selector de Sucursal
 *
 * Permite al usuario cambiar entre las sucursales a las que tiene acceso
 * sin necesidad de re-autenticarse.
 *
 * RESPONSABILIDADES:
 * - Mostrar lista de sucursales disponibles para el usuario
 * - Cambiar la sucursal activa en la sesión
 * - Mantener la página actual y refrescar componentes con nueva sucursal
 *
 * OPTIMIZADO: Usa caché del servicio para evitar consultas repetidas
 *
 * FASE 4 - Sistema Multi-Sucursal
 */
class SucursalSelector extends Component
{
    public $sucursalActual;
    public $sucursalesDisponibles;
    public $mostrarDropdown = false;

    public function mount()
    {
        $this->cargarSucursales();
    }

    public function cargarSucursales()
    {
        // Obtener sucursales disponibles usando el servicio (usa caché)
        $this->sucursalesDisponibles = SucursalService::getSucursalesDisponibles();

        // Obtener sucursal actual de la sesión
        $sucursalActualId = sucursal_activa();

        if ($sucursalActualId) {
            // Buscar en la colección ya cargada (no hace nueva consulta)
            $this->sucursalActual = $this->sucursalesDisponibles->firstWhere('id', $sucursalActualId);
        }

        // Si no hay sucursal en sesión, usar la primera disponible
        if (!$this->sucursalActual && $this->sucursalesDisponibles->isNotEmpty()) {
            $this->sucursalActual = $this->sucursalesDisponibles->first();
            session(['sucursal_id' => $this->sucursalActual->id]);
        }
    }

    public function cambiarSucursal($sucursalId)
    {
        // Buscar en la colección ya cargada (no hace nueva consulta)
        $sucursal = $this->sucursalesDisponibles->firstWhere('id', $sucursalId);

        if ($sucursal) {
            // Guardar en sesión
            session(['sucursal_id' => $sucursal->id]);

            // Limpiar caché del servicio de sucursales
            SucursalService::clearCache();

            // Limpiar caché de cajas y establecer primera caja de la nueva sucursal
            CajaService::clearCache();
            CajaService::establecerPrimeraCajaDisponible();

            // Limpiar caché del menú dinámico
            cache()->forget('menu_parent_items_' . auth()->id() . '_' . session('comercio_activo_id'));

            // Actualizar sucursal actual
            $this->sucursalActual = $sucursal;

            // Cerrar dropdown
            $this->mostrarDropdown = false;

            // Emitir evento GLOBAL para que todos los componentes se actualicen
            // Usamos dispatch() sin ->to() para que sea global a toda la página
            $this->dispatch('sucursal-changed',
                sucursalId: $sucursal->id,
                sucursalNombre: $sucursal->nombre
            );

            // Mostrar notificación
            $this->dispatch('notify',
                message: "Cambiado a sucursal: {$sucursal->nombre}",
                type: 'success'
            );
        }
    }

    public function toggleDropdown()
    {
        $this->mostrarDropdown = !$this->mostrarDropdown;
    }

    public function render()
    {
        return view('livewire.sucursal-selector');
    }
}
