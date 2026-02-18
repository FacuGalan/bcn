<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Opcionales') }}</h2>
                        <button
                            wire:click="create"
                            class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los grupos de opciones para tus artículos') }}</p>
                </div>
                <button
                    wire:click="create"
                    class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Nuevo Grupo') }}
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Buscar grupo...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <select
                        wire:model.live="filterTipo"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Todos los tipos') }}</option>
                        <option value="seleccionable">{{ __('Seleccionable') }}</option>
                        <option value="cuantitativo">{{ __('Cuantitativo') }}</option>
                    </select>
                    <select
                        wire:model.live="filterStatus"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Todos') }}</option>
                        <option value="active">{{ __('Activos') }}</option>
                        <option value="inactive">{{ __('Inactivos') }}</option>
                        <option value="deleted">{{ __('Eliminados') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Vista Móvil (Tarjetas) -->
        <div class="sm:hidden space-y-3">
            @forelse($grupos as $grupo)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ $grupo->trashed() ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $grupo->nombre }}</div>
                            @if($grupo->descripcion)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($grupo->descripcion, 60) }}</div>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @if($grupo->trashed())
                                <button wire:click="restaurar({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white transition-colors duration-150" title="{{ __('Restaurar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            @else
                                <button wire:click="gestionarDisponibilidad({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-amber-500 text-sm font-medium rounded-md text-amber-600 hover:bg-amber-500 hover:text-white transition-colors duration-150" title="{{ __('Disponibilidad') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <button wire:click="gestionarAsignacion({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white transition-colors duration-150" title="{{ __('Asignar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </button>
                                <button wire:click="edit({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button wire:click="confirmarEliminar({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $grupo->tipo === 'seleccionable' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                            {{ $grupo->tipo === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                        </span>
                        @if($grupo->obligatorio)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Obligatorio') }}</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            {{ $grupo->opcionales_count }} {{ __('opciones') }}
                        </span>
                        @if($grupo->trashed())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Eliminado') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $grupo->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $grupo->activo ? __('Activo') : __('Inactivo') }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron grupos opcionales') }}</p>
                </div>
            @endforelse
            <div class="mt-4">{{ $grupos->links() }}</div>
        </div>

        <!-- Vista Desktop (Tabla) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Grupo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Opciones') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Selección') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($grupos as $grupo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ $grupo->trashed() ? 'opacity-60' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $grupo->nombre }}</div>
                                    @if($grupo->descripcion)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($grupo->descripcion, 80) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $grupo->tipo === 'seleccionable' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                        {{ $grupo->tipo === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                    </span>
                                    @if($grupo->obligatorio)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Obligatorio') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $grupo->opcionales_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ __('Mín') }}: {{ $grupo->min_seleccion }}
                                        @if($grupo->max_seleccion)
                                            / {{ __('Máx') }}: {{ $grupo->max_seleccion }}
                                        @else
                                            / {{ __('Máx') }}: {{ __('∞') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($grupo->trashed())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Eliminado') }}</span>
                                    @else
                                        <button
                                            wire:click="toggleStatus({{ $grupo->id }})"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 {{ $grupo->activo ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                        >
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $grupo->activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                        <span class="ml-2 text-xs text-gray-600 dark:text-gray-400">{{ $grupo->activo ? __('Activo') : __('Inactivo') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        @if($grupo->trashed())
                                            <button wire:click="restaurar({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                {{ __('Restaurar') }}
                                            </button>
                                        @else
                                            <button wire:click="gestionarDisponibilidad({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-amber-500 text-sm font-medium rounded-md text-amber-600 hover:bg-amber-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors duration-150" title="{{ __('Disponibilidad') }}">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('Disponibilidad') }}
                                            </button>
                                            <button wire:click="gestionarAsignacion({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-600 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition-colors duration-150" title="{{ __('Asignar a artículos') }}">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                {{ __('Asignar') }}
                                            </button>
                                            <button wire:click="edit({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                {{ __('Editar') }}
                                            </button>
                                            <button wire:click="confirmarEliminar({{ $grupo->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron grupos opcionales') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $grupos->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Grupo -->
    @if($showModal)
        <div
            x-data="{ show: @entangle('showModal').live }"
            x-show="show"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div @click="show = false; $wire.cancel()" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                {{ $editMode ? __('Editar Grupo Opcional') : __('Nuevo Grupo Opcional') }}
                            </h3>

                            <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                                <!-- Columna izquierda: Datos del grupo (30%) -->
                                <div class="lg:col-span-3 space-y-4">
                                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Configuración del grupo') }}</h4>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                                        <input type="text" wire:model="nombre" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" placeholder="{{ __('Ej: Panes a elección, Salsas...') }}" required />
                                        @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tipo') }} *</label>
                                        <select wire:model="tipo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                            <option value="seleccionable">{{ __('Seleccionable') }} - {{ __('sí/no por opción') }}</option>
                                            <option value="cuantitativo">{{ __('Cuantitativo') }} - {{ __('cantidad por opción') }}</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción') }}</label>
                                        <textarea wire:model="descripcion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"></textarea>
                                    </div>

                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Mín. selección') }}</label>
                                            <input type="number" wire:model="min_seleccion" min="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Máx. selección') }}</label>
                                            <input type="number" wire:model="max_seleccion" min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" placeholder="{{ __('Sin límite') }}" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Orden') }}</label>
                                            <input type="number" wire:model="orden" min="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" id="obligatorio" wire:model="obligatorio" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Obligatorio') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" id="activo" wire:model="activo" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Grupo activo') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Columna derecha: Opciones del grupo (70%) -->
                                <div class="lg:col-span-7 lg:border-l lg:border-gray-200 lg:dark:border-gray-700 lg:pl-6 flex flex-col">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            {{ __('Opciones del grupo') }}
                                            @if(count($opciones) > 0)
                                                <span class="ml-1 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full normal-case">{{ count($opciones) }}</span>
                                            @endif
                                        </h4>
                                    </div>

                                    <!-- Agregar nueva opción (siempre visible arriba) -->
                                    <div class="flex items-center gap-2 p-2 mb-3 border-2 border-dashed border-green-300 dark:border-green-600 rounded-md bg-green-50/50 dark:bg-green-900/10">
                                        <input type="text" wire:model="nuevaOpcionNombre" wire:keydown.enter.prevent="agregarOpcion" class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" placeholder="{{ __('Nueva opción...') }}" />
                                        <div class="relative w-28">
                                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 text-xs">$</span>
                                            <input type="number" wire:model="nuevaOpcionPrecio" step="0.01" min="0" class="w-full pl-5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                        </div>
                                        <button type="button" wire:click="agregarOpcion" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    </div>

                                    <!-- Lista de opciones (scrolleable) -->
                                    @if(count($opciones) > 0)
                                        <div class="space-y-1.5 max-h-[35vh] overflow-y-auto pr-1 flex-1" x-data x-ref="listaOpciones"
                                             @opcion-agregada.window="$nextTick(() => { $refs.listaOpciones.scrollTop = $refs.listaOpciones.scrollHeight })">
                                            @foreach($opciones as $index => $opcion)
                                                <div class="flex items-center gap-1.5 p-1.5 bg-gray-50 dark:bg-gray-700 rounded-md">
                                                    <div class="flex flex-col gap-0.5">
                                                        <button type="button" wire:click="moverOpcion({{ $index }}, 'up')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 {{ $index === 0 ? 'invisible' : '' }}">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                        </button>
                                                        <button type="button" wire:click="moverOpcion({{ $index }}, 'down')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 {{ $index === count($opciones) - 1 ? 'invisible' : '' }}">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                        </button>
                                                    </div>
                                                    <input type="text" wire:model="opciones.{{ $index }}.nombre" class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 py-1.5" placeholder="{{ __('Nombre') }}" />
                                                    <div class="relative w-28">
                                                        <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 text-xs">$</span>
                                                        <input type="number" wire:model="opciones.{{ $index }}.precio_extra" step="0.01" min="0" class="w-full pl-5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 py-1.5" />
                                                    </div>
                                                    <label class="flex items-center cursor-pointer" title="{{ __('Activo') }}">
                                                        <input type="checkbox" wire:model="opciones.{{ $index }}.activo" class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-600" />
                                                    </label>
                                                    @if(isset($opcion['id']))
                                                        <button type="button" wire:click="editarRecetaOpcional({{ $opcion['id'] }})" class="text-amber-500 hover:text-amber-700 p-0.5" title="{{ __('Receta') }}">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                                        </button>
                                                    @endif
                                                    <button type="button" wire:click="eliminarOpcion({{ $index }})" class="text-red-500 hover:text-red-700 p-0.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="flex-1 flex items-center justify-center text-sm text-gray-400 dark:text-gray-500 py-8">
                                            {{ __('Agregá opciones usando el campo de arriba') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ $editMode ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button type="button" @click="show = false; $wire.cancel()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Receta de Opcional -->
    @if($showRecetaModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto" aria-labelledby="modal-receta-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelarRecetaOpcional" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-receta-title">
                            {{ __('Receta de') }}: {{ $opcionalRecetaNombre }}
                        </h3>

                        <div class="max-h-[60vh] overflow-y-auto pr-2">
                            @include('livewire.articulos._receta-editor')
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" wire:click="guardarRecetaOpcional" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                            {{ __('Guardar Receta') }}
                        </button>
                        @if($recetaId)
                            <button type="button" wire:click="confirmarEliminarRecetaOpcional" class="w-full inline-flex justify-center rounded-md border border-red-600 shadow-sm px-4 py-2 text-base font-medium text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm transition-colors">
                                {{ __('Eliminar Receta') }}
                            </button>
                        @endif
                        <button type="button" wire:click="cancelarRecetaOpcional" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:w-auto sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmación Eliminar Receta -->
    @if($showDeleteRecetaModal)
        <div class="fixed inset-0 z-[75] overflow-y-auto" aria-labelledby="modal-delete-receta" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminarRecetaOpcional"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white">{{ __('Eliminar receta') }}</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('¿Estás seguro de eliminar la receta de') }} <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ $opcionalRecetaNombre }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('Se eliminarán todos los ingredientes. Esta acción no se puede deshacer.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button" wire:click="eliminarRecetaOpcional" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                            {{ __('Eliminar') }}
                        </button>
                        <button type="button" wire:click="cancelarEliminarRecetaOpcional" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                                {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Disponibilidad por Sucursal -->
    @if($showDisponibilidadModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-disponibilidad" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cerrarDisponibilidad" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-disponibilidad">
                            {{ __('Disponibilidad') }}: {{ $disponibilidadGrupoNombre }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            {{ __('Marca como agotado o disponible cada opción por sucursal') }}
                        </p>

                        <div class="max-h-[60vh] overflow-auto">
                            @if(count($disponibilidadOpciones) > 0 && count($disponibilidadSucursales) > 0)
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Opción') }}</th>
                                            @foreach($disponibilidadSucursales as $sucursal)
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ $sucursal['nombre'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($disponibilidadOpciones as $opcion)
                                            <tr class="{{ !$opcion['activo'] ? 'opacity-50' : '' }}">
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $opcion['nombre'] }}</span>
                                                    @if(!$opcion['activo'])
                                                        <span class="ml-1 text-xs text-gray-400">({{ __('Inactivo') }})</span>
                                                    @endif
                                                </td>
                                                @foreach($disponibilidadSucursales as $sucursal)
                                                    <td class="px-3 py-3 text-center">
                                                        @if(($opcion['por_sucursal'][$sucursal['id']]['asignado'] ?? false))
                                                            <button
                                                                wire:click="toggleDisponibilidad({{ $opcion['id'] }}, {{ $sucursal['id'] }})"
                                                                class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 {{ ($opcion['por_sucursal'][$sucursal['id']]['disponible'] ?? true) ? 'bg-green-600' : 'bg-red-400' }}"
                                                                title="{{ ($opcion['por_sucursal'][$sucursal['id']]['disponible'] ?? true) ? __('Disponible') : __('Agotado') }}"
                                                            >
                                                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ ($opcion['por_sucursal'][$sucursal['id']]['disponible'] ?? true) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                            </button>
                                                        @else
                                                            <span class="text-xs text-gray-400" title="{{ __('No asignado en esta sucursal') }}">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <p class="text-sm">{{ __('Este grupo no tiene opciones definidas') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="cerrarDisponibilidad" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Asignación Masiva -->
    @if($showAsignacionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-asignacion" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cerrarAsignacion" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-asignacion">
                            {{ __('Asignar') }}: {{ $asignacionGrupoNombre }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            {{ __('Selecciona los artículos a los que quieres asignar este grupo opcional') }}
                        </p>

                        <!-- Búsqueda -->
                        <div class="mb-4">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaArticuloAsignacion"
                                placeholder="{{ __('Buscar artículo por nombre o código...') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            />
                        </div>

                        @if(count($articulosSeleccionados) > 0)
                            <div class="mb-3 px-3 py-2 bg-bcn-primary/10 rounded-md">
                                <span class="text-sm font-medium text-bcn-primary">{{ count($articulosSeleccionados) }} {{ __('artículos seleccionados') }}</span>
                            </div>
                        @endif

                        <!-- Lista de artículos -->
                        <div class="max-h-[50vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                            @php $articulosAsignacion = $this->articulosParaAsignacion; @endphp
                            @if(count($articulosAsignacion) > 0)
                                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($articulosAsignacion as $articulo)
                                        <div class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 {{ $articulo['ya_asignado'] ? 'opacity-50' : '' }}">
                                            @if(!$articulo['ya_asignado'])
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleArticuloSeleccionado({{ $articulo['id'] }})"
                                                    {{ in_array($articulo['id'], $articulosSeleccionados) ? 'checked' : '' }}
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 mr-3"
                                                />
                                            @else
                                                <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $articulo['nombre'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo['codigo'] }}</div>
                                            </div>
                                            @if($articulo['ya_asignado'])
                                                <span class="ml-2 text-xs text-green-600 dark:text-green-400 flex-shrink-0">{{ __('Ya asignado') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <p class="text-sm">
                                        @if(strlen($busquedaArticuloAsignacion) < 2)
                                            {{ __('Escribe al menos 2 caracteres para buscar') }}
                                        @else
                                            {{ __('No se encontraron artículos') }}
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        @if(count($articulosSeleccionados) > 0)
                            <button type="button" wire:click="asignarMasivo" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:w-auto sm:text-sm">
                                {{ __('Asignar a') }} {{ count($articulosSeleccionados) }} {{ __('artículos') }}
                            </button>
                        @endif
                        <button type="button" wire:click="cerrarAsignacion" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 sm:mt-0 sm:w-auto sm:text-sm">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmación Eliminar -->
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminar"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white">{{ __('Eliminar grupo opcional') }}</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('¿Estás seguro de eliminar el grupo') }} <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ $nombreGrupoAEliminar }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('El grupo y sus opciones serán marcados como eliminados. Podrás restaurarlos desde el filtro "Eliminados".') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button" wire:click="eliminar" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            {{ __('Eliminar') }}
                        </button>
                        <button type="button" wire:click="cancelarEliminar" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
