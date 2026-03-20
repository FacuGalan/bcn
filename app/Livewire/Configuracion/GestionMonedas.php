<?php

namespace App\Livewire\Configuracion;

use App\Models\Moneda;
use App\Models\TipoCambio;
use App\Services\CatalogoCache;
use Livewire\Component;
use Livewire\WithPagination;

class GestionMonedas extends Component
{
    use WithPagination;

    // ==================== Monedas ====================
    public ?int $monedaEditId = null;

    public string $monedaCodigo = '';

    public string $monedaNombre = '';

    public string $monedaSimbolo = '';

    public int $monedaDecimales = 2;

    public bool $monedaActivo = true;

    public bool $monedaEsPrincipal = false;

    public int $monedaOrden = 0;

    public bool $mostrarFormMoneda = false;

    // ==================== Tipos de Cambio - Filtros ====================
    public $filtroMonedaOrigen = '';

    public $filtroMonedaDestino = '';

    // ==================== Tipos de Cambio - Modal ====================
    public bool $mostrarModalTC = false;

    public bool $modoEdicionTC = false;

    public ?int $tipoCambioId = null;

    // ==================== Tipos de Cambio - Campos ====================
    public $moneda_origen_id = '';

    public $moneda_destino_id = '';

    public $tasa_compra = '';

    public $tasa_venta = '';

    public $fecha = '';

    // ==================== Reglas Tipo de Cambio ====================
    protected function rules(): array
    {
        return [
            'moneda_origen_id' => 'required|integer',
            'moneda_destino_id' => 'required|integer|different:moneda_origen_id',
            'tasa_compra' => 'required|numeric|min:0.000001',
            'tasa_venta' => 'required|numeric|min:0.000001',
            'fecha' => 'required|date',
        ];
    }

    protected function messages(): array
    {
        return [
            'moneda_origen_id.required' => __('Seleccione la moneda de origen'),
            'moneda_destino_id.required' => __('Seleccione la moneda de destino'),
            'moneda_destino_id.different' => __('La moneda de destino debe ser diferente a la de origen'),
            'tasa_compra.required' => __('Ingrese la tasa de compra'),
            'tasa_compra.min' => __('La tasa de compra debe ser mayor a 0'),
            'tasa_venta.required' => __('Ingrese la tasa de venta'),
            'tasa_venta.min' => __('La tasa de venta debe ser mayor a 0'),
            'fecha.required' => __('Ingrese la fecha'),
            'fecha.date' => __('La fecha no es válida'),
        ];
    }

    // ==================== Monedas - Acciones ====================

    public function nuevaMoneda(): void
    {
        $this->resetFormMoneda();
        $this->monedaOrden = Moneda::max('orden') + 1;
        $this->mostrarFormMoneda = true;
    }

    public function editMoneda(int $id): void
    {
        $moneda = Moneda::findOrFail($id);
        $this->monedaEditId = $moneda->id;
        $this->monedaCodigo = $moneda->codigo;
        $this->monedaNombre = $moneda->nombre;
        $this->monedaSimbolo = $moneda->simbolo;
        $this->monedaDecimales = $moneda->decimales;
        $this->monedaActivo = $moneda->activo;
        $this->monedaEsPrincipal = $moneda->es_principal;
        $this->monedaOrden = $moneda->orden;
        $this->mostrarFormMoneda = true;
    }

    public function guardarMoneda(): void
    {
        $this->validate([
            'monedaCodigo' => 'required|string|max:3|min:3',
            'monedaNombre' => 'required|string|max:50',
            'monedaSimbolo' => 'required|string|max:5',
            'monedaDecimales' => 'required|integer|min:0|max:4',
            'monedaOrden' => 'required|integer|min:0',
        ], [
            'monedaCodigo.required' => __('El código es obligatorio'),
            'monedaCodigo.min' => __('El código debe tener 3 caracteres'),
            'monedaCodigo.max' => __('El código debe tener 3 caracteres'),
            'monedaNombre.required' => __('El nombre es obligatorio'),
            'monedaSimbolo.required' => __('El símbolo es obligatorio'),
        ]);

        try {
            $data = [
                'codigo' => strtoupper($this->monedaCodigo),
                'nombre' => $this->monedaNombre,
                'simbolo' => $this->monedaSimbolo,
                'decimales' => $this->monedaDecimales,
                'activo' => $this->monedaActivo,
                'orden' => $this->monedaOrden,
            ];

            if ($this->monedaEsPrincipal) {
                Moneda::where('es_principal', true)->update(['es_principal' => false]);
                $data['es_principal'] = true;
            }

            if ($this->monedaEditId) {
                $moneda = Moneda::findOrFail($this->monedaEditId);
                $moneda->update($data);
                $message = __('Moneda actualizada correctamente');
            } else {
                if ($this->monedaEsPrincipal) {
                    $data['es_principal'] = true;
                }
                Moneda::create($data);
                $message = __('Moneda creada correctamente');
            }

            $this->dispatch('notify', message: $message, type: 'success');
            $this->mostrarFormMoneda = false;
            $this->resetFormMoneda();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error al guardar moneda: ').$e->getMessage(), type: 'error');
        }
    }

    public function toggleMoneda(int $id): void
    {
        $moneda = Moneda::findOrFail($id);

        if ($moneda->es_principal && $moneda->activo) {
            $this->dispatch('notify', message: __('No se puede desactivar la moneda principal'), type: 'error');

            return;
        }

        $moneda->update(['activo' => ! $moneda->activo]);
        $this->dispatch('notify', message: $moneda->activo ? __('Moneda activada') : __('Moneda desactivada'), type: 'success');
    }

