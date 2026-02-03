{{-- Card de Caja Individual --}}
<div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600 {{ $cajaEditandoPuntosId === $caja->id ? 'ring-2 ring-bcn-primary' : '' }}">
    @if($cajaEditandoPuntosId === $caja->id)
        {{-- Modo Edici&oacute;n de Puntos de Venta --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h5 class="font-semibold text-gray-900 dark:text-white">
                    {{ $caja->nombre }} - {{ __('Puntos de Venta') }}
                </h5>
            </div>

            {{-- Puntos de Venta Disponibles --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('Selecciona los puntos de venta a asignar') }}
                </label>

                @if($this->puntosVentaDisponibles->count() > 0)
                    <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                        @foreach($this->puntosVentaDisponibles as $pv)
                            @php
                                $estaAsignado = in_array($pv->id, $cajaPuntosAsignados);
                                $esDefecto = $cajaPuntoDefecto === $pv->id;
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg border {{ $estaAsignado ? 'bg-bcn-primary/5 border-bcn-primary/30 dark:bg-bcn-primary/10' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600' }}">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    {{-- Checkbox asignar --}}
                                    <button
                                        type="button"
                                        wire:click="togglePuntoVentaCaja({{ $pv->id }})"
                                        class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors {{ $estaAsignado ? 'bg-bcn-primary border-bcn-primary text-white' : 'border-gray-300 dark:border-gray-500 hover:border-bcn-primary' }}"
                                    >
                                        @if($estaAsignado)
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </button>

                                    {{-- Info del PV --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-semibold text-gray-900 dark:text-white">
                                                PV {{ $pv->numero_formateado }}
                                            </span>
                                            @if($pv->nombre)
                                                <span class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                    - {{ $pv->nombre }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $pv->cuit->cuit_formateado }} - {{ $pv->cuit->razon_social }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Bot&oacute;n Defecto --}}
                                @if($estaAsignado)
                                    <button
                                        type="button"
                                        wire:click="setPuntoVentaDefecto({{ $pv->id }})"
                                        class="flex-shrink-0 ml-2 px-2 py-1 rounded text-xs font-medium transition-colors {{ $esDefecto ? 'bg-bcn-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500' }}"
                                        :title="$esDefecto ? __('Punto de venta por defecto') : __('Establecer como defecto')"
                                    >
                                        @if($esDefecto)
                                            <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                        {{ __('Defecto') }}
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('El punto de venta marcado como "Defecto" se usará automáticamente al facturar') }}
                    </p>
                @else
                    <div class="text-center py-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <svg class="mx-auto h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                            {{ __('No hay puntos de venta disponibles') }}
                        </p>
                        <p class="text-xs text-yellow-600 dark:text-yellow-400">
                            {{ __('Configura primero los CUITs y sus puntos de venta') }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Botones --}}
            <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-600">
                <button
                    type="button"
                    wire:click="cancelarEdicionPuntosCaja"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    {{ __('Cancelar') }}
                </button>
                <button
                    type="button"
                    wire:click="guardarPuntosCaja"
                    class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ __('Guardar') }}
                </button>
            </div>
        </div>
    @else
        {{-- Modo Vista --}}
        <div>
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h5 class="font-semibold text-gray-900 dark:text-white">
                            {{ $caja->nombre }}
                        </h5>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $caja->activo ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                            {{ $caja->activo ? __('Activa') : __('Inactiva') }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                            {{ ucfirst($caja->tipo) }}
                        </span>
                        @if($caja->grupoCierre)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary/10 text-bcn-primary">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                </svg>
                                {{ $caja->grupoCierre->nombre ?? "Grupo #{$caja->grupoCierre->id}" }}
                            </span>
                        @endif
                    </div>

                    {{-- Configuraci&oacute;n actual --}}
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        @if($caja->limite_efectivo)
                            <div class="flex items-center gap-1">
                                <span class="text-gray-500">{{ __('Límite efectivo:') }}</span>
                                <span class="font-medium">${{ number_format($caja->limite_efectivo, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-1">
                            <span class="text-gray-500">{{ __('Carga inicial:') }}</span>
                            <span class="font-medium">{{ \App\Models\Caja::MODOS_CARGA_INICIAL[$caja->modo_carga_inicial ?? 'manual'] }}</span>
                            @if($caja->modo_carga_inicial === 'monto_fijo' && $caja->monto_fijo_inicial)
                                <span class="text-gray-500">($ {{ number_format($caja->monto_fijo_inicial, 2, ',', '.') }})</span>
                            @endif
                        </div>
                    </div>

                    {{-- Puntos de venta asignados --}}
                    <div class="mt-3">
                        @if($caja->puntosVenta->count() > 0)
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                                {{ __('Puntos de Venta:') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($caja->puntosVenta as $pv)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs {{ $pv->pivot->es_defecto ? 'bg-bcn-primary text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300' }}">
                                        <span class="font-mono font-semibold">PV {{ $pv->numero_formateado }}</span>
                                        <span class="mx-1 opacity-60">|</span>
                                        <span class="truncate max-w-[100px]" title="{{ $pv->cuit->razon_social }}">
                                            {{ $pv->cuit->cuit_formateado }}
                                        </span>
                                        @if($pv->pivot->es_defecto)
                                            <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20" title="{{ __('Defecto') }}">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>{{ __('Sin puntos de venta asignados') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Botones de acci&oacute;n --}}
            <div class="mt-4 flex gap-2">
                <button
                    wire:click="abrirConfigCaja({{ $caja->id }})"
                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('Configurar') }}
                </button>
                <button
                    wire:click="editarPuntosCaja({{ $caja->id }})"
                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('Puntos Fiscales') }}
                </button>
            </div>
        </div>
    @endif
</div>
