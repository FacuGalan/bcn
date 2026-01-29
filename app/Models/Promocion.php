<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Promocion
 *
 * Sistema flexible de promociones y descuentos.
 * Cada promoción es específica de una sucursal y puede tener múltiples condiciones.
 *
 * TIPOS DE PROMOCIÓN:
 * - descuento_porcentaje: Descuento porcentual (ej: 20% OFF)
 * - descuento_monto: Descuento en monto fijo (ej: $500 OFF)
 * - precio_fijo: Precio fijo (ej: $1000)
 * - recargo_porcentaje: Recargo porcentual (ej: +10%)
 * - recargo_monto: Recargo en monto fijo (ej: +$200)
 * - descuento_escalonado: Usa tabla promociones_escalas
 *
 * VALIDACIONES:
 * - Descuentos finales: máximo 70%
 * - Descuentos por cantidad: pueden ser 100% (ej: cada 4, regalo 1)
 *
 * COMBINABILIDAD:
 * - Si combinable = true → puede combinarse con otras promociones
 * - Si combinable = false → es excluyente
 * - Prioridad define orden de aplicación (1 = mayor prioridad)
 *
 * FASE 1 - Sistema de Precios Dinámico
 *
 * @property int $id
 * @property int $sucursal_id Sucursal a la que aplica
 * @property string $nombre Nombre de la promoción
 * @property string|null $descripcion Descripción detallada
 * @property string|null $codigo_cupon Código de cupón (si requiere)
 * @property string $tipo Tipo de promoción (enum)
 * @property float $valor Valor según tipo (monto o porcentaje)
 * @property int $prioridad Orden de aplicación (1 = mayor prioridad)
 * @property bool $combinable Si puede combinarse con otras
 * @property bool $activo Si está activa
 * @property \Carbon\Carbon|null $vigencia_desde Fecha desde la cual aplica
 * @property \Carbon\Carbon|null $vigencia_hasta Fecha hasta la cual aplica
 * @property array|null $dias_semana Días de semana [0,1,2,3,4,5,6] donde 0=Domingo
 * @property string|null $hora_desde Hora desde la cual aplica
 * @property string|null $hora_hasta Hora hasta la cual aplica
 * @property int|null $usos_maximos Cantidad máxima de usos total
 * @property int|null $usos_por_cliente Usos máximos por cliente
 * @property int $usos_actuales Contador de usos actuales
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionCondicion[] $condiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|PromocionEscala[] $escalas
 */
