<?php

namespace Tests\Feature\Services;

use App\Models\PedidoMostrador;
use App\Models\Sucursal;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Multi-PWA Clase B — Fase 3b: numeración de display (turno). Cubre el fallback
 * al permanente, modo manual, reset diario por N horarios (segmentos, incl.
 * overnight) y reinicio manual.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-03b).
 */
class NumeracionDisplayTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function service(): PedidoMostradorService
    {
        return app(PedidoMostradorService::class);
    }

    private function configurar(array $attrs): void
    {
        Sucursal::find($this->sucursalId)->update($attrs);
    }

    private function siguiente(): ?int
    {
        return $this->service()->siguienteNumeroDisplay($this->sucursalId);
    }

    public function test_devuelve_null_si_la_sucursal_no_usa_display(): void
    {
        $this->assertNull($this->siguiente());
    }

    public function test_modo_manual_incrementa_sin_reset_automatico(): void
    {
        $this->configurar(['usa_numeracion_display' => true, 'numeracion_display_modo' => 'manual']);

        $this->assertSame(1, $this->siguiente());
        $this->assertSame(2, $this->siguiente());
        $this->assertSame(3, $this->siguiente());
    }

    public function test_reinicio_manual_vuelve_a_uno(): void
    {
        $this->configurar(['usa_numeracion_display' => true, 'numeracion_display_modo' => 'manual']);
        $this->siguiente();
        $this->siguiente();

        $this->service()->reiniciarNumeracionDisplay($this->sucursalId, 1);

        $this->assertSame(1, $this->siguiente());
    }

    public function test_modo_diario_resetea_en_cada_horario_y_respeta_overnight(): void
    {
        $this->configurar([
            'usa_numeracion_display' => true,
            'numeracion_display_modo' => 'diario',
            'numeracion_display_horas' => [6, 18],
        ]);

        // Turno mañana (segmento 06:00).
        Carbon::setTestNow(Carbon::parse('2026-06-26 07:00'));
        $this->assertSame(1, $this->siguiente());
        $this->assertSame(2, $this->siguiente());

        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00')); // mismo segmento
        $this->assertSame(3, $this->siguiente());

        // Turno tarde (segmento 18:00) → reset.
        Carbon::setTestNow(Carbon::parse('2026-06-26 18:30'));
        $this->assertSame(1, $this->siguiente());

        // Madrugada del día siguiente: sigue siendo el segmento 18:00 (overnight).
        Carbon::setTestNow(Carbon::parse('2026-06-27 05:00'));
        $this->assertSame(2, $this->siguiente());

        // Mañana siguiente (segmento 06:00 nuevo) → reset.
        Carbon::setTestNow(Carbon::parse('2026-06-27 06:30'));
        $this->assertSame(1, $this->siguiente());
    }

    public function test_default_horas_es_seis(): void
    {
        // usa display + diario sin horas configuradas → default [6].
        $this->configurar([
            'usa_numeracion_display' => true,
            'numeracion_display_modo' => 'diario',
            'numeracion_display_horas' => null,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-26 10:00'));
        $this->assertSame(1, $this->siguiente());

        Carbon::setTestNow(Carbon::parse('2026-06-27 05:00')); // antes de las 6 → mismo segmento
        $this->assertSame(2, $this->siguiente());

        Carbon::setTestNow(Carbon::parse('2026-06-27 06:30')); // cruzó las 6 → reset
        $this->assertSame(1, $this->siguiente());
    }

    public function test_numero_visible_prioriza_display_y_cae_al_permanente(): void
    {
        $conDisplay = new PedidoMostrador(['numero' => 1387, 'numero_display' => 5]);
        $sinDisplay = new PedidoMostrador(['numero' => 1387, 'numero_display' => null]);

        $this->assertSame(5, $conDisplay->numero_visible);
        $this->assertSame(1387, $sinDisplay->numero_visible);
    }
}
