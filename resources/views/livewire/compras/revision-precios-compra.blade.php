{{-- Revisión de precios post-compra (RF-10): retomable, contra valores VIGENTES --}}
<div data-livewire-root="revision-precios-compra">
    <x-bcn-modal :title="__('Revisión de precios').' — '.($compra?->numero_comprobante ?? '')" color="bg-bcn-primary" maxWidth="5xl" onClose="cerrar">
        <x-slot:body>
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Artículos de la compra cuyo margen real quedó bajo la utilidad objetivo (calculado contra el costo y precio VIGENTES — retomable)') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-600 dark:text-gray-400">{{ __('Redondeo') }}</label>
                        <select wire:model.live="tipoRedondeo"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="ninguno">{{ __('Sin redondeo') }}</option>
                            <option value="entero">{{ __('Entero') }}</option>
                            <option value="decena">{{ __('Decena') }}</option>
                            <option value="centena">{{ __('Centena') }}</option>
                        </select>
                        <button type="button" wire:click="recalcular" class="text-xs text-bcn-primary hover:underline">{{ __('Recalcular') }}</button>
                    </div>
                </div>

                @if($filas === [])
                    <div class="text-center py-10 text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="mt-2 text-sm">{{ __('Todos los márgenes están sobre el objetivo') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">
                                        <input type="checkbox" checked @change="$wire.toggleTodas($event.target.checked)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículo') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase" title="{{ __('Costo rector vigente (último de compra)') }}">{{ __('Costo') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio actual') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Margen') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Objetivo') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sugerido') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio nuevo') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($filas as $i => $fila)
                                    <tr wire:key="fila-revision-{{ $fila['articulo_id'] }}" class="{{ $fila['seleccionado'] ? '' : 'opacity-50' }}">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" wire:model.live="filas.{{ $i }}.seleccionado"
                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700">
                                        </td>
                                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                            {{ $fila['nombre'] }}
                                            <span class="text-xs text-gray-400">{{ $fila['codigo'] }}</span>
                                            <span class="text-xs px-1.5 py-0.5 rounded {{ $fila['alcance'] === 'sucursal' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}"
                                                  title="{{ $fila['alcance'] === 'sucursal' ? __('Actualiza el precio de esta sucursal') : __('Actualiza el precio global del artículo') }}">
                                                {{ $fila['alcance'] === 'sucursal' ? __('sucursal') : __('global') }}
                                            </span>
                                            @if(!empty($fila['bajo_costo']))
                                                {{-- RF-B8: sugerido/precio nuevo igual o bajo el costo --}}
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                                      title="{{ __('El precio nuevo queda igual o por debajo del costo: re-marcá la fila para aplicarlo igual') }}">
                                                    {{ __('bajo costo') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">$@precio($fila['costo'])</td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">$@precio($fila['precio_actual'])</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                {{ number_format($fila['margen_real'], 1) }}%
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ number_format($fila['objetivo'], 1) }}%</td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                            {{ $fila['sugerido'] !== null ? '$'.number_format($fila['sugerido'], 2, ',', '.') : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="text" wire:model.live.debounce.500ms="filas.{{ $i }}.precio_nuevo"
                                                class="w-28 text-right rounded-md {{ !empty($fila['bajo_costo']) ? 'border-yellow-400 dark:border-yellow-600' : 'border-gray-300 dark:border-gray-600' }} dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('La cuenta del sugerido: costo × (1 + utilidad objetivo) × (1 + IVA si aplica) + redondeo. El precio nuevo es editable.') }}
                    </p>
                @endif
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                {{ __('Cerrar') }}
            </button>
            @if($filas !== [] && $this->puedeAplicar())
                <button type="button" wire:click="aplicar" wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    {{ __('Aplicar seleccionados') }}
                </button>
            @endif
        </x-slot:footer>
    </x-bcn-modal>
</div>
