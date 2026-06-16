<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionEmpresa;
use App\Livewire\Configuracion\CuitImpuestos;
use App\Livewire\Configuracion\FormasPagoSucursal;
use App\Livewire\Configuracion\GestionarFormasPago;
use App\Livewire\Configuracion\GestionMonedas;
use App\Livewire\Configuracion\Impresoras;
use App\Livewire\Configuracion\IntegracionesPago;
use App\Livewire\Configuracion\Precios\ListarPrecios;
use App\Livewire\Configuracion\Precios\WizardListaPrecio;
use App\Livewire\Configuracion\Precios\WizardPrecio;
use App\Livewire\Configuracion\Promociones\ListarPromociones;
use App\Livewire\Configuracion\Promociones\WizardPromocion;
use App\Livewire\Configuracion\PromocionesEspeciales\ListarPromocionesEspeciales;
use App\Livewire\Configuracion\PromocionesEspeciales\WizardPromocionEspecial;
use App\Livewire\Configuracion\RolesPermisos;
use App\Livewire\Configuracion\Usuarios;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests de Configuracion. Excluye FormasPago/ListarFormasPago
 * porque su mount() requiere parametros (no es smoke trivial).
 */
class SmokeConfiguracionTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // is_system_admin=true bypasa el check de permisos sin requerir asignar roles/permisos
        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        // Reset cache estático de SucursalService — el modelo cacheado entre tests
        // queda con valores viejos y rompe asserts (ver memoria bypass-sucursal-service-cache-en-tests-livewire).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, null);
        }

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_configuracion_empresa_monta(): void
    {
        Livewire::test(ConfiguracionEmpresa::class)->assertOk();
    }

    public function test_formas_pago_sucursal_monta(): void
    {
        Livewire::test(FormasPagoSucursal::class)->assertOk();
    }

    public function test_gestionar_formas_pago_monta(): void
    {
        Livewire::test(GestionarFormasPago::class)->assertOk();
    }

    public function test_gestion_monedas_monta(): void
    {
        Livewire::test(GestionMonedas::class)->assertOk();
    }

    public function test_impresoras_monta(): void
    {
        Livewire::test(Impresoras::class)->assertOk();
    }

    public function test_integraciones_pago_monta(): void
    {
        Livewire::test(IntegracionesPago::class)->assertOk();
    }

    public function test_integraciones_pago_abrir_config_para_integracion_existente(): void
    {
        // Asegurar catálogo MP sembrado (puede o no estarlo según el contexto).
        $mp = \App\Models\IntegracionPago::porCodigo('mercadopago_qr')->first();
        if (! $mp) {
            $mp = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        Livewire::test(IntegracionesPago::class)
            ->call('abrirConfig', $mp->id)
            ->assertSet('mostrarModal', true)
            ->assertSet('integracionPagoId', $mp->id)
            ->assertSet('editMode', false)
            // La ayuda del modal muestra la URL del webhook a configurar en MP.
            ->assertSee(route('integraciones.mercadopago.webhook'));
    }

    public function test_integraciones_pago_sincronizar_sucursal_persiste_mp_store_id(): void
    {
        $mp = \App\Models\IntegracionPago::porCodigo('mercadopago_qr')->first();
        if (! $mp) {
            $mp = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        // updateOrCreate por si tests previos en la misma corrida ya crearon un config
        // (UNIQUE constraint sobre integracion_pago_id + sucursal_id)
        $config = \App\Models\IntegracionPagoSucursal::updateOrCreate(
            [
                'integracion_pago_id' => $mp->id,
                'sucursal_id' => $this->sucursalId,
            ],
            [
                'modo' => 'test',
                'access_token_test' => 'TEST',
                'user_id_externo' => '12345',
            ]
        );

        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update([
            'direccion' => 'Test 123',
            'localidad' => 'CABA',
            'provincia' => 'AR-B',
            'latitud' => -34.6,
            'longitud' => -58.4,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.mercadopago.com/users/*/stores' => \Illuminate\Support\Facades\Http::response([
                'id' => 7654321,
                'external_id' => 'BCN-'.$this->comercio->id.'-'.$this->sucursalId,
            ], 201),
        ]);

        Livewire::test(IntegracionesPago::class)
            ->call('sincronizarSucursal', $config->id)
            ->assertDispatched('notify', fn ($name, $params) => ($params['type'] ?? null) === 'success');

        $this->assertSame('7654321', \App\Models\Sucursal::find($this->sucursalId)->mp_store_id);
    }

    public function test_integraciones_pago_probar_conexion_dispara_notify_success_con_credenciales_ok(): void
    {
        $mp = \App\Models\IntegracionPago::porCodigo('mercadopago_qr')->first();
        if (! $mp) {
            $mp = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        $config = \App\Models\IntegracionPagoSucursal::updateOrCreate(
            [
                'integracion_pago_id' => $mp->id,
                'sucursal_id' => $this->sucursalId,
            ],
            [
                'modo' => 'test',
                'access_token_test' => 'TEST-LIVEWIRE-OK',
                'user_id_externo' => '123456',
            ]
        );

        \Illuminate\Support\Facades\Http::fake([
            'api.mercadopago.com/users/me' => \Illuminate\Support\Facades\Http::response([
                'id' => 123456,
                'nickname' => 'LIVEWIRETEST',
            ], 200),
        ]);

        Livewire::test(IntegracionesPago::class)
            ->call('probarConexion', $config->id)
            ->assertDispatched('notify', function ($name, $params) {
                return ($params['type'] ?? null) === 'success'
                    && str_contains($params['message'] ?? '', 'LIVEWIRETEST');
            });
    }

    public function test_integraciones_pago_vincular_terminal_point_persiste_terminal_id(): void
    {
        $point = \App\Models\IntegracionPago::porCodigo('mercadopago_point')->first();
        if (! $point) {
            $point = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago_point',
                'nombre' => 'Mercado Pago - Point',
                'modos_disponibles' => ['point'],
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 2,
            ]);
        }

        $config = \App\Models\IntegracionPagoSucursal::updateOrCreate(
            [
                'integracion_pago_id' => $point->id,
                'sucursal_id' => $this->sucursalId,
            ],
            [
                'modo' => 'test',
                'access_token_test' => 'TEST-POINT',
                'user_id_externo' => '7777',
            ]
        );

        $caja = \App\Models\Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja Point',
            'codigo' => 'CPT',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.mercadopago.com/terminals/v1/list*' => \Illuminate\Support\Facades\Http::response([
                'data' => ['terminals' => [['id' => 'PAX_A910__SN999', 'operating_mode' => 'STANDALONE']]],
            ], 200),
            'api.mercadopago.com/terminals/v1/setup' => \Illuminate\Support\Facades\Http::response([
                'terminals' => [['id' => 'PAX_A910__SN999', 'operating_mode' => 'PDV']],
            ], 200),
        ]);

        Livewire::test(IntegracionesPago::class)
            ->call('buscarTerminales', $config->id)
            ->set("terminalSeleccionado.{$caja->id}", 'PAX_A910__SN999')
            ->call('vincularTerminal', $config->id, $caja->id)
            ->assertDispatched('notify', fn ($name, $params) => ($params['type'] ?? null) === 'success');

        $this->assertSame('PAX_A910__SN999', \App\Models\Caja::find($caja->id)->mp_point_terminal_id);
    }

    public function test_listar_precios_monta(): void
    {
        Livewire::test(ListarPrecios::class)->assertOk();
    }

    public function test_wizard_lista_precio_monta(): void
    {
        Livewire::test(WizardListaPrecio::class)->assertOk();
    }

    public function test_wizard_precio_monta(): void
    {
        Livewire::test(WizardPrecio::class)->assertOk();
    }

    public function test_listar_promociones_monta(): void
    {
        Livewire::test(ListarPromociones::class)->assertOk();
    }

    public function test_wizard_promocion_monta(): void
    {
        Livewire::test(WizardPromocion::class)->assertOk();
    }

    public function test_listar_promociones_especiales_monta(): void
    {
        Livewire::test(ListarPromocionesEspeciales::class)->assertOk();
    }

    public function test_wizard_promocion_especial_monta(): void
    {
        Livewire::test(WizardPromocionEspecial::class)->assertOk();
    }

    public function test_cuit_impuestos_monta(): void
    {
        Livewire::test(CuitImpuestos::class)->assertOk();
    }

    public function test_cuit_impuestos_abrir_y_agregar_impuesto(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        $imp = \App\Models\Impuesto::create([
            'codigo' => 'perc_iibb_ar_b', 'nombre' => 'Percepción IIBB Buenos Aires',
            'tipo' => 'iibb', 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B',
            'es_sistema' => true, 'activo' => true,
        ]);

        Livewire::test(CuitImpuestos::class)
            ->call('abrir', $cuit->id)
            ->assertSet('mostrarModal', true)
            ->assertSet('cuitId', $cuit->id)
            ->call('agregarImpuesto', $imp->id)
            ->assertCount('filas', 1);

        $this->assertDatabaseHas('cuit_impuesto_configs', [
            'cuit_id' => $cuit->id,
            'impuesto_id' => $imp->id,
            'inscripto' => 1,
        ], 'pymes_tenant');
    }

    public function test_cuit_impuestos_siembra_iva_por_defecto(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        foreach (['iva_debito' => 'debito_fiscal', 'iva_credito' => 'credito_fiscal'] as $codigo => $naturaleza) {
            \App\Models\Impuesto::create([
                'codigo' => $codigo, 'nombre' => $codigo, 'tipo' => 'iva',
                'naturaleza_default' => $naturaleza, 'jurisdiccion' => 'AR',
                'es_sistema' => true, 'activo' => true,
            ]);
        }

        Livewire::test(CuitImpuestos::class)
            ->call('abrir', $cuit->id)
            ->assertCount('filas', 2);

        // Marcador sin alícuota: el IVA real sale por artículo (21/10,5).
        $this->assertEquals(2, \App\Models\CuitImpuestoConfig::where('cuit_id', $cuit->id)->count());
        $this->assertEquals(2, \App\Models\CuitImpuestoConfig::where('cuit_id', $cuit->id)
            ->where('inscripto', true)->whereNull('alicuota')->count());
    }

    public function test_roles_permisos_monta(): void
    {
        Livewire::test(RolesPermisos::class)->assertOk();
    }

    public function test_usuarios_monta(): void
    {
        Livewire::test(Usuarios::class)->assertOk();
    }
}
