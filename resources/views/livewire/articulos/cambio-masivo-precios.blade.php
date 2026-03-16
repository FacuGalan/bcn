<div class="py-4 h-[calc(100dvh-85px)] flex flex-col" x-data>
    <div class="px-4 sm:px-6 lg:px-8 flex flex-col flex-1 min-h-0">
        <!-- Header -->
        <div class="mb-4 sm:mb-6 flex-shrink-0">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <button
                            wire:click="volver"
                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 dark:text-gray-400 hover:text-bcn-primary hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                            :title="__('Volver a artículos')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Cambio Masivo de Precios') }}</h2>
                            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Configura y aplica ajustes de precios a múltiples artículos') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Derecha: Botón programados -->
                <button
                    wire:click="toggleProgramados"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-bcn-primary text-bcn-primary hover:bg-bcn-primary hover:text-white rounded-lg text-xs font-medium transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Programados') }}
                    @if($this->pendientesCount > 0)
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-amber-500 rounded-full">
                            {{ $this->pendientesCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>

        <!-- Panel de cambios programados -->
        @if($showProgramados)
            <div class="mb-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-3 sm:p-4">
                    <!-- Header + Filtros -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Cambios programados') }}</h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Filtro estado -->
                            <select
                                wire:model.live="filtroProgramadosEstado"
                                class="py-1 px-2 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                            >
                                <option value="pendiente">{{ __('Pendientes') }}</option>
                                <option value="procesado">{{ __('Procesados') }}</option>
                                <option value="cancelado">{{ __('Cancelados') }}</option>
                                <option value="error">{{ __('Con error') }}</option>
                                <option value="todos">{{ __('Todos') }}</option>
                            </select>
                            <!-- Filtro fecha desde -->
                            <input
                                type="date"
                                wire:model.live="filtroProgramadosFechaDesde"
                                class="py-1 px-2 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                :title="__('Desde')"
                            >
                            <!-- Filtro fecha hasta -->
                            <input
                                type="date"
                                wire:model.live="filtroProgramadosFechaHasta"
                                class="py-1 px-2 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                :title="__('Hasta')"
                            >
                            <button wire:click="toggleProgramados" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if($this->cambiosProgramados->isEmpty())
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-3">{{ __('No hay cambios programados') }}</p>
                    @else
                        <!-- Desktop table -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Fecha programada') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ajuste') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Artículos') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Alcance') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Estado') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Acciones') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->cambiosProgramados as $programado)
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-900 dark:text-white">
                                                {{ $programado->fecha_programada->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                                {{ $programado->descripcion_ajuste }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                                {{ $programado->total_articulos }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700 dark:text-gray-300">
                                                @if($programado->alcance_precio === 'sucursal_actual')
                                                    {{ __('Sucursal') }} #{{ $programado->sucursal_id }}
                                                @else
                                                    {{ __('Global') }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                @switch($programado->estado)
                                                    @case('pendiente')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">{{ __('Pendiente') }}</span>
                                                        @break
                                                    @case('procesado')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Procesado') }}</span>
                                                        @break
                                                    @case('cancelado')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">{{ __('Cancelado') }}</span>
                                                        @break
                                                    @case('error')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200" title="{{ $programado->resultado }}">{{ __('Error') }}</span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right text-xs">
                                                @if($programado->estado === 'pendiente')
                                                    <button
                                                        wire:click="cancelarCambioProgramado({{ $programado->id }})"
                                                        wire:confirm="{{ __('¿Cancelar este cambio programado?') }}"
                                                        class="px-2 py-1 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-md text-xs font-medium transition-colors"
                                                    >
                                                        {{ __('Cancelar') }}
                                                    </button>
                                                @elseif($programado->estado === 'procesado')
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $programado->procesado_at?->format('d/m/Y H:i') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile cards -->
                        <div class="sm:hidden space-y-2">
                            @foreach($this->cambiosProgramados as $programado)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-medium text-gray-900 dark:text-white">{{ $programado->fecha_programada->format('d/m/Y H:i') }}</span>
                                        @switch($programado->estado)
                                            @case('pendiente')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">{{ __('Pendiente') }}</span>
                                                @break
                                            @case('procesado')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Procesado') }}</span>
                                                @break
                                            @case('cancelado')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">{{ __('Cancelado') }}</span>
                                                @break
                                            @case('error')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Error') }}</span>
                                                @break
                                        @endswitch
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                                        <p>{{ $programado->descripcion_ajuste }} — {{ $programado->total_articulos }} {{ __('artículos') }}</p>
                                        <p>
                                            @if($programado->alcance_precio === 'sucursal_actual')
                                                {{ __('Sucursal') }} #{{ $programado->sucursal_id }}
                                            @else
                                                {{ __('Global') }}
                                            @endif
                                        </p>
                                    </div>
                                    @if($programado->estado === 'pendiente')
                                        <div class="mt-2">
                                            <button
                                                wire:click="cancelarCambioProgramado({{ $programado->id }})"
                                                wire:confirm="{{ __('¿Cancelar este cambio programado?') }}"
                                                class="w-full px-2 py-1 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-md text-xs font-medium transition-colors"
                                            >
                                                {{ __('Cancelar') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Paso 1: Configuración -->
        @if($paso === 1)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-3 sm:p-4">
                    <!-- Títulos alineados -->
                    <div class="grid grid-cols-1 lg:grid-cols-10 gap-4 mb-3">
                        <div class="lg:col-span-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Configuración del ajuste') }}</h3>
                        </div>
                        <div class="lg:col-span-7">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Filtros (opcional)') }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Si no seleccionas ningún filtro, se aplicará a todos los artículos activos.') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido -->
                    <div class="grid grid-cols-1 lg:grid-cols-10 gap-4">
                        <!-- Columna izquierda: Ajuste (30%) -->
                        <div class="lg:col-span-3 space-y-3">
                            <!-- Tipo de ajuste + Tipo de valor en una fila -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de ajuste') }}</label>
                                    <div class="flex gap-3">
                                        <label class="flex items-center">
                                            <input type="radio" wire:model.live="tipoAjuste" value="descuento" class="text-bcn-primary focus:ring-bcn-primary">
                                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-300">{{ __('Descuento') }}</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" wire:model.live="tipoAjuste" value="recargo" class="text-bcn-primary focus:ring-bcn-primary">
                                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-300">{{ __('Recargo') }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de valor') }}</label>
                                    <div class="flex gap-3">
                                        <label class="flex items-center">
                                            <input type="radio" wire:model.live="tipoValor" value="porcentual" class="text-bcn-primary focus:ring-bcn-primary">
                                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-300">%</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" wire:model.live="tipoValor" value="fijo" class="text-bcn-primary focus:ring-bcn-primary">
                                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-300">$</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Valor + Redondeo en una fila -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="valorAjuste" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('Valor del') }} {{ $tipoAjuste }} *
                                    </label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-xs">{{ $tipoValor === 'porcentual' ? '%' : '$' }}</span>
                                        </div>
                                        <input
                                            type="number"
                                            id="valorAjuste"
                                            wire:model.live.debounce.500ms="valorAjuste"
                                            step="{{ $tipoValor === 'porcentual' ? '0.1' : '0.01' }}"
                                            min="0"
                                            class="block w-full pl-7 pr-2 py-1.5 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                            placeholder="0"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Redondeo') }}</label>
                                    <select
                                        wire:model.live="tipoRedondeo"
                                        class="w-full py-1.5 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    >
                                        <option value="sin_redondeo">{{ __('Sin redondeo') }}</option>
                                        <option value="entero">{{ __('Entero') }}</option>
                                        <option value="decena">{{ __('Decena') }}</option>
                                        <option value="centena">{{ __('Centena') }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Ejemplo -->
                            @if($valorAjuste > 0)
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
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
                                    <p class="text-xs">
                                        <span class="text-gray-500 dark:text-gray-400">{{ __('Ejemplo:') }} $1.000</span>
                                        <span class="mx-1">→</span>
                                        <span class="font-semibold {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}">
                                            ${{ number_format($ejemploNuevo, 2, ',', '.') }}
                                        </span>
                                    </p>
                                </div>
                            @endif

                            <!-- Alcance del cambio -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Alcance del cambio') }}</label>
                                <div class="space-y-1.5">
                                    <label class="flex items-start gap-2 p-2 rounded-lg border cursor-pointer transition-colors {{ $alcancePrecio === 'global' ? 'border-bcn-primary bg-bcn-primary/5' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        <input type="radio" wire:model.live="alcancePrecio" value="global" class="text-bcn-primary focus:ring-bcn-primary mt-0.5">
                                        <div>
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Todas las sucursales (precio genérico)') }}</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Se eliminarán los precios propios de las sucursales') }}</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-2 p-2 rounded-lg border cursor-pointer transition-colors {{ $alcancePrecio === 'sucursal_actual' ? 'border-bcn-primary bg-bcn-primary/5' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        <input type="radio" wire:model.live="alcancePrecio" value="sucursal_actual" class="text-bcn-primary focus:ring-bcn-primary mt-0.5">
                                        <div>
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Sucursal actual') }}</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Los precios propios no se verán afectados en otras sucursales') }}</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha: Filtros (70%) -->
                        <div class="lg:col-span-7">
                            <!-- Grid de Categorías y Etiquetas lado a lado -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Categorías -->
                                <div class="flex flex-col">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('Categorías') }}
                                        @if(count($categoriasSeleccionadas) > 0)
                                            <span class="ml-1 px-1.5 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                                {{ count($categoriasSeleccionadas) }}
                                            </span>
                                        @endif
                                    </label>
                                    <div class="relative mb-1.5">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="busquedaCategoria"
                                            :placeholder="__('Buscar categoría...')"
                                            class="w-full pl-7 pr-3 py-1 text-xs border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                        >
                                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-1.5 space-y-0.5" style="max-height: calc(100vh - 380px);">
                                        @forelse($categorias as $categoria)
                                            <label class="flex items-center px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="categoriasSeleccionadas"
                                                    value="{{ $categoria->id }}"
                                                    class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                                />
                                                <span class="ml-2 flex items-center gap-1.5">
                                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $categoria->color }}"></span>
                                                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ $categoria->nombre }}</span>
                                                </span>
                                            </label>
                                        @empty
                                            <p class="text-xs text-gray-500 dark:text-gray-400 p-2">{{ __('No hay categorías disponibles') }}</p>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Etiquetas agrupadas -->
                                <div class="flex flex-col">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('Etiquetas') }}
                                        @if(count($etiquetasSeleccionadas) > 0)
                                            <span class="ml-1 px-1.5 py-0.5 bg-bcn-primary/10 text-bcn-primary text-xs rounded-full">
                                                {{ count($etiquetasSeleccionadas) }}
                                            </span>
                                        @endif
                                    </label>
                                    <div class="relative mb-1.5">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="busquedaEtiqueta"
                                            :placeholder="__('Buscar grupo o etiqueta...')"
                                            class="w-full pl-7 pr-3 py-1 text-xs border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors"
                                        >
                                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md" style="max-height: calc(100vh - 380px);">
                                        @forelse($gruposEtiquetas as $grupo)
                                            @if($grupo->etiquetas->count() > 0)
                                                <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                                    <div class="px-2.5 py-1.5 bg-gray-50 dark:bg-gray-700 flex items-center gap-1.5 sticky top-0">
                                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $grupo->color }}"></span>
                                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                                    </div>
                                                    <div class="p-1.5 space-y-0.5">
                                                        @foreach($grupo->etiquetas as $etiqueta)
                                                            <label class="flex items-center gap-1.5 px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model.live="etiquetasSeleccionadas"
                                                                    value="{{ $etiqueta->id }}"
                                                                    class="w-3.5 h-3.5 rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary"
                                                                />
                                                                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $etiqueta->color ?? $grupo->color }}"></span>
                                                                <span class="text-xs text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @empty
                                            <p class="text-xs text-gray-500 dark:text-gray-400 p-2">{{ __('No hay etiquetas disponibles') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra inferior sticky: Botón -->
            <div class="sticky bottom-0 z-10 mt-3 bg-white dark:bg-gray-800 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] sm:rounded-lg border-t border-gray-200 dark:border-gray-700">
                <div class="px-4 py-2.5 sm:px-5 flex justify-end">
                    <button
                        wire:click="siguientePaso"
                        class="inline-flex items-center justify-center px-5 py-2 bg-bcn-primary border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150 whitespace-nowrap"
                    >
                        {{ __('Procesar') }}
                        <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        <!-- Paso 2: Vista previa y confirmación (layout fijo, solo tabla scrolleable) -->
        @if($paso === 2)
            <div class="flex flex-col flex-1 min-h-0">
                <!-- Resumen compacto de configuración -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-3 flex-shrink-0">
                    <div class="p-3 sm:p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Ajuste -->
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium {{ $tipoAjuste === 'descuento' ? 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800' : 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800' }}">
                                    @if($tipoAjuste === 'descuento')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                                    @else
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                                    @endif
                                    {{ ucfirst($tipoAjuste) }} {{ $tipoValor === 'porcentual' ? $valorAjuste . '%' : '$' . number_format($valorAjuste, 2, ',', '.') }}
                                </span>

                                <!-- Redondeo -->
                                @if($tipoRedondeo !== 'sin_redondeo')
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                                        {{ __('Redondeo') }}: {{ match($tipoRedondeo) { 'entero' => __('Entero'), 'decena' => __('Decena'), 'centena' => __('Centena'), default => '' } }}
                                    </span>
                                @endif

                                <!-- Filtros -->
                                @if(!empty($categoriasSeleccionadas) || !empty($etiquetasSeleccionadas))
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                                        @if(!empty($categoriasSeleccionadas) && !empty($etiquetasSeleccionadas))
                                            {{ count($categoriasSeleccionadas) }} {{ __('cat.') }} · {{ count($etiquetasSeleccionadas) }} {{ __('etiq.') }}
                                        @elseif(!empty($categoriasSeleccionadas))
                                            {{ count($categoriasSeleccionadas) }} {{ __('categoría(s)') }}
                                        @else
                                            {{ count($etiquetasSeleccionadas) }} {{ __('etiqueta(s)') }}
                                        @endif
                                    </span>
                                @endif

                                <!-- Alcance -->
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium {{ $alcancePrecio === 'sucursal_actual' ? 'bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800' : 'bg-cyan-50 text-cyan-700 border border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-300 dark:border-cyan-800' }}">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    {{ $alcancePrecio === 'sucursal_actual' ? __('Sucursal actual') : __('Precio genérico') }}
                                </span>
                            </div>

                            <!-- Botón modificar -->
                            <button
                                wire:click="pasoAnterior"
                                class="inline-flex items-center px-2.5 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ __('Modificar') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de artículos (flex-1 para ocupar espacio restante) -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex-1 flex flex-col min-h-0 overflow-hidden">
                    <!-- Buscador (fijo) -->
                    <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="relative flex-1 max-w-md">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="busquedaArticuloPreview"
                                    :placeholder="__('Buscar por código o nombre...')"
                                    class="w-full pl-8 pr-3 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-2 focus:ring-bcn-primary/20 focus:border-bcn-primary transition-colors text-xs"
                                >
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($busquedaArticuloPreview)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ count($articulosPreviewFiltrados) }}/{{ $totalArticulos }}
                                    </span>
                                @endif
                                <button
                                    wire:click="abrirModalAgregarArticulo"
                                    class="inline-flex items-center px-2.5 py-1.5 bg-bcn-primary text-white text-xs font-medium rounded-md hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    {{ __('Agregar') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido scrolleable de la tabla -->
                    <div class="flex-1 overflow-y-auto overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-bcn-light dark:bg-gray-700 sticky top-0 z-[1]">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Código') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Artículo') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider hidden md:table-cell">
                                        {{ __('Categoría') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Actual') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Nuevo') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider hidden sm:table-cell">
                                        {{ __('Dif.') }}
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-10">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($articulosPreviewFiltrados as $articulo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ $articulo['codigo'] }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="text-xs text-gray-900 dark:text-white">{{ $articulo['nombre'] }}</span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap hidden md:table-cell">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: {{ $articulo['categoria_color'] }}20; color: {{ $articulo['categoria_color'] }}; border: 1px solid {{ $articulo['categoria_color'] }}40;">
                                                {{ $articulo['categoria'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">${{ number_format($articulo['precio_viejo'], 2, ',', '.') }}</span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value="{{ $articulo['precio_nuevo'] }}"
                                                wire:change="actualizarPrecioManual({{ $articulo['id'] }}, $event.target.value)"
                                                class="w-24 text-right text-xs font-medium rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 py-1 {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}"
                                            />
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-right hidden sm:table-cell">
                                            <span class="text-xs {{ $articulo['diferencia'] >= 0 ? 'text-orange-600' : 'text-green-600' }}">
                                                {{ $articulo['diferencia'] >= 0 ? '+' : '' }}${{ number_format($articulo['diferencia'], 2, ',', '.') }}
                                                <span class="text-xs text-gray-400">({{ $articulo['diferencia_porcentaje'] >= 0 ? '+' : '' }}{{ $articulo['diferencia_porcentaje'] }}%)</span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                            <button
                                                wire:click="quitarArticulo({{ $articulo['id'] }})"
                                                class="text-red-400 hover:text-red-600 transition-colors"
                                                :title="__('Quitar de la lista')"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            <p class="mt-2 text-sm">{{ __('No se encontraron artículos con los filtros seleccionados') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer fijo con totales + botón -->
                    @if(count($articulosPreview) > 0)
                        <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 px-4 py-2.5 sm:px-5 flex-shrink-0">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Artículos') }}:</span>
                                        <span class="text-sm font-bold text-bcn-secondary dark:text-white">{{ $totalArticulos }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total actual') }}:</span>
                                        <span class="text-sm font-bold text-gray-600 dark:text-gray-300">${{ number_format($totalPrecioViejo, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total nuevo') }}:</span>
                                        <span class="text-sm font-bold {{ $tipoAjuste === 'descuento' ? 'text-green-600' : 'text-orange-600' }}">${{ number_format($totalPrecioNuevo, 2, ',', '.') }}</span>
                                    </div>
                                    @php $diferenciaTotales = $totalPrecioNuevo - $totalPrecioViejo; @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Diferencia') }}:</span>
                                        <span class="text-sm font-bold {{ $diferenciaTotales >= 0 ? 'text-orange-600' : 'text-green-600' }}">
                                            {{ $diferenciaTotales >= 0 ? '+' : '' }}${{ number_format($diferenciaTotales, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    wire:click="confirmarCambios"
                                    class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('Aplicar Cambios') }} ({{ $totalArticulos }})
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
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
                                {{ __('Agregar artículo a la lista') }}
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
                                :placeholder="__('Buscar por código o nombre...')"
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
                                        {{ __('No se encontraron artículos') }}
                                    </div>
                                @endforelse
                            @else
                                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                    {{ __('Escribe al menos 2 caracteres para buscar') }}
                                </div>
                            @endif
                        </div>

                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Haz clic en un artículo para agregarlo a la lista con el ajuste configurado.') }}
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
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    {{ __('Confirmar cambio de precios') }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('Estás a punto de modificar el precio de') }} <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $totalArticulos }} {{ __('artículos') }}</span>.
                                    </p>
                                    @if($alcancePrecio === 'sucursal_actual')
                                        <p class="text-sm text-purple-600 dark:text-purple-400 mt-2 font-medium">
                                            {{ __('Se guardarán como precios propios en') }} {{ __('la sucursal actual') }}.
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                            {{ __('Esta acción también actualizará los precios fijos en las listas de precios donde estos artículos participen.') }}
                                        </p>
                                        <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">
                                            {{ __('Se eliminarán los precios propios de las sucursales') }}.
                                        </p>
                                    @endif

                                    <!-- Modo de aplicación -->
                                    <div class="mt-4 space-y-2">
                                        <label class="flex items-start gap-2 p-2 rounded-lg border cursor-pointer transition-colors {{ $modoAplicacion === 'ahora' ? 'border-bcn-primary bg-bcn-primary/5' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                            <input type="radio" wire:model.live="modoAplicacion" value="ahora" class="text-bcn-primary focus:ring-bcn-primary mt-0.5">
                                            <div>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Aplicar ahora') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Los precios se actualizarán inmediatamente') }}</p>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-2 p-2 rounded-lg border cursor-pointer transition-colors {{ $modoAplicacion === 'programar' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                            <input type="radio" wire:model.live="modoAplicacion" value="programar" class="text-blue-500 focus:ring-blue-500 mt-0.5">
                                            <div>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Programar para después') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Los precios se actualizarán en la fecha y hora indicada') }}</p>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Campos de fecha/hora (solo si programar) -->
                                    @if($modoAplicacion === 'programar')
                                        <div class="mt-3 grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha') }}</label>
                                                <input
                                                    type="date"
                                                    wire:model="fechaProgramada"
                                                    min="{{ now()->format('Y-m-d') }}"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm"
                                                >
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hora') }}</label>
                                                <input
                                                    type="time"
                                                    wire:model="horaProgramada"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm"
                                                >
                                            </div>
                                        </div>
                                    @endif

                                    @if($modoAplicacion === 'ahora')
                                        <p class="text-sm text-red-600 mt-2 font-medium">
                                            {{ __('Esta acción no se puede deshacer.') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                        @if($modoAplicacion === 'ahora')
                            <button
                                type="button"
                                wire:click="aplicarCambios"
                                class="inline-flex w-full justify-center rounded-md bg-bcn-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 sm:w-auto transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ __('Aplicar ahora') }}
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="programarCambios"
                                class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 sm:w-auto transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Programar cambio') }}
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="cancelarConfirmacion"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-500 sm:mt-0 sm:w-auto transition-colors"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
