# Guía de Desarrollo - Componentes Livewire con Soporte de Sucursales

**Fecha:** 2025-11-10
**Versión:** 1.0.0
**Audiencia:** Desarrolladores

---

## 🎯 Objetivo

Esta guía te ayudará a crear nuevos componentes Livewire que se integren correctamente con el **sistema de cambio de sucursales sin reload**.

---

## 📋 Checklist para Nuevos Componentes

### ✅ Siempre Hacer

1. **Usar el helper `sucursal_activa()`** para obtener la sucursal actual
2. **Filtrar datos por sucursal** en todas las consultas
3. **Agregar listener `sucursal-changed`** si el componente muestra datos filtrados por sucursal
4. **Implementar método handler** para limpiar estado al cambiar sucursal

### ⚠️ Casos Especiales

- Si tu componente tiene **modales abiertos** → Ciérralos en el handler
- Si tu componente tiene **formularios** → Decide si limpiarlos o mantenerlos
- Si tu componente tiene **datos en caché** → Limpia el caché en el handler

---

## 🔧 Plantilla Base para Nuevos Componentes

### Opción 1: Componente Simple (Sin Modales)

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MiModelo;

/**
 * Componente: Mi Nuevo Componente
 *
 * Descripción de lo que hace el componente
 */
class MiComponente extends Component
{
    use WithPagination;

    // Propiedades
    public $search = '';
    public $filtro = 'all';

    /**
     * 🔔 IMPORTANTE: Agregar listener para cambio de sucursal
     */
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged',
        'sucursal-cambiada' => 'handleSucursalChanged'
    ];

    /**
     * 🔔 IMPORTANTE: Maneja el cambio de sucursal
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Opción 1: No hacer nada (el componente se re-renderizará automáticamente)
        // Los datos se actualizarán porque render() usa sucursal_activa()

        // Opción 2: Resetear página de paginación
        $this->resetPage();
    }

    public function render()
    {
        // 🔔 IMPORTANTE: Usar sucursal_activa() para filtrar
        $datos = MiModelo::where('sucursal_id', sucursal_activa())
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', "%{$this->search}%");
            })
            ->when($this->filtro !== 'all', function ($query) {
                $query->where('estado', $this->filtro);
            })
            ->paginate(10);

        return view('livewire.mi-modulo.mi-componente', [
            'datos' => $datos
        ]);
    }
}
```

---

### Opción 2: Componente con Modales y Estado

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MiModelo;
use App\Services\MiServicio;

/**
 * Componente: Mi Componente con Modales
 */
