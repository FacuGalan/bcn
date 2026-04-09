---
name: combobox-alta-rapida
description: Crear combobox con búsqueda inteligente y botón de alta rápida inline, siguiendo el patrón visual y funcional del proyecto.
user-invocable: true
argument-hint: "[entidad a buscar, ej: categoría, cliente, proveedor]"
---

# Combobox con Alta Rápida — Patrón UI Estándar

Tu trabajo es crear un combobox (select con búsqueda) que incluya un botón de alta rápida inline, siguiendo **exactamente** el patrón visual y funcional establecido en el proyecto.

## Antes de generar:

1. **Leer referencia obligatoria**: `resources/views/livewire/articulos/gestionar-articulos.blade.php` — buscar la sección "Categoría (combobox con búsqueda)"
2. **Leer el componente Livewire** donde se va a agregar el combobox
3. **Leer el modelo** de la entidad que se busca (para conocer campos disponibles)

## Al ejecutar este skill:

### 1. Preguntar al usuario

- **Entidad a buscar** (ej: categoría, cliente, proveedor)
- **Campos a mostrar** en cada opción del dropdown (ej: nombre, código, color dot)
- **Campos sobre los que se busca** (ej: nombre, código — la búsqueda filtra por todos)
- **Campos del alta rápida** (ej: nombre y prefijo para categoría, nombre y CUIT para cliente)
- **Valores por defecto** al crear (ej: color azul, activo=true)
- **Propiedad Livewire** que almacena el ID seleccionado (ej: `categoria_id`)

### 2. Estructura visual OBLIGATORIA

#### Input + botón unidos
```html
<div class="mt-1 flex">
    <!-- Input de búsqueda: rounded-l-md (sin borde derecho) -->
    <input class="block w-full rounded-l-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-8" />

    <!-- Botón +: rounded-r-md, indigo-600 (se une al input) -->
    <button class="flex-shrink-0 inline-flex items-center justify-center px-2 self-stretch bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
    </button>
</div>
```

**REGLAS visuales:**
- Sin gap entre input y botón (`flex` sin `gap`)
- Input: `rounded-l-md` (solo bordes izquierdos redondeados)
- Botón: `rounded-r-md` + `bg-indigo-600` + `hover:bg-indigo-700` + `text-white`
- Botón: `self-stretch` para igualar alto del input, `px-2` para padding horizontal
- Icono `+` (M12 4v16m8-8H4) de 4x4

#### Botón X para limpiar selección
- Posición absoluta dentro del input (`absolute inset-y-0 right-0 pr-2`)
- Solo visible cuando hay selección (`x-show="$wire.get('propiedad_id')"`)
- Color: `text-gray-400 hover:text-gray-600 dark:hover:text-gray-300`

#### Dropdown de resultados
- `absolute z-50 mt-1 w-full max-h-48 overflow-auto`
- `bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg`
- Transiciones: `ease-out duration-100` enter, `ease-in duration-75` leave
- Cada opción: `flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-300`
- Highlight activo: `bg-bcn-primary/10 dark:bg-bcn-primary/20`

#### Mini-formulario de alta rápida
- Fondo: `bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md`
- Título: `text-xs font-medium text-blue-700 dark:text-blue-300`
- Botón Crear: `bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-md`
- Botón Cancelar: `bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-xs rounded-md`

### 3. Funcionalidad OBLIGATORIA (Alpine.js)

#### Búsqueda inteligente
- Busca **subcadenas** (escribir "ida" encuentra "Beb**ida**s")
- Soporta **múltiples términos** separados por espacio (todos deben coincidir)
- Búsqueda sobre los campos definidos por el usuario (no solo nombre)

```javascript
get filtered() {
    if (!this.search) return this.items;
    const terms = this.search.toLowerCase().split(/\s+/);
    return this.items.filter(item => {
        // Concatenar todos los campos de búsqueda
        const searchable = [item.campo1, item.campo2].filter(Boolean).join(' ').toLowerCase();
        return terms.every(t => searchable.includes(t));
    });
}
```

#### Navegación con teclado
- **Flechas arriba/abajo**: navegar opciones con highlight visual
- **Enter**: seleccionar opción resaltada. Si no se navegó, seleccionar la **primera**
- **Escape**: cerrar dropdown
- **Scroll automático**: al navegar con flechas, hacer scroll para mantener visible la opción resaltada

```javascript
scrollToHighlighted() {
    this.$nextTick(() => {
        const dd = this.$refs.dropdown;
        if (!dd) return;
        const items = dd.querySelectorAll('button');
        if (items[this.highlightIndex]) items[this.highlightIndex].scrollIntoView({ block: 'nearest' });
    });
}
```

**IMPORTANTE**: Usar `dd.querySelectorAll('button')` para el scroll, NO `dd.children` (porque `<template x-for>` deja un nodo invisible que desfasa el índice).

#### Foco y UX
- Al hacer **foco** en el input: abrir dropdown, limpiar texto para buscar
- Al **click away**: cerrar dropdown, restaurar nombre de la selección actual
- Al **seleccionar**: setear `$wire` propiedad, mostrar nombre en input, cerrar dropdown

#### Alta rápida
- Al abrir formulario: **foco automático** en primer input (`x-init="$nextTick(() => $refs.nombre.focus())"`)
- **Enter** en cualquier input del formulario: ejecutar creación (no submit del form padre)
  - Usar `wire:keydown.enter.prevent="metodoCrear"`
- Después de crear: dispatch evento para que Alpine **agregue** el item nuevo al array local
  - Livewire: `$this->dispatch('entidad-creada', id: ..., nombre: ..., ...campos)`
  - Alpine: `@entidad-creada.window="items.push({...}); items.sort(...); search = $event.detail.nombre;"`

### 4. Componente Livewire (PHP)

Agregar al componente:

```php
// Propiedades
public bool $showAltaRapida{Entidad} = false;
public string $nueva{Entidad}Campo1 = '';
public string $nueva{Entidad}Campo2 = '';

// Método de creación
public function crear{Entidad}Rapida(): void
{
    $this->validate([...]);

    $entidad = Modelo::create([
        'campo1' => $this->nueva{Entidad}Campo1,
        // ... campos con defaults
    ]);

    CatalogoCache::clear(); // Si usa cache

    $this->propiedad_id = $entidad->id;
    $this->showAltaRapida{Entidad} = false;
    $this->reset(['nueva{Entidad}Campo1', 'nueva{Entidad}Campo2']);

    // Disparar auto-completado si aplica (ej: código por prefijo)

    $this->dispatch('entidad-creada', id: ..., nombre: ..., ...campos);
    $this->dispatch('notify', message: __('...'), type: 'success');
}

// Agregar al resetFormulario()
$this->reset(['showAltaRapida{Entidad}', 'nueva{Entidad}Campo1', ...]);
```

### 5. Checklist de verificación

- [ ] Input y botón unidos visualmente (sin gap, rounded-l/r)
- [ ] Botón `+` color indigo-600 con hover indigo-700
- [ ] Búsqueda por subcadena en todos los campos definidos
- [ ] Navegación con flechas + Enter selecciona primera si no se navegó
- [ ] Scroll automático al navegar con flechas (`querySelectorAll('button')`)
- [ ] Foco automático al abrir alta rápida
- [ ] Enter en inputs de alta rápida = crear (no submit form padre)
- [ ] Item nuevo queda seleccionado después de crear
- [ ] Botón X para limpiar selección
- [ ] Dark mode completo
- [ ] `wire:keydown.enter.prevent` en inputs del mini-formulario
