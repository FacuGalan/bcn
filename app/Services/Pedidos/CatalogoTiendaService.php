<?php

namespace App\Services\Pedidos;

use App\Models\Articulo;
use App\Models\CanalVenta;
use App\Models\Categoria;
use App\Models\FormaVenta;
use App\Models\PedidoDelivery;
use App\Models\Promocion;
use App\Models\PromocionEspecial;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Services\PrecioService;

/**
 * Catálogo público de la tienda (RF-11/RF-17, D12/D21).
 *
 * Criterio de visibilidad (RF-17): artículo activo + vendible en la sucursal
 * + visible_tienda + disponible para el tipo de pedido (RF-16). Agotado
 * (controla stock, sin existencia y sin permite_venta_sin_stock) = visible
 * pero NO pedible — la API de cotización/alta lo bloquea.
 *
 * Precios FINALES calculados por el MISMO motor del sistema (PrecioService:
 * 4 niveles de especificidad + promociones vigentes), con contexto forma de
 * venta por tipo + canal TIENDA. La tienda nunca calcula precios localmente.
 */
class CatalogoTiendaService
{
    public function __construct(protected PrecioService $precioService) {}

    /**
     * Key del cache server-side del catálogo (TiendaController::catalogo).
     * Centralizada acá para que el panel pueda invalidarla al guardar
     * config por artículo (RF-T14) — sin esto, la tienda/visor muestran
     * el catálogo viejo hasta 60s después de guardar.
     */
    public static function cacheKey(int $comercioId, int $sucursalId, string $tipo): string
    {
        return "tienda_catalogo:{$comercioId}:{$sucursalId}:{$tipo}";
    }

    /**
     * Invalida el cache del catálogo de la sucursal (ambos tipos de pedido).
     */
    public static function invalidarCache(int $comercioId, int $sucursalId): void
    {
        foreach ([PedidoDelivery::TIPO_DELIVERY, PedidoDelivery::TIPO_TAKE_AWAY] as $tipo) {
            \Illuminate\Support\Facades\Cache::forget(self::cacheKey($comercioId, $sucursalId, $tipo));
        }
    }

