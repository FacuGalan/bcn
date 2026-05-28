<?php

namespace Tests\Integration\Services;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MercadoPagoCollectorIndex;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 3 — MercadoPagoGateway.
 *
 * Cubre `probarConexion` (camino feliz + errores) y `modosSoportados`.
 * Resto de métodos del contrato son stubs hasta Fase 5.
 *
 * Usa Http::fake() para no pegarle a MP real.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 3 de 10).
 */
class MercadoPagoGatewayTest extends TestCase
{
    use WithSucursal, WithTenant;

    private MercadoPagoGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        if (! IntegracionPago::porCodigo('mercadopago_qr')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        MercadoPagoCollectorIndex::query()->delete();

        $this->gateway = new MercadoPagoGateway;
    }

    protected function tearDown(): void
    {
        MercadoPagoCollectorIndex::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearConfig(array $overrides = []): IntegracionPagoSucursal
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');

        return IntegracionPagoSucursal::create(array_merge([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
            'user_id_externo' => '999888777',
        ], $overrides));
    }

    // ==================== modosSoportados ====================

    public function test_modos_soportados_devuelve_qr_dinamico_y_estatico(): void
    {
        $modos = $this->gateway->modosSoportados();

        $this->assertContains('qr_dinamico', $modos);
        $this->assertContains('qr_estatico', $modos);
        $this->assertCount(2, $modos);
    }

    // ==================== probarConexion - camino feliz ====================

