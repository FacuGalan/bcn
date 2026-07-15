<?php

namespace Tests\Unit\Services;

use App\Services\VentaService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * RF-V8 (hardening fiscal saliente, tanda 2): cortesía total con concepto libre.
 *
 * Reproduce el path legacy de NuevaVenta::procesarVenta (único caller de
 * crearVenta SIN `_usar_totales_proporcionados`, usado por la cortesía total vía
 * confirmarInvitacionTotal): con un concepto libre en el carrito, el modo legacy
 * lanzaba "Concepto libre requiere _usar_totales_proporcionados=true" y la
 * cortesía no se podía confirmar. Un concepto siempre trae sus datos desde la
 * UI, así que usa la rama de datos proporcionados aunque la venta sea legacy.
 */
class VentaServiceCortesiaConceptoTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    private int $cajaAbiertaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->cajaAbiertaId = $this->crearCajaAbierta($this->sucursalId)->id;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_cortesia_total_con_concepto_libre_crea_la_venta(): void
    {
        $venta = (new VentaService)->crearVenta(
            [
                'sucursal_id' => $this->sucursalId,
                'usuario_id' => 1,
                'caja_id' => $this->cajaAbiertaId,
                'numero' => '0001-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
                'fecha' => now(),
                'total' => 0,
                'es_invitacion_total' => true,
                'invitacion_motivo' => 'Cortesía de la casa',
                'invitado_por_usuario_id' => 1,
                'invitado_at' => now(),
                'total_invitado' => 500.0,
            ],
            [[
                'es_concepto' => true,
                'concepto_descripcion' => 'Consumo especial',
                'tipo_iva_id' => $this->tiposIva[5]->id, // IVA 21%
                'iva_porcentaje' => 21,
                'cantidad' => 1,
                // El ítem invitado viaja con precio 0 (WithInvitaciones deja el
                // valor original en precio_unitario_original / monto_invitado).
                'precio_unitario' => 0.0,
                'precio_unitario_original' => 500.0,
                'descuento' => 0,
                'precio_iva_incluido' => true,
                'es_invitacion' => true,
                'invitacion_motivo' => 'Cortesía de la casa',
                'invitado_por_usuario_id' => 1,
                'invitado_at' => now(),
                'monto_invitado' => 500.0,
            ]],
        );

        $detalle = $venta->detalles()->first();
        $this->assertTrue((bool) $detalle->es_concepto);
        $this->assertSame('Consumo especial', $detalle->concepto_descripcion);
        $this->assertTrue((bool) $detalle->es_invitacion);
        $this->assertEqualsWithDelta(500.0, (float) $detalle->monto_invitado, 0.01);
        $this->assertTrue((bool) $venta->fresh()->es_invitacion_total);
        // Cortesía: nada que cobrar.
        $this->assertEqualsWithDelta(0.0, (float) $venta->fresh()->total, 0.01);
    }

    /** El mismo path legacy con un concepto NO invitado tampoco debe explotar. */
    public function test_venta_legacy_con_concepto_libre_usa_los_datos_del_detalle(): void
    {
        $venta = (new VentaService)->crearVenta(
            [
                'sucursal_id' => $this->sucursalId,
                'usuario_id' => 1,
                'caja_id' => $this->cajaAbiertaId,
                'numero' => '0001-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
                'fecha' => now(),
                'total' => 0,
            ],
            [[
                'es_concepto' => true,
                'concepto_descripcion' => 'Servicio varios',
                'tipo_iva_id' => $this->tiposIva[5]->id,
                'iva_porcentaje' => 21,
                'cantidad' => 1,
                'precio_unitario' => 121.0,
                'descuento' => 0,
                'precio_iva_incluido' => true,
            ]],
        );

        $detalle = $venta->detalles()->first();
        $this->assertEqualsWithDelta(121.0, (float) $detalle->total, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $detalle->precio_sin_iva * (float) $detalle->cantidad, 0.05);
        // Nota: no se asserta la cabecera — la matemática legacy de
        // calcularTotales queda fuera de este RF (en la práctica el path legacy
        // solo se ejercita con cortesías, donde todos los montos son 0).
    }
}
