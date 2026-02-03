<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('configuracion.promociones-especiales') }}"
                   wire:navigate
                   class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
                        {{ $modoEdicion ? __('Editar Promocion Especial') : __('Nueva Promocion Especial') }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        @if($modoEdicion)
                            {{ __('Paso') }} {{ $pasoActual - 1 }} {{ __('de') }} {{ $totalPasos - 1 }}
                        @else
                            {{ __('Paso') }} {{ $pasoActual }} {{ __('de') }} {{ $totalPasos }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Progress Steps --}}
        <div class="mb-6">
            <div class="flex items-center justify-between">
                @php
                    // En modo edicion, no mostramos el paso 1 (tipo)
                    $pasos = $modoEdicion
                        ? [2 => 'Config', 3 => 'Condiciones', 4 => 'Simulador']
                        : [1 => 'Tipo', 2 => 'Config', 3 => 'Condiciones', 4 => 'Simulador'];
                    $indexPaso = 0;
                @endphp
                @foreach($pasos as $numPaso => $labelPaso)
                    @php $indexPaso++; @endphp
                    <div class="flex flex-col items-center flex-1">
                        @if($modoEdicion)
                            {{-- En modo edicion, todos los pasos son clickeables --}}
                            @if($numPaso == $pasoActual)
                                {{-- Paso actual --}}
                                <button class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-bcn-primary text-white ring-4 ring-bcn-primary ring-opacity-30">
                                    {{ $indexPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-bcn-primary font-medium">{{ $labelPaso }}</span>
                            @else
                                {{-- Paso clickeable --}}
                                <button wire:click="irAPaso({{ $numPaso }})"
                                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-blue-100 text-blue-600 cursor-pointer hover:bg-bcn-primary hover:text-white border-2 border-blue-200 hover:border-bcn-primary">
                                    {{ $indexPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                            @endif
                        @else
                            {{-- Modo creacion: navegacion normal --}}
                            @if($numPaso < $pasoActual)
                                {{-- Paso completado - clickeable --}}
                                <button wire:click="irAPaso({{ $numPaso }})"
                                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-green-500 dark:bg-green-600 text-white cursor-pointer hover:bg-green-600 dark:hover:bg-green-500">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                {{-- Paso futuro - deshabilitado --}}
                                <button disabled class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                                    {{ $numPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-gray-400 dark:text-gray-500">{{ $labelPaso }}</span>
                            @endif
                        @endif
                    </div>
                    @if($indexPaso < count($pasos))
                        {{-- Linea conectora: en edicion siempre azul claro, en creacion verde si completado --}}
                        @if($modoEdicion)
                            <div class="flex-1 h-1 mx-1 sm:mx-2 hidden sm:block bg-blue-200"></div>
                        @else
                            <div class="flex-1 h-1 mx-1 sm:mx-2 hidden sm:block {{ $numPaso < $pasoActual ? 'bg-green-500 dark:bg-green-600' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">

            {{-- PASO 1: Selección de Tipo --}}
            @if($pasoActual == 1)
                <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Paso 1: Tipo de Promocion Especial') }}</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- NxM Básico --}}
                    <button type="button" wire:click="seleccionarTipo('nxm')"
                            class="p-6 border-2 rounded-xl text-left transition-all hover:border-purple-500 hover:bg-purple-50 dark:bg-purple-900/20 dark:hover:bg-purple-900/20
                                   {{ $tipo === 'nxm' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20 dark:bg-purple-900/20' : 'border-gray-200 dark:border-gray-700 dark:border-gray-700' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center text-2xl">
                                🔢
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ __('NxM (2x1, 3x2...)') }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Lleva N unidades y paga M. Aplica a un artículo específico o categoría.') }}
                                </p>
                                <div class="mt-2 text-xs text-purple-600 font-medium">
                                    {{ __('Ejemplo: 2x1 en Coca Cola') }}
                                </div>
                            </div>
                        </div>
                    </button>

                    {{-- NxM Avanzado --}}
                    <button type="button" wire:click="seleccionarTipo('nxm_avanzado')"
                            class="p-6 border-2 rounded-xl text-left transition-all hover:border-indigo-500 hover:bg-indigo-50 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/20
                                   {{ $tipo === 'nxm_avanzado' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 dark:border-gray-700' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center text-2xl">
                                🎯
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ __('NxM Avanzado') }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Artículos que activan la promo diferentes a los bonificados.') }}
                                </p>
                                <div class="mt-2 text-xs text-indigo-600 font-medium">
                                    {{ __('Ejemplo: 3 Oreos → 1 Bebida gratis') }}
                                </div>
                            </div>
                        </div>
                    </button>

                    {{-- Combo/Pack --}}
                    <button type="button" wire:click="seleccionarTipo('combo')"
                            class="p-6 border-2 rounded-xl text-left transition-all hover:border-orange-500 hover:bg-orange-50 dark:bg-orange-900/20 dark:hover:bg-orange-900/20
                                   {{ $tipo === 'combo' ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-700 dark:border-gray-700' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center text-2xl">
                                📦
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ __('Combo / Pack') }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Artículos específicos con precio fijo o descuento porcentual.') }}
                                </p>
                                <div class="mt-2 text-xs text-orange-600 font-medium">
                                    {{ __('Ejemplo: 3 Alfajores a $500') }}
                                </div>
                            </div>
                        </div>
                    </button>

                    {{-- Menú --}}
                    <button type="button" wire:click="seleccionarTipo('menu')"
                            class="p-6 border-2 rounded-xl text-left transition-all hover:border-green-500 hover:bg-green-50 dark:bg-green-900/20 dark:hover:bg-green-900/20
                                   {{ $tipo === 'menu' ? 'border-green-500 bg-green-50 dark:bg-green-900/20 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700 dark:border-gray-700' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center text-2xl">
                                🍽️
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ __('Menú del Día') }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                    {{ __('Grupos con opciones intercambiables a precio fijo o con descuento.') }}
                                </p>
                                <div class="mt-2 text-xs text-green-600 font-medium">
                                    {{ __('Ejemplo: Plato + Bebida + Postre = $1500') }}
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            @endif

            {{-- PASO 2: Configuración --}}
            @if($pasoActual == 2)
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $modoEdicion ? __('Configuración') : __('Paso 2: Configuración') }}</h2>
                    @php
                        $tipoConfig = [
                            'nxm' => ['icon' => '🔢', 'label' => 'NxM', 'bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
                            'nxm_avanzado' => ['icon' => '🎯', 'label' => 'NxM Avanzado', 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-800'],
                            'combo' => ['icon' => '📦', 'label' => 'Combo/Pack', 'bg' => 'bg-orange-100', 'text' => 'text-orange-800'],
                            'menu' => ['icon' => '🍽️', 'label' => 'Menú', 'bg' => 'bg-green-100', 'text' => 'text-green-800'],
                        ][$tipo] ?? ['icon' => '📋', 'label' => $tipo, 'bg' => 'bg-gray-100 dark:bg-gray-700', 'text' => 'text-gray-800'];
                    @endphp
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium {{ $tipoConfig['bg'] }} {{ $tipoConfig['text'] }}">
                        {{ $tipoConfig['icon'] }} {{ $tipoConfig['label'] }}
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                    {{-- COLUMNA IZQUIERDA: Datos básicos (30%) --}}
                    <div class="lg:col-span-3 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} *</label>
                            <input type="text" wire:model="nombre" :placeholder="__('Ej: 2x1 en Bebidas')"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción') }}</label>
                            <textarea wire:model="descripcion" rows="2" :placeholder="__('Descripción opcional...')"
                                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                        </div>

                        {{-- Sucursales --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            @if($modoEdicion)
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Sucursal') }} *</label>
                                <select wire:model.live="sucursalesSeleccionadas.0"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    @foreach($sucursales as $sucursal)
                                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                    @endforeach
                                </select>
                            @else
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sucursales') }} *</label>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="$set('sucursalesSeleccionadas', {{ $sucursales->pluck('id')->toJson() }})"
                                                class="text-xs text-blue-600 hover:text-blue-800">{{ __('Todas') }}</button>
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <button type="button" wire:click="$set('sucursalesSeleccionadas', [])"
                                                class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">{{ __('Ninguna') }}</button>
                                    </div>
                                </div>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    @foreach($sucursales as $sucursal)
                                        <label class="flex items-center p-1.5 rounded hover:bg-white dark:hover:bg-gray-600 cursor-pointer transition">
                                            <input type="checkbox" wire:model.live="sucursalesSeleccionadas" value="{{ $sucursal->id }}"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $sucursal->nombre }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if($modoEdicion)
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="activo" class="rounded text-bcn-primary focus:ring-bcn-primary w-5 h-5">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Promoción activa') }}</span>
                                </label>
                            </div>
                        @endif
                    </div>

                    {{-- COLUMNA DERECHA: Configuración específica (70%) --}}
                    <div class="lg:col-span-7">
                        {{-- NxM BÁSICO --}}
                        @if($tipo === 'nxm')
                            <div class="bg-purple-50 dark:bg-purple-900/20 dark:bg-purple-900/20 rounded-lg p-4 space-y-4">
                                <h3 class="font-semibold text-purple-900 dark:text-purple-300 dark:text-purple-300">{{ __('Configuración NxM') }}</h3>

                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer mb-3">
                                        <input type="checkbox" wire:model.live="usarEscalas" class="rounded text-purple-600 focus:ring-purple-500">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Usar escalas progresivas') }}</span>
                                    </label>
                                </div>

                                @if(!$usarEscalas)
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 space-y-3">
                                        {{-- Lleva / Bonifica --}}
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Lleva') }}</span>
                                            <input type="number" wire:model="nxmLleva" min="2" max="99"
                                                   class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bonifica') }}</span>
                                            @if($beneficioTipo === 'descuento')
                                                <input type="number" value="1" disabled
                                                       class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 font-bold bg-gray-100 dark:bg-gray-700 cursor-not-allowed"
                                                       :title="__('Cuando es descuento %, siempre se bonifica 1 unidad')">
                                            @else
                                                <input type="number" wire:model="nxmBonifica" min="1" max="98"
                                                       class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                                            @endif
                                        </div>

                                        {{-- Tipo de beneficio --}}
                                        <div class="flex items-center gap-4 pt-2 border-t dark:border-gray-600">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Beneficio:') }}</span>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" wire:model.live="beneficioTipo" value="gratis"
                                                       class="text-purple-600 focus:ring-purple-500">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Gratis') }}</span>
                                            </label>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" wire:model.live="beneficioTipo" value="descuento"
                                                       class="text-purple-600 focus:ring-purple-500">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Descuento %') }}</span>
                                            </label>
                                            @if($beneficioTipo === 'descuento')
                                                <div class="flex items-center gap-1">
                                                    <input type="number" wire:model="beneficioPorcentaje" min="1" max="99"
                                                           class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold text-sm">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Resumen --}}
                                        @php
                                            $bonificaReal = $beneficioTipo === 'descuento' ? 1 : $nxmBonifica;
                                        @endphp
                                        @if($nxmLleva && $bonificaReal && $bonificaReal < $nxmLleva)
                                            <div class="pt-2 border-t">
                                                <span class="px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                                    @if($beneficioTipo === 'gratis')
                                                        {{ $bonificaReal }} {{ __('gratis') }}
                                                    @else
                                                        1 {{ __('con') }} {{ $beneficioPorcentaje }}% {{ __('dto') }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    @include('livewire.configuracion.promociones-especiales.partials.escalas')
                                @endif

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aplica a:') }}</label>
                                    <div class="flex gap-3 mb-2">
                                        <label class="flex items-center gap-1 cursor-pointer text-sm">
                                            <input type="radio" wire:model.live="nxmAplicaA" value="articulo" class="text-purple-600 focus:ring-purple-500">
                                            <span class="text-gray-700 dark:text-gray-300">{{ __('Artículo') }}</span>
                                        </label>
                                        <label class="flex items-center gap-1 cursor-pointer text-sm">
                                            <input type="radio" wire:model.live="nxmAplicaA" value="categoria" class="text-purple-600 focus:ring-purple-500">
                                            <span class="text-gray-700 dark:text-gray-300">{{ __('Categoría') }}</span>
                                        </label>
                                    </div>

                                    @if($nxmAplicaA === 'articulo')
                                        @include('livewire.configuracion.promociones-especiales.partials.buscador-articulo', [
                                            'articuloId' => $nxmArticuloId,
                                            'busqueda' => $busquedaArticuloNxM,
                                            'resultados' => $articulosNxMResultados,
                                            'mostrar' => $mostrarBuscadorNxM,
                                            'abrirMethod' => 'abrirBuscadorNxM',
                                            'cerrarMethod' => 'cerrarBuscadorNxM',
                                            'seleccionarMethod' => 'seleccionarArticuloNxM',
                                            'limpiarMethod' => 'limpiarArticuloNxM',
                                            'busquedaModel' => 'busquedaArticuloNxM',
                                            'color' => 'purple',
                                        ])
                                    @else
                                        <select wire:model="nxmCategoriaId"
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring focus:ring-purple-500 focus:ring-opacity-50 text-sm">
                                            <option value="">{{ __('Seleccionar categoría...') }}</option>
                                            @foreach($categorias as $categoria)
                                                <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- NxM AVANZADO --}}
                        @if($tipo === 'nxm_avanzado')
                            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 space-y-4">
                                <h3 class="font-semibold text-indigo-900 dark:text-indigo-300">{{ __('Configuración NxM Avanzado') }}</h3>

                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer mb-3">
                                        <input type="checkbox" wire:model.live="usarEscalas" class="rounded text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Usar escalas progresivas') }}</span>
                                    </label>
                                </div>

                                @if(!$usarEscalas)
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 space-y-3">
                                        {{-- Lleva / Bonifica --}}
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Lleva') }}</span>
                                            <input type="number" wire:model="nxmLleva" min="2" max="99"
                                                   class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('bonifica') }}</span>
                                            @if($beneficioTipo === 'descuento')
                                                <input type="number" value="1" disabled
                                                       class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 font-bold bg-gray-100 dark:bg-gray-700 cursor-not-allowed"
                                                       :title="__('Cuando es descuento %, siempre se bonifica 1 unidad')">
                                            @else
                                                <input type="number" wire:model="nxmBonifica" min="1" max="98"
                                                       class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold">
                                            @endif
                                        </div>

                                        {{-- Tipo de beneficio --}}
                                        <div class="flex items-center gap-4 pt-2 border-t dark:border-gray-600">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Beneficio:') }}</span>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" wire:model.live="beneficioTipo" value="gratis"
                                                       class="text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Gratis') }}</span>
                                            </label>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" wire:model.live="beneficioTipo" value="descuento"
                                                       class="text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Descuento %') }}</span>
                                            </label>
                                            @if($beneficioTipo === 'descuento')
                                                <div class="flex items-center gap-1">
                                                    <input type="number" wire:model="beneficioPorcentaje" min="1" max="99"
                                                           class="w-16 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-bold text-sm">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Resumen --}}
                                        @php
                                            $bonificaRealAvz = $beneficioTipo === 'descuento' ? 1 : $nxmBonifica;
                                        @endphp
                                        @if($nxmLleva && $bonificaRealAvz && $bonificaRealAvz < $nxmLleva)
                                            <div class="pt-2 border-t">
                                                <span class="px-3 py-1.5 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">
                                                    @if($beneficioTipo === 'gratis')
                                                        {{ $bonificaRealAvz }} {{ __('gratis') }}
                                                    @else
                                                        1 {{ __('con') }} {{ $beneficioPorcentaje }}% {{ __('dto') }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    @include('livewire.configuracion.promociones-especiales.partials.escalas', ['color' => 'indigo'])
                                @endif

                                {{-- Artículos Trigger --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-indigo-900 dark:text-indigo-300">{{ __('Artículos que ACTIVAN (Triggers)') }}</span>
                                    </div>
                                    @foreach($gruposTrigger as $gIndex => $grupo)
                                        <div class="mb-2 p-2 bg-indigo-50 dark:bg-indigo-900/20 rounded">
                                            <div class="flex flex-wrap gap-1 mb-2">
                                                @foreach($grupo['articulos'] ?? [] as $aIndex => $art)
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-xs">
                                                        {{ $art['nombre'] }}
                                                        <button type="button" wire:click="eliminarArticuloTrigger({{ $gIndex }}, {{ $aIndex }})" class="text-indigo-600 hover:text-indigo-800">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                            <div class="flex gap-3">
                                                <button type="button" wire:click="abrirBuscadorTrigger({{ $gIndex }})"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800">{{ __('+ Agregar artículo') }}</button>
                                                <button type="button" wire:click="abrirCategoriasTrigger({{ $gIndex }})"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800">{{ __('+ Agregar por categoría') }}</button>
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($mostrarCategoriasTrigger)
                                        <div class="mt-2 p-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                                             x-data="{ }"
                                             @click.outside="$wire.cerrarCategoriasTrigger()">
                                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Seleccionar categoría:') }}</div>
                                            <div class="max-h-40 overflow-y-auto">
                                                @foreach($categorias as $cat)
                                                    <button type="button" wire:click="agregarArticulosPorCategoriaTrigger({{ $cat->id }})"
                                                            class="w-full px-2 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded flex justify-between items-center">
                                                        <span>{{ $cat->nombre }}</span>
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $cat->articulos_count ?? $cat->articulos()->where('activo', true)->count() }} art.</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                            <button type="button" wire:click="cerrarCategoriasTrigger" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('Cerrar') }}</button>
                                        </div>
                                    @endif

                                    @if($mostrarBuscadorTrigger)
                                        <div class="mt-2 p-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                                             x-data="{ }"
                                             @click.outside="$wire.cerrarBuscadorTrigger()">
                                            <input type="text"
                                                   wire:model.live.debounce.200ms="busquedaArticuloTrigger"
                                                   :placeholder="__('Buscar...')"
                                                   class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mb-2"
                                                   x-init="$el.focus()"
                                                   @keydown.enter.prevent="if ({{ count($articulosTriggerResultados) }} > 0) { $wire.seleccionarPrimerArticuloTrigger() }">
                                            <div class="max-h-32 overflow-y-auto">
                                                @foreach($articulosTriggerResultados as $art)
                                                    <button type="button" wire:click="agregarArticuloTrigger({{ $art['id'] }})"
                                                            class="w-full px-2 py-1 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">{{ $art['nombre'] }}</button>
                                                @endforeach
                                            </div>
                                            <button type="button" wire:click="cerrarBuscadorTrigger" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('Cerrar') }}</button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Artículos Reward --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-green-900 dark:text-green-300">{{ __('Artículos BONIFICABLES (Rewards)') }}</span>
                                    </div>
                                    @foreach($gruposReward as $gIndex => $grupo)
                                        <div class="mb-2 p-2 bg-green-50 dark:bg-green-900/20 rounded">
                                            <div class="flex flex-wrap gap-1 mb-2">
                                                @foreach($grupo['articulos'] ?? [] as $aIndex => $art)
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                                        {{ $art['nombre'] }}
                                                        <button type="button" wire:click="eliminarArticuloReward({{ $gIndex }}, {{ $aIndex }})" class="text-green-600 hover:text-green-800">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                            <div class="flex gap-3">
                                                <button type="button" wire:click="abrirBuscadorReward({{ $gIndex }})"
                                                        class="text-xs text-green-600 hover:text-green-800">{{ __('+ Agregar artículo') }}</button>
                                                <button type="button" wire:click="abrirCategoriasReward({{ $gIndex }})"
                                                        class="text-xs text-green-600 hover:text-green-800">{{ __('+ Agregar por categoría') }}</button>
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($mostrarCategoriasReward)
                                        <div class="mt-2 p-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                                             x-data="{ }"
                                             @click.outside="$wire.cerrarCategoriasReward()">
                                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Seleccionar categoría:') }}</div>
                                            <div class="max-h-40 overflow-y-auto">
                                                @foreach($categorias as $cat)
                                                    <button type="button" wire:click="agregarArticulosPorCategoriaReward({{ $cat->id }})"
                                                            class="w-full px-2 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-green-50 dark:hover:bg-green-900/30 rounded flex justify-between items-center">
                                                        <span>{{ $cat->nombre }}</span>
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $cat->articulos_count ?? $cat->articulos()->where('activo', true)->count() }} art.</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                            <button type="button" wire:click="cerrarCategoriasReward" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('Cerrar') }}</button>
                                        </div>
                                    @endif

                                    @if($mostrarBuscadorReward)
                                        <div class="mt-2 p-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                                             x-data="{ }"
                                             @click.outside="$wire.cerrarBuscadorReward()">
                                            <input type="text"
                                                   wire:model.live.debounce.200ms="busquedaArticuloReward"
                                                   :placeholder="__('Buscar...')"
                                                   class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mb-2"
                                                   x-init="$el.focus()"
                                                   @keydown.enter.prevent="if ({{ count($articulosRewardResultados) }} > 0) { $wire.seleccionarPrimerArticuloReward() }">
                                            <div class="max-h-32 overflow-y-auto">
                                                @foreach($articulosRewardResultados as $art)
                                                    <button type="button" wire:click="agregarArticuloReward({{ $art['id'] }})"
                                                            class="w-full px-2 py-1 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-green-50 dark:hover:bg-green-900/30">{{ $art['nombre'] }}</button>
                                                @endforeach
                                            </div>
                                            <button type="button" wire:click="cerrarBuscadorReward" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('Cerrar') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- COMBO --}}
                        @if($tipo === 'combo')
                            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 space-y-4">
                                <h3 class="font-semibold text-orange-900 dark:text-orange-300">{{ __('Artículos del Combo') }}</h3>

                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @forelse($comboItems as $index => $item)
                                        <div class="flex items-center gap-2 p-2 bg-white dark:bg-gray-800 border border-orange-200 rounded-lg">
                                            <div class="flex-1 min-w-0">
                                                <span class="font-medium text-gray-900 dark:text-white text-sm truncate block">{{ $item['nombre'] }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">$@precio($item['precio_unitario']) c/u</span>
                                            </div>
                                            <input type="number" wire:model="comboItems.{{ $index }}.cantidad" min="1" max="99"
                                                   class="w-14 text-center rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                                            <button type="button" wire:click="eliminarItemCombo({{ $index }})"
                                                    class="p-1 text-red-600 hover:bg-red-50 rounded transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="text-center py-4 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
                                            <p class="text-xs">{{ __('Agrega artículos (mín. 2 unidades)') }}</p>
                                        </div>
                                    @endforelse
                                </div>

                                {{-- Buscador combo --}}
                                <div class="relative">
                                    <button type="button" wire:click="abrirBuscadorCombo"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-left text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-orange-400 dark:hover:border-orange-500 transition text-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <span>{{ __('Agregar artículo...') }}</span>
                                    </button>

                                    @if($mostrarBuscadorCombo)
                                        <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border dark:border-gray-600 rounded-lg shadow-xl"
                                             x-data="{ }"
                                             @click.outside="$wire.cerrarBuscadorCombo()">
                                            <div class="p-2 border-b dark:border-gray-600">
                                                <input type="text"
                                                       wire:model.live.debounce.200ms="busquedaArticuloCombo"
                                                       :placeholder="__('Buscar...')"
                                                       class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                       x-init="$el.focus()"
                                                       @keydown.enter.prevent="if ({{ count($articulosComboResultados) }} > 0) { $wire.seleccionarPrimerArticuloCombo() }"
                                                       @keydown.escape="$wire.cerrarBuscadorCombo()">
                                            </div>
                                            <div class="max-h-40 overflow-y-auto">
                                                @forelse($articulosComboResultados as $articulo)
                                                    <button type="button" wire:click="agregarArticuloCombo({{ $articulo['id'] }})"
                                                            class="w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/30 text-sm flex justify-between">
                                                        <span>{{ $articulo['nombre'] }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400">$@precio($articulo['precio_base'] ?? 0)</span>
                                                    </button>
                                                @empty
                                                    <div class="px-3 py-3 text-center text-gray-500 dark:text-gray-400 text-sm">{{ __('No se encontraron') }}</div>
                                                @endforelse
                                            </div>
                                            <div class="p-2 border-t dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                                                <button type="button" wire:click="cerrarBuscadorCombo" class="text-xs text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">{{ __('Cerrar') }}</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Precio --}}
                                @if(count($comboItems) >= 1)
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 space-y-2">
                                        <div class="flex gap-2 mb-2">
                                            <label class="flex items-center gap-1 cursor-pointer text-sm">
                                                <input type="radio" wire:model.live="precioTipo" value="fijo" class="text-orange-600">
                                                <span class="text-gray-700 dark:text-gray-300">{{ __('Precio fijo') }}</span>
                                            </label>
                                            <label class="flex items-center gap-1 cursor-pointer text-sm">
                                                <input type="radio" wire:model.live="precioTipo" value="porcentaje" class="text-orange-600">
                                                <span class="text-gray-700 dark:text-gray-300">{{ __('% Descuento') }}</span>
                                            </label>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Precio normal:') }}</span>
                                            <span class="font-bold text-gray-400 dark:text-gray-500 line-through">$@precio($this->precioNormalCombo)</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $precioTipo === 'fijo' ? __('Precio combo') : __('Descuento') }} *:</span>
                                            <div class="relative w-28">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-sm">{{ $precioTipo === 'fijo' ? '$' : '' }}</span>
                                                <input type="number" wire:model.live="precioValor" min="1" max="{{ $precioTipo === 'porcentaje' ? '100' : '' }}"
                                                       class="w-full {{ $precioTipo === 'fijo' ? 'pl-6' : 'pr-7' }} text-right font-bold rounded-lg border-gray-300 dark:border-gray-600 {{ $precioTipo === 'porcentaje' && $precioValor > 100 ? 'border-red-500 ring-1 ring-red-500' : '' }}">
                                                @if($precioTipo === 'porcentaje')
                                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 text-sm">%</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($precioTipo === 'porcentaje' && $precioValor > 100)
                                            <div class="text-xs text-red-600">{{ __('El descuento no puede superar el 100%') }}</div>
                                        @endif
                                        @if($precioTipo === 'porcentaje' && $precioValor > 0 && $precioValor <= 100)
                                            @php
                                                $precioFinalCombo = $this->precioNormalCombo * (1 - ($precioValor / 100));
                                            @endphp
                                            <div class="flex justify-between items-center pt-2 border-t">
                                                <span class="text-sm text-orange-600 font-medium">{{ __('Precio final:') }}</span>
                                                <span class="font-bold text-orange-600 text-lg">$@precio($precioFinalCombo)</span>
                                            </div>
                                        @endif
                                        @if($this->ahorroCombo > 0 && !($precioTipo === 'porcentaje' && $precioValor > 100))
                                            <div class="flex justify-between items-center {{ $precioTipo === 'fijo' ? 'pt-2 border-t' : '' }}">
                                                <span class="text-sm text-green-600 font-medium">{{ __('Ahorro:') }}</span>
                                                <span class="font-bold text-green-600">$@precio($this->ahorroCombo)</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- MENÚ --}}
                        @if($tipo === 'menu')
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 space-y-4">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-semibold text-green-900 dark:text-green-300">{{ __('Grupos del Menú') }}</h3>
                                    <button type="button" wire:click="agregarGrupoMenu"
                                            class="text-xs text-green-600 hover:text-green-800 font-medium">{{ __('+ Agregar grupo') }}</button>
                                </div>

                                <div class="space-y-3 max-h-80 overflow-y-auto">
                                    @foreach($gruposMenu as $gIndex => $grupo)
                                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-green-200 dark:border-green-700">
                                            <div class="flex items-center gap-2 mb-2">
                                                <input type="text" wire:model="gruposMenu.{{ $gIndex }}.nombre" :placeholder="__('Nombre del grupo')"
                                                       class="flex-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">x</span>
                                                <input type="number" wire:model="gruposMenu.{{ $gIndex }}.cantidad" min="1" max="10"
                                                       class="w-12 text-center text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                @if(count($gruposMenu) > 1)
                                                    <button type="button" wire:click="eliminarGrupoMenu({{ $gIndex }})"
                                                            class="p-1 text-red-600 hover:bg-red-50 rounded">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap gap-1 mb-2">
                                                @foreach($grupo['articulos'] ?? [] as $aIndex => $art)
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                                        {{ $art['nombre'] }}
                                                        <button type="button" wire:click="eliminarArticuloMenu({{ $gIndex }}, {{ $aIndex }})"
                                                                class="text-green-600 hover:text-green-800">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>

                                            @if($mostrarBuscadorMenu && $grupoMenuActivo === $gIndex)
                                                {{-- Buscador dentro del grupo activo --}}
                                                <div class="mt-2 p-2 border dark:border-gray-600 rounded-lg bg-green-50 dark:bg-green-900/20"
                                                     x-data="{ }"
                                                     @click.outside="$wire.cerrarBuscadorMenu()">
                                                    <input type="text"
                                                           wire:model.live.debounce.200ms="busquedaArticuloMenu"
                                                           placeholder="Buscar artículo..."
                                                           class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white mb-2"
                                                           x-init="$el.focus()"
                                                           @keydown.enter.prevent="if ({{ count($articulosMenuResultados) }} > 0) { $wire.seleccionarPrimerArticuloMenu() }"
                                                           @keydown.escape="$wire.cerrarBuscadorMenu()">
                                                    <div class="max-h-32 overflow-y-auto">
                                                        @foreach($articulosMenuResultados as $art)
                                                            <button type="button" wire:click="agregarArticuloMenu({{ $art['id'] }})"
                                                                    class="w-full px-2 py-1 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-green-100 dark:hover:bg-green-900/40 rounded flex justify-between">
                                                                <span>{{ $art['nombre'] }}</span>
                                                                <span class="text-gray-500 dark:text-gray-400">$@precio($art['precio_base'] ?? 0)</span>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                    <button type="button" wire:click="cerrarBuscadorMenu" class="mt-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Cerrar</button>
                                                </div>
                                            @else
                                                <button type="button" wire:click="abrirBuscadorMenu({{ $gIndex }})"
                                                        class="text-xs text-green-600 hover:text-green-800">+ Agregar opción</button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Precio del menú --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 space-y-2">
                                    <div class="flex gap-2 mb-2">
                                        <label class="flex items-center gap-1 cursor-pointer text-sm">
                                            <input type="radio" wire:model.live="precioTipo" value="fijo" class="text-green-600">
                                            <span class="text-gray-700 dark:text-gray-300">Precio fijo</span>
                                        </label>
                                        <label class="flex items-center gap-1 cursor-pointer text-sm">
                                            <input type="radio" wire:model.live="precioTipo" value="porcentaje" class="text-green-600">
                                            <span class="text-gray-700 dark:text-gray-300">% Descuento</span>
                                        </label>
                                    </div>
                                    @if($this->precioNormalMenu > 0)
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-300">Precio base (mínimo):</span>
                                            <span class="font-bold text-gray-400 dark:text-gray-500 line-through">$@precio($this->precioNormalMenu)</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $precioTipo === 'fijo' ? 'Precio menú' : 'Descuento' }} *:</span>
                                        <div class="relative w-28">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-sm">{{ $precioTipo === 'fijo' ? '$' : '' }}</span>
                                            <input type="number" wire:model.live="precioValor" min="1" max="{{ $precioTipo === 'porcentaje' ? '100' : '' }}"
                                                   class="w-full {{ $precioTipo === 'fijo' ? 'pl-6' : 'pr-7' }} text-right font-bold rounded-lg border-gray-300 dark:border-gray-600 {{ $precioTipo === 'porcentaje' && $precioValor > 100 ? 'border-red-500 ring-1 ring-red-500' : '' }}">
                                            @if($precioTipo === 'porcentaje')
                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 text-sm">%</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($precioTipo === 'porcentaje' && $precioValor > 100)
                                        <div class="text-xs text-red-600">{{ __('El descuento no puede superar el 100%') }}</div>
                                    @endif
                                    @if($precioTipo === 'porcentaje' && $precioValor > 0 && $precioValor <= 100 && $this->precioNormalMenu > 0)
                                        @php
                                            $precioFinalMenu = $this->precioNormalMenu * (1 - ($precioValor / 100));
                                        @endphp
                                        <div class="flex justify-between items-center pt-2 border-t">
                                            <span class="text-sm text-green-600 font-medium">Precio final:</span>
                                            <span class="font-bold text-green-600 text-lg">$@precio($precioFinalMenu)</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- PASO 3: Condiciones --}}
            @if($pasoActual == 3)
                <h2 class="text-xl font-semibold mb-6">{{ $modoEdicion ? 'Condiciones' : 'Paso 3: Condiciones' }}</h2>

                <div class="space-y-6">
                    {{-- Vigencia --}}
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Vigencia</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Desde</label>
                                <input type="date" wire:model="vigenciaDesde"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Hasta</label>
                                <input type="date" wire:model="vigenciaHasta"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                        </div>
                    </div>

                    {{-- Días de la semana --}}
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Días de la semana</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['lunes' => 'Lun', 'martes' => 'Mar', 'miercoles' => 'Mié', 'jueves' => 'Jue', 'viernes' => 'Vie', 'sabado' => 'Sáb', 'domingo' => 'Dom'] as $valor => $etiqueta)
                                @php $isSelected = in_array($valor, $diasSemana); @endphp
                                <label class="relative inline-flex items-center justify-center min-w-[60px] px-3 py-2 rounded-lg border-2 cursor-pointer transition-all duration-200
                                    {{ $isSelected ? 'bg-bcn-primary text-white border-bcn-primary shadow-md' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-bcn-primary' }}">
                                    <input type="checkbox" wire:model.live="diasSemana" value="{{ $valor }}" class="sr-only">
                                    <span class="font-semibold text-sm">{{ $etiqueta }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Deja vacío para aplicar todos los días</p>
                    </div>

                    {{-- Horario --}}
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Horario</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Desde</label>
                                <input type="time" wire:model="horaDesde"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Hasta</label>
                                <input type="time" wire:model="horaHasta"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            </div>
                        </div>
                    </div>

                    {{-- Condiciones de venta --}}
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Condiciones de venta</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Forma de venta</label>
                                <select wire:model="formaVentaId"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">Todas</option>
                                    @foreach($formasVenta as $fv)
                                        <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Canal de venta</label>
                                <select wire:model="canalVentaId"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">Todos</option>
                                    @foreach($canalesVenta as $cv)
                                        <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Forma de pago</label>
                                <select wire:model="formaPagoId"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                    <option value="">Todas</option>
                                    @foreach($formasPago as $fp)
                                        <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Límite de usos --}}
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Límite de usos</h3>
                        <div class="max-w-xs">
                            <input type="number" wire:model="usosMaximos" min="1" placeholder="Sin límite"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dejar vacío para uso ilimitado</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- PASO 4: Resumen y Simulador --}}
            @if($pasoActual == 4)
                <h2 class="text-xl font-semibold mb-6">{{ $modoEdicion ? 'Prioridad y Simulador' : 'Paso 4: Prioridad y Simulador' }}</h2>

                <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                    {{-- Columna Izquierda: Resumen y Prioridad (30%) --}}
                    <div class="lg:col-span-3 space-y-4">
                        {{-- Resumen de la promoción --}}
                        <div class="bg-gradient-to-r from-bcn-primary/10 to-bcn-primary/5 border border-bcn-primary/20 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                @php
                                    $tipoConfig = [
                                        'nxm' => ['icon' => '🔢', 'label' => 'NxM', 'bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
                                        'nxm_avanzado' => ['icon' => '🎯', 'label' => 'NxM Avanzado', 'bg' => 'bg-indigo-100', 'text' => 'text-indigo-800'],
                                        'combo' => ['icon' => '📦', 'label' => 'Combo', 'bg' => 'bg-orange-100', 'text' => 'text-orange-800'],
                                        'menu' => ['icon' => '🍽️', 'label' => 'Menú', 'bg' => 'bg-green-100', 'text' => 'text-green-800'],
                                    ][$tipo] ?? ['icon' => '📋', 'label' => $tipo, 'bg' => 'bg-gray-100 dark:bg-gray-700', 'text' => 'text-gray-800'];
                                @endphp
                                <span class="text-xl">{{ $tipoConfig['icon'] }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium {{ $tipoConfig['bg'] }} {{ $tipoConfig['text'] }} rounded-full">
                                    {{ $tipoConfig['label'] }}
                                </span>
                            </div>
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $nombre ?: '(Sin nombre)' }}</h4>
                            @if($descripcion)
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $descripcion }}</p>
                            @endif
                        </div>

                        {{-- Prioridad --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h3 class="font-medium text-gray-900 dark:text-white mb-3">Prioridad</h3>
                            <div class="flex items-center gap-3">
                                <input type="number" wire:model.live="prioridad" min="1" max="999"
                                       class="w-20 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-lg font-bold">
                                <div class="flex-1">
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Menor = mayor prioridad</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Se aplican primero las de menor número</p>
                                </div>
                            </div>
                        </div>

                        {{-- Info de exclusividad --}}
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-amber-800">
                                    <strong>Artículos exclusivos:</strong> Cada unidad solo puede participar en UNA promoción especial. Si un artículo se usa en un combo, no puede usarse en otra promo.
                                </p>
                            </div>
                        </div>

                        {{-- Promociones competidoras --}}
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 border-b">
                                <h3 class="font-semibold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Otras promociones ({{ count($promocionesEspecialesCompetidoras) }})
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">Podes editar las prioridades para simular</p>
                            </div>
                            <div class="max-h-48 overflow-y-auto">
                                @forelse($promocionesEspecialesCompetidoras as $promo)
                                    @php
                                        $prioridadActual = $prioridadesTemporales[$promo->id] ?? $promo->prioridad;
                                        $cambio = $prioridadActual != $promo->prioridad;
                                    @endphp
                                    <div class="px-3 py-2 border-b last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700 {{ $cambio ? 'bg-yellow-50' : '' }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ $promo->nombre }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $promo->tipo)) }}</p>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <span class="text-xs text-gray-400 dark:text-gray-500">P:</span>
                                                <input type="number"
                                                       value="{{ $prioridadActual }}"
                                                       wire:change="actualizarPrioridadCompetidora({{ $promo->id }}, $event.target.value)"
                                                       min="1" max="999"
                                                       class="w-14 text-center text-sm rounded border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 py-1 {{ $cambio ? 'bg-yellow-100 border-yellow-400' : '' }}">
                                                @if($cambio)
                                                    <span class="text-xs text-yellow-600" title="Original: {{ $promo->prioridad }}">*</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-3 py-4 text-center text-gray-500 text-sm">
                                        No hay otras promociones activas
                                    </div>
                                @endforelse
                            </div>
                            @if(count(array_filter($prioridadesTemporales, fn($p, $id) => isset($promocionesEspecialesCompetidoras->firstWhere('id', $id)->prioridad) && $p != $promocionesEspecialesCompetidoras->firstWhere('id', $id)->prioridad, ARRAY_FILTER_USE_BOTH)) > 0)
                                <div class="px-3 py-2 bg-yellow-50 border-t border-yellow-200 text-xs text-yellow-700">
                                    <strong>*</strong> Las prioridades modificadas se guardaran al guardar la promocion
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Columna Derecha: Simulador (70%) --}}
                    <div class="lg:col-span-7 space-y-4">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="bg-purple-50 dark:bg-purple-900/30 px-4 py-3 border-b dark:border-gray-700">
                                <h3 class="font-semibold text-purple-900 dark:text-purple-300 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    Simulador de Venta
                                </h3>
                                <p class="text-xs text-purple-700 dark:text-purple-400 mt-1">Prueba cómo se aplican las promociones especiales</p>
                            </div>

                            <div class="p-3 sm:p-4 space-y-3">
                                {{-- Filtros del simulador --}}
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <button type="button"
                                            wire:click="$toggle('mostrarFiltrosSimulador')"
                                            class="w-full flex items-center justify-between p-3 text-xs font-medium text-gray-600 dark:text-gray-300 uppercase">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                            </svg>
                                            Contexto de la venta
                                        </span>
                                        <svg class="w-4 h-4 transition-transform {{ $mostrarFiltrosSimulador ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div class="p-3 pt-0 space-y-2 {{ $mostrarFiltrosSimulador ? '' : 'hidden' }}">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            {{-- Sucursal --}}
                                            <div>
                                                <label class="text-xs text-gray-500 block mb-1">Sucursal</label>
                                                <select wire:model.live="simuladorSucursalId"
                                                        class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    @foreach($sucursales as $suc)
                                                        @if(in_array($suc->id, $sucursalesSeleccionadas))
                                                            <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>

                                            {{-- Lista de Precios --}}
                                            <div>
                                                <label class="text-xs text-gray-500 block mb-1">Lista de Precios</label>
                                                <select wire:model.live="simuladorListaPrecioId"
                                                        class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    @forelse($listasPreciosSimulador as $lista)
                                                        <option value="{{ $lista['id'] }}">
                                                            {{ $lista['nombre'] }}
                                                            @if($lista['es_lista_base'])
                                                                (Base{{ $lista['ajuste_porcentaje'] != 0 ? ', ' . ($lista['ajuste_porcentaje'] > 0 ? '+' : '') . $lista['ajuste_porcentaje'] . '%' : '' }})
                                                            @elseif($lista['ajuste_porcentaje'] != 0)
                                                                ({{ $lista['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $lista['ajuste_porcentaje'] }}%)
                                                            @endif
                                                        </option>
                                                    @empty
                                                        <option value="">Sin listas</option>
                                                    @endforelse
                                                </select>
                                            </div>

                                            {{-- Forma de Venta --}}
                                            <div>
                                                <label class="text-xs text-gray-500 block mb-1">Forma Venta</label>
                                                <select wire:model.live="simuladorFormaVentaId"
                                                        class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    <option value="">Todas</option>
                                                    @foreach($formasVenta as $fv)
                                                        <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            {{-- Canal de Venta --}}
                                            <div>
                                                <label class="text-xs text-gray-500 block mb-1">Canal Venta</label>
                                                <select wire:model.live="simuladorCanalVentaId"
                                                        class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    <option value="">Todos</option>
                                                    @foreach($canalesVenta as $cv)
                                                        <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            {{-- Forma de Pago --}}
                                            <div class="sm:col-span-2">
                                                <label class="text-xs text-gray-500 block mb-1">Forma Pago</label>
                                                <select wire:model.live="simuladorFormaPagoId"
                                                        class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                    <option value="">Todas</option>
                                                    @foreach($formasPago as $fp)
                                                        <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Buscador de artículos --}}
                                <div class="relative">
                                    <button type="button"
                                            wire:click="abrirBuscadorArticulosSimulador"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-500 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-bcn-primary transition">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <span>Agregar artículo al carrito...</span>
                                    </button>

                                    @if($mostrarBuscadorArticulosSimulador)
                                        <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border rounded-lg shadow-xl"
                                             x-data="{}"
                                             @click.outside="$wire.cerrarBuscadorArticulosSimulador()">
                                            <div class="p-2 border-b">
                                                <input type="text"
                                                       wire:model.live.debounce.200ms="busquedaArticuloSimulador"
                                                       wire:keydown.enter="agregarPrimerArticuloSimulador"
                                                       wire:keydown.escape="cerrarBuscadorArticulosSimulador"
                                                       placeholder="Buscar artículo..."
                                                       x-init="$nextTick(() => $el.focus())"
                                                       class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                            </div>
                                            <div class="max-h-48 overflow-y-auto">
                                                @forelse($articulosSimuladorResultados as $art)
                                                    @php
                                                        $precioBase = $art['precio_base'] ?? $art['precio'] ?? 0;
                                                        $precioLista = $art['precio'] ?? 0;
                                                        $tieneAjuste = abs($precioLista - $precioBase) > 0.01;
                                                    @endphp
                                                    <button type="button"
                                                            wire:click="agregarArticuloSimulador({{ $art['id'] }})"
                                                            class="w-full px-3 py-2 text-left hover:bg-purple-50 dark:bg-purple-900/20 border-b last:border-b-0 flex items-center justify-between text-sm">
                                                        <div class="flex-1 min-w-0">
                                                            <span class="font-medium text-gray-900 dark:text-white">{{ $art['nombre'] }}</span>
                                                            @if($art['codigo'])
                                                                <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">({{ $art['codigo'] }})</span>
                                                            @endif
                                                        </div>
                                                        <div class="text-right flex-shrink-0 ml-2">
                                                            @if($tieneAjuste)
                                                                <span class="text-gray-400 dark:text-gray-500 text-xs line-through">$@precio($precioBase)</span>
                                                                <span class="{{ $precioLista < $precioBase ? 'text-green-600' : 'text-red-600' }} font-medium ml-1">
                                                                    $@precio($precioLista)
                                                                </span>
                                                            @else
                                                                <span class="text-gray-700 dark:text-gray-300 font-medium">$@precio($precioLista)</span>
                                                            @endif
                                                        </div>
                                                    </button>
                                                @empty
                                                    <div class="px-3 py-4 text-center text-gray-500 text-sm">
                                                        No se encontraron artículos
                                                    </div>
                                                @endforelse
                                            </div>
                                            <div class="p-2 border-t bg-gray-50 flex items-center justify-between">
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Enter para agregar primero</span>
                                                <button type="button"
                                                        wire:click="cerrarBuscadorArticulosSimulador"
                                                        class="px-3 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:bg-gray-700 rounded">
                                                    Cerrar
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Items del carrito --}}
                                @if(count($itemsSimulador) > 0)
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-500 uppercase">Artículos ({{ count($itemsSimulador) }})</span>
                                        </div>

                                        <div class="space-y-1.5 max-h-64 overflow-y-auto pb-1">
                                            @foreach($itemsSimulador as $index => $item)
                                                @php
                                                    $precioBase = $item['precio_base'] ?? $item['precio'] ?? 0;
                                                    $precioLista = $item['precio'] ?? 0;
                                                    $cantidad = $item['cantidad'] ?? 1;
                                                    $subtotalItem = $precioLista * $cantidad;
                                                    // Usar tiene_ajuste del item (calculado en PHP considerando si es lista base)
                                                    $tieneAjusteLista = $item['tiene_ajuste'] ?? (abs($precioLista - $precioBase) > 0.01);

                                                    // Buscar info de resultado si existe
                                                    $itemResultado = null;
                                                    if ($resultadoSimulador && isset($resultadoSimulador['items'][$index])) {
                                                        $itemResultado = $resultadoSimulador['items'][$index];
                                                    }
                                                    $promocionesItem = $itemResultado['promociones_participantes'] ?? [];
                                                    $unidadesConsumidas = $itemResultado['unidades_consumidas'] ?? 0;
                                                    $unidadesLibres = $itemResultado['unidades_libres'] ?? $cantidad;
                                                    $excluidoPromociones = $itemResultado['excluido_promociones'] ?? false;
                                                @endphp
                                                <div class="bg-gray-50 rounded-lg p-2 {{ $excluidoPromociones ? 'ring-2 ring-amber-400 m-1' : (!empty($promocionesItem) ? 'ring-2 ring-green-400 m-1' : '') }}">
                                                    {{-- Línea principal del artículo con grid fijo --}}
                                                    <div class="grid grid-cols-12 gap-2 items-center">
                                                        {{-- Nombre y precio unitario (5 cols) --}}
                                                        <div class="col-span-5 min-w-0">
                                                            <div class="flex items-center gap-1">
                                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item['nombre'] ?? 'Artículo' }}</p>
                                                                @if($excluidoPromociones)
                                                                    <span class="flex-shrink-0 px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px] leading-none font-medium" title="Este artículo no participa en promociones según la lista de precios seleccionada">
                                                                        SIN PROMO
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                @if($tieneAjusteLista)
                                                                    <span class="line-through text-gray-400 dark:text-gray-500">$@precio($precioBase)</span>
                                                                    <span class="{{ $precioLista < $precioBase ? 'text-green-600' : 'text-red-600' }} font-medium">
                                                                        $@precio($precioLista)
                                                                    </span> c/u
                                                                @else
                                                                    $@precio($precioLista) c/u
                                                                @endif
                                                            </p>
                                                        </div>

                                                        {{-- Cantidad (2 cols) --}}
                                                        <div class="col-span-2 flex justify-center">
                                                            <input type="number"
                                                                   wire:model.live.debounce.500ms="itemsSimulador.{{ $index }}.cantidad"
                                                                   min="1"
                                                                   class="w-14 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-center py-1.5 px-1">
                                                        </div>

                                                        {{-- Subtotal (3 cols) --}}
                                                        <div class="col-span-3 text-right">
                                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">$@precio($subtotalItem)</span>
                                                        </div>

                                                        {{-- Eliminar (2 cols) --}}
                                                        <div class="col-span-2 flex justify-end">
                                                            <button type="button" wire:click="eliminarItemSimulador({{ $index }})"
                                                                    class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Estado de consumo --}}
                                                    @if(!empty($promocionesItem))
                                                        <div class="mt-1.5 pt-1.5 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-1">
                                                            @foreach($promocionesItem as $promoNombre)
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-[10px]">
                                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                    {{ $promoNombre }}
                                                                </span>
                                                            @endforeach
                                                            @if($unidadesLibres > 0)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded text-[10px]">
                                                                    {{ $unidadesLibres }} sin usar
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <p>Agrega artículos para simular</p>
                                        <p class="text-xs mt-1">Prueba cómo interactúan las promociones</p>
                                    </div>
                                @endif

                                {{-- Resultado de la simulación --}}
                                @if($resultadoSimulador)
                                    <div class="border-t pt-3 space-y-3">
                                        {{-- Promociones aplicadas --}}
                                        @if(!empty($resultadoSimulador['promociones_aplicadas']))
                                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-lg p-3">
                                                <p class="text-xs font-semibold text-green-800 mb-2 flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    Promociones aplicadas:
                                                </p>
                                                @foreach($resultadoSimulador['promociones_aplicadas'] as $pa)
                                                    <div class="flex justify-between items-start text-xs mb-1 {{ $pa['es_nueva'] ? 'bg-yellow-50 rounded px-2 py-1' : '' }}">
                                                        <div class="flex-1">
                                                            <span class="{{ $pa['es_nueva'] ? 'text-yellow-800 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                                                {{ $pa['nombre'] }}
                                                                @if($pa['es_nueva'])
                                                                    <span class="px-1 py-0.5 bg-yellow-200 text-yellow-800 rounded text-[10px] ml-1">NUEVA</span>
                                                                @endif
                                                            </span>
                                                            <span class="text-gray-500 block">{{ $pa['descripcion'] }}</span>
                                                        </div>
                                                        <span class="text-green-600 font-medium ml-2">-$@precio($pa['descuento'])</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Promociones no aplicadas --}}
                                        @if(!empty($resultadoSimulador['promociones_no_aplicadas']))
                                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                                                <p class="text-xs font-semibold text-gray-500 mb-1">No aplicadas:</p>
                                                @foreach($resultadoSimulador['promociones_no_aplicadas'] as $pna)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500 {{ $pna['es_nueva'] ? 'bg-yellow-50 rounded px-1' : '' }}">
                                                        {{ $pna['nombre'] }}
                                                        @if($pna['es_nueva'])
                                                            <span class="px-1 bg-yellow-200 text-yellow-700 rounded text-[10px]">NUEVA</span>
                                                        @endif
                                                        - <span class="italic">{{ $pna['razon'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Totales --}}
                                        <div class="border-t pt-2 space-y-1">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600 dark:text-gray-300">Subtotal:</span>
                                                <span class="dark:text-gray-300">$@precio($resultadoSimulador['subtotal'])</span>
                                            </div>

                                            @if($resultadoSimulador['total_descuentos'] > 0)
                                                <div class="flex justify-between text-sm text-green-600">
                                                    <span>Descuentos:</span>
                                                    <span>-$@precio($resultadoSimulador['total_descuentos'])</span>
                                                </div>
                                            @endif

                                            <div class="flex justify-between text-lg font-bold border-t pt-2">
                                                <span>TOTAL:</span>
                                                <span class="text-bcn-primary">$@precio($resultadoSimulador['total_final'])</span>
                                            </div>

                                            @if($resultadoSimulador['total_descuentos'] > 0)
                                                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded p-2 text-center">
                                                    <span class="text-green-700 text-sm font-medium">
                                                        Ahorro: $@precio($resultadoSimulador['total_descuentos'])
                                                        @if($resultadoSimulador['subtotal'] > 0)
                                                            (@porcentaje(($resultadoSimulador['total_descuentos'] / $resultadoSimulador['subtotal']) * 100))
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Botones de navegación --}}
            <div class="flex justify-between mt-8 pt-6 border-t">
                @php $minPaso = $modoEdicion ? 2 : 1; @endphp
                <div>
                    @if($pasoActual > $minPaso)
                        <button type="button" wire:click="anterior"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Anterior
                        </button>
                    @endif
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('configuracion.promociones-especiales') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </a>

                    @if($pasoActual < $totalPasos)
                        <button type="button" wire:click="siguiente"
                                class="inline-flex items-center px-6 py-2 bg-bcn-primary text-white rounded-lg hover:bg-bcn-primary/90 transition font-medium">
                            Siguiente
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @else
                        <button type="button" wire:click="guardar"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                                class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            <span wire:loading.remove wire:target="guardar" class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $modoEdicion ? 'Guardar cambios' : 'Crear promocion' }}
                            </span>
                            <span wire:loading wire:target="guardar">Guardando...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
