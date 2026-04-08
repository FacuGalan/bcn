<div>
    {{-- Filtros --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Cupón') }}</label>
            <input type="text" wire:model.live.debounce.300ms="filtroHistorialCupon"
                placeholder="{{ __('Código de cupón...') }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Desde') }}</label>
            <input type="date" wire:model.live="filtroHistorialDesde"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Hasta') }}</label>
            <input type="date" wire:model.live="filtroHistorialHasta"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
    </div>

    {{-- Mobile: cards --}}
    <div class="sm:hidden space-y-3">
        @forelse($historialUsos as $uso)
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
            <div class="flex justify-between items-start mb-1">
                <span class="text-sm font-mono font-bold text-gray-900 dark:text-white">{{ $uso->cupon?->codigo }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $uso->fecha->format('d/m/Y H:i') }}</span>
            </div>
            <div class="space-y-0.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Venta') }}</span>
                    <span class="text-gray-900 dark:text-white">#{{ $uso->venta_id }}</span>
                </div>
                @if($uso->cliente)
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Cliente') }}</span>
                    <span class="text-gray-900 dark:text-white">{{ $uso->cliente->nombre }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Descontado') }}</span>
                    <span class="font-bold text-green-600 dark:text-green-400">${{ number_format($uso->monto_descontado, 2) }}</span>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="mt-2 text-sm">{{ __('No hay usos registrados') }}</p>
        </div>
        @endforelse
    </div>

    {{-- Desktop: tabla --}}
    <div class="hidden sm:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cupón') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Venta') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cliente') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sucursal') }}</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Descontado') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($historialUsos as $uso)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $uso->fecha->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2 text-sm font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $uso->cupon?->codigo }}</td>
                    <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">#{{ $uso->venta_id }}</td>
                    <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $uso->cliente?->nombre ?? '-' }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $uso->sucursal?->nombre ?? '-' }}</td>
                    <td class="px-3 py-2 text-sm font-bold text-right text-green-600 dark:text-green-400 whitespace-nowrap">${{ number_format($uso->monto_descontado, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No hay usos registrados') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if($historialUsos->hasPages())
    <div class="mt-3">
        {{ $historialUsos->links() }}
    </div>
    @endif
</div>
