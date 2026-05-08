<?php

namespace Tests\Integration\Models;

use App\Models\Moneda;
use App\Models\TipoCambio;
use Tests\TestCase;
use Tests\Traits\WithTenant;

/**
 * Tests del modelo TipoCambio. Foco principal:
 *
 *   - `obtenerTasaVentaConId()` devuelve el snapshot id+tasa correcto, lo que
 *     permite al flow de cobro persistir la cotización exacta vista en pantalla
 *     y evitar drift entre apertura del modal y persistencia (PR D — Repaso 1).
 *
 *   - La búsqueda toma siempre la última (por fecha DESC, id DESC) y funciona
 *     bidireccionalmente.
 */
class TipoCambioTest extends TestCase
{
    use WithTenant;

    protected Moneda $ars;

    protected Moneda $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->ars = Moneda::create([
            'codigo' => 'ARS',
            'nombre' => 'Peso Argentino',
            'simbolo' => '$',
            'es_principal' => true,
            'decimales' => 2,
            'activo' => true,
            'orden' => 1,
        ]);

        $this->usd = Moneda::create([
            'codigo' => 'USD',
            'nombre' => 'Dólar Estadounidense',
            'simbolo' => 'US$',
            'es_principal' => false,
            'decimales' => 2,
            'activo' => true,
            'orden' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_obtener_tasa_venta_con_id_devuelve_la_ultima_directa(): void
    {
        $hoy = now()->toDateString();

        $t1 = TipoCambio::create([
            'moneda_origen_id' => $this->usd->id,
            'moneda_destino_id' => $this->ars->id,
            'tasa_compra' => 990,
            'tasa_venta' => 1000,
            'fecha' => $hoy,
        ]);

        $snapshot = TipoCambio::obtenerTasaVentaConId($this->usd->id, $this->ars->id);

        $this->assertNotNull($snapshot);
        $this->assertEquals($t1->id, $snapshot['id']);
        $this->assertEquals(1000, $snapshot['tasa']);
    }

    public function test_obtener_tasa_venta_con_id_actualiza_al_registrar_nueva(): void
    {
        $hoy = now()->toDateString();

        $t1 = TipoCambio::create([
            'moneda_origen_id' => $this->usd->id,
            'moneda_destino_id' => $this->ars->id,
            'tasa_compra' => 990,
            'tasa_venta' => 1000,
            'fecha' => $hoy,
        ]);

        // Snapshot inicial → T1
        $snapshot1 = TipoCambio::obtenerTasaVentaConId($this->usd->id, $this->ars->id);
        $this->assertEquals($t1->id, $snapshot1['id']);

        // Se carga nueva cotización (mismo día, id mayor)
        $t2 = TipoCambio::create([
            'moneda_origen_id' => $this->usd->id,
            'moneda_destino_id' => $this->ars->id,
            'tasa_compra' => 1090,
            'tasa_venta' => 1100,
            'fecha' => $hoy,
        ]);

        // Nueva consulta → T2 (la última)
        $snapshot2 = TipoCambio::obtenerTasaVentaConId($this->usd->id, $this->ars->id);
        $this->assertEquals($t2->id, $snapshot2['id']);
        $this->assertEquals(1100, $snapshot2['tasa']);

        // Pero el snapshot1 sigue apuntando a T1 (id+tasa son inmutables al
        // momento de capturarse, eso es lo que evita drift al cobrar)
        $this->assertEquals($t1->id, $snapshot1['id']);
        $this->assertEquals(1000, $snapshot1['tasa']);
    }

    public function test_obtener_tasa_venta_con_id_busca_inversa_si_no_hay_directa(): void
    {
        $hoy = now()->toDateString();

        // Solo se carga la dirección ARS→USD (inversa)
        $tInversa = TipoCambio::create([
            'moneda_origen_id' => $this->ars->id,
            'moneda_destino_id' => $this->usd->id,
            'tasa_compra' => 0.0009,
            'tasa_venta' => 0.001,  // 1 ARS = 0.001 USD → invertido: 1 USD = 1000 ARS
            'fecha' => $hoy,
        ]);

        // Pidiendo USD→ARS, debe encontrar el inverso e invertir la tasa
        $snapshot = TipoCambio::obtenerTasaVentaConId($this->usd->id, $this->ars->id);

        $this->assertNotNull($snapshot);
        $this->assertEquals($tInversa->id, $snapshot['id']);
        $this->assertEquals(1000, $snapshot['tasa']);
    }

    public function test_obtener_tasa_venta_con_id_devuelve_null_si_no_hay_cotizacion(): void
    {
        $snapshot = TipoCambio::obtenerTasaVentaConId($this->usd->id, $this->ars->id);

        $this->assertNull($snapshot);
    }

    public function test_obtener_tasa_venta_legacy_sigue_funcionando(): void
    {
        $hoy = now()->toDateString();

        TipoCambio::create([
            'moneda_origen_id' => $this->usd->id,
            'moneda_destino_id' => $this->ars->id,
            'tasa_compra' => 1190,
            'tasa_venta' => 1200,
            'fecha' => $hoy,
        ]);

        // El método retro-compatible debe seguir devolviendo solo el float
        $tasa = TipoCambio::obtenerTasaVenta($this->usd->id, $this->ars->id);

        $this->assertEquals(1200, $tasa);
    }
}
