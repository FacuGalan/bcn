{{-- Tab Empresa --}}
<div class="p-6">
    <form wire:submit.prevent="guardarEmpresa">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Columna Izquierda: Datos --}}
            <div class="space-y-6">
                {{-- Nombre --}}
                <div>
                    <label for="empresa_nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Nombre de la Empresa') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="empresa_nombre"
                        wire:model="empresaNombre"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        :placeholder="__('Ej: Mi Empresa S.A.')"
                    >
                    @error('empresaNombre')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Dirección --}}
                <div>
                    <label for="empresa_direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Dirección') }}
                    </label>
                    <input
                        type="text"
                        id="empresa_direccion"
                        wire:model="empresaDireccion"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        :placeholder="__('Ej: Av. Corrientes 1234')"
                    >
                    @error('empresaDireccion')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Teléfono y Email --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="empresa_telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Teléfono') }}
                        </label>
                        <input
                            type="text"
                            id="empresa_telefono"
                            wire:model="empresaTelefono"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            :placeholder="__('Ej: 011-4444-5555')"
                        >
                        @error('empresaTelefono')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="empresa_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Email') }}
                        </label>
                        <input
                            type="email"
                            id="empresa_email"
                            wire:model="empresaEmail"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            :placeholder="__('Ej: contacto@miempresa.com')"
                        >
                        @error('empresaEmail')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Columna Derecha: Logo --}}
            <div class="space-y-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Logo de la Empresa') }}
                </label>

                <div class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    @if($empresaLogo)
                        <img src="{{ $empresaLogo->temporaryUrl() }}" alt="{{ __('Preview Logo') }}" class="max-h-40 mb-4 rounded">
                    @elseif($empresaLogoActual)
                        <img src="{{ asset('storage/' . $empresaLogoActual) }}" alt="{{ __('Logo Empresa') }}" class="max-h-40 mb-4 rounded">
                    @else
                        <svg class="w-20 h-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    @endif

                    <div class="mt-4 flex flex-col sm:flex-row gap-2">
                        <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            {{ __('Subir Logo') }}
                            <input type="file" wire:model="empresaLogo" accept="image/*" class="hidden">
                        </label>

                        @if($empresaLogoActual || $empresaLogo)
                            <button
                                type="button"
                                wire:click="eliminarLogoEmpresa"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                {{ __('Eliminar') }}
                            </button>
                        @endif
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('PNG, JPG o GIF. Máximo 2MB.') }}
                    </p>
                </div>

                @error('empresaLogo')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="empresaLogo" class="text-sm text-bcn-primary">
                    <svg class="animate-spin inline-block w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Subiendo imagen...') }}
                </div>
            </div>
        </div>

        {{-- Botón Guardar --}}
        <div class="mt-6 flex justify-end border-t border-gray-200 dark:border-gray-700 pt-6">
            <button
                type="submit"
                class="inline-flex items-center px-6 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ __('Guardar Cambios') }}
            </button>
        </div>
    </form>
</div>
