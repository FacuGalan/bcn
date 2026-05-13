{{-- Modal de Búsqueda Avanzada de Artículos (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
@if($mostrarModalBusquedaArticulos)
    <x-bcn-modal
        :show="$mostrarModalBusquedaArticulos"
        title="{{ __('Buscar Artículo') }}"
        color="bg-gray-500"
        maxWidth="7xl"
        onClose="cerrarModalBusquedaArticulos"
    >
        <x-slot:body>
            <div x-data="{
                hlIdx: 0,
                selecting: false,
                get rowCount() {
                    return this.$refs.tablaArticulos ? this.$refs.tablaArticulos.querySelectorAll('tr[data-row]').length : 0;
                },
                scrollToRow() {
                    this.$nextTick(() => {
                        const rows = this.$refs.tablaArticulos?.querySelectorAll('tr[data-row]');
                        if (rows && rows[this.hlIdx]) rows[this.hlIdx].scrollIntoView({ block: 'nearest' });
                    });
                },
                pickArticulo(id) {
                    if (this.selecting) return;
                    this.selecting = true;
                    $wire.seleccionarArticuloModal(id);
                },
                selectRow() {
                    if (this.selecting) return;
                    const rows = this.$refs.tablaArticulos?.querySelectorAll('tr[data-row]');
                    if (rows && rows[this.hlIdx]) rows[this.hlIdx].click();
                }
            }" class="flex gap-4">
                {{-- Sidebar: Filtro de etiquetas --}}
                @if(count($gruposEtiquetasModal) > 0)
                    <div class="w-56 flex-shrink-0 flex flex-col">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Etiquetas') }}
                            @if(count($etiquetasModalSeleccionadas) > 0)
                                <span class="ml-1 px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs rounded-full">
                                    {{ count($etiquetasModalSeleccionadas) }}
                                </span>
                            @endif
                        </label>
                        <div class="flex-1 max-h-[26rem] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                            @foreach($gruposEtiquetasModal as $grupo)
                                @if($grupo->etiquetas->count() > 0)
                                    <div class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 flex items-center gap-2 sticky top-0">
                                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $grupo->color }}"></span>
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $grupo->nombre }}</span>
                                        </div>
                                        <div class="p-1.5 space-y-0.5">
                                            @foreach($grupo->etiquetas as $etiqueta)
                                                <label class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.live="etiquetasModalSeleccionadas"
                                                        value="{{ $etiqueta->id }}"
                                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-600 w-3.5 h-3.5"
                                                    />
                                                    <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $etiqueta->color ?? $grupo->color }}"></div>
                                                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ $etiqueta->nombre }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @if(count($etiquetasModalSeleccionadas) > 0)
                            <button
                                type="button"
                                wire:click="$set('etiquetasModalSeleccionadas', [])"
                                class="mt-1.5 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                {{ __('Limpiar filtros') }}
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Contenido principal: búsqueda + tabla --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-3">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="busquedaArticuloModal"
                                @keydown.arrow-down.prevent="hlIdx = Math.min(hlIdx + 1, rowCount - 1); scrollToRow()"
                                @keydown.arrow-up.prevent="hlIdx = Math.max(hlIdx - 1, 0); scrollToRow()"
                                @keydown.enter.prevent="selectRow()"
                                x-init="setTimeout(() => $el.focus(), 350)"
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-gray-500 text-sm"
                                placeholder="{{ __('Filtrar por nombre, código, código de barras o categoría...') }}"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __(':count artículos', ['count' => count($articulosModalResultados)]) }}
                            · {{ __('↑↓ navegar · Enter seleccionar') }}
                        </p>
                    </div>

                    <div class="overflow-auto max-h-96 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Código') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Nombre') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">{{ __('Categoría') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio') }}</th>
                                </tr>
                            </thead>
                            <tbody x-ref="tablaArticulos" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($articulosModalResultados as $idx => $art)
                                    <tr
                                        data-row
                                        @click="pickArticulo({{ $art['id'] }})"
                                        @mouseenter="hlIdx = {{ $idx }}"
                                        :class="[hlIdx === {{ $idx }} ? 'bg-indigo-50 dark:bg-indigo-900/30' : '', selecting ? 'pointer-events-none opacity-60' : '']"
                                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $art['codigo'] }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ $art['nombre'] }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hidden sm:table-cell">{{ $art['categoria'] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-white font-medium whitespace-nowrap">$@precio($art['precio_base'])</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('No se encontraron artículos') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                {{ __('Cerrar') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
