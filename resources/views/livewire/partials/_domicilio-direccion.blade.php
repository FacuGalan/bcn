{{-- Sub-partial de domicilio-form: campos dirección + referencia. Se incluye
     en la posición clásica (antes del mapa) o al final ($direccionAlFinal),
     para que la dirección autocompletada desde el mapa quede editable última. --}}
@if($conDireccion)
    <div class="sm:col-span-2">
        <label for="{{ $idPrefix }}-direccion" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ $direccionLabel }}</label>
        <input id="{{ $idPrefix }}-direccion" type="text" wire:model="domDireccion" maxlength="255" placeholder="{{ __('Calle y número') }}"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        @if($autocompletarDireccion)
            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('Se completa sola desde el mapa; podés ajustarla al final.') }}</p>
        @endif
        @error('domDireccion') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
@endif

{{-- Referencia de entrega (piso/depto/timbre) --}}
@if($conReferencia)
    <div class="sm:col-span-2">
        <label for="{{ $idPrefix }}-referencia" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Referencia') }} <span class="text-gray-400">({{ __('piso, depto, timbre...') }})</span></label>
        <input id="{{ $idPrefix }}-referencia" type="text" wire:model="domReferencia" maxlength="255"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
@endif
