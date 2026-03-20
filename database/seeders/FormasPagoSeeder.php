<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: Formas de Pago
 *
 * Crea las formas de pago disponibles con sus configuraciones de cuotas.
 * Incluye: efectivo, tarjetas, transferencias, wallets, etc.
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class FormasPagoSeeder extends Seeder
{
    private $comercioId = 1;

    private $formasPago = [];

    private $conceptos = [];

    public function run(): void
    {
        echo "💳 Iniciando seeder de Formas de Pago...\n\n";

        $this->configurarTenant();

        // Primero crear los conceptos de pago
        $this->call(ConceptosPagoSeeder::class);
        $this->cargarConceptos();

        $this->crearFormasPago();
        $this->crearCuotas();
        $this->crearFormaPagoMixta();

        echo "\n✅ Seeder completado exitosamente!\n";
        echo "📊 Resumen:\n";
        echo '   - Formas de pago creadas: '.count($this->formasPago)."\n\n";
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

    private function cargarConceptos(): void
    {
        echo "\n📦 Cargando conceptos de pago...\n";

        $this->conceptos = ConceptoPago::all()->keyBy('codigo');

        foreach ($this->conceptos as $codigo => $concepto) {
            echo "   ✓ {$concepto->nombre} ({$codigo})\n";
        }
        echo "\n";
    }

    private function crearFormasPago(): void
    {
        echo "📋 Creando formas de pago...\n";

        $formasPagoData = [
            // Efectivo
            [
                'nombre' => 'Efectivo',
                'codigo' => 'EFECTIVO',
                'concepto' => 'efectivo',
                'concepto_codigo' => ConceptoPago::EFECTIVO,
                'descripcion' => 'Pago en efectivo',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => -5, // 5% descuento en efectivo
                'activo' => true,
            ],

            // Tarjeta de Débito
            [
                'nombre' => 'Tarjeta de Débito',
                'codigo' => 'DEBITO',
                'concepto' => 'tarjeta_debito',
                'concepto_codigo' => ConceptoPago::TARJETA_DEBITO,
                'descripcion' => 'Pago con tarjeta de débito',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 0,
                'activo' => true,
            ],

            // Tarjeta de Crédito - permite cuotas
            [
                'nombre' => 'Tarjeta de Crédito',
                'codigo' => 'CREDITO',
                'concepto' => 'tarjeta_credito',
                'concepto_codigo' => ConceptoPago::TARJETA_CREDITO,
                'descripcion' => 'Pago con tarjeta de crédito - hasta 12 cuotas',
                'permite_cuotas' => true,
                'es_mixta' => false,
                'ajuste_porcentaje' => 3, // 3% recargo en tarjeta crédito
                'activo' => true,
            ],

            // Transferencia
            [
                'nombre' => 'Transferencia Bancaria',
                'codigo' => 'TRANSFERENCIA',
                'concepto' => 'transferencia',
                'concepto_codigo' => ConceptoPago::TRANSFERENCIA,
                'descripcion' => 'Transferencia o depósito bancario',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 0,
                'activo' => true,
            ],

            // MercadoPago
            [
                'nombre' => 'MercadoPago',
                'codigo' => 'MERCADOPAGO',
                'concepto' => 'wallet',
                'concepto_codigo' => ConceptoPago::WALLET,
                'descripcion' => 'Pago con MercadoPago',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 5, // 5% recargo MercadoPago
                'activo' => true,
            ],

            // PayPal
            [
                'nombre' => 'PayPal',
                'codigo' => 'PAYPAL',
                'concepto' => 'wallet',
                'concepto_codigo' => ConceptoPago::WALLET,
                'descripcion' => 'Pago con PayPal',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 7, // 7% recargo PayPal
                'activo' => true,
            ],

            // Cheque
            [
                'nombre' => 'Cheque 30 días',
                'codigo' => 'CHEQUE',
                'concepto' => 'cheque',
                'concepto_codigo' => ConceptoPago::CHEQUE,
                'descripcion' => 'Pago con cheque a 30 días',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 0,
                'activo' => true,
            ],

            // Cuenta Corriente (crédito al cliente)
            [
                'nombre' => 'Cuenta Corriente',
                'codigo' => 'CTA_CTE',
                'concepto' => 'credito_cliente',
                'concepto_codigo' => ConceptoPago::CREDITO_CLIENTE,
                'descripcion' => 'Venta a crédito en cuenta corriente (clientes autorizados)',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'ajuste_porcentaje' => 0,
                'activo' => true,
            ],
        ];

        $contador = 0;
        foreach ($formasPagoData as $data) {
            // Obtener el concepto_pago_id
            $conceptoCodigo = $data['concepto_codigo'];
            unset($data['concepto_codigo']);

            $concepto = $this->conceptos[$conceptoCodigo] ?? null;
            $data['concepto_pago_id'] = $concepto?->id;

            // Verificar si ya existe
            $existing = FormaPago::where('codigo', $data['codigo'])->first();
            if ($existing) {
                // Actualizar con el concepto_pago_id si no lo tiene
                if (! $existing->concepto_pago_id && $concepto) {
                    $existing->update([
                        'concepto_pago_id' => $concepto->id,
                        'es_mixta' => false,
                    ]);
                    echo "   🔄 {$data['nombre']} actualizado con concepto\n";
                } else {
                    echo "   ⚠️  {$data['nombre']} ya existe\n";
                }
                $this->formasPago[$data['nombre']] = $existing;

                continue;
            }

            $formaPago = FormaPago::create($data);
            $this->formasPago[$data['nombre']] = $formaPago;
            $contador++;
            echo "   ✓ {$data['nombre']}";

            if ($data['permite_cuotas']) {
                echo ' [Permite Cuotas]';
            }

            if ($data['ajuste_porcentaje'] != 0) {
                $tipo = $data['ajuste_porcentaje'] > 0 ? 'recargo' : 'descuento';
                echo " [{$tipo}: ".abs($data['ajuste_porcentaje']).'%]';
            }

            echo "\n";
        }

        echo "\n📊 Total creado: {$contador} formas de pago\n\n";
    }

    private function crearCuotas(): void
    {
        echo "🔢 Creando planes de cuotas para Tarjeta de Crédito...\n";

        if (! isset($this->formasPago['Tarjeta de Crédito'])) {
            echo "   ⚠️  Tarjeta de Crédito no encontrada, saltando cuotas\n";

            return;
        }

        $tarjetaCredito = $this->formasPago['Tarjeta de Crédito'];

        $cuotasData = [
            // 1 pago - sin recargo
            ['cantidad_cuotas' => 1, 'recargo_porcentaje' => 0, 'activo' => true],

            // 3 cuotas sin interés
            ['cantidad_cuotas' => 3, 'recargo_porcentaje' => 0, 'activo' => true],

            // 6 cuotas - 10% recargo
            ['cantidad_cuotas' => 6, 'recargo_porcentaje' => 10, 'activo' => true],

            // 9 cuotas - 15% recargo
            ['cantidad_cuotas' => 9, 'recargo_porcentaje' => 15, 'activo' => true],

            // 12 cuotas - 20% recargo
            ['cantidad_cuotas' => 12, 'recargo_porcentaje' => 20, 'activo' => true],

            // 18 cuotas - 30% recargo (opcional, desactivado por defecto)
            ['cantidad_cuotas' => 18, 'recargo_porcentaje' => 30, 'activo' => false],
        ];

        $contador = 0;
        foreach ($cuotasData as $data) {
            // Verificar si ya existe
            $existing = FormaPagoCuota::where('forma_pago_id', $tarjetaCredito->id)
                ->where('cantidad_cuotas', $data['cantidad_cuotas'])
                ->first();
            if ($existing) {
                echo "   ⚠️  Plan {$data['cantidad_cuotas']} cuotas ya existe\n";

                continue;
            }

            $data['forma_pago_id'] = $tarjetaCredito->id;
            FormaPagoCuota::create($data);
            $contador++;

            $estado = $data['activo'] ? '✓' : '✗';
            echo "   {$estado} {$data['cantidad_cuotas']} cuota(s)";

            if ($data['recargo_porcentaje'] > 0) {
                echo " - {$data['recargo_porcentaje']}% recargo";
            } else {
                echo ' - sin interés';
            }

            if (! $data['activo']) {
                echo ' [INACTIVO]';
            }

            echo "\n";
        }

        echo "\n📊 Total creado: {$contador} planes de cuotas\n";
    }

    private function crearFormaPagoMixta(): void
    {
        echo "\n🔀 Creando forma de pago mixta...\n";

        // Verificar si ya existe
        $existing = FormaPago::where('codigo', 'MIXTA')->first();
        if ($existing) {
            echo "   ⚠️  Forma de pago Mixta ya existe\n";

            return;
        }

        // Crear la forma de pago mixta
        $mixta = FormaPago::create([
            'nombre' => 'Pago Mixto',
            'codigo' => 'MIXTA',
            'concepto' => 'otro', // Campo legacy
            'concepto_pago_id' => null, // NULL para mixtas
            'descripcion' => 'Permite combinar múltiples formas de pago (efectivo, tarjeta, transferencia, etc.)',
            'permite_cuotas' => false, // Las cuotas se manejan por cada forma de pago en el desglose
            'es_mixta' => true,
            'ajuste_porcentaje' => 0, // Sin ajuste propio, usa los de las formas de pago del desglose
            'activo' => true,
        ]);

        // Asignar los conceptos permitidos para la forma mixta
        $conceptosPermitidos = [
            ConceptoPago::EFECTIVO,
            ConceptoPago::TARJETA_DEBITO,
            ConceptoPago::TARJETA_CREDITO,
            ConceptoPago::TRANSFERENCIA,
            ConceptoPago::WALLET,
        ];

        $conceptosIds = [];
        foreach ($conceptosPermitidos as $codigo) {
            if (isset($this->conceptos[$codigo])) {
                $conceptosIds[] = $this->conceptos[$codigo]->id;
            }
        }

        $mixta->conceptosPermitidos()->attach($conceptosIds);

        $this->formasPago['Pago Mixto'] = $mixta;

        echo "   ✓ Pago Mixto [MIXTA]\n";
        echo '     Conceptos permitidos: '.implode(', ', $conceptosPermitidos)."\n";
    }
}
