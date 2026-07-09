<?php

namespace App\Livewire\Pedidos;

use App\Models\Caja;
use App\Models\Repartidor;
use App\Models\RepartidorFondo;
use App\Models\Sucursal;
use App\Services\Pedidos\RepartidorService;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Repartidores (RF-07) + fondos de repartidor (RF-09, D4/D13).
 *
 * ABM de repartidores (entidad propia, tipo propio/tercero, envío propio,
 * sucursales habilitadas) y gestión del fondo de ciclo largo por sucursal:
 * abrir (cambio desde una caja), reforzar, ver movimientos y rendir
 * (diferencia + liquidación de envíos de terceros + ingreso neto a caja).
 * Toda la lógica de dinero vive en RepartidorService.
 */
#[Layout('layouts.app')]
#[Lazy]
class Repartidores extends Component
{
    use SucursalAware, WithPagination;

    // ==================== FILTROS ====================

    public string $search = '';

    public string $filterStatus = 'active'; // all, active, inactive

    // ==================== MODAL ABM ====================

    public bool $showModal = false;

    public bool $editMode = false;

    public ?int $repartidorId = null;

    public string $nombre = '';

    public ?string $telefono = null;

    public string $tipo = Repartidor::TIPO_PROPIO;

    public bool $envioEsDelRepartidor = false;

    public bool $activo = true;

    /** @var array<int, bool> sucursal_id => habilitada */
    public array $sucursalesSeleccionadas = [];

    // ==================== MODAL FONDO: ABRIR / REFORZAR ====================

    public bool $showFondoModal = false;

    /** 'abrir' | 'reforzar' */
    public string $fondoModalModo = 'abrir';

    public ?int $fondoRepartidorId = null;

    public ?int $fondoId = null;

    public string $fondoMonto = '';

    public string $fondoCajaId = '';

    public string $fondoDetalle = '';

    public array $fondoInfo = [];

    // ==================== MODAL RENDIR ====================

    public bool $showRendirModal = false;

    public ?int $rendirFondoId = null;

    public string $rendirMontoDeclarado = '';

    public string $rendirCajaId = '';

    public string $rendirObservaciones = '';

    public array $rendirInfo = [];

    // ==================== MODAL MOVIMIENTOS ====================

    public bool $showMovimientosModal = false;

    public ?int $movimientosFondoId = null;

    protected RepartidorService $repartidorService;

    public function boot(RepartidorService $repartidorService): void
    {
        $this->repartidorService = $repartidorService;
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="6" :rows="6" />
        HTML;
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->cerrarModal();
        $this->cerrarFondoModal();
        $this->cerrarRendirModal();
        $this->cerrarMovimientosModal();
        $this->resetPage();
    }

