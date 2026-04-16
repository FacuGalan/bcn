---
name: vista
description: Crear vista Blade siguiendo el design system del proyecto (responsive, dark mode, cards mobile + tabla desktop, modales, toasts).
user-invocable: true
argument-hint: "[nombre-vista]"
---

# Vista — Crear Vista Blade con Design System

Tu trabajo es crear una vista Blade que siga **exactamente** el design system establecido en BCN Pymes.

## Antes de generar:

1. **Leer obligatorio**: `.claude/docs/design-system.md` — contiene todos los tokens de diseño, patrones y templates
2. **Leer una vista de referencia** similar al tipo que se necesita:
   - CRUD con tabla: `resources/views/livewire/articulos/gestionar-categorias.blade.php`
   - Dashboard/resumen: `resources/views/livewire/bancos/resumen-cuentas.blade.php`
   - Formulario complejo: `resources/views/livewire/ventas/nueva-venta.blade.php`
3. **Copiar la estructura de la referencia**, no improvisar. Las clases CSS deben coincidir exactamente.

## Al ejecutar este skill:

### 1. Preguntar al usuario
- Nombre del componente Livewire asociado
- Tipo de vista: listado CRUD | formulario | dashboard | detalle
- ¿Tiene modales? ¿Cuáles?
- ¿Tiene filtros/búsqueda?
- Columnas de la tabla (si aplica)

### 2. Estructura obligatoria (COPIAR EXACTO)

```html
<div class="py-4">
  <div class="px-4 sm:px-6 lg:px-8">

    <!-- 1. HEADER — mb-4 sm:mb-6 -->
    <div class="mb-4 sm:mb-6">
      <div class="flex justify-between items-start gap-3 sm:gap-4">
        <div class="flex-1">
          <div class="flex items-center justify-between gap-3 sm:block">
            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">
              {{ __('Título') }}
            </h2>
            <!-- Botones mobile: sm:hidden -->
            <div class="sm:hidden flex gap-2">
              <button class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-5 h-5">...</svg>
              </button>
            </div>
          </div>
          <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Descripción') }}</p>
        </div>
        <!-- Botones desktop: hidden sm:flex -->
        <div class="hidden sm:flex gap-3">
          <button class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
            <svg class="w-5 h-5 mr-2">...</svg>
            {{ __('Texto') }}
          </button>
        </div>
      </div>
    </div>

    <!-- 2. FILTROS — contenedor propio, colapsable en mobile -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
      <!-- Toggle mobile -->
      <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
        <button wire:click="toggleFilters" class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors">
          <span class="flex items-center">
            <svg class="w-5 h-5 mr-2"><!-- funnel icon --></svg>
            {{ __('Filtros') }}
          </span>
          <svg class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}"><!-- chevron --></svg>
        </button>
      </div>
      <!-- Contenido filtros -->
      <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- inputs con labels -->
        </div>
      </div>
    </div>

    <!-- 3. CARDS MOBILE — sm:hidden -->
    <div class="sm:hidden space-y-3">
      @forelse($items as $item)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <!-- Contenido card -->
        </div>
      @empty
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"><!-- icono --></svg>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No hay datos') }}</p>
        </div>
      @endforelse
    </div>

    <!-- 4. TABLA DESKTOP — hidden sm:block -->
    <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-bcn-light dark:bg-gray-900">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                {{ __('Columna') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($items as $item)
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ $item->campo }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="X" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                  {{ __('No hay datos') }}
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <!-- Paginación DENTRO del wrapper de tabla -->
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
        {{ $items->links() }}
      </div>
    </div>

  </div>
</div>
```

### 3. Clases CSS exactas (NO improvisar)

**IMPORTANTE**: Las siguientes clases son obligatorias. No usar variaciones (ej: `px-4` en lugar de `px-6` en tablas).

| Elemento | Clases EXACTAS |
|----------|---------------|
| Título página | `text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto` |
| Descripción | `mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300` |
| Header wrapper | `mb-4 sm:mb-6` |
| Filtros container | `bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6` |
| Filtros padding | `p-4 sm:p-6` |
| Card mobile | `bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4` |
| Tabla wrapper | `bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg` |
| Thead | `bg-bcn-light dark:bg-gray-900` |
| Th | `px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider` + `scope="col"` |
| Td | `px-6 py-4 whitespace-nowrap` |
| Tr body | `hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150` |
| Paginación desktop | `px-6 py-4 border-t border-gray-200 dark:border-gray-700` (dentro del table wrapper) |
| Botón desktop header | `inline-flex items-center justify-center px-4 py-2 ... rounded-md font-semibold text-xs ... uppercase tracking-widest` |
| Botón mobile header | `inline-flex items-center justify-center flex-shrink-0 w-10 h-10 ... rounded-md` |
| Botón editar en tabla | Con texto `{{ __('Editar') }}` + icono con `mr-1` — NO solo icono |
| Gap botones acciones | `gap-1.5` |
| Badge estado | `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium` |

