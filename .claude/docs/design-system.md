# Design System — BCN Pymes

## Colores de marca

| Token | Hex | Uso |
|-------|-----|-----|
| `bcn-primary` | `#FFAF22` | Acciones principales, focus, checkboxes, badges activos |
| `bcn-secondary` | `#222036` | Navbar, títulos, texto destacado |
| `bcn-light` | `#F7F7F7` | Fondos claros, header de tablas |
| `bcn-white` | `#FFFFFF` | Fondos de cards y modales |

## Colores semánticos (Tailwind defaults)

| Tipo | Fondo | Borde | Icono | Texto |
|------|-------|-------|-------|-------|
| Success | `bg-green-50` | `border-green-200` | `text-green-500` | `text-green-800` |
| Error | `bg-red-50` | `border-red-200` | `text-red-500` | `text-red-800` |
| Warning | `bg-yellow-50` | `border-yellow-200` | `text-yellow-500` | `text-yellow-800` |
| Info | `bg-blue-50` | `border-blue-200` | `text-blue-500` | `text-blue-800` |

## Dark Mode

- Activado por clase CSS en `<html>`: `class="dark"`
- Toggle guardado en `users.dark_mode`
- Evento Livewire `theme-changed` actualiza DOM sin recarga

### Mapeo claro → oscuro

| Elemento | Light | Dark |
|----------|-------|------|
| Fondo página | `bg-gray-100` | `dark:bg-gray-900` |
| Card | `bg-white` | `dark:bg-gray-800` |
| Texto principal | `text-gray-900` | `dark:text-white` |
| Texto secundario | `text-gray-600` | `dark:text-gray-400` |
| Texto terciario | `text-gray-500` | `dark:text-gray-400` |
| Bordes | `border-gray-200` | `dark:border-gray-700` |
| Inputs | `border-gray-300` | `dark:border-gray-600 dark:bg-gray-700 dark:text-white` |
| Header tabla | `bg-bcn-light` | `dark:bg-gray-900` |
| Hover fila | `hover:bg-gray-50` | `dark:hover:bg-gray-700` |

## Tipografía

| Elemento | Clases |
|----------|--------|
| Título página | `text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white` |
| Subtítulo | `font-semibold text-gray-700 dark:text-gray-300` |
| Descripción | `text-xs sm:text-sm text-gray-600 dark:text-gray-400` |
| Label formulario | `block text-sm font-medium text-gray-700 dark:text-gray-300` |
| Texto pequeño | `text-xs text-gray-500 dark:text-gray-400` |
| Header tabla | `text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider` |

Font: Figtree (bunny.net CDN).

## Botones

### Componentes Blade disponibles
```html
<x-primary-button>Guardar</x-primary-button>        <!-- bg-gray-800, blanco -->
<x-secondary-button>Cancelar</x-secondary-button>   <!-- bg-white, borde gris -->
<x-danger-button>Eliminar</x-danger-button>          <!-- bg-red-600, blanco -->
```

### Botón BCN Primary (acción principal de marca)
```html
<button class="bg-bcn-primary text-white hover:bg-opacity-90
  focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2
  rounded-md px-4 py-2 font-semibold transition">
```

### Patrón responsive: icono en mobile, texto en desktop
```html
<!-- Mobile -->
<button class="sm:hidden inline-flex items-center justify-center w-10 h-10
  bg-bcn-primary rounded-md text-white hover:bg-opacity-90">
  <svg class="w-5 h-5">...</svg>
</button>
<!-- Desktop -->
<button class="hidden sm:inline-flex items-center px-4 py-2
  bg-bcn-primary rounded-md text-white font-semibold hover:bg-opacity-90">
  <svg class="w-5 h-5 mr-2">...</svg>
  {{ __('Texto') }}
</button>
```

## Inputs y Formularios

```html
<!-- Input texto -->
<input class="mt-1 block w-full rounded-md
  border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white
  shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">

<!-- Select -->
<select class="mt-1 block w-full rounded-md
  border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white
  shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary">

<!-- Checkbox -->
<input type="checkbox" class="rounded border-gray-300 dark:border-gray-600
  text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">

<!-- Error de validación -->
<span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span>
```

## Modales

**OBLIGATORIO**: Usar `<x-bcn-modal>` para TODOS los modales. NO usar `<x-modal>` (deprecado).

### Características del componente
- **Mobile**: Bottom sheet (sube desde abajo, pill handle, pull-down to dismiss)
- **Desktop**: Modal centrado con animación fade+scale
- **Scroll lock**: Body no scrollea mientras el modal está abierto
- **Escape**: Cierra el modal con tecla Escape
- **Overlay**: Click en overlay cierra el modal
- **Footer fijo**: Los botones siempre visibles, contenido scrolleable

### Props

| Prop | Default | Descripción |
|------|---------|-------------|
| `title` | `''` | Título del modal (se muestra en el header) |
| `color` | `'bg-bcn-primary'` | Color del header. **REGLA: debe coincidir con el color del botón que abre el modal** |
| `maxWidth` | `'5xl'` | Ancho máximo: sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl |
| `onClose` | `'cancel'` | Método Livewire a ejecutar al cerrar |
| `submit` | `null` | Si se pasa, envuelve el contenido en `<form wire:submit="...">` |

### Slots

| Slot | Descripción |
|------|-------------|
| `body` | Contenido principal (scrolleable) |
| `footer` | Botones de acción (fijo abajo) |