    /**
     * @return array{categorias: list<array>, articulos: list<array>}
     */
    public function catalogo(Sucursal $sucursal, string $tipo = PedidoDelivery::TIPO_DELIVERY): array
    {
        $columnaTipo = $tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'disponible_take_away' : 'disponible_delivery';

        $formaVentaId = FormaVenta::where('activo', true)->get(['id', 'codigo'])
            ->first(fn ($f) => strtoupper((string) $f->codigo) === ($tipo === PedidoDelivery::TIPO_TAKE_AWAY ? 'TAKEAWAY' : 'DELIVERY'))
            ?->id;
        $canalVentaId = CanalVenta::where('activo', true)->get(['id', 'codigo'])
            ->first(fn ($c) => strtoupper((string) $c->codigo) === 'TIENDA')
            ?->id;

        $contexto = [
            'forma_venta_id' => $formaVentaId,
            'canal_venta_id' => $canalVentaId,
        ];

        $articulos = Articulo::query()
            ->where('activo', true)
            ->where($columnaTipo, true)
            ->whereHas('sucursales', function ($q) use ($sucursal) {
                $q->where('sucursales.id', $sucursal->id)
                    ->where('articulos_sucursales.activo', true)
                    ->where('articulos_sucursales.vendible', true)
                    ->where('articulos_sucursales.visible_tienda', true);
            })
            ->with([
                'tipoIva:id,porcentaje',
                'imagenesTienda',
                // Asignaciones de grupos opcionales DE ESTA SUCURSAL (el
                // vínculo es ArticuloGrupoOpcional, por sucursal), con el
                // mismo criterio del panel (OpcionalService::obtenerOpcionalesParaVenta):
                // activas + grupo activo; opciones activas con opcional activo
                // (las no disponibles viajan marcadas, la UI las deshabilita).
                'gruposOpcionales' => fn ($q) => $q->where('sucursal_id', $sucursal->id)
                    ->where('activo', true)
                    ->whereHas('grupoOpcional', fn ($g) => $g->where('activo', true))
                    ->orderBy('orden')
                    ->with([
                        'grupoOpcional',
                        'opciones' => fn ($o) => $o->where('activo', true)
                            ->whereHas('opcional', fn ($q2) => $q2->where('activo', true))
                            ->with('opcional')
                            ->orderBy('orden'),
                    ]),
            ])
            // Orden 100% MANUAL del panel (RF-T14): destacado NO fuerza
            // posición — es decoración (banner/tarjeta grande); el comercio
            // decide dónde cae cada artículo en el listado con drag & drop.
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        // Stock por artículo (para marcar agotados) en una sola query.
        $stocks = Stock::where('sucursal_id', $sucursal->id)
            ->whereIn('articulo_id', $articulos->pluck('id'))
            ->pluck('cantidad', 'articulo_id');

        // modo_stock del pivot por artículo.
        $pivots = \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('sucursal_id', $sucursal->id)
            ->whereIn('articulo_id', $articulos->pluck('id'))
            ->pluck('modo_stock', 'articulo_id');

        $itemsCatalogo = $articulos->map(function (Articulo $articulo) use ($sucursal, $contexto, $stocks, $pivots) {
            $controlaStock = ($pivots[$articulo->id] ?? 'ninguno') !== 'ninguno';
            $agotado = $controlaStock
                && ! $articulo->permite_venta_sin_stock
                && (float) ($stocks[$articulo->id] ?? 0) <= 0;

            try {
                $precioInfo = $this->precioService->calcularPrecioFinal(
                    $articulo->id,
                    (int) $sucursal->id,
                    1,
                    $contexto,
                );
            } catch (\Throwable $e) {
                $precioInfo = ['precio_final' => (float) $articulo->precio_base, 'promociones_aplicadas' => []];
            }

            return [
                'id' => (int) $articulo->id,
                'nombre' => $articulo->nombre,
                // RF-T14: descripción ESPECÍFICA de tienda si el comercio la
                // cargó en el panel; si no, la operativa (comportamiento previo).
                'descripcion' => filled($articulo->descripcion_tienda) ? $articulo->descripcion_tienda : $articulo->descripcion,
                'categoria_id' => $articulo->categoria_id,
                // ABSOLUTA con el host del request: la tienda corre en otro
                // origen y una ruta relativa se rompería contra su propio host.
                'imagen_url' => $articulo->imagen_path ? url($articulo->imagenUrl()) : null,
                // RF-T14 (aditivo): galería de fotos de tienda ordenada ([] =
                // sin galería, la tienda cae a imagen_url) y badges saneados.
                'imagenes' => $articulo->imagenesTienda
                    ->map(fn ($img) => url($img->url()))
                    ->values()
                    ->all(),
                'badges' => $articulo->badgesTienda(),
                // RF-T14: alérgenos declarados en el panel → aviso
                // "Contiene: ..." en el detalle de la tienda ([] = sin aviso).
                'alergenos' => $articulo->alergenosTienda(),
                'destacado' => (bool) $articulo->destacado,
                'orden' => (int) $articulo->orden,
                'pesable' => (bool) $articulo->pesable,
                'precio' => (float) ($precioInfo['precio_final'] ?? $precioInfo['precio'] ?? 0),
                // RF-T13 (aditivo): precio ANTES de promos, solo cuando hay
                // descuento — la tienda lo muestra tachado junto a la oferta.
                'precio_lista' => $this->precioLista($precioInfo),
                'promociones' => collect($precioInfo['promociones_aplicadas'] ?? [])
                    ->map(fn ($p) => is_array($p) ? ($p['nombre'] ?? null) : null)
                    ->filter()
                    ->values()
                    ->all(),
                'agotado' => $agotado,
                'pedible' => ! $agotado,
                'opcionales' => $articulo->gruposOpcionales
                    ->map(function ($asignacion) {
                        $grupo = $asignacion->grupoOpcional;

                        return [
                            'grupo_id' => (int) $grupo->id,
                            'nombre' => $grupo->nombre,
                            'tipo' => $grupo->tipo,
                            'obligatorio' => (bool) $grupo->obligatorio,
                            'min' => (int) ($grupo->min_seleccion ?? 0),
                            'max' => (int) ($grupo->max_seleccion ?? 0),
                            'opciones' => $asignacion->opciones->map(fn ($op) => [
                                'opcional_id' => (int) $op->opcional_id,
                                'nombre' => $op->opcional->nombre,
                                'precio_extra' => (float) $op->precio_extra,
                                'disponible' => (bool) $op->disponible,
                            ])->values()->all(),
                        ];
                    })
                    // Un grupo sin opciones vivas no se publica (si fuera
                    // obligatorio bloquearía el pedido sin nada que elegir).
                    ->filter(fn ($g) => $g['opciones'] !== [])
                    ->values()->all(),
            ];
        })->values()->all();

        $categorias = Categoria::where('activo', true)
            ->whereIn('id', $articulos->pluck('categoria_id')->filter()->unique())
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($cat) => [
                'id' => (int) $cat->id,
                'nombre' => $cat->nombre,
                'orden' => (int) $cat->orden,
                'imagen_url' => $cat->imagen_path
                    ? url('/storage/'.ltrim($cat->imagen_path, '/'))
                    : null,
            ])
            ->values()
            ->all();

