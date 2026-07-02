<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\DeliveryZona;
use App\Models\Sucursal;
use App\Services\Pedidos\CotizacionEnvio;
use App\Services\Pedidos\DeliveryEnvioService;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Matriz de cotización de envío (RF-06): sin geo / zona con horario y
 * prioridad / cálculo por km / fuera de alcance + calendario (RF-05) y
 * promesa automática (RF-15 core).
 */
class DeliveryEnvioServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected DeliveryEnvioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->service = new DeliveryEnvioService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function sucursalConConfig(array $config): Sucursal
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'latitud' => -34.6037000, // Obelisco
            'longitud' => -58.3816000,
            'config_delivery' => json_encode($config),
        ]);

        return Sucursal::find($this->sucursalId);
    }

    // ==================== COTIZACION ====================

    public function test_sin_coordenadas_devuelve_alcance_desconocido(): void
    {
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => true]);

        $cotizacion = $this->service->cotizar($sucursal, null, null);

        $this->assertSame(CotizacionEnvio::ALCANCE_DESCONOCIDO, $cotizacion->alcance);
        $this->assertNull($cotizacion->costo);
    }

    public function test_georreferenciacion_apagada_devuelve_desconocido_aunque_haya_coordenadas(): void
    {
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => false]);

        $cotizacion = $this->service->cotizar($sucursal, -34.61, -58.38);

        $this->assertSame(CotizacionEnvio::ALCANCE_DESCONOCIDO, $cotizacion->alcance);
    }

    public function test_calculo_por_km_con_base_y_km_incluidos(): void
    {
        // Criterio de aceptación: a ~5km con base $500 (2km incluidos) y
        // $200/km ⇒ envío $1.100.
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
            'costo_por_km_extra' => 200,
            'km_incluidos_en_base' => 2,
        ]);

        // ~5 km al sur del Obelisco (1° lat ≈ 111.19 km → 0.0449720 ≈ 5.0 km)
        $cotizacion = $this->service->cotizar($sucursal, -34.6486720, -58.3816000);

        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
        $this->assertEqualsWithDelta(5.0, $cotizacion->distanciaKm, 0.05);
        $this->assertEqualsWithDelta(1100.0, $cotizacion->costo, 15.0); // 500 + 3km×200
    }

    public function test_fuera_del_radio_devuelve_fuera_de_alcance(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
        ]);

        // ~15 km al sur
        $cotizacion = $this->service->cotizar($sucursal, -34.7386000, -58.3816000);

        $this->assertSame(CotizacionEnvio::ALCANCE_FUERA, $cotizacion->alcance);
        $this->assertNull($cotizacion->costo);
        $this->assertGreaterThan(10, $cotizacion->distanciaKm);
    }

    public function test_radio_null_es_sin_limite(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => null,
            'costo_envio_base' => 100,
        ]);

        $cotizacion = $this->service->cotizar($sucursal, -34.7386000, -58.3816000); // ~15km

        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
    }

    // ==================== ZONAS ====================

    public function test_zona_activa_pisa_el_calculo_por_km(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
            'costo_por_km_extra' => 200,
        ]);

        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Centro',
            'centro_lat' => -34.6037000,
            'centro_lng' => -58.3816000,
            'radio_km' => 3,
            'costo_envio' => 800,
            'orden' => 0,
            'activo' => true,
        ]);

        // ~2 km: dentro de la zona
        $cotizacion = $this->service->cotizar($sucursal, -34.6216888, -58.3816000);

        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
        $this->assertSame('Centro', $cotizacion->zona?->nombre);
        $this->assertEqualsWithDelta(800.0, $cotizacion->costo, 0.01);
    }

    public function test_zona_fuera_de_su_horario_cae_al_calculo_por_km(): void
    {
        // Criterio de aceptación: zona 3km $800 activa de 19 a 23:30 matchea
        // antes que el cálculo por km; fuera de su horario cae al cálculo.
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
            'costo_por_km_extra' => 200,
            'km_incluidos_en_base' => 2,
        ]);

        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Zona nocturna',
            'centro_lat' => -34.6037000,
            'centro_lng' => -58.3816000,
            'radio_km' => 3,
            'costo_envio' => 800,
            'rangos_horarios' => [['dias' => [1, 2, 3, 4, 5, 6, 7], 'desde' => '19:00', 'hasta' => '23:30']],
            'orden' => 0,
            'activo' => true,
        ]);

        $dentroDelHorario = Carbon::parse('2026-07-02 21:00:00');
        $fueraDelHorario = Carbon::parse('2026-07-02 15:00:00');
        $punto = [-34.6216888, -58.3816000]; // ~2 km

        $conZona = $this->service->cotizar($sucursal, $punto[0], $punto[1], $dentroDelHorario);
        $sinZona = $this->service->cotizar($sucursal, $punto[0], $punto[1], $fueraDelHorario);

        $this->assertSame('Zona nocturna', $conZona->zona?->nombre);
        $this->assertEqualsWithDelta(800.0, $conZona->costo, 0.01);

        $this->assertNull($sinZona->zona);
        $this->assertEqualsWithDelta(500.0, $sinZona->costo, 15.0); // 2km ≈ incluidos en base
    }

    public function test_zonas_matchean_por_orden_de_prioridad(): void
    {
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => true]);

        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Prioritaria',
            'centro_lat' => -34.6037000, 'centro_lng' => -58.3816000,
            'radio_km' => 5, 'costo_envio' => 600, 'orden' => 1, 'activo' => true,
        ]);
        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Secundaria',
            'centro_lat' => -34.6037000, 'centro_lng' => -58.3816000,
            'radio_km' => 5, 'costo_envio' => 900, 'orden' => 2, 'activo' => true,
        ]);

        $cotizacion = $this->service->cotizar($sucursal, -34.6216888, -58.3816000);

        $this->assertSame('Prioritaria', $cotizacion->zona?->nombre);
        $this->assertEqualsWithDelta(600.0, $cotizacion->costo, 0.01);
    }

    public function test_zona_inactiva_no_matchea(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'costo_envio_base' => 100,
        ]);

        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Apagada',
            'centro_lat' => -34.6037000, 'centro_lng' => -58.3816000,
            'radio_km' => 5, 'costo_envio' => 999, 'orden' => 0, 'activo' => false,
        ]);

        $cotizacion = $this->service->cotizar($sucursal, -34.6216888, -58.3816000);

        $this->assertNull($cotizacion->zona);
    }

    // ==================== PROMESA (RF-15 CORE) ====================

    public function test_demora_automatica_base_mas_minutos_por_km(): void
    {
        // Criterio de aceptación: base 15' + 4'/km a 5km ⇒ +35'.
        $config = array_merge(Sucursal::CONFIG_DELIVERY_DEFAULTS, [
            'modo_promesa' => 'automatica',
            'demora_base_min' => 15,
            'demora_min_por_km' => 4,
        ]);

        $this->assertSame(35, $this->service->estimarDemora($config, 5.0));
    }

    public function test_demora_null_en_modo_manual(): void
    {
        $config = array_merge(Sucursal::CONFIG_DELIVERY_DEFAULTS, ['modo_promesa' => 'manual']);

        $this->assertNull($this->service->estimarDemora($config, 5.0));
    }

    // ==================== CALENDARIO (RF-05) ====================

    public function test_calendario_respeta_dias_laborales_feriados_y_horarios(): void
    {
        $sucursal = $this->sucursalConConfig([
            'dias_laborales' => [1, 2, 3, 4, 5], // lunes a viernes
            'feriados' => ['2026-07-09'],
            'horarios_atencion' => [['dias' => [1, 2, 3, 4, 5], 'desde' => '19:00', 'hasta' => '23:30']],
        ]);

        // Jueves 2026-07-02 21:00 → abierto
        $this->assertTrue($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-02 21:00')));
        // Jueves 15:00 → fuera de horario
        $this->assertFalse($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-02 15:00')));
        // Sábado (no laboral)
        $this->assertFalse($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-04 21:00')));
        // Feriado (jueves 9/7)
        $this->assertFalse($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-09 21:00')));
    }

    public function test_horario_que_cruza_medianoche(): void
    {
        $sucursal = $this->sucursalConConfig([
            'horarios_atencion' => [['dias' => [1, 2, 3, 4, 5, 6, 7], 'desde' => '20:00', 'hasta' => '02:00']],
        ]);

        $this->assertTrue($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-02 23:00')));
        $this->assertTrue($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-02 01:30')));
        $this->assertFalse($this->service->estaAbierto($sucursal, Carbon::parse('2026-07-02 12:00')));
    }
}
