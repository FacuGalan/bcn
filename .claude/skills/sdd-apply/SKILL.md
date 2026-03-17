---
name: sdd-apply
description: Implementar un feature desde su especificación. Lee el spec y ejecuta fase por fase.
user-invocable: true
argument-hint: "[nombre-feature]"
---

# SDD Apply — Implementar desde Spec

Tu trabajo es implementar un feature siguiendo su especificación previamente aprobada.

## Al ejecutar este skill:

### 1. Cargar el spec
- Buscar en `.claude/specs/` el spec del feature (por argumento o listar disponibles)
- Si no existe spec, informar al usuario que debe crear uno primero con `/sdd-spec`
- Verificar que el estado sea `EN REVISIÓN` o `APROBADO` (no implementar specs `PENDIENTE`)

### 2. Mostrar plan de implementación
- Leer la sección "Plan de Implementación" del spec
- Identificar fases con estado `PENDIENTE`
- Preguntar al usuario por qué fase empezar (o la siguiente pendiente)

### 3. Implementar fase por fase

Para cada fase, seguir este orden estricto:

**A. Migraciones** (si la fase las requiere)
- Leer `.claude/docs/workflows-migraciones.md` para los templates
- Crear migración siguiendo convenciones tenant
- Ejecutar `php artisan migrate` si el usuario lo autoriza

**B. Modelos** (si la fase los requiere)
- `protected $connection = 'pymes_tenant'` para modelos tenant
- `$fillable`, relaciones, scopes según el spec

**C. Services** (si la fase los requiere)
- Crear en `app/Services/` con la lógica definida en el spec
- Seguir el patrón: methods que reciben datos, ejecutan lógica, retornan resultado

**D. Componentes Livewire** (si la fase los requiere)
- Leer `.claude/ESTANDARES_PROYECTO.md` para estándares
- Aplicar SucursalAware / CajaAware según lo definido en el spec
- Crear vista Blade correspondiente

**E. Rutas** (si la fase las requiere)
- Agregar en `routes/web.php` dentro del grupo auth+verified+tenant

**F. Traducciones** (si la fase las requiere)
- Agregar a los 3 archivos JSON manteniendo orden alfabético

### 4. Actualizar el spec
- Después de completar cada fase, actualizar su estado en el spec:
  - `[PENDIENTE]` → `[EN PROGRESO]` al iniciar
  - `[EN PROGRESO]` → `[COMPLETO]` al terminar

### 5. Siguiente fase
- Preguntar al usuario si continuar con la siguiente fase o pausar

## Reglas
- SIEMPRE leer el spec completo antes de empezar
- NUNCA implementar algo que no esté en el spec — si falta algo, actualizar el spec primero
- Respetar el orden de fases del spec
- Si surge un problema, informar al usuario y proponer ajuste al spec
- Regenerar `tenant_tables.sql` si se crearon/modificaron tablas tenant
