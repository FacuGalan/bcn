<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Identidad y apariencia de la tienda') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('La dirección y las métricas se guardan automáticamente. La apariencia se aplica con "Guardar apariencia", para que el público nunca vea un cambio a medias.') }}</p>
    </div>

    @if(! $tiendaId)
        {{-- Defensivo: el padre solo monta este componente con tienda creada. --}}
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Esta sucursal todavía no tiene tienda online.') }}</p>
    @else
        {{-- Scope Alpine del preview (RF-T12): config inicial por DATASET
             (cambiar un data-* NO re-inicializa Alpine; el gotcha del morph
             es solo con el atributo x-data). Visor a la derecha en xl+. --}}
        <div x-data="tiendaPreview"
            data-origen-tienda="{{ $origenTienda }}"
            data-logo-url="{{ $logoPreviewUrl }}"
            data-portada-url="{{ $portadaPreviewUrl }}"
            class="xl:grid xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] xl:gap-4 xl:items-start">
        <div class="space-y-3">
        {{-- ==================== DIRECCIÓN ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="ct-slug" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Dirección de la tienda (slug)') }}</label>
                <input id="ct-slug" type="text" wire:model.live.debounce.1000ms="slug" @disabled(! $puedeConfigurar)
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
                    <input id="ct-ga4" type="text" wire:model.live.debounce.1000ms="ga4MeasurementId" placeholder="G-XXXXXXXXXX" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                    @error('ga4MeasurementId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ct-pixel" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('ID del Pixel de Meta') }}</label>
                    <input id="ct-pixel" type="text" wire:model.live.debounce.1000ms="metaPixelId" placeholder="123456789012345" @disabled(! $puedeConfigurar)
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
                            {{-- Miniatura FIJA con la foto completa; el encuadre elegido se ve en la tienda/visor (RF-T13) --}}
                            <img src="{{ $portadaPreviewUrl }}" alt="{{ __('Portada de la tienda') }}"
                                class="max-h-24 w-full object-contain mb-3 rounded">
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

                    {{-- Encuadre de la portada (RF-T13; el fade/overlay se quitó en RF-T25: portada siempre cruda) --}}
                    @if($portadaPreviewUrl)
                        <div class="mt-2 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Encuadre') }}</span>
                                <div class="inline-flex rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden">
                                    @foreach(['top' => __('Arriba'), 'center' => __('Centro'), 'bottom' => __('Abajo')] as $pos => $labelPos)
                                        <button type="button" wire:click="$set('portadaPosicion', '{{ $pos }}')" @disabled(! $puedeConfigurar)
                                            class="px-3 py-1 text-xs font-medium transition-colors {{ $portadaPosicion === $pos ? 'bg-bcn-primary text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                            {{ $labelPos }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            {{-- ==================== HISTORIAS DESTACADAS (RF-T24 / RF-T34) ==================== --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-1">
                <h4 class="text-xs font-semibold text-gray-900 dark:text-white mb-1">{{ __('Historias destacadas') }}</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Subí hasta 3 fotos: el logo de tu tienda se rodea con un anillo estilo historias y al tocarlo se reproducen. Se suben al instante; arrastralas para cambiar el orden.') }}</p>
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex flex-wrap gap-3" data-sortable-historias x-init="initHistoriasSortable($el)">
                        @foreach($historiasActuales as $idx => $historia)
                            <div class="w-20" wire:key="historia-{{ $historia['id'] }}" data-historia-id="{{ $historia['id'] }}">
                                <div class="relative {{ $puedeConfigurar ? 'cursor-grab touch-none' : '' }}" title="{{ $puedeConfigurar ? __('Arrastrar para reordenar') : '' }}">
                                    <img src="{{ $historia['url'] }}" alt="{{ __('Historia') }} {{ $idx + 1 }}" class="w-20 h-32 object-cover rounded-md border border-gray-300 dark:border-gray-600 select-none" draggable="false">
                                    <span class="absolute top-1 left-1 px-1.5 py-0.5 bg-black/60 text-white text-[10px] font-bold rounded">{{ $idx + 1 }}</span>
                                </div>
                                <div class="mt-1 flex justify-center">
                                    <button type="button" wire:click="eliminarHistoria('{{ $historia['id'] }}')" @disabled(! $puedeConfigurar)
                                        class="p-1 rounded border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 disabled:opacity-40" title="{{ __('Eliminar') }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if(count($historiasActuales) < \App\Models\Tienda::MAX_HISTORIAS)
                        <label class="w-20 h-32 flex flex-col items-center justify-center gap-1 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-md cursor-pointer text-gray-400 dark:text-gray-500 hover:border-bcn-primary hover:text-bcn-primary transition-colors {{ $puedeConfigurar ? '' : 'opacity-50 pointer-events-none' }}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-[10px] font-medium">{{ __('Agregar') }}</span>
                            <input type="file" wire:model="historiaUploads" accept="image/*" multiple class="hidden" @disabled(! $puedeConfigurar)>
                        </label>
                    @endif
                </div>
                <div wire:loading wire:target="historiaUploads" class="mt-1 text-xs text-bcn-primary">{{ __('Subiendo imagen...') }}</div>
                @error('historiaUploads.*') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('JPG, PNG o WebP. Máximo 5MB. Ideal vertical (1080×1920).') }}</p>
                <label class="mt-2 flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="promosMostrarHome" @disabled(! $puedeConfigurar)
                        class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-xs text-gray-700 dark:text-gray-300">
                        {{ __('Mostrar también las promociones disponibles como historia') }}
                        <span class="block text-gray-500 dark:text-gray-400">{{ __('Agrega una primera historia con las promociones vigentes del día y sus condiciones. También activa el anillo aunque no haya fotos.') }}</span>
                    </span>
                </label>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
                    <label for="ct-logo-radio" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma del logo') }}</label>
                    <select id="ct-logo-radio" wire:model.live="logoRadio" @disabled(! $puedeConfigurar)
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="none">{{ __('Recto') }}</option>
                        <option value="sm">{{ __('Suave') }}</option>
                        <option value="md">{{ __('Medio') }}</option>
                        <option value="lg">{{ __('Amplio') }}</option>
                        <option value="full">{{ __('Redondo') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Los bordes redondeados generales aplican a tarjetas y carrito; el logo se controla acá.') }}</p>
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

            {{-- ==================== CONTENIDO Y REDES (RF-T13) ==================== --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-1">
                <h4 class="text-xs font-semibold text-gray-900 dark:text-white mb-2">{{ __('Contenido y redes') }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="ct-slogan" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Slogan (se muestra en el encabezado)') }}</label>
                        <input id="ct-slogan" type="text" maxlength="120" wire:model.live.debounce.500ms="slogan" @disabled(! $puedeConfigurar)
                            placeholder="{{ __('Ej: Las mejores pizzas de la zona') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                        @error('slogan') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:row-span-2">
                        <label for="ct-descripcion" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Texto descriptivo (se muestra en «Información del comercio»)') }}</label>
                        <textarea id="ct-descripcion" rows="8" maxlength="5000" wire:model.live.debounce.500ms="descripcion" @disabled(! $puedeConfigurar)
                            placeholder="{{ __('Contale a tus clientes sobre tu comercio, tu historia, tus horarios especiales...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                        @error('descripcion') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Vacío: la sección no aparece en la tienda.') }}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="ct-facebook" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook</label>
                            <input id="ct-facebook" type="url" wire:model.live.debounce.500ms="redFacebook" @disabled(! $puedeConfigurar)
                                placeholder="https://facebook.com/micomercio"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('redFacebook') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ct-instagram" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Instagram</label>
                            <input id="ct-instagram" type="url" wire:model.live.debounce.500ms="redInstagram" @disabled(! $puedeConfigurar)
                                placeholder="https://instagram.com/micomercio"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                            @error('redInstagram') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Con la URL cargada aparece el botón de la red en el encabezado de la tienda.') }}</p>
            </div>

            {{-- ==================== PRESENTACIÓN DEL CATÁLOGO (RF-T13) ==================== --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-1">
                <h4 class="text-xs font-semibold text-gray-900 dark:text-white mb-2">
                    {{ __('Presentación del catálogo') }}
                    <span class="ml-1 font-normal text-gray-500 dark:text-gray-400">{{ __('Los cambios de esta sección se ven al guardar.') }}</span>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="ct-layout" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cómo se muestran los artículos') }}</label>
                        <select id="ct-layout" wire:model="catalogoLayout" @disabled(! $puedeConfigurar)
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="grilla">{{ __('Grilla (foto protagonista)') }}</option>
                            <option value="lista">{{ __('Renglones (foto + detalle al lado)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="ct-destacados-modo" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Artículos destacados') }}</label>
                        <select id="ct-destacados-modo" wire:model.live="destacadosModo" @disabled(! $puedeConfigurar)
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="banner">{{ __('Banner deslizable arriba de todo') }}</option>
                            <option value="tarjeta_grande">{{ __('Tarjeta grande entre los artículos') }}</option>
                            <option value="ninguno">{{ __('Sin sección de destacados') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="ct-destacados-adorno" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Adorno del destacado') }}</label>
                        <select id="ct-destacados-adorno" wire:model="destacadosAdorno" @disabled(! $puedeConfigurar || $destacadosModo === 'ninguno')
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 disabled:opacity-50">
                            <option value="ninguno">{{ __('Ninguno') }}</option>
                            <option value="glow">{{ __('Brillo alrededor (glow)') }}</option>
                            <option value="badge">{{ __('Badge con icono de destacado') }}</option>
                            <option value="ambos">{{ __('Brillo + badge') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('En tarjeta grande decora la card; en banner, el título de la sección.') }}</p>
                    </div>
                    <div>
                        <label for="ct-destacados-titulo" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Título de la sección de destacados') }}</label>
                        <input type="text" id="ct-destacados-titulo" wire:model="destacadosTitulo" maxlength="40" @disabled(! $puedeConfigurar)
                            placeholder="{{ __('Destacados') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Vacío: se usa "Destacados".') }}</p>
                        @error('destacadosTitulo') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="categoriasPlegables" @disabled(! $puedeConfigurar)
                                class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                            <span class="text-xs text-gray-700 dark:text-gray-300">
                                {{ __('Categorías desplegables') }}
                                <span class="block text-gray-500 dark:text-gray-400">{{ __('Cada categoría del catálogo se puede plegar y desplegar tocando su título.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        @if($puedeConfigurar)
            <div class="flex justify-end">
                <button type="button" wire:click="guardarTienda"
                    class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Guardar apariencia') }}
                </button>
            </div>
        @endif

        {{-- ==================== ARTÍCULOS DE LA TIENDA (RF-T14) ====================
             Sub-componente con guardado INMEDIATO (no participa del botón
             "Guardar tienda"); queda en esta columna para que el visor
             sticky siga visible mientras se configura. --}}
        <livewire:configuracion.configuracion-tienda-articulos :wire:key="'cta-articulos-'.$tiendaId" />
        </div> {{-- /columna config --}}

        @include('livewire.configuracion.partials.tienda-preview-visor')
        </div> {{-- /grid preview --}}
    @endif
</div>
