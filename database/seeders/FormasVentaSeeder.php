<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\FormaVenta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: Formas de Venta
 *
 * Crea las formas de venta disponibles en el sistema.
 * Ejemplos: Local, Delivery, Take Away
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class FormasVentaSeeder extends Seeder
{
    private $comercioId = 1;

    public function run(): void
    {
        echo "🛍️  Iniciando seeder de Formas de Venta...\n\n";

        $this->configurarTenant();
        $this->crearFormasVenta();

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

    private function crearFormasVenta(): void
    {
        echo "📋 Creando formas de venta...\n";

        $formasVentaData = [
            [
                'nombre' => 'Local',
                'codigo' => 'LOCAL',
                'descripcion' => 'Consumo en el local (mesas, salón)',
                'activo' => true,
            ],
            [
                'nombre' => 'Delivery',
                'codigo' => 'DELIVERY',
                'descripcion' => 'Entrega a domicilio',
                'activo' => true,
            ],
            [
                'nombre' => 'Take Away',
                'codigo' => 'TAKEAWAY',
                'descripcion' => 'Para llevar (retiro en el local)',
                'activo' => true,
            ],
            [
                'nombre' => 'Mayorista',
                'codigo' => 'MAYORISTA',
                'descripcion' => 'Venta mayorista (grandes volúmenes)',
                'activo' => true,
            ],
            [
                'nombre' => 'Online',
                'codigo' => 'ONLINE',
                'descripcion' => 'Pedido online con envío',
                'activo' => true,
            ],
        ];

        $contador = 0;
        foreach ($formasVentaData as $data) {
            // Verificar si ya existe
            $existing = FormaVenta::where('codigo', $data['codigo'])->first();
            if ($existing) {
                echo "   ⚠️  {$data['nombre']} ya existe\n";

                continue;
            }

            FormaVenta::create($data);
            $contador++;
            echo "   ✓ {$data['nombre']} ({$data['codigo']})\n";
        }

        echo "\n📊 Total creado: {$contador} formas de venta\n";
    }
}
