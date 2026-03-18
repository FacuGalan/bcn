# Estándares del Proyecto BCN Pymes

**Fecha de creación:** 2025-11-10
**Última actualización:** 2025-11-10
**Audiencia:** Desarrolladores y Claude Code

---

## ⚠️ IMPORTANTE PARA CLAUDE CODE

**Este documento contiene los estándares OBLIGATORIOS que Claude Code debe seguir al desarrollar componentes para este proyecto.**

Cuando el usuario solicite crear nuevos componentes Livewire, **SIEMPRE** debes:

1. ✅ Seguir la **GUIA_DESARROLLO_COMPONENTES.md**
2. ✅ Implementar el sistema de eventos de sucursales si aplica
3. ✅ Usar el trait `SucursalAware` o implementar los listeners manualmente
4. ✅ Usar `sucursal_activa()` en todas las consultas que filtren por sucursal
5. ✅ Cerrar modales en el handler `handleSucursalChanged()` si el componente tiene modales
6. ✅ Preguntar al usuario si tienes dudas sobre si el componente debe reaccionar al cambio de sucursal

---

## 🏗️ Arquitectura del Proyecto

### Sistema Multi-Sucursal

Este proyecto implementa un **sistema multi-sucursal** donde:

- Cada usuario puede tener acceso a una o más sucursales
- La sucursal activa se almacena en `session('sucursal_id')`
- Al cambiar de sucursal, los componentes se actualizan **sin reload de página**
- Se usa un sistema de eventos Livewire para comunicación entre componentes

### Documentos de Referencia

| Documento | Propósito |
|-----------|-----------|
| `.claude/docs/componentes-livewire.md` | Patrones, traits, checklist y componentes de referencia |
| `.claude/docs/servicios-referencia.md` | Listado de models, services, traits por módulo |
| `.claude/ESTANDARES_PROYECTO.md` | Este archivo: estándares obligatorios |

---

## 🔧 Estándares Obligatorios para Componentes Livewire

### 1. Sistema de Eventos de Sucursales

**REGLA:** Todo componente que muestre datos filtrados por sucursal DEBE implementar el sistema de eventos.

#### Opción A: Usar el Trait (Recomendado)

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;
use App\Traits\SucursalAware; // ← Importar trait

class MiComponente extends Component
{
    use SucursalAware; // ← OBLIGATORIO para componentes con datos por sucursal

    public function render()
    {
        // Usar método del trait
        $datos = MiModelo::where('sucursal_id', $this->sucursalActual())->get();

        return view('livewire.mi-modulo.mi-componente', [
            'datos' => $datos
        ]);
    }
}
```

#### Opción B: Implementación Manual

Si por alguna razón no puedes usar el trait:

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;

class MiComponente extends Component
{
    // ✅ OBLIGATORIO: Agregar listeners
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged',
        'sucursal-cambiada' => 'handleSucursalChanged'
    ];

    // ✅ OBLIGATORIO: Implementar handler
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Cerrar modales si hay
        $this->showModal = false;

        // Resetear paginación si usa WithPagination
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        // ✅ OBLIGATORIO: Usar sucursal_activa()
        $datos = MiModelo::where('sucursal_id', sucursal_activa())->get();

        return view('livewire.mi-modulo.mi-componente', [
            'datos' => $datos
        ]);
    }
}
```

### 2. Uso del Helper `sucursal_activa()`

**REGLA:** SIEMPRE usar `sucursal_activa()` para obtener la sucursal actual.

```php
// ✅ CORRECTO
$ventas = Venta::where('sucursal_id', sucursal_activa())->get();

// ❌ INCORRECTO - No usar valores hardcodeados
$ventas = Venta::where('sucursal_id', 1)->get();

// ❌ INCORRECTO - No confiar en propiedades locales
$ventas = Venta::where('sucursal_id', $this->sucursalId)->get();
```

### 3. Creación de Registros

**REGLA:** Al crear registros, SIEMPRE incluir `sucursal_id` usando `sucursal_activa()`.

