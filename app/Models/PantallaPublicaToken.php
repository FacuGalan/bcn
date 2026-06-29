<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Índice global (DB config) que mapea token/código público → comercio + sucursal.
 *
 * Permite que las pantallas Clase B (llamador de pedidos, consultor de precios)
 * resuelvan el tenant correcto SIN sesión, a partir de un token en la URL, sin
 * escanear las N DBs tenant. Mismo patrón que MercadoPagoCollectorIndex.
 *
 * Vive en conexión 'config' porque debe ser accesible ANTES de saber a qué
 * tenant pertenece el request (las tablas tenant llevan prefijo por comercio).
 *
 * Dos credenciales con roles distintos:
 *  - `token` (largo, no adivinable): credencial de máquina. Nombra el canal de
 *    Reverb y autoriza los endpoints. Se guarda en localStorage del dispositivo.
 *  - `codigo_corto` (6 chars, alfabeto sin ambigüedades): credencial humana,
 *    SOLO para vincular una TV tipeando una URL corta. Se canjea por el token.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-01, RF-02).
 *
 * @property int $id
 * @property string $token
 * @property string $codigo_corto
 * @property int $comercio_id
 * @property int $sucursal_id FK lógica cross-DB a {prefix}sucursales.id
 */
class PantallaPublicaToken extends Model
{
    protected $connection = 'config';

    protected $table = 'pantalla_publica_tokens';

    protected $fillable = [
        'token',
        'codigo_corto',
        'comercio_id',
        'sucursal_id',
    ];

    /**
     * Alfabeto del código corto: sin caracteres ambiguos (0/O, 1/I/L) para que
     * sea fácil de tipear en el control remoto de una TV. 31 símbolos.
     */
    public const ALFABETO_CODIGO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LARGO_TOKEN = 40;

    public const LARGO_CODIGO = 6;

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'comercio_id');
    }

    /**
     * Genera un token largo aleatorio (no garantiza unicidad — usar
     * generarTokenUnico para chequear contra la tabla).
     */
    public static function generarToken(): string
    {
        return Str::random(self::LARGO_TOKEN);
    }

    /**
     * Genera un código corto con el alfabeto sin ambigüedades.
     */
    public static function generarCodigo(): string
    {
        $max = strlen(self::ALFABETO_CODIGO) - 1;
        $codigo = '';
        for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
            $codigo .= self::ALFABETO_CODIGO[random_int(0, $max)];
        }

        return $codigo;
    }

    /**
     * Genera un token único contra el índice global.
     */
    public static function generarTokenUnico(): string
    {
        do {
            $token = self::generarToken();
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Genera un código corto único contra el índice global.
     */
    public static function generarCodigoUnico(): string
    {
        do {
            $codigo = self::generarCodigo();
        } while (static::query()->where('codigo_corto', $codigo)->exists());

        return $codigo;
    }
}
