{{-- Modal de Artículo Pesable (reutilizable: NuevaVenta + NuevoPedidoMostrador) --}}
@if($mostrarModalPesable)
    <x-bcn-modal
        :show="$mostrarModalPesable"
        title="{{ __('Artículo Pesable') }}"
        color="bg-amber-500"
        maxWidth="md"
        onClose="cerrarModalPesable"
    >
        <x-slot:body>
            <div x-data="{
                precio: {{ $pesablePrecioUnitario }},
                cantidad: '',
                valor: '',
                editando: null,
                submitting: false,
                parseDec(s) {
                    if (s === null || s === undefined || s === '') return 0;
                    const n = parseFloat(String(s).replace(',', '.'));
                    return isNaN(n) ? 0 : n;
                },
                calcDesdeQty() {
                    if (this.editando !== 'cantidad') return;
                    const c = this.parseDec(this.cantidad);
                    if (c > 0 && this.precio > 0) {
                        this.valor = (Math.round(c * this.precio * 100) / 100).toString();
                    } else {
                        this.valor = '';
                    }
                },
                calcDesdeValor() {
                    if (this.editando !== 'valor') return;
                    const v = this.parseDec(this.valor);
                    if (v > 0 && this.precio > 0) {
                        this.cantidad = (Math.round(v / this.precio * 1000) / 1000).toString();
                    } else {
                        this.cantidad = '';
                    }
                },
                confirmar() {
                    if (this.submitting) return;
                    const c = this.parseDec(this.cantidad);
                    if (c <= 0) return;
                    this.submitting = true;
                    $wire.confirmarPesable(c);
                }
            }" @confirmar-pesable-modal.window="confirmar()" class="space-y-4">
                {{-- Info del artículo --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $pesableNombreArticulo }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Precio unitario') }}: <span class="font-medium text-gray-900 dark:text-white">${{ number_format($pesablePrecioUnitario, 2, ',', '.') }} / {{ $pesableUnidadMedida }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Cantidad') }} ({{ $pesableUnidadMedida }})
                        </label>
                        <input
                            type="text"
                            inputmode="decimal"
                            x-model="cantidad"
                            @input="cantidad = String($el.value).replace(',', '.'); calcDesdeQty()"
                            @focus="editando = 'cantidad'"
                            @keydown.enter.prevent="confirmar()"
                            x-init="setTimeout(() => $el.focus(), 350)"
                            class="block w-full text-lg text-center rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50"
                            placeholder="0.000"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Valor') }} ($)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 dark:text-gray-400 text-lg">$</span>
                            </div>
                            <input
                                type="text"
                                inputmode="decimal"
                                x-model="valor"
                                @input="valor = String($el.value).replace(',', '.'); calcDesdeValor()"
                                @focus="editando = 'valor'"
                                @keydown.enter.prevent="confirmar()"
                                class="block w-full pl-8 text-lg text-center rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    {{ __('Ingrese la cantidad o el valor y el otro se calculará automáticamente') }}
                </p>
            </div>
        </x-slot:body>
        <x-slot:footer>
            <button type="button" @click="close()" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-400">
                {{ __('Cancelar') }}
            </button>
            <button type="button" @click="$dispatch('confirmar-pesable-modal')" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500">
                {{ __('Agregar') }}
            </button>
        </x-slot:footer>
    </x-bcn-modal>
@endif
