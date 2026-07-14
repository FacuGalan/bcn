{{-- Editor de compra: modal a pantalla completa (Fase 6, sesión UX D7 — patrón NuevoPedidoDelivery) --}}
<div data-livewire-root="editor-compra">
<div class="fixed inset-0 z-40 bg-black/40 flex items-stretch justify-center p-2 sm:p-3"
    x-data="{
        _stackId: null,
        init() {
            window._bcnModalStack = window._bcnModalStack || [];
            this._stackId = Symbol();
            window._bcnModalStack.push(this._stackId);
            document.body.classList.add('overflow-hidden');
        },
        destroy() {
            if (this._stackId && window._bcnModalStack) {
                window._bcnModalStack = window._bcnModalStack.filter(id => id !== this._stackId);
            }
            if (!(window._bcnModalStack || []).length) {
                document.body.classList.remove('overflow-hidden');
            }
        },
        // Navegación tipo planilla: Enter avanza de celda; en la última celda
        // de la última fila agrega un renglón nuevo (D7 #2).
        celdas() { return Array.from($el.querySelectorAll('[data-cell]:not([disabled])')); },
        enfocar(c) { if (c) { c.focus(); c.select?.(); } },
        avanzar(e) {
            const celdas = this.celdas();
            const i = celdas.indexOf(e.target);
            if (i === -1) return;
            if (i === celdas.length - 1) {
                // Solo la grilla agrega renglón al final; en el encabezado
                // (única zona con celdas en una factura de servicio) no hace nada.
                if (e.target.dataset.fila === undefined) return;
                $wire.agregarRenglon().then(() => this.$nextTick(() => {
                    const nuevas = this.celdas();
                    const maxFila = Math.max(...nuevas.map(c => +c.dataset.fila || 0));
                    this.enfocar(nuevas.find(c => +c.dataset.fila === maxFila));
                }));
            } else {
                this.enfocar(celdas[i + 1]);
            }
        },
        // ↑/↓: misma columna de la fila anterior/siguiente (si esa fila no
        // tiene la columna — artículo ya elegido — cae a la primera celda).
        moverFila(e, delta) {
            const fila = +e.target.dataset.fila + delta;
            if (fila < 0) return;
            const col = e.target.dataset.col;
            this.enfocar(
                $el.querySelector(`[data-cell][data-fila='${fila}'][data-col='${col}']:not([disabled])`)
                ?? $el.querySelector(`[data-cell][data-fila='${fila}']:not([disabled])`)
            );
        },
        // ←/→: celda vecina SOLO con el caret en el borde del texto (no
        // rompe la edición dentro del input).
        moverCol(e, delta) {
            const el = e.target;
            const len = (el.value ?? '').length;
            const enBorde = delta < 0
                ? (el.selectionStart === 0 && el.selectionEnd === 0)
                : (el.selectionStart === len && el.selectionEnd === len);
            if (!enBorde) return;
            e.preventDefault();
            const celdas = this.celdas();
            this.enfocar(celdas[celdas.indexOf(el) + delta]);
        },
        focusCelda(fila, col) {
            this.$nextTick(() => this.enfocar($el.querySelector(`[data-cell][data-fila='${fila}'][data-col='${col}']:not([disabled])`)));
        },
        modalAbierto() {
            return $wire.mostrarModalPago || $wire.mostrarModalResumen || $wire.mostrarModalArticuloRapido
                || $wire.mostrarModalProveedorRapido || $wire.mostrarModalBusquedaArticulos;
        }
    }"
    @foco-celda.window="focusCelda($event.detail.fila, $event.detail.col ?? 'cantidad')"
    @keydown.escape.window="if (!modalAbierto()) $wire.cerrar()"
    @keydown.window="
        if ($event.key === 'F2' && !modalAbierto()) { $event.preventDefault(); $wire.abrirModalPago(); }
        if ($event.ctrlKey && ($event.key === 'g' || $event.key === 'G') && !modalAbierto()) { $event.preventDefault(); $wire.guardarBorrador(); }
    "
