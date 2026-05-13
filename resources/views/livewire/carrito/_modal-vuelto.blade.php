{{-- Modal de Cobro con Vuelto - Moneda Local (reutilizable) --}}
@if($mostrarModalVuelto)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-vuelto" role="dialog" aria-modal="true"
        x-data="{
            iniciado: false,
            recibido: {{ (float)($pagoConVuelto['monto_recibido'] ?? 0) }},
            totalAPagar: {{ (float)($pagoConVuelto['total_a_pagar'] ?? 0) }},
            get vuelto() {
                return Math.max(0, Math.round((this.recibido - this.totalAPagar) * 100) / 100);
            },
            get falta() {
                return Math.max(0, Math.round((this.totalAPagar - this.recibido) * 100) / 100);
            },
            get esInsuficiente() {
                return this.recibido < this.totalAPagar - 0.01;
            },
            init() {
                this.$nextTick(() => {
                    const input = this.$refs.inputMontoRecibido;
                    if (input) input.focus();
                });
            },
            onKeydown(e) {
                const input = this.$refs.inputMontoRecibido;
                if (!this.iniciado && input && e.key >= '0' && e.key <= '9') {
                    e.preventDefault();
                    this.iniciado = true;
                    this.recibido = parseFloat(e.key);
                    this.$nextTick(() => {
                        input.value = e.key;
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    });
                } else if (e.key !== 'Tab' && e.key !== 'Escape' && e.key !== 'Enter' && e.key !== 'F2') {
                    this.iniciado = true;
                }
            },
            onInput(e) {
                this.recibido = parseFloat(e.target.value) || 0;
                this.iniciado = true;
            },
            confirmar() {
                if (!this.esInsuficiente) {
                    $wire.set('pagoConVuelto.monto_recibido', this.recibido).then(() => {
                        $wire.confirmarPagoConVuelto();
                    });
                }
            }
        }"
        @keydown.escape.window="$wire.cerrarModalVuelto()"
        @keydown.f2.window.prevent="confirmar()"
        @keydown.enter.window.prevent="confirmar()"
        @keydown.window="onKeydown($event)">
        <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarModalVuelto"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full w-full">
                {{-- Header --}}
                <div class="bg-green-600 px-4 py-3 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        {{ __('Cobrar') }} — {{ $pagoConVuelto['nombre'] }}
                    </h3>
                </div>

                <div class="px-4 py-5 sm:p-6 space-y-5">
                    {{-- Total a pagar --}}
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-5 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Total a pagar') }}</p>
                        <p class="text-4xl font-extrabold text-gray-900 dark:text-white">${{ number_format($pagoConVuelto['total_a_pagar'], 2, ',', '.') }}</p>
                    </div>

                    {{-- Monto recibido --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Monto recibido') }}
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-green-600 dark:text-green-400 font-bold text-lg">$</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-ref="inputMontoRecibido"
                                :value="recibido"
                                @input="onInput($event)"
                                class="w-full pl-8 pr-3 py-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50 text-2xl font-bold text-right"
                            >
                        </div>
                    </div>

                    {{-- Vuelto / Falta --}}
                    <div class="rounded-xl p-5 text-center border-2 transition-colors"
                        :class="esInsuficiente
                            ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                            : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'"
                    >
                        <p class="text-xs uppercase tracking-wide mb-2"
                            :class="esInsuficiente ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                            x-text="esInsuficiente ? '{{ __('Falta') }}' : '{{ __('Vuelto') }}'">
                        </p>
                        <p class="text-5xl font-extrabold"
                            :class="esInsuficiente ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                            x-text="'$' + (esInsuficiente ? falta : vuelto).toFixed(2).replace('.', ',')">
                        </p>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                    <button
                        @click="confirmar()"
                        type="button"
                        :disabled="esInsuficiente"
                        class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-5 py-2.5 text-base font-medium text-white sm:text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="!esInsuficiente
                            ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
                            : 'bg-gray-400 cursor-not-allowed'">
                        {{ __('Confirmar Pago') }}
                        <kbd class="ml-2 px-1.5 py-0.5 text-xs rounded" :class="!esInsuficiente ? 'bg-green-800' : 'bg-gray-500'">F2</kbd>
                    </button>
                    <button
                        wire:click="cerrarModalVuelto"
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm">
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
