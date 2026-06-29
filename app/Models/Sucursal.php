<?php

namespace App\Models;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo Sucursal
 *
 * Representa una sucursal de un comercio.
 *
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $logo_path
 * @property bool $es_principal
 * @property int|null $datos_fiscales_id
 * @property bool $activa
 * @property array|null $configuracion
 * @property bool $usa_clave_autorizacion
 * @property string|null $clave_autorizacion
 * @property string $tipo_impresion_factura
 * @property bool $imprime_encabezado_comanda
 * @property bool $agrupa_articulos_venta
 * @property bool $agrupa_articulos_impresion
 * @property bool $facturacion_fiscal_automatica Si emite factura fiscal automáticamente según formas de pago
 * @property bool $usa_whatsapp_escritorio
 * @property bool $envia_whatsapp_comanda
 * @property string|null $mensaje_whatsapp_comanda
 * @property bool $envia_whatsapp_listo
 * @property string|null $mensaje_whatsapp_listo
 */
class Sucursal extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'sucursales';

    // Tipos de impresión en factura
    public const TIPOS_IMPRESION_FACTURA = [
        'solo_datos' => 'Solo datos fiscales',
        'solo_logo' => 'Solo logo',
        'ambos' => 'Logo y datos fiscales',
    ];

    /**
     * Valores por defecto de la personalización de la pantalla cliente (2da
     * pantalla). Se mergean con lo guardado en `config_pantalla_cliente` para
     * que nunca falten keys aunque la columna esté vacía o desactualizada.
     */
    public const CONFIG_PANTALLA_CLIENTE_DEFAULTS = [
        'mostrar_logo' => true,
        'mostrar_nombre' => true,
        'color_fondo' => '#222036',
        'animacion' => 'aurora',      // ninguna | respiracion | aurora
        'color_acento' => '#22d3ee',
        'color_texto' => 'auto',      // auto (contraste según fondo) o hex
        'mensaje_idle' => 'Listo para cobrar',
        'tamano_logo' => 'md',        // sm | md | lg
    ];

    /**
     * Valores por defecto de la personalización del monitor llamador (pantalla
     * Clase B). Se mergean con lo guardado en `config_llamador`.
     */
    public const CONFIG_LLAMADOR_DEFAULTS = [
        'titulo' => 'Pedidos',
        'mostrar_logo' => true,
        'color_fondo' => '#0f172a',
        'color_preparacion' => '#f59e0b', // ámbar (columna "En preparación")
        'color_listo' => '#22c55e',       // verde (columna "Listo / Retirar")
        'sonido' => true,                 // chime al pasar un pedido a "Listo"
        'tamano' => 'normal',             // compacto | normal | grande (densidad base; el auto-fit achica si no entran)
    ];

    /**
     * Provincias de Argentina con código ISO 3166-2 → nombre oficial.
     *
     * Se guarda el código en `sucursales.provincia` (ej. 'AR-B') y se traduce
     * a nombre al armar payloads para servicios externos (Mercado Pago, AFIP).
     * Permite homologar datos entre integraciones sin depender de typos.
     */
    public const PROVINCIAS_AR = [
        'AR-C' => 'Ciudad Autónoma de Buenos Aires',
        'AR-B' => 'Buenos Aires',
        'AR-K' => 'Catamarca',
        'AR-H' => 'Chaco',
        'AR-U' => 'Chubut',
        'AR-X' => 'Córdoba',
        'AR-W' => 'Corrientes',
        'AR-E' => 'Entre Ríos',
        'AR-P' => 'Formosa',
        'AR-Y' => 'Jujuy',
        'AR-L' => 'La Pampa',
        'AR-F' => 'La Rioja',
        'AR-M' => 'Mendoza',
        'AR-N' => 'Misiones',
        'AR-Q' => 'Neuquén',
        'AR-R' => 'Río Negro',
        'AR-A' => 'Salta',
        'AR-J' => 'San Juan',
        'AR-D' => 'San Luis',
        'AR-Z' => 'Santa Cruz',
        'AR-S' => 'Santa Fe',
        'AR-G' => 'Santiago del Estero',
        'AR-V' => 'Tierra del Fuego',
        'AR-T' => 'Tucumán',
    ];

    /**
     * Devuelve el nombre oficial de la provincia ISO guardada en este modelo.
     * Retorna null si el código no está en el catálogo o el campo está vacío.
     */
    public function provinciaNombre(): ?string
    {
        if (empty($this->provincia)) {
            return null;
        }

        return self::PROVINCIAS_AR[$this->provincia] ?? $this->provincia;
    }

    protected $fillable = [
        'nombre', 'nombre_publico', 'codigo', 'direccion', 'telefono', 'email', 'logo_path',
        'es_principal', 'datos_fiscales_id', 'activa', 'configuracion', 'config_pantalla_cliente',
        // Pantallas públicas Clase B (llamador de pedidos, consultor de precios)
        'token_publico', 'config_llamador', 'config_consultor_precios',
        // Numeración de display (turno) + toggle monitor
        'usa_llamador', 'usa_numeracion_display', 'numeracion_display_modo',
        'numeracion_display_horas', 'pedido_display_ultimo_numero', 'pedido_display_segmento_at',
        // Campos de configuración
        'usa_clave_autorizacion', 'clave_autorizacion', 'tipo_impresion_factura',
        'imprime_encabezado_comanda', 'agrupa_articulos_venta', 'agrupa_articulos_impresion',
        'control_stock_venta', 'control_stock_produccion', 'facturacion_fiscal_automatica',
        'usa_whatsapp_escritorio', 'envia_whatsapp_comanda', 'mensaje_whatsapp_comanda',
        'envia_whatsapp_listo', 'mensaje_whatsapp_listo',
        // Pedidos por Mostrador
        'pedido_mostrador_ultimo_numero', 'imprime_comanda_automatico',
        'pedido_conversion_automatica_al_entregar', 'usa_beepers',
        // Geolocalización + Mercado Pago Stores
        'latitud', 'longitud', 'localidad', 'localidad_id', 'provincia',
        'mp_store_id', 'mp_store_external_id',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activa' => 'boolean',
        'configuracion' => 'array',
        'config_pantalla_cliente' => 'array',
        'config_llamador' => 'array',
        'config_consultor_precios' => 'array',
        'usa_llamador' => 'boolean',
        'usa_numeracion_display' => 'boolean',
        'numeracion_display_horas' => 'array',
        'pedido_display_segmento_at' => 'datetime',
        'usa_clave_autorizacion' => 'boolean',
        'imprime_encabezado_comanda' => 'boolean',
        'agrupa_articulos_venta' => 'boolean',
        'agrupa_articulos_impresion' => 'boolean',
        'facturacion_fiscal_automatica' => 'boolean',
        'usa_whatsapp_escritorio' => 'boolean',
        'envia_whatsapp_comanda' => 'boolean',
        'envia_whatsapp_listo' => 'boolean',
        'pedido_mostrador_ultimo_numero' => 'integer',
        'imprime_comanda_automatico' => 'boolean',
        'pedido_conversion_automatica_al_entregar' => 'boolean',
        'usa_beepers' => 'boolean',
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'localidad_id' => 'integer',
    ];

    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * ¿Alguna caja de esta sucursal usa la pantalla orientada al cliente (2da
     * pantalla)? Gatea los botones de personalizar/instalar la 2da pantalla en
     * la config de la sucursal (la config es por sucursal, pero solo tiene
     * sentido si al menos un punto la usa).
     */
    public function usaPantallaCliente(): bool
    {
        return $this->cajas()->where('usa_pantalla_cliente', true)->exists();
    }

    public function estaSincronizadaEnMp(): bool
    {
        return ! empty($this->mp_store_id);
    }

    // Relaciones

    /**
     * Localidad del domicilio físico (tabla en config, ref soft sin FK cross-DB).
     * Domicilio estructurado de la sucursal (RF-11, Fase 9), independiente de
     * tener CUIT o integración de pago.
     */
    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'sucursal_id');
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class, 'sucursal_id');
    }

    public function gruposCierre(): HasMany
    {
        return $this->hasMany(GrupoCierre::class, 'sucursal_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'sucursal_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'sucursal_id');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'articulos_sucursales', 'sucursal_id', 'articulo_id')
            ->withPivot('activo')
            ->withTimestamps();
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'clientes_sucursales', 'sucursal_id', 'cliente_id')
            ->withPivot('lista_precio_id', 'descuento_porcentaje', 'limite_credito', 'saldo_actual', 'activo')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }

    // Métodos auxiliares
    public function tieneArticulo(int $articuloId): bool
    {
        return $this->articulos()->where('articulo_id', $articuloId)->wherePivot('activo', true)->exists();
    }

    public function getStock(int $articuloId): ?Stock
    {
        return $this->stocks()->where('articulo_id', $articuloId)->first();
    }

    public function tieneStockDisponible(int $articuloId, float $cantidad): bool
    {
        $stock = $this->getStock($articuloId);

        return $stock && $stock->cantidad >= $cantidad;
    }

    /**
     * Relación con CUITs asignados
     */
    public function cuits(): BelongsToMany
    {
        return $this->belongsToMany(Cuit::class, 'cuit_sucursal')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    /**
     * Obtiene la URL del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Personalización de la pantalla cliente (2da pantalla) de esta sucursal,
     * con los DEFAULTS mergeados para garantizar todas las keys. Lo consumen el
     * modal de configuración (carga) y el host del POS (envío por BroadcastChannel).
     */
    public function getConfigPantallaCliente(): array
    {
        $guardada = is_array($this->config_pantalla_cliente) ? $this->config_pantalla_cliente : [];

        return array_merge(self::CONFIG_PANTALLA_CLIENTE_DEFAULTS, $guardada);
    }

    /**
     * Personalización del monitor llamador (pantalla Clase B) con los DEFAULTS
     * mergeados para garantizar todas las keys.
     */
    public function getConfigLlamador(): array
    {
        $guardada = is_array($this->config_llamador) ? $this->config_llamador : [];

        return array_merge(self::CONFIG_LLAMADOR_DEFAULTS, $guardada);
    }

    /**
     * Horas de reset diario de la numeración de display (0-23), ordenadas y sin
     * duplicados. Default `[6]` (una sola jornada de 6am a 6am).
     *
     * @return list<int>
     */
    public function horasResetDisplay(): array
    {
        $horas = is_array($this->numeracion_display_horas) ? $this->numeracion_display_horas : [];

        $horas = array_values(array_unique(array_filter(
            array_map('intval', $horas),
            fn ($h) => $h >= 0 && $h <= 23
        )));

        sort($horas);

        return $horas ?: [6];
    }

    /**
     * URL del logo a mostrar en la pantalla cliente: el de la sucursal si tiene,
     * si no el del comercio (EmpresaConfig). Se arma con asset() (host del
     * request) — NO con Storage::url() que usa el host fijo de la config y rompe
     * si la app no corre en el puerto de APP_URL.
     */
    public function logoPantallaClienteUrl(): ?string
    {
        $path = $this->logo_path ?: EmpresaConfig::getConfig()->logo_path;

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * Nombre a mostrar en la pantalla cliente: nombre público de la sucursal,
     * o su nombre, o el nombre del comercio como último fallback.
     */
    public function nombrePantallaCliente(): string
    {
        return $this->nombre_publico
            ?: $this->nombre
            ?: EmpresaConfig::getConfig()->nombre;
    }

    /**
     * Actualiza el logo de la sucursal
     */
    public function updateLogo($file): void
    {
        // Eliminar logo anterior si existe
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }

        // Guardar nuevo logo
        $comercioId = app(TenantService::class)->getComercioId();
        $path = $file->store("logos/{$comercioId}/sucursales/{$this->id}", 'public');

        $this->logo_path = $path;
        $this->save();
    }

    /**
     * Elimina el logo
     */
    public function deleteLogo(): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
            $this->logo_path = null;
            $this->save();
        }
    }

    /**
     * Verifica si tiene logo
     */
    public function hasLogo(): bool
    {
        return ! empty($this->logo_path) && Storage::disk('public')->exists($this->logo_path);
    }
}