>
    <div class="w-full bg-white dark:bg-gray-900 flex flex-col overflow-hidden rounded-lg shadow-2xl">

        {{-- Header --}}
        <div class="bg-bcn-primary text-white px-4 sm:px-6 py-2 flex items-center justify-between gap-3 flex-shrink-0 rounded-t-lg">
            <h2 class="text-base sm:text-lg font-bold flex items-center gap-2 flex-wrap">
                @if($esNC)
                    {{ $compraId ? __('Editar Nota de Crédito') : __('Nueva Nota de Crédito') }}
                    @if($compraOrigen)
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">
                            {{ __('Origen') }}: {{ $compraOrigen->numero_comprobante }}
                        </span>
                    @endif
                @elseif($modoCorreccion)
                    {{ __('Corregir Compra') }}
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">{{ __('Corrección') }}</span>
                @else
                    {{ $compraId ? __('Editar Compra') : __('Nueva Compra') }}
                @endif
                @if($compraId && ! $modoCorreccion)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">{{ __('Borrador') }}</span>
                @endif
                @if($esServicio)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">{{ __('Servicio') }}</span>
                @endif
            </h2>
            <button type="button" wire:click="cerrar" class="text-white/80 hover:text-white flex-shrink-0" title="{{ __('Cerrar') }} (Esc)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Cuerpo scrolleable --}}
        <div class="flex-1 overflow-y-auto p-2 sm:p-3 space-y-2 min-h-0">

            {{-- ============ ENCABEZADO ============ --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-2 sm:p-3">
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                    {{-- 1. Proveedor (combobox con búsqueda + alta rápida) --}}
                    <div class="col-span-2 lg:col-span-3"
                        x-data="{
                            abierto: false,
                            busqueda: '',
                            highlight: 0,
                            proveedores: @js($proveedores->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'cuit' => $p->cuit])->values()),
                            get filtrados() {
                                if (!this.busqueda) return this.proveedores;
                                const terms = this.busqueda.toLowerCase().split(/\s+/);
                                return this.proveedores.filter(p => {
                                    const s = [p.nombre, p.cuit].filter(Boolean).join(' ').toLowerCase();
                                    return terms.every(t => s.includes(t));
                                });
                            },
                            nombreSeleccionado() {
                                const p = this.proveedores.find(p => p.id == $wire.proveedorId);
                                return p ? p.nombre : '';
                            },
                            seleccionar(p) {
                                $wire.seleccionarProveedor(p.id);
                                this.busqueda = '';
                                this.abierto = false;
                            }
                        }"
                        @proveedor-creado.window="proveedores.push({ id: $event.detail.id, nombre: $event.detail.nombre, cuit: $event.detail.cuit }); busqueda = ''; abierto = false"
                        @click.away="abierto = false">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Proveedor') }} *</label>
                        <div class="mt-1 flex">
                            <div class="relative flex-1 min-w-0">
                                <input type="text" data-cell
                                    :value="abierto ? busqueda : nombreSeleccionado()"
                                    @input="busqueda = $event.target.value; highlight = 0"
                                    @focus="abierto = true; busqueda = ''"
                                    @keydown.arrow-down.prevent="highlight = Math.min(highlight + 1, filtrados.length - 1)"
                                    @keydown.arrow-up.prevent="highlight = Math.max(highlight - 1, 0)"
                                    @keydown.enter.stop.prevent="if (abierto && filtrados[highlight]) { seleccionar(filtrados[highlight]); $nextTick(() => avanzar($event)); } else { avanzar($event); }"
                                    @keydown.escape.stop="abierto = false"
                                    placeholder="{{ __('Buscar proveedor...') }}"
                                    class="block w-full rounded-l-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <div x-show="abierto" x-transition.opacity
                                    class="absolute z-50 mt-1 w-full max-h-48 overflow-auto bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
                                    <template x-for="(p, i) in filtrados" :key="p.id">
                                        <button type="button" @click="seleccionar(p)"
                                            :class="i === highlight ? 'bg-bcn-primary/10 dark:bg-bcn-primary/20' : ''"
                                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-300">
                                            <span x-text="p.nombre"></span>
                                            <span class="text-xs text-gray-400" x-text="p.cuit"></span>
                                        </button>
                                    </template>
                                    <p x-show="!filtrados.length" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Sin resultados') }}</p>
                                </div>
                            </div>
                            <button type="button" @click="abierto = false; $wire.abrirProveedorRapido(busqueda)"
                                class="flex-shrink-0 inline-flex items-center justify-center px-2 self-stretch bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                title="{{ __('Nuevo proveedor') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            </button>
                        </div>
                        {{-- D23: modalidad servicio — sin artículos, el detalle son los conceptos.
                            Vive bajo el proveedor: su flag "prestador de servicios" la precarga. --}}
                        <label class="mt-1 flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 cursor-pointer"
                            title="{{ __('Factura de servicio (luz, gas, alquiler...): sin artículos ni stock — el detalle son renglones libres y la cuenta de compra es obligatoria') }}">
                            <input type="checkbox" wire:model.live="esServicio"
                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 w-3.5 h-3.5">
                            {{ __('Factura de servicio') }}
                        </label>
                    </div>

                    {{-- 2. CUIT comprador --}}
                    @if($this->esFiscalActual())
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('CUIT comprador') }}</label>
                            <select wire:model.live="cuitId" data-cell @keydown.enter.prevent="avanzar($event)"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <option value="">{{ __('Sin CUIT') }}</option>
                                @foreach($cuits as $cuit)
                                    <option value="{{ $cuit->id }}">{{ $cuit->razon_social }} ({{ $cuit->numero_cuit }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- 3. Tipo de comprobante (sugerido por proveedor × CUIT, editable) + toggle no fiscal (D15) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Comprobante') }}</label>
                        <select wire:model.live="tipoComprobante" @if($noFiscal) disabled @endif data-cell @keydown.enter.prevent="avanzar($event)"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 disabled:opacity-60">
                            @foreach($this->tiposDisponibles() as $tipo)
                                <option value="{{ $tipo }}">{{ __('compra_tipo_'.$tipo) }}</option>
                            @endforeach
                        </select>
                        <label class="mt-1 flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                            <input type="checkbox" wire:model.live="noFiscal"
                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 w-3.5 h-3.5">
                            {{ __('Compra no fiscal') }}
                        </label>
                    </div>

                    {{-- 4. Número del comprobante del proveedor (RF-13): PV + número, con relleno de ceros --}}
                    <div class="col-span-2"
                        x-data="{ pad(v, len) { v = v.trim(); return /^\d+$/.test(v) ? v.padStart(len, '0') : v; } }">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('N° comprobante') }}</label>
                        <div class="mt-1 flex items-center gap-1">
                            <input type="text" wire:model="numeroPv" placeholder="0001" maxlength="5" inputmode="numeric"
                                data-cell @keydown.enter.prevent="avanzar($event)"
                                @blur="$wire.set('numeroPv', pad($event.target.value, 4))"
                                class="block w-16 rounded-md shadow-sm text-sm text-center dark:bg-gray-700 dark:text-white focus:ring focus:ring-opacity-50
                                    {{ $esDuplicado ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:border-bcn-primary focus:ring-bcn-primary' }}">
                            <span class="text-gray-400 dark:text-gray-500">-</span>
                            <input type="text" wire:model="numeroCbte" placeholder="00012345" maxlength="20" inputmode="numeric"
                                data-cell @keydown.enter.prevent="avanzar($event)"
                                @blur="$wire.set('numeroCbte', pad($event.target.value, 8))"
                                class="block w-28 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:text-white focus:ring focus:ring-opacity-50
                                    {{ $esDuplicado ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:border-bcn-primary focus:ring-bcn-primary' }}">
                        </div>
                        @if($esDuplicado)
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ __('Ya existe una compra activa con este comprobante') }}</p>
                        @endif
                    </div>

                    {{-- 5. Fechas --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Fecha comprobante') }}@if($this->esFiscalActual()) *@endif
                        </label>
                        <input type="date" wire:model.live="fechaComprobante" data-cell @keydown.enter.prevent="avanzar($event)"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    @if(! $esNC)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Vencimiento') }}</label>
                            <input type="date" wire:model="fechaVencimiento" data-cell @keydown.enter.prevent="avanzar($event)"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>
                    @endif

                    {{-- 6. Cuenta de compra (RF-22; obligatoria en servicios, D23) --}}
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Cuenta de compra') }}@if($esServicio) *@endif</label>
                        <select wire:model="cuentaCompraId" data-cell @keydown.enter.prevent="avanzar($event)"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="">{{ __('Sin clasificar') }}</option>
                            @foreach($cuentasCompra as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 7. Descuento global (RF-05) — aplica a renglones, no a servicios --}}
                    @if(! $esServicio)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Desc. global (%)') }}</label>
                            <input type="text" wire:model.live.debounce.500ms="descuentoGlobal" placeholder="0" data-cell @keydown.enter.prevent="avanzar($event)"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>
                    @endif

                    {{-- 8. Observaciones --}}
                    <div class="col-span-2 lg:col-span-3">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Observaciones') }}</label>
                        <input type="text" wire:model="observaciones" data-cell @keydown.enter.prevent="avanzar($event)"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                </div>
            </div>

            {{-- Advertencias no bloqueantes (RF-06/RF-14) --}}
            @if($advertencias !== [])
                <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg px-4 py-3 space-y-1">
                    @foreach($advertencias as $aviso)
                        <p class="text-xs text-yellow-800 dark:text-yellow-200 flex items-start gap-1.5">
                            <svg class="w-4 h-4 flex-shrink-0 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>{{ $aviso }}</span>
                        </p>
                    @endforeach
                </div>
            @endif

            {{-- ============ GRILLA DE RENGLONES (planilla, D7 #2) ============ --}}
            {{-- D23: una factura de servicio no lleva artículos — su detalle son los conceptos --}}
            @if(! $esServicio)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-bcn-light dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider min-w-64">{{ __('Artículo') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-24">{{ $esNC ? __('Cant. devuelta') : __('Cant.') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-20">{{ __('Factor') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-20">{{ __('Stock') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-28">{{ __('Precio unit.') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-24">{{ __('Desc.') }}</th>
                                <th scope="col" class="px-2 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-28">{{ __('Unit. efectivo') }}</th>
                                @if($this->esFiscalActual() && $this->discriminaActual())
                                    <th scope="col" class="px-2 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-24">{{ __('IVA') }}</th>
                                @endif
                                <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider w-28">{{ __('Subtotal') }}</th>
                                <th scope="col" class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($renglones as $i => $renglon)
                                @php $calc = $this->calcularRenglon($renglon); @endphp
                                <tr wire:key="renglon-{{ $i }}">
                                    {{-- Artículo: buscador (código propio / código proveedor / nombre).
                                         Input DEFERRED + debounce Alpine (el morph no pisa el tipeo) y
                                         dropdown position:fixed (ningún overflow/footer lo tapa). --}}
                                    <td class="px-3 py-1 relative">
                                        @if(empty($renglon['articulo_id']))
                                            @php $hayDropdown = $renglon['resultados'] !== [] || mb_strlen(trim($renglon['busqueda'])) >= 2; @endphp
                                            <div x-data="{
                                                    highlight: 0,
                                                    t: null,
                                                    ddPos: '',
                                                    posicionarDd() {
                                                        const r = $refs.buscador.getBoundingClientRect();
                                                        this.ddPos = `position: fixed; top: ${r.bottom + 4}px; left: ${r.left}px; width: ${Math.max(r.width, 320)}px; z-index: 60;`;
                                                    }
                                                }"
                                                @click.away="@if($hayDropdown) $wire.cerrarResultadosFila({{ $i }}) @endif">
                                                <div class="flex">
                                                    <input type="text" x-ref="buscador"
                                                        wire:model="renglones.{{ $i }}.busqueda"
                                                        data-cell data-fila="{{ $i }}" data-col="articulo"
                                                        placeholder="{{ __('Código, código proveedor o nombre...') }}"
                                                        @input="highlight = 0; clearTimeout(t); t = setTimeout(() => $wire.buscarArticuloFila({{ $i }}), 350)"
                                                        @keydown.arrow-down.prevent="@if($renglon['resultados'] !== []) highlight = Math.min(highlight + 1, {{ count($renglon['resultados']) - 1 }}) @else moverFila($event, 1) @endif"
                                                        @keydown.arrow-up.prevent="@if($renglon['resultados'] !== []) highlight = Math.max(highlight - 1, 0) @else moverFila($event, -1) @endif"
                                                        @keydown.arrow-left="moverCol($event, -1)"
                                                        @keydown.arrow-right="moverCol($event, 1)"
                                                        @keydown.enter.stop.prevent="
                                                            const resultados = @js(collect($renglon['resultados'])->pluck('id'));
                                                            if (resultados[highlight] !== undefined) $wire.seleccionarArticuloFila({{ $i }}, resultados[highlight]);
                                                        "
                                                        @if($hayDropdown) @keydown.escape.stop="$wire.cerrarResultadosFila({{ $i }})" @endif
                                                        class="block w-full rounded-l-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    <button type="button" wire:click="abrirBusquedaAvanzada({{ $i }})" tabindex="-1"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-2 self-stretch bg-gray-500 hover:bg-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-gray-400"
                                                        title="{{ __('Búsqueda avanzada') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                    </button>
                                                    <button type="button" wire:click="abrirAltaRapida({{ $i }})" tabindex="-1"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-2 self-stretch bg-indigo-600 hover:bg-indigo-700 text-white rounded-r-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                        title="{{ __('Crear artículo nuevo') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    </button>
                                                </div>
                                                @if($hayDropdown)
                                                    <div x-init="posicionarDd()" :style="ddPos"
                                                        @scroll.window.capture="if (!$el.contains($event.target)) posicionarDd()"
                                                        class="fixed max-h-56 overflow-auto bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
                                                        @foreach($renglon['resultados'] as $ri => $resultado)
                                                            <button type="button"
                                                                wire:click="seleccionarArticuloFila({{ $i }}, {{ $resultado['id'] }})"
                                                                :class="{{ $ri }} === highlight ? 'bg-bcn-primary/10 dark:bg-bcn-primary/20' : ''"
                                                                class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                <span class="font-medium">{{ $resultado['nombre'] }}</span>
                                                                <span class="text-xs text-gray-400">{{ $resultado['codigo'] }}</span>
                                                                @if($resultado['codigo_proveedor'])
                                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                                                        {{ __('Prov.') }}: {{ $resultado['codigo_proveedor'] }}
                                                                    </span>
                                                                @endif
                                                            </button>
                                                        @endforeach
                                                        @if(mb_strlen(trim($renglon['busqueda'])) >= 2)
                                                            <button type="button" wire:click="abrirAltaRapida({{ $i }})"
                                                                class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border-t border-gray-200 dark:border-gray-700">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                                {{ __('Crear artículo nuevo') }}
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <button type="button" wire:click="limpiarArticuloFila({{ $i }})"
                                                class="inline-flex items-center gap-1 group cursor-pointer text-left" title="{{ __('Cambiar artículo') }}">
                                                <span class="text-sm text-gray-900 dark:text-white font-medium">{{ $renglon['nombre'] }}</span>
                                                <span class="text-xs text-gray-400">{{ $renglon['codigo'] }}</span>
                                                @if($renglon['codigo_proveedor_usado'])
                                                    <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">{{ $renglon['codigo_proveedor_usado'] }}</span>
                                                @endif
                                                <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-amber-500 transition-opacity flex-shrink-0" aria-hidden="true">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </span>
                                            </button>
                                        @endif
                                    </td>

                                    {{-- Cantidad comprada (bultos) --}}
                                    <td class="px-2 py-1">
                                        <input type="text" wire:model.live.debounce.500ms="renglones.{{ $i }}.cantidad_comprada"
                                            data-cell data-fila="{{ $i }}" data-col="cantidad" @keydown.enter.prevent="avanzar($event)"
                                            @keydown.arrow-up.prevent="moverFila($event, -1)" @keydown.arrow-down.prevent="moverFila($event, 1)"
                                            @keydown.arrow-left="moverCol($event, -1)" @keydown.arrow-right="moverCol($event, 1)"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        @if(isset($renglon['max_cantidad']) && $this->num($renglon['cantidad_comprada']) > $renglon['max_cantidad'])
                                            <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">{{ __('Máx.') }}: {{ $renglon['max_cantidad'] }}</p>
                                        @endif
                                    </td>

                                    {{-- Factor de conversión (RF-16) --}}
                                    <td class="px-2 py-1">
                                        <input type="text" wire:model.live.debounce.500ms="renglones.{{ $i }}.factor_conversion"
                                            data-cell data-fila="{{ $i }}" data-col="factor" @keydown.enter.prevent="avanzar($event)"
                                            @keydown.arrow-up.prevent="moverFila($event, -1)" @keydown.arrow-down.prevent="moverFila($event, 1)"
                                            @keydown.arrow-left="moverCol($event, -1)" @keydown.arrow-right="moverCol($event, 1)"
                                            title="{{ __('Unidades de stock por bulto del proveedor') }}"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    </td>

                                    {{-- Cantidad stock (auto) --}}
                                    <td class="px-2 py-1 text-right text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format($calc['cantidad_stock'], 3, ',', '.'), '0'), ',') }}
                                    </td>

                                    {{-- Precio unitario (por bulto; neto si discrimina, final si no) --}}
                                    <td class="px-2 py-1">
                                        <input type="text" wire:model.live.debounce.500ms="renglones.{{ $i }}.precio_unitario"
                                            data-cell data-fila="{{ $i }}" data-col="precio" @keydown.enter.prevent="avanzar($event)"
                                            @keydown.arrow-up.prevent="moverFila($event, -1)" @keydown.arrow-down.prevent="moverFila($event, 1)"
                                            @keydown.arrow-left="moverCol($event, -1)" @keydown.arrow-right="moverCol($event, 1)"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    </td>

                                    {{-- Descuentos en cascada como texto (D7 #4) --}}
                                    <td class="px-2 py-1">
                                        <input type="text" wire:model.live.debounce.500ms="renglones.{{ $i }}.descuentos_texto"
                                            data-cell data-fila="{{ $i }}" data-col="desc" @keydown.enter.prevent="avanzar($event)"
                                            @keydown.arrow-up.prevent="moverFila($event, -1)" @keydown.arrow-down.prevent="moverFila($event, 1)"
                                            @keydown.arrow-left="moverCol($event, -1)" @keydown.arrow-right="moverCol($event, 1)"
                                            placeholder="10+5+3" title="{{ __('Descuentos en cascada, como los imprime la factura') }}"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    </td>

                                    {{-- Unitario efectivo (auto) --}}
                                    <td class="px-2 py-1 text-right text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        $@precio($calc['unitario_efectivo'])
                                    </td>

                                    {{-- Tipo de IVA del renglón --}}
                                    @if($this->esFiscalActual() && $this->discriminaActual())
                                        <td class="px-2 py-1">
                                            <select wire:model.live="renglones.{{ $i }}.tipo_iva_id"
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="">—</option>
                                                @foreach($tiposIva as $tipoIva)
                                                    <option value="{{ $tipoIva->id }}">{{ rtrim(rtrim(number_format($tipoIva->porcentaje, 2, '.', ''), '0'), '.') }}%</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif

                                    {{-- Subtotal --}}
                                    <td class="px-3 py-1 text-right text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                        $@precio($calc['subtotal'])
                                    </td>

                                    {{-- Quitar --}}
                                    <td class="px-2 py-1 text-center">
                                        <button type="button" wire:click="quitarRenglon({{ $i }})" tabindex="-1"
                                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="{{ __('Quitar renglón') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" wire:click="agregarRenglon"
                        class="inline-flex items-center gap-1 text-sm text-bcn-primary hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        {{ __('Agregar renglón') }}
                    </button>
                </div>
            </div>
            @endif

            {{-- ============ SECCIÓN FISCAL + TOTALES (D7 #5) ============ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                <div class="space-y-2">
                    {{-- Desglose de IVA (RF-14) — solo comprobantes que discriminan --}}
                    @if($this->esFiscalActual())
                        @if($this->discriminaActual())
                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-2 sm:p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ __('Desglose de IVA') }}
                                        <span class="font-normal text-xs text-gray-500 dark:text-gray-400">({{ __('editable — calzalo con la factura física') }})</span>
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        @if($fiscalManual)
                                            <button type="button" wire:click="recalcularDesgloseFiscal" class="text-xs text-bcn-primary hover:underline">{{ $esServicio ? __('Recalcular del detalle') : __('Recalcular de los renglones') }}</button>
                                        @endif
                                        <button type="button" wire:click="agregarIva" class="text-xs text-bcn-primary hover:underline">{{ __('+ Alícuota') }}</button>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="grid grid-cols-12 gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="col-span-3">{{ __('Alícuota (%)') }}</span>
                                        <span class="col-span-4 text-right">{{ __('Base imponible') }}</span>
                                        <span class="col-span-4 text-right">{{ __('IVA') }}</span>
                                    </div>
                                    @foreach($ivas as $index => $iva)
                                        <div class="grid grid-cols-12 gap-2 items-center" wire:key="iva-{{ $index }}">
                                            <input type="text" wire:model.live.debounce.500ms="ivas.{{ $index }}.alicuota"
                                                class="col-span-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            <input type="text" wire:model.live.debounce.500ms="ivas.{{ $index }}.base_imponible"
                                                class="col-span-4 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            <input type="text" wire:model.live.debounce.500ms="ivas.{{ $index }}.importe"
                                                class="col-span-4 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            <button type="button" wire:click="quitarIva({{ $index }})" tabindex="-1"
                                                class="col-span-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 justify-self-center" title="{{ __('Quitar') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    @endforeach
                                    @if($ivas === [])
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $esServicio ? __('Se calcula automáticamente al cargar el detalle con IVA') : __('Se calcula automáticamente al cargar renglones con IVA') }}</p>
                                    @endif
                                </div>
                                {{-- Netos del encabezado (Libro IVA Compras) --}}
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Neto no gravado') }}</label>
                                        <input type="text" wire:model.live.debounce.500ms="netoNoGravado" placeholder="0"
                                            class="mt-0.5 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Neto exento') }}</label>
                                        <input type="text" wire:model.live.debounce.500ms="netoExento" placeholder="0"
                                            class="mt-0.5 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Conceptos de pie de factura (RF-15) — colapsable. D23: en una
                        factura de servicio son EL detalle (visibles aun sin fiscal) --}}
                    @if($this->esFiscalActual() || $esServicio)
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            x-data="{ abierto: {{ ($conceptos !== [] || $esServicio) ? 'true' : 'false' }} }">
                            <button type="button" @click="abierto = !abierto"
                                class="w-full flex items-center justify-between px-3 sm:px-4 py-2 text-left">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $esServicio ? __('Detalle del servicio') : __('Conceptos del pie') }}
                                    <span class="font-normal text-xs text-gray-500 dark:text-gray-400">({{ $esServicio ? __('renglones libres: descripción + monto + IVA') : __('flete, imp. internos, envases...') }})</span>
                                    @if($conceptos !== [])
                                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-bcn-primary/10 text-bcn-primary">{{ count($conceptos) }}</span>
                                    @endif
                                </span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="abierto ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="abierto" x-collapse class="px-3 sm:px-4 pb-3 space-y-2">
                                @foreach($conceptos as $index => $concepto)
                                    <div class="flex flex-wrap items-center gap-2" wire:key="concepto-{{ $index }}">
                                        @if(! $esServicio)
                                            <select wire:model.live="conceptos.{{ $index }}.tipo"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="flete">{{ __('Flete') }}</option>
                                                <option value="impuestos_internos">{{ __('Imp. internos') }}</option>
                                                <option value="envases">{{ __('Envases') }}</option>
                                                <option value="otro">{{ __('Otro') }}</option>
                                            </select>
                                        @endif
                                        <input type="text" wire:model="conceptos.{{ $index }}.descripcion" placeholder="{{ __('Descripción') }}"
                                            class="flex-1 min-w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <input type="text" wire:model.live.debounce.500ms="conceptos.{{ $index }}.monto" placeholder="{{ __('Monto') }}"
                                            title="{{ $this->discriminaActual() ? __('Monto NETO (el IVA del concepto va al desglose)') : __('Monto final') }}"
                                            class="w-24 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        @if($this->discriminaActual())
                                            <select wire:model.live="conceptos.{{ $index }}.tipo_iva_id" title="{{ __('IVA del concepto (para la sugerencia del desglose)') }}"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="">{{ __('Sin IVA') }}</option>
                                                @foreach($tiposIva as $tipoIva)
                                                    <option value="{{ $tipoIva->id }}">{{ rtrim(rtrim(number_format($tipoIva->porcentaje, 2, '.', ''), '0'), '.') }}%</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @if(! $esServicio)
                                            <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400 cursor-pointer whitespace-nowrap"
                                                title="{{ __('Los conceptos que computan costo se prorratean a los renglones (landed cost)') }}">
                                                <input type="checkbox" wire:model.live="conceptos.{{ $index }}.computa_costo"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 w-3.5 h-3.5">
                                                {{ __('Computa costo') }}
                                            </label>
                                        @endif
                                        <button type="button" wire:click="quitarConcepto({{ $index }})" tabindex="-1"
                                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="{{ __('Quitar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="agregarConcepto" class="text-xs text-bcn-primary hover:underline">{{ $esServicio ? __('+ Agregar renglón de detalle') : __('+ Agregar concepto') }}</button>
                            </div>
                        </div>
                    @endif

                    {{-- Percepciones sufridas (RF-06) — colapsable --}}
                    @if($this->esFiscalActual())
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            x-data="{ abierto: {{ $percepciones !== [] ? 'true' : 'false' }} }">
                            <button type="button" @click="abierto = !abierto"
                                class="w-full flex items-center justify-between px-3 sm:px-4 py-2 text-left">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ __('Percepciones sufridas') }}
                                    @if($percepciones !== [])
                                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-bcn-primary/10 text-bcn-primary">{{ count($percepciones) }}</span>
                                    @endif
                                </span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="abierto ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="abierto" x-collapse class="px-3 sm:px-4 pb-3 space-y-2">
                                @foreach($percepciones as $index => $percepcion)
                                    <div class="flex flex-wrap items-end gap-2" wire:key="percepcion-{{ $index }}">
                                        <div class="flex-1 min-w-36">
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ __('Impuesto') }}</label>
                                            <select wire:model.live="percepciones.{{ $index }}.impuesto_id"
                                                data-cell @keydown.enter.prevent="avanzar($event)"
                                                class="mt-0.5 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="">{{ __('Impuesto...') }}</option>
                                                @foreach($impuestosPercepcion as $impuesto)
                                                    <option value="{{ $impuesto->id }}">{{ $impuesto->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-24">
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ __('Base') }}</label>
                                            <input type="text" wire:model="percepciones.{{ $index }}.base_imponible" placeholder="{{ __('Base') }}"
                                                wire:change="calcularMontoPercepcion({{ $index }})"
                                                data-cell @keydown.enter.prevent="avanzar($event)"
                                                class="mt-0.5 w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </div>
                                        <div class="w-16">
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ __('Alíc. %') }}</label>
                                            <input type="text" wire:model="percepciones.{{ $index }}.alicuota" placeholder="%"
                                                wire:change="calcularMontoPercepcion({{ $index }})"
                                                data-cell @keydown.enter.prevent="avanzar($event)"
                                                class="mt-0.5 w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </div>
                                        <div class="w-24">
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ __('Monto') }}</label>
                                            <input type="text" wire:model.live.debounce.500ms="percepciones.{{ $index }}.monto" placeholder="{{ __('Monto') }}"
                                                data-cell @keydown.enter.prevent="avanzar($event)"
                                                class="mt-0.5 w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </div>
                                        <div class="w-16">
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400" title="{{ __('Parte computable como crédito fiscal (0 a 1): monto × coeficiente va al ledger fiscal, el resto al costo. Default: config del CUIT por jurisdicción.') }}">{{ __('Coef.') }}</label>
                                            <input type="number" step="0.0001" min="0" max="1" wire:model.live.debounce.500ms="percepciones.{{ $index }}.coeficiente" placeholder="0-1"
                                                title="{{ __('Parte computable como crédito fiscal (0 a 1): monto × coeficiente va al ledger fiscal, el resto al costo. Default: config del CUIT por jurisdicción.') }}"
                                                data-cell @keydown.enter.prevent="avanzar($event)"
                                                class="mt-0.5 w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </div>
                                        @if(!empty($percepcion['con_certificado']))
                                            <div class="w-28">
                                                <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400" title="{{ __('N° de constancia que respalda la percepción o retención (opcional; el respaldo habitual es la propia factura)') }}">{{ __('Certificado') }}</label>
                                                <input type="text" wire:model="percepciones.{{ $index }}.certificado_numero" placeholder="{{ __('Certificado') }}"
                                                    data-cell @keydown.enter.prevent="avanzar($event)"
                                                    class="mt-0.5 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            </div>
                                        @else
                                            <button type="button" wire:click="mostrarCertificadoPercepcion({{ $index }})" tabindex="-1"
                                                class="pb-2 text-[11px] text-gray-400 hover:text-bcn-primary dark:hover:text-bcn-primary whitespace-nowrap"
                                                title="{{ __('N° de constancia que respalda la percepción o retención (opcional; el respaldo habitual es la propia factura)') }}">
                                                + {{ __('certificado') }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="quitarPercepcion({{ $index }})" tabindex="-1"
                                            class="pb-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="{{ __('Quitar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="agregarPercepcion" class="text-xs text-bcn-primary hover:underline">{{ __('+ Agregar percepción') }}</button>
                            </div>
                        </div>
                    @else
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Compra no fiscal: sin cálculo de impuestos — el total pagado es el costo (D15)') }}
                            </p>
                        </div>
                    @endif
                </div>


                {{-- Pie: la cuenta completa, para verificar contra la factura en vivo (D7 #5) --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-2 sm:p-3 h-fit lg:sticky lg:top-0">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Totales del comprobante') }}</h4>
                    <dl class="space-y-1 text-sm">
                        @if(! $esServicio)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ $this->discriminaActual() ? __('Neto (renglones c/desc.)') : __('Subtotal (renglones c/desc.)') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($totales['subtotal'])</dd>
                            </div>
                        @endif
                        @if($totales['descuento_global'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('Descuento global') }} ({{ $descuentoGlobal }}%)</dt>
                                <dd class="text-red-600 dark:text-red-400">−$@precio($totales['descuento_global'])</dd>
                            </div>
                        @endif
                        @if($totales['conceptos'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ $esServicio ? __('Detalle del servicio') : __('Conceptos') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($totales['conceptos'])</dd>
                            </div>
                        @endif
                        @if($this->esFiscalActual() && $this->discriminaActual())
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('IVA') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($totales['iva'])</dd>
                            </div>
                        @endif
                        @if($totales['percepciones'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('Percepciones') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($totales['percepciones'])</dd>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5">
                            <dt class="font-bold text-gray-900 dark:text-white">{{ __('Total') }}</dt>
                            <dd class="font-bold text-lg text-gray-900 dark:text-white">$@precio($totales['total'])</dd>
                        </div>
                    </dl>
                    @if($proveedorSeleccionado)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $proveedorSeleccionado->tiene_cuenta_corriente
                                ? __('Proveedor con cuenta corriente')
                                : __('Proveedor sin cuenta corriente (solo contado)') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer de acciones --}}
        <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 px-3 sm:px-4 py-2 flex flex-wrap items-center justify-end gap-2 bg-gray-50 dark:bg-gray-800 rounded-b-lg">
            <button type="button" wire:click="cerrar"
                class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                {{ __('Cancelar') }}
            </button>
            @if(! $modoCorreccion && auth()->user()?->hasPermissionTo('func.compras.crear'))
                <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Guardar borrador') }}
                    <kbd class="hidden sm:inline ml-1.5 px-1 py-0 text-[9px] bg-gray-200 dark:bg-gray-600 rounded">Ctrl+G</kbd>
                </button>
            @endif
            @if(auth()->user()?->hasPermissionTo('func.compras.confirmar'))
                <button type="button" wire:click="abrirModalPago" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-sm font-medium text-white hover:bg-green-700">
                    {{ $modoCorreccion ? __('Guardar corrección') : ($esNC ? __('Confirmar nota de crédito') : __('Confirmar compra')) }}
                    <kbd class="hidden sm:inline ml-1.5 px-1 py-0 text-[9px] bg-black/20 rounded">F2</kbd>
                </button>
            @endif
        </div>
    </div>
</div>

{{-- ============ MODAL DE PAGO AL CONFIRMAR (D7 #6) ============ --}}
@if($mostrarModalPago)
    <x-bcn-modal :title="$modoCorreccion ? __('Guardar corrección — Pago') : __('Confirmar compra — Pago')" color="bg-green-600" maxWidth="2xl" onClose="cerrarModalPago">
        <x-slot:body>
            <div class="space-y-4">
                <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-700 px-4 py-3">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Total de la compra') }}</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">$@precio($totales['total'])</span>
                </div>

                @if($modoCorreccion)
                    <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 px-4 py-3 space-y-2">
                        <p class="text-xs text-amber-800 dark:text-amber-200">
                            {{ __('Por detrás se cancela la compra original y se recrea con estos datos (todo en una sola operación, con contraasientos)') }}
                        </p>
                        @if($correccionTienePagos)
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                                {{ __('La compra original tiene pagos por') }} $@precio($pagadoActivo) — {{ __('elegí qué hacer con ellos (D17)') }}
                            </p>
                            <label class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-200 cursor-pointer">
                                <input type="radio" wire:model.live="manejoPagosCorreccion" value="saldo_favor" class="mt-0.5 border-amber-400 text-amber-600 focus:ring-amber-500">
                                <span><strong>{{ __('Dejar como saldo a favor') }}</strong> — {{ __('podés consumirlo como pago de la compra corregida acá abajo') }}</span>
                            </label>
                            <label class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-200 cursor-pointer">
                                <input type="radio" wire:model.live="manejoPagosCorreccion" value="anular_pagos" class="mt-0.5 border-amber-400 text-amber-600 focus:ring-amber-500">
                                <span><strong>{{ __('Anular los pagos en cascada') }}</strong> — {{ __('error de carga: se anula cada orden de pago completa (bloqueado si el turno de caja está cerrado)') }}</span>
                            </label>
                        @endif
                    </div>
                @endif

                {{-- Modalidad: cta cte / contado --}}
                @if($proveedorSeleccionado?->tiene_cuenta_corriente)
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('modalidadPago', 'cta_cte')"
                            class="flex-1 px-3 py-2 rounded-md text-sm font-medium border transition-colors
                                {{ $modalidadPago === 'cta_cte'
                                    ? 'bg-green-600 text-white border-green-600'
                                    : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                            {{ __('Cuenta corriente') }}
                        </button>
                        <button type="button" wire:click="$set('modalidadPago', 'contado')"
                            class="flex-1 px-3 py-2 rounded-md text-sm font-medium border transition-colors
                                {{ $modalidadPago === 'contado'
                                    ? 'bg-green-600 text-white border-green-600'
                                    : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                            {{ __('Contado') }}
                        </button>
                    </div>
                @endif

                @if($modalidadPago === 'cta_cte')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Vencimiento') }}</label>
                            <input type="date" wire:model="fechaVencimiento"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer pb-2">
                                <input type="checkbox" wire:model.live="registrarPagoAhora"
                                    class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700">
                                {{ __('Registrar pago inicial parcial') }}
                            </label>
                        </div>
                    </div>
                @else
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input type="checkbox" wire:model.live="registrarPagoAhora"
                            class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700">
                        {{ __('Registrar el pago ahora') }}
                    </label>
                    @if(! $registrarPagoAhora)
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('La compra queda completada con saldo pendiente; se paga luego desde Pagos a Proveedores') }}</p>
                    @endif
                @endif

                @if($registrarPagoAhora)
                    @php $saldoFavorMostrar = $this->saldoFavorProyectado(); @endphp
                    @if($saldoFavorMostrar > 0)
                        <div class="flex items-center gap-3 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                            <p class="text-sm text-green-700 dark:text-green-300 flex-1">
                                {{ __('Saldo a favor disponible') }}: <strong>$@precio($saldoFavorMostrar)</strong>
                                @if($modoCorreccion && $correccionTienePagos && $manejoPagosCorreccion === 'saldo_favor' && $pagadoActivo > 0)
                                    <span class="text-xs">({{ __('incluye lo pagado de la original') }})</span>
                                @endif
                            </p>
                            <label class="text-sm text-green-700 dark:text-green-300">{{ __('Usar') }}</label>
                            <input type="text" wire:model.live.debounce.500ms="saldoFavorUsado"
                                class="w-28 rounded-md border-green-300 dark:border-green-700 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                    @endif

                    {{-- Desglose de formas de pago (patrón GestionarPagosProveedores, D14) --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Formas de pago') }}</h4>
                            <button type="button" wire:click="agregarRenglonPago" class="text-xs text-green-600 hover:underline">{{ __('+ Agregar renglón') }}</button>
                        </div>
                        <div class="space-y-2">
                            @foreach($pagosIniciales as $index => $pago)
                                <div class="flex flex-wrap items-center gap-2" wire:key="pago-{{ $index }}">
                                    <select wire:model="pagosIniciales.{{ $index }}.forma_pago_id"
                                        class="flex-1 min-w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="">{{ __('Forma de pago...') }}</option>
                                        @foreach($formasPago as $fp)
                                            <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model.live.debounce.500ms="pagosIniciales.{{ $index }}.monto" placeholder="{{ __('Monto') }}"
                                        class="w-28 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                    @if($this->puedePagarAvanzado())
                                        <select wire:model.live="pagosIniciales.{{ $index }}.origen"
                                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                            <option value="caja">{{ __('Caja') }}</option>
                                            <option value="tesoreria">{{ __('Tesorería') }}</option>
                                            <option value="cuenta_empresa">{{ __('Cuenta de empresa') }}</option>
                                        </select>
                                        @if(($pago['origen'] ?? 'caja') === 'caja')
                                            <select wire:model="pagosIniciales.{{ $index }}.caja_id"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                                @foreach($cajasDisponibles as $caja)
                                                    <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($pago['origen'] ?? '') === 'cuenta_empresa')
                                            <select wire:model="pagosIniciales.{{ $index }}.cuenta_empresa_id"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                                <option value="">{{ __('Cuenta...') }}</option>
                                                @foreach($cuentasEmpresa as $cuenta)
                                                    <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif
                                    @if(count($pagosIniciales) > 1)
                                        <button type="button" wire:click="quitarRenglonPago({{ $index }})"
                                            class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if(! $this->puedePagarAvanzado())
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('El pago sale de la caja activa (para otros orígenes se requiere el permiso de pago avanzado)') }}</p>
                        @endif

                        @php
                            $fondos = $this->fondosCargados();
                            $restante = round($totales['total'] - $fondos, 2);
                        @endphp
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">{{ __('Cargado') }}: <strong class="text-gray-900 dark:text-white">$@precio($fondos)</strong></span>
                            @if($modalidadPago === 'contado')
                                <span class="{{ abs($restante) <= 0.01 ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                    {{ __('Restante') }}: <strong>$@precio($restante)</strong>
                                </span>
                            @else
                                <span class="text-gray-600 dark:text-gray-400">{{ __('Queda en cuenta') }}: <strong class="text-gray-900 dark:text-white">$@precio(max($restante, 0))</strong></span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                {{ __('Volver') }}
            </button>
            <button type="button" wire:click="confirmar" wire:loading.attr="disabled"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Confirmar compra') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif

{{-- ============ MODAL RESUMEN POST-CONFIRMACIÓN (D7 #8) ============ --}}
@if($mostrarModalResumen)
    <x-bcn-modal :title="$resumen['es_nc'] ? __('Nota de crédito confirmada') : ($modoCorreccion ? __('Compra corregida') : __('Compra confirmada'))" color="bg-green-600" maxWidth="lg" onClose="cerrarResumen">
        <x-slot:body>
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                    <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-green-800 dark:text-green-200">{{ $resumen['numero'] ?? '' }}</p>
                        <p class="text-sm text-green-700 dark:text-green-300">
                            {{ __('Total') }}: <strong>$@precio($resumen['total'] ?? 0)</strong>
                            @if(($resumen['saldo_pendiente'] ?? 0) > 0)
                                · {{ __('Saldo pendiente') }}: <strong>$@precio($resumen['saldo_pendiente'])</strong>
                            @endif
                        </p>
                    </div>
                </div>

                @if($articulosRepriceados !== [])
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 px-4 py-3">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">
                            {{ trans_choice(':n artículo se repriceó automáticamente|:n artículos se repricearon automáticamente', count($articulosRepriceados), ['n' => count($articulosRepriceados)]) }}
                        </p>
                        <ul class="space-y-1">
                            @foreach($articulosRepriceados as $item)
                                <li class="text-xs text-blue-800 dark:text-blue-200 flex justify-between gap-2">
                                    <span>{{ $item['nombre'] }}</span>
                                    <span class="whitespace-nowrap">
                                        $@precio($item['precio_anterior']) → <strong>$@precio($item['precio_nuevo'])</strong>
                                        <span class="text-blue-500">({{ $item['alcance'] === 'sucursal' ? __('sucursal') : __('global') }})</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($articulosBajoMargen !== [])
                    <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 px-4 py-3">
                        <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                            {{ trans_choice(':n artículo quedó bajo el margen objetivo|:n artículos quedaron bajo el margen objetivo', count($articulosBajoMargen), ['n' => count($articulosBajoMargen)]) }}
                        </p>
                        <ul class="space-y-1">
                            @foreach($articulosBajoMargen as $item)
                                <li class="text-xs text-yellow-800 dark:text-yellow-200 flex justify-between gap-2">
                                    <span>{{ $item['nombre'] }}</span>
                                    <span class="whitespace-nowrap">{{ __('Margen') }}: {{ $item['margen_real'] }}% / {{ __('objetivo') }} {{ $item['objetivo'] }}%</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-yellow-700 dark:text-yellow-300">
                            {{ __('La revisión de precios es retomable desde el detalle de la compra (no bloquea)') }}
                        </p>
                    </div>
                @endif
            </div>
        </x-slot:body>
        <x-slot:footer>
            @if($articulosBajoMargen !== [] && ($resumen['compra_id'] ?? null))
                <button type="button" wire:click="abrirRevisionPrecios"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-teal-600 text-base font-medium text-white hover:bg-teal-700 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                    {{ __('Revisar precios') }}
                </button>
            @endif
            <button type="button" wire:click="cerrarResumen"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Aceptar') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif

{{-- ============ MODAL ALTA RÁPIDA DE ARTÍCULO (D7 #3) ============ --}}
@if($mostrarModalArticuloRapido)
    <x-bcn-modal :title="__('Nuevo artículo (alta rápida)')" color="bg-indigo-600" maxWidth="md" onClose="cerrarAltaRapida" submit="guardarArticuloRapido">
        <x-slot:body>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                    <input type="text" wire:model="artRapidoNombre" x-init="$nextTick(() => $el.focus())"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                    @error('artRapidoNombre') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</label>
                        <select wire:model.live="artRapidoCategoriaId"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="">{{ __('Sin categoría') }}</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('IVA') }} *</label>
                        <select wire:model="artRapidoTipoIvaId"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            @foreach($tiposIva as $tipoIva)
                                <option value="{{ $tipoIva->id }}">{{ $tipoIva->nombre }}</option>
                            @endforeach
                        </select>
                        @error('artRapidoTipoIvaId') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código propio') }} *</label>
                        <input type="text" wire:model="artRapidoCodigo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                        @error('artRapidoCodigo') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código del proveedor') }}</label>
                        <input type="text" wire:model="artRapidoCodigoProveedor"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('Se guarda para buscar por este código') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Precio de venta') }}</label>
                        <input type="text" wire:model="artRapidoPrecioVenta" placeholder="{{ __('Opcional') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                    </div>
                </div>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                {{ __('Cancelar') }}
            </button>
            <button type="submit"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Crear artículo') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif

{{-- ============ MODAL ALTA RÁPIDA DE PROVEEDOR ============ --}}
@if($mostrarModalProveedorRapido)
    <x-bcn-modal :title="__('Nuevo proveedor (alta rápida)')" color="bg-indigo-600" maxWidth="md" onClose="cerrarProveedorRapido" submit="guardarProveedorRapido">
        <x-slot:body>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                    <input type="text" wire:model="provRapidoNombre" x-init="$nextTick(() => $el.focus())"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                    @error('provRapidoNombre') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('CUIT') }}</label>
                        <input type="text" wire:model="provRapidoCuit" placeholder="30-12345678-9"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Condición IVA') }}</label>
                        <select wire:model="provRapidoCondicionIvaId"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="">{{ __('Sin especificar') }}</option>
                            @foreach($condicionesIva as $condicion)
                                <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('Define la letra de comprobante sugerida') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Días de pago') }}</label>
                        <input type="number" wire:model="provRapidoDiasPago" min="0" max="365" placeholder="{{ __('Opcional') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                        @error('provRapidoDiasPago') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cuenta de compra') }}</label>
                        <select wire:model="provRapidoCuentaCompraId"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="">{{ __('Sin clasificar') }}</option>
                            @foreach($cuentasCompra as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input type="checkbox" wire:model="provRapidoCuentaCorriente"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
                    {{ __('Tiene cuenta corriente') }}
                </label>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                {{ __('Cancelar') }}
            </button>
            <button type="submit"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Crear proveedor') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif

{{-- ============ MODAL BÚSQUEDA AVANZADA DE ARTÍCULOS (lupa, patrón ventas) ============ --}}
@include('livewire.carrito._modal-busqueda-articulos')
</div>
