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
use App\Services\Ventas\CambioFormaPagoService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 9 — pagos mixtos con integración (QR MP) + trazabilidad + bloqueo.
 *
 * Cubre los 3 entregables de la fase:
 *  1. Pago mixto (efectivo + QR) end-to-end: al confirmar el QR se materializan
 *     TODOS los pagos del desglose y el venta_pago del QR queda vinculado a su
 *     IntegracionPagoTransaccion (columna integracion_pago_transaccion_id).
 *  2. Bloqueo de anulación: una venta con cobro de integración confirmado no se
 *     puede cancelar (no hay refund real; la plata ya entró al proveedor).
 *  3. Bloqueo de modificación: el venta_pago cobrado por integración no se puede
 *     cambiar ni eliminar desde el cambio de forma de pago.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 9 de 10).
 */
class CobroQrPagoMixtoTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    private const QR_DATA = '00020101021243650016com.mercadolibre0201306364TESTQR5204000053039865802AR6304ABCD';

    private const ORDER_ID = 'ORDMIXTO01XY';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->actingAs(\App\Models\User::factory()->create());

        Caja::where('id', $this->cajaId)->update([
            'estado' => 'abierta',
            'fecha_apertura' => now(),
            'mp_pos_id' => 'POS-1',
            'mp_pos_external_id' => 'EXT-POS-1',
        ]);

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

    private function pagoDesglose(FormaPago $fp, float $monto, array $overrides = []): array
    {
        return array_merge([
            'forma_pago_id' => $fp->id,
            'nombre' => $fp->nombre,
            'concepto_pago_id' => $fp->concepto_pago_id,
            'monto_base' => $monto,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $monto,
            'monto_recibido' => $monto,
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
        ], $overrides);
    }

    private function llamarProtegido(NuevaVenta $component, string $metodo): void
    {
        $ref = new \ReflectionMethod($component, $metodo);
        $ref->setAccessible(true);
        $ref->invoke($component);
    }

    public function test_pago_mixto_efectivo_mas_qr_vincula_la_transaccion_al_pago_correcto(): void
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
            'nombre' => 'Producto Mixto', 'precio_base' => 100,
        ]);

        $efectivo = $this->crearFormaPagoEfectivo()['formaPago'];
        $fpQr = $this->crearFormaPagoConIntegracion();

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto Mixto', 100)];
        $component->calcularVenta();
        $total = (float) ($component->resultado['total_final'] ?? 0);
        $this->assertGreaterThan(0, $total);

        $montoEfectivo = 40.0;
        $montoQr = round($total - $montoEfectivo, 2);

        // Desglose mixto: parte efectivo (afecta caja) + parte QR (integración).
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = [
            $this->pagoDesglose($efectivo, $montoEfectivo, [
                'afecta_caja' => true,
                'monto_recibido' => $montoEfectivo,
            ]),
            $this->pagoDesglose($fpQr, $montoQr),
        ];

        // Paso 1: iniciar → debe abrir el QR por la porción de la integración, sin venta.
        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');

        $this->assertTrue($component->mostrarModalEsperandoPago, 'Debe abrirse el modal de espera');
        $this->assertEquals(0, Venta::count(), 'No debe existir la venta todavía');
        $txId = $component->cobroIntegracionTransaccionId;
        $this->assertNotNull($txId);
        $tx = IntegracionPagoTransaccion::find($txId);
        // El cobro QR es por la PORCIÓN de la integración, no por el total.
        $this->assertEquals($montoQr, (float) $tx->monto, 'El cobro QR debe ser por la porción de la integración');

        // Paso 2: polling confirma → materializa la venta con AMBOS pagos.
        $component->pollearCobroIntegracion();

        $this->assertEquals(1, Venta::count());
        $venta = Venta::first();
        $pagos = VentaPago::where('venta_id', $venta->id)->get();
        $this->assertCount(2, $pagos, 'Deben persistirse los dos pagos del desglose');

        $pagoQr = $pagos->firstWhere('forma_pago_id', $fpQr->id);
        $pagoEfectivo = $pagos->firstWhere('forma_pago_id', $efectivo->id);

        // Trazabilidad (Fase 9): SOLO el pago QR queda vinculado a la transacción.
        $this->assertEquals($txId, $pagoQr->integracion_pago_transaccion_id, 'El pago QR debe vincularse a su transacción');
        $this->assertNull($pagoEfectivo->integracion_pago_transaccion_id, 'El pago en efectivo NO debe vincularse a la integración');

        // El helper de venta y de pago detectan la integración confirmada.
        $this->assertTrue($venta->fresh()->tieneIntegracionPagoConfirmada());
        $this->assertTrue($pagoQr->fresh()->tieneIntegracionConfirmada());
        $this->assertFalse($pagoEfectivo->fresh()->tieneIntegracionConfirmada());
    }

    public function test_no_se_puede_anular_una_venta_con_cobro_de_integracion_confirmado(): void
    {
        $venta = $this->crearVentaWithCobroIntegracionConfirmado();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cobro por integración');

        app(VentaService::class)->cancelarVentaCompleta($venta->id);
    }

    public function test_anular_falla_sin_dejar_la_venta_cancelada(): void
    {
        $venta = $this->crearVentaWithCobroIntegracionConfirmado();

        try {
            app(VentaService::class)->cancelarVentaCompleta($venta->id);
            $this->fail('Debió lanzar excepción');
        } catch (\Exception $e) {
            // esperado
        }

        $this->assertFalse($venta->fresh()->estaCancelada(), 'La venta NO debe quedar cancelada tras el bloqueo');
    }

    public function test_no_se_puede_modificar_el_pago_cobrado_por_integracion(): void
    {
        $venta = $this->crearVentaWithCobroIntegracionConfirmado();
        $pagoQr = VentaPago::where('venta_id', $venta->id)
            ->whereNotNull('integracion_pago_transaccion_id')
            ->first();

        $resultado = app(CambioFormaPagoService::class)
            ->puedeModificarVentaPago($pagoQr->id, \Illuminate\Support\Facades\Auth::id());

        $this->assertFalse($resultado['puede'], 'No debe poder modificarse el pago de integración confirmado');
        $this->assertStringContainsString('integración', (string) $resultado['razon']);
    }

    /**
     * Arma una venta materializada con un pago en efectivo + un pago cobrado por
     * una integración (QR) ya confirmada y vinculada vía
     * integracion_pago_transaccion_id, sin pasar por todo el flujo de cobro.
     */
    private function crearVentaWithCobroIntegracionConfirmado(): Venta
    {
        $efectivo = $this->crearFormaPagoEfectivo();
        $fpQr = $this->crearFormaPagoConIntegracion();

        $config = IntegracionPagoSucursal::where('sucursal_id', $this->sucursalId)->first();

        $venta = $this->crearVentaBasica([
            'subtotal' => 100,
            'total' => 100,
            'total_final' => 100,
        ]);

        $tx = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $fpQr->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
            'usuario_iniciador_id' => \Illuminate\Support\Facades\Auth::id(),
            'modo_usado' => 'qr_dinamico',
            'monto' => 60,
            'external_id' => self::ORDER_ID,
            'estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
            'confirmado_en' => now(),
            'cobrable_type' => 'Venta',
            'cobrable_id' => $venta->id,
        ]);

        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $efectivo['formaPago']->id,
            'concepto_pago_id' => $efectivo['concepto']->id,
            'monto_base' => 40,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 40,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fpQr->id,
            'concepto_pago_id' => $fpQr->concepto_pago_id,
            'monto_base' => 60,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 60,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
            'integracion_pago_transaccion_id' => $tx->id,
        ]);

        return $venta;
    }
}
