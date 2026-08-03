{{-- Configuración de tienda POR ARTÍCULO (RF-T14): galería, badges,
     destacado y orden drag & drop. Guardado INMEDIATO por acción (no pasa
     por "Guardar tienda"); el visor se refresca solo por morph (debounced). --}}
@php
    // Look ESPEJO de la tienda (bcn-tienda badges-articulo.blade.php):
    // mismo emoji y misma familia de color por tipo, en paleta del panel.
    $estiloBadges = [
        'sin_tacc' => ['🌾', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'vegetariano' => ['🥕', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'vegano' => ['🌱', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'picante' => ['🌶️', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'nuevo' => ['✨', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
        'mas_vendido' => ['🔥', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        'artesanal' => ['🧑‍🍳', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
        'sin_azucar' => ['🍃', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'sin_lactosa' => ['🥛', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'kosher' => ['✡️', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
        'con_frutos_secos' => ['🌰', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
    ];
    $estiloBadgeCustom = 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600';
@endphp
<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2" x-data="tiendaArticulos">
    <div>
        <h3 class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Artículos de la tienda') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Fotos, badges, destacados y orden de cada artículo en la vidriera. Los cambios se guardan al instante y el visor se actualiza solo.') }}
        </p>
        @if($puedeConfigurar)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Arrastrá con ⠿ para reordenar categorías y artículos.') }}
            </p>
        @endif
    </div>

    <div class="space-y-2" data-sortable-categorias>
        @forelse($grupos as $grupo)
            <div wire:key="cta-cat-{{ $grupo['id'] }}" data-categoria-id="{{ $grupo['id'] }}"
                x-data="{ abierta: false }"
                class="border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                {{-- Header de categoría: handle + nombre + contador + chevron --}}
                <div class="flex items-center gap-1.5 px-2 py-1.5 bg-gray-50 dark:bg-gray-800">
                    @if($puedeConfigurar && $grupo['id'] !== 0)
                        <span data-drag-handle-categoria class="cursor-grab touch-none text-gray-400 dark:text-gray-500 select-none" title="{{ __('Arrastrar para reordenar') }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm6-12a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        </span>
                    @endif
                    <button type="button" @click="abierta = ! abierta" class="flex-1 flex items-center justify-between gap-2 text-left min-w-0">
                        <span class="text-xs font-medium text-gray-900 dark:text-white min-w-0">
                            {{ $grupo['nombre'] }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ $grupo['articulos']->count() }})</span>
                            {{-- Badges de la categoría (RF-T36): chips read-only en el header --}}
                            @if($grupo['badges'] !== [])
                                <span class="ml-1 inline-flex flex-wrap gap-1 align-middle">
                                    @foreach($grupo['badges'] as $badge)
                                        @if($badge['tipo'] === 'custom')
                                            <span class="inline-flex items-center rounded-full px-1.5 py-px text-[10px] font-medium {{ $estiloBadgeCustom }}">{{ $badge['texto'] }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-px text-[10px] font-medium {{ $estiloBadges[$badge['tipo']][1] ?? '' }}">
                                                <span aria-hidden="true">{{ $estiloBadges[$badge['tipo']][0] ?? '' }}</span>{{ $badgesCatalogo[$badge['tipo']] ?? $badge['tipo'] }}
                                            </span>
                                        @endif
                                    @endforeach
                                </span>
                            @endif
                        </span>
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform shrink-0" :class="abierta ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    @if($puedeConfigurar && $grupo['id'] !== 0)
                        <button type="button" wire:click="toggleEditorBadgesCategoria({{ $grupo['id'] }})"
                            class="shrink-0 p-1 rounded transition-colors {{ $categoriaBadges === $grupo['id'] ? 'text-bcn-primary' : 'text-gray-400 dark:text-gray-500 hover:text-bcn-primary' }}"
                            title="{{ __('Badges de la categoría') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                        </button>
                    @endif
                </div>

                {{-- Editor de badges de la categoría (RF-T36, guardado inmediato) --}}
                @if($categoriaBadges === $grupo['id'] && $grupo['id'] !== 0)
                    <div class="px-2 py-2 bg-white dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-700/60 space-y-1.5">
                        <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Badges de la categoría') }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ __('máximo :max', ['max' => $maxBadges]) }} — {{ __('la tienda los muestra junto al título del grupo') }})</span>
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($badgesCatalogo as $tipo => $label)
                                @php($seleccionado = in_array($tipo, $catBadgesSel, true))
                                <button type="button" wire:click="toggleBadgeCategoria('{{ $tipo }}')"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full border transition-all {{ $seleccionado
                                        ? ($estiloBadges[$tipo][1] ?? '').' border-transparent shadow-sm'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 grayscale opacity-75 hover:opacity-100 hover:grayscale-0' }}">
                                    <span aria-hidden="true">{{ $estiloBadges[$tipo][0] ?? '' }}</span>{{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <div>
                            <input type="text" wire:model.live.debounce.800ms="catBadgeCustom" maxlength="30"
                                placeholder="{{ __('Badge propio (ej: Receta de la casa)') }}"
                                class="w-full sm:w-64 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('catBadgeCustom') <p class="mt-1 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                {{-- Artículos de la categoría --}}
                <div x-show="abierta" class="divide-y divide-gray-100 dark:divide-gray-700/60" data-sortable-articulos>
                    @foreach($grupo['articulos'] as $articulo)
                        <div wire:key="cta-art-{{ $articulo->id }}" data-articulo-id="{{ $articulo->id }}" class="px-2 py-1.5">
                            <div class="flex items-center gap-2">
                                @if($puedeConfigurar)
                                    <span data-drag-handle-articulo class="cursor-grab touch-none text-gray-400 dark:text-gray-500 select-none shrink-0" title="{{ __('Arrastrar para reordenar') }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm6-12a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2zm0 4a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                    </span>
                                @endif

                                {{-- Miniatura: 1ª foto de tienda, si no la operativa --}}
                                @php($miniatura = $articulo->imagenesTienda->first()?->url() ?? $articulo->imagenUrl())
                                @if($miniatura)
                                    <img src="{{ $miniatura }}" alt="" class="w-9 h-9 rounded object-cover shrink-0">
                                @else
                                    <span class="w-9 h-9 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </span>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</p>
                                    @php($badgesFila = $articulo->badgesTienda())
                                    @if($badgesFila !== [])
                                        <span class="mt-0.5 flex flex-wrap gap-1">
                                            @foreach($badgesFila as $badge)
                                                @if($badge['tipo'] === 'custom')
                                                    <span class="inline-flex items-center rounded-full px-1.5 py-px text-[10px] font-medium {{ $estiloBadgeCustom }}">{{ $badge['texto'] }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-px text-[10px] font-medium {{ $estiloBadges[$badge['tipo']][1] ?? '' }}">
                                                        <span aria-hidden="true">{{ $estiloBadges[$badge['tipo']][0] ?? '' }}</span>{{ $badgesCatalogo[$badge['tipo']] ?? $badge['tipo'] }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </span>
                                    @endif
                                </div>

                                {{-- Canjeable por puntos en la tienda (RF-T54 ajustado, guardado
                                     inmediato). Costo: articulos.puntos_canje si está cargado
                                     (input provisorio de abajo); vacío = se deriva del precio del
                                     día (regla del POS). --}}
                                @php($canjeTienda = (bool) ($articulo->sucursales->first()?->pivot?->canje_tienda))
                                @if($canjeTienda)
                                    <input type="number" min="0" step="1" inputmode="numeric"
                                        value="{{ (int) $articulo->puntos_canje > 0 ? (int) $articulo->puntos_canje : '' }}"
                                        placeholder="{{ __('Auto') }}" @disabled(! $puedeConfigurar)
                                        wire:change="guardarPuntosCanje({{ $articulo->id }}, $event.target.value)"
                                        title="{{ __('Puntos para canjear este artículo (vacío = se calcula del precio)') }}"
                                        class="shrink-0 w-16 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-[11px] py-0.5 px-1.5 text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                @endif
                                <button type="button" wire:click="toggleCanjeTienda({{ $articulo->id }})" @disabled(! $puedeConfigurar)
                                    class="shrink-0 p-1 rounded transition-colors {{ $canjeTienda ? 'text-violet-500 hover:text-violet-600' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500' }}"
                                    title="{{ $canjeTienda ? __('Quitar canje por puntos en la tienda') : __('Permitir canje por puntos en la tienda') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.25c.41 0 .786.224.982.585l2.534 4.67 5.223.96a1.125 1.125 0 0 1 .607 1.868l-3.65 3.87.685 5.264a1.125 1.125 0 0 1-1.591 1.16L12 18.4l-4.79 2.227a1.125 1.125 0 0 1-1.59-1.16l.684-5.265-3.65-3.869a1.125 1.125 0 0 1 .607-1.868l5.223-.96 2.534-4.67c.196-.36.573-.585.982-.585Z" clip-rule="evenodd" opacity=".35"/><path d="M12 7.5a.75.75 0 0 1 .75.75v3h3a.75.75 0 0 1 0 1.5h-3v3a.75.75 0 0 1-1.5 0v-3h-3a.75.75 0 0 1 0-1.5h3v-3A.75.75 0 0 1 12 7.5Z"/></svg>
                                </button>

                                {{-- Destacado (guardado inmediato) --}}
                                <button type="button" wire:click="toggleDestacado({{ $articulo->id }})" @disabled(! $puedeConfigurar)
                                    class="shrink-0 p-1 rounded transition-colors {{ $articulo->destacado ? 'text-amber-500 hover:text-amber-600' : 'text-gray-300 dark:text-gray-600 hover:text-gray-400 dark:hover:text-gray-500' }}"
                                    title="{{ $articulo->destacado ? __('Quitar destacado') : __('Marcar como destacado') }}">
                                    <svg class="w-5 h-5" fill="{{ $articulo->destacado ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                                </button>

                                {{-- Abrir/cerrar editor de fotos + badges --}}
                                <button type="button" wire:click="{{ $articuloAbierto === $articulo->id ? 'cerrarEditor' : 'abrirEditor('.$articulo->id.')' }}"
                                    class="shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium rounded-md border transition-colors {{ $articuloAbierto === $articulo->id ? 'bg-bcn-primary text-white border-transparent' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                    {{ $articulo->imagenesTienda->count() }}
                                </button>
                            </div>

                            {{-- Editor expandido: galería + badges --}}
                            @if($articuloAbierto === $articulo->id)
                                <div class="mt-2 ml-6 p-2 rounded-md bg-gray-50 dark:bg-gray-800 space-y-3">
                                    {{-- GALERÍA --}}
                                    <div>
                                        <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ __('Fotos de la tienda') }}
                                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ $articulo->imagenesTienda->count() }}/{{ $maxFotos }} — {{ __('la primera es la principal') }})</span>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2" data-sortable-fotos x-init="initFotosSortable($el)">
                                            @foreach($articulo->imagenesTienda as $foto)
                                                <div wire:key="cta-foto-{{ $foto->id }}" data-foto-id="{{ $foto->id }}" class="relative group {{ $puedeConfigurar ? 'cursor-grab' : '' }}">
                                                    <img src="{{ $foto->url() }}" alt="" class="w-14 h-14 rounded object-cover">
                                                    @if($puedeConfigurar)
                                                        <button type="button" wire:click="quitarFoto({{ $foto->id }})"
                                                            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-600 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                                            title="{{ __('Quitar foto') }}">
                                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @if($puedeConfigurar && $articulo->imagenesTienda->count() < $maxFotos)
                                                <label class="w-14 h-14 rounded border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center cursor-pointer text-gray-400 dark:text-gray-500 hover:border-bcn-primary hover:text-bcn-primary transition-colors" title="{{ __('Agregar fotos') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                    <input type="file" wire:model="fotosUpload" multiple accept="image/*" class="hidden">
                                                </label>
                                            @endif
                                        </div>
                                        <div wire:loading wire:target="fotosUpload" class="mt-1 text-[11px] text-bcn-primary">{{ __('Subiendo imagen...') }}</div>
                                        @error('fotosUpload.*') <p class="mt-1 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ __('Sin fotos de tienda se usa la imagen del artículo del panel.') }}</p>
                                    </div>

                                    {{-- BADGES --}}
                                    <div>
                                        <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ __('Badges') }}
                                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ __('máximo :max', ['max' => $maxBadges]) }})</span>
                                        </p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($badgesCatalogo as $tipo => $label)
                                                @php($seleccionado = in_array($tipo, $badgesSel, true))
                                                {{-- Mismo look que en la tienda: apagado = gris/desaturado, prendido = su color real --}}
                                                <button type="button" wire:click="toggleBadge('{{ $tipo }}')" @disabled(! $puedeConfigurar)
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full border transition-all {{ $seleccionado
                                                        ? ($estiloBadges[$tipo][1] ?? '').' border-transparent shadow-sm'
                                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 grayscale opacity-75 hover:opacity-100 hover:grayscale-0' }}">
                                                    <span aria-hidden="true">{{ $estiloBadges[$tipo][0] ?? '' }}</span>{{ $label }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="mt-1.5">
                                            <input type="text" wire:model.live.debounce.800ms="badgeCustom" maxlength="30" @disabled(! $puedeConfigurar)
                                                placeholder="{{ __('Badge propio (ej: Receta de la casa)') }}"
                                                class="w-full sm:w-64 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                            @error('badgeCustom') <p class="mt-1 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    {{-- ALÉRGENOS (RF-T14): texto libre + botonera de atajos --}}
                                    <div>
                                        <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ __('Alérgenos') }}
                                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ __('la tienda muestra "Contiene: ..." en el detalle') }})</span>
                                        </p>
                                        <input type="text" wire:model.live.debounce.800ms="alergenos" maxlength="400" @disabled(! $puedeConfigurar)
                                            placeholder="{{ __('Separados por coma. Ej: soja, huevos, mostaza') }}"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach($alergenosSugeridos as $sugerido)
                                                <button type="button" wire:click="agregarAlergeno('{{ $sugerido }}')" @disabled(! $puedeConfigurar)
                                                    class="px-1.5 py-0.5 text-[10px] font-medium rounded-full border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-bcn-primary hover:text-bcn-primary transition-colors">
                                                    + {{ $sugerido }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- ENCARGOS (RF-T16) --}}
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="permiteEncargo" @disabled(! $puedeConfigurar)
                                            class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                                        <span class="text-[11px] text-gray-700 dark:text-gray-300">
                                            {{ __('Disponible para encargos') }}
                                            <span class="block text-gray-500 dark:text-gray-400">{{ __('Se puede pedir para un día futuro (si la tienda toma encargos).') }}</span>
                                        </span>
                                    </label>

                                    {{-- DESCRIPCIÓN PARA LA TIENDA (RF-T14) --}}
                                    <div>
                                        <p class="text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ __('Descripción para la tienda') }}
                                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ __('vacía: se usa la del artículo') }})</span>
                                        </p>
                                        <textarea rows="3" wire:model.live.debounce.800ms="descripcionTienda" maxlength="1000" @disabled(! $puedeConfigurar)
                                            placeholder="{{ $articulo->descripcion ?: __('Contá este producto como quieras que se lea en la tienda...') }}"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                                        @error('descripcionTienda') <p class="mt-1 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('No hay artículos visibles en la tienda de esta sucursal. Activá "visible en tienda" en los artículos que quieras publicar.') }}</p>
        @endforelse
    </div>
</div>
