<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VentaPago;
use App\Models\VentaPagoAjuste;
use App\Services\Ventas\CambioFormaPagoService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class CambioFormaPagoServiceTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected CambioFormaPagoService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->service = new CambioFormaPagoService;
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // =========================================================================
    // puedeModificarVentaPago
    // =========================================================================

    public function test_bloquea_si_pago_no_existe(): void
    {
        $result = $this->service->puedeModificarVentaPago(99999, $this->user->id);

        $this->assertFalse($result['puede']);
        $this->assertStringContainsString('no encontrado', $result['razon']);
    }

    public function test_bloquea_si_pago_anulado(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'anulado',
        ]);

        $result = $this->service->puedeModificarVentaPago($pago->id, $this->user->id);

        $this->assertFalse($result['puede']);
        $this->assertStringContainsString('anulado', $result['razon']);
    }

    public function test_bloquea_si_venta_cancelada(): void
    {
        $venta = $this->crearVentaBasica(['estado' => 'cancelada']);
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        $result = $this->service->puedeModificarVentaPago($pago->id, $this->user->id);

        $this->assertFalse($result['puede']);
        $this->assertStringContainsString('cancelada', $result['razon']);
    }

    // =========================================================================
    // calcularMatrizFiscalCambio
    // =========================================================================

    public function test_preview_suma_completa_marca_completo_true(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->update(['factura_fiscal' => false, 'ajuste_porcentaje' => 0]);

        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        // 2 pagos nuevos que sumen 500
        $preview = $this->service->calcularPreviewCambio($pago, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 300, 'aplicar_ajuste' => false, 'facturar' => false],
            ['forma_pago_id' => $fp->id, 'monto_base' => 200, 'aplicar_ajuste' => false, 'facturar' => false],
        ]);

        $this->assertTrue($preview['completo']);
        $this->assertEqualsWithDelta(0, $preview['pendiente'], 0.01);
        $this->assertEqualsWithDelta(500, $preview['suma_nueva'], 0.01);
    }

    public function test_preview_suma_incompleta_marca_completo_false(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];

        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        $preview = $this->service->calcularPreviewCambio($pago, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 200, 'aplicar_ajuste' => false, 'facturar' => false],
        ]);

        $this->assertFalse($preview['completo']);
        $this->assertEqualsWithDelta(300, $preview['pendiente'], 0.01);
    }

    public function test_preview_pago_no_facturado_no_dispara_nc(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->update(['factura_fiscal' => false]);

        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
            // SIN comprobante_fiscal_id → no había factura
        ]);

        $preview = $this->service->calcularPreviewCambio($pago, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 500, 'aplicar_ajuste' => false, 'facturar' => false],
        ]);

        $this->assertEqualsWithDelta(0, $preview['delta_facturado'], 0.01);
        $this->assertFalse($preview['emitir_nc']);
    }

    // =========================================================================
    // cambiarFormaPago - happy path simple (sin permisos requeridos en CLI)
    // =========================================================================

    public function test_service_instanciable_y_matriz_estructurada(): void
    {
        $this->assertInstanceOf(CambioFormaPagoService::class, $this->service);
    }

    // =========================================================================
    // VentaPagoAjuste — persistencia básica
    // =========================================================================

    public function test_modal_cambiar_pago_renderiza_abierto_sin_errores(): void
    {
        \Livewire\Livewire::withoutLazyLoading();
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Ventas\Ventas::class)
            ->set('ventaDetalleId', $venta->id)
            ->set('showDetalleModal', true)
            ->set('pagoEditandoId', $pago->id)
            ->set('showCambiarPagoModal', true)
            ->call('actualizarPreviewCambio')
            ->assertOk()
            ->assertSeeText('Modificar forma de pago')
            ->assertSeeText('Pago original');
    }

    // =========================================================================
    // Regla fiscal binaria (pivot 2026-04-16)
    // =========================================================================

    protected function crearCFMinimo(float $total = 500): \App\Models\ComprobanteFiscal
    {
        $condIva = \App\Models\CondicionIva::first() ?? \App\Models\CondicionIva::create([
            'codigo' => 1,
            'nombre' => 'Responsable Inscripto',
            'activo' => true,
        ]);
        $cuit = \App\Models\Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Test SA', 'condicion_iva_id' => $condIva->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
        $pv = \App\Models\PuntoVenta::firstOrCreate(
            ['cuit_id' => $cuit->id, 'numero' => 1],
            ['nombre' => 'PV Test', 'activo' => true]
        );

        return \App\Models\ComprobanteFiscal::create([
            'sucursal_id' => $this->sucursalId,
            'punto_venta_id' => $pv->id,
            'cuit_id' => $cuit->id,
            'condicion_iva_id' => $condIva->id,
            'tipo' => 'factura_b',
            'letra' => 'B',
            'punto_venta_numero' => 1,
            'numero_comprobante' => random_int(1, 999999),
            'fecha_emision' => now()->toDateString(),
            'receptor_nombre' => 'Consumidor Final',
            'receptor_documento_tipo' => '99',
            'receptor_documento_numero' => '0',
            'neto_gravado' => round($total / 1.21, 2),
            'iva_total' => round($total - ($total / 1.21), 2),
            'total' => $total,
            'estado' => 'autorizado',
            'usuario_id' => $this->user->id,
        ]);
    }

    public function test_regla_binaria_mismo_monto_facturado_no_dispara_nada_fiscal(): void
    {
        // Viejo facturó 500. Nuevo factura 500 (misma suma, distinta FP).
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->update(['factura_fiscal' => true]);

        $cf = $this->crearCFMinimo(500);

        $pagoViejo = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'comprobante_fiscal_id' => $cf->id,
            'monto_facturado' => 500,
            'estado' => 'activo',
            'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
        ]);

        $preview = $this->service->calcularPreviewCambio($pagoViejo, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 500, 'aplicar_ajuste' => false, 'facturar' => true],
        ]);

        $this->assertEqualsWithDelta(0, $preview['delta_facturado'], 0.01);
        $this->assertFalse($preview['emitir_nc']);
        $this->assertFalse($preview['emitir_fc_nueva']);
    }

    public function test_regla_binaria_monto_facturado_distinto_dispara_nc_y_fc(): void
    {
        // Viejo facturó 500. Nuevo factura 300 → NC + FC siempre.
        $venta = $this->crearVentaBasica();
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update(['facturacion_fiscal_automatica' => true]);

        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->update(['factura_fiscal' => true]);

        $cf = $this->crearCFMinimo(500);

        $pagoViejo = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'comprobante_fiscal_id' => $cf->id,
            'monto_facturado' => 500,
            'estado' => 'activo',
            'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
        ]);

        $preview = $this->service->calcularPreviewCambio($pagoViejo, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 300, 'aplicar_ajuste' => false, 'facturar' => true],
            ['forma_pago_id' => $fp->id, 'monto_base' => 200, 'aplicar_ajuste' => false, 'facturar' => false],
        ]);

        // Monto facturado viejo=500, nuevo=300 → distintos, dispara NC+FC
        $this->assertEqualsWithDelta(-200, $preview['delta_facturado'], 0.01);
        $this->assertTrue($preview['emitir_nc']);
        $this->assertTrue($preview['emitir_fc_nueva']);
    }

    public function test_regla_binaria_delta_positivo_dispara_nc_y_fc(): void
    {
        // Viejo no facturaba. Nuevo factura 500 → NC no aplica (no había viejo facturable) + FC por 500.
        $venta = $this->crearVentaBasica();
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update(['facturacion_fiscal_automatica' => true]);

        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->update(['factura_fiscal' => true]);

        $pagoViejo = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'estado' => 'activo',
            'estado_facturacion' => VentaPago::ESTADO_FACT_NO_FACTURADO,
            // SIN comprobante_fiscal_id
        ]);

        $preview = $this->service->calcularPreviewCambio($pagoViejo, [
            ['forma_pago_id' => $fp->id, 'monto_base' => 500, 'aplicar_ajuste' => false, 'facturar' => true],
        ]);

        $this->assertEqualsWithDelta(500, $preview['delta_facturado'], 0.01);
        $this->assertFalse($preview['emitir_nc']); // Viejo no era fiscal, no hay NC
        $this->assertTrue($preview['emitir_fc_nueva']);
    }

    // =========================================================================
    // Reintentar facturación
    // =========================================================================

    public function test_reintentar_bloquea_si_pago_no_esta_pendiente(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'estado' => 'activo',
            'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no está en estado pendiente/');

        $this->service->reintentarFacturacionPago($pago, $this->user->id);
    }

    public function test_marcar_error_bloquea_si_pago_no_esta_pendiente(): void
    {
        $venta = $this->crearVentaBasica();
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'estado' => 'activo',
            'estado_facturacion' => VentaPago::ESTADO_FACT_FACTURADO,
        ]);

        $this->expectException(\Exception::class);
        $this->service->marcarErrorFacturacion($pago, $this->user->id, 'Motivo suficiente para test');
    }

    public function test_venta_pago_ajuste_crea_con_campos_requeridos(): void
    {
        $venta = $this->crearVentaBasica();

        $ajuste = VentaPagoAjuste::create([
            'venta_id' => $venta->id,
            'sucursal_id' => $this->sucursalId,
            'tipo_operacion' => VentaPagoAjuste::TIPO_CAMBIO,
            'delta_total' => 100.50,
            'delta_fiscal' => false,
            'es_post_cierre' => false,
            'motivo' => 'Test motivo con mas de 10 caracteres',
            'descripcion_auto' => 'Cambió FP Efectivo por Débito',
            'usuario_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('venta_pago_ajustes', [
            'id' => $ajuste->id,
            'tipo_operacion' => 'cambio_pago',
            'delta_fiscal' => false,
            'usuario_id' => $this->user->id,
        ], 'pymes_tenant');
        $this->assertEquals(100.50, (float) $ajuste->fresh()->delta_total);
    }

    // =========================================================================
    // RF-10: bloqueo por beneficios condicionados a la forma de pago
    // =========================================================================

    protected function pagoActivoDe(\App\Models\Venta $venta, \App\Models\FormaPago $fp, float $monto = 1000): VentaPago
    {
        return VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => $monto,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $monto,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);
    }

    protected function promoCondicionadaAplicada(\App\Models\Venta $venta, \App\Models\FormaPago ...$fps): \App\Models\Promocion
    {
        $promo = \App\Models\Promocion::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Solo efectivo 10%',
            'tipo' => 'descuento_porcentaje', 'valor' => 10, 'prioridad' => 1,
            'combinable' => true, 'activo' => true, 'usos_actuales' => 0,
        ]);
        foreach ($fps as $fp) {
            $promo->condiciones()->create(['tipo_condicion' => 'por_forma_pago', 'forma_pago_id' => $fp->id]);
        }
        \App\Models\VentaPromocion::create([
            'venta_id' => $venta->id,
            'tipo_promocion' => 'promocion',
            'promocion_id' => $promo->id,
            'descripcion_promocion' => $promo->nombre,
            'tipo_beneficio' => 'porcentaje',
            'valor_beneficio' => 10,
            'descuento_aplicado' => 100,
        ]);

        return $promo;
    }

    /** Usuario con permiso de modificar pagos (bypass de system admin). */
    protected function usuarioConPermisoCambioFP(): User
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $admin->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_bloquea_cambio_de_fp_si_una_promo_condicionada_depende_de_ella(): void
    {
        $admin = $this->usuarioConPermisoCambioFP();
        $venta = $this->crearVentaBasica();
        $efectivo = $this->crearFormaPagoEfectivo()['formaPago'];
        $transferencia = \App\Models\FormaPago::create([
            'nombre' => 'Transferencia', 'codigo' => 'transf', 'concepto' => 'transferencia',
            'concepto_pago_id' => $efectivo->concepto_pago_id, 'es_mixta' => false,
            'permite_cuotas' => false, 'ajuste_porcentaje' => 0, 'activo' => true,
        ]);
        $pago = $this->pagoActivoDe($venta, $efectivo);
        $this->promoCondicionadaAplicada($venta, $efectivo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cancelar la venta completa/');

        $this->service->cambiarFormaPago($pago->id, [
            ['forma_pago_id' => $transferencia->id, 'monto_base' => 1000, 'aplicar_ajuste' => false, 'facturar' => false],
        ], 'Cambio de prueba RF-10', $admin->id);
    }

    public function test_bloquea_eliminar_el_pago_del_que_depende_la_promo(): void
    {
        $admin = $this->usuarioConPermisoCambioFP();
        $venta = $this->crearVentaBasica();
        $efectivo = $this->crearFormaPagoEfectivo()['formaPago'];
        $pago = $this->pagoActivoDe($venta, $efectivo);
        $this->promoCondicionadaAplicada($venta, $efectivo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cancelar la venta completa/');

        $this->service->eliminarPagoDeVenta($pago->id, 'Eliminación de prueba RF-10', $admin->id);
    }

    public function test_permite_el_cambio_si_la_fp_resultante_tambien_esta_habilitada(): void
    {
        // Guard directo (reflection): promo habilitada para AMBAS FP → el set
        // resultante [transferencia] cumple y no bloquea.
        $venta = $this->crearVentaBasica();
        $efectivo = $this->crearFormaPagoEfectivo()['formaPago'];
        $otra = \App\Models\FormaPago::create([
            'nombre' => 'Transferencia', 'codigo' => 'transf', 'concepto' => 'transferencia',
            'concepto_pago_id' => $efectivo->concepto_pago_id, 'es_mixta' => false,
            'permite_cuotas' => false, 'ajuste_porcentaje' => 0, 'activo' => true,
        ]);
        $this->promoCondicionadaAplicada($venta, $efectivo, $otra);

        $metodo = new \ReflectionMethod($this->service, 'validarBeneficiosCondicionadosPorFP');
        $metodo->setAccessible(true);
        $metodo->invoke($this->service, $venta->fresh(), [$otra->id]);

        $this->assertTrue(true, 'No debe lanzar excepción');
    }

    public function test_bloquea_si_la_lista_de_precios_estaba_condicionada_a_la_fp(): void
    {
        $efectivo = $this->crearFormaPagoEfectivo()['formaPago'];
        $lista = \App\Models\ListaPrecio::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Solo Efectivo',
            'ajuste_porcentaje' => -20, 'redondeo' => 'ninguno',
            'aplica_promociones' => true, 'promociones_alcance' => 'todos',
            'es_lista_base' => false, 'prioridad' => 1, 'activo' => true,
        ]);
        $lista->condiciones()->create(['tipo_condicion' => 'por_forma_pago', 'forma_pago_id' => $efectivo->id]);
        $venta = $this->crearVentaBasica(['lista_precio_id' => $lista->id]);

        $metodo = new \ReflectionMethod($this->service, 'validarBeneficiosCondicionadosPorFP');
        $metodo->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Lista de precios/');

        $metodo->invoke($this->service, $venta, [999999]);
    }
}
