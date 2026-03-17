---
name: sdd-verify
description: Verificar implementación contra spec. Revisa código, ejecuta tests reales y genera Spec Compliance Matrix.
user-invocable: true
argument-hint: "[nombre-feature]"
---

# SDD Verify — Verificar Implementación con Tests

Tu trabajo es validar que la implementación de un feature cumple con su especificación, incluyendo la ejecución de tests reales.

**Principio clave**: Un criterio de aceptación solo está COMPLIANT cuando hay un test que PASÓ probando el comportamiento.

## Al ejecutar este skill:

### Fase 1: Verificación estática (sin ejecutar código)

#### 1.1 Cargar el spec
- Buscar en `.claude/specs/` el spec del feature
- Leer completo: Modelo de Datos, Services, Componentes, Criterios de Aceptación

#### 1.2 Verificar modelo de datos
Para cada tabla definida en el spec:
- Verificar que exista la migración correspondiente
- Si es posible, verificar estructura con `SHOW CREATE TABLE`
- Verificar modelo: `$connection = 'pymes_tenant'`, `$fillable`, relaciones, scopes

#### 1.3 Verificar services
Para cada service/método definido en el spec:
- Verificar que el archivo exista
- Verificar que los métodos estén implementados
- Verificar patrón: transacciones, logging, excepciones

#### 1.4 Verificar componentes Livewire
Para cada componente definido en el spec:
- Verificar clase PHP + vista Blade
- Verificar traits (SucursalAware/CajaAware si aplica)
- Verificar checklist de `.claude/ESTANDARES_PROYECTO.md`

#### 1.5 Verificar rutas y traducciones
- Rutas existen en `routes/web.php`
- Traducciones en los 3 archivos JSON

### Fase 2: Verificación de tests (existencia)

#### 2.1 Buscar tests existentes
Buscar en `tests/` archivos que correspondan al feature:
- `tests/Unit/Services/{Service}Test.php`
- `tests/Integration/Models/{Modelo}Test.php`
- `tests/Feature/Livewire/{Modulo}/{Componente}Test.php`

#### 2.2 Mapear criterios → tests
Para cada criterio de aceptación del spec:
- ¿Hay un test que pruebe este comportamiento?
- Si NO hay test: marcarlo como `SIN COBERTURA`

#### 2.3 Generar tests faltantes
Si faltan tests para criterios críticos (Tier 1):
- Leer `.claude/docs/testing-patterns.md`
- Generar los tests siguiendo patrones del proyecto
- Preguntar al usuario si quiere que los cree

### Fase 3: Ejecución de tests

#### 3.1 Ejecutar tests del feature
```bash
php artisan test --filter={NombreFeature} --no-interaction
```

#### 3.2 Si hay fallos, analizar
- Leer output de PHPUnit
- Categorizar cada fallo:
  - **CRITICAL**: Test de lógica de negocio falla → la implementación tiene bug
  - **WARNING**: Test de UI falla → puede ser problema de setup
  - **SETUP**: Error de configuración (BD, traits) → no es bug del feature

#### 3.3 Si todos pasan, continuar a Fase 4

### Fase 4: Spec Compliance Matrix

Generar la matriz de cumplimiento:

```
## Spec Compliance Matrix: {nombre-feature}

| # | Criterio de Aceptación | Test | Resultado |
|---|------------------------|------|-----------|
| 1 | Puede crear X con datos válidos | ServiceTest::puede_crear_x | PASS ✓ |
| 2 | Falla sin stock suficiente | ServiceTest::falla_sin_stock | PASS ✓ |
| 3 | UI muestra listado filtrado | ComponenteTest::puede_buscar | PASS ✓ |
| 4 | Valida permisos de sucursal | — | SIN TEST ⚠ |

### Resultado: {X}/{Y} criterios con test que PASÓ

### Issues encontrados:

#### CRITICAL (bloquean aprobación)
- {descripción del issue y qué falta}

#### WARNING (deberían resolverse)
- {descripción}

#### SUGGESTION (mejoras opcionales)
- {descripción}
```

### Fase 5: Veredicto y actualización del spec

**APROBADO** si:
- Todos los criterios Tier 1 tienen tests que PASAN
- No hay issues CRITICAL
- Código sigue patrones del proyecto

**RECHAZADO** si:
- Hay tests que FALLAN para criterios Tier 1
- Faltan tests para criterios que involucran dinero/stock/ledger
- El código no sigue los patrones (falta SucursalAware, transacciones, etc.)

Acciones:
- Si APROBADO: marcar spec como `IMPLEMENTADO`, felicitar
- Si RECHAZADO: mantener como `EN PROGRESO`, listar lo que falta corregir

## Reglas

- Fase 1-2: NO modificar código — solo verificar y leer
- Fase 2.3: PUEDE crear tests (preguntar al usuario primero)
- Fase 3: EJECUTA tests reales con `php artisan test`
- Ser estricto: sin test que pase, el criterio NO está verificado
- Reportar con claridad: cada issue tiene categoría y acción sugerida
