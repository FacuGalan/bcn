<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioPrecioProgramado extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cambios_precio_programados';

    protected $fillable = [
        'usuario_id',
        'fecha_programada',
        'estado',
        'alcance_precio',
        'sucursal_id',
        'tipo_ajuste',
        'tipo_valor',
        'valor_ajuste',
        'tipo_redondeo',
        'total_articulos',
        'articulos_data',
        'resultado',
        'procesado_at',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'procesado_at' => 'datetime',
        'articulos_data' => 'array',
        'valor_ajuste' => 'decimal:2',
        'total_articulos' => 'integer',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->on('config');
    }

    /**
     * Cambios pendientes cuya fecha ya pasó.
     */
    public function scopeListos($query)
    {
        return $query->where('estado', 'pendiente')
            ->where('fecha_programada', '<=', now());
    }

    /**
     * Descripción legible del ajuste configurado.
     */
    public function getDescripcionAjusteAttribute(): string
    {
        $tipo = $this->tipo_ajuste === 'recargo' ? __('Recargo') : __('Descuento');
        $simbolo = $this->tipo_valor === 'porcentual' ? '%' : '$';

        return "{$tipo} {$this->valor_ajuste}{$simbolo}";
    }
}
