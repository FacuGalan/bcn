{{--
    Vista Livewire: Compras

    DESCRIPCIÓN:
    ===========
    Vista principal del módulo de compras. Permite listar compras existentes,
    crear nuevas compras mediante un sistema de carrito, registrar pagos
    a proveedores en cuenta corriente, y gestionar el flujo completo de compras.

    SECCIONES:
    ==========
    1. Barra de búsqueda y botón "Nueva Compra"
    2. Filtros (estado, forma de pago, fechas)
    3. Tabla de compras con paginación
    4. Modal para crear nueva compra
    5. Modal de detalles de compra
    6. Modal para registrar pagos

    FASE 4 - Sistema Multi-Sucursal (Vistas Livewire)
--}}

<div class="py-6">
    {{-- Header --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Compras') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ __('Gestión de compras y proveedores') }}</p>
            </div>
            <button
                wire:click="abrirCompraModal"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-green-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                {{ __('Nueva Compra') }}
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                <div class="flex-1">
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 sm:text-sm"
                        :placeholder="__('Buscar por número de comprobante o proveedor...')">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4" x-show="@js($showFilters) || window.innerWidth >= 640" x-data>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                    <select wire:model.live="filterEstado" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                        <option value="all">{{ __('Todos') }}</option>
                        <option value="completada">{{ __('Completada') }}</option>
                        <option value="pendiente">{{ __('Pendiente') }}</option>
                        <option value="cancelada">{{ __('Cancelada') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de Pago') }}</label>
                    <select wire:model.live="filterFormaPago" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                        <option value="all">{{ __('Todas') }}</option>
                        <option value="efectivo">{{ __('Efectivo') }}</option>
                        <option value="debito">{{ __('Débito') }}</option>
                        <option value="credito">{{ __('Crédito') }}</option>
                        <option value="cta_cte">{{ __('Cuenta Corriente') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                    <input wire:model.live="filterFechaDesde" type="date" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                    <input wire:model.live="filterFechaHasta" type="date" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de compras --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Comprobante') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Proveedor') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Forma Pago') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($compras as $compra)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $compra->numero_comprobante }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $compra->proveedor->nombre }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $compra->fecha->format('d/m/Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $compra->forma_pago === 'efectivo' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $compra->forma_pago === 'cta_cte' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $compra->forma_pago)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">$@precio($compra->total)</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $compra->estado === 'completada' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $compra->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $compra->estado === 'cancelada' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($compra->estado) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="verDetalle({{ $compra->id }})" class="text-green-600 hover:text-green-900 mr-3">{{ __('Ver') }}</button>
                                @if($compra->estado === 'pendiente' && $compra->forma_pago === 'cta_cte')
                                    <button wire:click="abrirModalPago({{ $compra->id }})" class="text-blue-600 hover:text-blue-900 mr-3">{{ __('Pagar') }}</button>
                                @endif
                                @if($compra->estado !== 'cancelada')
                                    <button wire:click="cancelarCompra({{ $compra->id }})" wire:confirm="{{ __('¿Está seguro de cancelar esta compra?') }}" class="text-red-600 hover:text-red-900">{{ __('Cancelar') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                <p class="text-gray-900 dark:text-white font-medium mb-1">{{ __('No hay compras registradas') }}</p>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('Comienza creando tu primera compra') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($compras->hasPages())
                <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">{{ $compras->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Modal Nueva Compra --}}
    @if($showCompraModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="cancelarCompraModal"></div>

                <div class="inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-green-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-white">{{ __('Nueva Compra') }}</h3>
                            <button wire:click="cancelarCompraModal" class="text-white hover:text-gray-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Buscar Artículo') }}</label>
                                    <input wire:model.live.debounce.300ms="buscarArticulo" type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md" :placeholder="__('Buscar por código o nombre...')">
                                </div>

                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-2 border-b dark:border-gray-600"><h4 class="text-sm font-medium dark:text-white">{{ __('Carrito') }} ({{ count($carrito) }} items)</h4></div>

                                    @if(empty($carrito))
                                        <div class="p-8 text-center text-gray-500 dark:text-gray-400"><p class="text-sm">{{ __('El carrito está vacío') }}</p></div>
                                    @else
                                        <div class="max-h-96 overflow-y-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Artículo') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Cant.') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Precio s/IVA') }}</th>
                                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Subtotal') }}</th>
                                                        <th class="px-3 py-2"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($carrito as $index => $item)
                                                        <tr>
                                                            <td class="px-3 py-3"><div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['articulo']->nombre }}</div></td>
                                                            <td class="px-3 py-3 text-right"><input wire:model.blur="carrito.{{ $index }}.cantidad" wire:change="actualizarCantidad({{ $index }}, $event.target.value)" type="number" step="0.01" class="w-20 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-right"></td>
                                                            <td class="px-3 py-3 text-right"><input wire:model.blur="carrito.{{ $index }}.precio_sin_iva" wire:change="actualizarPrecio({{ $index }}, $event.target.value)" type="number" step="0.01" class="w-24 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded text-right"></td>
                                                            <td class="px-3 py-3 text-right text-sm font-medium dark:text-white">$@precio($item['subtotal'])</td>
                                                            <td class="px-3 py-3 text-right"><button wire:click="eliminarDelCarrito({{ $index }})" class="text-red-600 hover:text-red-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Proveedor') }} <span class="text-red-500">*</span></label>
                                    <select wire:model="proveedorSeleccionado" class="block w-full pl-3 pr-10 py-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                        <option value="">{{ __('Seleccione un proveedor') }}</option>
                                        @foreach($proveedores as $proveedor)
                                            <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de Pago') }}</label>
                                    <select wire:model.live="formaPago" class="block w-full pl-3 pr-10 py-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                        <option value="efectivo">{{ __('Efectivo') }}</option>
                                        <option value="debito">{{ __('Débito') }}</option>
                                        <option value="credito">{{ __('Crédito') }}</option>
                                        <option value="cta_cte">{{ __('Cuenta Corriente') }}</option>
                                    </select>
                                </div>

                                @if($formaPago !== 'cta_cte')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Caja') }} <span class="text-red-500">*</span></label>
                                        <select wire:model="cajaSeleccionada" class="block w-full pl-3 pr-10 py-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                            <option value="">{{ __('Seleccione una caja') }}</option>
                                            @foreach($cajas as $caja)
                                                @if($caja->estaAbierta())
                                                    <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-300">{{ __('Subtotal (sin IVA):') }}</span>
                                        <span class="font-medium dark:text-white">$@precio($subtotal)</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-300">{{ __('IVA (Crédito Fiscal):') }}</span>
                                        <span class="font-medium dark:text-white">$@precio($totalIva)</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold border-t dark:border-gray-700 pt-2">
                                        <span class="dark:text-white">TOTAL:</span>
                                        <span class="text-green-600">$@precio($total)</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <button wire:click="procesarCompra" class="w-full px-4 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-green-600 hover:bg-green-700">{{ __('Procesar Compra') }}</button>
                                    <button wire:click="cancelarCompraModal" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">{{ __('Cancelar') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Detalle Compra --}}
    @if($showDetalleModal && $compraDetalle)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="cerrarDetalle"></div>
                <div class="inline-block w-full max-w-3xl my-8 bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b dark:border-gray-600">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium dark:text-white">{{ __('Detalle de Compra') }} #{{ $compraDetalle->numero_comprobante }}</h3>
                            <button wire:click="cerrarDetalle" class="text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Proveedor') }}</label><p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $compraDetalle->proveedor->nombre }}</p></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Fecha') }}</label><p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $compraDetalle->fecha->format('d/m/Y') }}</p></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Forma de Pago') }}</label><p class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $compraDetalle->forma_pago)) }}</p></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Estado') }}</label><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $compraDetalle->estado === 'completada' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ ucfirst($compraDetalle->estado) }}</span></div>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
                            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">{{ __('Subtotal:') }}</span><span class="font-medium dark:text-white">$@precio($compraDetalle->subtotal)</span></div>
                            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">{{ __('IVA:') }}</span><span class="font-medium dark:text-white">$@precio($compraDetalle->total_iva)</span></div>
                            <div class="flex justify-between text-lg font-bold border-t dark:border-gray-700 pt-2"><span class="dark:text-white">TOTAL:</span><span class="text-green-600">$@precio($compraDetalle->total)</span></div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end">
                        <button wire:click="cerrarDetalle" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">{{ __('Cerrar') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Registrar Pago --}}
    @if($showPagoModal && $compraPago)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="cancelarModalPago"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-blue-600 px-6 py-4"><h3 class="text-lg font-medium text-white">{{ __('Registrar Pago') }}</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Saldo Pendiente') }}</label><p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">$@precio($compraPago->saldo_pendiente)</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Monto a Pagar') }}</label><input wire:model="montoPago" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Caja') }}</label><select wire:model="cajaPago" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"><option value="">{{ __('Seleccione una caja') }}</option>@foreach($cajas as $caja)@if($caja->estaAbierta())<option value="{{ $caja->id }}">{{ $caja->nombre }}</option>@endif @endforeach</select></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="cancelarModalPago" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">{{ __('Cancelar') }}</button>
                        <button wire:click="registrarPago" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">{{ __('Registrar Pago') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
