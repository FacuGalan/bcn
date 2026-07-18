{{-- Vista previa en vivo de la tienda (RF-T11): drawer lateral derecho con un
     mock del storefront pintado SOLO con los design tokens del form (CSS vars
     --tp-*). El x-data es ESTÁTICO (cero interpolación Blade: el morph de
     Livewire re-inicializa Alpine si el atributo cambia — gotcha del
     proyecto) y sincroniza por $wire.entangle. x-show va en HIJOS del x-data,
     nunca en el mismo elemento (otro gotcha del proyecto). El interior del
     mock NO usa clases dark:: la tienda se pinta solo con su propio tema. --}}
<div x-data="{
        open: false,
        colorPrimario: $wire.entangle('colorPrimario').live,
        colorAcento: $wire.entangle('colorAcento').live,
        colorFondo: $wire.entangle('colorFondo').live,
        colorSuperficie: $wire.entangle('colorSuperficie').live,
        colorTexto: $wire.entangle('colorTexto').live,
        fuente: $wire.entangle('fuente').live,
        radios: $wire.entangle('radios').live,
        densidad: $wire.entangle('densidad').live,
        get radioCard() { return ({ none: '0px', sm: '4px', md: '8px', lg: '16px', full: '24px' })[this.radios] || '8px' },
        get radioBoton() { return this.radios === 'full' ? '9999px' : this.radioCard },
        get pad() { return ({ compacta: '8px', normal: '12px', amplia: '16px' })[this.densidad] || '12px' },
        get fontStack() {
            return ({
                system: 'system-ui, sans-serif',
                inter: 'Inter, ui-sans-serif, system-ui, sans-serif',
                poppins: 'Poppins, ui-sans-serif, system-ui, sans-serif',
                roboto: 'Roboto, ui-sans-serif, system-ui, sans-serif',
                montserrat: 'Montserrat, ui-sans-serif, system-ui, sans-serif',
                lora: 'Lora, Georgia, serif',
            })[this.fuente] || 'system-ui, sans-serif'
        },
        get cssVars() {
            return '--tp-primario:' + this.colorPrimario + ';--tp-acento:' + this.colorAcento
                + ';--tp-fondo:' + this.colorFondo + ';--tp-superficie:' + this.colorSuperficie
                + ';--tp-texto:' + this.colorTexto + ';--tp-radio:' + this.radioCard
                + ';--tp-radio-boton:' + this.radioBoton + ';--tp-pad:' + this.pad
                + ';--tp-font:' + this.fontStack;
        },
    }"
    @keydown.escape.window="open = false"
    class="inline-flex">

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

        {{-- Mock del storefront: SOLO tokens de la tienda, sin dark: --}}
        <div class="flex-1 overflow-y-auto" :style="cssVars"
            style="background-color: var(--tp-fondo); font-family: var(--tp-font); color: var(--tp-texto);">

            {{-- Header con portada + logo + nombre --}}
            <div class="relative">
                <div class="h-28 w-full overflow-hidden" style="background: linear-gradient(120deg, var(--tp-primario), var(--tp-acento));">
                    @if($portadaPreviewUrl)
                        <img src="{{ $portadaPreviewUrl }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>
                <div class="absolute -bottom-7 left-4 h-14 w-14 overflow-hidden border-2 border-white shadow"
                    style="border-radius: var(--tp-radio-boton); background-color: var(--tp-superficie);">
                    @if($logoPreviewUrl)
                        <img src="{{ $logoPreviewUrl }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="h-full w-full flex items-center justify-center text-lg font-bold" style="color: var(--tp-primario);">
                            {{ mb_substr(app(\App\Services\TenantService::class)->getComercio()?->nombre ?? 'T', 0, 1) }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="pt-9 pb-4" style="padding-left: var(--tp-pad); padding-right: var(--tp-pad);">
                <p class="text-sm font-bold" style="color: var(--tp-texto);">{{ sucursal_activa_model()?->nombre ?? __('Tu tienda') }}</p>
                <p class="text-[11px] opacity-70">{{ __('Abierto · Delivery y Take Away') }}</p>

                {{-- Chips de categorías --}}
                <div class="flex gap-1.5 mt-3 overflow-hidden">
                    <span class="px-2.5 py-1 text-[11px] font-semibold text-white" style="background-color: var(--tp-primario); border-radius: var(--tp-radio-boton);">{{ __('Todo') }}</span>
                    <span class="px-2.5 py-1 text-[11px]" style="background-color: var(--tp-superficie); border-radius: var(--tp-radio-boton); border: 1px solid color-mix(in srgb, var(--tp-texto) 15%, transparent);">{{ __('Pizzas') }}</span>
                    <span class="px-2.5 py-1 text-[11px]" style="background-color: var(--tp-superficie); border-radius: var(--tp-radio-boton); border: 1px solid color-mix(in srgb, var(--tp-texto) 15%, transparent);">{{ __('Bebidas') }}</span>
                    <span class="px-2.5 py-1 text-[11px]" style="background-color: var(--tp-superficie); border-radius: var(--tp-radio-boton); border: 1px solid color-mix(in srgb, var(--tp-texto) 15%, transparent);">{{ __('Postres') }}</span>
                </div>

                {{-- Cards de producto de ejemplo --}}
                <div class="mt-3 grid grid-cols-2" style="gap: var(--tp-pad);">
                    @foreach([
                        ['nombre' => __('Pizza muzzarella'), 'precio' => '$ 9.500', 'oferta' => true],
                        ['nombre' => __('Hamburguesa completa'), 'precio' => '$ 8.200', 'oferta' => false],
                        ['nombre' => __('Gaseosa 1,5 L'), 'precio' => '$ 2.800', 'oferta' => false],
                        ['nombre' => __('Flan casero'), 'precio' => '$ 3.100', 'oferta' => true],
                    ] as $producto)
                        <div class="overflow-hidden shadow-sm" style="background-color: var(--tp-superficie); border-radius: var(--tp-radio);">
                            <div class="relative h-16" style="background: color-mix(in srgb, var(--tp-primario) 18%, var(--tp-superficie));">
                                @if($producto['oferta'])
                                    <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 text-[9px] font-bold text-white" style="background-color: var(--tp-acento); border-radius: var(--tp-radio-boton);">{{ __('Oferta') }}</span>
                                @endif
                            </div>
                            <div style="padding: var(--tp-pad);">
                                <p class="text-[11px] font-semibold leading-tight" style="color: var(--tp-texto);">{{ $producto['nombre'] }}</p>
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-[11px] font-bold" style="color: var(--tp-texto);">{{ $producto['precio'] }}</span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold text-white" style="background-color: var(--tp-primario); border-radius: var(--tp-radio-boton);">{{ __('Agregar') }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Barra carrito --}}
            <div class="sticky bottom-0" style="padding: var(--tp-pad);">
                <div class="flex items-center justify-between px-3 py-2 text-white text-xs font-semibold shadow-lg"
                    style="background-color: var(--tp-primario); border-radius: var(--tp-radio-boton);">
                    <span>{{ __('Ver carrito') }} · 2</span>
                    <span>$ 12.300</span>
                </div>
            </div>
        </div>
    </div>
</div>
