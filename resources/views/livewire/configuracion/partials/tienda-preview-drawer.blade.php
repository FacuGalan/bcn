{{-- Drawer de vista previa (RF-T11/RF-T12): fallback para pantallas <xl,
     donde no entra el visor lateral. Usa el scope Alpine `tiendaPreview` del
     ANCESTRO (configuracion-tienda.blade.php) — acá no hay x-data propio.
     x-show va en HIJOS, nunca junto a x-data (gotcha del proyecto). --}}
<div class="inline-flex xl:hidden" @keydown.escape.window="open = false">

    {{-- Trigger --}}
    <button type="button" @click="open = true"
        class="inline-flex items-center gap-1 px-3 py-1.5 border border-bcn-primary text-bcn-primary text-xs font-semibold rounded-md hover:bg-bcn-primary/10 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        {{ __('Vista previa') }}
    </button>

    {{-- Backdrop --}}
    <div x-show="open" x-cloak @click="open = false"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-40 bg-black/40"></div>

    {{-- Panel --}}
    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 w-full max-w-md flex flex-col bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-200 dark:border-gray-700">

        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Vista previa de la tienda') }}</h3>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Simulación: los cambios de estética se reflejan al instante. La tipografía es aproximada; la tienda usa la fuente real.') }}</p>
            </div>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="{{ __('Cerrar') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            @include('livewire.configuracion.partials.tienda-preview-mock')
        </div>
    </div>
</div>