class MiComponenteComplejo extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public $search = '';

    // Propiedades de modales
    public $showCrearModal = false;
    public $showEditarModal = false;
    public $showDetalleModal = false;

    // Propiedades de formulario
    public $registroId = null;
    public $nombre = '';
    public $descripcion = '';

    // Servicios
    protected $miServicio;

    /**
     * 🔔 IMPORTANTE: Agregar listener para cambio de sucursal
     */
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged',
        'sucursal-cambiada' => 'handleSucursalChanged'
    ];

    public function boot(MiServicio $miServicio)
    {
        $this->miServicio = $miServicio;
    }

    /**
     * 🔔 IMPORTANTE: Maneja el cambio de sucursal
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // 1. Cerrar todos los modales abiertos
        $this->showCrearModal = false;
        $this->showEditarModal = false;
        $this->showDetalleModal = false;

        // 2. Limpiar formulario (los datos pueden ser de otra sucursal)
        $this->resetFormulario();

        // 3. Resetear paginación
        $this->resetPage();

        // El componente se re-renderizará automáticamente con datos de la nueva sucursal
    }

    protected function resetFormulario()
    {
        $this->registroId = null;
        $this->nombre = '';
        $this->descripcion = '';
    }

    public function render()
    {
        // 🔔 IMPORTANTE: Siempre filtrar por sucursal_activa()
        $datos = MiModelo::where('sucursal_id', sucursal_activa())
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', "%{$this->search}%");
            })
            ->paginate(10);

        return view('livewire.mi-modulo.mi-componente-complejo', [
            'datos' => $datos
        ]);
    }

    public function abrirModalCrear()
    {
        $this->resetFormulario();
        $this->showCrearModal = true;
    }

    public function guardar()
    {
        $this->validate([
            'nombre' => 'required|string|max:255',
        ]);

        // 🔔 IMPORTANTE: Siempre usar sucursal_activa() al crear registros
        $datos = [
            'sucursal_id' => sucursal_activa(),
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];

        MiModelo::create($datos);

        $this->dispatch('notify', message: 'Registro creado exitosamente', type: 'success');
        $this->showCrearModal = false;
        $this->resetFormulario();
    }
}
```

---

## 📝 Reglas de Oro

### 1. Siempre Usa `sucursal_activa()`

```php
// ✅ CORRECTO
$ventas = Venta::where('sucursal_id', sucursal_activa())->get();

// ❌ INCORRECTO (no se actualizará al cambiar sucursal)
$ventas = Venta::where('sucursal_id', 1)->get();

// ❌ INCORRECTO (puede ser obsoleto si se guardó antes del cambio)
$ventas = Venta::where('sucursal_id', $this->sucursalSeleccionada)->get();
```

### 2. Agrega el Listener en Componentes que Muestran Datos

```php
// ✅ SI tu componente muestra datos filtrados por sucursal
protected $listeners = [
    'sucursal-changed' => 'handleSucursalChanged',
    'sucursal-cambiada' => 'handleSucursalChanged'
];

// ❌ NO si tu componente no depende de la sucursal (ej: perfil de usuario)
// En este caso puedes omitir el listener
```

### 3. Cierra Modales en el Handler

```php
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    // ✅ Siempre cierra modales cuando cambies de sucursal
    $this->showModal = false;
    $this->showEditarModal = false;

    // ✅ Limpia datos del formulario
    $this->resetFormulario();

    // ✅ Resetea paginación si usas WithPagination
    $this->resetPage();
}
```

### 4. No Confíes en Datos Locales Después del Cambio

```php
// ❌ MAL - Estos datos pueden ser de la sucursal anterior
public $articulosCargados = [];

public function mount()
{
    $this->articulosCargados = Articulo::where('sucursal_id', sucursal_activa())->get();
}

// ✅ BIEN - Consulta en render() siempre tiene datos frescos
public function render()
{
    $articulos = Articulo::where('sucursal_id', sucursal_activa())->get();
    return view('...', ['articulos' => $articulos]);
}
```

---

## 🧪 Cómo Probar tu Componente

### Checklist de Pruebas

```
1. ✅ Navega a tu componente
2. ✅ Verifica que muestre datos de la sucursal actual
3. ✅ Cambia de sucursal usando el selector del header
4. ✅ Verifica que:
   - Los datos se actualizan a la nueva sucursal
   - No hay parpadeo
   - Los modales se cierran (si estaban abiertos)
   - Aparece la notificación de cambio
5. ✅ Prueba crear un registro → Debe crearse en la nueva sucursal
6. ✅ Cambia de sucursal nuevamente → Debe funcionar múltiples veces
```

---

## 🚀 Trait Reutilizable (Opcional)

Si quieres simplificar el código, puedes crear un trait:

### Crear el Trait

**Archivo:** `app/Traits/SucursalAware.php`

```php
<?php

namespace App\Traits;

trait SucursalAware
{
    /**
     * Listener para cambio de sucursal
     */
    protected function getListeners()
    {
        return array_merge(
            parent::getListeners() ?? [],
            [
                'sucursal-changed' => 'handleSucursalChanged',
                'sucursal-cambiada' => 'handleSucursalChanged'
            ]
        );
    }

    /**
     * Maneja el cambio de sucursal
     * Puede ser sobrescrito en el componente si necesitas comportamiento específico
     */
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Resetear paginación si existe
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }

        // Cerrar modales comunes si existen
        $modalProperties = [
            'showModal',
            'showCrearModal',
            'showEditarModal',
            'showDetalleModal',
            'showEliminarModal'
        ];

        foreach ($modalProperties as $prop) {
            if (property_exists($this, $prop)) {
                $this->$prop = false;
            }
        }

        // Hook para implementar en el componente
        if (method_exists($this, 'onSucursalChanged')) {
            $this->onSucursalChanged($sucursalId, $sucursalNombre);
        }
    }

    /**
     * Obtiene la sucursal actual
     */
    protected function sucursalActual(): int
    {
        return sucursal_activa();
    }
}
```

### Usar el Trait

```php
<?php

