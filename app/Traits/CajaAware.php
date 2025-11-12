<?php

namespace App\Traits;

/**
 * Trait CajaAware
 *
 * Proporciona funcionalidad automática para que los componentes Livewire
 * reaccionen al cambio de caja sin recarga de página.
 *
 * USO:
 * ====
 * use App\Traits\CajaAware;
 *
 * class MiComponente extends Component
 * {
 *     use CajaAware;
 *
 *     // El componente automáticamente escuchará 'caja-changed'
 *     // y podrá reaccionar al cambio
 * }
 *
 * PERSONALIZACIÓN:
 * ================
 * Si necesitas comportamiento personalizado, implementa:
 *
 * protected function onCajaChanged($cajaId, $cajaNombre)
 * {
 *     // Tu lógica personalizada aquí
 * }
 *
 * CARACTERÍSTICAS:
 * ================
 * - Escucha evento 'caja-changed'
 * - Resetea paginación automáticamente (si usa WithPagination)
 * - Cierra modales comunes automáticamente
 * - Proporciona método cajaActual() para obtener la caja
 *
 * NOTA IMPORTANTE:
 * ================
 * - Este trait NO es obligatorio para todos los componentes
 * - Solo úsalo en componentes que necesiten reaccionar al cambio de caja
 * - Ejemplos: Ventas (POS), Movimientos de Caja, Compras, Cierre de Caja
 * - Componentes como Stock o Configuración NO necesitan este trait
 *
 * @package App\Traits
 * @version 1.0.0
 */
trait CajaAware
{
    /**
     * Registra los listeners para cambio de caja
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
                'caja-changed' => 'handleCajaChanged'
            ]
        );
    }

    /**
     * Maneja el cambio de caja
     *
     * Comportamiento por defecto:
     * - Resetea paginación
     * - Cierra modales comunes
     * - Llama a onCajaChanged() si existe
     *
     * Puede ser sobrescrito en el componente si necesitas
     * comportamiento completamente personalizado.
     *
     * @param int|null $cajaId ID de la nueva caja
     * @param string|null $cajaNombre Nombre de la nueva caja
     * @return void
     */
    public function handleCajaChanged($cajaId = null, $cajaNombre = null)
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
            'showPosModal', // Específico de Ventas
            'showMovimientoModal', // Específico de Cajas
            'showCierreModal', // Específico de Cierre de Caja
        ];

        foreach ($modalProperties as $prop) {
            if (property_exists($this, $prop)) {
                $this->$prop = false;
            }
        }

        // 3. Hook personalizado para el componente
        // Si el componente define onCajaChanged(), se llamará aquí
        if (method_exists($this, 'onCajaChanged')) {
            $this->onCajaChanged($cajaId, $cajaNombre);
        }
    }

    /**
     * Obtiene el ID de la caja actual
     *
     * @return int|null
     */
    protected function cajaActual(): ?int
    {
        return caja_activa();
    }

    /**
     * Verifica si el usuario tiene acceso a una caja específica
     *
     * @param int $cajaId
     * @return bool
     */
    protected function tieneAccesoACaja(int $cajaId): bool
    {
        return \App\Services\CajaService::tieneAccesoACaja($cajaId);
    }

    /**
     * Obtiene todas las cajas disponibles para el usuario en la sucursal actual
     *
     * @return \Illuminate\Support\Collection
     */
    protected function cajasDisponibles()
    {
        return \App\Services\CajaService::getCajasDisponibles();
    }

    /**
     * Obtiene el modelo de la caja actual
     *
     * @return \App\Models\Caja|null
     */
    protected function cajaActivaModel()
    {
        return \App\Services\CajaService::getCajaActivaModel();
    }
}
