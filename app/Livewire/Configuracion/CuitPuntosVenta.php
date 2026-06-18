<?php

namespace App\Livewire\Configuracion;

use App\Models\Cuit;
use App\Models\CuitDomicilio;
use App\Models\PuntoVenta;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Puntos de venta por CUIT (RF-11, Fase 9 — refactor UI).
 *
 * Componente embebido (NO full-page, NO sucursal-aware). Se abre vía el evento
 * `abrir-puntos-cuit` desde la fila de un CUIT en ConfiguracionEmpresa, igual que
 * los modales de Impuestos y Domicilios. Gestiona el ABM de puntos de venta del
 * CUIT y la asignación del domicilio fiscal declarado de cada PV.
 *
 * Se extrajo del modal de alta/edición del CUIT para homogeneizar la UI (un botón
 * por sección: Impuestos / Domicilios / Puntos de venta).
 */
class CuitPuntosVenta extends Component
{
    public bool $mostrarModal = false;

    public ?int $cuitId = null;

    public string $cuitNombre = '';

    public string $cuitNumero = '';

    /** Puntos de venta del CUIT (para la lista). */
    public array $puntosVenta = [];

    /** Domicilios del CUIT para el selector por PV (id => label). */
    public array $domiciliosDelCuit = [];

    public $nuevoPuntoVentaNumero = '';

    public $nuevoPuntoVentaNombre = '';

    /** Id del PV pendiente de confirmar eliminación (null = sin confirmación). */
    public ?int $confirmandoEliminarId = null;

    /** Número formateado del PV a eliminar (para el mensaje). */
    public string $confirmandoEliminarLabel = '';

    #[On('abrir-puntos-cuit')]
    public function abrir(int $cuitId): void
    {
        $cuit = Cuit::find($cuitId);

        if (! $cuit) {
            return;
        }

        $this->cuitId = $cuit->id;
        $this->cuitNombre = $cuit->razon_social;
        $this->cuitNumero = $cuit->numero_cuit;
        $this->resetFormulario();
        $this->resetErrorBag();
        $this->cargarPuntosVenta();
        $this->cargarDomiciliosDelCuit();
        $this->mostrarModal = true;
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->reset(['cuitId', 'cuitNombre', 'cuitNumero', 'puntosVenta', 'domiciliosDelCuit', 'nuevoPuntoVentaNumero', 'nuevoPuntoVentaNombre', 'confirmandoEliminarId', 'confirmandoEliminarLabel']);
        $this->resetErrorBag();
    }