class Promocion extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'promociones';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'descripcion',
        'codigo_cupon',
        'tipo',
        'valor',
        'prioridad',
        'combinable',
        'activo',
        'vigencia_desde',
        'vigencia_hasta',
        'dias_semana',
        'hora_desde',
        'hora_hasta',
        'usos_maximos',
        'usos_por_cliente',
        'usos_actuales',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'prioridad' => 'integer',
        'combinable' => 'boolean',
        'activo' => 'boolean',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'dias_semana' => 'array',
        'usos_maximos' => 'integer',
        'usos_por_cliente' => 'integer',
        'usos_actuales' => 'integer',
    ];

    // ==================== Relaciones ====================

    /**
     * Sucursal a la que pertenece esta promoción
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Condiciones que deben cumplirse para aplicar esta promoción
     */
    public function condiciones(): HasMany
    {
        return $this->hasMany(PromocionCondicion::class, 'promocion_id');
    }

    /**
     * Escalas de descuento por cantidad (solo para tipo descuento_escalonado)
     */
    public function escalas(): HasMany
    {
        return $this->hasMany(PromocionEscala::class, 'promocion_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo promociones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Solo promociones vigentes por fecha
     */
    public function scopeVigentes($query, ?\DateTime $fecha = null)
    {
        $fecha = $fecha ?? now();

        return $query->where(function ($q) use ($fecha) {
            $q->whereNull('vigencia_desde')
              ->orWhere('vigencia_desde', '<=', $fecha);
        })->where(function ($q) use ($fecha) {
            $q->whereNull('vigencia_hasta')
              ->orWhere('vigencia_hasta', '>=', $fecha);
        });
    }

    /**
     * Scope: Por sucursal
     */
    public function scopePorSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Scope: Por tipo
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope: Combinables
     */
    public function scopeCombinables($query)
    {
        return $query->where('combinable', true);
    }

    /**
     * Scope: No combinables (excluyentes)
     */
    public function scopeExcluyentes($query)
    {
        return $query->where('combinable', false);
    }

    /**
     * Scope: Ordenar por prioridad (mayor primero)
     */
    public function scopeOrdenadoPorPrioridad($query)
    {
        return $query->orderBy('prioridad');
    }

    /**
     * Scope: Con cupón
     */
    public function scopeConCupon($query)
    {
        return $query->whereNotNull('codigo_cupon');
    }

    /**
     * Scope: Sin cupón (automáticas)
     */
    public function scopeAutomaticas($query)
    {
        return $query->whereNull('codigo_cupon');
    }

    /**
     * Scope: Por código de cupón
     */
    public function scopePorCodigoCupon($query, string $codigo)
    {
        return $query->where('codigo_cupon', $codigo);
    }

    /**
     * Scope: Que no hayan alcanzado el límite de usos
     */
    public function scopeConUsosDisponibles($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('usos_maximos')
              ->orWhereRaw('usos_actuales < usos_maximos');
        });
    }

    // ==================== Métodos de Validación ====================

    /**
     * Verifica si la promoción está vigente por fecha
     */
    public function estaVigentePorFecha(?\DateTime $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        if ($this->vigencia_desde && $fecha < $this->vigencia_desde) {
            return false;
        }

        if ($this->vigencia_hasta && $fecha > $this->vigencia_hasta) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si aplica en un día de la semana específico
     *
     * @param int|null $diaSemana 0=Domingo, 1=Lunes, ... 6=Sábado
     */
    public function aplicaEnDiaSemana(?int $diaSemana = null): bool
    {
        if (!$this->dias_semana || empty($this->dias_semana)) {
            return true; // Aplica todos los días
        }

        $dia = $diaSemana ?? (int) now()->dayOfWeek;
        return in_array($dia, $this->dias_semana);
    }

    /**
     * Verifica si aplica en un horario específico
     *
     * @param string|null $hora Formato HH:MM:SS
     */
    public function aplicaEnHorario(?string $hora = null): bool
    {
        if (!$this->hora_desde && !$this->hora_hasta) {
            return true; // Aplica todo el día
        }

        $hora = $hora ?? now()->format('H:i:s');

        if ($this->hora_desde && $hora < $this->hora_desde) {
            return false;
        }

        if ($this->hora_hasta && $hora > $this->hora_hasta) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si tiene usos disponibles
     */
    public function tieneUsosDisponibles(): bool
    {
        if ($this->usos_maximos === null) {
            return true; // Sin límite de usos
        }

        return $this->usos_actuales < $this->usos_maximos;
    }

    /**
     * Verifica si la promoción está completamente vigente
     * (fecha, día de semana, horario, usos)
     */
    public function estaVigente(?\DateTime $fecha = null, ?int $diaSemana = null, ?string $hora = null): bool
    {
        if (!$this->activo) {
            return false;
        }

        if (!$this->estaVigentePorFecha($fecha)) {
            return false;
        }

        if (!$this->aplicaEnDiaSemana($diaSemana)) {
            return false;
        }

        if (!$this->aplicaEnHorario($hora)) {
            return false;
        }

        if (!$this->tieneUsosDisponibles()) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si requiere cupón
     */
    public function requiereCupon(): bool
    {
        return !empty($this->codigo_cupon);
    }

    // ==================== Métodos de Cálculo ====================

    /**
     * Calcula el descuento/recargo para un monto dado
     *
     * @param float $monto Monto sobre el cual calcular
     * @param int|null $cantidad Cantidad (solo para descuentos escalonados)
     * @return array ['tipo' => 'descuento'|'recargo', 'valor' => float, 'porcentaje' => float|null]
     */
    public function calcularAjuste(float $monto, ?int $cantidad = null): array
    {
        switch ($this->tipo) {
            case 'descuento_porcentaje':
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => $this->valor,
                    'valor' => round($monto * ($this->valor / 100), 2),
                ];

            case 'descuento_monto':
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => null,
                    'valor' => min($this->valor, $monto), // No puede ser mayor al monto
                ];

            case 'precio_fijo':
                return [
                    'tipo' => 'descuento',
                    'porcentaje' => null,
                    'valor' => max(0, $monto - $this->valor), // Diferencia con precio fijo
                ];

            case 'recargo_porcentaje':
                return [
                    'tipo' => 'recargo',
                    'porcentaje' => $this->valor,
                    'valor' => round($monto * ($this->valor / 100), 2),
                ];

            case 'recargo_monto':
                return [
                    'tipo' => 'recargo',
                    'porcentaje' => null,
                    'valor' => $this->valor,
                ];

            case 'descuento_escalonado':
                if ($cantidad === null) {
                    return ['tipo' => 'descuento', 'porcentaje' => 0, 'valor' => 0];
                }
                return $this->calcularDescuentoEscalonado($monto, $cantidad);

            default:
                return ['tipo' => 'descuento', 'porcentaje' => 0, 'valor' => 0];
        }
    }

    /**
     * Calcula descuento escalonado según cantidad
     *
     * @param float $monto
     * @param int $cantidad
     * @return array
     */
    protected function calcularDescuentoEscalonado(float $monto, int $cantidad): array
    {
        $escala = $this->escalas()
                       ->where('cantidad_desde', '<=', $cantidad)
                       ->orderBy('cantidad_desde', 'desc')
                       ->first();

        if (!$escala) {
            return ['tipo' => 'descuento', 'porcentaje' => 0, 'valor' => 0];
        }

        return [
            'tipo' => 'descuento',
            'porcentaje' => $escala->descuento_porcentaje,
            'valor' => round($monto * ($escala->descuento_porcentaje / 100), 2),
        ];
    }

    /**
     * Incrementa el contador de usos
     */
    public function incrementarUso(): bool
    {
        $this->increment('usos_actuales');
        return true;
    }

    /**
     * Obtiene descripción legible de la vigencia temporal
     */
    public function obtenerDescripcionVigencia(): string
    {
        $partes = [];

        if ($this->vigencia_desde || $this->vigencia_hasta) {
            if ($this->vigencia_desde && $this->vigencia_hasta) {
                $partes[] = "Desde {$this->vigencia_desde->format('d/m/Y')} hasta {$this->vigencia_hasta->format('d/m/Y')}";
            } elseif ($this->vigencia_desde) {
                $partes[] = "Desde {$this->vigencia_desde->format('d/m/Y')}";
            } else {
                $partes[] = "Hasta {$this->vigencia_hasta->format('d/m/Y')}";
            }
        }

        if ($this->dias_semana && !empty($this->dias_semana)) {
            $nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $dias = array_map(fn($d) => $nombresDias[$d] ?? $d, $this->dias_semana);
            $partes[] = implode(', ', $dias);
        }

        if ($this->hora_desde || $this->hora_hasta) {
            if ($this->hora_desde && $this->hora_hasta) {
                $partes[] = "{$this->hora_desde} - {$this->hora_hasta}";
            } elseif ($this->hora_desde) {
                $partes[] = "Desde {$this->hora_desde}";
            } else {
                $partes[] = "Hasta {$this->hora_hasta}";
            }
        }

        return implode(' | ', $partes) ?: 'Sin restricciones de horario';
    }
}
