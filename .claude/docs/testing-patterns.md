# Patrones de Testing — BCN Pymes

## Infraestructura

### Bases de datos
- `config_test` — Tablas de sistema (users, comercios)
- `pymes_test` — Tablas compartidas + tablas tenant con prefijo

### Archivos clave
- `.env.testing` — Config de testing con BDs MySQL dedicadas
- `phpunit.xml` — 3 testsuites: Unit, Feature, Integration
- `tests/TestCase.php` — Base class que crea BDs si no existen
- `tests/Traits/WithTenant.php` — Crea comercio + tablas tenant de prueba
- `tests/Traits/WithSucursal.php` — Crea sucursal activa
- `tests/Traits/WithCaja.php` — Crea caja activa

## Traits de Testing

### WithTenant
Provee: `$this->comercio`, `$this->tenantPrefix`
```php
use WithTenant;

protected function setUp(): void {
    parent::setUp();
    $this->setUpTenant();        // Crea comercio + tablas tenant
}

protected function tearDown(): void {
    $this->tearDownTenant();     // Limpia tablas + comercio
    parent::tearDown();
}
```

### WithSucursal (requiere WithTenant)
Provee: `$this->sucursalId`
```php
use WithTenant, WithSucursal;

protected function setUp(): void {
    parent::setUp();
    $this->setUpTenant();
    $this->setUpSucursal();      // Crea sucursal + session
}
```

### WithCaja (requiere WithTenant + WithSucursal)
Provee: `$this->cajaId`
```php
use WithTenant, WithSucursal, WithCaja;

protected function setUp(): void {
    parent::setUp();
    $this->setUpTenant();
    $this->setUpSucursal();
    $this->setUpCaja();          // Crea caja + session
}
```

## Estructura de directorios

```
tests/
├── TestCase.php                    Base class
├── Traits/
│   ├── WithTenant.php             Contexto multi-tenant
│   ├── WithSucursal.php           Sucursal activa
│   └── WithCaja.php               Caja activa
├── Unit/
│   └── Services/                  Tests de lógica de negocio
│       └── {Service}Test.php
├── Feature/
│   ├── Auth/                      Tests de autenticación (Breeze)
│   └── Livewire/
│       └── {Modulo}/
│           └── {Componente}Test.php
└── Integration/
    └── Models/
        └── {Modelo}Test.php
```

## Cuándo usar qué testsuite

| Testsuite | Qué testear | Traits | Velocidad |
|-----------|-------------|--------|-----------|
| **Unit** | Services: lógica de negocio, transacciones, validaciones | WithTenant, WithSucursal | Media |
| **Feature** | Livewire: render, CRUD, eventos, permisos | WithTenant, WithSucursal | Lenta |
| **Integration** | Models: scopes, relaciones, casts, helpers | WithTenant | Rápida |

## Patrón AAA (Arrange-Act-Assert)

```php
/** @test */
public function puede_crear_venta_con_stock_suficiente(): void
{
    // Arrange — preparar datos
    $articulo = Articulo::create([...]);
    Stock::create(['articulo_id' => $articulo->id, 'stock_actual' => 10, ...]);

    // Act — ejecutar la acción
    $venta = $this->ventaService->crearVenta([
        'items' => [['articulo_id' => $articulo->id, 'cantidad' => 2]],
        'sucursal_id' => $this->sucursalId,
    ]);

    // Assert — verificar resultado
    $this->assertNotNull($venta->id);
    $this->assertEquals(8, Stock::where('articulo_id', $articulo->id)->value('stock_actual'));
}
```

## Naming de tests

- Archivo: `{ClaseOriginal}Test.php`
- Métodos: `/** @test */` + snake_case descriptivo
- Patrones:
  - `puede_hacer_algo` — caso exitoso
  - `falla_cuando_condicion` — caso de error esperado
  - `no_permite_accion_sin_permiso` — validación de acceso

## Comandos

```bash
# Ejecutar todos los tests
php artisan test

# Por testsuite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# Filtrar por clase
php artisan test --filter=VentaServiceTest

# Filtrar por método
php artisan test --filter=puede_crear_venta

# Con cobertura (requiere Xdebug/PCOV)
php artisan test --coverage
```

## Qué testear por prioridad

### Tier 1 — Crítico (SIEMPRE testear)
- Operaciones que involucran dinero (VentaService, CobroService)
- Operaciones de ledger (StockService, CuentaEmpresaService)
- Validaciones de negocio (stock suficiente, permisos de sucursal)
- Transacciones que deben hacer rollback si fallan

### Tier 2 — Importante
- Scopes de models
- CRUD en componentes Livewire
- Cambio de sucursal en componentes SucursalAware
- PrecioService (4 niveles de especificidad)

### Tier 3 — Deseable
- Relaciones de models
- Filtros y búsqueda
- Edge cases
- Soft deletes

## Testing Livewire

```php
use Livewire\Livewire;

/** @test */
public function componente_renderiza(): void
{
    Livewire::test(MiComponente::class)
        ->assertStatus(200)
        ->assertSee('Título esperado');
}

/** @test */
public function puede_crear_registro(): void
{
    Livewire::test(MiComponente::class)
        ->set('nombre', 'Test')
        ->set('precio', 100)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');
}

/** @test */
public function validacion_muestra_error(): void
{
    Livewire::test(MiComponente::class)
        ->set('nombre', '')  // campo requerido
        ->call('save')
        ->assertHasErrors(['nombre' => 'required']);
}
```
