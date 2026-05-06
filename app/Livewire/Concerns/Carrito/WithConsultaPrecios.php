<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;

/**
 * Consulta de precios de un articulo en todas las listas vigentes.
 *
 * Encapsula:
 * - Modal de consulta con la grilla de precios por lista (incluyendo % ajuste y precio fijo).
 * - Calculo del precio base efectivo (con override de sucursal).
 * - Cierre del modal.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->sucursalId            (SucursalAware)
 * - $this->busquedaArticulo      (WithBusquedaArticulos)
 * - $this->articulosResultados   (WithBusquedaArticulos)
 * - $this->modoConsulta          (WithCarritoItems)
 */
trait WithConsultaPrecios
{
    // =========================================
    // PROPIEDADES DE CONSULTA DE PRECIOS
    // =========================================

    /** @var array|null Artículo seleccionado para consulta de precios */
    public $articuloConsulta = null;

    /** @var bool Modal de consulta visible */
    public $mostrarModalConsulta = false;

    // =========================================
    // CONSULTA / CIERRE
    // =========================================

    /**
     * Consulta precios de un artículo en todas las listas
     */
    public function consultarPrecios($articuloId)
    {
        $articulo = Articulo::with('categoriaModel')->find($articuloId);

        if (! $articulo) {
            $this->dispatch('toast-error', message: __('Artículo no encontrado'));

            return;
        }

        // Obtener todas las listas de precios de la sucursal
        $listasPrecios = ListaPrecio::where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->orderBy('es_lista_base', 'desc')
            ->orderBy('prioridad')
            ->get();

        $precioBase = $articulo->obtenerPrecioBaseEfectivo($this->sucursalId);
        $precios = [];

        foreach ($listasPrecios as $lista) {
            // Verificar si tiene precio específico en la lista
            $detalleArticulo = ListaPrecioArticulo::buscarParaArticulo(
                $lista->id,
                $articuloId,
                $articulo->categoria_id
            );

            $tienePrecioEspecifico = false;
            $ajusteAplicado = $lista->ajuste_porcentaje ?? 0;

            if ($detalleArticulo) {
                // Usar el método calcularPrecio del modelo
                $resultado = $detalleArticulo->calcularPrecio($precioBase, $lista->ajuste_porcentaje ?? 0);
                $precioFinal = $resultado['precio'];
                $ajusteAplicado = $resultado['ajuste_porcentaje'];
                $tienePrecioEspecifico = in_array($resultado['tipo'], ['precio_fijo', 'ajuste_detalle']);
            } else {
                // Aplicar ajuste porcentual del encabezado
                $precioFinal = $precioBase * (1 + ($ajusteAplicado / 100));
            }

            $precios[] = [
                'lista_id' => $lista->id,
                'lista_nombre' => $lista->nombre,
                'es_lista_base' => $lista->es_lista_base,
                'ajuste_porcentaje' => $ajusteAplicado,
                'precio' => round($precioFinal, 2),
                'tiene_precio_especifico' => $tienePrecioEspecifico,
            ];
        }

        $this->articuloConsulta = [
            'id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'nombre' => $articulo->nombre,
            'categoria' => $articulo->categoriaModel?->nombre ?? $articulo->categoria,
            'precio_base' => $precioBase,
            'precios' => $precios,
        ];

        $this->mostrarModalConsulta = true;
        $this->modoConsulta = false;
        $this->busquedaArticulo = '';
        $this->articulosResultados = [];
    }

    /**
     * Cierra el modal de consulta
     */
    public function cerrarModalConsulta()
    {
        $this->mostrarModalConsulta = false;
        $this->articuloConsulta = null;
        $this->dispatch('focus-busqueda');
    }
}
