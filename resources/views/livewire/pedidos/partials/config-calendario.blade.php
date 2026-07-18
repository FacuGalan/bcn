{{-- Calendario de atención (RF-05/D16): partial del PADRE ConfiguracionDelivery
     — bindea props del padre; se incluye en la zona delivery o dentro del
     apartado Tienda Online según el estado del switch (RF-T11). La data
     aplica SIEMPRE (franjas, advertencias del panel, API), tenga o no tienda. --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Calendario de atención') }}</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('La tienda/API rechaza pedidos fuera de horario; el panel solo advierte.') }}</p>

    <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Días laborales') }}</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach($diasSemana as $dia => $label)
                <label class="inline-flex items-center gap-1 px-2 py-1 border rounded-md cursor-pointer text-xs {{ ($diasLaborales[$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                    <input type="checkbox" wire:model.live="diasLaborales.{{ $dia }}" class="sr-only" />
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between gap-2 mb-1">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Horarios de atención') }} <span class="text-gray-400">({{ __('vacío = siempre') }})</span></label>
            <button type="button" wire:click="agregarHorario" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar rango') }}</button>
        </div>
        @foreach($horariosAtencion as $i => $rango)
            <div class="flex flex-wrap items-center gap-2 mb-1.5 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5">
                <div class="flex flex-wrap gap-1">
                    @foreach($diasSemana as $dia => $label)
                        <label class="inline-flex items-center px-1.5 py-0.5 border rounded cursor-pointer text-[10px] {{ ($horariosAtencion[$i]['dias'][$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400' }}">
                            <input type="checkbox" wire:model.live="horariosAtencion.{{ $i }}.dias.{{ $dia }}" class="sr-only" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <input type="time" wire:model="horariosAtencion.{{ $i }}.desde" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                <span class="text-xs text-gray-400">—</span>
                <input type="time" wire:model="horariosAtencion.{{ $i }}.hasta" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                <button type="button" wire:click="quitarHorario({{ $i }})" class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endforeach
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Feriados sin atención') }}</label>
        <div class="flex flex-wrap items-center gap-2">
            <input type="date" wire:model="nuevoFeriado" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
            <button type="button" wire:click="agregarFeriado" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar') }}</button>
            @foreach($feriados as $i => $feriado)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs rounded-full">
                    {{ $feriado }}
                    <button type="button" wire:click="quitarFeriado({{ $i }})" class="text-gray-400 hover:text-red-500">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>
            @endforeach
        </div>
    </div>
</div>
