# BCN Pymes - Instrucciones del Proyecto

## Preferencias
- Responder siempre en español

## Stack
- Laravel 11 + Livewire 3 + Alpine.js + Tailwind CSS
- PHP 8.2+, MySQL 8, Node.js 18+

---

## Reglas Críticas

### Multi-tenant (3 conexiones)
- **config**: users, comercios (sin prefijo)
- **pymes**: menu_items, permissions (compartidas, sin prefijo)
- **pymes_tenant**: tablas con prefijo `{NNNNNN}_` por comercio
- Modelos tenant: SIEMPRE `protected $connection = 'pymes_tenant'`
- Prefijo: `str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_'`
- Ref: `.claude/docs/arquitectura-tenant.md`

### Migraciones tenant
- Iterar TODOS los comercios, SQL raw con prefijo, try/catch por comercio
- SIEMPRE regenerar `database/sql/tenant_tables.sql` después de cambios
- Ref: `.claude/docs/workflows-migraciones.md`

### Componentes Livewire
- **Sucursal-aware**: trait `SucursalAware` + `sucursal_activa()`. NO para catálogos globales
- **Caja-aware**: trait `CajaAware`, solo ventas/cobranza
- **Catálogos globales** (GruposOpcionales, Recetas): NO son SucursalAware
- Ref estándares: `.claude/ESTANDARES_PROYECTO.md`
- Ref patrones: `.claude/docs/componentes-livewire.md`

### Nuevo módulo
- Requiere: migración menu_items + permisos + rutas + componente + vista + traducciones
- Actualizar `ProvisionComercioCommand` → `seedRolesYPermisos()`
- Ref: `.claude/docs/workflows-nuevo-modulo.md`

### Patrones de negocio
- **Append-only ledger**: MovimientoCuentaCorriente, MovimientoStock, MovimientoCuentaEmpresa → contraasientos
- **Stock dual**: `stock` = caché, `movimientos_stock` = historial
- **Services**: Lógica en `app/Services/`, Livewire llama a services
- **morphMap**: AppServiceProvider mapea 'Articulo' y 'Opcional'
- **PrecioService**: 4 niveles especificidad + promociones + IVA + cuotas

### Traducciones
- 3 archivos: `lang/{es,en,pt}.json`, ordenados alfabéticamente, usar `__()`

### Gotchas
- `comercios.cuit` es NOT NULL UNIQUE → placeholder `'PROV-' . time()`
- Campo `email` (no 'mail'), pero `$fillable` incluye 'mail' como alias
- `menu_items.slug` tiene UNIQUE constraint
- Cache permisos: `user_permissions_{userId}_{comercioId}` → `optimize:clear`
- CLI (artisan) no tiene sesión → configurar TenantService manualmente

---

## Skills (Slash Commands)

| Trigger | Skill | Descripción |
|---------|-------|-------------|
| Nuevo feature complejo | `/sdd-init` | Iniciar workflow Spec-Driven Development |
| Explorar código | `/sdd-explore` | Explorar codebase antes de especificar |
| Crear especificación | `/sdd-spec` | Escribir spec en `.claude/specs/` |
| Implementar desde spec | `/sdd-apply` | Implementar fase por fase |
| Verificar implementación | `/sdd-verify` | Validar contra criterios del spec |
| Crear migración | `/migration` | Migración tenant-aware con templates |
| Módulo completo | `/nuevo-modulo` | Menu + permisos + modelo + service + componente + ruta |
| Componente Livewire | `/nuevo-componente` | Componente con estándares del proyecto |
| Vista Blade | `/vista` | Vista responsive con design system (dark mode, cards+tabla) |
| Service PHP | `/service` | Service con transacciones, logging, excepciones |
| Modelo Eloquent | `/modelo` | Modelo con conexión tenant, scopes, casts, relaciones |
| Agregar traducciones | `/traducir` | Agregar a 3 archivos manteniendo orden |
| Generar tests | `/test` | Tests para Service, Model o Livewire (PHPUnit + tenant) |
| Suite de tests | `/test-suite` | Suite completa de tests para un módulo entero |

## Workflow SDD (Spec-Driven Development)

**REGLA: Decidir automáticamente qué nivel de proceso usar según el pedido del usuario:**

