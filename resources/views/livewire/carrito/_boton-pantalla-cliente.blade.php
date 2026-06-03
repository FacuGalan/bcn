{{-- Botón flotante: conectar la pantalla orientada al cliente (segundo monitor).
     Solo se renderiza si la caja activa tiene el flag usa_pantalla_cliente.
     Toda la lógica es client-side (Alpine + window.bcnPantallaClienteHost). --}}
@if($this->usaPantallaClienteActiva)
    <div
        class="fixed bottom-2 left-1/2 -translate-x-1/2 z-40 print:hidden"
        x-data="{
            conectada: false,
            esModoApp: false,
            soportada: ('open' in window),
            pcConfig: @js($this->configPantallaCliente),
            sincronizarConfig() {
                if (window.bcnPantallaClienteHost && this.pcConfig && Object.keys(this.pcConfig).length) {
                    window.bcnPantallaClienteHost.setConfig(this.pcConfig);
                }
            },
            refrescar() {
                if (window.bcnPantallaClienteHost) {
                    // Pinguear mantiene fresca la detección de la PWA (responde pong).
                    window.bcnPantallaClienteHost.pingear();
                    this.conectada = window.bcnPantallaClienteHost.estaConectada();
                } else {
                    this.conectada = false;
                }
            },
            async conectar() {
                if (!window.bcnPantallaClienteHost) return;
                if (this.conectada) {
                    window.bcnPantallaClienteHost.desconectar();
                    this.conectada = false;
                    return;
                }
                this.sincronizarConfig();
                // En modo app no abre popup (solo re-pinguea); el botón es indicador.
                await window.bcnPantallaClienteHost.conectar();
                this.refrescar();
            },
            init() {
                this.esModoApp = window.bcnPantallaClienteHost
                    ? window.bcnPantallaClienteHost.esModoApp()
                    : false;
                this.sincronizarConfig();
                this.refrescar();
                this._t = setInterval(() => this.refrescar(), 2000);
            },
            destroy() { if (this._t) clearInterval(this._t); }
        }"
    >
        {{-- En modo PWA instalada y sin pantalla viva: el botón NO abre nada (no se
             puede lanzar la otra PWA desde web) → queda como indicador no clickeable. --}}
        <button
            type="button"
            @click="conectar()"
            :disabled="!conectada && esModoApp"
            class="inline-flex items-center gap-1.5 rounded-full shadow px-2.5 py-1 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-default"
            :class="conectada
                ? 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500'
                : (esModoApp
                    ? 'bg-gray-800/90 text-gray-100 focus:ring-gray-500 dark:bg-gray-700'
                    : 'bg-gray-800/90 text-gray-100 hover:bg-gray-700 focus:ring-gray-500 dark:bg-gray-700 dark:hover:bg-gray-600')"
            :title="conectada
                ? '{{ __('Pantalla cliente conectada (clic para cerrar)') }}'
                : (esModoApp
                    ? '{{ __('Abrí la app Pantalla Cliente en el monitor del cliente para conectarla') }}'
                    : '{{ __('Conectar pantalla cliente') }}')"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span x-show="!conectada && !esModoApp">{{ __('Conectar pantalla cliente') }}</span>
            <span x-show="!conectada && esModoApp">{{ __('Pantalla cliente desconectada') }}</span>
            <span x-show="conectada" class="flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span>
                {{ __('Pantalla cliente') }}
            </span>
        </button>
    </div>
@endif
