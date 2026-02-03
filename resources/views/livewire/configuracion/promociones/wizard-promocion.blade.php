<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('configuracion.promociones') }}"
                   wire:navigate
                   class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
                        {{ $modoEdicion ? __('Editar Promoción') : __('Crear Nueva Promoción') }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
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
                    // En modo edición, no mostramos el paso 1 (tipo)
                    $pasos = $modoEdicion
                        ? [2 => 'Config', 3 => 'Alcance', 4 => 'Condiciones', 5 => 'Simulador']
                        : [1 => 'Tipo', 2 => 'Config', 3 => 'Alcance', 4 => 'Condiciones', 5 => 'Simulador'];
                    $indexPaso = 0;
                @endphp
                @foreach($pasos as $numPaso => $labelPaso)
                    @php $indexPaso++; @endphp
                    <div class="flex flex-col items-center flex-1">
                        @if($modoEdicion)
                            {{-- En modo edición, todos los pasos son clickeables --}}
                            @if($numPaso == $pasoActual)
                                {{-- Paso actual --}}
                                <button class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-bcn-primary text-white ring-4 ring-bcn-primary ring-opacity-30">
                                    {{ $indexPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-bcn-primary font-medium">{{ $labelPaso }}</span>
                            @else
                                {{-- Paso clickeable --}}
                                <button wire:click="irAPaso({{ $numPaso }})"
                                        class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-300 cursor-pointer hover:bg-bcn-primary hover:text-white border-2 border-blue-200 dark:border-blue-700 hover:border-bcn-primary">
                                    {{ $indexPaso }}
                                </button>
                                <span class="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">{{ $labelPaso }}</span>
                            @endif
                        @else
                            {{-- Modo creación: navegación normal --}}
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
                        {{-- Línea conectora: en edición siempre azul claro, en creación verde si completado --}}
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

        {{-- PASO 1: Tipo de Promoción --}}
        @if($pasoActual == 1)
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Paso 1: Tipo de Promoción') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button wire:click="seleccionarTipo('descuento_porcentaje')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'descuento_porcentaje' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">💯</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Porcentaje') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Ej: 15% de descuento') }}</p>
                </button>

                <button wire:click="seleccionarTipo('descuento_monto')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'descuento_monto' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">💵</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Monto Fijo') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Ej: $100 de descuento') }}</p>
                </button>

                <button wire:click="seleccionarTipo('precio_fijo')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'precio_fijo' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">🏷️</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Precio Fijo') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Ej: $500 precio final') }}</p>
                </button>

                <button wire:click="seleccionarTipo('recargo_porcentaje')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'recargo_porcentaje' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">📈</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Recargo %') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Ej: +10% recargo') }}</p>
                </button>

                <button wire:click="seleccionarTipo('recargo_monto')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'recargo_monto' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">💰</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Recargo $') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Ej: +$100 recargo') }}</p>
                </button>

                <button wire:click="seleccionarTipo('descuento_escalonado')"
                        class="p-6 border-2 rounded-lg text-left transition {{ $tipo == 'descuento_escalonado' ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    <div class="text-2xl mb-2">📊</div>
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ __('Escalonado') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Por cantidad') }}</p>
                </button>
            </div>
        @endif

        {{-- PASO 2: Configuración --}}
        @if($pasoActual == 2)
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $modoEdicion ? __('Configuración Básica') : __('Paso 2: Configuración Básica') }}</h2>
                @if($modoEdicion)
                    @php
                        $tiposPromocion = [
                            'descuento_porcentaje' => ['label' => 'Descuento %', 'color' => 'bg-green-100 text-green-800', 'icon' => '💯'],
                            'descuento_monto' => ['label' => 'Descuento $', 'color' => 'bg-green-100 text-green-800', 'icon' => '💵'],
                            'precio_fijo' => ['label' => 'Precio Fijo', 'color' => 'bg-blue-100 text-blue-800', 'icon' => '🏷️'],
                            'recargo_porcentaje' => ['label' => 'Recargo %', 'color' => 'bg-orange-100 text-orange-800', 'icon' => '📈'],
                            'recargo_monto' => ['label' => 'Recargo $', 'color' => 'bg-orange-100 text-orange-800', 'icon' => '💰'],
                            'descuento_escalonado' => ['label' => 'Escalonado', 'color' => 'bg-purple-100 text-purple-800', 'icon' => '📊'],
                        ];
                        $tipoInfo = $tiposPromocion[$tipo] ?? ['label' => $tipo, 'color' => 'bg-gray-100 text-gray-800', 'icon' => '🏷️'];
                    @endphp
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium {{ $tipoInfo['color'] }}">
                        <span>{{ $tipoInfo['icon'] }}</span>
                        {{ $tipoInfo['label'] }}
                    </span>
                @endif
            </div>

            <div class="space-y-4">
                {{-- Nombre y Valor en el mismo renglón --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Nombre') }} *</label>
                        <input type="text" wire:model="nombre"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>

                    @if($tipo !== 'descuento_escalonado')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                @if(str_contains($tipo, 'porcentaje'))
                                    {{ __('Porcentaje') }} *
                                @elseif($tipo === 'precio_fijo')
                                    {{ __('Precio Final') }} *
                                @elseif(str_contains($tipo, 'monto'))
                                    {{ __('Monto') }} *
                                @else
                                    {{ __('Valor') }} *
                                @endif
                            </label>
                            <div class="relative">
                                @if(str_contains($tipo, 'porcentaje'))
                                    <input type="number" wire:model="valor" step="0.01" min="0" max="100"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pr-8">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">%</span>
                                @else
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                                    <input type="number" wire:model="valor" step="0.01" min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 pl-7">
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Descripción --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Descripción') }} <span class="text-gray-400 dark:text-gray-500 font-normal">({{ __('opcional') }})</span></label>
                    <textarea wire:model="descripcion" rows="2" :placeholder="__('Breve descripción de la promoción...')"
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"></textarea>
                </div>

                @if($tipo === 'descuento_escalonado')
                    <div class="border rounded-lg p-4 bg-purple-50 dark:bg-purple-900 dark:border-purple-700">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-purple-900 dark:text-purple-100">{{ __('Escalas de Descuento') }}</h3>
                            <button type="button" wire:click="agregarEscala"
                                    class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white rounded-md hover:bg-opacity-90 transition text-sm font-semibold shadow-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ __('Agregar Escala') }}
                            </button>
                        </div>

                        {{-- Encabezados --}}
                        <div class="grid grid-cols-5 gap-2 mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                            <div>{{ __('Cantidad Desde') }} *</div>
                            <div>{{ __('Cantidad Hasta') }}</div>
                            <div>{{ __('Tipo') }} *</div>
                            <div>{{ __('Valor') }} *</div>
                            <div></div>
                        </div>

                        {{-- Escalas --}}
                        @foreach($escalas as $index => $escala)
                            <div class="grid grid-cols-5 gap-2 mb-2 items-center">
                                <input type="number" wire:model="escalas.{{ $index }}.cantidad_desde"
                                       placeholder="Ej: 1" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm bg-white">
                                <input type="number" wire:model="escalas.{{ $index }}.cantidad_hasta"
                                       placeholder="Ej: 5 (vacío = sin límite)" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm bg-white">
                                <select wire:model="escalas.{{ $index }}.tipo_descuento"
                                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm bg-white">
                                    <option value="porcentaje">{{ __('Porcentaje %') }}</option>
                                    <option value="monto">{{ __('Monto $') }}</option>
                                    <option value="precio_fijo">{{ __('Precio Fijo') }}</option>
                                </select>
                                <input type="number" wire:model="escalas.{{ $index }}.valor"
                                       placeholder="Ej: 10" step="0.01" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm bg-white">
                                @if(count($escalas) > 1)
                                    <button type="button" wire:click="eliminarEscala({{ $index }})"
                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded transition"
                                            :title="__('Eliminar escala')"
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @else
                                    <div></div>
                                @endif
                            </div>
                        @endforeach

                        <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 bg-blue-50 dark:bg-blue-900 p-3 rounded">
                            <strong>{{ __('Ejemplo') }}:</strong>
                            <br>{{ __('1-5 unidades: 5% descuento') }}
                            <br>{{ __('6-10 unidades: 10% descuento') }}
                            <br>{{ __('11+ unidades (sin límite): 15% descuento') }}
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- PASO 3: Alcance --}}
        @if($pasoActual == 3)
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Paso 3: Alcance de la Promoción') }}</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Alcance') }}</label>
                    @if($tipo === 'precio_fijo')
                        {{-- Para precio fijo solo se permite artículo específico --}}
                        <div class="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg mb-3">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    <strong>{{ __('Precio Fijo Final') }}</strong> {{ __('solo puede aplicarse a un artículo específico.') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <label class="flex items-center text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                <input type="radio" disabled class="mr-2 cursor-not-allowed">
                                {{ __('Todos los artículos') }}
                            </label>
                            <label class="flex items-center text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                <input type="radio" disabled class="mr-2 cursor-not-allowed">
                                {{ __('Por categoría') }}
                            </label>
                            <label class="flex items-center text-gray-700 dark:text-gray-300">
                                <input type="radio" checked class="mr-2 text-bcn-primary">
                                {{ __('Artículo específico') }}
                            </label>
                        </div>
                    @else
                        <div class="flex gap-4">
                            <label class="flex items-center text-gray-700 dark:text-gray-300">
                                <input type="radio" wire:model.live="alcanceArticulos" value="todos" class="mr-2 text-bcn-primary">
                                {{ __('Todos los artículos') }}
                            </label>
                            <label class="flex items-center text-gray-700 dark:text-gray-300">
                                <input type="radio" wire:model.live="alcanceArticulos" value="categoria" class="mr-2 text-bcn-primary">
                                {{ __('Por categoría') }}
                            </label>
                            <label class="flex items-center text-gray-700 dark:text-gray-300">
                                <input type="radio" wire:model.live="alcanceArticulos" value="articulo" class="mr-2 text-bcn-primary">
                                {{ __('Artículo específico') }}
                            </label>
                        </div>
                    @endif
                </div>

                @if($alcanceArticulos === 'categoria')
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($categorias as $categoria)
                            <button wire:click="$set('categoriaId', {{ $categoria->id }})"
                                    class="p-4 border-2 rounded-lg text-left transition {{ $categoriaId == $categoria->id ? 'border-bcn-primary bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500' }}">
                                <div class="text-2xl mb-1">
                                    @if($categoria->icono)
                                        @php
                                            $iconParts = explode('.', $categoria->icono);
                                            $iconType = $iconParts[0] ?? 'icon';
                                            $iconName = $iconParts[1] ?? 'tag';
                                        @endphp
                                        <x-dynamic-component :component="$iconType . '.' . $iconName" class="w-8 h-8" style="color: {{ $categoria->color ?? '#6B7280' }}" />
                                    @else
                                        <x-icon.tag class="w-8 h-8" style="color: {{ $categoria->color ?? '#6B7280' }}" />
                                    @endif
                                </div>
                                <div class="font-medium text-sm text-gray-900 dark:text-white">{{ $categoria->nombre }}</div>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if($alcanceArticulos === 'articulo' || $tipo === 'precio_fijo')
                    <div>
                        @if($articuloId)
                            {{-- Artículo seleccionado --}}
                            <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                                <div class="flex-1">
                                    <span class="text-xs text-green-600 dark:text-green-400 font-medium">{{ __('Artículo seleccionado') }}</span>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $busquedaArticulo }}</p>
                                </div>
                                <button type="button" wire:click="limpiarArticulo"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                        :title="__('Quitar selección')"
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @else
                            {{-- Buscador de artículos estilo simulador --}}
                            <div class="relative">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="abrirBuscadorArticuloAlcance"
                                            class="flex-1 flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-bcn-primary transition">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <span>{{ __('Buscar artículo...') }}</span>
                                    </button>
                                </div>

                                {{-- Modal/Dropdown de búsqueda --}}
                                @if($mostrarBuscadorArticuloAlcance)
                                    <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg shadow-xl">
                                        <div class="p-2 border-b dark:border-gray-700">
                                            <input type="text"
                                                   wire:model.live.debounce.200ms="busquedaArticulo"
                                                   wire:keydown.enter="seleccionarPrimerArticulo"
                                                   wire:keydown.escape="cerrarBuscadorArticuloAlcance"
                                                   :placeholder="__('Nombre, código o escanear código de barras...')"
                                                   x-init="$nextTick(() => $el.focus())"
                                                   class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                        </div>
                                        <div class="max-h-48 overflow-y-auto">
                                            @forelse($articulos as $articulo)
                                                <button type="button"
                                                        wire:click="seleccionarArticulo({{ $articulo->id }})"
                                                        class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-gray-700 border-b dark:border-gray-700 last:border-b-0 flex items-center justify-between text-sm">
                                                    <div class="flex-1 min-w-0">
                                                        <span class="font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</span>
                                                        @if($articulo->codigo)
                                                            <span class="text-gray-400 text-xs ml-1">({{ $articulo->codigo }})</span>
                                                        @endif
                                                    </div>
                                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </button>
                                            @empty
                                                <div class="px-3 py-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                                    @if(strlen($busquedaArticulo) >= 2)
                                                        {{ __('No se encontraron artículos') }}
                                                    @else
                                                        {{ __('Escribe para buscar...') }}
                                                    @endif
                                                </div>
                                            @endforelse
                                        </div>
                                        <div class="p-2 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Enter para seleccionar primero') }}</span>
                                            <button type="button"
                                                    wire:click="cerrarBuscadorArticuloAlcance"
                                                    class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded transition">
                                                {{ __('Cerrar') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Sucursales') }} *
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">({{ __('Selecciona una o mas') }})</span>
                        </label>
                        <div class="flex gap-2">
                            <button type="button"
                                    wire:click="$set('sucursalesSeleccionadas', {{ $sucursales->pluck('id')->toJson() }})"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition">
                                {{ __('Todas') }}
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <button type="button"
                                    wire:click="$set('sucursalesSeleccionadas', [])"
                                    class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">
                                {{ __('Ninguna') }}
                            </button>
                        </div>
                    </div>
                    <div class="border rounded-lg p-3 {{ empty($sucursalesSeleccionadas) ? 'border-red-300 dark:border-red-700' : 'border-gray-300 dark:border-gray-600' }} bg-gray-50 dark:bg-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            @foreach($sucursales as $sucursal)
                                <label class="flex items-center p-2 rounded hover:bg-white dark:hover:bg-gray-600 cursor-pointer transition">
                                    <input type="checkbox"
                                           wire:model.live="sucursalesSeleccionadas"
                                           value="{{ $sucursal->id }}"
                                           class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $sucursal->nombre }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if(!empty($sucursalesSeleccionadas))
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ count($sucursalesSeleccionadas) }} {{ __('sucursal(es) seleccionada(s)') }}
                                </span>
                            </div>
                        @endif
                    </div>
                    @error('sucursalesSeleccionadas')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        @endif

        {{-- PASO 4: Restricciones --}}
        @if($pasoActual == 4)
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Paso 4: Restricciones y Condiciones') }}</h2>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Forma de Venta') }}</label>
                        <select wire:model="formaVentaId" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Todas</option>
                            @foreach($formasVenta as $forma)
                                <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Canal de Venta') }}</label>
                        <select wire:model="canalVentaId" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Todos</option>
                            @foreach($canalesVenta as $canal)
                                <option value="{{ $canal->id }}">{{ $canal->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Forma de Pago') }}</label>
                        <select wire:model="formaPagoId" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Todas</option>
                            @foreach($formasPago as $fp)
                                <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Monto Mínimo') }}</label>
                        <input type="number" wire:model="montoMinimo" step="0.01" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Cantidad Mínima') }}</label>
                        <input type="number" wire:model="cantidadMinima" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Vigencia Desde') }}</label>
                        <input type="date" wire:model="vigenciaDesde" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Vigencia Hasta') }}</label>
                        <input type="date" wire:model="vigenciaHasta" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                {{-- Días de la Semana y Código Cupón en el mismo renglón --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Días de la Semana --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Días de la Semana') }} <span class="text-gray-400 dark:text-gray-500 font-normal">({{ __('opcional') }})</span>
                        </label>
                        <div class="flex flex-wrap gap-1.5">
                            @php
                                $dias = [
                                    'lunes' => 'Lun',
                                    'martes' => 'Mar',
                                    'miercoles' => 'Mié',
                                    'jueves' => 'Jue',
                                    'viernes' => 'Vie',
                                    'sabado' => 'Sáb',
                                    'domingo' => 'Dom'
                                ];
                            @endphp
                            @foreach($dias as $valor => $etiqueta)
                                @php
                                    $isSelected = in_array($valor, $diasSemana ?? []);
                                @endphp
                                <label class="relative inline-flex items-center justify-center flex-1 min-w-[42px] h-[42px] rounded-lg border-2 cursor-pointer transition-all duration-200
                                    {{ $isSelected
                                        ? 'bg-bcn-primary text-white border-bcn-primary shadow-md'
                                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-bcn-primary hover:bg-bcn-primary hover:bg-opacity-10' }}">
                                    <input type="checkbox"
                                           wire:model.live="diasSemana"
                                           value="{{ $valor }}"
                                           class="sr-only">
                                    <span class="font-semibold text-xs">{{ $etiqueta }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            {{ __('Sin selección = todos los días') }}
                        </p>
                    </div>

                    {{-- Código Cupón --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('Código Cupón') }} <span class="text-gray-400 dark:text-gray-500 font-normal">({{ __('opcional') }})</span>
                        </label>
                        <input type="text" wire:model="codigoCupon" placeholder="Ej: VERANO2025"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 uppercase"
                               style="text-transform: uppercase;">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            {{ __('Si defines un código, la promoción solo aplica cuando el cliente lo ingresa') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Hora Desde') }}</label>
                        <input type="time" wire:model="horaDesde" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Hora Hasta') }}</label>
                        <input type="time" wire:model="horaHasta" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Usos Máximos') }}</label>
                        <input type="number" wire:model="usosMaximos" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                {{-- Combinable - Switch vistoso centrado --}}
                <div class="flex justify-center pt-4 border-t dark:border-gray-700">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="combinable" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-bcn-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 dark:after:border-gray-500 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-bcn-primary"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Combinable con otras promociones') }}</span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center -mt-2">
                    {{ __('Si está activado, esta promoción puede aplicarse junto con otras promociones combinables') }}
                </p>
            </div>
        @endif

        {{-- PASO 5: Prioridad y Simulador --}}
        @if($pasoActual == 5)
            @php
                // Detectar si hay promociones combinables con las que se pueda combinar
                $promocionesCombinables = collect($promocionesCompetidoras)->where('combinable', true);
                $mostrarPrioridad = $combinable && $promocionesCombinables->count() > 0;
            @endphp

            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">{{ __('Paso 5:') }} {{ $mostrarPrioridad ? __('Prioridad y ') : '' }}{{ __('Simulación') }}</h2>

            <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                {{-- Columna Izquierda: Prioridad y Promociones (30%) --}}
                <div class="lg:col-span-3 space-y-4">

                    {{-- Resumen de la promoción con botón editar --}}
                    <div class="bg-gradient-to-r from-bcn-primary/10 to-bcn-primary/5 dark:from-bcn-primary/20 dark:to-bcn-primary/10 border border-bcn-primary/20 rounded-lg p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium text-bcn-primary dark:text-bcn-primary uppercase tracking-wide">{{ __('Esta promoción') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $combinable ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                        {{ $combinable ? __('Combinable') : __('No combinable') }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ $nombre ?: __('(Sin nombre)') }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    @switch($tipo)
                                        @case('descuento_porcentaje')
                                            {{ $valor }}% de descuento
                                            @break
                                        @case('descuento_monto')
                                            $@precio($valor ?? 0) de descuento
                                            @break
                                        @case('recargo_porcentaje')
                                            {{ $valor }}% de recargo
                                            @break
                                        @case('recargo_monto')
                                            $@precio($valor ?? 0) de recargo
                                            @break
                                        @case('precio_fijo')
                                            Precio fijo $@precio($valor ?? 0)
                                            @break
                                        @case('descuento_escalonado')
                                            Descuento escalonado ({{ count($escalas) }} escalas)
                                            @break
                                        @default
                                            {{ ucfirst(str_replace('_', ' ', $tipo)) }}
                                    @endswitch
                                    @if(count($sucursalesSeleccionadas) > 0)
                                        <span class="text-gray-400 dark:text-gray-500 mx-1">•</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ count($sucursalesSeleccionadas) }} sucursal(es)</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($mostrarPrioridad)
                                    <div class="text-center">
                                        <span class="text-xs text-gray-500 block">{{ __('Prioridad') }}</span>
                                        <input type="number" wire:model.live="prioridad" min="1"
                                               class="w-16 rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-lg font-bold text-center">
                                    </div>
                                @endif
                                <button type="button"
                                        wire:click="$set('mostrarModalEdicion', true)"
                                        class="p-2 text-bcn-primary hover:bg-bcn-primary/10 rounded-lg transition-colors"
                                        :title="__('Editar promoción')"
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    @if($mostrarPrioridad)
                        {{-- Explicación de Prioridad (simplificada) --}}
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ __('La') }} <strong>{{ __('prioridad') }}</strong> {{ __('define el orden de aplicación entre promociones combinables. Cada una se calcula sobre el resultado de la anterior.') }}
                                </p>
                            </div>
                        </div>
                    @else
                        {{-- Mensaje informativo cuando no se necesita prioridad --}}
                        <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    @if(!$combinable)
                                        {{ __('Promoción no combinable. El sistema aplicará automáticamente la mejor opción para el cliente.') }}
                                    @else
                                        {{ __('No hay otras promociones combinables activas.') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Promociones Competidoras --}}
                    <div class="border dark:border-gray-700 rounded-lg">
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b dark:border-gray-600">
                            <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                {{ __('Otras promociones activas') }} ({{ count($promocionesCompetidoras) }})
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Promociones que podrían aplicarse en el mismo contexto') }}</p>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            @forelse($promocionesCompetidoras as $promo)
                                <div class="px-4 py-3 border-b dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $promo->nombre }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ ucfirst(str_replace('_', ' ', $promo->tipo)) }}
                                                @if($promo->tipo !== 'descuento_escalonado')
                                                    - {{ str_contains($promo->tipo, 'porcentaje') ? $promo->valor . '%' : '$' . formato_precio($promo->valor) }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $promo->combinable ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300' }}">
                                                {{ $promo->combinable ? __('Combinable') : __('No combinable') }}
                                            </span>
                                            @if($promo->combinable)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Prioridad') }}: {{ $promo->prioridad }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-sm">{{ __('No hay otras promociones activas') }}</p>
                                    <p class="text-xs">{{ __('Esta será la única promoción aplicable') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Columna Derecha: Simulador (70%) --}}
                <div class="lg:col-span-7 space-y-4">
                    <div class="border dark:border-gray-700 rounded-lg">
                        <div class="bg-purple-50 dark:bg-purple-900/30 px-4 py-3 border-b dark:border-gray-700">
                            <h3 class="font-semibold text-purple-900 dark:text-purple-300 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                {{ __('Simulador de Venta') }}
                            </h3>
                            <p class="text-xs text-purple-700 dark:text-purple-400 mt-1">{{ __('Prueba cómo se aplicarían las promociones') }}</p>
                        </div>

                        <div class="p-3 sm:p-4 space-y-3">
                            {{-- Filtros del simulador - Colapsable --}}
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg">
                                {{-- Header colapsable --}}
                                <button type="button"
                                        wire:click="$toggle('mostrarFiltrosSimulador')"
                                        class="w-full flex items-center justify-between p-3 text-xs font-medium text-gray-600 dark:text-gray-300 uppercase">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                        </svg>
                                        {{ __('Contexto de la venta') }}
                                    </span>
                                    <svg class="w-4 h-4 transition-transform {{ $mostrarFiltrosSimulador ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                {{-- Contenido de filtros --}}
                                <div class="p-3 pt-0 space-y-2 {{ $mostrarFiltrosSimulador ? '' : 'hidden' }}">

                                    {{-- Grid de filtros: 1 col en móvil, 2 cols en desktop --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        {{-- Sucursal --}}
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">{{ __('Sucursal') }}</label>
                                            <select wire:model.live="simuladorSucursalId"
                                                    class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                @foreach($sucursales as $suc)
                                                    @if(in_array($suc->id, $sucursalesSeleccionadas))
                                                        <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Lista de Precios --}}
                                        <div wire:key="lista-precios-{{ $simuladorSucursalId }}">
                                            <label class="text-xs text-gray-500 block mb-1">{{ __('Lista de Precios') }}</label>
                                            <select wire:model.live="simuladorListaPrecioId"
                                                    class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                @forelse($listasPreciosSimulador as $lista)
                                                    <option value="{{ $lista['id'] }}" @selected($simuladorListaPrecioId == $lista['id'])>
                                                        {{ $lista['nombre'] }}
                                                        @if($lista['es_lista_base'])
                                                            (Base{{ $lista['ajuste_porcentaje'] != 0 ? ', ' . ($lista['ajuste_porcentaje'] > 0 ? '+' : '') . $lista['ajuste_porcentaje'] . '%' : '' }})
                                                        @elseif($lista['ajuste_porcentaje'] != 0)
                                                            ({{ $lista['ajuste_porcentaje'] > 0 ? '+' : '' }}{{ $lista['ajuste_porcentaje'] }}%)
                                                        @endif
                                                    </option>
                                                @empty
                                                    <option value="">{{ __('Sin listas') }}</option>
                                                @endforelse
                                            </select>
                                            @php
                                                $listaSeleccionada = collect($listasPreciosSimulador)->firstWhere('id', $simuladorListaPrecioId);
                                            @endphp
                                            @if($listaSeleccionada)
                                                @if(!$listaSeleccionada['aplica_promociones'])
                                                    <p class="text-[10px] text-amber-600 mt-0.5 flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                        </svg>
                                                        {{ __('No aplica promociones') }}
                                                    </p>
                                                @elseif($listaSeleccionada['promociones_alcance'] === 'excluir_lista')
                                                    <p class="text-[10px] text-blue-600 mt-0.5 flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        {{ __('Excluye arts. con precio especial') }}
                                                    </p>
                                                @endif
                                            @endif
                                        </div>

                                        {{-- Forma de Venta --}}
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">{{ __('Forma Venta') }}</label>
                                            <select wire:model.live="simuladorFormaVentaId"
                                                    class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="">Todas</option>
                                                @foreach($formasVenta as $fv)
                                                    <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Canal de Venta --}}
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">{{ __('Canal Venta') }}</label>
                                            <select wire:model.live="simuladorCanalVentaId"
                                                    class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                                                <option value="">Todos</option>
                                                @foreach($canalesVenta as $cv)
                                                    <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Forma de Pago --}}
                                        <div class="sm:col-span-2">
                                            <label class="text-xs text-gray-500 block mb-1">{{ __('Forma Pago') }}</label>
                                            <select wire:model.live="simuladorFormaPagoId"
                                                    class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
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
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="abrirBuscadorArticulos"
                                            class="flex-1 flex items-center gap-2 px-3 py-2 text-sm text-left text-gray-500 bg-white border border-gray-300 rounded-lg hover:border-bcn-primary transition">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <span>{{ __('Agregar artículo...') }}</span>
                                    </button>
                                </div>

                                {{-- Modal/Dropdown de búsqueda --}}
                                @if($mostrarBuscadorArticulos)
                                    <div class="absolute z-20 left-0 right-0 mt-1 bg-white border rounded-lg shadow-xl">
                                        <div class="p-2 border-b">
                                            <input type="text"
                                                   wire:model.live.debounce.200ms="busquedaArticuloSimulador"
                                                   wire:keydown.enter="agregarPrimerArticulo"
                                                   wire:keydown.escape="cerrarBuscadorArticulos"
                                                   :placeholder="__('Nombre, código o escanear código de barras...')"
                                                   x-init="$nextTick(() => $el.focus())"
                                                   class="w-full text-sm rounded border-gray-300 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
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
                                                        class="w-full px-3 py-2 text-left hover:bg-blue-50 border-b last:border-b-0 flex items-center justify-between text-sm">
                                                    <div class="flex-1 min-w-0">
                                                        <span class="font-medium text-gray-900">{{ $art['nombre'] }}</span>
                                                        @if($art['codigo'])
                                                            <span class="text-gray-400 text-xs ml-1">({{ $art['codigo'] }})</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-right flex-shrink-0 ml-2">
                                                        @if($tieneAjuste)
                                                            <span class="text-gray-400 text-xs line-through">$@precio($precioBase)</span>
                                                            <span class="{{ $precioLista < $precioBase ? 'text-green-600' : 'text-red-600' }} font-medium ml-1">
                                                                $@precio($precioLista)
                                                            </span>
                                                        @else
                                                            <span class="text-gray-700 font-medium">$@precio($precioLista)</span>
                                                        @endif
                                                    </div>
                                                </button>
                                            @empty
                                                <div class="px-3 py-4 text-center text-gray-500 text-sm">
                                                    {{ __('No se encontraron artículos') }}
                                                </div>
                                            @endforelse
                                        </div>
                                        <div class="p-2 border-t bg-gray-50 flex items-center justify-between">
                                            <span class="text-xs text-gray-400">{{ __('Enter para agregar primero') }}</span>
                                            <button type="button"
                                                    wire:click="cerrarBuscadorArticulos"
                                                    class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded transition">
                                                {{ __('Cerrar') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Items agregados con detalle de promociones integrado --}}
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
                                                $tieneDescuento = $itemResultado && ($itemResultado['total_descuento'] > 0 || $itemResultado['total_recargo'] > 0);
                                                $excluidoPromociones = $itemResultado && ($itemResultado['excluido_promociones'] ?? false);
                                            @endphp
                                            <div class="bg-gray-50 rounded-lg p-2 {{ $excluidoPromociones ? 'ring-2 ring-amber-400 m-1' : '' }}">
                                                {{-- Línea principal del artículo con grid fijo --}}
                                                <div class="grid grid-cols-12 gap-2 items-center">
                                                    {{-- Nombre y precio unitario (5 cols) --}}
                                                    <div class="col-span-5 min-w-0">
                                                        <div class="flex items-center gap-1">
                                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $item['nombre'] ?? 'Artículo' }}</p>
                                                            @if($excluidoPromociones)
                                                                <span class="flex-shrink-0 px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px] leading-none font-medium" title="Este artículo no participa en promociones según la lista de precios seleccionada">
                                                                    SIN PROMO
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <p class="text-xs text-gray-500">
                                                            @if($tieneAjusteLista)
                                                                <span class="line-through text-gray-400">$@precio($precioBase)</span>
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
                                                               class="w-14 text-sm rounded border-gray-300 text-center py-1.5 px-1">
                                                    </div>

                                                    {{-- Subtotal original tachado (2 cols) - reservado siempre --}}
                                                    <div class="col-span-2 text-right">
                                                        @if($tieneDescuento)
                                                            <span class="text-sm text-gray-400 line-through">$@precio($itemResultado['subtotal_original'])</span>
                                                        @endif
                                                    </div>

                                                    {{-- Subtotal final (2 cols) --}}
                                                    <div class="col-span-2 text-right">
                                                        @if($tieneDescuento)
                                                            <span class="text-sm font-bold {{ $itemResultado['total_descuento'] > 0 ? 'text-green-600' : 'text-red-600' }}">$@precio($itemResultado['subtotal_final'])</span>
                                                        @else
                                                            <span class="text-sm font-semibold text-gray-900">$@precio($subtotalItem)</span>
                                                        @endif
                                                    </div>

                                                    {{-- Botón eliminar (1 col) --}}
                                                    <div class="col-span-1 flex justify-end">
                                                        <button type="button" wire:click="eliminarItemSimulador({{ $index }})"
                                                                class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Promociones aplicadas a este artículo --}}
                                                @if($itemResultado && count($itemResultado['promociones_aplicadas']) > 0)
                                                    <div class="mt-1.5 pt-1.5 border-t border-gray-200">
                                                        @foreach($itemResultado['promociones_aplicadas'] as $pa)
                                                            <div class="flex items-center justify-between text-xs {{ $pa['es_nueva'] ? 'text-yellow-700' : 'text-green-700' }}">
                                                                <span class="flex items-center gap-1">
                                                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                    <span class="truncate">{{ $pa['nombre'] }}</span>
                                                                    @if($pa['es_nueva'])
                                                                        <span class="px-1 py-0.5 bg-yellow-200 text-yellow-800 rounded text-[10px] leading-none flex-shrink-0">NUEVA</span>
                                                                    @endif
                                                                </span>
                                                                <span class="flex-shrink-0 ml-1 {{ $pa['tipo_ajuste'] === 'descuento' ? 'text-green-600' : 'text-red-600' }}">
                                                                    {{ $pa['tipo_ajuste'] === 'descuento' ? '-' : '+' }}$@precio($pa['valor_ajuste'])
                                                                    @if($pa['porcentaje'])
                                                                        <span class="text-gray-400">({{ $pa['porcentaje'] }}%)</span>
                                                                    @endif
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-6 text-gray-400 text-sm">
                                    <svg class="w-10 h-10 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p>{{ __('Agrega artículos para simular') }}</p>
                                </div>
                            @endif

                            {{-- Resultado de la simulación --}}
                            @if($resultadoSimulador && count($resultadoSimulador['items']) > 0)
                                <div class="border-t pt-3 space-y-3">
                                    {{-- Resumen de promociones --}}
                                    @if(!empty($resultadoSimulador['promociones_resumen']))
                                        @php
                                            $promoAplicadas = collect($resultadoSimulador['promociones_resumen'])->where('aplicada', true);
                                            $promoNoAplicadas = collect($resultadoSimulador['promociones_resumen'])->where('aplicada', false);
                                        @endphp

                                        @if($promoAplicadas->count() > 0)
                                            <div class="bg-green-50 border border-green-200 rounded-lg p-2">
                                                <p class="text-xs font-semibold text-green-800 mb-1">{{ __('Promociones aplicadas:') }}</p>
                                                @foreach($promoAplicadas as $pr)
                                                    <div class="flex justify-between items-center text-xs {{ $pr['es_nueva'] ? 'bg-yellow-50 rounded px-1' : '' }}">
                                                        <span class="{{ $pr['es_nueva'] ? 'text-yellow-800' : 'text-gray-700' }}">
                                                            {{ $pr['nombre'] }}
                                                            @if($pr['es_nueva'])
                                                                <span class="px-1 bg-yellow-200 text-yellow-800 rounded">NUEVA</span>
                                                            @endif
                                                            <span class="text-gray-400">({{ count($pr['aplicada_en']) }} art.)</span>
                                                        </span>
                                                        <span class="text-green-600 font-medium">
                                                            -$@precio($pr['total_descuento'])
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if($promoNoAplicadas->count() > 0)
                                            <div class="bg-gray-50 rounded-lg p-2">
                                                <p class="text-xs font-semibold text-gray-500 mb-1">{{ __('No aplicadas:') }}</p>
                                                @foreach($promoNoAplicadas as $pr)
                                                    <div class="text-xs text-gray-400 {{ $pr['es_nueva'] ? 'bg-yellow-50 rounded px-1' : '' }}">
                                                        {{ $pr['nombre'] }}
                                                        @if($pr['es_nueva'])
                                                            <span class="px-1 bg-yellow-200 text-yellow-700 rounded">NUEVA</span>
                                                        @endif
                                                        - <span class="italic">{{ $pr['razon'] ?? 'No óptima' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif

                                    {{-- Promociones a nivel de venta (precio fijo) --}}
                                    @if(!empty($resultadoSimulador['promociones_venta']))
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2 mt-2">
                                            <p class="text-xs font-semibold text-yellow-800 mb-1">{{ __('Promociones al total de la venta:') }}</p>
                                            @foreach($resultadoSimulador['promociones_venta'] as $pv)
                                                <div class="flex justify-between items-center text-xs {{ $pv['es_nueva'] ? 'bg-yellow-100 rounded px-1' : '' }}">
                                                    <span class="{{ $pv['es_nueva'] ? 'text-yellow-800' : 'text-gray-700' }}">
                                                        {{ $pv['nombre'] }}
                                                        @if($pv['es_nueva'])
                                                            <span class="px-1 bg-yellow-200 text-yellow-800 rounded">NUEVA</span>
                                                        @endif
                                                        @if(isset($pv['monto_fijo']))
                                                            <span class="text-gray-400">(Monto: $@precio($pv['monto_fijo']))</span>
                                                        @endif
                                                    </span>
                                                    <span class="text-green-600 font-medium">
                                                        -$@precio($pv['valor_ajuste'])
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Totales --}}
                                    <div class="border-t pt-2 space-y-1">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">{{ __('Subtotal') }}:</span>
                                            <span>$@precio($resultadoSimulador['subtotal'])</span>
                                        </div>

                                        @if($resultadoSimulador['total_descuentos'] > 0)
                                            <div class="flex justify-between text-sm text-green-600">
                                                <span>{{ __('Descuentos') }}:</span>
                                                <span>-$@precio($resultadoSimulador['total_descuentos'])</span>
                                            </div>
                                        @endif

                                        @if($resultadoSimulador['total_recargos'] > 0)
                                            <div class="flex justify-between text-sm text-red-600">
                                                <span>{{ __('Recargos') }}:</span>
                                                <span>+$@precio($resultadoSimulador['total_recargos'])</span>
                                            </div>
                                        @endif

                                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                                            <span>{{ __('TOTAL') }}:</span>
                                            <span class="text-bcn-primary">$@precio($resultadoSimulador['total_final'])</span>
                                        </div>

                                        @if($resultadoSimulador['total_descuentos'] > 0)
                                            <div class="bg-green-50 border border-green-200 rounded p-2 text-center">
                                                <span class="text-green-700 text-sm font-medium">
                                                    {{ __('Ahorro') }}: $@precio($resultadoSimulador['total_descuentos'])
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

        {{-- Errores de Validación --}}
        @if($errors->any())
            <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-2 text-red-800 font-medium mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Por favor corrige los siguientes errores:') }}
                </div>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Botones --}}
        <div class="flex justify-between mt-8 pt-6 border-t">
            <div>
                @if($pasoActual > 1)
                    <button wire:click="anterior" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        {{ __('← Anterior') }}
                    </button>
                @endif
            </div>

            <div class="flex gap-2">
                <a href="{{ route('configuracion.promociones') }}" wire:navigate
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                    {{ __('Cancelar') }}
                </a>

                @if($pasoActual < $totalPasos)
                    <button wire:click="siguiente" class="px-4 py-2 bg-bcn-primary text-white rounded hover:bg-bcn-primary-dark">
                        {{ __('Siguiente →') }}
                    </button>
                @else
                    <button wire:click="guardar"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span wire:loading.remove wire:target="guardar">{{ __('Guardar Promoción') }}</span>
                        <span wire:loading wire:target="guardar">{{ __('Guardando...') }}</span>
                    </button>
                @endif
            </div>
        </div>
        </div>
    </div>

    {{-- Modal de Edición Completa --}}
    @if($mostrarModalEdicion)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('mostrarModalEdicion', false)"></div>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    {{-- Header --}}
                    <div class="bg-bcn-primary px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-white" id="modal-title">{{ __('Editar Promoción') }}</h3>
                                {{-- Tipo como badge (solo lectura) --}}
                                <div class="mt-1">
                                    @php
                                        $tiposNombres = [
                                            'descuento_porcentaje' => ['nombre' => 'Descuento %', 'color' => 'bg-green-500'],
                                            'descuento_monto' => ['nombre' => 'Descuento $', 'color' => 'bg-green-500'],
                                            'descuento_escalonado' => ['nombre' => 'Escalonado', 'color' => 'bg-blue-500'],
                                            'recargo_porcentaje' => ['nombre' => 'Recargo %', 'color' => 'bg-red-400'],
                                            'recargo_monto' => ['nombre' => 'Recargo $', 'color' => 'bg-red-400'],
                                            'precio_fijo' => ['nombre' => 'Precio Fijo', 'color' => 'bg-purple-500'],
                                        ];
                                        $tipoInfo = $tiposNombres[$tipo] ?? ['nombre' => 'Sin tipo', 'color' => 'bg-gray-500'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tipoInfo['color'] }} text-white">
                                        {{ $tipoInfo['nombre'] }}
                                    </span>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('mostrarModalEdicion', false)" class="text-white/80 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Columna 1: Datos básicos y Valor --}}
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-900 border-b pb-2">{{ __('Datos Básicos') }}</h4>

                                {{-- Nombre --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nombre') }} *</label>
                                    <input type="text" wire:model.live="nombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" :placeholder="__('Ej: 10% Off Efectivo')">
                                </div>

                                {{-- Valor (si no es escalonado) --}}
                                @if($tipo !== 'descuento_escalonado')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Valor') }} *
                                            @if(str_contains($tipo ?? '', 'porcentaje'))
                                                <span class="text-gray-400">({{ __('porcentaje') }})</span>
                                            @elseif($tipo === 'precio_fijo')
                                                <span class="text-gray-400">({{ __('precio en $') }})</span>
                                            @else
                                                <span class="text-gray-400">({{ __('monto en $') }})</span>
                                            @endif
                                        </label>
                                        <input type="number" wire:model.live="valor" step="0.01" min="0"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                    </div>
                                @endif

                                {{-- Escalas (solo para escalonado) --}}
                                @if($tipo === 'descuento_escalonado')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Escalas de descuento') }}</label>
                                        <div class="space-y-2 max-h-40 overflow-y-auto">
                                            @foreach($escalas as $index => $escala)
                                                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
                                                    <div class="flex-1">
                                                        <label class="text-xs text-gray-500">{{ __('Desde cantidad') }}</label>
                                                        <input type="number" wire:model.live="escalas.{{ $index }}.cantidad_minima" min="1"
                                                               class="w-full text-sm rounded border-gray-300">
                                                    </div>
                                                    <div class="flex-1">
                                                        <label class="text-xs text-gray-500">{{ __('Descuento %') }}</label>
                                                        <input type="number" wire:model.live="escalas.{{ $index }}.valor" step="0.01" min="0"
                                                               class="w-full text-sm rounded border-gray-300">
                                                    </div>
                                                    <button type="button" wire:click="eliminarEscala({{ $index }})"
                                                            class="p-1 text-red-500 hover:bg-red-100 rounded mt-4">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <button type="button" wire:click="agregarEscala"
                                                class="mt-2 text-sm text-bcn-primary hover:underline">
                                            {{ __('+ Agregar escala') }}
                                        </button>
                                    </div>
                                @endif

                                {{-- Prioridad --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Prioridad') }}</label>
                                    <input type="number" wire:model.live="prioridad" min="1" max="100"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                    <p class="text-xs text-gray-400 mt-1">{{ __('Menor número = mayor prioridad') }}</p>
                                </div>

                                {{-- Combinable --}}
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" wire:model.live="combinable" id="modal_combinable"
                                           class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary">
                                    <label for="modal_combinable" class="text-sm text-gray-700">{{ __('Combinable con otras promociones') }}</label>
                                </div>

                                {{-- Descripción --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Descripción') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                                    <textarea wire:model="descripcion" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm" :placeholder="__('Descripción interna...')"></textarea>
                                </div>

                                {{-- Vigencia --}}
                                <div class="border-t pt-4">
                                    <h5 class="text-sm font-medium text-gray-700 mb-3">{{ __('Vigencia') }} <span class="text-gray-400">({{ __('opcional') }})</span></h5>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                                            <input type="date" wire:model.live="vigenciaDesde"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                                            <input type="date" wire:model.live="vigenciaHasta"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Columna 2: Alcance y Condiciones --}}
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-900 border-b pb-2">{{ __('Alcance') }}</h4>

                                {{-- Sucursales --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sucursales') }} *</label>
                                    <div class="border rounded-md p-2 max-h-28 overflow-y-auto space-y-1">
                                        @foreach($sucursales as $sucursal)
                                            <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                                                <input type="checkbox" wire:model.live="sucursalesSeleccionadas" value="{{ $sucursal->id }}"
                                                       class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary">
                                                {{ $sucursal->nombre }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Alcance de artículos --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Aplica a') }}</label>
                                    <select wire:model.live="alcanceArticulos" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        <option value="todos">{{ __('Todos los artículos') }}</option>
                                        <option value="categoria">{{ __('Una categoría específica') }}</option>
                                        <option value="articulo">{{ __('Un artículo específico') }}</option>
                                    </select>
                                </div>

                                @if($alcanceArticulos === 'categoria')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Categoría') }}</label>
                                        <select wire:model.live="categoriaId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                            <option value="">{{ __('Seleccionar...') }}</option>
                                            @foreach($categorias as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if($alcanceArticulos === 'articulo')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Artículo') }}</label>
                                        <select wire:model.live="articuloId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                            <option value="">{{ __('Seleccionar...') }}</option>
                                            @foreach($articulos as $art)
                                                <option value="{{ $art->id }}">{{ $art->nombre }} ({{ $art->codigo }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Condiciones --}}
                                <div class="border-t pt-4">
                                    <h5 class="text-sm font-medium text-gray-700 mb-3">{{ __('Condiciones') }} <span class="text-gray-400">({{ __('opcional') }})</span></h5>

                                    <div class="grid grid-cols-2 gap-3">
                                        {{-- Forma de Pago --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Forma de Pago') }}</label>
                                            <select wire:model.live="formaPagoId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                                <option value="">Todas</option>
                                                @foreach($formasPago as $fp)
                                                    <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Forma de Venta --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Forma de Venta') }}</label>
                                            <select wire:model.live="formaVentaId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                                <option value="">Todas</option>
                                                @foreach($formasVenta as $fv)
                                                    <option value="{{ $fv->id }}">{{ $fv->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Canal de Venta --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Canal de Venta') }}</label>
                                            <select wire:model.live="canalVentaId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                                <option value="">Todos</option>
                                                @foreach($canalesVenta as $cv)
                                                    <option value="{{ $cv->id }}">{{ $cv->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Monto Mínimo --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Monto Mínimo ($)') }}</label>
                                            <input type="number" wire:model.live="montoMinimo" min="0" step="0.01" placeholder="Sin mínimo"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        </div>

                                        {{-- Cantidad Mínima --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Cantidad Mínima') }}</label>
                                            <input type="number" wire:model.live="cantidadMinima" min="0" placeholder="Sin mínimo"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        </div>

                                        {{-- Cantidad Máxima --}}
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">{{ __('Cantidad Máxima') }}</label>
                                            <input type="number" wire:model.live="cantidadMaxima" min="0" placeholder="Sin máximo"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                        <button type="button"
                                wire:click="$set('mostrarModalEdicion', false)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
