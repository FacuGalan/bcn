<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TesoreriaSaldoMoneda extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'tesoreria_saldos_moneda';

    protected $fillable = [
        'tesoreria_id',
        'moneda_id',
        'saldo_actual',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================

    public function tesoreria(): BelongsTo
    {
        return $this->belongsTo(Tesoreria::class, 'tesoreria_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    // ==================== MÉTODOS ====================

    public static function obtenerOCrear(int $tesoreriaId, int $monedaId): self
    {
        return static::firstOrCreate(
            ['tesoreria_id' => $tesoreriaId, 'moneda_id' => $monedaId],
            ['saldo_actual' => 0]
        );
    }
}
