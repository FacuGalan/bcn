<?php

namespace App\Livewire\Configuracion;

use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Configuración impositiva por CUIT (RF-02 sistema-impositivo, Fase 3).
 *
 * Componente embebido (NO full-page, NO sucursal-aware: la config es global por
 * CUIT). Se abre vía el evento `abrir-impuestos-cuit` desde la fila de un CUIT
 * en ConfiguracionEmpresa. Gestiona `cuit_impuesto_configs`: lista editable
 * (alícuota, agente percepción/retención, inscripto, vigencia opcional) + alta
 * rápida desde el catálogo + alta de impuesto custom (es_sistema=false).
 *
 * v1 (decisión usuario 2026-06-16): una config actual por impuesto, editable en
 * lugar (sin historial de vigencias; vigente_desde/hasta opcionales). El origen
 * de alícuota es siempre `manual` (padrón = fase futura, D3).
 *
 * REVISAR (Fable):
 *  - El modelo admite múltiples vigencias por (cuit, impuesto) pero la UI edita
 *    una sola fila en lugar. Si el usuario carga vigente_desde, una segunda alta
 *    del mismo impuesto queda bloqueada por el filtro de disponibles → ok para
 *    v1, pero el historial de vigencias real no se gestiona (¿hace falta?).
 *  - `numero_inscripcion` (acá) vs `cuits.numero_iibb` (ya existente): posible
 *    redundancia para IIBB. Definir cuál es la fuente de verdad.
 *  - Permisos: el componente no tiene gate propio; confía en que ConfiguracionEmpresa
 *    ya está protegido. Confirmar si necesita un permiso fiscal propio (RF-10/Fase 7).
 *  - IVA débito/crédito a nivel CUIT: se siembran como MARCADOR (inscripto, SIN
 *    alícuota) al abrir un CUIT sin configs. El IVA real es por artículo (21/10,5)
 *    vía ComprobanteFiscalIva/CompraService. Cuando Fase 5/6 alimenten
 *    movimientos_fiscales de débito/crédito desde comprobantes/compras, deben tomar
 *    la alícuota POR LÍNEA, nunca esta config (que no tiene alícuota a propósito).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-02, Fase 3).
 */
class CuitImpuestos extends Component
{
    public bool $mostrarModal = false;

    public ?int $cuitId = null;

    public string $cuitNombre = '';

    public string $cuitNumero = '';

    /** Filas editables de configs del CUIT (espejo de cuit_impuesto_configs). */
    public array $filas = [];

    /** Búsqueda del combobox de alta rápida sobre el catálogo. */
    public string $buscarImpuesto = '';

    // --- Alta de impuesto custom ---
    public bool $mostrarFormCustom = false;

    public string $customNombre = '';

    public string $customTipo = Impuesto::TIPO_OTRO;

    public string $customNaturaleza = CuitImpuestoConfig::ORIGEN_MANUAL; // placeholder, se setea abajo

    public ?string $customJurisdiccion = null;

    protected function rules(): array
    {
        return [
            'filas.*.alicuota' => 'nullable|numeric|min:0|max:100',
            'filas.*.alicuota_minimo_base' => 'nullable|numeric|min:0',
            'filas.*.numero_inscripcion' => 'nullable|string|max:30',
            'filas.*.vigente_desde' => 'nullable|date',
            'filas.*.vigente_hasta' => 'nullable|date|after_or_equal:filas.*.vigente_desde',
        ];
    }

    #[On('abrir-impuestos-cuit')]
    public function abrir(int $cuitId): void
    {
        $cuit = Cuit::find($cuitId);

        if (! $cuit) {
            return;
        }

        $this->cuitId = $cuit->id;
        $this->cuitNombre = $cuit->razon_social;
        $this->cuitNumero = $cuit->numero_cuit;
        $this->buscarImpuesto = '';
        $this->mostrarFormCustom = false;
        $this->resetFormCustom();

        // Default: un CUIT recién configurado arranca con IVA débito y crédito
        // al 21% (la alícuota general). Editable/removible. OJO: el IVA real de
        // cada comprobante sale por artículo (21/10,5) vía ComprobanteFiscalIva;
        // esto es solo el default de referencia a nivel CUIT. Ver REVISAR (Fable).
        if (CuitImpuestoConfig::where('cuit_id', $cuit->id)->count() === 0) {
            $this->sembrarIvaPorDefecto();
        }

        $this->cargarFilas();
        $this->mostrarModal = true;
    }

