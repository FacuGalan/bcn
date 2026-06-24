{{--
    Partial reutilizable de domicilio (RF-11, Fase 9).
    Requiere el trait App\Traits\ManejaDomicilio en el componente host.

    Variables opcionales (vía @include):
      - $conTipo (bool)            : muestra el selector fiscal/comercial/otro (default true)
      - $conDireccion (bool)       : muestra el campo dirección (default true)
      - $conGeo (bool)             : muestra los campos latitud/longitud (default true)
      - $provinciaRequerida (bool) : marca la provincia como obligatoria (default true)
      - $idPrefix (string)         : prefijo para los ids de los <label for> (default 'dom')
--}}
@php($conTipo = $conTipo ?? true)
@php($conDireccion = $conDireccion ?? true)
@php($conGeo = $conGeo ?? true)
@php($provinciaRequerida = $provinciaRequerida ?? true)
@php($idPrefix = $idPrefix ?? 'dom')

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    @if($conTipo)
        <div>
            <label for="{{ $idPrefix }}-tipo" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Tipo de domicilio') }}</label>
            <select id="{{ $idPrefix }}-tipo" wire:model="domTipo"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="fiscal">{{ __('Fiscal') }}</option>
                <option value="comercial">{{ __('Comercial') }}</option>
                <option value="otro">{{ __('Otro') }}</option>
            </select>
        </div>
    @endif

    {{-- Provincia (ISO) --}}
    <div>
        <label for="{{ $idPrefix }}-provincia" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Provincia') }} @if($provinciaRequerida)<span class="text-red-500">*</span>@endif</label>
        <select id="{{ $idPrefix }}-provincia" wire:model.live="domProvincia"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            <option value="">{{ __('Seleccionar...') }}</option>
            @foreach($this->provinciasDomicilio as $codigo => $nombre)
                <option value="{{ $codigo }}">{{ $nombre }}</option>
            @endforeach
        </select>
        @error('domProvincia') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    {{-- Localidad (dependiente de la provincia) --}}
    <div>
        <label for="{{ $idPrefix }}-localidad" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Localidad') }}</label>
        <select id="{{ $idPrefix }}-localidad" wire:model.live="domLocalidadId" @disabled(empty($domLocalidades))
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white disabled:opacity-60">
            <option value="">{{ empty($domLocalidades) ? __('Seleccione provincia primero') : __('Seleccionar...') }}</option>
            @foreach($domLocalidades as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
    </div>

    {{-- Dirección --}}
    @if($conDireccion)
        <div class="sm:col-span-2">
            <label for="{{ $idPrefix }}-direccion" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Dirección') }}</label>
            <input id="{{ $idPrefix }}-direccion" type="text" wire:model="domDireccion" maxlength="255" placeholder="{{ __('Calle y número') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            @error('domDireccion') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endif

    {{-- Geo opcional --}}
    @if($conGeo)
        @php($mapsLat = $idPrefix.'-lat')
        @php($mapsLng = $idPrefix.'-lng')

        @if($this->mapsHabilitado())
            {{-- Picker de Google Maps (flujo invertido: provincia→localidad acotan el mapa) --}}
            <div class="sm:col-span-2" x-cloak
                x-data="domicilioMapa(@js([
                    'key' => config('services.google_maps.key'),
                    'mapId' => config('services.google_maps.map_id'),
                    'txtGeoError' => __('No pudimos obtener tu ubicación'),
                ]))">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ubicar en el mapa') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>

                <p x-show="!tieneCentro" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Elegí provincia y localidad para ubicar el domicilio') }}</p>

                <div wire:ignore class="mt-1 space-y-2">
                    <div x-ref="autocompleteSlot"></div>
                    <div x-ref="mapa" class="w-full h-64 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700"></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="usarMiUbicacion()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            {{ __('Usar mi ubicación actual') }}
                        </button>
                        <span x-show="cargando" class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cargando mapa…') }}</span>
                        <span x-show="error" x-cloak class="text-xs text-red-600 dark:text-red-400">{{ __('No se pudo cargar el mapa') }}</span>
                        <span x-show="geoError" x-cloak x-text="geoError" class="text-xs text-red-600 dark:text-red-400"></span>
                        <span x-show="!cargando && !error" class="text-xs text-gray-400 dark:text-gray-500 hidden sm:inline">{{ __('Arrastrá el marcador para ajustar el punto') }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500" x-show="coords" x-cloak>
                        {{ __('Coordenadas') }}:
                        <span class="tabular-nums" x-text="coords ? (Number(coords.lat).toFixed(6) + ', ' + Number(coords.lng).toFixed(6)) : ''"></span>
                    </p>
                </div>

                <button type="button" @click="manual = !manual" class="mt-2 text-xs text-bcn-primary hover:underline">{{ __('Ingresar coordenadas manualmente') }}</button>
                <div x-show="manual" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                    <div>
                        <label for="{{ $mapsLat }}" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Latitud') }}</label>
                        <input id="{{ $mapsLat }}" type="number" step="0.0000001" wire:model="domLatitud" placeholder="-34.6037"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        @error('domLatitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="{{ $mapsLng }}" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Longitud') }}</label>
                        <input id="{{ $mapsLng }}" type="number" step="0.0000001" wire:model="domLongitud" placeholder="-58.3816"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        @error('domLongitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @else
            {{-- Sin API key: inputs manuales (comportamiento original) --}}
            <div>
                <label for="{{ $mapsLat }}" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Latitud') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                <input id="{{ $mapsLat }}" type="number" step="0.0000001" wire:model="domLatitud" placeholder="-34.6037"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @error('domLatitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="{{ $mapsLng }}" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Longitud') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                <input id="{{ $mapsLng }}" type="number" step="0.0000001" wire:model="domLongitud" placeholder="-58.3816"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @error('domLongitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @endif
    @endif
</div>
