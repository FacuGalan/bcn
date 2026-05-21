{{-- Mini-modal global: invitar PEDIDO COMPLETO desde la vista principal
     (botón al lado de "Descuentos"). Marca todos los items como cortesía
     pero NO persiste — el usuario después confirma el pedido con cualquier
     botón (Borrador / Sin cobrar / Confirmar) y se guarda con total=0.
     Reusado por NuevoPedidoMostrador y, en el futuro, NuevaVenta vía trait
     WithInvitaciones. --}}
@if($mostrarModalInvitarTodo)
    {{-- Alpine scope local: trackea el motivo del cliente para habilitar el
         boton al instante (sin esperar round-trip a Livewire). `wire:model`
         sin .live + el submit del form de <x-bcn-modal> sincronizan el valor
         al server antes de disparar confirmarInvitarTodo. Defensa en backend
         valida que motivo no este vacio (puede llegar igual via API). --}}
    <div x-data="{ motivoLocal: @js($motivoInvitacionTotal) }">
        <x-bcn-modal
            :show="$mostrarModalInvitarTodo"
            title="{{ __('Invitar pedido completo') }}"
            color="bg-emerald-600"
            maxWidth="md"
            onClose="cerrarModalInvitarTodo"
            submit="confirmarInvitarTodo"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-lg p-3">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                            </svg>
                            <div class="text-xs text-emerald-800 dark:text-emerald-200">
                                {{ __('Se marcarán los :n items del pedido como cortesía. No se cobrará al cliente.', ['n' => count($items)]) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="invitar-todo-motivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Motivo de la invitación') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="invitar-todo-motivo"
                            wire:model="motivoInvitacionTotal"
                            x-model="motivoLocal"
                            rows="3"
                            maxlength="500"
                            placeholder="{{ __('Ej: Cortesía gerencia, evento especial, cliente VIP') }}"
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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                    </svg>
                    {{ __('Invitar pedido completo') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    </div>
@endif
