<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\CambioPrecioProgramado;
use App\Models\HistorialPrecio;
use App\Models\ListaPrecioArticulo;
use Illuminate\Support\Facades\DB;

class CambioPrecioProgramadoService
{
    /**
     * Ejecuta un cambio de precio programado.
     */
    public function ejecutarCambioProgramado(CambioPrecioProgramado $cambio): void
    {
        try {
            DB::connection('pymes_tenant')->beginTransaction();

            $articulosData = $cambio->articulos_data;
            $articulosActualizados = 0;
            $listasActualizadas = 0;

            // Construir detalle del cambio masivo
            $tipoAjusteLabel = $cambio->tipo_ajuste === 'recargo' ? __('Recargo') : __('Descuento');
            $tipoValorLabel = $cambio->tipo_valor === 'porcentual' ? '%' : '$';
            $redondeoLabel = $cambio->tipo_redondeo !== 'sin_redondeo' ? ', ' . __('redondeo') . ' ' . $cambio->tipo_redondeo : '';
            $detalleMasivo = "{$tipoAjusteLabel} {$cambio->valor_ajuste}{$tipoValorLabel}{$redondeoLabel} (" . __('programado') . ")";

            if ($cambio->alcance_precio === 'sucursal_actual' && $cambio->sucursal_id) {
                $articulosActualizados = $this->aplicarCambioSucursal($cambio, $articulosData, $detalleMasivo);
            } else {
                [$articulosActualizados, $listasActualizadas] = $this->aplicarCambioGlobal($cambio, $articulosData, $detalleMasivo);
            }

            $mensaje = __('Se actualizaron :count artículos', ['count' => $articulosActualizados]);
            if ($listasActualizadas > 0) {
                $mensaje .= ' ' . __('y :count precios en listas', ['count' => $listasActualizadas]);
            }

            $cambio->update([
                'estado' => 'procesado',
                'resultado' => $mensaje,
                'procesado_at' => now(),
            ]);

            DB::connection('pymes_tenant')->commit();
        } catch (\Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            $cambio->update([
                'estado' => 'error',
                'resultado' => $e->getMessage(),
                'procesado_at' => now(),
            ]);
        }
    }

