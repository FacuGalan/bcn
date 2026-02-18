<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex-1">
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Asignar Opcionales') }}</h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Asigna grupos de opcionales a tus artículos. Al asignar, se crean para todas las sucursales.') }}</p>
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
                            placeholder="{{ __('Buscar artículo por código o nombre...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>
                    <select
                        wire:model.live="filterAsignacion"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="all">{{ __('Todos') }}</option>
                        <option value="con_grupos">{{ __('Con grupos') }}</option>
                        <option value="sin_grupos">{{ __('Sin grupos') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Vista Móvil -->
        <div class="sm:hidden space-y-3">
            @forelse($articulos as $articulo)
                <button
                    wire:click="gestionarArticulo({{ $articulo->id }})"
                    class="w-full text-left bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:border-bcn-primary transition-colors"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($articulo->grupos_count > 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                    {{ $articulo->grupos_count }} {{ trans_choice('grupo|grupos', $articulo->grupos_count) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    {{ __('Sin grupos') }}
                                </span>
                            @endif
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </button>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron artículos') }}</p>
                </div>
            @endforelse
            <div class="mt-4">{{ $articulos->links() }}</div>
        </div>

        <!-- Vista Desktop (Tabla) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Código') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Categoría') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Grupos Opcionales') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($articulos as $articulo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $articulo->codigo }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($articulo->categoriaModel)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $articulo->categoriaModel->color ?? '#e5e7eb' }}20; color: {{ $articulo->categoriaModel->color ?? '#6b7280' }};">
                                            {{ $articulo->categoriaModel->nombre }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($articulo->grupos_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                            {{ $articulo->grupos_count }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        wire:click="gestionarArticulo({{ $articulo->id }})"
                                        class="inline-flex items-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                        {{ __('Gestionar') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron artículos') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $articulos->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Gestionar Grupos del Artículo -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancelarModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                @if($mostrandoAgregarGrupo)
                                    {{ __('Agregar grupo a') }}: {{ $articuloNombre }}
                                @else
                                    {{ __('Opcionales de') }}: {{ $articuloNombre }}
                                @endif
                            </h3>
                            @if(!$mostrandoAgregarGrupo)
                                <button
                                    type="button"
                                    wire:click="abrirAgregarGrupo"
                                    class="inline-flex items-center px-3 py-2 bg-bcn-primary border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-opacity-90 transition"
                                >
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    {{ __('Agregar Grupo') }}
                                </button>
                            @endif
                        </div>

                        {{-- Vista: Agregar Grupo (inline) --}}
                        @if($mostrandoAgregarGrupo)
                            <div>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaGrupo"
                                    placeholder="{{ __('Buscar grupo por nombre...') }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    autofocus
                                />
                            </div>

                            <div class="mt-3 max-h-[55vh] overflow-y-auto space-y-2 pr-1">
                                @forelse($this->gruposDisponibles as $grupo)
                                    <button
                                        type="button"
                                        wire:click="asignarGrupo({{ $grupo['id'] }})"
                                        class="w-full text-left px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-bcn-primary hover:bg-bcn-primary/5 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $grupo['nombre'] }}</span>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-xs {{ $grupo['tipo'] === 'seleccionable' ? 'text-blue-600 dark:text-blue-400' : 'text-purple-600 dark:text-purple-400' }}">
                                                        {{ $grupo['tipo'] === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                                    </span>
                                                    @if($grupo['obligatorio'])
                                                        <span class="text-xs text-red-600 dark:text-red-400">{{ __('Obligatorio') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $grupo['opcionales_count'] }} {{ __('opciones') }}</span>
                                                <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        {{ __('No hay grupos disponibles para asignar') }}
                                    </div>
                                @endforelse
                            </div>

                        {{-- Vista: Grupos Asignados --}}
                        @else
                            <div class="max-h-[55vh] overflow-y-auto space-y-3 pr-1">
                                @if(count($gruposAsignados) > 0)
                                    @foreach($gruposAsignados as $index => $grupo)
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $grupo['nombre'] }}</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $grupo['tipo'] === 'seleccionable' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                        {{ $grupo['tipo'] === 'seleccionable' ? __('Seleccionable') : __('Cuantitativo') }}
                                                    </span>
                                                    @if($grupo['obligatorio'])
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Obligatorio') }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    {{-- Flechas de orden --}}
                                                    <button
                                                        type="button"
                                                        wire:click="moverGrupoArriba({{ $index }})"
                                                        @if($index === 0) disabled @endif
                                                        class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors {{ $index === 0 ? 'opacity-30 cursor-not-allowed' : 'text-gray-500 dark:text-gray-400' }}"
                                                        title="{{ __('Subir') }}"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="moverGrupoAbajo({{ $index }})"
                                                        @if($index === count($gruposAsignados) - 1) disabled @endif
                                                        class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors {{ $index === count($gruposAsignados) - 1 ? 'opacity-30 cursor-not-allowed' : 'text-gray-500 dark:text-gray-400' }}"
                                                        title="{{ __('Bajar') }}"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="confirmarDesasignar({{ $grupo['grupo_id'] }}, '{{ addslashes($grupo['nombre']) }}')"
                                                        class="text-red-500 hover:text-red-700 p-1 ml-1"
                                                        title="{{ __('Quitar grupo') }}"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            @if(count($grupo['opciones']) > 0)
                                                <div class="px-4 py-2 space-y-1">
                                                    @foreach($grupo['opciones'] as $opcion)
                                                        <div class="flex items-center justify-between text-sm py-1">
                                                            <span class="text-gray-700 dark:text-gray-300">{{ $opcion['nombre'] }}</span>
                                                            <span class="text-gray-500 dark:text-gray-400">
                                                                @if((float)$opcion['precio_extra'] > 0)
                                                                    +${{ number_format($opcion['precio_extra'], 2) }}
                                                                @else
                                                                    $0.00
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 italic">
                                                    {{ __('Sin opciones activas') }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                        </svg>
                                        <p class="mt-2 text-sm">{{ __('Este artículo no tiene grupos opcionales asignados') }}</p>
                                        <p class="text-xs text-gray-400 mt-1">{{ __('Haz click en "Agregar Grupo" para asignar uno') }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        @if($mostrandoAgregarGrupo)
                            <button type="button" wire:click="cancelarAgregarGrupo" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                {{ __('Volver') }}
                            </button>
                        @else
                            <button type="button" wire:click="cancelarModal" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                                {{ __('Cerrar') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Submodal Confirmar Desasignación -->
    @if($showDesasignarModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-desasignar" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarDesasignar"></div>
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
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white">{{ __('Quitar grupo opcional') }}</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('¿Estás seguro de quitar el grupo') }} <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ $nombreGrupoADesasignar }}"</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('Se eliminará de TODAS las sucursales, incluyendo precios y configuraciones personalizadas.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button type="button" wire:click="desasignarGrupo" class="inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-colors">
                            {{ __('Quitar grupo') }}
                        </button>
                        <button type="button" wire:click="cancelarDesasignar" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
