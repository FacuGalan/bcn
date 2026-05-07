<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\HistorialPrecio;
use App\Models\Sucursal;
use App\Models\TipoIva;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Alta rapida de articulo desde NuevaVenta.
 *
 * Encapsula:
 * - Modal con form: nombre, categoria, codigo (auto-generado por prefijo),
 *   codigo de barras, unidad de medida, tipo IVA, precio base.
 * - Validacion (unique en codigo dentro de pymes_tenant).
 * - Creacion del articulo + sincronizacion con sucursales (activo solo en la actual).
 * - Registro de historial de precio.
 * - Auto-agregado al carrito tras creacion exitosa.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->busquedaArticulo       (WithBusquedaArticulos — pre-llena el nombre)
 * - $this->agregarArticulo()      (WithCarritoItems — agrega al crear)
 */
trait WithArticuloRapido
{
    // =========================================
    // PROPIEDADES DE ALTA RÁPIDA DE ARTÍCULO
    // =========================================

    public bool $mostrarModalArticuloRapido = false;

    public string $artRapidoNombre = '';

    public ?int $artRapidoCategoriaId = null;

    public string $artRapidoCodigo = '';

    public string $artRapidoCodigoBarras = '';

    public string $artRapidoUnidadMedida = 'unidad';

    public ?int $artRapidoTipoIvaId = null;

    public ?float $artRapidoPrecioBase = null;

    public $artRapidoCategorias = [];

    public $artRapidoTiposIva = [];

    // =========================================
    // ABRIR / CERRAR / RESET
    // =========================================

    public function abrirModalArticuloRapido(): void
    {
        $this->resetArticuloRapido();

        $this->artRapidoCategorias = Categoria::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color', 'prefijo']);

        $this->artRapidoTiposIva = TipoIva::orderBy('nombre')
            ->get(['id', 'nombre', 'porcentaje']);

        // Preseleccionar IVA 21% por defecto
        $iva21 = $this->artRapidoTiposIva->firstWhere('porcentaje', 21);
        $this->artRapidoTipoIvaId = $iva21?->id;

        // Pre-llenar con lo que el usuario escribió en la búsqueda
        $busqueda = trim($this->busquedaArticulo);
        if (! empty($busqueda)) {
            $this->artRapidoNombre = $busqueda;
        }

        $this->mostrarModalArticuloRapido = true;
    }

    public function cerrarModalArticuloRapido(): void
    {
        $this->mostrarModalArticuloRapido = false;
        $this->resetArticuloRapido();
        $this->dispatch('focus-busqueda');
    }

    protected function resetArticuloRapido(): void
    {
        $this->artRapidoNombre = '';
        $this->artRapidoCategoriaId = null;
        $this->artRapidoCodigo = '';
        $this->artRapidoCodigoBarras = '';
        $this->artRapidoUnidadMedida = 'unidad';
        $this->artRapidoTipoIvaId = null;
        $this->artRapidoPrecioBase = null;
        $this->artRapidoCategorias = [];
        $this->artRapidoTiposIva = [];
    }

    // =========================================
    // AUTO-CODIGO POR CATEGORIA
    // =========================================

    public function updatedArtRapidoCategoriaId($value): void
    {
        if (! $value) {
            return;
        }

        $categoria = Categoria::find($value);
        if ($categoria && $categoria->prefijo) {
            $ultimoCodigo = Articulo::where('codigo', 'like', $categoria->prefijo.'-%')
                ->orderByRaw('CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED) DESC')
                ->value('codigo');

            if ($ultimoCodigo) {
                $numero = (int) last(explode('-', $ultimoCodigo)) + 1;
            } else {
                $numero = 1;
            }

            $this->artRapidoCodigo = $categoria->prefijo.'-'.str_pad($numero, 3, '0', STR_PAD_LEFT);
        }
    }

    // =========================================
    // GUARDAR
    // =========================================

    public function guardarArticuloRapido(): void
    {
        $this->validate([
            'artRapidoNombre' => 'required|string|min:2|max:200',
            'artRapidoCodigo' => 'required|string|max:50|unique:pymes_tenant.articulos,codigo',
            'artRapidoCodigoBarras' => 'nullable|string|max:50',
            'artRapidoCategoriaId' => 'nullable|exists:pymes_tenant.categorias,id',
            'artRapidoUnidadMedida' => 'required|string|max:50',
            'artRapidoTipoIvaId' => 'required|exists:pymes_tenant.tipos_iva,id',
            'artRapidoPrecioBase' => 'required|numeric|min:0',
        ], [
            'artRapidoNombre.required' => __('El nombre es obligatorio'),
            'artRapidoNombre.min' => __('El nombre debe tener al menos 2 caracteres'),
            'artRapidoCodigo.required' => __('El código es obligatorio'),
            'artRapidoCodigo.unique' => __('Ya existe un artículo con este código'),
            'artRapidoTipoIvaId.required' => __('Seleccione un tipo de IVA'),
            'artRapidoPrecioBase.required' => __('El precio es obligatorio'),
        ]);

        try {
            $articulo = Articulo::create([
                'nombre' => $this->artRapidoNombre,
                'codigo' => $this->artRapidoCodigo,
                'codigo_barras' => $this->artRapidoCodigoBarras ?: null,
                'categoria_id' => $this->artRapidoCategoriaId,
                'unidad_medida' => $this->artRapidoUnidadMedida,
                'tipo_iva_id' => $this->artRapidoTipoIvaId,
                'precio_iva_incluido' => true,
                'precio_base' => $this->artRapidoPrecioBase,
                'es_materia_prima' => false,
                'activo' => true,
            ]);

            HistorialPrecio::registrar([
                'articulo_id' => $articulo->id,
                'precio_anterior' => 0,
                'precio_nuevo' => $this->artRapidoPrecioBase,
                'origen' => 'articulo_crear',
            ]);

            // Sincronizar con todas las sucursales (activo solo en la actual)
            $todasSucursales = Sucursal::pluck('id')->toArray();
            $sucursalActiva = sucursal_activa();
            $syncData = [];
            foreach ($todasSucursales as $sucursalId) {
                $esActiva = $sucursalId == $sucursalActiva;
                $syncData[$sucursalId] = [
                    'activo' => $esActiva,
                    'modo_stock' => 'ninguno',
                    'vendible' => true,
                    'precio_base' => null,
                ];
            }
            $articulo->sucursales()->sync($syncData);

            // Agregar el artículo recién creado al carrito
            $this->agregarArticulo($articulo->id);

            $this->cerrarModalArticuloRapido();

            $this->dispatch('notify',
                message: __('Artículo ":nombre" creado y agregado', ['nombre' => $articulo->nombre]),
                type: 'success'
            );
        } catch (Exception $e) {
            Log::error('Error al crear artículo rápido: '.$e->getMessage());
            $this->dispatch('notify',
                message: __('Error al crear el artículo'),
                type: 'error'
            );
        }
    }
}