| Tamaño del cambio | Qué hacer | Ejemplo |
|-------------------|-----------|---------|
| **Chico**: fix, ajuste, cambio puntual | Implementar directo | "Arreglá el bug en el login", "Cambiá el color del botón" |
| **Mediano**: feature nuevo acotado, 2-3 archivos | Explorar → planificar → implementar (usar plan mode) | "Agregá un filtro por fecha en ventas" |
| **Grande**: módulo nuevo, cambio estructural, +3 archivos | Flujo SDD completo con spec escrito | "Quiero un módulo de compras", "Implementar facturación electrónica" |

Para features grandes, seguir este flujo:
```
/sdd-init → /sdd-explore → /sdd-spec → (aprobación usuario) → /sdd-apply → /sdd-verify
```
- Specs se guardan en `.claude/specs/{nombre-feature}.md`
- Template: `.claude/docs/spec-template.md`
- Cada fase actualiza el estado del spec (PENDIENTE → EN PROGRESO → COMPLETO)
- **El usuario NO necesita invocar los skills manualmente** — Claude decide y ejecuta el flujo apropiado

---

### Testing
- BDs dedicadas: `config_test`, `pymes_test` (MySQL, no SQLite)
- Traits: `WithTenant`, `WithSucursal`, `WithCaja` para contexto multi-tenant en tests
- Prioridad: Services (dinero/stock/ledger) > Models (scopes) > Livewire (CRUD)
- `/sdd-verify` ejecuta tests reales y genera Spec Compliance Matrix
- Ref: `.claude/docs/testing-patterns.md`

---

## Git & CI/CD

### Conventional Commits (OBLIGATORIO)
Todos los commits DEBEN seguir el formato [Conventional Commits](https://www.conventionalcommits.org/):

```
<tipo>[scope opcional]: <descripción>

[cuerpo opcional]

[footer opcional]
```

| Tipo | Cuándo usar | Bump |
|------|-------------|------|
| `feat:` | Nueva funcionalidad | minor |
| `fix:` | Corrección de bug | patch |
| `refactor:` | Refactoring sin cambio funcional | — |
| `perf:` | Mejora de rendimiento | patch |
| `test:` | Agregar o modificar tests | — |
| `docs:` | Documentación | — |
| `ci:` | Cambios en CI/CD workflows | — |
| `chore:` | Mantenimiento, dependencias | — |
| `feat!:` o `BREAKING CHANGE:` | Cambio incompatible | major |

Ejemplos:
- `feat(ventas): agregar filtro por fecha en listado`
- `fix(stock): corregir cálculo de stock al anular venta`
- `refactor(auth): extraer lógica de permisos a PermissionService`

### Workflow de branches
- **Nunca push directo a master** — todo vía Pull Request
- Cada PR ejecuta automáticamente: Lint (Pint) + Tests (PHPUnit) + Security Review (Claude)
- Si algo falla, el PR queda bloqueado
- `master` siempre lista para producción

### GitHub Actions (`.github/workflows/`)
- `ci.yml` — Lint con Pint + Tests con PHPUnit en cada PR
- `claude-review.yml` — Security review automático + `@claude` interactivo en PRs
- `release-please.yml` — Versionado automático con Release Please

### Release Please
- Tras merge en master → crea Release PR automáticamente
- Al mergear Release PR → crea tag `vX.Y.Z` + GitHub Release + CHANGELOG
- Config: `release-please-config.json` (tipo PHP)
- Manifest: `.release-please-manifest.json` (versión actual)

---

## Comandos Útiles
- `php artisan comercio:provision --nombre= --database= --mail=`
- `php artisan menu:create` (interactivo)
- `php artisan optimize:clear`
- `php artisan test` / `php artisan test --filter=NombreTest`
- `php artisan precios:procesar-programados` (scheduler, cada minuto)

## Referencia Completa
- Arquitectura: `.claude/docs/arquitectura-tenant.md`
- Migraciones: `.claude/docs/workflows-migraciones.md`
- Nuevo módulo: `.claude/docs/workflows-nuevo-modulo.md`
- Componentes: `.claude/docs/componentes-livewire.md`
- Design system: `.claude/docs/design-system.md`
- Services/Models: `.claude/docs/servicios-referencia.md`
- Testing: `.claude/docs/testing-patterns.md`
- Template SDD: `.claude/docs/spec-template.md`
