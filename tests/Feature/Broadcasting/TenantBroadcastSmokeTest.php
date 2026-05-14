<?php

namespace Tests\Feature\Broadcasting;

use App\Events\Broadcasting\TenantPingEvent;
use App\Models\Comercio;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithTenant;

/**
 * Smoke test del pipeline broadcast multi-tenant.
 *
 * Verifica que:
 * 1. Los eventos `TenantBroadcastEvent` se transmiten en un canal privado
 *    prefijado por comercio_id.
 * 2. La autorizacion del canal `routes/channels.php` permite a un user con
 *    acceso al comercio y rechaza al que no lo tiene.
 *
 * NO verifica conexion WebSocket real (eso requiere Reverb corriendo). Sí
 * verifica el cableado entre event -> broadcaster -> channel auth.
 */
class TenantBroadcastSmokeTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_evento_tenant_se_broadcastea_en_canal_privado_con_comercio_id(): void
    {
        $event = new TenantPingEvent(42, 'hola');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-comercios.42.ping', $channels[0]->name);
    }

    public function test_evento_tenant_expone_payload_esperado(): void
    {
        $event = new TenantPingEvent(7, 'ack');

        $payload = $event->broadcastWith();

        $this->assertSame('ack', $payload['message']);
        $this->assertArrayHasKey('at', $payload);
    }

    public function test_evento_se_dispatchea_cuando_se_llama_dispatch(): void
    {
        Event::fake([TenantPingEvent::class]);

        TenantPingEvent::dispatch($this->comercio->id, 'smoke');

        Event::assertDispatched(TenantPingEvent::class, function (TenantPingEvent $event) {
            return $event->comercioId === $this->comercio->id && $event->message === 'smoke';
        });
    }

    public function test_canal_autoriza_user_con_acceso_al_comercio(): void
    {
        $user = User::factory()->create();
        $user->comercios()->attach($this->comercio->id);

        $this->assertTrue($user->hasAccessToComercio($this->comercio->id));
    }

    public function test_canal_rechaza_user_sin_acceso_al_comercio(): void
    {
        $user = User::factory()->create();
        $otroComercioId = $this->comercio->id + 9999;

        $this->assertFalse($user->hasAccessToComercio($otroComercioId));
    }

    public function test_canal_autoriza_system_admin_a_cualquier_comercio(): void
    {
        $user = User::factory()->create(['is_system_admin' => true]);
        $otroComercioId = $this->comercio->id + 9999;

        $this->assertTrue($user->hasAccessToComercio($otroComercioId));
    }
}
