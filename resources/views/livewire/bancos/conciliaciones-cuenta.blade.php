<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        @php
            $estadoBadge = [
                'generando' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'pendiente_revision' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'aplicada' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'descartada' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                'error' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            ];
            $estadoLabel = [
                'generando' => __('Generando'),
                'pendiente_revision' => __('Pendiente de revisión'),
                'aplicada' => __('Aplicada'),
                'descartada' => __('Descartada'),
                'error' => __('Error'),
            ];
            $clasificacionLabel = [
                'matcheado' => __('Conciliado'),
                'solo_proveedor' => __('Solo en el proveedor'),
                'solo_sistema' => __('Solo en el sistema'),
                'ya_registrado' => __('Ya registrado'),
            ];
            $clasificacionBadge = [
                'matcheado' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'solo_proveedor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'solo_sistema' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'ya_registrado' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            ];
            $tipoLabel = [
                'cobro' => __('Cobro'),
                'comision' => __('Comisión'),
                'devolucion' => __('Devolución'),
                'contracargo' => __('Contracargo'),
                'retiro' => __('Retiro'),
                'retiro_cancelado' => __('Retiro cancelado'),
                'acreditacion' => __('Acreditación'),
                'ajuste_inicial' => __('Ajuste inicial'),
                'otro' => __('Otro'),
            ];
        @endphp

        @if($detalle)
            {{-- ==================== DETALLE DE CORRIDA ==================== --}}
            <div @if($detalle->estaGenerando()) wire:poll.5s @endif>
                {{-- Header --}}
                <div class="mb-4 sm:mb-6">
                    <div class="flex items-center gap-3">
                        <button wire:click="cerrarDetalle"
                            class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition ease-in-out duration-150"
                            title="{{ __('Volver') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </button>
                        <div class="flex-1">
                            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Conciliación') }} #{{ $detalle->id }}</h2>
                            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                                {{ $detalle->cuentaEmpresa?->nombre }} · {{ $detalle->desde->format('d/m/Y') }} – {{ $detalle->hasta->format('d/m/Y') }}
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $estadoBadge[$detalle->estado] ?? '' }}">
                            {{ $estadoLabel[$detalle->estado] ?? $detalle->estado }}
                        </span>
                    </div>
                </div>

                @if($detalle->estaGenerando())
                    {{-- Esperando el reporte del proveedor --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-bcn-primary animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ __('Generando reporte del proveedor...') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('El proveedor genera el reporte de movimientos de forma asíncrona. Esta pantalla se actualiza sola.') }}</p>
                    </div>
                @elseif($detalle->estado === 'error')
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ __('La conciliación falló') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $detalle->error_mensaje }}</p>
                    </div>
                @else
                    {{-- Resumen --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Movimientos conciliados') }}</p>
                            <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">{{ $detalle->total_matcheados }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Solo en el proveedor') }}</p>
                            <p class="mt-1 text-lg font-bold text-amber-600 dark:text-amber-400">{{ $detalle->total_solo_proveedor }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Solo en el sistema') }}</p>
                            <p class="mt-1 text-lg font-bold {{ $detalle->total_solo_sistema > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $detalle->total_solo_sistema }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ajustes propuestos') }}</p>
                            <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">
                                <span class="text-green-600 dark:text-green-400">+{{ number_format($detalle->monto_propuesto_ingresos, 2, ',', '.') }}</span>
                                <span class="mx-1 text-gray-400">/</span>
                                <span class="text-red-600 dark:text-red-400">-{{ number_format($detalle->monto_propuesto_egresos, 2, ',', '.') }}</span>
                            </p>
                        </div>
                    </div>

                    {{-- Filtro por clasificación + acciones --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                <div class="flex-1">
                                    <select wire:model.live="filtroClasificacion"
                                        class="w-full sm:w-72 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        <option value="">{{ __('Todas las clasificaciones') }}</option>
                                        @foreach($clasificacionLabel as $valor => $label)
                                            <option value="{{ $valor }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($detalle->esEditable() && $this->puedeAplicar)
                                    <div class="flex gap-3">
                                        <button wire:click="confirmarDescartar"
                                            class="inline-flex items-center justify-center px-4 py-2 border border-red-600 rounded-md font-semibold text-xs text-red-600 uppercase tracking-widest hover:bg-red-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                            <svg class="w-4 h-4 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            <span class="hidden sm:inline">{{ __('Descartar') }}</span>
                                        </button>
                                        <button wire:click="confirmarAplicar"
                                            class="inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                            <svg class="w-4 h-4 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <span class="hidden sm:inline">{{ __('Aplicar ajustes') }}</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Filas: Cards móvil --}}
                    <div class="sm:hidden space-y-3">
                        @forelse($filas as $fila)
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $tipoLabel[$fila->tipo] ?? $fila->tipo }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $fila->descripcion ?: ($fila->referencia ?: $fila->id_externo) }}</div>
                                        @if($fila->fecha)
                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $fila->fecha->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $clasificacionBadge[$fila->clasificacion] ?? '' }}">
                                        {{ $clasificacionLabel[$fila->clasificacion] ?? $fila->clasificacion }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-sm font-bold {{ $fila->monto_neto >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                                        $ {{ number_format($fila->monto_neto, 2, ',', '.') }}
                                    </p>
                                    @if($fila->esPropuesta() && $detalle->esEditable())
                                        <button wire:click="toggleAccionFila({{ $fila->id }})"
                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $fila->accion === 'generar_movimiento' ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                            title="{{ $fila->accion === 'generar_movimiento' ? __('Se generará el movimiento') : __('Ignorada') }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $fila->accion === 'generar_movimiento' ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    @elseif($fila->movimiento_cuenta_empresa_id)
                                        <span class="text-xs text-green-600 dark:text-green-400 font-medium">{{ __('Movimiento generado') }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                                <p class="text-sm">{{ __('Sin movimientos en el período') }}</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Filas: Tabla desktop --}}
                    <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-bcn-light dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Detalle') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Clasificación') }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Monto') }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acción') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($filas as $fila)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ $fila->accion === 'ignorar' ? 'opacity-60' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $tipoLabel[$fila->tipo] ?? $fila->tipo }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                <div class="max-w-xs truncate">{{ $fila->descripcion ?: '-' }}</div>
                                                @if($fila->referencia || $fila->id_externo)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $fila->referencia ?: $fila->id_externo }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $fila->fecha?->format('d/m/Y H:i') ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $clasificacionBadge[$fila->clasificacion] ?? '' }}">
                                                    {{ $clasificacionLabel[$fila->clasificacion] ?? $fila->clasificacion }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium {{ $fila->monto_neto >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                                                $ {{ number_format($fila->monto_neto, 2, ',', '.') }}
                                                @if($fila->tipo !== 'comision' && $fila->comision > 0)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Comisión') }}: {{ number_format($fila->comision, 2, ',', '.') }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                @if($fila->esPropuesta() && $detalle->esEditable())
                                                    <div class="flex items-center justify-end gap-2">
                                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $fila->accion === 'generar_movimiento' ? __('Generar movimiento') : __('Ignorar') }}</span>
                                                        <button wire:click="toggleAccionFila({{ $fila->id }})"
                                                            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $fila->accion === 'generar_movimiento' ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600' }}">
                                                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $fila->accion === 'generar_movimiento' ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                        </button>
                                                    </div>
                                                @elseif($fila->movimiento_cuenta_empresa_id)
                                                    <span class="text-xs text-green-600 dark:text-green-400 font-medium">{{ __('Movimiento generado') }}</span>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Sin movimientos en el período') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- ==================== LISTADO DE CORRIDAS ==================== --}}
            {{-- Header --}}
            <div class="mb-4 sm:mb-6">
                <div class="flex justify-between items-start gap-3 sm:gap-4">
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-3 sm:block">
                            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Conciliaciones') }}</h2>
                            {{-- Botón mobile --}}
                            <div class="sm:hidden">
                                <button wire:click="abrirNueva"
                                    class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                    title="{{ __('Nueva conciliación') }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Concilie el saldo de sus cuentas contra los movimientos reales del proveedor de pago') }}</p>
                    </div>
                    {{-- Botón desktop --}}
                    <div class="hidden sm:flex gap-3">
                        <button wire:click="abrirNueva"
                            class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            {{ __('Nueva conciliación') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <select wire:model.live="filtroCuenta"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Todas las cuentas') }}</option>
                                @foreach($this->cuentasConciliables as $cuenta)
                                    <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <select wire:model.live="filtroEstado"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Todos los estados') }}</option>
                                @foreach($estadoLabel as $valor => $label)
                                    <option value="{{ $valor }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cards móvil --}}
            <div class="sm:hidden space-y-3">
                @forelse($corridas as $corrida)
                    <div wire:click="verDetalle({{ $corrida->id }})" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 cursor-pointer">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $corrida->cuentaEmpresa?->nombre }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $corrida->desde->format('d/m/Y') }} – {{ $corrida->hasta->format('d/m/Y') }}</div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $estadoBadge[$corrida->estado] ?? '' }}">
                                {{ $estadoLabel[$corrida->estado] ?? $corrida->estado }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $corrida->origen === 'programada' ? __('Programada') : __('Manual') }}</span>
                            <span>·</span>
                            <span>{{ $corrida->total_matcheados }} {{ __('conciliados') }}</span>
                            <span>·</span>
                            <span>{{ $corrida->total_solo_proveedor }} {{ __('por revisar') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" /></svg>
                        <p class="mt-2 text-sm">{{ __('Todavía no hay conciliaciones') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- Tabla desktop --}}
            <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-bcn-light dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Cuenta') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Período') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Origen') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Resultado') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($corridas as $corrida)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $corrida->cuentaEmpresa?->nombre }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $corrida->desde->format('d/m/Y') }} – {{ $corrida->hasta->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $corrida->origen === 'programada' ? __('Programada') : __('Manual') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $estadoBadge[$corrida->estado] ?? '' }}">
                                            {{ $estadoLabel[$corrida->estado] ?? $corrida->estado }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        @if($corrida->estaGenerando())
                                            -
                                        @else
                                            {{ $corrida->total_matcheados }} ✓ · {{ $corrida->total_solo_proveedor }} ⚠
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <button wire:click="verDetalle({{ $corrida->id }})"
                                            class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white transition-colors duration-150">
                                            <svg class="w-4 h-4 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            <span class="hidden sm:inline">{{ __('Ver') }}</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Todavía no hay conciliaciones') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($corridas->hasPages())
                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                        {{ $corridas->links() }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Modal: Nueva conciliación --}}
        @if($showModalNueva)
        <x-bcn-modal
            :title="__('Nueva conciliación')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cancelarNueva"
            submit="crearCorrida"
        >
            <x-slot:body>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cuenta') }} *</label>
                        <select wire:model="nuevaCuentaId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($this->cuentasConciliables as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                        @error('nuevaCuentaId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Solo se pueden conciliar cuentas vinculadas a una integración de pago en producción') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Desde') }} *</label>
                        <input wire:model="nuevaDesde" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                        @error('nuevaDesde') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Hasta') }} *</label>
                        <input wire:model="nuevaHasta" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                        @error('nuevaHasta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button wire:click="cancelarNueva" type="button"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="crearCorrida" type="button"
                    class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Conciliar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
        @endif

        {{-- Modal: Aplicar --}}
        @if($showConfirmAplicar)
        <x-bcn-modal
            :title="__('Aplicar ajustes')"
            color="bg-green-600"
            maxWidth="lg"
            onClose="$set('showConfirmAplicar', false)"
            submit="aplicar"
        >
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Se registrarán los movimientos propuestos en el saldo de la cuenta') }}:
                </p>
                @if($detalle)
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="rounded-md bg-green-50 dark:bg-green-900/20 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ingresos') }}</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">+ $ {{ number_format($detalle->monto_propuesto_ingresos, 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-md bg-red-50 dark:bg-red-900/20 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Egresos') }}</p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400">- $ {{ number_format($detalle->monto_propuesto_egresos, 2, ',', '.') }}</p>
                        </div>
                    </div>
                @endif
                @if($this->esPrimeraConciliacion)
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Saldo real en el proveedor al inicio del período') }} ({{ __('opcional') }})</label>
                        <input wire:model="saldoInicialProveedor" type="text" inputmode="decimal" placeholder="0,00"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Si lo completa, se generará un ajuste inicial por la diferencia con el saldo del sistema a esa fecha') }}</p>
                    </div>
                @endif
            </x-slot:body>
            <x-slot:footer>
                <button wire:click="$set('showConfirmAplicar', false)" type="button"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="aplicar" type="button" wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Aplicar ajustes') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
        @endif

        {{-- Modal: Descartar --}}
        @if($showConfirmDescartar)
        <x-bcn-modal
            :title="__('Descartar conciliación')"
            color="bg-red-600"
            maxWidth="md"
            onClose="$set('showConfirmDescartar', false)"
            submit="descartar"
        >
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('No se registrará ningún movimiento. Podrá volver a conciliar el mismo período más adelante.') }}
                </p>
            </x-slot:body>
            <x-slot:footer>
                <button wire:click="$set('showConfirmDescartar', false)" type="button"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="descartar" type="button"
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Descartar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
        @endif
    </div>
</div>
