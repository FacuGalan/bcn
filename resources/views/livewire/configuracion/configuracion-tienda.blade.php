<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Identidad y apariencia de la tienda') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dirección pública, métricas y estética. Se guardan con el botón "Guardar tienda".') }}</p>
    </div>

    @if(! $tiendaId)
        {{-- Defensivo: el padre solo monta este componente con tienda creada. --}}
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Esta sucursal todavía no tiene tienda online.') }}</p>
    @else
        {{-- ==================== DIRECCIÓN ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="ct-slug" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Dirección de la tienda (slug)') }}</label>
                <input id="ct-slug" type="text" wire:model="slug" @disabled(! $puedeConfigurar)
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                @error('slug') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @if($urlPublica)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 break-all">
                        {{ __('URL pública:') }} <a href="{{ $urlPublica }}" target="_blank" rel="noopener" class="text-bcn-primary hover:underline">{{ $urlPublica }}</a>
                    </p>
                @endif
                <p class="mt-1 text-xs text-orange-600 dark:text-orange-400">{{ __('Cambiar la dirección rompe los links ya compartidos y los accesos directos instalados.') }}</p>
            </div>
        </div>

        {{-- ==================== ANALYTICS (RF-T7) ==================== --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
            <h3 class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Métricas (Google Analytics y Meta Pixel)') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Con los IDs cargados, la tienda mide visitas, carritos y compras en tus propias cuentas. Vacíos, no se inyecta ningún script.') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="ct-ga4" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('ID de medición GA4') }}</label>
                    <input id="ct-ga4" type="text" wire:model="ga4MeasurementId" placeholder="G-XXXXXXXXXX" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                    @error('ga4MeasurementId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ct-pixel" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('ID del Pixel de Meta') }}</label>
                    <input id="ct-pixel" type="text" wire:model="metaPixelId" placeholder="123456789012345" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                    @error('metaPixelId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ==================== TEMA VISUAL (RF-T6) + IMÁGENES (RF-T11) ==================== --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Apariencia de la tienda') }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Logo, portada, colores, tipografía y estilo con los que se pinta tu tienda.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @include('livewire.configuracion.partials.tienda-preview-drawer')
                    <button type="button" wire:click="restablecerTema" @disabled(! $puedeConfigurar)
                        class="text-xs text-bcn-primary hover:underline disabled:opacity-50">{{ __('Restablecer al tema default') }}</button>
                </div>
            </div>

            {{-- Logo + portada (se persisten al Guardar tienda) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Logo de la tienda') }}</label>
                    <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                        @if($logoPreviewUrl)
                            <img src="{{ $logoPreviewUrl }}" alt="{{ __('Logo de la tienda') }}" class="max-h-24 mb-3 rounded">
                        @else
                            <svg class="w-12 h-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        @endif
                        <div class="mt-2 flex flex-wrap justify-center gap-2">
                            <label class="cursor-pointer inline-flex items-center px-3 py-1.5 bg-bcn-primary text-white text-xs font-medium rounded-md hover:bg-bcn-primary/90 transition-colors {{ $puedeConfigurar ? '' : 'opacity-50 pointer-events-none' }}">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                {{ __('Subir logo') }}
                                <input type="file" wire:model="logoUpload" accept="image/*" class="hidden" @disabled(! $puedeConfigurar)>
                            </label>
                            @if($logoPreviewUrl)
                                <button type="button" wire:click="eliminarLogo" @disabled(! $puedeConfigurar)
                                    class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700 transition-colors disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    {{ __('Eliminar') }}
                                </button>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('JPG, PNG o WebP. Máximo 5MB. Se muestra en el encabezado de la tienda.') }}</p>
                        <div wire:loading wire:target="logoUpload" class="mt-1 text-xs text-bcn-primary">{{ __('Subiendo imagen...') }}</div>
                    </div>
                    @error('logoUpload') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Portada (banner del encabezado)') }}</label>
                    <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                        @if($portadaPreviewUrl)
                            <img src="{{ $portadaPreviewUrl }}" alt="{{ __('Portada de la tienda') }}" class="max-h-24 w-full object-cover mb-3 rounded">
                        @else
                            <svg class="w-12 h-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        @endif
                        <div class="mt-2 flex flex-wrap justify-center gap-2">
                            <label class="cursor-pointer inline-flex items-center px-3 py-1.5 bg-bcn-primary text-white text-xs font-medium rounded-md hover:bg-bcn-primary/90 transition-colors {{ $puedeConfigurar ? '' : 'opacity-50 pointer-events-none' }}">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                {{ __('Subir portada') }}
                                <input type="file" wire:model="portadaUpload" accept="image/*" class="hidden" @disabled(! $puedeConfigurar)>
                            </label>
                            @if($portadaPreviewUrl)
                                <button type="button" wire:click="eliminarPortada" @disabled(! $puedeConfigurar)
                                    class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700 transition-colors disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    {{ __('Eliminar') }}
                                </button>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('JPG, PNG o WebP. Máximo 5MB. Ideal apaisada (1600×900 o más ancha).') }}</p>
                        <div wire:loading wire:target="portadaUpload" class="mt-1 text-xs text-bcn-primary">{{ __('Subiendo imagen...') }}</div>
                    </div>
                    @error('portadaUpload') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach([
                    'colorPrimario' => __('Primario (botones)'),
                    'colorAcento' => __('Acento (ofertas)'),
                    'colorFondo' => __('Fondo'),
                    'colorSuperficie' => __('Tarjetas'),
                    'colorTexto' => __('Texto'),
                ] as $prop => $label)
                    <div>
                        <label for="ct-{{ $prop }}" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
                        <div class="flex items-center gap-1.5">
                            <input id="ct-{{ $prop }}" type="color" wire:model.live="{{ $prop }}" @disabled(! $puedeConfigurar)
                                class="h-8 w-9 p-0.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 cursor-pointer" />
                            <input type="text" wire:model.live="{{ $prop }}" @disabled(! $puedeConfigurar)
                                class="w-full min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                        </div>
                        @error($prop) <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ __('Color inválido (formato #rrggbb)') }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="ct-fuente" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipografía') }}</label>
                    <select id="ct-fuente" wire:model.live="fuente" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="system">{{ __('Del sistema (rápida)') }}</option>
                        <option value="inter">Inter</option>
                        <option value="poppins">Poppins</option>
                        <option value="roboto">Roboto</option>
                        <option value="montserrat">Montserrat</option>
                        <option value="lora">Lora</option>
                    </select>
                </div>
                <div>
                    <label for="ct-radios" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Bordes redondeados') }}</label>
                    <select id="ct-radios" wire:model.live="radios" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="none">{{ __('Rectos') }}</option>
                        <option value="sm">{{ __('Suaves') }}</option>
                        <option value="md">{{ __('Medios') }}</option>
                        <option value="lg">{{ __('Amplios') }}</option>
                        <option value="full">{{ __('Redondos') }}</option>
                    </select>
                </div>
                <div>
                    <label for="ct-densidad" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Densidad del contenido') }}</label>
                    <select id="ct-densidad" wire:model.live="densidad" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="compacta">{{ __('Compacta') }}</option>
                        <option value="normal">{{ __('Normal') }}</option>
                        <option value="amplia">{{ __('Amplia') }}</option>
                    </select>
                </div>
            </div>
        </div>

        @if($puedeConfigurar)
            <div class="flex justify-end">
                <button type="button" wire:click="guardarTienda"
                    class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Guardar tienda') }}
                </button>
            </div>
        @endif
    @endif
</div>
