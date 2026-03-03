<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Movimientos de Cuenta') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Historial de movimientos por cuenta') }}</p>
                </div>
            </div>
        </div>

        {{-- Selector de cuenta + saldo + botón nuevo movimiento --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuenta') }}</label>
                        <select wire:model.live="cuentaSeleccionada"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Seleccionar cuenta...') }}</option>
                            @foreach($this->cuentas as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->nombre_completo }} ({{ $cuenta->moneda?->codigo ?? 'ARS' }})</option>
                            @endforeach
                        </select>
                    </div>

                    @if($cuentaActual)
                    <div class="flex-shrink-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo actual') }}</p>
                        <p class="text-xl font-bold {{ $cuentaActual->saldo_actual >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                            {{ $cuentaActual->moneda?->simbolo ?? '$' }} {{ number_format($cuentaActual->saldo_actual, 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        {{-- Mobile: solo icono --}}
                        <button wire:click="abrirNuevoMovimiento"
                            class="sm:hidden inline-flex items-center justify-center w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                            title="{{ __('Nuevo Movimiento') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                        {{-- Desktop: con texto --}}
                        <button wire:click="abrirNuevoMovimiento"
                            class="hidden sm:inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            {{ __('Nuevo Movimiento') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($cuentaSeleccionada)
        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div>
                        <select wire:model.live="filtroTipo"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todos los tipos') }}</option>
                            <option value="ingreso">{{ __('Ingresos') }}</option>
                            <option value="egreso">{{ __('Egresos') }}</option>
                        </select>
                    </div>
                    <div>
                        <select wire:model.live="filtroConcepto"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todos los conceptos') }}</option>
                            @foreach($this->conceptos as $concepto)
                            <option value="{{ $concepto->id }}">{{ $concepto->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <select wire:model.live="filtroEstado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Todos los estados') }}</option>
                            <option value="activo">{{ __('Solo activos') }}</option>
                            <option value="anulado">{{ __('Solo anulados') }}</option>
                        </select>
                    </div>
                    <div>
                        <input wire:model.live="fechaDesde" type="date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    </div>
                    <div>
                        <input wire:model.live="fechaHasta" type="date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                    </div>
                </div>
            </div>
        </div>

        {{-- Vista Móvil - Tarjetas --}}
        <div class="sm:hidden space-y-3">
            @forelse($movimientos as $mov)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border {{ $mov->esAnulado() ? 'border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-900/10' : ($mov->movimientoAnulado ? 'border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-900/10' : 'border-gray-200 dark:border-gray-700') }} p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <div class="text-sm font-medium {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }}">{{ $mov->concepto_descripcion }}</div>
                            @if($mov->observaciones)
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $mov->observaciones }}</div>
                            @endif
                            {{-- Referencia cruzada anulación --}}
                            @if($mov->esAnulado() && $mov->movimientoAnulacion)
                                <div class="text-xs text-red-500 dark:text-red-400 mt-1">
                                    <svg class="inline w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                    {{ __('Anulado por mov.') }} #{{ $mov->movimientoAnulacion->id }}
                                </div>
                            @elseif($mov->movimientoAnulado)
                                <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    <svg class="inline w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                    {{ __('Contraasiento de mov.') }} #{{ $mov->movimientoAnulado->id }}
                                </div>
                            @endif
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                                @if($mov->usuario?->name)
                                    &middot; {{ $mov->usuario->name }}
                                @endif
                            </div>
                        </div>
                        <div class="text-right ml-3">
                            <p class="text-sm font-bold {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : ($mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">${{ number_format($mov->saldo_posterior, 2, ',', '.') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                {{ $mov->tipo === 'ingreso' ? __('Ingreso') : __('Egreso') }}
                            </span>
                            @if($mov->origen_tipo)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                {{ __($mov->origen_tipo) }}
                            </span>
                            @endif
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
                        @if($mov->esActivo() && !$mov->movimientoAnulado && $mov->origen_tipo === 'Manual')
                        <button wire:click="confirmarAnular({{ $mov->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white transition-colors duration-150">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                        </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron movimientos') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Vista Desktop - Tabla --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Concepto') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Origen') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Monto') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Saldo') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Usuario') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($movimientos as $mov)
                        <tr class="{{ $mov->esAnulado() ? 'bg-red-50/50 dark:bg-red-900/10' : ($mov->movimientoAnulado ? 'bg-amber-50/50 dark:bg-amber-900/10' : '') }} hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="{{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white' }}">{{ $mov->concepto_descripcion }}</div>
                                @if($mov->observaciones)
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $mov->observaciones }}</div>
                                @endif
                                {{-- Referencia cruzada --}}
                                @if($mov->esAnulado() && $mov->movimientoAnulacion)
                                <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                    <svg class="inline w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                    {{ __('Anulado por mov.') }} #{{ $mov->movimientoAnulacion->id }}
                                </div>
                                @elseif($mov->movimientoAnulado)
                                <div class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                    <svg class="inline w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                    {{ __('Contraasiento de mov.') }} #{{ $mov->movimientoAnulado->id }}
                                </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mov->tipo === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                    {{ $mov->tipo === 'ingreso' ? __('Ingreso') : __('Egreso') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $mov->origen_tipo ? __($mov->origen_tipo) : '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-medium whitespace-nowrap {{ $mov->esAnulado() ? 'line-through text-gray-400 dark:text-gray-500' : ($mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">
                                ${{ number_format($mov->saldo_posterior, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $mov->usuario?->name ?? '-' }}
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    {{ __('Activo') }}
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($mov->esActivo() && !$mov->movimientoAnulado && $mov->origen_tipo === 'Manual')
                                <button wire:click="confirmarAnular({{ $mov->id }})" class="inline-flex items-center justify-center px-3 py-2 border border-red-600 text-sm font-medium rounded-md text-red-600 hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 transition-colors duration-150">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    {{ __('Anular') }}
                                </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                                <p class="mt-2">{{ __('No se encontraron movimientos') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($movimientos instanceof \Illuminate\Pagination\LengthAwarePaginator && $movimientos->hasPages())
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $movimientos->links() }}
            </div>
            @endif
        </div>
        @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('Seleccione una cuenta') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Elija una cuenta del selector para ver sus movimientos') }}</p>
        </div>
        @endif

        {{-- Modal nuevo movimiento --}}
        @if($showNuevoMovimiento)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showNuevoMovimiento', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="guardarMovimiento">
                        <div class="bg-green-600 px-4 py-4 sm:px-6">
                            <h3 class="text-lg font-semibold text-white">{{ __('Nuevo Movimiento Manual') }}</h3>
                        </div>

                        <div class="px-4 py-5 sm:p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tipo') }} *</label>
                                <select wire:model.live="nuevoTipo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                    <option value="ingreso">{{ __('Ingreso') }}</option>
                                    <option value="egreso">{{ __('Egreso') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Monto') }} *</label>
                                <input wire:model="nuevoMonto" type="number" step="0.01" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                @error('nuevoMonto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Concepto') }}</label>
                                <select wire:model="nuevoConceptoId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                    <option value="">{{ __('Sin concepto predefinido') }}</option>
                                    @foreach($this->conceptosManuales as $concepto)
                                    <option value="{{ $concepto->id }}">{{ $concepto->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción') }} *</label>
                                <input wire:model="nuevoDescripcion" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                @error('nuevoDescripcion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Observaciones') }}</label>
                                <textarea wire:model="nuevoObservaciones" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"></textarea>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 sm:ml-3 sm:w-auto sm:text-sm transition">
                                {{ __('Registrar') }}
                            </button>
                            <button type="button" wire:click="$set('showNuevoMovimiento', false)"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal anular movimiento --}}
        @if($showAnularModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showAnularModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="anularMovimiento">
                        <div class="bg-red-600 px-4 py-4 sm:px-6">
                            <h3 class="text-lg font-semibold text-white">{{ __('Anular Movimiento') }}</h3>
                        </div>

                        <div class="px-4 py-5 sm:p-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Se creará un contraasiento para revertir este movimiento. Esta acción no se puede deshacer.') }}</p>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Motivo de anulación') }} *</label>
                                <input wire:model="motivoAnulacion" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                @error('motivoAnulacion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 sm:ml-3 sm:w-auto sm:text-sm transition">
                                {{ __('Anular') }}
                            </button>
                            <button type="button" wire:click="$set('showAnularModal', false)"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
