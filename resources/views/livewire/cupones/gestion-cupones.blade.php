<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Cupones') }}</h2>
                        {{-- Mobile: icon-only --}}
                        <div class="sm:hidden flex gap-2">
                            <button wire:click="abrirHistorialModal"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                title="{{ __('Historial de uso') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            <button wire:click="abrirCrearModal"
                                class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                                title="{{ __('Crear cupón') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Crea y gestiona cupones de descuento para tus clientes') }}</p>
                </div>
                {{-- Desktop: full buttons --}}
                <div class="hidden sm:flex gap-3">
                    <button wire:click="abrirHistorialModal"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Historial de uso') }}
                    </button>
                    <button wire:click="abrirCrearModal"
                        class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Crear cupón') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Filtros + Listado --}}
        @include('livewire.cupones.partials.tab-listado')
    </div>

    {{-- Modal Crear Cupón --}}
    @if($showCrearModal)
        <x-bcn-modal
            :title="__('Crear cupón')"
            color="bg-bcn-primary"
            maxWidth="2xl"
            onClose="cerrarCrearModal"
            submit="crearCupon"
        >
            <x-slot:body>
                @include('livewire.cupones.partials.form-crear')
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarCrearModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-primary/90">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Crear cupón') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Editar Cupón --}}
    @if($showEditarModal)
        <x-bcn-modal
            :title="__('Editar cupón')"
            color="bg-bcn-primary"
            :maxWidth="$editCuponFueUsado ? 'md' : '2xl'"
            onClose="cancelarEdicion"
            submit="guardarEdicion"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Activo') }}</label>
                        <button type="button" wire:click="$toggle('editActivo')"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 {{ $editActivo ? 'bg-bcn-primary' : 'bg-gray-200 dark:bg-gray-600' }}"
                            role="switch">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 {{ $editActivo ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción') }}</label>
                        <input type="text" wire:model="editDescripcion"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>

                    {{-- Campos editables solo si el cupón NO fue usado --}}
                    @if(!$editCuponFueUsado)
                        {{-- Modo descuento y valor --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de descuento') }}</label>
                                <select wire:model.live="editModoDescuento"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="porcentaje">{{ __('Porcentaje') }} (%)</option>
                                    <option value="monto_fijo">{{ __('Monto fijo') }} ($)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ $editModoDescuento === 'porcentaje' ? __('Porcentaje') : __('Monto') }}
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                        {{ $editModoDescuento === 'porcentaje' ? '%' : '$' }}
                                    </span>
                                    <input type="number" wire:model="editValorDescuento" step="0.01"
                                        min="0.01" {{ $editModoDescuento === 'porcentaje' ? 'max=100' : '' }}
                                        class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                @error('editValorDescuento') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Aplica a --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aplica a') }}</label>
                            <select wire:model.live="editAplicaA"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="total">{{ __('Total de la venta') }}</option>
                                <option value="articulos">{{ __('Artículos específicos') }}</option>
                            </select>
                        </div>

                        {{-- Artículos específicos --}}
                        @if($editAplicaA === 'articulos')
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Artículos que bonifica') }}</label>
                            <div class="relative">
                                <input type="text" wire:model.live.debounce.300ms="editSearchArticulo"
                                    placeholder="{{ __('Buscar artículo por nombre, código o cód. barras...') }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    @keydown.enter.prevent="$wire.editAgregarPrimerArticulo()">
                                @if(strlen($editSearchArticulo) >= 2)
                                <div class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-200 dark:border-gray-600 max-h-48 overflow-y-auto">
                                    @forelse($this->resultadosBusquedaEditArticulo as $articulo)
                                    <button type="button" wire:click="editAgregarArticulo({{ $articulo->id }})"
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
                            @if(count($editArticulosSeleccionados) > 0)
                            <div class="space-y-2">
                                @foreach($editArticulosSeleccionados as $artId => $art)
                                <div class="flex items-center gap-3 px-3 py-2 bg-gray-50 dark:bg-gray-600 rounded">
                                    <div class="flex-1">
                                        <span class="text-sm text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $art['codigo'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ __('Cant.') }}</label>
                                        <input type="number" wire:model="editArticulosSeleccionados.{{ $artId }}.cantidad" min="1"
                                            placeholder="&#8734;"
                                            class="w-16 text-center rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-500 dark:text-white">
                                    </div>
                                    <button type="button" wire:click="editQuitarArticulo({{ $artId }})" class="text-red-400 hover:text-red-600 flex-shrink-0">
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
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha de vencimiento') }}</label>
                        <input type="date" wire:model="editFechaVencimiento"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Uso máximo') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">0 = {{ __('Usos ilimitados') }}</p>
                        <input type="number" wire:model="editUsoMaximo" min="0"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    {{-- Formas de pago válidas --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Formas de pago válidas') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Si no selecciona ninguna, el cupón aplica a todas las formas de pago') }}</p>
                        @if(count($formasPagoDisponibles) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($formasPagoDisponibles as $fp)
                            <label class="flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-gray-600 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-500 transition-colors">
                                <input type="checkbox" wire:model="editFormasPagoSeleccionadas" value="{{ $fp['id'] }}"
                                    class="rounded border-gray-300 dark:border-gray-500 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $fp['nombre'] }}</span>
                            </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cancelarEdicion"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-bcn-primary border border-transparent rounded-md shadow-sm hover:bg-bcn-primary/90">
                    {{ __('Guardar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal Historial de Uso --}}
    @if($showHistorialModal)
        <x-bcn-modal
            :title="__('Historial de uso')"
            color="bg-gray-600"
            maxWidth="4xl"
            onClose="cerrarHistorialModal"
        >
            <x-slot:body>
                @include('livewire.cupones.partials.tab-historial')
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