    public function marcarPrincipal(int $id): void
    {
        $moneda = Moneda::findOrFail($id);

        if (! $moneda->activo) {
            $this->dispatch('notify', message: __('Active la moneda primero'), type: 'error');

            return;
        }

        Moneda::where('es_principal', true)->update(['es_principal' => false]);
        $moneda->update(['es_principal' => true, 'activo' => true]);
        $this->dispatch('notify', message: __('Moneda principal actualizada'), type: 'success');
    }

    public function cancelarFormMoneda(): void
    {
        $this->mostrarFormMoneda = false;
        $this->resetFormMoneda();
    }

    private function resetFormMoneda(): void
    {
        $this->monedaEditId = null;
        $this->monedaCodigo = '';
        $this->monedaNombre = '';
        $this->monedaSimbolo = '';
        $this->monedaDecimales = 2;
        $this->monedaActivo = true;
        $this->monedaEsPrincipal = false;
        $this->monedaOrden = 0;
    }

    // ==================== Tipos de Cambio - Acciones ====================

    public function crearTC(): void
    {
        $this->reset(['tipoCambioId', 'moneda_origen_id', 'moneda_destino_id', 'tasa_compra', 'tasa_venta']);
        $this->fecha = now()->format('Y-m-d');
        $this->modoEdicionTC = false;
        $this->mostrarModalTC = true;

        $principal = Moneda::obtenerPrincipal();
        if ($principal) {
            $this->moneda_destino_id = $principal->id;
        }
    }

    public function updatedMonedaOrigenId($value): void
    {
        $this->preCargarUltimaTasa();
    }

    public function updatedMonedaDestinoId($value): void
    {
        $this->preCargarUltimaTasa();
    }

    private function preCargarUltimaTasa(): void
    {
        if ($this->moneda_origen_id && $this->moneda_destino_id && $this->moneda_origen_id != $this->moneda_destino_id) {
            $ultima = TipoCambio::ultimaTasa((int) $this->moneda_origen_id, (int) $this->moneda_destino_id);
            if ($ultima) {
                $this->tasa_compra = $ultima->tasa_compra;
                $this->tasa_venta = $ultima->tasa_venta;
            }
        }
    }

    public function editarTC(int $id): void
    {
        $tc = TipoCambio::findOrFail($id);
        $this->tipoCambioId = $tc->id;
        $this->moneda_origen_id = $tc->moneda_origen_id;
        $this->moneda_destino_id = $tc->moneda_destino_id;
        $this->tasa_compra = $tc->tasa_compra;
        $this->tasa_venta = $tc->tasa_venta;
        $this->fecha = $tc->fecha->format('Y-m-d');
        $this->modoEdicionTC = true;
        $this->mostrarModalTC = true;
    }

    public function guardarTC(): void
    {
        $this->validate();

        $data = [
            'moneda_origen_id' => $this->moneda_origen_id,
            'moneda_destino_id' => $this->moneda_destino_id,
            'tasa_compra' => $this->tasa_compra,
            'tasa_venta' => $this->tasa_venta,
            'fecha' => $this->fecha,
            'usuario_id' => auth()->id(),
        ];

        if ($this->modoEdicionTC && $this->tipoCambioId) {
            TipoCambio::findOrFail($this->tipoCambioId)->update($data);
            $this->dispatch('notify', type: 'success', message: __('Tipo de cambio actualizado'));
        } else {
            TipoCambio::create($data);
            $this->dispatch('notify', type: 'success', message: __('Tipo de cambio registrado'));
        }

        $this->cerrarModalTC();
    }

    public function eliminarTC(int $id): void
    {
        TipoCambio::findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: __('Tipo de cambio eliminado'));
    }

    public function cerrarModalTC(): void
    {
        $this->mostrarModalTC = false;
        $this->resetValidation();
    }

    public function updatingFiltroMonedaOrigen(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroMonedaDestino(): void
    {
        $this->resetPage();
    }

    // ==================== Render ====================

    public function render()
    {
        $query = TipoCambio::with(['monedaOrigen', 'monedaDestino', 'usuario'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($this->filtroMonedaOrigen) {
            $query->where('moneda_origen_id', $this->filtroMonedaOrigen);
        }

        if ($this->filtroMonedaDestino) {
            $query->where('moneda_destino_id', $this->filtroMonedaDestino);
        }

        $tiposCambio = $query->paginate(15);
        $monedas = CatalogoCache::monedas();
        $monedasActivas = $monedas->where('activo', true);

        // Cotizaciones vigentes
        $vigentes = collect();
        $pares = TipoCambio::select('moneda_origen_id', 'moneda_destino_id')
            ->distinct()
            ->get();

        foreach ($pares as $par) {
            $ultima = TipoCambio::ultimaTasa($par->moneda_origen_id, $par->moneda_destino_id);
            if ($ultima) {
                $ultima->load(['monedaOrigen', 'monedaDestino']);
                $vigentes->push($ultima);
            }
        }

        return view('livewire.configuracion.gestion-monedas', [
            'tiposCambio' => $tiposCambio,
            'monedas' => $monedas,
            'monedasActivas' => $monedasActivas,
            'vigentes' => $vigentes,
        ]);
    }
}
