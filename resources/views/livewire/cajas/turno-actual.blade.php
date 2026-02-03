<div class="py-4 px-4 sm:px-6">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Turno Actual') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Gestion de cajas y movimientos del turno') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('cajas.historial-turnos') }}"
               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Historial') }}
            </a>
            <button
                wire:click="cargarCajas"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ __('Actualizar') }}
            </button>
        </div>
    </div>

    {{-- Resumen General --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                {{ __('Resumen General') }}
            </h2>
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></span>
                    {{ $totalesGenerales['cajasAbiertas'] ?? 0 }} {{ __('activas') }}
                </span>
                @if(($totalesGenerales['cajasPausadas'] ?? 0) > 0)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    <span class="w-2 h-2 bg-amber-500 rounded-full mr-1.5"></span>
                    {{ $totalesGenerales['cajasPausadas'] }} {{ __('pausadas') }}
                </span>
                @endif
                @if(($totalesGenerales['cajasCerradas'] ?? 0) > 0)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                    {{ $totalesGenerales['cajasCerradas'] }} {{ __('sin turno') }}
                </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Fondo Inicial') }}</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($totalesGenerales['saldoInicial'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wider">{{ __('Ingresos') }}</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300 mt-1">+${{ number_format($totalesGenerales['ingresos'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <p class="text-xs text-red-600 dark:text-red-400 uppercase tracking-wider">{{ __('Egresos') }}</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300 mt-1">-${{ number_format($totalesGenerales['egresos'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wider">{{ __('Saldo Actual') }}</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300 mt-1">${{ number_format($totalesGenerales['saldoActual'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                <p class="text-xs text-purple-600 dark:text-purple-400 uppercase tracking-wider">{{ __('Operaciones') }}</p>
                <p class="text-xl font-bold text-purple-700 dark:text-purple-300 mt-1">{{ $totalesGenerales['operaciones'] ?? 0 }}</p>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
                <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Movimientos') }}</p>
                <p class="text-xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $totalesGenerales['movimientos'] ?? 0 }}</p>
            </div>
        </div>

        {{-- Totales por Concepto --}}
        @if(!empty($totalesGenerales['porConcepto']))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Por Concepto de Pago') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach($totalesGenerales['porConcepto'] as $concepto)
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $concepto['nombre'] }}:</span>
                    <span class="ml-1 text-gray-900 dark:text-white font-semibold">${{ number_format($concepto['monto'], 2, ',', '.') }}</span>
                    <span class="ml-1 text-gray-400 text-xs">({{ $concepto['cantidad'] }})</span>
                </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Mensaje si no hay cajas --}}
    @if($cajas->isEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">{{ __('No hay cajas disponibles') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No tienes cajas asignadas o todas estan cerradas.') }}</p>
    </div>
    @else

    {{-- Cajas agrupadas --}}
    <div class="space-y-6">
        @foreach($cajasAgrupadas as $grupoKey => $cajasGrupo)
            @php
                $esGrupo = !str_starts_with($grupoKey, 'individual_');
                $grupo = $esGrupo ? $cajasGrupo->first()->grupoCierre : null;
                $todasAbiertas = $cajasGrupo->every(fn($c) => $c->estado === 'abierta');
                $todasCerradas = $cajasGrupo->every(fn($c) => $c->estado === 'cerrada');
                $algunaCajaAbierta = $cajasGrupo->contains(fn($c) => $c->estado === 'abierta');
                $tieneMovimientosPendientes = $cajasGrupo->contains(fn($c) => $c->tieneMovimientosPendientes);
                // Para grupos con fondo comun: verificar si el fondo > 0 indica turno activo
                $fondoComunActivo = $grupo && $grupo->fondo_comun && ($grupo->saldo_fondo_comun ?? 0) > 0;
                // El turno del grupo esta activo si alguna caja esta abierta O tiene movimientos pendientes O tiene fondo comun activo
                $turnoGrupoActivo = $algunaCajaAbierta || $tieneMovimientosPendientes || $fondoComunActivo;
                // El turno esta cerrado si todas estan cerradas Y no hay movimientos pendientes Y no hay fondo comun activo
                $turnoGrupoCerrado = $todasCerradas && !$tieneMovimientosPendientes && !$fondoComunActivo;
            @endphp

            @if($esGrupo)
            {{-- GRUPO DE CAJAS --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Header del Grupo --}}
                <div class="bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-800 flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $grupo->nombre ?? __('Grupo de Cajas') }}
                                    @if($grupo->fondo_comun)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-300">
                                            {{ __('Fondo Comun') }}
                                        </span>
                                    @endif
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $cajasGrupo->count() }} {{ __('cajas') }} - {{ __('Cierre conjunto') }}
                                    @if($grupo->fondo_comun && $turnoGrupoActivo)
                                        <span class="ml-2 text-green-600 dark:text-green-400 font-medium">
                                            | {{ __('Fondo') }}: ${{ number_format($grupo->saldo_fondo_comun ?? 0, 2, ',', '.') }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($turnoGrupoActivo)
                            {{-- Turno activo: mostrar botón cerrar --}}
                            <button
                                wire:click="abrirModalCierre(null, {{ $grupo->id }})"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                {{ __('Cerrar Turno Grupo') }}
                            </button>
                            @elseif($turnoGrupoCerrado)
                            {{-- Turno cerrado: mostrar botón abrir --}}
                            <button
                                wire:click="abrirModalApertura(null, {{ $grupo->id }})"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                </svg>
                                {{ __('Abrir Turno Grupo') }}
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Cajas del Grupo --}}
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($cajasGrupo as $caja)
                            @include('livewire.cajas.partials.caja-card', ['caja' => $caja, 'enGrupo' => true])
                        @endforeach
                    </div>
                </div>
            </div>
            @else
            {{-- CAJA INDIVIDUAL --}}
            @php $caja = $cajasGrupo->first(); @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @include('livewire.cajas.partials.caja-card', ['caja' => $caja, 'enGrupo' => false])
            </div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Modal de Apertura de Turno --}}
    @if($showAperturaModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showAperturaModal', false)"></div>

            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ __('Abrir Turno') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @if($esAperturaGrupal)
                                    {{ __('Apertura grupal') }} - {{ count($cajasAAbrir) }} {{ __('cajas') }}
                                @else
                                    {{ __('Apertura individual') }}
                                @endif
                            </p>
                        </div>
                        <button wire:click="$set('showAperturaModal', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        {{-- Si el grupo usa fondo común, mostrar un solo input --}}
                        @if($esAperturaGrupal && $grupoUsaFondoComun)
                            @php $grupoInfo = $this->getGrupoParaApertura(); @endphp
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <h4 class="font-semibold text-blue-900 dark:text-blue-300">
                                        {{ __('Fondo Comun del Grupo') }}
                                    </h4>
                                </div>
                                <p class="text-sm text-blue-700 dark:text-blue-400 mb-3">
                                    {{ __('Este grupo utiliza fondo comun. El monto ingresado sera compartido entre las') }} {{ $grupoInfo['cantidad_cajas'] ?? count($cajasAAbrir) }} {{ __('cajas.') }}
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-blue-800 dark:text-blue-300 mb-1">
                                        {{ __('Fondo Comun Total') }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-blue-600">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.blur="fondoComunTotal"
                                            x-init="$nextTick(() => $el.focus())"
                                            class="block w-full pl-8 pr-4 py-3 text-xl font-bold border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-700 text-blue-900 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="0.00"
                                        >
                                    </div>
                                </div>
                            </div>

                            {{-- Lista informativa de cajas que se abrirán --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Cajas que se abriran:') }}</h5>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($cajasAAbrir as $cajaId)
                                        @php $cajaApertura = $this->getCajaParaApertura($cajaId); @endphp
                                        @if($cajaApertura)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300">
                                            {{ $cajaApertura['nombre'] }} #{{ $cajaApertura['numero'] }}
                                        </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Fondo individual por caja (comportamiento original) --}}
                            @foreach($cajasAAbrir as $cajaId)
                            @php $cajaApertura = $this->getCajaParaApertura($cajaId); @endphp
                            @if($cajaApertura)
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-medium text-gray-900 dark:text-white">
                                        {{ $cajaApertura['nombre'] }}
                                        <span class="text-sm font-normal text-gray-500">#{{ $cajaApertura['numero'] }}</span>
                                    </h4>
                                    <span class="text-xs px-2 py-1 rounded bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300">
                                        @switch($cajaApertura['modo_carga'])
                                            @case('ultimo_cierre')
                                                {{ __('Auto (ultimo cierre)') }}
                                                @break
                                            @case('monto_fijo')
                                                {{ __('Auto') }} (${{ number_format($cajaApertura['monto_fijo'], 0) }})
                                                @break
                                            @default
                                                {{ __('Manual') }}
                                        @endswitch
                                    </span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('Fondo Inicial') }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.live="fondosIniciales.{{ $cajaId }}"
                                            @if($loop->first) x-init="$nextTick(() => $el.focus())" @endif
                                            class="block w-full pl-8 pr-4 py-2.5 text-lg font-semibold border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-bcn-primary focus:border-bcn-primary"
                                            placeholder="0.00"
                                        >
                                    </div>
                                </div>
                            </div>
                            @endif
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                    <button
                        wire:click="procesarApertura"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm disabled:opacity-50"
                    >
                        <svg wire:loading wire:target="procesarApertura" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Abrir Turno') }}
                    </button>
                    <button
                        wire:click="$set('showAperturaModal', false)"
                        type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm"
                    >
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de Cierre de Turno - Pantalla completa --}}
    @if($showCierreModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        {{-- Overlay --}}
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="cancelarCierre"></div>

        {{-- Modal Container - altura máxima adaptativa --}}
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            {{-- Header fijo --}}
            <div class="flex-shrink-0 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            {{ __('Cierre de Turno') }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            @if($esCierreGrupal)
                                {{ __('Cierre grupal') }} - {{ count($cajasACerrar) }} {{ __('cajas') }}
                            @else
                                {{ __('Cierre individual') }}
                            @endif
                        </p>
                    </div>
                    <button wire:click="cancelarCierre" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Contenido scrolleable --}}
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div class="space-y-4">
                    @if($cierreUsaFondoComun)
                        {{-- VISTA CONSOLIDADA PARA FONDO COMUN --}}
                        @php $datosConsolidados = $this->getDatosConsolidadosCierre(); @endphp
                        @if($datosConsolidados)
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-700">
                            {{-- Header del grupo --}}
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center mr-2">
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </span>
                                    {{ $datosConsolidados['grupo_nombre'] }}
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-300">
                                        {{ __('Fondo Comun') }}
                                    </span>
                                </h4>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $datosConsolidados['cantidad_cajas'] }} {{ __('cajas') }}
                                </span>
                            </div>

                            {{-- Resumen consolidado --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Fondo Inicial') }}</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">${{ number_format($datosConsolidados['fondo_inicial'], 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Ingresos Total') }}</p>
                                    <p class="text-lg font-bold text-green-600 dark:text-green-400 mt-1">+${{ number_format($datosConsolidados['total_ingresos'], 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Egresos Total') }}</p>
                                    <p class="text-lg font-bold text-red-600 dark:text-red-400 mt-1">-${{ number_format($datosConsolidados['total_egresos'], 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-900/50 rounded-lg p-3 text-center border border-blue-300 dark:border-blue-600">
                                    <p class="text-xs text-blue-700 dark:text-blue-300 uppercase tracking-wide font-medium">{{ __('Saldo Sistema') }}</p>
                                    <p class="text-lg font-bold text-blue-800 dark:text-blue-200 mt-1">${{ number_format($datosConsolidados['saldo_sistema'], 2, ',', '.') }}</p>
                                </div>
                            </div>

                            {{-- Detalle por caja (colapsado) --}}
                            <div class="mb-5">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Detalle por Caja') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($datosConsolidados['cajas'] as $cajaInfo)
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $cajaInfo['nombre'] }}:</span>
                                        <span class="ml-1 text-green-600 dark:text-green-400">+${{ number_format($cajaInfo['ingresos'], 2, ',', '.') }}</span>
                                        @if($cajaInfo['egresos'] > 0)
                                        <span class="ml-1 text-red-600 dark:text-red-400">-${{ number_format($cajaInfo['egresos'], 2, ',', '.') }}</span>
                                        @endif
                                    </span>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Input de arqueo UNICO para todo el grupo --}}
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-blue-300 dark:border-blue-600">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    {{ __('Efectivo Total Contado (Arqueo del Fondo Comun)') }}
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 text-xl font-medium">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        wire:model.live.debounce.300ms="saldoDeclaradoFondoComun"
                                        x-init="$nextTick(() => $el.focus())"
                                        class="block w-full pl-10 pr-4 py-4 text-2xl font-bold border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center"
                                        :placeholder="__('Ingrese el total del efectivo')"
                                    >
                                </div>

                                {{-- Calculo de diferencia --}}
                                @php
                                    $saldoDeclaradoFC = $saldoDeclaradoFondoComun !== '' ? (float)$saldoDeclaradoFondoComun : null;
                                    $saldoSistemaFC = $datosConsolidados['saldo_sistema'];
                                    $diferenciaFC = $saldoDeclaradoFC !== null ? $saldoDeclaradoFC - $saldoSistemaFC : null;
                                @endphp

                                @if($saldoDeclaradoFC !== null)
                                <div class="mt-3 p-3 rounded-xl text-center {{ $diferenciaFC == 0 ? 'bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700' : ($diferenciaFC > 0 ? 'bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700' : 'bg-red-100 dark:bg-red-900/40 border border-red-300 dark:border-red-700') }}">
                                    <div class="flex items-center justify-center space-x-2">
                                        @if($diferenciaFC == 0)
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-green-700 dark:text-green-400">{{ __('Fondo Cuadrado') }}</span>
                                        @elseif($diferenciaFC > 0)
                                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ __('Sobrante') }}: +${{ number_format($diferenciaFC, 2, ',', '.') }}</span>
                                        @else
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-red-700 dark:text-red-400">{{ __('Faltante') }}: -${{ number_format(abs($diferenciaFC), 2, ',', '.') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @else
                                <p class="mt-3 text-sm text-center text-gray-500 dark:text-gray-400">
                                    {{ __('Ingrese el efectivo total contado para calcular la diferencia') }}
                                </p>
                                @endif
                            </div>
                        </div>
                        @endif
                    @else
                        {{-- VISTA NORMAL (por caja individual) --}}
                        @foreach($cajasACerrar as $cajaId)
                        @php $cajaCierre = $this->getCajaParaCierre($cajaId); @endphp
                        @if($cajaCierre)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-5 border border-gray-200 dark:border-gray-600">
                            {{-- Nombre de la caja --}}
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                                    <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center mr-2">
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </span>
                                    {{ $cajaCierre['nombre'] }}
                                    <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">#{{ $cajaCierre['numero'] }}</span>
                                </h4>
                                @if($cajaCierre['fecha_apertura'])
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Abierta') }}: {{ $cajaCierre['fecha_apertura'] }}
                                </span>
                                @endif
                            </div>

                            {{-- Resumen de operaciones --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Fondo Inicial') }}</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">${{ number_format($cajaCierre['saldo_inicial'], 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Ingresos') }}</p>
                                    <p class="text-lg font-bold text-green-600 dark:text-green-400 mt-1">+${{ number_format($cajaCierre['ingresos'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-gray-100 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Egresos') }}</p>
                                    <p class="text-lg font-bold text-red-600 dark:text-red-400 mt-1">-${{ number_format($cajaCierre['egresos'] ?? 0, 2, ',', '.') }}</p>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center border border-blue-200 dark:border-blue-700">
                                    <p class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wide font-medium">{{ __('Saldo Sistema') }}</p>
                                    <p class="text-lg font-bold text-blue-700 dark:text-blue-300 mt-1">${{ number_format($cajaCierre['saldo_actual'], 2, ',', '.') }}</p>
                                </div>
                            </div>

                            {{-- Input de arqueo --}}
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-gray-200 dark:border-gray-600">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    {{ __('Efectivo Contado (Arqueo)') }}
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 text-xl font-medium">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        wire:model.live.debounce.300ms="saldosDeclarados.{{ $cajaId }}"
                                        @if($loop->first) x-init="$nextTick(() => $el.focus())" @endif
                                        class="block w-full pl-10 pr-4 py-4 text-2xl font-bold border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center"
                                        :placeholder="__('Ingrese el monto contado')"
                                    >
                                </div>

                                {{-- Calculo de diferencia --}}
                                @php
                                    $saldoDeclarado = isset($saldosDeclarados[$cajaId]) && $saldosDeclarados[$cajaId] !== '' ? (float)$saldosDeclarados[$cajaId] : null;
                                    $saldoSistema = $cajaCierre['saldo_actual'];
                                    $diferencia = $saldoDeclarado !== null ? $saldoDeclarado - $saldoSistema : null;
                                @endphp

                                @if($saldoDeclarado !== null)
                                <div class="mt-3 p-3 rounded-xl text-center {{ $diferencia == 0 ? 'bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700' : ($diferencia > 0 ? 'bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700' : 'bg-red-100 dark:bg-red-900/40 border border-red-300 dark:border-red-700') }}">
                                    <div class="flex items-center justify-center space-x-2">
                                        @if($diferencia == 0)
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-green-700 dark:text-green-400">{{ __('Caja Cuadrada') }}</span>
                                        @elseif($diferencia > 0)
                                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ __('Sobrante') }}: +${{ number_format($diferencia, 2, ',', '.') }}</span>
                                        @else
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="text-lg font-bold text-red-700 dark:text-red-400">{{ __('Faltante') }}: -${{ number_format(abs($diferencia), 2, ',', '.') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @else
                                <p class="mt-3 text-sm text-center text-gray-500 dark:text-gray-400">
                                    {{ __('Ingrese el efectivo contado para calcular la diferencia') }}
                                </p>
                                @endif
                            </div>
                        </div>
                        @endif
                        @endforeach
                    @endif

                    {{-- Observaciones --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Observaciones (opcional)') }}
                        </label>
                        <textarea
                            wire:model="observacionesCierre"
                            rows="2"
                            class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm"
                            :placeholder="__('Notas sobre el cierre del turno...')"
                        ></textarea>
                    </div>
                </div>
            </div>

            {{-- Footer fijo --}}
            <div class="flex-shrink-0 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button
                        wire:click="cancelarCierre"
                        type="button"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        {{ __('Cancelar') }}
                    </button>
                    <button
                        wire:click="procesarCierre"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="procesarCierre" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="procesarCierre" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        {{ __('Confirmar Cierre') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: Detalle de Movimientos de Caja                        --}}
    {{-- ============================================================ --}}
    @if($showDetalleModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-hidden" aria-labelledby="modal-detalle" role="dialog" aria-modal="true"
         x-data="{ init() { document.body.classList.add('overflow-hidden') }, destroy() { document.body.classList.remove('overflow-hidden') } }">
        {{-- Overlay --}}
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"
             wire:click="cerrarModalDetalle"></div>

        {{-- Modal Container --}}
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
                {{-- Header --}}
                <div class="flex-shrink-0 bg-indigo-50 dark:bg-indigo-900/20 px-6 py-4 border-b border-indigo-100 dark:border-indigo-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('Detalle de Movimientos') }} — {{ $detalleInfo['nombre'] ?? '' }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ __('Saldo actual') }}:
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    ${{ number_format($detalleInfo['saldo_actual'] ?? 0, 2, ',', '.') }}
                                </span>
                                &middot; {{ $detalleInfo['cantidad_movimientos'] ?? 0 }} {{ __('movimientos en efectivo') }}
                                @if($detalleInfo['es_fondo_comun'] ?? false)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ __('Fondo Unificado') }}
                                    </span>
                                @endif
                            </p>
                        </div>
                        <button wire:click="cerrarModalDetalle" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Cuerpo scrollable --}}
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        {{-- Columna principal: Movimientos en Efectivo --}}
                        <div class="lg:col-span-2">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                {{ __('Movimientos en Efectivo') }}
                            </h4>

                            @if(count($detalleMovimientos) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Hora') }}</th>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Tipo') }}</th>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                            <th class="text-right py-2 px-2 text-xs font-medium text-green-600 dark:text-green-400 uppercase">{{ __('Ingreso') }}</th>
                                            <th class="text-right py-2 px-2 text-xs font-medium text-red-600 dark:text-red-400 uppercase">{{ __('Egreso') }}</th>
                                            <th class="text-right py-2 px-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($detalleMovimientos as $mov)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="py-1.5 px-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                {{ $mov['fecha'] }}
                                            </td>
                                            <td class="py-1.5 px-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                                    {{ $mov['tipo'] === 'ingreso'
                                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                                    {{ $mov['etiqueta'] }}
                                                </span>
                                            </td>
                                            <td class="py-1.5 px-2 text-xs text-gray-700 dark:text-gray-300 max-w-[200px] truncate" title="{{ $mov['concepto'] }}">
                                                {{ $mov['concepto'] }}
                                            </td>
                                            <td class="py-1.5 px-2 text-right text-xs whitespace-nowrap {{ $mov['tipo'] === 'ingreso' ? 'text-green-600 dark:text-green-400 font-medium' : 'text-gray-300 dark:text-gray-600' }}">
                                                @if($mov['tipo'] === 'ingreso')
                                                    +${{ number_format($mov['monto'], 2, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right text-xs whitespace-nowrap {{ $mov['tipo'] === 'egreso' ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-300 dark:text-gray-600' }}">
                                                @if($mov['tipo'] === 'egreso')
                                                    -${{ number_format($mov['monto'], 2, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right text-xs font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                                ${{ number_format($mov['saldo_acumulado'], 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                                            <td colspan="3" class="py-2 px-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                {{ __('TOTALES') }}
                                            </td>
                                            <td class="py-2 px-2 text-right text-xs font-bold text-green-600 dark:text-green-400">
                                                +${{ number_format($detalleInfo['total_ingresos'] ?? 0, 2, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-2 text-right text-xs font-bold text-red-600 dark:text-red-400">
                                                -${{ number_format($detalleInfo['total_egresos'] ?? 0, 2, ',', '.') }}
                                            </td>
                                            <td class="py-2 px-2 text-right text-xs font-bold text-gray-900 dark:text-white">
                                                ${{ number_format($detalleInfo['saldo_actual'] ?? 0, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @else
                            <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="text-sm">{{ __('Sin movimientos en efectivo') }}</p>
                            </div>
                            @endif
                        </div>

                        {{-- Columna secundaria: Otros Medios de Pago --}}
                        <div class="lg:col-span-1">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                {{ __('Otros Medios de Pago') }}
                            </h4>

                            @if(count($detalleOtrosConceptos) > 0)
                            <div class="space-y-3">
                                @foreach($detalleOtrosConceptos as $concepto)
                                <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $concepto['nombre'] }}
                                        </span>
                                        <span class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                            ${{ number_format($concepto['monto_total'], 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        {{ $concepto['cantidad'] }} {{ $concepto['cantidad'] === 1 ? __('operacion') : __('operaciones') }}
                                    </p>
                                    <div class="space-y-1">
                                        @foreach($concepto['detalle'] as $det)
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">{{ $det['referencia'] }}</span>
                                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                                ${{ number_format($det['monto'], 2, ',', '.') }}
                                            </span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <div class="text-center py-8 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <p class="text-sm">{{ __('Sin operaciones con otros medios de pago') }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-t border-gray-200 dark:border-gray-600 flex justify-end">
                    <button
                        wire:click="cerrarModalDetalle"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        {{ __('Cerrar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
