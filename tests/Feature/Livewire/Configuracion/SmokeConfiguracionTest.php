<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\ConfiguracionEmpresa;
use App\Livewire\Configuracion\ConfiguracionTienda;
use App\Livewire\Configuracion\CuitDomicilios;
use App\Livewire\Configuracion\CuitImpuestos;
use App\Livewire\Configuracion\CuitPuntosVenta;
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

    public function test_integraciones_pago_modal_direccion_valida_campos_requeridos(): void
    {
        // El modal usa el trait ManejaDomicilio (picker). MP exige provincia,
        // localidad de catálogo, dirección y coordenadas: todas obligatorias acá.
        Livewire::test(IntegracionesPago::class)
            ->call('abrirModalDireccion')
            ->assertSet('mostrarModalDireccion', true)
            ->set('domProvincia', '')
            ->set('domLocalidadId', null)
            ->set('domDireccion', '')
            ->set('domLatitud', null)
            ->set('domLongitud', null)
            ->call('guardarDireccion')
            ->assertHasErrors(['domProvincia', 'domLocalidadId', 'domDireccion', 'domLatitud', 'domLongitud']);
    }

    public function test_integraciones_pago_guardar_direccion_sincroniza_localidad_catalogo(): void
    {
        $localidad = \App\Models\Localidad::query()->first();
        if (! $localidad) {
            $this->markTestSkipped('Catálogo de localidades no sembrado en config_test.');
        }
        $codigoProv = \App\Models\Provincia::query()->whereKey($localidad->provincia_id)->value('codigo');

        Livewire::test(IntegracionesPago::class)
            ->call('abrirModalDireccion')
            ->set('domProvincia', $codigoProv)
            ->set('domLocalidadId', $localidad->id)
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->set('domLatitud', '-34.6037')
            ->set('domLongitud', '-58.3816')
            ->call('guardarDireccion')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalDireccion', false);

        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $this->assertSame((int) $localidad->id, (int) $sucursal->localidad_id);
        // El string `localidad` se sincroniza con el nombre del catálogo (lo usa MP).
        $this->assertSame($localidad->nombre, $sucursal->localidad);
        $this->assertSame('Av. Siempreviva 742', $sucursal->direccion);
        $this->assertEquals(-34.6037, (float) $sucursal->latitud);
        $this->assertEquals(-58.3816, (float) $sucursal->longitud);
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

    public function test_cuit_impuestos_quitar_persiste(): void
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
        $config = \App\Models\CuitImpuestoConfig::create([
            'cuit_id' => $cuit->id, 'impuesto_id' => $imp->id, 'inscripto' => true,
            'origen_alicuota' => 'manual',
        ]);

        Livewire::test(CuitImpuestos::class)
            ->call('abrir', $cuit->id)
            ->call('quitarImpuesto', $config->id)
            ->assertCount('filas', 0);

        // El borrado debe persistir en la BD (regresión: comparación === string/int).
        $this->assertDatabaseMissing('cuit_impuesto_configs', ['id' => $config->id], 'pymes_tenant');
    }

    public function test_cuit_impuestos_abrir_no_resiembra_iva(): void
    {
        // Regresión (2026-06-16): abrir un CUIT vacío NO debe re-sembrar IVA
        // (antes re-creaba los impuestos que el usuario acababa de borrar).
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
            ->assertCount('filas', 0);

        $this->assertEquals(0, \App\Models\CuitImpuestoConfig::where('cuit_id', $cuit->id)->count());
    }

    public function test_cuit_impuestos_no_ofrece_iva_debito_credito_en_el_catalogo(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        \App\Models\Impuesto::create([
            'codigo' => 'iva_debito', 'nombre' => 'IVA Débito', 'tipo' => 'iva',
            'naturaleza_default' => 'debito_fiscal', 'jurisdiccion' => 'AR', 'es_sistema' => true, 'activo' => true,
        ]);
        $percIibb = \App\Models\Impuesto::create([
            'codigo' => 'perc_iibb_ar_b', 'nombre' => 'Percepción IIBB BA', 'tipo' => 'iibb',
            'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B', 'es_sistema' => true, 'activo' => true,
        ]);

        // El catálogo del combobox ofrece percepciones/retenciones pero NO el IVA débito/crédito.
        Livewire::test(CuitImpuestos::class)
            ->call('abrir', $cuit->id)
            ->set('buscarImpuesto', 'iva')
            ->assertViewHas('impuestosDisponibles', fn ($d) => $d->pluck('codigo')->doesntContain('iva_debito'))
            ->set('buscarImpuesto', 'IIBB')
            ->assertViewHas('impuestosDisponibles', fn ($d) => $d->pluck('codigo')->contains('perc_iibb_ar_b'));
    }

    public function test_cuit_domicilios_monta(): void
    {
        Livewire::test(CuitDomicilios::class)->assertOk();
    }

    public function test_set_coordenadas_desde_mapa_valida_rango(): void
    {
        // Bridge del picker de Google Maps (trait ManejaDomicilio): setea lat/lng
        // válidas e ignora fuera de rango / no numéricas, sin romper el form.
        Livewire::test(CuitDomicilios::class)
            ->call('setCoordenadasDesdeMapa', -34.6037, -58.3816)
            ->assertSet('domLatitud', '-34.6037')
            ->assertSet('domLongitud', '-58.3816')
            ->call('setCoordenadasDesdeMapa', 999, 'abc') // inválidas → no pisan
            ->assertSet('domLatitud', '-34.6037')
            ->assertSet('domLongitud', '-58.3816');
    }

    public function test_cuit_domicilios_abrir_y_guardar_domicilio(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );

        Livewire::test(CuitDomicilios::class)
            ->call('abrir', $cuit->id)
            ->assertSet('mostrarModal', true)
            ->assertSet('cuitId', $cuit->id)
            ->call('nuevoDomicilio')
            ->set('domProvincia', 'AR-B')
            ->set('domDireccion', 'Calle Falsa 123')
            ->call('guardarDomicilio')
            ->assertCount('domicilios', 1);

        // El primer domicilio queda como principal.
        $this->assertDatabaseHas('cuit_domicilios', [
            'cuit_id' => $cuit->id,
            'provincia' => 'AR-B',
            'direccion' => 'Calle Falsa 123',
            'es_principal' => 1,
        ], 'pymes_tenant');
    }

    public function test_cuit_domicilios_marcar_principal_y_eliminar(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        $d1 = \App\Models\CuitDomicilio::create([
            'cuit_id' => $cuit->id, 'tipo' => 'fiscal', 'provincia' => 'AR-B',
            'direccion' => 'Dom 1', 'es_principal' => true, 'activo' => true,
        ]);
        $d2 = \App\Models\CuitDomicilio::create([
            'cuit_id' => $cuit->id, 'tipo' => 'comercial', 'provincia' => 'AR-C',
            'direccion' => 'Dom 2', 'es_principal' => false, 'activo' => true,
        ]);

        Livewire::test(CuitDomicilios::class)
            ->call('abrir', $cuit->id)
            ->call('marcarPrincipal', $d2->id)
            ->call('confirmarEliminar', $d1->id)
            ->assertSet('confirmandoEliminarId', $d1->id)
            ->call('eliminarConfirmado')
            ->assertSet('confirmandoEliminarId', null)
            ->assertCount('domicilios', 1);

        $this->assertDatabaseHas('cuit_domicilios', ['id' => $d2->id, 'es_principal' => 1], 'pymes_tenant');
        $this->assertDatabaseMissing('cuit_domicilios', ['id' => $d1->id], 'pymes_tenant');
    }

    public function test_configuracion_empresa_tab_cuits_renderiza(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        \App\Models\CuitDomicilio::firstOrCreate(
            ['cuit_id' => $cuit->id, 'es_principal' => true],
            ['tipo' => 'fiscal', 'provincia' => 'AR-B', 'direccion' => 'Calle 1', 'activo' => true]
        );

        // Assert DINÁMICO: el numero_cuit 20111111113 se comparte con las
        // suites fiscales (razón social 'Emisor SA') y la tabla cuits PERSISTE
        // entre corridas (no entra al DELETE selectivo de WithTenant) → el
        // firstOrCreate devuelve el CUIT que haya quedado en la BD, con
        // cualquiera de las dos razones sociales. Assertear el literal
        // 'Test SA' hacía fallar el test según qué suite corrió primero.
        Livewire::test(ConfiguracionEmpresa::class)
            ->set('tabActivo', 'cuits')
            ->assertOk()
            ->assertSee($cuit->razon_social);
    }

    public function test_configuracion_empresa_editar_cuit_monta(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('editarCuit', $cuit->id)
            ->assertOk()
            ->assertSet('mostrarModalCuit', true);
    }

    public function test_configuracion_empresa_tab_se_sanea_si_es_invalido(): void
    {
        Livewire::withQueryParams(['tab' => 'inexistente'])
            ->test(ConfiguracionEmpresa::class)
            ->assertOk()
            ->assertSet('tabActivo', 'empresa');
    }

    public function test_cuit_puntos_venta_monta(): void
    {
        Livewire::test(CuitPuntosVenta::class)->assertOk();
    }

    public function test_cuit_puntos_venta_abrir_agregar_y_asignar_domicilio(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        \App\Models\PuntoVenta::withTrashed()->where('cuit_id', $cuit->id)->forceDelete();
        $dom = \App\Models\CuitDomicilio::create([
            'cuit_id' => $cuit->id, 'tipo' => 'fiscal', 'provincia' => 'AR-B',
            'direccion' => 'Av Siempre Viva 742', 'es_principal' => true, 'activo' => true,
        ]);

        $component = Livewire::test(CuitPuntosVenta::class)
            ->call('abrir', $cuit->id)
            ->assertSet('mostrarModal', true)
            ->set('nuevoPuntoVentaNumero', 1)
            ->call('agregarPuntoVenta')
            ->assertCount('puntosVenta', 1);

        $pv = \App\Models\PuntoVenta::where('cuit_id', $cuit->id)->where('numero', 1)->first();
        $component->call('actualizarDomicilioPv', $pv->id, $dom->id);

        $this->assertDatabaseHas('puntos_venta', [
            'id' => $pv->id,
            'cuit_domicilio_id' => $dom->id,
        ], 'pymes_tenant');
    }

    public function test_cuit_puntos_venta_confirmar_y_eliminar(): void
    {
        $condIva = \App\Models\CondicionIva::firstOrCreate(['codigo' => 1], ['nombre' => 'Responsable Inscripto']);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        \App\Models\PuntoVenta::withTrashed()->where('cuit_id', $cuit->id)->forceDelete();
        $pv = \App\Models\PuntoVenta::create(['cuit_id' => $cuit->id, 'numero' => 7, 'activo' => true]);

        Livewire::test(CuitPuntosVenta::class)
            ->call('abrir', $cuit->id)
            ->call('confirmarEliminar', $pv->id)
            ->assertSet('confirmandoEliminarId', $pv->id)
            ->call('eliminarConfirmado')
            ->assertSet('confirmandoEliminarId', null)
            ->assertCount('puntosVenta', 0);

        $this->assertSoftDeleted('puntos_venta', ['id' => $pv->id], 'pymes_tenant');
    }

    public function test_configuracion_empresa_editar_sucursal_guarda_domicilio_fisico(): void
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('editarSucursal', $sucursal->id)
            ->assertOk()
            ->assertSet('sucursalEditandoId', $sucursal->id)
            ->set('domProvincia', 'AR-B')
            ->set('domLatitud', '-34.6037')
            ->set('domLongitud', '-58.3816')
            ->call('guardarSucursal');

        $fresca = \App\Models\Sucursal::find($sucursal->id);
        $this->assertSame('AR-B', $fresca->provincia);
        $this->assertSame('-34.6037000', (string) $fresca->latitud);
    }

    public function test_configuracion_llamador_abre_asegura_token_y_guarda(): void
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirLlamador', $sucursal->id)
            ->assertOk()
            ->assertSet('mostrarModalLlamador', true)
            ->assertSet('llSucursalId', $sucursal->id)
            ->set('llUsaLlamador', true)
            ->set('llTitulo', 'Retiro')
            ->set('llColorListo', '#10b981')
            ->call('guardarLlamador')
            ->assertSet('mostrarModalLlamador', false);

        $fresca = \App\Models\Sucursal::find($sucursal->id);
        $this->assertTrue((bool) $fresca->usa_llamador);
        $this->assertSame('Retiro', $fresca->getConfigLlamador()['titulo']);
        $this->assertSame('#10b981', $fresca->getConfigLlamador()['color_listo']);
        // asegurarToken dejó token + código en el índice global (config).
        $this->assertNotNull($fresca->token_publico);
        $this->assertDatabaseHas('pantalla_publica_tokens', [
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $sucursal->id,
        ], 'config');
    }

    public function test_configuracion_llamador_regenera_token(): void
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        $component = Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirLlamador', $sucursal->id);

        $tokenViejo = $component->get('llToken');

        $component->call('regenerarTokenLlamador');

        $this->assertNotSame($tokenViejo, $component->get('llToken'));
        $this->assertSame($component->get('llToken'), \App\Models\Sucursal::find($sucursal->id)->token_publico);
    }

    public function test_configuracion_sucursal_numeracion_display_guarda_y_reinicia(): void
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirConfigSucursal', $sucursal->id)
            ->assertOk()
            ->set('configUsaNumeracionDisplay', true)
            ->set('configNumeracionDisplayModo', 'diario')
            ->set('configNumeracionDisplayHoras', [6])
            ->set('configNumeracionNuevaHora', 18)
            ->call('agregarHoraNumeracion')
            ->assertSet('configNumeracionDisplayHoras', [6, 18])
            ->call('quitarHoraNumeracion', 6)
            ->assertSet('configNumeracionDisplayHoras', [18])
            ->call('guardarConfigSucursal');

        $fresca = \App\Models\Sucursal::find($sucursal->id);
        $this->assertTrue((bool) $fresca->usa_numeracion_display);
        $this->assertSame('diario', $fresca->numeracion_display_modo);
        $this->assertSame([18], $fresca->horasResetDisplay());

        // Reinicio manual deja el contador en 0.
        \App\Models\Sucursal::where('id', $sucursal->id)->update(['pedido_display_ultimo_numero' => 7]);
        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirConfigSucursal', $sucursal->id)
            ->call('reiniciarNumeracionDisplay');
        $this->assertSame(0, (int) \App\Models\Sucursal::find($sucursal->id)->pedido_display_ultimo_numero);
    }

    public function test_configuracion_consultor_precios_abre_y_guarda(): void
    {
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);

        Livewire::test(ConfiguracionEmpresa::class)
            ->call('abrirConsultorPrecios', $sucursal->id)
            ->assertOk()
            ->assertSet('mostrarModalConsultor', true)
            ->assertSet('cpSucursalId', $sucursal->id)
            ->set('cpUsaConsultor', true)
            ->set('cpTitulo', 'Precios')
            ->set('cpColorAcento', '#0ea5e9')
            ->set('cpMensajeIdle', 'Pasá tu producto')
            ->set('cpDuracion', 8)
            ->call('guardarConsultorPrecios')
            ->assertSet('mostrarModalConsultor', false);

        $fresca = \App\Models\Sucursal::find($sucursal->id);
        $this->assertTrue((bool) $fresca->usa_consultor_precios);
        $config = $fresca->getConfigConsultorPrecios();
        $this->assertSame('Precios', $config['titulo']);
        $this->assertSame('#0ea5e9', $config['color_acento']);
        $this->assertSame('Pasá tu producto', $config['mensaje_idle']);
        $this->assertSame(8, $config['duracion_resultado']);
        $this->assertNotNull($fresca->token_publico);
    }

    public function test_configuracion_tienda_monta(): void
    {
        Livewire::test(ConfiguracionTienda::class)->assertOk();
    }

    public function test_configuracion_tienda_guardar(): void
    {
        // La tabla config.tiendas persiste entre corridas: limpiar lo del
        // comercio fixture. La CREACIÓN y `habilitada` son del PADRE
        // (RF-T11, ConfiguracionDeliveryTiendaTest); acá se crea directo.
        \App\Models\Tienda::where('comercio_id', $this->comercio->id)->delete();
        $tienda = \App\Models\Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'slug' => 'tienda-smoke-test',
            'habilitada' => false,
        ]);

        Livewire::test(ConfiguracionTienda::class)
            ->assertSet('tiendaId', $tienda->id)
            ->set('ga4MeasurementId', 'g-abc123')
            ->set('metaPixelId', '123456789012345')
            ->set('colorPrimario', '#FF0000')
            ->set('fuente', 'poppins')
            ->set('radios', 'lg')
            ->set('densidad', 'compacta')
            ->call('guardarTienda')
            ->assertHasNoErrors();

        $tienda->refresh();
        $this->assertFalse((bool) $tienda->habilitada, 'El hijo NO toca habilitada (único escritor: el padre, RF-T11)');
        $this->assertSame('G-ABC123', $tienda->ga4_measurement_id, 'GA4 se normaliza a mayúsculas');
        $this->assertSame('123456789012345', $tienda->meta_pixel_id);
        $this->assertSame('#ff0000', $tienda->tema['colores']['primario']);
        $this->assertSame('poppins', $tienda->tema['tipografia']['fuente']);
        $this->assertSame('lg', $tienda->tema['radios']);
        $this->assertSame('compacta', $tienda->tema['densidad']);

        \App\Models\Tienda::where('comercio_id', $this->comercio->id)->delete();
    }

    public function test_configuracion_tienda_valida_slug_duplicado_y_analytics(): void
    {
        \App\Models\Tienda::where('comercio_id', $this->comercio->id)->delete();

        // Tienda de OTRA sucursal que ya ocupa un slug (unique global, D15).
        \App\Models\Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId + 999,
            'slug' => 'slug-ocupado-test',
            'habilitada' => true,
        ]);

        \App\Models\Tienda::create([
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'slug' => 'tienda-valida-test',
            'habilitada' => false,
        ]);

        Livewire::test(ConfiguracionTienda::class)
            ->set('slug', 'slug-ocupado-test')
            ->call('guardarTienda')
            ->assertHasErrors(['slug'])
            ->set('slug', 'slug-libre-test')
            ->set('ga4MeasurementId', 'no-es-ga4')
            ->call('guardarTienda')
            ->assertHasErrors(['ga4MeasurementId'])
            ->set('ga4MeasurementId', '')
            ->set('metaPixelId', 'abc')
            ->call('guardarTienda')
            ->assertHasErrors(['metaPixelId']);

        \App\Models\Tienda::where('comercio_id', $this->comercio->id)->delete();
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
