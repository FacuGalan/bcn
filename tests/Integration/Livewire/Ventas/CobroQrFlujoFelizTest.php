<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Caja;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\CuponService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\OpcionalService;
use App\Services\PuntosService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 5 — camino feliz del cobro QR dinámico, end-to-end con MercadoPago
 * fakeado (Http::fake).
 *
 * Verifica el NÚCLEO del que dependen las fases siguientes (webhook, manual,
 * mixtos): al cobrar con una FP con integración se inicia el QR (sin venta),
 * y cuando el polling detecta el pago aprobado se materializa la venta y se le
 * asocia la transacción. No pega a MP real; la integración real ya quedó
 * validada con una transacción de prueba en vivo.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 5 de 10).
 */
class CobroQrFlujoFelizTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    private const QR_DATA = '00020101021243650016com.mercadolibre0201306364TESTQR5204000053039865802AR6304ABCD';

    private const ORDER_ID = 'ORDTEST01ABC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        // El cobro registra usuario_iniciador_id = Auth::id() (NOT NULL).
        $this->actingAs(\App\Models\User::factory()->create());

        // La caja debe estar abierta y sincronizada con un POS de MP.
        Caja::where('id', $this->cajaId)->update([
            'estado' => 'abierta',
            'fecha_apertura' => now(),
            'mp_pos_id' => 'POS-1',
            'mp_pos_external_id' => 'EXT-POS-1',
        ]);

        // Catálogo de integración + config de la sucursal (modo test).
        $integracion = IntegracionPago::firstOrCreate(
            ['codigo' => 'mercadopago_qr'],
            [
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]
        );

        IntegracionPagoSucursal::create([
            'integracion_pago_id' => $integracion->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
            'user_id_externo' => '999888777',
            'activo' => true,
        ]);
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function prepararComponente(): NuevaVenta
    {
        $component = new NuevaVenta;
        $component->sucursalId = $this->sucursalId;
        $component->cajaSeleccionada = $this->cajaId;
        $component->boot(
            app(VentaService::class),
            app(OpcionalService::class),
            app(CuponService::class),
            app(PuntosService::class)
        );

        return $component;
    }

    private function crearFormaPagoConIntegracion(): FormaPago
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => 'WALLET'],
            ['nombre' => 'Billetera virtual', 'activo' => true, 'orden' => 5]
        );

        $fp = FormaPago::create([
            'nombre' => 'Mercado Pago QR',
            'codigo' => 'mp_qr',
            'concepto' => 'wallet',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        $integracion = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $fp->integraciones()->attach($integracion->id, [
            'modo_default' => 'qr_dinamico',
            'modos_permitidos' => json_encode(['qr_dinamico']),
            'es_principal' => true,
        ]);

        return $fp;
    }

    private function itemCarrito(int $articuloId, string $nombre, float $precio): array
    {
        return [
            'articulo_id' => $articuloId,
            'nombre' => $nombre,
            'codigo' => 'ART-'.$articuloId,
            'categoria_id' => null,
            'categoria_nombre' => null,
            'precio_base' => $precio,
            'precio' => $precio,
            'tiene_ajuste' => false,
            'cantidad' => 1,
            'iva_codigo' => 5,
            'iva_porcentaje' => 21.0,
            'iva_nombre' => 'IVA 21%',
            'precio_iva_incluido' => true,
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
            'puntos_canje' => null,
            'pagado_con_puntos' => false,
        ];
    }

    private function llamarProtegido(NuevaVenta $component, string $metodo): void
    {
        $ref = new \ReflectionMethod($component, $metodo);
        $ref->setAccessible(true);
        $ref->invoke($component);
    }

    public function test_flujo_feliz_qr_crea_venta_y_asocia_la_transaccion(): void
    {
        Http::fake([
            // GET estado de la orden → processed = aprobado
            '*/v1/orders/*' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'processed',
            ], 200),
            // POST crear orden → devuelve el QR
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto QR', 'precio_base' => 100,
        ]);

        $fp = $this->crearFormaPagoConIntegracion();

        // Sanity: la FP debe detectarse como integrada y resolver su integración.
        $this->assertTrue(FormaPago::find($fp->id)->tieneIntegracion(), 'La FP debe tener integración');
        $this->assertNotNull(FormaPago::find($fp->id)->integracionPrincipal(), 'Debe resolver integración principal');

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto QR', 100)];
        $component->calcularVenta();

        $total = (float) ($component->resultado['total_final'] ?? 0);
        $this->assertGreaterThan(0, $total, 'El carrito debe tener un total > 0');

        // Desglose con un único pago a la FP con integración (QR), monto completo.
        $component->formaPagoId = $fp->id;
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = [[
            'forma_pago_id' => $fp->id,
            'nombre' => $fp->nombre,
            'concepto_pago_id' => $fp->concepto_pago_id,
            'monto_base' => $total,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $total,
            'monto_recibido' => $total,
            'vuelto' => 0,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'factura_fiscal' => false,
            'es_moneda_extranjera' => false,
            'moneda_id' => null,
            'tipo_cambio_id' => null,
            'tipo_cambio_tasa' => null,
            'monto_moneda_original' => null,
        ]];

        // --- Paso 1: iniciar cobro → debe abrir el QR, SIN crear la venta ---
        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');

        $this->assertTrue($component->mostrarModalEsperandoPago, 'Debe abrirse el modal de espera');
        $this->assertNotNull($component->cobroIntegracionTransaccionId, 'Debe haber una transacción en curso');
        $this->assertSame(self::QR_DATA, $component->cobroIntegracionQrData, 'Debe tener el QR del gateway');
        $this->assertNotNull($component->cobroIntegracionQrSvg, 'Debe haberse renderizado el SVG del QR');
        $this->assertEquals(0, Venta::count(), 'NO debe existir la venta todavía (cobro primero)');

        $txId = $component->cobroIntegracionTransaccionId;
        $tx = IntegracionPagoTransaccion::find($txId);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->estado);
        $this->assertNull($tx->cobrable_id, 'La transacción nace sin cobrable');

        // --- Paso 2: polling detecta pago aprobado → materializa la venta ---
        $component->pollearCobroIntegracion();

        $this->assertEquals(1, Venta::count(), 'Debe crearse exactamente una venta al confirmar el pago');
        $venta = Venta::first();

        $tx->refresh();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado, 'La transacción debe quedar confirmada');
        $this->assertEquals($venta->id, $tx->cobrable_id, 'La transacción debe asociarse a la venta creada');
        $this->assertStringContainsString('Venta', (string) $tx->cobrable_type, 'cobrable_type debe apuntar a Venta');

        // Estado del componente limpio tras el cobro.
        $this->assertFalse($component->mostrarModalEsperandoPago, 'El modal debe cerrarse');
        $this->assertNull($component->cobroIntegracionTransaccionId, 'El estado del cobro debe resetearse');

        // Auditoría: eventos creado → iniciado_en_gateway → confirmado → cobrable_asociado
        $eventos = IntegracionPagoEvento::where('transaccion_id', $txId)->pluck('evento')->all();
        $this->assertContains(IntegracionPagoEvento::EVENTO_CONFIRMADO, $eventos);
        $this->assertContains(IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO, $eventos);
    }

    public function test_si_la_facturacion_falla_con_cobro_qr_confirmado_la_venta_se_registra_pendiente(): void
    {
        Http::fake([
            '*/v1/orders/*' => Http::response(['id' => self::ORDER_ID, 'status' => 'processed'], 200),
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto QR', 'precio_base' => 100,
        ]);
        $fp = $this->crearFormaPagoConIntegracion();

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto QR', 100)];
        $component->calcularVenta();
        $total = (float) ($component->resultado['total_final'] ?? 0);

        // Forzar facturación: la caja del test no tiene punto de venta/CUIT, así que
        // crearComprobanteFiscal va a fallar → el catch debe registrar igual la venta.
        $component->emitirFacturaFiscal = true;
        $component->formaPagoId = $fp->id;
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = [[
            'forma_pago_id' => $fp->id,
            'nombre' => $fp->nombre,
            'concepto_pago_id' => $fp->concepto_pago_id,
            'monto_base' => $total,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $total,
            'monto_recibido' => $total,
            'vuelto' => 0,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'factura_fiscal' => true,
            'es_moneda_extranjera' => false,
            'moneda_id' => null,
            'tipo_cambio_id' => null,
            'tipo_cambio_tasa' => null,
            'monto_moneda_original' => null,
        ]];

        // Paso 1: iniciar cobro (QR), sin venta todavía.
        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');
        $this->assertTrue($component->mostrarModalEsperandoPago);
        $txId = $component->cobroIntegracionTransaccionId;
        $this->assertEquals(0, Venta::count());

        // Paso 2: pago aprobado → la FC falla, pero la venta DEBE quedar registrada.
        $component->pollearCobroIntegracion();

        $this->assertEquals(1, Venta::count(), 'La venta debe registrarse aunque la facturación falle (el cobro ya entró)');
        $venta = Venta::first();

        // El pago queda en pendiente_de_facturar (reintentable desde Cajas).
        $pago = VentaPago::where('venta_id', $venta->id)->first();
        $this->assertEquals(VentaPago::ESTADO_FACT_PENDIENTE, $pago->estado_facturacion, 'El pago debe quedar pendiente de facturar');

        // No se emitió comprobante fiscal.
        $this->assertEquals(0, $venta->comprobantesFiscales()->count(), 'No debe haber comprobante fiscal');

        // El cobro QR quedó confirmado y asociado a la venta.
        $tx = IntegracionPagoTransaccion::find($txId);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado);
        $this->assertEquals($venta->id, $tx->cobrable_id);
    }
}
