<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary flex items-center h-10 sm:h-auto">{{ __('Gestión de Etiquetas') }}</h2>
                        <!-- Botones móviles -->
                        <div class="sm:hidden flex items-center gap-2">
                            <a
                                href="{{ route('articulos.asignar-etiquetas') }}"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-secondary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-secondary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                :title="__('Asignar etiquetas a artículos')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z" />
                                </svg>
                            </a>
                            <button
                                wire:click="createGrupo"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                :title="__('Crear nuevo grupo de etiquetas')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los grupos de etiquetas y sus valores para clasificar artículos') }}</p>
                </div>
                <!-- Botones Desktop -->
                <div class="hidden sm:flex items-center gap-2">
                    <a
                        href="{{ route('articulos.asignar-etiquetas') }}"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-secondary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-secondary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        :title="__('Asignar etiquetas a artículos')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z" />
                        </svg>
                        {{ __('Asignar a Artículos') }}
                    </a>
                    <button
                        wire:click="createGrupo"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        :title="__('Crear nuevo grupo de etiquetas')"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nuevo Grupo') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <!-- Botón de filtros (solo móvil) -->
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="toggleFilters"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($search || $filterStatus !== 'all')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
                            </span>
                        @endif
                    </span>
                    <svg class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Contenedor de filtros -->
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Búsqueda -->
                    <div class="sm:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Nombre de grupo o etiqueta...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro de estado -->
                    <div>
                        <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                        <select
                            id="filterStatus"
                            wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todos') }}</option>
                            <option value="active">{{ __('Activos') }}</option>
                            <option value="inactive">{{ __('Inactivos') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Controles de expandir/colapsar -->
                <div class="mt-4 flex gap-2">
                    <button
                        wire:click="expandirTodos"
                        class="text-xs text-bcn-primary hover:text-bcn-secondary transition-colors"
                    >
                        {{ __('Expandir todos') }}
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        wire:click="colapsarTodos"
                        class="text-xs text-bcn-primary hover:text-bcn-secondary transition-colors"
                    >
                        {{ __('Colapsar todos') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de grupos (acordeón) -->
        <div class="space-y-4">
            @forelse($grupos as $grupo)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Encabezado del grupo -->
                    <div class="p-4 flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                         wire:click="toggleGrupo({{ $grupo->id }})">
                        <div class="flex items-center gap-3 flex-1">
                            <!-- Indicador de expansión -->
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-transform {{ in_array($grupo->id, $gruposExpandidos) ? 'rotate-90' : '' }}"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>

                            <!-- Color del grupo -->
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: {{ $grupo->color }}20;">
                                <div class="w-5 h-5 rounded-full" style="background-color: {{ $grupo->color }};"></div>
                            </div>

                            <!-- Info del grupo -->
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $grupo->nombre }}</span>
                                    @if($grupo->codigo)
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">({{ $grupo->codigo }})</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $grupo->etiquetas_count }} {{ __('etiqueta(s)') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $grupo->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $grupo->activo ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones del grupo -->
                        <div class="flex items-center gap-2" wire:click.stop>
                            <button
                                wire:click="createEtiqueta({{ $grupo->id }})"
                                class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 dark:hover:bg-gray-600 rounded-md transition-colors"
                                :title="__('Agregar etiqueta')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                            <button
                                wire:click="editGrupo({{ $grupo->id }})"
                                class="p-2 text-bcn-primary hover:text-bcn-secondary hover:bg-bcn-light dark:hover:bg-gray-600 rounded-md transition-colors"
                                :title="__('Editar grupo')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                wire:click="toggleGrupoStatus({{ $grupo->id }})"
                                class="p-2 {{ $grupo->activo ? 'text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50' : 'text-green-600 hover:text-green-800 hover:bg-green-50' }} dark:hover:bg-gray-600 rounded-md transition-colors"
                                :title="__($grupo->activo ? 'Desactivar' : 'Activar') + ' ' + __('grupo')"
                            >
                                @if($grupo->activo)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </button>
                            <button
                                wire:click="confirmarEliminarGrupo({{ $grupo->id }})"
                                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-gray-600 rounded-md transition-colors"
                                :title="__('Eliminar grupo')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Contenido del grupo (etiquetas) -->
                    @if(in_array($grupo->id, $gruposExpandidos))
                        <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                            @if($grupo->etiquetas->count() > 0)
                                <div class="p-4">
                                    <table class="w-full">
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach($grupo->etiquetas as $etiqueta)
                                                <tr class="hover:bg-white dark:hover:bg-gray-800 transition-colors {{ !$etiqueta->activo ? 'opacity-50' : '' }}">
                                                    <td class="py-2 pr-4">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $etiqueta->color ?? $grupo->color }};"></div>
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                            @if($etiqueta->codigo)
                                                                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">({{ $etiqueta->codigo }})</span>
                                                            @endif
                                                            @if(!$etiqueta->activo)
                                                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">- {{ __('Inactiva') }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="py-2 text-right whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1">
                                                            <button
                                                                wire:click="editEtiqueta({{ $etiqueta->id }})"
                                                                class="p-1.5 text-bcn-primary hover:text-bcn-secondary hover:bg-bcn-light dark:hover:bg-gray-700 rounded transition-colors"
                                                                :title="__('Editar')"
                                                            >
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                            </button>
                                                            <button
                                                                wire:click="toggleEtiquetaStatus({{ $etiqueta->id }})"
                                                                class="p-1.5 rounded transition-colors {{ $etiqueta->activo ? 'text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 dark:hover:bg-gray-700' : 'text-green-600 hover:text-green-800 hover:bg-green-50 dark:hover:bg-gray-700' }}"
                                                                :title="__($etiqueta->activo ? 'Desactivar' : 'Activar')"
                                                            >
                                                                @if($etiqueta->activo)
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                                    </svg>
                                                                @else
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                @endif
                                                            </button>
                                                            <button
                                                                wire:click="confirmarEliminarEtiqueta({{ $etiqueta->id }})"
                                                                class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-gray-700 rounded transition-colors"
                                                                :title="__('Eliminar')"
                                                            >
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    <p class="mt-2 text-sm">{{ __('Este grupo no tiene etiquetas') }}</p>
                                    <button
                                        wire:click="createEtiqueta({{ $grupo->id }})"
                                        class="mt-3 inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        {{ __('Agregar primera etiqueta') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ __('No hay grupos de etiquetas') }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Crea tu primer grupo de etiquetas para empezar a clasificar artículos.') }}</p>
                    <button
                        wire:click="createGrupo"
                        class="mt-4 inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 transition"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Crear Primer Grupo') }}
                    </button>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal para crear/editar grupo -->
    @if($showGrupoModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelGrupo" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="saveGrupo">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                    {{ $editModeGrupo ? __('Editar Grupo de Etiquetas') : __('Nuevo Grupo de Etiquetas') }}
                                </h3>

                                <div class="space-y-4">
                                    <!-- Nombre -->
                                    <div>
                                        <label for="grupoNombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                                        <input
                                            type="text"
                                            id="grupoNombre"
                                            wire:model="grupoNombre"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                            :placeholder="__('Ej: Marca, Color, Tamaño...')"
                                            required
                                        />
                                        @error('grupoNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Código -->
                                    <div>
                                        <label for="grupoCodigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código (opcional)') }}</label>
                                        <input
                                            type="text"
                                            id="grupoCodigo"
                                            wire:model="grupoCodigo"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 font-mono uppercase"
                                            :placeholder="__('Ej: MARCA')"
                                            maxlength="50"
                                        />
                                        @error('grupoCodigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Descripción -->
                                    <div>
                                        <label for="grupoDescripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción (opcional)') }}</label>
                                        <textarea
                                            id="grupoDescripcion"
                                            wire:model="grupoDescripcion"
                                            rows="2"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                            :placeholder="__('Descripción del grupo...')"
                                        ></textarea>
                                        @error('grupoDescripcion') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Color -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Color') }} *</label>
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="color"
                                                wire:model.live="grupoColor"
                                                class="h-12 w-24 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                                            />
                                            <input
                                                type="text"
                                                wire:model.live="grupoColor"
                                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 font-mono text-sm"
                                                placeholder="#3B82F6"
                                                maxlength="7"
                                            />
                                        </div>
                                        @error('grupoColor') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Estado activo -->
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="grupoActivo"
                                            wire:model="grupoActivo"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                        />
                                        <label for="grupoActivo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Grupo activo') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editModeGrupo ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                wire:click="cancelGrupo"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal para crear/editar etiqueta -->
    @if($showEtiquetaModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelEtiqueta" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="saveEtiqueta">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                    {{ $editModeEtiqueta ? __('Editar Etiqueta') : __('Nueva Etiqueta') }}
                                </h3>

                                <div class="space-y-4">
                                    <!-- Nombre -->
                                    <div>
                                        <label for="etiquetaNombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                                        <input
                                            type="text"
                                            id="etiquetaNombre"
                                            wire:model="etiquetaNombre"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                            :placeholder="__('Ej: Samsung, Rojo, Grande...')"
                                            required
                                        />
                                        @error('etiquetaNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Código -->
                                    <div>
                                        <label for="etiquetaCodigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código (opcional)') }}</label>
                                        <input
                                            type="text"
                                            id="etiquetaCodigo"
                                            wire:model="etiquetaCodigo"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 font-mono uppercase"
                                            :placeholder="__('Ej: SAMS')"
                                            maxlength="50"
                                        />
                                        @error('etiquetaCodigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Color (opcional) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('Color específico (opcional)') }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal ml-1">- {{ __('Si no se define, usa el color del grupo') }}</span>
                                        </label>
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="color"
                                                wire:model.live="etiquetaColor"
                                                class="h-12 w-24 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                                                value="{{ $etiquetaColor ?? '#6B7280' }}"
                                            />
                                            <input
                                                type="text"
                                                wire:model.live="etiquetaColor"
                                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 font-mono text-sm"
                                                :placeholder="__('Vacío = usa color del grupo')"
                                                maxlength="7"
                                            />
                                            @if($etiquetaColor)
                                                <button
                                                    type="button"
                                                    wire:click="$set('etiquetaColor', null)"
                                                    class="text-xs text-red-600 hover:text-red-800"
                                                >
                                                    {{ __('Limpiar') }}
                                                </button>
                                            @endif
                                        </div>
                                        @error('etiquetaColor') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Estado activo -->
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="etiquetaActivo"
                                            wire:model="etiquetaActivo"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                        />
                                        <label for="etiquetaActivo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">{{ __('Etiqueta activa') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editModeEtiqueta ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                wire:click="cancelEtiqueta"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de confirmación de eliminación -->
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminar"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    {{ __('Eliminar') }} {{ $deleteType === 'grupo' ? __('grupo') : __('etiqueta') }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('¿Estás seguro de eliminar') }} {{ $deleteType === 'grupo' ? __('el grupo') : __('la etiqueta') }}
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">"{{ $nombreItemAEliminar }}"</span>?
                                    </p>
                                    @if($deleteType === 'grupo')
                                        <p class="text-sm text-red-600 mt-2">
                                            {{ __('Esta acción también eliminará todas las etiquetas del grupo.') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            type="button"
                            wire:click="eliminar"
                            class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('Eliminar') }}
                        </button>
                        <button
                            type="button"
                            wire:click="cancelarEliminar"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
