<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- ==================== Header ==================== --}}
        <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Posición fiscal') }}</h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                    {{ __('IVA e IIBB por CUIT y período') }}
                </p>
            </div>
            @if($posicionIva)
                <button wire:click="exportarCsv"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span class="hidden sm:inline">{{ __('Exportar CSV') }}</span>
                </button>
            @endif
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

        @if(! $posicionIva)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Seleccioná un CUIT y un período para ver la posición fiscal.') }}</p>
            </div>
        @else
            {{-- ==================== Posición IVA ==================== --}}
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-bcn-secondary dark:text-white uppercase tracking-wide mb-3">{{ __('Posición IVA') }}</h3>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Débito fiscal') }}</p>
                        <p class="mt-1 text-lg font-bold text-red-600 dark:text-red-400">${{ number_format($posicionIva['debito_fiscal'], 2, ',', '.') }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Crédito fiscal') }}</p>
                        <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">${{ number_format($posicionIva['credito_fiscal'], 2, ',', '.') }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo técnico') }}</p>
                        <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">${{ number_format($posicionIva['saldo_tecnico'], 2, ',', '.') }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('A cuenta (percep./retenc. IVA)') }}</p>
                        <p class="mt-1 text-lg font-bold text-blue-600 dark:text-blue-400">${{ number_format($posicionIva['a_cuenta'], 2, ',', '.') }}</p>
                    </div>
                </div>

                {{-- Resultado del período --}}
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    @if($posicionIva['a_pagar'] > 0)
                        <div class="bg-red-50 dark:bg-red-900/30 rounded-lg border border-red-200 dark:border-red-800 p-4">
                            <p class="text-xs text-red-700 dark:text-red-300">{{ __('IVA a pagar') }}</p>
                            <p class="mt-1 text-2xl font-bold text-red-700 dark:text-red-300">${{ number_format($posicionIva['a_pagar'], 2, ',', '.') }}</p>
                        </div>
                    @else
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-800 p-4">
                            <p class="text-xs text-green-700 dark:text-green-300">{{ __('Saldo a favor') }}</p>
                            <p class="mt-1 text-2xl font-bold text-green-700 dark:text-green-300">${{ number_format($posicionIva['saldo_a_favor'], 2, ',', '.') }}</p>
                        </div>
                    @endif

                    @if($posicionIva['percepciones_iva_aplicadas'] > 0 || $posicionIva['retenciones_iva_aplicadas'] > 0)
                        <div class="bg-amber-50 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-800 p-4">
                            <p class="text-xs text-amber-700 dark:text-amber-300">{{ __('IVA percibido/retenido como agente (a depositar — no integra la posición)') }}</p>
                            <p class="mt-1 text-lg font-bold text-amber-700 dark:text-amber-300">
                                ${{ number_format($posicionIva['percepciones_iva_aplicadas'] + $posicionIva['retenciones_iva_aplicadas'], 2, ',', '.') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ==================== Posición IIBB ==================== --}}
            <div>
                <h3 class="text-sm font-semibold text-bcn-secondary dark:text-white uppercase tracking-wide mb-3">{{ __('Posición IIBB por jurisdicción') }}</h3>

                @if(count($posicionIibb) === 0)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sin movimientos de IIBB en el período.') }}</p>
                    </div>
                @else
                    {{-- Cards móvil --}}
                    <div class="sm:hidden space-y-3">
                        @foreach($posicionIibb as $j)
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $j['jurisdiccion_nombre'] }}</p>
                                <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Gravado') }}</dt>
                                    <dd class="text-right text-gray-900 dark:text-white">${{ number_format($j['base_imponible'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('No gravado') }}</dt>
                                    <dd class="text-right text-gray-900 dark:text-white">${{ number_format($j['no_gravado'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Exento') }}</dt>
                                    <dd class="text-right text-gray-900 dark:text-white">${{ number_format($j['exento'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Ingresos totales') }}</dt>
                                    <dd class="text-right font-semibold text-gray-900 dark:text-white">${{ number_format($j['ingresos_totales'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Percepciones sufridas') }}</dt>
                                    <dd class="text-right text-gray-900 dark:text-white">${{ number_format($j['percepciones_sufridas'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Retenciones sufridas') }}</dt>
                                    <dd class="text-right text-gray-900 dark:text-white">${{ number_format($j['retenciones_sufridas'], 2, ',', '.') }}</dd>
                                    <dt class="text-gray-500 dark:text-gray-400 font-medium">{{ __('A cuenta') }}</dt>
                                    <dd class="text-right font-bold text-blue-600 dark:text-blue-400">${{ number_format($j['a_cuenta'], 2, ',', '.') }}</dd>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    {{-- Tabla desktop --}}
                    <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Jurisdicción') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Gravado') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('No gravado') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Exento') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Ingresos totales') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Percepciones sufridas') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Retenciones sufridas') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('A cuenta') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($posicionIibb as $j)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $j['jurisdiccion_nombre'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">${{ number_format($j['base_imponible'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">${{ number_format($j['no_gravado'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">${{ number_format($j['exento'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">${{ number_format($j['ingresos_totales'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">${{ number_format($j['percepciones_sufridas'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">${{ number_format($j['retenciones_sufridas'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-600 dark:text-blue-400">${{ number_format($j['a_cuenta'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
