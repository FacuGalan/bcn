<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Moneda extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'monedas';

    protected $fillable = [
        'codigo',
        'nombre',
        'simbolo',
        'es_principal',
        'decimales',
        'activo',
        'orden',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'decimales' => 'integer',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }

    // ==================== Métodos ====================

    public static function obtenerPrincipal(): ?self
    {
        return static::principal()->first();
    }

    public function esPrincipal(): bool
    {
        return $this->es_principal === true;
    }
}
