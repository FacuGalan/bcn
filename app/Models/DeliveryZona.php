<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DeliveryZona (RF-05/RF-06)
 *
 * Zona de entrega por sucursal dibujada como POLIGONO en el mapa
 * (`poligono` = [{lat, lng}, ...] en orden de dibujo). Una zona sin poligono
 * es legacy del v1 por radio: queda "pendiente de redibujar" y NO matchea.
 *
 * Costo: `costo_envio` es el default (aplica SIEMPRE que el punto caiga en el
 * poligono) y `rangos_horarios` lo pisa por franja
 * ([{dias:[1..7], desde:'20:00', hasta:'23:30', costo: 1500}]) — permite
 * cobrar mas de noche o determinado dia/hora. `orden` = prioridad de match
 * (primera zona de la lista que contiene el punto gana; se ordena por drag &
 * drop en la config).
 */
class DeliveryZona extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'delivery_zonas';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'centro_lat',
        'centro_lng',
        'radio_km',
        'poligono',
        'costo_envio',
        'rangos_horarios',
        'orden',
        'activo',
    ];

    protected $casts = [
        'centro_lat' => 'decimal:7',
        'centro_lng' => 'decimal:7',
        'radio_km' => 'decimal:2',
        'poligono' => 'array',
        'costo_envio' => 'decimal:2',
        'rangos_horarios' => 'array',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('id');
    }

    /**
     * ¿Tiene un poligono dibujado valido (3+ vertices)? Sin poligono la zona
     * es legacy por radio: pendiente de redibujar, no matchea.
     */
    public function tienePoligono(): bool
    {
        return is_array($this->poligono) && count($this->poligono) >= 3;
    }

    /**
     * Punto dentro del poligono (ray casting sobre lat/lng planos — a escala
     * de ciudad la distorsion es despreciable). Borde cuenta como adentro
     * por el criterio del cruce.
     */
    public function contienePunto(float $lat, float $lng): bool
    {
        if (! $this->tienePoligono()) {
            return false;
        }

        $vertices = array_values($this->poligono);
        $n = count($vertices);
        $dentro = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $latI = (float) ($vertices[$i]['lat'] ?? 0);
            $lngI = (float) ($vertices[$i]['lng'] ?? 0);
            $latJ = (float) ($vertices[$j]['lat'] ?? 0);
            $lngJ = (float) ($vertices[$j]['lng'] ?? 0);

            if (($lngI > $lng) !== ($lngJ > $lng)
                && $lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * Costo del envio para un momento dado: la primera franja de
     * `rangos_horarios` que matchee dia+hora (con costo definido) pisa el
     * default; sin franja aplicable rige `costo_envio`. Una franja que cruza
     * la medianoche (desde > hasta) pertenece a la JORNADA del dia en que
     * arranca: viernes 20:00–02:00 cubre tambien la madrugada del sabado.
     */
    public function costoPara(?\Carbon\CarbonInterface $cuando = null): float
    {
        $cuando ??= now();

        $dia = $cuando->isoWeekday();
        $diaAnterior = $cuando->copy()->subDay()->isoWeekday();
        $hora = $cuando->format('H:i');

        foreach ((array) $this->rangos_horarios as $rango) {
            if (! isset($rango['costo']) || $rango['costo'] === '' || $rango['costo'] === null) {
                continue;
            }

            $dias = array_map('intval', (array) ($rango['dias'] ?? [1, 2, 3, 4, 5, 6, 7]));
            $desde = (string) ($rango['desde'] ?? '00:00');
            $hasta = (string) ($rango['hasta'] ?? '23:59');

            $matchea = $desde <= $hasta
                ? (in_array($dia, $dias, true) && $hora >= $desde && $hora <= $hasta)
                : ((in_array($dia, $dias, true) && $hora >= $desde)
                    || (in_array($diaAnterior, $dias, true) && $hora <= $hasta));

            if ($matchea) {
                return round((float) $rango['costo'], 2);
            }
        }

        return round((float) $this->costo_envio, 2);
    }
}
