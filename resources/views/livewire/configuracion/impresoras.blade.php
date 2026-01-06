<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-bcn-secondary">Configuracion de Impresoras</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Administra las impresoras y sus asignaciones por sucursal/caja</p>
            </div>
            <button
                wire:click="create"
                class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Impresora
            </button>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Nombre de la impresora..."
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                        />
                    </div>
                    <div>
                        <label for="filterTipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                        <select
                            id="filterTipo"
                            wire:model.live="filterTipo"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                        >
                            <option value="all">Todos</option>
                            <option value="termica">Termica (ESC/POS)</option>
                            <option value="laser_inkjet">Laser/Inkjet</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de impresoras -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($impresoras as $impresora)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200 {{ !$impresora->activa ? 'opacity-60' : '' }}">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-2 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    <h3 class="text-lg font-semibold text-bcn-secondary">{{ $impresora->nombre }}</h3>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate" title="{{ $impresora->nombre_sistema }}">
                                    {{ $impresora->nombre_sistema }}
                                </p>
                                <div class="mt-3 space-y-2">
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $impresora->tipo === 'termica' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }}">
                                            {{ $impresora->tipo_legible }}
                                        </span>
                                        <span class="ml-2 text-xs">{{ $impresora->formato_papel_legible }}</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $impresora->activa ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ $impresora->activa ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button
                                wire:click="edit({{ $impresora->id }})"
                                class="inline-flex justify-center items-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar
                            </button>
                            <button
                                wire:click="abrirAsignaciones({{ $impresora->id }})"
                                class="inline-flex justify-center items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Asignar
                            </button>
                            <button
                                wire:click="probarImpresion({{ $impresora->id }})"
                                class="inline-flex justify-center items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Probar
                            </button>
                            <button
                                wire:click="delete({{ $impresora->id }})"
                                wire:confirm="Esta seguro de eliminar esta impresora?"
                                class="inline-flex justify-center items-center px-3 py-2 border border-red-300 dark:border-red-600 text-sm font-medium rounded-md text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-500 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <p class="mt-2">No hay impresoras configuradas</p>
                        <p class="text-sm mt-1">Haz clic en "Nueva Impresora" para agregar una</p>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Paginacion -->
        @if($impresoras->hasPages())
            <div class="mt-6">
                {{ $impresoras->links() }}
            </div>
        @endif

        <!-- Seccion de Configuracion por Sucursal -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-bcn-secondary mb-4">Configuracion por Sucursal</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($sucursales as $sucursal)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ $sucursal->nombre }}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sucursal->cajas->count() }} caja(s)</p>
                            </div>
                            <button
                                wire:click="abrirConfigSucursal({{ $sucursal->id }})"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Configurar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Impresora -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancel" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Editar Impresora' : 'Nueva Impresora' }}
                            </h3>

                            <div class="space-y-4">
                                <!-- Impresoras detectadas -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Impresoras del Sistema</label>
                                    @if(count($impresorasDetectadas) > 0)
                                        <div class="max-h-40 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md">
                                            @foreach($impresorasDetectadas as $imp)
                                                <button
                                                    type="button"
                                                    wire:click="seleccionarImpresoraDetectada('{{ $imp }}')"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {{ $nombreSistema === $imp ? 'bg-bcn-primary text-white' : 'text-gray-700 dark:text-gray-300' }}"
                                                >
                                                    {{ $imp }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-500 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                            <p>Detectando impresoras...</p>
                                            <p class="text-xs mt-1">Asegurate de tener QZ Tray instalado y ejecutandose.</p>
                                            <button type="button" wire:click="$dispatch('detectar-impresoras')" class="mt-2 text-bcn-primary hover:underline text-xs">
                                                Reintentar deteccion
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                                    <input
                                        type="text"
                                        id="nombre"
                                        wire:model="nombre"
                                        placeholder="Ej: Impresora Caja 1"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                    />
                                    @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <!-- Nombre del sistema (readonly) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Impresora Seleccionada *</label>
                                    <input
                                        type="text"
                                        wire:model="nombreSistema"
                                        readonly
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm bg-gray-50 dark:bg-gray-600"
                                    />
                                    @error('nombreSistema') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <!-- Tipo y Formato -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                                        <select
                                            id="tipo"
                                            wire:model.live="tipo"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                        >
                                            @foreach(\App\Models\Impresora::TIPOS as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="formatoPapel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Formato Papel</label>
                                        <select
                                            id="formatoPapel"
                                            wire:model.live="formatoPapel"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                        >
                                            @foreach(\App\Models\Impresora::FORMATOS_PAPEL as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Activa -->
                                <div class="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="activa"
                                        wire:model="activa"
                                        class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary"
                                    />
                                    <label for="activa" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Impresora activa</label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end space-x-3">
                            <button
                                type="button"
                                wire:click="cancel"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-bcn-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-opacity-90"
                            >
                                {{ $editMode ? 'Actualizar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Asignaciones -->
    @if($showModalAsignacion)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-asignacion" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancel" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full max-h-[90vh] overflow-y-auto">
                    <div class="bg-white dark:bg-gray-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Asignar Impresora a Sucursales/Cajas
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Selecciona los tipos de documento que esta impresora puede imprimir en cada sucursal/caja.
                        </p>

                        <div class="space-y-6">
                            @foreach($sucursales as $sucursal)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">{{ $sucursal->nombre }}</h4>

                                    <!-- Para toda la sucursal -->
                                    <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Toda la sucursal</span>
                                            <label class="flex items-center text-xs">
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleDefecto({{ $sucursal->id }}, 'all')"
                                                    {{ $this->esDefecto($sucursal->id, 'all') ? 'checked' : '' }}
                                                    class="rounded border-gray-300 text-bcn-primary mr-1"
                                                />
                                                Por defecto
                                            </label>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($tiposDocumento as $tipo => $label)
                                                <label class="inline-flex items-center text-xs">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleAsignacion({{ $sucursal->id }}, 'all', '{{ $tipo }}')"
                                                        {{ $this->tieneAsignacion($sucursal->id, 'all', $tipo) ? 'checked' : '' }}
                                                        class="rounded border-gray-300 text-bcn-primary mr-1"
                                                    />
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Por caja -->
                                    @if($sucursal->cajas->count() > 0)
                                        <div class="space-y-2">
                                            @foreach($sucursal->cajas as $caja)
                                                <div class="p-3 border border-gray-200 dark:border-gray-600 rounded">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $caja->nombre }}</span>
                                                        <label class="flex items-center text-xs">
                                                            <input
                                                                type="checkbox"
                                                                wire:click="toggleDefecto({{ $sucursal->id }}, {{ $caja->id }})"
                                                                {{ $this->esDefecto($sucursal->id, $caja->id) ? 'checked' : '' }}
                                                                class="rounded border-gray-300 text-bcn-primary mr-1"
                                                            />
                                                            Por defecto
                                                        </label>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach($tiposDocumento as $tipo => $label)
                                                            <label class="inline-flex items-center text-xs">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:click="toggleAsignacion({{ $sucursal->id }}, {{ $caja->id }}, '{{ $tipo }}')"
                                                                    {{ $this->tieneAsignacion($sucursal->id, $caja->id, $tipo) ? 'checked' : '' }}
                                                                    class="rounded border-gray-300 text-bcn-primary mr-1"
                                                                />
                                                                {{ $label }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            wire:click="cancel"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            wire:click="guardarAsignaciones"
                            class="px-4 py-2 bg-bcn-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-opacity-90"
                        >
                            Guardar Asignaciones
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Configuracion Sucursal -->
    @if($showModalConfig)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-config" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="cancel" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Configuracion de Impresion
                        </h3>

                        <div class="space-y-4">
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="configImpresionAutomaticaVenta"
                                        class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Imprimir ticket automaticamente al completar venta</span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="configImpresionAutomaticaFactura"
                                        class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Imprimir factura automaticamente al emitir</span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="configAbrirCajonEfectivo"
                                        class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Abrir cajon de dinero con pagos en efectivo</span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="configCortarPapelAutomatico"
                                        class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Cortar papel automaticamente (impresoras termicas)</span>
                                </label>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texto pie de ticket</label>
                                <textarea
                                    wire:model="configTextoPieTicket"
                                    rows="2"
                                    placeholder="Ej: Gracias por su compra!"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Texto legal factura</label>
                                <textarea
                                    wire:model="configTextoLegalFactura"
                                    rows="2"
                                    placeholder="Ej: Documento no valido como factura"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end space-x-3">
                        <button
                            type="button"
                            wire:click="cancel"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            wire:click="guardarConfigSucursal"
                            class="px-4 py-2 bg-bcn-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-opacity-90"
                        >
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
