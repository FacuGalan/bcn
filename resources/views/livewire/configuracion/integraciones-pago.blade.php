<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-bcn-secondary">{{ __('Integraciones de Pago') }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                {{ __('Configure las credenciales de cada proveedor por sucursal. Las credenciales se guardan encriptadas.') }}
            </p>
        </div>

        {{-- Cards por integración --}}
        @forelse ($integraciones as $integracion)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                {{-- Header del card --}}
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-bcn-primary/10 flex items-center justify-center">
                            <svg class="w-6 h-6 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-bcn-secondary">{{ $integracion->nombre }}</h3>
                            @if ($integracion->descripcion)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $integracion->descripcion }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="hidden sm:flex flex-wrap gap-1">
                        @foreach ($integracion->modos_disponibles ?? [] as $modo)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ str_replace('_', ' ', $modo) }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Lista de sucursales --}}
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($sucursales as $sucursal)
                        @php
                            $config = $configs->get($integracion->id.':'.$sucursal->id);
                            $estado = $config === null
                                ? 'sin_configurar'
                                : ($config->activo ? 'configurado' : 'inactivo');
                        @endphp

                        <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $sucursal->nombre }}</p>
                                    @if ($config)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Modo') }}: <span class="font-medium">{{ ucfirst($config->modo) }}</span>
                                            @if ($config->user_id_externo)
                                                · {{ __('User ID Mercado Pago') }}: {{ $config->user_id_externo }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                {{-- Badge de estado --}}
                                @if ($estado === 'configurado')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                        {{ __('Configurado') }}
                                    </span>
                                @elseif ($estado === 'inactivo')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <span class="w-1.5 h-1.5 mr-1.5 bg-yellow-500 rounded-full"></span>
                                        {{ __('Inactivo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        <span class="w-1.5 h-1.5 mr-1.5 bg-gray-400 rounded-full"></span>
                                        {{ __('Sin configurar') }}
                                    </span>
                                @endif

                                {{-- Acciones --}}
                                <button
                                    wire:click="abrirConfig({{ $integracion->id }}, {{ $sucursal->id }})"
                                    class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs sm:text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="hidden sm:inline">{{ $config ? __('Editar') : __('Configurar') }}</span>
                                </button>

                                @if ($config)
                                    <button
                                        type="button"
                                        disabled
                                        title="{{ __('Disponible en la próxima fase') }}"
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs sm:text-sm font-medium rounded-md text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-700/50 cursor-not-allowed"
                                    >
                                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="hidden sm:inline">{{ __('Probar conexión') }}</span>
                                    </button>

                                    <button
                                        wire:click="eliminar({{ $config->id }})"
                                        wire:confirm="{{ __('¿Está seguro de eliminar esta configuración?') }}"
                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 dark:border-red-600 text-xs sm:text-sm font-medium rounded-md text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-500 transition-colors duration-150"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if ($sucursales->isEmpty())
                        <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No hay sucursales disponibles') }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No hay integraciones disponibles') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Modal de configuración --}}
    @if ($mostrarModal)
        <x-bcn-modal
            :title="$editMode ? __('Editar configuración de integración') : __('Configurar integración')"
            color="bg-bcn-primary"
            maxWidth="2xl"
            onClose="cerrarModal"
            submit="guardar"
        >
            <x-slot:body>
                <div class="space-y-4">
                    {{-- Modo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Modo (Test / Producción)') }} *</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" wire:model.live="modo" value="test"
                                       class="border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Test') }}</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" wire:model.live="modo" value="produccion"
                                       class="border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Producción') }}</span>
                            </label>
                        </div>
                        @error('modo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Credenciales de producción --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Credenciales de Producción') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Access Token') }}</label>
                            <input type="password" wire:model="access_token_produccion"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('access_token_produccion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Public Key') }}</label>
                            <input type="text" wire:model="public_key_produccion"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('public_key_produccion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Credenciales de test --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Credenciales de Test') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Access Token') }}</label>
                            <input type="password" wire:model="access_token_test"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('access_token_test') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Public Key') }}</label>
                            <input type="text" wire:model="public_key_test"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('public_key_test') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- User ID externo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('User ID Mercado Pago') }}
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">{{ __('(crítico para resolver webhooks)') }}</span>
                        </label>
                        <input type="text" wire:model="user_id_externo"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                        @error('user_id_externo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Webhook secret + timeout --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Webhook Secret') }}</label>
                            <input type="password" wire:model="webhook_secret"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('webhook_secret') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Timeout (segundos)') }} *</label>
                            <input type="number" wire:model="timeout_segundos" min="30" max="3600"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('timeout_segundos') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Activo --}}
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="activo" id="activo"
                               class="rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary" />
                        <label for="activo" class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Configuración activa') }}</label>
                    </div>
                </div>
            </x-slot:body>

            <x-slot:footer>
                <button type="button" @click="close()"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ $editMode ? __('Actualizar') : __('Guardar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
