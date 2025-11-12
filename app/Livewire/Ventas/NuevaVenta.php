<?php

namespace App\Livewire\Ventas;

use Livewire\Component;
use App\Models\Caja;

/**
 * Componente Livewire: Nueva Venta
 *
 * Componente sencillo para iniciar una nueva venta.
 * Permite seleccionar la caja antes de comenzar el proceso de venta.
 *
 * CARACTERÍSTICAS:
 * ===============
 * - Selector de caja integrado
 * - Validación de caja seleccionada
 * - Interfaz limpia y simple
 * - Preparado para expandir funcionalidad de venta
 *
 * PERMISOS REQUERIDOS:
 * ===================
 * - ventas.crear: Permiso para crear nuevas ventas
 *
 * @package App\Livewire\Ventas
 * @version 1.0.0
 */
class NuevaVenta extends Component
{
    /**
     * Caja seleccionada para la venta
     * @var int|null
     */
    public $cajaSeleccionada = null;

    /**
     * Mensaje de bienvenida o estado
     * @var string
     */
    public $mensaje = '';

    /**
     * Escuchar eventos
     */
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged',
        'caja-changed' => 'handleCajaChanged'
    ];

    /**
     * Inicialización del componente
     * Redirige directamente al POS completo si hay una caja seleccionada
     */
    public function mount()
    {
        $this->cargarCajaActual();

        // Si hay caja seleccionada, redirigir directamente al POS completo
        if ($this->cajaSeleccionada) {
            return redirect()->route('ventas.index');
        }
    }

    /**
     * Carga la caja actual
     */
    public function cargarCajaActual()
    {
        // Intentar usar la caja activa del usuario
        $this->cajaSeleccionada = caja_activa();

        if ($this->cajaSeleccionada) {
            $caja = Caja::find($this->cajaSeleccionada);
            if ($caja) {
                $this->mensaje = "Caja seleccionada: {$caja->nombre}";
            } else {
                $this->cajaSeleccionada = null;
                $this->mensaje = "Por favor, seleccione una caja para comenzar";
            }
        } else {
            $this->mensaje = "Por favor, seleccione una caja para comenzar";
        }
    }

    /**
     * Cambia la caja activa
     * Este método es llamado desde el CajaSelector anidado
     */
    public function cambiarCaja($cajaId)
    {
        \App\Services\CajaService::establecerCajaActiva($cajaId);
        \App\Services\CajaService::clearCache();

        $caja = \App\Models\Caja::find($cajaId);
        if ($caja) {
            $this->dispatch('caja-changed', cajaId: $caja->id, cajaNombre: $caja->nombre);
        }
    }

    /**
     * Maneja el cambio de sucursal
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        $this->cajaSeleccionada = null;
        $this->mensaje = "Sucursal cambiada. Por favor, seleccione una caja";
    }

    /**
     * Maneja el cambio de caja
     * Redirige automáticamente al POS cuando se selecciona una caja
     */
    public function handleCajaChanged($cajaId, $cajaNombre)
    {
        $this->cajaSeleccionada = $cajaId;
        $this->mensaje = "Caja seleccionada: {$cajaNombre}";

        // Redirigir automáticamente al POS cuando hay una caja seleccionada
        return redirect()->route('ventas.index');
    }

    /**
     * Método para iniciar el proceso de venta
     * Redirige al POS completo después de validar la caja
     */
    public function iniciarVenta()
    {
        if (!$this->cajaSeleccionada) {
            $this->dispatch('toast-error', message: 'Debe seleccionar una caja antes de iniciar una venta');
            return;
        }

        $caja = Caja::find($this->cajaSeleccionada);

        if (!$caja) {
            $this->dispatch('toast-error', message: 'La caja seleccionada no existe');
            return;
        }

        if (!$caja->activo) {
            $this->dispatch('toast-error', message: 'La caja seleccionada no está activa');
            return;
        }

        // Redirigir al POS completo
        return redirect()->route('ventas.index');
    }

    /**
     * Obtiene las cajas disponibles para la sucursal actual
     */
    public function getCajasDisponibles()
    {
        $sucursalId = sucursal_activa();

        if (!$sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        return view('livewire.ventas.nueva-venta', [
            'cajas' => $this->getCajasDisponibles(),
        ]);
    }
}
