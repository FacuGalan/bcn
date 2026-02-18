<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6 flex items-center justify-between">
            <div class="flex-1">
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Recetas') }}</h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Recetas genéricas definidas para artículos y opcionales') }}</p>
            </div>
            <button wire:click="abrirNuevaReceta" class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Nueva Receta') }}
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Buscar por nombre o código...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <select
                        wire:model.live="filterTipo"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Todos los tipos') }}</option>
                        <option value="Articulo">{{ __('Artículos') }}</option>
                        <option value="Opcional">{{ __('Opcionales') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Vista Móvil -->
        <div class="sm:hidden space-y-3">
            @forelse($recetas as $receta)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $receta->recetable_type === 'Articulo' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                    {{ $receta->recetable_type === 'Articulo' ? __('Artículo') : __('Opcional') }}
                                </span>
                            </div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $receta->recetable?->nombre ?? __('Eliminado') }}</div>
                            @if($receta->recetable_type === 'Articulo' && $receta->recetable?->codigo)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $receta->recetable->codigo }}</div>
                            @elseif($receta->recetable_type === 'Opcional' && $receta->recetable?->grupoOpcional)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $receta->recetable->grupoOpcional->nombre }}</div>
                            @endif
                        </div>
                        <div class="flex gap-1 ml-2">
                            <button wire:click="editarReceta({{ $receta->id }})" class="p-2 border border-bcn-primary text-bcn-primary rounded-md hover:bg-bcn-primary hover:text-white transition-colors" title="{{ __('Editar') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button wire:click="abrirCopiar({{ $receta->id }})" class="p-2 border border-green-600 text-green-600 rounded-md hover:bg-green-600 hover:text-white transition-colors" title="{{ __('Copiar a') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $receta->ingredientes->count() }} {{ __('ingredientes') }}
                        @if($receta->notas)
                            · {{ Str::limit($receta->notas, 40) }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No hay recetas definidas') }}</p>
                </div>
            @endforelse
            <div class="mt-4">{{ $recetas->links() }}</div>
        </div>

        <!-- Vista Desktop -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Nombre') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Ingredientes') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Cant. producida') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Notas') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($recetas as $receta)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $receta->recetable_type === 'Articulo' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                        {{ $receta->recetable_type === 'Articulo' ? __('Artículo') : __('Opcional') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $receta->recetable?->nombre ?? __('Eliminado') }}</div>
                                    @if($receta->recetable_type === 'Articulo' && $receta->recetable?->codigo)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $receta->recetable->codigo }}</div>
                                    @elseif($receta->recetable_type === 'Opcional' && $receta->recetable?->grupoOpcional)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $receta->recetable->grupoOpcional->nombre }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $receta->ingredientes->count() }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm text-gray-900 dark:text-white">@cantidad($receta->cantidad_producida)</span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($receta->notas)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($receta->notas, 50) }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editarReceta({{ $receta->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150" title="{{ __('Editar') }}">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            {{ __('Editar') }}
                                        </button>
                                        <button wire:click="abrirCopiar({{ $receta->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150" title="{{ __('Copiar a') }}">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            {{ __('Copiar a') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="mt-2">{{ __('No hay recetas definidas') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $recetas->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Editor de Receta -->
    @if($showRecetaModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-receta" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelarReceta" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    {{ __('Receta') }}: {{ $recetableNombre }}
                                </h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1 {{ $recetableType === 'Articulo' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                    {{ $recetableType === 'Articulo' ? __('Artículo') : __('Opcional') }}
                                </span>
                            </div>
                            @if($recetaId)
                                <button type="button" wire:click="confirmarEliminarReceta" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                    {{ __('Eliminar receta') }}
                                </button>
                            @endif
                        </div>

                        <div class="max-h-[60vh] overflow-y-auto pr-1">
                            @include('livewire.articulos._receta-editor')
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="guardarReceta" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Guardar Receta') }}
                        </button>
                        <button type="button" wire:click="cancelarReceta" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminar Receta -->
    @if($showDeleteRecetaModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminarReceta"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Eliminar receta') }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('¿Estás seguro de eliminar la receta de') }} <span class="font-semibold">"{{ $recetableNombre }}"</span>?
                                    {{ __('Se eliminarán todos los ingredientes.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button" wire:click="eliminarReceta" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto">
                            {{ __('Eliminar') }}
                        </button>
                        <button type="button" wire:click="cancelarEliminarReceta" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Nueva Receta -->
    @if($showNuevaRecetaModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-nueva-receta" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cerrarNuevaReceta" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                {{ __('Nueva Receta') }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">
                                    {{ __('Paso') }} {{ $nuevaRecetaPaso }} {{ __('de') }} 2
                                </span>
                            </h3>
                        </div>

                        @if($nuevaRecetaPaso === 1)
                            {{-- PASO 1: Armar receta --}}
                            <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                                <!-- Tipo -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de destino') }}</label>
                                    <div class="flex gap-3">
                                        <label class="flex items-center gap-2 px-3 py-2 rounded-md border cursor-pointer {{ $nuevaRecetaTipo === 'Articulo' ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400' }}">
                                            <input type="radio" wire:model.live="nuevaRecetaTipo" value="Articulo" class="sr-only" />
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Artículos') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-2 rounded-md border cursor-pointer {{ $nuevaRecetaTipo === 'Opcional' ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400' }}">
                                            <input type="radio" wire:model.live="nuevaRecetaTipo" value="Opcional" class="sr-only" />
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">{{ __('Opcionales') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Cantidad producida -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Cantidad producida por receta') }}</label>
                                    <input
                                        type="number"
                                        wire:model="nuevaRecetaCantProducida"
                                        step="0.001"
                                        min="0.001"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    />
                                </div>

                                <!-- Buscar ingrediente -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Agregar ingrediente') }}</label>
                                    <div class="relative mt-1">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="nuevaRecetaBusquedaIng"
                                            wire:keydown.enter.prevent="nuevaRecetaAgregarPrimerIng"
                                            placeholder="{{ __('Buscar artículo por código o nombre...') }}"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm pl-8"
                                        />
                                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>

                                    @if(count($nuevaRecetaResultadosIng) > 0)
                                        <div class="mt-1 border border-gray-200 dark:border-gray-600 rounded-md max-h-40 overflow-y-auto bg-white dark:bg-gray-700">
                                            @foreach($nuevaRecetaResultadosIng as $resultado)
                                                <button
                                                    type="button"
                                                    wire:click="nuevaRecetaAgregarIng({{ $resultado['id'] }})"
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
                                    @elseif(strlen($nuevaRecetaBusquedaIng) >= 2)
                                        <div class="mt-1 px-3 py-2 text-sm text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 rounded-md">
                                            {{ __('No se encontraron artículos') }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Ingredientes agregados -->
                                @if(count($nuevaRecetaIngredientes) > 0)
                                    <div class="space-y-2">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ingredientes') }} <span class="ml-1 px-1.5 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">{{ count($nuevaRecetaIngredientes) }}</span></label>
                                        @foreach($nuevaRecetaIngredientes as $index => $ingrediente)
                                            <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded-md">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $ingrediente['nombre'] }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ingrediente['codigo'] }} · {{ $ingrediente['unidad_medida'] }}</div>
                                                </div>
                                                <div class="w-28">
                                                    <input
                                                        type="number"
                                                        wire:model="nuevaRecetaIngredientes.{{ $index }}.cantidad"
                                                        step="0.001"
                                                        min="0.001"
                                                        class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        placeholder="{{ __('Cantidad') }}"
                                                    />
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 w-12">{{ $ingrediente['unidad_medida'] }}</span>
                                                <button type="button" wire:click="nuevaRecetaEliminarIng({{ $index }})" class="text-red-500 hover:text-red-700 p-1 flex-shrink-0">
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
                                        wire:model="nuevaRecetaNotas"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                        placeholder="{{ __('Instrucciones o notas opcionales...') }}"
                                    ></textarea>
                                </div>
                            </div>
                        @else
                            {{-- PASO 2: Seleccionar destinos --}}
                            <div class="space-y-4">
                                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-md text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium">{{ __('Receta') }}:</span>
                                    {{ count($nuevaRecetaIngredientes) }} {{ __('ingredientes') }}
                                    · {{ __('Cant. producida') }}: {{ $nuevaRecetaCantProducida }}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-2 {{ $nuevaRecetaTipo === 'Articulo' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                        {{ $nuevaRecetaTipo === 'Articulo' ? __('Artículos') : __('Opcionales') }}
                                    </span>
                                </div>

                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Selecciona los destinos donde asignar esta receta') }}
                                </p>

                                <!-- Búsqueda -->
                                <div>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="nuevaRecetaBusquedaDest"
                                        placeholder="{{ __('Buscar destino...') }}"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    />
                                </div>

                                @if(count($nuevaRecetaDestinos) > 0)
                                    <div class="px-3 py-2 bg-bcn-primary/10 rounded-md">
                                        <span class="text-sm font-medium text-bcn-primary">{{ count($nuevaRecetaDestinos) }} {{ __('seleccionados') }}</span>
                                    </div>
                                @endif

                                <!-- Lista de destinos -->
                                <div class="max-h-[40vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                    @php $listaDestinos = $this->nuevaRecetaListaDestinos; @endphp
                                    @if(count($listaDestinos) > 0)
                                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($listaDestinos as $destino)
                                                <label class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleNuevaRecetaDestino({{ $destino['id'] }})"
                                                        {{ in_array($destino['id'], $nuevaRecetaDestinos) ? 'checked' : '' }}
                                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 mr-3"
                                                    />
                                                    <span class="text-sm text-gray-900 dark:text-white">{{ $destino['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                            <p class="text-sm">{{ $nuevaRecetaTipo === 'Articulo' ? __('No se encontraron artículos sin receta') : __('No se encontraron opcionales sin receta') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        @if($nuevaRecetaPaso === 1)
                            <button type="button" wire:click="nuevaRecetaSiguiente" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                                {{ __('Siguiente') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @else
                            @if(count($nuevaRecetaDestinos) > 0)
                                <button type="button" wire:click="guardarNuevaReceta" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                                    {{ __('Crear receta para') }} {{ count($nuevaRecetaDestinos) }}
                                </button>
                            @endif
                            <button type="button" wire:click="nuevaRecetaVolver" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                {{ __('Volver') }}
                            </button>
                        @endif
                        <button type="button" wire:click="cerrarNuevaReceta" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:w-auto sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Copiar Receta -->
    @if($showCopiarModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-copiar" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cerrarCopiar" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-1">
                            {{ __('Copiar receta de') }}: {{ $copiarDesdeNombre }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            {{ __('Selecciona los destinos donde copiar esta receta') }}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-1 {{ $copiarDesdeType === 'Articulo' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                {{ $copiarDesdeType === 'Articulo' ? __('Solo artículos sin receta') : __('Solo opcionales sin receta') }}
                            </span>
                        </p>

                        <!-- Búsqueda -->
                        <div class="mb-4">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaDestino"
                                placeholder="{{ __('Buscar destino...') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            />
                        </div>

                        @if(count($destinosSeleccionados) > 0)
                            <div class="mb-3 px-3 py-2 bg-bcn-primary/10 rounded-md">
                                <span class="text-sm font-medium text-bcn-primary">{{ count($destinosSeleccionados) }} {{ __('seleccionados') }}</span>
                            </div>
                        @endif

                        <!-- Lista de destinos -->
                        <div class="max-h-[50vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                            @php $destinos = $this->destinosCopia; @endphp
                            @if(count($destinos) > 0)
                                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($destinos as $destino)
                                        <label class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:click="toggleDestinoSeleccionado({{ $destino['id'] }})"
                                                {{ in_array($destino['id'], $destinosSeleccionados) ? 'checked' : '' }}
                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 mr-3"
                                            />
                                            <span class="text-sm text-gray-900 dark:text-white">{{ $destino['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <p class="text-sm">{{ __('No se encontraron destinos sin receta') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        @if(count($destinosSeleccionados) > 0)
                            <button type="button" wire:click="ejecutarCopia" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                                {{ __('Copiar a') }} {{ count($destinosSeleccionados) }}
                            </button>
                        @endif
                        <button type="button" wire:click="cerrarCopiar" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:w-auto sm:text-sm">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
