# Patrones de Componentes Livewire

## Cuándo usar cada trait

| Trait | Usar cuando... | NO usar cuando... |
|-------|----------------|-------------------|
| `SucursalAware` | El componente muestra/crea datos filtrados por sucursal | Es catálogo global (GruposOpcionales, Recetas) |
| `CajaAware` | El componente depende de caja (ventas, cobranzas) | No involucra operaciones de caja |
| Ninguno | Catálogos globales, configuración, dashboard general | Datos filtrados por sucursal |

## Lazy Loading (OBLIGATORIO para componentes full-page)

Todo componente registrado como ruta en `routes/web.php` DEBE usar `#[Lazy]` con un skeleton placeholder:

1. Agregar `use Livewire\Attributes\Lazy;` en imports
2. Agregar `#[Lazy]` antes de `class`
3. Agregar método `placeholder()` antes de `mount()` (o `render()` si no hay mount)

### Skeletons reutilizables

| Tipo de vista | Skeleton | Props |
|---------------|----------|-------|
| Tabla/listado | `<x-skeleton.page-table />` | `:statCards`, `:filterCount`, `:columns`, `:rows` |
| Dashboard/métricas | `<x-skeleton.page-dashboard />` | `:statCards`, `:sections` |
| Formulario/wizard | `<x-skeleton.page-form />` | `:tabs`, `:fields`, `:hasBackButton` |

### Cuándo NO usar #[Lazy]

- Componentes embebidos (`<livewire:>` dentro de otros): CajaSelector, SucursalSelector, etc.
- Componentes de auth (Logout, LoginForm)
- Sub-componentes dentro de wizards

## Plantilla base con SucursalAware

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\SucursalAware;
use App\Models\MiModelo;

#[Lazy]
class MiComponente extends Component
{
    use WithPagination, SucursalAware;

    public $search = '';
    public $showModal = false;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :filterCount="2" :columns="5" />
        HTML;
    }

    // Hook opcional para lógica extra al cambiar sucursal
    protected function onSucursalChanged($sucursalId, $sucursalNombre)
    {
        $this->search = '';
    }

    public function render()
    {
        $datos = MiModelo::where('sucursal_id', $this->sucursalActual())
            ->when($this->search, fn($q) => $q->where('nombre', 'like', "%{$this->search}%"))
            ->paginate(10);

        return view('livewire.mi-modulo.mi-componente', ['datos' => $datos]);
    }

    public function guardar()
    {
        $this->validate([/* ... */]);

        MiModelo::create([
            'sucursal_id' => $this->sucursalActual(), // OBLIGATORIO
            // ... otros campos
        ]);

        $this->dispatch('notify', message: 'Guardado exitosamente', type: 'success');
        $this->showModal = false;
    }
}
```

## Checklist básico

- [ ] `#[Lazy]` + `placeholder()` con skeleton (si es full-page)
- [ ] Sucursal-aware → Implementar SucursalAware trait (o listeners manuales)
- [ ] Usa `sucursal_activa()` / `$this->sucursalActual()` en todas las queries
- [ ] Al crear registros incluye `sucursal_id`
- [ ] Modales se cierran en `handleSucursalChanged()`
- [ ] WithPagination → `resetPage()` en handler
- [ ] Formularios con estado → limpiarlos en handler

## Checklist avanzado

- [ ] Caché incluye sucursal en la clave
- [ ] Propiedades computadas se recalculan al cambiar sucursal
- [ ] Validar acceso a sucursal en operaciones sensibles con `SucursalService::tieneAccesoASucursal()`

## Componentes de referencia

| Complejidad | Componente | Traits | Modales |
|-------------|-----------|--------|---------|
| Baja | `Livewire/Bancos/ResumenCuentas.php` | SucursalAware | No |
| Media | `Livewire/Stock/StockInventario.php` | SucursalAware | Múltiples |
| Alta | `Livewire/Ventas/NuevaVenta.php` | SucursalAware+CajaAware | Sí |
| Global | `Livewire/Articulos/GestionarGruposOpcionales.php` | Ninguno | Sí |
