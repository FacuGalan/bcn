{{--
    Partial reutilizable de domicilio (RF-11, Fase 9).
    Requiere el trait App\Traits\ManejaDomicilio en el componente host.

    Variables opcionales (vía @include):
      - $conTipo (bool)     : muestra el selector fiscal/comercial/otro (default true)
      - $conDireccion (bool): muestra el campo dirección (default true)
      - $idPrefix (string)  : prefijo para los ids de los <label for> (default 'dom')
--}}
@php($conTipo = $conTipo ?? true)
@php($conDireccion = $conDireccion ?? true)
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
        <label for="{{ $idPrefix }}-provincia" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Provincia') }} <span class="text-red-500">*</span></label>
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
        <select id="{{ $idPrefix }}-localidad" wire:model="domLocalidadId" @disabled(empty($domLocalidades))
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
    <div>
        <label for="{{ $idPrefix }}-lat" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Latitud') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
        <input id="{{ $idPrefix }}-lat" type="number" step="0.0000001" wire:model="domLatitud" placeholder="-34.6037"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        @error('domLatitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="{{ $idPrefix }}-lng" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Longitud') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
        <input id="{{ $idPrefix }}-lng" type="number" step="0.0000001" wire:model="domLongitud" placeholder="-58.3816"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        @error('domLongitud') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>
