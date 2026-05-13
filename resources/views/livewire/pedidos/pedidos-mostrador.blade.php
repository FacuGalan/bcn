<div class="py-4" wire:poll.15s>
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">
                            {{ __('Pedidos por Mostrador') }}
                        </h2>
                        {{-- Botón móvil --}}
                        <div class="sm:hidden flex gap-2">
                            <a href="#"
                                onclick="event.preventDefault(); window.notify('{{ __('Disponible en próxima entrega') }}', 'info')"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                title="{{ __('Nuevo Pedido') }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Pedidos activos por sucursal — cambiar estado, cobrar, convertir en venta o cancelar') }}
                    </p>
                </div>
                {{-- Botón desktop --}}
                <div class="hidden sm:flex gap-3">
                    <a href="#"
                        onclick="event.preventDefault(); window.notify('{{ __('Disponible en próxima entrega') }}', 'info')"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                        title="{{ __('Nuevo Pedido') }}"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nuevo Pedido') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button wire:click="toggleFilters"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                    </span>
                    <svg class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input type="text" id="search" wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('N°, identificador, cliente...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                    </div>
                    <div>
                        <label for="filterEstadoPedido" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado del pedido') }}</label>
                        <select id="filterEstadoPedido" wire:model.live="filterEstadoPedido"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="activos">{{ __('Solo activos') }}</option>
                            <option value="all">{{ __('Todos') }}</option>
                            @foreach($estadosPedido as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filterEstadoPago" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado del pago') }}</label>
                        <select id="filterEstadoPago" wire:model.live="filterEstadoPago"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="all">{{ __('Todos') }}</option>
                            @foreach($estadosPago as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="filterFechaDesde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                            <input type="date" id="filterFechaDesde" wire:model.live="filterFechaDesde"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        </div>
                        <div>
                            <label for="filterFechaHasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                            <input type="date" id="filterFechaHasta" wire:model.live="filterFechaHasta"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button wire:click="resetFilters"
                        class="text-xs text-gray-600 dark:text-gray-400 hover:text-bcn-primary underline">
                        {{ __('Limpiar filtros') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Cards móvil --}}
        <div class="sm:hidden space-y-3">
            @forelse($pedidos as $pedido)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-base font-bold text-bcn-secondary dark:text-white">
                                    @if($pedido->numero)
                                        #{{ $pedido->numero }}
                                    @else
                                        <span class="italic text-gray-500">{{ __('Borrador') }}</span>
                                    @endif
                                </span>
                                @if($pedido->identificador)
                                    <span class="text-xs text-gray-600 dark:text-gray-400">/ {{ $pedido->identificador }}</span>
                                @endif
                                @if($pedido->numero_beeper)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        🔔 {{ $pedido->numero_beeper }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $pedido->nombre_cliente_final ?? __('Sin cliente') }}
                                @if($pedido->telefono_cliente_final) — {{ $pedido->telefono_cliente_final }} @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $pedido->fecha->format('d/m/Y H:i') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-base font-bold text-bcn-secondary dark:text-white">
                                ${{ number_format($pedido->total_final, 2, ',', '.') }}
                            </div>
                            @if($pedido->total_cobrado > 0)
                                <div class="text-xs text-green-700 dark:text-green-400">
                                    {{ __('Cobrado') }}: ${{ number_format($pedido->total_cobrado, 2, ',', '.') }}
                                </div>
                            @endif
                            @if($pedido->total_planificado > 0)
                                <div class="text-xs text-blue-700 dark:text-blue-400">
                                    {{ __('Planificado') }}: ${{ number_format($pedido->total_planificado, 2, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 flex-wrap mb-3">
                        <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" />
                        <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" />
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button wire:click="verDetalle({{ $pedido->id }})"
                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{ __('Ver') }}
                        </button>
                        @if(!in_array($pedido->estado_pedido, ['cancelado','facturado']))
                            <button wire:click="abrirCambiarEstado({{ $pedido->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-blue-300 dark:border-blue-600 rounded text-xs text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30">
                                {{ __('Estado') }}
                            </button>
                            @if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
                                <button wire:click="abrirCobrar({{ $pedido->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-green-300 dark:border-green-600 rounded text-xs text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30">
                                    {{ __('Cobrar') }}
                                </button>
                            @endif
                            @if($pedido->estado_pedido !== 'borrador' && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta'))
                                <button wire:click="abrirConvertir({{ $pedido->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-bcn-primary rounded text-xs text-bcn-primary hover:bg-bcn-primary hover:text-white">
                                    {{ __('Convertir') }}
                                </button>
                            @endif
                            @if(auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar'))
                                <button wire:click="abrirCancelar({{ $pedido->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                    {{ __('Cancelar') }}
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No hay pedidos para estos filtros') }}</p>
                </div>
            @endforelse
            <div class="mt-4">{{ $pedidos->links() }}</div>
        </div>

        {{-- Tabla desktop --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('N°') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Identificador') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Cliente') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Pago') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($pedidos as $pedido)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-bold text-bcn-secondary dark:text-white">
                                        @if($pedido->numero)
                                            #{{ $pedido->numero }}
                                        @else
                                            <span class="italic text-gray-500 text-xs">{{ __('Borrador') }}</span>
                                        @endif
                                    </div>
                                    @if($pedido->numero_beeper)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 mt-1">
                                            🔔 {{ $pedido->numero_beeper }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $pedido->identificador ?: '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ $pedido->nombre_cliente_final ?? __('Sin cliente') }}
                                    </div>
                                    @if($pedido->telefono_cliente_final)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $pedido->telefono_cliente_final }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    {{ $pedido->fecha->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="text-sm font-bold text-bcn-secondary dark:text-white">
                                        ${{ number_format($pedido->total_final, 2, ',', '.') }}
                                    </div>
                                    @if($pedido->total_cobrado > 0)
                                        <div class="text-xs text-green-700 dark:text-green-400">
                                            {{ __('Cob.') }}: ${{ number_format($pedido->total_cobrado, 2, ',', '.') }}
                                        </div>
                                    @endif
                                    @if($pedido->total_planificado > 0)
                                        <div class="text-xs text-blue-700 dark:text-blue-400">
                                            {{ __('Plan.') }}: ${{ number_format($pedido->total_planificado, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" />
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" />
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1 flex-wrap">
                                        <button wire:click="verDetalle({{ $pedido->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                            title="{{ __('Ver detalle') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        @if(!in_array($pedido->estado_pedido, ['cancelado','facturado']))
                                            <button wire:click="abrirCambiarEstado({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-blue-300 dark:border-blue-600 rounded text-xs text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                                title="{{ __('Cambiar estado') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                </svg>
                                            </button>
                                            @if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
                                                <button wire:click="abrirCobrar({{ $pedido->id }})"
                                                    class="inline-flex items-center px-2 py-1 border border-green-300 dark:border-green-600 rounded text-xs text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30"
                                                    title="{{ __('Cobrar pendiente') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            @endif
                                            @if($pedido->estado_pedido !== 'borrador' && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta'))
                                                <button wire:click="abrirConvertir({{ $pedido->id }})"
                                                    class="inline-flex items-center px-2 py-1 border border-bcn-primary rounded text-xs text-bcn-primary hover:bg-bcn-primary hover:text-white"
                                                    title="{{ __('Convertir en venta') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            @endif
                                            <button wire:click="reimprimirComanda({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                                title="{{ __('Imprimir comanda') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                            </button>
                                            @if(auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar'))
                                                <button wire:click="abrirCancelar({{ $pedido->id }})"
                                                    class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30"
                                                    title="{{ __('Cancelar pedido') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('No hay pedidos para estos filtros') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $pedidos->links() }}
            </div>
        </div>
    </div>

    {{-- ==================== MODAL: DETALLE ==================== --}}
    @if($showDetalleModal && $pedidoDetalle)
        <x-bcn-modal
            :title="__('Pedido') . ' ' . ($pedidoDetalle->numero ? '#' . $pedidoDetalle->numero : __('(Borrador)'))"
            color="bg-bcn-secondary"
            maxWidth="3xl"
            onClose="cerrarDetalle"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">{{ __('Cliente') }}</div>
                            <div class="text-gray-900 dark:text-gray-100">{{ $pedidoDetalle->nombre_cliente_final ?? __('Sin cliente') }}</div>
                            @if($pedidoDetalle->telefono_cliente_final)
                                <div class="text-gray-500 dark:text-gray-400 text-xs">{{ $pedidoDetalle->telefono_cliente_final }}</div>
                            @endif
                        </div>
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300">{{ __('Fecha') }}</div>
                            <div class="text-gray-900 dark:text-gray-100">{{ $pedidoDetalle->fecha->format('d/m/Y H:i') }}</div>
                        </div>
                        @if($pedidoDetalle->identificador)
                            <div>
                                <div class="font-semibold text-gray-700 dark:text-gray-300">{{ __('Identificador') }}</div>
                                <div class="text-gray-900 dark:text-gray-100">{{ $pedidoDetalle->identificador }}</div>
                            </div>
                        @endif
                        @if($pedidoDetalle->numero_beeper)
                            <div>
                                <div class="font-semibold text-gray-700 dark:text-gray-300">{{ __('Beeper') }}</div>
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-base font-mono font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    🔔 {{ $pedidoDetalle->numero_beeper }}
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-pedidos.badge-estado-pedido :estado="$pedidoDetalle->estado_pedido" />
                        <x-pedidos.badge-estado-pago :estado="$pedidoDetalle->estado_pago" />
                        @if($pedidoDetalle->venta_id)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                {{ __('Venta') }} #{{ $pedidoDetalle->venta?->numero ?? $pedidoDetalle->venta_id }}
                            </span>
                        @endif
                    </div>

                    {{-- Items --}}
                    <div>
                        <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 text-sm">{{ __('Detalle') }}</div>
                        <div class="border border-gray-200 dark:border-gray-700 rounded overflow-hidden">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Artículo') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Cant.') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('P.U.') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($pedidoDetalle->detalles as $detalle)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                                {{ $detalle->es_concepto ? $detalle->concepto_descripcion : ($detalle->articulo?->nombre ?? '-') }}
                                                @if($detalle->opcionales->isNotEmpty())
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        @foreach($detalle->opcionales as $opc)
                                                            <span class="inline-block mr-2">+ {{ $opc->descripcion }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($detalle->cantidad, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">${{ number_format($detalle->precio_unitario, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100 font-medium">${{ number_format($detalle->total, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Total final') }}</td>
                                        <td class="px-3 py-2 text-right text-sm font-bold text-bcn-secondary dark:text-white">${{ number_format($pedidoDetalle->total_final, 2, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- Pagos --}}
                    @if($pedidoDetalle->pagos->isNotEmpty())
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 text-sm">{{ __('Pagos') }}</div>
                            <div class="border border-gray-200 dark:border-gray-700 rounded overflow-hidden">
                                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Forma de pago') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Monto') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Estado') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($pedidoDetalle->pagos as $pago)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $pago->formaPago?->nombre ?? '-' }}</td>
                                                <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100 font-medium">${{ number_format($pago->monto_final, 2, ',', '.') }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($pago->estado === 'activo') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                        @elseif($pago->estado === 'planificado') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                        @else bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                                        {{ __(ucfirst($pago->estado)) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($pedidoDetalle->observaciones)
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-1 text-sm">{{ __('Observaciones') }}</div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $pedidoDetalle->observaciones }}</p>
                        </div>
                    @endif
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button" wire:click="reimprimirPrecuenta({{ $pedidoDetalle->id }})"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Imprimir precuenta') }}
                </button>
                <button type="button" wire:click="reimprimirComanda({{ $pedidoDetalle->id }})"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Imprimir comanda') }}
                </button>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-secondary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CAMBIAR ESTADO ==================== --}}
    @if($showCambiarEstadoModal)
        <x-bcn-modal
            :title="__('Cambiar estado del pedido')"
            color="bg-blue-600"
            maxWidth="md"
            onClose="cancelarCambiarEstado"
            submit="confirmarCambiarEstado"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nuevo estado') }}</label>
                        <select wire:model="nuevoEstado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm">
                            @foreach($transicionesDisponibles as $estado)
                                <option value="{{ $estado }}">{{ __($estadosPedido[$estado] ?? $estado) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observación (opcional)') }}</label>
                        <textarea wire:model="observacionEstado" rows="3"
                            placeholder="{{ __('Ej: Demora en cocina, ajuste de pedido...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm"></textarea>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:w-auto sm:text-sm">
                    {{ __('Aplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CANCELAR ==================== --}}
    @if($showCancelarModal)
        <x-bcn-modal
            :title="__('Cancelar pedido')"
            color="bg-red-600"
            maxWidth="md"
            onClose="cancelarCancelar"
            submit="ejecutarCancelar"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded p-3 text-sm">
                        <p class="text-red-800 dark:text-red-200">
                            {{ __('Al cancelar el pedido se anulan todos sus pagos activos (con contraasiento de caja) y se revierte el stock descontado. Esta acción no se puede deshacer.') }}
                        </p>
                        @if($cancelarPedidoInfo['tiene_pagos_activos'] ?? false)
                            <p class="text-red-800 dark:text-red-200 mt-2 font-semibold">
                                ⚠ {{ __('Este pedido tiene pagos cobrados. Se generarán contraasientos de caja.') }}
                            </p>
                        @endif
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <div><strong>{{ __('Pedido') }}:</strong>
                            @if($cancelarPedidoInfo['numero'] ?? null) #{{ $cancelarPedidoInfo['numero'] }} @endif
                            @if($cancelarPedidoInfo['identificador'] ?? null) ({{ $cancelarPedidoInfo['identificador'] }}) @endif
                        </div>
                        <div><strong>{{ __('Cliente') }}:</strong> {{ $cancelarPedidoInfo['cliente'] ?? '—' }}</div>
                        <div><strong>{{ __('Total') }}:</strong> ${{ number_format($cancelarPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }} *</label>
                        <textarea wire:model="motivoCancelacion" rows="3" required minlength="5"
                            placeholder="{{ __('Ej: cliente no se presentó, error de carga, etc.') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring focus:ring-red-500 focus:ring-opacity-50 text-sm"></textarea>
                        @error('motivoCancelacion')
                            <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm">
                    {{ __('Cancelar pedido') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: COBRAR PENDIENTE ==================== --}}
    @if($showCobrarModal)
        <x-bcn-modal
            :title="__('Cobrar pedido') . ' ' . (($cobrarPedidoInfo['numero'] ?? null) ? '#' . $cobrarPedidoInfo['numero'] : '')"
            color="bg-green-600"
            maxWidth="lg"
            onClose="cerrarCobrar"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="bg-gray-100 dark:bg-gray-700 rounded p-3">
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Total') }}</div>
                            <div class="text-sm font-bold text-bcn-secondary dark:text-white">${{ number_format($cobrarPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded p-3">
                            <div class="text-xs text-green-700 dark:text-green-300">{{ __('Cobrado') }}</div>
                            <div class="text-sm font-bold text-green-800 dark:text-green-200">${{ number_format($cobrarPedidoInfo['total_cobrado'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded p-3">
                            <div class="text-xs text-blue-700 dark:text-blue-300">{{ __('Planificado') }}</div>
                            <div class="text-sm font-bold text-blue-800 dark:text-blue-200">${{ number_format($cobrarPedidoInfo['total_planificado'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded p-3">
                            <div class="text-xs text-yellow-700 dark:text-yellow-300">{{ __('Pendiente') }}</div>
                            <div class="text-sm font-bold text-yellow-800 dark:text-yellow-200">${{ number_format($cobrarPedidoInfo['pendiente'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                    </div>

                    @if(empty($cobrarPagosPlanificados))
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded p-4 text-sm text-blue-800 dark:text-blue-200">
                            {{ __('Este pedido no tiene pagos planificados. Para agregar nuevos pagos, abrí el pedido desde "Nuevo Pedido" (disponible en próxima entrega).') }}
                        </div>
                    @else
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 text-sm">{{ __('Pagos planificados') }}</div>
                            <div class="space-y-2">
                                @foreach($cobrarPagosPlanificados as $pago)
                                    <div class="flex justify-between items-center bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded p-3">
                                        <div class="flex-1">
                                            <div class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ $pago['forma_pago'] }}</div>
                                            <div class="text-xs text-blue-700 dark:text-blue-300">
                                                ${{ number_format($pago['monto_final'], 2, ',', '.') }}
                                                @if($pago['cuotas']) — {{ $pago['cuotas'] }} {{ __('cuotas') }} @endif
                                                @if($pago['referencia']) — {{ $pago['referencia'] }} @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="confirmarPagoPlanificado({{ $pago['id'] }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700">
                                                {{ __('Cobrar') }}
                                            </button>
                                            <button wire:click="eliminarPagoPlanificado({{ $pago['id'] }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center px-2 py-1.5 border border-red-300 dark:border-red-600 text-xs text-red-700 dark:text-red-300 rounded hover:bg-red-50 dark:hover:bg-red-900/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:w-auto sm:text-sm">
                    {{ __('Listo') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CONVERTIR EN VENTA ==================== --}}
    @if($showConvertirModal)
        <x-bcn-modal
            :title="__('Convertir pedido en venta')"
            color="bg-bcn-primary"
            maxWidth="md"
            onClose="cancelarConvertir"
            submit="ejecutarConvertir"
        >
            <x-slot:body>
                <div class="space-y-3">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <div><strong>{{ __('Pedido') }}:</strong>
                            @if($convertirPedidoInfo['numero'] ?? null) #{{ $convertirPedidoInfo['numero'] }} @endif
                            @if($convertirPedidoInfo['identificador'] ?? null) ({{ $convertirPedidoInfo['identificador'] }}) @endif
                        </div>
                        <div><strong>{{ __('Cliente') }}:</strong> {{ $convertirPedidoInfo['cliente'] ?? '—' }}</div>
                        <div><strong>{{ __('Total') }}:</strong> ${{ number_format($convertirPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                        <div><strong>{{ __('Cobrado') }}:</strong> ${{ number_format($convertirPedidoInfo['total_cobrado'] ?? 0, 2, ',', '.') }}</div>
                        @if(($convertirPedidoInfo['total_planificado'] ?? 0) > 0)
                            <div><strong>{{ __('Planificado') }}:</strong> ${{ number_format($convertirPedidoInfo['total_planificado'], 2, ',', '.') }}</div>
                        @endif
                    </div>

                    @if(($convertirPedidoInfo['pendiente'] ?? 0) > 0.005)
                        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded p-3 text-sm">
                            <p class="text-red-800 dark:text-red-200 font-semibold">
                                ⚠ {{ __('Faltan') }} ${{ number_format($convertirPedidoInfo['pendiente'], 2, ',', '.') }} {{ __('sin cubrir') }}
                            </p>
                            <p class="text-red-700 dark:text-red-300 mt-1 text-xs">
                                {{ __('Cargá pagos planificados o cobrados antes de convertir, o agregá un pago con forma "cuenta corriente".') }}
                            </p>
                        </div>
                    @elseif($convertirPedidoInfo['tiene_pagos_planificados'] ?? false)
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded p-3 text-sm text-blue-800 dark:text-blue-200">
                            {{ __('Los pagos planificados se materializarán al convertir (se generarán MovimientoCaja por cada uno).') }}
                        </div>
                    @endif

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Al convertir se crea la venta, se migran los pagos, se reasignan los movimientos de stock y caja. El pedido queda en estado "Facturado".') }}
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    @disabled(($convertirPedidoInfo['pendiente'] ?? 0) > 0.005)
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto sm:text-sm">
                    {{ __('Convertir en venta') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
