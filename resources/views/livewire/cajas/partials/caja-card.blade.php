{{-- Tarjeta de Caja Individual --}}
@php
    $estaActiva = $caja->estado === 'abierta';
    $tieneMovimientos = $caja->tieneMovimientosPendientes ?? false;
    $nuncaAbierta = $caja->nuncaAbierta ?? ($caja->fecha_apertura === null);
    // Turno del grupo activo (para cajas en grupos con fondo comun)
    $turnoGrupoActivo = $caja->turnoGrupoActivo ?? false;
    // Pausada = cerrada pero con movimientos pendientes (turno aun activo)
    // O cerrada pero pertenece a un grupo con fondo comun que tiene turno activo
    $estaPausada = !$estaActiva && ($tieneMovimientos || $turnoGrupoActivo) && !$nuncaAbierta;
    // Turno cerrado = cerrada y sin movimientos pendientes y no en grupo con turno activo
    $turnoCerrado = !$estaActiva && !$tieneMovimientos && !$turnoGrupoActivo && !$nuncaAbierta;
    // Turno activo = abierta o pausada
    $turnoActivo = $estaActiva || $estaPausada;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border {{ $estaActiva ? 'border-green-200 dark:border-green-800' : ($estaPausada ? 'border-amber-200 dark:border-amber-800' : 'border-gray-200 dark:border-gray-700') }} overflow-hidden {{ !$enGrupo ? 'shadow-sm' : '' }}">
    {{-- Header de la Caja --}}
    <div class="px-4 py-3 {{ $estaActiva ? 'bg-green-50 dark:bg-green-900/20' : ($estaPausada ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-700/50') }} border-b {{ $estaActiva ? 'border-green-100 dark:border-green-800' : ($estaPausada ? 'border-amber-100 dark:border-amber-800' : 'border-gray-100 dark:border-gray-600') }}">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                {{-- Indicador de estado --}}
                <div class="w-8 h-8 rounded-full {{ $estaActiva ? 'bg-green-100 dark:bg-green-800' : ($estaPausada ? 'bg-amber-100 dark:bg-amber-800' : 'bg-gray-200 dark:bg-gray-600') }} flex items-center justify-center mr-3">
                    @if($estaActiva)
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    @elseif($estaPausada)
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    @else
                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    @endif
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white flex items-center">
                        {{ $caja->nombre }}
                        @if($nuncaAbierta)
                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                {{ __('Nueva') }}
                            </span>
                        @elseif($estaPausada)
                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ __('Pausada') }}
                            </span>
                        @endif
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        #{{ $caja->numero_formateado }}
                        @if($turnoActivo && $caja->fecha_apertura)
                            - {{ __('Desde') }} {{ $caja->fecha_apertura->format('H:i') }}
                        @elseif($nuncaAbierta)
                            - {{ __('Sin turno previo') }}
                        @elseif($turnoCerrado)
                            - {{ __('Turno cerrado') }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Botones de accion --}}
            <div class="flex items-center gap-2">
                @if($estaActiva)
                    {{-- Caja ACTIVA: boton pausar + cerrar turno --}}
                    <button
                        wire:click="desactivarCaja({{ $caja->id }})"
                        class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors"
                        :title="__('Pausar caja (sin cerrar turno)')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>

                    @if(!$enGrupo)
                    <button
                        wire:click="abrirModalCierre({{ $caja->id }})"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                        :title="__('Cerrar turno')"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        {{ __('Cerrar') }}
                    </button>
                    @endif

                @elseif($estaPausada)
                    {{-- Caja PAUSADA: boton reactivar + cerrar turno --}}
                    <button
                        wire:click="activarCaja({{ $caja->id }})"
                        class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors"
                        :title="__('Reactivar caja')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>

                    @if(!$enGrupo)
                    <button
                        wire:click="abrirModalCierre({{ $caja->id }})"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                        :title="__('Cerrar turno')"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        {{ __('Cerrar') }}
                    </button>
                    @endif

                @else
                    {{-- Caja SIN TURNO ACTIVO: solo boton abrir turno (si es individual) --}}
                    @if(!$enGrupo)
                    <button
                        wire:click="abrirModalApertura({{ $caja->id }})"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                        :title="__('Abrir turno')"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                        </svg>
                        {{ __('Abrir') }}
                    </button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Contenido --}}
    <div class="p-4">
        {{-- Metricas principales --}}
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo Actual') }}</p>
                <p class="text-lg font-bold {{ $estaActiva ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                    ${{ number_format($caja->saldo_actual, 2, ',', '.') }}
                </p>
            </div>
            <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Operaciones') }}</p>
                <p class="text-lg font-bold {{ $estaActiva ? 'text-purple-600 dark:text-purple-400' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $cajasOperaciones[$caja->id] ?? $caja->cantidadOperaciones ?? 0 }}
                </p>
            </div>
        </div>

        {{-- Resumen de movimientos --}}
        @php
            $resumenMov = $cajasResumenMovimientos[$caja->id] ?? $caja->resumenMovimientos ?? ['ingresos' => 0, 'egresos' => 0];
        @endphp
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center text-green-600 dark:text-green-400">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                    </svg>
                    +${{ number_format($resumenMov['ingresos'] ?? 0, 0, ',', '.') }}
                </span>
                <span class="inline-flex items-center text-red-600 dark:text-red-400">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                    </svg>
                    -${{ number_format($resumenMov['egresos'] ?? 0, 0, ',', '.') }}
                </span>
            </div>

            {{-- Indicador de movimientos pendientes --}}
            @if($tieneMovimientos)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                {{ $caja->movimientos->count() }} {{ __('mov.') }}
            </span>
            @endif
        </div>

        {{-- Estado adicional para cajas inactivas con movimientos o en grupo con turno activo --}}
        @if($estaPausada && $tieneMovimientos)
        <div class="mt-3 p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <p class="text-xs text-amber-700 dark:text-amber-400 flex items-center">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ __('Caja pausada con movimientos pendientes de cierre') }}
            </p>
        </div>
        @elseif($estaPausada && $turnoGrupoActivo)
        <div class="mt-3 p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <p class="text-xs text-amber-700 dark:text-amber-400 flex items-center">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Caja pausada - El turno del grupo sigue activo') }}
            </p>
        </div>
        @elseif($nuncaAbierta)
        <div class="mt-3 p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
            <p class="text-xs text-purple-700 dark:text-purple-400 flex items-center">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Esta caja nunca ha sido abierta. Inicie un turno para comenzar a operar.') }}
            </p>
        </div>
        @endif

        {{-- Totales por concepto (colapsable) --}}
        @php
            $totalesConceptoCaja = $cajasTotalesPorConcepto[$caja->id] ?? $caja->totalesPorConcepto ?? [];
        @endphp
        @if(!empty($totalesConceptoCaja))
        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <button
                wire:click="toggleConcepto('caja-{{ $caja->id }}')"
                class="w-full flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
            >
                <span>{{ __('Por Concepto de Pago') }}</span>
                <svg class="w-3.5 h-3.5 transition-transform {{ in_array('caja-' . $caja->id, $conceptosExpandidos) ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            @if(in_array('caja-' . $caja->id, $conceptosExpandidos))
            <div class="mt-2 space-y-1">
                @foreach($totalesConceptoCaja as $concepto)
                <div class="flex items-center justify-between text-xs py-1 px-2 bg-gray-50 dark:bg-gray-700/30 rounded">
                    <span class="text-gray-600 dark:text-gray-400">{{ $concepto['nombre'] }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        ${{ number_format($concepto['monto'], 2, ',', '.') }}
                        <span class="text-gray-400">({{ $concepto['cantidad'] }})</span>
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Botón Detalle de Movimientos --}}
        @if($turnoActivo)
        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <button
                wire:click="abrirModalDetalle({{ $caja->id }})"
                class="w-full text-center text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium py-1 transition-colors"
            >
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                {{ __('Ver Detalle de Movimientos') }}
            </button>
        </div>
        @endif
    </div>
</div>
