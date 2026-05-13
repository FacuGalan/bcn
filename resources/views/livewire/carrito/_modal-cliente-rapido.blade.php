{{-- Modal de Alta Rápida de Cliente (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
{{-- Espera: $condicionesIvaCliente (Collection) inyectada vía render() del componente host --}}
@if($mostrarModalClienteRapido)
    <x-bcn-modal
        :show="$mostrarModalClienteRapido"
        title="{{ __('Nuevo Cliente') }}"
        color="bg-bcn-primary"
        maxWidth="3xl"
        onClose="cerrarModalClienteRapido"
        submit="guardarClienteRapido"
    >
        <x-slot:body>
            <div class="space-y-6">
                {{-- Toggle modo alta: Manual / Por CUIT --}}
                <div class="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
                    <button
                        type="button"
                        wire:click="$set('clienteRapidoModoAlta', 'manual')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ $clienteRapidoModoAlta === 'manual' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}"
                    >
                        {{ __('Manual') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$set('clienteRapidoModoAlta', 'cuit')"
                        @if(!$clienteRapidoArcaDisponible) disabled @endif
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ $clienteRapidoModoAlta === 'cuit' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }} {{ !$clienteRapidoArcaDisponible ? 'opacity-50 cursor-not-allowed' : '' }}"
                        @if(!$clienteRapidoArcaDisponible) title="{{ __('No hay certificados ARCA configurados') }}" @endif
                    >
                        {{ __('Por CUIT') }}
                    </button>
                </div>

                {{-- Sección consulta CUIT (modo CUIT) --}}
                @if($clienteRapidoModoAlta === 'cuit')
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ __('Ingrese el CUIT del cliente para obtener sus datos fiscales de ARCA.') }}
                        </p>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="clienteRapidoCuit"
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary"
                                placeholder="20-12345678-9"
                                wire:keydown.enter.prevent="consultarCuitClienteRapido"
                            />
                            <button
                                type="button"
                                wire:click="consultarCuitClienteRapido"
                                wire:loading.attr="disabled"
                                wire:target="consultarCuitClienteRapido"
                                class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                <svg wire:loading wire:target="consultarCuitClienteRapido" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="consultarCuitClienteRapido">{{ __('Consultar') }}</span>
                                <span wire:loading wire:target="consultarCuitClienteRapido">{{ __('Consultando...') }}</span>
                            </button>
                        </div>
                        @if($clienteRapidoErrorCuit)
                            <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300">
                                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $clienteRapidoErrorCuit }}
                                </p>
                            </div>
                        @endif
                        @if($clienteRapidoExitoCuit)
                            <div class="mt-3 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $clienteRapidoExitoCuit }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Datos básicos --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                        <input type="text" wire:model="clienteRapidoNombre" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary" placeholder="{{ __('Nombre del cliente') }}" />
                        @error('clienteRapidoNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Razón Social') }}</label>
                        <input type="text" wire:model="clienteRapidoRazonSocial" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary" placeholder="{{ __('Razón social (si difiere del nombre)') }}" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('CUIT') }}
                            @if($clienteRapidoDatosDesdeArca && $clienteRapidoModoAlta === 'cuit')
                                <svg class="inline w-4 h-4 text-green-500 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            @endif
                        </label>
                        <input
                            type="text"
                            wire:model{{ $clienteRapidoModoAlta === 'manual' ? '.live.debounce.500ms' : '' }}="clienteRapidoCuit"
                            class="w-full rounded-md shadow-sm focus:border-bcn-primary focus:ring-bcn-primary {{ $clienteRapidoDatosDesdeArca && $clienteRapidoModoAlta === 'cuit' ? 'bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-300' : 'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' }}"
                            placeholder="20-12345678-9"
                            {{ $clienteRapidoDatosDesdeArca && $clienteRapidoModoAlta === 'cuit' ? 'readonly' : '' }}
                        />
                        @error('clienteRapidoCuit') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        @if($clienteRapidoModoAlta === 'manual' && $clienteRapidoValidacionCuitMsg)
                            <p class="mt-1 text-xs {{ $clienteRapidoValidacionCuitTipo === 'success' ? 'text-green-600 dark:text-green-400' : '' }}{{ $clienteRapidoValidacionCuitTipo === 'error' ? 'text-red-600 dark:text-red-400' : '' }}">
                                @if($clienteRapidoValidacionCuitTipo === 'success')
                                    <svg class="inline w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                @elseif($clienteRapidoValidacionCuitTipo === 'error')
                                    <svg class="inline w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                @endif
                                {{ $clienteRapidoValidacionCuitMsg }}
                            </p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email') }}</label>
                        <input type="email" wire:model="clienteRapidoEmail" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary" placeholder="cliente@email.com" />
                        @error('clienteRapidoEmail') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" wire:model="clienteRapidoTelefono" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary" placeholder="{{ __('Ej: +54 11 1234-5678') }}" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Dirección') }}</label>
                        <input type="text" wire:model="clienteRapidoDireccion" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary" placeholder="{{ __('Dirección completa') }}" />
                    </div>
                </div>

                {{-- Configuración fiscal --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Configuración Fiscal') }}</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Condición IVA') }}
                            @if($clienteRapidoDatosDesdeArca)
                                <svg class="inline w-4 h-4 text-green-500 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <span class="text-xs font-normal text-green-600 dark:text-green-400">{{ __('Dato de ARCA') }}</span>
                            @endif
                        </label>
                        <select
                            wire:model="clienteRapidoCondicionIvaId"
                            class="w-full rounded-md shadow-sm focus:border-bcn-primary focus:ring-bcn-primary {{ $clienteRapidoDatosDesdeArca ? 'bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-300' : 'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' }}"
                            {{ $clienteRapidoDatosDesdeArca ? 'disabled' : '' }}
                        >
                            @foreach($condicionesIvaCliente as $condicion)
                                <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </x-slot:body>

        <x-slot:footer>
            <button
                type="button"
                @click="close()"
                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm"
            >
                {{ __('Cancelar') }}
            </button>
            <button
                type="submit"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm"
            >
                {{ __('Crear Cliente') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
