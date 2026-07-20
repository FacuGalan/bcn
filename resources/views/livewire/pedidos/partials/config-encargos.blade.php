{{-- Encargos (RF-T16): partial del PADRE ConfiguracionDelivery — pedidos
     para día futuro. Calendario PROPIO (independiente del de atención,
     precargado desde él al activar por primera vez): permite tomar encargos
     para días en que el local no atiende al público. Auto-guardado RF-T15. --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" wire:model.live="aceptaProgramados" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
        <span class="text-sm text-gray-700 dark:text-gray-300">
            <span class="font-semibold text-gray-900 dark:text-white">{{ __('Tomar pedidos por encargue') }}</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('La tienda permite pedir para un día y horario futuros. Los encargos entran al panel recién cerca de su hora y tienen su propia solapa y reporte de producción.') }}</span>
        </span>
    </label>

    @if($aceptaProgramados)
        <div class="pl-6 space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="cd-enc-anticipacion" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Anticipación mínima (horas)') }}</label>
                    <input id="cd-enc-anticipacion" type="number" min="0" wire:model.live.debounce.800ms="encargosAnticipacionHoras"
                        class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                </div>
                <div>
                    <label for="cd-enc-maxdias" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta cuántos días adelante') }}</label>
                    <input id="cd-enc-maxdias" type="number" min="1" wire:model.live.debounce.800ms="encargosMaxDias"
                        class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                </div>
                <div>
                    <label for="cd-enc-aparecen" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aparecen en el panel (min antes)') }}</label>
                    <input id="cd-enc-aparecen" type="number" min="0" wire:model.live.debounce.800ms="programadosAparecenMinAntes"
                        class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Días con encargos') }}</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($diasSemana as $dia => $label)
                        <label class="inline-flex items-center gap-1 px-2 py-1 border rounded-md cursor-pointer text-xs {{ ($encargosDias[$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                            <input type="checkbox" wire:model.live="encargosDias.{{ $dia }}" class="sr-only" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between gap-2 mb-1">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Horarios de entrega/retiro de encargos') }} <span class="text-gray-400">({{ __('vacío = todo el día') }})</span></label>
                    <button type="button" wire:click="agregarHorarioEncargos" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar rango') }}</button>
                </div>
                @foreach($encargosHorarios as $i => $rango)
                    <div class="flex flex-wrap items-center gap-2 mb-1.5 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5">
                        <div class="flex flex-wrap gap-1">
                            @foreach($diasSemana as $dia => $label)
                                <label class="inline-flex items-center px-1.5 py-0.5 border rounded cursor-pointer text-[10px] {{ ($encargosHorarios[$i]['dias'][$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400' }}">
                                    <input type="checkbox" wire:model.live="encargosHorarios.{{ $i }}.dias.{{ $dia }}" class="sr-only" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <input type="time" wire:model.live.debounce.500ms="encargosHorarios.{{ $i }}.desde" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                        <span class="text-xs text-gray-400">—</span>
                        <input type="time" wire:model.live.debounce.500ms="encargosHorarios.{{ $i }}.hasta" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                        <button type="button" wire:click="quitarHorarioEncargos({{ $i }})" class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fechas sin encargos') }}</label>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="date" wire:model="nuevoFeriadoEncargos" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                    <button type="button" wire:click="agregarFeriadoEncargos" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar') }}</button>
                    @foreach($encargosFeriados as $i => $feriado)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs rounded-full">
                            {{ $feriado }}
                            <button type="button" wire:click="quitarFeriadoEncargos({{ $i }})" class="text-gray-400 hover:text-red-500">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </span>
                    @endforeach
                </div>
            </div>

            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                {{ __('Este calendario es INDEPENDIENTE del de atención: podés tomar encargos para días en que el local no atiende al público. Los artículos se habilitan uno a uno con "Disponible para encargos".') }}
            </p>
        </div>
    @endif
</div>
