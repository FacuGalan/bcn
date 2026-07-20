<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Tienda (RF-13, D15) — BD CONFIG.
 *
 * Registro GLOBAL de tiendas online: la tienda es POR SUCURSAL (no por
 * comercio) — el slug de la URL pública identifica comercio+sucursal sin
 * abrir la BD tenant, y el middleware api.tenant lo usa para configurar el
 * contexto. `sucursal_id` es FK lógico a la sucursal tenant del comercio.
 */
class Tienda extends Model
{
    protected $connection = 'config';

    protected $table = 'tiendas';

    /**
     * Design tokens default de la tienda (Principio 10 del spec tienda-online).
     * `tema` NULL o parcial en BD → se mergea sobre estos defaults; las claves
     * son CONTRATO con bcn-tienda (agregar es aditivo, renombrar rompe).
     */
    public const TEMA_DEFAULTS = [
        'colores' => [
            'primario' => '#4f46e5',
            'acento' => '#f59e0b',
            'fondo' => '#f9fafb',
            'superficie' => '#ffffff',
            'texto' => '#111827',
        ],
        'tipografia' => [
            'fuente' => 'system',
        ],
        'radios' => 'md',
        'densidad' => 'normal',
        // RF-T13 — cada default replica el comportamiento previo al RF:
        // una tienda que no toca nada se ve EXACTAMENTE igual que antes.
        'portada' => [
            'overlay' => true,        // fade con color primario sobre la portada
            'posicion' => 'center',   // encuadre vertical: top|center|bottom
        ],
        'textos' => [
            'slogan' => '',           // hero, bajo el nombre ('' = no se muestra)
            'descripcion' => '',      // sección propia de la home ('' = sin sección)
        ],
        'redes' => [
            'facebook' => '',         // URL del perfil ('' = sin botón)
            'instagram' => '',
        ],
        'catalogo' => [
            'layout' => 'grilla',     // grilla|lista
        ],
        'destacados' => [
            'modo' => 'banner',       // banner|tarjeta_grande|ninguno
            'adorno' => 'ninguno',    // glow|badge|ambos|ninguno (solo tarjeta_grande)
        ],
        'promos' => [
            'mostrar_home' => false,  // aviso "Promociones de hoy" en la home
        ],
    ];

    /** Fuentes self-hosted disponibles en bcn-tienda (catálogo cerrado). */
    public const FUENTES_DISPONIBLES = ['system', 'inter', 'poppins', 'roboto', 'montserrat', 'lora'];

    public const RADIOS_DISPONIBLES = ['none', 'sm', 'md', 'lg', 'full'];

    public const DENSIDADES_DISPONIBLES = ['compacta', 'normal', 'amplia'];

    // RF-T13 — catálogos cerrados de las opciones nuevas (validación panel).

    public const POSICIONES_PORTADA = ['top', 'center', 'bottom'];

    public const LAYOUTS_CATALOGO = ['grilla', 'lista'];

    public const MODOS_DESTACADOS = ['banner', 'tarjeta_grande', 'ninguno'];

    public const ADORNOS_DESTACADOS = ['glow', 'badge', 'ambos', 'ninguno'];

    /** Seteos de conducta de la tienda (Principio 10). v1: sin seteos, objeto reservado. */
    public const COMPORTAMIENTO_DEFAULTS = [];

    protected $fillable = [
        'comercio_id',
        'sucursal_id',
        'slug',
        'habilitada',
        'dominio_propio',
        'ga4_measurement_id',
        'meta_pixel_id',
        'tema',
        'logo_path',
        'portada_path',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'sucursal_id' => 'integer',
        'tema' => 'array',
    ];

    /** Tema efectivo: defaults del core con merge profundo del JSON persistido. */
    public function temaCompleto(): array
    {
        return array_replace_recursive(self::TEMA_DEFAULTS, $this->tema ?? []);
    }

    /**
     * URLs root-relative de logo/portada (mismo criterio que
     * Articulo::imagenUrl(): no Storage::url() porque arma con APP_URL).
     * La API las absolutiza con url() para que sirvan cross-origin.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? '/storage/'.ltrim($this->logo_path, '/') : null;
    }

    public function portadaUrl(): ?string
    {
        return $this->portada_path ? '/storage/'.ltrim($this->portada_path, '/') : null;
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    public function scopeHabilitadas(Builder $query): Builder
    {
        return $query->where('habilitada', true);
    }

    public function scopePorSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
