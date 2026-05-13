{{-- Modal Full-Screen: Alta/Edición de Pedido por Mostrador --}}
<div class="fixed inset-0 z-40 bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
    x-data
    @keydown.escape.window="$wire.cerrar()"
>
    {{-- Header sticky --}}
    <div class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 sm:px-6 py-2 flex items-center justify-between gap-3 flex-shrink-0">
        <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-bcn-secondary dark:text-white flex items-center gap-2 flex-wrap">
                @if($modoEdicion)
                    {{ __('Editar Pedido') }} #{{ $pedidoId }}
                    @if($estadoPedidoActual)
                        <x-pedidos.badge-estado-pedido :estado="$estadoPedidoActual" />
                    @endif
                @else
                    {{ __('Nuevo Pedido') }}
                @endif
            </h2>
        </div>
        <button type="button" wire:click="cerrar" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 flex-shrink-0"
            title="{{ __('Cerrar') }} (Esc)">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Cuerpo scrolleable: layout 2 columnas --}}
    <div class="flex-1 overflow-hidden flex flex-col lg:flex-row gap-3 p-3 sm:p-4 min-h-0">
        {{-- Columna izquierda: búsqueda + detalle items --}}
        <div class="flex-1 flex flex-col gap-3 min-h-0 lg:min-w-0">
            {{-- Búsqueda de artículos (reutilizado de NuevaVenta) --}}
            @include('livewire.carrito._busqueda-articulos')

            {{-- Detalle/lista de items (reutilizado) --}}
            @include('livewire.carrito._detalle-items')
        </div>

        {{-- Columna derecha: cliente + extras --}}
        <div class="w-full lg:w-96 lg:flex-shrink-0 flex flex-col gap-3 overflow-y-auto min-h-0 pb-2">
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

            {{-- Descuentos / Cupón / Puntos --}}
            <div class="grid grid-cols-3 gap-2">
                <button type="button" wire:click="abrirModalDescuentos" @disabled(count($items) === 0)
                    class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Descuentos') }}</div>
                    <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                        @if($descuentoGeneralActivo)
                            @if($descuentoGeneralTipo === 'porcentaje')
                                {{ $descuentoGeneralValor }}%
                            @else
                                ${{ number_format($descuentoGeneralValor, 0, ',', '.') }}
                            @endif
                        @else
                            {{ __('Aplicar') }}
                        @endif
                    </div>
                </button>
                <button type="button" wire:click="$set('cuponCodigoInput', '')"
                    @disabled(count($items) === 0)
                    class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Cupón') }}</div>
                    <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                        {{ $cuponAplicado ? ($cuponAplicado['codigo'] ?? __('Aplicado')) : __('Aplicar') }}
                    </div>
                </button>
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                    <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Puntos cliente') }}</div>
                    <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                        @if($clienteSeleccionado)
                            {{ number_format($puntosSaldoCliente ?? 0, 0, ',', '.') }} {{ __('pts') }}
                        @else
                            <span class="italic text-gray-500 text-[10px]">{{ __('Seleccioná cliente') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cupón input compacto --}}
            @if(!$cuponAplicado && count($items) > 0)
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                    <div class="flex gap-2">
                        <input type="text" wire:model="cuponCodigoInput"
                            placeholder="{{ __('Código de cupón') }}"
                            @keydown.enter.prevent="$wire.validarCupon()"
                            class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-xs" />
                        <button type="button" wire:click="validarCupon"
                            class="inline-flex items-center px-2 py-1 bg-bcn-primary text-white text-xs rounded hover:bg-opacity-90">
                            {{ __('Validar') }}
                        </button>
                    </div>
                </div>
            @elseif($cuponAplicado)
                <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-2 flex justify-between items-center text-xs">
                    <div>
                        <div class="font-medium text-green-900 dark:text-green-200">{{ $cuponAplicado['codigo'] ?? '' }}</div>
                        <div class="text-green-700 dark:text-green-300">
                            -${{ number_format($cuponMontoDescuento ?? 0, 2, ',', '.') }}
                        </div>
                    </div>
                    <button type="button" wire:click="quitarCupon" class="text-red-600 hover:text-red-700 underline">
                        {{ __('Quitar') }}
                    </button>
                </div>
            @endif

            {{-- Totales --}}
            @if($resultado)
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-3 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>{{ __('Subtotal') }}</span>
                        <span>${{ number_format($resultado['subtotal'] ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>{{ __('IVA') }}</span>
                        <span>${{ number_format($resultado['iva_total'] ?? 0, 2, ',', '.') }}</span>
                    </div>
                    @if(($resultado['descuento_total'] ?? 0) > 0)
                        <div class="flex justify-between text-red-600 dark:text-red-400">
                            <span>{{ __('Descuentos') }}</span>
                            <span>-${{ number_format($resultado['descuento_total'] ?? 0, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700 text-base font-bold text-bcn-secondary dark:text-white">
                        <span>{{ __('Total final') }}</span>
                        <span>${{ number_format($resultado['total_final'] ?? $resultado['total'] ?? 0, 2, ',', '.') }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Footer fijo con botones de acción --}}
    <div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-end gap-2 flex-shrink-0">
        <button type="button" wire:click="cerrar" wire:loading.attr="disabled"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            {{ __('Cancelar') }}
        </button>
        @if(!$modoEdicion)
            <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                {{ __('Guardar borrador') }}
            </button>
        @endif
        <button type="button" wire:click="confirmarPedido" wire:loading.attr="disabled"
            class="inline-flex items-center px-5 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-bcn-primary hover:bg-opacity-90 shadow">
            {{ $modoEdicion ? __('Guardar cambios') : __('Confirmar pedido') }}
        </button>
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
</div>
