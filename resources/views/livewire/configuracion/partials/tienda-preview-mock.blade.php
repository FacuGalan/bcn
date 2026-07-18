{{-- Mock del storefront (RF-T11/RF-T12): SIMULACIÓN pintada solo con las CSS
     vars --tp-* del scope Alpine `tiendaPreview` (getter cssVars). Se usa
     como fallback del visor cuando la tienda está despublicada y dentro del
     drawer móvil. Sin clases dark:: la tienda se pinta con su propio tema. --}}
<div class="overflow-y-auto" :style="cssVars"
    style="background-color: var(--tp-fondo); font-family: var(--tp-font); color: var(--tp-texto);">

    {{-- Header con portada + logo + nombre --}}
    <div class="relative">
        <div class="relative h-28 w-full overflow-hidden" style="background: linear-gradient(120deg, var(--tp-primario), var(--tp-acento));">
            @if($portadaPreviewUrl)
                <img src="{{ $portadaPreviewUrl }}" alt="" class="h-full w-full object-cover"
                    :style="'object-position: center ' + portadaPosicion">
                {{-- Fade con el color primario (RF-T13, toggle en vivo) --}}
                <div x-show="portadaOverlay" class="absolute inset-0"
                    style="background: color-mix(in srgb, var(--tp-primario) 55%, transparent);"></div>
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
        {{-- Slogan en vivo (RF-T13) --}}
        <p x-show="slogan" x-text="slogan" class="text-[11px] italic opacity-80" style="color: var(--tp-texto);"></p>
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
