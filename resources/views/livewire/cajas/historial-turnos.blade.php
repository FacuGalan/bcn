<div class="py-4 px-4 sm:px-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Historial de Turnos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Consulta y detalle de cierres de turno anteriores
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('cajas.movimientos-manuales') }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                Movimientos
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

    {{-- Filtros - Plegable en móvil --}}
    <div x-data="{ filtrosAbiertos: window.innerWidth >= 640 }"
         x-init="window.addEventListener('resize', () => { if(window.innerWidth >= 640) filtrosAbiertos = true })"
         class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
        {{-- Header de filtros (solo visible en móvil) --}}
        <button @click="filtrosAbiertos = !filtrosAbiertos"
                class="w-full sm:hidden flex items-center justify-between px-4 py-3 text-left">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="font-medium text-gray-700 dark:text-gray-300">Filtros</span>
                @if($filtroTipo || $filtroCajaId)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                    Activos
                </span>
                @endif
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': filtrosAbiertos }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Contenido de filtros --}}
        <div x-show="filtrosAbiertos" x-collapse class="px-4 pb-4 pt-2 sm:py-4 border-t sm:border-t-0 border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
                {{-- Fecha Desde --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Desde</label>
                    <input type="date" wire:model.live="filtroFechaDesde"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-bcn-primary focus:border-bcn-primary">
                </div>

                {{-- Fecha Hasta --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
                    <input type="date" wire:model.live="filtroFechaHasta"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-bcn-primary focus:border-bcn-primary">
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo</label>
                    <select wire:model.live="filtroTipo"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-bcn-primary focus:border-bcn-primary">
                        <option value="">Todos</option>
                        <option value="individual">Individual</option>
                        <option value="grupo">Grupal</option>
                    </select>
                </div>

                {{-- Caja --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Caja</label>
                    <select wire:model.live="filtroCajaId"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-bcn-primary focus:border-bcn-primary">
                        <option value="">Todas</option>
                        @foreach($cajasDisponibles as $caja)
                        <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Botón limpiar --}}
                <div class="col-span-2 sm:col-span-1 flex items-end">
                    <button wire:click="limpiarFiltros"
                            class="w-full px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Resumen del Periodo --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Resumen del Periodo</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ $filtroFechaDesde ? \Carbon\Carbon::parse($filtroFechaDesde)->format('d/m/Y') : '' }}
                -
                {{ $filtroFechaHasta ? \Carbon\Carbon::parse($filtroFechaHasta)->format('d/m/Y') : '' }}
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
            <div class="text-center p-2 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">{{ $resumen['total_cierres'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Cierres</p>
            </div>
            <div class="text-center p-2 sm:p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xl sm:text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($resumen['total_ingresos'], 0, ',', '.') }}</p>
                <p class="text-xs text-green-600 dark:text-green-400">Ingresos</p>
            </div>
            <div class="text-center p-2 sm:p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400">${{ number_format($resumen['total_egresos'], 0, ',', '.') }}</p>
                <p class="text-xs text-red-600 dark:text-red-400">Egresos</p>
            </div>
            <div class="text-center p-2 sm:p-3 {{ $resumen['total_diferencia'] == 0 ? 'bg-gray-50 dark:bg-gray-700/50' : ($resumen['total_diferencia'] > 0 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-red-50 dark:bg-red-900/20') }} rounded-lg">
                <p class="text-xl sm:text-2xl font-bold {{ $resumen['total_diferencia'] == 0 ? 'text-gray-600 dark:text-gray-400' : ($resumen['total_diferencia'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                    {{ $resumen['total_diferencia'] >= 0 ? '+' : '' }}${{ number_format($resumen['total_diferencia'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Diferencia</p>
            </div>
            <div class="text-center p-2 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg col-span-2 sm:col-span-1">
                <p class="text-xl sm:text-2xl font-bold {{ $resumen['cierres_con_diferencia'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400' }}">{{ $resumen['cierres_con_diferencia'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Con Diferencia</p>
            </div>
        </div>
    </div>

    {{-- Listado de Cierres --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- Header de la tabla --}}
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="hidden lg:grid lg:grid-cols-12 gap-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                <div class="col-span-2">Fecha/Hora</div>
                <div class="col-span-3">Turno</div>
                <div class="col-span-1 text-right">Inicial</div>
                <div class="col-span-1 text-right">Ingresos</div>
                <div class="col-span-1 text-right">Egresos</div>
                <div class="col-span-1 text-right">Final</div>
                <div class="col-span-1 text-right">Diferencia</div>
                <div class="col-span-2 text-center">Acciones</div>
            </div>
            <div class="lg:hidden text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ $cierres->total() }} cierres encontrados
            </div>
        </div>

        {{-- Contenido --}}
        @if($cierres->isEmpty())
        <div class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">Sin cierres de turno</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No se encontraron cierres en el periodo seleccionado.</p>
        </div>
        @else
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($cierres as $cierre)
            @php
                $tieneDiferencia = $cierre->total_diferencia != 0;
                $esFaltante = $cierre->total_diferencia < 0;
                $cantidadCajas = $cierre->detalleCajas->count();
            @endphp
            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                {{-- Vista móvil --}}
                <div class="lg:hidden space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ $cierre->fecha_cierre->format('d/m/Y H:i') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                @if($cierre->esGrupal())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Grupo</span>
                                    {{ $cierre->grupoCierre?->nombre ?? 'Grupo' }}
                                @else
                                    {{ $cierre->detalleCajas->first()?->caja_nombre ?? 'Caja' }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $cierre->usuario?->name }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($cierre->total_saldo_final, 0, ',', '.') }}</p>
                            @if($tieneDiferencia)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $esFaltante ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                {{ $esFaltante ? '' : '+' }}${{ number_format($cierre->total_diferencia, 0, ',', '.') }}
                            </span>
                            @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">OK</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="verDetalle({{ $cierre->id }})"
                                class="flex-1 px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40">
                            Ver Detalle
                        </button>
                        <button wire:click="reimprimir({{ $cierre->id }})"
                                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Vista desktop --}}
                <div class="hidden lg:grid lg:grid-cols-12 gap-4 items-center">
                    {{-- Fecha/Hora --}}
                    <div class="col-span-2">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $cierre->fecha_cierre->format('d/m/Y') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cierre->fecha_cierre->format('H:i') }}</p>
                    </div>

                    {{-- Turno --}}
                    <div class="col-span-3">
                        <div class="flex items-center gap-2">
                            @if($cierre->esGrupal())
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $cantidadCajas }}
                            </span>
                            <span class="font-medium text-gray-900 dark:text-white truncate">{{ $cierre->grupoCierre?->nombre ?? 'Grupo' }}</span>
                            @else
                            <span class="font-medium text-gray-900 dark:text-white truncate">{{ $cierre->detalleCajas->first()?->caja_nombre ?? 'Caja' }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $cierre->usuario?->name }}</p>
                    </div>

                    {{-- Saldo Inicial --}}
                    <div class="col-span-1 text-right">
                        <p class="text-gray-600 dark:text-gray-400">${{ number_format($cierre->total_saldo_inicial, 0, ',', '.') }}</p>
                    </div>

                    {{-- Ingresos --}}
                    <div class="col-span-1 text-right">
                        <p class="text-green-600 dark:text-green-400 font-medium">+${{ number_format($cierre->total_ingresos, 0, ',', '.') }}</p>
                    </div>

                    {{-- Egresos --}}
                    <div class="col-span-1 text-right">
                        <p class="text-red-600 dark:text-red-400 font-medium">-${{ number_format($cierre->total_egresos, 0, ',', '.') }}</p>
                    </div>

                    {{-- Final --}}
                    <div class="col-span-1 text-right">
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($cierre->total_saldo_final, 0, ',', '.') }}</p>
                    </div>

                    {{-- Diferencia --}}
                    <div class="col-span-1 text-right">
                        @if($tieneDiferencia)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $esFaltante ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                            {{ $esFaltante ? '' : '+' }}${{ number_format($cierre->total_diferencia, 0, ',', '.') }}
                        </span>
                        @else
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            OK
                        </span>
                        @endif
                    </div>

                    {{-- Acciones --}}
                    <div class="col-span-2 flex items-center justify-center gap-2">
                        <button wire:click="verDetalle({{ $cierre->id }})"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Detalle
                        </button>
                        <button wire:click="reimprimir({{ $cierre->id }})"
                                title="Reimprimir"
                                class="inline-flex items-center px-2.5 py-1.5 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Paginación --}}
        @if($cierres->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            {{ $cierres->links() }}
        </div>
        @endif
        @endif
    </div>

    {{-- Modal de Detalle --}}
    @if($showDetalleModal && $cierreDetalle)
    <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        {{-- Overlay --}}
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cerrarDetalle"></div>

        {{-- Modal Container - Full screen en móvil, centrado en desktop --}}
        <div class="fixed inset-0 sm:inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:max-w-4xl md:w-full md:max-h-[90vh] flex flex-col bg-white dark:bg-gray-800 sm:rounded-xl shadow-xl overflow-hidden">
            {{-- Header --}}
            <div class="flex-shrink-0 px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span class="truncate">Cierre #{{ $cierreDetalle->id }}</span>
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $cierreDetalle->fecha_cierre->format('d/m/Y H:i') }}
                            <span class="hidden sm:inline">- {{ $cierreDetalle->usuario?->name }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="reimprimir({{ $cierreDetalle->id }})"
                                class="inline-flex items-center p-2 sm:px-3 sm:py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                            <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span class="hidden sm:inline">Reimprimir</span>
                        </button>
                        <button wire:click="cerrarDetalle" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Contenido scrolleable --}}
            <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4">
                {{-- Información General --}}
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Resumen General</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Tipo</p>
                            <p class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mt-1">
                                @if($cierreDetalle->esGrupal())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs sm:text-sm bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        Grupal
                                    </span>
                                @else
                                    Individual
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Inicial</p>
                            <p class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mt-1">${{ number_format($cierreDetalle->total_saldo_inicial, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
                            <p class="text-xs text-green-600 dark:text-green-400 uppercase">Ingresos</p>
                            <p class="text-base sm:text-lg font-bold text-green-700 dark:text-green-300 mt-1">+${{ number_format($cierreDetalle->total_ingresos, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 text-center">
                            <p class="text-xs text-red-600 dark:text-red-400 uppercase">Egresos</p>
                            <p class="text-base sm:text-lg font-bold text-red-700 dark:text-red-300 mt-1">-${{ number_format($cierreDetalle->total_egresos, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
                            <p class="text-xs text-blue-600 dark:text-blue-400 uppercase">Saldo Final</p>
                            <p class="text-lg sm:text-xl font-bold text-blue-700 dark:text-blue-300 mt-1">${{ number_format($cierreDetalle->total_saldo_final, 0, ',', '.') }}</p>
                        </div>
                        <div class="{{ $cierreDetalle->total_diferencia == 0 ? 'bg-green-50 dark:bg-green-900/20' : ($cierreDetalle->total_diferencia > 0 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-red-50 dark:bg-red-900/20') }} rounded-lg p-3 text-center">
                            <p class="text-xs {{ $cierreDetalle->total_diferencia == 0 ? 'text-green-600 dark:text-green-400' : ($cierreDetalle->total_diferencia > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }} uppercase">Diferencia</p>
                            <p class="text-lg sm:text-xl font-bold {{ $cierreDetalle->total_diferencia == 0 ? 'text-green-700 dark:text-green-300' : ($cierreDetalle->total_diferencia > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300') }} mt-1">
                                @if($cierreDetalle->total_diferencia == 0)
                                    OK
                                @else
                                    {{ $cierreDetalle->total_diferencia > 0 ? '+' : '' }}${{ number_format($cierreDetalle->total_diferencia, 0, ',', '.') }}
                                @endif
                            </p>
                        </div>
                        @if($cierreDetalle->fecha_apertura)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center col-span-2 sm:col-span-1">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Duracion</p>
                            <p class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mt-1">
                                {{ $cierreDetalle->duracion_horas ? number_format($cierreDetalle->duracion_horas, 1) . 'h' : '-' }}
                            </p>
                        </div>
                        @endif
                    </div>

                    {{-- Totales por Concepto (consolidado de todas las cajas) --}}
                    @php
                        $totalesPorConcepto = collect();
                        $totalesPorFormaPago = collect();

                        foreach($cierreDetalle->detalleCajas as $detalleCaja) {
                            // Consolidar conceptos
                            if ($detalleCaja->desglose_conceptos) {
                                foreach($detalleCaja->desglose_conceptos as $concepto => $monto) {
                                    $totalesPorConcepto[$concepto] = ($totalesPorConcepto[$concepto] ?? 0) + $monto;
                                }
                            }
                            // Consolidar formas de pago
                            if ($detalleCaja->desglose_formas_pago) {
                                foreach($detalleCaja->desglose_formas_pago as $forma => $monto) {
                                    $totalesPorFormaPago[$forma] = ($totalesPorFormaPago[$forma] ?? 0) + $monto;
                                }
                            }
                        }
                    @endphp

                    @if($totalesPorConcepto->count() > 0 || $totalesPorFormaPago->count() > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold mb-2">Totales Consolidados</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Por Forma de Pago --}}
                            @if($totalesPorFormaPago->count() > 0)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                <p class="text-xs text-gray-400 dark:text-gray-500 mb-2">Por Forma de Pago</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($totalesPorFormaPago->sortByDesc(fn($v) => $v) as $forma => $monto)
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-white dark:bg-gray-600 text-xs border border-gray-200 dark:border-gray-500">
                                        <span class="text-gray-600 dark:text-gray-300">{{ $forma }}:</span>
                                        <span class="ml-1 font-semibold text-gray-900 dark:text-white">${{ number_format($monto, 0, ',', '.') }}</span>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- Por Concepto --}}
                            @if($totalesPorConcepto->count() > 0)
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                <p class="text-xs text-blue-500 dark:text-blue-400 mb-2">Por Concepto</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($totalesPorConcepto->sortByDesc(fn($v) => $v) as $concepto => $monto)
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-white dark:bg-blue-900/30 text-xs border border-blue-200 dark:border-blue-700">
                                        <span class="text-blue-600 dark:text-blue-300">{{ $concepto }}:</span>
                                        <span class="ml-1 font-semibold text-blue-800 dark:text-blue-200">${{ number_format($monto, 0, ',', '.') }}</span>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Detalle por Caja --}}
                @if($cierreDetalle->detalleCajas->count() > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Detalle por Caja</h4>
                    <div class="space-y-3">
                        @foreach($cierreDetalle->detalleCajas as $detalleCaja)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 sm:p-4 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between mb-2 sm:mb-3">
                                <h5 class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base">{{ $detalleCaja->caja_nombre }}</h5>
                                @if($detalleCaja->tieneDiferencia())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $detalleCaja->tieneFaltante() ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ $detalleCaja->tipo_diferencia === 'faltante' ? '-' : '+' }}${{ number_format(abs($detalleCaja->diferencia), 0, ',', '.') }}
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    OK
                                </span>
                                @endif
                            </div>
                            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 sm:gap-3 text-xs sm:text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Inicial</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${{ number_format($detalleCaja->saldo_inicial, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Ingresos</p>
                                    <p class="font-medium text-green-600 dark:text-green-400">+${{ number_format($detalleCaja->total_ingresos, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Egresos</p>
                                    <p class="font-medium text-red-600 dark:text-red-400">-${{ number_format($detalleCaja->total_egresos, 0, ',', '.') }}</p>
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-gray-500 dark:text-gray-400">Sistema</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${{ number_format($detalleCaja->saldo_sistema, 0, ',', '.') }}</p>
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-gray-500 dark:text-gray-400">Declarado</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${{ number_format($detalleCaja->saldo_declarado, 0, ',', '.') }}</p>
                                </div>
                            </div>

                            {{-- Desglose por Forma de Pago --}}
                            @if($detalleCaja->desglose_formas_pago && count($detalleCaja->desglose_formas_pago) > 0)
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Por Forma de Pago</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($detalleCaja->desglose_formas_pago as $forma => $monto)
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 dark:bg-gray-600 text-xs">
                                        <span class="text-gray-600 dark:text-gray-300">{{ $forma }}:</span>
                                        <span class="ml-1 font-medium text-gray-900 dark:text-white">${{ number_format($monto, 0, ',', '.') }}</span>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- Desglose por Concepto --}}
                            @if($detalleCaja->desglose_conceptos && count($detalleCaja->desglose_conceptos) > 0)
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Por Concepto</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($detalleCaja->desglose_conceptos as $concepto => $monto)
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-blue-50 dark:bg-blue-900/30 text-xs">
                                        <span class="text-blue-600 dark:text-blue-300">{{ $concepto }}:</span>
                                        <span class="ml-1 font-medium text-blue-800 dark:text-blue-200">${{ number_format($monto, 0, ',', '.') }}</span>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Movimientos en Efectivo del Turno --}}
                @if($cierreDetalle->movimientos->count() > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                        Movimientos en Efectivo ({{ $cierreDetalle->movimientos->count() }})
                    </h4>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                        <div class="max-h-48 sm:max-h-60 overflow-y-auto">
                            <table class="w-full text-xs sm:text-sm">
                                <thead class="bg-gray-100 dark:bg-gray-600 sticky top-0">
                                    <tr>
                                        <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Hora</th>
                                        <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Concepto</th>
                                        <th class="px-2 sm:px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                    @foreach($cierreDetalle->movimientos->take(50) as $mov)
                                    <tr>
                                        <td class="px-2 sm:px-3 py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $mov->created_at->format('H:i') }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-gray-900 dark:text-white truncate max-w-[120px] sm:max-w-xs">{{ $mov->concepto }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-right whitespace-nowrap {{ $mov->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} font-medium">
                                            {{ $mov->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($mov->monto, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($cierreDetalle->movimientos->count() > 50)
                        <div class="px-3 py-2 bg-gray-100 dark:bg-gray-600 text-center text-xs text-gray-500 dark:text-gray-400">
                            Mostrando 50 de {{ $cierreDetalle->movimientos->count() }} movimientos
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Pagos del Turno --}}
                @php
                    $totalVentaPagos = $cierreDetalle->ventaPagos->count();
                    $totalCobroPagos = $cierreDetalle->cobroPagos->count();
                    $totalPagos = $totalVentaPagos + $totalCobroPagos;
                @endphp
                @if($totalPagos > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                        Pagos del Turno ({{ $totalPagos }})
                    </h4>

                    {{-- Resumen de pagos por forma de pago --}}
                    @php
                        $resumenPagos = [];

                        // Agrupar pagos de ventas
                        foreach($cierreDetalle->ventaPagos as $pago) {
                            $forma = $pago->formaPago?->nombre ?? 'Otro';
                            $concepto = $pago->conceptoPago?->nombre ?? $pago->formaPago?->conceptoPago?->nombre ?? '';
                            $key = $forma . ($concepto ? " ({$concepto})" : '');

                            if (!isset($resumenPagos[$key])) {
                                $resumenPagos[$key] = ['cantidad' => 0, 'total' => 0];
                            }
                            $resumenPagos[$key]['cantidad']++;
                            $resumenPagos[$key]['total'] += $pago->monto_final;
                        }

                        // Agrupar pagos de cobros
                        foreach($cierreDetalle->cobroPagos as $pago) {
                            $forma = $pago->formaPago?->nombre ?? 'Otro';
                            $concepto = $pago->conceptoPago?->nombre ?? '';
                            $key = $forma . ($concepto ? " ({$concepto})" : '');

                            if (!isset($resumenPagos[$key])) {
                                $resumenPagos[$key] = ['cantidad' => 0, 'total' => 0];
                            }
                            $resumenPagos[$key]['cantidad']++;
                            $resumenPagos[$key]['total'] += $pago->monto_final;
                        }

                        // Ordenar por total descendente
                        uasort($resumenPagos, fn($a, $b) => $b['total'] <=> $a['total']);
                    @endphp

                    {{-- Resumen por forma de pago --}}
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach($resumenPagos as $forma => $datos)
                        <div class="inline-flex items-center px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
                            <div class="text-xs">
                                <p class="font-medium text-indigo-700 dark:text-indigo-300">{{ $forma }}</p>
                                <p class="text-indigo-600 dark:text-indigo-400">
                                    {{ $datos['cantidad'] }} {{ $datos['cantidad'] == 1 ? 'pago' : 'pagos' }} •
                                    <span class="font-semibold">${{ number_format($datos['total'], 0, ',', '.') }}</span>
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Tabs para ver detalles --}}
                    <div x-data="{ tabActiva: 'ventas' }">
                        <div class="flex border-b border-gray-200 dark:border-gray-600 mb-3">
                            <button @click="tabActiva = 'ventas'"
                                    :class="tabActiva === 'ventas' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                                    class="py-2 px-4 text-sm font-medium border-b-2 transition-colors">
                                Pagos Ventas ({{ $totalVentaPagos }})
                            </button>
                            <button @click="tabActiva = 'cobros'"
                                    :class="tabActiva === 'cobros' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                                    class="py-2 px-4 text-sm font-medium border-b-2 transition-colors">
                                Pagos Cobros ({{ $totalCobroPagos }})
                            </button>
                        </div>

                        {{-- Pagos de Ventas --}}
                        <div x-show="tabActiva === 'ventas'" x-cloak>
                            @if($totalVentaPagos > 0)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                                <div class="max-h-48 sm:max-h-60 overflow-y-auto">
                                    <table class="w-full text-xs sm:text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-600 sticky top-0">
                                            <tr>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Venta</th>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Forma Pago</th>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 hidden sm:table-cell">Referencia</th>
                                                <th class="px-2 sm:px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                            @foreach($cierreDetalle->ventaPagos->take(50) as $pago)
                                            <tr>
                                                <td class="px-2 sm:px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">
                                                    #{{ $pago->venta?->numero ?? $pago->venta_id }}
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {{ $pago->formaPago?->nombre ?? '-' }}
                                                    @if($pago->cuotas && $pago->cuotas > 1)
                                                    <span class="text-xs text-gray-400">({{ $pago->cuotas }}c)</span>
                                                    @endif
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-gray-500 dark:text-gray-400 hidden sm:table-cell truncate max-w-[100px]">
                                                    {{ $pago->referencia ?? '-' }}
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-right font-medium text-green-600 dark:text-green-400 whitespace-nowrap">
                                                    ${{ number_format($pago->monto_final, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($totalVentaPagos > 50)
                                <div class="px-3 py-2 bg-gray-100 dark:bg-gray-600 text-center text-xs text-gray-500 dark:text-gray-400">
                                    Mostrando 50 de {{ $totalVentaPagos }} pagos
                                </div>
                                @endif
                            </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Sin pagos de ventas</p>
                            @endif
                        </div>

                        {{-- Pagos de Cobros --}}
                        <div x-show="tabActiva === 'cobros'" x-cloak>
                            @if($totalCobroPagos > 0)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                                <div class="max-h-48 sm:max-h-60 overflow-y-auto">
                                    <table class="w-full text-xs sm:text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-600 sticky top-0">
                                            <tr>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cobro</th>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Forma Pago</th>
                                                <th class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 hidden sm:table-cell">Referencia</th>
                                                <th class="px-2 sm:px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                            @foreach($cierreDetalle->cobroPagos->take(50) as $pago)
                                            <tr>
                                                <td class="px-2 sm:px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">
                                                    #{{ $pago->cobro?->numero ?? $pago->cobro_id }}
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {{ $pago->formaPago?->nombre ?? '-' }}
                                                    @if($pago->cuotas && $pago->cuotas > 1)
                                                    <span class="text-xs text-gray-400">({{ $pago->cuotas }}c)</span>
                                                    @endif
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-gray-500 dark:text-gray-400 hidden sm:table-cell truncate max-w-[100px]">
                                                    {{ $pago->referencia ?? '-' }}
                                                </td>
                                                <td class="px-2 sm:px-3 py-2 text-right font-medium text-green-600 dark:text-green-400 whitespace-nowrap">
                                                    ${{ number_format($pago->monto_final, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($totalCobroPagos > 50)
                                <div class="px-3 py-2 bg-gray-100 dark:bg-gray-600 text-center text-xs text-gray-500 dark:text-gray-400">
                                    Mostrando 50 de {{ $totalCobroPagos }} pagos
                                </div>
                                @endif
                            </div>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Sin pagos de cobros</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Observaciones --}}
                @if($cierreDetalle->observaciones)
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Observaciones</h4>
                    <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">{{ $cierreDetalle->observaciones }}</p>
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex-shrink-0 px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex justify-end">
                    <button wire:click="cerrarDetalle"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
