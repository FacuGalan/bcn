<?php

namespace App\Livewire\Configuracion;

use App\Models\ConceptoPago;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoSucursal;
use App\Models\IntegracionPago;
use App\Models\Moneda;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\PuntosService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
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

    public $multiplicador_puntos = 1; // multiplicador para cálculo de puntos

    public $factura_fiscal = false; // si genera factura fiscal por defecto

    public $activo = true;

    // Forma de pago mixta
    public $es_mixta = false;

    public array $conceptos_permitidos = []; // IDs de conceptos permitidos para mixtas

    // Integraciones de pago (N:M) — solo FP simple cuyo concepto permite_integracion.
    // Cada fila: ['integracion_pago_id'=>, 'modo_default'=>, 'es_principal'=>bool]
    // Cada integración usa UN modo de cobro (modo_default). El pivote conserva
    // `modos_permitidos` por compatibilidad; se persiste como [modo_default].
    public array $integraciones_fp = [];

    // Cuenta empresa y moneda
    public $cuenta_empresa_id = null;

    public $moneda_id = null;

    // Sucursales seleccionadas
    public array $sucursales_seleccionadas = [];

    // Propiedades para gestión de cuotas
    public $gestionandoCuotas = false;

    public $formaPagoCuotasId = null;

    public $cuotas = [];

    public $nuevaCuota = [
        'cantidad_cuotas' => 1,
        'recargo_porcentaje' => 0,
        'descripcion' => '',
    ];

    // Modal
    public $mostrarModal = false;

    public $modoEdicion = false;

    // Modal de ordenar
    public $mostrarModalOrden = false;

    public array $formasPagoOrden = [];

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
            $rules['multiplicador_puntos'] = 'nullable|numeric|min:0|max:99.99';
            $rules['factura_fiscal'] = 'boolean';

            // Integraciones de pago: solo si el concepto las permite.
            if ($this->conceptoPermiteIntegracion()) {
                $rules['integraciones_fp'] = 'array';
                $rules['integraciones_fp.*.integracion_pago_id'] = 'required|exists:pymes_tenant.integraciones_pago,id';
                $rules['integraciones_fp.*.modo_default'] = 'required|string';
            }
        }

        return $rules;
    }

    protected function messages()
    {
        return [
            'nombre.required' => __('El nombre es obligatorio'),
            'nombre.max' => __('El nombre no puede exceder 100 caracteres'),
            'concepto_pago_id.required' => __('Debe seleccionar un concepto de pago'),
            'concepto_pago_id.exists' => __('El concepto seleccionado no es válido'),
            'ajuste_porcentaje.min' => __('El ajuste no puede ser menor a -100%'),
            'ajuste_porcentaje.max' => __('El ajuste no puede exceder 100%'),
            'conceptos_permitidos.required' => __('Debe seleccionar los conceptos permitidos'),
            'conceptos_permitidos.min' => __('Una forma de pago mixta debe permitir al menos 2 conceptos'),
            'integraciones_fp.*.integracion_pago_id.required' => __('Debe seleccionar una integración'),
            'integraciones_fp.*.integracion_pago_id.exists' => __('La integración seleccionada no es válida'),
            'integraciones_fp.*.modo_default.required' => __('Debe seleccionar un modo de cobro'),
        ];
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="1" :columns="5" :rows="6" />
        HTML;
    }

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
            $this->multiplicador_puntos = 1;
            $this->factura_fiscal = false;
            $this->integraciones_fp = []; // las mixtas no usan integraciones
        } else {
            // No es mixta: limpiar conceptos permitidos
            $this->conceptos_permitidos = [];
        }
    }

    /**
     * Si cambia el concepto y el nuevo no permite integración, limpiar las filas.
     */
    public function updatedConceptoPagoId()
    {
        if (! $this->conceptoPermiteIntegracion()) {
            $this->integraciones_fp = [];
        }
    }

    /**
     * Al elegir una integración en una fila, precargar modo default y modos
     * permitidos con los modos disponibles de esa integración.
     */
    public function updatedIntegracionesFp($value, $key)
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);

        if ($field !== 'integracion_pago_id') {
            return;
        }

        $integracion = $this->integracionesActivas()->firstWhere('id', (int) $value);
        $modos = $integracion?->modos_disponibles ?? [];

        // Preseleccionar el primer modo disponible (el usuario puede cambiarlo).
        $this->integraciones_fp[$index]['modo_default'] = $modos[0] ?? null;
    }

    public function agregarIntegracion()
    {
        $this->integraciones_fp[] = [
            'integracion_pago_id' => null,
            'modo_default' => null,
            'es_principal' => count($this->integraciones_fp) === 0,
        ];
    }

    public function quitarIntegracion($index)
    {
        unset($this->integraciones_fp[$index]);
        $this->integraciones_fp = array_values($this->integraciones_fp);

        // Garantizar que siempre haya una principal si quedan filas.
        if (! empty($this->integraciones_fp)
            && ! collect($this->integraciones_fp)->contains(fn ($r) => ! empty($r['es_principal']))) {
            $this->integraciones_fp[0]['es_principal'] = true;
        }
    }

    public function marcarPrincipal($index)
    {
        foreach (array_keys($this->integraciones_fp) as $i) {
            $this->integraciones_fp[$i]['es_principal'] = ($i == $index);
        }
    }

    /**
     * El concepto simple seleccionado permite tener integración de pago.
     */
    public function conceptoPermiteIntegracion(): bool
    {
        if ($this->es_mixta || ! $this->concepto_pago_id) {
            return false;
        }

        return (bool) optional(ConceptoPago::find($this->concepto_pago_id))->permite_integracion;
    }

    /**
     * Integraciones de pago activas del comercio (catálogo).
     */
    private function integracionesActivas()
    {
        return IntegracionPago::activas()->orderBy('orden')->get();
    }

    /**
     * Etiqueta legible para un modo de integración.
     */
    public function labelModo(string $modo): string
    {
        return match ($modo) {
            'qr_dinamico' => __('QR dinámico'),
            'qr_estatico' => __('QR estático'),
            default => $modo,
        };
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
        $formaPago = FormaPago::with(['sucursales', 'conceptoPago', 'conceptosPermitidos', 'integraciones'])->findOrFail($id);

        $this->formaPagoId = $formaPago->id;
        $this->nombre = $formaPago->nombre;
        $this->descripcion = $formaPago->descripcion;
        $this->activo = $formaPago->activo;
        $this->es_mixta = $formaPago->es_mixta ?? false;

        // Cuenta empresa y moneda
        $this->cuenta_empresa_id = $formaPago->cuenta_empresa_id;
        $this->moneda_id = $formaPago->moneda_id;

        if ($this->es_mixta) {
            // Forma de pago mixta
            $this->concepto_pago_id = null;
            $this->permite_cuotas = false;
            $this->ajuste_porcentaje = 0;
            $this->multiplicador_puntos = 1;
            $this->factura_fiscal = false;
            $this->conceptos_permitidos = $formaPago->conceptosPermitidos->pluck('id')->toArray();
            $this->integraciones_fp = [];
        } else {
            // Forma de pago simple
            $this->concepto_pago_id = $formaPago->concepto_pago_id;
            $this->permite_cuotas = $formaPago->permite_cuotas;
            $this->ajuste_porcentaje = $formaPago->ajuste_porcentaje ?? 0;
            $this->multiplicador_puntos = $formaPago->multiplicador_puntos ?? 1;
            $this->factura_fiscal = $formaPago->factura_fiscal ?? false;
            $this->conceptos_permitidos = [];

            // Cargar integraciones de pago asociadas (pivote).
            $this->integraciones_fp = $formaPago->integraciones->map(fn ($int) => [
                'integracion_pago_id' => $int->id,
                'modo_default' => $int->pivot->modo_default,
                'es_principal' => (bool) $int->pivot->es_principal,
            ])->toArray();
        }

        // Cargar sucursales donde está activa
        $this->sucursales_seleccionadas = $formaPago->sucursales()
            ->wherePivot('activo', true)
            ->pluck('sucursal_id')
            ->toArray();

        // Si no tiene configuración de sucursales, seleccionar todas por defecto
        if (empty($this->sucursales_seleccionadas) && ! FormaPagoSucursal::where('forma_pago_id', $id)->exists()) {
            $this->sucursales_seleccionadas = Sucursal::pluck('id')->toArray();
        }

        $this->modoEdicion = true;
        $this->mostrarModal = true;
    }

    public function guardar()
    {
        $this->validate();

        // Validaciones de integridad de integraciones (no duplicar, default ∈ permitidos).
        if (! $this->es_mixta && $this->conceptoPermiteIntegracion()) {
            $vistos = [];
            foreach ($this->integraciones_fp as $i => $row) {
                $intId = $row['integracion_pago_id'] ?? null;
                if ($intId && in_array($intId, $vistos, true)) {
                    $this->addError("integraciones_fp.$i.integracion_pago_id", __('Esta integración ya está agregada'));

                    return;
                }
                if ($intId) {
                    $vistos[] = $intId;
                }
            }
        }

        try {
            // Obtener el concepto para el campo legacy 'concepto'
            $conceptoLegacy = 'otro';
            if (! $this->es_mixta && $this->concepto_pago_id) {
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
                'multiplicador_puntos' => $this->es_mixta ? 1 : ($this->multiplicador_puntos ?: 1),
                'factura_fiscal' => $this->es_mixta ? false : $this->factura_fiscal,
                'activo' => $this->activo,
                'cuenta_empresa_id' => $this->cuenta_empresa_id ?: null,
                'moneda_id' => $this->moneda_id ?: null,
            ];

            if ($this->modoEdicion) {
                $formaPago = FormaPago::findOrFail($this->formaPagoId);
                $formaPago->update($datos);
                $message = __('Forma de pago actualizada exitosamente');
            } else {
                // Asignar orden al final
                $datos['orden'] = (FormaPago::max('orden') ?? 0) + 1;
                $formaPago = FormaPago::create($datos);
                $message = __('Forma de pago creada exitosamente');
            }

            // Sincronizar conceptos permitidos (solo para mixtas)
            if ($this->es_mixta) {
                $formaPago->conceptosPermitidos()->sync($this->conceptos_permitidos);
            } else {
                // Si no es mixta, limpiar conceptos permitidos
                $formaPago->conceptosPermitidos()->detach();
            }

            // Sincronizar integraciones de pago (solo FP simple cuyo concepto las permite)
            if (! $this->es_mixta && $this->conceptoPermiteIntegracion()) {
                $syncIntegraciones = [];
                foreach ($this->integraciones_fp as $row) {
                    if (empty($row['integracion_pago_id'])) {
                        continue;
                    }
                    $modo = $row['modo_default'] ?? null;
                    $syncIntegraciones[$row['integracion_pago_id']] = [
                        'modo_default' => $modo,
                        // Un solo modo por integración: el pivote conserva la columna
                        // por compatibilidad, espejando el modo elegido.
                        'modos_permitidos' => json_encode($modo ? [$modo] : []),
                        'es_principal' => ! empty($row['es_principal']),
                    ];
                }
                $formaPago->integraciones()->sync($syncIntegraciones);
            } else {
                // FP mixta o concepto que no permite integración: limpiar.
                $formaPago->integraciones()->detach();
            }

            // Sincronizar sucursales
            $todasSucursales = Sucursal::pluck('id')->toArray();
            $syncData = [];
            foreach ($todasSucursales as $sucursalId) {
                $syncData[$sucursalId] = [
                    'activo' => in_array($sucursalId, $this->sucursales_seleccionadas),
                ];
            }
            $formaPago->sucursales()->sync($syncData);

            CatalogoCache::clear();
            $this->dispatch('notify', message: $message, type: 'success');
            $this->cerrarModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al guardar: ').$e->getMessage(), type: 'error');
        }
    }

    public function eliminar($id)
    {
        try {
            $formaPago = FormaPago::findOrFail($id);
            $formaPago->delete();
            $this->dispatch('notify', message: __('Forma de pago eliminada exitosamente'), type: 'success');
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('No se puede eliminar: ').$e->getMessage(), type: 'error');
        }
    }

    public function toggleStatus($id)
    {
        try {
            $formaPago = FormaPago::findOrFail($id);
            $formaPago->activo = ! $formaPago->activo;
            $formaPago->save();

            $this->dispatch('notify', message: $formaPago->activo ? __('Forma de pago activada') : __('Forma de pago desactivada'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al cambiar estado: ').$e->getMessage(), type: 'error');
        }
    }

    // ==================== Gestión de Cuotas ====================

    public function gestionarCuotas($id)
    {
        $formaPago = FormaPago::with('cuotas')->findOrFail($id);

        if (! $formaPago->permite_cuotas) {
            session()->flash('error', __('Esta forma de pago no permite cuotas'));

            return;
        }

        $this->formaPagoCuotasId = $id;
        $this->cuotas = $formaPago->cuotas->map(function ($cuota) {
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
            'nuevaCuota.cantidad_cuotas.required' => __('La cantidad de cuotas es obligatoria'),
            'nuevaCuota.cantidad_cuotas.min' => __('Debe ser al menos 1 cuota'),
            'nuevaCuota.cantidad_cuotas.max' => __('No puede exceder 99 cuotas'),
            'nuevaCuota.recargo_porcentaje.required' => __('El recargo es obligatorio'),
            'nuevaCuota.recargo_porcentaje.min' => __('El recargo no puede ser negativo'),
            'nuevaCuota.recargo_porcentaje.max' => __('El recargo no puede exceder 100%'),
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
            $this->dispatch('notify', message: __('Plan de cuotas agregado exitosamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al agregar cuota: ').$e->getMessage(), type: 'error');
        }
    }

    public function eliminarCuota($cuotaId)
    {
        try {
            $cuota = FormaPagoCuota::findOrFail($cuotaId);
            $cuota->delete();

            // Recargar cuotas
            $this->gestionarCuotas($this->formaPagoCuotasId);
            $this->dispatch('notify', message: __('Plan de cuotas eliminado exitosamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al eliminar cuota: ').$e->getMessage(), type: 'error');
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
            'descripcion' => '',
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
        $this->multiplicador_puntos = 1;
        $this->factura_fiscal = false;
        $this->activo = true;
        $this->es_mixta = false;
        $this->conceptos_permitidos = [];
        $this->integraciones_fp = [];
        $this->sucursales_seleccionadas = [];
        $this->cuenta_empresa_id = null;
        $this->moneda_id = null;
        $this->resetValidation();
    }

    // ==================== Ordenar ====================

    public function abrirModalOrden()
    {
        $this->formasPagoOrden = FormaPago::orderBy('orden')->orderBy('id')
            ->get(['id', 'nombre', 'orden'])
            ->toArray();
        $this->mostrarModalOrden = true;
    }

    public function guardarOrden(array $ids)
    {
        try {
            foreach ($ids as $index => $id) {
                FormaPago::where('id', $id)->update(['orden' => $index + 1]);
            }

            CatalogoCache::clear();
            $this->dispatch('notify', message: __('Orden actualizado exitosamente'), type: 'success');
            $this->mostrarModalOrden = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al guardar orden: ').$e->getMessage(), type: 'error');
        }
    }

    public function cerrarModalOrden()
    {
        $this->mostrarModalOrden = false;
        $this->formasPagoOrden = [];
    }

    public function render()
    {
        $query = FormaPago::with(['sucursales' => function ($query) {
            $query->wherePivot('activo', true);
        }, 'conceptoPago', 'conceptosPermitidos']);

        // Aplicar búsqueda
        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('concepto', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('descripcion', 'like', '%'.$this->busqueda.'%');
            });
        }

        // Aplicar filtro de estado
        if ($this->filtroActivo !== 'todos') {
            $query->where('activo', $this->filtroActivo === 'activos');
        }

        $formasPago = $query->orderBy('orden')->orderBy('id')->paginate(10);

        // Obtener todas las sucursales para el modal
        $sucursales = CatalogoCache::sucursalesTodas();

        // Obtener todos los conceptos de pago activos para el modal
        $conceptosPago = ConceptoPago::activos()->ordenados()->get();

        // Obtener cuentas empresa y monedas para el modal
        $cuentasEmpresa = CuentaEmpresa::activas()->orderBy('nombre')->get();
        $monedas = CatalogoCache::monedas();

        return view('livewire.configuracion.gestionar-formas-pago', [
            'formasPago' => $formasPago,
            'sucursales' => $sucursales,
            'conceptosPago' => $conceptosPago,
            'cuentasEmpresa' => $cuentasEmpresa,
            'monedas' => $monedas,
            'puntosActivo' => app(PuntosService::class)->isProgramaActivo(),
            'integracionesDisponibles' => $this->integracionesActivas(),
            'conceptoPermiteIntegracionSel' => $this->conceptoPermiteIntegracion(),
        ]);
    }
}
