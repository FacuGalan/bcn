<div class="py-4"
     x-data
     x-on:paso-cambiado.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('configuracion.precios') }}"
                   wire:navigate
                   class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-900 hover:bg-gray-200 transition-colors">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
                        {{ $modoEdicion ? __('Editar Lista de Precios') : __('Nueva Lista de Precios') }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Paso') }} {{ $pasoActual }} {{ __('de') }} {{ $totalPasos }}</p>
                </div>
            </div>
        </div>

        {{-- Progress Steps --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                @php
                    // Si es lista base, solo mostrar 2 pasos
                    $pasos = $esListaBase
                        ? [1 => __('Datos'), 2 => __('Precios')]
                        : [1 => __('Datos'), 2 => __('Precios'), 3 => __('Vigencia'), 4 => __('Condiciones'), 5 => __('Articulos')];
                @endphp
                @foreach($pasos as $numPaso => $labelPaso)
                    <div class="flex flex-col items-center flex-1">
                        @if($numPaso < $pasoActual)
                            {{-- Paso completado --}}
                            <button wire:click="irAPaso({{ $numPaso }})"
                                    class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-green-500 text-white cursor-pointer hover:bg-green-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                            <span class="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                        @elseif($numPaso == $pasoActual)
                            {{-- Paso actual --}}
                            <button class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-bcn-primary text-white ring-4 ring-bcn-primary ring-opacity-30">
                                {{ $numPaso }}
                            </button>
                            <span class="mt-1 text-[10px] sm:text-xs text-bcn-primary font-medium">{{ $labelPaso }}</span>
                        @else
                            {{-- Paso futuro --}}
                            @if($modoEdicion)
                                {{-- En modo edición, permitir click en pasos futuros --}}
                                <button wire:click="irAPaso({{ $numPaso }})"
                                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 cursor-pointer hover:bg-gray-300 dark:hover:bg-gray-600">
                                    {{ $numPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                            @else
                                {{-- En modo creación, pasos futuros deshabilitados --}}
                                <button disabled class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                                    {{ $numPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                            @endif
                        @endif
                    </div>
                    @if($numPaso < $totalPasos)
                        <div class="flex-1 h-1 mx-2 hidden sm:block {{ $numPaso < $pasoActual ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Aviso para lista base --}}
        @if($esListaBase)
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-amber-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <h4 class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ __('Lista Base') }}</h4>
                        <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                            {{ __('Esta es una') }} <strong>{{ __('lista base') }}</strong>. {{ __('Solo puedes modificar los datos basicos y el porcentaje de ajuste.') }}
                            {{ __('Las listas base aplican siempre a todos los articulos sin restricciones de vigencia, condiciones o articulos especificos.') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Contenido del paso actual --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">

            {{-- PASO 1: Datos basicos --}}
            @if($pasoActual == 1)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Datos Basicos') }}</h2>

                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Informacion de la lista') }}</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            {{-- Nombre (mitad izquierda) --}}
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Nombre') }} *</label>
                                <input type="text"
                                       wire:model="nombre"
                                       :placeholder="__('Ej: Lista Mayoristas')"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Codigo (1/4) --}}
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Codigo (opcional)') }}</label>
                                <input type="text"
                                       wire:model="codigo"
                                       :placeholder="__('Ej: MAYOR-001')"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>

                            {{-- Prioridad (1/4) --}}
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Prioridad') }} *</label>
                                <input type="number"
                                       wire:model="prioridad"
                                       min="1"
                                       max="999"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Menor = mayor prioridad') }}</p>
                                @error('prioridad') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Descripcion (ancho completo del paso) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Descripcion (opcional)') }}</label>
                        <textarea wire:model="descripcion"
                                  rows="3"
                                  :placeholder="__('Describe el proposito de esta lista...')"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                    </div>
                </div>
            @endif

            {{-- PASO 2: Configuracion de precios --}}
            @if($pasoActual == 2)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Configuracion de Precios') }}</h2>

                    {{-- Card 1: Ajuste de precio --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Ajuste de precio') }}</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            {{-- Tipo de ajuste --}}
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Tipo') }}</label>
                                <select wire:model.live="tipoAjuste"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="recargo">{{ __('Recargo') }}</option>
                                    <option value="descuento">{{ __('Descuento') }}</option>
                                </select>
                            </div>

                            {{-- Porcentaje --}}
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Porcentaje') }} *</label>
                                <div class="relative">
                                    <input type="number"
                                           wire:model.blur="porcentajeAbsoluto"
                                           step="0.01"
                                           min="0"
                                           max="1000"
                                           placeholder="0"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-8">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none">%</span>
                                </div>
                                @error('ajustePorcentaje') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Redondeo --}}
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Redondeo') }}</label>
                                <select wire:model="redondeo"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    @foreach($opcionesRedondeo as $valor => $label)
                                        <option value="{{ $valor }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Badge resumen del ajuste --}}
                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Efecto sobre el precio base') }}:</span>
                            @if($ajustePorcentaje > 0)
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                    +@porcentaje($ajustePorcentaje)
                                </span>
                            @elseif($ajustePorcentaje < 0)
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                    @porcentaje($ajustePorcentaje)
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                    {{ __('sin ajuste') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Card 2: Promociones (solo si no es lista base) --}}
                    @if(!$esListaBase)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Comportamiento con promociones') }}</h3>
                            </div>

                            {{-- Toggle aplica promociones --}}
                            <div class="flex items-center justify-between">
                                <div class="flex-1 pr-4">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Permite aplicar promociones') }}</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Las promociones se calculan usando los precios de esta lista como base') }}</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                    <input type="checkbox" wire:model.live="aplicaPromociones" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-bcn-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white dark:bg-gray-800 after:border-gray-300 dark:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bcn-primary"></div>
                                </label>
                            </div>

                            {{-- Alcance (solo si aplica) --}}
                            @if($aplicaPromociones)
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">{{ __('Alcance de las promociones') }}</label>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border-2 p-3 transition
                                            {{ $promocionesAlcance === 'todos' ? 'border-bcn-primary bg-white dark:bg-gray-800' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500' }}">
                                            <input type="radio" wire:model.live="promocionesAlcance" value="todos"
                                                   class="mt-1 text-bcn-primary focus:ring-bcn-primary">
                                            <div class="flex-1">
                                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ __('A toda la venta') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Todos los artículos participan en el cálculo de promociones') }}</p>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border-2 p-3 transition
                                            {{ $promocionesAlcance === 'excluir_lista' ? 'border-bcn-primary bg-white dark:bg-gray-800' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500' }}">
                                            <input type="radio" wire:model.live="promocionesAlcance" value="excluir_lista"
                                                   class="mt-1 text-bcn-primary focus:ring-bcn-primary">
                                            <div class="flex-1">
                                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ __('Excluir artículos con precio en esta lista') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Los artículos que tengan un precio especial en esta lista no participan en promociones') }}</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Card 3: Lista estática --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Precios congelados') }}</h3>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex-1 pr-4">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Lista estática (congelar precios)') }}</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Los precios se calculan al grabar y quedan fijos aunque cambie el precio base del artículo') }}</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                    <input type="checkbox" wire:model.live="estatica" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-bcn-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white dark:bg-gray-800 after:border-gray-300 dark:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bcn-primary"></div>
                                </label>
                            </div>

                            @if($estatica)
                                <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg">
                                    <div class="flex gap-2">
                                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <div>
                                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-200 mb-1">{{ __('Importante') }}</p>
                                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                                {{ __('Al grabar se generará un snapshot de precios para todos los artículos de la sucursal. Los artículos nuevos creados después quedarán fuera de esta lista.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Preview del precio --}}
                    @php
                        $precioPreview = 1000 * (1 + ($ajustePorcentaje / 100));
                    @endphp
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <h4 class="font-semibold text-sm text-blue-900 dark:text-blue-200">{{ __('Vista previa del cálculo') }}</h4>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <span>{{ __('Precio base') }}:</span>
                            <span class="font-mono font-bold text-gray-900 dark:text-white">$1.000,00</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                            <span>{{ __('con esta lista') }}:</span>
                            <span class="font-mono font-bold text-lg {{ $ajustePorcentaje > 0 ? 'text-red-600 dark:text-red-400' : ($ajustePorcentaje < 0 ? 'text-green-600 dark:text-green-400' : 'text-bcn-primary') }}">
                                $@precio($precioPreview)
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- PASO 3: Vigencia --}}
            @if($pasoActual == 3)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Vigencia y Restricciones Horarias') }}</h2>

                    {{-- Callout info --}}
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                {{ __('Todos los campos de este paso son opcionales. Si no los completás, la lista aplicará siempre sin restricciones.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Card 1: Vigencia (fechas) --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Vigencia') }}</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Vigente desde') }}</label>
                                <input type="date"
                                       wire:model="vigenciaDesde"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Vigente hasta') }}</label>
                                <input type="date"
                                       wire:model="vigenciaHasta"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ __('Dejar vacio para sin restriccion de fechas') }}</p>
                    </div>

                    {{-- Card 2: Restricciones horarias --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Restricciones horarias') }}</h3>
                        </div>

                        {{-- Dias de la semana --}}
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">{{ __('Dias de la semana') }}</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($opcionesDiasSemana as $valor => $label)
                                    <label @class([
                                        'inline-flex items-center px-3 py-2 rounded-lg border-2 cursor-pointer transition text-sm',
                                        'bg-bcn-primary text-white border-bcn-primary' => in_array($valor, $diasSemana),
                                        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-bcn-primary' => !in_array($valor, $diasSemana),
                                    ])>
                                        <input type="checkbox"
                                               wire:model.live="diasSemana"
                                               value="{{ $valor }}"
                                               class="sr-only">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ __('No seleccionar ninguno = todos los dias') }}</p>
                        </div>

                        {{-- Horas --}}
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-600 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Hora desde') }}</label>
                                <input type="time"
                                       wire:model="horaDesde"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Hora hasta') }}</label>
                                <input type="time"
                                       wire:model="horaHasta"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                        </div>
                    </div>

                    {{-- Card 3: Cantidades --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Cantidad por linea de venta') }}</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Cantidad minima') }}</label>
                                <input type="number"
                                       wire:model="cantidadMinima"
                                       step="0.001"
                                       min="0"
                                       :placeholder="__('Ej: 10')"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Cantidad maxima') }}</label>
                                <input type="number"
                                       wire:model="cantidadMaxima"
                                       step="0.001"
                                       min="0"
                                       :placeholder="__('Sin limite')"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- PASO 4: Condiciones --}}
            @if($pasoActual == 4)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Condiciones de Aplicacion') }}</h2>

                    {{-- Callout info --}}
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                {{ __('Define cuando debe aplicarse esta lista. Todas las condiciones deben cumplirse (AND).') }}
                            </p>
                        </div>
                    </div>

                    {{-- Card: Condiciones configuradas --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Condiciones configuradas') }}</h3>
                            </div>
                            @if(count($condiciones) > 0)
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-bcn-primary/10 text-bcn-primary dark:bg-bcn-primary/20">
                                    {{ count($condiciones) }}
                                </span>
                            @endif
                        </div>

                        @if(count($condiciones) > 0)
                            <div class="space-y-2">
                                @foreach($condiciones as $index => $cond)
                                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 flex-shrink-0">
                                                {{ $opcionesTipoCondicion[$cond['tipo']] ?? $cond['tipo'] }}
                                            </span>
                                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $cond['descripcion'] }}</span>
                                        </div>
                                        <button wire:click="eliminarCondicion({{ $index }})"
                                                class="text-red-500 hover:text-red-700 p-1 flex-shrink-0">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6 bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Sin condiciones - la lista aplicara siempre') }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Card: Agregar condición --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Agregar condicion') }}</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Tipo de condicion') }}</label>
                                <select wire:model.live="nuevaCondicionTipo"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">{{ __('Seleccionar...') }}</option>
                                    @foreach($opcionesTipoCondicion as $valor => $label)
                                        <option value="{{ $valor }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Campos segun tipo --}}
                            @if($nuevaCondicionTipo == 'por_forma_pago')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Forma de pago') }}</label>
                                    <select wire:model="nuevaCondicionFormaPagoId"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->formasPago as $fp)
                                            <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_forma_venta')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Forma de venta') }}</label>
                                    <select wire:model="nuevaCondicionFormaVentaId"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->formasVenta as $fv)
                                            <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_canal')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Canal de venta') }}</label>
                                    <select wire:model="nuevaCondicionCanalVentaId"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->canalesVenta as $cv)
                                            <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_total_compra')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Monto minimo') }}</label>
                                    <input type="number"
                                           wire:model="nuevaCondicionMontoMinimo"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Monto maximo') }}</label>
                                    <input type="number"
                                           wire:model="nuevaCondicionMontoMaximo"
                                           step="0.01"
                                           min="0"
                                           :placeholder="__('Sin limite')"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                </div>
                            @endif
                        </div>

                        @if($nuevaCondicionTipo)
                            <button wire:click="agregarCondicion"
                                    class="mt-4 inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-sm transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ __('Agregar Condicion') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- PASO 5: Articulos especificos --}}
            @if($pasoActual == 5)
                <div class="space-y-4">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Articulos y Categorias Especificos') }}</h2>

                    {{-- Callout info con ajuste global --}}
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                <strong>{{ __('Opcional') }}:</strong> {{ __('Si no agregas ningun articulo o categoria, esta lista aplicara el ajuste de') }}
                                <span class="font-bold {{ $ajustePorcentaje > 0 ? 'text-red-600 dark:text-red-400' : ($ajustePorcentaje < 0 ? 'text-green-600 dark:text-green-400' : '') }}">
                                    {{ $ajustePorcentaje > 0 ? '+' : '' }}@porcentaje($ajustePorcentaje)
                                </span>
                                {{ __('a') }} <strong>{{ __('todos los articulos') }}</strong>.
                            </p>
                        </div>
                    </div>

                    {{-- Card: Agregar artículos/categorías --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Agregar a la lista') }}</h3>
                        </div>

                        {{-- Buscar articulo --}}
                        <div class="relative mb-4"
                             x-data="{
                                inputFocused: false,
                                selectedIndex: 0,
                                searchTimeout: null,
                                init() {
                                    this.$watch('inputFocused', (v) => { if (!v) this.selectedIndex = 0; });
                                },
                                get resultCount() {
                                    return this.$refs.resultsList ? this.$refs.resultsList.querySelectorAll('[data-result-item]').length : 0;
                                },
                                moveUp() {
                                    if (this.selectedIndex > 0) this.selectedIndex--;
                                    this.scrollToSelected();
                                },
                                moveDown() {
                                    if (this.selectedIndex < this.resultCount - 1) this.selectedIndex++;
                                    this.scrollToSelected();
                                },
                                scrollToSelected() {
                                    this.$nextTick(() => {
                                        const items = this.$refs.resultsList?.querySelectorAll('[data-result-item]');
                                        if (items && items[this.selectedIndex]) {
                                            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
                                        }
                                    });
                                },
                                selectCurrent() {
                                    const items = this.$refs.resultsList?.querySelectorAll('[data-result-item]');
                                    if (items && items[this.selectedIndex]) {
                                        items[this.selectedIndex].click();
                                    } else {
                                        $wire.agregarPrimerArticuloBusqueda();
                                    }
                                },
                                handleInput() {
                                    this.selectedIndex = 0;
                                    clearTimeout(this.searchTimeout);
                                    this.searchTimeout = setTimeout(() => { $wire.$refresh(); }, 350);
                                }
                             }"
                             @click.outside="inputFocused = false">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Buscar articulo') }}</label>
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text"
                                       x-ref="inputBusqueda"
                                       wire:model="busquedaArticulo"
                                       @input="handleInput()"
                                       @focus="inputFocused = true"
                                       @keydown.arrow-up.prevent="moveUp()"
                                       @keydown.arrow-down.prevent="moveDown()"
                                       @keydown.enter.prevent="selectCurrent()"
                                       @keydown.escape="inputFocused = false; $el.blur()"
                                       autocomplete="off"
                                       :placeholder="__('Buscar por nombre, codigo, codigo de barras o categoria...')"
                                       class="w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>

                            {{-- Dropdown de resultados --}}
                            @if(count($articulosEncontrados) > 0)
                                <div x-show="inputFocused"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-ref="resultsList"
                                     class="absolute z-30 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-xl max-h-72 overflow-auto">
                                    @foreach($articulosEncontrados as $idx => $art)
                                        <button type="button"
                                                data-result-item
                                                wire:click="agregarArticulo({{ $art['id'] }})"
                                                @mouseenter="selectedIndex = {{ $idx }}"
                                                :class="selectedIndex === {{ $idx }} ? 'bg-bcn-primary/10 dark:bg-bcn-primary/20' : ''"
                                                class="w-full text-left px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-bcn-primary/10 dark:hover:bg-gray-700 focus:outline-none transition flex justify-between items-center">
                                            <div class="min-w-0 flex-1 pr-2">
                                                <div class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ $art['nombre'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                                    @if($art['codigo'])
                                                        {{ __('Código') }}: {{ $art['codigo'] }}
                                                    @endif
                                                    @if(!empty($art['codigo_barras']))
                                                        @if($art['codigo']) | @endif
                                                        {{ __('Barras') }}: {{ $art['codigo_barras'] }}
                                                    @endif
                                                    @if(!empty($art['categoria_nombre']))
                                                        <span class="ml-1 text-bcn-primary">· {{ $art['categoria_nombre'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="font-mono text-sm text-gray-600 dark:text-gray-300 flex-shrink-0">$@precio($art['precio_base'])</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen($busquedaArticulo) >= 2)
                                <div x-show="inputFocused"
                                     class="absolute z-30 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-xl p-4">
                                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No se encontraron artículos') }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Agregar por categoria --}}
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-600">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('O agregar por categoria') }}</label>
                            <div class="flex gap-2">
                                <select wire:model="categoriaSeleccionadaId"
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">{{ __('Seleccionar categoria...') }}</option>
                                    @foreach($this->categorias as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                    @endforeach
                                </select>
                                <button type="button"
                                        wire:click="agregarCategoriaSeleccionada"
                                        class="inline-flex items-center px-4 py-2 bg-bcn-primary hover:bg-bcn-primary/90 text-white rounded-lg text-sm font-medium transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ __('Agregar') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Card: Lista de artículos/categorías agregados --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Artículos y categorías en la lista') }}</h3>
                            </div>
                            @if(count($articulosEspecificos) > 0)
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-bcn-primary/10 text-bcn-primary dark:bg-bcn-primary/20">
                                    {{ count($articulosEspecificos) }}
                                </span>
                            @endif
                        </div>

                        @if(count($articulosEspecificos) > 0)
                            <div x-data="{
                                    focus(index, col) {
                                        const el = document.querySelector('[data-cell=\"'+col+'-'+index+'\"]');
                                        if (el) { el.focus(); el.select && el.select(); }
                                    }
                                 }">
                                {{-- Cards móvil --}}
                                <div class="sm:hidden space-y-2">
                                    @foreach($articulosEspecificos as $index => $art)
                                        <div wire:key="art-m-{{ $index }}" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                                            <div class="flex items-start justify-between mb-2">
                                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                                    <span @class([
                                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium flex-shrink-0',
                                                        'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200' => $art['tipo'] == 'articulo',
                                                        'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200' => $art['tipo'] == 'categoria',
                                                    ])>
                                                        {{ $art['tipo'] == 'articulo' ? __('Art') : __('Cat') }}
                                                    </span>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $art['nombre'] }}</span>
                                                </div>
                                                <button type="button"
                                                        wire:click.prevent.stop="eliminarArticuloEspecifico({{ $index }})"
                                                        class="text-red-500 hover:text-red-700 p-1 flex-shrink-0">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            @if($art['precio_base_original'])
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                    {{ __('Precio base') }}: <span class="font-mono text-gray-700 dark:text-gray-300">$@precio($art['precio_base_original'])</span>
                                                </div>
                                            @endif
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Ajuste %') }}</label>
                                                    <div class="relative">
                                                        <input type="number"
                                                               wire:model.blur="articulosEspecificos.{{ $index }}.ajuste_porcentaje"
                                                               wire:change="recalcularPrecioFinalDesdePorcentaje({{ $index }})"
                                                               step="0.01"
                                                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-6">
                                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-xs pointer-events-none">%</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Precio final') }}</label>
                                                    @if($art['tipo'] == 'articulo')
                                                        <input type="number"
                                                               wire:model.blur="articulosEspecificos.{{ $index }}.precio_fijo"
                                                               wire:change="recalcularPorcentajeDesdeMontoFijo({{ $index }})"
                                                               step="0.01"
                                                               min="0"
                                                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    @else
                                                        <div class="w-full text-sm text-gray-400 dark:text-gray-500 py-2">{{ __('N/A') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Tabla desktop --}}
                                <div class="hidden sm:block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">{{ __('Articulo/Categoria') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">{{ __('Precio Base') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">{{ __('Ajuste %') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">{{ __('Precio Final') }}</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($articulosEspecificos as $index => $art)
                                                <tr wire:key="art-{{ $index }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            <span @class([
                                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                                'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200' => $art['tipo'] == 'articulo',
                                                                'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200' => $art['tipo'] == 'categoria',
                                                            ])>
                                                                {{ $art['tipo'] == 'articulo' ? __('Art') : __('Cat') }}
                                                            </span>
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-300">
                                                        @if($art['precio_base_original'])
                                                            $@precio($art['precio_base_original'])
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="relative w-28">
                                                            <input type="number"
                                                                   data-cell="pct-{{ $index }}"
                                                                   wire:model.blur="articulosEspecificos.{{ $index }}.ajuste_porcentaje"
                                                                   wire:change="recalcularPrecioFinalDesdePorcentaje({{ $index }})"
                                                                   x-on:keydown.arrow-up.prevent="focus({{ $index - 1 }}, 'pct')"
                                                                   x-on:keydown.arrow-down.prevent="focus({{ $index + 1 }}, 'pct')"
                                                                   step="0.01"
                                                                   class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-6">
                                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-xs pointer-events-none">%</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        @if($art['tipo'] == 'articulo')
                                                            <input type="number"
                                                                   data-cell="final-{{ $index }}"
                                                                   wire:model.blur="articulosEspecificos.{{ $index }}.precio_fijo"
                                                                   wire:change="recalcularPorcentajeDesdeMontoFijo({{ $index }})"
                                                                   x-on:keydown.arrow-up.prevent="focus({{ $index - 1 }}, 'final')"
                                                                   x-on:keydown.arrow-down.prevent="focus({{ $index + 1 }}, 'final')"
                                                                   step="0.01"
                                                                   min="0"
                                                                   class="w-32 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                        @else
                                                            <span class="text-gray-400 dark:text-gray-500 text-sm">{{ __('N/A') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button"
                                                                wire:click.prevent.stop="eliminarArticuloEspecifico({{ $index }})"
                                                                class="text-red-500 hover:text-red-700 p-1">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Sin articulos especificos - se aplicara') }}
                                    <span class="font-bold {{ $ajustePorcentaje > 0 ? 'text-red-600 dark:text-red-400' : ($ajustePorcentaje < 0 ? 'text-green-600 dark:text-green-400' : '') }}">
                                        {{ $ajustePorcentaje > 0 ? '+' : '' }}@porcentaje($ajustePorcentaje)
                                    </span>
                                    {{ __('a todos los articulos') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Botones de navegacion --}}
            <div class="flex justify-between mt-8 pt-6 border-t">
                <div>
                    @if($pasoActual > 1)
                        <button type="button" wire:click="anterior"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            {{ __('Anterior') }}
                        </button>
                    @endif
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('configuracion.precios') }}"
                       wire:navigate
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        {{ __('Cancelar') }}
                    </a>

                    @if($pasoActual < $totalPasos)
                        <button type="button" wire:click="siguiente"
                                class="inline-flex items-center px-6 py-2 bg-bcn-primary text-white rounded-lg hover:bg-bcn-primary/90 font-medium transition">
                            {{ __('Siguiente') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @else
                        <button type="button" wire:click="guardar"
                                class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $modoEdicion ? __('Guardar Cambios') : __('Crear Lista') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
