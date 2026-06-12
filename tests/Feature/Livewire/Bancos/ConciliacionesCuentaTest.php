<?php

namespace Tests\Feature\Livewire\Bancos;

use App\Livewire\Bancos\ConciliacionesCuenta;
use App\Models\ConceptoMovimientoCuenta;
use App\Models\ConciliacionCuenta;
use App\Models\ConciliacionFila;
use App\Models\CuentaEmpresa;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MovimientoCuentaEmpresa;
use App\Models\User;
use App\Services\IntegracionesPago\ConciliacionCuentaService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 4 — pantalla de conciliaciones (crear corrida, revisión,
 * permisos de aplicar/descartar).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 4, RF-06/RF-09).
 */
class ConciliacionesCuentaTest extends TestCase
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
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        ConceptoMovimientoCuenta::firstOrCreate(
            ['codigo' => 'retiro_integracion'],
            ['nombre' => 'Retiro a banco desde el proveedor', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 14, 'activo' => true],
        );

        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);
        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        ConciliacionFila::query()->delete();
        ConciliacionCuenta::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        MovimientoCuentaEmpresa::query()->delete();
        CuentaEmpresa::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function actuarComoAdmin(): User
    {
        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function crearCuentaConciliable(): CuentaEmpresa
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'produccion',
            'access_token_produccion' => 'APP_USR-PROD-UI',
            'user_id_externo' => '999888777',
            'activo' => true,
        ]);

        return CuentaEmpresa::create([
            'nombre' => 'Mercado Pago 999888777',
            'tipo' => CuentaEmpresa::TIPO_BILLETERA,
            'subtipo' => 'mercadopago',
            'identificador_externo' => '999888777',
            'activo' => true,
        ]);
    }

    /**
     * Corrida en pendiente_revision con una fila de retiro propuesta.
     */
    private function corridaEnRevision(CuentaEmpresa $cuenta): ConciliacionCuenta
    {
        $service = app(ConciliacionCuentaService::class);
        $corrida = $service->crearCorrida($cuenta, now()->subDays(7), now(), 1);
        $service->ejecutarMatch($corrida, [[
            'tipo' => 'retiro',
            'id_externo' => 'RET-1',
            'referencia' => null,
            'fecha' => now()->subDay()->format('Y-m-d H:i:s'),
            'descripcion' => 'withdrawal',
            'monto_bruto' => -500.0,
            'comision' => 0.0,
            'monto_neto' => -500.0,
        ]]);

        return $corrida->refresh();
    }

    public function test_crear_corrida_desde_el_componente(): void
    {
        $this->actuarComoAdmin();
        $cuenta = $this->crearCuentaConciliable();

        Livewire::test(ConciliacionesCuenta::class)
            ->call('abrirNueva')
            ->set('nuevaCuentaId', (string) $cuenta->id)
            ->call('crearCorrida')
            ->assertSet('showModalNueva', false)
            ->assertDispatched('toast-success');

        $this->assertSame(1, ConciliacionCuenta::deCuenta($cuenta->id)->count());
    }

    public function test_crear_corrida_para_cuenta_sin_config_prod_muestra_error(): void
    {
        $this->actuarComoAdmin();
        // Cuenta con identificador pero SIN config de integración.
        $cuenta = CuentaEmpresa::create([
            'nombre' => 'MP huérfana', 'tipo' => CuentaEmpresa::TIPO_BILLETERA,
            'subtipo' => 'mercadopago', 'identificador_externo' => '111222333', 'activo' => true,
        ]);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('abrirNueva')
            ->set('nuevaCuentaId', (string) $cuenta->id)
            ->call('crearCorrida')
            ->assertDispatched('toast-error');

        $this->assertSame(0, ConciliacionCuenta::count());
    }

    public function test_toggle_accion_de_fila_recalcula_totales(): void
    {
        $this->actuarComoAdmin();
        $cuenta = $this->crearCuentaConciliable();
        $corrida = $this->corridaEnRevision($cuenta);
        $fila = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->first();

        $this->assertEquals(500, (float) $corrida->monto_propuesto_egresos);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('verDetalle', $corrida->id)
            ->call('toggleAccionFila', $fila->id);

        $this->assertSame(ConciliacionFila::ACCION_IGNORAR, $fila->fresh()->accion);
        $this->assertEquals(0, (float) $corrida->fresh()->monto_propuesto_egresos);
    }

    public function test_aplicar_requiere_permiso(): void
    {
        // Usuario regular sin func.conciliaciones.aplicar.
        $this->actingAs(User::factory()->create(['is_system_admin' => false]));
        $cuenta = $this->crearCuentaConciliable();
        $corrida = $this->corridaEnRevision($cuenta);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('verDetalle', $corrida->id)
            ->call('confirmarAplicar')
            ->assertSet('showConfirmAplicar', false)
            ->call('aplicar');

        $this->assertSame(ConciliacionCuenta::ESTADO_PENDIENTE_REVISION, $corrida->fresh()->estado);
        $this->assertSame(0, MovimientoCuentaEmpresa::count());
    }

    public function test_aplicar_con_permiso_genera_los_movimientos(): void
    {
        $this->actuarComoAdmin();
        $cuenta = $this->crearCuentaConciliable();
        $corrida = $this->corridaEnRevision($cuenta);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('verDetalle', $corrida->id)
            ->call('confirmarAplicar')
            ->assertSet('showConfirmAplicar', true)
            ->call('aplicar')
            ->assertDispatched('toast-success');

        $this->assertSame(ConciliacionCuenta::ESTADO_APLICADA, $corrida->fresh()->estado);
        $this->assertSame(1, MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count());
        $this->assertEquals(-500, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_detalle_arranca_en_novedades_oculta_ya_registrado_y_muestra_hint_de_lag(): void
    {
        $this->actuarComoAdmin();
        $cuenta = $this->crearCuentaConciliable();
        $corrida = $this->corridaEnRevision($cuenta);

        // Ruido: fila ya materializada en una corrida anterior.
        ConciliacionFila::create([
            'conciliacion_cuenta_id' => $corrida->id,
            'tipo' => ConciliacionFila::TIPO_COBRO,
            'clasificacion' => ConciliacionFila::CLASIFICACION_YA_REGISTRADO,
            'id_externo' => 'PAY-VIEJO',
            'fecha' => now()->subDays(3),
            'descripcion' => 'cobro viejo ya registrado',
            'monto_bruto' => 100,
            'monto_neto' => 100,
            'accion' => ConciliacionFila::ACCION_SIN_ACCION,
        ]);

        // Cobro reciente sin contraparte en el reporte (lag del proveedor).
        ConciliacionFila::create([
            'conciliacion_cuenta_id' => $corrida->id,
            'tipo' => ConciliacionFila::TIPO_COBRO,
            'clasificacion' => ConciliacionFila::CLASIFICACION_SOLO_SISTEMA,
            'fecha' => now()->subHours(2),
            'descripcion' => 'cobro reciente solo sistema',
            'monto_bruto' => 50,
            'monto_neto' => 50,
            'accion' => ConciliacionFila::ACCION_SIN_ACCION,
        ]);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('verDetalle', $corrida->id)
            ->assertSet('filtroClasificacion', ConciliacionesCuenta::FILTRO_NOVEDADES)
            ->assertSee('cobro reciente solo sistema')
            ->assertDontSee('cobro viejo ya registrado')
            ->assertSee('todavía no figura en el reporte del proveedor')
            ->set('filtroClasificacion', '')
            ->assertSee('cobro viejo ya registrado');
    }

    public function test_descartar_con_permiso(): void
    {
        $this->actuarComoAdmin();
        $cuenta = $this->crearCuentaConciliable();
        $corrida = $this->corridaEnRevision($cuenta);

        Livewire::test(ConciliacionesCuenta::class)
            ->call('verDetalle', $corrida->id)
            ->call('confirmarDescartar')
            ->call('descartar')
            ->assertDispatched('toast-success');

        $this->assertSame(ConciliacionCuenta::ESTADO_DESCARTADA, $corrida->fresh()->estado);
        $this->assertSame(0, MovimientoCuentaEmpresa::count());
    }
}
