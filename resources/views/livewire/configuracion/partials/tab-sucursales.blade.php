{{-- Tab Sucursales --}}
<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Configuración de Sucursales') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Configura el logo y datos adicionales de cada sucursal') }}
        </p>
    </div>

    {{-- Lista de Sucursales --}}
    @if($this->sucursales->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($this->sucursales as $sucursal)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 {{ $sucursalEditandoId === $sucursal->id ? 'ring-2 ring-bcn-primary' : '' }}">
                    @if($sucursalEditandoId === $sucursal->id)
                        {{-- Modo Edición --}}
                        <form wire:submit.prevent="guardarSucursal">
                            <div class="space-y-4">
                                {{-- Nombre Interno --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('Nombre Interno') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalNombre"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="{{ __('Ej: Sucursal Norte') }}"
                                    >
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Para uso interno del sistema') }}</p>
                                    @error('sucursalNombre')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Nombre Público --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('Nombre Público') }}
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalNombrePublico"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="{{ __('Ej: Helados Favoritos Rivadavia') }}"
                                    >
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Nombre comercial visible al público') }}</p>
                                    @error('sucursalNombrePublico')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Dirección --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('Dirección') }}
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalDireccion"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                </div>

                                {{-- Domicilio físico estructurado: provincia + localidad + geo (RF-11) --}}
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        {{ __('Ubicación de la sucursal (provincia, localidad y coordenadas). Útil para reportes, logística y tienda online; independiente de la facturación.') }}
                                    </p>
                                    @include('livewire.partials.domicilio-form', ['conTipo' => false, 'conDireccion' => false, 'idPrefix' => 'sucdom'])
                                </div>

                                {{-- Teléfono y Email --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Teléfono') }}
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="sucursalTelefono"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Email') }}
                                        </label>
                                        <input
                                            type="email"
                                            wire:model="sucursalEmail"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >
                                        @error('sucursalEmail')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Logo (dropzone moderno, estilo imágenes de artículos; sin focal point) --}}
                                <div>
                                    <label for="sucursalLogoUpload" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Logo') }}</label>

                                    {{-- Input file oculto. Cualquier <label for="sucursalLogoUpload"> abre el selector. --}}
                                    <input type="file" id="sucursalLogoUpload" wire:model="sucursalLogo" accept="image/jpeg,image/png,image/webp" class="sr-only">

                                    @php
                                        $logoPreview = $sucursalLogo
                                            ? $sucursalLogo->temporaryUrl()
                                            : ($sucursal->hasLogo() ? '/storage/'.ltrim($sucursal->logo_path, '/') : null);
                                    @endphp

                                    <div class="relative group w-full max-w-[160px]" wire:loading.class="opacity-60" wire:target="sucursalLogo">
                                        @if($logoPreview)
                                            {{-- Con logo: preview contenido (sin recorte) + acciones en hover --}}
                                            <div class="relative aspect-square w-full bg-gray-100 dark:bg-gray-800 rounded-md overflow-hidden border-2 border-gray-200 dark:border-gray-700 group-hover:border-bcn-primary transition-colors">
                                                <img src="{{ $logoPreview }}" alt="{{ __('Logo') }}" class="w-full h-full object-contain p-2 select-none" draggable="false">
                                            </div>
                                            <div class="absolute top-1.5 right-1.5 flex gap-1">
                                                <label for="sucursalLogoUpload" title="{{ __('Cambiar logo') }}"
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/95 text-gray-700 hover:bg-white hover:text-bcn-primary shadow-md transition-colors cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                </label>
                                                @if($sucursalLogo)
                                                    <button type="button" wire:click="$set('sucursalLogo', null)" title="{{ __('Descartar selección') }}"
                                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/95 text-gray-700 hover:bg-white hover:text-red-600 shadow-md transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                @elseif($sucursal->hasLogo())
                                                    <button type="button" wire:click="eliminarLogoSucursal({{ $sucursal->id }})" title="{{ __('Quitar logo') }}"
                                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/95 text-gray-700 hover:bg-red-600 hover:text-white shadow-md transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            {{-- Sin logo: dropzone clickable --}}
                                            <label for="sucursalLogoUpload"
                                                class="block cursor-pointer rounded-md overflow-hidden border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-bcn-primary hover:bg-bcn-primary/5 dark:hover:bg-bcn-primary/10 bg-gray-50 dark:bg-gray-700/50 transition-colors">
                                                <div class="aspect-square w-full flex flex-col items-center justify-center gap-1 px-3 text-gray-500 dark:text-gray-400 group-hover:text-bcn-primary">
                                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                    <span class="text-xs font-medium">{{ __('Subir logo') }}</span>
                                                    <span class="text-[10px] text-center opacity-80">{{ __('PNG, JPG o WebP · máx. 2MB') }}</span>
                                                </div>
                                            </label>
                                        @endif
                                    </div>

                                    {{-- Recomendación de proporción --}}
                                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400 max-w-[260px] leading-snug">
                                        {{ __('Recomendado: imagen cuadrada (1:1), preferentemente PNG con fondo transparente, mínimo 400×400 px.') }}
                                    </p>

                                    {{-- Carga + errores --}}
                                    <div class="mt-1 text-[11px] space-y-1 max-w-[200px]">
                                        <div wire:loading wire:target="sucursalLogo" class="text-bcn-primary inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            {{ __('Cargando...') }}
                                        </div>
                                        @error('sucursalLogo') <span class="text-red-600 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- Botones --}}
                                <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <button
                                        type="button"
                                        wire:click="cancelarEdicionSucursal"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        {{ __('Cancelar') }}
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ __('Guardar') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        {{-- Modo Vista --}}
                        <div class="flex items-start gap-4">
                            {{-- Logo --}}
                            <div class="flex-shrink-0">
                                <div class="w-20 h-20 rounded-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                                    @if($sucursal->hasLogo())
                                        <img src="/storage/{{ ltrim($sucursal->logo_path, '/') }}" alt="{{ __('Logo') }} {{ $sucursal->nombre }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $sucursal->nombre }}
                                    </h4>
                                    @if($sucursal->es_principal)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary/10 text-bcn-primary dark:bg-bcn-primary/20">
                                            {{ __('Principal') }}
                                        </span>
                                    @endif
                                    @if($sucursal->activa)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            {{ __('Activa') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                            {{ __('Inactiva') }}
                                        </span>
                                    @endif
                                </div>

                                @if($sucursal->nombre_publico)
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <span class="font-medium">{{ __('Público:') }}</span> {{ $sucursal->nombre_publico }}
                                    </p>
                                @endif

                                <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    @if($sucursal->direccion)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span class="truncate">{{ $sucursal->direccion }}</span>
                                        </div>
                                    @endif
                                    @if($sucursal->telefono)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                            <span>{{ $sucursal->telefono }}</span>
                                        </div>
                                    @endif
                                    @if($sucursal->email)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="truncate">{{ $sucursal->email }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Botones --}}
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        wire:click="editarSucursal({{ $sucursal->id }})"
                                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        {{ __('Editar') }}
                                    </button>
                                    <button
                                        wire:click="abrirConfigSucursal({{ $sucursal->id }})"
                                        class="inline-flex items-center px-3 py-1.5 bg-bcn-primary/10 border border-bcn-primary/30 text-bcn-primary text-sm font-medium rounded-md hover:bg-bcn-primary/20 dark:bg-bcn-primary/20 dark:border-bcn-primary/40 dark:hover:bg-bcn-primary/30 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ __('Configurar') }}
                                    </button>
                                    @if($sucursal->usaPantallaCliente())
                                        <button
                                            wire:click="abrirPersonalizarPantalla({{ $sucursal->id }})"
                                            class="inline-flex items-center px-3 py-1.5 bg-violet-600 border border-transparent text-white text-sm font-medium rounded-md hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-colors"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                                            </svg>
                                            {{ __('Personalizar 2da pantalla') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- Estado vacío --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('No hay sucursales') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('No se encontraron sucursales configuradas.') }}
            </p>
        </div>
    @endif
</div>