    /**
     * Aplica cambio de precio a una sucursal específica.
     */
    protected function aplicarCambioSucursal(CambioPrecioProgramado $cambio, array $articulosData, string $detalleMasivo): int
    {
        $sucursalId = $cambio->sucursal_id;
        $articulosActualizados = 0;

        foreach ($articulosData as $articuloData) {
            $precioNuevo = (float) $articuloData['precio_nuevo'];

            $exists = DB::connection('pymes_tenant')
                ->table('articulos_sucursales')
                ->where('articulo_id', $articuloData['id'])
                ->where('sucursal_id', $sucursalId)
                ->exists();

            if ($exists) {
                DB::connection('pymes_tenant')
                    ->table('articulos_sucursales')
                    ->where('articulo_id', $articuloData['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->update(['precio_base' => $precioNuevo, 'updated_at' => now()]);
            } else {
                DB::connection('pymes_tenant')
                    ->table('articulos_sucursales')
                    ->insert([
                        'articulo_id' => $articuloData['id'],
                        'sucursal_id' => $sucursalId,
                        'activo' => true,
                        'modo_stock' => 'ninguno',
                        'vendible' => true,
                        'precio_base' => $precioNuevo,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            HistorialPrecio::registrar([
                'articulo_id' => $articuloData['id'],
                'sucursal_id' => $sucursalId,
                'precio_anterior' => (float) $articuloData['precio_viejo'],
                'precio_nuevo' => $precioNuevo,
                'usuario_id' => $cambio->usuario_id,
                'origen' => 'masivo_sucursal',
                'porcentaje_cambio' => $cambio->tipo_valor === 'porcentual'
                    ? (float) $cambio->valor_ajuste * ($cambio->tipo_ajuste === 'descuento' ? -1 : 1)
                    : null,
                'detalle' => $detalleMasivo,
            ]);

            $articulosActualizados++;
        }

        return $articulosActualizados;
    }

    /**
     * Aplica cambio de precio global (todas las sucursales).
     */
    protected function aplicarCambioGlobal(CambioPrecioProgramado $cambio, array $articulosData, string $detalleMasivo): array
    {
        $articulosActualizados = 0;
        $listasActualizadas = 0;
        $articuloIds = array_column($articulosData, 'id');

        foreach ($articulosData as $articuloData) {
            $articulo = Articulo::find($articuloData['id']);

            if (!$articulo) {
                continue;
            }

            $precioViejo = (float) $articulo->precio_base;
            $precioNuevo = (float) $articuloData['precio_nuevo'];

            $porcentajeCambio = $precioViejo > 0
                ? (($precioNuevo - $precioViejo) / $precioViejo) * 100
                : 0;

            $articulo->precio_base = $precioNuevo;
            $articulo->save();
            $articulosActualizados++;

            HistorialPrecio::registrar([
                'articulo_id' => $articulo->id,
                'precio_anterior' => $precioViejo,
                'precio_nuevo' => $precioNuevo,
                'usuario_id' => $cambio->usuario_id,
                'origen' => 'masivo_global',
                'porcentaje_cambio' => $cambio->tipo_valor === 'porcentual'
                    ? (float) $cambio->valor_ajuste * ($cambio->tipo_ajuste === 'descuento' ? -1 : 1)
                    : null,
                'detalle' => $detalleMasivo,
            ]);

            // Actualizar precios fijos en lista_precio_articulos
            $registrosLista = ListaPrecioArticulo::where('articulo_id', $articulo->id)
                ->whereNotNull('precio_fijo')
                ->get();

            foreach ($registrosLista as $registro) {
                $precioFijoViejo = (float) $registro->precio_fijo;
                $precioFijoNuevo = $precioFijoViejo * (1 + ($porcentajeCambio / 100));
                $precioFijoNuevo = $this->aplicarRedondeo($precioFijoNuevo, $cambio->tipo_redondeo);

                $registro->precio_fijo = $precioFijoNuevo;
                $registro->precio_base_original = $precioNuevo;
                $registro->save();
                $listasActualizadas++;
            }
        }

        // Registrar historial para overrides que serán eliminados
        $overridesExistentes = DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->whereIn('articulo_id', $articuloIds)
            ->whereNotNull('precio_base')
            ->get(['articulo_id', 'sucursal_id', 'precio_base']);

        foreach ($overridesExistentes as $override) {
            $precioGenericoNuevo = 0;
            foreach ($articulosData as $ad) {
                if ($ad['id'] == $override->articulo_id) {
                    $precioGenericoNuevo = (float) $ad['precio_nuevo'];
                    break;
                }
            }

            HistorialPrecio::registrar([
                'articulo_id' => $override->articulo_id,
                'sucursal_id' => $override->sucursal_id,
                'precio_anterior' => (float) $override->precio_base,
                'precio_nuevo' => $precioGenericoNuevo,
                'usuario_id' => $cambio->usuario_id,
                'origen' => 'masivo_global',
                'detalle' => __('Override eliminado por cambio masivo global') . ' (' . __('programado') . ')',
            ]);
        }

        // Eliminar overrides de precio_base
        DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->whereIn('articulo_id', $articuloIds)
            ->whereNotNull('precio_base')
            ->update(['precio_base' => null, 'updated_at' => now()]);

        return [$articulosActualizados, $listasActualizadas];
    }

    /**
     * Aplica redondeo según configuración.
     */
    protected function aplicarRedondeo(float $precio, string $tipoRedondeo): float
    {
        return match ($tipoRedondeo) {
            'entero' => round($precio),
            'decena' => round($precio / 10) * 10,
            'centena' => round($precio / 100) * 100,
            default => round($precio, 2),
        };
    }
}
