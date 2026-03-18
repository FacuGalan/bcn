# Patrones de Componentes Livewire

## Cuándo usar cada trait

| Trait | Usar cuando... | NO usar cuando... |
|-------|----------------|-------------------|
| `SucursalAware` | El componente muestra/crea datos filtrados por sucursal | Es catálogo global (GruposOpcionales, Recetas) |
| `CajaAware` | El componente depende de caja (ventas, cobranzas) | No involucra operaciones de caja |
| Ninguno | Catálogos globales, configuración, dashboard general | Datos filtrados por sucursal |

## Plantilla base con SucursalAware

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\SucursalAware;
use App\Models\MiModelo;

class MiComponente extends Component
{
    use WithPagination, SucursalAware;

    public $search = '';
    public $showModal = false;

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
