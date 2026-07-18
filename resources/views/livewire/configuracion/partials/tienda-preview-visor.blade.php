{{-- Visor lateral (RF-T12): la TIENDA REAL embebida cuando está publicada
     (persistida — la API 404ea despublicadas); mock como fallback. Vive en
     el scope Alpine `tiendaPreview` del ancestro. Solo xl+ (en <xl está el
     drawer). El marco de celular es SOLO decorativo, pero el iframe renderiza
     en un viewport VIRTUAL de celular real (390×844) escalado con transform:
     así la proporción de letras/tarjetas es la misma que en un teléfono, en
     vez del render "achatado" de un iframe chico. La escala baja por media
     query para que el celular entero entre en pantallas bajas. --}}
<div class="hidden xl:block xl:sticky xl:top-4 space-y-2 tp-visor">
    <style>
        .tp-visor { --tp-escala: 0.87; }
        @media (max-height: 860px) { .tp-visor { --tp-escala: 0.78; } }
        @media (max-height: 740px) { .tp-visor { --tp-escala: 0.65; } }
        @media (max-height: 640px) { .tp-visor { --tp-escala: 0.55; } }
        .tp-visor .tp-pantalla { width: calc(390px * var(--tp-escala)); height: calc(844px * var(--tp-escala)); }
        .tp-visor .tp-lienzo { width: 390px; height: 844px; transform: scale(var(--tp-escala)); transform-origin: top left; }
        .tp-visor .tp-scroll { scrollbar-width: none; }
        .tp-visor .tp-scroll::-webkit-scrollbar { display: none; }
    </style>
    @if($publicadaPersistida && $urlPublica)
        <div class="flex items-center justify-between gap-2 px-1">
            <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ __('Tu tienda en vivo') }}</p>
            <a href="{{ $urlPublica }}" target="_blank" rel="noopener" class="text-xs text-bcn-primary hover:underline">
                {{ __('Abrir en pestaña nueva') }}
            </a>
        </div>
        <div class="mx-auto w-fit">
            <div class="relative rounded-[2.5rem] bg-gray-900 dark:bg-gray-950 p-2.5 shadow-2xl ring-1 ring-gray-700 dark:ring-gray-800">
                {{-- Botones laterales decorativos del "celular" --}}
                <div class="absolute -left-[3px] top-24 h-8 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -left-[3px] top-36 h-12 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -right-[3px] top-28 h-16 w-[3px] rounded-r-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="relative rounded-[2rem] overflow-hidden bg-white dark:bg-white tp-pantalla">
                    {{-- Notch (isla) decorativo --}}
                    <div class="absolute top-2 left-1/2 -translate-x-1/2 z-10 h-4 w-20 rounded-full bg-gray-900 dark:bg-gray-950 pointer-events-none"></div>
                    <iframe x-ref="iframe" src="{{ $urlPublica }}?preview=1" loading="lazy"
                        title="{{ __('Vista previa de la tienda') }}"
                        class="tp-lienzo block bg-white"></iframe>
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
        <div class="mx-auto w-fit">
            <div class="relative rounded-[2.5rem] bg-gray-900 dark:bg-gray-950 p-2.5 shadow-2xl ring-1 ring-gray-700 dark:ring-gray-800">
                <div class="absolute -left-[3px] top-24 h-8 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -left-[3px] top-36 h-12 w-[3px] rounded-l-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="absolute -right-[3px] top-28 h-16 w-[3px] rounded-r-full bg-gray-700 dark:bg-gray-700"></div>
                <div class="relative rounded-[2rem] overflow-hidden bg-white dark:bg-white tp-pantalla">
                    <div class="absolute top-2 left-1/2 -translate-x-1/2 z-10 h-4 w-20 rounded-full bg-gray-900 dark:bg-gray-950 pointer-events-none"></div>
                    <div class="tp-scroll overflow-y-auto h-full">
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
