<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <button
                            wire:click="volver"
                            class="inline-flex items-center justify-center w-10 h-10 text-gray-500 dark:text-gray-400 hover:text-bcn-primary hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                            title="Volver a artículos"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">Cambio Masivo de Precios</h2>
                            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">Actualiza los precios de múltiples artículos de forma rápida</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indicador de pasos -->
        <div class="mb-6">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <!-- Paso 1 -->
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $paso >= 1 ? 'bg-bcn-primary text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }} font-semibold">
                            1
                        </div>
                        <span class="ml-2 text-sm font-medium {{ $paso >= 1 ? 'text-bcn-primary' : 'text-gray-500 dark:text-gray-400' }}">Configurar</span>
                    </div>

                    <!-- Línea -->
                    <div class="w-16 sm:w-24 h-1 mx-4 {{ $paso >= 2 ? 'bg-bcn-primary' : 'bg-gray-200 dark:bg-gray-700' }}"></div>

                    <!-- Paso 2 -->
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $paso >= 2 ? 'bg-bcn-primary text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }} font-semibold">
                            2
                        </div>
                        <span class="ml-2 text-sm font-medium {{ $paso >= 2 ? 'text-bcn-primary' : 'text-gray-500 dark:text-gray-400' }}">Revisar y Aplicar</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paso 1: Configuración -->
        @if($paso === 1)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <!-- Títulos alineados -->
                    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 mb-4">
                        <div class="lg:col-span-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Configuración del ajuste</h3>
                        </div>
                        <div class="lg:col-span-7">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filtros (opcional)</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Si no seleccionas ningún filtro, se aplicará a todos los artículos activos.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido -->
                    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                        <!-- Columna izquierda: Ajuste (30%) -->
                        <div class="lg:col-span-3 space-y-4">
                            <!-- Tipo de ajuste -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de ajuste</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" wire:model.live="tipoAjuste" value="descuento" class="text-bcn-primary focus:ring-bcn-primary">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Descuento</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" wire:model.live="tipoAjuste" value="recargo" class="text-bcn-primary focus:ring-bcn-primary">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Recargo</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Tipo de valor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo de valor</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" wire:model.live="tipoValor" value="porcentual" class="text-bcn-primary focus:ring-bcn-primary">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Porcentual (%)</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" wire:model.live="tipoValor" value="fijo" class="text-bcn-primary focus:ring-bcn-primary">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Fijo ($)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Valor del ajuste -->
                            <div>
                                <label for="valorAjuste" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Valor del {{ $tipoAjuste }} *
                                </label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 dark:text-gray-400 sm:text-sm">{{ $tipoValor === 'porcentual' ? '%' : '$' }}</span>
                                    </div>
                                    <input
                                        type="number"
                                        id="valorAjuste"
                                        wire:model.live="valorAjuste"
                                        step="{{ $tipoValor === 'porcentual' ? '0.1' : '0.01' }}"
                                        min="0"
                                        class="block w-full pl-8 pr-3 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                        placeholder="0"
                                    />
                                </div>
                            </div>

                            <!-- Tipo de redondeo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Redondeo</label>
                                <select
                                    wire:model.live="tipoRedondeo"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                >
                                    <option value="sin_redondeo">Sin redondeo (2 decimales)</option>
                                    <option value="entero">Entero (ej: 99.50 -> 100)</option>
                                    <option value="decena">Decena (ej: 93 -> 90)</option>
                                    <option value="centena">Centena (ej: 850 -> 900)</option>
                                </select>
                            </div>

                            <!-- Ejemplo -->
                            @if($valorAjuste > 0)
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">Ejemplo:</p>
                                    @php
                                        $ejemploPrecio = 1000;
                                        $ejemploNuevo = $tipoValor === 'porcentual'
                                            ? $ejemploPrecio * (1 + (($tipoAjuste === 'descuento' ? -$valorAjuste : $valorAjuste) / 100))
                                            : ($tipoAjuste === 'descuento' ? $ejemploPrecio - $valorAjuste : $ejemploPrecio + $valorAjuste);
                                        $ejemploNuevo = match($tipoRedondeo) {
                                            'entero' => round($ejemploNuevo),
                                            'decena' => round($ejemploNuevo / 10) * 10,
                                            'centena' => round($ejemploNuevo / 100) * 100,
                                            default => round($ejemploNuevo, 2),
                                        };
                                    @endphp
                                    <p class="text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Precio: $1,000</span>
                                        <span class="mx-2">→</span>
                                        <span class="font-semibold {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}">
                                            ${{ number_format($ejemploNuevo, 2, ',', '.') }}
                                        </span>
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Columna derecha: Filtros (70%) -->
                        <div class="lg:col-span-7">
                            <!-- Grid de Categorías y Etiquetas lado a lado -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Categorías -->
                                <div class="flex flex-col">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Categorías
                                        @if(count($categoriasSeleccionadas) > 0)
                                            <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                                {{ count($categoriasSeleccionadas) }} seleccionadas
                                            </span>
                                        @endif
                                    </label>
                                    <!-- Buscador de categorías -->
                                    <div class="relative mb-2">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="busquedaCategoria"
                                            placeholder="Buscar categoría..."
                                            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                        >
                                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 max-h-[19rem] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 space-y-1">
                                        @forelse($categorias as $categoria)
                                            <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="categoriasSeleccionadas"
                                                    value="{{ $categoria->id }}"
                                                    class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                                />
                                                <span class="ml-2 flex items-center gap-2">
                                                    <span class="w-3 h-3 rounded-full" style="background-color: {{ $categoria->color }}"></span>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $categoria->nombre }}</span>
                                                </span>
                                            </label>
                                        @empty
                                            <p class="text-sm text-gray-500 dark:text-gray-400 p-2">No hay categorías disponibles</p>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Etiquetas agrupadas -->
                                <div class="flex flex-col">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Etiquetas
                                        @if(count($etiquetasSeleccionadas) > 0)
                                            <span class="ml-2 px-2 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                                {{ count($etiquetasSeleccionadas) }} seleccionadas
                                            </span>
                                        @endif
                                    </label>
                                    <!-- Buscador de etiquetas -->
                                    <div class="relative mb-2">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="busquedaEtiqueta"
                                            placeholder="Buscar grupo o etiqueta..."
                                            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                        >
                                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 max-h-[19rem] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                        @forelse($gruposEtiquetas as $grupo)
                                            @if($grupo->etiquetas->count() > 0)
                                                <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2 sticky top-0">
                                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $grupo->color }}"></span>
                                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                                    </div>
                                                    <div class="p-2 space-y-1">
                                                        @foreach($grupo->etiquetas as $etiqueta)
                                                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model.live="etiquetasSeleccionadas"
                                                                    value="{{ $etiqueta->id }}"
                                                                    class="w-4 h-4 rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                                                />
                                                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $etiqueta->color ?? $grupo->color }}"></span>
                                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @empty
                                            <p class="text-sm text-gray-500 dark:text-gray-400 p-3">No hay etiquetas disponibles</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón siguiente -->
                    <div class="mt-6 flex justify-end">
                        <button
                            wire:click="siguientePaso"
                            class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            Procesar
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Paso 2: Vista previa y confirmación -->
        @if($paso === 2)
            <!-- Resumen de configuración -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <!-- Indicadores de configuración -->
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Ajuste -->
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $tipoAjuste === 'descuento' ? 'bg-green-50 border border-green-200' : 'bg-orange-50 border border-orange-200' }}">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $tipoAjuste === 'descuento' ? 'bg-green-100' : 'bg-orange-100' }}">
                                    @if($tipoAjuste === 'descuento')
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }} font-medium">{{ ucfirst($tipoAjuste) }}</p>
                                    <p class="text-sm font-bold {{ $tipoAjuste === 'descuento' ? 'text-green-700' : 'text-orange-700' }}">
                                        {{ $tipoValor === 'porcentual' ? $valorAjuste . '%' : '$' . number_format($valorAjuste, 2, ',', '.') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Redondeo -->
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-600">
                                    <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Redondeo</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ match($tipoRedondeo) {
                                            'entero' => 'Entero',
                                            'decena' => 'Decena',
                                            'centena' => 'Centena',
                                            default => 'Sin redondeo',
                                        } }}
                                    </p>
                                </div>
                            </div>

                            <!-- Filtros aplicados -->
                            @if(!empty($categoriasSeleccionadas) || !empty($etiquetasSeleccionadas))
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 border border-indigo-200">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-indigo-600 font-medium">Filtros</p>
                                        <p class="text-sm font-bold text-indigo-700">
                                            @if(!empty($categoriasSeleccionadas) && !empty($etiquetasSeleccionadas))
                                                {{ count($categoriasSeleccionadas) }} cat. · {{ count($etiquetasSeleccionadas) }} etiq.
                                            @elseif(!empty($categoriasSeleccionadas))
                                                {{ count($categoriasSeleccionadas) }} categoría(s)
                                            @else
                                                {{ count($etiquetasSeleccionadas) }} etiqueta(s)
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs text-blue-600 font-medium">Alcance</p>
                                        <p class="text-sm font-bold text-blue-700">Todos los artículos</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Botón modificar filtros -->
                        <button
                            wire:click="pasoAnterior"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Modificar filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Totales -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Artículos a modificar</div>
                    <div class="text-2xl font-bold text-bcn-secondary dark:text-white">{{ $totalArticulos }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total precio actual</div>
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">${{ number_format($totalPrecioViejo, 2, ',', '.') }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total precio nuevo</div>
                    <div class="text-2xl font-bold {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}">${{ number_format($totalPrecioNuevo, 2, ',', '.') }}</div>
                </div>
            </div>

            <!-- Tabla de artículos -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <!-- Buscador de artículos en el preview -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <div class="relative flex-1 max-w-md">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaArticuloPreview"
                                placeholder="Buscar por código o nombre..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors text-sm"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($busquedaArticuloPreview)
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    Mostrando {{ count($articulosPreviewFiltrados) }} de {{ $totalArticulos }} artículos
                                </span>
                            @endif
                            <button
                                wire:click="abrirModalAgregarArticulo"
                                class="inline-flex items-center px-3 py-2 bg-bcn-primary text-white text-sm font-medium rounded-lg hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Agregar artículo
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-bcn-light dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Código
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Artículo
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Categoría
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Precio Actual
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Precio Nuevo
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Diferencia
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($articulosPreviewFiltrados as $articulo)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo['codigo'] }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm text-gray-900 dark:text-white">{{ $articulo['nombre'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              style="background-color: {{ $articulo['categoria_color'] }}20; color: {{ $articulo['categoria_color'] }}; border: 1px solid {{ $articulo['categoria_color'] }}40;">
                                            {{ $articulo['categoria'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">${{ number_format($articulo['precio_viejo'], 2, ',', '.') }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value="{{ $articulo['precio_nuevo'] }}"
                                            wire:change="actualizarPrecioManual({{ $articulo['id'] }}, $event.target.value)"
                                            class="w-28 text-right text-sm font-medium rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}"
                                        />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm {{ $articulo['diferencia'] >= 0 ? 'text-orange-600' : 'text-green-600' }}">
                                            {{ $articulo['diferencia'] >= 0 ? '+' : '' }}${{ number_format($articulo['diferencia'], 2, ',', '.') }}
                                            <span class="text-xs text-gray-400">({{ $articulo['diferencia_porcentaje'] >= 0 ? '+' : '' }}{{ $articulo['diferencia_porcentaje'] }}%)</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <button
                                            wire:click="quitarArticulo({{ $articulo['id'] }})"
                                            class="text-red-500 hover:text-red-700 transition-colors"
                                            title="Quitar de la lista"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <p class="mt-2">No se encontraron artículos con los filtros seleccionados</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Botón de aplicar -->
                @if(count($articulosPreview) > 0)
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                        <button
                            wire:click="confirmarCambios"
                            class="inline-flex items-center px-6 py-3 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Aplicar Cambios ({{ $totalArticulos }} artículos)
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Modal para agregar artículo -->
    @if($showModalAgregarArticulo)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-agregar-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalAgregarArticulo"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-agregar-title">
                                Agregar artículo a la lista
                            </h3>
                            <button
                                wire:click="cerrarModalAgregarArticulo"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Buscador de artículos -->
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaArticuloAgregar"
                                placeholder="Buscar por código o nombre..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors text-sm"
                                autofocus
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>

                        <!-- Lista de resultados -->
                        <div class="mt-3 max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            @if(strlen($busquedaArticuloAgregar) >= 2)
                                @forelse($articulosParaAgregar as $articulo)
                                    <div
                                        wire:click="agregarArticuloManual({{ $articulo->id }})"
                                        class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0 transition-colors"
                                    >
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->codigo }}</span>
                                                @if($articulo->categoriaModel)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                                          style="background-color: {{ $articulo->categoriaModel->color }}20; color: {{ $articulo->categoriaModel->color }};">
                                                        {{ $articulo->categoriaModel->nombre }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ $articulo->nombre }}</p>
                                        </div>
                                        @php
                                            $precioBase = (float) $articulo->precio_base;
                                            if ($tipoValor === 'porcentual') {
                                                $factor = $tipoAjuste === 'descuento' ? -$valorAjuste : $valorAjuste;
                                                $precioNuevoCalc = $precioBase * (1 + ($factor / 100));
                                            } else {
                                                $precioNuevoCalc = $tipoAjuste === 'descuento'
                                                    ? $precioBase - $valorAjuste
                                                    : $precioBase + $valorAjuste;
                                            }
                                            $precioNuevoCalc = max(0, $precioNuevoCalc);
                                            $precioNuevoCalc = match($tipoRedondeo) {
                                                'entero' => round($precioNuevoCalc),
                                                'decena' => round($precioNuevoCalc / 10) * 10,
                                                'centena' => round($precioNuevoCalc / 100) * 100,
                                                default => round($precioNuevoCalc, 2),
                                            };
                                        @endphp
                                        <div class="text-right ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">${{ number_format($precioBase, 2, ',', '.') }}</p>
                                            <p class="text-xs {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}">
                                                → ${{ number_format($precioNuevoCalc, 2, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                        No se encontraron artículos
                                    </div>
                                @endforelse
                            @else
                                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                    Escribe al menos 2 caracteres para buscar
                                </div>
                            @endif
                        </div>

                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            Haz clic en un artículo para agregarlo a la lista con el ajuste configurado.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de confirmación -->
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarConfirmacion"></div>

            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    Confirmar cambio de precios
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Estás a punto de modificar el precio de <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $totalArticulos }} artículos</span>.
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Esta acción también actualizará los precios fijos en las listas de precios donde estos artículos participen.
                                    </p>
                                    <p class="text-sm text-red-600 mt-2 font-medium">
                                        Esta acción no se puede deshacer.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        <button
                            type="button"
                            wire:click="aplicarCambios"
                            class="inline-flex w-full justify-center rounded-md bg-bcn-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 sm:w-auto transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Confirmar
                        </button>
                        <button
                            type="button"
                            wire:click="cancelarConfirmacion"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
