<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — Modo Point (Fase 1): siembra la integración de
 * catálogo `mercadopago_point` en cada comercio.
 *
 * Point es un PRODUCTO MP separado del QR (cada producto MP es una aplicación
 * con su propio access_token; doc oficial mp-point/create-application). Por eso
 * es una fila de catálogo aparte, con sus propias credenciales por sucursal.
 * Reusa la MISMA clase Gateway (MercadoPagoGateway, rama por modo) y la MISMA
 * Orders API (POST /v1/orders con type:"point") que el QR dinámico.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago-point.md (Fase 1, RF-01).
 */
return new class extends Migration
{
    private string $codigo = 'mercadopago_point';

    private string $nombre = 'Mercado Pago - Point';

    private string $descripcion = 'Cobros con Mercado Pago Point: el monto se envía a la terminal física desde el sistema y el cliente paga con tarjeta o QR en el propio aparato.';

    private string $modosDisponibles = '["point"]';

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
                    'orden' => 2,
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