```php
public function guardar()
{
    $this->validate([...]);

    // ✅ OBLIGATORIO: Incluir sucursal_id
    MiModelo::create([
        'sucursal_id' => sucursal_activa(), // ← OBLIGATORIO
        'nombre' => $this->nombre,
        'descripcion' => $this->descripcion,
        // ... otros campos
    ]);

    $this->dispatch('notify', message: 'Registro creado exitosamente', type: 'success');
}
```

### 4. Modales

**REGLA:** Si el componente tiene modales, DEBEN cerrarse en el handler.

```php
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    // ✅ OBLIGATORIO: Cerrar todos los modales
    $this->showCrearModal = false;
    $this->showEditarModal = false;
    $this->showDetalleModal = false;
    $this->showEliminarModal = false;

    // Limpiar formularios
    $this->resetFormulario();

    // Resetear paginación
    $this->resetPage();
}
```

**RAZÓN:** Los datos del modal pueden pertenecer a la sucursal anterior.

### 5. Validaciones de Acceso

**REGLA:** Validar acceso a sucursal antes de realizar operaciones sensibles.

```php
use App\Services\SucursalService;

public function editar($id)
{
    $registro = MiModelo::findOrFail($id);

    // ✅ OBLIGATORIO: Validar que el registro pertenece a una sucursal accesible
    if (!SucursalService::tieneAccesoASucursal($registro->sucursal_id)) {
        $this->dispatch('notify',
            message: 'No tienes acceso a esta sucursal',
            type: 'error'
        );
        return;
    }

    // Continuar con la edición...
}
```

---

## 📋 Checklist de Desarrollo de Componentes

Claude Code debe verificar estos puntos antes de finalizar un componente:

### Checklist Básico

```
□ ¿El componente muestra datos filtrados por sucursal?
  └─ SI → Implementar sistema de eventos (trait o manual)
  └─ NO → Omitir sistema de eventos

□ ¿Usa sucursal_activa() en todas las consultas?

□ ¿Al crear registros incluye sucursal_id?

□ ¿Tiene modales?
  └─ SI → Cerrarlos en handleSucursalChanged()

□ ¿Usa WithPagination?
  └─ SI → Resetear en handleSucursalChanged()

□ ¿Tiene formularios con estado?
  └─ SI → Limpiarlos en handleSucursalChanged()

□ ¿Valida acceso a sucursal en operaciones sensibles?
```

### Checklist Avanzado

```
□ ¿Usa caché?
  └─ SI → Incluir sucursal en clave del caché
  └─ SI → Limpiar caché en handleSucursalChanged()

□ ¿Tiene propiedades computadas que dependen de la sucursal?
  └─ SI → Recalcularlas en handleSucursalChanged()

□ ¿Interactúa con otros componentes?
  └─ SI → Asegurar que los eventos se propaguen correctamente

□ ¿Tiene operaciones asíncronas?
  └─ SI → Validar que la sucursal no cambió durante la operación
```

---

## 🚨 Errores Comunes a Evitar

### ❌ Error 1: No implementar listeners

```php
// ❌ MAL - El componente no se actualizará al cambiar sucursal
class Ventas extends Component
{
    public function render()
    {
        $ventas = Venta::where('sucursal_id', sucursal_activa())->get();
        return view('livewire.ventas', ['ventas' => $ventas]);
    }
}
```

```php
// ✅ BIEN
class Ventas extends Component
{
    use SucursalAware; // ← Agregar trait

    public function render()
    {
        $ventas = Venta::where('sucursal_id', $this->sucursalActual())->get();
        return view('livewire.ventas', ['ventas' => $ventas]);
    }
}
```

### ❌ Error 2: Usar valores hardcodeados

```php
// ❌ MAL
$ventas = Venta::where('sucursal_id', 1)->get();

// ✅ BIEN
$ventas = Venta::where('sucursal_id', sucursal_activa())->get();
```

### ❌ Error 3: No cerrar modales

```php
// ❌ MAL - Los modales quedan abiertos con datos de otra sucursal
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    $this->resetPage();
    // No cierra modales
}

// ✅ BIEN
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    $this->showModal = false;
    $this->showEditarModal = false;
    $this->resetPage();
}
```

