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
 * Fase 4 — cobro con QR de monto libre (qr_libre), end-to-end en NuevaVenta.
 *
 * A diferencia del QR dinámico/estático: NO se llama a Mercado Pago (el cliente
 * pone el monto en su app), se muestra la imagen del QR "Cobrar" subida en la
 * FormaPago, y la confirmación es manual (no hay polling de auto-confirmación).
 *
 * Ref: .claude/specs/integraciones-pago-qr-monto-libre.md (Fase 4).
 */
class CobroQrLibreTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    // URL root-relativa derivada del imagen_path (portable, igual que Articulo::imagenUrl).
    private const QR_IMG_URL = '/storage/integraciones/qr_libre/1/cobrar.webp';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        // Confirmación manual requiere permiso → usuario system admin (lo tiene todo).
        $this->actingAs(\App\Models\User::factory()->create(['is_system_admin' => true]));

        Caja::where('id', $this->cajaId)->update([
            'estado' => 'abierta',
            'fecha_apertura' => now(),
        ]);

        $integracion = IntegracionPago::firstOrCreate(
            ['codigo' => 'mercadopago_qr'],
            [
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico', 'qr_libre'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]
        );

        // qr_libre no exige token, pero sí que la integración exista/activa en la
        // sucursal (la transacción la referencia). Cargamos token igual (realista).
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

    private function crearFormaPagoQrLibre(bool $conImagen = true): FormaPago
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => 'WALLET'],
            ['nombre' => 'Billetera virtual', 'activo' => true, 'orden' => 5]
        );

        $fp = FormaPago::create([
            'nombre' => 'MP QR Libre',
            'codigo' => 'mp_qr_libre',
            'concepto' => 'wallet',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        $integracion = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $fp->integraciones()->attach($integracion->id, [
            'modo_default' => 'qr_libre',
            'modos_permitidos' => json_encode(['qr_libre']),
            'es_principal' => true,
            'config_qr_libre' => $conImagen
                ? json_encode(['imagen_path' => 'integraciones/qr_libre/1/cobrar.webp', 'imagen_url' => self::QR_IMG_URL])
                : null,
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

    private function desglose(FormaPago $fp, float $total): array
    {
        return [[
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
    }

    private function llamarProtegido(NuevaVenta $component, string $metodo): void
    {
        $ref = new \ReflectionMethod($component, $metodo);
        $ref->setAccessible(true);
        $ref->invoke($component);
    }

    public function test_cobro_qr_libre_muestra_la_imagen_sin_llamar_a_mp_y_confirma_manual(): void
    {
        // Si tocara MP, assertNothingSent fallaría.
        Http::fake();

        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto Libre', 'precio_base' => 100,
        ]);
        $fp = $this->crearFormaPagoQrLibre();

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto Libre', 100)];
        $component->calcularVenta();
        $total = (float) ($component->resultado['total_final'] ?? 0);
        $this->assertGreaterThan(0, $total);

        $component->formaPagoId = $fp->id;
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = $this->desglose($fp, $total);

        // --- Paso 1: iniciar cobro → muestra la imagen del QR, SIN venta ni API ---
        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');

        $this->assertTrue($component->mostrarModalEsperandoPago, 'Debe abrirse el modal de espera');
        $this->assertSame('qr_libre', $component->cobroIntegracionModo);
        $this->assertSame(self::QR_IMG_URL, $component->cobroIntegracionQrImagenUrl, 'Debe mostrar la imagen del QR subida');
        $this->assertNull($component->cobroIntegracionQrData, 'qr_libre no tiene trama EMVCo');
        $this->assertEquals(0, Venta::count(), 'No debe existir venta todavía');

        $txId = $component->cobroIntegracionTransaccionId;
        $tx = IntegracionPagoTransaccion::find($txId);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->estado);
        $this->assertNull($tx->external_id, 'No hay order en MP');

        // El polling es no-op para qr_libre (no rompe ni materializa).
        $component->pollearCobroIntegracion();
        $this->assertEquals(0, Venta::count(), 'El polling no debe materializar nada en qr_libre');
        $this->assertTrue($component->mostrarModalEsperandoPago, 'El modal sigue abierto esperando confirmación manual');

        // --- Paso 2: confirmación manual → materializa la venta ---
        $component->confirmarCobroIntegracionManual();

        $this->assertEquals(1, Venta::count(), 'Al confirmar manualmente debe crearse la venta');
        $venta = Venta::first();

        $tx->refresh();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO_MANUAL, $tx->estado);
        $this->assertEquals($venta->id, $tx->cobrable_id, 'La transacción debe asociarse a la venta');
        $this->assertFalse($component->mostrarModalEsperandoPago, 'El modal debe cerrarse');
        $this->assertNull($component->cobroIntegracionTransaccionId, 'El estado del cobro debe resetearse');

        // No se llamó a Mercado Pago en ningún momento.
        Http::assertNothingSent();

        // Auditoría: confirmación manual + asociación del cobrable.
        $eventos = IntegracionPagoEvento::where('transaccion_id', $txId)->pluck('evento')->all();
        $this->assertContains(IntegracionPagoEvento::EVENTO_CONFIRMADO_MANUAL, $eventos);
        $this->assertContains(IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO, $eventos);
    }

    public function test_cobro_qr_libre_lo_confirma_un_usuario_sin_permiso_de_override(): void
    {
        // qr_libre no tiene webhook: la confirmación manual ES el cobro, así que la
        // puede hacer quien opera la caja SIN el permiso integraciones_pago.confirmar_manual
        // (ese permiso solo restringe el override excepcional de los modos automáticos).
        $cajero = \App\Models\User::factory()->create(['is_system_admin' => false]);
        $this->actingAs($cajero);
        $this->assertFalse($cajero->hasPermissionTo('integraciones_pago.confirmar_manual'));

        Http::fake();

        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto Libre', 'precio_base' => 100,
        ]);
        $fp = $this->crearFormaPagoQrLibre();

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto Libre', 100)];
        $component->calcularVenta();
        $total = (float) ($component->resultado['total_final'] ?? 0);

        $component->formaPagoId = $fp->id;
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = $this->desglose($fp, $total);

        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');
        $this->assertTrue($component->mostrarModalEsperandoPago);

        // El cajero confirma manualmente: debe materializar la venta igual.
        $component->confirmarCobroIntegracionManual();

        $this->assertEquals(1, Venta::count(), 'El cajero sin permiso de override debe poder confirmar qr_libre');
        $tx = IntegracionPagoTransaccion::first();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO_MANUAL, $tx->estado);
        $this->assertFalse($component->mostrarModalEsperandoPago);
    }

    public function test_cobro_qr_libre_sin_imagen_configurada_no_abre_modal_ni_crea_transaccion(): void
    {
        Http::fake();

        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto Libre', 'precio_base' => 100,
        ]);
        $fp = $this->crearFormaPagoQrLibre(conImagen: false);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarrito($articulo->id, 'Producto Libre', 100)];
        $component->calcularVenta();
        $total = (float) ($component->resultado['total_final'] ?? 0);

        $component->formaPagoId = $fp->id;
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = $this->desglose($fp, $total);

        $this->llamarProtegido($component, 'verificarPuntoVentaYProcesar');

        $this->assertFalse($component->mostrarModalEsperandoPago, 'Sin imagen no debe abrirse el modal');
        $this->assertEquals(0, IntegracionPagoTransaccion::count(), 'No debe crearse transacción sin imagen');
        $this->assertEquals(0, Venta::count());
    }
}
