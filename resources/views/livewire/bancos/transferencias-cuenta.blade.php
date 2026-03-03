<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Transferencias entre Cuentas') }}</h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Transfiera fondos entre cuentas propias de la empresa') }}</p>
        </div>

        {{-- Formulario de transferencia --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">{{ __('Nueva Transferencia') }}</h3>
                <form wire:submit="transferir">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Cuenta origen --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuenta Origen') }} *</label>
                            <select wire:model.live="cuentaOrigenId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Seleccionar...') }}</option>
                                @foreach($this->cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}">
                                    {{ $cuenta->nombre_completo }} ({{ $cuenta->moneda?->simbolo ?? '$' }}{{ number_format($cuenta->saldo_actual, 2, ',', '.') }})
                                </option>
                                @endforeach
                            </select>
                            @error('cuentaOrigenId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Cuenta destino --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuenta Destino') }} *</label>
                            <select wire:model="cuentaDestinoId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                {{ !$cuentaOrigenId ? 'disabled' : '' }}>
                                <option value="">{{ __('Seleccionar...') }}</option>
                                @foreach($this->cuentasDestino as $cuenta)
                                <option value="{{ $cuenta->id }}">
                                    {{ $cuenta->nombre_completo }} ({{ $cuenta->moneda?->simbolo ?? '$' }}{{ number_format($cuenta->saldo_actual, 2, ',', '.') }})
                                </option>
                                @endforeach
                            </select>
                            @error('cuentaDestinoId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @if($cuentaOrigenId && $this->cuentasDestino->isEmpty())
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('No hay cuentas destino con la misma moneda') }}</p>
                            @endif
                        </div>

                        {{-- Monto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto') }} *</label>
                            <input wire:model="monto" type="number" step="0.01" min="0.01"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('monto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Concepto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Concepto') }} *</label>
                            <input wire:model="concepto" type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                placeholder="{{ __('Ej: Reposición de fondos') }}">
                            @error('concepto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            {{ __('Transferir') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Historial de transferencias --}}
        {{-- Vista Móvil - Tarjetas --}}
        <div class="sm:hidden space-y-3">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Historial de Transferencias') }}</h3>
            @forelse($transferencias as $transf)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $transf->created_at->format('d/m/Y H:i') }}</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ $transf->cuentaOrigen?->moneda?->simbolo ?? '$' }}{{ number_format($transf->monto, 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $transf->cuentaOrigen?->nombre }}</span>
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $transf->cuentaDestino?->nombre }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $transf->concepto }}</p>
                        @if($transf->usuario?->name)
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $transf->usuario->name }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    <p class="mt-2 text-sm">{{ __('No se han realizado transferencias') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Vista Desktop - Tabla --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Historial de Transferencias') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Origen') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Destino') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Monto') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Concepto') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Usuario') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($transferencias as $transf)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $transf->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $transf->cuentaOrigen?->nombre }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <svg class="w-5 h-5 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $transf->cuentaDestino?->nombre }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $transf->cuentaOrigen?->moneda?->simbolo ?? '$' }}{{ number_format($transf->monto, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $transf->concepto }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $transf->usuario?->name ?? '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                                <p class="mt-2">{{ __('No se han realizado transferencias') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transferencias->hasPages())
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $transferencias->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
