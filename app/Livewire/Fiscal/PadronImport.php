<?php

namespace App\Livewire\Fiscal;

use App\Services\Fiscal\PadronImportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importador de padrón de percepción IIBB ARBA/AGIP (sistema-impositivo Fase 10b, RF-14).
 *
 * Sube el archivo de padrón de la agencia, lo filtra contra los CUIT de los
 * clientes del comercio y upsertea su perfil fiscal por sujeto
 * (`cliente_impuesto_configs`, origen padrón). Global (no sucursal-aware).
 * Permiso: `func.fiscal.configuracion`.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-14, Fase 10b).
 */
#[Lazy]
#[Layout('layouts.app')]
class PadronImport extends Component
{
    use WithFileUploads;

    public string $agencia = PadronImportService::AGENCIA_ARBA;

    public $archivo = null;

    /** Resumen de la última corrida (ResumenImportacion::toArray) o null. */
    public ?array $resumen = null;

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.configuracion')) {
            abort(403, __('No tiene permiso para importar padrones'));
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-form />
        HTML;
    }

    /**
     * Reglas de validación de la subida.
     *
     * Seguridad (defensa en profundidad): solo se acepta el padrón COMPRIMIDO
     * tal cual lo baja el usuario de la agencia (.zip oficial de ARBA/AGIP, o
     * .gz) — el .txt plano pesa demasiado para subirlo por la web. Solo esas
     * extensiones + MIMEs, más el techo de tamaño. El `accept` del input es solo
     * una pista del navegador y se puede saltear; esta es la barrera real del
     * lado servidor. El archivo nunca se ejecuta ni se incluye: se descomprime y
     * lee por streaming (fgets) y se descarta lo que no parsea como fila.
     */
    protected function rules(): array
    {
        return [
            'agencia' => 'required|in:'.PadronImportService::AGENCIA_ARBA.','.PadronImportService::AGENCIA_AGIP,
            'archivo' => [
                'required',
                'file',
                'extensions:zip,gz',
                'mimetypes:application/zip,application/x-zip-compressed,application/gzip,application/x-gzip,application/octet-stream',
                'max:102400',
            ],
        ];
    }

    /** Mensajes claros en vez de los `validation.*` por defecto. */
    protected function messages(): array
    {
        return [
            'agencia.required' => __('Elegí una agencia (ARBA o AGIP).'),
            'agencia.in' => __('La agencia seleccionada no es válida.'),
            'archivo.required' => __('Seleccioná el archivo comprimido del padrón.'),
            'archivo.file' => __('No se pudo leer el archivo subido. Probá de nuevo.'),
            'archivo.extensions' => __('El padrón debe subirse comprimido (.zip o .gz).'),
            'archivo.mimetypes' => __('El padrón debe subirse comprimido (.zip o .gz).'),
            'archivo.max' => __('El archivo supera el tamaño máximo permitido (100 MB).'),
        ];
    }

    public function updatedArchivo(): void
    {
        $this->resumen = null;
        $this->validateOnly('archivo');
    }

    public function updatedAgencia(): void
    {
        $this->resumen = null;
    }

    public function importar(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.configuracion')) {
            $this->dispatch('notify', message: __('No tiene permiso para importar padrones'), type: 'error');

            return;
        }

        $this->validate();

        try {
            $resumen = app(PadronImportService::class)->importar(
                $this->archivo->getRealPath(),
                $this->agencia,
            );

            $this->resumen = $resumen->toArray();
            $this->dispatch('notify', message: __(':n clientes actualizados desde el padrón', ['n' => $resumen->impactadas()]), type: 'success');
            $this->reset('archivo');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', message: __('Error al importar el padrón: :msg', ['msg' => $e->getMessage()]), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.fiscal.padron-import');
    }
}
