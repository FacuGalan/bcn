<?php

namespace App\Livewire\Compras;

use App\Models\Impuesto;
use App\Models\Proveedor;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Perfil fiscal del proveedor (D24 compras-costos-precios).
 *
 * Componente embebido (NO full-page, NO sucursal-aware: el perfil es global por
 * proveedor). Espejo de Clientes\ClienteImpuestos pero con semántica de AGENTE
 * QUE NOS PERCIBE: gestiona las percepciones habituales del proveedor
 * [{impuesto_id, alicuota}] que el editor de compras precarga como renglones al
 * elegirlo (con monto vacío: el valor exacto sale de la factura física).
 *
 * Persiste en el JSON `proveedores.percepciones_habituales` — no hay tabla
 * propia porque la alícuota es el único dato por impuesto; si el perfil crece
 * (coeficiente CM, vigencias) migrar a tabla espejo de cliente_impuesto_configs.
 * Se abre vía el evento `abrir-impuestos-proveedor` desde la fila del ABM.
 */
class ProveedorImpuestos extends Component
{
    public bool $mostrarModal = false;

    public ?int $proveedorId = null;

    public string $proveedorNombre = '';

    public string $proveedorCuit = '';

    /** Filas editables [{impuesto_id, codigo, nombre, jurisdiccion, alicuota}]. */
    public array $filas = [];

    /** Búsqueda del combobox de alta rápida sobre el catálogo. */
    public string $buscarImpuesto = '';

    protected function rules(): array
    {
        return [
            'filas.*.alicuota' => 'nullable|numeric|min:0|max:100',
        ];
    }

    #[On('abrir-impuestos-proveedor')]
    public function abrir(int $proveedorId): void
    {
        $proveedor = Proveedor::find($proveedorId);

        if (! $proveedor) {
            return;
        }

        $this->proveedorId = $proveedor->id;
        $this->proveedorNombre = $proveedor->nombre;
        $this->proveedorCuit = $proveedor->cuit ?? '';
        $this->buscarImpuesto = '';

        $this->cargarFilas($proveedor);
        $this->mostrarModal = true;
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->reset(['proveedorId', 'proveedorNombre', 'proveedorCuit', 'filas', 'buscarImpuesto']);
        $this->resetErrorBag();
    }

    /**
     * Catálogo disponible para agregar: percepciones activas que el proveedor
     * aún no tiene configuradas, filtradas por la búsqueda.
     */
    public function getImpuestosDisponiblesProperty()
    {
        if ($this->proveedorId === null) {
            return collect();
        }

        $yaConfigurados = collect($this->filas)->pluck('impuesto_id')->all();

        return Impuesto::activos()
            ->whereNotIn('id', $yaConfigurados)
            ->where('naturaleza_default', 'percepcion')
            ->when($this->buscarImpuesto !== '', function ($q) {
                $term = '%'.$this->buscarImpuesto.'%';
                $q->where(fn ($sub) => $sub->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term));
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get();
    }

    /**
     * Agrega un impuesto del catálogo al perfil (en memoria; persiste al guardar).
     */
    public function agregarImpuesto(int $impuestoId): void
    {
        if ($this->proveedorId === null || collect($this->filas)->contains('impuesto_id', $impuestoId)) {
            return;
        }

        $impuesto = Impuesto::activos()
            ->where('naturaleza_default', 'percepcion')
            ->find($impuestoId);

        if (! $impuesto) {
            return;
        }

        $this->filas[] = [
            'impuesto_id' => $impuesto->id,
            'codigo' => $impuesto->codigo,
            'nombre' => $impuesto->nombre,
            'jurisdiccion' => $impuesto->jurisdiccion,
            'alicuota' => '',
        ];

        $this->buscarImpuesto = '';
    }

    /**
     * Quita un impuesto del perfil (en memoria; persiste al guardar).
     */
    public function quitarImpuesto(int $impuestoId): void
    {
        $this->filas = collect($this->filas)
            ->reject(fn ($f) => (int) $f['impuesto_id'] === $impuestoId)
            ->values()
            ->all();
    }

    /**
     * Persiste el perfil completo en proveedores.percepciones_habituales.
     */
    public function guardar(): void
    {
        $this->validate();

        $proveedor = Proveedor::find($this->proveedorId);

        if (! $proveedor) {
            return;
        }

        $proveedor->update([
            'percepciones_habituales' => collect($this->filas)
                ->map(fn ($f) => [
                    'impuesto_id' => (int) $f['impuesto_id'],
                    'alicuota' => (float) str_replace(',', '.', (string) ($f['alicuota'] ?? 0)) ?: null,
                ])
                ->values()
                ->all() ?: null,
        ]);

        $this->dispatch('notify', message: __('Perfil fiscal del proveedor guardado'), type: 'success');
        $this->cerrar();
    }

    /**
     * Arma las filas editables desde el JSON, enriquecidas con el catálogo.
     */
    private function cargarFilas(Proveedor $proveedor): void
    {
        $percepciones = collect((array) $proveedor->percepciones_habituales);

        $impuestos = Impuesto::whereIn('id', $percepciones->pluck('impuesto_id')->filter())
            ->get()
            ->keyBy('id');

        $this->filas = $percepciones
            ->filter(fn ($p) => isset($p['impuesto_id']) && $impuestos->has((int) $p['impuesto_id']))
            ->map(function ($p) use ($impuestos) {
                $impuesto = $impuestos[(int) $p['impuesto_id']];

                return [
                    'impuesto_id' => $impuesto->id,
                    'codigo' => $impuesto->codigo,
                    'nombre' => $impuesto->nombre,
                    'jurisdiccion' => $impuesto->jurisdiccion,
                    'alicuota' => isset($p['alicuota']) && (float) $p['alicuota'] > 0 ? (string) (float) $p['alicuota'] : '',
                ];
            })
            ->sortBy('nombre')
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.compras.proveedor-impuestos', [
            'impuestosDisponibles' => $this->impuestosDisponibles,
        ]);
    }
}
