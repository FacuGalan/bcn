<div class="p-4 sm:p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Pagos pendientes de facturar') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Pagos cuya emisión de factura falló y pueden reintentarse') }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha desde') }}</label>
                <input type="date" wire:model.live="filtroFechaDesde" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha hasta') }}</label>
                <input type="date" wire:model.live="filtroFechaHasta" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de pago') }}</label>
                <select wire:model.live="filtroFormaPagoId" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($formasPagoDisponibles as $fp)
                        <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                <select wire:model.live="filtroEstado" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
                    <option value="pendiente_de_facturar">{{ __('Pendientes de facturar') }}</option>
                    <option value="error_arca">{{ __('Con error ARCA') }}</option>
                    <option value="todos">{{ __('Todos') }}</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end mt-3">
            <button type="button" wire:click="limpiarFiltros" class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                {{ __('Limpiar filtros') }}
            </button>
        </div>
    </div>

    {{-- Cards móvil --}}
    <div class="sm:hidden space-y-3">
        @forelse($pagos as $pago)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white text-sm">{{ __('Venta') }} #{{ $pago->venta->numero ?? '-' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $pago->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($pago->estado_facturacion === 'pendiente_de_facturar')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                            {{ __('Pendiente') }}
                        </span>
                    @elseif($pago->estado_facturacion === 'error_arca')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                            {{ __('Error ARCA') }}
                        </span>
                    @endif
                </div>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1 mb-3">
                    <p><span class="font-medium">{{ __('Cliente') }}:</span> {{ $pago->venta?->cliente?->nombre ?? '-' }}</p>
                    <p><span class="font-medium">{{ __('Forma de pago') }}:</span> {{ $pago->formaPago?->nombre ?? '-' }}</p>
                    <p><span class="font-medium">{{ __('Monto') }}:</span> ${{ number_format($pago->monto_final, 2, ',', '.') }}</p>
                </div>
                <div class="flex gap-2">
                    @if($pago->estado_facturacion === 'pendiente_de_facturar' && auth()->user()?->hasPermissionTo('func.reintentar_facturacion'))
                        <button type="button" wire:click="abrirReintentar({{ $pago->id }})"
                            class="flex-1 px-2 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            {{ __('Reintentar') }}
                        </button>
                        <button type="button" wire:click="abrirMarcarError({{ $pago->id }})"
                            class="flex-1 px-2 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                            {{ __('Marcar error') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                {{ __('No hay pagos pendientes con estos filtros') }}
            </div>
        @endforelse
    </div>

    {{-- Tabla desktop --}}
    <div class="hidden sm:block bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Venta') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cliente') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Forma de pago') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Monto') }}</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($pagos as $pago)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $pago->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white whitespace-nowrap">#{{ $pago->venta->numero ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $pago->venta?->cliente?->nombre ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $pago->formaPago?->nombre ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-right whitespace-nowrap text-gray-900 dark:text-white">${{ number_format($pago->monto_final, 2, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($pago->estado_facturacion === 'pendiente_de_facturar')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        {{ __('Pendiente') }}
                                    </span>
                                @elseif($pago->estado_facturacion === 'error_arca')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        {{ __('Error ARCA') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if($pago->estado_facturacion === 'pendiente_de_facturar' && auth()->user()?->hasPermissionTo('func.reintentar_facturacion'))
                                    <button type="button" wire:click="abrirReintentar({{ $pago->id }})"
                                        title="{{ __('Reintentar facturación') }}"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md mr-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="abrirMarcarError({{ $pago->id }})"
                                        title="{{ __('Marcar como error') }}"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No hay pagos pendientes con estos filtros') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $pagos->links() }}
    </div>

    {{-- Modal Reintentar --}}
    <x-bcn-modal wire:model="showReintentarModal" title="{{ __('Reintentar facturación') }}" color="bg-blue-600" maxWidth="md">
        <x-slot:body>
            <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('Se intentará emitir la factura nuevamente ante ARCA. Si vuelve a fallar, el pago quedará marcado como error.') }}
            </p>
        </x-slot:body>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cerrarReintentarModal"
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="confirmarReintentar" wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmarReintentar">{{ __('Reintentar') }}</span>
                    <span wire:loading wire:target="confirmarReintentar">{{ __('Procesando...') }}</span>
                </button>
            </div>
        </x-slot:footer>
    </x-bcn-modal>

    {{-- Modal Marcar error --}}
    <x-bcn-modal wire:model="showMarcarErrorModal" title="{{ __('Marcar como error ARCA') }}" color="bg-red-600" maxWidth="md">
        <x-slot:body>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                {{ __('El pago quedará marcado como error y no podrá reintentarse automáticamente. Ingresá el motivo.') }}
            </p>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }} <span class="text-red-500">*</span></label>
            <textarea wire:model.live="motivoMarcarError" rows="3" minlength="10"
                placeholder="{{ __('Ej: ARCA rechaza por CUIT inválido, a resolver manualmente') }}"
                class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm"></textarea>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Mínimo 10 caracteres') }}</p>
        </x-slot:body>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cerrarMarcarErrorModal"
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="confirmarMarcarError" wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md disabled:opacity-50">
                    {{ __('Marcar error') }}
                </button>
            </div>
        </x-slot:footer>
    </x-bcn-modal>
</div>
