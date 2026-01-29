{{-- Buscador de artículo para NxM básico --}}
@php
    $colorBase = $color ?? 'purple';
    $colorClasses = [
        'purple' => ['ring' => 'focus:ring-purple-500', 'border' => 'focus:border-purple-500', 'hover' => 'hover:bg-purple-50 dark:hover:bg-purple-900/30', 'text' => 'text-purple-600', 'bg' => 'bg-purple-100'],
        'indigo' => ['ring' => 'focus:ring-indigo-500', 'border' => 'focus:border-indigo-500', 'hover' => 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30', 'text' => 'text-indigo-600', 'bg' => 'bg-indigo-100'],
        'orange' => ['ring' => 'focus:ring-orange-500', 'border' => 'focus:border-orange-500', 'hover' => 'hover:bg-orange-50 dark:hover:bg-orange-900/30', 'text' => 'text-orange-600', 'bg' => 'bg-orange-100'],
        'green' => ['ring' => 'focus:ring-green-500', 'border' => 'focus:border-green-500', 'hover' => 'hover:bg-green-50 dark:hover:bg-green-900/30', 'text' => 'text-green-600', 'bg' => 'bg-green-100'],
    ][$colorBase] ?? ['ring' => 'focus:ring-purple-500', 'border' => 'focus:border-purple-500', 'hover' => 'hover:bg-purple-50 dark:hover:bg-purple-900/30', 'text' => 'text-purple-600', 'bg' => 'bg-purple-100'];
@endphp

<div class="relative">
    @if($articuloId)
        {{-- Artículo seleccionado --}}
        <div class="flex items-center gap-2 p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg">
            <span class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $busqueda }}</span>
            <button type="button" wire:click="{{ $limpiarMethod }}"
                    class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @else
        {{-- Buscador --}}
        <button type="button" wire:click="{{ $abrirMethod }}"
                class="w-full flex items-center gap-2 px-3 py-2 text-left text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-gray-400 dark:hover:border-gray-500 transition text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span>Buscar artículo...</span>
        </button>

        @if($mostrar)
            <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg shadow-xl">
                <div class="p-2 border-b dark:border-gray-700">
                    <input type="text" wire:model.live.debounce.200ms="{{ $busquedaModel }}"
                           wire:keydown.escape="{{ $cerrarMethod }}"
                           placeholder="Escriba para buscar..."
                           autofocus
                           class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white {{ $colorClasses['ring'] }} {{ $colorClasses['border'] }}">
                </div>
                <div class="max-h-48 overflow-y-auto">
                    @forelse($resultados as $articulo)
                        <button type="button" wire:click="{{ $seleccionarMethod }}({{ $articulo['id'] }})"
                                class="w-full px-3 py-2 text-left {{ $colorClasses['hover'] }} text-sm flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <span class="block font-medium text-gray-900 dark:text-white truncate">{{ $articulo['nombre'] }}</span>
                                @if(!empty($articulo['codigo']))
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo['codigo'] }}</span>
                                @endif
                            </div>
                            @if(isset($articulo['precio_base']))
                                <span class="text-gray-500 dark:text-gray-400 text-xs ml-2">$@precio($articulo['precio_base'])</span>
                            @endif
                        </button>
                    @empty
                        <div class="px-3 py-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                            <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            No se encontraron artículos
                        </div>
                    @endforelse
                </div>
                <div class="p-2 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                    <button type="button" wire:click="{{ $cerrarMethod }}"
                            class="text-xs text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">
                        Cerrar
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
