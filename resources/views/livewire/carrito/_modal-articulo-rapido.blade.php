{{-- Modal de Alta Rápida de Artículo (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
@if($mostrarModalArticuloRapido)
    <x-bcn-modal
        :show="$mostrarModalArticuloRapido"
        title="{{ __('Nuevo Artículo') }}"
        color="bg-indigo-600"
        maxWidth="2xl"
        onClose="cerrarModalArticuloRapido"
        submit="guardarArticuloRapido"
    >
        <x-slot:body>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                    <input type="text" wire:model="artRapidoNombre" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" placeholder="{{ __('Ej: Coca Cola 500ml') }}" />
                    @error('artRapidoNombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</label>
                        <select wire:model.live="artRapidoCategoriaId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="">{{ __('Sin categoría') }}</option>
                            @foreach($artRapidoCategorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código') }} *</label>
                        <input type="text" wire:model="artRapidoCodigo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" placeholder="Ej: ART-001" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se propone automáticamente si la categoría tiene prefijo') }}</p>
                        @error('artRapidoCodigo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código de barras') }}</label>
                        <input type="text" wire:model="artRapidoCodigoBarras" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" placeholder="EAN-13, UPC..." maxlength="50" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Unidad de Medida') }} *</label>
                        <select wire:model="artRapidoUnidadMedida" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="unidad">{{ __('Unidad') }}</option>
                            <option value="kg">{{ __('Kilogramo (kg)') }}</option>
                            <option value="gr">{{ __('Gramo (gr)') }}</option>
                            <option value="lt">{{ __('Litro (lt)') }}</option>
                            <option value="ml">{{ __('Mililitro (ml)') }}</option>
                            <option value="mt">{{ __('Metro (mt)') }}</option>
                            <option value="cm">{{ __('Centímetro (cm)') }}</option>
                            <option value="caja">{{ __('Caja') }}</option>
                            <option value="paquete">{{ __('Paquete') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Tipo de IVA') }} *</label>
                        <select wire:model="artRapidoTipoIvaId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($artRapidoTiposIva as $tipoIva)
                                <option value="{{ $tipoIva->id }}">{{ $tipoIva->nombre }} ({{ $tipoIva->porcentaje }}%)</option>
                            @endforeach
                        </select>
                        @error('artRapidoTipoIvaId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Precio Base') }} *</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                            </div>
                            <input type="number" wire:model="artRapidoPrecioBase" step="0.01" min="0" class="block w-full pl-7 pr-3 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" placeholder="0.00" />
                        </div>
                        @error('artRapidoPrecioBase') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                {{ __('Cancelar') }}
            </button>
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                {{ __('Crear y Agregar') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
