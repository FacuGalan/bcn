{{-- Mini-modal de cortesia: textarea para motivo (obligatorio) + boton Invitar.
     Reusado por NuevoPedidoMostrador y NuevaVenta via trait WithInvitaciones. --}}
@if($mostrarModalInvitarItem)
    @php
        $itemInvitar = $invitarItemIndex !== null ? ($items[$invitarItemIndex] ?? null) : null;
    @endphp
    {{-- Scope Alpine local: el boton se habilita al instante con motivoLocal,
         sin esperar round-trip a Livewire. El wire:model (sin .live) sincroniza
         al server justo antes del submit del form de <x-bcn-modal>. Backend
         igual valida motivo no vacio como defensa. --}}
    <div x-data="{ motivoLocal: @js($invitarItemMotivo) }">
        <x-bcn-modal
            :show="$mostrarModalInvitarItem"
            title="{{ __('Invitar renglón') }}"
            color="bg-emerald-600"
            maxWidth="md"
            onClose="cerrarModalInvitarItem"
            submit="confirmarInvitarItem"
        >
            <x-slot:body>
                <div class="space-y-4">
                    @if($itemInvitar)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $itemInvitar['nombre'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $itemInvitar['cantidad'] }} x $@precio($itemInvitar['precio'])
                                <span class="ml-2 font-medium text-gray-700 dark:text-gray-300">
                                    = $@precio($itemInvitar['precio'] * $itemInvitar['cantidad'])
                                </span>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="invitar-item-motivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Motivo de la invitación') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="invitar-item-motivo"
                            wire:model="invitarItemMotivo"
                            x-model="motivoLocal"
                            rows="3"
                            maxlength="500"
                            placeholder="{{ __('Ej: Cortesía gerencia, error de cocina, cliente VIP') }}"
                            class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50"
                            x-init="$nextTick(() => $el.focus())"
                        ></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('Quedará registrado para reportes y auditoría.') }}
                        </p>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                        wire:loading.attr="disabled"
                        :disabled="motivoLocal.trim().length === 0"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 10h14a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1v-9a1 1 0 011-1z"/>
                    </svg>
                    {{ __('Invitar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    </div>
@endif
