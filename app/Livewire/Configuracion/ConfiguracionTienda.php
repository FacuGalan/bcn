<?php

namespace App\Livewire\Configuracion;

use App\Models\Tienda;
use App\Services\ImagenTiendaService;
use App\Services\TenantService;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tienda Online de la sucursal (RF-T10/RF-T11 + RF-T7 + RF-T6 parcial,
 * spec tienda-online): apartado embebido en ConfiguracionDelivery.
 *
 * Administra el registro GLOBAL `config.tiendas` de la sucursal activa
 * (D15: una tienda = una sucursal = un slug): slug, IDs de analytics
 * (GA4 + Meta Pixel) y el tema visual (design tokens que
 * `GET /tiendas/{slug}` sirve a bcn-tienda).
 *
 * Sub-componente EMBEBIDO: guarda SOLO sobre `config.tiendas` — no toca el
 * JSON config_delivery del padre. La CREACIÓN y `habilitada` (publicada)
 * son del PADRE (switch maestro RF-T11, único escritor): este componente
 * se monta solo cuando la tienda ya existe. Permiso `func.tienda.config`.
 */
class ConfiguracionTienda extends Component
{
    use SucursalAware, WithFileUploads;

    /**
     * Estado PERSISTIDO de `habilitada`, montado por el PADRE (RF-T12): con
     * true el visor embebe la tienda real (la API 404ea despublicadas); con
     * false cae al mock. El padre lo re-monta vía wire:key al cambiar.
     */
    public bool $publicadaPersistida = false;

    public ?int $tiendaId = null;

    public string $slug = '';

    public string $ga4MeasurementId = '';

    public string $metaPixelId = '';

    // ==================== TEMA (design tokens, RF-T6) ====================

    public string $colorPrimario = '';

    public string $colorAcento = '';

    public string $colorFondo = '';

    public string $colorSuperficie = '';

    public string $colorTexto = '';

    public string $fuente = 'system';

    public string $radios = 'md';

    public string $densidad = 'normal';

    // ==================== LOGO Y PORTADA (RF-T11) ====================

    /**
     * Uploads pendientes: nada persiste hasta "Guardar tienda" (coherente
     * con el resto del form); el preview mientras tanto usa temporaryUrl().
     * El procesamiento definitivo (re-encode WebP) lo hace ImagenTiendaService.
     */
    public $logoUpload = null;

    public $portadaUpload = null;

    public string $logoPathActual = '';

    public string $portadaPathActual = '';

    public function mount(): void
    {
        $this->cargar();
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->cargar();
    }

    // ==================== CARGA ====================

    protected function cargar(): void
    {
        $this->resetValidation();

        $tienda = $this->tiendaActual();

        if (! $tienda) {
            $this->tiendaId = null;
            $this->slug = '';
            $this->ga4MeasurementId = '';
            $this->metaPixelId = '';
            $this->logoUpload = null;
            $this->portadaUpload = null;
            $this->logoPathActual = '';
            $this->portadaPathActual = '';
            $this->aplicarTema(Tienda::TEMA_DEFAULTS);

            return;
        }

        $this->tiendaId = $tienda->id;
        $this->slug = $tienda->slug;
        $this->ga4MeasurementId = (string) ($tienda->ga4_measurement_id ?? '');
        $this->metaPixelId = (string) ($tienda->meta_pixel_id ?? '');
        $this->logoUpload = null;
        $this->portadaUpload = null;
        $this->logoPathActual = (string) ($tienda->logo_path ?? '');
        $this->portadaPathActual = (string) ($tienda->portada_path ?? '');
        $this->aplicarTema($tienda->temaCompleto());
    }

    protected function tiendaActual(): ?Tienda
    {
        $comercioId = $this->comercioActualId();
        $sucursalId = (int) $this->sucursalActual();

        if (! $comercioId || ! $sucursalId) {
            return null;
        }

        return Tienda::where('comercio_id', $comercioId)
            ->where('sucursal_id', $sucursalId)
            ->first();
    }

    protected function comercioActualId(): int
    {
        return (int) (app(TenantService::class)->getComercio()?->id ?? 0);
    }

