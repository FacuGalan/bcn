<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para las condiciones de IVA de AFIP
 *
 * Crea las 14 condiciones de IVA definidas por AFIP para Argentina.
 * Estos datos son compartidos por todos los comercios.
 */
class CondicionesIvaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $condiciones = [
            ['codigo' => 1, 'nombre' => 'IVA Responsable Inscripto', 'descripcion' => 'Responsable inscripto en el impuesto al valor agregado'],
            ['codigo' => 2, 'nombre' => 'IVA Responsable no Inscripto', 'descripcion' => 'Responsable no inscripto en el IVA (categoría discontinuada)'],
            ['codigo' => 3, 'nombre' => 'IVA no Responsable', 'descripcion' => 'No responsable frente al IVA'],
            ['codigo' => 4, 'nombre' => 'IVA Sujeto Exento', 'descripcion' => 'Sujeto exento del impuesto al valor agregado'],
            ['codigo' => 5, 'nombre' => 'Consumidor Final', 'descripcion' => 'Consumidor final'],
            ['codigo' => 6, 'nombre' => 'Responsable Monotributo', 'descripcion' => 'Responsable monotributo'],
            ['codigo' => 7, 'nombre' => 'Sujeto no Categorizado', 'descripcion' => 'Sujeto no categorizado'],
            ['codigo' => 8, 'nombre' => 'Proveedor del Exterior', 'descripcion' => 'Proveedor del exterior'],
            ['codigo' => 9, 'nombre' => 'Cliente del Exterior', 'descripcion' => 'Cliente del exterior'],
            ['codigo' => 10, 'nombre' => 'IVA Liberado – Ley Nº 19.640', 'descripcion' => 'IVA liberado según Ley 19.640 (Tierra del Fuego)'],
            ['codigo' => 11, 'nombre' => 'IVA Responsable Inscripto – Agente de Percepción', 'descripcion' => 'Responsable inscripto que actúa como agente de percepción'],
            ['codigo' => 12, 'nombre' => 'Pequeño Contribuyente Eventual', 'descripcion' => 'Pequeño contribuyente eventual'],
            ['codigo' => 13, 'nombre' => 'Monotributista Social', 'descripcion' => 'Monotributista social'],
            ['codigo' => 14, 'nombre' => 'Pequeño Contribuyente Eventual Social', 'descripcion' => 'Pequeño contribuyente eventual social'],
        ];

        $now = now();

        foreach ($condiciones as &$condicion) {
            $condicion['created_at'] = $now;
            $condicion['updated_at'] = $now;
        }

        DB::connection('config')->table('condiciones_iva')->insert($condiciones);

        $this->command->info('Condiciones de IVA creadas: ' . count($condiciones));
    }
}
