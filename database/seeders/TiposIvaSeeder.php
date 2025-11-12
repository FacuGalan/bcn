<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: Tipos de IVA
 *
 * Inserta los tipos de IVA según códigos de AFIP.
 * Este seeder debe ejecutarse para cada comercio usando la conexión pymes_tenant.
 *
 * FASE 1 - Sistema Multi-Sucursal (Extensión IVA)
 */
class TiposIvaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposIva = [
            [
                'codigo' => 3,
                'nombre' => 'Exento',
                'porcentaje' => 0.00,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 4,
                'nombre' => 'IVA 10.5%',
                'porcentaje' => 10.50,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 5,
                'nombre' => 'IVA 21%',
                'porcentaje' => 21.00,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::connection('pymes_tenant')->table('tipos_iva')->insert($tiposIva);

        $this->command->info('✓ Tipos de IVA insertados correctamente');
    }
}