namespace App\Livewire\MiModulo;

use Livewire\Component;
use App\Traits\SucursalAware;

class MiComponente extends Component
{
    use SucursalAware; // ← Usa el trait

    public $search = '';
    public $showModal = false;

    // Ya no necesitas definir $listeners ni handleSucursalChanged()
    // El trait se encarga automáticamente

    // Si necesitas comportamiento personalizado:
    protected function onSucursalChanged($sucursalId, $sucursalNombre)
    {
        // Tu lógica personalizada aquí
        $this->search = ''; // Limpiar búsqueda, por ejemplo
    }

    public function render()
    {
        $datos = MiModelo::where('sucursal_id', $this->sucursalActual()) // Método del trait
            ->get();

        return view('...', ['datos' => $datos]);
    }
}
```

---

## 📊 Comparativa de Enfoques

| Enfoque | Ventajas | Desventajas | Cuándo Usar |
|---------|----------|-------------|-------------|
| **Manual** (copiar/pegar código) | Simple, explícito, fácil de entender | Código repetitivo | Pocos componentes |
| **Trait** | DRY, consistente, menos código | Más abstracto | Muchos componentes |

---

## 🎓 Ejemplos Reales del Sistema

### Componentes que YA implementan esto:

1. **`DashboardSucursal.php`**
   - Listener: ✅
   - Handler: Actualiza `$sucursalSeleccionada`
   - Sin modales

2. **`Ventas.php`**
   - Listener: ✅
   - Handler: Cierra modales + Limpia carrito
   - Complejo (POS con carrito)

3. **`StockInventario.php`**
   - Listener: ✅
   - Handler: Actualiza sucursal + Cierra modales
   - Múltiples modales

4. **`DynamicMenu.php`**
   - Listener: ✅
   - Handler: Limpia caché + Re-inicializa
   - Caso especial (menú)

**Puedes usar estos como referencia** cuando desarrolles nuevos componentes.

---

## ❓ Preguntas Frecuentes

### ¿Qué pasa si NO agrego el listener?

- El componente NO se actualizará cuando cambies de sucursal
- Seguirá mostrando datos de la sucursal anterior
- El usuario tendría que refrescar manualmente (F5)

### ¿Necesito el listener si mi componente no muestra datos por sucursal?

No. Por ejemplo:
- Perfil de usuario
- Configuración global del comercio
- Componentes de UI sin datos

### ¿Puedo tener datos en `mount()` o deben estar en `render()`?

- **Datos que cambian por sucursal:** En `render()` para que se actualicen
- **Datos estáticos/globales:** En `mount()` está bien

### ¿Qué pasa con los datos en caché?

Si tu componente usa caché (ej: `cache()->remember()`), asegúrate de:
1. Incluir la sucursal en la clave del caché
2. Limpiar el caché en `handleSucursalChanged()`

Ejemplo:
```php
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    // Limpiar caché específico
    cache()->forget('mi_componente_datos_' . auth()->id() . '_' . $sucursalId);

    $this->resetPage();
}
```

---

## ✅ Checklist Final

Antes de dar por terminado un nuevo componente:

```
□ Usa sucursal_activa() en todas las consultas
□ Agrega listener si muestra datos por sucursal
□ Implementa handleSucursalChanged() si tiene modales o estado
□ Cierra modales en el handler
□ Limpia formularios en el handler
□ Resetea paginación en el handler
□ Prueba cambiar de sucursal múltiples veces
□ Verifica que no hay parpadeo
□ Verifica que los datos se actualizan correctamente
```

---

## 📚 Documentos Relacionados

- `SISTEMA_EVENTOS_SUCURSALES.md` - Arquitectura completa del sistema de eventos
- `SISTEMA_ACCESO_SUCURSALES.md` - Sistema de permisos por sucursal
- `OPTIMIZACIONES_SUCURSALES.md` - Optimizaciones de rendimiento

---

**¡Listo para desarrollar! 🚀**

Si tienes dudas o encuentras un caso especial, consulta los componentes existentes como referencia o pregunta.

---

**FIN DEL DOCUMENTO**
