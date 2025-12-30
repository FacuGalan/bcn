<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Categoria;
use App\Models\Articulo;
use App\Models\Comercio;

/**
 * Seeder: Categorías
 *
 * Crea categorías de ejemplo para clasificar artículos.
 * También asigna categorías a los artículos existentes.
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class CategoriasSeeder extends Seeder
{
    private $comercioId = 1;
    private $categorias = [];

    public function run(): void
    {
        echo "🏷️  Iniciando seeder de Categorías...\n\n";

        $this->configurarTenant();
        $this->crearCategorias();
        $this->asignarCategoriasAArticulos();

        echo "\n✅ Seeder completado exitosamente!\n";
        echo "📊 Resumen:\n";
        echo "   - Categorías creadas: " . count($this->categorias) . "\n\n";
    }

    private function configurarTenant(): void
    {
        echo "⚙️  Configurando tenant para comercio {$this->comercioId}...\n";

        $comercio = Comercio::find($this->comercioId);
        $prefix = str_pad($this->comercioId, 6, '0', STR_PAD_LEFT) . '_';

        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes'
        ]);

        DB::purge('pymes_tenant');
        echo "   ✓ Tenant configurado (prefix: {$prefix})\n\n";
    }

    private function crearCategorias(): void
    {
        echo "📁 Creando categorías...\n";

        $categoriasData = [
            [
                'nombre' => 'Bebidas',
                'codigo' => 'BEB',
                'descripcion' => 'Bebidas en general: gaseosas, aguas, jugos, cervezas',
                'color' => '#3B82F6', // Azul
                'activo' => true,
            ],
            [
                'nombre' => 'Snacks',
                'codigo' => 'SNK',
                'descripcion' => 'Snacks y golosinas: papas fritas, alfajores, galletitas',
                'color' => '#F59E0B', // Naranja
                'activo' => true,
            ],
            [
                'nombre' => 'Limpieza',
                'codigo' => 'LIM',
                'descripcion' => 'Productos de limpieza e higiene',
                'color' => '#10B981', // Verde
                'activo' => true,
            ],
            [
                'nombre' => 'Alimentos',
                'codigo' => 'ALM',
                'descripcion' => 'Alimentos básicos: arroz, fideos, aceite, etc.',
                'color' => '#EF4444', // Rojo
                'activo' => true,
            ],
            [
                'nombre' => 'Lácteos',
                'codigo' => 'LAC',
                'descripcion' => 'Productos lácteos: leche, yogur, queso',
                'color' => '#8B5CF6', // Púrpura
                'activo' => true,
            ],
            [
                'nombre' => 'Carnes',
                'codigo' => 'CAR',
                'descripcion' => 'Carnes rojas, pollo, pescado',
                'color' => '#DC2626', // Rojo oscuro
                'activo' => true,
            ],
            [
                'nombre' => 'Panadería',
                'codigo' => 'PAN',
                'descripcion' => 'Pan, facturas, productos de panadería',
                'color' => '#D97706', // Marrón
                'activo' => true,
            ],
            [
                'nombre' => 'Congelados',
                'codigo' => 'CON',
                'descripcion' => 'Productos congelados',
                'color' => '#06B6D4', // Cyan
                'activo' => true,
            ],
            [
                'nombre' => 'Ofertas',
                'codigo' => 'OFE',
                'descripcion' => 'Productos en oferta especial',
                'color' => '#EC4899', // Rosa
                'activo' => true,
            ],
            [
                'nombre' => 'Higiene Personal',
                'codigo' => 'HIG',
                'descripcion' => 'Productos de higiene personal',
                'color' => '#14B8A6', // Teal
                'activo' => true,
            ],
        ];

        foreach ($categoriasData as $data) {
            // Verificar si ya existe
            $existing = Categoria::where('codigo', $data['codigo'])->first();
            if ($existing) {
                $this->categorias[$data['codigo']] = $existing;
                echo "   ⚠️  {$data['nombre']} ya existe\n";
                continue;
            }

            $categoria = Categoria::create($data);
            $this->categorias[$data['codigo']] = $categoria;
            echo "   ✓ {$data['nombre']} ({$data['codigo']})\n";
        }
        echo "\n";
    }

    private function asignarCategoriasAArticulos(): void
    {
        echo "🔗 Asignando categorías a artículos existentes...\n";

        // Mapeo de códigos de artículos a códigos de categorías
        $mapeo = [
            // Bebidas
            'BEB001' => 'BEB', // Coca Cola
            'BEB002' => 'BEB', // Agua Mineral
            'BEB003' => 'BEB', // Cerveza
            'BEB004' => 'BEB', // Jugo

            // Snacks
            'SNK001' => 'SNK', // Papas Lays
            'SNK002' => 'SNK', // Alfajor
            'SNK003' => 'SNK', // Galletitas

            // Limpieza
            'LIM001' => 'LIM', // Detergente
            'LIM002' => 'LIM', // Lavandina
            'LIM003' => 'LIM', // Esponja

            // Alimentos
            'ALM001' => 'ALM', // Arroz
            'ALM002' => 'ALM', // Fideos
            'ALM003' => 'ALM', // Aceite
        ];

        $contador = 0;
        foreach ($mapeo as $codigoArticulo => $codigoCategoria) {
            $articulo = Articulo::where('codigo', $codigoArticulo)->first();

            if (!$articulo) {
                echo "   ⚠️  Artículo {$codigoArticulo} no encontrado\n";
                continue;
            }

            if (!isset($this->categorias[$codigoCategoria])) {
                echo "   ⚠️  Categoría {$codigoCategoria} no encontrada\n";
                continue;
            }

            $categoria = $this->categorias[$codigoCategoria];

            // Solo asignar si no tiene categoría ya
            if (!$articulo->categoria_id) {
                $articulo->categoria_id = $categoria->id;
                $articulo->save();
                $contador++;
            }
        }

        echo "   ✓ {$contador} artículos actualizados con categorías\n";
    }
}
