<?php

namespace Database\Seeders;

use App\Models\ConceptoPago;
use Illuminate\Database\Seeder;

/**
 * Seeder para conceptos de pago
 *
 * Crea los conceptos de pago base del sistema.
 * Esta tabla es fija y no editable por el usuario.
 */
class ConceptosPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conceptos = [
            [
                'codigo' => ConceptoPago::EFECTIVO,
                'nombre' => 'Efectivo',
                'descripcion' => 'Pago en efectivo (billetes y monedas)',
                'permite_cuotas' => false,
                'permite_vuelto' => true,
                'orden' => 1,
            ],
            [
                'codigo' => ConceptoPago::TARJETA_DEBITO,
                'nombre' => 'Tarjeta de Débito',
                'descripcion' => 'Pago con tarjeta de débito bancaria',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'orden' => 2,
            ],
            [
                'codigo' => ConceptoPago::TARJETA_CREDITO,
                'nombre' => 'Tarjeta de Crédito',
                'descripcion' => 'Pago con tarjeta de crédito (permite cuotas)',
                'permite_cuotas' => true,
                'permite_vuelto' => false,
                'orden' => 3,
            ],
            [
                'codigo' => ConceptoPago::TRANSFERENCIA,
                'nombre' => 'Transferencia',
                'descripcion' => 'Transferencia bancaria o CBU/CVU',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'orden' => 4,
            ],
            [
                'codigo' => ConceptoPago::WALLET,
                'nombre' => 'Billetera Digital',
                'descripcion' => 'Billeteras digitales (MercadoPago, PayPal, etc.)',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'orden' => 5,
            ],
            [
                'codigo' => ConceptoPago::CHEQUE,
                'nombre' => 'Cheque',
                'descripcion' => 'Pago con cheque bancario',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'orden' => 6,
            ],
            [
                'codigo' => ConceptoPago::CREDITO_CLIENTE,
                'nombre' => 'Crédito Cliente',
                'descripcion' => 'Venta a crédito en cuenta corriente del cliente',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'orden' => 7,
            ],
        ];

        foreach ($conceptos as $concepto) {
            ConceptoPago::updateOrCreate(
                ['codigo' => $concepto['codigo']],
                $concepto
            );
        }

        $this->command->info('Conceptos de pago creados/actualizados: ' . count($conceptos));
    }
}
