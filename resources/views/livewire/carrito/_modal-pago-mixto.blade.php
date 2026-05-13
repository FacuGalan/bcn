{{-- Modal de Pago / Desglose Mixto (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
@if($mostrarModalPago)
    <div
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-pago"
        role="dialog"
        aria-modal="true"
        x-data="{
            pagosCount: {{ count($desglosePagos) }},
            init() {
                this.$nextTick(() => {
                    // Enfocar el input de búsqueda de FP si hay pendiente
                    const busquedaFP = this.$el.querySelector('[x-ref=inputBusquedaFP]');
                    if (busquedaFP) { busquedaFP.focus(); return; }
                    // Si ya no hay pendiente, enfocar primer elemento
                    const firstInput = this.$el.querySelector('input, button[type=button]:not([disabled])');
                    if (firstInput) firstInput.focus();
                });
            }
        }"
        @keydown.escape.window="$wire.cerrarModalPago()"
        x-on:focus-busqueda-fp.window="setTimeout(() => { const el = $el.querySelector('[x-ref=inputBusquedaFP]'); if (el) el.focus(); }, 150)"
        @keydown.enter.window.prevent="
            const activeEl = document.activeElement;
            // No interceptar Enter si estamos en inputs del selector de FP o monto (lo manejan ellos)
            if (activeEl && (activeEl.hasAttribute('x-ref'))) {
                const ref = activeEl.getAttribute('x-ref');
                if (ref === 'inputBusquedaFP' || ref === 'inputMontoDesglose' || ref === 'btnAgregar') return;
            }
            // Si el desglose está completo, confirmar pago
            if ({{ $this->desgloseCompleto() ? 'true' : 'false' }}) $wire.confirmarPago();
        "
        @keydown.tab.prevent="
            const focusables = [...$el.querySelectorAll('input, button:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')];
            const current = document.activeElement;
            let idx = focusables.indexOf(current);
            idx = $event.shiftKey ? idx - 1 : idx + 1;
            if (idx < 0) idx = focusables.length - 1;
            if (idx >= focusables.length) idx = 0;
            focusables[idx]?.focus();
        "
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalPago"></div>

            {{-- Centrado --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Modal --}}
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                {{-- Header --}}
                <div class="bg-green-600 px-3 py-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex-shrink-0 flex items-center justify-center h-8 w-8 rounded-full bg-green-100">
                            <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-medium text-white">
                            {{ count($desglosePagos) > 1 || $montoPendienteDesglose > 0 ? __('Desglose de Pagos') : __('Confirmar Pago') }}
                        </h3>
                    </div>
                    <button wire:click="cerrarModalPago" class="text-white hover:text-green-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Contenido --}}
                <div class="px-3 py-3 space-y-3 max-h-[70vh] overflow-y-auto">
                    {{-- Resumen del total --}}
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 flex justify-between items-center">
                        <div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total a cobrar') }}:</span>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                $@precio($resultado['total_final'] ?? 0)
                            </div>
                        </div>
                        @if($montoPendienteDesglose > 0.01)
                            <div class="text-right">
                                <span class="text-sm text-orange-600">{{ __('Pendiente') }}:</span>
                                <div class="text-xl font-bold text-orange-600">
                                    $@precio($montoPendienteDesglose)
                                </div>
                            </div>
                        @else
                            <div class="text-right">
                                <span class="text-sm text-green-600">{{ __('Total con ajustes') }}:</span>
                                <div class="text-xl font-bold text-green-600">
                                    $@precio($totalConAjustes)
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Pagos agregados --}}
                    @if(count($desglosePagos) > 0)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1.5 border-b border-gray-200 dark:border-gray-600">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Formas de Pago') }}</h4>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($desglosePagos as $index => $pago)
                                    <div class="px-3 py-2 {{ count($desglosePagos) === 1 && $montoPendienteDesglose <= 0.01 ? '' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }} {{ ($pago['factura_fiscal'] ?? false) ? 'border-l-4 border-indigo-500' : '' }}">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $pago['nombre'] }}</span>
                                                    @if($pago['ajuste_porcentaje'] != 0)
                                                        <span class="text-xs px-2 py-0.5 rounded {{ $pago['ajuste_porcentaje'] > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                                            {{ $pago['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $pago['ajuste_porcentaje'] }}%
                                                        </span>
                                                    @endif
                                                    {{-- Indicador de Factura Fiscal --}}
                                                    <button
                                                        wire:click="toggleFacturaFiscalDesglose({{ $index }})"
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors {{ ($pago['factura_fiscal'] ?? false) ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                                                        :title="($pago['factura_fiscal'] ?? false) ? __('Factura fiscal activada') : __('Clic para activar factura fiscal')"
                                                    >
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                        </svg>
                                                        {{ __('Fiscal') }}
                                                        @if($pago['factura_fiscal'] ?? false)
                                                            <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                            </svg>
                                                        @endif
                                                    </button>
                                                </div>

                                                {{-- Detalle moneda extranjera --}}
                                                @if(!empty($pago['es_moneda_extranjera']) && !empty($pago['monto_moneda_original']))
                                                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                        {{ $pago['moneda_info']['simbolo'] ?? '' }} {{ number_format($pago['monto_moneda_original'], 2, ',', '.') }}
                                                        {{ $pago['moneda_info']['codigo'] ?? '' }}
                                                        × {{ number_format($pago['tipo_cambio_tasa'], 2, ',', '.') }}
                                                    </div>
                                                @endif

                                                {{-- Detalles del monto --}}
                                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    <span>{{ __('Base') }}: $@precio($pago['monto_base'])</span>
                                                    @if($pago['monto_ajuste'] != 0)
                                                        <span class="mx-1">→</span>
                                                        <span class="{{ $pago['monto_ajuste'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                            {{ $pago['monto_ajuste'] > 0 ? '+' : '' }}$@precio($pago['monto_ajuste'])
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Selector de cuotas --}}
                                                @if($pago['permite_cuotas'] && count($pago['cuotas_disponibles']) > 0)
                                                    <div class="mt-2 flex items-center gap-2">
                                                        <label class="text-xs text-gray-600 dark:text-gray-400">{{ __('Cuotas') }}:</label>
                                                        <select
                                                            wire:change="actualizarCuotasDesglose({{ $index }}, $event.target.value)"
                                                            class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500">
                                                            @foreach($pago['cuotas_disponibles'] as $cuota)
                                                                <option value="{{ $cuota['cantidad'] }}" @selected($pago['cuotas'] == $cuota['cantidad'])>
                                                                    {{ $cuota['cantidad'] }} cuota{{ $cuota['cantidad'] > 1 ? 's' : '' }}
                                                                    @if($cuota['recargo'] > 0)
                                                                        (+{{ $cuota['recargo'] }}%)
                                                                    @else
                                                                        ({{ __('sin interés') }})
                                                                    @endif
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @if($pago['cuotas'] > 1)
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                $@precio($pago['monto_final'] / $pago['cuotas']) c/u
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif

                                                {{-- Input monto recibido para efectivo --}}
                                                @if($pago['permite_vuelto'])
                                                    <div class="mt-2 flex items-center gap-2"
                                                        x-data="{
                                                            recibido: {{ $pago['monto_recibido'] }},
                                                            montoFinal: {{ $pago['monto_final'] }},
                                                            iniciado: false,
                                                            get vuelto() {
                                                                return Math.max(0, Math.round((this.recibido - this.montoFinal) * 100) / 100);
                                                            },
                                                            onKeydown(e) {
                                                                if (!this.iniciado && e.key >= '0' && e.key <= '9') {
                                                                    e.preventDefault();
                                                                    this.iniciado = true;
                                                                    this.recibido = parseFloat(e.key);
                                                                    this.$dispatch('vuelto-updated', { index: {{ $index }}, recibido: this.recibido });
                                                                    this.$nextTick(() => {
                                                                        const input = this.$refs.inputRecibido;
                                                                        if (input) { input.value = e.key; input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
                                                                    });
                                                                } else if (e.key !== 'Tab' && e.key !== 'Escape') {
                                                                    this.iniciado = true;
                                                                }
                                                            },
                                                            onInput(e) {
                                                                this.recibido = parseFloat(e.target.value) || 0;
                                                                this.iniciado = true;
                                                                this.$dispatch('vuelto-updated', { index: {{ $index }}, recibido: this.recibido });
                                                            },
                                                            sync() {
                                                                $wire.actualizarMontoRecibido({{ $index }}, this.recibido);
                                                            }
                                                        }"
                                                        @click.away="sync()"
                                                    >
                                                        <label class="text-xs text-gray-600 dark:text-gray-400">{{ __('Recibido') }}:</label>
                                                        <div class="relative">
                                                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                x-ref="inputRecibido"
                                                                :value="recibido"
                                                                @keydown="onKeydown($event)"
                                                                @input="onInput($event)"
                                                                @blur="sync()"
                                                                class="w-28 pl-6 pr-2 py-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                                                tabindex="-1">
                                                        </div>
                                                        <span class="text-lg font-bold transition-all"
                                                            :class="vuelto > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-300 dark:text-gray-600'"
                                                            x-text="'{{ __('Vuelto') }}: $' + vuelto.toFixed(2).replace('.', ',')"
                                                        ></span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                        $@precio($pago['monto_final'])
                                                    </div>
                                                    @if($pago['recargo_cuotas'] > 0)
                                                        <div class="text-xs text-red-600">
                                                            +$@precio(($pago['monto_base'] + $pago['monto_ajuste']) * $pago['recargo_cuotas'] / 100) cuotas
                                                        </div>
                                                    @endif
                                                </div>
                                                <button
                                                    wire:click="eliminarDelDesglose({{ $index }})"
                                                    class="text-red-500 hover:text-red-700 p-1"
                                                    title="{{ __('Eliminar forma de pago') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Agregar forma de pago (solo si hay pendiente o es mixta) --}}
                    @if($montoPendienteDesglose > 0.01)
                        <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-gray-50 dark:bg-gray-700"
                            x-data="{
                                busqueda: '',
                                fpSeleccionadaId: @entangle('nuevoPago.forma_pago_id').live,
                                formasPago: @js(collect($formasPagoSucursal)->where('es_mixta', false)->values()->toArray()),
                                navIndex: -1,
                                cols: window.innerWidth >= 640 ? 4 : 3,
                                get filtradas() {
                                    if (!this.busqueda) return this.formasPago;
                                    const q = this.busqueda.trim().toLowerCase();
                                    return this.formasPago.filter(fp =>
                                        String(fp.id) === q ||
                                        fp.nombre.toLowerCase().includes(q) ||
                                        (fp.codigo && fp.codigo.toLowerCase().includes(q))
                                    );
                                },
                                seleccionar(fp) {
                                    this.fpSeleccionadaId = fp.id;
                                    this.busqueda = '';
                                    this.navIndex = -1;
                                },
                                limpiar() {
                                    this.fpSeleccionadaId = null;
                                    this.busqueda = '';
                                    this.navIndex = -1;
                                    this.$nextTick(() => {
                                        if (this.$refs.inputBusquedaFP) this.$refs.inputBusquedaFP.focus();
                                    });
                                },
                                handleBusquedaKeydown(e) {
                                    const len = this.filtradas.length;
                                    if (e.key === 'ArrowDown') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        if (this.navIndex < 0) { this.navIndex = 0; }
                                        else { this.navIndex = Math.min(this.navIndex + this.cols, len - 1); }
                                    } else if (e.key === 'ArrowUp') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        if (this.navIndex >= this.cols) { this.navIndex -= this.cols; }
                                        else { this.navIndex = -1; this.$refs.inputBusquedaFP?.focus(); }
                                    } else if (e.key === 'ArrowRight') {
                                        if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.min(this.navIndex + 1, len - 1); }
                                    } else if (e.key === 'ArrowLeft') {
                                        if (this.navIndex >= 0) { e.preventDefault(); this.navIndex = Math.max(this.navIndex - 1, 0); }
                                    } else if (e.key === 'Enter') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        if (this.navIndex >= 0 && this.navIndex < len) {
                                            this.seleccionar(this.filtradas[this.navIndex]);
                                        } else if (len > 0) {
                                            this.seleccionar(this.filtradas[0]);
                                        }
                                    } else {
                                        this.navIndex = -1;
                                    }
                                },
                                handleMontoKeydown(e) {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        $wire.agregarAlDesglose();
                                    }
                                },
                                fpNombre(fp) {
                                    return (fp.codigo || fp.nombre.substring(0,3).toUpperCase());
                                }
                            }"
                            x-init="$nextTick(() => { if (!fpSeleccionadaId && $refs.inputBusquedaFP) $refs.inputBusquedaFP.focus(); })"
                        >
                            {{-- Selector de forma de pago por botones --}}
                            <template x-if="!fpSeleccionadaId">
                                <div>
                                    {{-- Input de búsqueda --}}
                                    <div class="relative mb-2">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </span>
                                        <input
                                            type="text"
                                            x-ref="inputBusquedaFP"
                                            x-model="busqueda"
                                            @keydown="handleBusquedaKeydown($event)"
                                            class="w-full pl-8 pr-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                            placeholder="{{ __('Buscar por ID, código o nombre...') }}">
                                    </div>

                                    {{-- Grid de botones de formas de pago --}}
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5 max-h-40 overflow-y-auto" x-ref="gridFP">
                                        <template x-for="(fp, idx) in filtradas" :key="fp.id">
                                            <button
                                                type="button"
                                                @click="seleccionar(fp)"
                                                class="flex flex-col items-center justify-center p-2 rounded-lg border-2 transition-all text-center min-h-[52px]"
                                                :class="navIndex === idx
                                                    ? 'border-green-500 bg-green-50 dark:bg-green-900/30 ring-2 ring-green-400'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20'"
                                                :x-ref="'fp-btn-' + idx"
                                            >
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono leading-none" x-text="fp.id"></span>
                                                <span class="text-xs font-bold text-green-700 dark:text-green-400 uppercase tracking-wide leading-tight" x-text="fp.codigo || fp.nombre.substring(0, 3).toUpperCase()"></span>
                                                <span class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight mt-0.5 truncate w-full" x-text="fp.nombre"></span>
                                                <template x-if="fp.ajuste_porcentaje != 0">
                                                    <span class="text-[9px] font-medium mt-0.5"
                                                        :class="fp.ajuste_porcentaje > 0 ? 'text-red-600' : 'text-green-600'"
                                                        x-text="(fp.ajuste_porcentaje > 0 ? '+' : '') + fp.ajuste_porcentaje + '%'"></span>
                                                </template>
                                            </button>
                                        </template>
                                    </div>

                                    {{-- Sin resultados --}}
                                    <template x-if="busqueda && filtradas.length === 0">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-2">{{ __('No se encontraron formas de pago') }}</p>
                                    </template>
                                </div>
                            </template>

                            {{-- FP seleccionada: mostrar chip + monto + agregar --}}
                            <template x-if="fpSeleccionadaId">
                                <div x-data="{
                                    montoLocal: @entangle('nuevoPago.monto').live,
                                    tasaLocal: @entangle('nuevoPago.tipo_cambio_tasa').live,
                                    get fpActual() {
                                        return formasPago.find(fp => fp.id == fpSeleccionadaId) || null;
                                    },
                                    get esMonedaExt() {
                                        return this.fpActual && (this.fpActual.es_moneda_extranjera || false);
                                    },
                                    get simbolo() {
                                        if (this.esMonedaExt && this.fpActual.moneda_info) return this.fpActual.moneda_info.codigo || this.fpActual.moneda_info.simbolo || '$';
                                        return '$';
                                    },
                                    get codigoLabel() {
                                        if (!this.fpActual) return '';
                                        return this.fpActual.codigo || this.fpActual.nombre.substring(0,3).toUpperCase();
                                    },
                                    get equivalente() {
                                        const m = parseFloat(this.montoLocal) || 0;
                                        const t = parseFloat(this.tasaLocal) || 0;
                                        return m * t;
                                    },
                                    get equivalenteFormat() {
                                        return this.equivalente.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                }" x-init="$nextTick(() => { $refs.inputMontoDesglose?.focus() })">
                                    <div class="flex items-center gap-2">
                                        {{-- Chip de FP seleccionada --}}
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-lg text-sm font-medium">
                                            <span class="font-bold uppercase" x-text="codigoLabel"></span>
                                            <span class="text-green-600 dark:text-green-400" x-text="fpActual ? fpActual.nombre : ''"></span>
                                            <template x-if="fpActual && fpActual.ajuste_porcentaje != 0">
                                                <span class="text-xs"
                                                    :class="fpActual.ajuste_porcentaje > 0 ? 'text-red-600' : 'text-green-600'"
                                                    x-text="'(' + (fpActual.ajuste_porcentaje > 0 ? '+' : '') + fpActual.ajuste_porcentaje + '%)'"></span>
                                            </template>
                                            <button type="button" @click="limpiar()" class="ml-0.5 text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>

                                        {{-- Monto --}}
                                        <div class="relative flex-1">
                                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500 dark:text-gray-400 text-xs font-medium pointer-events-none" x-text="simbolo"></span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                x-ref="inputMontoDesglose"
                                                x-model="montoLocal"
                                                @keydown="handleMontoKeydown($event)"
                                                class="w-full pr-2 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-green-500 focus:ring-green-500"
                                                :class="esMonedaExt ? 'pl-12' : 'pl-6'"
                                                :placeholder="esMonedaExt ? '0.00' : '{{ number_format($montoPendienteDesglose, 2, ',', '.') }}'"
                                                title="{{ __('Vacío = monto pendiente completo') }}">
                                        </div>

                                        {{-- Botón Agregar --}}
                                        <button
                                            wire:click="agregarAlDesglose"
                                            type="button"
                                            x-ref="btnAgregar"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 whitespace-nowrap">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            {{ __('Agregar') }}
                                        </button>
                                    </div>

                                    {{-- Cotización (si moneda extranjera) --}}
                                    <template x-if="esMonedaExt">
                                        <div class="mt-2 flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md px-2 py-1.5">
                                            <span class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('Cotización') }} (<span x-text="fpActual?.moneda_info?.codigo || ''"></span>):</span>
                                            <div class="relative w-24">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    x-model="tasaLocal"
                                                    class="w-full px-2 py-1 text-sm border-amber-300 dark:border-amber-600 dark:bg-gray-800 dark:text-white rounded-md shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                                    placeholder="0.00">
                                            </div>
                                            <template x-if="equivalente > 0">
                                                <span class="text-xs text-amber-600 dark:text-amber-400" x-text="'= $' + equivalenteFormat"></span>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Cuotas (si aplica) - en fila separada, dropdown hacia arriba --}}
                                    @if(count($cuotasDisponibles) > 0)
                                        <div class="relative mt-2">
                                            @php
                                                $cuotaSelDesglose = collect($cuotasDesgloseConMontos)->firstWhere('cantidad', $nuevoPago['cuotas']);
                                                $montoBase = (float) ($nuevoPago['monto'] ?? 0) ?: $montoPendienteDesglose;
                                                $fpDesglose = collect($formasPagoSucursal)->firstWhere('id', (int) $nuevoPago['forma_pago_id']);
                                                $ajusteDesglose = $fpDesglose ? ($fpDesglose['ajuste_porcentaje'] ?? 0) : 0;
                                                $montoConAjusteDesglose = round($montoBase + ($montoBase * $ajusteDesglose / 100), 2);
                                            @endphp

                                            {{-- Dropdown de opciones (arriba del selector) --}}
                                            @if($cuotasDesgloseSelectorAbierto)
                                                <div class="absolute z-30 left-0 right-0 bottom-full mb-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-40 overflow-y-auto">
                                                    {{-- Opción: 1 pago --}}
                                                    <div
                                                        wire:click="seleccionarCuotaDesglose(1)"
                                                        class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $nuevoPago['cuotas'] == 1 ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}"
                                                    >
                                                        <div class="flex-1">
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">{{ __('sin financiación') }}</span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">$@precio($montoConAjusteDesglose)</span>
                                                        @if($nuevoPago['cuotas'] == 1)
                                                            <svg class="w-3 h-3 text-blue-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                            </svg>
                                                        @endif
                                                    </div>

                                                    {{-- Opciones de cuotas --}}
                                                    @foreach($cuotasDesgloseConMontos as $cuota)
                                                        <div
                                                            wire:click="seleccionarCuotaDesglose({{ $cuota['cantidad'] }})"
                                                            class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $nuevoPago['cuotas'] == $cuota['cantidad'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}"
                                                        >
                                                            <div class="flex-1">
                                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad'] }} cuotas</span>
                                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">de $@precio($cuota['valor_cuota'])</span>
                                                            </div>
                                                            @if($cuota['recargo'] > 0)
                                                                <span class="text-xs font-medium text-red-600 mx-2">+{{ $cuota['recargo'] }}%</span>
                                                            @endif
                                                            <span class="text-sm font-semibold {{ $cuota['recargo'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuota['total_con_recargo'])</span>
                                                            @if($nuevoPago['cuotas'] == $cuota['cantidad'])
                                                                <svg class="w-3 h-3 text-blue-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Selector visible --}}
                                            <div
                                                wire:click="toggleCuotasDesgloseSelector"
                                                class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                            >
                                                @if($nuevoPago['cuotas'] == 1 || !$cuotaSelDesglose)
                                                    <div class="flex items-center px-2 py-1">
                                                        <div class="flex-1 min-w-0">
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">{{ __('sin financiación') }}</span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">$@precio($montoConAjusteDesglose)</span>
                                                        <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasDesgloseSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                        </svg>
                                                    </div>
                                                @else
                                                    <div class="flex items-center px-2 py-1">
                                                        <div class="flex-1 min-w-0">
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cuotaSelDesglose['cantidad'] }} cuotas</span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">de $@precio($cuotaSelDesglose['valor_cuota'])</span>
                                                        </div>
                                                        @if($cuotaSelDesglose['recargo'] > 0)
                                                            <span class="text-xs font-medium text-red-600 mx-2">+{{ $cuotaSelDesglose['recargo'] }}%</span>
                                                        @endif
                                                        <span class="text-sm font-semibold {{ $cuotaSelDesglose['recargo'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">$@precio($cuotaSelDesglose['total_con_recargo'])</span>
                                                        <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasDesgloseSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>{{-- /x-data fpActual --}}
                            </template>
                        </div>
                    @endif

                    {{-- Total vuelto general --}}
                    @php
                        $vueltoTotal = collect($desglosePagos)->sum('vuelto');
                        $montoFiscalDesglose = collect($desglosePagos)->where('factura_fiscal', true)->sum('monto_final');
                        $cantidadFiscales = collect($desglosePagos)->where('factura_fiscal', true)->count();
                        $pagosConVuelto = collect($desglosePagos)->filter(fn($p) => $p['permite_vuelto'])->values();
                    @endphp
                    @if($pagosConVuelto->count() > 0)
                        <div
                            x-data="{
                                vueltoBase: {{ $vueltoTotal }},
                                pagosVuelto: @js($pagosConVuelto->map(fn($p, $i) => ['index' => collect($desglosePagos)->search($p), 'monto_final' => $p['monto_final'], 'monto_recibido' => $p['monto_recibido']])->values()->toArray()),
                                overrides: {},
                                get vueltoTotal() {
                                    let total = 0;
                                    for (const p of this.pagosVuelto) {
                                        const recibido = this.overrides[p.index] !== undefined ? this.overrides[p.index] : p.monto_recibido;
                                        total += Math.max(0, Math.round((recibido - p.monto_final) * 100) / 100);
                                    }
                                    return total;
                                }
                            }"
                            @vuelto-updated.window="overrides[$event.detail.index] = $event.detail.recibido"
                            x-show="vueltoTotal > 0"
                            x-transition
                            class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 rounded-xl p-5 text-center"
                        >
                            <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wide mb-2">{{ __('Vuelto a entregar') }}</p>
                            <p class="text-5xl font-extrabold text-green-600 dark:text-green-400" x-text="'$' + vueltoTotal.toFixed(2).replace('.', ',')"></p>
                        </div>
                    @endif

                    {{-- Resumen de Facturación Fiscal --}}
                    @if(count($desglosePagos) > 0)
                        <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg p-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ __('Facturación Fiscal') }}</span>
                                        @if($cantidadFiscales > 0)
                                            <span class="text-xs text-indigo-500 dark:text-indigo-400 ml-1">({{ $cantidadFiscales }} FP)</span>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">({{ __('sin factura') }})</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if($montoFiscalDesglose > 0)
                                        <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($montoFiscalDesglose, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-lg font-medium text-gray-400">$0,00</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 flex flex-row-reverse gap-2">
                    <button
                        wire:click="confirmarPago"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        @if(!$this->desgloseCompleto()) disabled @endif
                        type="button"
                        class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg wire:loading.remove class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove>{{ __('Confirmar') }}</span>
                        <span wire:loading>{{ __('Procesando...') }}</span>
                    </button>
                    {{-- Botón "Confirmar sin cobrar" — solo aparece si el host lo habilita
                        (NuevoPedidoMostrador setea $puedeConfirmarSinCobrar = true). En
                        NuevaVenta queda invisible porque la prop no existe. --}}
                    @if($puedeConfirmarSinCobrar ?? false)
                        <button
                            wire:click="confirmarSinCobrar"
                            wire:loading.attr="disabled"
                            type="button"
                            class="inline-flex justify-center items-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                            {{ __('Confirmar sin cobrar') }}
                        </button>
                    @endif
                    <button
                        wire:click="cerrarModalPago"
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-3 py-2 bg-white dark:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        {{ __('Volver') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
