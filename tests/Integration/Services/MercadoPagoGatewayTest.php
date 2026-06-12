<?php

namespace Tests\Integration\Services;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
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
 * Cubre `probarConexion` (camino feliz + errores), `modosSoportados`,
 * Stores/POS y el parseo/firma del webhook (`procesarWebhook`/`verificarFirma`).
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

    public function test_modos_soportados_devuelve_qr_dinamico_estatico_libre_y_point(): void
    {
        $modos = $this->gateway->modosSoportados();

        $this->assertContains('qr_dinamico', $modos);
        $this->assertContains('qr_estatico', $modos);
        $this->assertContains('point', $modos);
        $this->assertContains('qr_libre', $modos);
        $this->assertCount(4, $modos);
    }

    // ==================== identidadCuentaEmpresa ====================

    public function test_identidad_cuenta_empresa_devuelve_subtipo_e_identificador(): void
    {
        $config = $this->crearConfig(); // user_id_externo = 999888777

        $identidad = $this->gateway->identidadCuentaEmpresa($config);

        $this->assertSame('mercadopago', $identidad['subtipo']);
        $this->assertSame('999888777', $identidad['identificador_externo']);
        $this->assertSame('Mercado Pago 999888777', $identidad['nombre_sugerido']);
    }

    public function test_identidad_cuenta_empresa_es_null_sin_user_id_externo(): void
    {
        $config = $this->crearConfig(['user_id_externo' => null]);

        $this->assertNull($this->gateway->identidadCuentaEmpresa($config));
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

    // ==================== Webhook (Fase 6) ====================

    public function test_procesar_webhook_parsea_order_y_collector(): void
    {
        $parsed = $this->gateway->procesarWebhook([
            'type' => 'order',
            'user_id' => '555444333',
            'data' => ['id' => 'ORD-123'],
        ], []);

        $this->assertSame('order', $parsed['tipo']);
        $this->assertSame('ORD-123', $parsed['order_id']);
        $this->assertSame('555444333', $parsed['user_id_externo']);
    }

    public function test_procesar_webhook_toma_order_id_del_query_con_punto_convertido(): void
    {
        // PHP convierte `data.id` del query string en `data_id`.
        $parsed = $this->gateway->procesarWebhook([
            'topic' => 'order',
            'user_id' => '1',
            'data_id' => 'ORD-FROM-QUERY',
        ], []);

        $this->assertSame('ORD-FROM-QUERY', $parsed['order_id']);
    }

    public function test_verificar_firma_valida_el_hmac(): void
    {
        $secret = 'mi-secreto';
        $dataId = 'ORD-XYZ';
        $ts = '1717000000';
        $requestId = 'req-1';
        $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$ts.';';
        $v1 = hash_hmac('sha256', $manifest, $secret);

        $headers = ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => $requestId];

        $this->assertTrue($this->gateway->verificarFirma($secret, $headers, $dataId));
        $this->assertFalse($this->gateway->verificarFirma('otro-secreto', $headers, $dataId));
        $this->assertFalse($this->gateway->verificarFirma($secret, ['x-signature' => ''], $dataId));
    }

    // ==================== Cobro QR — iniciarCobro (Fase 5 + 7) ====================

    /**
     * Transacción en memoria (sin tocar DB ni FKs): el gateway solo usa
     * id, monto, modo_usado y la caja relacionada.
     */
    private function transaccionFake(\App\Models\Caja $caja, string $modo): IntegracionPagoTransaccion
    {
        $tx = new IntegracionPagoTransaccion([
            'monto' => 1500.50,
            'modo_usado' => $modo,
            'caja_id' => $caja->id,
        ]);
        $tx->id = 4242;
        $tx->setRelation('caja', $caja);

        return $tx;
    }

    private function cajaSincronizadaConPos(array $overrides = []): \App\Models\Caja
    {
        $caja = $this->crearCaja();
        $caja->update(array_merge([
            'mp_pos_id' => '999111',
            'mp_pos_external_id' => 'BCN'.$this->comercio->id.'POS'.$caja->id,
            'mp_pos_qr_url' => 'https://mp.com/qr/999111/static.png',
        ], $overrides));

        return $caja->refresh();
    }

    public function test_iniciar_cobro_dinamico_envia_mode_dynamic_y_devuelve_qr_data(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD-DIN-1',
                'type_response' => ['qr_data' => '000201EMVCO-DINAMICO'],
            ], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaSincronizadaConPos();
        $tx = $this->transaccionFake($caja, 'qr_dinamico');

        $res = $this->gateway->iniciarCobro($config, $tx);

        $this->assertSame('000201EMVCO-DINAMICO', $res['qr_data']);
        $this->assertNull($res['qr_image_url']);
        $this->assertSame('ORD-DIN-1', $res['external_id']);
        $this->assertSame('BCN-TX-4242', $res['external_reference']);

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/v1/orders')
            && $r->data()['config']['qr']['mode'] === 'dynamic'
            && $r->data()['config']['qr']['external_pos_id'] === $caja->mp_pos_external_id
            && $r->hasHeader('X-Idempotency-Key', 'order-BCN-TX-4242'));
    }

    public function test_iniciar_cobro_estatico_envia_mode_static_y_usa_qr_impreso_del_pos(): void
    {
        // En estático MP NO devuelve qr_data: la orden con monto se encola en el
        // POS y el cliente escanea el QR impreso. No debe fallar por falta de qr_data.
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD-EST-1',
            ], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaSincronizadaConPos();
        $tx = $this->transaccionFake($caja, 'qr_estatico');

        $res = $this->gateway->iniciarCobro($config, $tx);

        $this->assertNull($res['qr_data']);
        $this->assertSame('https://mp.com/qr/999111/static.png', $res['qr_image_url']);
        $this->assertSame('ORD-EST-1', $res['external_id']);

        Http::assertSent(fn ($r) => $r->data()['config']['qr']['mode'] === 'static');
    }

    public function test_iniciar_cobro_dinamico_sin_qr_data_lanza_excepcion(): void
    {
        // En dinámico SÍ es obligatorio el qr_data (la app lo renderiza).
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD-X'], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaSincronizadaConPos();
        $tx = $this->transaccionFake($caja, 'qr_dinamico');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/código QR/i');

        $this->gateway->iniciarCobro($config, $tx);
    }

    public function test_iniciar_cobro_sin_pos_sincronizado_lanza_excepcion(): void
    {
        $config = $this->crearConfig();
        $caja = $this->crearCaja(); // sin mp_pos_*
        $tx = $this->transaccionFake($caja, 'qr_estatico');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/POS.*sincroniz/i');

        $this->gateway->iniciarCobro($config, $tx);
    }

    // ==================== Cobro Point — iniciarCobro (Fase 2) ====================

    private function cajaConTerminalPoint(): \App\Models\Caja
    {
        $caja = $this->crearCaja();
        $caja->update(['mp_point_terminal_id' => 'PAX_A910__SN123456']);

        return $caja->refresh();
    }

    private function transaccionFakePoint(\App\Models\Caja $caja, array $point = []): IntegracionPagoTransaccion
    {
        $tx = new IntegracionPagoTransaccion([
            'monto' => 1500.50,
            'modo_usado' => 'point',
            'caja_id' => $caja->id,
            'metadata' => $point ? ['point' => $point] : null,
        ]);
        $tx->id = 7373;
        $tx->setRelation('caja', $caja);

        return $tx;
    }

    public function test_iniciar_cobro_point_envia_type_point_terminal_y_no_devuelve_qr(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD00000POINT1'], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaConTerminalPoint();
        $tx = $this->transaccionFakePoint($caja); // sin default_type ⇒ "Abierto"

        $res = $this->gateway->iniciarCobro($config, $tx);

        $this->assertNull($res['qr_data']);
        $this->assertNull($res['qr_image_url']);
        $this->assertSame('ORD00000POINT1', $res['external_id']);
        $this->assertSame('BCN-TX-7373', $res['external_reference']);

        Http::assertSent(function ($r) use ($caja) {
            $d = $r->data();

            return $r->method() === 'POST'
                && str_ends_with($r->url(), '/v1/orders')
                && $d['type'] === 'point'
                && $d['config']['point']['terminal_id'] === $caja->mp_point_terminal_id
                && $d['transactions']['payments'][0]['amount'] === '1500.50'
                && str_starts_with($d['expiration_time'], 'PT') && str_ends_with($d['expiration_time'], 'S')
                // "Abierto": NO se envía payment_method.
                && ! array_key_exists('payment_method', $d['config'])
                && $r->hasHeader('X-Idempotency-Key', 'order-BCN-TX-7373');
        });
    }

    public function test_iniciar_cobro_point_credito_con_cuotas_envia_default_type_e_installments(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD-PT-CUOTAS'], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaConTerminalPoint();
        $tx = $this->transaccionFakePoint($caja, ['default_type' => 'credit_card', 'installments' => 3]);

        $this->gateway->iniciarCobro($config, $tx);

        Http::assertSent(function ($r) {
            $pm = $r->data()['config']['payment_method'] ?? [];

            return ($pm['default_type'] ?? null) === 'credit_card'
                && ($pm['default_installments'] ?? null) === 3;
        });
    }

    public function test_iniciar_cobro_point_debito_no_envia_installments(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD-PT-DEB'], 201),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaConTerminalPoint();
        // Aunque venga installments, en débito no aplica.
        $tx = $this->transaccionFakePoint($caja, ['default_type' => 'debit_card', 'installments' => 6]);

        $this->gateway->iniciarCobro($config, $tx);

        Http::assertSent(function ($r) {
            $pm = $r->data()['config']['payment_method'] ?? [];

            return ($pm['default_type'] ?? null) === 'debit_card'
                && ! array_key_exists('default_installments', $pm);
        });
    }

    public function test_iniciar_cobro_point_sin_terminal_asignada_lanza_excepcion(): void
    {
        $config = $this->crearConfig();
        $caja = $this->crearCaja(); // sin mp_point_terminal_id
        $tx = $this->transaccionFakePoint($caja);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/terminal Point/i');

        $this->gateway->iniciarCobro($config, $tx);
    }

    public function test_cancelar_cobro_point_envia_header_at_terminal(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders/*/cancel' => Http::response(['status' => 'canceled'], 200),
        ]);

        $config = $this->crearConfig();
        $caja = $this->cajaConTerminalPoint();
        $tx = $this->transaccionFakePoint($caja);
        $tx->external_id = 'ORD-PT-CANCEL';

        $this->assertTrue($this->gateway->cancelarCobro($config, $tx));

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_contains($r->url(), '/v1/orders/ORD-PT-CANCEL/cancel')
            && $r->hasHeader('x-allow-cancelable-status', 'at_terminal'));
    }

    // ==================== Cobro QR monto-libre (qr_libre) ====================

    public function test_iniciar_cobro_qr_libre_no_llama_a_mp_y_devuelve_la_imagen(): void
    {
        // Si el gateway pegara a MP, assertNothingSent fallaría.
        Http::fake();

        $config = $this->crearConfig();
        $tx = new IntegracionPagoTransaccion([
            'monto' => 2500.00,
            'modo_usado' => 'qr_libre',
            'metadata' => ['qr_libre_imagen_url' => 'https://cdn.bcn/qr-cobrar.png'],
        ]);
        $tx->id = 4242;

        $res = $this->gateway->iniciarCobro($config, $tx);

        $this->assertNull($res['qr_data']);
        $this->assertSame('https://cdn.bcn/qr-cobrar.png', $res['qr_image_url']);
        $this->assertNull($res['external_id']); // no hay order en MP
        $this->assertSame('BCN-TX-4242', $res['external_reference']);

        Http::assertNothingSent();
    }

    public function test_iniciar_cobro_qr_libre_sin_imagen_configurada_lanza_excepcion(): void
    {
        Http::fake();

        $config = $this->crearConfig();
        $tx = new IntegracionPagoTransaccion([
            'monto' => 1000.00,
            'modo_usado' => 'qr_libre',
        ]); // sin metadata['qr_libre_imagen_url']
        $tx->id = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/imagen del QR/i');

        $this->gateway->iniciarCobro($config, $tx);

        Http::assertNothingSent();
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

    public function test_store_usa_nombre_publico_de_la_sucursal_si_existe(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response(['id' => 1, 'external_id' => 'X'], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update(['nombre_publico' => 'Pizzería del Centro']);

        $this->gateway->crearStore($config, $sucursal->refresh(), $this->comercio->id);

        Http::assertSent(fn ($request) => $request->data()['name'] === 'Pizzería del Centro');
    }

    public function test_store_cae_al_nombre_del_comercio_si_la_sucursal_no_tiene_nombre_publico(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response(['id' => 1, 'external_id' => 'X'], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update(['nombre_publico' => null]);

        $this->gateway->crearStore($config, $sucursal->refresh(), $this->comercio->id);

        // No el nombre interno de la sucursal ni el de la cuenta MP: el del comercio.
        Http::assertSent(fn ($request) => $request->data()['name'] === $this->comercio->nombre);
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

    public function test_crear_store_con_external_id_duplicado_adopta_la_existente_y_la_actualiza(): void
    {
        $externalId = 'BCN-'.$this->comercio->id.'-'.$this->sucursalId;

        Http::fake([
            // La búsqueda devuelve la store que ya existe en la cuenta MP.
            'api.mercadopago.com/users/*/stores/search*' => Http::response([
                'results' => [
                    ['id' => 5550001, 'external_id' => $externalId],
                ],
            ], 200),
            // El PUT de actualización (adopción).
            'api.mercadopago.com/users/*/stores/5550001' => Http::response([
                'id' => 5550001,
                'external_id' => $externalId,
            ], 200),
            // El POST de creación falla con el 400 de external_id duplicado.
            'api.mercadopago.com/users/*/stores' => Http::response([
                'message' => "external id '{$externalId}' is already assigned to this user 555111",
                'error' => 'bad_request',
            ], 400),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();

        $resp = $this->gateway->crearStore($config, $sucursal, $this->comercio->id);

        $this->assertSame(5550001, $resp['id']);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/stores'));
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/stores/search'));
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/stores/5550001'));
    }

    public function test_crear_pos_con_external_id_duplicado_adopta_el_existente_y_conserva_su_qr(): void
    {
        $config = $this->crearConfig();
        $sucursal = $this->sucursalSincronizada();
        $caja = $this->crearCaja();
        $externalId = \App\Services\IntegracionesPago\MercadoPagoGateway::externalIdPos($this->comercio->id, $caja->id);

        Http::fake([
            // La búsqueda (GET /pos?external_id=...) devuelve el POS existente con su QR.
            'api.mercadopago.com/pos?*' => Http::response([
                'paging' => ['total' => 1],
                'results' => [
                    [
                        'id' => 6660002,
                        'external_id' => $externalId,
                        'qr' => [
                            'image' => 'https://mp.com/qr/existente.png',
                            'template_document' => 'https://mp.com/qr/existente.pdf',
                        ],
                    ],
                ],
            ], 200),
            // El PUT de actualización no devuelve el QR (caso real de MP).
            'api.mercadopago.com/pos/6660002' => Http::response(['id' => 6660002], 200),
            // El POST de creación falla con el 409 de POS ya existente (caso real de MP).
            'api.mercadopago.com/pos' => Http::response([
                'message' => 'Point of sale with corresponding user and id exists',
                'error' => 'point_of_sale_exists',
            ], 409),
        ]);

        $resp = $this->gateway->crearPos($config, $caja, $sucursal, null, $this->comercio->id);

        $this->assertSame(6660002, $resp['id']);
        // Conservó el QR del POS existente aunque el PUT no lo devolvió.
        $this->assertSame('https://mp.com/qr/existente.png', $resp['qr']['image']);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/pos'));
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'external_id='));
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/pos/6660002'));
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

    // ==================== Reporte de cuenta (conciliación) ====================

    public function test_solicitar_reporte_cuenta_envia_rango_y_devuelve_solicitud(): void
    {
        $config = $this->crearConfig();

        Http::fake([
            '*/v1/account/settlement_report/config' => Http::response(['columns' => [['key' => 'DATE']]], 200),
            '*/v1/account/settlement_report' => Http::response([], 202),
        ]);

        $solicitud = $this->gateway->solicitarReporteCuenta(
            $config,
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-10'),
        );

        // Solicitud = JSON con el rango pedido + cuándo se pidió (para que el
        // matcheo del listado descarte reportes anteriores a la solicitud).
        $datos = json_decode($solicitud, true);
        $this->assertSame('2026-06-01T00:00:00Z', $datos['begin']);
        $this->assertSame('2026-06-10T23:59:59Z', $datos['end']);
        $this->assertNotEmpty($datos['solicitado_en']);
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/v1/account/settlement_report')
            && $r['begin_date'] === '2026-06-01T00:00:00Z'
            && $r['end_date'] === '2026-06-10T23:59:59Z');
    }

    public function test_solicitar_reporte_crea_la_config_si_no_existe(): void
    {
        $config = $this->crearConfig();

        Http::fake([
            '*/v1/account/settlement_report/config' => Http::sequence()
                ->push(['message' => 'not found'], 404)
                ->push(['columns' => []], 201),
            '*/v1/account/settlement_report' => Http::response([], 202),
        ]);

        $this->gateway->solicitarReporteCuenta(
            $config,
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-01'),
        );

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/settlement_report/config')
            && $r['include_withdraw'] === true);
    }

    public function test_obtener_reporte_devuelve_null_si_no_esta_listo(): void
    {
        $config = $this->crearConfig();

        Http::fake([
            '*/v1/account/settlement_report/list' => Http::response([
                ['file_name' => 'otro-rango.csv', 'begin_date' => '2026-05-01T00:00:00Z', 'end_date' => '2026-05-31T23:59:59Z'],
            ], 200),
        ]);

        $resultado = $this->gateway->obtenerReporteCuenta($config, '2026-06-01T00:00:00Z|2026-06-10T23:59:59Z');

        $this->assertNull($resultado);
    }

    public function test_obtener_reporte_descarga_y_normaliza_todos_los_tipos(): void
    {
        $config = $this->crearConfig();

        $csv = implode("\n", [
            'TRANSACTION_TYPE,SOURCE_ID,EXTERNAL_REFERENCE,TRANSACTION_DATE,TRANSACTION_AMOUNT,FEE_AMOUNT,SETTLEMENT_NET_AMOUNT,PAYMENT_METHOD',
            'SETTLEMENT,111,BCN-TX-1,2026-06-02T10:00:00.000-04:00,1000.00,-41.00,959.00,account_money',
            'REFUND,222,,2026-06-03T11:00:00.000-04:00,-500.00,0,-500.00,credit_card',
            'CHARGEBACK,333,,2026-06-04T12:00:00.000-04:00,-200.00,0,-200.00,credit_card',
            'WITHDRAWAL,444,,2026-06-05T13:00:00.000-04:00,-3000.00,0,-3000.00,',
            'WITHDRAWAL_CANCEL,555,,2026-06-06T14:00:00.000-04:00,3000.00,0,3000.00,',
            'SOMETHING_NEW,666,,2026-06-07T15:00:00.000-04:00,750.00,0,750.00,',
        ]);

        Http::fake([
            '*/v1/account/settlement_report/list' => Http::response([
                ['file_name' => 'bcn-conciliacion-1.csv', 'begin_date' => '2026-06-01T00:00:00Z', 'end_date' => '2026-06-10T23:59:59Z'],
            ], 200),
            '*/v1/account/settlement_report/bcn-conciliacion-1.csv' => Http::response($csv, 200),
        ]);

        $resultado = $this->gateway->obtenerReporteCuenta($config, '2026-06-01T00:00:00Z|2026-06-10T23:59:59Z');

        $this->assertSame('bcn-conciliacion-1.csv', $resultado['archivo']);
        $filas = $resultado['filas'];
        $this->assertCount(6, $filas);

        $porId = collect($filas)->keyBy('id_externo');
        $this->assertSame('cobro', $porId['111']['tipo']);
        $this->assertSame('BCN-TX-1', $porId['111']['referencia']);
        $this->assertEquals(1000.00, $porId['111']['monto_bruto']);
        $this->assertEquals(41.00, $porId['111']['comision']);
        $this->assertEquals(959.00, $porId['111']['monto_neto']);
        $this->assertSame('2026-06-02', substr($porId['111']['fecha'], 0, 10));

        $this->assertSame('devolucion', $porId['222']['tipo']);
        $this->assertSame('contracargo', $porId['333']['tipo']);
        $this->assertSame('retiro', $porId['444']['tipo']);
        $this->assertSame('retiro_cancelado', $porId['555']['tipo']);
        // Crédito de tipo desconocido → acreditación (rendiciones, transferencias).
        $this->assertSame('acreditacion', $porId['666']['tipo']);
    }

    public function test_obtener_reporte_soporta_dialecto_net_credit_debit_y_separador_punto_y_coma(): void
    {
        $config = $this->crearConfig();

        $csv = implode("\n", [
            'DATE;SOURCE_ID;EXTERNAL_REFERENCE;RECORD_TYPE;DESCRIPTION;NET_CREDIT_AMOUNT;NET_DEBIT_AMOUNT;GROSS_AMOUNT;MP_FEE_AMOUNT',
            '2026-06-02T10:00:00.000-04:00;777;BCN-TX-9;release;payment;959.00;0.00;1000.00;-41.00',
            '2026-06-05T13:00:00.000-04:00;888;;release;withdrawal;0.00;3000.00;-3000.00;0.00',
        ]);

        Http::fake([
            '*/v1/account/settlement_report/list' => Http::response([
                ['file_name' => 'rep.csv', 'begin_date' => '2026-06-01T03:00:00Z', 'end_date' => '2026-06-10T03:00:00Z'],
            ], 200),
            '*/v1/account/settlement_report/rep.csv' => Http::response($csv, 200),
        ]);

        $resultado = $this->gateway->obtenerReporteCuenta($config, '2026-06-01T00:00:00Z|2026-06-10T23:59:59Z');

        $filas = collect($resultado['filas'])->keyBy('id_externo');
        $this->assertSame('cobro', $filas['777']['tipo']);
        $this->assertEquals(959.00, $filas['777']['monto_neto']);
        $this->assertEquals(41.00, $filas['777']['comision']);
        $this->assertSame('retiro', $filas['888']['tipo']);
        $this->assertEquals(-3000.00, $filas['888']['monto_neto']);
    }

    public function test_obtener_reporte_con_error_de_descarga_lanza_excepcion(): void
    {
        $config = $this->crearConfig();

        Http::fake([
            '*/v1/account/settlement_report/list' => Http::response([
                ['file_name' => 'rep.csv', 'begin_date' => '2026-06-01T00:00:00Z', 'end_date' => '2026-06-10T23:59:59Z'],
            ], 200),
            '*/v1/account/settlement_report/rep.csv' => Http::response(['message' => 'forbidden'], 403),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->gateway->obtenerReporteCuenta($config, '2026-06-01T00:00:00Z|2026-06-10T23:59:59Z');
    }
}
