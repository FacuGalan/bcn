{{-- Reporte de producción de encargos (RF-T16): qué preparar por día según
     los pedidos programados aceptados. Imprimible con el print del navegador. --}}
<div class="px-3 sm:px-4 lg:px-6 py-4 space-y-4">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-2 print:hidden">
        <div>
            <h1 class="text-lg font-bold text-bcn-secondary dark:text-white">{{ __('Producción de encargos') }}</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Qué hay que preparar por día según los encargos aceptados. Los pedidos "por aceptar" no cuentan.') }}</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label for="pe-desde" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                <input id="pe-desde" type="date" wire:model.live="fechaDesde"
                    class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
            </div>
            <div>
                <label for="pe-hasta" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                <input id="pe-hasta" type="date" wire:model.live="fechaHasta"
                    class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
            </div>
            <button type="button" onclick="window.print()"
                class="h-9 px-3 inline-flex items-center gap-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                {{ __('Imprimir') }}
            </button>
            <a href="{{ route('pedidos.delivery') }}" wire:navigate
                class="h-9 px-3 inline-flex items-center gap-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                {{ __('Volver a pedidos') }}
            </a>
        </div>
    </div>

    {{-- Reporte por día --}}
    @forelse($reporte as $fecha => $articulos)
        @php $dia = \Illuminate\Support\Carbon::parse($fecha); @endphp
        <div wire:key="prod-dia-{{ $fecha }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-2.5 bg-fuchsia-50 dark:bg-fuchsia-900/20 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                    {{ $dia->isToday() ? __('Hoy') : ($dia->isTomorrow() ? __('Mañana') : '') }} {{ $dia->translatedFormat('l d/m/Y') }}
                </h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">({{ count($articulos) }} {{ __('artículos') }})</span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach($articulos as $articuloId => $fila)
                    @php $clave = $fecha.'|'.$articuloId; @endphp
                    <div wire:key="prod-{{ $clave }}">
                        <button type="button" wire:click="toggleRenglon('{{ $clave }}')"
                            class="w-full px-4 py-2 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <span class="text-lg font-bold text-fuchsia-700 dark:text-fuchsia-300 tabular-nums w-16 text-right shrink-0">
                                {{ rtrim(rtrim(number_format($fila['cantidad'], 3, ',', '.'), '0'), ',') }}
                            </span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 w-14 shrink-0">{{ $fila['unidad'] ?: __('unid.') }}</span>
                            <span class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $fila['articulo'] }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">{{ count($fila['pedidos']) }} {{ __('pedidos') }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform {{ $renglonExpandido === $clave ? 'rotate-180' : '' }} print:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        @if($renglonExpandido === $clave)
                            <div class="px-4 pb-2 pl-24">
                                <table class="w-full text-xs">
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                        @foreach($fila['pedidos'] as $p)
                                            <tr>
                                                <td class="py-1 pr-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $p['hora'] }}</td>
                                                <td class="py-1 pr-3 font-medium text-gray-700 dark:text-gray-200">#{{ $p['numero'] }}</td>
                                                <td class="py-1 pr-3 text-gray-600 dark:text-gray-300 truncate max-w-[16rem]">{{ $p['cliente'] }}</td>
                                                <td class="py-1 pr-3 text-gray-500 dark:text-gray-400">{{ $p['tipo'] === 'take_away' ? __('Para llevar') : __('Delivery') }}</td>
                                                <td class="py-1 text-right font-semibold text-gray-700 dark:text-gray-200 tabular-nums">{{ rtrim(rtrim(number_format($p['cantidad'], 3, ',', '.'), '0'), ',') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-16 text-center bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No hay encargos aceptados en el rango elegido.') }}</p>
        </div>
    @endforelse
</div>
