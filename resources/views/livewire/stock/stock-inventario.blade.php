{{-- Vista: Gestión de Stock/Inventario --}}

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Stock</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Control de inventario y alertas</p>
    </div>

    {{-- Alertas --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-900">Stock Bajo Mínimo</h3>
                        <p class="text-2xl font-bold text-yellow-700">{{ $alertasBajoMinimo }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <h3 class="text-lg font-semibold text-red-900">Sin Stock</h3>
                        <p class="text-2xl font-bold text-red-700">{{ $articulosSinStock }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sucursal</label>
                    <select wire:model.live="sucursalSeleccionada" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        @foreach($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar Artículo</label>
                    <input wire:model.live.debounce.300ms="search" type="text" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Código o nombre...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alertas</label>
                    <select wire:model.live="filterAlerta" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="all">Todos</option>
                        <option value="bajo_minimo">Bajo Mínimo</option>
                        <option value="sin_stock">Sin Stock</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Artículo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Mínimo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Máximo</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($stocks as $stock)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $stock->articulo->nombre }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stock->articulo->codigo }}</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm font-bold {{ $stock->cantidad <= 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                                    @cantidad($stock->cantidad)
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">{{ $stock->cantidad_minima ?? '-' }}</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">{{ $stock->cantidad_maxima ?? '-' }}</td>
                            <td class="px-6 py-4 text-center">
                                @if($stock->estaBajoMinimo())
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Bajo Mínimo</span>
                                @elseif($stock->cantidad <= 0)
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Sin Stock</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Normal</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                <button wire:click="abrirModalAjuste({{ $stock->id }})" class="text-blue-600 hover:text-blue-900">Ajustar</button>
                                <button wire:click="abrirModalInventario({{ $stock->id }})" class="text-purple-600 hover:text-purple-900">Inventario</button>
                                <button wire:click="abrirModalUmbrales({{ $stock->id }})" class="text-gray-600 hover:text-gray-900">Umbrales</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No hay stock registrado</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($stocks->hasPages())<div class="px-4 py-3 border-t dark:border-gray-700">{{ $stocks->links() }}</div>@endif
        </div>
    </div>

    {{-- Modal Ajuste --}}
    @if($showAjusteModal && $stockAjuste)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showAjusteModal', false)"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-blue-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Ajustar Stock</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Artículo</label><p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $stockAjuste->articulo->nombre }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Stock actual: @cantidad($stockAjuste->cantidad)</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad de Ajuste</label><input wire:model="cantidadAjuste" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Positivo aumenta, negativo disminuye"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Motivo</label><textarea wire:model="motivoAjuste" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showAjusteModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="procesarAjuste" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Procesar Ajuste</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Inventario --}}
    @if($showInventarioModal && $stockInventario)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showInventarioModal', false)"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-purple-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Inventario Físico</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Artículo</label><p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $stockInventario->articulo->nombre }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Stock actual en sistema: @cantidad($stockInventario->cantidad)</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad Física Contada</label><input wire:model="cantidadFisica" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones</label><textarea wire:model="observacionesInventario" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"></textarea></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showInventarioModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="procesarInventario" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">Registrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Umbrales --}}
    @if($showUmbralesModal && $stockUmbrales)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showUmbralesModal', false)"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-gray-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Configurar Umbrales</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Artículo</label><p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $stockUmbrales->articulo->nombre }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad Mínima</label><input wire:model="cantidadMinima" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad Máxima</label><input wire:model="cantidadMaxima" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500"></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showUmbralesModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="actualizarUmbrales" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-gray-600 hover:bg-gray-700">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