    public function test_probar_conexion_devuelve_info_de_la_cuenta_cuando_id_coincide(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response([
                'id' => 999888777,
                'nickname' => 'TESTUSER123',
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'site_id' => 'MLA',
            ], 200),
        ]);

        $config = $this->crearConfig();

        $info = $this->gateway->probarConexion($config);

        $this->assertSame('999888777', $info['id']);
        $this->assertSame('TESTUSER123', $info['nickname']);
        $this->assertSame('test@example.com', $info['email']);
        $this->assertSame('test', $info['modo']);
    }

    public function test_probar_conexion_sin_user_id_externo_configurado_no_valida_match(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response([
                'id' => 111222333,
                'nickname' => 'ANY',
            ], 200),
        ]);

        $config = $this->crearConfig(['user_id_externo' => null]);

        $info = $this->gateway->probarConexion($config);

        $this->assertSame('111222333', $info['id']);
    }

    public function test_probar_conexion_usa_access_token_segun_modo_activo(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['id' => 999888777], 200),
        ]);

        $config = $this->crearConfig([
            'modo' => 'produccion',
            'access_token_produccion' => 'PROD-TOKEN-XYZ',
            'access_token_test' => 'TEST-TOKEN-ABC',
        ]);

        $this->gateway->probarConexion($config);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer PROD-TOKEN-XYZ'));
    }

    // ==================== probarConexion - errores ====================

    public function test_probar_conexion_sin_access_token_lanza_excepcion(): void
    {
        $config = $this->crearConfig(['access_token_test' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No hay Access Token cargado');

        $this->gateway->probarConexion($config);
    }

    public function test_probar_conexion_con_401_lanza_excepcion_de_token_invalido(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['message' => 'invalid_token'], 401),
        ]);

        $config = $this->crearConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access Token inválido');

        $this->gateway->probarConexion($config);
    }

    public function test_probar_conexion_con_mismatch_de_user_id_lanza_excepcion(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response([
                'id' => 555444333,
                'nickname' => 'OTRACUENTA',
            ], 200),
        ]);

        $config = $this->crearConfig(['user_id_externo' => '999888777']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no coincide/i');

        $this->gateway->probarConexion($config);
    }

    public function test_probar_conexion_con_error_de_red_lanza_excepcion_envuelta(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $config = $this->crearConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo conectar con Mercado Pago');

        $this->gateway->probarConexion($config);
    }

    public function test_probar_conexion_con_500_lanza_excepcion_legible(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['message' => 'internal error'], 500),
        ]);

        $config = $this->crearConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/500/');

        $this->gateway->probarConexion($config);
    }

    // ==================== Stubs Fase 5 ====================

    public function test_iniciar_cobro_lanza_bad_method_call(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->gateway->iniciarCobro(
            $this->crearConfig(),
            new \App\Models\IntegracionPagoTransaccion
        );
    }

    public function test_procesar_webhook_lanza_bad_method_call(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->gateway->procesarWebhook([], []);
    }

    // ==================== Stores ====================

    private function crearSucursalConCoordenadas(): \App\Models\Sucursal
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update([
            'direccion' => 'Av. Corrientes 1234',
            'localidad' => 'CABA',
            'provincia' => 'AR-B', // ISO 3166-2 — al armar payload de MP se traduce a "Buenos Aires"
            'latitud' => -34.6037,
            'longitud' => -58.3816,
        ]);

        return $sucursal->refresh();
    }

    public function test_crear_store_envia_payload_correcto_y_devuelve_id(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response([
                'id' => 7777777,
                'external_id' => 'BCN-1-'.$this->sucursalId,
                'location' => ['latitude' => -34.6037, 'longitude' => -58.3816],
            ], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();

        $resp = $this->gateway->crearStore($config, $sucursal, $this->comercio->id);

        $this->assertSame(7777777, $resp['id']);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/stores')
                && $body['external_id'] === 'BCN-'.$this->comercio->id.'-'.$this->sucursalId
                && $body['location']['latitude'] === -34.6037
                && $body['location']['longitude'] === -58.3816
                && $body['location']['city_name'] === 'CABA'
                && $body['location']['state_name'] === 'Buenos Aires' // 'AR-B' traducido al nombre oficial
                && $body['location']['street_name'] === 'Av. Corrientes'
                && $body['location']['street_number'] === '1234';
        });
    }

    public function test_crear_store_sin_coordenadas_lanza_excepcion(): void
    {
        $config = $this->crearConfig();
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/coordenadas/i');

        $this->gateway->crearStore($config, $sucursal, $this->comercio->id);
    }

    public function test_crear_store_sin_localidad_lanza_excepcion_explicita(): void
    {
        $config = $this->crearConfig();
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update([
            'direccion' => 'Av. Corrientes 1234',
            'latitud' => -34.6,
            'longitud' => -58.4,
            'localidad' => null,
            'provincia' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/localidad/i');

        $this->gateway->crearStore($config, $sucursal->refresh(), $this->comercio->id);
    }

    public function test_payload_store_traduce_codigo_iso_de_provincia_a_nombre_oficial(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response(['id' => 1], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update([
            'direccion' => 'Calle X 100',
            'localidad' => 'Córdoba',
            'provincia' => 'AR-X', // Córdoba
            'latitud' => -31.4,
            'longitud' => -64.2,
        ]);

        $this->gateway->crearStore($config, $sucursal->refresh(), $this->comercio->id);

        Http::assertSent(fn ($req) => $req->data()['location']['state_name'] === 'Córdoba');
    }

    public function test_guard_response_extrae_cause_array_de_mp(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response([
                'message' => 'Validation Error',
                'error' => 'validation_error',
                'cause' => [
                    ['code' => 4001, 'description' => 'city_name no puede ser vacío'],
                    ['code' => 4002, 'description' => 'state_name es requerido'],
                ],
            ], 400),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();

        try {
            $this->gateway->crearStore($config, $sucursal, $this->comercio->id);
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $this->assertStringContainsString('400', $msg);
            $this->assertStringContainsString('city_name no puede ser vacío', $msg);
            $this->assertStringContainsString('state_name es requerido', $msg);
        }
    }

    public function test_actualizar_store_requiere_mp_store_id(): void
    {
        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no tiene un Store creado/i');

        $this->gateway->actualizarStore($config, $sucursal, $this->comercio->id);
    }

    public function test_actualizar_store_no_envia_external_id(): void
    {
        // MP rechaza el external_id en el PUT por colisión consigo mismo
        // ("already assigned to this user"). El update debe omitirlo.
        Http::fake([
            'api.mercadopago.com/users/*/stores/*' => Http::response(['id' => 7777777], 200),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();

        $this->gateway->actualizarStore($config, $sucursal, $this->comercio->id);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/stores/7777777')
                && ! array_key_exists('external_id', $request->data())
                && $request->data()['location']['city_name'] === 'CABA';
        });
    }

    public function test_actualizar_pos_no_envia_external_id(): void
    {
        Http::fake([
            'api.mercadopago.com/pos/*' => Http::response(['id' => 999111], 200),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();
        $caja = $this->crearCaja();
        $caja->update(['mp_pos_id' => '999111', 'mp_pos_external_id' => 'BCN1POS1']);

        $this->gateway->actualizarPos($config, $caja->refresh(), $sucursal, null, $this->comercio->id);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/pos/999111')
                && ! array_key_exists('external_id', $request->data())
                && $request->data()['store_id'] === 7777777;
        });
    }

    public function test_eliminar_store_404_se_considera_ok(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores/*' => Http::response(['message' => 'not found'], 404),
        ]);

        $config = $this->crearConfig();
        $this->assertTrue($this->gateway->eliminarStore($config, '9999'));
    }

    // ==================== POS ====================

    private function crearCaja(): \App\Models\Caja
    {
        return \App\Models\Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja 1',
            'codigo' => 'C1',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
        ]);
    }

    private function sucursalSincronizada(): \App\Models\Sucursal
    {
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update([
            'mp_store_id' => '7777777',
            'mp_store_external_id' => 'BCN-'.$this->comercio->id.'-'.$this->sucursalId,
        ]);

        return $sucursal->refresh();
    }

    public function test_crear_pos_devuelve_qr_urls(): void
    {
        Http::fake([
            'api.mercadopago.com/pos' => Http::response([
                'id' => 999111,
                'qr' => [
                    'image' => 'https://mp.com/qr/999111/abc.png',
                    'template_document' => 'https://mp.com/qr/999111/abc.pdf',
                ],
                'external_id' => 'BCN1POS1',
            ], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();
        $caja = $this->crearCaja();

        $resp = $this->gateway->crearPos($config, $caja, $sucursal, null, $this->comercio->id);

        $this->assertSame(999111, $resp['id']);
        $this->assertStringContainsString('.png', $resp['qr']['image']);
    }

    public function test_crear_pos_sin_store_sincronizado_lanza_excepcion(): void
    {
        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas(); // sin mp_store_id
        $caja = $this->crearCaja();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sincronizarse primero/i');

        $this->gateway->crearPos($config, $caja, $sucursal, null, $this->comercio->id);
    }

    public function test_crear_pos_con_rubro_gastronomia_incluye_category(): void
    {
        Http::fake([
            'api.mercadopago.com/pos' => Http::response(['id' => 1, 'qr' => ['image' => 'x', 'template_document' => 'y']], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();
        $caja = $this->crearCaja();

        $this->gateway->crearPos($config, $caja, $sucursal, \App\Models\Comercio::RUBRO_GASTRONOMIA, $this->comercio->id);

        Http::assertSent(fn ($req) => ($req->data()['category'] ?? null) === 621102);
    }

    public function test_crear_pos_con_rubro_otro_no_incluye_category(): void
    {
        Http::fake([
            'api.mercadopago.com/pos' => Http::response(['id' => 1, 'qr' => ['image' => 'x', 'template_document' => 'y']], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();
        $caja = $this->crearCaja();

        $this->gateway->crearPos($config, $caja, $sucursal, \App\Models\Comercio::RUBRO_OTRO, $this->comercio->id);

        Http::assertSent(fn ($req) => ! array_key_exists('category', $req->data()));
    }

    public function test_external_id_helpers_respetan_limites_de_mp(): void
    {
        $storeExt = \App\Services\IntegracionesPago\MercadoPagoGateway::externalIdStore(1, 999);
        $posExt = \App\Services\IntegracionesPago\MercadoPagoGateway::externalIdPos(1, 999);

        $this->assertSame('BCN-1-999', $storeExt);
        $this->assertSame('BCN1POS999', $posExt);
        $this->assertLessThanOrEqual(60, strlen($storeExt));
        $this->assertLessThanOrEqual(40, strlen($posExt));
        // El POS exige external_id estrictamente alfanumérico (sin guiones).
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $posExt);
    }
}
