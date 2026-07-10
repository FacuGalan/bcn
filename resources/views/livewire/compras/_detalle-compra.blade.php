{{-- Detalle de compra (D7 #11): reconstrucción PERFECTA de los montos de la factura.
     Recibe: $compra (con relaciones), $pagosAplicados, $costosGenerados,
     $puedeVerCostos, $puedeCrear, $puedeCancelar, $labelTipo --}}
<x-bcn-modal :title="__('Detalle').' — '.$compra->numero_comprobante" color="bg-bcn-primary" maxWidth="5xl" onClose="cerrarDetalle">
    <x-slot:body>
        <div class="space-y-4">

            {{-- Encabezado --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-2 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Proveedor') }}</p>
                    <p class="text-gray-900 dark:text-white font-medium">{{ $compra->proveedor?->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Comprobante') }}</p>
                    <p class="text-gray-900 dark:text-white">
                        {{ $labelTipo($compra->tipo_comprobante) }}
                        @if($compra->numero_comprobante_proveedor)
                            · {{ $compra->numero_comprobante_proveedor }}
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Estado') }}</p>
                    <div class="flex items-center gap-2">
                        @include('livewire.compras._badge-estado-compra', ['compra' => $compra])
                        @if($compra->estaCompletada() && ! $compra->esNotaCredito())
                            @include('livewire.compras._badge-pago-compra', ['compra' => $compra])
                        @endif
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Fechas') }}</p>
                    <p class="text-gray-900 dark:text-white">
                        {{ __('Carga') }}: {{ $compra->fecha?->format('d/m/Y') }}
                        @if($compra->fecha_comprobante)
                            <br>{{ __('Comprobante') }}: {{ $compra->fecha_comprobante->format('d/m/Y') }}
                        @endif
                        @if($compra->fecha_vencimiento)
                            <br>{{ __('Vencimiento') }}: {{ $compra->fecha_vencimiento->format('d/m/Y') }}
                        @endif
                    </p>
                </div>
                @if($compra->cuit)
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('CUIT comprador') }}</p>
                        <p class="text-gray-900 dark:text-white">{{ $compra->cuit->razon_social }} ({{ $compra->cuit->numero_cuit }})</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cuenta de compra') }}</p>
                    <p class="text-gray-900 dark:text-white">{{ $compra->cuentaCompra?->nombre ?? __('Sin clasificar') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Cargada por') }}</p>
                    <p class="text-gray-900 dark:text-white">{{ $compra->usuario?->name ?? '—' }} · {{ $compra->sucursal?->nombre }}</p>
                </div>
                @if($compra->compraOrigen)
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Compra origen (NC)') }}</p>
                        <p class="text-gray-900 dark:text-white">{{ $compra->compraOrigen->numero_comprobante }}</p>
                    </div>
                @endif
                @if($compra->observaciones)
                    <div class="col-span-2 sm:col-span-3 lg:col-span-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Observaciones') }}</p>
                        <p class="text-gray-900 dark:text-white whitespace-pre-line">{{ $compra->observaciones }}</p>
                    </div>
                @endif
            </div>

            {{-- Renglones --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Renglones') }}</h4>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículo') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cantidad') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio unit.') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Desc.') }}</th>
                                @if($compra->discriminaIva())
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IVA') }}</th>
                                @endif
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Subtotal') }}</th>
                                @if($puedeVerCostos && ! $compra->esBorrador())
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase" title="{{ __('Costo por unidad de stock: renglón con descuentos − desc. global + conceptos prorrateados (÷ factor)') }}">{{ __('Costo unit.') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($compra->detalles as $detalle)
                                <tr>
                                    <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                        {{ $detalle->articulo?->nombre ?? __('Artículo eliminado') }}
                                        <span class="text-xs text-gray-400">{{ $detalle->articulo?->codigo }}</span>
                                        @if($detalle->codigo_proveedor_usado)
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">{{ $detalle->codigo_proveedor_usado }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format((float) $detalle->cantidad_comprada, 3, ',', '.'), '0'), ',') }}
                                        @if((float) $detalle->factor_conversion !== 1.0)
                                            × {{ rtrim(rtrim(number_format((float) $detalle->factor_conversion, 4, ',', '.'), '0'), ',') }}
                                            = {{ rtrim(rtrim(number_format((float) $detalle->cantidad, 3, ',', '.'), '0'), ',') }} <span class="text-xs text-gray-400">{{ __('stock') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">$@precio($detalle->precio_unitario)</td>
                                    <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                        @if((array) $detalle->descuentos !== [])
                                            {{ implode('+', array_map(fn ($d) => rtrim(rtrim(number_format((float) $d, 2, '.', ''), '0'), '.'), (array) $detalle->descuentos)) }}%
                                            <span class="text-xs text-gray-400">(−$@precio($detalle->descuento_monto))</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    @if($compra->discriminaIva())
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                            {{ $detalle->tipoIva ? rtrim(rtrim(number_format((float) $detalle->tipoIva->porcentaje, 2, '.', ''), '0'), '.').'%' : '—' }}
                                        </td>
                                    @endif
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white whitespace-nowrap">$@precio($detalle->subtotal)</td>
                                    @if($puedeVerCostos && ! $compra->esBorrador())
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                            $@precio($detalle->costo_unitario_computable)
                                            @if((float) $detalle->descuento_global_monto > 0 || (float) $detalle->conceptos_costo_monto > 0)
                                                <p class="text-xs text-gray-400 whitespace-nowrap">
                                                    @if((float) $detalle->descuento_global_monto > 0) {{ __('desc. global') }}: −$@precio($detalle->descuento_global_monto) @endif
                                                    @if((float) $detalle->conceptos_costo_monto > 0) {{ __('conceptos') }}: +$@precio($detalle->conceptos_costo_monto) @endif
                                                </p>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-4">
                    {{-- Desglose fiscal --}}
                    @if($compra->esFiscal() && $compra->ivas->isNotEmpty())
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Desglose de IVA') }}</h4>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Alícuota') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Base imponible') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IVA') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($compra->ivas as $iva)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ rtrim(rtrim(number_format((float) $iva->alicuota, 2, '.', ''), '0'), '.') }}%</td>
                                                <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">$@precio($iva->base_imponible)</td>
                                                <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">$@precio($iva->importe)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if((float) $compra->neto_no_gravado > 0 || (float) $compra->neto_exento > 0)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if((float) $compra->neto_no_gravado > 0) {{ __('Neto no gravado') }}: $@precio($compra->neto_no_gravado) @endif
                                    @if((float) $compra->neto_exento > 0) · {{ __('Neto exento') }}: $@precio($compra->neto_exento) @endif
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Conceptos --}}
                    @if($compra->conceptos->isNotEmpty())
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Conceptos del pie') }}</h4>
                            <ul class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($compra->conceptos as $concepto)
                                    <li class="px-3 py-2 flex justify-between text-sm">
                                        <span class="text-gray-800 dark:text-gray-200">
                                            {{ __(ucfirst(str_replace('_', ' ', $concepto->tipo))) }}
                                            @if($concepto->descripcion) — {{ $concepto->descripcion }} @endif
                                            @if($concepto->computa_costo)
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">{{ __('Computa costo') }}</span>
                                            @endif
                                            @if($concepto->tipoIva)
                                                <span class="text-xs text-gray-400">{{ __('IVA') }} {{ rtrim(rtrim(number_format((float) $concepto->tipoIva->porcentaje, 2, '.', ''), '0'), '.') }}%</span>
                                            @endif
                                        </span>
                                        <span class="text-gray-900 dark:text-white whitespace-nowrap">$@precio($concepto->monto)</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Percepciones --}}
                    @if($compra->percepciones->isNotEmpty())
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Percepciones sufridas') }}</h4>
                            <ul class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($compra->percepciones as $percepcion)
                                    <li class="px-3 py-2 flex justify-between text-sm">
                                        <span class="text-gray-800 dark:text-gray-200">
                                            {{ $percepcion->impuesto?->nombre ?? '—' }}
                                            @if($percepcion->base_imponible)
                                                <span class="text-xs text-gray-400">({{ __('base') }} $@precio($percepcion->base_imponible)@if($percepcion->alicuota) × {{ rtrim(rtrim(number_format((float) $percepcion->alicuota, 4, '.', ''), '0'), '.') }}% @endif)</span>
                                            @endif
                                        </span>
                                        <span class="text-gray-900 dark:text-white whitespace-nowrap">$@precio($percepcion->monto)</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- La cuenta completa (reconstrucción de la factura) --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 h-fit">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Totales del comprobante') }}</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600 dark:text-gray-400">{{ $compra->discriminaIva() ? __('Neto (renglones c/desc.)') : __('Subtotal (renglones c/desc.)') }}</dt>
                            <dd class="text-gray-900 dark:text-white">$@precio($compra->subtotal)</dd>
                        </div>
                        @if((float) $compra->descuento_global_monto > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ __('Descuento global') }}
                                    @if($compra->descuento_global_porcentaje !== null)
                                        ({{ rtrim(rtrim(number_format((float) $compra->descuento_global_porcentaje, 2, '.', ''), '0'), '.') }}%)
                                    @endif
                                </dt>
                                <dd class="text-red-600 dark:text-red-400">−$@precio($compra->descuento_global_monto)</dd>
                            </div>
                        @endif
                        @if($compra->conceptos->isNotEmpty())
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('Conceptos') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($compra->conceptos->sum('monto'))</dd>
                            </div>
                        @endif
                        @if((float) $compra->total_iva > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('IVA') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($compra->total_iva)</dd>
                            </div>
                        @endif
                        @if($compra->percepciones->isNotEmpty())
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('Percepciones') }}</dt>
                                <dd class="text-gray-900 dark:text-white">$@precio($compra->percepciones->sum('monto'))</dd>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5">
                            <dt class="font-bold text-gray-900 dark:text-white">{{ __('Total') }}</dt>
                            <dd class="font-bold text-lg text-gray-900 dark:text-white">$@precio($compra->total)</dd>
                        </div>
                        @if($compra->estaCompletada() && ! $compra->esNotaCredito())
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">{{ __('Saldo pendiente') }}</dt>
                                <dd class="{{ $compra->tieneSaldoPendiente() ? 'text-orange-600 dark:text-orange-400 font-semibold' : 'text-green-600 dark:text-green-400' }}">$@precio($compra->saldo_pendiente)</dd>
                            </div>
                        @endif
                    </dl>

                    {{-- Pagos aplicados --}}
                    @if($pagosAplicados->isNotEmpty())
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-4 mb-1">{{ __('Pagos aplicados') }}</h4>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach($pagosAplicados as $aplicacion)
                                <li class="py-1.5 flex justify-between items-center">
                                    <span class="text-gray-800 dark:text-gray-200">
                                        {{ $aplicacion->pagoProveedor?->numero }}
                                        <span class="text-xs text-gray-400">{{ $aplicacion->pagoProveedor?->fecha ? \Carbon\Carbon::parse($aplicacion->pagoProveedor->fecha)->format('d/m/Y') : '' }}</span>
                                        @if($aplicacion->pagoProveedor?->estado !== 'activo')
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">{{ __('Anulada') }}</span>
                                        @endif
                                    </span>
                                    <span class="text-gray-900 dark:text-white whitespace-nowrap">$@precio($aplicacion->monto_aplicado)</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- NCs vinculadas --}}
                    @if($compra->notasCredito->isNotEmpty())
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-4 mb-1">{{ __('Notas de crédito vinculadas') }}</h4>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach($compra->notasCredito as $nc)
                                <li class="py-1.5 flex justify-between items-center">
                                    <button type="button" wire:click="verDetalle({{ $nc->id }})" class="text-bcn-primary hover:underline text-left">
                                        {{ $nc->numero_comprobante }}
                                    </button>
                                    <span class="text-gray-900 dark:text-white whitespace-nowrap">−$@precio($nc->total)</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Costos que generó (RF-03, gated por costos.ver) --}}
                    @if($puedeVerCostos && $costosGenerados->isNotEmpty())
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-4 mb-1">
                            {{ __('Costos que generó') }}
                            <span class="font-normal text-xs text-gray-500 dark:text-gray-400">({{ __('el costo se actualiza con las compras') }})</span>
                        </h4>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            @foreach($costosGenerados as $costo)
                                <li class="py-1.5 flex justify-between items-center gap-2">
                                    <span class="text-gray-800 dark:text-gray-200">
                                        {{ $costo->articulo?->nombre ?? '—' }}
                                        <span class="text-xs text-gray-400">({{ $costo->sucursal_id ? __('sucursal') : __('consolidado') }})</span>
                                    </span>
                                    <span class="text-gray-900 dark:text-white whitespace-nowrap">
                                        ${{ number_format((float) $costo->costo_anterior, 2, ',', '.') }} → <strong>${{ number_format((float) $costo->costo_nuevo, 2, ',', '.') }}</strong>
                                        @if($costo->porcentaje_cambio !== null)
                                            <span class="text-xs {{ (float) $costo->porcentaje_cambio > 0 ? 'text-red-500' : 'text-green-500' }}">
                                                ({{ (float) $costo->porcentaje_cambio > 0 ? '+' : '' }}{{ number_format((float) $costo->porcentaje_cambio, 1, ',', '.') }}%)
                                            </span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </x-slot:body>
    <x-slot:footer>
        <button type="button" @click="close()"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
            {{ __('Cerrar') }}
        </button>
        @if($compra->esBorrador() && $puedeCrear)
            <button type="button" wire:click="abrirEditarCompra({{ $compra->id }})"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Editar borrador') }}
            </button>
        @endif
        @if($compra->estaCompletada() && ! $compra->esNotaCredito() && $puedeCrear)
            <button type="button" wire:click="abrirNCDesdeCompra({{ $compra->id }})"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Cargar NC') }}
            </button>
        @endif
        @if($compra->estaCompletada() && $puedeCancelar)
            <button type="button" wire:click="abrirCancelar({{ $compra->id }})"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                {{ __('Cancelar compra') }}
            </button>
        @endif
    </x-slot:footer>
</x-bcn-modal>
