<?php

namespace App\Livewire\Configuracion;

use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoSucursal;
use App\Models\ConceptoPago;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class GestionarFormasPago extends Component
{
    use WithPagination;

    // Propiedades del formulario principal
    public $formaPagoId = null;
    public $nombre = '';
    public $concepto_pago_id = null;
    public $descripcion = '';
    public $permite_cuotas = false;
    public $ajuste_porcentaje = 0; // positivo=recargo, negativo=descuento
    public $factura_fiscal = false; // si genera factura fiscal por defecto
    public $activo = true;

    // Forma de pago mixta
    public $es_mixta = false;
    public array $conceptos_permitidos = []; // IDs de conceptos permitidos para mixtas

    // Sucursales seleccionadas
    public array $sucursales_seleccionadas = [];

    // Propiedades para gestión de cuotas
    public $gestionandoCuotas = false;
    public $formaPagoCuotasId = null;
    public $cuotas = [];
    public $nuevaCuota = [
        'cantidad_cuotas' => 1,
        'recargo_porcentaje' => 0,
        'descripcion' => ''
    ];

    // Modal
    public $mostrarModal = false;
    public $modoEdicion = false;

    // Búsqueda y filtros
    public $busqueda = '';
    public $filtroActivo = 'todos';

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'filtroActivo' => ['except' => 'todos'],
    ];

    protected function rules()
    {
        $rules = [
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'es_mixta' => 'boolean',
        ];

        if ($this->es_mixta) {
            // Para mixtas: debe tener al menos 2 conceptos permitidos
            $rules['conceptos_permitidos'] = 'required|array|min:2';
        } else {
            // Para simples: debe tener un concepto seleccionado
            // Especificar conexión pymes_tenant donde está la tabla conceptos_pago
            $rules['concepto_pago_id'] = 'required|exists:pymes_tenant.conceptos_pago,id';
            $rules['permite_cuotas'] = 'boolean';
            $rules['ajuste_porcentaje'] = 'nullable|numeric|min:-100|max:100';
            $rules['factura_fiscal'] = 'boolean';
        }

        return $rules;
    }

    protected $messages = [
        'nombre.required' => 'El nombre es obligatorio',
        'nombre.max' => 'El nombre no puede exceder 100 caracteres',
        'concepto_pago_id.required' => 'Debe seleccionar un concepto de pago',
        'concepto_pago_id.exists' => 'El concepto seleccionado no es válido',
        'ajuste_porcentaje.min' => 'El ajuste no puede ser menor a -100%',
        'ajuste_porcentaje.max' => 'El ajuste no puede exceder 100%',
        'conceptos_permitidos.required' => 'Debe seleccionar los conceptos permitidos',
        'conceptos_permitidos.min' => 'Una forma de pago mixta debe permitir al menos 2 conceptos',
    ];

    /**
     * Cuando cambia es_mixta, resetear campos relacionados
     */
    public function updatedEsMixta($value)
    {
        if ($value) {
            // Es mixta: limpiar campos que no aplican
            $this->concepto_pago_id = null;
            $this->permite_cuotas = false;
            $this->ajuste_porcentaje = 0;
            $this->factura_fiscal = false;
        } else {
            // No es mixta: limpiar conceptos permitidos
            $this->conceptos_permitidos = [];
        }
    }

    public function crear()
    {
        $this->resetFormulario();
        $this->modoEdicion = false;

        // Seleccionar todas las sucursales por defecto
        $this->sucursales_seleccionadas = Sucursal::pluck('id')->toArray();

        $this->mostrarModal = true;
    }

    public function edit($id)
    {
        $formaPago = FormaPago::with(['sucursales', 'conceptoPago', 'conceptosPermitidos'])->findOrFail($id);

        $this->formaPagoId = $formaPago->id;
        $this->nombre = $formaPago->nombre;
        $this->descripcion = $formaPago->descripcion;
        $this->activo = $formaPago->activo;
        $this->es_mixta = $formaPago->es_mixta ?? false;

        if ($this->es_mixta) {
            // Forma de pago mixta
            $this->concepto_pago_id = null;
            $this->permite_cuotas = false;
            $this->ajuste_porcentaje = 0;
            $this->factura_fiscal = false;
            $this->conceptos_permitidos = $formaPago->conceptosPermitidos->pluck('id')->toArray();
        } else {
            // Forma de pago simple
            $this->concepto_pago_id = $formaPago->concepto_pago_id;
            $this->permite_cuotas = $formaPago->permite_cuotas;
            $this->ajuste_porcentaje = $formaPago->ajuste_porcentaje ?? 0;
            $this->factura_fiscal = $formaPago->factura_fiscal ?? false;
            $this->conceptos_permitidos = [];
        }

        // Cargar sucursales donde está activa
        $this->sucursales_seleccionadas = $formaPago->sucursales()
            ->wherePivot('activo', true)
            ->pluck('sucursal_id')
            ->toArray();

        // Si no tiene configuración de sucursales, seleccionar todas por defecto
        if (empty($this->sucursales_seleccionadas) && !FormaPagoSucursal::where('forma_pago_id', $id)->exists()) {
            $this->sucursales_seleccionadas = Sucursal::pluck('id')->toArray();
        }

        $this->modoEdicion = true;
        $this->mostrarModal = true;
    }

    public function guardar()
    {
        $this->validate();

        try {
            // Obtener el concepto para el campo legacy 'concepto'
            $conceptoLegacy = 'otro';
            if (!$this->es_mixta && $this->concepto_pago_id) {
                $concepto = ConceptoPago::find($this->concepto_pago_id);
                $conceptoLegacy = $concepto?->codigo ?? 'otro';
            }

            $datos = [
                'nombre' => $this->nombre,
                'concepto' => $conceptoLegacy, // Campo legacy
                'concepto_pago_id' => $this->es_mixta ? null : $this->concepto_pago_id,
                'es_mixta' => $this->es_mixta,
                'descripcion' => $this->descripcion,
                'permite_cuotas' => $this->es_mixta ? false : $this->permite_cuotas,
                'ajuste_porcentaje' => $this->es_mixta ? 0 : ($this->ajuste_porcentaje ?: 0),
                'factura_fiscal' => $this->es_mixta ? false : $this->factura_fiscal,
                'activo' => $this->activo,
            ];

            if ($this->modoEdicion) {
                $formaPago = FormaPago::findOrFail($this->formaPagoId);
                $formaPago->update($datos);
                $message = 'Forma de pago actualizada exitosamente';
            } else {
                $formaPago = FormaPago::create($datos);
                $message = 'Forma de pago creada exitosamente';
            }

            // Sincronizar conceptos permitidos (solo para mixtas)
            if ($this->es_mixta) {
                $formaPago->conceptosPermitidos()->sync($this->conceptos_permitidos);
            } else {
                // Si no es mixta, limpiar conceptos permitidos
                $formaPago->conceptosPermitidos()->detach();
            }

            // Sincronizar sucursales
            $todasSucursales = Sucursal::pluck('id')->toArray();
            $syncData = [];
            foreach ($todasSucursales as $sucursalId) {
                $syncData[$sucursalId] = [
                    'activo' => in_array($sucursalId, $this->sucursales_seleccionadas)
                ];
            }
            $formaPago->sucursales()->sync($syncData);

            $this->dispatch('notify', message: $message, type: 'success');
            $this->cerrarModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al guardar: ' . $e->getMessage(), type: 'error');
        }
    }

    public function eliminar($id)
    {
        try {
            $formaPago = FormaPago::findOrFail($id);
            $formaPago->delete();
            $this->dispatch('notify', message: 'Forma de pago eliminada exitosamente', type: 'success');
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'No se puede eliminar: ' . $e->getMessage(), type: 'error');
        }
    }

    public function toggleStatus($id)
    {
        try {
            $formaPago = FormaPago::findOrFail($id);
            $formaPago->activo = !$formaPago->activo;
            $formaPago->save();

            $this->dispatch('notify', message: $formaPago->activo ? 'Forma de pago activada' : 'Forma de pago desactivada', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al cambiar estado: ' . $e->getMessage(), type: 'error');
        }
    }

    // ==================== Gestión de Cuotas ====================

    public function gestionarCuotas($id)
    {
        $formaPago = FormaPago::with('cuotas')->findOrFail($id);

        if (!$formaPago->permite_cuotas) {
            session()->flash('error', 'Esta forma de pago no permite cuotas');
            return;
        }

        $this->formaPagoCuotasId = $id;
        $this->cuotas = $formaPago->cuotas->map(function($cuota) {
            return [
                'id' => $cuota->id,
                'cantidad_cuotas' => $cuota->cantidad_cuotas,
                'recargo_porcentaje' => $cuota->recargo_porcentaje,
                'descripcion' => $cuota->descripcion,
            ];
        })->toArray();

        $this->resetNuevaCuota();
        $this->gestionandoCuotas = true;
    }

    public function agregarCuota()
    {
        $this->validate([
            'nuevaCuota.cantidad_cuotas' => 'required|integer|min:1|max:99',
            'nuevaCuota.recargo_porcentaje' => 'required|numeric|min:0|max:100',
            'nuevaCuota.descripcion' => 'nullable|string|max:200',
        ], [
            'nuevaCuota.cantidad_cuotas.required' => 'La cantidad de cuotas es obligatoria',
            'nuevaCuota.cantidad_cuotas.min' => 'Debe ser al menos 1 cuota',
            'nuevaCuota.cantidad_cuotas.max' => 'No puede exceder 99 cuotas',
            'nuevaCuota.recargo_porcentaje.required' => 'El recargo es obligatorio',
            'nuevaCuota.recargo_porcentaje.min' => 'El recargo no puede ser negativo',
            'nuevaCuota.recargo_porcentaje.max' => 'El recargo no puede exceder 100%',
        ]);

        try {
            FormaPagoCuota::create([
                'forma_pago_id' => $this->formaPagoCuotasId,
                'cantidad_cuotas' => $this->nuevaCuota['cantidad_cuotas'],
                'recargo_porcentaje' => $this->nuevaCuota['recargo_porcentaje'],
                'descripcion' => $this->nuevaCuota['descripcion'] ?: null,
            ]);

            // Recargar cuotas
            $this->gestionarCuotas($this->formaPagoCuotasId);
            $this->dispatch('notify', message: 'Plan de cuotas agregado exitosamente', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al agregar cuota: ' . $e->getMessage(), type: 'error');
        }
    }

    public function eliminarCuota($cuotaId)
    {
        try {
            $cuota = FormaPagoCuota::findOrFail($cuotaId);
            $cuota->delete();

            // Recargar cuotas
            $this->gestionarCuotas($this->formaPagoCuotasId);
            $this->dispatch('notify', message: 'Plan de cuotas eliminado exitosamente', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error al eliminar cuota: ' . $e->getMessage(), type: 'error');
        }
    }

    public function cerrarGestionCuotas()
    {
        $this->gestionandoCuotas = false;
        $this->formaPagoCuotasId = null;
        $this->cuotas = [];
        $this->resetNuevaCuota();
    }

    private function resetNuevaCuota()
    {
        $this->nuevaCuota = [
            'cantidad_cuotas' => 1,
            'recargo_porcentaje' => 0,
            'descripcion' => ''
        ];
    }

    // ==================== Helpers ====================

    public function cerrarModal()
    {
        $this->mostrarModal = false;
        $this->resetFormulario();
    }

    private function resetFormulario()
    {
        $this->formaPagoId = null;
        $this->nombre = '';
        $this->concepto_pago_id = null;
        $this->descripcion = '';
        $this->permite_cuotas = false;
        $this->ajuste_porcentaje = 0;
        $this->factura_fiscal = false;
        $this->activo = true;
        $this->es_mixta = false;
        $this->conceptos_permitidos = [];
        $this->sucursales_seleccionadas = [];
        $this->resetValidation();
    }

    public function render()
    {
        $query = FormaPago::with(['sucursales' => function($query) {
            $query->wherePivot('activo', true);
        }, 'conceptoPago', 'conceptosPermitidos']);

        // Aplicar búsqueda
        if ($this->busqueda) {
            $query->where(function($q) {
                $q->where('nombre', 'like', '%' . $this->busqueda . '%')
                  ->orWhere('concepto', 'like', '%' . $this->busqueda . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->busqueda . '%');
            });
        }

        // Aplicar filtro de estado
        if ($this->filtroActivo !== 'todos') {
            $query->where('activo', $this->filtroActivo === 'activos');
        }

        $formasPago = $query->orderBy('nombre')->paginate(10);

        // Obtener todas las sucursales para el modal
        $sucursales = Sucursal::orderBy('nombre')->get();

        // Obtener todos los conceptos de pago activos para el modal
        $conceptosPago = ConceptoPago::activos()->ordenados()->get();

        return view('livewire.configuracion.gestionar-formas-pago', [
            'formasPago' => $formasPago,
            'sucursales' => $sucursales,
            'conceptosPago' => $conceptosPago,
        ]);
    }
}
