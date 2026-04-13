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
- **Lazy loading**: OBLIGATORIO `#[Lazy]` + `placeholder()` con skeleton en componentes full-page
  - Skeletons reutilizables: `<x-skeleton.page-table />`, `<x-skeleton.page-dashboard />`, `<x-skeleton.page-form />`
  - NO aplicar a embebidos (CajaSelector, SucursalSelector) ni sub-componentes
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

### Documentación del sistema
- Actualizar `docs/manual-usuario.md` y `docs/ai-knowledge-base.md` **al crear un PR** (no en cada commit individual), para tener contexto completo de todos los cambios del branch
- `manual-usuario.md`: funcionalidades por módulo/vista, acciones, filtros, modales, flujos
- `ai-knowledge-base.md`: modelo de datos, lógica de negocio, patrones de consulta, reglas

### Awareness de selectores (Alpine store global)
- El navbar usa un Alpine store `awareness = { sucursal: false, caja: false }` para mostrar/ocultar labels en selectores desktop
- Los traits `SucursalAware` y `CajaAware` setean `awareness.sucursal/caja = true` al montar
- En `livewire:navigating` se resetea a `false` para que cada página controle qué muestra
- **PROHIBIDO**: Forzar el store global desde el menú móvil o cualquier otro lugar. Usar la prop `show-labels` del componente selector en su lugar
- Los selectores móviles usan `<livewire:sucursal-selector :show-labels="true" />` para siempre mostrar labels sin afectar desktop

### No romper funcionalidad existente
- **Todo cambio en móvil debe verificarse en desktop y viceversa**
- Antes de modificar componentes compartidos (navigation, selectores, modales), entender cómo se usan en AMBOS contextos
- Si un componente tiene comportamiento condicional (x-show, awareness, responsive), NO forzar valores globales — usar props o clases CSS scoped

### Gotchas
- **Transacciones**: SIEMPRE `DB::connection('pymes_tenant')->transaction()`, NUNCA `DB::transaction()` (usa conexión default que no protege escrituras tenant)
- `comercios.cuit` es NOT NULL UNIQUE → placeholder `'PROV-' . time()`
- Campo `email` (no 'mail'), pero `$fillable` incluye 'mail' como alias
- `menu_items.slug` tiene UNIQUE constraint
- Cache permisos: `user_permissions_{userId}_{comercioId}` → `optimize:clear`
- CLI (artisan) no tiene sesión → configurar TenantService manualmente

---

## Skills — USO OBLIGATORIO

**REGLA ABSOLUTA: NUNCA escribir archivos a mano si existe un skill para ese tipo de archivo.** ANTES de crear/modificar cualquier artefacto, verificar esta tabla. Invocar el skill correspondiente SIN EXCEPCIÓN:

| ANTES de... | INVOCAR | Qué garantiza |
|-------------|---------|---------------|
| Crear/modificar migración | `/migration` | Iteración por comercios, prefijo, try/catch, regenera tenant_tables.sql |
| Crear modelo Eloquent | `/modelo` | Conexión correcta, casts, scopes, relaciones, fillable |
| Crear componente Livewire | `/nuevo-componente` | Traits correctos, lazy loading, skeleton, eventos |
| Crear vista Blade | `/vista` | Design system exacto, responsive, dark mode, `<x-bcn-modal>` |
| Crear service PHP | `/service` | Transacciones tenant, logging, excepciones |
| Crear módulo completo | `/nuevo-modulo` | Menu + permisos + modelo + service + componente + ruta + traducciones |
| Combobox con búsqueda + alta rápida | `/combobox-alta-rapida` | Input+botón unidos, búsqueda inteligente, teclado, alta inline |
| Agregar traducciones | `/traducir` | 3 archivos (es/en/pt), orden alfabético |
| Generar tests | `/test` | PHPUnit + multi-tenant + traits |
| Suite de tests completa | `/test-suite` | Unit + feature + integration para módulo entero |
| Feature grande (módulo nuevo, +3 archivos) | `/sdd-init` | Workflow Spec-Driven Development completo |
| Explorar código antes de spec | `/sdd-explore` | Identifica código relacionado, tablas, services |
| Escribir especificación | `/sdd-spec` | Spec en `.claude/specs/` con template |
| Implementar desde spec | `/sdd-apply` | Implementación fase por fase |
| Verificar implementación | `/sdd-verify` | Tests reales + Spec Compliance Matrix |

