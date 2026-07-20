<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen de la GALERÍA DE TIENDA de un artículo (RF-T14).
 *
 * Independiente de la imagen operativa del panel (`articulos.imagen_path`),
 * que queda como fallback cuando el artículo no tiene galería. Máximo 5 por
 * artículo (validado en ImagenArticuloTiendaService). El orden lo maneja el
 * panel de configuración de la tienda (drag & drop).
 *
 * @property int $id
 * @property int $articulo_id
 * @property string $path
 * @property int $orden
 * @property-read Articulo $articulo
 */
class ArticuloImagenTienda extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'articulo_imagenes_tienda';

    protected $fillable = [
        'articulo_id',
        'path',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    /**
     * URL pública como path relativo al host actual (mismo criterio que
     * Articulo::imagenUrl()).
     */
    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }
}