    protected function aplicarTema(array $tema): void
    {
        $this->colorPrimario = (string) ($tema['colores']['primario'] ?? Tienda::TEMA_DEFAULTS['colores']['primario']);
        $this->colorAcento = (string) ($tema['colores']['acento'] ?? Tienda::TEMA_DEFAULTS['colores']['acento']);
        $this->colorFondo = (string) ($tema['colores']['fondo'] ?? Tienda::TEMA_DEFAULTS['colores']['fondo']);
        $this->colorSuperficie = (string) ($tema['colores']['superficie'] ?? Tienda::TEMA_DEFAULTS['colores']['superficie']);
        $this->colorTexto = (string) ($tema['colores']['texto'] ?? Tienda::TEMA_DEFAULTS['colores']['texto']);
        $this->fuente = in_array($tema['tipografia']['fuente'] ?? '', Tienda::FUENTES_DISPONIBLES, true)
            ? $tema['tipografia']['fuente']
            : Tienda::TEMA_DEFAULTS['tipografia']['fuente'];
        $this->radios = in_array($tema['radios'] ?? '', Tienda::RADIOS_DISPONIBLES, true)
            ? $tema['radios']
            : Tienda::TEMA_DEFAULTS['radios'];
        $this->densidad = in_array($tema['densidad'] ?? '', Tienda::DENSIDADES_DISPONIBLES, true)
            ? $tema['densidad']
            : Tienda::TEMA_DEFAULTS['densidad'];
    }

    // ==================== GUARDAR ====================

    public function guardarTienda(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.tienda.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar la tienda online'));

            return;
        }

        $tienda = $this->tiendaActual();
        if (! $tienda || $tienda->id !== $this->tiendaId) {
            return;
        }

        $this->slug = Str::slug(trim($this->slug));

        $this->validate([
            'slug' => 'required|string|min:3|max:60',
            'ga4MeasurementId' => ['nullable', 'string', 'max:30', 'regex:/^G-[A-Z0-9]+$/i'],
            'metaPixelId' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'logoUpload' => 'nullable|image|max:5120',
            'portadaUpload' => 'nullable|image|max:5120',
            'colorPrimario' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'colorAcento' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'colorFondo' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'colorSuperficie' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'colorTexto' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'fuente' => 'required|in:'.implode(',', Tienda::FUENTES_DISPONIBLES),
            'radios' => 'required|in:'.implode(',', Tienda::RADIOS_DISPONIBLES),
            'densidad' => 'required|in:'.implode(',', Tienda::DENSIDADES_DISPONIBLES),
        ], [
            'slug.required' => __('Ingresá la dirección (slug) de la tienda'),
            'slug.min' => __('La dirección de la tienda debe tener al menos 3 caracteres'),
            'ga4MeasurementId.regex' => __('El ID de GA4 tiene formato G-XXXXXXXXXX'),
            'metaPixelId.regex' => __('El ID del Pixel de Meta es numérico'),
        ]);

        if (Tienda::where('slug', $this->slug)->where('id', '!=', $tienda->id)->exists()) {
            $this->addError('slug', __('Esa dirección ya está en uso por otra tienda'));

            return;
        }

