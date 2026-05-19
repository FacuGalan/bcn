{{-- Mini-modal de confirmacion para quitar la invitacion de un item. --}}
@if($mostrarModalDesinvitarItem)
    @php
        $itemDesinvitar = $desinvitarItemIndex !== null ? ($items[$desinvitarItemIndex] ?? null) : null;
    @endphp
    <x-bcn-modal
        :show="$mostrarModalDesinvitarItem"
        title="{{ __('Quitar invitación') }}"
        color="bg-amber-500"
        maxWidth="md"
        onClose="cerrarModalDesinvitarItem"
        submit="confirmarDesinvitarItem"
    >
        <x-slot:body>
            <div class="space-y-3">
                @if($itemDesinvitar)
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $itemDesinvitar['nombre'] }}
                        </div>
                        @if(!empty($itemDesinvitar['precio_unitario_original']))
                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ __('Precio original') }}:
                                <span class="font-medium text-gray-900 dark:text-white">
                                    $@precio($itemDesinvitar['precio_unitario_original'])
                                </span>
                            </div>
                        @endif
                        @if(!empty($itemDesinvitar['invitacion_motivo']))
                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ __('Motivo') }}:
                                <span class="italic">{{ $itemDesinvitar['invitacion_motivo'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Se restaurará el precio original del renglón y se recalcularán promociones, cupones y descuentos sobre el item.') }}
                </p>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                {{ __('Cancelar') }}
            </button>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                {{ __('Quitar invitación') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
