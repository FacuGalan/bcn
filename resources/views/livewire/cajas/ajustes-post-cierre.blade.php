<div class="p-4 sm:p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Reporte de ajustes post-cierre') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Cambios de pago aplicados sobre ventas de turnos ya cerrados') }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha desde') }}</label>
                <input type="date" wire:model.live="filtroFechaDesde" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha hasta') }}</label>
                <input type="date" wire:model.live="filtroFechaHasta" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Usuario') }}</label>
                <select wire:model.live="filtroUsuarioId" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($usuariosDisponibles as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Turno original') }}</label>
                <select wire:model.live="filtroTurnoId" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($turnosDisponibles as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre_descriptivo ?? 'Cierre' }} — {{ $t->fecha_cierre?->format('d/m/Y H:i') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                <select wire:model.live="filtroTipoOperacion" class="w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm shadow-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="cambio_pago">{{ __('Cambiar forma de pago') }}</option>
                    <option value="agregar_pago">{{ __('Agregar pago') }}</option>
                    <option value="eliminar_pago">{{ __('Eliminar pago') }}</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end mt-3">
            <button type="button" wire:click="limpiarFiltros" class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                {{ __('Limpiar filtros') }}
            </button>
        </div>
    </div>

    {{-- Cards móvil --}}
    <div class="sm:hidden space-y-3">
        @forelse($ajustes as $aj)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $aj->descripcion_auto }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $aj->created_at->format('d/m/Y H:i') }} — {{ $aj->usuario->name ?? '-' }}</p>
                    </div>
                    @if($aj->delta_total != 0)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $aj->delta_total > 0 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
                            {{ $aj->delta_total > 0 ? '+' : '' }}$@precio($aj->delta_total)
                        </span>
                    @endif
                </div>
                <div class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                    <p><span class="font-medium">{{ __('Venta') }}:</span> #{{ $aj->venta->numero ?? '-' }}</p>
                    <p><span class="font-medium">{{ __('Turno original') }}:</span> {{ $aj->turnoOriginal?->nombre_descriptivo ?? '-' }} — {{ $aj->turnoOriginal?->fecha_cierre?->format('d/m/Y H:i') ?? '-' }}</p>
                    @if($aj->motivo)
                        <p class="italic text-gray-600 dark:text-gray-400">"{{ $aj->motivo }}"</p>
                    @endif
                    @if($aj->nc_emitida_flag && $aj->ncEmitida)
                        <p><span class="font-medium">NC:</span> {{ $aj->ncEmitida->numero_formateado ?? '-' }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                {{ __('No hay ajustes post-cierre registrados con estos filtros') }}
            </div>
        @endforelse
    </div>

    {{-- Tabla desktop --}}
    <div class="hidden sm:block bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuario') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Venta') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turno original') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cambio') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ΔMonto</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Motivo') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('NC') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ajustes as $aj)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white whitespace-nowrap">{{ $aj->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $aj->usuario->name ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white whitespace-nowrap">#{{ $aj->venta->numero ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $aj->turnoOriginal?->nombre_descriptivo ?? '-' }}
                                <span class="block text-xs text-gray-500">{{ $aj->turnoOriginal?->fecha_cierre?->format('d/m/Y H:i') ?? '' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                {{ $aj->formaPagoAnterior?->nombre ?? '-' }}
                                <span class="text-gray-400">→</span>
                                {{ $aj->formaPagoNueva?->nombre ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-sm text-right whitespace-nowrap">
                                @if($aj->delta_total != 0)
                                    <span class="{{ $aj->delta_total > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $aj->delta_total > 0 ? '+' : '' }}$@precio($aj->delta_total)</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-600 dark:text-gray-400 italic max-w-xs truncate" title="{{ $aj->motivo }}">{{ $aj->motivo }}</td>
                            <td class="px-4 py-2.5 text-sm whitespace-nowrap">
                                @if($aj->nc_emitida_flag && $aj->ncEmitida)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                        {{ $aj->ncEmitida->numero_formateado ?? 'NC' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No hay ajustes post-cierre registrados con estos filtros') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $ajustes->links() }}
    </div>
</div>