### Reglas de UI/Vistas (OBLIGATORIO)

- **Modales**: SIEMPRE usar `<x-bcn-modal>`. PROHIBIDO `<x-modal>` (deprecado). Color de header = color del botón que abre el modal
- **Vistas**: Estructura obligatoria → header + filtros + cards móvil (`sm:hidden`) + tabla desktop (`hidden sm:block`)
- **Dark mode**: TODA clase de color DEBE tener su variante `dark:`
- **CSS exacto**: Copiar clases del `.claude/docs/design-system.md`, NUNCA improvisar clases similares
- **Botones responsive**: Móvil solo icono, desktop icono + texto
- Ref: `.claude/docs/design-system.md`

## Workflow SDD (Spec-Driven Development)

**REGLA: Decidir automáticamente qué nivel de proceso usar según el pedido del usuario:**

| Tamaño del cambio | Qué hacer | Ejemplo |
|-------------------|-----------|---------|
| **Chico**: fix, ajuste, cambio puntual | Implementar directo | "Arreglá el bug en el login", "Cambiá el color del botón" |
| **Mediano**: feature nuevo acotado, 2-3 archivos | Explorar → planificar → implementar (usar plan mode) | "Agregá un filtro por fecha en ventas" |
| **Grande**: módulo nuevo, cambio estructural, +3 archivos | OBLIGATORIO flujo SDD completo con spec escrito | "Quiero un módulo de compras", "Implementar facturación electrónica" |

Para features grandes, seguir este flujo:
```
/sdd-init → /sdd-explore → /sdd-spec → (aprobación usuario) → /sdd-apply → /sdd-verify
```
- Specs se guardan en `.claude/specs/{nombre-feature}.md`
- Template: `.claude/docs/spec-template.md`
- Cada fase actualiza el estado del spec (PENDIENTE → EN PROGRESO → COMPLETO)
- **El usuario NO necesita invocar los skills manualmente** — Claude decide y ejecuta el flujo apropiado

---

### Verificación post-implementación (OBLIGATORIO)

Después de CADA implementación, verificar:

1. **Lint**: `php vendor/bin/pint --test` (archivos modificados)
2. **Tests**: `php artisan test --filter=NombreRelacionado` si existen tests del área
3. **Tenant**: Si se tocó migraciones → regenerar `database/sql/tenant_tables.sql`
4. **Traducciones**: Si se agregaron strings → verificar en los 3 archivos (es/en/pt)
5. **Regresión UI**: Si se tocó componentes compartidos → verificar que no se rompió desktop NI móvil
6. **Documentación**: Al crear PR, si se agregó/modificó funcionalidad → actualizar `docs/manual-usuario.md` y `docs/ai-knowledge-base.md`

### Testing
- BDs dedicadas: `config_test`, `pymes_test` (MySQL, no SQLite)
- Traits: `WithTenant`, `WithSucursal`, `WithCaja` para contexto multi-tenant en tests
- Prioridad: Services (dinero/stock/ledger) > Models (scopes) > Livewire (CRUD)
- `/sdd-verify` ejecuta tests reales y genera Spec Compliance Matrix
- Ref: `.claude/docs/testing-patterns.md`

---

## Git & CI/CD

- **Conventional Commits OBLIGATORIO**: `feat:`, `fix:`, `refactor:`, `perf:`, `test:`, `docs:`, `ci:`, `chore:` — ver detalle en ref
- **Nunca push directo a master** — todo vía branch + Pull Request
- **Al iniciar sesión**: verificar rama actual. Si no hay trabajo en curso, `git checkout master && git pull` antes de crear rama nueva
- CI automático en cada PR: Lint (Pint) + Tests (PHPUnit). Si falla, PR bloqueado
- Release Please: no mergear Release PR tras cada PR, acumular cambios coherentes
- Ref completa: `.claude/docs/git-workflow.md`

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
- Git & CI/CD: `.claude/docs/git-workflow.md`
- Configuración de servidores: `.claude/docs/server-config.md`
