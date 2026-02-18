{{--
    Partial reutilizable para editar una receta.
    Espera estas variables del componente padre:
    - $recetaIngredientes (array de ['articulo_id', 'nombre', 'codigo', 'unidad_medida', 'cantidad'])
    - $busquedaIngrediente (string)
    - $resultadosBusqueda (array)
    - $recetaCantidadProducida (float)
    - $recetaNotas (string)
    - $recetaEsOverride (bool) - si es override de sucursal
    - $recetaSucursalNombre (string|null) - nombre de la sucursal si es override
--}}

<div class="space-y-4">
    <!-- Cantidad producida -->
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Cantidad producida por receta') }}</label>
            <input
                type="number"
                wire:model="recetaCantidadProducida"
                step="0.001"
                min="0.001"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
            />
        </div>
        @if(isset($recetaEsOverride) && $recetaEsOverride)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                {{ __('Override') }}: {{ $recetaSucursalNombre ?? '' }}
            </span>
        @else
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                {{ __('Default (todas las sucursales)') }}
            </span>
        @endif
    </div>

    <!-- Buscar ingrediente -->
    <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Agregar ingrediente') }}</label>
        <div class="relative mt-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="busquedaIngrediente"
                wire:keydown.enter.prevent="agregarPrimerIngrediente"
                placeholder="{{ __('Buscar artículo por código o nombre...') }}"
                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm pl-8"
            />
            <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <!-- Resultados de búsqueda -->
        @if(count($resultadosBusqueda) > 0)
            <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-md max-h-40 overflow-y-auto bg-white dark:bg-gray-700">
                @foreach($resultadosBusqueda as $resultado)
                    <button
                        type="button"
                        wire:click="agregarIngrediente({{ $resultado['id'] }})"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm flex items-center justify-between border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                    >
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $resultado['nombre'] }}</span>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $resultado['codigo'] }}</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $resultado['unidad_medida'] }}</span>
                    </button>
                @endforeach
            </div>
        @elseif(strlen($busquedaIngrediente) >= 2)
            <div class="mt-1 px-3 py-2 text-sm text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 rounded-md">
                {{ __('No se encontraron artículos') }}
            </div>
        @endif
    </div>

    <!-- Ingredientes actuales -->
    @if(count($recetaIngredientes) > 0)
        <div class="space-y-2">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ingredientes') }} <span class="ml-1 px-1.5 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">{{ count($recetaIngredientes) }}</span></label>
            @foreach($recetaIngredientes as $index => $ingrediente)
                <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $ingrediente['nombre'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ingrediente['codigo'] }} · {{ $ingrediente['unidad_medida'] }}</div>
                    </div>
                    <div class="w-28">
                        <input
                            type="number"
                            wire:model="recetaIngredientes.{{ $index }}.cantidad"
                            step="0.001"
                            min="0.001"
                            class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                            placeholder="{{ __('Cantidad') }}"
                        />
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 w-12">{{ $ingrediente['unidad_medida'] }}</span>
                    <button type="button" wire:click="eliminarIngrediente({{ $index }})" class="text-red-500 hover:text-red-700 p-1 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Notas -->
    <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Notas de la receta') }}</label>
        <textarea
            wire:model="recetaNotas"
            rows="2"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
            placeholder="{{ __('Instrucciones o notas opcionales...') }}"
        ></textarea>
    </div>
</div>
