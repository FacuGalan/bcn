<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
                    @if($modoEdicion)
                        {{ __('Editar Pedido') }} #{{ $pedidoId }}
                        <span class="ml-2 text-xs font-normal italic text-gray-500 dark:text-gray-400">
                            ({{ __(ucfirst($estadoPedidoActual ?? '')) }})
                        </span>
                    @else
                        {{ __('Nuevo Pedido') }}
                    @endif
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Cargá los artículos y datos del pedido. Pagos y conversión se gestionan desde la lista.') }}
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="button" wire:click="cancelarYVolver"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Volver') }}
                </button>
                @if(!$modoEdicion)
                    <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        {{ __('Guardar borrador') }}
                    </button>
                @endif
                <button type="button" wire:click="confirmarPedido" wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-bcn-primary hover:bg-opacity-90">
                    {{ $modoEdicion ? __('Guardar cambios') : __('Confirmar pedido') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- COLUMNA IZQUIERDA: Cliente, identificador, observaciones --}}
            <div class="lg:col-span-4 space-y-4">
                {{-- Cliente --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-2">{{ __('Cliente') }}</h3>
                    @if($clienteSeleccionado)
                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-3 flex justify-between items-center">
                            <div>
                                <div class="font-medium text-green-900 dark:text-green-200">{{ $clienteNombre }}</div>
                                @if($clienteCondicionIva)
                                    <div class="text-xs text-green-700 dark:text-green-300">{{ $clienteCondicionIva }}</div>
                                @endif
                            </div>
                            <button type="button" wire:click="limpiarCliente"
                                class="text-red-600 hover:text-red-700 dark:text-red-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="busquedaCliente"
                                placeholder="{{ __('Buscar cliente por nombre, teléfono o CUIT...') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if(!empty($clientesResultados))
                                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md overflow-auto border border-gray-200 dark:border-gray-600">
                                    @foreach($clientesResultados as $c)
                                        <button type="button" wire:click="seleccionarCliente({{ $c['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100">
                                            <div class="font-medium">{{ $c['nombre'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                @if($c['telefono'] ?? null) {{ $c['telefono'] }} @endif
                                                @if($c['cuit'] ?? null) — {{ $c['cuit'] }} @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <button type="button" wire:click="abrirModalClienteRapido"
                            class="mt-2 text-xs text-bcn-primary hover:underline">
                            + {{ __('Alta rápida de cliente') }}
                        </button>

                        {{-- Cliente temporal (RF-17) --}}
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                {{ __('O ingresá datos temporales (no se guarda como cliente)') }}
                            </div>
                            <input type="text" wire:model="nombreClienteTemporal"
                                placeholder="{{ __('Nombre del cliente') }}"
                                class="w-full mb-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            <input type="text" wire:model="telefonoClienteTemporal"
                                placeholder="{{ __('Teléfono del cliente') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if(trim($nombreClienteTemporal ?? '') && trim($telefonoClienteTemporal ?? ''))
                                <button type="button" wire:click="abrirModalAltaClienteTemporal"
                                    class="mt-2 text-xs text-bcn-primary hover:underline">
                                    + {{ __('Dar de alta como cliente') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Identificación del pedido --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-2">{{ __('Identificación del pedido') }}</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ __('Identificador') }}</label>
                            <input type="text" wire:model="identificador" maxlength="100"
                                placeholder="{{ __('Ej: Mesa 5, Juan, retira 18hs...') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        </div>
                        @if($sucursalUsaBeepers)
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
                                    {{ __('Número de beeper') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="numeroBeeper" maxlength="20"
                                    placeholder="{{ __('Ingresá el número de beeper') }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('El número de beeper es obligatorio') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Lista de precios --}}
                @if(count($listasPreciosDisponibles) > 1)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-2">{{ __('Lista de precios') }}</h3>
                        <select wire:model.live="listaPrecioId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @foreach($listasPreciosDisponibles as $lista)
                                <option value="{{ $lista['id'] }}">{{ $lista['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Observaciones --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-2">{{ __('Observaciones') }}</h3>
                    <textarea wire:model="observaciones" rows="3"
                        placeholder="{{ __('Notas internas, especificaciones de cocina, etc.') }}"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"></textarea>
                </div>
            </div>

            {{-- COLUMNA CENTRAL/DERECHA: Carrito --}}
            <div class="lg:col-span-8 space-y-4">
                {{-- Búsqueda de artículos --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                    <div class="flex gap-2">
                        <div class="flex-1 relative">
                            <input type="text" wire:model.live.debounce.250ms="busquedaArticulo"
                                placeholder="{{ __('Buscar artículo por nombre o código...') }}"
                                @keydown.enter.prevent="$wire.agregarPrimerArticulo()"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            @if(!empty($articulosResultados))
                                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md overflow-auto border border-gray-200 dark:border-gray-600">
                                    @foreach($articulosResultados as $a)
                                        <button type="button" wire:click="seleccionarArticulo({{ $a['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100">
                                            <div class="flex justify-between">
                                                <span class="font-medium">{{ $a['nombre'] }}</span>
                                                <span class="text-bcn-primary font-semibold">${{ number_format($a['precio'] ?? 0, 2, ',', '.') }}</span>
                                            </div>
                                            @if($a['codigo'] ?? null)
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $a['codigo'] }}</div>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <button type="button" wire:click="abrirModalBusquedaArticulos"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200"
                            title="{{ __('Buscar con filtros') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                        </button>
                        <button type="button" wire:click="abrirModalArticuloRapido"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200"
                            title="{{ __('Alta rápida de artículo') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Carrito --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-4 py-2 bg-bcn-light dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ __('Detalle') }} ({{ count($items) }})
                        </span>
                        @if(count($items) > 0)
                            <button type="button" wire:click="$set('mostrarConfirmLimpiar', true)"
                                class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:underline">
                                {{ __('Limpiar carrito') }}
                            </button>
                        @endif
                    </div>
                    @if(count($items) === 0)
                        <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Sin artículos. Buscá un artículo arriba para agregarlo al pedido.') }}
                        </div>
                    @else
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($items as $index => $item)
                                <div class="p-3 flex flex-col sm:flex-row sm:items-center gap-2"
                                    wire:key="item-{{ $index }}-{{ $item['articulo_id'] ?? 'concepto' }}">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <div class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                                {{ $item['nombre'] ?? '—' }}
                                            </div>
                                            @if($item['pagado_con_puntos'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    {{ __('Puntos') }}
                                                </span>
                                            @endif
                                            @if($item['tiene_promocion'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                    {{ __('Promo') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if(!empty($item['opcionales']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                @foreach($item['opcionales'] as $grupo)
                                                    @foreach($grupo['selecciones'] ?? [] as $sel)
                                                        <span class="mr-2">+ {{ $sel['nombre'] ?? '' }}</span>
                                                    @endforeach
                                                @endforeach
                                                <button type="button" wire:click="editarOpcionalesItem({{ $index }})"
                                                    class="text-bcn-primary hover:underline ml-1">
                                                    {{ __('Editar') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1">
                                            <input type="number" min="0.001" step="0.001"
                                                value="{{ $item['cantidad'] ?? 1 }}"
                                                wire:change="actualizarCantidad({{ $index }}, $event.target.value)"
                                                class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm text-center" />
                                        </div>
                                        <div class="text-right min-w-[80px]">
                                            <div class="text-sm font-semibold text-bcn-secondary dark:text-white">
                                                ${{ number_format(($item['precio'] ?? 0) * ($item['cantidad'] ?? 0), 2, ',', '.') }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                ${{ number_format($item['precio'] ?? 0, 2, ',', '.') }} c/u
                                            </div>
                                        </div>
                                        <button type="button" wire:click="abrirAjusteManual({{ $index }})"
                                            class="text-gray-500 hover:text-bcn-primary dark:text-gray-400"
                                            title="{{ __('Ajustar precio') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button type="button" wire:click="eliminarItem({{ $index }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                                            title="{{ __('Eliminar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Descuentos, cupón, puntos --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <button type="button" wire:click="abrirModalDescuentos" @disabled(count($items) === 0)
                        class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Descuentos') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">
                            @if($descuentoGeneralActivo)
                                @if($descuentoGeneralTipo === 'porcentaje')
                                    {{ $descuentoGeneralValor }}%
                                @else
                                    ${{ number_format($descuentoGeneralValor, 2, ',', '.') }}
                                @endif
                            @else
                                {{ __('Aplicar') }}
                            @endif
                        </div>
                    </button>
                    <button type="button" wire:click="$set('cuponCodigoInput', '')"
                        x-data x-on:click="$nextTick(() => $refs.cuponInput?.focus())"
                        @disabled(count($items) === 0)
                        class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cupón') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">
                            @if($cuponAplicado)
                                {{ $cuponAplicado['codigo'] ?? __('Aplicado') }}
                            @else
                                {{ __('Aplicar') }}
                            @endif
                        </div>
                    </button>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Puntos del cliente') }}</div>
                        <div class="font-semibold text-gray-900 dark:text-white">
                            @if($clienteSeleccionado)
                                {{ number_format($puntosSaldoCliente ?? 0, 0, ',', '.') }} {{ __('pts') }}
                            @else
                                <span class="text-xs italic text-gray-500">{{ __('Seleccioná cliente') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Input de cupón (compacto) --}}
                @if(!$cuponAplicado && count($items) > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3">
                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ __('Código de cupón') }}</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="cuponCodigoInput" x-ref="cuponInput"
                                placeholder="{{ __('Ej: PROMO10') }}"
                                @keydown.enter.prevent="$wire.validarCupon()"
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                            <button type="button" wire:click="validarCupon"
                                class="inline-flex items-center px-3 py-2 bg-bcn-primary text-white text-sm rounded hover:bg-opacity-90">
                                {{ __('Validar') }}
                            </button>
                        </div>
                    </div>
                @elseif($cuponAplicado)
                    <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-3 flex justify-between items-center">
                        <div class="text-sm">
                            <div class="font-medium text-green-900 dark:text-green-200">{{ $cuponAplicado['codigo'] ?? '' }}</div>
                            <div class="text-xs text-green-700 dark:text-green-300">
                                {{ __('Descuento') }}: ${{ number_format($cuponMontoDescuento ?? 0, 2, ',', '.') }}
                            </div>
                        </div>
                        <button type="button" wire:click="quitarCupon" class="text-red-600 hover:text-red-700 text-xs underline">
                            {{ __('Quitar') }}
                        </button>
                    </div>
                @endif

                {{-- Totales --}}
                @if($resultado)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                        <div class="space-y-1 text-sm">
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
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- INCLUDES de modales/wizards reutilizables --}}
    @include('livewire.ventas._wizard-opcionales')
    @include('livewire.ventas._modal-descuentos')

    {{-- Modal: confirmar limpiar carrito --}}
    @if($mostrarConfirmLimpiar)
        <x-bcn-modal :title="__('Limpiar carrito')" color="bg-red-600" maxWidth="md" onClose="$set('mostrarConfirmLimpiar', false)">
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Se quitarán todos los artículos del carrito. ¿Confirmás?') }}
                </p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="limpiarCarrito" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm">
                    {{ __('Limpiar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal: artículo rápido --}}
    @if($mostrarModalArticuloRapido)
        <x-bcn-modal :title="__('Alta rápida de artículo')" color="bg-bcn-primary" maxWidth="lg" onClose="cerrarModalArticuloRapido" submit="guardarArticuloRapido">
            <x-slot:body>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                        <input type="text" wire:model="artRapidoNombre" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        @error('artRapidoNombre') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Precio base') }} *</label>
                        <input type="number" step="0.01" min="0" wire:model="artRapidoPrecioBase" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                        @error('artRapidoPrecioBase') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría') }} *</label>
                        <select wire:model="artRapidoCategoriaId" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($artRapidoCategorias as $cat)
                                <option value="{{ $cat['id'] }}">{{ $cat['nombre'] }}</option>
                            @endforeach
                        </select>
                        @error('artRapidoCategoriaId') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo IVA') }}</label>
                        <select wire:model="artRapidoTipoIvaId" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @foreach($artRapidoTiposIva as $iva)
                                <option value="{{ $iva['id'] }}">{{ $iva['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Código') }}</label>
                        <input type="text" wire:model="artRapidoCodigo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Crear y agregar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal: pesable --}}
    @if($mostrarModalPesable)
        <x-bcn-modal :title="__('Cantidad') . ': ' . $pesableNombreArticulo" color="bg-bcn-primary" maxWidth="sm" onClose="cerrarModalPesable">
            <x-slot:body>
                <div x-data="{ cantidad: 1 }">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad') }} ({{ $pesableUnidadMedida }})</label>
                    <input type="number" step="0.001" min="0.001" x-model="cantidad"
                        @keydown.enter.prevent="$wire.confirmarPesable(cantidad)"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-base" />
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" x-data x-on:click="$wire.confirmarPesable(parseFloat($el.closest('[role=dialog]').querySelector('input[type=number]').value || 1))"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Agregar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal: ajuste manual de precio --}}
    @if($ajusteManualPopoverIndex !== null)
        <x-bcn-modal :title="__('Ajuste manual de precio')" color="bg-blue-600" maxWidth="md" onClose="cerrarAjusteManual" submit="aplicarAjusteManual">
            <x-slot:body>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                        <select wire:model="ajusteManualTipo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm">
                            <option value="porcentaje">{{ __('Porcentaje (%)') }}</option>
                            <option value="monto">{{ __('Precio final ($)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Valor') }}</label>
                        <input type="number" step="0.01" wire:model="ajusteManualValor" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('Para porcentaje: positivo = descuento, negativo = recargo') }}
                        </p>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="quitarAjusteManual({{ $ajusteManualPopoverIndex }})"
                    class="w-full inline-flex justify-center rounded-md border border-red-300 dark:border-red-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 sm:w-auto sm:text-sm">
                    {{ __('Quitar ajuste') }}
                </button>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:w-auto sm:text-sm">
                    {{ __('Aplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