### 4. Reglas de diseño obligatorias

- **SIEMPRE** dark mode: cada `bg-white` lleva `dark:bg-gray-800`, cada `text-gray-900` lleva `dark:text-white`
- **SIEMPRE** responsive: cards en mobile (`sm:hidden`), tabla en desktop (`hidden sm:block`)
- **SIEMPRE** botones responsive: icono solo en mobile, icono+texto en desktop
- **SIEMPRE** usar `{{ __('texto') }}` para todo texto visible (i18n)
- **SIEMPRE** empty states con icono SVG + mensaje
- **SIEMPRE** usar colores de marca: `bcn-primary`, `bcn-secondary`, `bcn-light`
- **SIEMPRE** inputs con focus bcn-primary: `focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50`
- **SIEMPRE** filtros en contenedor separado de la tabla, colapsables en mobile con `toggleFilters`
- **SIEMPRE** paginación desktop dentro del wrapper de tabla con `border-t`
- **SIEMPRE** botón de editar en tabla desktop muestra texto "Editar" (no solo icono)
- **NUNCA** colores hardcoded que no sean del design system
- **NUNCA** olvidar dark mode en ningún elemento
- **NUNCA** poner filtros y tabla en el mismo contenedor

### 5. Modales

**OBLIGATORIO**: Usar `<x-bcn-modal>` para TODOS los modales. **NO** usar `<x-modal>` (deprecado).

```html
<x-bcn-modal :title="__('Título')" color="bg-bcn-primary" maxWidth="5xl" onClose="cancel" submit="save">
    <x-slot:body>...</x-slot:body>
    <x-slot:footer>...</x-slot:footer>
</x-bcn-modal>
```

### 6. Checklist de compliance (OBLIGATORIO antes de entregar)

Verificar CADA punto. Si alguno falla, corregir antes de entregar:

**Estructura:**
- [ ] Header usa `mb-4 sm:mb-6` con flex anidado (`flex-1`, `items-start`)
- [ ] Título usa `text-xl sm:text-2xl` (responsive, no `text-2xl` fijo)
- [ ] Botones mobile dentro del div del título, botones desktop fuera
- [ ] Filtros en contenedor separado con `overflow-hidden shadow-sm sm:rounded-lg`
- [ ] Filtros colapsables en mobile (propiedad `$showFilters` + `toggleFilters()`)
- [ ] Cards mobile y tabla desktop son contenedores hermanos, no anidados

**Tabla desktop:**
- [ ] Wrapper: `bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg`
- [ ] Thead: `bg-bcn-light dark:bg-gray-900` (NO `bg-gray-50`)
- [ ] Th: `px-6 py-3` + `tracking-wider` + `scope="col"`
- [ ] Td: `px-6 py-4` (NO `px-4 py-3`)
- [ ] Tr: tiene `hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150`
- [ ] Paginación: dentro del wrapper con `px-6 py-4 border-t`
- [ ] Botón editar: muestra texto "Editar" en desktop (icono + `mr-1` + texto)
- [ ] Gap acciones: `gap-1.5`

**Cards mobile:**
- [ ] `bg-white dark:bg-gray-800` (NO `bg-gray-50`)
- [ ] Tiene `shadow-sm border border-gray-200 dark:border-gray-700`
- [ ] Empty state con icono SVG + texto en card con mismo estilo

**General:**
- [ ] Todos los textos usan `{{ __('...') }}`
- [ ] Dark mode en TODOS los elementos (bg, text, border)
- [ ] Modales usan `<x-bcn-modal>` (NO `<x-modal>`)
- [ ] Contenido del modal envuelto en `<x-slot:body>...</x-slot:body>` y botones en `<x-slot:footer>...</x-slot:footer>` — bcn-modal requiere slots con nombre, contenido directo rompe con `Undefined variable $body`
- [ ] Focus de inputs usa `bcn-primary`
- [ ] Permisos: `auth()->user()?->hasPermissionTo('func.X')` — NUNCA `@can()`, `auth()->user()?->can()`, `Gate::allows()` (User no usa HasRoles trait, can() siempre da false)

### 7. Actualizar documentación

Si la vista agrega funcionalidades nuevas (botones, modales, filtros, campos) que no están en la documentación existente:
- **`docs/manual-usuario.md`**: Actualizar la sección del módulo correspondiente con las nuevas funcionalidades
