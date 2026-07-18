<?php

namespace App\Livewire\Configuracion;

use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\TenantService;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Tienda Online de la sucursal (RF-T10 + RF-T7 + RF-T6 parcial, spec
 * tienda-online): apartado embebido en ConfiguracionDelivery.
 *
 * Administra el registro GLOBAL `config.tiendas` de la sucursal activa
 * (D15: una tienda = una sucursal = un slug): creación con slug sugerido,
 * habilitada, IDs de analytics (GA4 + Meta Pixel) y el tema visual (design
 * tokens que `GET /tiendas/{slug}` sirve a bcn-tienda).
 *
 * Sub-componente EMBEBIDO: guarda SOLO sobre `config.tiendas` — no toca el
 * JSON config_delivery del padre. Requiere permiso `func.tienda.config`.
 */
class ConfiguracionTienda extends Component
{
    use SucursalAware;

    public ?int $tiendaId = null;

    public string $slug = '';

    public bool $habilitada = false;

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
            $this->habilitada = false;
            $this->ga4MeasurementId = '';
            $this->metaPixelId = '';
            $this->aplicarTema(Tienda::TEMA_DEFAULTS);

            return;
        }

        $this->tiendaId = $tienda->id;
        $this->slug = $tienda->slug;
        $this->habilitada = (bool) $tienda->habilitada;
        $this->ga4MeasurementId = (string) ($tienda->ga4_measurement_id ?? '');
        $this->metaPixelId = (string) ($tienda->meta_pixel_id ?? '');
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

    // ==================== CREAR ====================

    public function crearTienda(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.tienda.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar la tienda online'));

            return;
        }

        $comercioId = $this->comercioActualId();
        $sucursal = Sucursal::find($this->sucursalActual());

        if (! $comercioId || ! $sucursal) {
            return;
        }

        if ($this->tiendaActual()) {
            $this->cargar();

            return;
        }

        try {
            Tienda::create([
                'comercio_id' => $comercioId,
                'sucursal_id' => (int) $sucursal->id,
                'slug' => $this->slugSugerido($comercioId, $sucursal),
                'habilitada' => false,
            ]);

            $this->cargar();
            $this->dispatch('toast-success', message: __('Tienda creada. Revisá la configuración y habilitala cuando esté lista.'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Slug sugerido: comercio + sucursal slugificados, único global (la URL
     * pública identifica comercio+sucursal, D15). Ante colisión suma sufijo.
     */
    protected function slugSugerido(int $comercioId, Sucursal $sucursal): string
    {
        $comercio = app(TenantService::class)->getComercio();
        $base = Str::slug(trim(($comercio->nombre ?? '').' '.$sucursal->nombre));
        $base = Str::limit($base ?: 'tienda-'.$comercioId, 55, '');

        $slug = $base;
        $i = 2;
        while (Tienda::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
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
                'habilitada' => $this->habilitada,
                'ga4_measurement_id' => $this->ga4MeasurementId !== '' ? strtoupper($this->ga4MeasurementId) : null,
                'meta_pixel_id' => $this->metaPixelId !== '' ? $this->metaPixelId : null,
                'tema' => $this->temaDesdeForm(),
            ]);

            $this->dispatch('toast-success', message: __('Configuración de la tienda guardada'));
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
        ]);
    }
}
