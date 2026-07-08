<?php

namespace Tests\Feature\Services;

use App\Events\Broadcasting\PedidoLlamadorPublicoBroadcast;
use App\Models\PantallaPublicaToken;
use App\Models\PedidoMostrador;
use App\Models\Sucursal;
use App\Services\PantallaPublicaService;
use App\Services\Pedidos\PedidoMostradorService;
use App\Services\TenantService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Multi-PWA Clase B — Fase 2: monitor llamador. Cubre el snapshot de columnas, el
 * payload mínimo (solo primer nombre), el endpoint público, el evento público en
 * canal `llamador.{token}` y su emisión al cambiar de estado.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-03, RF-04).
 */
class PantallaPublicaLlamadorTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        app(TenantService::class)->usarComercioParaProceso($this->comercio->id);
    }

    protected function tearDown(): void
    {
        PantallaPublicaToken::query()->where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearPedido(string $estado, int $numero, ?string $nombre): PedidoMostrador
    {
        return PedidoMostrador::create([
            'sucursal_id' => $this->sucursalId,
            'usuario_id' => 1,
            'fecha' => now(),
            'numero' => $numero,
            'estado_pedido' => $estado,
            'estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            'total_final' => 1000,
            'nombre_cliente_temporal' => $nombre,
            'identificador' => $nombre,
        ]);
    }

    private function token(): PantallaPublicaToken
    {
        return PantallaPublicaToken::create([
            'token' => PantallaPublicaToken::generarTokenUnico(),
            'codigo_corto' => PantallaPublicaToken::generarCodigoUnico(),
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);
    }

    public function test_snapshot_separa_columnas_y_expone_solo_primer_nombre(): void
    {
        $this->crearPedido(PedidoMostrador::ESTADO_EN_PREPARACION, 10, 'Ana Gómez');
        $this->crearPedido(PedidoMostrador::ESTADO_LISTO, 11, 'Juan Pérez');
        // Un entregado NO debe aparecer.
        $this->crearPedido(PedidoMostrador::ESTADO_ENTREGADO, 12, 'Otro');

        $snapshot = app(PantallaPublicaService::class)->pedidosParaLlamador(Sucursal::find($this->sucursalId));

        $this->assertCount(1, $snapshot['en_preparacion']);
        $this->assertCount(1, $snapshot['listo']);
        $this->assertSame(10, $snapshot['en_preparacion'][0]['numero']);
        $this->assertSame('Ana', $snapshot['en_preparacion'][0]['nombre'], 'Solo primer nombre, sin apellido');
        $this->assertSame('Juan', $snapshot['listo'][0]['nombre']);
    }

    public function test_endpoint_snapshot_devuelve_config_y_pedidos(): void
    {
        Sucursal::find($this->sucursalId)->update(['usa_llamador' => true]);
        $index = $this->token();
        $this->crearPedido(PedidoMostrador::ESTADO_LISTO, 20, 'Pedro');

        $this->get(route('clase-b.llamador.snapshot', ['token' => $index->token]))
            ->assertOk()
            ->assertJsonPath('activo', true)
            ->assertJsonStructure([
                'sucursal' => ['nombre'],
                'config' => ['titulo', 'color_listo', 'sonido'],
                'pedidos' => ['en_preparacion', 'listo'],
            ])
            ->assertJsonPath('pedidos.listo.0.numero', 20)
            ->assertJsonPath('pedidos.listo.0.nombre', 'Pedro');
    }

    public function test_endpoint_snapshot_inactivo_devuelve_activo_false(): void
    {
        Sucursal::find($this->sucursalId)->update(['usa_llamador' => false]);
        $index = $this->token();

        // No 404: la pantalla necesita la respuesta para mostrar el cartel de
        // "desactivado". Sin pedidos (no se computan con el llamador apagado).
        $this->get(route('clase-b.llamador.snapshot', ['token' => $index->token]))
            ->assertOk()
            ->assertJsonPath('activo', false)
            ->assertJsonPath('pedidos', []);
    }

    public function test_endpoint_snapshot_404_con_token_invalido(): void
    {
        $this->get(route('clase-b.llamador.snapshot', ['token' => 'no-existe']))
            ->assertNotFound();
    }

    public function test_evento_publico_usa_canal_por_token_y_payload_minimo(): void
    {
        $event = new PedidoLlamadorPublicoBroadcast('TOKENABC', 45, 'Juan', 'listo');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        // Canal PÚBLICO (sin prefijo private-).
        $this->assertSame('llamador.TOKENABC', $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame(45, $payload['numero']);
        $this->assertSame('Juan', $payload['nombre']);
        $this->assertSame('listo', $payload['estado']);
        $this->assertArrayHasKey('at', $payload);
    }

    public function test_cambiar_estado_a_listo_emite_evento_publico(): void
    {
        Sucursal::find($this->sucursalId)->update(['token_publico' => 'TOKDESUC', 'usa_llamador' => true]);
        $pedido = $this->crearPedido(PedidoMostrador::ESTADO_EN_PREPARACION, 30, 'Lucía Fernández');

        Event::fake([PedidoLlamadorPublicoBroadcast::class]);

        app(PedidoMostradorService::class)->cambiarEstado($pedido, PedidoMostrador::ESTADO_LISTO);

        Event::assertDispatched(PedidoLlamadorPublicoBroadcast::class, function ($e) {
            return $e->token === 'TOKDESUC'
                && $e->numero === 30
                && $e->nombre === 'Lucía'
                && $e->estado === 'listo';
        });
    }

    public function test_take_away_de_delivery_no_entra_al_llamador(): void
    {
        // rev9 delivery: el llamador es SOLO de mostrador — los take-away de
        // delivery tienen su propio circuito ("Para retirar") y numeración.
        Sucursal::find($this->sucursalId)->update(['token_publico' => 'TOKDLV', 'usa_llamador' => true]);

        $pedidoTa = \App\Models\PedidoDelivery::create([
            'sucursal_id' => $this->sucursalId,
            'tipo' => \App\Models\PedidoDelivery::TIPO_TAKE_AWAY,
            'usuario_id' => 1,
            'fecha' => now(),
            'numero' => 99,
            'estado_pedido' => \App\Models\PedidoDelivery::ESTADO_EN_PREPARACION,
            'estado_pago' => \App\Models\PedidoDelivery::ESTADO_PAGO_PENDIENTE,
            'total_final' => 500,
            'nombre_cliente_temporal' => 'Tomás',
        ]);

        // Ni en el snapshot…
        $snapshot = app(PantallaPublicaService::class)->pedidosParaLlamador(Sucursal::find($this->sucursalId));
        $this->assertCount(0, $snapshot['en_preparacion']);

        // …ni en el broadcast en vivo.
        Event::fake([PedidoLlamadorPublicoBroadcast::class]);
        app(\App\Services\Pedidos\PedidoDeliveryService::class)
            ->cambiarEstado($pedidoTa, \App\Models\PedidoDelivery::ESTADO_LISTO);
        Event::assertNotDispatched(PedidoLlamadorPublicoBroadcast::class);
    }

    public function test_sin_usa_llamador_no_emite_evento(): void
    {
        // Sucursal con token pero llamador DESACTIVADO: no debe publicarse nada
        // (evita el HTTP síncrono a Reverb en cada cambio de estado).
        Sucursal::find($this->sucursalId)->update(['token_publico' => 'TOKDESUC', 'usa_llamador' => false]);
        $pedido = $this->crearPedido(PedidoMostrador::ESTADO_EN_PREPARACION, 31, 'Mara');

        Event::fake([PedidoLlamadorPublicoBroadcast::class]);

        app(PedidoMostradorService::class)->cambiarEstado($pedido, PedidoMostrador::ESTADO_LISTO);

        Event::assertNotDispatched(PedidoLlamadorPublicoBroadcast::class);
    }
}
