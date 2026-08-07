<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): siembra la integración de
 * catálogo `mercadopago_checkout` en cada comercio.
 *
 * Checkout Pro es un PRODUCTO MP separado del QR y de Point (cada producto MP
 * es una aplicación con su propio access_token). Por eso es una fila de
 * catálogo aparte, con sus propias credenciales por sucursal. Reusa la MISMA
 * clase Gateway (MercadoPagoGateway, rama por modo) pero otra API:
 * POST /checkout/preferences → init_point (link de pago).
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 1, RF-T75).
 */
return new class extends Migration
{
    private string $codigo = 'mercadopago_checkout';

    private string $nombre = 'Mercado Pago - Checkout Online';

    private string $descripcion = 'Pago online en la tienda con Mercado Pago (Checkout Pro): el consumidor paga en la página segura de Mercado Pago y el pedido entra al circuito ya pagado.';

    private string $modosDisponibles = '["checkout_pro"]';

    private string $gatewayClass = 'App\\Services\\IntegracionesPago\\MercadoPagoGateway';

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        $now = now();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $existe = DB::connection('pymes')
                    ->table($prefix.'integraciones_pago')
                    ->where('codigo', $this->codigo)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::connection('pymes')->table($prefix.'integraciones_pago')->insert([
                    'codigo' => $this->codigo,
                    'nombre' => $this->nombre,
                    'descripcion' => $this->descripcion,
                    'modos_disponibles' => $this->modosDisponibles,
                    'gateway_class' => $this->gatewayClass,
                    'activo' => 1,
                    'orden' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')
                    ->table($prefix.'integraciones_pago')
                    ->where('codigo', $this->codigo)
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
