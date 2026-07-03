<?php

namespace App\Services\Pedidos;

use App\Models\Articulo;
use App\Models\CanalVenta;
use App\Models\Categoria;
use App\Models\FormaVenta;
use App\Models\PedidoDelivery;
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
                'gruposOpcionales.opcionales' => fn ($q) => $q->where('activo', true)->orderBy('orden'),
            ])
            ->orderByDesc('destacado')
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
                'descripcion' => $articulo->descripcion,
                'categoria_id' => $articulo->categoria_id,
                'imagen_url' => $articulo->imagenUrl(),
                'destacado' => (bool) $articulo->destacado,
                'orden' => (int) $articulo->orden,
                'pesable' => (bool) $articulo->pesable,
                'precio' => (float) ($precioInfo['precio_final'] ?? $precioInfo['precio'] ?? 0),
                'promociones' => collect($precioInfo['promociones_aplicadas'] ?? [])
                    ->map(fn ($p) => is_array($p) ? ($p['nombre'] ?? null) : null)
                    ->filter()
                    ->values()
                    ->all(),
                'agotado' => $agotado,
                'pedible' => ! $agotado,
                'opcionales' => $articulo->gruposOpcionales->map(fn ($grupo) => [
                    'grupo_id' => (int) $grupo->id,
                    'nombre' => $grupo->nombre,
                    'obligatorio' => (bool) ($grupo->pivot->obligatorio ?? $grupo->obligatorio ?? false),
                    'min' => (int) ($grupo->pivot->min_selecciones ?? $grupo->min_selecciones ?? 0),
                    'max' => (int) ($grupo->pivot->max_selecciones ?? $grupo->max_selecciones ?? 0),
                    'opciones' => $grupo->opcionales->map(fn ($op) => [
                        'id' => (int) $op->id,
                        'nombre' => $op->nombre,
                        'precio_extra' => (float) $op->precio_extra,
                    ])->values()->all(),
                ])->values()->all(),
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
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($cat->imagen_path)
                    : null,
            ])
            ->values()
            ->all();

        return [
            'categorias' => $categorias,
            'articulos' => $itemsCatalogo,
        ];
    }
}
