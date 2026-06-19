<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\Impuesto;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Perfil fiscal del cliente (RF-13/RF-15 sistema-impositivo, Fase 10a).
 *
 * Componente embebido (NO full-page, NO sucursal-aware: el perfil fiscal es global
 * por cliente). Espejo de Configuracion\CuitImpuestos pero con semántica de SUJETO
 * PERCIBIDO: gestiona `cliente_impuesto_configs` (exención, alícuota por sujeto,
 * N° de padrón, vigencia, origen). Se abre vía el evento `abrir-impuestos-cliente`
 * desde la fila de un cliente en GestionarClientes.
 *
 * El combobox excluye el tipo IVA (la percepción de IVA es automática, no se
 * configura por cliente) y las naturalezas débito/crédito fiscal; sólo ofrece
 * percepciones (IIBB y otras provinciales). El override manual gana sobre el
 * padrón (Fase 10b) — ver ImpuestoService::calcularTributos.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-13/RF-15, Fase 10a).
 */
class ClienteImpuestos extends Component
{
    public bool $mostrarModal = false;

    public ?int $clienteId = null;

    public string $clienteNombre = '';

    public string $clienteCuit = '';

    /** Filas editables del perfil fiscal del cliente (espejo de cliente_impuesto_configs). */
    public array $filas = [];

    /** Búsqueda del combobox de alta rápida sobre el catálogo. */
    public string $buscarImpuesto = '';

    protected function rules(): array
    {
        return [
            'filas.*.alicuota' => 'nullable|numeric|min:0|max:100',
            'filas.*.alicuota_minimo_base' => 'nullable|numeric|min:0',
            'filas.*.numero_padron' => 'nullable|string|max:30',
            'filas.*.vigente_desde' => 'nullable|date',
            'filas.*.vigente_hasta' => 'nullable|date|after_or_equal:filas.*.vigente_desde',
        ];
    }

    #[On('abrir-impuestos-cliente')]
    public function abrir(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);

        if (! $cliente) {
            return;
        }

        $this->clienteId = $cliente->id;
        $this->clienteNombre = $cliente->obtenerNombreFiscal();
        $this->clienteCuit = $cliente->cuit ?? '';
        $this->buscarImpuesto = '';

        $this->cargarFilas();
        $this->mostrarModal = true;
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->reset(['clienteId', 'clienteNombre', 'clienteCuit', 'filas', 'buscarImpuesto']);
        $this->resetErrorBag();
    }

    /**
     * Catálogo disponible para agregar: percepciones activas que el cliente aún no
     * tiene configuradas, filtradas por la búsqueda. Excluye el tipo IVA (la
     * percepción de IVA es automática) y las naturalezas débito/crédito fiscal.
     */
    public function getImpuestosDisponiblesProperty()
    {
        if ($this->clienteId === null) {
            return collect();
        }

        $yaConfigurados = collect($this->filas)->pluck('impuesto_id')->all();

        return Impuesto::activos()
            ->whereNotIn('id', $yaConfigurados)
            ->where('tipo', '!=', Impuesto::TIPO_IVA)
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
     * Agrega un impuesto del catálogo al perfil del cliente (origen manual).
     */
    public function agregarImpuesto(int $impuestoId): void
    {
        if ($this->clienteId === null) {
            return;
        }

        $impuesto = Impuesto::activos()
            ->where('tipo', '!=', Impuesto::TIPO_IVA)
            ->where('naturaleza_default', 'percepcion')
            ->find($impuestoId);

        if (! $impuesto) {
            return;
        }

        ClienteImpuestoConfig::firstOrCreate(
            ['cliente_id' => $this->clienteId, 'impuesto_id' => $impuestoId, 'vigente_desde' => null],
            [
                'exento' => false,
                'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_MANUAL,
            ]
        );

        $this->buscarImpuesto = '';
        $this->cargarFilas();
    }

    /**
     * Persiste todas las filas editadas de una sola pasada. La edición manual
     * fuerza `origen_alicuota = manual` para que el importador de padrón (Fase 10b)
     * no la pise.
     */
    public function guardar(): void
    {
        $this->validate();

        DB::connection('pymes_tenant')->transaction(function () {
            foreach ($this->filas as $fila) {
                $config = ClienteImpuestoConfig::where('cliente_id', $this->clienteId)
                    ->whereKey($fila['id'])
                    ->first();

                if (! $config) {
                    continue;
                }

                $config->update([
                    'exento' => (bool) $fila['exento'],
                    'alicuota' => $fila['alicuota'] !== '' ? $fila['alicuota'] : null,
                    'alicuota_minimo_base' => $fila['alicuota_minimo_base'] !== '' ? $fila['alicuota_minimo_base'] : null,
                    'numero_padron' => $fila['numero_padron'] ?: null,
                    'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_MANUAL,
                    'vigente_desde' => $fila['vigente_desde'] ?: null,
                    'vigente_hasta' => $fila['vigente_hasta'] ?: null,
                ]);
            }
        });

        $this->dispatch('notify', message: __('Perfil fiscal del cliente guardado'), type: 'success');
        $this->cerrar();
    }

    /**
     * Quita la config de un impuesto del perfil del cliente.
     */
    public function quitarImpuesto(int $configId): void
    {
        if ($this->clienteId === null) {
            return;
        }

        ClienteImpuestoConfig::where('cliente_id', $this->clienteId)
            ->whereKey($configId)
            ->delete();

        $this->cargarFilas();
    }

    /**
     * Recarga las filas editables desde la BD.
     */
    private function cargarFilas(): void
    {
        $this->filas = ClienteImpuestoConfig::with('impuesto')
            ->where('cliente_id', $this->clienteId)
            ->get()
            ->sortBy(fn ($c) => $c->impuesto?->nombre)
            ->map(fn ($c) => [
                'id' => $c->id,
                'impuesto_id' => $c->impuesto_id,
                'codigo' => $c->impuesto?->codigo,
                'nombre' => $c->impuesto?->nombre,
                'tipo' => $c->impuesto?->tipo,
                'jurisdiccion' => $c->impuesto?->jurisdiccion,
                'exento' => (bool) $c->exento,
                'alicuota' => $c->alicuota !== null ? (string) (float) $c->alicuota : '',
                'alicuota_minimo_base' => $c->alicuota_minimo_base !== null ? (string) (float) $c->alicuota_minimo_base : '',
                'numero_padron' => $c->numero_padron ?? '',
                'origen_alicuota' => $c->origen_alicuota,
                'vigente_desde' => $c->vigente_desde?->format('Y-m-d') ?? '',
                'vigente_hasta' => $c->vigente_hasta?->format('Y-m-d') ?? '',
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.clientes.cliente-impuestos', [
            'impuestosDisponibles' => $this->impuestosDisponibles,
        ]);
    }
}
