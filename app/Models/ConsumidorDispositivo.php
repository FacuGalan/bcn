<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dispositivo recordado del consumidor (RF-T66) — BD CONFIG.
 *
 * Par selector/validator estilo recaller: el selector identifica la fila,
 * el validator (persistido SOLO como sha256) autentica el canje. Toda la
 * lógica de emisión/canje/rotación vive en DispositivoService — este modelo
 * no se escribe desde ningún otro lado.
 */
class ConsumidorDispositivo extends Model
{
    protected $connection = 'config';

    protected $table = 'consumidor_dispositivos';

    protected $fillable = [
        'selector',
        'validator_hash',
        'nombre',
        'ip_ultima',
        'ultimo_uso_at',
        'expira_at',
    ];

    protected $hidden = ['selector', 'validator_hash'];

    protected $casts = [
        'ultimo_uso_at' => 'datetime',
        'expira_at' => 'datetime',
    ];

    public function consumidor(): BelongsTo
    {
        return $this->belongsTo(Consumidor::class, 'consumidor_id');
    }
}
