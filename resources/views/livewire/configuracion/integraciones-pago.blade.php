<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-bcn-secondary">{{ __('Integraciones de Pago') }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                {{ __('Configure las credenciales de cada proveedor para esta sucursal. Las credenciales se guardan encriptadas.') }}
            </p>
            @if ($sucursalActiva)
                <div class="mt-3 inline-flex items-center px-3 py-1.5 rounded-md bg-bcn-primary/10 dark:bg-bcn-primary/20">
                    <svg class="w-4 h-4 mr-1.5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span class="text-sm font-medium text-bcn-primary">{{ __('Sucursal') }}: {{ $sucursalActiva->nombre }}</span>
                </div>
            @endif
        </div>

        {{-- Cards por integración --}}
        @forelse ($integraciones as $integracion)
            @php
                $config = $configs->get($integracion->id);
                $estado = $config === null
                    ? 'sin_configurar'
                    : ($config->activo ? 'configurado' : 'inactivo');
            @endphp

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    {{-- Info de la integración --}}
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-bcn-primary/10 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-bcn-secondary truncate">{{ $integracion->nombre }}</h3>
                            @if ($integracion->descripcion)
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $integracion->descripcion }}</p>
                            @endif
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($integracion->modos_disponibles ?? [] as $modoCat)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ str_replace('_', ' ', $modoCat) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Estado + Acciones --}}
                    <div class="flex flex-col sm:items-end gap-2">
                        @if ($estado === 'configurado')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                {{ __('Configurado') }}
                                @if ($config)
                                    · {{ ucfirst($config->modo) }}
                                @endif
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

                        <div class="flex items-center gap-2">
                            <button
                                wire:click="abrirConfig({{ $integracion->id }})"
                                @disabled(! $sucursalActiva)
                                class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs sm:text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary dark:focus:ring-offset-gray-800 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
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
                                    wire:click="probarConexion({{ $config->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="probarConexion({{ $config->id }})"
                                    title="{{ __('Probar conexión') }}"
                                    class="inline-flex items-center px-3 py-1.5 border border-green-300 dark:border-green-700 text-xs sm:text-sm font-medium rounded-md text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-green-500 transition-colors duration-150 disabled:opacity-60 disabled:cursor-wait"
                                >
                                    <svg wire:loading.remove wire:target="probarConexion({{ $config->id }})" class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg wire:loading wire:target="probarConexion({{ $config->id }})" class="animate-spin w-4 h-4 sm:mr-1.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
                </div>

                {{-- Sección Stores/POS (solo si es MercadoPago y hay config) --}}
                @if ($config && $integracion->codigo === \App\Models\IntegracionPago::CODIGO_MERCADOPAGO_QR && $sucursalActiva)
                    <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 px-4 sm:px-6 py-4 space-y-4">
                        {{-- Estado de la sucursal en MP --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                {{ __('Sincronización de la sucursal con Mercado Pago') }}
                            </h4>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                <div class="text-xs sm:text-sm">
                                    @if ($sucursalActiva->estaSincronizadaEnMp())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                            {{ __('Sincronizada') }}
                                        </span>
                                        <span class="ml-2 text-gray-500 dark:text-gray-400">Store ID: {{ $sucursalActiva->mp_store_id }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            <span class="w-1.5 h-1.5 mr-1.5 bg-gray-400 rounded-full"></span>
                                            {{ __('No sincronizada') }}
                                        </span>
                                    @endif
                                    @php
                                        $datosCompletos = $sucursalActiva->tieneCoordenadas()
                                            && ! empty($sucursalActiva->direccion)
                                            && ! empty($sucursalActiva->localidad)
                                            && ! empty($sucursalActiva->provincia);
                                    @endphp
                                    @if ($datosCompletos)
                                        <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                            ({{ $sucursalActiva->localidad }}, {{ $sucursalActiva->provinciaNombre() }} · {{ $sucursalActiva->latitud }}, {{ $sucursalActiva->longitud }})
                                        </span>
                                    @else
                                        <span class="ml-2 text-xs text-amber-700 dark:text-amber-400">
                                            ⚠ {{ __('Faltan datos (dirección, localidad, provincia o coordenadas)') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    <button wire:click="abrirModalDireccion"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs sm:text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                        <span class="hidden sm:inline">{{ __('Dirección y coordenadas') }}</span>
                                    </button>

                                    <button wire:click="sincronizarSucursal({{ $config->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizarSucursal({{ $config->id }})"
                                            @disabled(! $datosCompletos)
                                            class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs sm:text-sm font-medium rounded-md text-white bg-bcn-primary hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <svg wire:loading.remove wire:target="sincronizarSucursal({{ $config->id }})" class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <svg wire:loading wire:target="sincronizarSucursal({{ $config->id }})" class="animate-spin w-4 h-4 sm:mr-1.5" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span class="hidden sm:inline">{{ $sucursalActiva->estaSincronizadaEnMp() ? __('Actualizar en MP') : __('Sincronizar con MP') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Cajas y POS --}}
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                {{ __('Cajas (POS) de esta sucursal') }}
                            </h4>

                            @if ($cajas->isEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('No hay cajas activas en esta sucursal') }}</p>
                            @else
                                <div class="space-y-2">
                                    @foreach ($cajas as $caja)
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                            <div class="text-xs sm:text-sm">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $caja->nombre }}</span>
                                                <span class="text-gray-500 dark:text-gray-400 ml-1">#{{ $caja->numero }}</span>
                                                @if ($caja->estaSincronizadaEnMp())
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                                        {{ __('POS Sincronizado') }}
                                                    </span>
                                                @else
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                        <span class="w-1.5 h-1.5 mr-1.5 bg-gray-400 rounded-full"></span>
                                                        {{ __('Sin POS') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-2">
                                                @if ($caja->mp_pos_qr_url)
                                                    <a href="{{ $caja->mp_pos_qr_url }}" target="_blank" rel="noopener"
                                                       title="{{ __('Ver QR estático del POS') }}"
                                                       class="inline-flex items-center px-2 sm:px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        <span class="hidden sm:inline">{{ __('Ver QR') }}</span>
                                                    </a>
                                                @endif
                                                @if ($caja->mp_pos_qr_pdf_url)
                                                    <a href="{{ $caja->mp_pos_qr_pdf_url }}" target="_blank" rel="noopener"
                                                       title="{{ __('Imprimir el QR estático para dejarlo en el mostrador') }}"
                                                       class="inline-flex items-center px-2 sm:px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                        <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                        </svg>
                                                        <span class="hidden sm:inline">{{ __('Imprimir QR') }}</span>
                                                    </a>
                                                @endif

                                                <button wire:click="sincronizarCaja({{ $config->id }}, {{ $caja->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="sincronizarCaja({{ $config->id }}, {{ $caja->id }})"
                                                        @disabled(! $sucursalActiva->estaSincronizadaEnMp())
                                                        title="{{ ! $sucursalActiva->estaSincronizadaEnMp() ? __('Sincronizá primero la sucursal') : '' }}"
                                                        class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                    <svg wire:loading.remove wire:target="sincronizarCaja({{ $config->id }}, {{ $caja->id }})" class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    <svg wire:loading wire:target="sincronizarCaja({{ $config->id }}, {{ $caja->id }})" class="animate-spin w-4 h-4 sm:mr-1.5" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span class="hidden sm:inline">{{ $caja->estaSincronizadaEnMp() ? __('Actualizar') : __('Crear POS') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Sección Terminales Point (solo si es MercadoPago Point y hay config) --}}
                @if ($config && $integracion->codigo === \App\Models\IntegracionPago::CODIGO_MERCADOPAGO_POINT && $sucursalActiva)
                    <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 px-4 sm:px-6 py-4 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {{ __('Terminales por caja') }}
                            </h4>

                            <button wire:click="buscarTerminales({{ $config->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="buscarTerminales({{ $config->id }})"
                                    class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs sm:text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <svg wire:loading.remove wire:target="buscarTerminales({{ $config->id }})" class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg wire:loading wire:target="buscarTerminales({{ $config->id }})" class="animate-spin w-4 h-4 sm:mr-1.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Buscar terminales') }}</span>
                            </button>
                        </div>

                        @if ($cajas->isEmpty())
                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('No hay cajas activas en esta sucursal') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($cajas as $caja)
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="text-xs sm:text-sm">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $caja->nombre }}</span>
                                            <span class="text-gray-500 dark:text-gray-400 ml-1">#{{ $caja->numero }}</span>
                                            @if ($caja->mp_point_terminal_id)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full"></span>
                                                    {{ __('Terminal') }}: {{ \App\Services\IntegracionesPago\SincronizacionMercadoPagoService::formatearTerminal($caja->mp_point_terminal_id) }}
                                                </span>
                                            @else
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                    <span class="w-1.5 h-1.5 mr-1.5 bg-gray-400 rounded-full"></span>
                                                    {{ __('Sin terminal') }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if ($caja->mp_point_terminal_id)
                                                <button wire:click="desvincularTerminal({{ $caja->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="desvincularTerminal({{ $caja->id }})"
                                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors">
                                                    <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                    </svg>
                                                    <span class="hidden sm:inline">{{ __('Desvincular') }}</span>
                                                </button>
                                            @elseif (! empty($terminalesDisponibles))
                                                <div x-data="{ ayudaTerminal: false }" class="relative flex items-center">
                                                    <button type="button"
                                                            @click="ayudaTerminal = !ayudaTerminal"
                                                            @click.outside="ayudaTerminal = false"
                                                            class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                                            title="{{ __('¿Dónde encuentro este número?') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </button>
                                                    <div x-show="ayudaTerminal" x-cloak x-transition
                                                         class="absolute left-0 top-full mt-2 w-64 z-20 rounded-md bg-gray-900 dark:bg-gray-700 text-white text-xs px-3 py-2 shadow-lg">
                                                        {{ __('El número de serie (SC) está en la etiqueta con código de barras pegada en la parte de atrás del Point, en la parte superior.') }}
                                                    </div>
                                                </div>
                                                <select wire:model="terminalSeleccionado.{{ $caja->id }}"
                                                        class="text-xs sm:text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-bcn-primary focus:ring-bcn-primary py-1.5">
                                                    <option value="">{{ __('Elegí una terminal') }}</option>
                                                    @foreach ($terminalesDisponibles as $term)
                                                        <option value="{{ $term['id'] }}">{{ \App\Services\IntegracionesPago\SincronizacionMercadoPagoService::formatearTerminal($term['id']) }} ({{ $term['operating_mode'] ?? '—' }})</option>
                                                    @endforeach
                                                </select>
                                                <button wire:click="vincularTerminal({{ $config->id }}, {{ $caja->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="vincularTerminal({{ $config->id }}, {{ $caja->id }})"
                                                        class="inline-flex items-center px-3 py-1.5 border border-bcn-primary text-xs font-medium rounded-md text-white bg-bcn-primary hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                    <span class="hidden sm:inline">{{ __('Vincular') }}</span>
                                                    <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                    </svg>
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">{{ __('Buscá terminales para vincular') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Al vincular, la terminal se pone en modo integrado (PDV) y los cobros se le envían desde el sistema.') }}
                            {{ __('Después de vincular, reiniciá el dispositivo con conexión a internet para que el cambio de modo se aplique.') }}
                        </p>
                    </div>
                @endif
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
                <div class="space-y-4" x-data="{ mostrarAyuda: false }">
                    {{-- Modo + botón Ayuda --}}
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div class="flex-1">
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

                        <button type="button" @click="mostrarAyuda = !mostrarAyuda"
                                class="inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium rounded-md border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-text="mostrarAyuda ? '{{ __('Ocultar ayuda') }}' : '{{ __('¿Cómo obtener las credenciales?') }}'"></span>
                        </button>
                    </div>

                    {{-- Tutorial colapsable --}}
                    @php
                        $esPointAyuda = $integraciones->firstWhere('id', $integracionPagoId)?->codigo === 'mercadopago_point';
                    @endphp
                    <div x-show="mostrarAyuda" x-collapse x-cloak
                         class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4">
                        <div class="space-y-5 text-sm">
                            {{-- Nota inicial sobre las credenciales por producto --}}
                            <div class="flex items-start gap-2 p-3 rounded-md bg-blue-100/60 dark:bg-blue-900/40 border border-blue-200 dark:border-blue-800">
                                <svg class="w-5 h-5 mt-0.5 text-blue-700 dark:text-blue-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-xs text-blue-800 dark:text-blue-200">
                                    {{ __('Las credenciales de Mercado Pago se generan por aplicación (producto). QR y Point usan aplicaciones separadas, cada una con sus propias credenciales y su propio webhook, aunque pertenezcan a la misma cuenta de Mercado Pago.') }}
                                </p>
                            </div>

                            {{-- Paso 1: Crear aplicación en MP --}}
                            <div>
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2 flex items-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-200 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-bold mr-2">1</span>
                                    {{ __('Crear una aplicación en Mercado Pago') }}
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200 ml-8">
                                    <li>{{ __('Ingrese a') }} <a href="https://www.mercadopago.com.ar/developers/panel" target="_blank" rel="noopener" class="underline font-medium">www.mercadopago.com.ar/developers/panel</a> {{ __('con la cuenta de Mercado Pago de la sucursal') }}</li>
                                    <li>{{ __('Vaya a "Tus integraciones" y haga clic en "Crear aplicación"') }}</li>
                                    <li>{!! __('Tipo de integración: seleccione <strong>"Pagos presenciales"</strong>') !!}</li>
                                    <li>{!! __('Modelo de integración: <strong>"Plataforma"</strong> (si está usando BCN Pymes como plataforma de cobro). Solo elija "Desarrollo propio" si es desarrollador de Mercado Pago.') !!}</li>
                                    @if ($esPointAyuda)
                                        <li>{!! __('Producto a integrar: seleccione <strong>"Point"</strong>') !!}</li>
                                    @else
                                        <li>{!! __('Producto a integrar: seleccione <strong>"QR"</strong>') !!}</li>
                                    @endif
                                    <li>{{ __('Acepte los términos y confirme la creación de la aplicación') }}</li>
                                </ol>
                            </div>

                            {{-- Paso 2: Credenciales de producción --}}
                            <div>
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2 flex items-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-200 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-bold mr-2">2</span>
                                    {{ __('Copiar credenciales de Producción') }}
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200 ml-8">
                                    <li>{{ __('Dentro de la aplicación recién creada, en el menú lateral elija "Credenciales de producción"') }}</li>
                                    <li>{{ __('Es posible que Mercado Pago le pida activar el modo producción (verificar identidad, aceptar condiciones, etc.)') }}</li>
                                    <li>{{ __('Copie el Access Token y la Public Key y péguelos en los campos correspondientes de este formulario') }}</li>
                                </ol>
                            </div>

                            {{-- Paso 3: Credenciales de test --}}
                            <div>
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2 flex items-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-200 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-bold mr-2">3</span>
                                    {{ __('Copiar credenciales de Test') }}
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200 ml-8">
                                    <li>{{ __('En el mismo panel, elija "Credenciales de prueba"') }}</li>
                                    <li>{{ __('Copie el Access Token y la Public Key de prueba') }}</li>
                                    <li>{{ __('Recomendado para validar la integración antes de pasar a producción') }}</li>
                                </ol>
                            </div>

                            {{-- Paso 4: User ID --}}
                            <div>
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2 flex items-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-200 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-bold mr-2">4</span>
                                    {{ __('User ID Mercado Pago') }}
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200 ml-8">
                                    <li>{{ __('En el panel de Mercado Pago, vaya a "Tu negocio" → "Datos de tu cuenta"') }}</li>
                                    <li>{{ __('Copie el "ID de usuario" (número de 9-10 dígitos)') }}</li>
                                    <li>{{ __('Es indispensable para que el sistema sepa a qué sucursal pertenece cada pago recibido') }}</li>
                                </ol>
                            </div>

                        </div>
                    </div>

                    {{-- Credenciales según modo activo --}}
                    @if ($modo === 'produccion')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-md border border-green-200 dark:border-green-800 bg-green-50/50 dark:bg-green-900/10">
                            <div class="sm:col-span-2 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <p class="text-xs font-semibold text-green-800 dark:text-green-300 uppercase tracking-wider">{{ __('Credenciales de Producción') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Access Token') }} *</label>
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
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-md border border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/10">
                            <div class="sm:col-span-2 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-amber-700 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 uppercase tracking-wider">{{ __('Credenciales de Test') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Access Token') }} *</label>
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
                    @endif

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
                    <div x-data="{ mostrarAyudaWebhook: false }">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Webhook Secret') }}</label>
                                <input type="password" wire:model="webhook_secret"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                @error('webhook_secret') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                <button type="button" @click="mostrarAyudaWebhook = !mostrarAyudaWebhook"
                                        class="mt-2 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span x-text="mostrarAyudaWebhook ? '{{ __('Ocultar ayuda') }}' : '{{ __('¿Cómo obtener el Webhook Secret?') }}'"></span>
                                </button>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Timeout (segundos)') }} *</label>
                                <input type="number" wire:model="timeout_segundos" min="30" max="3600"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                                @error('timeout_segundos') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Ayuda colapsable: configurar el webhook en Mercado Pago --}}
                        <div x-show="mostrarAyudaWebhook" x-collapse x-cloak
                             class="mt-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4">
                            <div class="text-sm">
                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                    {{ __('Configurar el Webhook (notificaciones de pago)') }}
                                </h4>
                                <ol class="list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200">
                                    <li>{{ __('Dentro de la aplicación creada en Mercado Pago, vaya al apartado "Notificaciones" y seleccione "Webhooks"') }}</li>
                                    <li>{!! __('Haga clic en "Configurar notificaciones" y asegúrese de estar en la pestaña <strong>"Modo productivo"</strong>') !!}</li>
                                    <li>{{ __('En "URL de producción" ingrese la siguiente dirección:') }}</li>
                                </ol>
                                <div class="mt-1.5 flex items-center gap-2" x-data="{ copiado: false }">
                                    <code class="flex-1 min-w-0 truncate text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded-md px-2 py-1.5 text-blue-900 dark:text-blue-100">{{ route('integraciones.mercadopago.webhook') }}</code>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ route('integraciones.mercadopago.webhook') }}'); copiado = true; setTimeout(() => copiado = false, 2000)"
                                            class="inline-flex items-center px-2 py-1.5 text-xs font-medium rounded-md border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors shrink-0">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        <span x-show="!copiado">{{ __('Copiar') }}</span>
                                        <span x-show="copiado" x-cloak>{{ __('¡Copiado!') }}</span>
                                    </button>
                                </div>
                                <ol class="mt-1.5 list-decimal list-inside space-y-1 text-blue-800 dark:text-blue-200" start="4">
                                    <li>{!! __('En "Eventos", seleccione únicamente <strong>"Order (Mercado Pago)"</strong> (no marque otros eventos)') !!}</li>
                                    <li>{{ __('Guarde la configuración: Mercado Pago generará una clave secreta') }}</li>
                                    <li>{{ __('Copie esa clave y péguela en el campo "Webhook Secret" de este formulario') }}</li>
                                </ol>
                                <p class="mt-2 text-xs text-blue-700 dark:text-blue-300">
                                    {{ __('El webhook permite confirmar los pagos al instante, incluso si el cajero cierra el navegador. Sin webhook, el sistema detecta el pago por consulta periódica (más lento). Cada aplicación de Mercado Pago (QR, Point) tiene su propio webhook y su propia clave secreta: repita estos pasos en cada una.') }}
                                </p>
                            </div>
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

    {{-- Modal de dirección y coordenadas --}}
    @if ($mostrarModalDireccion)
        <x-bcn-modal
            title="{{ __('Dirección y coordenadas de la sucursal') }}"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cerrarModalDireccion"
            submit="guardarDireccion"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="p-3 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-xs text-blue-800 dark:text-blue-200">
                        {{ __('Por ahora las coordenadas se cargan manualmente. Para obtenerlas: abrí Google Maps, buscá la dirección, hacé click derecho sobre el punto exacto y copiá las coordenadas que aparecen primero (formato: -34.123456, -58.654321).') }}
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Dirección') }} *</label>
                        <input type="text" wire:model="direccion"
                               placeholder="Ej: Av. Corrientes 1234"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                        @error('direccion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Localidad') }} *</label>
                            <input type="text" wire:model="localidad" placeholder="Ej: CABA"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('localidad') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Provincia') }} *</label>
                            <select wire:model="provincia"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <option value="">{{ __('Seleccione una provincia') }}</option>
                                @foreach (\App\Models\Sucursal::PROVINCIAS_AR as $codigo => $nombre)
                                    <option value="{{ $codigo }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            @error('provincia') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Latitud') }} *</label>
                            <input type="text" wire:model="latitud" placeholder="-34.6037"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('latitud') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Longitud') }} *</label>
                            <input type="text" wire:model="longitud" placeholder="-58.3816"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('longitud') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
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
                    {{ __('Guardar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
