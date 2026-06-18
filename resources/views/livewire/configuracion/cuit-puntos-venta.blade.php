<div>
    @if($mostrarModal)
        <x-bcn-modal
            :title="__('Puntos de venta de :cuit', ['cuit' => $cuitNombre])"
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

                {{-- Alta de punto de venta --}}
                <div class="mb-5 p-4 rounded-md border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40">
                    <div class="grid grid-cols-1 sm:grid-cols-[8rem_1fr_auto] gap-3 sm:items-end">
                        <div>
                            <label for="pv-numero" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Número') }} <span class="text-red-500">*</span></label>
                            <input id="pv-numero" type="number" min="1" max="99999" wire:model="nuevoPuntoVentaNumero" data-enter-default
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            @error('nuevoPuntoVentaNumero') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="pv-nombre" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                            <input id="pv-nombre" type="text" maxlength="100" wire:model="nuevoPuntoVentaNombre" data-enter-default
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <button type="button" wire:click="agregarPuntoVenta"
                            class="inline-flex items-center justify-center px-3 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors">
                            <svg class="w-4 h-4 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span class="hidden sm:inline">{{ __('Agregar') }}</span>
                        </button>
                    </div>
                </div>

                {{-- Lista de puntos de venta --}}
                @if(count($puntosVenta) > 0)
                    <div class="space-y-2">
                        @foreach($puntosVenta as $pv)
                            <div wire:key="pv-{{ $pv['id'] }}"
                                class="p-3 rounded-md border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ str_pad($pv['numero'], 4, '0', STR_PAD_LEFT) }}</span>
                                        @if(!empty($pv['nombre']))
                                            <span class="text-gray-600 dark:text-gray-400 truncate">{{ $pv['nombre'] }}</span>
                                        @endif
                                        @unless($pv['activo'])
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-600 dark:text-gray-400">{{ __('Inactivo') }}</span>
                                        @endunless
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1">
                                        <button type="button" wire:click="togglePuntoVentaActivo({{ $pv['id'] }})"
                                            class="p-1.5 rounded transition-colors {{ $pv['activo'] ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                                            title="{{ $pv['activo'] ? __('Desactivar') : __('Activar') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($pv['activo'])
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                @endif
                                            </svg>
                                        </button>
                                        <button type="button" wire:click="confirmarEliminar({{ $pv['id'] }})"
                                            class="p-1.5 text-red-600 hover:bg-red-50 rounded dark:hover:bg-red-900/20 transition-colors" title="{{ __('Eliminar') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Domicilio fiscal declarado del PV (RF-11) --}}
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-600">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Domicilio fiscal') }}</label>
                                    @if(count($domiciliosDelCuit) > 0)
                                        <select
                                            wire:change="actualizarDomicilioPv({{ $pv['id'] }}, $event.target.value)"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-white">
                                            <option value="">{{ __('Sin domicilio asignado') }}</option>
                                            @foreach($domiciliosDelCuit as $domId => $domLabel)
                                                <option value="{{ $domId }}" @selected((int)($pv['cuit_domicilio_id'] ?? 0) === (int)$domId)>{{ $domLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <p class="text-xs text-gray-400 dark:text-gray-500 italic">{{ __('Cargá domicilios para este CUIT (botón "Domicilios") y luego asignalos.') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Este CUIT no tiene puntos de venta. Agregá uno arriba.') }}</p>
                    </div>
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
            :title="__('Eliminar punto de venta')"
            :message="__('¿Eliminar el punto de venta :numero?', ['numero' => $confirmandoEliminarLabel])"
            confirm="eliminarConfirmado"
            cancel="cancelarEliminar"
        />
    @endif
</div>
