<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoCambio extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'tipos_cambio';

    protected $fillable = [
        'moneda_origen_id',
        'moneda_destino_id',
        'tasa_compra',
        'tasa_venta',
        'fecha',
        'usuario_id',
    ];

    protected $casts = [
        'tasa_compra' => 'decimal:6',
        'tasa_venta' => 'decimal:6',
        'fecha' => 'date',
    ];

    // ==================== Relaciones ====================

    public function monedaOrigen(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_origen_id');
    }

    public function monedaDestino(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    // ==================== Métodos ====================

    /**
     * Obtiene la última cotización para un par de monedas.
     * Busca en ambas direcciones (bidireccional).
     */
    public static function ultimaTasa(int $origenId, int $destinoId): ?self
    {
        // Buscar dirección directa
        $directa = static::where('moneda_origen_id', $origenId)
            ->where('moneda_destino_id', $destinoId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($directa) {
            return $directa;
        }

        // Buscar dirección inversa
        return static::where('moneda_origen_id', $destinoId)
            ->where('moneda_destino_id', $origenId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Obtiene la tasa de venta para convertir de moneda extranjera a moneda principal.
     * Busca en ambas direcciones e invierte si es necesario.
     *
     * @param  int  $monedaExtranjeraId  ID de la moneda extranjera (ej: USD)
     * @param  int  $monedaPrincipalId  ID de la moneda principal (ej: ARS)
     * @return float|null Tasa: cuántas unidades de principal por 1 extranjera (ej: 1400.00)
     */
    public static function obtenerTasaVenta(int $monedaExtranjeraId, int $monedaPrincipalId): ?float
    {
        $snapshot = static::obtenerTasaVentaConId($monedaExtranjeraId, $monedaPrincipalId);

        return $snapshot['tasa'] ?? null;
    }

    /**
     * Versión "snapshot": además de la tasa, devuelve el id del record `tipos_cambio`
     * usado, para persistir junto con la tasa al cobrar (par id+valor inmutable).
     *
     * Si la búsqueda directa no encuentra y se usa la inversa, devuelve el id del
     * record inverso. La tasa siempre se devuelve en sentido extranjera→principal
     * (consistente con `obtenerTasaVenta()`), aunque venga de un record inverso.
     *
     * @return array{tasa: float, id: int}|null
     */
    public static function obtenerTasaVentaConId(int $monedaExtranjeraId, int $monedaPrincipalId): ?array
    {
        // Dirección directa: extranjera→principal (ej: USD→ARS = 1400)
        $directa = static::where('moneda_origen_id', $monedaExtranjeraId)
            ->where('moneda_destino_id', $monedaPrincipalId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($directa) {
            return ['tasa' => (float) $directa->tasa_venta, 'id' => $directa->id];
        }

        // Dirección inversa: principal→extranjera (ej: ARS→USD = 0.000714)
        $inversa = static::where('moneda_origen_id', $monedaPrincipalId)
            ->where('moneda_destino_id', $monedaExtranjeraId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($inversa && (float) $inversa->tasa_venta > 0) {
            return ['tasa' => round(1 / (float) $inversa->tasa_venta, 6), 'id' => $inversa->id];
        }

        return null;
    }
}
