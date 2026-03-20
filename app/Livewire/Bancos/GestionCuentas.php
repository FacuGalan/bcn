<?php

namespace App\Livewire\Bancos;

use App\Models\CuentaEmpresa;
use App\Models\Moneda;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Traits\SucursalAware;

#[Layout('layouts.app')]
class GestionCuentas extends Component
{
    use WithPagination, SucursalAware;

    // Filtros
    public string $search = '';
    public string $filtroTipo = '';
    public string $filtroActivo = '1';

    // Modal
    public bool $showModal = false;
    public ?int $cuentaId = null;
    public string $nombre = '';
    public string $tipo = 'banco';
    public ?string $subtipo = null;
    public ?string $banco = null;
    public ?string $numero_cuenta = null;
    public ?string $cbu = null;
    public ?string $alias = null;
    public ?string $titular = null;
    public ?int $moneda_id = null;
    public ?string $color = null;
    public array $sucursales_seleccionadas = [];

    // Confirmación eliminar
    public bool $showConfirmDelete = false;
    public ?int $deleteId = null;

    protected function rules()
    {
        return [
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:banco,billetera_digital',
            'subtipo' => 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'numero_cuenta' => 'nullable|string|max:50',
            'cbu' => 'nullable|string|max:22',
            'alias' => 'nullable|string|max:50',
            'titular' => 'nullable|string|max:191',
            'moneda_id' => 'required|exists:pymes_tenant.monedas,id',
            'color' => 'nullable|string|max:7',
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo()
    {
        $this->resetPage();
    }

    public function updatedFiltroActivo()
    {
        $this->resetPage();
    }

    public function crear()
    {
        $this->reset(['cuentaId', 'nombre', 'tipo', 'subtipo', 'banco', 'numero_cuenta', 'cbu', 'alias', 'titular', 'moneda_id', 'color', 'sucursales_seleccionadas']);
        $this->tipo = 'banco';
        $monedaPrincipal = Moneda::obtenerPrincipal();
        $this->moneda_id = $monedaPrincipal?->id;
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        $cuenta = CuentaEmpresa::findOrFail($id);
        $this->cuentaId = $cuenta->id;
        $this->nombre = $cuenta->nombre;
        $this->tipo = $cuenta->tipo;
        $this->subtipo = $cuenta->subtipo;
        $this->banco = $cuenta->banco;
        $this->numero_cuenta = $cuenta->numero_cuenta;
        $this->cbu = $cuenta->cbu;
        $this->alias = $cuenta->alias;
        $this->titular = $cuenta->titular;
        $this->moneda_id = $cuenta->moneda_id;
        $this->color = $cuenta->color;
        $this->sucursales_seleccionadas = $cuenta->sucursales()->pluck('sucursal_id')->toArray();
        $this->showModal = true;
    }

    public function guardar()
    {
        $this->validate();

        $data = [
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'subtipo' => $this->subtipo,
            'banco' => $this->tipo === 'banco' ? $this->banco : null,
            'numero_cuenta' => $this->tipo === 'banco' ? $this->numero_cuenta : null,
            'cbu' => $this->tipo === 'banco' ? $this->cbu : null,
            'alias' => $this->alias,
            'titular' => $this->titular,
            'moneda_id' => $this->moneda_id,
            'color' => $this->color,
        ];

        if ($this->cuentaId) {
            $cuenta = CuentaEmpresa::findOrFail($this->cuentaId);
            $cuenta->update($data);
        } else {
            $cuenta = CuentaEmpresa::create($data);
        }

        // Sync sucursales
        $cuenta->sucursales()->sync(
            collect($this->sucursales_seleccionadas)->mapWithKeys(fn($id) => [$id => ['activo' => true]])->toArray()
        );

        $this->showModal = false;
        $this->dispatch('toast-success', message: $this->cuentaId ? __('Cuenta actualizada correctamente') : __('Cuenta creada correctamente'));
    }

    public function toggleStatus(int $id)
    {
        $cuenta = CuentaEmpresa::findOrFail($id);
        $cuenta->update(['activo' => !$cuenta->activo]);
        $this->dispatch('toast-success', message: $cuenta->activo ? __('Cuenta activada') : __('Cuenta desactivada'));
    }

    public function confirmarEliminar(int $id)
    {
        $this->deleteId = $id;
        $this->showConfirmDelete = true;
    }

    public function eliminar()
    {
        if (!$this->deleteId) return;

        $cuenta = CuentaEmpresa::findOrFail($this->deleteId);

        // No eliminar si tiene movimientos
        if ($cuenta->movimientos()->count() > 0) {
            $this->dispatch('toast-error', message: __('No se puede eliminar una cuenta con movimientos. Desactívela en su lugar.'));
            $this->showConfirmDelete = false;
            return;
        }

        $cuenta->sucursales()->detach();
        $cuenta->delete();

        $this->showConfirmDelete = false;
        $this->deleteId = null;
        $this->dispatch('toast-success', message: __('Cuenta eliminada correctamente'));
    }

    public function cancel()
    {
        $this->showModal = false;
    }

    public function cancelarEliminar()
    {
        $this->showConfirmDelete = false;
        $this->deleteId = null;
    }

    public function getMonedasProperty()
    {
        return CatalogoCache::monedas();
    }

    public function getSucursalesProperty()
    {
        return CatalogoCache::sucursales();
    }

    public function render()
    {
        $query = CuentaEmpresa::query()->with(['moneda', 'sucursales']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('banco', 'like', "%{$this->search}%")
                  ->orWhere('cbu', 'like', "%{$this->search}%")
                  ->orWhere('alias', 'like', "%{$this->search}%");
            });
        }

        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->filtroActivo !== '') {
            $query->where('activo', $this->filtroActivo === '1');
        }

        $cuentas = $query->orderBy('orden')->orderBy('nombre')->paginate(15);

        return view('livewire.bancos.gestion-cuentas', [
            'cuentas' => $cuentas,
        ]);
    }
}
