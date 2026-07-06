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

    // ==================== ZONAS (polígonos) ====================

    /**
     * Cuadrado de `$mitadLado` grados alrededor de un centro (≈ 0.02° ≈ 2.2km).
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    private function cuadrado(float $lat, float $lng, float $mitadLado = 0.02): array
    {
        return [
            ['lat' => $lat - $mitadLado, 'lng' => $lng - $mitadLado],
            ['lat' => $lat - $mitadLado, 'lng' => $lng + $mitadLado],
            ['lat' => $lat + $mitadLado, 'lng' => $lng + $mitadLado],
            ['lat' => $lat + $mitadLado, 'lng' => $lng - $mitadLado],
        ];
    }

    private function zonaPoligono(string $nombre, array $poligono, float $costo, array $extra = []): DeliveryZona
    {
        return DeliveryZona::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'nombre' => $nombre,
            'centro_lat' => $poligono[0]['lat'],
            'centro_lng' => $poligono[0]['lng'],
            'radio_km' => 0,
            'poligono' => $poligono,
            'costo_envio' => $costo,
            'orden' => 0,
            'activo' => true,
        ], $extra));
    }

    public function test_zona_poligono_pisa_el_calculo_por_km(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
            'costo_por_km_extra' => 200,
        ]);

        $this->zonaPoligono('Centro', $this->cuadrado(-34.6037, -58.3816), 800);

        $cotizacion = $this->service->cotizar($sucursal, -34.6037, -58.3816);

        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
        $this->assertSame('Centro', $cotizacion->zona?->nombre);
        $this->assertEqualsWithDelta(800.0, $cotizacion->costo, 0.01);
    }

    public function test_con_zonas_dibujadas_fuera_de_todas_es_fuera_de_alcance(): void
    {
        // Decisión 2026-07-06: con zonas dibujadas NO hay fallback por km —
        // lo que queda fuera de todos los polígonos es fuera de alcance
        // (forzable con permiso), aunque esté dentro del radio general.
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 50,
            'costo_envio_base' => 500,
        ]);

        $this->zonaPoligono('Centro', $this->cuadrado(-34.6037, -58.3816, 0.01), 800);

        // ~5 km al sur: dentro del radio general pero fuera del polígono.
        $cotizacion = $this->service->cotizar($sucursal, -34.6486720, -58.3816000);

        $this->assertSame(CotizacionEnvio::ALCANCE_FUERA, $cotizacion->alcance);
        $this->assertNull($cotizacion->costo);
    }

    public function test_zona_legacy_sin_poligono_no_participa_y_rige_el_radio_general(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 500,
            'km_incluidos_en_base' => 10,
        ]);

        // Zona v1 por radio (sin polígono): pendiente de redibujar.
        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'Legacy',
            'centro_lat' => -34.6037, 'centro_lng' => -58.3816,
            'radio_km' => 5, 'costo_envio' => 999, 'orden' => 0, 'activo' => true,
        ]);

        $cotizacion = $this->service->cotizar($sucursal, -34.6037, -58.3816);

        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
        $this->assertNull($cotizacion->zona, 'La zona sin polígono no debe matchear');
        $this->assertEqualsWithDelta(500.0, $cotizacion->costo, 0.01);
    }

    public function test_zonas_matchean_por_orden_de_prioridad(): void
    {
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => true]);

        $this->zonaPoligono('Prioritaria', $this->cuadrado(-34.6037, -58.3816), 600, ['orden' => 1]);
        $this->zonaPoligono('Secundaria', $this->cuadrado(-34.6037, -58.3816), 900, ['orden' => 2]);

        $cotizacion = $this->service->cotizar($sucursal, -34.6037, -58.3816);

        $this->assertSame('Prioritaria', $cotizacion->zona?->nombre);
        $this->assertEqualsWithDelta(600.0, $cotizacion->costo, 0.01);
    }

    public function test_zona_inactiva_no_matchea(): void
    {
        $sucursal = $this->sucursalConConfig([
            'georreferenciar_pedidos' => true,
            'costo_envio_base' => 100,
        ]);

        $this->zonaPoligono('Apagada', $this->cuadrado(-34.6037, -58.3816), 999, ['activo' => false]);

        $cotizacion = $this->service->cotizar($sucursal, -34.6037, -58.3816);

        $this->assertNull($cotizacion->zona);
        $this->assertSame(CotizacionEnvio::ALCANCE_OK, $cotizacion->alcance);
    }

    public function test_franja_de_costo_pisa_el_default_y_fuera_de_franja_rige_el_default(): void
    {
        // La zona está SIEMPRE disponible: las franjas solo cambian el costo
        // (más caro de noche); fuera de franja aplica el default.
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => true]);

        $this->zonaPoligono('Centro', $this->cuadrado(-34.6037, -58.3816), 800, [
            'rangos_horarios' => [
                ['dias' => [1, 2, 3, 4, 5, 6, 7], 'desde' => '20:00', 'hasta' => '23:30', 'costo' => 1500],
            ],
        ]);

        $deNoche = $this->service->cotizar($sucursal, -34.6037, -58.3816, cuando: Carbon::parse('2026-07-02 21:00'));
        $deDia = $this->service->cotizar($sucursal, -34.6037, -58.3816, cuando: Carbon::parse('2026-07-02 15:00'));

        $this->assertSame('Centro', $deNoche->zona?->nombre);
        $this->assertEqualsWithDelta(1500.0, $deNoche->costo, 0.01);

        $this->assertSame('Centro', $deDia->zona?->nombre, 'Fuera de franja la zona sigue disponible');
        $this->assertEqualsWithDelta(800.0, $deDia->costo, 0.01);
    }

    public function test_franja_de_costo_solo_ciertos_dias_y_cruce_de_medianoche(): void
    {
        $sucursal = $this->sucursalConConfig(['georreferenciar_pedidos' => true]);

        // Viernes 22:00–02:00 más caro: cubre también la madrugada del sábado.
        $this->zonaPoligono('Centro', $this->cuadrado(-34.6037, -58.3816), 800, [
            'rangos_horarios' => [
                ['dias' => [5], 'desde' => '22:00', 'hasta' => '02:00', 'costo' => 2000],
            ],
        ]);

        $viernesNoche = Carbon::parse('2026-07-03 23:00');   // viernes
        $sabadoMadrugada = Carbon::parse('2026-07-04 01:00'); // sábado 01:00 (jornada del viernes)
        $juevesNoche = Carbon::parse('2026-07-02 23:00');     // jueves: no aplica

        $this->assertEqualsWithDelta(2000.0, $this->service->cotizar($sucursal, -34.6037, -58.3816, cuando: $viernesNoche)->costo, 0.01);
        $this->assertEqualsWithDelta(2000.0, $this->service->cotizar($sucursal, -34.6037, -58.3816, cuando: $sabadoMadrugada)->costo, 0.01);
        $this->assertEqualsWithDelta(800.0, $this->service->cotizar($sucursal, -34.6037, -58.3816, cuando: $juevesNoche)->costo, 0.01);
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
