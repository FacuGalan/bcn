<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionEmpresa;
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
        $mp = \App\Models\IntegracionPago::porCodigo('mercadopago')->first();
        if (! $mp) {
            $mp = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago',
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
            ->assertSet('editMode', false);
    }

    public function test_integraciones_pago_probar_conexion_dispara_notify_success_con_credenciales_ok(): void
    {
        $mp = \App\Models\IntegracionPago::porCodigo('mercadopago')->first();
        if (! $mp) {
            $mp = \App\Models\IntegracionPago::create([
                'codigo' => 'mercadopago',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        $config = \App\Models\IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mp->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-LIVEWIRE-OK',
            'user_id_externo' => '123456',
        ]);

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

    public function test_roles_permisos_monta(): void
    {
        Livewire::test(RolesPermisos::class)->assertOk();
    }

    public function test_usuarios_monta(): void
    {
        Livewire::test(Usuarios::class)->assertOk();
    }
}
