{{-- Lista/tabla de items del carrito con cantidad, ajustes, opcionales (reutilizable) --}}
<div class="flex-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden flex flex-col min-h-0">
    <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h4 class="text-xs font-medium text-gray-900 dark:text-white">{{ __('Items') }} ({{ count($items) }})</h4>
        @if($resultado && $resultado['subtotal'] > 0)
            <span class="text-xs text-gray-600">{{ __('Subt') }}: $@precio($resultado['subtotal'])</span>
        @endif
    </div>

    @if(empty($items))
        <div class="py-4 text-center text-gray-500">
            <svg class="mx-auto h-8 w-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="text-xs">{{ __('Agrega artículos para comenzar') }}</p>
        </div>
    @else
        <div class="flex-1 overflow-y-auto min-h-0"
             x-ref="carritoScroll"
             @scroll-carrito-abajo.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
             @auto-clear-resaltado.window="setTimeout(() => $wire.limpiarResaltado(), 5000)">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                    <tr>
                        <th class="px-2 py-1 w-8"></th>
                        <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículo') }}</th>
                        <th class="px-2 py-1 text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase w-20">{{ __('Cant.') }}</th>
                        <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('P.Unit') }}</th>
                        <th class="px-1 py-1 w-12"></th>{{-- Columna para ajuste manual --}}
                        <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Subt.') }}</th>
                        <th class="px-2 py-1 text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase w-16">{{ __('Promo') }}</th>
                        <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($items as $index => $item)
                        @php
                            $itemResultado = $resultado['items'][$index] ?? null;
                            $tienePromoEspecial = $itemResultado && $itemResultado['unidades_consumidas'] > 0;
                            $tienePromoComun = $itemResultado && !empty($itemResultado['promociones_comunes']);
                            $tienePromo = $tienePromoEspecial || $tienePromoComun;
                            $excluido = $itemResultado && $itemResultado['excluido_promociones'];
                            $tieneAjuste = $item['tiene_ajuste'] ?? false;
                            $esDescuento = $tieneAjuste && $item['precio'] < $item['precio_base'];
                            $esRecargo = $tieneAjuste && $item['precio'] > $item['precio_base'];
                            $esInvitacion = !empty($item['es_invitacion']);
                            $precioInvitacionOriginal = (float) ($item['precio_unitario_original'] ?? $item['precio_base'] ?? 0);
                            $tooltipInvitacion = $esInvitacion
                                ? __('Invitado') . ($item['invitacion_motivo'] ?? '' ? ' — ' . $item['invitacion_motivo'] : '')
                                : '';
                        @endphp
                        <tr data-item-index="{{ $index }}"
                            class="transition-colors duration-300 {{ $esInvitacion ? 'bg-emerald-50 dark:bg-emerald-900/30' : ($tienePromo ? 'bg-green-50 dark:bg-green-900/30' : ($excluido ? 'bg-yellow-50 dark:bg-yellow-900/30' : '')) }} {{ $itemResaltado === $index ? 'bg-yellow-200 dark:bg-yellow-700 animate-pulse' : '' }}">
                            {{-- Acciones: solo Eliminar (el botón Invitar se movió
                                a la columna Total para alejarlo del eliminar y
                                evitar mis-taps en pantalla táctil). --}}
                            <td class="px-2 py-1.5">
                                <div class="flex items-center justify-center">
                                    <button wire:click="eliminarItem({{ $index }})" class="text-red-600 hover:text-red-800 p-1" title="{{ __('Eliminar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            {{-- Artículo --}}
                            <td class="px-2 py-1.5 relative">
                                {{-- Click en todo el bloque (texto + icono) = editar
                                    nombre. Touch-friendly. El lapiz aparece en hover
                                    como pista visual pero NO es un button separado. --}}
                                <button type="button"
                                        wire:click="abrirEditarNombre({{ $index }})"
                                        class="flex items-center gap-0.5 group text-left max-w-full hover:text-indigo-600 dark:hover:text-indigo-400"
                                        title="{{ $item['nombre'] }}">
                                    <span class="text-xs font-medium text-gray-900 dark:text-white truncate max-w-[180px] group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        {{ $item['nombre'] }}
                                    </span>
                                    <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-opacity flex-shrink-0"
                                          aria-hidden="true">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                </button>
                                {{-- Popover de editar nombre — mismo formato que el
                                    selector de descuentos: caja fixed, pico tipo
                                    tooltip alineado a la izquierda (porque la columna
                                    nombre alinea a la izquierda). Anchor al boton del
                                    nombre para que salga justo debajo del texto. --}}
                                @if($editarNombreIndex === $index)
                                    <div class="fixed z-[100] w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2"
                                         x-data="{
                                             init() {
                                                 this.$nextTick(() => {
                                                     const nameBtn = this.$el.parentElement.querySelector('button[wire\\:click^=&quot;abrirEditarNombre&quot;]');
                                                     const target = nameBtn || this.$el.parentElement;
                                                     const rect = target.getBoundingClientRect();
                                                     this.$el.style.top = (rect.bottom + 6) + 'px';
                                                     this.$el.style.left = rect.left + 'px';
                                                     this.$refs.nombreInput.focus();
                                                     this.$refs.nombreInput.select();
                                                 });
                                             }
                                         }"
                                         @click.outside="$wire.cerrarEditarNombre()">
                                        {{-- Pico tipo tooltip a la izquierda. --}}
                                        <div class="absolute left-3 w-3 h-3 bg-white dark:bg-gray-800 border-l border-t border-gray-200 dark:border-gray-700"
                                             style="top: -7px; transform: rotate(45deg);"
                                             aria-hidden="true"></div>
                                        <div class="text-left text-[10px] font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            {{ __('Editar nombre') }}
                                        </div>
                                        <div class="flex gap-1">
                                            <input
                                                x-ref="nombreInput"
                                                type="text"
                                                wire:model="editarNombreValor"
                                                wire:keydown.enter="aplicarEditarNombre"
                                                wire:keydown.escape="cerrarEditarNombre"
                                                maxlength="120"
                                                class="flex-1 w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                            />
                                            <button
                                                wire:click="aplicarEditarNombre"
                                                class="px-2 py-1 text-[10px] font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">
                                                OK
                                            </button>
                                        </div>
                                    </div>
                                @endif
                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ $item['codigo'] }}@if($item['categoria_nombre']) <span class="text-indigo-600 dark:text-indigo-400">| {{ $item['categoria_nombre'] }}</span>@endif</div>
                                @if(!empty($item['opcionales']))
                                    @php
                                        $tooltipOpcionales = collect($item['opcionales'])
                                            ->map(fn($g) => $g['grupo_nombre'] . ': ' . collect($g['selecciones'])->map(fn($s) => $s['cantidad'] > 1 ? $s['nombre'].' x'.$s['cantidad'] : $s['nombre'])->join(', '))
                                            ->join("\n");
                                        $resumenOpcionales = collect($item['opcionales'])
                                            ->flatMap(fn($g) => collect($g['selecciones'])->map(fn($s) => $s['cantidad'] > 1 ? $s['nombre'].' x'.$s['cantidad'] : $s['nombre']))
                                            ->join(', ');
                                    @endphp
                                    <div class="text-[10px] text-orange-600 dark:text-orange-400 flex items-center gap-1 cursor-pointer" title="{{ $tooltipOpcionales }}">
                                        <span class="truncate max-w-[170px]">
                                            {{ $resumenOpcionales }}
                                        </span>
                                        @if(($item['precio_opcionales'] ?? 0) > 0)
                                            <span class="text-green-600 dark:text-green-400 whitespace-nowrap">(+$@precio($item['precio_opcionales']))</span>
                                        @endif
                                        <button wire:click="editarOpcionalesItem({{ $index }})" class="text-orange-500 hover:text-orange-700 ml-0.5" title="{{ __('Editar opcionales') }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </div>
                                @endif
                                @if($tienePromoEspecial && !empty($itemResultado['promociones_especiales']))
                                    <div class="text-[10px] text-green-600">{{ implode(', ', array_map(fn($p) => is_array($p) ? ($p['nombre'] ?? '') : $p, $itemResultado['promociones_especiales'])) }}</div>
                                @endif
                                @if($tienePromoComun && !empty($itemResultado['promociones_comunes']))
                                    <div class="text-[10px] text-blue-600">{{ implode(', ', array_map(fn($p) => is_array($p) ? ($p['nombre'] ?? '') : $p, $itemResultado['promociones_comunes'])) }}</div>
                                @endif
                                @if($excluido && !$esInvitacion)
                                    <div class="text-[10px] text-yellow-600">{{ __('Sin promos') }}</div>
                                @endif
                                @if($esInvitacion)
                                    {{-- Badge "Invitado". El motivo va como tooltip
                                        ($tooltipInvitacion) para no ocupar lugar en
                                        el detalle del carrito. --}}
                                    <div class="mt-0.5 inline-flex items-center gap-1">
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 cursor-help"
                                              title="{{ $tooltipInvitacion }}">
                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                                            </svg>
                                            {{ __('Invitado') }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            {{-- Cantidad — input mas alto + botones mas anchos
                                (touch-friendly). El input mantiene su w-10 para no
                                quedar desproporcionado; los botones suman ancho via
                                px-3 y suben su py-1.5 para alinearse con el input. --}}
                            <td class="px-2 py-1.5 text-center">
                                <div class="inline-flex items-center">
                                    <button type="button" wire:click="actualizarCantidad({{ $index }}, {{ max(1, $item['cantidad'] - 1) }})" class="px-3 py-1.5 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-500 text-xs font-bold leading-4">&minus;</button>
                                    <input wire:change="actualizarCantidad({{ $index }}, $event.target.value)" type="number" min="1" value="{{ $item['cantidad'] }}" class="w-10 px-0.5 py-1.5 text-xs border-y border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                    <button type="button" wire:click="actualizarCantidad({{ $index }}, {{ $item['cantidad'] + 1 }})" class="px-3 py-1.5 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-500 text-xs font-bold leading-4">+</button>
                                </div>
                            </td>
                            {{-- Precio — clickeable: abre selector de ajuste ($/% / Pts).
                                Mantiene los colores actuales (verde/rojo/amarillo) cuando
                                hay ajuste/canje, y un icono tag aparece en hover como
                                pista visual. Item invitado NO es clickeable (precio=0). --}}
                            <td class="px-2 py-1.5 text-right relative">
                                @php
                                    $tieneAjusteManual = ($item['ajuste_manual_tipo'] ?? null) !== null;
                                    $precioMostrarTachado = $tieneAjusteManual
                                        ? ($item['precio_sin_ajuste_manual'] ?? $item['precio_base'])
                                        : $item['precio_base'];
                                    $pagadoConPuntos = $item['pagado_con_puntos'] ?? false;
                                    $precioColor = $esInvitacion
                                        ? 'text-emerald-600'
                                        : ($pagadoConPuntos
                                            ? 'text-yellow-700 dark:text-yellow-400'
                                            : (($tieneAjuste || $tieneAjusteManual)
                                                ? ($item['precio'] < $precioMostrarTachado ? 'text-green-600' : 'text-red-600')
                                                : 'text-gray-900 dark:text-white'));
                                @endphp
                                @if($esInvitacion)
                                    <div class="text-xs font-medium text-emerald-600">$0.00</div>
                                    <div class="text-[10px] text-gray-400 line-through">$@precio($precioInvitacionOriginal)</div>
                                @else
                                    <button type="button"
                                            wire:click="abrirSelectorAjuste({{ $index }})"
                                            class="group flex items-center justify-end gap-1 w-full hover:underline {{ $precioColor }}"
                                            title="{{ __('Aplicar descuento / canje') }}">
                                        <span class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0 text-gray-400 group-hover:text-indigo-500"
                                              aria-hidden="true">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                        </span>
                                        <span class="text-xs font-medium">$@precio($item['precio'])</span>
                                    </button>
                                    @if($tieneAjuste || $tieneAjusteManual)
                                        <div class="text-[10px] text-gray-400 line-through">$@precio($precioMostrarTachado)</div>
                                    @endif
                                @endif

                                {{-- Popover unificado de descuentos. Tiene dos fases:
                                    1) Selector: 3 botones $/%/Pts (selectorAjusteIndex)
                                    2) Input: input + OK + back (ajusteManualPopoverIndex)
                                    Mantener un solo <div> evita el "flash" entre fases:
                                    el contenedor no se desmonta, solo cambia el contenido. --}}
                                @if($selectorAjusteIndex === $index || $ajusteManualPopoverIndex === $index)
                                    @php
                                        $vpcSel = $this->valorPuntoCanje;
                                        $puedeCanjearPts = $clienteSeleccionado
                                            && $puntosDisponibles
                                            && $vpcSel > 0;
                                        $ptsNecesariosSel = $puedeCanjearPts
                                            ? (int) ceil(($item['precio'] ?? 0) / $vpcSel) * ($item['cantidad'] ?? 1)
                                            : 0;
                                        $faseInput = $ajusteManualPopoverIndex === $index;
                                    @endphp
                                    <div wire:key="popup-descuento-{{ $index }}"
                                         class="fixed z-[100] w-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2"
                                         x-data="{
                                             topPx: 0,
                                             rightPx: 0,
                                             calcPos() {
                                                 const priceBtn = this.$el.parentElement.querySelector('button[wire\\:click^=&quot;abrirSelectorAjuste&quot;]');
                                                 const target = priceBtn || this.$el.parentElement;
                                                 const rect = target.getBoundingClientRect();
                                                 this.topPx = rect.bottom + 6;
                                                 this.rightPx = window.innerWidth - rect.right;
                                             },
                                             init() {
                                                 this.$nextTick(() => this.calcPos());
                                             }
                                         }"
                                         :style="`top: ${topPx}px; right: ${rightPx}px;`"
                                         @click.outside="$wire.cerrarPopupDescuentos()">
                                        {{-- Pico tipo tooltip. --}}
                                        <div class="absolute right-3 w-3 h-3 bg-white dark:bg-gray-800 border-l border-t border-gray-200 dark:border-gray-700"
                                             style="top: -7px; transform: rotate(45deg);"
                                             aria-hidden="true"></div>

                                        @if($faseInput)
                                            {{-- FASE INPUT: input + OK, con botón back
                                                para volver al selector. --}}
                                            <div class="flex items-center justify-between mb-1.5">
                                                <button type="button"
                                                        wire:click="abrirSelectorAjuste({{ $index }})"
                                                        class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                                        title="{{ __('Volver') }}">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                                                    </svg>
                                                </button>
                                                <div class="text-[10px] font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                    @if($ajusteManualTipo === 'monto')
                                                        {{ __('Nuevo precio ($)') }}
                                                    @else
                                                        {{ __('Descuento % (+ desc / - rec)') }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex gap-1">
                                                <input
                                                    type="number"
                                                    wire:model="ajusteManualValor"
                                                    wire:keydown.enter="aplicarAjusteManual"
                                                    wire:keydown.escape="cerrarPopupDescuentos"
                                                    x-init="$nextTick(() => { $el.focus(); $el.select?.(); })"
                                                    class="flex-1 w-40 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                    step="{{ $ajusteManualTipo === 'monto' ? '0.01' : '1' }}"
                                                    placeholder="{{ $ajusteManualTipo === 'monto' ? 'Ej: 1500' : 'Ej: 10' }}">
                                                <button
                                                    wire:click="aplicarAjusteManual"
                                                    class="px-2 py-1 text-[10px] font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">
                                                    OK
                                                </button>
                                            </div>
                                            <div class="text-[9px] text-gray-500 dark:text-gray-400 mt-1">
                                                @if($ajusteManualTipo === 'monto')
                                                    {{ __('Base') }}: $@precio($item['precio_base'])
                                                @else
                                                    {{ __('Ej: 10 = -10% desc') }}
                                                @endif
                                            </div>
                                        @else
                                            {{-- FASE SELECTOR: 3 botones $/%/Pts. --}}
                                            <div class="text-left text-[10px] font-medium text-gray-700 dark:text-gray-300 mb-1.5 whitespace-nowrap">
                                                {{ __('Descuentos') }}
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <button
                                                    wire:click="abrirAjusteManual({{ $index }}, 'monto')"
                                                    type="button"
                                                    class="p-2 rounded-md transition-colors border bg-blue-50 text-blue-700 border-blue-300 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700 h-8 w-8 flex items-center justify-center text-sm font-bold leading-none"
                                                    title="{{ __('Establecer precio fijo ($)') }}">
                                                    $
                                                </button>
                                                <button
                                                    wire:click="abrirAjusteManual({{ $index }}, 'porcentaje')"
                                                    type="button"
                                                    class="p-2 rounded-md transition-colors border bg-green-50 text-green-700 border-green-300 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700 h-8 w-8 flex items-center justify-center text-sm font-bold leading-none"
                                                    title="{{ __('Aplicar descuento %') }}">
                                                    %
                                                </button>
                                                @if($puedeCanjearPts)
                                                    <button
                                                        wire:click="seleccionarCanjePuntos({{ $index }})"
                                                        type="button"
                                                        class="p-2 rounded-md transition-colors border bg-yellow-50 text-yellow-700 border-yellow-300 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700 h-8 w-8 flex items-center justify-center"
                                                        title="{{ __('Canjear con puntos') }} ({{ $ptsNecesariosSel }} pts)">
                                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            {{-- Columna "Ajuste/Canje" — ahora solo muestra el badge
                                para QUITAR el descuento aplicado. Los 3 botones de
                                aplicar ($/%/Pts) se movieron al popup del precio
                                unitario (abrirSelectorAjuste). --}}
                            <td class="px-1 py-1.5 relative">
                                @if($esInvitacion)
                                    <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                @elseif($item['pagado_con_puntos'] ?? false)
                                    @php
                                        $vpc = $this->valorPuntoCanje;
                                        $ptsCanje = $vpc > 0 ? (int) ceil(($item['precio'] ?? 0) / $vpc) * ($item['cantidad'] ?? 1) : 0;
                                    @endphp
                                    <button
                                        wire:click="quitarCanjeArticulo({{ $index }})"
                                        class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[9px] font-medium bg-yellow-100 text-yellow-700 hover:bg-yellow-200 border border-yellow-300 cursor-pointer"
                                        title="{{ __('Clic para quitar canje') }}">
                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        {{ $ptsCanje }}pts
                                    </button>
                                @elseif($tieneAjusteManual)
                                    <button
                                        wire:click="quitarAjusteManual({{ $index }})"
                                        class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[9px] font-medium bg-purple-100 text-purple-700 hover:bg-purple-200 border border-purple-300 cursor-pointer"
                                        title="{{ __('Clic para quitar ajuste manual') }}">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        {{ __('manual') }}
                                    </button>
                                @endif
                                {{-- (El popover del input de ajuste vive ahora dentro
                                    del popup unificado en la columna del precio, para
                                    evitar el flash entre fases.) --}}
                            </td>
                            {{-- Subtotal --}}
                            <td class="px-2 py-1.5 text-right text-xs font-medium">
                                @if($esInvitacion)
                                    <span class="text-emerald-600">$0.00</span>
                                    <div class="text-[10px] text-gray-400 line-through">
                                        $@precio($precioInvitacionOriginal * $item['cantidad'])
                                    </div>
                                @else
                                    <span class="text-gray-900 dark:text-white">$@precio($item['precio'] * $item['cantidad'])</span>
                                @endif
                            </td>
                            {{-- Promo --}}
                            <td class="px-2 py-1.5 text-center">
                                @php
                                    $subtotalItem = $item['precio'] * $item['cantidad'];
                                    $descuentoComun = $itemResultado['descuento_comun'] ?? 0;
                                    $totalItem = $subtotalItem - $descuentoComun;
                                @endphp
                                <div class="flex flex-col items-center gap-0.5">
                                    @if($esInvitacion)
                                        <span class="text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                                            {{ __('Cortesía') }}
                                        </span>
                                    @else
                                        @if($tienePromoEspecial)
                                            <span class="inline-flex items-center px-1 py-0 rounded text-[10px] font-medium bg-green-100 text-green-800">{{ $itemResultado['unidades_consumidas'] }}/{{ $item['cantidad'] }}</span>
                                        @endif
                                        @if($tienePromoComun && $descuentoComun > 0)
                                            <span class="inline-flex items-center px-1 py-0 rounded text-[10px] font-medium bg-blue-100 text-blue-800">-$@precio($descuentoComun)</span>
                                        @endif
                                        @if($excluido)
                                            <span class="text-[10px] font-medium text-yellow-600">N/A</span>
                                        @endif
                                        @if(!$tienePromoEspecial && !$tienePromoComun && !$excluido)
                                            <span class="text-gray-400 text-[10px]">-</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            {{-- Total — el monto es clickeable (touch-friendly):
                                tap abre invitar/desinvitar segun el estado. En
                                desktop ademas aparece un icono regalo a la
                                izquierda en hover como pista visual. --}}
                            <td class="px-2 py-1.5 text-right text-xs font-medium">
                                @php
                                    $totalAccion = $this->puedeInvitarRenglon
                                        ? ($esInvitacion ? 'abrirDesinvitarItem' : 'abrirInvitarItem')
                                        : null;
                                    $totalTooltip = $totalAccion
                                        ? ($esInvitacion ? __('Quitar invitación') : __('Invitar renglón'))
                                        : '';
                                @endphp
                                @if($totalAccion)
                                    {{-- Todo el bloque (icono + monto) es un solo
                                        button: tap directo en el monto o el icono
                                        dispara invitar/desinvitar (touch-friendly). --}}
                                    <button type="button"
                                            wire:click="{{ $totalAccion }}({{ $index }})"
                                            class="flex items-center justify-end gap-1 group w-full hover:underline {{ $esInvitacion ? 'text-emerald-600 hover:text-emerald-700' : ($tienePromoComun && $descuentoComun > 0 ? 'text-green-600 hover:text-green-700' : 'text-gray-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400') }}"
                                            title="{{ $totalTooltip }}">
                                        <span class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0 {{ $esInvitacion ? 'text-emerald-600' : 'text-gray-400 group-hover:text-emerald-600' }}"
                                              aria-hidden="true">
                                            @if($esInvitacion)
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                                                </svg>
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                                                </svg>
                                            @endif
                                        </span>
                                        <span>
                                            @if($esInvitacion)
                                                $0.00
                                            @elseif($tienePromoComun && $descuentoComun > 0)
                                                $@precio($totalItem)
                                            @else
                                                $@precio($subtotalItem)
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    {{-- Sin permiso para invitar: monto plano sin click. --}}
                                    @if($esInvitacion)
                                        <span class="text-emerald-600">$0.00</span>
                                    @elseif($tienePromoComun && $descuentoComun > 0)
                                        <span class="text-green-600">$@precio($totalItem)</span>
                                    @else
                                        <span class="text-gray-900 dark:text-white">$@precio($subtotalItem)</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
