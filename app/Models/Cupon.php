<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cupon extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cupones';

    protected $fillable = [
        'codigo',
        'tipo',
        'cliente_id',
        'descripcion',
        'modo_descuento',
        'valor_descuento',
        'aplica_a',
        'uso_maximo',
        'uso_actual',
        'fecha_vencimiento',
        'activo',
        'puntos_consumidos',
        'created_by_usuario_id',
    ];

    protected $casts = [
        'valor_descuento' => 'decimal:2',
        'uso_maximo' => 'integer',
        'uso_actual' => 'integer',
        'fecha_vencimiento' => 'date',
        'activo' => 'boolean',
        'puntos_consumidos' => 'integer',
    ];

    // --- Relaciones ---

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_usuario_id');
    }

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(Articulo::class, 'cupon_articulos')
            ->withTimestamps();
    }

    public function cuponArticulos(): HasMany
    {
        return $this->hasMany(CuponArticulo::class);
    }

    public function usos(): HasMany
    {
        return $this->hasMany(CuponUso::class);
    }

    // --- Scopes ---

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigentes($query)
    {
        return $query->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento')
                    ->orWhere('fecha_vencimiento', '>=', now()->toDateString());
            });
    }

    public function scopePromocionales($query)
    {
        return $query->where('tipo', 'promocional');
    }

    public function scopeDesdePuntos($query)
    {
        return $query->where('tipo', 'puntos');
    }

    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // --- Métodos ---

    public function esDePuntos(): bool
    {
        return $this->tipo === 'puntos';
    }

    public function esPromocional(): bool
    {
        return $this->tipo === 'promocional';
    }

    public function aplicaATotal(): bool
    {
        return $this->aplica_a === 'total';
    }

    public function aplicaAArticulos(): bool
    {
        return $this->aplica_a === 'articulos';
    }

    public function esPorcentaje(): bool
    {
        return $this->modo_descuento === 'porcentaje';
    }

    public function esMontoFijo(): bool
    {
        return $this->modo_descuento === 'monto_fijo';
    }

    public function estaVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }

        if ($this->fecha_vencimiento && $this->fecha_vencimiento->lt(now()->startOfDay())) {
            return false;
        }

        return true;
    }

    public function tieneUsosDisponibles(): bool
    {
        if ($this->uso_maximo === 0) {
            return true; // Ilimitado
        }

        return $this->uso_actual < $this->uso_maximo;
    }

    public function puedeSerUsadoPor(?int $clienteId): bool
    {
        if ($this->esPromocional()) {
            return true;
        }

        // Cupón de puntos: solo el cliente dueño
        return $this->cliente_id === $clienteId;
    }
}
