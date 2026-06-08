<?php

namespace Tests\Feature\Livewire\Cajas;

use App\Livewire\Cajas\GestionCajas;
use App\Livewire\Cajas\HistorialTurnos;
use App\Livewire\Cajas\MovimientosManuales;
use App\Livewire\Cajas\TurnoActual;
use App\Models\Caja;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Componentes ya cubiertos: AjustesPostCierre, PagosPendientesFacturacion.
 */
class SmokeCajasTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();

        $user = User::factory()->create();
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
        ]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gestion_cajas_monta(): void
    {
        Livewire::test(GestionCajas::class)->assertOk();
    }

    public function test_historial_turnos_monta(): void
    {
        Livewire::test(HistorialTurnos::class)->assertOk();
    }

    public function test_movimientos_manuales_monta(): void
    {
        Livewire::test(MovimientosManuales::class)->assertOk();
    }

    public function test_turno_actual_monta(): void
    {
        Livewire::test(TurnoActual::class)->assertOk();
    }

    public function test_gestion_cajas_ver_terminal_point_abre_modal_con_el_terminal_id(): void
    {
        Caja::where('id', $this->cajaId)->update(['mp_point_terminal_id' => 'PAX_A910__SNCAJA']);

        Livewire::test(GestionCajas::class)
            ->call('verTerminalPoint', $this->cajaId)
            ->assertSet('showTerminalModal', true)
            ->assertSet('terminalPointInfo.terminal_id', 'PAX_A910__SNCAJA');
    }
}
