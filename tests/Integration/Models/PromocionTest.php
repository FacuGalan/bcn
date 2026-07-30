<?php

namespace Tests\Integration\Models;

use App\Models\Promocion;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class PromocionTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Helper: crea una promocion de prueba.
     */
    private function crearPromocion(array $overrides = []): Promocion
    {
        return Promocion::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Promo Test '.uniqid(),
            'tipo' => 'descuento_porcentaje',
            'valor' => 10,
            'prioridad' => 1,
            'combinable' => true,
            'activo' => true,
            'usos_actuales' => 0,
        ], $overrides));
    }

    public function test_calcular_ajuste_descuento_porcentaje(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_porcentaje',
            'valor' => 20,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertEquals(20, $ajuste['porcentaje']);
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_descuento_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_monto',
            'valor' => 500,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        $this->assertEquals(500, $ajuste['valor']);
    }

    public function test_calcular_ajuste_precio_fijo(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'precio_fijo',
            'valor' => 800,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        // valor = max(0, 1000 - 800) = 200
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_recargo_porcentaje(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'recargo_porcentaje',
            'valor' => 10,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('recargo', $ajuste['tipo']);
        $this->assertEquals(10, $ajuste['porcentaje']);
        $this->assertEquals(100, $ajuste['valor']);
    }

    public function test_calcular_ajuste_recargo_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'recargo_monto',
            'valor' => 200,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('recargo', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_descuento_monto_no_supera_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_monto',
            'valor' => 1500,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        // min(1500, 1000) = 1000
        $this->assertEquals(1000, $ajuste['valor']);
    }

    public function test_vigencia_por_fecha_dentro_de_rango(): void
    {
        $promo = $this->crearPromocion([
            'vigencia_desde' => now()->subDay()->toDateString(),
            'vigencia_hasta' => now()->addDay()->toDateString(),
        ]);

        $this->assertTrue($promo->estaVigentePorFecha(now()));
    }

    public function test_vigencia_por_fecha_fuera_de_rango(): void
    {
        $promo = $this->crearPromocion([
            'vigencia_desde' => now()->subDays(5)->toDateString(),
            'vigencia_hasta' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($promo->estaVigentePorFecha(now()));
    }

    public function test_aplica_en_dia_semana(): void
    {
        $promo = $this->crearPromocion([
            'dias_semana' => [1, 2, 3],
        ]);

        // Dia 2 (Martes) debe aplicar
        $this->assertTrue($promo->aplicaEnDiaSemana(2));

        // Dia 5 (Viernes) no debe aplicar
        $this->assertFalse($promo->aplicaEnDiaSemana(5));
    }

    public function test_aplica_en_horario(): void
    {
        $promo = $this->crearPromocion([
            'hora_desde' => '08:00:00',
            'hora_hasta' => '20:00:00',
        ]);

        // 10:00 dentro de rango
        $this->assertTrue($promo->aplicaEnHorario('10:00:00'));

        // 22:00 fuera de rango
        $this->assertFalse($promo->aplicaEnHorario('22:00:00'));
    }

    public function test_tiene_usos_disponibles(): void
    {
        $promo = $this->crearPromocion([
            'usos_maximos' => 5,
            'usos_actuales' => 3,
        ]);

        $this->assertTrue($promo->tieneUsosDisponibles());

        // Alcanzar el limite
        $promo->update(['usos_actuales' => 5]);
        $promo->refresh();

        $this->assertFalse($promo->tieneUsosDisponibles());
    }

    public function test_scope_vigentes_filtra_por_fecha(): void
    {
        // 2 promociones vigentes
        $this->crearPromocion([
            'vigencia_desde' => now()->subDay()->toDateString(),
            'vigencia_hasta' => now()->addDay()->toDateString(),
        ]);

        $this->crearPromocion([
            'vigencia_desde' => null,
            'vigencia_hasta' => null,
        ]);

        // 1 promocion expirada
        $this->crearPromocion([
            'vigencia_desde' => now()->subDays(10)->toDateString(),
            'vigencia_hasta' => now()->subDays(5)->toDateString(),
        ]);

        $vigentes = Promocion::where('sucursal_id', $this->sucursalId)
            ->vigentes(now())
            ->count();

        $this->assertEquals(2, $vigentes);
    }

    // ==================== RF-T29: rangos min/max ====================

    public function test_condicion_por_cantidad_evalua_rango(): void
    {
        $promo = $this->crearPromocion();
        $condicion = $promo->condiciones()->create([
            'tipo_condicion' => 'por_cantidad',
            'cantidad_minima' => 2,
            'cantidad_maxima' => 5,
        ]);

        $this->assertFalse($condicion->seCumple(['cantidad' => 1]), 'Bajo el mínimo no cumple');
        $this->assertTrue($condicion->seCumple(['cantidad' => 2]), 'Borde inferior cumple');
        $this->assertTrue($condicion->seCumple(['cantidad' => 5]), 'Borde superior cumple');
        $this->assertFalse($condicion->seCumple(['cantidad' => 6]), 'Sobre el máximo no cumple');
    }

    public function test_condicion_sin_maximo_mantiene_comportamiento_legado(): void
    {
        $promo = $this->crearPromocion();
        $condicion = $promo->condiciones()->create([
            'tipo_condicion' => 'por_total_compra',
            'monto_minimo' => 1000,
        ]);

        $this->assertTrue($condicion->seCumple(['total' => 999999]), 'Máximo NULL = sin tope');
        $this->assertFalse($condicion->seCumple(['total' => 999]));
    }

    public function test_condicion_por_total_evalua_rango(): void
    {
        $promo = $this->crearPromocion();
        $condicion = $promo->condiciones()->create([
            'tipo_condicion' => 'por_total_compra',
            'monto_minimo' => 1000,
            'monto_maximo' => 5000,
        ]);

        $this->assertFalse($condicion->seCumple(['total' => 999]));
        $this->assertTrue($condicion->seCumple(['total' => 3000]));
        $this->assertFalse($condicion->seCumple(['total' => 5001]));
    }

    // ==================== RF-T28: OR dentro del tipo, AND entre tipos ====

    public function test_validar_condiciones_or_dentro_del_tipo_and_entre_tipos(): void
    {
        // Promo con DOS formas de pago habilitadas (2 filas por_forma_pago) +
        // cantidad mínima. El AND plano anterior exigía cumplir AMBAS filas
        // de FP a la vez ⇒ jamás aplicaba (bug preexistente).
        $fp1 = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp2 = $this->crearFormaPagoCC()['formaPago'];

        $promo = $this->crearPromocion();
        $promo->condiciones()->create(['tipo_condicion' => 'por_forma_pago', 'forma_pago_id' => $fp1->id]);
        $promo->condiciones()->create(['tipo_condicion' => 'por_forma_pago', 'forma_pago_id' => $fp2->id]);
        $promo->condiciones()->create(['tipo_condicion' => 'por_cantidad', 'cantidad_minima' => 2]);
        $promo->load('condiciones');

        $validar = new \ReflectionMethod(\App\Services\PrecioService::class, 'validarCondicionesPromocion');
        $servicio = app(\App\Services\PrecioService::class);

        // Una de las dos FP + cantidad OK ⇒ aplica (OR dentro de por_forma_pago).
        $this->assertTrue($validar->invoke($servicio, $promo, ['forma_pago_id' => $fp1->id, 'cantidad' => 3]));
        $this->assertTrue($validar->invoke($servicio, $promo, ['forma_pago_id' => $fp2->id, 'cantidad' => 3]));
        // FP ajena ⇒ no aplica.
        $this->assertFalse($validar->invoke($servicio, $promo, ['forma_pago_id' => 999999, 'cantidad' => 3]));
        // Cantidad bajo el mínimo ⇒ no aplica aunque la FP esté (AND entre tipos).
        $this->assertFalse($validar->invoke($servicio, $promo, ['forma_pago_id' => $fp1->id, 'cantidad' => 1]));
    }

    public function test_validar_condiciones_alcance_articulo_o_categoria_es_un_solo_grupo(): void
    {
        // Alcance mixto: artículo X + categoría Y (el wizard permite marcar
        // ambos a la vez). Debe evaluarse como UN solo grupo OR — igual que
        // WithCalculoVenta::promocionAplicaAItem — no como dos grupos en AND
        // (que hacían que el catálogo de la tienda mostrara un precio
        // distinto del que cobraba el carrito).
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $categoria = \App\Models\Categoria::create(['nombre' => 'Alcance OR '.uniqid(), 'activo' => true]);

        $promo = $this->crearPromocion();
        $promo->condiciones()->create(['tipo_condicion' => 'por_articulo', 'articulo_id' => $articulo->id]);
        $promo->condiciones()->create(['tipo_condicion' => 'por_categoria', 'categoria_id' => $categoria->id]);
        $promo->load('condiciones');

        $validar = new \ReflectionMethod(\App\Services\PrecioService::class, 'validarCondicionesPromocion');
        $servicio = app(\App\Services\PrecioService::class);

        // El artículo del alcance, aunque NO pertenezca a la categoría ⇒ aplica.
        $this->assertTrue($validar->invoke($servicio, $promo, ['articulo_id' => $articulo->id, 'categoria_id' => 999999]));
        // Otro artículo de la categoría del alcance ⇒ aplica.
        $this->assertTrue($validar->invoke($servicio, $promo, ['articulo_id' => 999999, 'categoria_id' => $categoria->id]));
        // Fuera de ambos ⇒ no aplica.
        $this->assertFalse($validar->invoke($servicio, $promo, ['articulo_id' => 999999, 'categoria_id' => 999999]));
    }

    // ==================== RF-T28: especiales plurales ====================

    public function test_promocion_especial_evalua_formas_venta_plurales_con_fallback(): void
    {
        $plural = \App\Models\PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Especial plural '.uniqid(),
            'tipo' => \App\Models\PromocionEspecial::TIPO_NXM,
            'modo_aplicacion' => \App\Models\PromocionEspecial::MODO_AUTOMATICA,
            'nxm_lleva' => 2, 'nxm_paga' => 1,
            'prioridad' => 1, 'activo' => true, 'usos_actuales' => 0,
            'formas_venta_ids' => [10, 20],
        ]);

        $this->assertTrue($plural->cumpleCondiciones(['forma_venta_id' => 20]));
        $this->assertFalse($plural->cumpleCondiciones(['forma_venta_id' => 30]));

        // Registro LEGADO: solo el singular cargado ⇒ fallback como lista de uno.
        $legada = \App\Models\PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Especial legada '.uniqid(),
            'tipo' => \App\Models\PromocionEspecial::TIPO_NXM,
            'modo_aplicacion' => \App\Models\PromocionEspecial::MODO_AUTOMATICA,
            'nxm_lleva' => 2, 'nxm_paga' => 1,
            'prioridad' => 1, 'activo' => true, 'usos_actuales' => 0,
            'canal_venta_id' => 5,
        ]);

        $this->assertSame([5], $legada->canalesVentaIds());
        $this->assertTrue($legada->cumpleCondiciones(['canal_venta_id' => 5]));
        $this->assertFalse($legada->cumpleCondiciones(['canal_venta_id' => 6]));
        // Sin restricción de FV ⇒ cualquier forma de venta pasa.
        $this->assertTrue($legada->cumpleCondiciones(['canal_venta_id' => 5, 'forma_venta_id' => 99]));
    }
}
