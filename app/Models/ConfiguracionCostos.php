<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de costos del comercio (una fila, RF-02/RF-08):
 * utilidad default de la cascada y costo rector (v1 fijo en 'ultimo',
 * la UI lo muestra en solo lectura).
 */
class ConfiguracionCostos extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'configuracion_costos';

    protected $fillable = [
        'utilidad_default',
        'costo_rector',
    ];

    protected $casts = [
        'utilidad_default' => 'decimal:2',
    ];

    /**
     * Singleton por comercio: la fila la seedea la migración/provisión;
     * firstOrCreate cubre tenants de test creados desde el SQL pelado.
     */
    public static function obtener(): self
    {
        return static::firstOrCreate([], [
            'utilidad_default' => 30.00,
            'costo_rector' => 'ultimo',
        ]);
    }
}
