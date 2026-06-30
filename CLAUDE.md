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
- **Smoke test OBLIGATORIO**: TODO componente Livewire nuevo debe tener un test `Livewire::test(Componente::class)->assertOk()` en el `Smoke{Modulo}Test.php` correspondiente. El skill `/nuevo-componente` lo agrega automáticamente. Detecta errores de mount, sintaxis Blade, variables indefinidas. Sin smoke test, el componente NO se considera terminado.
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
- Usar el agente `@docs-sync` para actualizar automáticamente ambos archivos analizando el diff del branch
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
| Botón inline con icono que aparece en hover | `/boton-inline-hover` | Contenido visible, icono `opacity-0 group-hover:opacity-100`, todo el botón es clickeable |
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
6. **Documentación**: Al crear PR, si se agregó/modificó funcionalidad → invocar `@docs-sync` para actualizar docs automáticamente

### Testing
- BDs dedicadas: `config_test`, `pymes_test` (MySQL, no SQLite)
- Traits: `WithTenant`, `WithSucursal`, `WithCaja` para contexto multi-tenant en tests
- **PROHIBIDO `RefreshDatabase`**: ejecuta `migrate:fresh` que DROPEA todas las tablas. Si la config se envenena, borra la BD real. Usar:
  - Tests con contexto tenant: `WithTenant` (tablas persisten, DELETE selectivo)
  - Tests genericos (auth, sin tenant): `DatabaseTransactions` con `protected $connectionsToTransact = ['config', 'pymes']`
- **Defensa en TestCase**: `guardAgainstRealDatabases()` corre ANTES de `parent::setUp()` y aborta si `DB_DATABASE`/`DB_CONFIG_DATABASE` no son `*_test`. No quitarla nunca.
- Prioridad: Services (dinero/stock/ledger) > Models (scopes) > Livewire (CRUD)
- `/sdd-verify` ejecuta tests reales y genera Spec Compliance Matrix
- Ref: `.claude/docs/testing-patterns.md`

---

## Git & CI/CD

- **Conventional Commits OBLIGATORIO**: `feat:`, `fix:`, `refactor:`, `perf:`, `test:`, `docs:`, `ci:`, `chore:` — ver detalle en ref
- **Nunca push directo a master** — todo vía branch + Pull Request
- **Al iniciar sesión**: verificar rama actual. Si no hay trabajo en curso, `git checkout master && git pull` antes de crear rama nueva
- **ANTES de crear rama nueva desde master** (OBLIGATORIO): ejecutar `gh pr list --state open --author @me`. Si hay PRs abiertos:
  - Listarlos al usuario y preguntar: ¿mergear primero?, ¿basar nueva rama en el PR abierto?, ¿o continuar desde master a sabiendas?
  - **NUNCA** crear rama nueva silenciosamente si hay PRs propios abiertos sin mergear — se pierde visibilidad de cambios ya hechos y aparecen conflictos evitables
- CI automático en cada PR: Lint (Pint) + Tests (PHPUnit). Si falla, PR bloqueado
- Release Please: no mergear Release PR tras cada PR, acumular cambios coherentes
- Ref completa: `.claude/docs/git-workflow.md`

---

## Deploy a producción (LEER al deployar — incluido el Claude del lado del server)

Secuencia OBLIGATORIA tras `git pull` en el server. Saltarse un paso reintroduce lentitud o sirve vistas viejas:

```bash
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # incluye tenant (itera todos los comercios)
npm ci && npm run build            # public/build gitignored → se compila acá; cambia el hash del SW
php artisan deploy:warm            # cachés SEGURAS: view+route+event+ICONS (un comando, ver abajo)
sudo systemctl reload php8.2-fpm   # OBLIGATORIO: OPcache (validate_timestamps=0) NO toma el código/cachés nuevos sin esto
```

**Reglas que NO se negocian (cada una causó un problema real):**
- **SIEMPRE `php artisan deploy:warm`** (bundlea `view:cache` + `route:cache` + `event:cache` + **`icons:cache`**). El `icons:cache` es CRÍTICO: sin él, blade-icons escanea ~1200 SVGs de heroicons **en cada request** → ~600 ms de lentitud sistémica en TODO el sistema. Mismo patrón que el Volt mount.
- **NUNCA `php artisan optimize`** ni `config:cache`: serializa el `.env` real → riesgo de envenenar tests (incidente 2026-05-04). El hook `post-merge` corre `optimize:clear` a propósito.
- **El `reload php-fpm` es el paso que hace efectivo el deploy.** Sin él, OPcache sigue ejecutando el código viejo y "deployás pero no cambia nada". El SAPI web es `php8.2-fpm` (el CLI es 8.3).
- Si una vista "se ve mal" tras deploy: es caché del Service Worker del cliente, se auto-sana al primer load (o probar en incógnito). Requiere que el deploy haya corrido `npm run build`.
- Playbook completo + diagnóstico de lentitud: `.claude/docs/deploy-playbook.md`. Config de server: `.claude/docs/server-config.md`.

---

## Comandos Útiles
- `php artisan comercio:provision --nombre= --database= --mail=`
- `php artisan menu:create` (interactivo)
- `php artisan optimize:clear`
- `php artisan deploy:warm` (warm de cachés seguras tras deploy: view+route+event+icons; NO config:cache)
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
