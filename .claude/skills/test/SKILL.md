---
name: test
description: Generar tests para Services, Models o Livewire siguiendo los patrones del proyecto (PHPUnit, multi-tenant, traits de testing).
user-invocable: true
argument-hint: "[clase a testear]"
---

# Test — Generar Tests

Tu trabajo es generar tests PHPUnit para BCN Pymes siguiendo los patrones del proyecto multi-tenant.

## Al ejecutar este skill:

### 1. Identificar qué testear
- Si se pasó argumento (`$ARGUMENTS`), buscar la clase
- Si no, preguntar: ¿Service, Model o Componente Livewire?

### 2. Leer la clase a testear
- Leer el archivo completo para entender métodos públicos, dependencias, lógica

### 3. Leer referencia de testing
- Leer `.claude/docs/testing-patterns.md` para los patrones

### 4. Determinar tipo de test y ubicación

| Tipo de clase | Tipo de test | Ubicación | Traits necesarios |
|---------------|-------------|-----------|-------------------|
| Service | Unit | `tests/Unit/Services/` | WithTenant, WithSucursal |
| Model | Integration | `tests/Integration/Models/` | WithTenant |
| Livewire | Feature | `tests/Feature/Livewire/` | WithTenant, WithSucursal |

### 5. Generar el test

#### Para Services (Unit):
```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Traits\WithTenant;
use Tests\Traits\WithSucursal;
use App\Services\MiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MiServiceTest extends TestCase
{
    use RefreshDatabase, WithTenant, WithSucursal;

    protected MiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->service = new MiService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /** @test */
    public function puede_crear_registro(): void
    {
        // Arrange
        $data = ['campo' => 'valor', 'sucursal_id' => $this->sucursalId];

        // Act
        $resultado = $this->service->crear($data);

        // Assert
        $this->assertNotNull($resultado->id);
        $this->assertEquals('valor', $resultado->campo);
    }

    /** @test */
    public function falla_con_datos_invalidos(): void
    {
        $this->expectException(\Exception::class);
        $this->service->crear([]);
    }
}
```

#### Para Models (Integration):
```php
/** @test */
public function scope_activos_filtra_correctamente(): void
{
    // Crear registros activos e inactivos
    Modelo::create(['activo' => true, ...]);
    Modelo::create(['activo' => false, ...]);

    $activos = Modelo::activos()->count();
    $this->assertEquals(1, $activos);
}

/** @test */
public function relacion_con_sucursal(): void
{
    $modelo = Modelo::create(['sucursal_id' => $this->sucursalId, ...]);
    $this->assertInstanceOf(Sucursal::class, $modelo->sucursal);
}
```

#### Para Livewire (Feature):
```php
use Livewire\Livewire;

/** @test */
public function puede_renderizar_componente(): void
{
    Livewire::test(MiComponente::class)
        ->assertStatus(200);
}

/** @test */
public function puede_buscar(): void
{
    // Crear datos de prueba
    Modelo::create(['nombre' => 'Test Item', 'sucursal_id' => $this->sucursalId]);

    Livewire::test(MiComponente::class)
        ->set('search', 'Test')
        ->assertSee('Test Item');
}

/** @test */
public function puede_crear_via_modal(): void
{
    Livewire::test(MiComponente::class)
        ->set('nombre', 'Nuevo Item')
        ->call('save')
        ->assertDispatched('notify');
}
```

### 6. Qué testear en cada caso

#### Services — testear:
- Caso exitoso de cada método público
- Validaciones (qué datos inválidos lanzan excepción)
- Transacciones (que se haga rollback si falla)
- Efectos secundarios (si crea un movimiento de stock, verificar la tabla stock)
- Ledger: verificar que contraasientos cancelen matemáticamente

#### Models — testear:
- Cada scope retorna lo esperado
- Relaciones cargan correctamente
- Casts devuelven el tipo correcto
- Helpers retornan valores esperados
- SoftDeletes funciona

#### Livewire — testear:
- Componente renderiza sin errores
- Búsqueda/filtros funcionan
- CRUD: crear, editar, eliminar
- Validación muestra errores
- Cambio de sucursal limpia estado (si es SucursalAware)
- Modales se abren/cierran correctamente

### 7. Convenciones de naming

- Archivo: `{ClaseOriginal}Test.php`
- Métodos: snake_case descriptivo con `/** @test */`
- Pattern: `puede_hacer_algo`, `falla_cuando_condicion`, `no_permite_accion`

### 8. Reglas

- SIEMPRE Arrange-Act-Assert (AAA)
- SIEMPRE usar traits WithTenant/WithSucursal cuando se accede a BD tenant
- SIEMPRE limpiar en tearDown (tearDownTenant)
- NUNCA depender de datos de otro test (tests independientes)
- NUNCA testear implementación interna — testear comportamiento
- Usar `RefreshDatabase` para tests que modifican BD
