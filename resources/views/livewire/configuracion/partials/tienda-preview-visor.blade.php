{{-- Visor lateral (RF-T12): la TIENDA REAL embebida cuando está publicada
     (persistida — la API 404ea despublicadas); mock como fallback. Vive en
     el scope Alpine `tiendaPreview` del ancestro. Solo xl+ (en <xl está el
     drawer). --}}
<div class="hidden xl:block xl:sticky xl:top-4 space-y-2">
    @if($publicadaPersistida && $urlPublica)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800 shadow-sm">
            <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Tu tienda en vivo') }}</p>
                <a href="{{ $urlPublica }}" target="_blank" rel="noopener" class="text-xs text-bcn-primary hover:underline">
                    {{ __('Abrir en pestaña nueva') }}
                </a>
            </div>
            <iframe x-ref="iframe" src="{{ $urlPublica }}?preview=1" loading="lazy"
                title="{{ __('Vista previa de la tienda') }}"
                class="w-full bg-white" style="height: min(720px, calc(100vh - 10rem));"></iframe>
        </div>
        <p class="text-[11px] text-gray-500 dark:text-gray-400">
            {{ __('Es tu tienda real: los cambios de estética se previsualizan al instante y se aplican de verdad recién al guardar.') }}
        </p>
    @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800 shadow-sm">
            <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Vista previa (simulación)') }}</p>
            </div>
            @include('livewire.configuracion.partials.tienda-preview-mock')
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-400">
            {{ __('Publicá la tienda (switch + Guardar) para ver acá tu tienda REAL en vivo en lugar de la simulación.') }}
        </p>
    @endif
</div>