    /**
     * Crea las configs de IVA débito y crédito al 21% si el CUIT no tiene
     * ninguna config aún (solo si el catálogo trae esos códigos).
     */
    private function sembrarIvaPorDefecto(): void
    {
        foreach (['iva_debito', 'iva_credito'] as $codigo) {
            $imp = Impuesto::activos()->porCodigo($codigo)->first();

            if (! $imp) {
                continue;
            }

            CuitImpuestoConfig::firstOrCreate(
                ['cuit_id' => $this->cuitId, 'impuesto_id' => $imp->id, 'vigente_desde' => null],
                [
                    'inscripto' => true,
                    'es_agente_percepcion' => false,
                    'es_agente_retencion' => false,
                    // Sin alícuota: el IVA débito/crédito real sale por artículo
                    // (21/10,5) vía ComprobanteFiscalIva. Esto es solo el marcador
                    // de que el CUIT está inscripto en IVA.
                    'alicuota' => null,
                    'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
                ]
            );
        }
    }

    public function cerrar(): void
    {
        $this->mostrarModal = false;
        $this->reset(['cuitId', 'cuitNombre', 'cuitNumero', 'filas', 'buscarImpuesto', 'mostrarFormCustom']);
        $this->resetFormCustom();
        $this->resetErrorBag();
    }

    /**
     * Catálogo disponible para agregar: impuestos activos que el CUIT aún no
     * tiene configurados, filtrados por la búsqueda del combobox.
     */
    public function getImpuestosDisponiblesProperty()
    {
        if ($this->cuitId === null) {
            return collect();
        }

        $yaConfigurados = collect($this->filas)->pluck('impuesto_id')->all();

        return Impuesto::activos()
            ->whereNotIn('id', $yaConfigurados)
            ->when($this->buscarImpuesto !== '', function ($q) {
                $term = '%'.$this->buscarImpuesto.'%';
                $q->where(fn ($sub) => $sub->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term));
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get();
    }

    /**
     * Agrega un impuesto del catálogo: crea su config (alcanzado, alícuota a
     * cargar) y recarga la lista.
     */
    public function agregarImpuesto(int $impuestoId): void
    {
        if ($this->cuitId === null) {
            return;
        }

        $impuesto = Impuesto::activos()->find($impuestoId);

        if (! $impuesto) {
            return;
        }

        CuitImpuestoConfig::firstOrCreate(
            ['cuit_id' => $this->cuitId, 'impuesto_id' => $impuestoId, 'vigente_desde' => null],
            [
                'inscripto' => true,
                'es_agente_percepcion' => false,
                'es_agente_retencion' => false,
                'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
            ]
        );

        $this->buscarImpuesto = '';
        $this->cargarFilas();
    }

    /**
     * Persiste todas las filas editadas de una sola pasada.
     */
    public function guardar(): void
    {
        $this->validate();

        DB::connection('pymes_tenant')->transaction(function () {
            foreach ($this->filas as $fila) {
                $config = CuitImpuestoConfig::where('cuit_id', $this->cuitId)
                    ->whereKey($fila['id'])
                    ->first();

                if (! $config) {
                    continue;
                }

                // El IVA débito/crédito no lleva alícuota a nivel CUIT (sale por
                // artículo); se ignora cualquier valor que llegue.
                $sinAlicuota = in_array($fila['naturaleza'], ['debito_fiscal', 'credito_fiscal'], true);

                $config->update([
                    'inscripto' => (bool) $fila['inscripto'],
                    'es_agente_percepcion' => (bool) $fila['es_agente_percepcion'],
                    'es_agente_retencion' => (bool) $fila['es_agente_retencion'],
                    'alicuota' => (! $sinAlicuota && $fila['alicuota'] !== '') ? $fila['alicuota'] : null,
                    'alicuota_minimo_base' => $fila['alicuota_minimo_base'] !== '' ? $fila['alicuota_minimo_base'] : null,
                    'numero_inscripcion' => $fila['numero_inscripcion'] ?: null,
                    'vigente_desde' => $fila['vigente_desde'] ?: null,
                    'vigente_hasta' => $fila['vigente_hasta'] ?: null,
                ]);
            }
        });

        $this->dispatch('notify', message: __('Configuración impositiva guardada'), type: 'success');
        $this->cerrar();
    }

    /**
     * Quita la config de un impuesto del CUIT (no afecta el catálogo).
     */
    public function quitarImpuesto(int $configId): void
    {
        if ($this->cuitId === null) {
            return;
        }

        CuitImpuestoConfig::where('cuit_id', $this->cuitId)
            ->whereKey($configId)
            ->delete();

        $this->cargarFilas();
    }

    // ==================== Impuesto custom ====================

    public function toggleFormCustom(): void
    {
        $this->mostrarFormCustom = ! $this->mostrarFormCustom;
        $this->resetFormCustom();
        $this->resetErrorBag(['customNombre', 'customTipo', 'customNaturaleza', 'customJurisdiccion']);
    }

    public function crearImpuestoCustom(): void
    {
        $tipos = [Impuesto::TIPO_IVA, Impuesto::TIPO_IIBB, Impuesto::TIPO_GANANCIAS, Impuesto::TIPO_CREDITO_DEBITO, Impuesto::TIPO_OTRO];
        $naturalezas = ['percepcion', 'retencion', 'debito_fiscal', 'credito_fiscal', 'tributo'];

        $this->validate([
            'customNombre' => 'required|string|max:150',
            'customTipo' => 'required|in:'.implode(',', $tipos),
            'customNaturaleza' => 'required|in:'.implode(',', $naturalezas),
            'customJurisdiccion' => 'nullable|string|max:6',
        ]);

        $impuesto = Impuesto::create([
            'codigo' => $this->generarCodigoCustom($this->customNombre),
            'nombre' => $this->customNombre,
            'tipo' => $this->customTipo,
            'naturaleza_default' => $this->customNaturaleza,
            'jurisdiccion' => $this->customJurisdiccion ?: null,
            'es_sistema' => false,
            'activo' => true,
        ]);

        // Lo agrega directamente al CUIT.
        $this->agregarImpuesto($impuesto->id);

        $this->mostrarFormCustom = false;
        $this->resetFormCustom();
        $this->dispatch('notify', message: __('Impuesto creado y agregado'), type: 'success');
    }

    private function generarCodigoCustom(string $nombre): string
    {
        $base = 'custom_'.preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($nombre)));
        $base = trim(substr($base, 0, 40), '_');
        $codigo = $base;
        $i = 1;