    public function agregarPuntoVenta(): void
    {
        if (! $this->cuitId) {
            return;
        }

        $this->validate([
            'nuevoPuntoVentaNumero' => 'required|integer|min:1|max:99999',
            'nuevoPuntoVentaNombre' => 'nullable|max:100',
        ], [
            'nuevoPuntoVentaNumero.required' => __('El número de punto de venta es obligatorio.'),
            'nuevoPuntoVentaNumero.integer' => __('El número debe ser un entero.'),
            'nuevoPuntoVentaNumero.min' => __('El número debe ser al menos 1.'),
            'nuevoPuntoVentaNumero.max' => __('El número no puede superar 99999.'),
        ]);

        try {
            // Restaurar un PV soft-deleted con el mismo número si existe (la UNIQUE
            // (cuit_id, numero) ignora el soft-delete).
            $pvEliminado = PuntoVenta::withTrashed()
                ->where('cuit_id', $this->cuitId)
                ->where('numero', $this->nuevoPuntoVentaNumero)
                ->whereNotNull('deleted_at')
                ->first();

            if ($pvEliminado) {
                $pvEliminado->restore();
                $pvEliminado->update([
                    'nombre' => $this->nuevoPuntoVentaNombre ?: $pvEliminado->nombre,
                    'activo' => true,
                ]);
                $mensaje = __('Punto de venta restaurado');
            } else {
                $existe = PuntoVenta::where('cuit_id', $this->cuitId)
                    ->where('numero', $this->nuevoPuntoVentaNumero)
                    ->exists();

                if ($existe) {
                    $this->addError('nuevoPuntoVentaNumero', __('Este número de punto de venta ya existe para este CUIT.'));

                    return;
                }

                PuntoVenta::create([
                    'cuit_id' => $this->cuitId,
                    'numero' => $this->nuevoPuntoVentaNumero,
                    'nombre' => $this->nuevoPuntoVentaNombre ?: null,
                    'activo' => true,
                ]);
                $mensaje = __('Punto de venta agregado');
            }

            $this->resetFormulario();
            $this->cargarPuntosVenta();
            $this->notificarCambio();
            $this->dispatch('notify', message: $mensaje, type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    public function togglePuntoVentaActivo(int $id): void
    {
        $pv = PuntoVenta::where('cuit_id', $this->cuitId)->find($id);

        if (! $pv) {
            return;
        }

        $pv->activo = ! $pv->activo;
        $pv->save();

        $this->cargarPuntosVenta();
        $this->notificarCambio();

        $estado = $pv->activo ? __('activado') : __('desactivado');
        $this->dispatch('notify', message: __('Punto de venta :numero :estado', ['numero' => $pv->numero_formateado, 'estado' => $estado]), type: 'success');
    }

    public function confirmarEliminar(int $id): void
    {
        $pv = PuntoVenta::where('cuit_id', $this->cuitId)->find($id);

        if (! $pv) {
            return;
        }

        $this->confirmandoEliminarId = $pv->id;
        $this->confirmandoEliminarLabel = $pv->numero_formateado;
    }

    public function cancelarEliminar(): void
    {
        $this->confirmandoEliminarId = null;
        $this->confirmandoEliminarLabel = '';
    }

    public function eliminarConfirmado(): void
    {
        if ($this->cuitId === null || $this->confirmandoEliminarId === null) {
            return;
        }

        $pv = PuntoVenta::where('cuit_id', $this->cuitId)->find($this->confirmandoEliminarId);

        if (! $pv) {
            $this->cancelarEliminar();

            return;
        }

        $numero = $pv->numero_formateado;
        $pv->activo = false;
        $pv->save();
        $pv->delete();

        $this->cancelarEliminar();
        $this->cargarPuntosVenta();
        $this->notificarCambio();
        $this->dispatch('notify', message: __('Punto de venta :numero eliminado', ['numero' => $numero]), type: 'success');
    }

    /**
     * Asigna (o desasigna) el domicilio declarado de un punto de venta.
     */
    public function actualizarDomicilioPv(int $pvId, $domicilioId): void
    {
        $pv = PuntoVenta::where('cuit_id', $this->cuitId)->find($pvId);

        if (! $pv) {
            return;
        }

        $pv->update(['cuit_domicilio_id' => $domicilioId ?: null]);
        $this->cargarPuntosVenta();
    }

    /**
     * Si el modal de domicilios cambia los domicilios del mismo CUIT, refrescar
     * el selector.
     */
    #[On('domicilios-actualizados')]
    public function refrescarDomicilios(?int $cuitId = null): void
    {
        if ($this->mostrarModal && $this->cuitId && (int) $cuitId === (int) $this->cuitId) {
            $this->cargarDomiciliosDelCuit();
        }
    }

    private function cargarPuntosVenta(): void
    {
        $this->puntosVenta = PuntoVenta::where('cuit_id', $this->cuitId)
            ->orderBy('numero')
            ->get(['id', 'numero', 'nombre', 'activo', 'cuit_domicilio_id'])
            ->map(fn ($pv) => [
                'id' => $pv->id,
                'numero' => $pv->numero,
                'nombre' => $pv->nombre,
                'activo' => (bool) $pv->activo,
                'cuit_domicilio_id' => $pv->cuit_domicilio_id,
            ])
            ->all();
    }

    private function cargarDomiciliosDelCuit(): void
    {
        $this->domiciliosDelCuit = CuitDomicilio::with('localidad')
            ->where('cuit_id', $this->cuitId)
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function ($d) {
                $label = $d->direccion ?: __('(sin dirección)');
                if ($d->localidad) {
                    $label .= ' — '.$d->localidad->nombre;
                }
                $label .= ' ('.$d->provincia.')';
                if ($d->es_principal) {
                    $label .= ' · '.__('Principal');
                }

                return [$d->id => $label];
            })
            ->toArray();
    }

    private function resetFormulario(): void
    {
        $this->nuevoPuntoVentaNumero = '';
        $this->nuevoPuntoVentaNombre = '';
    }

    /**
     * Avisa a la lista de CUITs (ConfiguracionEmpresa) que refresque su resumen.
     */
    private function notificarCambio(): void
    {
        $this->dispatch('puntos-venta-actualizados', cuitId: $this->cuitId);
    }

    public function render()
    {
        return view('livewire.configuracion.cuit-puntos-venta');
    }
}
