<?php

namespace App\Livewire\Configuracion;

use App\Models\Impresora;
use App\Models\ImpresoraSucursalCaja;
use App\Models\ImpresoraTipoDocumento;
use App\Models\ConfiguracionImpresion;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Impresoras extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filterTipo = 'all';

    // Modal CRUD
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $impresoraId = null;

    // Formulario impresora
    public string $nombre = '';
    public string $nombreSistema = '';
    public string $tipo = 'termica';
    public string $formatoPapel = '80mm';
    public int $anchoCaracteres = 48;
    public bool $activa = true;
    public array $configuracion = [];

    // Modal asignaciones
    public bool $showModalAsignacion = false;
    public ?int $impresoraAsignar = null;
    public array $asignaciones = [];

    // Modal configuracion sucursal
    public bool $showModalConfig = false;
    public ?int $sucursalConfigId = null;
    public bool $configImpresionAutomaticaVenta = true;
    public bool $configImpresionAutomaticaFactura = true;
    public bool $configAbrirCajonEfectivo = true;
    public bool $configCortarPapelAutomatico = true;
    public string $configTextoPieTicket = '';
    public string $configTextoLegalFactura = '';

    // Impresoras detectadas del sistema (via JS)
    public array $impresorasDetectadas = [];

    // Sucursales disponibles
    public $sucursales;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterTipo' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->sucursales = Sucursal::where('activa', true)
            ->with(['cajas' => fn($q) => $q->where('activo', true)])
            ->orderBy('nombre')
            ->get();
    }

    // Listeners para comunicacion JS
    #[On('impresoras-detectadas')]
    public function recibirImpresorasDetectadas(array $impresoras): void
    {
        $this->impresorasDetectadas = $impresoras;
    }

    public function seleccionarImpresoraDetectada(string $nombreSistema): void
    {
        $this->nombreSistema = $nombreSistema;

        // Auto-detectar tipo basado en nombre
        $nombreLower = strtolower($nombreSistema);
        if (str_contains($nombreLower, 'thermal') ||
            str_contains($nombreLower, 'pos') ||
            str_contains($nombreLower, 'receipt') ||
            str_contains($nombreLower, '58mm') ||
            str_contains($nombreLower, '80mm')) {
            $this->tipo = 'termica';
            $this->formatoPapel = '80mm';
            $this->anchoCaracteres = 48;
        } else {
            $this->tipo = 'laser_inkjet';
            $this->formatoPapel = 'a4';
            $this->anchoCaracteres = 80;
        }
    }

    public function updatedTipo(): void
    {
        $this->formatoPapel = $this->tipo === 'termica' ? '80mm' : 'a4';
        $this->anchoCaracteres = Impresora::ANCHOS_CARACTERES[$this->formatoPapel] ?? 48;
    }

    public function updatedFormatoPapel(): void
    {
        $this->anchoCaracteres = Impresora::ANCHOS_CARACTERES[$this->formatoPapel] ?? 48;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // CRUD Impresoras
    public function create(): void
    {
        $this->reset(['nombre', 'nombreSistema', 'tipo', 'formatoPapel', 'anchoCaracteres', 'activa', 'configuracion', 'impresoraId', 'impresorasDetectadas']);
        $this->activa = true;
        $this->tipo = 'termica';
        $this->formatoPapel = '80mm';
        $this->anchoCaracteres = 48;
        $this->editMode = false;
        $this->showModal = true;

        // Pedir deteccion de impresoras al JS
        $this->dispatch('detectar-impresoras');
    }

    public function edit(int $id): void
    {
        $impresora = Impresora::findOrFail($id);

        $this->impresoraId = $impresora->id;
        $this->nombre = $impresora->nombre;
        $this->nombreSistema = $impresora->nombre_sistema;
        $this->tipo = $impresora->tipo;
        $this->formatoPapel = $impresora->formato_papel;
        $this->anchoCaracteres = $impresora->ancho_caracteres;
        $this->activa = $impresora->activa;
        $this->configuracion = $impresora->configuracion ?? [];

        $this->editMode = true;
        $this->showModal = true;

        $this->dispatch('detectar-impresoras');
    }

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'nombreSistema' => 'required|string|max:255',
            'tipo' => 'required|in:termica,laser_inkjet',
            'formatoPapel' => 'required|in:80mm,58mm,a4,carta',
            'anchoCaracteres' => 'required|integer|min:20|max:100',
        ], [
            'nombre.required' => __('El nombre es obligatorio'),
            'nombreSistema.required' => __('Debe seleccionar una impresora del sistema'),
        ]);

        $data = [
            'nombre' => $this->nombre,
            'nombre_sistema' => $this->nombreSistema,
            'tipo' => $this->tipo,
            'formato_papel' => $this->formatoPapel,
            'ancho_caracteres' => $this->anchoCaracteres,
            'activa' => $this->activa,
            'configuracion' => $this->configuracion,
        ];

        if ($this->editMode) {
            Impresora::find($this->impresoraId)->update($data);
            $message = __('Impresora actualizada correctamente');
        } else {
            Impresora::create($data);
            $message = __('Impresora creada correctamente');
        }

        $this->dispatch('notify', message: $message, type: 'success');
        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $impresora = Impresora::findOrFail($id);
        $impresora->delete();
        $this->dispatch('notify', message: __('Impresora eliminada correctamente'), type: 'success');
    }

    public function toggleStatus(int $id): void
    {
        $impresora = Impresora::findOrFail($id);
        $impresora->activa = !$impresora->activa;
        $impresora->save();

        $status = $impresora->activa ? __('activada') : __('desactivada');
        $this->dispatch('notify', message: __('Impresora :status', ['status' => $status]), type: 'success');
    }

    // Asignaciones
    public function abrirAsignaciones(int $impresoraId): void
    {
        $this->impresoraAsignar = $impresoraId;
        $impresora = Impresora::with('asignaciones.tiposDocumento')->find($impresoraId);

        $this->asignaciones = [];
        foreach ($impresora->asignaciones as $asig) {
            $key = $asig->sucursal_id . '_' . ($asig->caja_id ?? 'all');
            $this->asignaciones[$key] = [
                'id' => $asig->id,
                'sucursal_id' => $asig->sucursal_id,
                'caja_id' => $asig->caja_id,
                'es_defecto' => $asig->es_defecto,
                'tipos' => $asig->tiposDocumento->pluck('tipo_documento')->toArray(),
            ];
        }

        $this->showModalAsignacion = true;
    }

    public function toggleAsignacion(int $sucursalId, $cajaId, string $tipoDoc): void
    {
        $cajaId = $cajaId === 'all' ? null : (int)$cajaId;
        $key = $sucursalId . '_' . ($cajaId ?? 'all');

        if (!isset($this->asignaciones[$key])) {
            $this->asignaciones[$key] = [
                'sucursal_id' => $sucursalId,
                'caja_id' => $cajaId,
                'es_defecto' => false,
                'tipos' => [],
            ];
        }

        $tipos = $this->asignaciones[$key]['tipos'];
        if (in_array($tipoDoc, $tipos)) {
            $this->asignaciones[$key]['tipos'] = array_values(array_diff($tipos, [$tipoDoc]));
        } else {
            $this->asignaciones[$key]['tipos'][] = $tipoDoc;
        }

        // Limpiar asignaciones vacias
        if (empty($this->asignaciones[$key]['tipos'])) {
            unset($this->asignaciones[$key]);
        }
    }

    public function toggleDefecto(int $sucursalId, $cajaId): void
    {
        $cajaId = $cajaId === 'all' ? null : (int)$cajaId;
        $key = $sucursalId . '_' . ($cajaId ?? 'all');

        if (isset($this->asignaciones[$key])) {
            $this->asignaciones[$key]['es_defecto'] = !$this->asignaciones[$key]['es_defecto'];
        }
    }

    public function tieneAsignacion(int $sucursalId, $cajaId, string $tipoDoc): bool
    {
        $cajaId = $cajaId === 'all' ? null : (int)$cajaId;
        $key = $sucursalId . '_' . ($cajaId ?? 'all');

        return isset($this->asignaciones[$key]) &&
               in_array($tipoDoc, $this->asignaciones[$key]['tipos'] ?? []);
    }

    public function esDefecto(int $sucursalId, $cajaId): bool
    {
        $cajaId = $cajaId === 'all' ? null : (int)$cajaId;
        $key = $sucursalId . '_' . ($cajaId ?? 'all');

        return isset($this->asignaciones[$key]) &&
               ($this->asignaciones[$key]['es_defecto'] ?? false);
    }

    public function guardarAsignaciones(): void
    {
        DB::connection('pymes_tenant')->transaction(function () {
            // Eliminar asignaciones existentes
            ImpresoraSucursalCaja::where('impresora_id', $this->impresoraAsignar)->delete();

            foreach ($this->asignaciones as $key => $asig) {
                if (empty($asig['tipos'])) continue;

                $impresoraSucursalCaja = ImpresoraSucursalCaja::create([
                    'impresora_id' => $this->impresoraAsignar,
                    'sucursal_id' => $asig['sucursal_id'],
                    'caja_id' => $asig['caja_id'],
                    'es_defecto' => $asig['es_defecto'] ?? false,
                ]);

                foreach ($asig['tipos'] as $tipo) {
                    ImpresoraTipoDocumento::create([
                        'impresora_sucursal_caja_id' => $impresoraSucursalCaja->id,
                        'tipo_documento' => $tipo,
                        'copias' => 1,
                        'activo' => true,
                    ]);
                }
            }
        });

        $this->dispatch('notify', message: __('Asignaciones guardadas correctamente'), type: 'success');
        $this->showModalAsignacion = false;
    }

    // Configuracion de sucursal
    public function abrirConfigSucursal(int $sucursalId): void
    {
        $this->sucursalConfigId = $sucursalId;
        $config = ConfiguracionImpresion::obtenerParaSucursal($sucursalId);

        $this->configImpresionAutomaticaVenta = $config->impresion_automatica_venta;
        $this->configImpresionAutomaticaFactura = $config->impresion_automatica_factura;
        $this->configAbrirCajonEfectivo = $config->abrir_cajon_efectivo;
        $this->configCortarPapelAutomatico = $config->cortar_papel_automatico;
        $this->configTextoPieTicket = $config->texto_pie_ticket ?? '';
        $this->configTextoLegalFactura = $config->texto_legal_factura ?? '';

        $this->showModalConfig = true;
    }

    public function guardarConfigSucursal(): void
    {
        $config = ConfiguracionImpresion::obtenerParaSucursal($this->sucursalConfigId);
        $config->update([
            'impresion_automatica_venta' => $this->configImpresionAutomaticaVenta,
            'impresion_automatica_factura' => $this->configImpresionAutomaticaFactura,
            'abrir_cajon_efectivo' => $this->configAbrirCajonEfectivo,
            'cortar_papel_automatico' => $this->configCortarPapelAutomatico,
            'texto_pie_ticket' => $this->configTextoPieTicket ?: null,
            'texto_legal_factura' => $this->configTextoLegalFactura ?: null,
        ]);

        $this->dispatch('notify', message: __('Configuración guardada correctamente'), type: 'success');
        $this->showModalConfig = false;
    }

    // Prueba de impresion
    public function probarImpresion(int $id): void
    {
        $impresora = Impresora::findOrFail($id);

        $this->dispatch('probar-impresion', [
            'impresoraId' => $impresora->id,
            'impresora' => $impresora->nombre_sistema,
            'tipo' => $impresora->tipo,
        ]);
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->showModalAsignacion = false;
        $this->showModalConfig = false;
    }

    protected function getImpresoras()
    {
        $query = Impresora::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('nombre_sistema', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterTipo !== 'all') {
            $query->where('tipo', $this->filterTipo);
        }

        return $query->orderBy('nombre')->paginate(10);
    }

    public function render()
    {
        return view('livewire.configuracion.impresoras', [
            'impresoras' => $this->getImpresoras(),
            'tiposDocumento' => ImpresoraTipoDocumento::TIPOS_DOCUMENTO,
        ]);
    }
}
