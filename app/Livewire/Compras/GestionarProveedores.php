<?php

namespace App\Livewire\Compras;

use App\Models\CondicionIva;
use App\Models\CuentaCompra;
use App\Models\Impuesto;
use App\Models\Proveedor;
use App\Services\CuentaCorrienteProveedorService;
use App\Traits\SucursalAware;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ABM de proveedores (spec compras-costos, Fase 5 — pantalla NUEVA: hasta
 * ahora los proveedores solo existían como select dentro de Compras).
 *
 * Incluye la configuración de cuenta corriente (RF-18: habilitación + días
 * de pago), la cuenta de compra default (RF-22) y el ABM del catálogo de
 * cuentas de compra. El estado de cuenta usa la sucursal activa (D19).
 */
#[Layout('layouts.app')]
#[Lazy]
class GestionarProveedores extends Component
{
    use SucursalAware, WithPagination;

    // Filtros
    public string $search = '';

    public string $filterStatus = 'all';

    // Modal ABM
    public bool $showModal = false;

    public bool $editMode = false;

    public ?int $proveedorId = null;

    public string $codigo = '';

    public string $nombre = '';

    public string $razon_social = '';

    public string $cuit = '';

    public string $email = '';

    public string $telefono = '';

    public string $direccion = '';

    public ?int $condicion_iva_id = null;

    public ?int $cuenta_compra_id = null;

    public bool $tiene_cuenta_corriente = false;

    public ?int $dias_pago = null;

    /** D23: proveedor de servicios — sugiere la modalidad "factura de servicio" en el editor. */
    public bool $es_servicio = false;

    /** D24: percepciones típicas [{impuesto_id, alicuota}] que el editor precarga al elegirlo. */
    public array $percepciones_habituales = [];

    public bool $activo = true;

    // Modal cuentas de compra (RF-22)
    public bool $showCuentasModal = false;

    public string $nuevaCuentaNombre = '';

    // Modal estado de cuenta
    public bool $showExtractoModal = false;

    public ?int $proveedorExtractoId = null;

    public array $extracto = [];

    public array $saldosExtracto = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function onSucursalChanged(): void
    {
        $this->resetPage();
        $this->showExtractoModal = false;
    }

    // ==================== ABM proveedor ====================

    public function create(): void
    {
        $this->reset([
            'proveedorId', 'codigo', 'nombre', 'razon_social', 'cuit', 'email', 'telefono',
            'direccion', 'condicion_iva_id', 'cuenta_compra_id', 'tiene_cuenta_corriente', 'dias_pago',
            'es_servicio', 'percepciones_habituales',
        ]);
        $this->activo = true;
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit(int $proveedorId): void
    {
        $proveedor = Proveedor::findOrFail($proveedorId);

        $this->proveedorId = $proveedor->id;
        $this->codigo = $proveedor->codigo ?? '';
        $this->nombre = $proveedor->nombre;
        $this->razon_social = $proveedor->razon_social ?? '';
        $this->cuit = $proveedor->cuit ?? '';
        $this->email = $proveedor->email ?? '';
        $this->telefono = $proveedor->telefono ?? '';
        $this->direccion = $proveedor->direccion ?? '';
        $this->condicion_iva_id = $proveedor->condicion_iva_id;
        $this->cuenta_compra_id = $proveedor->cuenta_compra_id;
        $this->tiene_cuenta_corriente = (bool) $proveedor->tiene_cuenta_corriente;
        $this->dias_pago = $proveedor->dias_pago;
        $this->es_servicio = (bool) $proveedor->es_servicio;
        $this->percepciones_habituales = collect((array) $proveedor->percepciones_habituales)
            ->map(fn ($p) => [
                'impuesto_id' => $p['impuesto_id'] ?? null,
                'alicuota' => isset($p['alicuota']) && (float) $p['alicuota'] > 0 ? (string) $p['alicuota'] : '',
            ])
            ->values()
            ->all();
        $this->activo = (bool) $proveedor->activo;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:191',
            'codigo' => 'nullable|string|max:50',
            'razon_social' => 'nullable|string|max:191',
            'cuit' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'condicion_iva_id' => 'nullable|integer',
            'cuenta_compra_id' => 'nullable|integer',
            'dias_pago' => 'nullable|integer|min:0|max:365',
            'percepciones_habituales.*.impuesto_id' => 'nullable|integer',
            'percepciones_habituales.*.alicuota' => 'nullable|numeric|min:0|max:100',
        ]);

