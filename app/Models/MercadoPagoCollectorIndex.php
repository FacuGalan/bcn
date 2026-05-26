<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Índice global (DB config) que mapea user_id MP → comercio + sucursal.
 *
 * Permite que el webhook único de MP resuelva el tenant correcto sin
 * escanear las N DBs tenant. Se sincroniza vía hooks de
 * IntegracionPagoSucursal (saved/deleted).
 *
 * Vive en conexión 'config' porque debe ser accesible ANTES de saber a qué
 * tenant pertenece la transacción.
 *
 * @property int $id
 * @property string $user_id_externo
 * @property string $modo 'test' | 'produccion'
 * @property int $comercio_id
 * @property int $sucursal_id FK lógica cross-DB
 * @property int $integracion_pago_sucursal_id FK lógica cross-DB
 * @property bool $activo
 */
class MercadoPagoCollectorIndex extends Model
{
    protected $connection = 'config';

    protected $table = 'mercadopago_collector_index';

    protected $fillable = [
        'user_id_externo',
        'modo',
        'comercio_id',
        'sucursal_id',
        'integracion_pago_sucursal_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorUserId($query, string $userId, string $modo)
    {
        return $query->where('user_id_externo', $userId)->where('modo', $modo);
    }
}
