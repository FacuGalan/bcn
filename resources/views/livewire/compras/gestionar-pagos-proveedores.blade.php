<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Pagos a Proveedores') }}</h2>
            <p class="hidden sm:block mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Deuda con proveedores de la sucursal activa, órdenes de pago y anticipos') }}</p>
        </div>

        <!-- Filtros -->
        <div class="mb-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar proveedor...') }}"
                   class="w-full sm:w-96 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
        </div>

        <!-- Cards móvil -->
        <div class="sm:hidden space-y-3">
            @forelse($proveedores as $proveedor)
                @php($saldo = $saldos[$proveedor->id] ?? ['saldo_deuda' => 0, 'saldo_favor' => 0])
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="flex justify-between items-start">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $proveedor->nombre }}</p>
                        <div class="text-right">
                            <p class="text-sm font-bold {{ $saldo['saldo_deuda'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">$@precio($saldo['saldo_deuda'])</p>
                            @if($saldo['saldo_favor'] > 0)
                                <p class="text-xs text-green-600 dark:text-green-400">{{ __('A favor') }}: $@precio($saldo['saldo_favor'])</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2 justify-end">
                        <button wire:click="verExtracto({{ $proveedor->id }})" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ __('Cuenta') }}</button>
                        <button wire:click="abrirPago({{ $proveedor->id }}, true)" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ __('Anticipo') }}</button>
                        <button wire:click="abrirPago({{ $proveedor->id }})" class="px-3 py-1.5 text-xs rounded-md bg-bcn-primary text-white">{{ __('Pagar') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">{{ __('Sin proveedores con cuenta corriente o deuda en esta sucursal') }}</p>
            @endforelse
        </div>

        <!-- Tabla desktop -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Proveedor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('CUIT') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Deuda (sucursal)') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('A favor') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($proveedores as $proveedor)
                            @php($saldo = $saldos[$proveedor->id] ?? ['saldo_deuda' => 0, 'saldo_favor' => 0])
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $proveedor->nombre }}</p>
                                    @if($proveedor->dias_pago)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Paga a :dias días', ['dias' => $proveedor->dias_pago]) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $proveedor->cuit ?: '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold {{ $saldo['saldo_deuda'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">$@precio($saldo['saldo_deuda'])</td>
                                <td class="px-4 py-3 text-right text-sm {{ $saldo['saldo_favor'] > 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">$@precio($saldo['saldo_favor'])</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button wire:click="verExtracto({{ $proveedor->id }})" class="inline-flex items-center px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 mr-1">{{ __('Estado de cuenta') }}</button>
                                    <button wire:click="abrirPago({{ $proveedor->id }}, true)" class="inline-flex items-center px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 mr-1">{{ __('Anticipo') }}</button>
                                    <button wire:click="abrirPago({{ $proveedor->id }})" class="inline-flex items-center px-3 py-1.5 text-xs rounded-md bg-bcn-primary text-white hover:bg-opacity-90">{{ __('Pagar') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Sin proveedores con cuenta corriente o deuda en esta sucursal') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $proveedores->links() }}</div>
    </div>

    {{-- Modal de pago / anticipo --}}
    @if($showPagoModal && $proveedorPago)
        <x-bcn-modal :title="($esAnticipo ? __('Anticipo a') : __('Pagar a')).' '.$proveedorPago->nombre" color="bg-bcn-primary" maxWidth="3xl" onClose="$set('showPagoModal', false)">
            <x-slot:body>
                <div class="space-y-4">
                    @if(! $esAnticipo)
                        {{-- Compras pendientes --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Compras pendientes') }}</h4>
                                <div class="flex items-center gap-2">
                                    <input type="text" wire:model="montoADistribuir" placeholder="{{ __('Monto') }}"
                                           class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                    <button wire:click="distribuirFifo" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">{{ __('Distribuir (FIFO)') }}</button>
                                </div>
                            </div>
                            <div class="overflow-x-auto max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Compra') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Vencimiento') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('A aplicar') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @forelse($comprasPendientes as $compra)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                                                    {{ $compra['numero'] }}
                                                    @if($compra['numero_proveedor']) <span class="text-xs text-gray-500">({{ $compra['numero_proveedor'] }})</span> @endif
                                                </td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {{ $compra['fecha_vencimiento'] ? \Carbon\Carbon::parse($compra['fecha_vencimiento'])->format('d/m/Y') : '—' }}
                                                    @if($compra['dias_vencida'] > 0)
                                                        <span class="ml-1 inline-flex px-1.5 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __(':dias d vencida', ['dias' => $compra['dias_vencida']]) }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">$@precio($compra['saldo_pendiente'])</td>
                                                <td class="px-3 py-2 text-right">
                                                    <input type="text" wire:model="montosAplicar.{{ $compra['compra_id'] }}"
                                                           class="w-24 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('Sin compras pendientes en esta sucursal') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($saldoFavorDisponible > 0)
                            <div class="flex items-center gap-3 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                                <p class="text-sm text-green-700 dark:text-green-300 flex-1">{{ __('Saldo a favor disponible') }}: <strong>$@precio($saldoFavorDisponible)</strong></p>
                                <label class="text-sm text-green-700 dark:text-green-300">{{ __('Usar') }}</label>
                                <input type="text" wire:model="saldoFavorUsado" class="w-28 rounded-md border-green-300 dark:border-green-700 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                            </div>
                        @endif
                    @endif

                    {{-- Desglose de formas de pago --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Formas de pago') }}</h4>
                            <button wire:click="agregarRenglonPago" class="text-xs text-bcn-primary hover:underline">{{ __('+ Agregar renglón') }}</button>
                        </div>
                        <div class="space-y-2">
                            @foreach($pagos as $index => $pago)
                                <div class="flex flex-wrap items-center gap-2">
                                    <select wire:model="pagos.{{ $index }}.forma_pago_id" class="flex-1 min-w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                        <option value="">{{ __('Forma de pago...') }}</option>
                                        @foreach($formasPago as $fp)
                                            <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="pagos.{{ $index }}.monto" placeholder="{{ __('Monto') }}"
                                           class="w-28 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                    @if($this->puedePagarAvanzado())
                                        <select wire:model.live="pagos.{{ $index }}.origen" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                            <option value="caja">{{ __('Caja') }}</option>
                                            <option value="tesoreria">{{ __('Tesorería') }}</option>
                                            <option value="cuenta_empresa">{{ __('Cuenta de empresa') }}</option>
                                        </select>
                                        @if(($pago['origen'] ?? 'caja') === 'caja')
                                            <select wire:model="pagos.{{ $index }}.caja_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                                @foreach($cajasDisponibles as $caja)
                                                    <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($pago['origen'] ?? '') === 'cuenta_empresa')
                                            <select wire:model="pagos.{{ $index }}.cuenta_empresa_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                                                <option value="">{{ __('Cuenta...') }}</option>
                                                @foreach($cuentasEmpresa as $cuenta)
                                                    <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif
                                    @if(count($pagos) > 1)
                                        <button wire:click="quitarRenglonPago({{ $index }})" class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if(! $this->puedePagarAvanzado())
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('El pago sale de la caja activa (para otros orígenes se requiere el permiso de pago avanzado)') }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Observaciones') }}</label>
                        <input type="text" wire:model="observaciones" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring-bcn-primary">
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">{{ __('Cancelar') }}</button>
                <button type="button" wire:click="confirmarPago" wire:loading.attr="disabled" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    {{ $esAnticipo ? __('Registrar anticipo') : __('Registrar pago') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal estado de cuenta + OPs --}}
    @if($showExtractoModal && $proveedorExtracto)
        <x-bcn-modal :title="__('Estado de cuenta — :nombre', ['nombre' => $proveedorExtracto->nombre])" color="bg-bcn-primary" maxWidth="4xl" onClose="$set('showExtractoModal', false)">
            <x-slot:body>
                <div class="flex gap-4 mb-4">
                    <div class="flex-1 rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3">
                        <p class="text-xs text-red-700 dark:text-red-300">{{ __('Deuda (sucursal activa)') }}</p>
                        <p class="text-lg font-bold text-red-700 dark:text-red-300">$@precio($saldosExtracto['saldo_deuda'] ?? 0)</p>
                    </div>
                    <div class="flex-1 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                        <p class="text-xs text-green-700 dark:text-green-300">{{ __('Saldo a favor nuestro') }}</p>
                        <p class="text-lg font-bold text-green-700 dark:text-green-300">$@precio($saldosExtracto['saldo_favor'] ?? 0)</p>
                    </div>
                </div>

                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Movimientos') }}</h4>
                <div class="overflow-x-auto max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md mb-4">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Debe') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Haber') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($extracto as $mov)
                                <tr class="{{ $mov['es_anulacion'] ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $mov['concepto'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $mov['debe'] > 0 ? '$'.number_format($mov['debe'], 2, ',', '.') : '' }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $mov['haber'] > 0 ? '$'.number_format($mov['haber'], 2, ',', '.') : '' }}</td>
                                    <td class="px-3 py-2 text-right font-medium {{ $mov['saldo_deuda'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">$@precio($mov['saldo_deuda'])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('Sin movimientos en esta sucursal') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Órdenes de pago') }}</h4>
                <div class="overflow-x-auto max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Número') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Monto') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($opsProveedor as $op)
                                <tr class="{{ $op->estaAnulado() ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $op->numero }} @if($op->esAnticipo())<span class="text-xs text-gray-500">({{ __('anticipo') }})</span>@endif</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $op->fecha->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">$@precio($op->monto_total + $op->saldo_favor_usado)</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs rounded-full {{ $op->estaAnulado() ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                                            {{ $op->estaAnulado() ? __('Anulada') : __('Activa') }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if(! $op->estaAnulado())
                                            <button wire:click="abrirAnular({{ $op->id }})" class="text-xs text-red-500 hover:text-red-700">{{ __('Anular') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400">{{ __('Sin órdenes de pago') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:w-auto sm:text-sm">{{ __('Cerrar') }}</button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal anular OP --}}
    @if($showAnularModal)
        <x-bcn-modal :title="__('Anular orden de pago')" color="bg-red-600" maxWidth="md" onClose="$set('showAnularModal', false)" submit="confirmarAnular">
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Se contraasienta el ledger y la plata vuelve a su origen (caja, Tesorería o cuenta). Los saldos de las compras aplicadas se restauran.') }}</p>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Motivo') }} *</label>
                <input type="text" wire:model="motivoAnulacion" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                @error('motivoAnulacion') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">{{ __('Cancelar') }}</button>
                <button type="button" wire:click="confirmarAnular" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">{{ __('Anular') }}</button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
