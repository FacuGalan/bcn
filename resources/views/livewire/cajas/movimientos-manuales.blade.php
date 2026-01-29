<div class="py-4 px-4 sm:px-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Movimientos Manuales</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Transferencias, ingresos y egresos manuales de caja
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($cajaActual)
            <div class="flex items-center gap-3 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Caja Activa</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $cajaActual->nombre }}</p>
                </div>
                <div class="h-10 w-px bg-gray-200 dark:bg-gray-700"></div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Saldo</p>
                    <p class="font-semibold text-green-600 dark:text-green-400">${{ number_format($cajaActual->saldo_actual, 2, ',', '.') }}</p>
                </div>
            </div>
            @endif
            <a href="{{ route('cajas.historial-turnos') }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Historial
            </a>
            <a href="{{ route('cajas.turno-actual') }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                </svg>
                Turno Actual
            </a>
        </div>
    </div>

    @if(!$cajaActual)
        {{-- Sin caja seleccionada --}}
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl shadow-sm p-6 text-center">
            <svg class="w-12 h-12 mx-auto text-amber-500 dark:text-amber-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h3 class="text-lg font-medium text-amber-800 dark:text-amber-200">Sin Caja Operativa</h3>
            <p class="mt-1 text-sm text-amber-600 dark:text-amber-300">
                Seleccione una caja con turno abierto para realizar movimientos manuales.
            </p>
        </div>
    @else
        {{-- Tabs --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            {{-- Tab Headers --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px">
                    <button wire:click="cambiarTab('transferencia')"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $tabActivo === 'transferencia' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Transferencia
                    </button>
                    <button wire:click="cambiarTab('ingreso')"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $tabActivo === 'ingreso' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Ingreso
                    </button>
                    <button wire:click="cambiarTab('egreso')"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors {{ $tabActivo === 'egreso' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                        </svg>
                        Egreso
                    </button>
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="p-6">
                {{-- TRANSFERENCIA --}}
                @if($tabActivo === 'transferencia')
                <div class="space-y-4">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-3">
                        <p class="text-sm text-indigo-700 dark:text-indigo-300 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Transfiere efectivo desde <strong class="mx-1">{{ $cajaActual->nombre }}</strong> hacia otra caja
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Caja Destino --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Caja Destino</label>
                            <select wire:model="transferencia.caja_destino_id"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Seleccionar caja...</option>
                                @foreach($cajasDisponibles as $caja)
                                    @if($caja->id !== $cajaActualId)
                                    <option value="{{ $caja->id }}">{{ $caja->nombre }} (Saldo: ${{ number_format($caja->saldo_actual, 2, ',', '.') }})</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('transferencia.caja_destino_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Monto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" wire:model="transferencia.monto" step="0.01" min="0"
                                       class="w-full pl-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="0.00">
                            </div>
                            @error('transferencia.monto') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Motivo --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo</label>
                            <input type="text" wire:model="transferencia.motivo"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="Ej: Cambio de efectivo, refuerzo de caja...">
                            @error('transferencia.motivo') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button wire:click="confirmarTransferencia"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Transferir
                        </button>
                    </div>
                </div>
                @endif

                {{-- INGRESO --}}
                @if($tabActivo === 'ingreso')
                <div class="space-y-4">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                        <p class="text-sm text-green-700 dark:text-green-300 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Registra un ingreso de efectivo a <strong class="mx-1">{{ $cajaActual->nombre }}</strong>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Origen --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Origen</label>
                            <select wire:model="ingreso.origen"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500">
                                <option value="tesoreria" {{ !$tesoreriaActiva ? 'disabled' : '' }}>
                                    Tesoreria {{ $tesoreriaActiva ? '(Saldo: $' . number_format($tesoreria->saldo_actual, 2, ',', '.') . ')' : '(No activa)' }}
                                </option>
                                <option value="otro">Otro origen (sin afectar tesoreria)</option>
                            </select>
                            @error('ingreso.origen') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Monto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" wire:model="ingreso.monto" step="0.01" min="0"
                                       class="w-full pl-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500"
                                       placeholder="0.00">
                            </div>
                            @error('ingreso.monto') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Motivo --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo</label>
                            <input type="text" wire:model="ingreso.motivo"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-green-500 focus:border-green-500"
                                   placeholder="Ej: Fondo adicional, ajuste de caja...">
                            @error('ingreso.motivo') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button wire:click="confirmarIngreso"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Registrar Ingreso
                        </button>
                    </div>
                </div>
                @endif

                {{-- EGRESO --}}
                @if($tabActivo === 'egreso')
                <div class="space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                        <p class="text-sm text-red-700 dark:text-red-300 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Registra un egreso/retiro de efectivo de <strong class="mx-1">{{ $cajaActual->nombre }}</strong>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Destino --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destino</label>
                            <select wire:model="egreso.destino"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500">
                                <option value="tesoreria" {{ !$tesoreriaActiva ? 'disabled' : '' }}>
                                    Tesoreria {{ !$tesoreriaActiva ? '(No activa)' : '' }}
                                </option>
                                <option value="otro">Otro destino (sin afectar tesoreria)</option>
                            </select>
                            @error('egreso.destino') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- Monto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" wire:model="egreso.monto" step="0.01" min="0"
                                       class="w-full pl-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                                       placeholder="0.00">
                            </div>
                            @error('egreso.monto') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            @if($cajaActual)
                            <p class="mt-1 text-xs text-gray-500">Disponible: ${{ number_format($cajaActual->saldo_actual, 2, ',', '.') }}</p>
                            @endif
                        </div>

                        {{-- Motivo --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo</label>
                            <input type="text" wire:model="egreso.motivo"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                                   placeholder="Ej: Retiro parcial, pago a proveedor...">
                            @error('egreso.motivo') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button wire:click="confirmarEgreso"
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                            Registrar Egreso
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Historial --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Movimientos Recientes --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-white">Movimientos Manuales Recientes</h3>
                </div>
                <div class="p-4">
                    @if(count($movimientosRecientes) > 0)
                    <div class="space-y-2">
                        @foreach($movimientosRecientes as $mov)
                        <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full {{ $mov['tipo'] === 'ingreso' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30' }} flex items-center justify-center">
                                    @if($mov['tipo'] === 'ingreso')
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                    </svg>
                                    @else
                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                                    </svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[200px]">{{ $mov['concepto'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $mov['fecha'] }} - {{ $mov['usuario'] }}</p>
                                </div>
                            </div>
                            <span class="font-medium {{ $mov['tipo'] === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $mov['tipo'] === 'ingreso' ? '+' : '-' }}${{ number_format($mov['monto'], 2, ',', '.') }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-center text-gray-500 dark:text-gray-400 py-4">No hay movimientos manuales recientes</p>
                    @endif
                </div>
            </div>

            {{-- Transferencias Recientes --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-white">Transferencias Recientes</h3>
                </div>
                <div class="p-4">
                    @if(count($transferenciasRecientes) > 0)
                    <div class="space-y-2">
                        @foreach($transferenciasRecientes as $trans)
                        <div class="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full {{ $trans['tipo'] === 'entrada' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-indigo-100 dark:bg-indigo-900/30' }} flex items-center justify-center">
                                    @if($trans['tipo'] === 'entrada')
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                                    </svg>
                                    @else
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                                    </svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $trans['tipo'] === 'entrada' ? 'Desde' : 'Hacia' }} {{ $trans['caja_relacionada'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $trans['fecha'] }} - {{ $trans['motivo'] ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium {{ $trans['tipo'] === 'entrada' ? 'text-green-600 dark:text-green-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                                    {{ $trans['tipo'] === 'entrada' ? '+' : '-' }}${{ number_format($trans['monto'], 2, ',', '.') }}
                                </span>
                                <span class="block text-xs px-1.5 py-0.5 rounded {{ $trans['estado'] === 'completada' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ ucfirst($trans['estado']) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-center text-gray-500 dark:text-gray-400 py-4">No hay transferencias recientes</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Confirmacion --}}
    @if($showConfirmModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full {{ $accionPendiente === 'transferencia' ? 'bg-indigo-100 dark:bg-indigo-900/30' : ($accionPendiente === 'ingreso' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30') }} sm:mx-0 sm:h-10 sm:w-10">
                            @if($accionPendiente === 'transferencia')
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            @elseif($accionPendiente === 'ingreso')
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            @else
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                            @endif
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                Confirmar {{ $accionPendiente === 'transferencia' ? 'Transferencia' : ($accionPendiente === 'ingreso' ? 'Ingreso' : 'Egreso') }}
                            </h3>
                            <div class="mt-4 space-y-2">
                                @if($accionPendiente === 'transferencia')
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Desde:</span> {{ $datosPendientes['caja_origen'] ?? '' }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Hacia:</span> {{ $datosPendientes['caja_destino'] ?? '' }}
                                </p>
                                @else
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Caja:</span> {{ $datosPendientes['caja'] ?? '' }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">{{ $accionPendiente === 'ingreso' ? 'Origen' : 'Destino' }}:</span> {{ $datosPendientes[$accionPendiente === 'ingreso' ? 'origen' : 'destino'] ?? '' }}
                                </p>
                                @endif
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Monto:</span>
                                    <span class="text-lg font-bold {{ $accionPendiente === 'ingreso' ? 'text-green-600' : ($accionPendiente === 'egreso' ? 'text-red-600' : 'text-indigo-600') }}">
                                        ${{ number_format($datosPendientes['monto'] ?? 0, 2, ',', '.') }}
                                    </span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Motivo:</span> {{ $datosPendientes['motivo'] ?? '' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" wire:click="ejecutarAccion"
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 {{ $accionPendiente === 'transferencia' ? 'bg-indigo-600 hover:bg-indigo-700' : ($accionPendiente === 'ingreso' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700') }} text-base font-medium text-white focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Confirmar
                    </button>
                    <button type="button" wire:click="cancelarAccion"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
