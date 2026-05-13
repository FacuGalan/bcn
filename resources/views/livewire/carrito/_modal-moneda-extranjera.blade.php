{{-- Modal Simple de Pago en Moneda Extranjera (reutilizable) --}}
@if($mostrarModalMonedaExtranjera)
    @php
        $meEquivPrincipal = (float)($pagoMonedaExtranjera['equivalente_principal'] ?? 0);
        $meTotalVenta = (float)($pagoMonedaExtranjera['total_venta'] ?? 0);
        $meVuelto = (float)($pagoMonedaExtranjera['vuelto'] ?? 0);
        $meEsInsuficiente = $meEquivPrincipal < $meTotalVenta - 0.01;
        $meSinDatos = $meEquivPrincipal <= 0;
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-moneda-ext" role="dialog" aria-modal="true"
        x-data="{
            iniciado: false,
            init() {
                this.$nextTick(() => {
                    const input = this.$refs.inputMontoExtranjera;
                    if (input) { input.focus(); }
                });
            },
            onKeydown(e) {
                const input = this.$refs.inputMontoExtranjera;
                if (!this.iniciado && input && e.key >= '0' && e.key <= '9') {
                    e.preventDefault();
                    this.iniciado = true;
                    $wire.set('pagoMonedaExtranjera.monto_extranjera', e.key);
                    this.$nextTick(() => {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    });
                } else if (e.key >= '0' && e.key <= '9' || e.key === '.' || e.key === ',' || e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Tab') {
                    this.iniciado = true;
                }
            },
            confirmar() {
                const input = this.$refs.inputMontoExtranjera;
                const monto = parseFloat(input ? input.value : 0) || 0;
                const cotizacion = parseFloat($wire.get('pagoMonedaExtranjera.cotizacion') || 0);
                if (monto > 0 && cotizacion > 0) {
                    // Asegurar que el valor del input llegue al servidor antes de confirmar
                    $wire.set('pagoMonedaExtranjera.monto_extranjera', monto).then(() => {
                        $wire.confirmarPagoMonedaExtranjera();
                    });
                }
            }
        }"
        @keydown.escape.window="$wire.cerrarModalMonedaExtranjera()"
        @keydown.f2.window.prevent="confirmar()"
        @keydown.enter.window.prevent="confirmar()">
        <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalMonedaExtranjera"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full w-full">
                {{-- Header --}}
                <div class="bg-amber-600 px-4 py-3 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Pago en') }} {{ $pagoMonedaExtranjera['moneda_codigo'] }} — {{ $pagoMonedaExtranjera['nombre'] }}
                    </h3>
                </div>

                <div class="px-4 py-5 sm:p-6 space-y-5">
                    {{-- Total a pagar --}}
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-5 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Total a pagar') }}</p>
                        <p class="text-4xl font-extrabold text-gray-900 dark:text-white">${{ number_format($pagoMonedaExtranjera['total_venta'], 2, ',', '.') }}</p>
                    </div>

                    {{-- Cotización (informativa) --}}
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Cotización') }}: <span class="font-semibold text-gray-700 dark:text-gray-300">1 {{ $pagoMonedaExtranjera['moneda_codigo'] }} = ${{ number_format((float)($pagoMonedaExtranjera['cotizacion'] ?? 0), 2, ',', '.') }}</span>
                    </p>

                    {{-- Monto en moneda extranjera --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('¿Cuántos') }} {{ $pagoMonedaExtranjera['moneda_codigo'] }} {{ __('entrega?') }}
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-amber-600 dark:text-amber-400 font-bold text-lg">{{ $pagoMonedaExtranjera['moneda_simbolo'] }}</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-ref="inputMontoExtranjera"
                                @keydown="onKeydown($event)"
                                wire:model.live.debounce.200ms="pagoMonedaExtranjera.monto_extranjera"
                                class="w-full pl-10 pr-3 py-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50 text-2xl font-bold text-right"
                                placeholder="0.00">
                        </div>
                    </div>

                    {{-- Equivalente en moneda principal --}}
                    @if(!$meSinDatos)
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-center">
                            <p class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-1">{{ __('Equivale a') }}</p>
                            <p class="text-2xl font-extrabold text-blue-800 dark:text-blue-200">${{ number_format($meEquivPrincipal, 2, ',', '.') }}</p>
                        </div>
                    @endif

                    {{-- Vuelto / Falta --}}
                    @if(!$meSinDatos)
                        <div class="rounded-xl p-5 text-center {{ $meEsInsuficiente ? 'bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800' : 'bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800' }}">
                            <p class="text-xs uppercase tracking-wide mb-2 {{ $meEsInsuficiente ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ $meEsInsuficiente ? __('Falta') : __('Vuelto') }}
                            </p>
                            <p class="text-5xl font-extrabold {{ $meEsInsuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                ${{ number_format($meEsInsuficiente ? ($meTotalVenta - $meEquivPrincipal) : $meVuelto, 2, ',', '.') }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                    <button
                        @click="confirmar()"
                        type="button"
                        @if($meSinDatos || $meEsInsuficiente) disabled @endif
                        class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-5 py-2.5 text-base font-medium text-white sm:text-sm transition
                            {{ !$meSinDatos && !$meEsInsuficiente
                                ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                                : 'bg-gray-400 cursor-not-allowed' }}
                            focus:outline-none focus:ring-2 focus:ring-offset-2">
                        {{ __('Confirmar Pago') }}
                        <kbd class="ml-2 px-1.5 py-0.5 text-xs {{ !$meSinDatos && !$meEsInsuficiente ? 'bg-green-800' : 'bg-gray-500' }} rounded">F2</kbd>
                    </button>
                    <button
                        wire:click="cerrarModalMonedaExtranjera"
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm">
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
