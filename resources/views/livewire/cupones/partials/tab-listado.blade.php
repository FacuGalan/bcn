{{-- Filtros --}}
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
    {{-- Botón de filtros (solo móvil) --}}
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
                @if($searchCupon || $filtroTipo || $filtroEstado)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                        {{ __('Activos') }}
                    </span>
                @endif
            </span>
            <svg
                class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    {{-- Contenedor de filtros --}}
    <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="searchCupon"
                    placeholder="{{ __('Código o descripción...') }}"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                <select wire:model.live="filtroTipo"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    <option value="">{{ __('Todos los tipos') }}</option>
                    <option value="promocional">{{ __('Cupón promocional') }}</option>
                    <option value="puntos">{{ __('Cupón desde puntos') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                <select wire:model.live="filtroEstado"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    <option value="">{{ __('Todos los estados') }}</option>
                    <option value="activo">{{ __('Vigentes') }}</option>
                    <option value="inactivo">{{ __('Inactivos') }}</option>
                    <option value="vencido">{{ __('Vencidos') }}</option>
                    <option value="agotado">{{ __('Agotados') }}</option>
                </select>
            </div>
        </div>
    </div>
</div>

{{-- Mobile: cards --}}
<div class="sm:hidden space-y-3">
    @forelse($cupones as $cupon)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex justify-between items-start mb-2">
            <div>
                <span class="text-sm font-bold font-mono text-gray-900 dark:text-white">{{ $cupon->codigo }}</span>
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cupon->esPromocional() ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                    {{ $cupon->esPromocional() ? __('Promocional') : __('Puntos') }}
                </span>
            </div>
            @php
                $estadoClass = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
                $estadoLabel = __('Activo');
                if (!$cupon->activo) {
                    $estadoClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                    $estadoLabel = __('Inactivo');
                } elseif ($cupon->fecha_vencimiento && $cupon->fecha_vencimiento->lt(now()->startOfDay())) {
                    $estadoClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                    $estadoLabel = __('Vencido');
                } elseif ($cupon->uso_maximo > 0 && $cupon->uso_actual >= $cupon->uso_maximo) {
                    $estadoClass = 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
                    $estadoLabel = __('Agotado');
                }
            @endphp
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $estadoClass }}">
                {{ $estadoLabel }}
            </span>
        </div>
        @if($cupon->descripcion)
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">{{ $cupon->descripcion }}</p>
        @endif
        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
            <span>
                {{ $cupon->esPorcentaje() ? $cupon->valor_descuento . '%' : '$' . number_format($cupon->valor_descuento, 2) }}
                — {{ $cupon->aplicaATotal() ? __('Total de la venta') : __('Artículos específicos') }}
            </span>
            <span>
                {{ $cupon->uso_actual }}/{{ $cupon->uso_maximo == 0 ? '∞' : $cupon->uso_maximo }}
            </span>
        </div>
        <div class="flex justify-end gap-2">
            <button wire:click="editarCupon({{ $cupon->id }})"
                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150"
                title="{{ __('Editar') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button wire:click="toggleCuponActivo({{ $cupon->id }})"
                class="inline-flex items-center justify-center px-3 py-2 border text-sm font-medium rounded-md transition-colors duration-150 {{ $cupon->activo ? 'border-red-600 text-red-600 hover:bg-red-600 hover:text-white' : 'border-green-600 text-green-600 hover:bg-green-600 hover:text-white' }}"
                title="{{ $cupon->activo ? __('Desactivar') : __('Activar') }}">
                @if($cupon->activo)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
            </button>
        </div>
    </div>
    @empty
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
        </svg>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No hay cupones') }}</p>
    </div>
    @endforelse

    {{-- Paginación Mobile --}}
    @if($cupones->hasPages())
    <div class="mt-4">
        {{ $cupones->links() }}
    </div>
    @endif
</div>

{{-- Desktop: tabla --}}
<div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-bcn-light dark:bg-gray-900">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Código') }}</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Descuento') }}</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Aplica a') }}</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Usos') }}</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Vencimiento') }}</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($cupones as $cupon)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-mono font-bold text-gray-900 dark:text-white">{{ $cupon->codigo }}</span>
                        @if($cupon->descripcion)
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $cupon->descripcion }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cupon->esPromocional() ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                            {{ $cupon->esPromocional() ? __('Promocional') : __('Puntos') }}
                        </span>
                        @if($cupon->cliente)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $cupon->cliente->nombre }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        @if($cupon->esPorcentaje())
                            {{ $cupon->valor_descuento }}%
                        @else
                            ${{ number_format($cupon->valor_descuento, 2) }}
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ $cupon->aplicaATotal() ? __('Total') : __('Artículos') }}
                    </td>
                    <td class="px-6 py-4 text-sm text-center text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ $cupon->uso_actual }}/{{ $cupon->uso_maximo == 0 ? '∞' : $cupon->uso_maximo }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {{ $cupon->fecha_vencimiento ? $cupon->fecha_vencimiento->format('d/m/Y') : __('Sin vencimiento') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php
                            $estadoClass = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
                            $estadoLabel = __('Activo');
                            if (!$cupon->activo) {
                                $estadoClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                                $estadoLabel = __('Inactivo');
                            } elseif ($cupon->fecha_vencimiento && $cupon->fecha_vencimiento->lt(now()->startOfDay())) {
                                $estadoClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                                $estadoLabel = __('Vencido');
                            } elseif ($cupon->uso_maximo > 0 && $cupon->uso_actual >= $cupon->uso_maximo) {
                                $estadoClass = 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
                                $estadoLabel = __('Agotado');
                            }
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $estadoClass }}">
                            {{ $estadoLabel }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <div class="flex justify-end gap-1.5">
                            <button wire:click="editarCupon({{ $cupon->id }})"
                                class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-150"
                                title="{{ __('Editar cupón') }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                {{ __('Editar') }}
                            </button>
                            <button wire:click="toggleCuponActivo({{ $cupon->id }})"
                                class="inline-flex items-center justify-center w-9 h-9 border text-xs font-medium rounded transition-colors duration-150 {{ $cupon->activo ? 'border-red-600 text-red-600 hover:bg-red-600 hover:text-white' : 'border-green-600 text-green-600 hover:bg-green-600 hover:text-white' }}"
                                title="{{ $cupon->activo ? __('Desactivar') : __('Activar') }}">
                                @if($cupon->activo)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('No hay cupones') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{-- Paginación Desktop --}}
    @if($cupones->hasPages())
    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
        {{ $cupones->links() }}
    </div>
    @endif
</div>
