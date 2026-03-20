<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\CanalVenta;
use App\Models\Comercio;
use App\Models\FormaVenta;
use App\Models\PrecioBase;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: Precios Base
 *
 * Crea precios con diferentes niveles de especificidad para demostrar
 * la jerarquía del sistema:
 * 1. Precio genérico (sin forma ni canal)
 * 2. Precio por forma de venta
 * 3. Precio por canal de venta
 * 4. Precio por forma + canal (más específico)
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class PreciosBaseSeeder extends Seeder
{
    private $comercioId = 1;

    private $articulos = [];

    private $sucursales = [];

    private $formasVenta = [];

    private $canalesVenta = [];

    public function run(): void
    {
        echo "💰 Iniciando seeder de Precios Base...\n\n";

        $this->configurarTenant();
        $this->cargarDatos();
        $this->crearPreciosEjemplo();

        echo "\n✅ Seeder completado exitosamente!\n\n";
    }

    private function configurarTenant(): void
    {
        echo "⚙️  Configurando tenant para comercio {$this->comercioId}...\n";

        $comercio = Comercio::find($this->comercioId);
        $prefix = str_pad($this->comercioId, 6, '0', STR_PAD_LEFT).'_';

        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes',
        ]);

        DB::purge('pymes_tenant');
        echo "   ✓ Tenant configurado (prefix: {$prefix})\n\n";
    }

    private function cargarDatos(): void
    {
        echo "📥 Cargando datos necesarios...\n";

        // Cargar artículos
        $this->articulos = Articulo::whereIn('codigo', [
            'BEB001', // Coca Cola
            'BEB002', // Agua Mineral
            'SNK001', // Papas Lays
            'ALM001', // Arroz
        ])->get()->keyBy('codigo');

        // Cargar sucursales
        $this->sucursales = Sucursal::where('activa', true)->get()->keyBy('codigo');

        // Cargar formas de venta
        $this->formasVenta = FormaVenta::where('activo', true)->get()->keyBy('codigo');

        // Cargar canales de venta
        $this->canalesVenta = CanalVenta::where('activo', true)->get()->keyBy('codigo');

        echo "   ✓ {$this->articulos->count()} artículos\n";
        echo "   ✓ {$this->sucursales->count()} sucursales\n";
        echo "   ✓ {$this->formasVenta->count()} formas de venta\n";
        echo "   ✓ {$this->canalesVenta->count()} canales de venta\n\n";
    }

    private function crearPreciosEjemplo(): void
    {
        echo "💲 Creando precios de ejemplo...\n\n";

        // ============================================================
        // EJEMPLO 1: COCA COLA - Todos los niveles de especificidad
        // ============================================================
        if (isset($this->articulos['BEB001']) && isset($this->sucursales['CENTRAL'])) {
            $cocaCola = $this->articulos['BEB001'];
            $central = $this->sucursales['CENTRAL'];

            echo "   📦 Coca Cola 500ml - Casa Central:\n";

            // Nivel 1: Precio genérico (base)
            $this->crearPrecio([
                'articulo_id' => $cocaCola->id,
                'sucursal_id' => $central->id,
                'forma_venta_id' => null,
                'canal_venta_id' => null,
                'precio' => 350,
                'descripcion' => 'Precio genérico (base)',
            ]);

            // Nivel 2: Precio por canal (Web más caro)
            if (isset($this->canalesVenta['WEB'])) {
                $this->crearPrecio([
                    'articulo_id' => $cocaCola->id,
                    'sucursal_id' => $central->id,
                    'forma_venta_id' => null,
                    'canal_venta_id' => $this->canalesVenta['WEB']->id,
                    'precio' => 380,
                    'descripcion' => 'Precio por canal (Web)',
                ]);
            }

            // Nivel 3: Precio por forma de venta (Delivery más caro)
            if (isset($this->formasVenta['DELIVERY'])) {
                $this->crearPrecio([
                    'articulo_id' => $cocaCola->id,
                    'sucursal_id' => $central->id,
                    'forma_venta_id' => $this->formasVenta['DELIVERY']->id,
                    'canal_venta_id' => null,
                    'precio' => 400,
                    'descripcion' => 'Precio por forma de venta (Delivery)',
                ]);
            }

            // Nivel 4: Precio específico (Delivery + WhatsApp más caro aún)
            if (isset($this->formasVenta['DELIVERY']) && isset($this->canalesVenta['WHATSAPP'])) {
                $this->crearPrecio([
                    'articulo_id' => $cocaCola->id,
                    'sucursal_id' => $central->id,
                    'forma_venta_id' => $this->formasVenta['DELIVERY']->id,
                    'canal_venta_id' => $this->canalesVenta['WHATSAPP']->id,
                    'precio' => 420,
                    'descripcion' => 'Precio específico (Delivery + WhatsApp)',
                ]);
            }

            echo "\n";
        }

        // ============================================================
        // EJEMPLO 2: AGUA MINERAL - Precios diferentes por sucursal
        // ============================================================
        if (isset($this->articulos['BEB002'])) {
            $aguaMineral = $this->articulos['BEB002'];

            echo "   💧 Agua Mineral 500ml - Precios por sucursal:\n";

            // Central: $200
            if (isset($this->sucursales['CENTRAL'])) {
                $this->crearPrecio([
                    'articulo_id' => $aguaMineral->id,
                    'sucursal_id' => $this->sucursales['CENTRAL']->id,
                    'precio' => 200,
                    'descripcion' => 'Central - Precio base',
                ]);
            }

            // Norte: $220
            if (isset($this->sucursales['NORTE'])) {
                $this->crearPrecio([
                    'articulo_id' => $aguaMineral->id,
                    'sucursal_id' => $this->sucursales['NORTE']->id,
                    'precio' => 220,
                    'descripcion' => 'Norte - Precio base',
                ]);
            }

            // Sur: $250
            if (isset($this->sucursales['SUR'])) {
                $this->crearPrecio([
                    'articulo_id' => $aguaMineral->id,
                    'sucursal_id' => $this->sucursales['SUR']->id,
                    'precio' => 250,
                    'descripcion' => 'Sur - Precio base',
                ]);
            }

            echo "\n";
        }

        // ============================================================
        // EJEMPLO 3: PAPAS LAYS - Precio mayorista vs minorista
        // ============================================================
        if (isset($this->articulos['SNK001']) && isset($this->sucursales['CENTRAL'])) {
            $papas = $this->articulos['SNK001'];
            $central = $this->sucursales['CENTRAL'];

            echo "   🥔 Papas Lays 150g - Precios diferenciados:\n";

            // Precio normal
            $this->crearPrecio([
                'articulo_id' => $papas->id,
                'sucursal_id' => $central->id,
                'precio' => 420,
                'descripcion' => 'Precio normal',
            ]);

            // Precio mayorista (más barato)
            if (isset($this->formasVenta['MAYORISTA'])) {
                $this->crearPrecio([
                    'articulo_id' => $papas->id,
                    'sucursal_id' => $central->id,
                    'forma_venta_id' => $this->formasVenta['MAYORISTA']->id,
                    'precio' => 350,
                    'descripcion' => 'Precio mayorista',
                ]);
            }

            // Precio Salón (más caro)
            if (isset($this->formasVenta['LOCAL']) && isset($this->canalesVenta['SALON'])) {
                $this->crearPrecio([
                    'articulo_id' => $papas->id,
                    'sucursal_id' => $central->id,
                    'forma_venta_id' => $this->formasVenta['LOCAL']->id,
                    'canal_venta_id' => $this->canalesVenta['SALON']->id,
                    'precio' => 480,
                    'descripcion' => 'Precio salón (servicio de mesa)',
                ]);
            }

            echo "\n";
        }

        // ============================================================
        // EJEMPLO 4: ARROZ - Precio con vigencia (oferta temporal)
        // ============================================================
        if (isset($this->articulos['ALM001']) && isset($this->sucursales['CENTRAL'])) {
            $arroz = $this->articulos['ALM001'];
            $central = $this->sucursales['CENTRAL'];

            echo "   🍚 Arroz Gallo 1kg - Con vigencias:\n";

            // Precio normal
            $this->crearPrecio([
                'articulo_id' => $arroz->id,
                'sucursal_id' => $central->id,
                'precio' => 680,
                'descripcion' => 'Precio normal',
            ]);

            // Precio en oferta (vigente próximos 7 días)
            $this->crearPrecio([
                'articulo_id' => $arroz->id,
                'sucursal_id' => $central->id,
                'precio' => 550,
                'vigencia_desde' => now(),
                'vigencia_hasta' => now()->addDays(7),
                'descripcion' => 'Precio oferta (7 días)',
            ]);

            echo "\n";
        }

        // ============================================================
        // Crear precios genéricos para el resto de artículos
        // ============================================================
        echo "   📊 Creando precios base para resto de artículos...\n";

        $articulosSinPrecio = Articulo::whereNotIn('codigo', ['BEB001', 'BEB002', 'SNK001', 'ALM001'])
            ->get();

        foreach ($articulosSinPrecio as $articulo) {
            foreach ($this->sucursales as $sucursal) {
                $existing = PrecioBase::where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $sucursal->id)
                    ->whereNull('forma_venta_id')
                    ->whereNull('canal_venta_id')
                    ->exists();

                if (! $existing) {
                    PrecioBase::create([
                        'articulo_id' => $articulo->id,
                        'sucursal_id' => $sucursal->id,
                        'forma_venta_id' => null,
                        'canal_venta_id' => null,
                        'precio' => $articulo->precio_base ?? 100,
                        'activo' => true,
                    ]);
                }
            }
        }

        $total = PrecioBase::count();
        echo "      ✓ Total precios en sistema: {$total}\n";
    }

    private function crearPrecio(array $data): void
    {
        // Verificar si ya existe
        $existing = PrecioBase::where('articulo_id', $data['articulo_id'])
            ->where('sucursal_id', $data['sucursal_id'])
            ->where('forma_venta_id', $data['forma_venta_id'] ?? null)
            ->where('canal_venta_id', $data['canal_venta_id'] ?? null);

        // Si tiene vigencias, agregar a la búsqueda
        if (isset($data['vigencia_desde'])) {
            $existing->where('vigencia_desde', $data['vigencia_desde']);
        }

        if ($existing->exists()) {
            echo "      ⚠️  {$data['descripcion']} ya existe\n";

            return;
        }

        PrecioBase::create([
            'articulo_id' => $data['articulo_id'],
            'sucursal_id' => $data['sucursal_id'],
            'forma_venta_id' => $data['forma_venta_id'] ?? null,
            'canal_venta_id' => $data['canal_venta_id'] ?? null,
            'precio' => $data['precio'],
            'vigencia_desde' => $data['vigencia_desde'] ?? null,
            'vigencia_hasta' => $data['vigencia_hasta'] ?? null,
            'activo' => $data['activo'] ?? true,
        ]);

        echo "      ✓ {$data['descripcion']}: \${$data['precio']}\n";
    }
}
