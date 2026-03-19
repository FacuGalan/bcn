<?php

namespace Tests\Integration\Models;

use App\Models\Articulo;
use App\Models\Receta;
use App\Models\RecetaIngrediente;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class RecetaTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected int $otraSucursalId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->otraSucursalId = $this->crearSucursalAdicional('Sucursal Override');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // =========================================================================
    // Receta::resolver
    // =========================================================================
    public function test_resolver_override_tiene_prioridad(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');
        $ingrediente = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Ingrediente Default',
            'precio_base' => 50,
        ]);
        $ingredienteOverride = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Ingrediente Override',
            'precio_base' => 75,
        ]);

        // Receta default (sucursal_id = null)
        $recetaDefault = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $recetaDefault->id,
            'articulo_id' => $ingrediente->id,
            'cantidad' => 2,
        ]);

        // Receta override para esta sucursal
        $recetaOverride = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => $this->sucursalId,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $recetaOverride->id,
            'articulo_id' => $ingredienteOverride->id,
            'cantidad' => 3,
        ]);

        // Resolver debe devolver el override
        $resultado = Receta::resolver('Articulo', $articulo->id, $this->sucursalId);

        $this->assertNotNull($resultado);
        $this->assertEquals($recetaOverride->id, $resultado->id);
        $this->assertEquals($this->sucursalId, $resultado->sucursal_id);
    }

    public function test_resolver_default_si_no_hay_override(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');
        $ingrediente = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Ingrediente Default',
            'precio_base' => 50,
        ]);

        // Solo receta default
        $recetaDefault = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $recetaDefault->id,
            'articulo_id' => $ingrediente->id,
            'cantidad' => 5,
        ]);

        // No hay override para esta sucursal -> debe resolver la default
        $resultado = Receta::resolver('Articulo', $articulo->id, $this->sucursalId);

        $this->assertNotNull($resultado);
        $this->assertEquals($recetaDefault->id, $resultado->id);
        $this->assertNull($resultado->sucursal_id);
    }

    public function test_resolver_null_si_override_inactivo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');
        $ingrediente = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Ingrediente',
            'precio_base' => 50,
        ]);

        // Receta default activa
        $recetaDefault = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $recetaDefault->id,
            'articulo_id' => $ingrediente->id,
            'cantidad' => 2,
        ]);

        // Override INACTIVO -> anula la receta para esta sucursal
        Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => $this->sucursalId,
            'cantidad_producida' => 1,
            'activo' => false, // Inactivo = anulada
        ]);

        $resultado = Receta::resolver('Articulo', $articulo->id, $this->sucursalId);

        $this->assertNull($resultado);
    }

    public function test_resolver_null_sin_receta(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');

        // No crear ninguna receta
        $resultado = Receta::resolver('Articulo', $articulo->id, $this->sucursalId);

        $this->assertNull($resultado);
    }

    public function test_receta_carga_ingredientes(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');
        $ingrediente1 = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Harina',
            'precio_base' => 50,
        ]);
        $ingrediente2 = $this->crearArticuloConStock($this->sucursalId, 200, 'unitario', [
            'nombre' => 'Azucar',
            'precio_base' => 30,
        ]);

        $receta = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 10,
            'activo' => true,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $receta->id,
            'articulo_id' => $ingrediente1->id,
            'cantidad' => 2.5,
        ]);
        RecetaIngrediente::create([
            'receta_id' => $receta->id,
            'articulo_id' => $ingrediente2->id,
            'cantidad' => 1.5,
        ]);

        $resultado = Receta::resolver('Articulo', $articulo->id, $this->sucursalId);

        $this->assertNotNull($resultado);
        $this->assertTrue($resultado->relationLoaded('ingredientes'));
        $this->assertCount(2, $resultado->ingredientes);

        // Verificar que los ingredientes tienen el articulo cargado
        foreach ($resultado->ingredientes as $ing) {
            $this->assertTrue($ing->relationLoaded('articulo'));
            $this->assertNotNull($ing->articulo);
        }
    }

    public function test_es_default_vs_es_override(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'receta');

        // Receta default
        $recetaDefault = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);

        // Receta override
        $recetaOverride = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => $this->otraSucursalId,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);

        // Default: sucursal_id es null
        $this->assertTrue($recetaDefault->esDefault());
        $this->assertFalse($recetaDefault->esOverride());
        $this->assertNull($recetaDefault->sucursal_id);

        // Override: sucursal_id tiene valor
        $this->assertFalse($recetaOverride->esDefault());
        $this->assertTrue($recetaOverride->esOverride());
        $this->assertEquals($this->otraSucursalId, $recetaOverride->sucursal_id);
    }
}
