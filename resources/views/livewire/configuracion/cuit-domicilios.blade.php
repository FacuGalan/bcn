<div>
    @if($mostrarModal)
        <x-bcn-modal
            :title="__('Domicilios de :cuit', ['cuit' => $cuitNombre])"
            color="bg-bcn-primary"
            maxWidth="3xl"
            onClose="cerrar"
        >
            <x-slot:body>
                {{-- CUIT sobre el que se está trabajando --}}
                <div class="mb-4 px-3 py-2 rounded-md bg-bcn-primary/10 dark:bg-bcn-primary/20">
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">{{ __('CUIT') }}:</span>
                        <span class="font-mono">{{ $cuitNumero }}</span>
                        <span class="text-gray-500 dark:text-gray-400">· {{ $cuitNombre }}</span>
                    </p>
                </div>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Domicilios declarados ante AFIP. Cada punto de venta se asocia a uno; la jurisdicción de Ingresos Brutos sale de la provincia del domicilio.') }}
                </p>

                {{-- Lista de domicilios --}}
                @if(count($domicilios) > 0)
                    <div class="space-y-2 mb-4">
                        @foreach($domicilios as $dom)
                            <div wire:key="dom-{{ $dom['id'] }}"
                                class="p-3 rounded-md border border-gray-200 dark:border-gray-600 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $dom['direccion'] ?: __('(sin dirección)') }}</span>
                                        @if($dom['es_principal'])
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-bcn-primary/15 text-bcn-primary dark:text-bcn-primary font-medium">{{ __('Principal') }}</span>
                                        @endif
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">{{ __(ucfirst($dom['tipo'])) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $dom['localidad'] ? $dom['localidad'].', ' : '' }}{{ $dom['provincia_nombre'] ?? $dom['provincia'] }}
                                        <span class="font-mono">({{ $dom['provincia'] }})</span>
                                    </p>
                                </div>
                                <div class="shrink-0 flex items-center gap-1">
                                    @if(! $dom['es_principal'])
                                        <button type="button" wire:click="marcarPrincipal({{ $dom['id'] }})"
                                            class="text-xs text-bcn-primary hover:underline px-1.5 py-1"
                                            title="{{ __('Marcar como principal') }}">{{ __('Principal') }}</button>
                                    @endif
                                    <button type="button" wire:click="editarDomicilio({{ $dom['id'] }})"
                                        class="text-gray-500 hover:text-bcn-primary dark:text-gray-400 p-1" title="{{ __('Editar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" wire:click="confirmarEliminar({{ $dom['id'] }})"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 p-1" title="{{ __('Eliminar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif(! $mostrarForm)
                    <div class="text-center py-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Este CUIT no tiene domicilios cargados.') }}</p>
                    </div>
                @endif

                {{-- Form de alta/edición --}}
                @if($mostrarForm)
                    <div class="mt-2 p-4 rounded-md border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                            {{ $editandoId ? __('Editar domicilio') : __('Nuevo domicilio') }}
                        </h4>

                        @include('livewire.partials.domicilio-form', ['conTipo' => true, 'idPrefix' => 'cuitdom'])

                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" wire:click="cancelarForm"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                                {{ __('Cancelar') }}
                            </button>
                            <button type="button" wire:click="guardarDomicilio"
                                class="inline-flex items-center px-3 py-1.5 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors">
                                {{ __('Guardar domicilio') }}
                            </button>
                        </div>
                    </div>
                @else
                    <button type="button" wire:click="nuevoDomicilio"
                        class="inline-flex items-center gap-1 text-sm text-bcn-primary hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Agregar domicilio') }}
                    </button>
                @endif
            </x-slot:body>

            <x-slot:footer>
                <button type="button" wire:click="cerrar"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>

        {{-- Confirmación de eliminación (se apila sobre el modal) --}}
        <x-bcn-confirm
            :show="$confirmandoEliminarId !== null"
            :title="__('Eliminar domicilio')"
            :message="__('¿Eliminar el domicilio :dom? Los puntos de venta asociados quedarán sin domicilio.', ['dom' => $confirmandoEliminarLabel])"
            confirm="eliminarConfirmado"
            cancel="cancelarEliminar"
        />
    @endif
</div>
