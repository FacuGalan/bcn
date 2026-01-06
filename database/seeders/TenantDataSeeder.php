<?php

namespace Database\Seeders;

use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para datos iniciales de un comercio (tenant)
 *
 * Este seeder crea los datos básicos necesarios para que un comercio
 * pueda operar: tipos de IVA, categorías, formas de pago, etc.
 *
 * Uso: php artisan tenant:migrate {comercio_id} --seed --seeder=TenantDataSeeder
 */
class TenantDataSeeder extends Seeder
{
    public function run(): void
    {
        $prefix = TenantService::getTablePrefix();
        if (empty($prefix)) {
            $this->command->error('No hay comercio activo. Use tenant:migrate {comercio_id}');
            return;
        }

        $this->command->info("Inicializando datos para comercio con prefijo: {$prefix}");

        // Ejecutar seeders en orden
        $this->call([
            TiposIvaSeeder::class,
            CategoriasSeeder::class,
            FormasVentaSeeder::class,
            CanalesVentaSeeder::class,
            FormasPagoSeeder::class,
            ConceptosPagoSeeder::class,
            CondicionesIvaSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->command->info('Datos iniciales del comercio creados exitosamente.');
    }
}
