<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConceptoMovimientoCuenta extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'conceptos_movimiento_cuenta';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'es_sistema',
        'activo',
        'orden',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeSistema($query)
    {
        return $query->where('es_sistema', true);
    }

    public function scopeManuales($query)
    {
        return $query->where('es_sistema', false);
    }

    public function scopeDeIngreso($query)
    {
        return $query->whereIn('tipo', ['ingreso', 'ambos']);
    }

    public function scopeDeEgreso($query)
    {
        return $query->whereIn('tipo', ['egreso', 'ambos']);
    }

    // ==================== Métodos ====================

    public function permiteIngreso(): bool
    {
        return in_array($this->tipo, ['ingreso', 'ambos']);
    }

    public function permiteEgreso(): bool
    {
        return in_array($this->tipo, ['egreso', 'ambos']);
    }
}
