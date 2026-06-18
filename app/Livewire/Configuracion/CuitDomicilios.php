<?php

namespace App\Livewire\Configuracion;

use App\Models\Cuit;
use App\Models\CuitDomicilio;
use App\Traits\ManejaDomicilio;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Domicilios fiscales por CUIT (RF-11, Fase 9).
 *
 * Componente embebido (NO full-page, NO sucursal-aware: los domicilios son por
 * CUIT, global). Se abre vía el evento `abrir-domicilios-cuit` desde la fila de
 * un CUIT en ConfiguracionEmpresa. Espejo de AFIP: cada CUIT declara N domicilios
 * y uno es el principal. La jurisdicción de la operación sale del domicilio del
 * punto de venta (ver Fase 9, paso 6).
 *
 * Usa el trait reutilizable ManejaDomicilio (provincia→localidad+geo) + el partial
 * livewire.partials.domicilio-form.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-11, Fase 9).
 */
class CuitDomicilios extends Component
{
    use ManejaDomicilio;

    public bool $mostrarModal = false;

    public ?int $cuitId = null;

    public string $cuitNombre = '';

    public string $cuitNumero = '';

    /** Domicilios del CUIT (para mostrar la lista). */
    public array $domicilios = [];

    /** Form de alta/edición visible. */
    public bool $mostrarForm = false;

    /** Id del domicilio en edición (null = alta). */
    public ?int $editandoId = null;

    /** Id del domicilio pendiente de confirmar eliminación (null = sin confirmación). */
    public ?int $confirmandoEliminarId = null;

    /** Etiqueta del domicilio a eliminar (para el mensaje de confirmación). */
    public string $confirmandoEliminarLabel = '';

    #[On('abrir-domicilios-cuit')]
    public function abrir(int $cuitId): void
    {
        $cuit = Cuit::find($cuitId);

        if (! $cuit) {
            return;
        }

        $this->cuitId = $cuit->id;
        $this->cuitNombre = $cuit->razon_social;
        $this->cuitNumero = $cuit->numero_cuit;
        $this->mostrarForm = false;
        $this->editandoId = null;
        $this->resetDomicilio();
        $this->resetErrorBag();
        $this->cargarDomicilios();
        $this->mostrarModal = true;
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->reset(['cuitId', 'cuitNombre', 'cuitNumero', 'domicilios', 'mostrarForm', 'editandoId', 'confirmandoEliminarId', 'confirmandoEliminarLabel']);
        $this->resetDomicilio();
        $this->resetErrorBag();
    }

    /**
     * Abre el form en modo alta.
     */
    public function nuevoDomicilio(): void
    {
        $this->editandoId = null;
        $this->resetDomicilio();
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    /**
     * Carga un domicilio existente en el form para editarlo.
     */
    public function editarDomicilio(int $id): void
    {
        $dom = CuitDomicilio::where('cuit_id', $this->cuitId)->whereKey($id)->first();

        if (! $dom) {
            return;
        }

        $this->editandoId = $dom->id;
        $this->setDomicilioDesde($dom->toArray());
        $this->resetErrorBag();
        $this->mostrarForm = true;
    }

    public function cancelarForm(): void
    {
        $this->mostrarForm = false;
        $this->editandoId = null;
        $this->resetDomicilio();
        $this->resetErrorBag();
    }

    /**
     * Persiste el domicilio (alta o edición). El primer domicilio del CUIT queda
     * como principal automáticamente.
     */
    public function guardarDomicilio(): void
    {
        if ($this->cuitId === null) {
            return;
        }

        $this->validate($this->reglasDomicilio());

        DB::connection('pymes_tenant')->transaction(function () {
            $datos = $this->datosDomicilio();

            if ($this->editandoId) {
                $dom = CuitDomicilio::where('cuit_id', $this->cuitId)->whereKey($this->editandoId)->first();
                if ($dom) {
                    $dom->update($datos);
                }
            } else {
                $esPrimero = ! CuitDomicilio::where('cuit_id', $this->cuitId)->exists();
                CuitDomicilio::create(array_merge($datos, [
                    'cuit_id' => $this->cuitId,
                    'es_principal' => $esPrimero,
                    'activo' => true,
                ]));
            }
        });

        $this->mostrarForm = false;
        $this->editandoId = null;
        $this->resetDomicilio();
        $this->cargarDomicilios();
        $this->dispatch('domicilios-actualizados', cuitId: $this->cuitId);
        $this->dispatch('notify', message: __('Domicilio guardado'), type: 'success');
    }

    /**
     * Marca un domicilio como principal (y desmarca el resto del CUIT).
     */
    public function marcarPrincipal(int $id): void
    {
        if ($this->cuitId === null) {
            return;
        }

        DB::connection('pymes_tenant')->transaction(function () use ($id) {
            CuitDomicilio::where('cuit_id', $this->cuitId)->update(['es_principal' => false]);
            CuitDomicilio::where('cuit_id', $this->cuitId)->whereKey($id)->update(['es_principal' => true]);
        });

        $this->cargarDomicilios();
        $this->dispatch('domicilios-actualizados', cuitId: $this->cuitId);
    }

    /**
     * Pide confirmación para eliminar un domicilio (abre el modal de confirmación).
     */
    public function confirmarEliminar(int $id): void
    {
        $dom = CuitDomicilio::with('localidad')->where('cuit_id', $this->cuitId)->whereKey($id)->first();

        if (! $dom) {
            return;
        }

        $this->confirmandoEliminarId = $dom->id;
        $this->confirmandoEliminarLabel = trim(($dom->direccion ?: __('(sin dirección)')).($dom->localidad ? ', '.$dom->localidad->nombre : ''));
    }

    public function cancelarEliminar(): void
    {
        $this->confirmandoEliminarId = null;
        $this->confirmandoEliminarLabel = '';
    }

    /**
     * Elimina el domicilio confirmado. Los PV que lo referencian quedan en NULL
     * (FK ON DELETE SET NULL). Si era el principal y quedan otros, promueve el primero.
     */
    public function eliminarConfirmado(): void
    {
        if ($this->cuitId === null || $this->confirmandoEliminarId === null) {
            return;
        }

        $id = $this->confirmandoEliminarId;

        DB::connection('pymes_tenant')->transaction(function () use ($id) {
            $dom = CuitDomicilio::where('cuit_id', $this->cuitId)->whereKey($id)->first();
            if (! $dom) {
                return;
            }

            $eraPrincipal = $dom->es_principal;
            $dom->delete();

            if ($eraPrincipal) {
                $otro = CuitDomicilio::where('cuit_id', $this->cuitId)->orderBy('id')->first();
                if ($otro) {
                    $otro->update(['es_principal' => true]);
                }
            }
        });

        $this->cancelarEliminar();
        $this->cargarDomicilios();
        $this->dispatch('domicilios-actualizados', cuitId: $this->cuitId);
        $this->dispatch('notify', message: __('Domicilio eliminado'), type: 'success');
    }

    private function cargarDomicilios(): void
    {
        $this->domicilios = CuitDomicilio::with(['localidad', 'provinciaRef'])
            ->where('cuit_id', $this->cuitId)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'tipo' => $d->tipo,
                'provincia' => $d->provincia,
                'provincia_nombre' => $d->provinciaRef?->nombre,
                'localidad' => $d->localidad?->nombre,
                'direccion' => $d->direccion,
                'es_principal' => (bool) $d->es_principal,
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.configuracion.cuit-domicilios');
    }
}