### ❌ Error 4: No limpiar estado

```php
// ❌ MAL - El carrito tiene productos de otra sucursal
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    $this->resetPage();
    // No limpia $this->carrito
}

// ✅ BIEN
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    $this->resetPage();
    $this->carrito = []; // Limpiar carrito
    $this->resetFormulario();
}
```

---

## 🎯 Plantilla Base Recomendada

Claude Code debe usar esta plantilla al crear nuevos componentes:

```php
<?php

namespace App\Livewire\[Modulo];

use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\SucursalAware;
use App\Models\[Modelo];
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * Componente: [Nombre del Componente]
 *
 * RESPONSABILIDADES:
 * - [Describir responsabilidades]
 *
 * FASE: [Número de fase]
 */
class [NombreComponente] extends Component
{
    use WithPagination, SucursalAware;

    // Propiedades de búsqueda/filtros
    public $search = '';

    // Propiedades de modales
    public $showModal = false;

    // Propiedades de formulario
    public $registroId = null;

    /**
     * Hook personalizado para cambio de sucursal
     * (Opcional, solo si necesitas comportamiento específico)
     */
    protected function onSucursalChanged($sucursalId, $sucursalNombre)
    {
        // Lógica personalizada aquí
        $this->search = '';
    }

    public function render()
    {
        $datos = [Modelo]::where('sucursal_id', $this->sucursalActual())
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', "%{$this->search}%");
            })
            ->paginate(10);

        return view('livewire.[modulo].[nombre-componente]', [
            'datos' => $datos
        ]);
    }

    public function guardar()
    {
        $this->validate([
            // Validaciones
        ]);

        [Modelo]::create([
            'sucursal_id' => $this->sucursalActual(), // ← OBLIGATORIO
            // ... otros campos
        ]);

        $this->dispatch('notify', message: 'Guardado exitosamente', type: 'success');
        $this->showModal = false;
    }
}
```

---

## 🔍 Ejemplos de Referencia

Claude Code puede consultar estos archivos como referencia:

### Componentes Simples
- `app/Livewire/Dashboard/DashboardSucursal.php` - Dashboard básico
- Listener: ✅ | Modales: ❌ | Complejidad: Baja

### Componentes Complejos
- `app/Livewire/Ventas/Ventas.php` - POS con carrito
- Listener: ✅ | Modales: ✅ | Estado complejo: ✅ | Complejidad: Alta

- `app/Livewire/Stock/StockInventario.php` - Gestión de stock
- Listener: ✅ | Modales: ✅ (múltiples) | Complejidad: Media

### Componentes de Sistema
- `app/Livewire/DynamicMenu.php` - Menú dinámico
- Listener: ✅ | Caché: ✅ | Caso especial

---

## Orden de Consulta

1. **Primero:** Leer este archivo (`ESTANDARES_PROYECTO.md`)
2. **Segundo:** Consultar `.claude/docs/componentes-livewire.md` para patrones y checklist
3. **Tercero:** Ver ejemplos en los componentes existentes
4. **Cuarto:** Si hay dudas, preguntar al usuario

---

## ✅ Compromiso de Claude Code

Al desarrollar componentes para este proyecto, Claude Code se compromete a:

1. ✅ Leer este documento antes de crear cualquier componente nuevo
2. ✅ Seguir los estándares establecidos
3. ✅ Usar la plantilla base recomendada
4. ✅ Implementar el sistema de eventos si aplica
5. ✅ Validar el checklist antes de finalizar
6. ✅ Preguntar al usuario si hay ambigüedad
7. ✅ Documentar cualquier desviación de los estándares con justificación

---

## 🔄 Actualización de Este Documento

Este documento debe actualizarse cuando:
- Se agreguen nuevos estándares al proyecto
- Se identifiquen patrones comunes que deban estandarizarse
- Se descubran errores comunes que deban documentarse
- Cambien las tecnologías o arquitectura del proyecto

---

**Última revisión:** 2025-11-10
**Versión:** 1.0.0
**Estado:** ✅ Vigente

---

**FIN DEL DOCUMENTO**
