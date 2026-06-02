{{-- Modal "Esperando pago" — cobro por QR con integración (Fase 5, compartido NuevaVenta + Pedidos) --}}
@if($mostrarModalEsperandoPago)
    @php($__cobroComercioId = app(\App\Services\TenantService::class)->getComercioId())
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-esperando-pago" role="dialog" aria-modal="true"
        wire:poll.3s="pollearCobroIntegracion"
        data-usa-pantalla-cliente="{{ $this->usaPantallaClienteActiva ? '1' : '0' }}"
        @if($__cobroComercioId && $cobroIntegracionTransaccionId)
            data-cobro-canal="comercios.{{ $__cobroComercioId }}.integraciones-pago.transaccion.{{ $cobroIntegracionTransaccionId }}"
        @endif
        x-data="{
            expira: {{ (int) ($cobroIntegracionExpiraTs ?? 0) }},
            ahora: Math.floor(Date.now() / 1000),
            timer: null,
            cobroCanal: null,
            enPantallaCliente: false,
            get restante() {
                return Math.max(0, this.expira - this.ahora);
            },
            get mm() {
                return String(Math.floor(this.restante / 60)).padStart(2, '0');
            },
            get ss() {
                return String(this.restante % 60).padStart(2, '0');
            },
            get expirado() {
                return this.expira > 0 && this.restante <= 0;
            },
            init() {
                this.timer = setInterval(() => { this.ahora = Math.floor(Date.now() / 1000); }, 1000);

                // Si la caja usa pantalla cliente y hay una ventana conectada,
                // mandamos el QR a ese monitor y mostramos el modal compacto.
                const usa = this.$el.dataset.usaPantallaCliente === '1';
                const host = window.bcnPantallaClienteHost;
                if (usa && host && host.estaConectada()) {
                    this.$nextTick(() => {
                        const svg = this.$refs.qrLocal ? this.$refs.qrLocal.innerHTML : '';
                        host.enviarQr(svg, {{ (float) $cobroIntegracionMonto }}, '{{ __('Escaneá para pagar') }}');
                        this.enPantallaCliente = true;
                    });
                }

                // Reverb (Fase 6): cuando MP confirma por webhook, el server
                // broadcastea y reaccionamos al instante re-consultando el estado
                // (que confirma y materializa). El wire:poll queda de respaldo
                // por si el websocket no está disponible.
                const canal = this.$el.dataset.cobroCanal;
                if (canal && window.Echo) {
                    this.cobroCanal = canal;
                    window.Echo.private(canal).listen('.IntegracionPagoActualizado', () => {
                        this.$wire.pollearCobroIntegracion();
                    });
                }
            },
            destroy() {
                if (this.timer) clearInterval(this.timer);
                if (this.enPantallaCliente && window.bcnPantallaClienteHost) {
                    window.bcnPantallaClienteHost.limpiar();
                }
                if (this.cobroCanal && window.Echo) {
                    window.Echo.leave(this.cobroCanal);
                }
            }
        }"
        @keydown.escape.window="$wire.cancelarCobroIntegracion()">
        <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full w-full">
                {{-- Header --}}
                <div class="bg-sky-600 px-4 py-3 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        {{ __('Esperando pago') }}
                    </h3>
                </div>

                <div class="px-4 py-5 sm:p-6 space-y-5">
                    {{-- Monto a cobrar --}}
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">{{ __('Monto a cobrar') }}</p>
                        <p class="text-3xl font-extrabold text-gray-900 dark:text-white">${{ number_format($cobroIntegracionMonto, 2, ',', '.') }}</p>
                    </div>

                    {{-- QR --}}
                    <div class="flex flex-col items-center">
                        @if($cobroIntegracionQrSvg || $cobroIntegracionQrImagenUrl)
                            {{-- QR en la pantalla del cajero (se oculta si va al monitor del cliente).
                                 Dinámico: SVG renderizado de la trama EMVCo.
                                 Estático: imagen del QR impreso del POS. --}}
                            <div x-show="!enPantallaCliente" class="flex flex-col items-center">
                                <div class="bg-white p-3 rounded-xl border border-gray-200 dark:border-gray-600 shadow-sm" wire:ignore x-ref="qrLocal">
                                    @if($cobroIntegracionQrSvg)
                                        {!! $cobroIntegracionQrSvg !!}
                                    @else
                                        <img src="{{ $cobroIntegracionQrImagenUrl }}" alt="{{ __('Código QR de la caja') }}" class="w-[240px] h-[240px] object-contain" />
                                    @endif
                                </div>
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    @if($cobroIntegracionQrSvg)
                                        {{ __('Escaneá el código con la app para pagar') }}
                                    @else
                                        {{ __('Pedile al cliente que escanee el QR de la caja') }}
                                    @endif
                                </p>
                            </div>
                            {{-- Modo compacto: el QR se está mostrando en el monitor del cliente --}}
                            <div x-show="enPantallaCliente" x-cloak class="flex flex-col items-center text-center py-2">
                                <div class="flex items-center justify-center w-20 h-20 rounded-full bg-sky-100 dark:bg-sky-900/30 mb-3">
                                    <svg class="w-10 h-10 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="text-base font-medium text-gray-700 dark:text-gray-200">{{ __('Mostrando el QR en la pantalla del cliente') }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Pedile al cliente que escanee con su app') }}</p>
                            </div>
                        @else
                            <div class="flex items-center justify-center w-[240px] h-[240px] bg-gray-100 dark:bg-gray-700 rounded-xl">
                                <svg class="animate-spin h-8 w-8 text-sky-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Estado: esperando confirmación + countdown --}}
                    <div class="rounded-xl p-4 text-center border-2 border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20">
                        <div class="flex items-center justify-center gap-2 text-sky-700 dark:text-sky-300">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ __('Esperando confirmación del pago…') }}</span>
                        </div>
                        @if($cobroIntegracionExpiraTs)
                            <p class="mt-2 text-xs uppercase tracking-wide text-sky-500 dark:text-sky-400">{{ __('Expira en') }}</p>
                            <p class="text-2xl font-bold text-sky-700 dark:text-sky-300"
                               x-text="expirado ? '{{ __('Expirado') }}' : (mm + ':' + ss)"></p>
                        @endif
                    </div>

                    {{-- Confirmación manual (RF-12): fallback con permiso si el pago no se detecta solo --}}
                    @if($this->puedeConfirmarManual)
                        <div x-data="{ confirmando: false }">
                            <button x-show="!confirmando" type="button" @click="confirmando = true"
                                    class="w-full text-center text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline">
                                {{ __('El pago no se detectó automáticamente') }}
                            </button>
                            <div x-show="confirmando" x-cloak class="rounded-xl border-2 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-3">
                                <p class="text-xs text-amber-800 dark:text-amber-200">
                                    {{ __('Confirmá solo si verificaste que el cliente pagó. El sistema no detectó el pago automáticamente y esta acción queda registrada.') }}
                                </p>
                                <div class="mt-3 flex gap-2">
                                    <button type="button" wire:click="confirmarCobroIntegracionManual"
                                            class="flex-1 inline-flex justify-center rounded-md px-3 py-2 bg-amber-600 text-white text-xs font-medium hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                        {{ __('Sí, el cliente pagó') }}
                                    </button>
                                    <button type="button" @click="confirmando = false"
                                            class="inline-flex justify-center rounded-md px-3 py-2 border border-gray-300 dark:border-gray-600 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        {{ __('Volver') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                    <button
                        wire:click="cancelarCobroIntegracion"
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:text-sm">
                        {{ __('Cancelar cobro') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
