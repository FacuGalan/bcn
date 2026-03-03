<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produccion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'producciones';

    protected $fillable = [
        'sucursal_id',
        'usuario_id',
        'fecha',
        'estado',
        'observaciones',
        'anulado_por_usuario_id',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_anulacion' => 'datetime',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function anuladoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ProduccionDetalle::class, 'produccion_id');
    }

    // Scopes
    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function scopeAnuladas($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // Helpers
    public function estaConfirmada(): bool
    {
        return $this->estado === 'confirmado';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulado';
    }
}
