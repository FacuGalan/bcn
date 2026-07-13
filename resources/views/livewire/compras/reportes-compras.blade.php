{{-- Reportes de compras (RF-22, patrón ReportesTesoreria) --}}
@php $labelTipo = fn (string $tipo) => __('compra_tipo_'.$tipo); @endphp
<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">
                {{ __('Reportes de Compras') }}
            </h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                {{ __('¿Cuánto gasté y en qué? Compras completadas menos notas de crédito, por cuenta, proveedor o mes') }}
            </p>
        </div>

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Corte') }}</label>
                        <select wire:model.live="tipoReporte"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="cuenta">{{ __('Por cuenta de compra') }}</option>
                            <option value="proveedor">{{ __('Por proveedor') }}</option>
                            <option value="mes">{{ __('Por mes') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Desde') }}</label>
                        <input type="date" wire:model="fechaDesde"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Hasta') }}</label>
                        <input type="date" wire:model="fechaHasta"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    <div class="flex items-end">
                        <button wire:click="generarReporte" wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg wire:loading wire:target="generarReporte" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Generar Reporte') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resumen del período --}}
        @if($resumen !== [])
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
                <div class="p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        {{ __('Resumen del Período') }}: {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}
                    </h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-center">
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ __('Comprobantes') }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $resumen['comprobantes'] }}</p>
                        </div>
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                            <p class="text-xs uppercase text-blue-600 dark:text-blue-300">{{ __('Compras') }}</p>
                            <p class="text-xl font-bold text-blue-700 dark:text-blue-200">$@precio($resumen['compras'])</p>
                        </div>
                        <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">
                            <p class="text-xs uppercase text-purple-600 dark:text-purple-300">{{ __('Notas de crédito') }}</p>
                            <p class="text-xl font-bold text-purple-700 dark:text-purple-200">−$@precio($resumen['notas_credito'])</p>
                        </div>
                        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                            <p class="text-xs uppercase text-green-600 dark:text-green-300">{{ __('Neto del período') }}</p>
                            <p class="text-xl font-bold text-green-700 dark:text-green-200">$@precio($resumen['neto'])</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Datos --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            @if($datosReporte !== [])
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-bcn-light dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ match($tipoReporte) { 'proveedor' => __('Proveedor'), 'mes' => __('Mes'), default => __('Cuenta de compra') } }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Comprobantes') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Compras') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('NC') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Neto') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($datosReporte as $fila)
                                <tr wire:key="grupo-{{ $fila['clave'] }}"
                                    wire:click="expandirGrupo('{{ $fila['clave'] }}')"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 cursor-pointer {{ $grupoExpandido === $fila['clave'] ? 'bg-gray-50 dark:bg-gray-700' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-gray-400 transition-transform {{ $grupoExpandido === $fila['clave'] ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            {{ $fila['etiqueta'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600 dark:text-gray-400">{{ $fila['comprobantes'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">$@precio($fila['compras'])</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-purple-600 dark:text-purple-400">
                                        {{ $fila['notas_credito'] > 0 ? '−$'.number_format($fila['notas_credito'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900 dark:text-white">$@precio($fila['neto'])</td>
                                </tr>
                                {{-- Drill-down: compras del grupo --}}
                                @if($grupoExpandido === $fila['clave'] && $comprasDetalle !== [])
                                    <tr wire:key="detalle-{{ $fila['clave'] }}">
                                        <td colspan="5" class="px-6 py-3 bg-gray-50 dark:bg-gray-900/50">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr>
                                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('N°') }}</th>
                                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Proveedor') }}</th>
                                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Comprobante') }}</th>
                                                        <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                                                        <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($comprasDetalle as $compra)
                                                        <tr>
                                                            <td class="px-3 py-1.5 text-gray-800 dark:text-gray-200">
                                                                {{ $compra['numero'] }}
                                                                @if($compra['es_nc'])
                                                                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">NC</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $compra['proveedor'] }}</td>
                                                            <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">
                                                                {{ $labelTipo($compra['tipo']) }}
                                                                @if($compra['numero_proveedor']) · {{ $compra['numero_proveedor'] }} @endif
                                                            </td>
                                                            <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $compra['fecha'] }}</td>
                                                            <td class="px-3 py-1.5 text-right {{ $compra['es_nc'] ? 'text-purple-600 dark:text-purple-400' : 'text-gray-900 dark:text-white' }}">
                                                                {{ $compra['es_nc'] ? '−' : '' }}$@precio($compra['total'])
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif($resumen !== [])
                <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-sm">{{ __('No hay compras en el período seleccionado') }}</p>
                </div>
            @else
                <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('Elegí el corte y el período, y generá el reporte') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
