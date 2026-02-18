<?php

namespace App\Models;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected $fillable = [
        'nombre', 'nombre_publico', 'codigo', 'direccion', 'telefono', 'email', 'logo_path',
        'es_principal', 'datos_fiscales_id', 'activa', 'configuracion',
        // Campos de configuración
        'usa_clave_autorizacion', 'clave_autorizacion', 'tipo_impresion_factura',
        'imprime_encabezado_comanda', 'agrupa_articulos_venta', 'agrupa_articulos_impresion',
        'control_stock_venta', 'facturacion_fiscal_automatica',
        'usa_whatsapp_escritorio', 'envia_whatsapp_comanda', 'mensaje_whatsapp_comanda',
        'envia_whatsapp_listo', 'mensaje_whatsapp_listo',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activa' => 'boolean',
        'configuracion' => 'array',
        'usa_clave_autorizacion' => 'boolean',
        'imprime_encabezado_comanda' => 'boolean',
        'agrupa_articulos_venta' => 'boolean',
        'agrupa_articulos_impresion' => 'boolean',
        'facturacion_fiscal_automatica' => 'boolean',
        'usa_whatsapp_escritorio' => 'boolean',
        'envia_whatsapp_comanda' => 'boolean',
        'envia_whatsapp_listo' => 'boolean',
    ];

    // Relaciones
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
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
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
        return !empty($this->logo_path) && Storage::disk('public')->exists($this->logo_path);
    }
}