        $datos = [
            'codigo' => $this->codigo ?: null,
            'nombre' => $this->nombre,
            'razon_social' => $this->razon_social ?: null,
            'cuit' => $this->cuit ?: null,
            'email' => $this->email ?: null,
            'telefono' => $this->telefono ?: null,
            'direccion' => $this->direccion ?: null,
            'condicion_iva_id' => $this->condicion_iva_id,
            'cuenta_compra_id' => $this->cuenta_compra_id,
            'tiene_cuenta_corriente' => $this->tiene_cuenta_corriente,
            'dias_pago' => $this->dias_pago,
            'es_servicio' => $this->es_servicio,
            'percepciones_habituales' => collect($this->percepciones_habituales)
                ->filter(fn ($p) => ! empty($p['impuesto_id']))
                ->map(fn ($p) => [
                    'impuesto_id' => (int) $p['impuesto_id'],
                    'alicuota' => (float) str_replace(',', '.', (string) ($p['alicuota'] ?? 0)) ?: null,
                ])
                ->values()
                ->all() ?: null,
            'activo' => $this->activo,
        ];

        if ($this->editMode) {
            Proveedor::findOrFail($this->proveedorId)->update($datos);
            $mensaje = __('Proveedor actualizado correctamente');
        } else {
            Proveedor::create($datos);
            $mensaje = __('Proveedor creado correctamente');
        }

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: $mensaje);
    }

    public function toggleActivo(int $proveedorId): void
    {
        $proveedor = Proveedor::findOrFail($proveedorId);
        $proveedor->update(['activo' => ! $proveedor->activo]);
    }

    public function cancel(): void
    {
        $this->showModal = false;
    }

    // D24: percepciones habituales (repetidor del modal)

    public function agregarPercepcionHabitual(): void
    {
        $this->percepciones_habituales[] = ['impuesto_id' => null, 'alicuota' => ''];
    }

    public function quitarPercepcionHabitual(int $index): void
    {
        unset($this->percepciones_habituales[$index]);
        $this->percepciones_habituales = array_values($this->percepciones_habituales);
    }

    // ==================== Cuentas de compra (RF-22) ====================

    public function openCuentasModal(): void
    {
        $this->nuevaCuentaNombre = '';
        $this->showCuentasModal = true;
    }

    public function agregarCuenta(): void
    {
        $this->validate(['nuevaCuentaNombre' => 'required|string|max:100']);

        CuentaCompra::create([
            'nombre' => $this->nuevaCuentaNombre,
            'orden' => (int) CuentaCompra::max('orden') + 1,
            'activo' => true,
        ]);

        $this->nuevaCuentaNombre = '';
        $this->dispatch('notify', type: 'success', message: __('Cuenta de compra creada'));
    }

    public function toggleCuenta(int $cuentaId): void
    {
        $cuenta = CuentaCompra::findOrFail($cuentaId);
        $cuenta->update(['activo' => ! $cuenta->activo]);
    }

    public function cerrarCuentasModal(): void
    {
        $this->showCuentasModal = false;
    }

    // ==================== Estado de cuenta (D19) ====================

    public function verExtracto(int $proveedorId): void
    {
        $this->proveedorExtractoId = $proveedorId;

        $ccService = app(CuentaCorrienteProveedorService::class);
        $this->extracto = $ccService->obtenerExtractoResumido($proveedorId, $this->sucursalActual())->toArray();
        $this->saldosExtracto = $ccService->obtenerSaldos($proveedorId, $this->sucursalActual());

        $this->showExtractoModal = true;
    }

    public function cerrarExtracto(): void
    {
        $this->showExtractoModal = false;
        $this->extracto = [];
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="6" :rows="8" />
        HTML;
    }

    public function render()
    {
        $query = Proveedor::query()->with(['cuentaCompra:id,nombre', 'condicionIva:id,nombre']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('razon_social', 'like', '%'.$this->search.'%')
                    ->orWhere('codigo', 'like', '%'.$this->search.'%')
                    ->orWhere('cuit', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        return view('livewire.compras.gestionar-proveedores', [
            'proveedores' => $query->orderBy('nombre')->paginate(15),
            'cuentasCompra' => CuentaCompra::orderBy('orden')->get(),
            'condicionesIva' => CondicionIva::orderBy('nombre')->get(['id', 'nombre']),
            'impuestosPercepcion' => Impuesto::activos()
                ->where('naturaleza_default', 'percepcion')
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'proveedorExtracto' => $this->proveedorExtractoId ? Proveedor::find($this->proveedorExtractoId) : null,
        ]);
    }
}
