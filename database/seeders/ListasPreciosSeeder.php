<?php

namespace Database\Seeders;

use App\Models\ListaPrecio;
use App\Models\ListaPrecioCondicion;
use App\Models\ListaPrecioArticulo;
use App\Models\Sucursal;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\FormaPago;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use Illuminate\Database\Seeder;

/**
 * Seeder para crear listas de precios de ejemplo
 *
 * Crea:
 * 1. Lista Base obligatoria para cada sucursal (sin ajuste, sin condiciones)
 * 2. Lista Mayorista (+15% con condición de cantidad mínima)
 * 3. Lista Efectivo (-5% con condición de forma de pago)
 * 4. Lista Delivery (+10% con condición de forma de venta)
 *
 * FASE 2 - Sistema de Listas de Precios
 */
class ListasPreciosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursales = Sucursal::all();

        if ($sucursales->isEmpty()) {
            $this->command->warn('No hay sucursales. Ejecuta primero el seeder de sucursales.');
            return;
        }

        foreach ($sucursales as $sucursal) {
            $this->command->info("Creando listas de precios para sucursal: {$sucursal->nombre}");

            // 1. Lista Base (obligatoria)
            $this->crearListaBase($sucursal);

            // 2. Lista Mayorista
            $this->crearListaMayorista($sucursal);

            // 3. Lista Descuento Efectivo
            $this->crearListaEfectivo($sucursal);

            // 4. Lista Delivery
            $this->crearListaDelivery($sucursal);
        }

        $this->command->info('Listas de precios creadas exitosamente.');
    }

    /**
     * Crea la lista base obligatoria
     */
    protected function crearListaBase(Sucursal $sucursal): void
    {
        $existe = ListaPrecio::where('sucursal_id', $sucursal->id)
                             ->where('es_lista_base', true)
                             ->exists();

        if ($existe) {
            $this->command->warn("  - Lista Base ya existe para {$sucursal->nombre}");
            return;
        }

        ListaPrecio::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Lista Base',
            'codigo' => 'BASE',
            'descripcion' => 'Lista de precios base de la sucursal. Usa el precio definido en cada artículo sin ajustes.',
            'ajuste_porcentaje' => 0,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => true,
            'prioridad' => 999,
            'activo' => true,
        ]);

        $this->command->info("  + Lista Base creada");
    }

    /**
     * Crea lista para ventas mayoristas
     */
    protected function crearListaMayorista(Sucursal $sucursal): void
    {
        $existe = ListaPrecio::where('sucursal_id', $sucursal->id)
                             ->where('codigo', 'MAYORISTA')
                             ->exists();

        if ($existe) {
            $this->command->warn("  - Lista Mayorista ya existe para {$sucursal->nombre}");
            return;
        }

        $lista = ListaPrecio::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Lista Mayorista',
            'codigo' => 'MAYORISTA',
            'descripcion' => 'Precios especiales para compras mayoristas. Requiere cantidad mínima de 10 unidades.',
            'ajuste_porcentaje' => -15, // 15% descuento
            'redondeo' => 'decena',
            'aplica_promociones' => false, // No acumula con promociones
            'promociones_alcance' => 'todos',
            'cantidad_minima' => 10,
            'es_lista_base' => false,
            'prioridad' => 50,
            'activo' => true,
        ]);

        $this->command->info("  + Lista Mayorista creada (15% descuento, min 10 unidades)");
    }

    /**
     * Crea lista con descuento por pago en efectivo
     */
    protected function crearListaEfectivo(Sucursal $sucursal): void
    {
        $existe = ListaPrecio::where('sucursal_id', $sucursal->id)
                             ->where('codigo', 'EFECTIVO')
                             ->exists();

        if ($existe) {
            $this->command->warn("  - Lista Efectivo ya existe para {$sucursal->nombre}");
            return;
        }

        // Buscar forma de pago "Efectivo"
        $formaPagoEfectivo = FormaPago::where('concepto', 'efectivo')
                                       ->where('activo', true)
                                       ->first();

        $lista = ListaPrecio::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Descuento Efectivo',
            'codigo' => 'EFECTIVO',
            'descripcion' => '5% de descuento por pago en efectivo.',
            'ajuste_porcentaje' => -5, // 5% descuento
            'redondeo' => 'entero',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => false,
            'prioridad' => 80,
            'activo' => true,
        ]);

        // Agregar condición: solo si paga en efectivo
        if ($formaPagoEfectivo) {
            ListaPrecioCondicion::create([
                'lista_precio_id' => $lista->id,
                'tipo_condicion' => 'por_forma_pago',
                'forma_pago_id' => $formaPagoEfectivo->id,
            ]);

            $this->command->info("  + Lista Efectivo creada (5% descuento, condición: pago efectivo)");
        } else {
            $this->command->warn("  + Lista Efectivo creada SIN condición (no se encontró forma de pago efectivo)");
        }
    }

    /**
     * Crea lista con recargo para delivery
     */
    protected function crearListaDelivery(Sucursal $sucursal): void
    {
        $existe = ListaPrecio::where('sucursal_id', $sucursal->id)
                             ->where('codigo', 'DELIVERY')
                             ->exists();

        if ($existe) {
            $this->command->warn("  - Lista Delivery ya existe para {$sucursal->nombre}");
            return;
        }

        // Buscar forma de venta "Delivery"
        $formaVentaDelivery = FormaVenta::where('nombre', 'like', '%delivery%')
                                         ->where('activo', true)
                                         ->first();

        $lista = ListaPrecio::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Precios Delivery',
            'codigo' => 'DELIVERY',
            'descripcion' => '10% de recargo para pedidos por delivery.',
            'ajuste_porcentaje' => 10, // 10% recargo
            'redondeo' => 'centena',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => false,
            'prioridad' => 70,
            'activo' => true,
        ]);

        // Agregar condición: solo para delivery
        if ($formaVentaDelivery) {
            ListaPrecioCondicion::create([
                'lista_precio_id' => $lista->id,
                'tipo_condicion' => 'por_forma_venta',
                'forma_venta_id' => $formaVentaDelivery->id,
            ]);

            $this->command->info("  + Lista Delivery creada (10% recargo, condición: forma venta delivery)");
        } else {
            $this->command->warn("  + Lista Delivery creada SIN condición (no se encontró forma de venta delivery)");
        }
    }
}
