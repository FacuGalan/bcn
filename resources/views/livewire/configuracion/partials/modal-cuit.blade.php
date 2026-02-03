{{-- Modal CUIT --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {{-- Overlay --}}
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalCuit"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        {{-- Modal Content --}}
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            {{-- Header --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ $modoEdicionCuit ? __('Editar CUIT') : __('Nuevo CUIT') }}
                    </h3>
                    <button wire:click="cerrarModalCuit" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                <form wire:submit.prevent="guardarCuit">
                    {{-- Sección: Datos Básicos --}}
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

                            {{-- Razón Social --}}
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Razón Social') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="cuitRazonSocial"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    :placeholder="__('Razón social según AFIP')"
                                >
                                @error('cuitRazonSocial')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Nombre Fantasía --}}
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Nombre Fantasía') }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="cuitNombreFantasia"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    :placeholder="__('Nombre comercial')"
                                >
                            </div>

                            {{-- Condición IVA --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Condición IVA') }} <span class="text-red-500">*</span>
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
                                    :placeholder="__('Número de IIBB')"
                                >
                            </div>
                        </div>
                    </div>

                    {{-- Sección: Domicilio --}}
                    <div class="mb-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
                            {{ __('Domicilio Fiscal') }}
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {{-- Dirección --}}
                            <div class="sm:col-span-2 lg:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Dirección') }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="cuitDireccion"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    :placeholder="__('Calle y número')"
                                >
                            </div>

                            {{-- Provincia --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Provincia') }}
                                </label>
                                <select
                                    wire:model.live="cuitProvinciaId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">{{ __('Seleccionar...') }}</option>
                                    @foreach($this->provincias as $provincia)
                                        <option value="{{ $provincia->id }}">{{ $provincia->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Localidad --}}
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Localidad') }}
                                </label>
                                <select
                                    wire:model="cuitLocalidadId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    {{ empty($localidades) ? 'disabled' : '' }}
                                >
                                    <option value="">{{ empty($localidades) ? __('Seleccione provincia primero') : __('Seleccionar...') }}</option>
                                    @foreach($localidades as $localidad)
                                        <option value="{{ $localidad->id }}">
                                            {{ $localidad->nombre }}
                                            @if($localidad->codigo_postal)
                                                ({{ $localidad->codigo_postal }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Sección: AFIP --}}
                    <div class="mb-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">
                            {{ __('Configuración AFIP') }}
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
                                    <option value="testing">{{ __('Testing (Homologación)') }}</option>
                                    <option value="produccion">{{ __('Producción') }}</option>
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
                                        wire:confirm="{{ __('¿Está seguro de eliminar los certificados? Deberá cargarlos nuevamente.') }}"
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
                                    {{ __('Los certificados se guardarán encriptados de forma segura al guardar el CUIT.') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Sección: Puntos de Venta (solo en edición) --}}
                    @if($modoEdicionCuit && $cuitId)
                        <div class="pt-6 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                                    {{ __('Puntos de Venta') }}
                                </h4>
                            </div>

                            {{-- Formulario nuevo punto de venta --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600 mb-4">
                                <div class="flex flex-col sm:flex-row gap-4 items-end">
                                    {{-- Número --}}
                                    <div class="w-full sm:w-24">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                            {{ __('Número') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            wire:model="nuevoPuntoVentaNumero"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono"
                                            placeholder="1"
                                            min="1"
                                            max="99999"
                                        >
                                        @error('nuevoPuntoVentaNumero')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Nombre --}}
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                            {{ __('Nombre/Descripción') }}
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="nuevoPuntoVentaNombre"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            :placeholder="__('Ej: Caja Principal')"
                                        >
                                    </div>

                                    {{-- Botón Agregar --}}
                                    <div class="w-full sm:w-auto">
                                        <button
                                            type="button"
                                            wire:click="agregarPuntoVenta"
                                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-bcn-primary rounded-md hover:bg-bcn-primary/90 transition-colors"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            {{ __('Agregar') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Lista de puntos de venta existentes --}}
                            @if(count($puntosVenta) > 0)
                                <div class="space-y-2">
                                    @foreach($puntosVenta as $pv)
                                        <div class="flex items-center justify-between bg-white dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                                            <div class="flex items-center gap-4">
                                                <span class="font-mono text-lg font-semibold text-gray-900 dark:text-white">
                                                    {{ str_pad($pv['numero'], 4, '0', STR_PAD_LEFT) }}
                                                </span>
                                                @if(!empty($pv['nombre']))
                                                    <span class="text-gray-600 dark:text-gray-400">{{ $pv['nombre'] }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="togglePuntoVentaActivo({{ $pv['id'] }})"
                                                    class="p-1.5 rounded transition-colors {{ $pv['activo'] ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                                                    title="{{ $pv['activo'] ? __('Desactivar') : __('Activar') }}"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($pv['activo'])
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        @endif
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="confirmarEliminarPuntoVenta({{ $pv['id'] }})"
                                                    class="p-1.5 text-red-600 hover:bg-red-50 rounded dark:hover:bg-red-900/20 transition-colors"
                                                    title="{{ __('Eliminar') }}"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6 bg-gray-50 dark:bg-gray-700/50 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                                    <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('Sin puntos de venta. Complete el formulario de arriba para agregar uno.') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </form>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-200 dark:border-gray-600 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <button
                    type="button"
                    wire:click="cerrarModalCuit"
                    class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    type="button"
                    wire:click="guardarCuit"
                    class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-primary/90 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $modoEdicionCuit ? __('Guardar Cambios') : __('Crear CUIT') }}
                </button>
            </div>
        </div>
    </div>
</div>
