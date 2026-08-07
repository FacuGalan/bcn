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

    /**
     * RF-T40: días de gracia para verificar el email. Vencidos, la cuenta
     * queda RESTRINGIDA (no puede crear pedidos logueada) hasta verificar.
     * No hay estado persistido: se computa de created_at + email_verified_at,
     * así verificar des-restringe al instante.
     */
    public const DIAS_GRACIA_VERIFICACION = 7;

    protected $connection = 'config';

    protected $table = 'consumidores';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'telefono',
        'fecha_nacimiento',
    ];

    // google_id (RF-T49) queda FUERA de fillable a propósito: es identidad,
    // solo lo setea el flujo Sign in with Google (forceFill/asignación).
    protected $hidden = ['password', 'remember_token', 'google_id'];

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

    /** RF-T66: dispositivos recordados (remember-token rotativo). */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(ConsumidorDispositivo::class, 'consumidor_id');
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

    /** RF-T40: fecha límite para verificar el email (null si ya verificó). */
    public function verificacionVenceEl(): ?\Illuminate\Support\Carbon
    {
        if ($this->email_verified_at !== null) {
            return null;
        }

        return $this->created_at->copy()->addDays(self::DIAS_GRACIA_VERIFICACION);
    }

    /** RF-T40: venció la gracia sin verificar ⇒ pedidos logueados bloqueados. */
    public function verificacionVencida(): bool
    {
        return $this->verificacionVenceEl()?->isPast() ?? false;
    }
}
