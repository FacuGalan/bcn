{{-- Mini-modal de confirmación para quitar la cortesía de TODO el pedido a
     la vez (cuando el pedido está marcado como invitación total). Restaura
     los precios originales de todos los items y limpia los metadatos. --}}
@if($mostrarModalDesinvitarTodo)
    @php
        $cantidadInvitados = collect($items)->where('es_invitacion', true)->count();
    @endphp
    <x-bcn-modal
        :show="$mostrarModalDesinvitarTodo"
        title="{{ __('Quitar cortesía del pedido') }}"
        color="bg-amber-500"
        maxWidth="md"
        onClose="cerrarModalDesinvitarTodo"
        submit="desinvitarTodos"
    >
        <x-slot:body>
            <div class="space-y-3">
                <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                        </svg>
                        <div class="text-xs text-emerald-800 dark:text-emerald-200">
                            {{ __(':n items del pedido están marcados como cortesía.', ['n' => $cantidadInvitados]) }}
                            @if(! empty($motivoInvitacionTotal))
                                <div class="mt-1 italic">{{ __('Motivo') }}: {{ $motivoInvitacionTotal }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Se restaurarán los precios originales de todos los renglones y se recalcularán promociones, cupones y descuentos.') }}
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
                {{ __('Quitar cortesía') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
