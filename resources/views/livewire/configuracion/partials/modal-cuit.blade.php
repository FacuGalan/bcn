{{-- Modal CUIT --}}
<x-bcn-modal
    :title="$modoEdicionCuit ? __('Editar CUIT') : __('Nuevo CUIT')"
    color="bg-bcn-primary"
    maxWidth="4xl"
    onClose="cerrarModalCuit"
    submit="guardarCuit"
>
    <x-slot:body>
        {{-- Seccion: Datos Basicos --}}
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
                {{ __('Datos del Contribuyente') }}
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- CUIT --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('CUIT') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model.blur="cuitNumeroCuit"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono"
                        placeholder="20123456789"
                        maxlength="11"
                    >
                    @error('cuitNumeroCuit')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Razon Social --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Razon Social') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="cuitRazonSocial"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="{{ __('Razon social segun AFIP') }}"
                    >
                    @error('cuitRazonSocial')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nombre Fantasia --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Nombre Fantasia') }}
                    </label>
                    <input
                        type="text"
                        wire:model="cuitNombreFantasia"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="{{ __('Nombre comercial') }}"
                    >
                </div>

                {{-- Condicion IVA --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Condicion IVA') }} <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="cuitCondicionIvaId"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                        <option value="">{{ __('Seleccionar...') }}</option>
                        @foreach($this->condicionesIva as $condicion)
                            <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                        @endforeach
                    </select>
                    @error('cuitCondicionIvaId')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nro IIBB --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Nro. Ingresos Brutos') }}
                    </label>
                    <input
                        type="text"
                        wire:model="cuitNumeroIibb"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        placeholder="{{ __('Numero de IIBB') }}"
                    >
                </div>
            </div>
        </div>


        {{-- Seccion: AFIP --}}
        <div class="mb-6 pt-6 border-t border-gray-200 dark:border-gray-600">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
                {{ __('Configuracion AFIP') }}
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Fecha Inicio Actividades --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Inicio de Actividades') }}
                    </label>
                    <input
                        type="date"
                        wire:model="cuitFechaInicioActividades"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                </div>

                {{-- Entorno AFIP --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Entorno AFIP') }} <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="cuitEntornoAfip"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                        <option value="testing">{{ __('Testing (Homologacion)') }}</option>
                        <option value="produccion">{{ __('Produccion') }}</option>
                    </select>
                </div>

                {{-- Activo --}}
                <div class="flex items-center pt-6">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="cuitActivo"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-bcn-primary/20 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-bcn-primary"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('CUIT Activo') }}</span>
                    </label>
                </div>
            </div>

            {{-- Certificados Digitales --}}
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-600">
                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    {{ __('Certificados Digitales AFIP') }}
                </h5>

                @if($cuitTieneCertificado && $cuitTieneClave)
                    {{-- Estado: Certificados cargados --}}
                    <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ __('Certificados configurados') }}</p>
                                <p class="text-xs text-green-600 dark:text-green-400">{{ __('El CUIT tiene certificado y clave privada cargados') }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="eliminarCertificadosCuit"
                            wire:confirm="{{ __('Esta seguro de eliminar los certificados? Debera cargarlos nuevamente.') }}"
                            class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium"
                        >
                            {{ __('Eliminar') }}
                        </button>
                    </div>
                @else
                    {{-- Formulario para cargar certificados --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Certificado --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                                {{ __('Certificado (.crt, .pem)') }}
                                @if($cuitTieneCertificado)
                                    <span class="text-green-500 ml-1">- {{ __('Cargado') }}</span>
                                @endif
                            </label>
                            <div class="relative">
                                <input
                                    type="file"
                                    wire:model="cuitCertificado"
                                    accept=".crt,.pem"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                >
                                <div class="flex items-center justify-center px-4 py-3 border-2 border-dashed rounded-lg transition-all {{ $cuitCertificado ? 'border-green-400 bg-green-50 dark:bg-green-900/20 dark:border-green-600' : ($cuitTieneCertificado ? 'border-green-300 dark:border-green-700' : 'border-gray-300 dark:border-gray-600 hover:border-bcn-primary dark:hover:border-bcn-primary') }}">
                                    @if($cuitCertificado)
                                        <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-sm font-medium truncate max-w-[150px]">{{ $cuitCertificado->getClientOriginalName() }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <span class="text-sm">{{ $cuitTieneCertificado ? __('Reemplazar certificado') : __('Subir certificado') }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div wire:loading wire:target="cuitCertificado" class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-800/80 rounded-lg">
                                    <svg class="animate-spin h-5 w-5 text-bcn-primary" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        {{-- Clave Privada --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                                {{ __('Clave Privada (.key, .pem)') }}
                                @if($cuitTieneClave)
                                    <span class="text-green-500 ml-1">- {{ __('Cargada') }}</span>
                                @endif
                            </label>
                            <div class="relative">
                                <input
                                    type="file"
                                    wire:model="cuitClave"
                                    accept=".key,.pem"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                >
                                <div class="flex items-center justify-center px-4 py-3 border-2 border-dashed rounded-lg transition-all {{ $cuitClave ? 'border-green-400 bg-green-50 dark:bg-green-900/20 dark:border-green-600' : ($cuitTieneClave ? 'border-green-300 dark:border-green-700' : 'border-gray-300 dark:border-gray-600 hover:border-bcn-primary dark:hover:border-bcn-primary') }}">
                                    @if($cuitClave)
                                        <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-sm font-medium truncate max-w-[150px]">{{ $cuitClave->getClientOriginalName() }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                            </svg>
                                            <span class="text-sm">{{ $cuitTieneClave ? __('Reemplazar clave') : __('Subir clave privada') }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div wire:loading wire:target="cuitClave" class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-800/80 rounded-lg">
                                    <svg class="animate-spin h-5 w-5 text-bcn-primary" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Los certificados se guardaran encriptados de forma segura al guardar el CUIT.') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Puntos de venta, impuestos y domicilios se gestionan desde los
             botones de cada CUIT en la lista (RF-11, refactor UI). --}}
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
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ $modoEdicionCuit ? __('Guardar Cambios') : __('Crear CUIT') }}
        </button>
    </x-slot:footer>
</x-bcn-modal>
