{{-- Modal Full-Screen: Alta/Edición de Pedido por Mostrador --}}
<div class="fixed inset-0 z-40 bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
    x-data
    @keydown.escape.window="$wire.cerrar()"
>
    {{-- Header naranja --}}
    <div class="bg-bcn-primary text-white px-4 sm:px-6 py-3 flex items-center justify-between gap-3 flex-shrink-0 shadow">
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
            {{-- Contenido scrolleable --}}
            <div class="flex-1 overflow-y-auto space-y-3 pr-1 min-h-0">
                {{-- Cliente (reutilizado) --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                    @include('livewire.carrito._busqueda-cliente')

                    @unless($clienteSeleccionado)
                        {{-- Cliente temporal (RF-17) --}}
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ __('O ingresá datos temporales (no se guarda como cliente)') }}
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="nombreClienteTemporal"
                                placeholder="{{ __('Nombre del cliente') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            <input type="text" wire:model.live.debounce.300ms="telefonoClienteTemporal"
                                placeholder="{{ __('Teléfono del cliente') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if(trim($nombreClienteTemporal ?? '') !== '' && trim($telefonoClienteTemporal ?? '') !== '')
                                <button type="button" wire:click="abrirModalAltaClienteTemporal"
                                    class="text-xs text-bcn-primary hover:underline">
                                    + {{ __('Dar de alta como cliente') }}
                                </button>
                            @endif
                        </div>
                    @endunless
                </div>

                {{-- Beeper (solo si la sucursal lo usa) --}}
                @if($sucursalUsaBeepers)
                    <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Número de beeper') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="numeroBeeper" maxlength="20"
                            placeholder="{{ __('Ingresá el número de beeper') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                    </div>
                @endif

                {{-- Lista de precios (solo si hay >1) --}}
                @if(count($listasPreciosDisponibles) > 1)
                    <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        <label for="listaPrecioId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Lista de Precios') }}</label>
                        <select id="listaPrecioId" wire:model.live="listaPrecioId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @foreach($listasPreciosDisponibles as $lista)
                                <option value="{{ $lista['id'] }}">{{ $lista['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Forma de Pago + Cuotas (igual a NuevaVenta) --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-3 space-y-2">
                    <div>
                        <label for="formaPagoId" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de Pago') }}</label>
                        <select wire:model.live="formaPagoId" id="formaPagoId"
                            class="block w-full pl-2 pr-6 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($this->formasPago as $fp)
                                <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Cuotas (solo si la FP las permite) --}}
                    @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cuotas') }}</label>
                            <select wire:model.live="cuotaSeleccionadaId"
                                class="block w-full pl-2 pr-6 py-1.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                                <option value="">{{ __('1 pago') }}</option>
                                @foreach($cuotasFormaPagoDisponibles as $cuota)
                                    <option value="{{ $cuota['id'] }}">{{ $cuota['cantidad_cuotas'] }} {{ __('cuotas') }} · ${{ number_format($cuota['valor_cuota'], 2, ',', '.') }} @if($cuota['recargo_porcentaje'] > 0)(+{{ $cuota['recargo_porcentaje'] }}%)@endif</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Info de ajuste por FP --}}
                    @if($formaPagoId && ($ajusteFormaPagoInfo['porcentaje'] ?? 0) != 0)
                        <div class="text-xs text-gray-600 dark:text-gray-400">
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

            {{-- Footer fijo de la columna (solo botones de acción) --}}
            <div class="flex-shrink-0 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                @if(!$modoEdicion)
                    <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                        class="w-full inline-flex justify-center items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        {{ __('Guardar borrador') }}
                    </button>
                @endif
                <button type="button" wire:click="confirmarPedido" wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent rounded-md text-sm font-bold text-white bg-bcn-primary hover:bg-opacity-90 shadow">
                    {{ $modoEdicion ? __('Guardar cambios') : __('Confirmar pedido') }}
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
</div>