        try {
            $tienda->update([
                'slug' => $this->slug,
                'ga4_measurement_id' => $this->ga4MeasurementId !== '' ? strtoupper($this->ga4MeasurementId) : null,
                'meta_pixel_id' => $this->metaPixelId !== '' ? $this->metaPixelId : null,
                'tema' => $this->temaDesdeForm(),
            ]);

            // Logo/portada pendientes: recién acá se procesan (re-encode
            // WebP + reemplazo del anterior) — nada persiste sin guardar.
            $imagenes = app(ImagenTiendaService::class);
            if ($this->logoUpload) {
                $imagenes->actualizarLogo($tienda, $this->logoUpload);
                $this->logoUpload = null;
            }
            if ($this->portadaUpload) {
                $imagenes->actualizarPortada($tienda, $this->portadaUpload);
                $this->portadaUpload = null;
            }
            $tienda->refresh();
            $this->logoPathActual = (string) ($tienda->logo_path ?? '');
            $this->portadaPathActual = (string) ($tienda->portada_path ?? '');

            // El visor recarga el iframe: lo persistido ya incluye todo.
            $this->dispatch('tienda-guardada');
            $this->dispatch('toast-success', message: __('Configuración de la tienda guardada'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== LOGO Y PORTADA (RF-T11) ====================

    /**
     * El visor en vivo (RF-T12) recibe las URLs de preview por evento: son
     * server-rendered (temporaryUrl del upload pendiente) y no entanglables.
     */
    public function updatedLogoUpload(): void
    {
        $this->emitirImagenesPreview();
    }

    public function updatedPortadaUpload(): void
    {
        $this->emitirImagenesPreview();
    }

    protected function emitirImagenesPreview(): void
    {
        $this->dispatch(
            'tienda-preview-imagenes',
            logoUrl: $this->previewUrl($this->logoUpload, $this->logoPathActual),
            portadaUrl: $this->previewUrl($this->portadaUpload, $this->portadaPathActual),
        );
    }

    /** Descarta el upload pendiente o borra la imagen ya guardada. */
    public function eliminarLogo(): void
    {
        $this->eliminarImagen('logoUpload', 'logoPathActual', 'eliminarLogo');
    }

    public function eliminarPortada(): void
    {
        $this->eliminarImagen('portadaUpload', 'portadaPathActual', 'eliminarPortada');
    }

    protected function eliminarImagen(string $propUpload, string $propActual, string $metodoService): void
    {
        if (! auth()->user()?->hasPermissionTo('func.tienda.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar la tienda online'));

            return;
        }

        // Upload pendiente: descartarlo alcanza (no se persistió nada).
        if ($this->{$propUpload}) {
            $this->{$propUpload} = null;

            return;
        }

        $tienda = $this->tiendaActual();
        if (! $tienda || $this->{$propActual} === '') {
            return;
        }

        try {
            app(ImagenTiendaService::class)->{$metodoService}($tienda);
            $this->{$propActual} = '';
            $this->emitirImagenesPreview();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function restablecerTema(): void
    {
        $this->aplicarTema(Tienda::TEMA_DEFAULTS);
    }

    /** @return array{colores: array<string,string>, tipografia: array{fuente: string}, radios: string, densidad: string} */
    protected function temaDesdeForm(): array
    {
        return [
            'colores' => [
                'primario' => strtolower($this->colorPrimario),
                'acento' => strtolower($this->colorAcento),
                'fondo' => strtolower($this->colorFondo),
                'superficie' => strtolower($this->colorSuperficie),
                'texto' => strtolower($this->colorTexto),
            ],
            'tipografia' => ['fuente' => $this->fuente],
            'radios' => $this->radios,
            'densidad' => $this->densidad,
        ];
    }

    // ==================== RENDER ====================

    public function render()
    {
        return view('livewire.configuracion.configuracion-tienda', [
            'urlPublica' => $this->slug !== ''
                ? config('tienda.url').'/tienda/'.$this->slug
                : null,
            'puedeConfigurar' => (bool) auth()->user()?->hasPermissionTo('func.tienda.config'),
            // Origen (scheme://host[:port]) del frontend de la tienda: es el
            // targetOrigin del postMessage del visor (RF-T12), nunca '*'.
            'origenTienda' => $this->origenTienda(),
            // Para el uploader y el preview en vivo: upload pendiente gana
            // sobre lo guardado. temporaryUrl() puede fallar si el tmp
            // expiró — degradar a lo persistido.
            'logoPreviewUrl' => $this->previewUrl($this->logoUpload, $this->logoPathActual),
            'portadaPreviewUrl' => $this->previewUrl($this->portadaUpload, $this->portadaPathActual),
        ]);
    }

    protected function origenTienda(): string
    {
        $partes = parse_url((string) config('tienda.url'));
        if (empty($partes['scheme']) || empty($partes['host'])) {
            return '';
        }

        return $partes['scheme'].'://'.$partes['host'].(isset($partes['port']) ? ':'.$partes['port'] : '');
    }

    protected function previewUrl($upload, string $pathActual): ?string
    {
        if ($upload) {
            try {
                return $upload->temporaryUrl();
            } catch (Exception) {
                // cae al persistido
            }
        }

        return $pathActual !== '' ? asset('storage/'.ltrim($pathActual, '/')) : null;
    }
}
