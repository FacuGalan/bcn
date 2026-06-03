<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-bcn-secondary dark:text-white">
                {{ __('Configuración de Empresa') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                {{ __('Gestiona los datos de tu empresa, CUITs y sucursales') }}
            </p>
        </div>

        {{-- Tabs --}}
        <div class="mb-6">
            <nav class="flex space-x-1 sm:space-x-4 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
                <button
                    wire:click="cambiarTab('empresa')"
                    class="flex items-center px-3 sm:px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $tabActivo === 'empresa' ? 'text-bcn-primary border-bcn-primary' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    {{ __('Empresa') }}
                </button>
                <button
                    wire:click="cambiarTab('cuits')"
                    class="flex items-center px-3 sm:px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $tabActivo === 'cuits' ? 'text-bcn-primary border-bcn-primary' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('CUITs') }}
                </button>
                <button
                    wire:click="cambiarTab('sucursales')"
                    class="flex items-center px-3 sm:px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $tabActivo === 'sucursales' ? 'text-bcn-primary border-bcn-primary' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('Sucursales') }}
                </button>
                <button
                    wire:click="cambiarTab('cajas')"
                    class="flex items-center px-3 sm:px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $tabActivo === 'cajas' ? 'text-bcn-primary border-bcn-primary' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    {{ __('Cajas') }}
                </button>
            </nav>
        </div>

        {{-- Contenido de Tabs --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            @if($tabActivo === 'empresa')
                @include('livewire.configuracion.partials.tab-empresa')
            @elseif($tabActivo === 'cuits')
                @include('livewire.configuracion.partials.tab-cuits')
            @elseif($tabActivo === 'sucursales')
                @include('livewire.configuracion.partials.tab-sucursales')
            @elseif($tabActivo === 'cajas')
                @include('livewire.configuracion.partials.tab-cajas')
            @endif
        </div>

        {{-- Modal CUIT --}}
        @if($mostrarModalCuit)
            @include('livewire.configuracion.partials.modal-cuit')
        @endif

        {{-- Modal Configuración Sucursal --}}
        @if($mostrarModalConfigSucursal)
            @include('livewire.configuracion.partials.modal-config-sucursal')
        @endif

        {{-- Modal Confirmación Eliminar CUIT --}}
        @if($mostrarConfirmacionEliminarCuit)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('mostrarConfirmacionEliminarCuit', false)"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                        {{ __('Eliminar CUIT') }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('¿Está seguro de que desea eliminar este CUIT? Esta acción eliminará también todos los puntos de venta asociados y no se puede deshacer.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                wire:click="eliminarCuit"
                                type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Eliminar') }}
                            </button>
                            <button
                                wire:click="$set('mostrarConfirmacionEliminarCuit', false)"
                                type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal Configuración de Caja --}}
        @if($mostrarModalConfigCaja)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalConfigCaja"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <form wire:submit.prevent="guardarConfigCaja">
                            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                                {{-- Header --}}
                                <div class="mb-6">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ __('Configurar Caja') }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('Define los parámetros de operación de la caja') }}
                                    </p>
                                </div>

                                <div class="space-y-4">
                                    {{-- Nombre --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Nombre') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model.live="configCajaNombre"
                                            maxlength="15"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="{{ __('Ej: Caja Mostrador, Delivery...') }}"
                                        >
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Máximo 15 caracteres') }} ({{ mb_strlen($configCajaNombre ?? '') }}/15)
                                        </p>
                                        @error('configCajaNombre')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Límite de Efectivo --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Límite de Efectivo') }}
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:model="configCajaLimiteEfectivo"
                                                class="block w-full pl-7 pr-12 rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Monto máximo de efectivo permitido en caja. Dejar vacío para sin límite.') }}
                                        </p>
                                        @error('configCajaLimiteEfectivo')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Modo de Carga Inicial --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Modo de Carga Inicial del Turno') }}
                                        </label>
                                        <select
                                            wire:model.live="configCajaModoCargaInicial"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >
                                            @foreach(\App\Models\Caja::MODOS_CARGA_INICIAL as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if($configCajaModoCargaInicial === 'manual')
                                                {{ __('El operador ingresa manualmente el monto inicial al abrir el turno') }}
                                            @elseif($configCajaModoCargaInicial === 'ultimo_cierre')
                                                {{ __('Se usa automáticamente el saldo del último cierre') }}
                                            @else
                                                {{ __('Se usa automáticamente el monto fijo definido') }}
                                            @endif
                                        </p>
                                        @error('configCajaModoCargaInicial')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Monto Fijo Inicial (solo si modo es monto_fijo) --}}
                                    @if($configCajaModoCargaInicial === 'monto_fijo')
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ __('Monto Fijo Inicial') }} <span class="text-red-500">*</span>
                                            </label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">$</span>
                                                </div>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    wire:model="configCajaMontoFijoInicial"
                                                    class="block w-full pl-7 pr-12 rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    placeholder="0.00"
                                                >
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ __('Este monto se usará automáticamente al abrir cada turno') }}
                                            </p>
                                            @error('configCajaMontoFijoInicial')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif

                                    {{-- Pantalla orientada al cliente (segundo monitor del puesto) --}}
                                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model="configCajaUsaPantallaCliente"
                                                class="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700"
                                            >
                                            <span class="text-sm">
                                                <span class="block font-medium text-gray-700 dark:text-gray-300">
                                                    {{ __('Usa pantalla orientada al cliente') }}
                                                </span>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                                    {{ __('Si este puesto tiene un segundo monitor apuntando al cliente, el QR de cobro se mostrará ahí en lugar de en tu pantalla. Requiere monitores en modo "Extender" y navegador Chrome o Edge.') }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button
                                    type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ __('Guardar') }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="cerrarModalConfigCaja"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ __('Cancelar') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal Personalizar Pantalla Cliente (2da pantalla) --}}
        @if($mostrarModalPersonalizarPantalla)
            <x-bcn-modal
                show
                :title="__('Personalizar 2da pantalla') . ' — ' . $pcSucursalNombre"
                color="bg-violet-600"
                maxWidth="4xl"
                submit="guardarPersonalizarPantalla"
                onClose="cerrarModalPersonalizarPantalla"
            >
                <x-slot:body>
                    <div
                        x-data="{
                            mostrarLogo: @entangle('pcMostrarLogo'),
                            mostrarNombre: @entangle('pcMostrarNombre'),
                            colorFondo: @entangle('pcColorFondo'),
                            animacion: @entangle('pcAnimacion'),
                            colorAcento: @entangle('pcColorAcento'),
                            colorTexto: @entangle('pcColorTexto'),
                            mensajeIdle: @entangle('pcMensajeIdle'),
                            tamanoLogo: @entangle('pcTamanoLogo'),
                            colorTextoCustom: '#ffffff',
                            get autoTexto() { return this.colorTexto === 'auto'; },
                            set autoTexto(v) { this.colorTexto = v ? 'auto' : this.colorTextoCustom; },
                            contraste(hex) {
                                const c = (hex || '#000000').replace('#', '');
                                if (c.length !== 6) return '#ffffff';
                                const r = parseInt(c.substr(0, 2), 16);
                                const g = parseInt(c.substr(2, 2), 16);
                                const b = parseInt(c.substr(4, 2), 16);
                                return (0.299 * r + 0.587 * g + 0.114 * b) > 140 ? '#111827' : '#ffffff';
                            },
                            get textoEfectivo() {
                                return this.colorTexto === 'auto' ? this.contraste(this.colorFondo) : this.colorTexto;
                            },
                            get logoSize() {
                                return { sm: '3rem', md: '4.5rem', lg: '6rem' }[this.tamanoLogo] || '4.5rem';
                            },
                            init() {
                                if (this.colorTexto !== 'auto') this.colorTextoCustom = this.colorTexto;
                            }
                        }"
                        class="grid grid-cols-1 lg:grid-cols-2 gap-6"
                    >
                        {{-- Columna de controles --}}
                        <div class="space-y-5">
                            {{-- Toggles logo / nombre --}}
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" x-model="mostrarLogo"
                                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Mostrar logo') }}</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" x-model="mostrarNombre"
                                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Mostrar nombre') }}</span>
                                </label>
                            </div>

                            {{-- Color de fondo --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Color de fondo') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="colorFondo"
                                        class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600 bg-transparent cursor-pointer p-0.5">
                                    <input type="text" x-model="colorFondo" maxlength="7"
                                        class="w-28 rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                @error('pcColorFondo') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Color de acento --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Color de acento') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="colorAcento"
                                        class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600 bg-transparent cursor-pointer p-0.5">
                                    <input type="text" x-model="colorAcento" maxlength="7"
                                        class="w-28 rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se usa en el monto y los detalles destacados.') }}</p>
                                @error('pcColorAcento') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Color de texto --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Color de texto') }}</label>
                                <label class="flex items-center gap-3 cursor-pointer mb-2">
                                    <input type="checkbox" x-model="autoTexto"
                                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 dark:bg-gray-700">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Contraste automático según el fondo') }}</span>
                                </label>
                                <div class="flex items-center gap-2" x-show="!autoTexto" x-cloak>
                                    <input type="color" x-model="colorTextoCustom" @input="colorTexto = colorTextoCustom"
                                        class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600 bg-transparent cursor-pointer p-0.5">
                                    <input type="text" x-model="colorTextoCustom" @input="colorTexto = colorTextoCustom" maxlength="7"
                                        class="w-28 rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                @error('pcColorTexto') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Animación --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Animación') }}</label>
                                <select x-model="animacion"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="ninguna">{{ __('Ninguna') }}</option>
                                    <option value="respiracion">{{ __('Respiración + glow') }}</option>
                                    <option value="aurora">{{ __('Aurora + flotación') }}</option>
                                </select>
                                @error('pcAnimacion') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Tamaño del logo --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tamaño del logo') }}</label>
                                <select x-model="tamanoLogo"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="sm">{{ __('Pequeño') }}</option>
                                    <option value="md">{{ __('Mediano') }}</option>
                                    <option value="lg">{{ __('Grande') }}</option>
                                </select>
                                @error('pcTamanoLogo') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            {{-- Mensaje idle --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Mensaje de espera') }}</label>
                                <input type="text" x-model="mensajeIdle" maxlength="60"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    placeholder="{{ __('Listo para cobrar') }}">
                                @error('pcMensajeIdle') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Columna de preview en vivo --}}
                        <div>
                            {{-- Animaciones del preview (replican las de la pantalla cliente real,
                                 escaladas para el mini-recuadro). Scopeadas con .pc-prev-*. --}}
                            <style>
                                .pc-prev-aurora {
                                    position: absolute; inset: -25%; z-index: 0; opacity: 0;
                                    background:
                                        radial-gradient(45% 45% at 20% 25%, var(--pc-acento) 0%, transparent 60%),
                                        radial-gradient(40% 40% at 80% 30%, var(--pc-acento) 0%, transparent 55%),
                                        radial-gradient(50% 50% at 50% 85%, var(--pc-acento) 0%, transparent 60%);
                                    background-size: 200% 200%;
                                    filter: blur(28px) saturate(140%);
                                    transition: opacity .8s ease;
                                }
                                .pc-prev-anim-aurora .pc-prev-aurora {
                                    opacity: .4;
                                    animation: pc-prev-aurora-move 22s ease-in-out infinite alternate;
                                }
                                .pc-prev-anim-aurora .pc-prev-logo { animation: pc-prev-flota 7s ease-in-out infinite; }
                                .pc-prev-anim-respiracion .pc-prev-logo,
                                .pc-prev-anim-respiracion .pc-prev-nombre {
                                    animation: pc-prev-respira 5.5s ease-in-out infinite;
                                }
                                @keyframes pc-prev-respira {
                                    0%, 100% { transform: scale(1); opacity: .9; filter: drop-shadow(0 0 0 transparent); }
                                    50%      { transform: scale(1.05); opacity: 1; filter: drop-shadow(0 0 10px var(--pc-acento)); }
                                }
                                @keyframes pc-prev-aurora-move {
                                    0%   { background-position: 0% 0%, 100% 0%, 50% 100%; }
                                    50%  { background-position: 40% 60%, 60% 40%, 30% 50%; }
                                    100% { background-position: 100% 100%, 0% 100%, 70% 0%; }
                                }
                                @keyframes pc-prev-flota {
                                    0%, 100% { transform: translateY(0); }
                                    50%      { transform: translateY(-8px); }
                                }
                                @media (prefers-reduced-motion: reduce) {
                                    .pc-prev-logo, .pc-prev-nombre { animation: none !important; }
                                    .pc-prev-aurora { animation: none !important; }
                                }
                            </style>

                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Vista previa') }}</p>
                            <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-600 shadow-inner aspect-[4/3] relative flex flex-col items-center justify-center text-center px-6"
                                :class="{ 'pc-prev-anim-aurora': animacion === 'aurora', 'pc-prev-anim-respiracion': animacion === 'respiracion' }"
                                :style="`background-color: ${colorFondo}; color: ${textoEfectivo}; --pc-acento: ${colorAcento};`">

                                {{-- Capa de fondo para la aurora --}}
                                <div class="pc-prev-aurora" aria-hidden="true"></div>

                                {{-- Contenido sobre la aurora --}}
                                <div class="relative z-10 flex flex-col items-center w-full">
                                    <template x-if="mostrarLogo && '{{ $pcLogoUrl }}'">
                                        <img src="{{ $pcLogoUrl }}" alt="logo" class="pc-prev-logo object-contain mb-3" :style="`max-height: ${logoSize}; max-width: 60%;`">
                                    </template>
                                    <template x-if="mostrarNombre">
                                        <p class="pc-prev-nombre text-lg font-bold mb-2" x-text="'{{ $pcSucursalNombre }}'"></p>
                                    </template>
                                    <p class="text-sm font-light opacity-70" x-text="mensajeIdle || '{{ __('Listo para cobrar') }}'"></p>
                                    <p class="text-2xl font-extrabold mt-3" :style="`color: ${colorAcento};`">$1.234,00</p>
                                </div>

                                {{-- Footer Powered by --}}
                                <div class="absolute bottom-2 left-0 right-0 z-10 flex items-center justify-center gap-1.5 opacity-40">
                                    <span class="text-[10px]" :style="`color: ${textoEfectivo};`">{{ __('Powered by') }}</span>
                                    <img src="{{ asset('banner_bcn.png') }}" alt="BCNSOFT" class="h-3 object-contain">
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('La configuración aplica a todas las cajas de esta sucursal.') }}</p>
                        </div>
                    </div>
                </x-slot:body>

                <x-slot:footer>
                    <button type="button" wire:click="cerrarModalPersonalizarPantalla"
                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-violet-600 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500">
                        {{ __('Guardar') }}
                    </button>
                </x-slot:footer>
            </x-bcn-modal>
        @endif

        {{-- Modal Confirmación Eliminar Punto de Venta --}}
        @if($mostrarConfirmacionEliminarPV)
            <div class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminarPuntoVenta"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                        {{ __('Eliminar Punto de Venta') }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('¿Está seguro de que desea eliminar el punto de venta') }} <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ $pvEliminarNumero }}</span>{{ __('? Esta acción no se puede deshacer.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                wire:click="eliminarPuntoVenta"
                                type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Eliminar') }}
                            </button>
                            <button
                                wire:click="cancelarEliminarPuntoVenta"
                                type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
