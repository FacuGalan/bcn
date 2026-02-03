<div class="py-4 px-4 sm:px-6">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Reportes de Tesoreria') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Analisis y trazabilidad de movimientos') }}
            </p>
        </div>
    </div>

    @if($tesoreria)
    {{-- Panel de Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Tipo de Reporte --}}
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo de Reporte') }}</label>
                <select wire:model.live="tipoReporte" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
                    <option value="libro">{{ __('Libro de Tesoreria') }}</option>
                    <option value="cajas">{{ __('Resumen por Cajas') }}</option>
                    <option value="trazabilidad">{{ __('Trazabilidad de Efectivo') }}</option>
                    <option value="arqueos">{{ __('Historial de Arqueos') }}</option>
                </select>
            </div>

            {{-- Fecha Desde --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                <input type="date" wire:model="fechaDesde" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
            </div>

            {{-- Fecha Hasta --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                <input type="date" wire:model="fechaHasta" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
            </div>

            {{-- Boton Generar --}}
            <div class="flex items-end">
                <button
                    wire:click="generarReporte"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-bcn-primary rounded-lg hover:bg-bcn-primary/90"
                >
                    <svg wire:loading wire:target="generarReporte" class="inline-block w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Generar Reporte') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Resumen --}}
    @if(!empty($resumen))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ __('Resumen del Periodo:') }} {{ $resumen['periodo']['desde'] ?? '' }} - {{ $resumen['periodo']['hasta'] ?? '' }}
        </h3>

        @if($tipoReporte === 'libro')
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo Inicial') }}</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($resumen['saldo_inicial'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                <p class="text-xs text-green-600 dark:text-green-400 uppercase">{{ __('Ingresos') }}</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300">+${{ number_format($resumen['total_ingresos'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                <p class="text-xs text-red-600 dark:text-red-400 uppercase">{{ __('Egresos') }}</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300">-${{ number_format($resumen['total_egresos'] ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase">{{ __('Saldo Final') }}</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300">${{ number_format($resumen['saldo_final'] ?? 0, 2, ',', '.') }}</p>
            </div>
        </div>
        @elseif($tipoReporte === 'cajas')
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                <p class="text-xs text-red-600 dark:text-red-400 uppercase">{{ __('Provisiones') }}</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300">${{ number_format($resumen['total_provisiones'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                <p class="text-xs text-green-600 dark:text-green-400 uppercase">{{ __('Rendiciones') }}</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300">${{ number_format($resumen['total_rendiciones'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase">{{ __('Sobrantes') }}</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300">${{ number_format($resumen['total_sobrantes'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center">
                <p class="text-xs text-amber-600 dark:text-amber-400 uppercase">{{ __('Faltantes') }}</p>
                <p class="text-xl font-bold text-amber-700 dark:text-amber-300">${{ number_format($resumen['total_faltantes'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">
                <p class="text-xs text-purple-600 dark:text-purple-400 uppercase">{{ __('Diferencia Neta') }}</p>
                <p class="text-xl font-bold text-purple-700 dark:text-purple-300">${{ number_format($resumen['total_diferencias'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Cajas Activas') }}</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $resumen['cajas_con_operaciones'] ?? 0 }}</p>
            </div>
        </div>
        @elseif($tipoReporte === 'trazabilidad')
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="text-xs text-red-600 dark:text-red-400 uppercase">{{ __('Provisiones') }}</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300">${{ number_format($resumen['provisiones']['total'] ?? 0, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500">{{ $resumen['provisiones']['cantidad'] ?? 0 }} {{ __('operaciones') }}</p>
            </div>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-xs text-green-600 dark:text-green-400 uppercase">{{ __('Rendiciones') }}</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300">${{ number_format($resumen['rendiciones']['total'] ?? 0, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500">{{ $resumen['rendiciones']['cantidad'] ?? 0 }} {{ __('operaciones') }}</p>
            </div>
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase">{{ __('Depositos') }}</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300">${{ number_format($resumen['depositos']['total'] ?? 0, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500">{{ $resumen['depositos']['cantidad'] ?? 0 }} {{ __('operaciones') }}</p>
            </div>
        </div>
        @elseif($tipoReporte === 'arqueos')
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ __('Total Arqueos') }}</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $resumen['total_arqueos'] ?? 0 }}</p>
            </div>
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                <p class="text-xs text-green-600 dark:text-green-400 uppercase">{{ __('Cuadrados') }}</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ $resumen['arqueos_cuadrados'] ?? 0 }}</p>
            </div>
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase">{{ __('Sobrantes') }}</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300">${{ number_format($resumen['total_sobrantes'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                <p class="text-xs text-red-600 dark:text-red-400 uppercase">{{ __('Faltantes') }}</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300">${{ number_format($resumen['total_faltantes'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Datos del Reporte --}}
    @if(!empty($datosReporte))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            @if($tipoReporte === 'libro')
            {{-- Tabla Libro de Tesoreria --}}
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuario') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ingreso') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Egreso') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($datosReporte as $mov)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $mov['fecha'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $mov['concepto'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $mov['usuario'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400 font-medium">
                            {{ $mov['tipo'] === 'ingreso' ? '+$' . number_format($mov['monto'], 2, ',', '.') : '' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400 font-medium">
                            {{ $mov['tipo'] === 'egreso' ? '-$' . number_format($mov['monto'], 2, ',', '.') : '' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-semibold">${{ number_format($mov['saldo_posterior'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @elseif($tipoReporte === 'cajas')
            {{-- Tabla Resumen por Cajas --}}
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Caja') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Provisiones') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Rendiciones') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sobrantes') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Faltantes') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($datosReporte as $caja)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            {{ $caja['caja_nombre'] }}
                            <span class="text-xs text-gray-500">#{{ $caja['caja_numero'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400">
                            ${{ number_format($caja['total_provisiones'], 0, ',', '.') }}
                            <span class="text-xs text-gray-400">({{ $caja['cantidad_provisiones'] }})</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400">
                            ${{ number_format($caja['total_rendiciones'], 0, ',', '.') }}
                            <span class="text-xs text-gray-400">({{ $caja['cantidad_rendiciones'] }})</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-blue-600 dark:text-blue-400">${{ number_format($caja['sobrantes'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right text-amber-600 dark:text-amber-400">${{ number_format($caja['faltantes'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold {{ $caja['balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $caja['balance'] >= 0 ? '+' : '' }}${{ number_format($caja['balance'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @elseif($tipoReporte === 'trazabilidad')
            {{-- Tabla Trazabilidad --}}
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Tipo') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Origen') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Destino') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Monto') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($datosReporte as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $item['fecha'] }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($item['tipo'] === 'provision')
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Provision') }}</span>
                            @elseif($item['tipo'] === 'rendicion')
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ __('Rendicion') }}</span>
                            @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ __('Deposito') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item['origen'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item['destino'] }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">${{ number_format($item['monto'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-center">
                            @if($item['estado'] === 'confirmado')
                            <span class="text-green-600 dark:text-green-400">{{ __('Confirmado') }}</span>
                            @elseif($item['estado'] === 'pendiente')
                            <span class="text-amber-600 dark:text-amber-400">{{ __('Pendiente') }}</span>
                            @else
                            <span class="text-gray-500">{{ ucfirst($item['estado']) }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @elseif($tipoReporte === 'arqueos')
            {{-- Tabla Arqueos --}}
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuario') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sistema') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Contado') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Diferencia') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($datosReporte as $arq)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $arq['fecha'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $arq['usuario'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-300">${{ number_format($arq['saldo_sistema'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white font-medium">${{ number_format($arq['saldo_contado'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold {{ $arq['diferencia'] == 0 ? 'text-green-600' : ($arq['diferencia'] > 0 ? 'text-blue-600' : 'text-red-600') }}">
                            @if($arq['diferencia'] == 0)
                                {{ __('Cuadrado') }}
                            @elseif($arq['diferencia'] > 0)
                                +${{ number_format($arq['diferencia'], 2, ',', '.') }}
                            @else
                                -${{ number_format(abs($arq['diferencia']), 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-center">
                            @if($arq['estado'] === 'aprobado')
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ __('Aprobado') }}</span>
                            @elseif($arq['estado'] === 'pendiente')
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Pendiente') }}</span>
                            @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Rechazado') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @elseif(empty($datosReporte) && !empty($resumen))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <p class="text-gray-500 dark:text-gray-400">{{ __('No hay datos para mostrar en el periodo seleccionado') }}</p>
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">{{ __('Seleccione un reporte') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Configure los filtros y haga clic en "Generar Reporte" para ver los datos.') }}</p>
    </div>
    @endif
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
        <p class="text-gray-500 dark:text-gray-400">{{ __('Seleccione una sucursal para ver los reportes') }}</p>
    </div>
    @endif
</div>
