---
name: boton-inline-hover
description: Crear botón inline donde el contenido textual queda siempre visible y el icono de acción aparece solo en hover. El conjunto entero actúa como botón clickeable con cursor pointer. Patrón estándar del proyecto para acciones contextuales (cobrar, editar nombre, invitar item, etc).
user-invocable: true
argument-hint: "[acción, ej: cobrar, editar, eliminar]"
---

# Botón Inline con Icono Hover — Patrón UI Estándar

Tu trabajo es crear un botón inline donde:
- El contenido textual (badge, label, monto, etc.) está **siempre visible**.
- El icono de acción aparece **solo en hover** (`opacity-0 → opacity-100`).
- Todo el conjunto es un `<button>` clickeable con cursor pointer.
- Si la acción no corresponde, se renderiza solo el contenido textual sin interacción.

Este patrón se usa para acciones contextuales asociadas a un dato visible: cobrar un pedido al lado del badge de estado_pago, editar nombre de un item al lado del nombre, invitar un item al lado de su precio, etc.

## Antes de generar:

1. **Leer referencia obligatoria**: `resources/views/livewire/carrito/_detalle-items.blade.php` — buscar `abrirEditarNombre` (botón editar nombre) o `abrirInvitarItem` (botón invitar item).
2. **Leer referencia secundaria**: `resources/views/livewire/pedidos/pedidos-mostrador.blade.php` — buscar el patrón `cobrarRapido` en la columna "Pago".
3. **Confirmar con el usuario**:
   - Qué contenido textual queda siempre visible (ej: badge de estado, nombre, monto).
   - Qué acción dispara el botón (método `wire:click`).
   - Condición para que el botón sea clickeable (ej: solo si tiene saldo pendiente, solo si el usuario tiene permiso).
   - Color del icono en hover (verde para cobrar, indigo para editar, rojo para borrar, emerald para invitar, etc.).

## Estructura HTML OBLIGATORIA

```blade
@if($condicionParaSerClickeable)
    <button type="button"
            wire:click="metodoAccion({{ $entidad->id }})"
            class="inline-flex items-center gap-1 group cursor-pointer"
            title="{{ __('Texto descriptivo') }}">
        {{-- Contenido siempre visible --}}
        {{ $contenidoTextual }}

        {{-- Icono que aparece en hover --}}
        <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-{COLOR}-600 dark:group-hover:text-{COLOR}-400 transition-opacity flex-shrink-0"
              aria-hidden="true">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{ICONO}" />
            </svg>
        </span>
    </button>
@else
    {{-- Sin acción disponible: solo el contenido textual --}}
    {{ $contenidoTextual }}
@endif
```

## REGLAS visuales — NO improvisar

- El `<button>` envolvente: `inline-flex items-center gap-1 group cursor-pointer`. **NO** agregar border, background, padding extra ni efectos de fondo al botón mismo (queda invisible como contenedor; solo el icono interno tiene estilo).
- El span del icono:
  - `opacity-0 group-hover:opacity-100 transition-opacity` (aparece smooth en hover).
  - `text-gray-400 group-hover:text-{color}-600 dark:group-hover:text-{color}-400` (gris apagado por defecto, color vivo en hover).
  - `flex-shrink-0` para que no se comprima.
  - `aria-hidden="true"` (decorativo, el botón ya tiene `title`).
- El SVG dentro del span: `w-3.5 h-3.5` (tamaño estándar). Usar `w-4 h-4` solo si el icono lo amerita.
- El `title` del botón es lo que se lee como tooltip y para accesibilidad — usar `__()` con texto descriptivo.
- **NUNCA** mostrar el icono visible permanentemente (eso es otro patrón). **NUNCA** invertir: contenido en hover + icono visible.

## Colores por tipo de acción (convención del proyecto)

| Acción | Color hover |
|--------|-------------|
| Cobrar / Confirmar pago | `green-600` / dark `green-400` |
| Editar (nombre, datos) | `indigo-600` / dark `indigo-400` |
| Invitar / Cortesía | `emerald-600` / dark `emerald-400` |
| Eliminar | `red-600` / dark `red-400` |
| Convertir / Avanzar | `bcn-primary` |
| Imprimir | `gray-600` / dark `gray-300` |

## Iconos SVG comunes (paths del proyecto)

- **Cobrar/Dinero**: `M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1` + segundo path `M21 12a9 9 0 11-18 0 9 9 0 0118 0z`.
- **Editar/Lápiz**: `M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z`.
- **Invitar/Regalo**: `M4 6h16M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6M4 6V4a2 2 0 012-2h12a2 2 0 012 2v2M9 12h6` (variaciones según el contexto).
- **Eliminar/Tacho**: `M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16`.

## Ejemplo real — botón Cobrar al lado del badge

```blade
@if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
    <button type="button"
            wire:click="cobrarRapido({{ $pedido->id }})"
            class="inline-flex items-center gap-1 group cursor-pointer"
            title="{{ $pedido->total_planificado > 0 ? __('Confirmar pagos planificados') : __('Abrir desglose de cobro') }}">
        <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" />
        <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-opacity flex-shrink-0"
              aria-hidden="true">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>
    </button>
@else
    <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" />
@endif
```

## Ejemplo real — botón Editar Nombre del item

```blade
<button type="button"
        wire:click="abrirEditarNombre({{ $index }})"
        class="flex items-center gap-0.5 group text-left max-w-full hover:text-indigo-600 dark:hover:text-indigo-400"
        title="{{ $item['nombre'] }}">
    <span class="text-xs font-medium text-gray-900 dark:text-white truncate max-w-[180px] group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
        {{ $item['nombre'] }}
    </span>
    <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-opacity flex-shrink-0"
          aria-hidden="true">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
    </span>
</button>
```

## Checklist post-generación

- [ ] El conjunto entero es un `<button type="button">` (no un `<div>` con onclick).
- [ ] Tiene `class` con `group` + `cursor-pointer`.
- [ ] El icono está dentro de un `<span>` con `opacity-0 group-hover:opacity-100 transition-opacity`.
- [ ] El color del icono en hover sigue la convención por tipo de acción.
- [ ] El `title` está traducido con `__()`.
- [ ] Existe un `@else` que renderiza solo el contenido textual cuando la acción no corresponde.
- [ ] Funciona en dark mode (siempre incluir `dark:` variantes).
- [ ] Aplica tanto en tabla desktop como en cards móvil si el contexto lo requiere.
