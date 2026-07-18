{{-- Visor lateral (RF-T12): la TIENDA REAL embebida cuando está publicada
     (persistida — la API 404ea despublicadas); mock como fallback. Vive en
     el scope Alpine `tiendaPreview` del ancestro. Solo xl+ (en <xl está el
     drawer). El marco de celular es SOLO decorativo: refuerza que se está
     viendo la experiencia móvil de la tienda. --}}
<div class="hidden xl:block xl:sticky xl:top-4 space-y-2">
    @if($publicadaPersistida && $urlPublica)
        <div class="flex items-center justify-between gap-2 px-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Tu tienda en vivo') }}</p>
            <a href="{{ $urlPublica }}" target="_blank" rel="noopener" class="text-xs text-bcn-primary hover:underline">
                {{ __('Abrir en pestaña nueva') }}
            </a>
        </div>
        <div class="mx-auto w-full max-w-[22.5rem]">
            <div class="relative rounded-[2.5rem] bg-gray-900 dark:bg-gray-950 p-2.5 shadow-2xl ring-1 ring-gray-700 dark:ring-gray-800">
                {{-- Botones laterales decorativos del "celular" --}}
                <div class="absolute -left-[3px] top-24 h-8 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -left-[3px] top-36 h-12 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -right-[3px] top-28 h-16 w-[3px] rounded-r-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="relative rounded-[2rem] overflow-hidden bg-white dark:bg-white">
                    {{-- Notch (isla) decorativo --}}
                    <div class="absolute top-2 left-1/2 -translate-x-1/2 z-10 h-4 w-20 rounded-full bg-gray-900 dark:bg-gray-950 pointer-events-none"></div>
                    <iframe x-ref="iframe" src="{{ $urlPublica }}?preview=1" loading="lazy"
                        title="{{ __('Vista previa de la tienda') }}"
                        class="w-full bg-white block" style="height: min(700px, calc(100vh - 12rem));"></iframe>
                </div>
            </div>
        </div>
        <p class="text-[11px] text-gray-500 dark:text-gray-400 text-center">
            {{ __('Es tu tienda real: los cambios de estética se previsualizan al instante y se aplican de verdad recién al guardar.') }}
        </p>
    @else
        <div class="px-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Vista previa (simulación)') }}</p>
        </div>
        <div class="mx-auto w-full max-w-[22.5rem]">
            <div class="relative rounded-[2.5rem] bg-gray-900 dark:bg-gray-950 p-2.5 shadow-2xl ring-1 ring-gray-700 dark:ring-gray-800">
                <div class="absolute -left-[3px] top-24 h-8 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -left-[3px] top-36 h-12 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -right-[3px] top-28 h-16 w-[3px] rounded-r-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="relative rounded-[2rem] overflow-hidden bg-white dark:bg-white">
                    <div class="absolute top-2 left-1/2 -translate-x-1/2 z-10 h-4 w-20 rounded-full bg-gray-900 dark:bg-gray-950 pointer-events-none"></div>
                    <div class="overflow-y-auto" style="height: min(700px, calc(100vh - 12rem));">
                        @include('livewire.configuracion.partials.tienda-preview-mock')
                    </div>
                </div>
            </div>
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-400 text-center">
            {{ __('Publicá la tienda (switch + Guardar) para ver acá tu tienda REAL en vivo en lugar de la simulación.') }}
        </p>
    @endif
</div>
