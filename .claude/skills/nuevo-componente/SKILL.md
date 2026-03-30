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

## Reglas
- SIEMPRE leer ESTANDARES_PROYECTO.md antes de generar
- SIEMPRE leer un componente de referencia existente
- NO inventar patrones nuevos — seguir los existentes
- Si el componente NO es sucursal-aware, NO agregar SucursalAware
- SIEMPRE actualizar docs/ al finalizar