        while (Impuesto::porCodigo($codigo)->exists()) {
            $codigo = $base.'_'.$i++;
        }

        return $codigo;
    }

    private function resetFormCustom(): void
    {
        $this->customNombre = '';
        $this->customTipo = Impuesto::TIPO_OTRO;
        $this->customNaturaleza = 'percepcion';
        $this->customJurisdiccion = null;
    }

    /**
     * Recarga las filas editables desde la BD (configs sin vigente_desde = la
     * "actual" del v1; ver decisión del spec).
     */
    private function cargarFilas(): void
    {
        $this->filas = CuitImpuestoConfig::with('impuesto')
            ->where('cuit_id', $this->cuitId)
            ->get()
            ->sortBy(fn ($c) => $c->impuesto?->nombre)
            ->map(fn ($c) => [
                'id' => $c->id,
                'impuesto_id' => $c->impuesto_id,
                'codigo' => $c->impuesto?->codigo,
                'nombre' => $c->impuesto?->nombre,
                'tipo' => $c->impuesto?->tipo,
                'naturaleza' => $c->impuesto?->naturaleza_default,
                'jurisdiccion' => $c->impuesto?->jurisdiccion,
                'inscripto' => (bool) $c->inscripto,
                'es_agente_percepcion' => (bool) $c->es_agente_percepcion,
                'es_agente_retencion' => (bool) $c->es_agente_retencion,
                'alicuota' => $c->alicuota !== null ? (string) (float) $c->alicuota : '',
                'alicuota_minimo_base' => $c->alicuota_minimo_base !== null ? (string) (float) $c->alicuota_minimo_base : '',
                'numero_inscripcion' => $c->numero_inscripcion ?? '',
                'vigente_desde' => $c->vigente_desde?->format('Y-m-d') ?? '',
                'vigente_hasta' => $c->vigente_hasta?->format('Y-m-d') ?? '',
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.configuracion.cuit-impuestos', [
            'impuestosDisponibles' => $this->impuestosDisponibles,
        ]);
    }
}