        return [
            'categorias' => $categorias,
            'articulos' => $itemsCatalogo,
            // RF-T13 (aditivo): promos de alcance general vigentes HOY, para
            // el aviso "Promociones de hoy" de la home de la tienda.
            'promociones_genericas' => $this->promocionesGenericas($sucursal, $canalVentaId),
        ];
    }

    /**
     * Precio ANTES de promociones (para tachado), derivado del mismo cálculo
     * de PrecioService: se escala el precio final por la proporción
     * sin-descuento/con-descuento (IVA y ajustes escalan igual). Null cuando
     * no hubo descuento (la tienda no muestra tachado).
     */
    protected function precioLista(array $precioInfo): ?float
    {
        $descuento = (float) ($precioInfo['descuento_total'] ?? 0);
        $subSin = (float) ($precioInfo['subtotal_sin_descuento'] ?? 0);
        $subCon = (float) ($precioInfo['subtotal_con_descuento'] ?? 0);
        $precioFinal = (float) ($precioInfo['precio_final'] ?? 0);

        if ($descuento <= 0 || $subCon <= 0 || $subSin <= $subCon || $precioFinal <= 0) {
            return null;
        }

        return round($precioFinal * $subSin / $subCon, 2);
    }

    /**
     * Promociones de alcance GENERAL vigentes hoy (RF-T13): las que NO se
     * reflejan (o no completas) en el precio unitario del catálogo.
     *
     * - Promocion común AUTOMÁTICA (sin cupón) sin condición por_articulo:
     *   combos por cantidad/total/FP y descuentos por categoría.
     * - PromocionEspecial AUTOMÁTICA (NxM, grupos): por naturaleza dependen
     *   de la unión de varios artículos.
     *
     * Filtro "hoy": vigencia por fecha + día de semana. Las limitadas por
     * horario se listan igual (son "de hoy"; el detalle vive en descripcion).
     *
     * @return list<array{nombre: string, descripcion: string|null}>
     */
    protected function promocionesGenericas(Sucursal $sucursal, ?int $canalVentaId): array
    {
        $hoy = now()->dayOfWeek; // 0 = domingo (misma convención del modelo)

        $comunes = Promocion::activas()
            ->vigentes()
            ->porSucursal((int) $sucursal->id)
            ->automaticas()
            ->conUsosDisponibles()
            ->with('condiciones')
            ->get()
            ->filter(fn (Promocion $p) => empty($p->dias_semana) || in_array($hoy, array_map('intval', $p->dias_semana), true))
            ->filter(fn (Promocion $p) => ! $p->condiciones->contains('tipo_condicion', 'por_articulo'));

        $especiales = PromocionEspecial::activas()
            ->vigentes()
            ->where('sucursal_id', (int) $sucursal->id)
            ->where('modo_aplicacion', PromocionEspecial::MODO_AUTOMATICA)
            ->get()
            ->filter(fn (PromocionEspecial $p) => empty($p->dias_semana) || in_array($hoy, array_map('intval', $p->dias_semana), true))
            // Con canal restringido, solo si es el canal TIENDA.
            ->filter(fn (PromocionEspecial $p) => ! $p->canal_venta_id || (int) $p->canal_venta_id === (int) $canalVentaId);

        return $comunes->map(fn ($p) => ['nombre' => (string) $p->nombre, 'descripcion' => $p->descripcion !== '' ? $p->descripcion : null])
            ->concat($especiales->map(fn ($p) => ['nombre' => (string) $p->nombre, 'descripcion' => $p->descripcion !== '' ? $p->descripcion : null]))
            ->unique('nombre')
            ->values()
            ->all();
    }
}
