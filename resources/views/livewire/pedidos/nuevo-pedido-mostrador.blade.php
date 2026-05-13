{{-- Modal Full-Screen con margen mínimo: Alta/Edición de Pedido por Mostrador --}}
<div class="fixed inset-0 z-40 bg-black/40 flex items-stretch justify-center p-2 sm:p-3"
    x-data
    @keydown.escape.window="$wire.cerrar()"
>
    <div class="w-full bg-white dark:bg-gray-900 flex flex-col overflow-hidden rounded-lg shadow-2xl">
    {{-- Header naranja --}}
    <div class="bg-bcn-primary text-white px-4 sm:px-6 py-3 flex items-center justify-between gap-3 flex-shrink-0 rounded-t-lg">
        <h2 class="text-base sm:text-lg font-bold flex items-center gap-2 flex-wrap">
            @if($modoEdicion)
                {{ __('Editar Pedido de Mostrador') }} #{{ $pedidoId }}
                @if($estadoPedidoActual)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">
                        {{ __(ucfirst($estadoPedidoActual)) }}
                    </span>
                @endif
            @else
                {{ __('Nuevo Pedido de Mostrador') }}
            @endif
        </h2>
        <button type="button" wire:click="cerrar" class="text-white/80 hover:text-white flex-shrink-0"
            title="{{ __('Cerrar') }} (Esc)">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Cuerpo: layout 2 columnas --}}
    <div class="flex-1 overflow-hidden flex flex-col lg:flex-row gap-3 p-3 sm:p-4 min-h-0">
        {{-- Columna izquierda: búsqueda + detalle items --}}
        <div class="flex-1 flex flex-col gap-3 min-h-0 lg:min-w-0">
            @include('livewire.carrito._busqueda-articulos')
            @include('livewire.carrito._detalle-items')
        </div>

        {{-- Columna derecha: contenido scrolleable + footer fijo --}}
        <div class="w-full lg:w-96 lg:flex-shrink-0 flex flex-col min-h-0 gap-2">
            {{-- Contenido scrolleable. Inputs compactos al estilo NuevaVenta. --}}
            <div class="flex-1 overflow-y-auto space-y-2 pr-1 min-h-0">
                {{-- Cliente (reutilizado) --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                    @include('livewire.carrito._busqueda-cliente')

                    @unless($clienteSeleccionado)
                        {{-- Cliente temporal (RF-17): nombre+teléfono en una sola fila --}}
                        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1.5">
                            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('O datos temporales') }}
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <input type="text" wire:model.live.debounce.300ms="nombreClienteTemporal"
                                    placeholder="{{ __('Nombre') }}"
                                    class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                                <input type="text" wire:model.live.debounce.300ms="telefonoClienteTemporal"
                                    placeholder="{{ __('Teléfono') }}"
                                    class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                            </div>
                            @if(trim($nombreClienteTemporal ?? '') !== '' && trim($telefonoClienteTemporal ?? '') !== '')
                                <button type="button" wire:click="abrirModalAltaClienteTemporal"
                                    class="text-[10px] text-bcn-primary hover:underline">
                                    + {{ __('Dar de alta como cliente') }}
                                </button>
                            @endif
                        </div>
                    @endunless
                </div>

                {{-- Beeper (solo si la sucursal lo usa) --}}
                @if($sucursalUsaBeepers)
                    <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                        <label class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">
                            {{ __('Beeper') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="numeroBeeper" maxlength="20"
                            placeholder="{{ __('N° de beeper') }}"
                            class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                    </div>
                @endif

                {{-- Lista de Precios + Forma de Pago en el mismo renglón --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2 space-y-1.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="listaPrecioId" class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Lista de Precios') }}</label>
                            @if(count($listasPreciosDisponibles) > 1)
                                <select id="listaPrecioId" wire:model.live="listaPrecioId"
                                    class="block w-full pl-2 pr-6 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                                    @foreach($listasPreciosDisponibles as $lista)
                                        <option value="{{ $lista['id'] }}">{{ $lista['nombre'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <div class="text-xs text-gray-700 dark:text-gray-200 py-1 truncate">
                                    {{ $listasPreciosDisponibles[0]['nombre'] ?? __('—') }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <label for="formaPagoId" class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Forma de Pago') }}</label>
                            <select wire:model.live="formaPagoId" id="formaPagoId"
                                class="block w-full pl-2 pr-6 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                                <option value="">{{ __('Seleccionar...') }}</option>
                                @foreach($this->formasPago as $fp)
                                    <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Selector de Cuotas (estilo NuevaVenta, con detalle de valor + recargo + total) --}}
                    @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                        <div class="relative">
                            <label class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Cuotas') }}</label>

                            <div wire:click="toggleCuotasSelector"
                                class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                                @if(!$cuotaSeleccionadaId)
                                    <div class="flex items-center px-2 py-1.5">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                        </div>
                                        <div class="text-center px-2"><span class="text-[10px] text-gray-400">—</span></div>
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">${{ number_format(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}</span>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                @else
                                    @php
                                        $cuotaSel = collect($cuotasFormaPagoDisponibles)->firstWhere('id', (int) $cuotaSeleccionadaId);
                                    @endphp
                                    @if($cuotaSel)
                                        <div class="flex items-center px-2 py-1.5">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuotaSel['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuotaSel['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuotaSel['recargo_porcentaje'] > 0)
                                                    <span class="text-[10px] font-medium text-red-600">+{{ $cuotaSel['recargo_porcentaje'] }}%</span>
                                                @else
                                                    <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold {{ $cuotaSel['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuotaSel['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if($cuotasSelectorAbierto)
                                <div class="absolute z-20 w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-y-auto">
                                    <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ !$cuotaSeleccionadaId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                        <input type="radio" wire:model.live="cuotaSeleccionadaId" value="" class="sr-only">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                        </div>
                                        <div class="text-center px-2"><span class="text-[10px] text-gray-400">—</span></div>
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">${{ number_format(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}</span>
                                        </div>
                                    </label>
                                    @foreach($cuotasFormaPagoDisponibles as $cuota)
                                        <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $cuotaSeleccionadaId == $cuota['id'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                            <input type="radio" wire:model.live="cuotaSeleccionadaId" value="{{ $cuota['id'] }}" class="sr-only">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuota['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuota['recargo_porcentaje'] > 0)
                                                    <span class="text-[10px] font-medium text-red-600">+{{ $cuota['recargo_porcentaje'] }}%</span>
                                                @else
                                                    <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold {{ $cuota['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuota['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($formaPagoId && ($ajusteFormaPagoInfo['porcentaje'] ?? 0) != 0)
                        <div class="text-[10px] text-gray-600 dark:text-gray-400">
                            @if($ajusteFormaPagoInfo['porcentaje'] > 0)
                                {{ __('Recargo') }} {{ $ajusteFormaPagoInfo['porcentaje'] }}%: +${{ number_format($ajusteFormaPagoInfo['monto'] ?? 0, 2, ',', '.') }}
                            @else
                                {{ __('Descuento') }} {{ abs($ajusteFormaPagoInfo['porcentaje']) }}%: -${{ number_format(abs($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Botón compacto de Descuentos y Beneficios (estilo NuevaVenta) --}}
                @if(!empty($items))
                    <button
                        wire:click="abrirModalDescuentos"
                        type="button"
                        class="w-full inline-flex justify-center items-center px-2 py-1.5 border rounded-md shadow-sm text-xs font-medium
                            {{ ($descuentoGeneralActivo || $cuponAplicado || $canjePuntosActivo)
                                ? 'border-purple-400 dark:border-purple-500 text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 hover:bg-purple-100 dark:hover:bg-purple-900/50'
                                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        {{ __('Descuentos') }}
                        @if($descuentoGeneralActivo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-purple-200 dark:bg-purple-700 text-purple-800 dark:text-purple-200 rounded">
                                {{ $descuentoGeneralTipo === 'porcentaje' ? $descuentoGeneralValor . '%' : '$' . number_format($descuentoGeneralValor, 2, ',', '.') }}
                            </span>
                        @endif
                        @if($cuponAplicado && $cuponInfo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-amber-200 dark:bg-amber-700 text-amber-800 dark:text-amber-200 rounded">
                                {{ $cuponInfo['codigo'] }}
                            </span>
                        @endif
                        @if($canjePuntosActivo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-yellow-200 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-200 rounded">
                                {{ $canjePuntosUnidades }}pts
                            </span>
                        @endif
                    </button>
                @endif

                {{-- Resumen de Totales (reusado 1:1 de NuevaVenta: subtotal, descuentos, ajuste FP, recargo cuotas, total, desglose IVA colapsable) --}}
                @if($resultado)
                    @include('livewire.carrito._resumen-totales')
                @endif
            </div>

            {{-- Footer fijo de la columna (acciones) --}}
            @php
                // Total a cobrar para el botón verde (mismo cálculo que NuevaVenta).
                $totalACobrar = 0;
                if (! empty($resultado)) {
                    if (isset($resultado['desglose_iva'])) {
                        $dgIva = $resultado['desglose_iva'];
                        if (isset($dgIva['total_mixto'])) {
                            $totalACobrar = $dgIva['total_mixto'];
                        } elseif (isset($dgIva['total_con_ajuste_fp']) && $dgIva['total_con_ajuste_fp'] != ($dgIva['total'] ?? 0)) {
                            $totalACobrar = $dgIva['total_con_ajuste_fp'];
                        } else {
                            $totalACobrar = $resultado['total_final'] ?? 0;
                        }
                    } else {
                        $totalACobrar = $resultado['total_final'] ?? 0;
                    }
                }
            @endphp
            <div class="flex-shrink-0 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1.5">
                {{-- Fila 1: Borrador + Sin cobrar (50/50) --}}
                <div class="flex gap-1.5">
                    @if(!$modoEdicion || $estadoPedidoActual === 'borrador')
                        <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                            class="flex-1 inline-flex justify-center items-center px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            {{ __('Guardar borrador') }}
                        </button>
                    @endif
                    <button type="button" wire:click="confirmarSinCobrar" wire:loading.attr="disabled"
                        class="flex-1 inline-flex justify-center items-center px-2 py-1.5 border border-gray-400 dark:border-gray-500 rounded-md text-xs font-medium text-gray-800 dark:text-gray-100 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500">
                        {{ __('Confirmar sin cobrar') }}
                    </button>
                </div>

                {{-- Fila 2: Confirmar pedido $XXXX en verde --}}
                <button type="button" wire:click="confirmarPedido" wire:loading.attr="disabled"
                    @if(empty($items)) disabled @endif
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent rounded-md text-sm font-bold text-white bg-green-600 hover:bg-green-700 shadow disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    {{ __('Confirmar') }}
                    @if($totalACobrar > 0)
                        <span class="ml-1.5">${{ number_format($totalACobrar, 2, ',', '.') }}</span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- Modales reutilizados de NuevaVenta --}}
    @include('livewire.carrito._modal-cliente-rapido')
    @include('livewire.carrito._modal-articulo-rapido')
    @include('livewire.carrito._modal-busqueda-articulos')
    @include('livewire.carrito._modal-pesable')
    @include('livewire.ventas._wizard-opcionales')
    @include('livewire.ventas._modal-descuentos')

    {{-- Modal: Concepto libre --}}
    @if($mostrarModalConcepto)
        <x-bcn-modal :title="__('Agregar concepto')" color="bg-emerald-600" maxWidth="md" onClose="cerrarModalConcepto" submit="agregarConcepto">
            <x-slot:body>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción') }}</label>
                        <input type="text" wire:model="conceptoDescripcion" placeholder="{{ __('Ej: Adicional de empaque') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría') }}</label>
                        <select wire:model="conceptoCategoriaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Sin categoría') }}</option>
                            @foreach($categoriasDisponibles as $cat)
                                <option value="{{ $cat['id'] }}">{{ $cat['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Importe') }} *</label>
                        <input type="number" step="0.01" min="0" wire:model="conceptoImporte" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm" />
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-emerald-600 hover:bg-emerald-700">
                    {{ __('Agregar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal: Confirmar limpiar carrito --}}
    @if($mostrarConfirmLimpiar)
        <x-bcn-modal :title="__('¿Limpiar el carrito?')" color="bg-red-600" maxWidth="sm" onClose="cancelarLimpiarCarrito">
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Se eliminarán todos los artículos del carrito.') }}</p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="ejecutarLimpiarCarrito" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-red-600 hover:bg-red-700">
                    {{ __('Limpiar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Popover edición de nombre de item --}}
    @if($editarNombreIndex !== null)
        <x-bcn-modal :title="__('Editar nombre')" color="bg-blue-600" maxWidth="md" onClose="cerrarEditarNombre" submit="aplicarEditarNombre">
            <x-slot:body>
                <input type="text" wire:model="editarNombreValor"
                    x-init="$nextTick(() => $el.focus())"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm" />
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-blue-600 hover:bg-blue-700">
                    {{ __('Aplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de Pago / Desglose Mixto (reusado de NuevaVenta) --}}
    @include("livewire.carrito._modal-pago-mixto")
    @include("livewire.carrito._modal-moneda-extranjera")
    @include("livewire.carrito._modal-vuelto")
    </div>{{-- /modal contenedor con margen --}}
</div>