### Uso básico (modal con form)
```html
@if($showModal)
    <x-bcn-modal
        :title="$editMode ? __('Editar Artículo') : __('Nuevo Artículo')"
        color="bg-bcn-primary"
        maxWidth="5xl"
        onClose="cancel"
        submit="save"
    >
        <x-slot:body>
            {{-- Campos del formulario --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }}</label>
                    <input type="text" wire:model="nombre" class="mt-1 block w-full rounded-md ..." />
                </div>
            </div>
        </x-slot:body>

        <x-slot:footer>
            <button type="button" @click="close()"
                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600
                shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700
                dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                {{ __('Cancelar') }}
            </button>
            <button type="submit"
                class="w-full inline-flex justify-center rounded-md border border-transparent
                shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white
                hover:bg-opacity-90 sm:w-auto sm:text-sm">
                {{ $editMode ? __('Actualizar') : __('Crear') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
```

### Uso sin form (modal informativo o con acciones directas)
```html
@if($showDeleteModal)
    <x-bcn-modal
        :title="__('Confirmar Eliminación')"
        color="bg-red-600"
        maxWidth="md"
        onClose="cancelarEliminar"
    >
        <x-slot:body>
            <p>{{ __('¿Estás seguro?') }}</p>
        </x-slot:body>

        <x-slot:footer>
            <button type="button" @click="close()" class="...">{{ __('Cancelar') }}</button>
            <button type="button" wire:click="eliminar" class="... bg-red-600 ...">{{ __('Eliminar') }}</button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
```

### Regla de colores

El color del header y el botón de confirmar deben coincidir con el botón que abre el modal:

| Acción | Color header | Color botón confirmar |
|--------|-------------|----------------------|
| Crear/Editar (CRUD) | `bg-bcn-primary` | `bg-bcn-primary` |
| Eliminar | `bg-red-600` | `bg-red-600` |
| Abrir caja | `bg-green-600` | `bg-green-600` |
| Cerrar caja | `bg-red-600` | `bg-red-600` |
| Movimiento | `bg-blue-600` | `bg-blue-600` |

### Switches dentro de modales

Usar formato switch (no checkbox) con fondo `bg-gray-100 dark:bg-gray-700`:
```html
<div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 flex items-center justify-between">
    <label class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{{ __('Label') }}</label>
    <button type="button" wire:click="$toggle('propiedad')"
        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full
        cursor-pointer transition-colors duration-200 focus:outline-none focus:ring-2
        focus:ring-offset-2 focus:ring-bcn-primary
        {{ $propiedad ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-500' }}"
        role="switch">
        <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow
            transform transition duration-200
            {{ $propiedad ? 'translate-x-5' : 'translate-x-0' }}"></span>
    </button>
</div>
```

## Notificaciones Toast

```php
// Desde Livewire
$this->dispatch('notify', message: 'Guardado exitosamente', type: 'success');

// Desde JS/Alpine
window.notify('Mensaje', 'success');  // success | error | warning | info
```

## Estructura de Vista (template estándar)

```html
<div class="py-4">
  <div class="px-4 sm:px-6 lg:px-8">

    <!-- 1. Header -->
    <div class="mb-4 sm:mb-6">
      <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
        {{ __('Título') }}
      </h2>
      <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-400">
        {{ __('Descripción') }}
      </p>
      <!-- Botón: icono en mobile, texto en desktop -->
    </div>

    <!-- 2. Filtros (opcional) -->
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-4">
      <div class="p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <input wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar...') }}">
        </div>
      </div>
    </div>

    <!-- 3. Cards mobile -->
    <div class="sm:hidden space-y-3">
      @forelse($items as $item)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm
          border border-gray-200 dark:border-gray-700 p-4">
          <!-- Contenido card -->
        </div>
      @empty
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400"><!-- icono --></svg>
          <p class="mt-2 text-sm text-gray-500">{{ __('No hay datos') }}</p>
        </div>
      @endforelse
    </div>

    <!-- 4. Tabla desktop -->
    <div class="hidden sm:block bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-bcn-light dark:bg-gray-900">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium
              text-gray-700 dark:text-gray-300 uppercase tracking-wider">
              {{ __('Columna') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @forelse($items as $item)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ $item->campo }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="X" class="px-6 py-12 text-center text-gray-500">
                {{ __('No hay datos') }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
        {{ $items->links() }}
      </div>
    </div>

  </div>
</div>
```

## Badges de estado

```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
  bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
  Activo
</span>
```

## Spacing estándar

| Elemento | Clases |
|----------|--------|
| Padding card | `p-4 sm:p-6` |
| Margin sección | `mb-4 sm:mb-6` |
| Container | `px-4 sm:px-6 lg:px-8` |
| Gap en grids | `gap-4` |
| Espacio entre cards | `space-y-3` |

## Responsive breakpoints

| Breakpoint | Uso |
|------------|-----|
| Default (mobile) | Cards, botones icon-only, stack vertical |
| `sm:` (640px) | Tablas, botones con texto, grids 2 cols |
| `md:` (768px) | Grids 2 cols |
| `lg:` (1024px) | Grids 3 cols, sidebar |

## Iconos

- SVG components en `resources/views/components/icon/`
- 60+ iconos disponibles
- Uso: `<x-icon.nombre class="w-5 h-5" />`
- También: Heroicons via clases (`heroicon-o-*`)
