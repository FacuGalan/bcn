{{-- Modal de Descuentos y Beneficios (F4) --}}
@if($showModalDescuentos)
    <x-bcn-modal
        :show="$showModalDescuentos"
        :title="__('Descuentos y beneficios')"
        color="bg-purple-500"
        maxWidth="lg"
        onClose="cerrarModalDescuentos"
    >
        <x-slot:body>
            <div class="space-y-4" x-data="{ seccionActiva: 'descuento' }">

                {{-- ══════════════════════════════════════════════════ --}}
                {{-- Sección 1: Descuento General --}}
                {{-- ══════════════════════════════════════════════════ --}}
                @if(auth()->user()?->hasPermissionTo('func.descuento_general'))
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <button
                            type="button"
                            @click="seccionActiva = seccionActiva === 'descuento' ? '' : 'descuento'"
                            class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                        >
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Descuento general') }}</span>
                                @if($descuentoGeneralActivo)
                                    <span class="px-2 py-0.5 text-xs font-semibold bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 rounded-full">
                                        {{ $descuentoGeneralTipo === 'porcentaje' ? $descuentoGeneralValor . '%' : '$' . number_format($descuentoGeneralValor, 2, ',', '.') }}
                                        {{ __('aplicado') }}
                                    </span>
                                @endif
                            </div>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="seccionActiva === 'descuento' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="seccionActiva === 'descuento'" x-collapse class="px-4 py-3 space-y-3">
                            {{-- Estado activo --}}
                            @if($descuentoGeneralActivo)
                                <div class="flex items-center justify-between bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
                                    <div>
                                        <p class="text-sm font-medium text-purple-700 dark:text-purple-300">
                                            {{ __('Descuento general aplicado') }}
                                        </p>
                                        <p class="text-xs text-purple-600 dark:text-purple-400 mt-0.5">
                                            @if($descuentoGeneralTipo === 'porcentaje')
                                                {{ $descuentoGeneralValor }}% &mdash; -${{ number_format($descuentoGeneralMonto, 2, ',', '.') }}
                                            @else
                                                ${{ number_format($descuentoGeneralValor, 2, ',', '.') }} {{ __('fijo') }}
                                            @endif
                                        </p>
                                    </div>
                                    <button
                                        wire:click="quitarDescuentoGeneral"
                                        type="button"
                                        class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium rounded-md hover:bg-red-200 dark:hover:bg-red-900/50"
                                    >
                                        {{ __('Quitar descuento') }}
                                    </button>
                                </div>
                            @endif

                            {{-- Formulario (siempre visible para permitir cambiar/re-aplicar: RF-33, RF-35) --}}
                            <div class="{{ $descuentoGeneralActivo ? 'pt-2 border-t border-gray-200 dark:border-gray-700' : '' }}">
                                @if($descuentoGeneralActivo)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Cambiar descuento (reemplaza el actual)') }}:</p>
                                @endif

                                {{-- Selector tipo --}}
                                <div class="flex gap-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model.live="descuentoGeneralInputTipo" value="porcentaje"
                                            class="text-purple-600 focus:ring-purple-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Porcentaje') }} (%)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" wire:model.live="descuentoGeneralInputTipo" value="monto_fijo"
                                            class="text-purple-600 focus:ring-purple-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Monto fijo') }} ($)</span>
                                    </label>
                                </div>

                                {{-- Input valor --}}
                                <div class="flex items-end gap-2 mt-2">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ $descuentoGeneralInputTipo === 'porcentaje' ? __('Porcentaje de descuento') : __('Monto a descontar') }}
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">
                                                {{ $descuentoGeneralInputTipo === 'porcentaje' ? '%' : '$' }}
                                            </span>
                                            <input
                                                wire:model="descuentoGeneralInputValor"
                                                type="number"
                                                step="{{ $descuentoGeneralInputTipo === 'porcentaje' ? '0.5' : '0.01' }}"
                                                min="0"
                                                max="{{ $descuentoGeneralInputTipo === 'porcentaje' ? '100' : '' }}"
                                                class="block w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm"
                                                placeholder="0"
                                                @keydown.enter="$wire.aplicarDescuentoGeneral()"
                                            >
                                        </div>
                                    </div>
                                    <button
                                        wire:click="aplicarDescuentoGeneral"
                                        type="button"
                                        class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 whitespace-nowrap"
                                    >
                                        {{ $descuentoGeneralActivo ? __('Cambiar') : __('Aplicar') }}
                                    </button>
                                </div>

                                {{-- Info de tope --}}
                                @if($topeDescuentoUsuario !== null)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('Máximo descuento permitido') }}: <span class="font-medium text-purple-600 dark:text-purple-400">{{ $topeDescuentoUsuario }}%</span>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ══════════════════════════════════════════════════ --}}
                {{-- Sección 2: Aplicar Cupón (RF-26) --}}
                {{-- ══════════════════════════════════════════════════ --}}
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <button
                        type="button"
                        @click="seccionActiva = seccionActiva === 'cupon' ? '' : 'cupon'"
                        class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Aplicar cupón') }}</span>
                            @if($cuponAplicado && $cuponInfo)
                                <span class="px-2 py-0.5 text-xs font-semibold bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded-full">
                                    {{ $cuponInfo['codigo'] }} {{ __('aplicado') }}
                                </span>
                            @endif
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="seccionActiva === 'cupon' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="seccionActiva === 'cupon'" x-collapse class="px-4 py-3 space-y-3">
                        @if($cuponAplicado && $cuponInfo)
                            {{-- Cupón aplicado: mostrar detalle + botón quitar --}}
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                                            {{ __('Cupón aplicado') }}: {{ $cuponInfo['codigo'] }}
                                        </p>
                                        <div class="text-xs text-amber-600 dark:text-amber-400 mt-1 space-y-0.5">
                                            @if($cuponInfo['descripcion'])
                                                <p>{{ $cuponInfo['descripcion'] }}</p>
                                            @endif
                                            <p>
                                                {{ $cuponInfo['modo_descuento'] === 'porcentaje' ? $cuponInfo['valor_descuento'] . '%' : '$' . number_format($cuponInfo['valor_descuento'], 2, ',', '.') }}
                                                {{ $cuponInfo['aplica_a'] === 'total' ? __('sobre el total') : __('en artículos específicos') }}
                                            </p>
                                            <p class="font-medium">{{ __('Descuento') }}: -${{ number_format($cuponInfo['monto_descuento'], 2, ',', '.') }}</p>
                                            @if(!empty($cuponInfo['formas_pago_permitidas']))
                                                <p>{{ __('Válido para') }}: {{ implode(', ', $cuponInfo['formas_pago_permitidas']) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <button
                                        wire:click="quitarCupon"
                                        type="button"
                                        class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium rounded-md hover:bg-red-200 dark:hover:bg-red-900/50"
                                    >
                                        {{ __('Quitar cupón') }}
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Input de código --}}
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        {{ __('Código de cupón') }}
                                    </label>
                                    <input
                                        wire:model="cuponCodigoInput"
                                        type="text"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 text-sm uppercase"
                                        placeholder="CUP-XXXXXX"
                                        @keydown.enter="$wire.validarCupon()"
                                    >
                                </div>
                                <button
                                    wire:click="validarCupon"
                                    wire:loading.attr="disabled"
                                    wire:target="validarCupon"
                                    type="button"
                                    class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 whitespace-nowrap disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="validarCupon">{{ __('Validar') }}</span>
                                    <span wire:loading wire:target="validarCupon">...</span>
                                </button>
                            </div>

                            {{-- Detalle del cupón validado (antes de aplicar) --}}
                            @if($cuponInfo)
                                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm font-medium text-green-700 dark:text-green-300">{{ $cuponInfo['codigo'] }}</span>
                                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded
                                            {{ $cuponInfo['tipo'] === 'puntos' ? 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300' : 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' }}">
                                            {{ $cuponInfo['tipo'] === 'puntos' ? __('Desde puntos') : __('Promocional') }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                        @if($cuponInfo['descripcion'])
                                            <p>{{ $cuponInfo['descripcion'] }}</p>
                                        @endif
                                        <p>
                                            <span class="font-medium">{{ __('Descuento') }}:</span>
                                            {{ $cuponInfo['modo_descuento'] === 'porcentaje' ? $cuponInfo['valor_descuento'] . '%' : '$' . number_format($cuponInfo['valor_descuento'], 2, ',', '.') }}
                                            {{ $cuponInfo['aplica_a'] === 'total' ? __('sobre el total') : __('en artículos específicos') }}
                                        </p>
                                        <p>
                                            <span class="font-medium">{{ __('Usos') }}:</span>
                                            {{ $cuponInfo['uso_actual'] }}/{{ $cuponInfo['uso_maximo'] == 0 ? __('ilimitado') : $cuponInfo['uso_maximo'] }}
                                        </p>
                                        @if($cuponInfo['fecha_vencimiento'])
                                            <p>
                                                <span class="font-medium">{{ __('Vence') }}:</span>
                                                {{ $cuponInfo['fecha_vencimiento'] }}
                                            </p>
                                        @endif
                                        @if($cuponInfo['monto_descuento'] > 0)
                                            <p class="text-green-700 dark:text-green-300 font-medium">
                                                {{ __('Ahorro estimado') }}: -${{ number_format($cuponInfo['monto_descuento'], 2, ',', '.') }}
                                            </p>
                                        @endif
                                        @if(!empty($cuponInfo['formas_pago_permitidas']))
                                            <p>
                                                <span class="font-medium">{{ __('Válido para') }}:</span>
                                                {{ implode(', ', $cuponInfo['formas_pago_permitidas']) }}
                                            </p>
                                        @endif
                                        @if($cuponInfo['aplica_a'] === 'articulos' && empty($cuponInfo['articulos_bonificados']))
                                            <p class="text-orange-600 dark:text-orange-400">
                                                {{ __('Ningún artículo del carrito coincide con este cupón') }}
                                            </p>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="aplicarCupon"
                                        type="button"
                                        class="mt-2 w-full px-3 py-1.5 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500
                                            {{ $cuponInfo['aplica_a'] === 'articulos' && empty($cuponInfo['articulos_bonificados']) ? 'opacity-50 cursor-not-allowed' : '' }}"
                                        {{ $cuponInfo['aplica_a'] === 'articulos' && empty($cuponInfo['articulos_bonificados']) ? 'disabled' : '' }}
                                    >
                                        {{ __('Aplicar cupón') }}
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- ══════════════════════════════════════════════════ --}}
                {{-- Sección 3: Canjear Puntos (RF-24) --}}
                {{-- ══════════════════════════════════════════════════ --}}
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden {{ !$clienteSeleccionado || !$puntosDisponibles ? 'opacity-50' : '' }}">
                    <button
                        type="button"
                        @click="seccionActiva = seccionActiva === 'puntos' ? '' : 'puntos'"
                        class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                        {{ !$clienteSeleccionado || !$puntosDisponibles ? 'disabled' : '' }}
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Canjear puntos') }}</span>
                            @if($canjePuntosActivo)
                                <span class="px-2 py-0.5 text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 rounded-full">
                                    ${{ number_format($canjePuntosMonto, 2, ',', '.') }} ({{ $canjePuntosUnidades }} pts) {{ __('aplicado') }}
                                </span>
                            @elseif($puntosDisponibles)
                                <span class="px-1.5 py-0.5 text-[10px] bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300 rounded font-medium">
                                    {{ number_format($this->puntosLibres) }} pts
                                </span>
                            @elseif(!$clienteSeleccionado)
                                <span class="px-1.5 py-0.5 text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-400 rounded">{{ __('Seleccione cliente') }}</span>
                            @else
                                <span class="px-1.5 py-0.5 text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-400 rounded">{{ __('Sin puntos suficientes') }}</span>
                            @endif
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="seccionActiva === 'puntos' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="seccionActiva === 'puntos'" x-collapse class="px-4 py-3 space-y-3">
                        @if($clienteSeleccionado && $puntosDisponibles)
                            @if($canjePuntosActivo)
                                {{-- Canje activo --}}
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-yellow-700 dark:text-yellow-300">
                                                {{ __('Pagar con puntos') }}
                                            </p>
                                            <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-0.5">
                                                ${{ number_format($canjePuntosMonto, 2, ',', '.') }} = {{ $canjePuntosUnidades }} {{ __('puntos') }}
                                            </p>
                                        </div>
                                        <button
                                            wire:click="quitarCanjePuntos"
                                            type="button"
                                            class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-xs font-medium rounded-md hover:bg-red-200 dark:hover:bg-red-900/50"
                                        >
                                            {{ __('Quitar canje') }}
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- Info de saldo y formulario --}}
                            <div class="{{ $canjePuntosActivo ? 'pt-2 border-t border-gray-200 dark:border-gray-700' : '' }}">
                                @if($canjePuntosActivo)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Cambiar monto (reemplaza el actual)') }}:</p>
                                @endif

                                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-2">
                                    <span>{{ __('Saldo disponible') }}: <span class="font-medium text-yellow-600 dark:text-yellow-400">{{ number_format($this->puntosLibres) }} pts</span></span>
                                    <span>{{ __('Máx. canjeable') }}: <span class="font-medium">${{ number_format($this->canjePuntosMaximoReal, 2, ',', '.') }}</span></span>
                                </div>

                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ __('Monto a pagar con puntos') }}
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                            <input
                                                wire:model="canjePuntosInputMonto"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="{{ $this->canjePuntosMaximoReal }}"
                                                class="block w-full pl-8 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 text-sm"
                                                placeholder="0.00"
                                                @keydown.enter="$wire.aplicarCanjePuntos()"
                                            >
                                        </div>
                                    </div>
                                    <button
                                        wire:click="aplicarCanjePuntos"
                                        type="button"
                                        class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 whitespace-nowrap"
                                    >
                                        {{ $canjePuntosActivo ? __('Cambiar') : __('Aplicar') }}
                                    </button>
                                </div>
                            </div>
                        @elseif(!$clienteSeleccionado)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Seleccione un cliente para ver sus puntos disponibles.') }}</p>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('El cliente tiene') }} {{ number_format($puntosSaldoCliente) }} {{ __('puntos') }}.
                                {{ __('Mínimo para canje') }}: {{ $puntosMinimoCanje }} pts.
                            </p>
                        @endif
                    </div>
                </div>

            </div>
        </x-slot:body>

        <x-slot:footer>
            <button
                @click="close()"
                type="button"
                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
            >
                {{ __('Cerrar') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
