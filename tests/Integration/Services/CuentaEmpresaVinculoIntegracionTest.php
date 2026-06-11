<?php

namespace Tests\Integration\Services;

use App\Models\CuentaEmpresa;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MercadoPagoCollectorIndex;
use App\Models\MovimientoCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use App\Services\IntegracionesPago\IntegracionPagoSucursalService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 3 — Vínculo CuentaEmpresa ↔ Integraciones de Pago.
 *
 * Verifica `CuentaEmpresaService::findOrCreateParaIntegracion()` (genérico,
 * solo producción, lookup en cascada D5, idempotente) y su invocación
 * automática al guardar credenciales vía `IntegracionPagoSucursalService`.
 *
 * Ref: .claude/specs/vinculo-cuenta-empresa-integraciones.md (RF-01/RF-02, D5).
 */
class CuentaEmpresaVinculoIntegracionTest extends TestCase
{
    use WithSucursal, WithTenant;

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
                'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
                'activo' => true,
                'orden' => 1,
            ]);
        }

        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        MovimientoCuentaEmpresa::query()->delete();
        CuentaEmpresa::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        MercadoPagoCollectorIndex::query()->delete();
    }

    private function crearConfig(array $overrides = []): IntegracionPagoSucursal
    {
        return IntegracionPagoSucursal::create(array_merge([
            'integracion_pago_id' => IntegracionPago::porCodigo('mercadopago_qr')->value('id'),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'produccion',
            'access_token_produccion' => 'APP_USR-PROD-TOKEN',
            'user_id_externo' => '111222333',
        ], $overrides));
    }

    // ==================== findOrCreateParaIntegracion ====================

    public function test_crea_cuenta_en_produccion_con_identidad_del_gateway(): void
    {
        $cuenta = CuentaEmpresaService::findOrCreateParaIntegracion($this->crearConfig());

        $this->assertNotNull($cuenta);
        $this->assertSame(CuentaEmpresa::TIPO_BILLETERA, $cuenta->tipo);
        $this->assertSame('mercadopago', $cuenta->subtipo);
        $this->assertSame('111222333', $cuenta->identificador_externo);
        $this->assertSame('Mercado Pago 111222333', $cuenta->nombre);
        $this->assertTrue($cuenta->activo);
    }

    public function test_en_modo_test_es_noop(): void
    {
        $config = $this->crearConfig(['modo' => 'test', 'access_token_test' => 'TEST-TOKEN']);

        $this->assertNull(CuentaEmpresaService::findOrCreateParaIntegracion($config));
        $this->assertSame(0, CuentaEmpresa::count());
    }

    public function test_sin_user_id_externo_es_noop(): void
    {
        $config = $this->crearConfig(['user_id_externo' => null]);

        $this->assertNull(CuentaEmpresaService::findOrCreateParaIntegracion($config));
        $this->assertSame(0, CuentaEmpresa::count());
    }

    public function test_es_idempotente_no_duplica_cuentas(): void
    {
        $config = $this->crearConfig();

        $primera = CuentaEmpresaService::findOrCreateParaIntegracion($config);
        $segunda = CuentaEmpresaService::findOrCreateParaIntegracion($config);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, CuentaEmpresa::count());
    }

    public function test_dos_configs_con_la_misma_cuenta_mp_comparten_cuenta_empresa(): void
    {
        // Dos sucursales (acá: dos configs) con el MISMO user_id_externo → una sola cuenta.
        $configA = $this->crearConfig();
        $configB = $this->crearConfig(['sucursal_id' => $this->sucursalId, 'integracion_pago_id' => $this->crearCatalogoPoint()]);

        $cuentaA = CuentaEmpresaService::findOrCreateParaIntegracion($configA);
        $cuentaB = CuentaEmpresaService::findOrCreateParaIntegracion($configB);

        $this->assertSame($cuentaA->id, $cuentaB->id);
        $this->assertSame(1, CuentaEmpresa::count());
    }

    public function test_identidades_distintas_generan_cuentas_distintas(): void
    {
        $cuentaA = CuentaEmpresaService::findOrCreateParaIntegracion($this->crearConfig());
        $cuentaB = CuentaEmpresaService::findOrCreateParaIntegracion(
            $this->crearConfig(['integracion_pago_id' => $this->crearCatalogoPoint(), 'user_id_externo' => '999888777'])
        );

        $this->assertNotSame($cuentaA->id, $cuentaB->id);
        $this->assertSame(2, CuentaEmpresa::count());
    }

    public function test_completa_unica_cuenta_manual_del_subtipo_sin_identificador(): void
    {
        // D5(b): una cuenta MP creada a mano antes del feature se reutiliza.
        $manual = CuentaEmpresa::create([
            'nombre' => 'MP del local',
            'tipo' => CuentaEmpresa::TIPO_BILLETERA,
            'subtipo' => 'mercadopago',
            'activo' => true,
        ]);

        $cuenta = CuentaEmpresaService::findOrCreateParaIntegracion($this->crearConfig());

        $this->assertSame($manual->id, $cuenta->id);
        $this->assertSame('111222333', $cuenta->identificador_externo);
        $this->assertSame('MP del local', $cuenta->nombre); // conserva el nombre manual
        $this->assertSame(1, CuentaEmpresa::count());
    }

    public function test_con_varias_cuentas_manuales_ambiguas_crea_una_nueva(): void
    {
        // D5(c): ante ambigüedad no adivina — crea una cuenta nueva.
        CuentaEmpresa::create(['nombre' => 'MP 1', 'tipo' => CuentaEmpresa::TIPO_BILLETERA, 'subtipo' => 'mercadopago', 'activo' => true]);
        CuentaEmpresa::create(['nombre' => 'MP 2', 'tipo' => CuentaEmpresa::TIPO_BILLETERA, 'subtipo' => 'mercadopago', 'activo' => true]);

        $cuenta = CuentaEmpresaService::findOrCreateParaIntegracion($this->crearConfig());

        $this->assertSame('Mercado Pago 111222333', $cuenta->nombre);
        $this->assertSame(3, CuentaEmpresa::count());
    }

    // ==================== Auto-vínculo al guardar credenciales ====================

    public function test_guardar_credenciales_prod_via_service_auto_vincula(): void
    {
        IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => IntegracionPago::porCodigo('mercadopago_qr')->value('id'),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'produccion',
            'access_token_produccion' => 'APP_USR-XYZ',
            'user_id_externo' => '444555666',
        ]);

        $cuenta = CuentaEmpresa::porIdentidad('mercadopago', '444555666')->first();
        $this->assertNotNull($cuenta, 'Guardar credenciales prod debe auto-crear la CuentaEmpresa');
    }

    public function test_guardar_credenciales_test_via_service_no_vincula(): void
    {
        IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => IntegracionPago::porCodigo('mercadopago_qr')->value('id'),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-XYZ',
            'user_id_externo' => '444555666',
        ]);

        $this->assertSame(0, CuentaEmpresa::count());
    }

    public function test_actualizar_a_produccion_via_service_auto_vincula(): void
    {
        $config = IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => IntegracionPago::porCodigo('mercadopago_qr')->value('id'),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-XYZ',
            'user_id_externo' => '777000111',
        ]);

        $this->assertSame(0, CuentaEmpresa::count());

        IntegracionPagoSucursalService::actualizar($config, [
            'modo' => 'produccion',
            'access_token_produccion' => 'APP_USR-PROD',
        ]);

        $this->assertNotNull(CuentaEmpresa::porIdentidad('mercadopago', '777000111')->first());
    }

    private function crearCatalogoPoint(): int
    {
        if (! IntegracionPago::porCodigo('mercadopago_point')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_point',
                'nombre' => 'Mercado Pago - Point',
                'modos_disponibles' => ['point'],
                'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
                'activo' => true,
                'orden' => 2,
            ]);
        }

        return IntegracionPago::porCodigo('mercadopago_point')->value('id');
    }
}
