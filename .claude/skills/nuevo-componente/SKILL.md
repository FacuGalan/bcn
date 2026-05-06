---
name: nuevo-componente
description: Crear componente Livewire siguiendo estándares del proyecto (SucursalAware, CajaAware, patrones).
user-invocable: true
argument-hint: "[nombre-componente]"
---

# Nuevo Componente — Livewire con Estándares

Tu trabajo es crear un componente Livewire que cumpla con todos los estándares de BCN Pymes.

## Al ejecutar este skill:

### 1. Recopilar información

Preguntar al usuario:
- **Nombre del componente** (ej: `GestionarClientes`)
- **Módulo/directorio** (ej: `Clientes` → `app/Livewire/Clientes/`)
- **Es sucursal-aware?** → usa trait `SucursalAware`
- **Es caja-aware?** → usa trait `CajaAware`
- **Tiene paginación?** → usa `WithPagination`
- **Qué modales necesita?** (crear, editar, eliminar, detalle)
- **Modelo(s) principal(es)** que consulta
- **Tiene búsqueda/filtros?**

### 2. Leer estándares

- Leer `.claude/ESTANDARES_PROYECTO.md`
- Leer `.claude/docs/componentes-livewire.md`

### 3. Elegir componente de referencia

Según la complejidad, leer un componente existente como referencia:

| Complejidad | Componente de referencia |
|-------------|-------------------------|
| Simple (listado sin modales) | `app/Livewire/Bancos/ResumenCuentas.php` |
| Media (CRUD con modales) | `app/Livewire/Articulos/GestionarCategorias.php` |
| Alta (estado complejo) | `app/Livewire/Ventas/NuevaVenta.php` |
| Global (sin sucursal) | `app/Livewire/Articulos/GestionarGruposOpcionales.php` |

### 4. Generar componente

**Clase PHP** en `app/Livewire/{Modulo}/{NombreComponente}.php`:
- Namespace correcto
- `use Livewire\Attributes\Lazy;` en imports
- `#[Lazy]` antes de la declaración de clase (OBLIGATORIO si es full-page)
- Método `placeholder()` antes de `mount()` con skeleton apropiado:
  - Tabla/listado: `<x-skeleton.page-table :filterCount="N" :columns="N" />`
  - Dashboard: `<x-skeleton.page-dashboard :statCards="N" :sections="N" />`
  - Formulario/wizard: `<x-skeleton.page-form :tabs="N" :fields="N" />`
- Traits según lo definido (SucursalAware, CajaAware, WithPagination)
- Propiedades para búsqueda, modales, formulario
- Hook `onSucursalChanged()` si tiene lógica extra al cambiar sucursal
- Método `render()` con query filtrada por `$this->sucursalActual()` si aplica
- Métodos CRUD si tiene modales
- Validación de acceso a sucursal en operaciones sensibles

**Vista Blade** en `resources/views/livewire/{modulo}/{nombre-componente}.blade.php`:
- Layout responsive con Tailwind
- Tabla/listado con datos
- Modales para crear/editar si aplica
- Mensajes de notificación
- Estados vacíos

### 5. Validar checklist

Antes de terminar, verificar:
```
[ ] #[Lazy] + placeholder() con skeleton (si es full-page)
[ ] SucursalAware trait aplicado (si corresponde)
[ ] Usa sucursalActual() / sucursal_activa() en queries
[ ] Al crear registros incluye sucursal_id
[ ] Modales se cierran en handleSucursalChanged
[ ] WithPagination → resetPage() en handler
[ ] Formularios se limpian al cambiar sucursal
[ ] Validación de acceso a sucursal en operaciones sensibles
```

### 6. Actualizar documentación

Después de crear el componente, actualizar:
- **`docs/manual-usuario.md`**: Agregar sección del nuevo componente en el módulo correspondiente (acciones, filtros, modales, campos)
- **`docs/ai-knowledge-base.md`**: Si el componente introduce tablas/lógica nueva, agregar al modelo de datos y lógica de negocio

### 7. Agregar smoke test (OBLIGATORIO)

Todo componente Livewire nuevo DEBE tener al menos un smoke test que verifique
que monta sin errores. Esto detecta: errores en `mount()`, sintaxis Blade
inválida, variables indefinidas en la vista, dependencias rotas.

**Dónde agregarlo**: en el archivo `tests/Feature/Livewire/{Modulo}/Smoke{Modulo}Test.php`
correspondiente a la carpeta del componente. Si NO existe, crearlo siguiendo
el patrón del proyecto (ver `tests/Feature/Livewire/Articulos/SmokeArticulosTest.php`
como referencia simple, o `SmokeCajasTest.php` para componentes CajaAware).

**Template del test a agregar**:
```php
public function test_{nombre_componente}_monta(): void
{
    Livewire::test(NombreComponente::class)->assertOk();
}
```

**Setup del archivo (si lo creás nuevo)**:
- `WithTenant` siempre
- `WithSucursal` si el componente es SucursalAware o si su carpeta lo requiere
- `WithCaja` si el componente es CajaAware
- `Livewire::withoutLazyLoading()` en el `setUp()` (clave si el componente usa `#[Lazy]`)

**Validar**: `php artisan test --filter=Smoke{Modulo}Test` debe pasar antes
de confirmar al usuario.

## Reglas
- SIEMPRE leer ESTANDARES_PROYECTO.md antes de generar
- SIEMPRE leer un componente de referencia existente
- NO inventar patrones nuevos — seguir los existentes
- Si el componente NO es sucursal-aware, NO agregar SucursalAware
- SIEMPRE actualizar docs/ al finalizar
- **PROHIBIDO confirmar el componente como terminado sin que su smoke test pase** (paso 7)
- Permisos: usar `auth()->user()?->hasPermissionTo('func.X')`, NUNCA `can()` (User no usa HasRoles trait)
- `SucursalAware`: el método del trait se llama `$this->sucursalActual()` — NO `obtenerSucursalActual()` (ese es propio del componente `Ventas.php` y no está en el trait)
