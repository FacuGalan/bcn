<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Monedas') }}</h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Gestiona las monedas y cotizaciones del sistema') }}</p>
                </div>
            </div>
        </div>

        <!-- ==================== SECCIÓN MONEDAS ==================== -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="px-4 py-4 sm:px-6 sm:py-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('Monedas del Sistema') }}
                    </h3>
                    @if(!$mostrarFormMoneda)
                        <button type="button" wire:click="nuevaMoneda"
                            class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Nueva Moneda') }}
                        </button>
                    @endif
                </div>

                {{-- Formulario nueva/editar moneda --}}
                @if($mostrarFormMoneda)
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                            {{ $monedaEditId ? __('Editar Moneda') : __('Nueva Moneda') }}
                        </h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Código') }} *</label>
                                <input type="text" wire:model="monedaCodigo" maxlength="3"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm uppercase"
                                    placeholder="ARS">
                                @error('monedaCodigo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Símbolo') }} *</label>
                                <input type="text" wire:model="monedaSimbolo" maxlength="5"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    placeholder="$">
                                @error('monedaSimbolo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                                <input type="text" wire:model="monedaNombre" maxlength="50"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    placeholder="{{ __('Peso Argentino') }}">
                                @error('monedaNombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Decimales') }}</label>
                                <input type="number" wire:model="monedaDecimales" min="0" max="4"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Orden') }}</label>
                                <input type="number" wire:model="monedaOrden" min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            </div>
                            <div class="col-span-2 flex items-end gap-4">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="monedaActivo"
                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Activa') }}</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="monedaEsPrincipal"
                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Principal') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" wire:click="cancelarFormMoneda"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                                {{ __('Cancelar') }}
                            </button>
                            <button type="button" wire:click="guardarMoneda"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm text-white bg-bcn-primary hover:bg-opacity-90 transition">
                                {{ $monedaEditId ? __('Actualizar') : __('Crear') }}
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Listado de monedas --}}
                <div class="space-y-2">
                    @foreach($monedas as $moneda)
                        <div class="flex items-center justify-between p-3 rounded-lg border {{ $moneda->activo ? 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700/50' : 'border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 opacity-60' }}">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $moneda->es_principal ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-gray-100 dark:bg-gray-600' }}">
                                    <span class="text-sm font-bold {{ $moneda->es_principal ? 'text-yellow-700 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $moneda->simbolo }}</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $moneda->nombre }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300">{{ $moneda->codigo }}</span>
                                        @if($moneda->es_principal)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                {{ __('Principal') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $moneda->decimales }} {{ __('decimales') }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(!$moneda->es_principal)
                                    <button wire:click="marcarPrincipal({{ $moneda->id }})"
                                        class="inline-flex items-center p-1.5 text-gray-400 dark:text-gray-500 hover:text-yellow-600 dark:hover:text-yellow-400 transition"
                                        title="{{ __('Marcar como principal') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>
                                    </button>
                                @endif
                                <button wire:click="editMoneda({{ $moneda->id }})"
                                    class="inline-flex items-center p-1.5 text-gray-400 dark:text-gray-500 hover:text-bcn-primary dark:hover:text-bcn-primary transition"
                                    title="{{ __('Editar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="toggleMoneda({{ $moneda->id }})"
                                    class="relative inline-flex flex-shrink-0 h-5 w-9 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none {{ $moneda->activo ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                    title="{{ $moneda->activo ? __('Desactivar') : __('Activar') }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $moneda->activo ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    @if($monedas->isEmpty())
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p class="text-sm">{{ __('No hay monedas configuradas') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ==================== SECCIÓN TIPOS DE CAMBIO ==================== -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-center">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                    {{ __('Tipos de Cambio') }}
                </h3>
                <div class="flex gap-2">
                    <!-- Mobile -->
                    <button
                        wire:click="crearTC"
                        class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        title="{{ __('Nueva cotización') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                    <!-- Desktop -->
                    <button
                        wire:click="crearTC"
                        class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Nueva Cotización') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Cotizaciones Vigentes -->
        @if($vigentes->count() > 0)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ __('Cotizaciones Vigentes') }}</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($vigentes as $v)
                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $v->monedaOrigen->codigo }} / {{ $v->monedaDestino->codigo }}
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $v->fecha->format('d/m/Y') }}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Compra') }}: <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($v->tasa_compra, 2) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Venta') }}: <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($v->tasa_venta, 2) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="filtroMonedaOrigen" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda que se cotiza') }}</label>
                    <select
                        id="filtroMonedaOrigen"
                        wire:model.live="filtroMonedaOrigen"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($monedasActivas as $m)
                            <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtroMonedaDestino" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Se expresa en') }}</label>
                    <select
                        id="filtroMonedaDestino"
                        wire:model.live="filtroMonedaDestino"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                    >
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($monedasActivas as $m)
                            <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Mobile Cards -->
        <div class="sm:hidden space-y-3">
            @forelse($tiposCambio as $tc)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ $tc->monedaOrigen->codigo }} / {{ $tc->monedaDestino->codigo }}
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $tc->fecha->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex gap-1">
                        <button wire:click="editarTC({{ $tc->id }})" class="px-2 py-1 border border-bcn-primary text-bcn-primary hover:bg-bcn-primary hover:text-white rounded text-xs transition">
                            {{ __('Editar') }}
                        </button>
                        <button wire:click="eliminarTC({{ $tc->id }})" wire:confirm="{{ __('¿Eliminar esta cotización?') }}" class="px-2 py-1 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded text-xs transition">
                            {{ __('Eliminar') }}
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Compra') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">{{ number_format($tc->tasa_compra, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Venta') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">{{ number_format($tc->tasa_venta, 2) }}</span>
                    </div>
                </div>
                @if($tc->usuario)
                <div class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ $tc->usuario->name }}</div>
                @endif
            </div>
            @empty
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-center text-gray-500 dark:text-gray-400">
                {{ __('No hay tipos de cambio registrados') }}
            </div>
            @endforelse
            <div class="mt-4">
                {{ $tiposCambio->links() }}
            </div>
        </div>

        <!-- Desktop Table -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Par') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Compra') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Venta') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Usuario') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tiposCambio as $tc)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $tc->fecha->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ $tc->monedaOrigen->codigo }} / {{ $tc->monedaDestino->codigo }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format($tc->tasa_compra, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format($tc->tasa_venta, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $tc->usuario?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <button wire:click="editarTC({{ $tc->id }})" class="px-3 py-2 border border-bcn-primary text-bcn-primary hover:bg-bcn-primary hover:text-white rounded-md text-xs font-medium transition">
                                    {{ __('Editar') }}
                                </button>
                                <button wire:click="eliminarTC({{ $tc->id }})" wire:confirm="{{ __('¿Eliminar esta cotización?') }}" class="px-3 py-2 border border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-md text-xs font-medium transition">
                                    {{ __('Eliminar') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ __('No hay tipos de cambio registrados') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $tiposCambio->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Tipo de Cambio -->
    @if($mostrarModalTC)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalTC"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full w-full">
                <!-- Header -->
                <div class="{{ $modoEdicionTC ? 'bg-bcn-primary' : 'bg-green-600' }} px-4 py-3 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        {{ $modoEdicionTC ? __('Editar Tipo de Cambio') : __('Nueva Cotización') }}
                    </h3>
                </div>

                <form wire:submit="guardarTC">
                    <div class="px-4 py-5 sm:p-6 space-y-4">
                        <!-- Par de monedas -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="moneda_origen_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Moneda que se cotiza') }}</label>
                                <select
                                    id="moneda_origen_id"
                                    wire:model.live="moneda_origen_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                >
                                    <option value="">{{ __('Seleccionar') }}</option>
                                    @foreach($monedasActivas as $m)
                                        <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ej: USD, EUR') }}</p>
                                @error('moneda_origen_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="moneda_destino_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Se expresa en') }}</label>
                                <select
                                    id="moneda_destino_id"
                                    wire:model.live="moneda_destino_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                >
                                    <option value="">{{ __('Seleccionar') }}</option>
                                    @foreach($monedasActivas as $m)
                                        <option value="{{ $m->id }}">{{ $m->codigo }} - {{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Ej: ARS, BRL') }}</p>
                                @error('moneda_destino_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Texto de ayuda -->
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Ej: si 1 dólar vale 1.400 pesos, seleccione USD como moneda a cotizar y ARS como moneda de expresión') }}
                        </p>

                        <!-- Tasas -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tasa_compra" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tasa compra') }}</label>
                                <input
                                    type="number"
                                    id="tasa_compra"
                                    wire:model.live.debounce.500ms="tasa_compra"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    placeholder="0.00"
                                />
                                @error('tasa_compra') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="tasa_venta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tasa venta') }}</label>
                                <input
                                    type="number"
                                    id="tasa_venta"
                                    wire:model.live.debounce.500ms="tasa_venta"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                                    placeholder="0.00"
                                />
                                @error('tasa_venta') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Preview dinámico (debajo de las tasas) -->
                        @if($moneda_origen_id && $moneda_destino_id && $tasa_venta)
                            @php
                                $mOrigen = $monedasActivas->firstWhere('id', (int)$moneda_origen_id);
                                $mDestino = $monedasActivas->firstWhere('id', (int)$moneda_destino_id);
                            @endphp
                            @if($mOrigen && $mDestino)
                            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-200 text-center">
                                    1 {{ $mOrigen->codigo }} = {{ number_format((float)$tasa_venta, 2, ',', '.') }} {{ $mDestino->codigo }}
                                </p>
                            </div>
                            @endif
                        @endif

                        <!-- Fecha -->
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha') }}</label>
                            <input
                                type="date"
                                id="fecha"
                                wire:model="fecha"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                            />
                            @error('fecha') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm"
                        >
                            {{ $modoEdicionTC ? __('Actualizar') : __('Guardar') }}
                        </button>
                        <button
                            type="button"
                            wire:click="cerrarModalTC"
                            class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
