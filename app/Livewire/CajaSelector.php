<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Caja;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;

/**
 * Componente Livewire: Selector de Caja (Botón Flotante)
 *
 * Permite al usuario cambiar entre las cajas a las que tiene acceso
 * en la sucursal actual.
 *
 * PATRÓN: Replica exactamente el comportamiento de SucursalSelector
 *
 * RESPONSABILIDADES:
 * - Mostrar botón flotante con la caja activa
 * - Mostrar lista de cajas disponibles en dropdown
 * - Cambiar la caja activa en la sesión
 * - Refrescar automáticamente al cambiar de sucursal
 * - Emitir eventos para que otros componentes se actualicen
 *
 * EVENTOS EMITIDOS:
 * - 'caja-changed' → Cuando cambia la caja activa
 *
 * EVENTOS ESCUCHADOS:
 * - 'sucursal-changed' → Actualiza lista de cajas y selecciona primera disponible
 *
 * OPTIMIZADO: Usa caché del servicio para evitar consultas repetidas
 *
 * FASE 4 - Sistema de Cajas por Usuario
 */
class CajaSelector extends Component
{
    public $cajaActual;
    public $cajasDisponibles;
    public $mostrarDropdown = false;

    /**
     * Escuchar eventos de cambio de sucursal
     */
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged'
    ];

    public function mount()
    {
        $this->cargarCajas();
    }

    /**
     * Maneja el cambio de sucursal
     * Recarga la lista de cajas de la nueva sucursal y selecciona la primera
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        \Log::info('CajaSelector::handleSucursalChanged llamado', [
            'sucursalId' => $sucursalId,
            'sucursalNombre' => $sucursalNombre,
            'session_sucursal_id' => session('sucursal_id')
        ]);

        // Cerrar dropdown si está abierto
        $this->mostrarDropdown = false;

        // Limpiar caché de cajas
        CajaService::clearCache();

        // Establecer primera caja disponible de la nueva sucursal
        $primeraCajaId = CajaService::establecerPrimeraCajaDisponible();
        \Log::info('Primera caja establecida', ['primeraCajaId' => $primeraCajaId]);

        // Recargar cajas de la nueva sucursal
        $this->cargarCajas();
        \Log::info('Cajas recargadas', [
            'count' => $this->cajasDisponibles->count(),
            'cajaActual' => $this->cajaActual ? $this->cajaActual->nombre : 'null'
        ]);

        // Notificar a otros componentes sobre la caja actualizada
        if ($this->cajaActual) {
            $this->dispatch('caja-changed',
                cajaId: $this->cajaActual->id,
                cajaNombre: $this->cajaActual->nombre
            );
            \Log::info('Evento caja-changed emitido desde handleSucursalChanged');
        }
    }

    public function cargarCajas()
    {
        // Obtener cajas disponibles usando el servicio (usa caché)
        $this->cajasDisponibles = CajaService::getCajasDisponibles();

        // Obtener caja actual de la sesión
        $cajaActualId = caja_activa();

        if ($cajaActualId) {
            // Buscar en la colección ya cargada (no hace nueva consulta)
            $this->cajaActual = $this->cajasDisponibles->firstWhere('id', $cajaActualId);
        }

        // Si no hay caja en sesión, usar la primera disponible
        if (!$this->cajaActual && $this->cajasDisponibles->isNotEmpty()) {
            $this->cajaActual = $this->cajasDisponibles->first();
            session(['caja_activa' => $this->cajaActual->id]);
        }
    }

    public function toggleDropdown()
    {
        $this->mostrarDropdown = !$this->mostrarDropdown;
    }

    public function cambiarCaja($cajaId)
    {
        \Log::info('CajaSelector::cambiarCaja llamado', ['cajaId' => $cajaId]);

        // Buscar en la colección ya cargada (no hace nueva consulta)
        $caja = $this->cajasDisponibles->firstWhere('id', $cajaId);

        \Log::info('Caja encontrada', ['caja' => $caja ? $caja->nombre : 'null']);

        if ($caja) {
            // Guardar en sesión
            session(['caja_activa' => $caja->id]);

            // Limpiar caché del servicio de cajas
            CajaService::clearCache();

            // Actualizar caja actual
            $this->cajaActual = $caja;

            // Cerrar dropdown
            $this->mostrarDropdown = false;

            // Emitir evento GLOBAL para que todos los componentes se actualicen
            // Usamos dispatch() sin ->to() para que sea global a toda la página
            $this->dispatch('caja-changed',
                cajaId: $caja->id,
                cajaNombre: $caja->nombre
            );

            // Mostrar notificación
            $this->dispatch('notify',
                message: "Cambiado a caja: {$caja->nombre}",
                type: 'success'
            );

            \Log::info('Evento caja-changed emitido');
        } else {
            \Log::error('No se encontró la caja', ['cajaId' => $cajaId, 'disponibles' => $this->cajasDisponibles->pluck('id')]);
        }
    }

    public function render()
    {
        return view('livewire.caja-selector');
    }
}
