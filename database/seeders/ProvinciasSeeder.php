<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para las provincias de Argentina
 *
 * Crea las 24 provincias argentinas con códigos ISO 3166-2:AR.
 * Estos datos son compartidos por todos los comercios.
 */
class ProvinciasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provincias = [
            ['codigo' => 'AR-C', 'nombre' => 'Ciudad Autónoma de Buenos Aires'],
            ['codigo' => 'AR-B', 'nombre' => 'Buenos Aires'],
            ['codigo' => 'AR-K', 'nombre' => 'Catamarca'],
            ['codigo' => 'AR-H', 'nombre' => 'Chaco'],
            ['codigo' => 'AR-U', 'nombre' => 'Chubut'],
            ['codigo' => 'AR-X', 'nombre' => 'Córdoba'],
            ['codigo' => 'AR-W', 'nombre' => 'Corrientes'],
            ['codigo' => 'AR-E', 'nombre' => 'Entre Ríos'],
            ['codigo' => 'AR-P', 'nombre' => 'Formosa'],
            ['codigo' => 'AR-Y', 'nombre' => 'Jujuy'],
            ['codigo' => 'AR-L', 'nombre' => 'La Pampa'],
            ['codigo' => 'AR-F', 'nombre' => 'La Rioja'],
            ['codigo' => 'AR-M', 'nombre' => 'Mendoza'],
            ['codigo' => 'AR-N', 'nombre' => 'Misiones'],
            ['codigo' => 'AR-Q', 'nombre' => 'Neuquén'],
            ['codigo' => 'AR-R', 'nombre' => 'Río Negro'],
            ['codigo' => 'AR-A', 'nombre' => 'Salta'],
            ['codigo' => 'AR-J', 'nombre' => 'San Juan'],
            ['codigo' => 'AR-D', 'nombre' => 'San Luis'],
            ['codigo' => 'AR-Z', 'nombre' => 'Santa Cruz'],
            ['codigo' => 'AR-S', 'nombre' => 'Santa Fe'],
            ['codigo' => 'AR-G', 'nombre' => 'Santiago del Estero'],
            ['codigo' => 'AR-V', 'nombre' => 'Tierra del Fuego'],
            ['codigo' => 'AR-T', 'nombre' => 'Tucumán'],
        ];

        $now = now();

        foreach ($provincias as &$provincia) {
            $provincia['created_at'] = $now;
            $provincia['updated_at'] = $now;
        }

        DB::connection('config')->table('provincias')->insert($provincias);

        $this->command->info('Provincias creadas: ' . count($provincias));
    }
}
