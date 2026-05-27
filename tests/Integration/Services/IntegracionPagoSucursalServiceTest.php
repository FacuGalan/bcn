<?php

namespace Tests\Integration\Services;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MercadoPagoCollectorIndex;
use App\Services\IntegracionesPago\IntegracionPagoSucursalService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 2 — IntegracionPagoSucursalService.
 *
 * Verifica que el service envuelve correctamente las operaciones CRUD del
 * modelo `IntegracionPagoSucursal` en transacción + logging, y que la
 * sincronización del índice global (`mercadopago_collector_index`) se
 * mantiene consistente a través del service.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 2).
 */
class IntegracionPagoSucursalServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

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
                'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
                'activo' => true,
                'orden' => 1,
            ]);
        }

        MercadoPagoCollectorIndex::query()->delete();
    }

    protected function tearDown(): void
    {
        MercadoPagoCollectorIndex::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function mpId(): int
    {
        return IntegracionPago::porCodigo('mercadopago')->value('id');
    }

    public function test_crear_persiste_config_y_sincroniza_indice_global(): void
    {
        $config = IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => $this->mpId(),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-SVC',
            'user_id_externo' => 'mp-user-svc-001',
        ]);

        $this->assertInstanceOf(IntegracionPagoSucursal::class, $config);
        $this->assertNotNull($config->id);
        $this->assertSame('TEST-TOKEN-SVC', $config->fresh()->access_token_test);

        $idx = MercadoPagoCollectorIndex::porUserId('mp-user-svc-001', 'test')->first();
        $this->assertNotNull($idx);
        $this->assertSame($this->comercio->id, $idx->comercio_id);
        $this->assertSame($config->id, $idx->integracion_pago_sucursal_id);
    }

    public function test_actualizar_aplica_cambios_y_resincroniza_el_indice(): void
    {
        $config = IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => $this->mpId(),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TOKEN-VIEJO',
            'user_id_externo' => 'mp-user-cambio-001',
        ]);

        IntegracionPagoSucursalService::actualizar($config, [
            'access_token_test' => 'TOKEN-NUEVO',
            'user_id_externo' => 'mp-user-cambio-002',
            'timeout_segundos' => 600,
        ]);

        $fresh = $config->fresh();
        $this->assertSame('TOKEN-NUEVO', $fresh->access_token_test);
        $this->assertSame('mp-user-cambio-002', $fresh->user_id_externo);
        $this->assertSame(600, $fresh->timeout_segundos);

        $this->assertSame(0, MercadoPagoCollectorIndex::porUserId('mp-user-cambio-001', 'test')->count());
        $this->assertSame(1, MercadoPagoCollectorIndex::porUserId('mp-user-cambio-002', 'test')->count());
    }

    public function test_eliminar_borra_config_y_limpia_indice(): void
    {
        $config = IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => $this->mpId(),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            'user_id_externo' => 'mp-user-borrar',
        ]);

        $configId = $config->id;

        IntegracionPagoSucursalService::eliminar($config);

        $this->assertNull(IntegracionPagoSucursal::find($configId));
        $this->assertSame(0, MercadoPagoCollectorIndex::porUserId('mp-user-borrar', 'test')->count());
    }

    public function test_sincronizar_indice_repoblar_si_se_borro_manualmente(): void
    {
        $config = IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => $this->mpId(),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'X',
            'user_id_externo' => 'mp-user-resync',
        ]);

        // Borrado directo del índice (simula inconsistencia).
        MercadoPagoCollectorIndex::query()->delete();
        $this->assertSame(0, MercadoPagoCollectorIndex::count());

        IntegracionPagoSucursalService::sincronizarIndice($config);

        $idx = MercadoPagoCollectorIndex::porUserId('mp-user-resync', 'test')->first();
        $this->assertNotNull($idx);
        $this->assertSame($config->id, $idx->integracion_pago_sucursal_id);
    }
}
