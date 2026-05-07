<?php

namespace Tests\Unit\Services;

use App\Models\Caja;
use App\Models\Cupon;
use App\Services\CuponService;
use Exception;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Verifica la revalidación del cupón en el momento de cobro.
 *
 * Regla 2026-05-07 (Repaso 1): aplicarCuponEnVenta debe revalidar TANTO usos
 * disponibles COMO vigencia (fechas) — no alcanza con que esté vigente al
 * momento de validar input, porque entre validar y cobrar puede pasar tiempo.
 */
class CuponServiceVigenciaTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected CuponService $cuponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->cuponService = app(CuponService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_aplicar_cupon_vencido_lanza_excepcion(): void
    {
        $cupon = Cupon::create([
            'codigo' => 'CUP-VENCIDO-'.strtoupper(substr(uniqid(), -4)),
            'tipo' => 'promocional',
            'descripcion' => 'Test vencido',
            'modo_descuento' => 'porcentaje',
            'valor_descuento' => 10,
            'aplica_a' => 'total',
            'activo' => true,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'created_by_usuario_id' => 1,
        ]);

        $venta = $this->crearVentaBasica(['_caja' => Caja::find($this->cajaId)]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('vigente');

        $this->cuponService->aplicarCuponEnVenta($cupon, $venta, 100, 1);
    }

    public function test_aplicar_cupon_sin_usos_lanza_excepcion(): void
    {
        $cupon = Cupon::create([
            'codigo' => 'CUP-AGOTADO-'.strtoupper(substr(uniqid(), -4)),
            'tipo' => 'promocional',
            'descripcion' => 'Test agotado',
            'modo_descuento' => 'porcentaje',
            'valor_descuento' => 10,
            'aplica_a' => 'total',
            'activo' => true,
            'uso_maximo' => 1,
            'uso_actual' => 1,
            'created_by_usuario_id' => 1,
        ]);

        $venta = $this->crearVentaBasica(['_caja' => Caja::find($this->cajaId)]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uso máximo');

        $this->cuponService->aplicarCuponEnVenta($cupon, $venta, 100, 1);
    }

    public function test_aplicar_cupon_vigente_y_disponible_funciona(): void
    {
        $cupon = Cupon::create([
            'codigo' => 'CUP-OK-'.strtoupper(substr(uniqid(), -4)),
            'tipo' => 'promocional',
            'descripcion' => 'Test OK',
            'modo_descuento' => 'porcentaje',
            'valor_descuento' => 10,
            'aplica_a' => 'total',
            'activo' => true,
            'uso_maximo' => 0, // 0 = ilimitado
            'uso_actual' => 0,
            'fecha_vencimiento' => now()->addDay()->toDateString(),
            'created_by_usuario_id' => 1,
        ]);

        $venta = $this->crearVentaBasica(['_caja' => Caja::find($this->cajaId)]);

        $uso = $this->cuponService->aplicarCuponEnVenta($cupon, $venta, 100, 1);

        $this->assertNotNull($uso->id);
        $this->assertEquals(1, $cupon->fresh()->uso_actual);
    }
}
