<?php

namespace Tests\Integration\Models;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 1 — Integraciones de Pago.
 *
 * Cubre los 5 modelos creados: catálogo, config por sucursal (con encriptación
 * y sincronización del índice global), transacciones polimórficas, eventos de
 * auditoría e índice de resolución multi-tenant.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 1).
 */
class IntegracionPagoTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // Sembrar catálogo MP si no está (depende de si el tenant ya fue provisionado).
        if (! IntegracionPago::porCodigo('mercadopago_qr')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
                'activo' => true,
                'orden' => 1,
            ]);
        }

        // Limpiar índice global (DB config) por si quedó de tests anteriores.
        MercadoPagoCollectorIndex::query()->delete();
    }

    protected function tearDown(): void
    {
        // Limpiar índice global antes de tearDown del tenant.
        MercadoPagoCollectorIndex::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ========================================================================
    // IntegracionPago (catálogo)
    // ========================================================================

    public function test_integracion_pago_scope_activas_filtra_inactivas(): void
    {
        $activa = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $inactiva = IntegracionPago::create([
            'codigo' => 'fake_provider',
            'nombre' => 'Fake Provider',
            'modos_disponibles' => ['x'],
            'gateway_class' => 'App\\Fake',
            'activo' => false,
        ]);

        $activas = IntegracionPago::activas()->get();

        $this->assertTrue($activas->contains('id', $activa->id));
        $this->assertFalse($activas->contains('id', $inactiva->id));
    }

    public function test_integracion_pago_cast_modos_disponibles_es_array(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $this->assertIsArray($mp->modos_disponibles);
        $this->assertContains('qr_dinamico', $mp->modos_disponibles);
        $this->assertContains('qr_estatico', $mp->modos_disponibles);
    }

    public function test_soporta_modo_devuelve_true_para_modos_listados(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $this->assertTrue($mp->soportaModo('qr_dinamico'));
        $this->assertTrue($mp->soportaModo('qr_estatico'));
        $this->assertFalse($mp->soportaModo('link_pago'));
        $this->assertFalse($mp->soportaModo('inexistente'));
    }

    // ========================================================================
    // IntegracionPagoSucursal (config + encriptación + hooks)
    // ========================================================================

    public function test_credenciales_se_guardan_encriptadas_en_db(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $tokenPlano = 'TEST-1234567890-abcdef-secreto';

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => $tokenPlano,
            'user_id_externo' => 'test-user-001',
        ]);

        // El accessor del modelo devuelve el valor desencriptado.
        $this->assertSame($tokenPlano, $config->fresh()->access_token_test);

        // Pero el valor en DB es distinto (encriptado).
        $raw = DB::connection('pymes_tenant')->table('integraciones_pago_sucursales')
            ->where('id', $config->id)
            ->value('access_token_test');

        $this->assertNotSame($tokenPlano, $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_get_access_token_activo_devuelve_segun_modo(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TOKEN-TEST',
            'access_token_produccion' => 'TOKEN-PROD',
        ]);

        $this->assertSame('TOKEN-TEST', $config->getAccessTokenActivo());

        $config->update(['modo' => 'produccion']);
        $this->assertSame('TOKEN-PROD', $config->fresh()->getAccessTokenActivo());
    }

    public function test_crear_config_mp_sincroniza_indice_global(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            'user_id_externo' => 'mp-user-12345',
        ]);

        $idx = MercadoPagoCollectorIndex::porUserId('mp-user-12345', 'test')->first();

        $this->assertNotNull($idx);
        $this->assertSame($this->comercio->id, $idx->comercio_id);
        $this->assertSame($this->sucursalId, $idx->sucursal_id);
        $this->assertSame($config->id, $idx->integracion_pago_sucursal_id);
    }

    public function test_cambio_de_user_id_borra_entrada_vieja_del_indice(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            'user_id_externo' => 'user-viejo',
        ]);

        $config->update(['user_id_externo' => 'user-nuevo']);

        $this->assertSame(0, MercadoPagoCollectorIndex::porUserId('user-viejo', 'test')->count());
        $this->assertSame(1, MercadoPagoCollectorIndex::porUserId('user-nuevo', 'test')->count());
    }

    public function test_borrar_config_borra_entrada_del_indice(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            'user_id_externo' => 'user-eliminado',
        ]);

        $config->delete();

        $this->assertSame(0, MercadoPagoCollectorIndex::porUserId('user-eliminado', 'test')->count());
    }

    public function test_config_sin_user_id_externo_no_sincroniza_indice(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $countAntes = MercadoPagoCollectorIndex::count();

        IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            // sin user_id_externo
        ]);

        $this->assertSame($countAntes, MercadoPagoCollectorIndex::count());
    }

    public function test_defaults_aplican_al_crear_sin_pasar_modo_ni_activo(): void
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        $this->assertSame('test', $config->modo);
        $this->assertTrue($config->activo);
        $this->assertSame(300, $config->timeout_segundos);
    }

    // ========================================================================
    // IntegracionPagoTransaccion (estados, scopes, helpers)
    // ========================================================================

    private function crearTransaccion(array $overrides = []): IntegracionPagoTransaccion
    {
        $mp = IntegracionPago::porCodigo('mercadopago_qr')->first();
        // Reusar config si ya existe (UNIQUE constraint integracion_pago_id+sucursal_id).
        $config = IntegracionPagoSucursal::firstOrCreate(
            ['integracion_pago_id' => $mp->id, 'sucursal_id' => $this->sucursalId],
            ['modo' => 'test', 'access_token_test' => 'X']
        );

        $fpId = DB::connection('pymes_tenant')->table('formas_pago')->insertGetId([
            'nombre' => 'MP Test',
            'codigo' => 'MPTEST',
            'concepto' => 'wallet',
            'es_mixta' => false,
            'permite_cuotas' => false,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return IntegracionPagoTransaccion::create(array_merge([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $fpId,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => IntegracionPagoTransaccion::MODO_QR_DINAMICO,
            'monto' => 1500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'expira_en' => now()->addMinutes(5),
            'cobrable_type' => 'App\\Models\\Venta',
            'cobrable_id' => 1,
        ], $overrides));
    }

    public function test_transaccion_helpers_estado(): void
    {
        $tx = $this->crearTransaccion();

        $this->assertTrue($tx->estaPendiente());
        $this->assertFalse($tx->estaConfirmada());
        $this->assertFalse($tx->estaEnEstadoTerminal());
        $this->assertFalse($tx->estaVencida());
    }

    public function test_transaccion_esta_confirmada_para_ambos_estados_confirmados(): void
    {
        $tx1 = $this->crearTransaccion(['estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO]);
        $tx2 = $this->crearTransaccion(['estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO_MANUAL]);

        $this->assertTrue($tx1->estaConfirmada());
        $this->assertTrue($tx2->estaConfirmada());
        $this->assertTrue($tx1->estaEnEstadoTerminal());
        $this->assertTrue($tx2->estaEnEstadoTerminal());
    }

    public function test_scope_vencidas_filtra_pendientes_con_expira_en_pasado(): void
    {
        $vencida = $this->crearTransaccion(['expira_en' => now()->subMinutes(10)]);
        $vigente = $this->crearTransaccion(['expira_en' => now()->addMinutes(5)]);
        // Una "pendiente" pero ya confirmada NO debe aparecer aunque expira_en pasó.
        $confirmadaVencida = $this->crearTransaccion([
            'expira_en' => now()->subMinutes(10),
            'estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
        ]);

        $vencidas = IntegracionPagoTransaccion::vencidas()->get();

        $this->assertTrue($vencidas->contains('id', $vencida->id));
        $this->assertFalse($vencidas->contains('id', $vigente->id));
        $this->assertFalse($vencidas->contains('id', $confirmadaVencida->id));
    }

    public function test_scope_por_cobrable_filtra_por_tipo_e_id(): void
    {
        $tx1 = $this->crearTransaccion(['cobrable_type' => 'App\\Models\\Venta', 'cobrable_id' => 99]);
        $tx2 = $this->crearTransaccion(['cobrable_type' => 'App\\Models\\PedidoMostrador', 'cobrable_id' => 99]);

        $result = IntegracionPagoTransaccion::porCobrable('App\\Models\\Venta', 99)->get();

        $this->assertCount(1, $result);
        $this->assertSame($tx1->id, $result->first()->id);
    }

    // ========================================================================
    // IntegracionPagoEvento (append-only, sin updated_at)
    // ========================================================================

    public function test_evento_no_tiene_updated_at_y_setea_created_at_automatico(): void
    {
        $tx = $this->crearTransaccion();

        $evento = IntegracionPagoEvento::create([
            'transaccion_id' => $tx->id,
            'evento' => IntegracionPagoEvento::EVENTO_CREADO,
        ]);

        $this->assertNotNull($evento->created_at);

        $raw = DB::connection('pymes_tenant')->table('integraciones_pago_eventos')
            ->where('id', $evento->id)
            ->first();

        // La tabla no tiene columna updated_at (verificable porque no aparece en el dump).
        $this->assertObjectNotHasProperty('updated_at', $raw);
    }

    public function test_evento_pertenece_a_transaccion(): void
    {
        $tx = $this->crearTransaccion();

        $evento = IntegracionPagoEvento::create([
            'transaccion_id' => $tx->id,
            'evento' => IntegracionPagoEvento::EVENTO_WEBHOOK_RECIBIDO,
            'payload_externo' => ['payment_id' => 'XYZ123'],
        ]);

        $this->assertSame($tx->id, $evento->transaccion->id);
        $this->assertSame(['payment_id' => 'XYZ123'], $evento->payload_externo);
    }
}
