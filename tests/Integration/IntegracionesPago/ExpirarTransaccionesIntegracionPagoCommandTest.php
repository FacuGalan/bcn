<?php

namespace Tests\Integration\IntegracionesPago;

use App\Events\IntegracionesPago\IntegracionPagoActualizado;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\TenantService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Test Fase 8 — comando programado de expiración (RF-16).
 *
 * Verifica el wiring multi-tenant: el comando recorre comercios, setea contexto
 * y delega en CobroIntegracionService::expirarPendientesVencidas().
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 8).
 */
class ExpirarTransaccionesIntegracionPagoCommandTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        if (! IntegracionPago::porCodigo('mercadopago_qr')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_qr', 'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class, 'activo' => true, 'orden' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearTransaccion(\Carbon\Carbon $expiraEn): IntegracionPagoTransaccion
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        $config = IntegracionPagoSucursal::firstOrCreate(
            ['integracion_pago_id' => $mpId, 'sucursal_id' => $this->sucursalId],
            ['modo' => 'test', 'access_token_test' => 'TOK', 'user_id_externo' => '999888777'],
        );
        $fp = FormaPago::firstOrCreate(
            ['codigo' => 'QR_CMD'],
            ['nombre' => 'QR Cmd', 'concepto' => 'wallet', 'activo' => true],
        );

        return IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $fp->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => 'qr_estatico',
            'monto' => 500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'expira_en' => $expiraEn,
        ]);
    }

    public function test_comando_expira_las_vencidas_y_deja_las_vigentes(): void
    {
        Event::fake([IntegracionPagoActualizado::class]);

        $vencida = $this->crearTransaccion(now()->subMinutes(2));
        $vigente = $this->crearTransaccion(now()->addMinutes(5));

        $this->artisan('integraciones-pago:expirar-pendientes')->assertSuccessful();

        // El comando pudo dejar el contexto tenant en otro comercio: re-fijarlo.
        app(TenantService::class)->setComercio($this->comercio);

        $this->assertSame(IntegracionPagoTransaccion::ESTADO_EXPIRADO, $vencida->fresh()->estado);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $vigente->fresh()->estado);
    }
}
