<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Impresora extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'impresoras';

    public const TIPOS = [
        'termica' => 'Térmica (ESC/POS)',
        'laser_inkjet' => 'Láser/Inkjet (HTML)',
    ];

    public const FORMATOS_PAPEL = [
        '80mm' => '80mm (Térmica)',
        '58mm' => '58mm (Térmica)',
        'a4' => 'A4',
        'carta' => 'Carta',
    ];

    public const ANCHOS_CARACTERES = [
        '80mm' => 48,
        '58mm' => 32,
        'a4' => 80,
        'carta' => 80,
    ];

    protected $fillable = [
        'nombre',
        'nombre_sistema',
        'tipo',
        'formato_papel',
        'ancho_caracteres',
        'activa',
        'configuracion',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'configuracion' => 'array',
    ];

    // Relaciones
    public function asignaciones(): HasMany
    {
        return $this->hasMany(ImpresoraSucursalCaja::class);
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'impresora_sucursal_caja')
            ->withPivot(['caja_id', 'es_defecto'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeTermicas($query)
    {
        return $query->where('tipo', 'termica');
    }

    public function scopeLaserInkjet($query)
    {
        return $query->where('tipo', 'laser_inkjet');
    }

    // Métodos
    public function esTermica(): bool
    {
        return $this->tipo === 'termica';
    }

    public function esLaserInkjet(): bool
    {
        return $this->tipo === 'laser_inkjet';
    }

    public function soportaCortador(): bool
    {
        return $this->esTermica() && ($this->configuracion['tiene_cortador'] ?? true);
    }

    public function soportaCajonDinero(): bool
    {
        return $this->esTermica() && ($this->configuracion['tiene_cajon'] ?? false);
    }

    public function getTipoLegibleAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getFormatoPapelLegibleAttribute(): string
    {
        return self::FORMATOS_PAPEL[$this->formato_papel] ?? $this->formato_papel;
    }
}
