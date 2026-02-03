{{-- Configurador de escalas para NxM --}}
@php
    $colorBase = $color ?? 'purple';
    $colorClasses = [
        'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-300', 'btn' => 'text-purple-600 hover:text-purple-800', 'radio' => 'text-purple-600 focus:ring-purple-500'],
        'indigo' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-300', 'btn' => 'text-indigo-600 hover:text-indigo-800', 'radio' => 'text-indigo-600 focus:ring-indigo-500'],
    ][$colorBase] ?? ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-300', 'btn' => 'text-purple-600 hover:text-purple-800', 'radio' => 'text-purple-600 focus:ring-purple-500'];
@endphp

<div class="space-y-2">
    @forelse($escalas as $index => $escala)
        <div class="bg-white dark:bg-gray-700 rounded-lg p-3 border {{ $colorClasses['border'] }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Escala') }} {{ $index + 1 }}</span>
                <button type="button" wire:click="eliminarEscala({{ $index }})"
                        class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Rango de cantidades + Lleva/Bonifica --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Desde (unidades)') }}</label>
                    <input type="number" wire:model="escalas.{{ $index }}.cantidad_desde" min="1"
                           class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-center">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Hasta (opcional)') }}</label>
                    <input type="number" wire:model="escalas.{{ $index }}.cantidad_hasta" min="1"
                           :placeholder="__('Sin lim.')"
                           class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-center">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Lleva') }}</label>
                    <input type="number" wire:model="escalas.{{ $index }}.lleva" min="2"
                           class="w-full text-sm text-center rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Bonifica') }}</label>
                    @if(($escala['beneficio_tipo'] ?? 'gratis') === 'descuento')
                        <input type="number" value="1" disabled
                               class="w-full text-sm text-center rounded border-gray-300 dark:border-gray-600 font-bold bg-gray-100 dark:bg-gray-900 cursor-not-allowed"
                               :title="__('Cuando es descuento %, siempre se bonifica 1 unidad')">
                    @else
                        <input type="number" wire:model="escalas.{{ $index }}.bonifica" min="1"
                               class="w-full text-sm text-center rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                    @endif
                </div>
            </div>

            {{-- Tipo de beneficio --}}
            <div class="flex items-center gap-3 justify-center pt-2 border-t dark:border-gray-600 flex-wrap">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" wire:model.live="escalas.{{ $index }}.beneficio_tipo" value="gratis"
                           class="{{ $colorClasses['radio'] }}">
                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ __('Gratis') }}</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" wire:model.live="escalas.{{ $index }}.beneficio_tipo" value="descuento"
                           class="{{ $colorClasses['radio'] }}">
                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ __('Descuento') }}</span>
                </label>
                @if(($escala['beneficio_tipo'] ?? 'gratis') === 'descuento')
                    <div class="flex items-center gap-1">
                        <input type="number" wire:model="escalas.{{ $index }}.beneficio_porcentaje" min="1" max="99"
                               class="w-16 text-sm text-center rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                        <span class="text-xs text-gray-500 dark:text-gray-400">%</span>
                    </div>
                @endif
            </div>

            {{-- Resumen --}}
            @php
                $lleva = $escala['lleva'] ?? 0;
                $beneficioTipoEscala = $escala['beneficio_tipo'] ?? 'gratis';
                $bonifica = $beneficioTipoEscala === 'descuento' ? 1 : ($escala['bonifica'] ?? 0);
                $porcentaje = $escala['beneficio_porcentaje'] ?? 0;
            @endphp
            @if($lleva && $bonifica && $bonifica < $lleva)
                <div class="mt-2 text-center">
                    <span class="px-2 py-0.5 {{ $colorClasses['bg'] }} {{ $colorClasses['text'] }} rounded text-xs font-medium">
                        @if($beneficioTipoEscala === 'gratis')
                            {{ $bonifica }} {{ __('gratis') }}
                        @else
                            1 {{ __('con') }} {{ $porcentaje }}% {{ __('dto') }}
                        @endif
                    </span>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white dark:bg-gray-700 rounded-lg p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No hay escalas configuradas') }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Agrega escalas para ofrecer diferentes promociones segun cantidad') }}</p>
        </div>
    @endforelse

    <button type="button" wire:click="agregarEscala"
            class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm {{ $colorClasses['btn'] }} bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        {{ __('Agregar escala') }}
    </button>

    @if(count($escalas) > 0)
        <div class="mt-2 p-2 {{ $colorClasses['bg'] }} rounded text-xs {{ $colorClasses['text'] }}">
            <strong>{{ __('Tip:') }}</strong> {{ __('Las escalas se evaluan en orden. Configura desde menores a mayores cantidades.') }}
        </div>
    @endif
</div>
