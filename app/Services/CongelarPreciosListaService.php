<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Congelación de Precios para Listas Estáticas
 *
 * Genera un snapshot completo de precios para una lista de precios marcada
 * como estática. Itera todos los artículos activos de la sucursal y persiste
 * el precio final calculado en lista_precio_articulos.precio_fijo, de modo
 * que el precio de venta no varíe aunque cambie el precio base del artículo.
 *
 * JERARQUÍA DE AJUSTE (se aplica en snapshot y re-snapshot):
 * 1. Fila específica del artículo con ajuste_porcentaje → usa ese %
 * 2. Fila de la categoría del artículo con ajuste_porcentaje → usa ese %
 * 3. ajuste_porcentaje del encabezado de la lista
 *
 * EXCEPCIÓN (precio manual):
 * Si ya existe una fila específica del artículo con precio_fijo != null
 * Y ajuste_porcentaje == null, se considera un precio manual puesto por
 * el usuario y NO se toca.
 */
class CongelarPreciosListaService
{
    /**
     * Congela los precios de una lista estática.
     *
     * @return int Cantidad de precios congelados / actualizados
     *
     * @throws Exception
     */
    public function congelar(ListaPrecio $lista): int
    {
        if (! $lista->estatica) {
            throw new Exception(__('La lista no es estática'));
        }

        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $ajusteHeader = (float) $lista->ajuste_porcentaje;

            $filasCategoria = $lista->articulos()
                ->whereNotNull('categoria_id')
                ->whereNull('articulo_id')
                ->get()
                ->keyBy('categoria_id');

            $filasArticulo = $lista->articulos()
                ->whereNotNull('articulo_id')
                ->get()
                ->keyBy('articulo_id');

            $articulos = Articulo::activos()
                ->whereHas('sucursales', function ($q) use ($lista) {
                    $q->where('sucursal_id', $lista->sucursal_id)
                        ->where('articulos_sucursales.activo', 1);
                })
                ->get();

            $congelados = 0;

            foreach ($articulos as $articulo) {
                $filaArticulo = $filasArticulo->get($articulo->id);

                // Excepción: precio manual puesto por el usuario — preservar intacto
                if ($filaArticulo && $filaArticulo->precio_fijo !== null && $filaArticulo->ajuste_porcentaje === null) {
                    $congelados++;

                    continue;
                }

                $ajusteAplicable = $this->determinarAjuste(
                    $filaArticulo,
                    $articulo->categoria_id ? $filasCategoria->get($articulo->categoria_id) : null,
                    $ajusteHeader
                );

                $precioBase = $articulo->obtenerPrecioBaseEfectivo($lista->sucursal_id);
                $precioCalculado = $precioBase * (1 + ($ajusteAplicable / 100));
                $precioFijo = $lista->aplicarRedondeo($precioCalculado);

                $origen = ($filaArticulo && $filaArticulo->origen === 'manual') ? 'manual' : 'snapshot';

                ListaPrecioArticulo::updateOrCreate(
                    [
                        'lista_precio_id' => $lista->id,
                        'articulo_id' => $articulo->id,
                    ],
                    [
                        'categoria_id' => null,
                        'precio_fijo' => $precioFijo,
                        'ajuste_porcentaje' => $ajusteAplicable,
                        'precio_base_original' => $precioBase,
                        'origen' => $origen,
                    ]
                );

                $congelados++;
            }

            $lista->forceFill(['precios_congelados_at' => now()])->save();

            DB::connection('pymes_tenant')->commit();

            Log::info('Precios de lista estática congelados', [
                'lista_precio_id' => $lista->id,
                'sucursal_id' => $lista->sucursal_id,
                'congelados' => $congelados,
            ]);

            return $congelados;
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error al congelar precios de lista', [
                'lista_precio_id' => $lista->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determina el ajuste porcentual aplicable según la jerarquía:
     * fila específica > categoría > header.
     */
    private function determinarAjuste(?ListaPrecioArticulo $filaArticulo, ?ListaPrecioArticulo $filaCategoria, float $ajusteHeader): float
    {
        if ($filaArticulo && $filaArticulo->ajuste_porcentaje !== null) {
            return (float) $filaArticulo->ajuste_porcentaje;
        }

        if ($filaCategoria && $filaCategoria->ajuste_porcentaje !== null) {
            return (float) $filaCategoria->ajuste_porcentaje;
        }

        return $ajusteHeader;
    }
}
