{{-- Vista: Gestión de Cajas --}}

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Cajas</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Apertura, cierre y movimientos de caja</p>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($cajas as $caja)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-4 {{ $caja->estaAbierta() ? 'bg-green-50 dark:bg-green-900/20 border-b-4 border-green-500' : 'bg-gray-50 dark:bg-gray-700 border-b-4 border-gray-300 dark:border-gray-600' }}">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $caja->nombre }}</h3>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $caja->estaAbierta() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $caja->estaAbierta() ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ ucfirst($caja->tipo) }}</p>
                    </div>

                    <div class="px-6 py-4">
                        @if($caja->estaAbierta())
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Saldo Actual:</span>
                                    <span class="text-2xl font-bold text-gray-900 dark:text-white">$@precio($caja->saldo_actual)</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-300">Saldo Inicial:</span>
                                    <span class="font-medium dark:text-white">$@precio($caja->saldo_inicial)</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-300">Ingresos:</span>
                                    <span class="font-medium text-green-600">$@precio($caja->obtenerTotalIngresos())</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-300">Egresos:</span>
                                    <span class="font-medium text-red-600">$@precio($caja->obtenerTotalEgresos())</span>
                                </div>
                                <div class="pt-2 border-t dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                                    Abierta: {{ $caja->fecha_apertura->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Caja cerrada</p>
                                @if($caja->fecha_cierre)
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Último cierre: {{ $caja->fecha_cierre->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600 flex flex-col gap-2">
                        @if($caja->estaAbierta())
                            <button wire:click="abrirModalMovimiento({{ $caja->id }})" class="w-full px-3 py-2 border border-transparent rounded text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Registrar Movimiento</button>
                            <button wire:click="abrirModalCierre({{ $caja->id }})" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cerrar Caja</button>
                        @else
                            <button wire:click="abrirModalApertura({{ $caja->id }})" class="w-full px-3 py-2 border border-transparent rounded text-sm font-medium text-white bg-green-600 hover:bg-green-700">Abrir Caja</button>
                        @endif
                        <button wire:click="verMovimientos({{ $caja->id }})" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Ver Movimientos</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Modal Abrir Caja --}}
    @if($showAbrirModal && $cajaAbrir)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showAbrirModal', false)"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-green-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Abrir Caja: {{ $cajaAbrir->nombre }}</h3></div>
                    <div class="px-6 py-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Saldo Inicial</label>
                        <input wire:model="saldoInicial" type="number" step="0.01" min="0" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showAbrirModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="procesarApertura" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">Abrir Caja</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Cerrar Caja --}}
    @if($showCerrarModal && $cajaCerrar && $arqueo)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showCerrarModal', false)"></div>
                <div class="inline-block w-full max-w-2xl bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-red-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Cerrar Caja: {{ $cajaCerrar->nombre }}</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <h4 class="font-semibold text-blue-900 dark:text-blue-300 mb-3">Resumen de Arqueo</h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">Saldo Inicial:</span><span class="font-medium dark:text-white">$@precio($arqueo['saldo_inicial'])</span></div>
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">Total Ingresos:</span><span class="font-medium text-green-600">$@precio($arqueo['total_ingresos'])</span></div>
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">Total Egresos:</span><span class="font-medium text-red-600">$@precio($arqueo['total_egresos'])</span></div>
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">Movimientos:</span><span class="font-medium dark:text-white">{{ $arqueo['cantidad_movimientos'] }}</span></div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-blue-300 dark:border-blue-700">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-900 dark:text-white">Saldo Calculado:</span>
                                    <span class="text-2xl font-bold text-blue-900 dark:text-blue-400">$@precio($arqueo['saldo_calculado'])</span>
                                </div>
                                @if($arqueo['tiene_diferencia'])
                                    <div class="mt-2 p-2 bg-yellow-100 dark:bg-yellow-900/20 rounded">
                                        <p class="text-sm text-yellow-800 dark:text-yellow-300">Diferencia detectada: $@precio($arqueo['diferencia']) ({{ $arqueo['tipo_diferencia'] }})</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showCerrarModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="procesarCierre" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700">Confirmar Cierre</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Movimiento Manual --}}
    @if($showMovimientoModal && $cajaMovimiento)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showMovimientoModal', false)"></div>
                <div class="inline-block w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-blue-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Registrar Movimiento</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de Movimiento</label><select wire:model="tipoMovimiento" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto</label><input wire:model="montoMovimiento" type="number" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Concepto</label><input wire:model="conceptoMovimiento" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de Pago</label><select wire:model="formaPagoMovimiento" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"><option value="efectivo">Efectivo</option><option value="debito">Débito</option><option value="credito">Crédito</option></select></div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end gap-3">
                        <button wire:click="$set('showMovimientoModal', false)" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
                        <button wire:click="procesarMovimiento" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Registrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Ver Movimientos --}}
    @if($showMovimientosModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="cerrarMovimientos"></div>
                <div class="inline-block w-full max-w-4xl bg-white dark:bg-gray-800 rounded-lg shadow-xl">
                    <div class="bg-gray-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Movimientos de Caja</h3></div>
                    <div class="px-6 py-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Concepto</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($movimientos as $mov)
                                    <tr><td class="px-3 py-2 text-sm dark:text-gray-300">{{ $mov->created_at->format('d/m/Y H:i') }}</td><td class="px-3 py-2"><span class="inline-flex px-2 py-1 text-xs rounded-full {{ $mov->tipo_movimiento === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ ucfirst($mov->tipo_movimiento) }}</span></td><td class="px-3 py-2 text-sm dark:text-gray-300">{{ $mov->concepto }}</td><td class="px-3 py-2 text-sm text-right font-medium dark:text-white">$@precio($mov->monto)</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No hay movimientos</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if($movimientos->hasPages())<div class="mt-4">{{ $movimientos->links() }}</div>@endif
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 border-t dark:border-gray-600 flex justify-end"><button wire:click="cerrarMovimientos" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">Cerrar</button></div>
                </div>
            </div>
        </div>
    @endif
</div>
