<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaCuentaEmpresa extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'transferencias_cuenta_empresa';

    protected $fillable = [
        'cuenta_origen_id',
        'cuenta_destino_id',
        'monto',
        'moneda_id',
        'concepto',
        'movimiento_origen_id',
        'movimiento_destino_id',
        'usuario_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(CuentaEmpresa::class, 'cuenta_origen_id');
    }

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(CuentaEmpresa::class, 'cuenta_destino_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function movimientoOrigen(): BelongsTo
    {
        return $this->belongsTo(MovimientoCuentaEmpresa::class, 'movimiento_origen_id');
    }

    public function movimientoDestino(): BelongsTo
    {
        return $this->belongsTo(MovimientoCuentaEmpresa::class, 'movimiento_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }
}
