<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo Consumidor (RF-13, D8/D11) — BD CONFIG.
 *
 * Cuenta GLOBAL de la tienda online (el "cliente general" cross-comercio).
 * Este spec solo deja la estructura + guard `consumidores` listos; el
 * registro/login/checkout los implementa el proyecto tienda. El consumidor
 * NO se convierte automáticamente en cliente tenant: el mapping
 * consumidor↔cliente por comercio (consumidor_comercio) lo decide cada
 * comercio (`comercios.tienda_alta_cliente_automatica`, D11).
 */
class Consumidor extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'config';

    protected $table = 'consumidores';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'telefono',
        'fecha_nacimiento',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'fecha_nacimiento' => 'date:Y-m-d',
    ];

    public function direcciones(): HasMany
    {
        return $this->hasMany(ConsumidorDireccion::class, 'consumidor_id');
    }

    public function comercios(): HasMany
    {
        return $this->hasMany(ConsumidorComercio::class, 'consumidor_id');
    }

    /**
     * Cliente tenant mapeado para un comercio (null si el comercio todavía
     * no lo materializó, D11).
     */
    public function clienteIdEn(int $comercioId): ?int
    {
        $mapping = $this->comercios()->where('comercio_id', $comercioId)->first();

        return $mapping?->cliente_id;
    }
}
