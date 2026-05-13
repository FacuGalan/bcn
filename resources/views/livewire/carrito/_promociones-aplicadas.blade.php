{{-- Card de Promociones Aplicadas: muestra debajo del detalle si hay promos especiales o comunes activas. Reutilizable. --}}
@if($resultado && (count($resultado['promociones_especiales_aplicadas']) > 0 || count($resultado['promociones_comunes_aplicadas']) > 0))
    @php
        $totalPromos = count($resultado['promociones_especiales_aplicadas']) + count($resultado['promociones_comunes_aplicadas']);
    @endphp
    <div class="border border-green-200 rounded-lg overflow-hidden bg-green-50">
        <div class="bg-green-100 px-2 py-1 border-b border-green-200 flex justify-between items-center">
            <h4 class="text-xs font-medium text-green-800">{{ __('Promociones') }} ({{ $totalPromos }})</h4>
            @if($totalPromos > 4)
                <span class="text-[10px] text-green-600">scroll ↓</span>
            @endif
        </div>
        <div class="px-2 py-1.5 space-y-0.5 max-h-20 overflow-y-auto">
            @foreach($resultado['promociones_especiales_aplicadas'] as $promo)
                <div class="flex justify-between items-center text-xs">
                    <div><span class="font-medium text-green-700">{{ $promo['nombre'] }}</span> <span class="text-green-600 text-[10px]">({{ Str::limit($promo['descripcion'], 20) }})</span></div>
                    <span class="font-semibold text-green-700">-$@precio($promo['descuento'])</span>
                </div>
            @endforeach
            @foreach($resultado['promociones_comunes_aplicadas'] as $promo)
                <div class="flex justify-between items-center text-xs">
                    <div><span class="font-medium text-green-700">{{ $promo['nombre'] }}</span> <span class="text-green-600 text-[10px]">({{ Str::limit($promo['descripcion'], 20) }})</span></div>
                    <span class="font-semibold text-green-700">-$@precio($promo['descuento'])</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
