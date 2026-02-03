<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
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
                            <span class="mt-1 text-xs hidden sm:block text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                        @elseif($numPaso == $pasoActual)
                            {{-- Paso actual --}}
                            <button class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-bcn-primary text-white ring-4 ring-bcn-primary ring-opacity-30">
                                {{ $numPaso }}
                            </button>
                            <span class="mt-1 text-xs hidden sm:block text-bcn-primary font-medium">{{ $labelPaso }}</span>
                        @else
                            {{-- Paso futuro --}}
                            @if($modoEdicion)
                                {{-- En modo edición, permitir click en pasos futuros --}}
                                <button wire:click="irAPaso({{ $numPaso }})"
                                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 cursor-pointer hover:bg-gray-300 dark:hover:bg-gray-600">
                                    {{ $numPaso }}
                                </button>
                                <span class="mt-1 text-xs hidden sm:block text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                            @else
                                {{-- En modo creación, pasos futuros deshabilitados --}}
                                <button disabled class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                                    {{ $numPaso }}
                                </button>
                                <span class="mt-1 text-xs hidden sm:block text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
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
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b pb-2">{{ __('Datos Basicos') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Sucursal --}}
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursal') }} *</label>
                            <select wire:model="sucursalId"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <option value="">{{ __('Seleccionar sucursal...') }}</option>
                                @foreach($this->sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                @endforeach
                            </select>
                            @error('sucursalId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Nombre --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                            <input type="text"
                                   wire:model="nombre"
                                   :placeholder="__('Ej: Lista Mayoristas')"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Codigo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Codigo (opcional)') }}</label>
                            <input type="text"
                                   wire:model="codigo"
                                   :placeholder="__('Ej: MAYOR-001')"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>

                        {{-- Prioridad --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Prioridad') }} *</label>
                            <input type="number"
                                   wire:model="prioridad"
                                   min="1"
                                   max="999"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Menor numero = mayor prioridad (1 es maxima)') }}</p>
                            @error('prioridad') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Descripcion --}}
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripcion (opcional)') }}</label>
                            <textarea wire:model="descripcion"
                                      rows="2"
                                      :placeholder="__('Describe el proposito de esta lista...')"
                                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                        </div>
                    </div>
                </div>
            @endif

            {{-- PASO 2: Configuracion de precios --}}
            @if($pasoActual == 2)
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b pb-2">{{ __('Configuracion de Precios') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Tipo de Ajuste + Porcentaje --}}
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Ajuste de Precios') }} *</label>
                            <div class="flex items-center gap-3">
                                <select wire:model.live="tipoAjuste"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="recargo">{{ __('Recargo') }}</option>
                                    <option value="descuento">{{ __('Descuento') }}</option>
                                </select>
                                <div class="relative flex-1 max-w-[150px]">
                                    <input type="number"
                                           wire:model.blur="porcentajeAbsoluto"
                                           step="0.01"
                                           min="0"
                                           max="1000"
                                           placeholder="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-8">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">%</span>
                                </div>
                                <span class="text-sm font-medium {{ $ajustePorcentaje > 0 ? 'text-red-600' : ($ajustePorcentaje < 0 ? 'text-green-600' : 'text-gray-600 dark:text-gray-300') }}">
                                    @if($ajustePorcentaje != 0)
                                        ({{ $ajustePorcentaje > 0 ? '+' : '' }}@porcentaje($ajustePorcentaje))
                                    @else
                                        ({{ __('sin ajuste') }})
                                    @endif
                                </span>
                            </div>
                            @error('ajustePorcentaje') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Redondeo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Redondeo') }}</label>
                            <select wire:model="redondeo"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                @foreach($opcionesRedondeo as $valor => $label)
                                    <option value="{{ $valor }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Aplica Promociones (oculto para lista base) --}}
                        @if(!$esListaBase)
                            <div class="sm:col-span-2 pt-2 border-t">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Permite aplicar promociones') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Las promociones se calculan usando los precios de esta lista como base') }}</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="aplicaPromociones" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-bcn-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white dark:bg-gray-800 after:border-gray-300 dark:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bcn-primary"></div>
                                    </label>
                                </div>
                            </div>

                            {{-- Alcance Promociones --}}
                            @if($aplicaPromociones)
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Alcance de Promociones') }}</label>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-all {{ $promocionesAlcance === 'todos' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}">
                                            <input type="radio" wire:model.live="promocionesAlcance" value="todos"
                                                   class="mt-0.5 text-bcn-primary focus:ring-bcn-primary">
                                            <div>
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('A toda la venta') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Todos los artículos participan en el cálculo de promociones') }}</p>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-all {{ $promocionesAlcance === 'excluir_lista' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}">
                                            <input type="radio" wire:model.live="promocionesAlcance" value="excluir_lista"
                                                   class="mt-0.5 text-bcn-primary focus:ring-bcn-primary">
                                            <div>
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Excluir artículos con precio en esta lista') }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Los artículos que tengan un precio especial en esta lista no participan en promociones') }}</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Preview del precio --}}
                    @php
                        $precioPreview = 1000 * (1 + ($ajustePorcentaje / 100));
                    @endphp
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mt-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Vista previa') }}</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ __('Si un articulo tiene precio base de') }} <span class="font-mono font-bold">$1.000,00</span>,
                            {{ __('con esta lista el precio sera') }}:
                            <span class="font-mono font-bold {{ $ajustePorcentaje > 0 ? 'text-red-600' : ($ajustePorcentaje < 0 ? 'text-green-600' : 'text-bcn-primary') }}">
                                $@precio($precioPreview)
                            </span>
                        </p>
                    </div>
                </div>
            @endif

            {{-- PASO 3: Vigencia --}}
            @if($pasoActual == 3)
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b pb-2">{{ __('Vigencia y Restricciones Horarias') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Fecha desde --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Vigente desde') }}</label>
                            <input type="date"
                                   wire:model="vigenciaDesde"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Dejar vacio para sin restriccion') }}</p>
                        </div>

                        {{-- Fecha hasta --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Vigente hasta') }}</label>
                            <input type="date"
                                   wire:model="vigenciaHasta"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>

                        {{-- Dias de la semana --}}
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Dias de la semana') }}</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($opcionesDiasSemana as $valor => $label)
                                    <label @class([
                                        'inline-flex items-center px-3 py-2 rounded-lg border cursor-pointer transition-colors',
                                        'bg-bcn-primary text-white border-bcn-primary' => in_array($valor, $diasSemana),
                                        'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-bcn-primary' => !in_array($valor, $diasSemana),
                                    ])>
                                        <input type="checkbox"
                                               wire:model.live="diasSemana"
                                               value="{{ $valor }}"
                                               class="sr-only">
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('No seleccionar ninguno = todos los dias') }}</p>
                        </div>

                        {{-- Hora desde --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hora desde') }}</label>
                            <input type="time"
                                   wire:model="horaDesde"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>

                        {{-- Hora hasta --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hora hasta') }}</label>
                            <input type="time"
                                   wire:model="horaHasta"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>

                        {{-- Cantidad minima --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad minima') }}</label>
                            <input type="number"
                                   wire:model="cantidadMinima"
                                   step="0.001"
                                   min="0"
                                   :placeholder="__('Ej: 10')"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Cantidad minima por linea de venta') }}</p>
                        </div>

                        {{-- Cantidad maxima --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cantidad maxima') }}</label>
                            <input type="number"
                                   wire:model="cantidadMaxima"
                                   step="0.001"
                                   min="0"
                                   :placeholder="__('Ej: 100')"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>
                    </div>
                </div>
            @endif

            {{-- PASO 4: Condiciones --}}
            @if($pasoActual == 4)
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b pb-2">{{ __('Condiciones de Aplicacion') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Define cuando debe aplicarse esta lista. Todas las condiciones deben cumplirse (AND).') }}</p>

                    {{-- Lista de condiciones existentes --}}
                    @if(count($condiciones) > 0)
                        <div class="space-y-2">
                            @foreach($condiciones as $index => $cond)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                            {{ $opcionesTipoCondicion[$cond['tipo']] ?? $cond['tipo'] }}
                                        </span>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $cond['descripcion'] }}</span>
                                    </div>
                                    <button wire:click="eliminarCondicion({{ $index }})"
                                            class="text-red-500 hover:text-red-700 p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Sin condiciones - la lista aplicara siempre') }}</p>
                        </div>
                    @endif

                    {{-- Agregar nueva condicion --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Agregar condicion') }}</h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de condicion') }}</label>
                                <select wire:model.live="nuevaCondicionTipo"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">{{ __('Seleccionar...') }}</option>
                                    @foreach($opcionesTipoCondicion as $valor => $label)
                                        <option value="{{ $valor }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Campos segun tipo --}}
                            @if($nuevaCondicionTipo == 'por_forma_pago')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de pago') }}</label>
                                    <select wire:model="nuevaCondicionFormaPagoId"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->formasPago as $fp)
                                            <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_forma_venta')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Forma de venta') }}</label>
                                    <select wire:model="nuevaCondicionFormaVentaId"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->formasVenta as $fv)
                                            <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_canal')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Canal de venta') }}</label>
                                    <select wire:model="nuevaCondicionCanalVentaId"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        <option value="">{{ __('Seleccionar...') }}</option>
                                        @foreach($this->canalesVenta as $cv)
                                            <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($nuevaCondicionTipo == 'por_total_compra')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto minimo') }}</label>
                                    <input type="number"
                                           wire:model="nuevaCondicionMontoMinimo"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto maximo') }}</label>
                                    <input type="number"
                                           wire:model="nuevaCondicionMontoMaximo"
                                           step="0.01"
                                           min="0"
                                           :placeholder="__('Sin limite')"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                </div>
                            @endif
                        </div>

                        @if($nuevaCondicionTipo)
                            <button wire:click="agregarCondicion"
                                    class="mt-4 inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b pb-2">{{ __('Articulos y Categorias Especificos') }}</h3>

                    {{-- Mensaje informativo --}}
                    <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                <strong>{{ __('Opcional') }}:</strong> {{ __('Si no agregas ningun articulo o categoria, esta lista aplicara el ajuste de') }}
                                <span class="font-bold {{ $ajustePorcentaje > 0 ? 'text-red-600' : ($ajustePorcentaje < 0 ? 'text-green-600' : '') }}">
                                    {{ $ajustePorcentaje > 0 ? '+' : '' }}@porcentaje($ajustePorcentaje)
                                </span>
                                {{ __('a') }} <strong>{{ __('todos los articulos') }}</strong>.
                            </p>
                        </div>
                    </div>

                    {{-- Buscar articulo --}}
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar articulo') }}</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="busquedaArticulo"
                               :placeholder="__('Escribe para buscar por nombre o codigo...')"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">

                        {{-- Resultados de busqueda --}}
                        @if(count($articulosEncontrados) > 0)
                            <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg max-h-60 overflow-auto">
                                @foreach($articulosEncontrados as $art)
                                    <button wire:click="agregarArticulo({{ $art['id'] }})"
                                            class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 flex justify-between items-center">
                                        <div>
                                            <span class="font-medium">{{ $art['nombre'] }}</span>
                                            @if($art['codigo'])
                                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $art['codigo'] }}</span>
                                            @endif
                                        </div>
                                        <span class="text-sm text-gray-600 dark:text-gray-300">$@precio($art['precio_base'])</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Agregar por categoria --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('O agregar por categoria') }}</label>
                        <div class="flex gap-2">
                            <select id="selectCategoria"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                <option value="">{{ __('Seleccionar categoria...') }}</option>
                                @foreach($this->categorias as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                            <button onclick="agregarCategoriaSeleccionada()"
                                    class="px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                {{ __('Agregar') }}
                            </button>
                        </div>
                    </div>

                    {{-- Lista de articulos/categorias agregados --}}
                    @if(count($articulosEspecificos) > 0)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Articulo/Categoria') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio Base') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Precio Fijo') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ajuste %') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($articulosEspecificos as $index => $art)
                                        <tr wire:key="art-{{ $index }}">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <span @class([
                                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                        'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' => $art['tipo'] == 'articulo',
                                                        'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200' => $art['tipo'] == 'categoria',
                                                    ])>
                                                        {{ $art['tipo'] == 'articulo' ? __('Art') : __('Cat') }}
                                                    </span>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                @if($art['precio_base_original'])
                                                    $@precio($art['precio_base_original'])
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($art['tipo'] == 'articulo')
                                                    <input type="number"
                                                           wire:model.blur="articulosEspecificos.{{ $index }}.precio_fijo"
                                                           wire:change="recalcularPorcentajeDesdeMontoFijo({{ $index }})"
                                                           step="0.01"
                                                           min="0"
                                                           :placeholder="__('Auto')"
                                                           class="w-24 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-sm">{{ __('N/A') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1">
                                                    <input type="number"
                                                           wire:model.blur="articulosEspecificos.{{ $index }}.ajuste_porcentaje"
                                                           step="0.01"
                                                           class="w-20 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    <span class="text-gray-500 dark:text-gray-400">%</span>
                                                </div>
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
                    @else
                        <div class="text-center py-8 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Sin articulos especificos - se aplicara') }}
                                <span class="font-bold {{ $ajustePorcentaje > 0 ? 'text-red-600' : ($ajustePorcentaje < 0 ? 'text-green-600' : '') }}">
                                    {{ $ajustePorcentaje > 0 ? '+' : '' }}@porcentaje($ajustePorcentaje)
                                </span>
                                {{ __('a todos los articulos') }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Botones de navegacion --}}
            <div class="flex justify-between mt-8 pt-6 border-t">
                <div>
                    @if($pasoActual > 1)
                        <button wire:click="anterior"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            {{ __('Anterior') }}
                        </button>
                    @endif
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('configuracion.precios') }}"
                       wire:navigate
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        {{ __('Cancelar') }}
                    </a>

                    @if($pasoActual < $totalPasos)
                        <button wire:click="siguiente"
                                class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Siguiente') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @else
                        <button wire:click="guardar"
                                class="inline-flex items-center px-6 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
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

    <script>
        function agregarCategoriaSeleccionada() {
            const select = document.getElementById('selectCategoria');
            const categoriaId = select.value;
            if (categoriaId) {
                @this.call('agregarCategoria', categoriaId);
                select.value = '';
            }
        }
    </script>
</div>
