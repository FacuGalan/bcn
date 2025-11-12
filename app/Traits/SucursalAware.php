<?php

namespace App\Traits;

/**
 * Trait SucursalAware
 *
 * Proporciona funcionalidad automática para que los componentes Livewire
 * reaccionen al cambio de sucursal sin recarga de página.
 *
 * USO:
 * ====
 * use App\Traits\SucursalAware;
 *
 * class MiComponente extends Component
 * {
 *     use SucursalAware;
 *
 *     // El componente automáticamente escuchará 'sucursal-changed'
 *     // y cerrará modales, reseteará paginación, etc.
 * }
 *
 * PERSONALIZACIÓN:
 * ================
 * Si necesitas comportamiento personalizado, implementa:
 *
 * protected function onSucursalChanged($sucursalId, $sucursalNombre)
 * {
 *     // Tu lógica personalizada aquí
 * }
 *
 * CARACTERÍSTICAS:
 * ================
 * - Escucha eventos 'sucursal-changed' y 'sucursal-cambiada'
 * - Resetea paginación automáticamente (si usa WithPagination)
 * - Cierra modales comunes automáticamente
 * - Proporciona método sucursalActual() para obtener la sucursal
 *
 * @package App\Traits
 * @version 1.0.0
 */
trait SucursalAware
{
    /**
     * Registra los listeners para cambio de sucursal
     *
     * Se fusiona con los listeners existentes del componente
     *
     * @return array
     */
    protected function getListeners()
    {
        $parentListeners = [];

        // Obtener listeners del padre si existen
        if (method_exists(parent::class, 'getListeners')) {
            $parentListeners = parent::getListeners();
        }

        // Si el componente ya definió $listeners como propiedad
        if (property_exists($this, 'listeners') && is_array($this->listeners)) {
            $parentListeners = array_merge($parentListeners, $this->listeners);
        }

        // Fusionar con los listeners del trait
        return array_merge(
            $parentListeners,
            [
                'sucursal-changed' => 'handleSucursalChanged',
                'sucursal-cambiada' => 'handleSucursalChanged'
            ]
        );
    }

    /**
     * Maneja el cambio de sucursal
     *
     * Comportamiento por defecto:
     * - Resetea paginación
     * - Cierra modales comunes
     * - Llama a onSucursalChanged() si existe
     *
     * Puede ser sobrescrito en el componente si necesitas
     * comportamiento completamente personalizado.
     *
     * @param int|null $sucursalId ID de la nueva sucursal
     * @param string|null $sucursalNombre Nombre de la nueva sucursal
     * @return void
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // 1. Resetear paginación si existe (WithPagination trait)
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }

        // 2. Cerrar modales comunes si existen
        $modalProperties = [
            'showModal',
            'showCrearModal',
            'showEditarModal',
            'showDetalleModal',
            'showEliminarModal',
            'showVerModal',
            'showConfirmarModal',
        ];

        foreach ($modalProperties as $prop) {
            if (property_exists($this, $prop)) {
                $this->$prop = false;
            }
        }

        // 3. Hook personalizado para el componente
        // Si el componente define onSucursalChanged(), se llamará aquí
        if (method_exists($this, 'onSucursalChanged')) {
            $this->onSucursalChanged($sucursalId, $sucursalNombre);
        }
    }

    /**
     * Obtiene el ID de la sucursal actual
     *
     * @return int
     */
    protected function sucursalActual(): int
    {
        return sucursal_activa();
    }

    /**
     * Verifica si el usuario tiene acceso a una sucursal específica
     *
     * @param int $sucursalId
     * @return bool
     */
    protected function tieneAccesoASucursal(int $sucursalId): bool
    {
        return \App\Services\SucursalService::tieneAccesoASucursal($sucursalId);
    }

    /**
     * Obtiene todas las sucursales disponibles para el usuario
     *
     * @return \Illuminate\Support\Collection
     */
    protected function sucursalesDisponibles()
    {
        return \App\Services\SucursalService::getSucursalesDisponibles();
    }

    /**
     * Obtiene el modelo de la sucursal actual
     *
     * @return \App\Models\Sucursal|null
     */
    protected function sucursalActivaModel()
    {
        return \App\Services\SucursalService::getSucursalActivaModel();
    }
}
