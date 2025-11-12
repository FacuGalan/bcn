{{-- Vista: Gestión de Cajas --}}

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Cajas</h1>
        <p class="text-sm text-gray-600 mt-1">Apertura, cierre y movimientos de caja</p>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($cajas as $caja)
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-4 {{ $caja->estaAbierta() ? 'bg-green-50 border-b-4 border-green-500' : 'bg-gray-50 border-b-4 border-gray-300' }}">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900">{{ $caja->nombre }}</h3>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $caja->estaAbierta() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $caja->estaAbierta() ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">{{ ucfirst($caja->tipo) }}</p>
                    </div>

                    <div class="px-6 py-4">
                        @if($caja->estaAbierta())
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Saldo Actual:</span>
                                    <span class="text-2xl font-bold text-gray-900">${{ number_format($caja->saldo_actual, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Saldo Inicial:</span>
                                    <span class="font-medium">${{ number_format($caja->saldo_inicial, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Ingresos:</span>
                                    <span class="font-medium text-green-600">${{ number_format($caja->obtenerTotalIngresos(), 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Egresos:</span>
                                    <span class="font-medium text-red-600">${{ number_format($caja->obtenerTotalEgresos(), 2) }}</span>
                                </div>
                                <div class="pt-2 border-t text-xs text-gray-500">
                                    Abierta: {{ $caja->fecha_apertura->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <p class="text-sm text-gray-500">Caja cerrada</p>
                                @if($caja->fecha_cierre)
                                    <p class="text-xs text-gray-400 mt-1">Último cierre: {{ $caja->fecha_cierre->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="px-6 py-3 bg-gray-50 border-t flex flex-col gap-2">
                        @if($caja->estaAbierta())
                            <button wire:click="abrirModalMovimiento({{ $caja->id }})" class="w-full px-3 py-2 border border-transparent rounded text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Registrar Movimiento</button>
                            <button wire:click="abrirModalCierre({{ $caja->id }})" class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cerrar Caja</button>
                        @else
                            <button wire:click="abrirModalApertura({{ $caja->id }})" class="w-full px-3 py-2 border border-transparent rounded text-sm font-medium text-white bg-green-600 hover:bg-green-700">Abrir Caja</button>
                        @endif
                        <button wire:click="verMovimientos({{ $caja->id }})" class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Ver Movimientos</button>
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
                <div class="inline-block w-full max-w-md bg-white rounded-lg shadow-xl">
                    <div class="bg-green-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Abrir Caja: {{ $cajaAbrir->nombre }}</h3></div>
                    <div class="px-6 py-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Saldo Inicial</label>
                        <input wire:model="saldoInicial" type="number" step="0.01" min="0" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t flex justify-end gap-3">
                        <button wire:click="$set('showAbrirModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
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
                <div class="inline-block w-full max-w-2xl bg-white rounded-lg shadow-xl">
                    <div class="bg-red-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Cerrar Caja: {{ $cajaCerrar->nombre }}</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-semibold text-blue-900 mb-3">Resumen de Arqueo</h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="flex justify-between"><span class="text-gray-600">Saldo Inicial:</span><span class="font-medium">${{ number_format($arqueo['saldo_inicial'], 2) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Total Ingresos:</span><span class="font-medium text-green-600">${{ number_format($arqueo['total_ingresos'], 2) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Total Egresos:</span><span class="font-medium text-red-600">${{ number_format($arqueo['total_egresos'], 2) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Movimientos:</span><span class="font-medium">{{ $arqueo['cantidad_movimientos'] }}</span></div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-blue-300">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-900">Saldo Calculado:</span>
                                    <span class="text-2xl font-bold text-blue-900">${{ number_format($arqueo['saldo_calculado'], 2) }}</span>
                                </div>
                                @if($arqueo['tiene_diferencia'])
                                    <div class="mt-2 p-2 bg-yellow-100 rounded">
                                        <p class="text-sm text-yellow-800">Diferencia detectada: ${{ number_format($arqueo['diferencia'], 2) }} ({{ $arqueo['tipo_diferencia'] }})</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t flex justify-end gap-3">
                        <button wire:click="$set('showCerrarModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
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
                <div class="inline-block w-full max-w-md bg-white rounded-lg shadow-xl">
                    <div class="bg-blue-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Registrar Movimiento</h3></div>
                    <div class="px-6 py-4 space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700">Tipo de Movimiento</label><select wire:model="tipoMovimiento" class="mt-1 block w-full border-gray-300 rounded-md"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700">Monto</label><input wire:model="montoMovimiento" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium text-gray-700">Concepto</label><input wire:model="conceptoMovimiento" type="text" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                        <div><label class="block text-sm font-medium text-gray-700">Forma de Pago</label><select wire:model="formaPagoMovimiento" class="mt-1 block w-full border-gray-300 rounded-md"><option value="efectivo">Efectivo</option><option value="debito">Débito</option><option value="credito">Crédito</option></select></div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t flex justify-end gap-3">
                        <button wire:click="$set('showMovimientoModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
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
                <div class="inline-block w-full max-w-4xl bg-white rounded-lg shadow-xl">
                    <div class="bg-gray-600 px-6 py-4"><h3 class="text-lg font-medium text-white">Movimientos de Caja</h3></div>
                    <div class="px-6 py-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th><th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($movimientos as $mov)
                                    <tr><td class="px-3 py-2 text-sm">{{ $mov->created_at->format('d/m/Y H:i') }}</td><td class="px-3 py-2"><span class="inline-flex px-2 py-1 text-xs rounded-full {{ $mov->tipo_movimiento === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ ucfirst($mov->tipo_movimiento) }}</span></td><td class="px-3 py-2 text-sm">{{ $mov->concepto }}</td><td class="px-3 py-2 text-sm text-right font-medium">${{ number_format($mov->monto, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">No hay movimientos</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if($movimientos->hasPages())<div class="mt-4">{{ $movimientos->links() }}</div>@endif
                    </div>
                    <div class="bg-gray-50 px-6 py-3 border-t flex justify-end"><button wire:click="cerrarMovimientos" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cerrar</button></div>
                </div>
            </div>
        </div>
    @endif
</div>
