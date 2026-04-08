<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPuntos extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'configuracion_puntos';

    protected $fillable = [
        'activo',
        'modo_acumulacion',
        'monto_por_punto',
        'valor_punto_canje',
        'minimo_canje',
        'redondeo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'monto_por_punto' => 'decimal:2',
        'valor_punto_canje' => 'decimal:2',
        'minimo_canje' => 'integer',
    ];

    // --- Métodos ---

    public function esGlobal(): bool
    {
        return $this->modo_acumulacion === 'global';
    }

    public function esPorSucursal(): bool
    {
        return $this->modo_acumulacion === 'por_sucursal';
    }
}
