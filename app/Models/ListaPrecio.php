<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo ListaPrecio
 *
 * Sistema de listas de precios flexible por sucursal.
 * Cada lista puede definir un porcentaje de ajuste global y condiciones de aplicación.
 *
 * CARACTERÍSTICAS:
 * - Una lista pertenece a una sucursal
 * - Puede tener un ajuste porcentual global (+ recargo, - descuento)
 * - Configuración de redondeo de precios
 * - Control sobre aplicación de promociones
 * - Vigencia temporal (fechas, días de semana, horarios)
 * - Lista base obligatoria por sucursal (no eliminable)
 *
 * TIPOS DE REDONDEO:
 * - ninguno: Sin redondeo
 * - entero: Redondea a entero más cercano
 * - decena: Redondea a decena más cercana (10, 20, 30...)
 * - centena: Redondea a centena más cercana (100, 200, 300...)
 *
 * ALCANCE DE PROMOCIONES:
 * - todos: Las promociones aplican a toda la venta
 * - excluir_lista: Los artículos con precio especial en esta lista no participan en promociones
 *
 * FASE 2 - Sistema de Listas de Precios
 *
 * @property int $id
 * @property int $sucursal_id
 * @property string $nombre
 * @property string|null $codigo
 * @property string|null $descripcion
 * @property float $ajuste_porcentaje
 * @property string $redondeo
 * @property bool $aplica_promociones
 * @property string $promociones_alcance
 * @property \Carbon\Carbon|null $vigencia_desde
 * @property \Carbon\Carbon|null $vigencia_hasta
 * @property array|null $dias_semana
 * @property string|null $hora_desde
 * @property string|null $hora_hasta
 * @property float|null $cantidad_minima
 * @property float|null $cantidad_maxima
 * @property bool $es_lista_base
 * @property int $prioridad
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read Sucursal $sucursal
 * @property-read \Illuminate\Database\Eloquent\Collection|ListaPrecioCondicion[] $condiciones
 * @property-read \Illuminate\Database\Eloquent\Collection|ListaPrecioArticulo[] $articulos
 * @property-read \Illuminate\Database\Eloquent\Collection|Cliente[] $clientes
 */