    // ==================== FILTROS ====================

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    protected function getRepartidores()
    {
        $query = Repartidor::with(['sucursales:id,nombre', 'fondos' => fn ($q) => $q->abiertos()]);

        if ($this->search) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$this->search}%")
                ->orWhere('telefono', 'like', "%{$this->search}%"));
        }

        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        return $query->orderBy('nombre')->paginate(15);
    }

    // ==================== ABM (RF-07) ====================

    protected function validarPermiso(): bool
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para gestionar repartidores'));

            return false;
        }

        return true;
    }

    public function abrirCrear(): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $this->resetFormulario();
        // Por defecto habilitado en la sucursal actual.
        if ($this->sucursalActual()) {
            $this->sucursalesSeleccionadas[(int) $this->sucursalActual()] = true;
        }
        $this->editMode = false;
        $this->showModal = true;
    }

    public function abrirEditar(int $id): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $repartidor = Repartidor::with('sucursales:id')->find($id);
        if (! $repartidor) {
            $this->dispatch('toast-error', message: __('Repartidor no encontrado'));

            return;
        }

        $this->repartidorId = $repartidor->id;
        $this->nombre = $repartidor->nombre;
        $this->telefono = $repartidor->telefono;
        $this->tipo = $repartidor->tipo;
        $this->envioEsDelRepartidor = (bool) $repartidor->envio_es_del_repartidor;
        $this->activo = (bool) $repartidor->activo;
        $this->sucursalesSeleccionadas = $repartidor->sucursales
            ->mapWithKeys(fn ($s) => [(int) $s->id => true])
            ->toArray();
        $this->editMode = true;
        $this->showModal = true;
    }

    public function guardar(): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $this->validate([
            'nombre' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:30',
            'tipo' => 'required|in:propio,tercero',
        ], [
            'nombre.required' => __('Ingresá el nombre del repartidor'),
        ]);

        $sucursalIds = array_keys(array_filter($this->sucursalesSeleccionadas));
        if (empty($sucursalIds)) {
            $this->dispatch('toast-error', message: __('Habilitá el repartidor en al menos una sucursal'));

            return;
        }

        try {
            $datos = [
                'nombre' => trim($this->nombre),
                'telefono' => trim($this->telefono ?? '') ?: null,
                'tipo' => $this->tipo,
                'envio_es_del_repartidor' => $this->tipo === Repartidor::TIPO_TERCERO && $this->envioEsDelRepartidor,
                'activo' => $this->activo,
            ];

            if ($this->editMode && $this->repartidorId) {
                $repartidor = Repartidor::findOrFail($this->repartidorId);
                $repartidor->update($datos);
                $mensaje = __('Repartidor actualizado');
            } else {
                $repartidor = Repartidor::create($datos);
                $mensaje = __('Repartidor creado');
            }

            $repartidor->sucursales()->sync($sucursalIds);

            $this->dispatch('toast-success', message: $mensaje);
            $this->cerrarModal();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetFormulario();
    }

    protected function resetFormulario(): void
    {
        $this->repartidorId = null;
        $this->nombre = '';
        $this->telefono = null;
        $this->tipo = Repartidor::TIPO_PROPIO;
        $this->envioEsDelRepartidor = false;
        $this->activo = true;
        $this->sucursalesSeleccionadas = [];
        $this->resetValidation();
    }

    // ==================== FONDO: ABRIR / REFORZAR (RF-09) ====================

    public function abrirFondoModal(int $repartidorId): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $repartidor = Repartidor::find($repartidorId);
        if (! $repartidor) {
            return;
        }

        $fondo = $repartidor->fondoAbierto((int) $this->sucursalActual());

        $this->fondoRepartidorId = $repartidorId;
        $this->fondoId = $fondo?->id;
        $this->fondoModalModo = $fondo ? 'reforzar' : 'abrir';
        $this->fondoMonto = '';
        $this->fondoCajaId = (string) (caja_activa() ?? '');
        $this->fondoDetalle = '';
        $this->fondoInfo = [
            'repartidor' => $repartidor->nombre,
            'saldo_teorico' => $fondo ? $this->repartidorService->saldoTeorico($fondo) : null,
        ];
        $this->showFondoModal = true;
    }

    public function confirmarFondo(): void
    {
        $monto = (float) $this->fondoMonto;

        if ($this->fondoCajaId === '') {
            $this->dispatch('toast-error', message: __('Elegí la caja de origen del cambio'));

            return;
        }

        try {
            if ($this->fondoModalModo === 'abrir') {
                $this->repartidorService->abrirFondo(
                    repartidorId: (int) $this->fondoRepartidorId,
                    sucursalId: (int) $this->sucursalActual(),
                    cajaOrigenId: (int) $this->fondoCajaId,
                    monto: $monto,
                    detalle: trim($this->fondoDetalle) ?: null,
                );
                $this->dispatch('toast-success', message: __('Fondo abierto'));
            } else {
                $fondo = RepartidorFondo::findOrFail($this->fondoId);
                $this->repartidorService->reforzarFondo(
                    $fondo,
                    monto: $monto,
                    cajaId: (int) $this->fondoCajaId,
                    detalle: trim($this->fondoDetalle) ?: null,
                );
                $this->dispatch('toast-success', message: __('Fondo reforzado'));
            }
            $this->cerrarFondoModal();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarFondoModal(): void
    {
        $this->showFondoModal = false;
        $this->fondoRepartidorId = null;
        $this->fondoId = null;
        $this->fondoMonto = '';
        $this->fondoCajaId = '';
        $this->fondoDetalle = '';
        $this->fondoInfo = [];
    }

    // ==================== RENDIR (RF-09/D13) ====================

    public function abrirRendir(int $fondoId): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $fondo = RepartidorFondo::with('repartidor')->find($fondoId);
        if (! $fondo || ! $fondo->estaAbierto()) {
            $this->dispatch('toast-error', message: __('El fondo no está abierto'));

            return;
        }

        $this->rendirFondoId = $fondo->id;
        $this->rendirMontoDeclarado = '';
        $this->rendirCajaId = (string) (caja_activa() ?? $fondo->caja_origen_id);
        $this->rendirObservaciones = '';
        $this->rendirInfo = [
            'repartidor' => $fondo->repartidor->nombre,
            'abierto_at' => $fondo->abierto_at?->format('d/m/Y H:i'),
            'monto_inicial' => (float) $fondo->monto_inicial,
            'saldo_teorico' => $this->repartidorService->saldoTeorico($fondo),
            'liquida_envios' => (bool) $fondo->repartidor->envio_es_del_repartidor,
        ];
        $this->showRendirModal = true;
    }

    public function confirmarRendir(): void
    {
        if ($this->rendirCajaId === '' || $this->rendirMontoDeclarado === '') {
            $this->dispatch('toast-error', message: __('Ingresá el efectivo declarado y la caja receptora'));

            return;
        }

        try {
            $fondo = RepartidorFondo::findOrFail($this->rendirFondoId);
            $fondo = $this->repartidorService->rendirFondo(
                $fondo,
                montoDeclarado: (float) $this->rendirMontoDeclarado,
                cajaRendicionId: (int) $this->rendirCajaId,
                observaciones: trim($this->rendirObservaciones) ?: null,
            );

            $diferencia = (float) $fondo->diferencia;
            $mensaje = abs($diferencia) < 0.005
                ? __('Fondo rendido sin diferencia')
                : ($diferencia > 0
                    ? __('Fondo rendido con sobrante de $:monto', ['monto' => number_format($diferencia, 2, ',', '.')])
                    : __('Fondo rendido con faltante de $:monto', ['monto' => number_format(abs($diferencia), 2, ',', '.')]));

            $this->dispatch('toast-success', message: $mensaje);
            $this->cerrarRendirModal();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cerrarRendirModal(): void
    {
        $this->showRendirModal = false;
        $this->rendirFondoId = null;
        $this->rendirMontoDeclarado = '';
        $this->rendirCajaId = '';
        $this->rendirObservaciones = '';
        $this->rendirInfo = [];
    }

    // ==================== MOVIMIENTOS ====================

    public function verMovimientos(int $fondoId): void
    {
        $this->movimientosFondoId = $fondoId;
        $this->showMovimientosModal = true;
    }

    public function cerrarMovimientosModal(): void
    {
        $this->showMovimientosModal = false;
        $this->movimientosFondoId = null;
    }

    // ==================== RENDER ====================

    public function render()
    {
        $sucursalId = (int) $this->sucursalActual();

        $fondoMovimientos = $this->movimientosFondoId
            ? RepartidorFondo::with(['repartidor:id,nombre', 'movimientos' => fn ($q) => $q->orderByDesc('id')])
                ->find($this->movimientosFondoId)
            : null;

        return view('livewire.pedidos.repartidores', [
            'repartidores' => $this->getRepartidores(),
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(['id', 'nombre']),
            'cajasSucursal' => $sucursalId
                ? Caja::where('sucursal_id', $sucursalId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'estado'])
                : collect(),
            'sucursalId' => $sucursalId,
            'fondoMovimientos' => $fondoMovimientos,
            'totalEnFondos' => $sucursalId ? $this->repartidorService->totalEnFondosAbiertos($sucursalId) : 0.0,
        ]);
    }
}
