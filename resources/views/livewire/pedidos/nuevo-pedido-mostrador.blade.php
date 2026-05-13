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

    {{-- Modal: Pago del pedido (desglose mixto, cobrar ahora vs planificado) --}}
    @if($mostrarModalPagoPedido)
        @php
            $totalFinalPedido = (float) ($resultado['total_final'] ?? $resultado['total'] ?? 0);
            $cubiertoDesglose = array_sum(array_map(fn($p) => (float)($p['monto_final'] ?? 0), $desglosePagosPedido));
            $pendienteDesglose = round($totalFinalPedido - $cubiertoDesglose, 2);
            $fpSeleccionada = $nuevoPagoPedido['forma_pago_id']
                ? collect($formasPagoDisponibles)->firstWhere('id', (int)$nuevoPagoPedido['forma_pago_id'])
                : null;
        @endphp
        <x-bcn-modal :title="__('Confirmar pedido y pagos')" color="bg-bcn-primary" maxWidth="3xl" onClose="cerrarModalPagoPedido">
            <x-slot:body>
                <div class="space-y-4">
                    {{-- Resumen del total --}}
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-gray-100 dark:bg-gray-700 rounded p-2">
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Total') }}</div>
                            <div class="text-base font-bold text-bcn-secondary dark:text-white">${{ number_format($totalFinalPedido, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded p-2">
                            <div class="text-xs text-green-700 dark:text-green-300">{{ __('Cubierto') }}</div>
                            <div class="text-base font-bold text-green-800 dark:text-green-200">${{ number_format($cubiertoDesglose, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded p-2">
                            <div class="text-xs text-yellow-700 dark:text-yellow-300">{{ __('Pendiente') }}</div>
                            <div class="text-base font-bold text-yellow-800 dark:text-yellow-200">${{ number_format($pendienteDesglose, 2, ',', '.') }}</div>
                        </div>
                    </div>

                    {{-- Lista de pagos agregados al desglose --}}
                    @if(!empty($desglosePagosPedido))
                        <div>
                            <div class="text-xs font-semibold uppercase text-gray-700 dark:text-gray-300 mb-2">{{ __('Pagos cargados') }}</div>
                            <div class="space-y-2">
                                @foreach($desglosePagosPedido as $idx => $pago)
                                    <div class="flex items-center gap-2 border rounded p-2
                                        {{ ($pago['planificado'] ?? false)
                                            ? 'border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20'
                                            : 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/20' }}">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {{ $pago['forma_pago_nombre'] }}
                                                @if($pago['cuotas'] ?? null)
                                                    <span class="text-xs text-gray-500">— {{ $pago['cuotas'] }} {{ __('cuotas') }}</span>
                                                @endif
                                                @if($pago['referencia'] ?? null)
                                                    <span class="text-xs text-gray-500">— {{ $pago['referencia'] }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ __('Base') }}: ${{ number_format($pago['monto_base'], 2, ',', '.') }}
                                                @if(($pago['monto_ajuste'] ?? 0) != 0)
                                                    · {{ __('Ajuste') }}: ${{ number_format($pago['monto_ajuste'], 2, ',', '.') }}
                                                @endif
                                                @if(($pago['recargo_cuotas_monto'] ?? 0) > 0)
                                                    · {{ __('Recargo cuotas') }}: ${{ number_format($pago['recargo_cuotas_monto'], 2, ',', '.') }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-bold text-bcn-secondary dark:text-white">
                                                ${{ number_format($pago['monto_final'], 2, ',', '.') }}
                                            </div>
                                            <button type="button" wire:click="togglePlanificadoEnDesglose({{ $idx }})"
                                                class="text-[10px] underline {{ ($pago['planificado'] ?? false) ? 'text-blue-700 dark:text-blue-300' : 'text-green-700 dark:text-green-300' }}">
                                                {{ ($pago['planificado'] ?? false) ? __('Planificado') : __('Cobrar ahora') }}
                                            </button>
                                        </div>
                                        <button type="button" wire:click="eliminarPagoDelDesglose({{ $idx }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                                            title="{{ __('Eliminar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Form para agregar un nuevo pago --}}
                    @if($pendienteDesglose > 0.005)
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-3">
                            <div class="text-xs font-semibold uppercase text-gray-700 dark:text-gray-300">{{ __('Agregar pago') }}</div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de pago') }} *</label>
                                    <select wire:model.live="nuevoPagoPedido.forma_pago_id"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($formasPagoDisponibles as $fp)
                                            <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('nuevoPagoPedido.forma_pago_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto') }} *</label>
                                    <input type="number" step="0.01" min="0.01" wire:model="nuevoPagoPedido.monto_base"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                                    @error('nuevoPagoPedido.monto_base') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            @if($fpSeleccionada && !empty($fpSeleccionada['cuotas']))
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Plan de cuotas') }}</label>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" wire:click="seleccionarCuotasPagoPedido(1, 0)"
                                            class="px-2 py-1 text-xs rounded border {{ ($nuevoPagoPedido['cuotas'] ?? 1) == 1 ? 'bg-bcn-primary text-white border-bcn-primary' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                            1x
                                        </button>
                                        @foreach($fpSeleccionada['cuotas'] as $cu)
                                            <button type="button" wire:click="seleccionarCuotasPagoPedido({{ $cu['cantidad'] }}, {{ $cu['recargo'] }})"
                                                class="px-2 py-1 text-xs rounded border {{ ($nuevoPagoPedido['cuotas'] ?? 1) == $cu['cantidad'] ? 'bg-bcn-primary text-white border-bcn-primary' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                                {{ $cu['cantidad'] }}x
                                                @if($cu['recargo'] > 0)<span class="text-[10px] opacity-80">(+{{ $cu['recargo'] }}%)</span>@endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Referencia (opcional)') }}</label>
                                    <input type="text" wire:model="nuevoPagoPedido.referencia"
                                        placeholder="{{ __('Ej: últimos 4 tarjeta, transferencia #...') }}"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" />
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" wire:model="nuevoPagoPedido.planificado"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700" />
                                        <span>{{ __('Guardar sin cobrar (planificado)') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="agregarPagoAlDesglosePedido"
                                    class="inline-flex items-center px-3 py-1.5 bg-bcn-primary text-white text-sm rounded hover:bg-opacity-90">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    {{ __('Agregar al desglose') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded p-3 text-sm text-green-800 dark:text-green-200 text-center">
                            ✓ {{ __('El monto total está cubierto. Podés confirmar el pedido.') }}
                        </div>
                    @endif
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="button" wire:click="confirmarPagosPedido" wire:loading.attr="disabled"
                    @disabled($pendienteDesglose > 0.005 && $totalFinalPedido > 0.005)
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto sm:text-sm">
                    {{ __('Confirmar pedido') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
