<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImpresoraSucursalCaja extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'impresora_sucursal_caja';

    protected $fillable = [
        'impresora_id',
        'sucursal_id',
        'caja_id',
        'es_defecto',
    ];

    protected $casts = [
        'es_defecto' => 'boolean',
    ];

    // Relaciones
    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function tiposDocumento(): HasMany
    {
        return $this->hasMany(ImpresoraTipoDocumento::class);
    }

    // Scopes
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorCaja($query, ?int $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }

    public function scopeDefecto($query)
    {
        return $query->where('es_defecto', true);
    }

    public function scopeSinCajaEspecifica($query)
    {
        return $query->whereNull('caja_id');
    }

    // Métodos
    public function esParaTodaLaSucursal(): bool
    {
        return is_null($this->caja_id);
    }

    public function getDescripcionAttribute(): string
    {
        $desc = $this->sucursal->nombre;
        if ($this->caja) {
            $desc .= ' - ' . $this->caja->nombre;
        } else {
            $desc .= ' (Toda la sucursal)';
        }
        return $desc;
    }
}
