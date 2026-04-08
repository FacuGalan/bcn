<div class="space-y-5">
    {{-- Tipo de cupón --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Tipo de cupón') }}</label>
        <div class="flex space-x-4">
            <label class="flex items-center cursor-pointer">
                <input type="radio" wire:model.live="tipoCupon" value="promocional"
                    class="text-bcn-primary focus:ring-bcn-primary border-gray-300 dark:border-gray-600">
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Cupón promocional') }}</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="radio" wire:model.live="tipoCupon" value="puntos"
                    class="text-bcn-primary focus:ring-bcn-primary border-gray-300 dark:border-gray-600">
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Cupón desde puntos') }}</span>
            </label>
        </div>
    </div>

    {{-- Cliente (solo para cupón desde puntos) --}}
    @if($tipoCupon === 'puntos')
    <div class="bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-800 rounded-lg p-4 space-y-4">
        <h4 class="text-sm font-medium text-purple-800 dark:text-purple-300">{{ __('Cupón desde puntos') }}</h4>

        @if(!$clienteCuponId)
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar cliente') }}</label>
                <input type="text" wire:model.live.debounce.300ms="searchClienteCupon"
                    placeholder="{{ __('Nombre, CUIT o teléfono...') }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @if(strlen($searchClienteCupon) >= 2)
                <div class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-60 overflow-y-auto">
                    @forelse($this->resultadosBusquedaClienteCupon as $cliente)
                    <button type="button" wire:click="seleccionarClienteCupon({{ $cliente->id }})"
                        class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $cliente->nombre }}</span>
                            <span class="text-sm font-bold text-bcn-primary">{{ number_format($cliente->puntos_saldo_cache) }} pts</span>
                        </div>
                    </button>
                    @empty
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron clientes') }}</div>
                    @endforelse
                </div>
                @endif
            </div>
            @error('clienteCuponId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        @else
            <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-lg">
                <div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $this->clienteCupon?->nombre }}</span>
                    <span class="ml-2 text-sm font-bold text-bcn-primary">{{ number_format($this->clienteCupon?->puntos_saldo_cache ?? 0) }} pts</span>
                </div>
                <button type="button" wire:click="limpiarClienteCupon" class="text-gray-400 hover:text-red-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Puntos a consumir') }}</label>
                <input type="number" wire:model="puntosConsumidos" min="1" max="{{ $this->clienteCupon?->puntos_saldo_cache ?? 0 }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @error('puntosConsumidos') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        @endif
    </div>
    @endif

    {{-- Código --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Código de cupón') }}</label>
        <div class="flex space-x-2">
            <input type="text" wire:model="codigo"
                class="block flex-1 rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm font-mono dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <button type="button" wire:click="generarNuevoCodigo"
                class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300" title="{{ __('Regenerar código') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>
        @error('codigo') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    {{-- Descripción --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción') }}</label>
        <input type="text" wire:model="descripcion"
            placeholder="{{ __('Ej: Descuento por inauguración') }}"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>

    {{-- Modo descuento y valor --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de descuento') }}</label>
            <select wire:model.live="modoDescuento"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="porcentaje">{{ __('Porcentaje') }} (%)</option>
                <option value="monto_fijo">{{ __('Monto fijo') }} ($)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $modoDescuento === 'porcentaje' ? __('Porcentaje') : __('Monto') }}
            </label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                    {{ $modoDescuento === 'porcentaje' ? '%' : '$' }}
                </span>
                <input type="number" wire:model="valorDescuento" step="0.01"
                    min="0.01" {{ $modoDescuento === 'porcentaje' ? 'max=100' : '' }}
                    class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            @error('valorDescuento') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
    </div>

    {{-- Aplica a --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aplica a') }}</label>
        <select wire:model.live="aplicaA"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <option value="total">{{ __('Total de la venta') }}</option>
            <option value="articulos">{{ __('Artículos específicos') }}</option>
        </select>
    </div>

    {{-- Artículos específicos --}}
    @if($aplicaA === 'articulos')
    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Artículos que bonifica') }}</label>
        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="searchArticulo"
                placeholder="{{ __('Buscar artículo por nombre, código o cód. barras...') }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                @keydown.enter.prevent="$wire.agregarPrimerArticuloCupon()">
            @if(strlen($searchArticulo) >= 2)
            <div class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-48 overflow-y-auto">
                @forelse($this->resultadosBusquedaArticulo as $articulo)
                <button type="button" wire:click="agregarArticulo({{ $articulo->id }})"
                    class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0 text-sm">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $articulo->codigo }}</span>
                </button>
                @empty
                <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron artículos') }}</div>
                @endforelse
            </div>
            @endif
        </div>
        @if(count($articulosSeleccionados) > 0)
        <div class="space-y-2">
            @foreach($articulosSeleccionados as $artId => $art)
            <div class="flex items-center gap-3 px-3 py-2 bg-gray-50 dark:bg-gray-600 rounded">
                <div class="flex-1">
                    <span class="text-sm text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $art['codigo'] }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ __('Cant.') }}</label>
                    <input type="number" wire:model="articulosSeleccionados.{{ $artId }}.cantidad" min="1"
                        placeholder="∞"
                        class="w-16 text-center rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white">
                </div>
                <button type="button" wire:click="quitarArticulo({{ $artId }})" class="text-red-400 hover:text-red-600 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cantidad vacía = aplica a todas las unidades') }}</p>
        @else
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Agrega al menos un artículo') }}</p>
        @endif
    </div>
    @endif

    {{-- Uso máximo y vencimiento --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Uso máximo') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">0 = {{ __('Usos ilimitados') }}</p>
            <input type="number" wire:model="usoMaximo" min="0"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha de vencimiento') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Dejar vacío si no vence') }}</p>
            <input type="date" wire:model="fechaVencimiento"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
    </div>

    {{-- Formas de pago válidas --}}
    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Formas de pago válidas') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Si no selecciona ninguna, el cupón aplica a todas las formas de pago') }}</p>
        </div>
        @if(count($formasPagoDisponibles) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($formasPagoDisponibles as $fp)
            <label class="flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-600 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500 transition-colors">
                <input type="checkbox" wire:model="formasPagoSeleccionadas" value="{{ $fp['id'] }}"
                    class="rounded border-gray-300 dark:border-gray-500 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $fp['nombre'] }}</span>
            </label>
            @endforeach
        </div>
        @else
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('No hay formas de pago configuradas') }}</p>
        @endif
    </div>
</div>
