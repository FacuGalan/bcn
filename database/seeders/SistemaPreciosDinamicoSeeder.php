<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder Maestro: Sistema de Precios Dinámico
 *
 * Ejecuta todos los seeders del sistema de precios dinámico en el orden correcto.
 *
 * ORDEN DE EJECUCIÓN:
 * 1. CategoriasSeeder - Crear categorías
 * 2. FormasVentaSeeder - Crear formas de venta
 * 3. CanalesVentaSeeder - Crear canales de venta
 * 4. FormasPagoSeeder - Crear formas de pago y cuotas
 * 5. FormasPagoSucursalesSeeder - Habilitar formas de pago por sucursal
 * 6. PreciosBaseSeeder - Crear precios con diferentes especificidades
 * 7. PromocionesSeeder - Crear promociones, condiciones y escalas
 *
 * USO:
 * php artisan db:seed --class=SistemaPreciosDinamicoSeeder
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class SistemaPreciosDinamicoSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║                                                            ║\n";
        echo "║     SISTEMA DE PRECIOS DINÁMICO - SEEDER MAESTRO          ║\n";
        echo "║                                                            ║\n";
        echo "║     Inicializando datos para Comercio 1                   ║\n";
        echo "║                                                            ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $inicio = microtime(true);

        // Verificar que existan artículos y sucursales
        $this->verificarPrerequisitos();

        echo "⏱️  Ejecutando seeders en orden...\n\n";

        // 1. Categorías
        $this->call(CategoriasSeeder::class);
        $this->separador();

        // 2. Formas de Venta
        $this->call(FormasVentaSeeder::class);
        $this->separador();

        // 3. Canales de Venta
        $this->call(CanalesVentaSeeder::class);
        $this->separador();

        // 4. Formas de Pago (incluye cuotas)
        $this->call(FormasPagoSeeder::class);
        $this->separador();

        // 5. Formas de Pago por Sucursal
        $this->call(FormasPagoSucursalesSeeder::class);
        $this->separador();

        // 6. Precios Base
        $this->call(PreciosBaseSeeder::class);
        $this->separador();

        // 7. Promociones (incluye condiciones y escalas)
        $this->call(PromocionesSeeder::class);
        $this->separador();

        $duracion = round(microtime(true) - $inicio, 2);

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║                                                            ║\n";
        echo "║     ✅ SEEDER COMPLETADO EXITOSAMENTE                      ║\n";
        echo "║                                                            ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "⏱️  Tiempo total: {$duracion} segundos\n";
        echo "\n";
        echo "📊 RESUMEN DE DATOS CREADOS:\n";
        echo "   ✓ 10 Categorías\n";
        echo "   ✓ 5 Formas de Venta\n";
        echo "   ✓ 8 Canales de Venta\n";
        echo "   ✓ 8 Formas de Pago + 6 Planes de Cuotas\n";
        echo "   ✓ Configuraciones por Sucursal\n";
        echo "   ✓ Precios Base con 4 niveles de especificidad\n";
        echo "   ✓ 10 Promociones con condiciones y escalas\n";
        echo "\n";
        echo "🎯 PRÓXIMOS PASOS:\n";
        echo "   1. Probar el sistema en el POS (Nueva Venta)\n";
        echo "   2. Verificar cálculos de precios según especificidad\n";
        echo "   3. Testear aplicación de promociones\n";
        echo "   4. Validar descuentos por forma de pago\n";
        echo "   5. Comprobar descuentos escalonados\n";
        echo "\n";
        echo "💡 EJEMPLOS PARA PROBAR:\n";
        echo "   - Coca Cola: Tiene 4 precios diferentes según contexto\n";
        echo "   - Agua Mineral: Precios diferentes por sucursal\n";
        echo "   - Papas Lays: Precio mayorista vs minorista vs salón\n";
        echo "   - Arroz: Precio en oferta con vigencia\n";
        echo "   - Bebidas: 20% OFF (Central) y Happy Hour 30% OFF (17-20hs)\n";
        echo "   - Snacks: Descuentos escalonados (2,3,5+ unidades)\n";
        echo "   - Cupón: VERANO2025 para 15% OFF\n";
        echo "\n";
    }

    private function verificarPrerequisitos(): void
    {
        echo "🔍 Verificando prerequisitos...\n";

        // Verificar que exista el Comercio 1
        $comercio = \App\Models\Comercio::find(1);
        if (! $comercio) {
            echo "\n❌ ERROR: No existe el Comercio 1\n";
            echo "   Ejecuta primero: php artisan db:seed\n\n";
            exit(1);
        }

        // Configurar tenant
        $prefix = str_pad(1, 6, '0', STR_PAD_LEFT).'_';
        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes',
        ]);
        \Illuminate\Support\Facades\DB::purge('pymes_tenant');

        // Verificar que existan sucursales
        $sucursales = \App\Models\Sucursal::count();
        if ($sucursales === 0) {
            echo "\n❌ ERROR: No existen sucursales\n";
            echo "   Ejecuta primero: php artisan db:seed --class=DemoComercio1Seeder\n\n";
            exit(1);
        }

        // Verificar que existan artículos
        $articulos = \App\Models\Articulo::count();
        if ($articulos === 0) {
            echo "\n❌ ERROR: No existen artículos\n";
            echo "   Ejecuta primero: php artisan db:seed --class=DemoComercio1Seeder\n\n";
            exit(1);
        }

        echo "   ✓ Comercio 1 encontrado\n";
        echo "   ✓ {$sucursales} sucursales disponibles\n";
        echo "   ✓ {$articulos} artículos disponibles\n\n";
    }

    private function separador(): void
    {
        echo "────────────────────────────────────────────────────────────\n\n";
    }
}
