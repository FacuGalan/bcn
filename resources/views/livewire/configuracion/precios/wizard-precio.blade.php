<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Crear Nuevo Precio</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Asistente paso a paso para configurar un nuevo precio</p>
    </div>

    {{-- Progress Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            @for($i = 1; $i <= $totalPasos; $i++)
                <div class="flex items-center flex-1">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $pasoActual >= $i ? 'bg-bcn-primary text-white' : 'bg-gray-200 text-gray-600 dark:text-gray-300' }} font-bold">
                        {{ $i }}
                    </div>
                    @if($i < $totalPasos)
                        <div class="flex-1 h-1 mx-2 {{ $pasoActual > $i ? 'bg-bcn-primary' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endfor
        </div>
        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-300">
            <span class="{{ $pasoActual == 1 ? 'font-bold text-bcn-primary' : '' }}">Artículo</span>
            <span class="{{ $pasoActual == 2 ? 'font-bold text-bcn-primary' : '' }}">Contexto</span>
            <span class="{{ $pasoActual == 3 ? 'font-bold text-bcn-primary' : '' }}">Precio y Vigencia</span>
        </div>
    </div>

    {{-- Contenido del Paso Actual --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">

        {{-- PASO 1: Artículo --}}
        @if($pasoActual == 1)
            <h2 class="text-xl font-semibold mb-4">Paso 1: Selecciona el Artículo</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Buscar Artículo</label>
                <input type="text"
                       wire:model.live.debounce.300ms="busquedaArticulo"
                       placeholder="Escribe el nombre o código del artículo..."
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
            </div>

            @if($articuloId)
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <strong>Artículo seleccionado:</strong> {{ $busquedaArticulo }}
                </div>
            @endif

            @if(count($articulos) > 0 && !$articuloId)
                <div class="border rounded-lg max-h-60 overflow-y-auto">
                    @foreach($articulos as $articulo)
                        <button wire:click="seleccionarArticulo({{ $articulo->id }})"
                                class="w-full p-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 border-b last:border-b-0 transition">
                            <div class="font-medium">{{ $articulo->nombre }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $articulo->codigo }}</div>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- PASO 2: Contexto --}}
        @if($pasoActual == 2)
            <h2 class="text-xl font-semibold mb-4">Paso 2: Configura el Contexto</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Define dónde aplicará este precio</p>

            <div class="grid grid-cols-1 gap-4">
                {{-- Sucursales (múltiple selección) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Sucursales * (selecciona una o más)</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto border rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                        @foreach($sucursales as $sucursal)
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 dark:bg-gray-900 p-2 rounded transition">
                                <input type="checkbox"
                                       wire:model="sucursalesSeleccionadas"
                                       value="{{ $sucursal->id }}"
                                       class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                <span class="text-sm">{{ $sucursal->nombre }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if(count($sucursalesSeleccionadas) > 0)
                        <p class="text-xs text-green-600 mt-2">
                            {{ count($sucursalesSeleccionadas) }} sucursal(es) seleccionada(s)
                        </p>
                    @endif
                    @error('sucursalesSeleccionadas') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

                {{-- Forma de Venta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Forma de Venta (Opcional)</label>
                    <select wire:model="formaVentaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="">Todas las formas</option>
                        @foreach($formasVenta as $forma)
                            <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Canal de Venta --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Canal de Venta (Opcional)</label>
                    <select wire:model="canalVentaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        <option value="">Todos los canales</option>
                        @foreach($canalesVenta as $canal)
                            <option value="{{ $canal->id }}">{{ $canal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>Tip:</strong> Deja las opciones en blanco para que el precio aplique de forma genérica.
                </p>
            </div>
        @endif

        {{-- PASO 3: Precio y Vigencia --}}
        @if($pasoActual == 3)
            <h2 class="text-xl font-semibold mb-4">Paso 3: Precio y Vigencia</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                {{-- Precio --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Precio *</label>
                    <input type="number"
                           wire:model.blur="precio"
                           wire:change="verificarConflictos"
                           step="0.01"
                           min="0"
                           placeholder="0.00"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    @error('precio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="border-t pt-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Vigencia (Opcional)</h3>
                <p class="text-xs text-gray-600 dark:text-gray-300 mb-3">Define el período en el que este precio estará vigente. Si dejas ambas fechas vacías, el precio será permanente.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Vigencia Desde --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vigencia Desde</label>
                        <input type="date"
                               wire:model.blur="vigenciaDesde"
                               wire:change="verificarConflictos"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>

                    {{-- Vigencia Hasta --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vigencia Hasta</label>
                        <input type="date"
                               wire:model.blur="vigenciaHasta"
                               wire:change="verificarConflictos"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                </div>
            </div>

            <div class="flex items-center">
                <input type="checkbox" wire:model="activo" id="activo" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                <label for="activo" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Precio activo</label>
            </div>

            {{-- Resumen --}}
            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <h4 class="font-semibold mb-2">Resumen del Precio:</h4>
                <div class="text-sm space-y-1">
                    <div><strong>Artículo:</strong> {{ $busquedaArticulo }}</div>
                    <div>
                        <strong>Sucursales:</strong>
                        @if(count($sucursalesSeleccionadas) > 0)
                            <ul class="list-disc list-inside ml-2 mt-1">
                                @foreach($sucursalesSeleccionadas as $sucursalId)
                                    <li>{{ $sucursales->find($sucursalId)?->nombre }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-red-500">Ninguna seleccionada</span>
                        @endif
                    </div>
                    @if($formaVentaId)
                        <div><strong>Forma de Venta:</strong> {{ $formasVenta->find($formaVentaId)?->nombre }}</div>
                    @endif
                    @if($canalVentaId)
                        <div><strong>Canal de Venta:</strong> {{ $canalesVenta->find($canalVentaId)?->nombre }}</div>
                    @endif
                    <div class="pt-2 border-t mt-2"><strong>Precio:</strong> $@precio($precio ?? 0)</div>
                    @if($vigenciaDesde || $vigenciaHasta)
                        <div class="pt-2 border-t mt-2">
                            <strong>Vigencia:</strong>
                            @if($vigenciaDesde && $vigenciaHasta)
                                Desde {{ \Carbon\Carbon::parse($vigenciaDesde)->format('d/m/Y') }} hasta {{ \Carbon\Carbon::parse($vigenciaHasta)->format('d/m/Y') }}
                            @elseif($vigenciaDesde)
                                Desde {{ \Carbon\Carbon::parse($vigenciaDesde)->format('d/m/Y') }}
                            @elseif($vigenciaHasta)
                                Hasta {{ \Carbon\Carbon::parse($vigenciaHasta)->format('d/m/Y') }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Conflictos detectados --}}
            @if(count($preciosConflictivos) > 0)
                <div class="mt-6 p-4 bg-red-50 border-2 border-red-300 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-red-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1">
                            <h4 class="font-semibold text-red-800 mb-2">⚠ Conflictos Detectados ({{ count($preciosConflictivos) }})</h4>
                            <p class="text-xs text-red-700 mb-3">Los siguientes precios se solaparían con el precio que intentas crear:</p>
                            <div class="space-y-3 max-h-60 overflow-y-auto">
                                @foreach($preciosConflictivos as $conflicto)
                                    <div class="bg-white dark:bg-gray-800 border border-red-200 rounded p-3 text-xs">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div><strong>Sucursal:</strong> {{ $conflicto->sucursal->nombre }}</div>
                                            <div><strong>Precio:</strong> $@precio($conflicto->precio)</div>
                                            <div><strong>Forma Venta:</strong> {{ $conflicto->formaVenta?->nombre ?? 'Todas' }}</div>
                                            <div><strong>Canal:</strong> {{ $conflicto->canalVenta?->nombre ?? 'Todos' }}</div>
                                            <div class="col-span-2">
                                                <strong>Vigencia:</strong>
                                                @if($conflicto->vigencia_desde && $conflicto->vigencia_hasta)
                                                    {{ $conflicto->vigencia_desde->format('d/m/Y') }} al {{ $conflicto->vigencia_hasta->format('d/m/Y') }}
                                                @elseif($conflicto->vigencia_desde)
                                                    Desde {{ $conflicto->vigencia_desde->format('d/m/Y') }}
                                                @elseif($conflicto->vigencia_hasta)
                                                    Hasta {{ $conflicto->vigencia_hasta->format('d/m/Y') }}
                                                @else
                                                    <span class="text-orange-600 font-semibold">Permanente</span>
                                                @endif
                                            </div>
                                            <div class="col-span-2">
                                                <strong>Estado:</strong>
                                                <span class="px-2 py-1 rounded-full text-xs {{ $conflicto->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-gray-900 text-gray-800' }}">
                                                    {{ $conflicto->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- Botones de Navegación --}}
        <div class="flex justify-between mt-8 pt-6 border-t">
            <div>
                @if($pasoActual > 1)
                    <button wire:click="anterior"
                            class="px-4 py-2 bg-gray-200 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 transition">
                        ← Anterior
                    </button>
                @endif
            </div>

            <div class="flex gap-2">
                <a href="{{ route('configuracion.precios') }}"
                   wire:navigate
                   class="px-4 py-2 bg-gray-200 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 transition">
                    Cancelar
                </a>

                @if($pasoActual < $totalPasos)
                    <button wire:click="siguiente"
                            class="px-4 py-2 bg-bcn-primary text-white rounded-md hover:bg-bcn-primary-dark transition">
                        Siguiente →
                    </button>
                @else
                    <button wire:click="guardar"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        Guardar Precio
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
