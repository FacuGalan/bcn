<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-4 mb-2">
                <button wire:click="volver" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-bcn-primary">Asignar Etiquetas</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gestiona las etiquetas de los artículos de forma masiva</p>
                </div>
            </div>
        </div>

        {{-- Selector de modo --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Modo de Operación</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Modo: Etiqueta a Artículos --}}
                <button
                    wire:click="cambiarModo('etiqueta_a_articulos')"
                    class="p-4 rounded-lg border-2 transition-all text-left {{ $modo === 'etiqueta_a_articulos' ? 'border-bcn-primary bg-bcn-primary/5' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $modo === 'etiqueta_a_articulos' ? 'bg-bcn-primary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }} flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold {{ $modo === 'etiqueta_a_articulos' ? 'text-bcn-primary' : 'text-gray-700 dark:text-gray-300' }}">Una Etiqueta → Muchos Artículos</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selecciona una etiqueta y asígnala a múltiples artículos</p>
                        </div>
                    </div>
                </button>

                {{-- Modo: Artículo a Etiquetas --}}
                <button
                    wire:click="cambiarModo('articulo_a_etiquetas')"
                    class="p-4 rounded-lg border-2 transition-all text-left {{ $modo === 'articulo_a_etiquetas' ? 'border-bcn-primary bg-bcn-primary/5' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg {{ $modo === 'articulo_a_etiquetas' ? 'bg-bcn-primary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }} flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold {{ $modo === 'articulo_a_etiquetas' ? 'text-bcn-primary' : 'text-gray-700 dark:text-gray-300' }}">Un Artículo → Muchas Etiquetas</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selecciona un artículo y asígnale múltiples etiquetas</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        {{-- Contenido según el modo --}}
        @if($modo === 'etiqueta_a_articulos')
            {{-- ==================== MODO: ETIQUETA A ARTÍCULOS ==================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Panel izquierdo: Selección de etiqueta --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-bcn-primary text-white text-sm flex items-center justify-center">1</span>
                            Seleccionar Etiqueta
                        </h2>
                    </div>

                    @if($etiquetaSeleccionada)
                        {{-- Etiqueta seleccionada --}}
                        <div class="p-4">
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center gap-3">
                                    <div class="w-4 h-4 rounded-full" style="background-color: {{ $colorEtiquetaSeleccionada }};"></div>
                                    <div>
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $nombreEtiquetaSeleccionada }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">({{ $grupoEtiquetaSeleccionada }})</span>
                                    </div>
                                </div>
                                <button wire:click="limpiarEtiqueta" class="text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        {{-- Búsqueda de etiquetas --}}
                        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaEtiqueta"
                                    placeholder="Buscar etiqueta..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                >
                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Lista de etiquetas agrupadas --}}
                        <div class="max-h-96 overflow-y-auto">
                            @forelse($gruposEtiquetas as $grupo)
                                @if($grupo->etiquetas->count() > 0)
                                    <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2">
                                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $grupo->color }};"></div>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">({{ $grupo->etiquetas->count() }})</span>
                                        </div>
                                        <div class="p-2">
                                            @foreach($grupo->etiquetas as $etiqueta)
                                                <button
                                                    wire:click="seleccionarEtiqueta({{ $etiqueta->id }})"
                                                    class="w-full text-left px-3 py-2 rounded-lg hover:bg-bcn-primary/5 dark:hover:bg-gray-700 transition-colors flex items-center gap-2"
                                                >
                                                    <div class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $etiqueta->color ?? $grupo->color }};"></div>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <p>No se encontraron etiquetas</p>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                {{-- Panel derecho: Selección de artículos --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-bcn-primary text-white text-sm flex items-center justify-center">2</span>
                                Seleccionar Artículos
                                @if(count($articulosSeleccionados) > 0)
                                    <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-sm rounded-full">
                                        {{ count($articulosSeleccionados) }} seleccionados
                                    </span>
                                @endif
                            </h2>
                            @if($etiquetaSeleccionada)
                                <div class="flex items-center gap-2">
                                    <button wire:click="seleccionarTodosArticulos" class="text-xs text-bcn-primary hover:underline">Seleccionar todos</button>
                                    <span class="text-gray-300 dark:text-gray-600">|</span>
                                    <button wire:click="deseleccionarTodosArticulos" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Limpiar</button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Búsqueda de artículos --}}
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaArticulo"
                                placeholder="Buscar por código o nombre..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                @if(!$etiquetaSeleccionada) disabled @endif
                            >
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Lista de artículos --}}
                    <div class="max-h-80 overflow-y-auto">
                        @if(!$etiquetaSeleccionada)
                            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <p>Selecciona una etiqueta primero</p>
                            </div>
                        @else
                            @forelse($articulos as $articulo)
                                <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleArticulo({{ $articulo->id }})"
                                        {{ in_array($articulo->id, $articulosSeleccionados) ? 'checked' : '' }}
                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                    >
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $articulo->nombre }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</p>
                                    </div>
                                </label>
                            @empty
                                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <p>No se encontraron artículos</p>
                                </div>
                            @endforelse
                        @endif
                    </div>

                    {{-- Paginación --}}
                    @if($etiquetaSeleccionada && $articulos->hasPages())
                        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                            {{ $articulos->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Botón Guardar --}}
            @if($etiquetaSeleccionada)
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex justify-end">
                    <button
                        wire:click="guardarEtiquetaArticulos"
                        class="px-6 py-2.5 bg-bcn-primary text-white rounded-lg hover:bg-bcn-primary/90 transition-colors flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar Asignación
                    </button>
                </div>
            @endif

        @else
            {{-- ==================== MODO: ARTÍCULO A ETIQUETAS ==================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Panel izquierdo: Selección de artículo --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-bcn-primary text-white text-sm flex items-center justify-center">1</span>
                            Seleccionar Artículo
                        </h2>
                    </div>

                    @if($articuloSeleccionado)
                        {{-- Artículo seleccionado --}}
                        <div class="p-4">
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                                <div>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $nombreArticuloSeleccionado }}</span>
                                </div>
                                <button wire:click="limpiarArticulo" class="text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        {{-- Búsqueda de artículos --}}
                        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaArticulo"
                                    placeholder="Buscar por código o nombre..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                >
                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Lista de artículos --}}
                        <div class="max-h-96 overflow-y-auto">
                            @forelse($articulos as $articulo)
                                <button
                                    wire:click="seleccionarArticulo({{ $articulo->id }})"
                                    class="w-full text-left px-4 py-3 hover:bg-bcn-primary/5 dark:hover:bg-gray-700 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                                >
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $articulo->nombre }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</p>
                                </button>
                            @empty
                                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <p>No se encontraron artículos</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- Paginación --}}
                        @if($articulos->hasPages())
                            <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                                {{ $articulos->links() }}
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Panel derecho: Selección de etiquetas --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-bcn-primary text-white text-sm flex items-center justify-center">2</span>
                            Seleccionar Etiquetas
                            @if(count($etiquetasSeleccionadas) > 0)
                                <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-sm rounded-full">
                                    {{ count($etiquetasSeleccionadas) }} seleccionadas
                                </span>
                            @endif
                        </h2>
                    </div>

                    {{-- Búsqueda de etiquetas --}}
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaEtiqueta"
                                placeholder="Buscar etiqueta..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                @if(!$articuloSeleccionado) disabled @endif
                            >
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Lista de etiquetas agrupadas --}}
                    <div class="max-h-80 overflow-y-auto">
                        @if(!$articuloSeleccionado)
                            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <p>Selecciona un artículo primero</p>
                            </div>
                        @else
                            @forelse($gruposEtiquetas as $grupo)
                                @if($grupo->etiquetas->count() > 0)
                                    <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2">
                                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $grupo->color }};"></div>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">({{ $grupo->etiquetas->count() }})</span>
                                        </div>
                                        <div class="p-2">
                                            @foreach($grupo->etiquetas as $etiqueta)
                                                <label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleEtiqueta({{ $etiqueta->id }})"
                                                        {{ in_array($etiqueta->id, $etiquetasSeleccionadas) ? 'checked' : '' }}
                                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                                    >
                                                    <div class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $etiqueta->color ?? $grupo->color }};"></div>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <p>No se encontraron etiquetas</p>
                                </div>
                            @endforelse
                        @endif
                    </div>
                </div>
            </div>

            {{-- Botón Guardar --}}
            @if($articuloSeleccionado)
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex justify-end">
                    <button
                        wire:click="guardarArticuloEtiquetas"
                        class="px-6 py-2.5 bg-bcn-primary text-white rounded-lg hover:bg-bcn-primary/90 transition-colors flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar Asignación
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
