# Resumen Rápido - Desarrollo de Nuevos Componentes

**Para:** Desarrolladores
**Tiempo de lectura:** 2 minutos

---

## 🎯 Lo que Necesitas Saber

Cuando crees un **nuevo componente Livewire**, debes decidir:

### ¿Tu componente muestra datos filtrados por sucursal?

#### ✅ **SÍ** → Agregar 3 cosas:

```php
class MiComponente extends Component
{
    // 1️⃣ Agregar listener
    protected $listeners = [
        'sucursal-changed' => 'handleSucursalChanged',
        'sucursal-cambiada' => 'handleSucursalChanged'
    ];

    // 2️⃣ Implementar handler
    public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
    {
        // Cerrar modales si hay
        $this->showModal = false;

        // Resetear paginación si hay
        $this->resetPage();

        // Limpiar formularios si hay
        $this->resetFormulario();
    }

    // 3️⃣ Usar sucursal_activa() en render()
    public function render()
    {
        $datos = MiModelo::where('sucursal_id', sucursal_activa())->get();

        return view('...', ['datos' => $datos]);
    }
}
```

#### ❌ **NO** → No hacer nada

Si tu componente no depende de la sucursal (ej: perfil de usuario, configuración general), no necesitas agregar nada.

---

## 🚀 Opción Rápida: Usar el Trait

En lugar de copiar/pegar código, usa el trait:

```php
use App\Traits\SucursalAware;

class MiComponente extends Component
{
    use SucursalAware; // ← Todo automático

    public $showModal = false;

    // Ya no necesitas definir $listeners ni handleSucursalChanged()

    public function render()
    {
        // Usa el método del trait
        $datos = MiModelo::where('sucursal_id', $this->sucursalActual())->get();

        return view('...', ['datos' => $datos]);
    }
}
```

**El trait hace automáticamente:**
- ✅ Escucha eventos `sucursal-changed` y `sucursal-cambiada`
- ✅ Cierra modales comunes (`showModal`, `showCrearModal`, etc.)
- ✅ Resetea paginación (si usa `WithPagination`)
- ✅ Proporciona métodos útiles: `sucursalActual()`, `tieneAccesoASucursal()`, etc.

---

## 📋 Checklist Rápido

Antes de terminar tu componente:

```
□ ¿Muestra datos por sucursal? → Agregar listener
□ ¿Tiene modales? → Cerrarlos en handler
□ ¿Tiene formularios? → Limpiarlos en handler
□ ¿Usa WithPagination? → Resetear en handler
□ ¿Usa sucursal_activa() en todas las consultas?
□ Probaste cambiar de sucursal y funciona?
```

---

## 🎓 Ejemplos de Referencia

Mira estos componentes que YA lo implementan:

- **`app/Livewire/Dashboard/DashboardSucursal.php`** - Simple (sin modales)
- **`app/Livewire/Ventas/Ventas.php`** - Complejo (con POS y carrito)
- **`app/Livewire/Stock/StockInventario.php`** - Múltiples modales

---

## 📚 Documentación Completa

Para más detalles, consulta:
- `GUIA_DESARROLLO_COMPONENTES.md` - Guía completa con ejemplos
- `SISTEMA_EVENTOS_SUCURSALES.md` - Arquitectura del sistema

---

**¡Eso es todo! 🚀**

Con estos 3 puntos (listener, handler, sucursal_activa) tus componentes funcionarán perfectamente.
