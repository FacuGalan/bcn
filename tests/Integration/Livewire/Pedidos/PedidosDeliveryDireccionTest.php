<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\PedidosDelivery;
use App\Models\DeliveryZona;
use App\Models\PedidoDelivery;
use App\Models\User;
use App\Services\Pedidos\PedidoDeliveryService;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Spec delivery-burbuja-y-mapa (RF-05/RF-06): corregir la dirección de un
 * pedido ya cargado desde el modal Ver, con recotización avisada — ningún
 * cambio de plata en silencio, el costo no se toca con cobros registrados y
 * los estados terminales no se editan.
 */
class PedidosDeliveryDireccionTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Bypass del cache de SucursalService (mismo patrón que SmokePedidosTest).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        $p = $ref->getProperty('sucursalIdsCache');
        $p->setAccessible(true);
        $p->setValue(null, [0]);

        // Sucursal en el Obelisco, georreferenciada, con una zona "Sur" a ~5km
        // (costo propio $1200) y costo por radio general de $500.
        $this->habilitarDelivery([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
        ]);
        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Sur',
            'centro_lat' => -34.65,
            'centro_lng' => -58.3816,
            'radio_km' => 0,
            'poligono' => [
                ['lat' => -34.67, 'lng' => -58.40],
                ['lat' => -34.67, 'lng' => -58.36],
                ['lat' => -34.63, 'lng' => -58.36],
                ['lat' => -34.63, 'lng' => -58.40],
            ],
            'costo_envio' => 1200,
            'orden' => 0,
            'activo' => true,
        ]);
        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Centro',
            'centro_lat' => -34.6037,
            'centro_lng' => -58.3816,
            'radio_km' => 0,
            'poligono' => [
                ['lat' => -34.62, 'lng' => -58.40],
                ['lat' => -34.62, 'lng' => -58.36],
                ['lat' => -34.59, 'lng' => -58.36],
                ['lat' => -34.59, 'lng' => -58.40],
            ],
            'costo_envio' => 500,
            'orden' => 1,
            'activo' => true,
        ]);

        $this->service = new PedidoDeliveryService;
        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /** Pedido confirmado, sin pagos, parado en zona Centro (zona/renglón reales). */
    private function pedidoEnCentro(): PedidoDelivery
    {
        $pedido = $this->pedidoDeliveryConfirmado(overrides: [
            'latitud' => -34.6037,
            'longitud' => -58.3816,
            'costo_envio' => 500,
        ]);

        // El alta real deja zona/distancia vía cotización; el helper crudo no.
        $this->service->recotizarEnvio($pedido);

        return $pedido->fresh(['zona', 'detalles']);
    }

    // ==================== SERVICE ====================

    public function test_previsualizar_detecta_cambio_de_zona_y_costo(): void
    {
        $pedido = $this->pedidoEnCentro();

        $preview = $this->service->previsualizarCorreccionDireccion($pedido, -34.65, -58.3816);

        $this->assertSame('ok', $preview['alcance']);
        $this->assertSame('Centro', $preview['zona_antes']);
        $this->assertSame('Sur', $preview['zona_despues']);
        $this->assertEqualsWithDelta(500.0, $preview['costo_antes'], 0.01);
        $this->assertEqualsWithDelta(1200.0, $preview['costo_despues'], 0.01);
    }

    public function test_previsualizar_sin_cambios_devuelve_null(): void
    {
        $pedido = $this->pedidoEnCentro();

        $this->assertNull($this->service->previsualizarCorreccionDireccion($pedido, -34.6037, -58.3816));
    }

    public function test_previsualizar_fuera_de_alcance(): void
    {
        $pedido = $this->pedidoEnCentro();

        $preview = $this->service->previsualizarCorreccionDireccion($pedido, -34.90, -58.50);

        $this->assertSame('fuera', $preview['alcance']);
        $this->assertEqualsWithDelta(500.0, $preview['costo_antes'], 0.01);
    }

    public function test_previsualizar_con_pagos_devuelve_null(): void
    {
        $pedido = $this->pedidoEnCentro();
        $pedido->update(['estado_pago' => PedidoDelivery::ESTADO_PAGO_PARCIAL]);

        $this->assertNull($this->service->previsualizarCorreccionDireccion($pedido->fresh(), -34.65, -58.3816));
    }

    public function test_corregir_actualiza_direccion_zona_costo_y_renglon(): void
    {
        $pedido = $this->pedidoEnCentro();

        $actualizado = $this->service->corregirDireccion($pedido, [
            'direccion' => 'Nueva Calle 123',
            'referencia' => 'PB',
            'latitud' => -34.65,
            'longitud' => -58.3816,
        ]);

        $this->assertSame('Nueva Calle 123', $actualizado->direccion_entrega);
        $this->assertSame('Sur', $actualizado->zona?->nombre);
        $this->assertEqualsWithDelta(1200.0, (float) $actualizado->costo_envio, 0.01);

        $renglon = $actualizado->detalles->firstWhere('es_costo_envio', true);
        $this->assertNotNull($renglon, 'El renglón-concepto de envío acompaña al costo');
        $this->assertEqualsWithDelta(1200.0, (float) $renglon->total, 0.01);
    }

    public function test_corregir_fuera_de_alcance_guarda_sin_zona_y_conserva_costo(): void
    {
        $pedido = $this->pedidoEnCentro();

        $actualizado = $this->service->corregirDireccion($pedido, [
            'direccion' => 'Lejos 999',
            'referencia' => null,
            'latitud' => -34.90,
            'longitud' => -58.50,
        ]);

        $this->assertSame('Lejos 999', $actualizado->direccion_entrega);
        $this->assertNull($actualizado->zona_id);
        $this->assertEqualsWithDelta(500.0, (float) $actualizado->costo_envio, 0.01, 'Fuera de alcance el costo no se pisa');
    }

    public function test_corregir_con_pagos_no_toca_el_costo(): void
    {
        $pedido = $this->pedidoEnCentro();
        $pedido->update(['estado_pago' => PedidoDelivery::ESTADO_PAGO_PARCIAL]);

        $actualizado = $this->service->corregirDireccion($pedido->fresh(), [
            'direccion' => 'Corregida 456',
            'referencia' => null,
            'latitud' => -34.65,
            'longitud' => -58.3816,
        ]);

        $this->assertSame('Corregida 456', $actualizado->direccion_entrega);
        $this->assertEqualsWithDelta(500.0, (float) $actualizado->costo_envio, 0.01, 'Con cobros el envío queda como estaba');
        $this->assertSame('Centro', $actualizado->zona?->nombre, 'La zona tampoco se recalcula');
    }

    public function test_corregir_en_estado_terminal_falla(): void
    {
        $pedido = $this->pedidoEnCentro();
        $pedido->update(['estado_pedido' => PedidoDelivery::ESTADO_ENTREGADO]);

        $this->expectExceptionMessage('ya es histórica');

        $this->service->corregirDireccion($pedido->fresh(), [
            'direccion' => 'X',
            'latitud' => -34.65,
            'longitud' => -58.3816,
        ]);
    }

    // ==================== COMPONENTE ====================

    public function test_abrir_editar_cierra_el_detalle_y_precarga_el_domicilio(): void
    {
        $pedido = $this->pedidoEnCentro();

        Livewire::test(PedidosDelivery::class)
            ->call('verDetalle', $pedido->id)
            ->assertSet('showDetalleModal', true)
            ->call('abrirEditarDireccion', $pedido->id)
            ->assertSet('showDireccionModal', true)
            ->assertSet('showDetalleModal', false)
            ->assertSet('domDireccion', 'Av. Siempreviva 742')
            ->assertSet('domReferencia', 'Timbre 3B');
    }

    public function test_guardar_con_delta_pide_confirmacion_y_confirmar_persiste_y_reabre_detalle(): void
    {
        $pedido = $this->pedidoEnCentro();

        $componente = Livewire::test(PedidosDelivery::class)
            ->call('abrirEditarDireccion', $pedido->id)
            ->set('domDireccion', 'Nueva Calle 123')
            ->call('setCoordenadasDesdeMapa', -34.65, -58.3816)
            ->call('guardarDireccion');

        $this->assertSame('ok', $componente->get('direccionPreview')['alcance'], 'El delta espera confirmación');
        $this->assertEqualsWithDelta(500.0, (float) $pedido->fresh()->costo_envio, 0.01, 'Nada se persistió todavía');

        $componente->call('confirmarGuardarDireccion')
            ->assertSet('showDireccionModal', false)
            ->assertSet('showDetalleModal', true)
            ->assertDispatched('toast-success');

        $pedido->refresh();
        $this->assertSame('Nueva Calle 123', $pedido->direccion_entrega);
        $this->assertEqualsWithDelta(1200.0, (float) $pedido->costo_envio, 0.01);
    }

    public function test_mover_el_pin_despues_del_delta_lo_invalida(): void
    {
        $pedido = $this->pedidoEnCentro();

        Livewire::test(PedidosDelivery::class)
            ->call('abrirEditarDireccion', $pedido->id)
            ->call('setCoordenadasDesdeMapa', -34.65, -58.3816)
            ->call('guardarDireccion')
            ->call('setCoordenadasDesdeMapa', -34.6037, -58.3816)
            ->assertSet('direccionPreview', null);
    }

    public function test_pedido_terminal_no_abre_el_modal(): void
    {
        $pedido = $this->pedidoEnCentro();
        $pedido->update(['estado_pedido' => PedidoDelivery::ESTADO_CANCELADO]);

        Livewire::test(PedidosDelivery::class)
            ->call('abrirEditarDireccion', $pedido->id)
            ->assertSet('showDireccionModal', false)
            ->assertDispatched('toast-error');
    }
}
