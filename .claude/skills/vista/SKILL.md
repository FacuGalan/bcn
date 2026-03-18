---
name: vista
description: Crear vista Blade siguiendo el design system del proyecto (responsive, dark mode, cards mobile + tabla desktop, modales, toasts).
user-invocable: true
argument-hint: "[nombre-vista]"
---

# Vista — Crear Vista Blade con Design System

Tu trabajo es crear una vista Blade que siga exactamente el design system establecido en BCN Pymes.

## Antes de generar:

1. **Leer obligatorio**: `.claude/docs/design-system.md` — contiene todos los tokens de diseño, patrones y templates
2. **Leer una vista de referencia** similar al tipo que se necesita:
   - CRUD con tabla: `resources/views/livewire/articulos/gestionar-categorias.blade.php`
   - Dashboard/resumen: `resources/views/livewire/bancos/resumen-cuentas.blade.php`
   - Formulario complejo: `resources/views/livewire/ventas/nueva-venta.blade.php`

## Al ejecutar este skill:

### 1. Preguntar al usuario
- Nombre del componente Livewire asociado
- Tipo de vista: listado CRUD | formulario | dashboard | detalle
- ¿Tiene modales? ¿Cuáles?
- ¿Tiene filtros/búsqueda?
- Columnas de la tabla (si aplica)

### 2. Estructura obligatoria

SIEMPRE seguir esta estructura (de `design-system.md`):
```
div.py-4 > div.px-4.sm:px-6.lg:px-8
  ├── Header (título + descripción + botón acción)
  ├── Filtros card (si aplica)
  ├── Cards mobile (sm:hidden)
  ├── Tabla desktop (hidden sm:block)
  └── Modal(es) (@if/$showModal)
```

### 3. Reglas de diseño obligatorias

- **SIEMPRE** dark mode: cada `bg-white` lleva `dark:bg-gray-800`, cada `text-gray-900` lleva `dark:text-white`
- **SIEMPRE** responsive: cards en mobile (`sm:hidden`), tabla en desktop (`hidden sm:block`)
- **SIEMPRE** botones responsive: icono solo en mobile, icono+texto en desktop
- **SIEMPRE** usar `{{ __('texto') }}` para todo texto visible (i18n)
- **SIEMPRE** empty states con icono SVG + mensaje
- **SIEMPRE** usar colores de marca: `bcn-primary`, `bcn-secondary`, `bcn-light`
- **SIEMPRE** inputs con focus bcn-primary: `focus:border-bcn-primary focus:ring focus:ring-bcn-primary`
- **NUNCA** colores hardcoded que no sean del design system
- **NUNCA** olvidar dark mode en ningún elemento

### 4. Componentes Blade a usar

| Necesidad | Componente |
|-----------|-----------|
| Botón principal | `<x-primary-button>` o clase `bg-bcn-primary` |
| Botón secundario | `<x-secondary-button>` |
| Botón peligro | `<x-danger-button>` |
| Modal | `<x-modal name="..." :show="true">` |
| Input | clase estándar del design-system.md |
| Notificación | `$this->dispatch('notify', message: '...', type: 'success')` |

### 5. Antes de entregar

Verificar:
- [ ] Todos los textos usan `{{ __('...') }}`
- [ ] Dark mode en TODOS los elementos (bg, text, border)
- [ ] Responsive: cards mobile + tabla desktop
- [ ] Botones responsive: icono mobile + texto desktop
- [ ] Empty state cuando no hay datos
- [ ] Modales usan `<x-modal>`
- [ ] Focus de inputs usa `bcn-primary`
- [ ] Paginación con `{{ $items->links() }}`