class ListaPrecio extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'listas_precios';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'codigo',
        'descripcion',
        'ajuste_porcentaje',
        'redondeo',
        'aplica_promociones',
        'promociones_alcance',
        'vigencia_desde',
        'vigencia_hasta',
        'dias_semana',
        'hora_desde',
        'hora_hasta',
        'cantidad_minima',
        'cantidad_maxima',
        'es_lista_base',
        'prioridad',
        'activo',
    ];

    protected $casts = [
        'ajuste_porcentaje' => 'decimal:2',
        'aplica_promociones' => 'boolean',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'dias_semana' => 'array',
        'cantidad_minima' => 'decimal:3',
        'cantidad_maxima' => 'decimal:3',
        'es_lista_base' => 'boolean',
        'prioridad' => 'integer',
        'activo' => 'boolean',
    ];

    // ==================== Relaciones ====================

    /**
     * Sucursal a la que pertenece esta lista
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Condiciones que deben cumplirse para que aplique esta lista
     */
    public function condiciones(): HasMany
    {
        return $this->hasMany(ListaPrecioCondicion::class, 'lista_precio_id');
    }

    /**
     * Artículos/categorías que participan en esta lista con sus precios/ajustes
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(ListaPrecioArticulo::class, 'lista_precio_id');
    }

    /**
     * Clientes que tienen asignada esta lista
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'lista_precio_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Solo listas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Solo listas vigentes por fecha
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
     * Scope: Solo listas base
     */
    public function scopeListasBase($query)
    {
        return $query->where('es_lista_base', true);
    }

    /**
     * Scope: Excluir listas base
     */
    public function scopeSinListasBase($query)
    {
        return $query->where('es_lista_base', false);
    }

    /**
     * Scope: Ordenar por prioridad (mayor primero)
     */
    public function scopeOrdenadoPorPrioridad($query)
    {
        return $query->orderBy('prioridad');
    }

    /**
     * Scope: Con condiciones cargadas
     */
    public function scopeConCondiciones($query)
    {
        return $query->with('condiciones');
    }

    /**
     * Scope: Con artículos cargados
     */
    public function scopeConArticulos($query)
    {
        return $query->with('articulos');
    }

    // ==================== Métodos de Validación ====================

    /**
     * Verifica si la lista está vigente por fecha
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
     * Verifica si aplica para una cantidad específica
     */
    public function aplicaParaCantidad(float $cantidad): bool
    {
        if ($this->cantidad_minima !== null && $cantidad < $this->cantidad_minima) {
            return false;
        }

        if ($this->cantidad_maxima !== null && $cantidad > $this->cantidad_maxima) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si la lista está completamente vigente
     * (fecha, día de semana, horario)
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

        return true;
    }

    /**
     * Valida todas las condiciones de la lista contra un contexto de venta
     *
     * @param array $contexto Array con claves: forma_pago_id, forma_venta_id, canal_venta_id, total_compra
     * @return bool
     */
    public function validarCondiciones(array $contexto): bool
    {
        // Si no tiene condiciones, siempre aplica
        if ($this->condiciones->isEmpty()) {
            return true;
        }

        // Todas las condiciones deben cumplirse (AND)
        foreach ($this->condiciones as $condicion) {
            if (!$condicion->evaluar($contexto)) {
                return false;
            }
        }

        return true;
    }

    // ==================== Métodos de Cálculo de Precios ====================

    /**
     * Obtiene el precio para un artículo según esta lista
     *
     * @param Articulo $articulo
     * @return array ['precio' => float, 'ajuste_porcentaje' => float, 'origen' => string]
     */
    public function obtenerPrecioArticulo(Articulo $articulo): array
    {
        $precioBase = (float) $articulo->precio_base;

        // 1. Buscar por artículo específico
        $detalle = $this->articulos()
            ->where('articulo_id', $articulo->id)
            ->first();

        if ($detalle) {
            return $this->calcularPrecioDesdeDetalle($detalle, $precioBase, 'articulo');
        }

        // 2. Buscar por categoría del artículo
        if ($articulo->categoria_id) {
            $detalle = $this->articulos()
                ->where('categoria_id', $articulo->categoria_id)
                ->first();

            if ($detalle) {
                return $this->calcularPrecioDesdeDetalle($detalle, $precioBase, 'categoria');
            }
        }

        // 3. Usar ajuste del encabezado
        $ajuste = (float) $this->ajuste_porcentaje;
        $precioAjustado = $precioBase * (1 + ($ajuste / 100));

        return [
            'precio' => $this->aplicarRedondeo($precioAjustado),
            'precio_sin_redondeo' => $precioAjustado,
            'ajuste_porcentaje' => $ajuste,
            'origen' => 'encabezado',
            'precio_base' => $precioBase,
        ];
    }

    /**
     * Calcula el precio desde un registro de detalle
     */
    protected function calcularPrecioDesdeDetalle(ListaPrecioArticulo $detalle, float $precioBase, string $origen): array
    {
        // Si tiene precio fijo, usarlo directamente
        if ($detalle->precio_fijo !== null) {
            $ajusteCalculado = $precioBase > 0
                ? (($detalle->precio_fijo - $precioBase) / $precioBase) * 100
                : 0;

            return [
                'precio' => $this->aplicarRedondeo((float) $detalle->precio_fijo),
                'precio_sin_redondeo' => (float) $detalle->precio_fijo,
                'ajuste_porcentaje' => round($ajusteCalculado, 2),
                'origen' => $origen . '_precio_fijo',
                'precio_base' => $precioBase,
            ];
        }

        // Usar ajuste porcentaje del detalle o del encabezado
        $ajuste = $detalle->ajuste_porcentaje ?? (float) $this->ajuste_porcentaje;
        $precioAjustado = $precioBase * (1 + ($ajuste / 100));

        return [
            'precio' => $this->aplicarRedondeo($precioAjustado),
            'precio_sin_redondeo' => $precioAjustado,
            'ajuste_porcentaje' => $ajuste,
            'origen' => $origen . '_ajuste',
            'precio_base' => $precioBase,
        ];
    }

    /**
     * Aplica el redondeo configurado a un precio
     */
    public function aplicarRedondeo(float $precio): float
    {
        return match ($this->redondeo) {
            'entero' => round($precio),
            'decena' => round($precio / 10) * 10,
            'centena' => round($precio / 100) * 100,
            default => round($precio, 2), // ninguno
        };
    }

    // ==================== Métodos de Utilidad ====================

    /**
     * Verifica si es la lista base de la sucursal
     */
    public function esListaBase(): bool
    {
        return $this->es_lista_base;
    }

    /**
     * Verifica si puede ser eliminada
     */
    public function puedeEliminarse(): bool
    {
        return !$this->es_lista_base;
    }

    /**
     * Cuenta cuántas condiciones tiene
     */
    public function contarCondiciones(): int
    {
        return $this->condiciones()->count();
    }

    /**
     * Cuenta cuántos artículos/categorías tiene en el detalle
     */
    public function contarArticulos(): int
    {
        return $this->articulos()->count();
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

    /**
     * Obtiene descripción del ajuste
     */
    public function obtenerDescripcionAjuste(): string
    {
        $ajuste = (float) $this->ajuste_porcentaje;

        if ($ajuste == 0) {
            return 'Sin ajuste';
        }

        $tipo = $ajuste > 0 ? 'Recargo' : 'Descuento';
        return "{$tipo} " . abs($ajuste) . '%';
    }

    // ==================== Métodos Estáticos ====================

    /**
     * Obtiene la lista base de una sucursal
     */
    public static function obtenerListaBase(int $sucursalId): ?self
    {
        return self::porSucursal($sucursalId)
            ->listasBase()
            ->first();
    }

    /**
     * Crea la lista base obligatoria para una sucursal
     */
    public static function crearListaBase(int $sucursalId, string $nombre = 'Lista Base'): self
    {
        return self::create([
            'sucursal_id' => $sucursalId,
            'nombre' => $nombre,
            'codigo' => 'BASE',
            'descripcion' => 'Lista de precios base de la sucursal. No puede ser eliminada.',
            'ajuste_porcentaje' => 0,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => true,
            'prioridad' => 999, // Menor prioridad (se usa como fallback)
            'activo' => true,
        ]);
    }

    /**
     * Busca la lista más específica que aplique para un contexto de venta
     *
     * @param int $sucursalId
     * @param array $contexto Array con: forma_pago_id, forma_venta_id, canal_venta_id, total_compra, cantidad, fecha, hora, dia_semana
     * @param int|null $listaPrecioIdManual ID de lista seleccionada manualmente
     * @param int|null $clienteId ID del cliente (para buscar su lista asignada)
     * @return self|null
     */
    public static function buscarListaAplicable(
        int $sucursalId,
        array $contexto = [],
        ?int $listaPrecioIdManual = null,
        ?int $clienteId = null
    ): ?self {
        // 1. Si hay lista seleccionada manualmente, usarla
        if ($listaPrecioIdManual) {
            $lista = self::find($listaPrecioIdManual);
            if ($lista && $lista->sucursal_id === $sucursalId && $lista->activo) {
                return $lista;
            }
        }

        // 2. Si hay cliente, buscar su lista asignada
        if ($clienteId) {
            $cliente = Cliente::find($clienteId);
            if ($cliente && $cliente->lista_precio_id) {
                $lista = self::find($cliente->lista_precio_id);
                if ($lista && $lista->sucursal_id === $sucursalId && $lista->activo) {
                    return $lista;
                }
            }
        }

        // 3. Buscar listas que cumplan condiciones, ordenadas por prioridad
        $fecha = $contexto['fecha'] ?? now();
        $diaSemana = $contexto['dia_semana'] ?? (int) now()->dayOfWeek;
        $hora = $contexto['hora'] ?? now()->format('H:i:s');
        $cantidad = $contexto['cantidad'] ?? 1;

        $listas = self::activas()
            ->porSucursal($sucursalId)
            ->vigentes($fecha)
            ->sinListasBase()
            ->ordenadoPorPrioridad()
            ->conCondiciones()
            ->get();

        foreach ($listas as $lista) {
            // Validar día de semana y horario
            if (!$lista->aplicaEnDiaSemana($diaSemana)) {
                continue;
            }
            if (!$lista->aplicaEnHorario($hora)) {
                continue;
            }
            if (!$lista->aplicaParaCantidad($cantidad)) {
                continue;
            }

            // Validar condiciones
            if ($lista->validarCondiciones($contexto)) {
                return $lista;
            }
        }

        // 4. Fallback: Lista base de la sucursal
        return self::obtenerListaBase($sucursalId);
    }

    /**
     * Detecta posibles conflictos con otras listas
     *
     * @return array Lista de listas que podrían entrar en conflicto
     */
    public function detectarConflictos(): array
    {
        $conflictos = [];

        // Obtener otras listas de la misma sucursal
        $otrasListas = self::activas()
            ->porSucursal($this->sucursal_id)
            ->where('id', '!=', $this->id)
            ->sinListasBase()
            ->conCondiciones()
            ->conArticulos()
            ->get();

        foreach ($otrasListas as $otraLista) {
            $tieneConflicto = false;
            $razones = [];

            // Verificar superposición de vigencia
            if ($this->tieneVigenciaSuperpuesta($otraLista)) {
                // Verificar condiciones similares
                if ($this->tieneCondicionesSimilares($otraLista)) {
                    $tieneConflicto = true;
                    $razones[] = 'Condiciones similares';
                }

                // Verificar artículos/categorías superpuestos
                if ($this->tieneArticulosSuperpuestos($otraLista)) {
                    $tieneConflicto = true;
                    $razones[] = 'Artículos/categorías superpuestos';
                }
            }

            if ($tieneConflicto) {
                $conflictos[] = [
                    'lista' => $otraLista,
                    'razones' => $razones,
                ];
            }
        }

        return $conflictos;
    }

    /**
     * Verifica si hay superposición de vigencia con otra lista
     */
    protected function tieneVigenciaSuperpuesta(self $otra): bool
    {
        // Si alguna no tiene vigencia, potencialmente se superponen
        if (!$this->vigencia_desde && !$this->vigencia_hasta) {
            return true;
        }
        if (!$otra->vigencia_desde && !$otra->vigencia_hasta) {
            return true;
        }

        // Verificar superposición de rangos de fecha
        $inicioA = $this->vigencia_desde ?? Carbon::minValue();
        $finA = $this->vigencia_hasta ?? Carbon::maxValue();
        $inicioB = $otra->vigencia_desde ?? Carbon::minValue();
        $finB = $otra->vigencia_hasta ?? Carbon::maxValue();

        return $inicioA <= $finB && $finA >= $inicioB;
    }

    /**
     * Verifica si tiene condiciones similares a otra lista
     */
    protected function tieneCondicionesSimilares(self $otra): bool
    {
        // Si ambas no tienen condiciones, son similares
        if ($this->condiciones->isEmpty() && $otra->condiciones->isEmpty()) {
            return true;
        }

        // Comparar tipos de condiciones
        $misCondiciones = $this->condiciones->pluck('tipo_condicion')->unique();
        $otrasCondiciones = $otra->condiciones->pluck('tipo_condicion')->unique();

        // Si comparten al menos un tipo de condición con valores similares
        $tiposComunes = $misCondiciones->intersect($otrasCondiciones);

        foreach ($tiposComunes as $tipo) {
            $misValores = $this->condiciones->where('tipo_condicion', $tipo);
            $otrosValores = $otra->condiciones->where('tipo_condicion', $tipo);

            foreach ($misValores as $miCondicion) {
                foreach ($otrosValores as $otraCondicion) {
                    if ($miCondicion->esIgualA($otraCondicion)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Verifica si tiene artículos/categorías superpuestos con otra lista
     */
    protected function tieneArticulosSuperpuestos(self $otra): bool
    {
        // Obtener IDs de artículos
        $misArticulos = $this->articulos->whereNotNull('articulo_id')->pluck('articulo_id');
        $otrosArticulos = $otra->articulos->whereNotNull('articulo_id')->pluck('articulo_id');

        if ($misArticulos->intersect($otrosArticulos)->isNotEmpty()) {
            return true;
        }

        // Obtener IDs de categorías
        $misCategorias = $this->articulos->whereNotNull('categoria_id')->pluck('categoria_id');
        $otrasCategorias = $otra->articulos->whereNotNull('categoria_id')->pluck('categoria_id');

        if ($misCategorias->intersect($otrasCategorias)->isNotEmpty()) {
            return true;
        }

        return false;
    }
}
