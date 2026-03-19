<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Producción') }}</h2>
                        <div class="sm:hidden flex gap-2">
                            <a href="{{ route('stock.produccion-lote') }}" wire:navigate class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-emerald-600 rounded-md text-white hover:bg-emerald-700 transition" title="{{ __('Producir Lote') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </a>
                            <button wire:click="verHistorial" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 transition" title="{{ __('Historial') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Convertir materia prima en producto terminado según recetas definidas') }}</p>
                </div>
                <div class="hidden sm:flex gap-3">
                    <a href="{{ route('stock.produccion-lote') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-emerald-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        {{ __('Producir Lote') }}
                    </a>
                    <button wire:click="verHistorial" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ __('Historial') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Buscador --}}
        <div class="mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-bcn-primary focus:border-bcn-primary sm:text-sm"
                    placeholder="{{ __('Buscar artículo por nombre, código...') }}"
                >
            </div>
        </div>

        {{-- Tabla de artículos con receta --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden {{ count($colaProduccion) > 0 ? 'mb-36 sm:mb-28' : '' }}">
            {{-- Desktop --}}
            <div class="hidden sm:block">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Artículo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Código') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Categoría') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($articulos as $articulo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $articulo->categoriaModel->nombre ?? '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        wire:click="abrirProducir({{ $articulo->id }})"
                                        class="inline-flex items-center px-3 py-1.5 border border-emerald-400 dark:border-emerald-600 rounded-md text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                        {{ __('Producir') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No se encontraron artículos con receta activa.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($articulos as $articulo)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $articulo->nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }} {{ $articulo->categoriaModel ? '/ ' . $articulo->categoriaModel->nombre : '' }}</p>
                        </div>
                        <button
                            wire:click="abrirProducir({{ $articulo->id }})"
                            class="ml-3 inline-flex items-center px-3 py-1.5 border border-emerald-400 dark:border-emerald-600 rounded-md text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition"
                        >
                            {{ __('Producir') }}
                        </button>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        {{ __('No se encontraron artículos con receta activa.') }}
                    </div>
                @endforelse
            </div>

            {{-- Paginación --}}
            @if($articulos->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $articulos->links() }}
                </div>
            @endif
        </div>

        {{-- Cola de producción (barra fija inferior) --}}
        @if(count($colaProduccion) > 0)
            <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t-2 border-emerald-500 shadow-lg z-40 px-4 py-3 sm:px-6">
                <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-1 min-w-0 overflow-x-auto">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-sm font-bold flex-shrink-0">
                            {{ count($colaProduccion) }}
                        </span>
                        <div class="flex gap-2 overflow-x-auto">
                            @foreach($colaProduccion as $index => $item)
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 whitespace-nowrap flex-shrink-0">
                                    {{ $item['nombre'] }} x{{ $item['cantidad'] }}
                                    <button wire:click="quitarDeCola({{ $index }})" class="ml-1 text-emerald-600 dark:text-emerald-400 hover:text-red-500" title="{{ __('Quitar') }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <button wire:click="limpiarCola" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            {{ __('Limpiar') }}
                        </button>
                        <button wire:click="abrirConfirmarLote" class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition">
                            {{ __('Confirmar lote') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ======================== MODALES ======================== --}}

    {{-- Modal Producir --}}
    @if($showProducirModal)
        <x-bcn-modal
            :show="$showProducirModal"
            :title="__('Producir') . ': ' . $producirArticuloNombre"
            color="bg-emerald-600"
            maxWidth="5xl"
            onClose="cerrarProducirModal"
        >
            <x-slot:body>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Define la cantidad a producir y revisa los ingredientes necesarios.') }}</p>

                {{-- Cantidad --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad a producir') }}</label>
                    <input
                        type="number"
                        wire:model.live.debounce.300ms="producirCantidad"
                        min="0.001"
                        step="0.001"
                        class="block w-full sm:w-40 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:text-white"
                    >
                </div>

                {{-- Tabla de ingredientes --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ingrediente') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Und') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('x Unidad') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Total') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cant. usada') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Stock') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Stock result.') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($producirIngredientes as $i => $ing)
                                @php
                                    $cantUsada = (float) $ing['cantidad_real'];
                                    $stockDisp = (float) $ing['stock_disponible'];
                                    $stockResultante = round($stockDisp - $cantUsada, 3);
                                    $insuficiente = $stockResultante < 0;
                                @endphp
                                <tr class="{{ $insuficiente ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $ing['nombre'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">{{ $ing['unidad_medida'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">{{ number_format($ing['cantidad_por_unidad'], 3) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">{{ number_format($ing['cantidad_receta'], 3) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <input
                                            type="number"
                                            wire:model.live.debounce.300ms="producirIngredientes.{{ $i }}.cantidad_real"
                                            min="0"
                                            step="0.001"
                                            class="w-24 text-right rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">
                                        {{ number_format($stockDisp, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold {{ $insuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ number_format($stockResultante, 3) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button"
                        wire:click="agregarACola"
                        class="w-full inline-flex justify-center rounded-md border border-emerald-400 dark:border-emerald-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 sm:w-auto sm:text-sm">
                    {{ __('Agregar a cola') }}
                </button>
                <button type="button"
                        wire:click="producirIndividual"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Producir ahora') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Confirmar Lote --}}
    @if($showConfirmarLoteModal)
        <x-bcn-modal
            :show="$showConfirmarLoteModal"
            :title="__('Confirmar lote de producción')"
            color="bg-emerald-600"
            maxWidth="4xl"
            onClose="cancelarConfirmarLote"
        >
            <x-slot:body>
                {{-- Resumen de artículos a producir --}}
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Artículos a producir') }}</h4>
                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Artículo') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Cantidad') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($colaProduccion as $item)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $item['nombre'] }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">{{ $item['cantidad'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Resumen consolidado de ingredientes --}}
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Ingredientes necesarios (consolidado)') }}</h4>
                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ingrediente') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Total necesario') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Stock disponible') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Diferencia') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($resumenIngredientes as $ing)
                                @php $faltante = $ing['diferencia'] < 0; @endphp
                                <tr class="{{ $faltante ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $ing['nombre'] }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">{{ number_format($ing['total_necesario'], 3) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">{{ number_format($ing['stock_disponible'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold {{ $faltante ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $faltante ? '' : '+' }}{{ number_format($ing['diferencia'], 3) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Observaciones --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones (opcional)') }}</label>
                    <textarea
                        wire:model="loteObservaciones"
                        rows="2"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:text-white"
                        placeholder="{{ __('Notas sobre esta producción...') }}"
                    ></textarea>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button"
                        wire:click="confirmarLote"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Confirmar producción') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Historial --}}
    @if($showHistorialModal)
        <x-bcn-modal
            :show="$showHistorialModal"
            :title="__('Historial de producciones')"
            color="bg-bcn-primary"
            maxWidth="5xl"
            onClose="cerrarHistorial"
        >
            <x-slot:body>
                {{-- Filtros de fecha --}}
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Desde') }}</label>
                        <input type="date" wire:model="filterFechaDesde" wire:change="cargarHistorial" class="rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Hasta') }}</label>
                        <input type="date" wire:model="filterFechaHasta" wire:change="cargarHistorial" class="rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                {{-- Tabla de historial --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Fecha') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('# Lote') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Artículos') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Usuario') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Estado') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($historial as $prod)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $prod['fecha'] }}</td>
                                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400">#{{ $prod['id'] }}</td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">
                                        @foreach($prod['articulos'] as $art)
                                            <span class="inline-block mr-1">{{ $art['nombre'] }} x{{ $art['cantidad'] }}{{ !$loop->last ? ',' : '' }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $prod['usuario'] }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if($prod['estado'] === 'confirmado')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                                {{ __('Confirmado') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300" title="{{ $prod['motivo_anulacion'] }}">
                                                {{ __('Anulado') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button wire:click="verDetalle({{ $prod['id'] }})" class="text-bcn-primary hover:text-bcn-primary/80 text-xs font-medium mr-2">
                                            {{ __('Ver') }}
                                        </button>
                                        @if($prod['estado'] === 'confirmado')
                                            <button wire:click="abrirAnular({{ $prod['id'] }})" class="text-red-600 dark:text-red-400 hover:text-red-800 text-xs font-medium">
                                                {{ __('Anular') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                        {{ __('No hay producciones en el período seleccionado.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Detalle Producción --}}
    @if($showDetalleModal && $detalleProduccion)
        <x-bcn-modal
            :show="$showDetalleModal"
            :title="__('Producción') . ' #' . $detalleProduccion['id']"
            color="bg-bcn-primary"
            maxWidth="3xl"
            onClose="cerrarDetalle"
            zIndex="z-[60]"
        >
            <x-slot:body>
                <div class="flex justify-between items-start mb-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $detalleProduccion['fecha'] }} - {{ $detalleProduccion['usuario'] }}</p>
                    @if($detalleProduccion['estado'] === 'confirmado')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">{{ __('Confirmado') }}</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">{{ __('Anulado') }}</span>
                    @endif
                </div>

                @if($detalleProduccion['observaciones'])
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 italic">{{ $detalleProduccion['observaciones'] }}</p>
                @endif

                @if($detalleProduccion['estado'] === 'anulado' && $detalleProduccion['motivo_anulacion'])
                    <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-md">
                        <p class="text-sm text-red-700 dark:text-red-300">
                            <strong>{{ __('Motivo de anulación') }}:</strong> {{ $detalleProduccion['motivo_anulacion'] }}
                        </p>
                        @if($detalleProduccion['anulado_por'])
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ __('Por') }}: {{ $detalleProduccion['anulado_por'] }}</p>
                        @endif
                    </div>
                @endif

                @foreach($detalleProduccion['detalles'] as $detalle)
                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
                            {{ $detalle['articulo'] }} - {{ $detalle['cantidad_producida'] }} {{ __('unidades') }}
                        </h4>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Ingrediente') }}</th>
                                    <th class="px-2 py-1 text-right font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Según receta') }}</th>
                                    <th class="px-2 py-1 text-right font-medium text-gray-500 dark:text-gray-300 uppercase">{{ __('Real usado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($detalle['ingredientes'] as $ing)
                                    @php $diferente = abs($ing['cantidad_receta'] - $ing['cantidad_real']) > 0.001; @endphp
                                    <tr>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ $ing['articulo'] }}</td>
                                        <td class="px-2 py-1 text-right text-gray-500 dark:text-gray-400">{{ number_format($ing['cantidad_receta'], 3) }}</td>
                                        <td class="px-2 py-1 text-right {{ $diferente ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-900 dark:text-white' }}">
                                            {{ number_format($ing['cantidad_real'], 3) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Anular Producción --}}
    @if($showAnularModal)
        <x-bcn-modal
            :show="$showAnularModal"
            :title="__('Anular producción')"
            color="bg-red-600"
            maxWidth="lg"
            onClose="cancelarAnulacion"
            zIndex="z-[60]"
        >
            <x-slot:body>
                <div class="flex items-start">
                    <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Se revertirán todos los movimientos de stock. Esta acción no se puede deshacer.') }}</p>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo de anulación') }} *</label>
                            <textarea
                                wire:model="motivoAnulacion"
                                rows="3"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:text-white"
                                placeholder="{{ __('Ingrese el motivo...') }}"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button"
                        @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button"
                        wire:click="confirmarAnulacion"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 sm:w-auto sm:text-sm">
                    {{ __('Anular producción') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
