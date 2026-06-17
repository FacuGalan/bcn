<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- ==================== Header ==================== --}}
        <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Libros IVA') }}</h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Subdiarios de IVA ventas y compras por CUIT y período') }}
                </p>
            </div>
            <button wire:click="exportarCsv"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                <span class="hidden sm:inline">{{ __('Exportar CSV') }}</span>
            </button>
        </div>

        {{-- ==================== Filtros ==================== --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('CUIT') }}</label>
                    <select wire:model.live="cuitId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        @forelse($cuits as $c)
                            <option value="{{ $c->id }}">{{ $c->razon_social }} ({{ $c->numero_cuit }})</option>
                        @empty
                            <option value="">{{ __('No hay CUITs configurados') }}</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Período') }}</label>
                    <input type="month" wire:model.live="periodo"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                </div>
            </div>
        </div>

        {{-- ==================== Tabs ==================== --}}
        <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-4">
                <button wire:click="setTab('ventas')"
                    class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm {{ $tab === 'ventas' ? 'border-bcn-primary text-bcn-primary' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300' }}">
                    {{ __('Ventas') }}
                </button>
                <button wire:click="setTab('compras')"
                    class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm {{ $tab === 'compras' ? 'border-bcn-primary text-bcn-primary' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300' }}">
                    {{ __('Compras') }}
                </button>
            </nav>
        </div>

        @if($tab === 'ventas')
            {{-- ==================== Libro IVA Ventas ==================== --}}
            @if($comprobantes->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No hay comprobantes emitidos en el período.') }}</p>
                </div>
            @else
                {{-- Cards móvil --}}
                <div class="sm:hidden space-y-3">
                    @foreach($comprobantes as $c)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $c->tipo_legible }} {{ $c->numero_formateado }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ optional($c->fecha_emision)->format('d/m/Y') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $c->receptor_nombre }}</p>
                            <dl class="mt-2 grid grid-cols-2 gap-1 text-xs">
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('Neto gravado') }}</dt>
                                <dd class="text-right text-gray-900 dark:text-white">${{ number_format((float) $c->neto_gravado, 2, ',', '.') }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('IVA') }}</dt>
                                <dd class="text-right text-gray-900 dark:text-white">${{ number_format((float) $c->iva_total, 2, ',', '.') }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Total') }}</dt>
                                <dd class="text-right font-bold text-gray-900 dark:text-white">${{ number_format((float) $c->total, 2, ',', '.') }}</dd>
                            </dl>
                        </div>
                    @endforeach
                </div>

                {{-- Tabla desktop --}}
                <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-x-auto shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Fecha') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Comprobante') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Receptor') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Neto gravado') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('No gravado') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Exento') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('IVA') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Tributos') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comprobantes as $c)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ optional($c->fecha_emision)->format('d/m/Y') }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $c->tipo_legible }} {{ $c->numero_formateado }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $c->receptor_nombre }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->neto_gravado, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->neto_no_gravado, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->neto_exento, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->iva_total, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->tributos, 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">${{ number_format((float) $c->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($totalesVentas)
                            <tfoot class="bg-gray-50 dark:bg-gray-900/50 font-semibold">
                                <tr>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-white" colspan="3">{{ __('Totales') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['neto_gravado'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['neto_no_gravado'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['neto_exento'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['iva'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['tributos'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($totalesVentas['total'], 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        @else
            {{-- ==================== Libro IVA Compras ==================== --}}
            @if($compras->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No hay compras con crédito fiscal en el período.') }}</p>
                </div>
            @else
                {{-- Cards móvil --}}
                <div class="sm:hidden space-y-3">
                    @foreach($compras as $linea)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ __('Compra') }} #{{ $linea['origen_id'] }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $linea['fecha'] ? \Carbon\Carbon::parse($linea['fecha'])->format('d/m/Y') : '' }}</span>
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-1 text-xs">
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('Crédito fiscal') }}</dt>
                                <dd class="text-right text-gray-900 dark:text-white">${{ number_format($linea['credito_fiscal'], 2, ',', '.') }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('Percepciones') }}</dt>
                                <dd class="text-right text-gray-900 dark:text-white">${{ number_format($linea['percepciones'], 2, ',', '.') }}</dd>
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('Retenciones') }}</dt>
                                <dd class="text-right text-gray-900 dark:text-white">${{ number_format($linea['retenciones'], 2, ',', '.') }}</dd>
                            </dl>
                        </div>
                    @endforeach
                </div>

                {{-- Tabla desktop --}}
                <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-x-auto shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Fecha') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Compra') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Crédito fiscal') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Percepciones') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Retenciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($compras as $linea)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $linea['fecha'] ? \Carbon\Carbon::parse($linea['fecha'])->format('d/m/Y') : '' }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-white">#{{ $linea['origen_id'] }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($linea['credito_fiscal'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($linea['percepciones'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-900 dark:text-white whitespace-nowrap">${{ number_format($linea['retenciones'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
