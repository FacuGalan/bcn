<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PromocionEspecial extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'promociones_especiales';

    // Tipos de promoción
    const TIPO_NXM = 'nxm';
    const TIPO_NXM_AVANZADO = 'nxm_avanzado';
    const TIPO_COMBO = 'combo';
    const TIPO_MENU = 'menu';

    // Tipos de precio
    const PRECIO_FIJO = 'fijo';
    const PRECIO_PORCENTAJE = 'porcentaje';

    // Tipos de beneficio NxM
    const BENEFICIO_GRATIS = 'gratis';
    const BENEFICIO_DESCUENTO = 'descuento';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'descripcion',
        'tipo',
        'nxm_lleva',
        'nxm_paga',
        'nxm_bonifica',
        'beneficio_tipo',
        'beneficio_porcentaje',
        'nxm_articulo_id',
        'nxm_categoria_id',
        'usa_escalas',
        'precio_tipo',
        'precio_valor',
        'prioridad',
        'activo',
        'vigencia_desde',
        'vigencia_hasta',
        'dias_semana',
        'hora_desde',
        'hora_hasta',
        'forma_venta_id',
        'canal_venta_id',
        'forma_pago_id',
        'usos_maximos',
        'usos_actuales',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'usa_escalas' => 'boolean',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'dias_semana' => 'array',
        'precio_valor' => 'decimal:2',
        'beneficio_porcentaje' => 'decimal:2',
        'nxm_lleva' => 'integer',
        'nxm_paga' => 'integer',
        'nxm_bonifica' => 'integer',
        'prioridad' => 'integer',
        'usos_maximos' => 'integer',
        'usos_actuales' => 'integer',
    ];

    // ==================== Relaciones ====================

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function articuloNxM()
    {
        return $this->belongsTo(Articulo::class, 'nxm_articulo_id');
    }

    public function categoriaNxM()
    {
        return $this->belongsTo(Categoria::class, 'nxm_categoria_id');
    }

    public function grupos()
    {
        return $this->hasMany(PromocionEspecialGrupo::class)->orderBy('orden');
    }

    public function gruposTrigger()
    {
        return $this->hasMany(PromocionEspecialGrupo::class)->where('es_trigger', true)->orderBy('orden');
    }

    public function gruposReward()
    {
        return $this->hasMany(PromocionEspecialGrupo::class)->where('es_reward', true)->orderBy('orden');
    }

    public function escalas()
    {
        return $this->hasMany(PromocionEspecialEscala::class)->orderBy('cantidad_desde');
    }

    public function formaVenta()
    {
        return $this->belongsTo(FormaVenta::class);
    }

    public function canalVenta()
    {
        return $this->belongsTo(CanalVenta::class);
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class);
    }

    // ==================== Scopes ====================

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigentes($query)
    {
        $hoy = Carbon::today();

        return $query->where(function ($q) use ($hoy) {
            $q->whereNull('vigencia_desde')
              ->orWhere('vigencia_desde', '<=', $hoy);
        })->where(function ($q) use ($hoy) {
            $q->whereNull('vigencia_hasta')
              ->orWhere('vigencia_hasta', '>=', $hoy);
        });
    }

    public function scopeTipoNxM($query)
    {
        return $query->whereIn('tipo', [self::TIPO_NXM, self::TIPO_NXM_AVANZADO]);
    }

    public function scopeTipoNxMBasico($query)
    {
        return $query->where('tipo', self::TIPO_NXM);
    }

    public function scopeTipoNxMAvanzado($query)
    {
        return $query->where('tipo', self::TIPO_NXM_AVANZADO);
    }

    public function scopeTipoCombo($query)
    {
        return $query->whereIn('tipo', [self::TIPO_COMBO, self::TIPO_MENU]);
    }

    public function scopeTipoComboBasico($query)
    {
        return $query->where('tipo', self::TIPO_COMBO);
    }

    public function scopeTipoMenu($query)
    {
        return $query->where('tipo', self::TIPO_MENU);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeOrdenadoPorPrioridad($query)
    {
        return $query->orderBy('prioridad', 'asc');
    }

    // ==================== Helpers de tipo ====================

    public function esNxM(): bool
    {
        return in_array($this->tipo, [self::TIPO_NXM, self::TIPO_NXM_AVANZADO]);
    }

    public function esNxMBasico(): bool
    {
        return $this->tipo === self::TIPO_NXM;
    }

    public function esNxMAvanzado(): bool
    {
        return $this->tipo === self::TIPO_NXM_AVANZADO;
    }

    public function esComboOMenu(): bool
    {
        return in_array($this->tipo, [self::TIPO_COMBO, self::TIPO_MENU]);
    }

    public function esComboBasico(): bool
    {
        return $this->tipo === self::TIPO_COMBO;
    }

    public function esMenu(): bool
    {
        return $this->tipo === self::TIPO_MENU;
    }

    // ==================== Métodos de verificación ====================

    /**
     * Verifica si la promoción está vigente en este momento
     */
    public function estaVigente(): bool
    {
        $hoy = Carbon::today();
        $ahora = Carbon::now();

        // Verificar fecha
        if ($this->vigencia_desde && $hoy->lt($this->vigencia_desde)) {
            return false;
        }
        if ($this->vigencia_hasta && $hoy->gt($this->vigencia_hasta)) {
            return false;
        }

        // Verificar día de la semana
        if (!empty($this->dias_semana)) {
            $diasMap = [
                'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
                'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7
            ];
            $diaSemana = strtolower($ahora->locale('es')->dayName);
            $diaSemana = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $diaSemana);

            if (!in_array($diaSemana, $this->dias_semana)) {
                return false;
            }
        }

        // Verificar horario
        if ($this->hora_desde || $this->hora_hasta) {
            $horaActual = $ahora->format('H:i:s');
            if ($this->hora_desde && $horaActual < $this->hora_desde) {
                return false;
            }
            if ($this->hora_hasta && $horaActual > $this->hora_hasta) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si aún quedan usos disponibles
     */
    public function tieneUsosDisponibles(): bool
    {
        if ($this->usos_maximos === null) {
            return true;
        }
        return $this->usos_actuales < $this->usos_maximos;
    }

    /**
     * Verifica si cumple las condiciones de venta
     */
    public function cumpleCondiciones(array $contexto): bool
    {
        if ($this->forma_venta_id) {
            if (empty($contexto['forma_venta_id']) || $contexto['forma_venta_id'] != $this->forma_venta_id) {
                return false;
            }
        }

        if ($this->canal_venta_id) {
            if (empty($contexto['canal_venta_id']) || $contexto['canal_venta_id'] != $this->canal_venta_id) {
                return false;
            }
        }

        if ($this->forma_pago_id) {
            if (empty($contexto['forma_pago_id']) || $contexto['forma_pago_id'] != $this->forma_pago_id) {
                return false;
            }
        }

        return true;
    }

    // ==================== Métodos de cálculo NxM ====================

    /**
     * Obtiene los IDs de artículos que activan la promoción (triggers)
     * Para NxM básico: el artículo directo o todos los de la categoría
     * Para NxM avanzado: los artículos de los grupos trigger
     */
    public function obtenerArticulosTrigger(): array
    {
        if ($this->esNxMBasico()) {
            if ($this->nxm_articulo_id) {
                return [$this->nxm_articulo_id];
            }
            if ($this->nxm_categoria_id) {
                return Articulo::where('categoria_id', $this->nxm_categoria_id)
                    ->where('activo', true)
                    ->pluck('id')
                    ->toArray();
            }
            return [];
        }

        if ($this->esNxMAvanzado()) {
            return $this->gruposTrigger()
                ->with('articulos')
                ->get()
                ->flatMap(fn($g) => $g->articulos->pluck('id'))
                ->unique()
                ->values()
                ->toArray();
        }

        return [];
    }

    /**
     * Obtiene los IDs de artículos que pueden ser bonificados (rewards)
     * Para NxM básico: igual que triggers
     * Para NxM avanzado: los artículos de los grupos reward
     */
    public function obtenerArticulosReward(): array
    {
        if ($this->esNxMBasico()) {
            return $this->obtenerArticulosTrigger();
        }

        if ($this->esNxMAvanzado()) {
            return $this->gruposReward()
                ->with('articulos')
                ->get()
                ->flatMap(fn($g) => $g->articulos->pluck('id'))
                ->unique()
                ->values()
                ->toArray();
        }

        return [];
    }

    /**
     * Obtiene la escala NxM aplicable para una cantidad dada
     * Retorna: lleva, bonifica, beneficio_tipo, beneficio_porcentaje
     */
    public function obtenerEscalaParaCantidad(int $cantidad): ?array
    {
        if (!$this->esNxM()) {
            return null;
        }

        // Si usa escalas, buscar la aplicable
        if ($this->usa_escalas) {
            $escalaAplicable = $this->escalas()
                ->where('cantidad_desde', '<=', $cantidad)
                ->where(function($q) use ($cantidad) {
                    $q->whereNull('cantidad_hasta')
                      ->orWhere('cantidad_hasta', '>=', $cantidad);
                })
                ->orderBy('cantidad_desde', 'desc')
                ->first();

            if ($escalaAplicable) {
                return [
                    'lleva' => $escalaAplicable->lleva,
                    'bonifica' => $escalaAplicable->bonifica,
                    'beneficio_tipo' => $escalaAplicable->beneficio_tipo,
                    'beneficio_porcentaje' => $escalaAplicable->beneficio_porcentaje,
                ];
            }
            return null;
        }

        // Sin escalas, usar valores directos
        if ($this->nxm_lleva && $this->nxm_bonifica && $cantidad >= $this->nxm_lleva) {
            return [
                'lleva' => $this->nxm_lleva,
                'bonifica' => $this->nxm_bonifica,
                'beneficio_tipo' => $this->beneficio_tipo,
                'beneficio_porcentaje' => $this->beneficio_porcentaje,
            ];
        }

        return null;
    }

    /**
     * Calcula el resultado de aplicar NxM a una cantidad de triggers
     * Retorna: [unidades_pagan, unidades_bonificadas, beneficio_tipo, beneficio_porcentaje, rewards]
     */
    public function calcularNxM(int $cantidadTriggers, array $articulosVenta = []): array
    {
        $escala = $this->obtenerEscalaParaCantidad($cantidadTriggers);

        if (!$escala) {
            return [
                'unidades_pagan' => $cantidadTriggers,
                'unidades_bonificadas' => 0,
                'beneficio_tipo' => self::BENEFICIO_GRATIS,
                'beneficio_porcentaje' => 100,
                'rewards' => [],
            ];
        }

        $lleva = $escala['lleva'];
        $bonifica = $escala['bonifica'];
        $beneficioTipo = $escala['beneficio_tipo'] ?? self::BENEFICIO_GRATIS;
        $beneficioPorcentaje = $beneficioTipo === self::BENEFICIO_GRATIS ? 100 : ($escala['beneficio_porcentaje'] ?? 0);

        $packsCompletos = intdiv($cantidadTriggers, $lleva);
        $sobrante = $cantidadTriggers % $lleva;
        $unidadesBonificadas = $packsCompletos * $bonifica;
        $unidadesPagan = $cantidadTriggers - $unidadesBonificadas;

        // Si es descuento parcial, todas las unidades se pagan pero algunas con descuento
        if ($beneficioTipo === self::BENEFICIO_DESCUENTO) {
            $unidadesPagan = $cantidadTriggers; // Todas pagan algo
        }

        // Para NxM avanzado, las unidades bonificadas vienen de los rewards
        $rewards = [];
        if ($this->esNxMAvanzado() && $unidadesBonificadas > 0) {
            $articulosReward = $this->obtenerArticulosReward();
            $rewards = [
                'cantidad' => $unidadesBonificadas,
                'articulos_posibles' => $articulosReward,
            ];
        }

        return [
            'unidades_pagan' => $unidadesPagan,
            'unidades_bonificadas' => $unidadesBonificadas,
            'beneficio_tipo' => $beneficioTipo,
            'beneficio_porcentaje' => $beneficioPorcentaje,
            'rewards' => $rewards,
        ];
    }

    // ==================== Métodos de cálculo Combo/Menú ====================

    /**
     * Obtiene los grupos con sus artículos para Combo/Menú
     */
    public function obtenerGruposConArticulos(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grupos()->with('articulos')->get();
    }

    /**
     * Verifica si los artículos de la venta cumplen con el combo/menú
     * Para cada grupo, necesita al menos 'cantidad' artículos del grupo
     * Retorna la cantidad de combos/menús que se pueden armar
     */
    public function calcularCombosDisponibles(array $articulosVenta): int
    {
        if (!$this->esComboOMenu()) {
            return 0;
        }

        $grupos = $this->obtenerGruposConArticulos();

        if ($grupos->isEmpty()) {
            return 0;
        }

        $combosMaximos = PHP_INT_MAX;

        foreach ($grupos as $grupo) {
            $articulosGrupo = $grupo->articulos->pluck('id')->toArray();
            $cantidadRequerida = $grupo->cantidad;

            // Contar cuántas unidades de artículos del grupo hay en la venta
            $cantidadEnVenta = 0;
            foreach ($articulosVenta as $artVenta) {
                if (in_array($artVenta['articulo_id'], $articulosGrupo)) {
                    $cantidadEnVenta += $artVenta['cantidad'];
                }
            }

            // Cuántos combos se pueden armar con este grupo
            $combosConEsteGrupo = intdiv($cantidadEnVenta, $cantidadRequerida);
            $combosMaximos = min($combosMaximos, $combosConEsteGrupo);
        }

        return $combosMaximos === PHP_INT_MAX ? 0 : $combosMaximos;
    }

    /**
     * Calcula el precio del combo/menú
     * Si es fijo: retorna precio_valor
     * Si es porcentaje: calcula el descuento sobre los precios de los artículos
     */
    public function calcularPrecioCombo(array $preciosArticulos, array $articulosSeleccionados = []): float
    {
        if ($this->precio_tipo === self::PRECIO_FIJO) {
            return (float) $this->precio_valor;
        }

        // Porcentaje: calcular sobre la suma de precios
        $total = 0;
        foreach ($articulosSeleccionados as $articuloId => $cantidad) {
            $precio = $preciosArticulos[$articuloId] ?? 0;
            $total += $precio * $cantidad;
        }

        $descuento = $total * ($this->precio_valor / 100);
        return $total - $descuento;
    }

    /**
     * Calcula el precio normal (sin promoción) de los artículos del combo
     */
    public function calcularPrecioNormalCombo(array $preciosArticulos, array $articulosSeleccionados): float
    {
        $total = 0;
        foreach ($articulosSeleccionados as $articuloId => $cantidad) {
            $precio = $preciosArticulos[$articuloId] ?? 0;
            $total += $precio * $cantidad;
        }
        return $total;
    }

    // ==================== Helpers ====================

    /**
     * Retorna una descripción legible del tipo de promoción
     */
    public function getDescripcionTipoAttribute(): string
    {
        switch ($this->tipo) {
            case self::TIPO_NXM:
                if ($this->usa_escalas) {
                    return 'NxM (escalas)';
                }
                $desc = "Lleva {$this->nxm_lleva} → {$this->nxm_bonifica}";
                if ($this->beneficio_tipo === self::BENEFICIO_GRATIS) {
                    $desc .= ' gratis';
                } else {
                    $desc .= " con {$this->beneficio_porcentaje}% dto";
                }
                return $desc;

            case self::TIPO_NXM_AVANZADO:
                return 'NxM Avanzado';

            case self::TIPO_COMBO:
                if ($this->precio_tipo === self::PRECIO_PORCENTAJE) {
                    return "Combo ({$this->precio_valor}% dto)";
                }
                return 'Combo $' . number_format($this->precio_valor, 0, ',', '.');

            case self::TIPO_MENU:
                if ($this->precio_tipo === self::PRECIO_PORCENTAJE) {
                    return "Menú ({$this->precio_valor}% dto)";
                }
                return 'Menú $' . number_format($this->precio_valor, 0, ',', '.');
        }

        return $this->tipo;
    }

    /**
     * Retorna el nombre legible del tipo
     */
    public function getNombreTipoAttribute(): string
    {
        return match($this->tipo) {
            self::TIPO_NXM => 'NxM',
            self::TIPO_NXM_AVANZADO => 'NxM Avanzado',
            self::TIPO_COMBO => 'Combo/Pack',
            self::TIPO_MENU => 'Menú',
            default => $this->tipo,
        };
    }
}
