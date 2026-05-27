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

        if (! IntegracionPago::porCodigo('mercadopago')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago',
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
        $mpId = IntegracionPago::porCodigo('mercadopago')->value('id');

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
}
