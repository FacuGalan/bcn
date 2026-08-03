{{-- Canje de artículos por puntos (RF-T58/RF-T59/RF-T60). Guardado
     INMEDIATO por acción (sin botón guardar): habilitación por sucursal
     (pivot canje_tienda — el mismo flag de la tienda online), puntos fijos
     y modo de opcionales (campos GLOBALES del artículo). --}}
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mt-6 space-y-5">
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ __('Canje de artículos por puntos') }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Elegí qué artículos se pueden canjear, cuántos puntos cuestan y cómo juegan los opcionales. Se guarda al instante.') }}
        </p>
    </div>

    {{-- Switch de restricción (RF-T58) --}}
    <div class="flex items-start justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ __('Restringir canje a artículos habilitados') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Apagado: cualquier artículo se canjea en el punto de venta y el pago con puntos cubre toda la venta. Prendido: solo los artículos habilitados acá participan del canje — por artículo y como pago — en el POS y la tienda; el resto (y el envío) se paga con plata.') }}
            </p>
        </div>
        <button type="button" wire:click="toggleRestringirCanje"
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 {{ $restringirCanjeArticulos ? 'bg-bcn-primary' : 'bg-gray-200 dark:bg-gray-600' }}"
            role="switch" aria-checked="{{ $restringirCanjeArticulos ? 'true' : 'false' }}">
            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $restringirCanjeArticulos ? 'translate-x-5' : 'translate-x-0' }}"></span>
        </button>
    </div>

    {{-- Cómo funciona cada modo de opcionales (RF-T59, ejemplos vivos) --}}
    @php($vp = (float) $valorPuntoCanje > 0 ? (float) $valorPuntoCanje : null)
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 space-y-1.5">
        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300">
            {{ __('Cómo se calcula el costo de un canje') }}
        </h4>
        <p class="text-xs text-blue-700 dark:text-blue-400">
            {{ __('Si el artículo tiene puntos cargados, ese es su costo; vacío ("Auto") se calcula del precio del día según el valor del punto.') }}
            {{ __('Ejemplo: artículo de $1.000 con un opcional de $500') }}@if($vp) ({{ __('con el punto a') }} ${{ number_format($vp, 2) }})@endif:
        </p>
        <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1 list-disc pl-4">
            <li>
                <span class="font-semibold">{{ __('Incluidos en el canje') }}:</span>
                {{ __('el canje cubre artículo + opcionales.') }}
                @if($vp) {{ __('Auto: cuesta :pts pts ($1.500 canjeados). Con puntos fijos, cuesta esos puntos elija lo que elija.', ['pts' => (int) ceil(1500 / $vp)]) }} @endif
            </li>
            <li>
                <span class="font-semibold">{{ __('Se cobran en plata') }}:</span>
                {{ __('el canje cubre solo el artículo; el opcional se paga aparte.') }}
                @if($vp) {{ __('Auto: cuesta :pts pts y el cliente paga $500.', ['pts' => (int) ceil(1000 / $vp)]) }} @endif
            </li>
            <li>
                <span class="font-semibold">{{ __('Se suman en puntos') }}:</span>
                {{ __('el opcional se convierte a puntos y se suma al costo.') }}
                @if($vp) {{ __('Auto: cuesta :pts pts (:base del artículo + :extra del opcional).', ['pts' => (int) (ceil(1000 / $vp) + ceil(500 / $vp)), 'base' => (int) ceil(1000 / $vp), 'extra' => (int) ceil(500 / $vp)]) }} @endif
            </li>
        </ul>
    </div>

    {{-- Controles: sucursal + búsqueda + masivo --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        @if(count($sucursalesConfig) > 1)
            <select wire:model.live="canjeSucursalId"
                class="rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @foreach($sucursalesConfig as $sucursalId => $config)
                    <option value="{{ $sucursalId }}">{{ $config['nombre'] }}</option>
                @endforeach
            </select>
        @endif

        <input type="search" wire:model.live.debounce.400ms="busquedaCanje"
            placeholder="{{ __('Buscar artículo…') }}"
            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" />

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" wire:click="habilitarTodosCanje"
                wire:confirm="{{ __('¿Habilitar el canje para TODOS los artículos de la sucursal?') }}"
                class="px-3 py-1.5 text-xs font-medium rounded-md border border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300 hover:bg-violet-50 dark:hover:bg-violet-900/30 transition-colors">
                {{ __('Habilitar todos') }}
            </button>
            <button type="button" wire:click="quitarTodosCanje"
                wire:confirm="{{ __('¿Quitar el canje de TODOS los artículos de la sucursal?') }}"
                class="px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                {{ __('Quitar todos') }}
            </button>
        </div>
    </div>

    {{-- Tabla de artículos --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/60">
                <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                    <th class="px-3 py-2">{{ __('Artículo') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('Canje') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Puntos') }}</th>
                    <th class="px-3 py-2">{{ __('Opcionales en el canje') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                @forelse($articulosCanje as $articulo)
                    @php($canjeOn = (bool) ($articulo->sucursales->first()?->pivot?->canje_tienda))
                    <tr wire:key="pp-canje-{{ $articulo->id }}">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $articulo->nombre }}</p>
                            @if($articulo->codigo)
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ $articulo->codigo }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button type="button" wire:click="toggleCanjeArticulo({{ $articulo->id }})"
                                class="px-1.5 py-0.5 rounded-md border text-[10px] font-bold transition-colors {{ $canjeOn ? 'bg-violet-100 text-violet-700 border-violet-300 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-700' : 'text-gray-400 dark:text-gray-500 border-gray-300 dark:border-gray-600 hover:text-gray-500 dark:hover:text-gray-400' }}"
                                title="{{ $canjeOn ? __('Quitar canje por puntos') : __('Permitir canje por puntos') }}">
                                pts.
                            </button>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <input type="number" min="0" step="1" inputmode="numeric"
                                value="{{ (int) $articulo->puntos_canje > 0 ? (int) $articulo->puntos_canje : '' }}"
                                placeholder="{{ __('Auto') }}" @disabled(! $canjeOn)
                                wire:change="guardarPuntosCanjeArticulo({{ $articulo->id }}, $event.target.value)"
                                title="{{ __('Puntos para canjear este artículo (vacío = se calcula del precio)') }}"
                                class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1 px-1.5 text-right focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 disabled:opacity-40" />
                        </td>
                        <td class="px-3 py-2">
                            <select wire:change="guardarCanjeOpcionales({{ $articulo->id }}, $event.target.value)" @disabled(! $canjeOn)
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1 focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 disabled:opacity-40">
                                <option value="incluidos" @selected(($articulo->canje_opcionales ?? 'incluidos') === 'incluidos')>{{ __('Incluidos en el canje') }}</option>
                                <option value="en_plata" @selected($articulo->canje_opcionales === 'en_plata')>{{ __('Se cobran en plata') }}</option>
                                <option value="en_puntos" @selected($articulo->canje_opcionales === 'en_puntos')>{{ __('Se suman en puntos') }}</option>
                            </select>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('No hay artículos para mostrar') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($articulosCanjeTotal > $maxFilasCanje)
        <p class="text-xs text-gray-400 dark:text-gray-500">
            {{ __('Se muestran :max de :total artículos — refiná la búsqueda para ver el resto', ['max' => $maxFilasCanje, 'total' => $articulosCanjeTotal]) }}
        </p>
    @endif
</div>
