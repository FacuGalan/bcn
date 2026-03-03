<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Resumen de Cuentas') }}</h2>
                        {{-- Botón mobile --}}
                        <div class="sm:hidden">
                            <button wire:click="$set('showConciliacionModal', true)"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Conciliación Bancaria') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Vista general de cuentas bancarias y billeteras digitales') }}</p>
                </div>
                {{-- Botón desktop --}}
                <div class="hidden sm:flex gap-3">
                    <button wire:click="$set('showConciliacionModal', true)"
                        class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ __('Conciliación Bancaria') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Totales por moneda --}}
        @if($totalesPorMoneda->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
            @foreach($totalesPorMoneda as $total)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-3 sm:p-4">
                <div class="flex items-center">
                    <div class="hidden sm:block flex-shrink-0 p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Total') }} {{ $total['moneda']?->codigo ?? 'N/A' }}
                        </p>
                        <p class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">
                            {{ $total['moneda']?->simbolo ?? '$' }} {{ number_format($total['total'], 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-sm text-gray-400 dark:text-gray-500">
                        {{ $total['cantidad_cuentas'] }} {{ __('cuentas') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Tarjetas de cuentas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
            @forelse($cuentas as $cuenta)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $cuenta->tipo === 'banco' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-purple-100 dark:bg-purple-900/30' }}"
                                @if($cuenta->color) style="background-color: {{ $cuenta->color }}20" @endif>
                                @if($cuenta->tipo === 'banco')
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" /></svg>
                                @else
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                @endif
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $cuenta->nombre }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $cuenta->tipo === 'banco' ? $cuenta->banco : ($cuenta->subtipo ? __(App\Models\CuentaEmpresa::SUBTIPOS[$cuenta->subtipo] ?? $cuenta->subtipo) : __('Billetera Digital')) }}
                                </p>
                            </div>
                        </div>
                        @if($cuenta->color)
                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $cuenta->color }}"></span>
                        @endif
                    </div>
                    <div class="mt-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo actual') }}</p>
                        <p class="text-xl font-bold {{ $cuenta->saldo_actual >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                            {{ $cuenta->moneda?->simbolo ?? '$' }} {{ number_format($cuenta->saldo_actual, 2, ',', '.') }}
                        </p>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('bancos.movimientos', ['cuenta' => $cuenta->id]) }}" class="text-xs text-bcn-primary hover:underline">
                        {{ __('Ver movimientos') }} →
                    </a>
                </div>
            </div>
            @empty
            <div class="col-span-full bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" /></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('Sin cuentas configuradas') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Configure cuentas bancarias o billeteras desde') }} <a href="{{ route('bancos.cuentas') }}" class="text-bcn-primary hover:underline">{{ __('Gestión de Cuentas') }}</a></p>
            </div>
            @endforelse
        </div>

        {{-- Últimos movimientos --}}
        @if($ultimosMovimientos->count() > 0)
        {{-- Mobile: tarjetas --}}
        <div class="sm:hidden space-y-3">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Últimos Movimientos') }}</h3>
            @foreach($ultimosMovimientos as $mov)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border {{ $mov->esAnulado() ? 'border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-900/10' : ($mov->movimientoAnulado ? 'border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-900/10' : 'border-gray-200 dark:border-gray-700') }} p-4">
                    <div class="flex items-start justify-between mb-1">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $mov->cuentaEmpresa?->nombre }}</div>
                            <div class="text-xs {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400' }}">{{ $mov->concepto_descripcion }}</div>
                            {{-- Referencia cruzada --}}
                            @if($mov->esAnulado() && $mov->movimientoAnulacion)
                                <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                    {{ __('Anulado por mov.') }} #{{ $mov->movimientoAnulacion->id }}
                                </div>
                            @elseif($mov->movimientoAnulado)
                                <div class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                    {{ __('Contraasiento de mov.') }} #{{ $mov->movimientoAnulado->id }}
                                </div>
                            @endif
                        </div>
                        <div class="text-right ml-3">
                            <p class="text-sm font-bold {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : ($mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex flex-wrap gap-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                {{ $mov->tipo === 'ingreso' ? __('Ingreso') : __('Egreso') }}
                            </span>
                            @if($mov->esAnulado())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                {{ __('Anulado') }}
                            </span>
                            @elseif($mov->movimientoAnulado)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                {{ __('Contraasiento') }}
                            </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $mov->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop: tabla --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Últimos Movimientos') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Cuenta') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Concepto') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Monto') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Saldo') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($ultimosMovimientos as $mov)
                        <tr class="{{ $mov->esAnulado() ? 'bg-red-50/50 dark:bg-red-900/10' : ($mov->movimientoAnulado ? 'bg-amber-50/50 dark:bg-amber-900/10' : '') }} hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $mov->cuentaEmpresa?->nombre }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="{{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400' }}">{{ $mov->concepto_descripcion }}</div>
                                @if($mov->esAnulado() && $mov->movimientoAnulacion)
                                <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                    {{ __('Anulado por mov.') }} #{{ $mov->movimientoAnulacion->id }}
                                </div>
                                @elseif($mov->movimientoAnulado)
                                <div class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                    {{ __('Contraasiento de mov.') }} #{{ $mov->movimientoAnulado->id }}
                                </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($mov->esAnulado())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    {{ __('Anulado') }}
                                </span>
                                @elseif($mov->movimientoAnulado)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                    {{ __('Contraasiento') }}
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                    {{ $mov->tipo === 'ingreso' ? __('Ingreso') : __('Egreso') }}
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-medium whitespace-nowrap {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : ($mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">
                                ${{ number_format($mov->saldo_posterior, 2, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Modal Conciliación Bancaria (placeholder) --}}
        @if($showConciliacionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showConciliacionModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 text-center">
                        <svg class="mx-auto h-16 w-16 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ __('Conciliación Bancaria') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('La funcionalidad de conciliación bancaria estará disponible próximamente. Permitirá importar extractos bancarios y conciliar automáticamente con los movimientos registrados.') }}
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6">
                        <button type="button" wire:click="$set('showConciliacionModal', false)"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 sm:text-sm transition">
                            {{ __('Entendido') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
