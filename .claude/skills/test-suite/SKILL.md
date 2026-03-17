---
name: test-suite
description: Crear suite completa de tests para un módulo (unit + feature + integration). Genera tests para Service, Model y Livewire de un módulo.
user-invocable: true
argument-hint: "[nombre-módulo]"
---

# Test Suite — Suite Completa de Tests por Módulo

Tu trabajo es crear una suite completa de tests para un módulo de BCN Pymes, cubriendo todas las capas.

## Al ejecutar este skill:

### 1. Identificar el módulo
- Si se pasó argumento, buscar archivos del módulo
- Si no, preguntar al usuario

### 2. Mapear archivos del módulo

Buscar en el codebase:
- `app/Services/{Modulo}Service.php` → test Unit
- `app/Models/{Modulo}.php` (y modelos relacionados) → tests Integration
- `app/Livewire/{Modulo}/` (componentes) → tests Feature

### 3. Generar la estructura completa

```
tests/
├── Unit/Services/
│   └── {Modulo}ServiceTest.php          ← Lógica de negocio
├── Integration/Models/
│   └── {Modelo}Test.php                 ← Scopes, relaciones, casts
└── Feature/Livewire/{Modulo}/
    └── {Componente}Test.php             ← UI, CRUD, eventos
```

### 4. Para cada archivo, usar el skill /test internamente

Leer `.claude/skills/test/SKILL.md` para los patrones de cada tipo.

### 5. Priorizar qué testear

**Tier 1 — Crítico** (siempre testear):
- Métodos de Service que crean/modifican datos (transacciones)
- Métodos que involucran dinero (ventas, pagos, cuentas)
- Operaciones de ledger (movimientos de stock, cuenta corriente)
- Validaciones de negocio (stock suficiente, permisos)

**Tier 2 — Importante** (testear si hay tiempo):
- Scopes de Model
- CRUD en Livewire
- Cambio de sucursal en componentes SucursalAware

**Tier 3 — Deseable** (nice to have):
- Relaciones de Model
- Filtros y búsqueda en Livewire
- Edge cases

### 6. Cobertura mínima por módulo

| Capa | Mínimo |
|------|--------|
| Service | 1 test por método público |
| Model | 1 test por scope + 1 por relación principal |
| Livewire | render + create + edit + delete |

### 7. Reporte final

Al terminar, mostrar:
```
Suite de tests para módulo: {Nombre}

Archivos creados:
- tests/Unit/Services/{Modulo}ServiceTest.php (X tests)
- tests/Integration/Models/{Modelo}Test.php (X tests)
- tests/Feature/Livewire/{Modulo}/{Componente}Test.php (X tests)

Total: X tests

Para ejecutar:
  php artisan test --testsuite=Unit --filter={Modulo}
  php artisan test --testsuite=Integration --filter={Modelo}
  php artisan test --testsuite=Feature --filter={Componente}
  php artisan test  (todos)
```

### 8. Reglas

- SIEMPRE leer el código fuente antes de generar tests
- SIEMPRE usar WithTenant/WithSucursal/WithCaja según necesite
- NO generar tests triviales (getters/setters sin lógica)
- Priorizar tests de lógica de negocio sobre tests de UI
- Tests deben ser independientes entre sí
- Nombres descriptivos en español: `puede_crear_venta`, `falla_sin_stock_suficiente`
