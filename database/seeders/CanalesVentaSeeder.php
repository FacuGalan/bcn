<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\CanalVenta;
use App\Models\Comercio;

/**
 * Seeder: Canales de Venta
 *
 * Crea los canales de venta disponibles en el sistema.
 * Ejemplos: POS, Salón, Mostrador, Web, Telefónico
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class CanalesVentaSeeder extends Seeder
{
    private $comercioId = 1;

    public function run(): void
    {
        echo "📺 Iniciando seeder de Canales de Venta...\n\n";

        $this->configurarTenant();
        $this->crearCanalesVenta();

        echo "\n✅ Seeder completado exitosamente!\n\n";
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

    private function crearCanalesVenta(): void
    {
        echo "📋 Creando canales de venta...\n";

        $canalesVentaData = [
            [
                'nombre' => 'POS',
                'codigo' => 'POS',
                'descripcion' => 'Punto de venta físico (mostrador principal)',
                'activo' => true,
            ],
            [
                'nombre' => 'Salón',
                'codigo' => 'SALON',
                'descripcion' => 'Mesas del salón (restaurante/bar)',
                'activo' => true,
            ],
            [
                'nombre' => 'Mostrador',
                'codigo' => 'MOSTRADOR',
                'descripcion' => 'Ventas directas en mostrador secundario',
                'activo' => true,
            ],
            [
                'nombre' => 'Web',
                'codigo' => 'WEB',
                'descripcion' => 'E-commerce / Tienda online',
                'activo' => true,
            ],
            [
                'nombre' => 'Telefónico',
                'codigo' => 'TELEFONO',
                'descripcion' => 'Pedidos por teléfono',
                'activo' => true,
            ],
            [
                'nombre' => 'WhatsApp',
                'codigo' => 'WHATSAPP',
                'descripcion' => 'Pedidos por WhatsApp',
                'activo' => true,
            ],
            [
                'nombre' => 'App Móvil',
                'codigo' => 'APP',
                'descripcion' => 'Aplicación móvil',
                'activo' => true,
            ],
            [
                'nombre' => 'Marketplace',
                'codigo' => 'MARKETPLACE',
                'descripcion' => 'Pedidos Ya, Rappi, etc.',
                'activo' => true,
            ],
        ];

        $contador = 0;
        foreach ($canalesVentaData as $data) {
            // Verificar si ya existe
            $existing = CanalVenta::where('codigo', $data['codigo'])->first();
            if ($existing) {
                echo "   ⚠️  {$data['nombre']} ya existe\n";
                continue;
            }

            CanalVenta::create($data);
            $contador++;
            echo "   ✓ {$data['nombre']} ({$data['codigo']})\n";
        }

        echo "\n📊 Total creado: {$contador} canales de venta\n";
    }
}
