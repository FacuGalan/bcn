<div class="px-3 sm:px-4 lg:px-6 py-4 space-y-3">
    {{-- ==================== HEADER ==================== --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex-1 min-w-0">
            <h1 class="text-lg font-bold text-bcn-secondary dark:text-white">{{ __('Repartidores') }}</h1>
            @if($totalEnFondos > 0.005)
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('En fondos de repartidores') }}: <span class="font-semibold text-cyan-700 dark:text-cyan-300">${{ number_format($totalEnFondos, 2, ',', '.') }}</span>
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            {{-- Buscador --}}
            <div class="relative">
                <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar...') }}"
                    class="pl-7 pr-2 py-1.5 w-40 lg:w-52 h-9 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-1 focus:ring-bcn-primary" />
            </div>

            <select wire:model.live="filterStatus" title="{{ __('Estado') }}"
                class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                <option value="active">{{ __('Activos') }}</option>
                <option value="inactive">{{ __('Inactivos') }}</option>
                <option value="all">{{ __('Todos') }}</option>
            </select>

            @if(auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores'))
                <button type="button" wire:click="abrirCrear"
                    class="h-9 px-3 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">{{ __('Nuevo') }}</span>
                </button>
            @endif
        </div>
    </div>

    @php
        $fondoDe = fn ($repartidor) => $repartidor->fondos->firstWhere('sucursal_id', $sucursalId);
        $puedeGestionar = auth()->user()?->hasPermissionTo('func.pedidos_delivery.repartidores');
    @endphp

    {{-- ==================== CARDS MÓVIL ==================== --}}
    <div class="sm:hidden space-y-3">
        @forelse($repartidores as $repartidor)
            @php $fondo = $fondoDe($repartidor); @endphp
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-base font-bold text-bcn-secondary dark:text-white flex items-center gap-2 flex-wrap">
                            {{ $repartidor->nombre }}
                            @unless($repartidor->activo)
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('Inactivo') }}</span>
                            @endunless
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __(\App\Models\Repartidor::TIPOS[$repartidor->tipo] ?? $repartidor->tipo) }}
                            @if($repartidor->telefono) — {{ $repartidor->telefono }} @endif
                        </div>
                        @if($repartidor->envio_es_del_repartidor)
                            <span class="inline-flex mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">{{ __('Envío propio') }}</span>
                        @endif
                    </div>
                    <div class="text-right">
                        @if($fondo)
                            <div class="text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Fondo') }}</div>
                            <div class="text-sm font-bold text-cyan-700 dark:text-cyan-300">${{ number_format($fondo->saldoTeorico(), 2, ',', '.') }}</div>
                        @else
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ __('Sin fondo') }}</span>
                        @endif
                    </div>
                </div>
                @if($puedeGestionar)
                    <div class="flex gap-2 flex-wrap">
                        <button wire:click="abrirEditar({{ $repartidor->id }})"
                            class="inline-flex items-center px-2.5 py-1.5 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                            {{ __('Editar') }}
                        </button>
                        <button wire:click="abrirFondoModal({{ $repartidor->id }})"
                            class="inline-flex items-center px-2.5 py-1.5 border border-cyan-300 dark:border-cyan-600 rounded text-xs text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-900/30">
                            {{ $fondo ? __('Reforzar') : __('Abrir fondo') }}
                        </button>
                        @if($fondo)
                            <button wire:click="verMovimientos({{ $fondo->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                {{ __('Movimientos') }}
                            </button>
                            <button wire:click="abrirRendir({{ $fondo->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-emerald-400 dark:border-emerald-500 rounded text-xs bg-emerald-600 text-white hover:bg-emerald-700">
                                {{ __('Rendir') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                {{ __('No hay repartidores. Creá el primero para armar salidas y fondos.') }}
            </div>
        @endforelse
        <div class="mt-4">{{ $repartidores->links() }}</div>
    </div>

    {{-- ==================== TABLA DESKTOP ==================== --}}
    <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-bcn-light dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Tipo') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Sucursales') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fondo abierto') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($repartidores as $repartidor)
                        @php $fondo = $fondoDe($repartidor); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $repartidor->nombre }}</div>
                                @if($repartidor->telefono)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $repartidor->telefono }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ __(\App\Models\Repartidor::TIPOS[$repartidor->tipo] ?? $repartidor->tipo) }}</span>
                                @if($repartidor->envio_es_del_repartidor)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                                        title="{{ __('El costo de envío se le liquida al rendir el fondo (no es ingreso del comercio)') }}">
                                        {{ __('Envío propio') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($repartidor->sucursales as $suc)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium {{ (int) $suc->id === $sucursalId ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $suc->nombre }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if($fondo)
                                    <div class="text-sm font-bold text-cyan-700 dark:text-cyan-300">${{ number_format($fondo->saldoTeorico(), 2, ',', '.') }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('desde') }} {{ $fondo->abierto_at?->format('d/m H:i') }}</div>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('Sin fondo') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($repartidor->activo)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Activo') }}</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('Inactivo') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                @if($puedeGestionar)
                                    <div class="flex justify-end gap-1 flex-wrap">
                                        <button wire:click="abrirEditar({{ $repartidor->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30"
                                            title="{{ __('Editar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="abrirFondoModal({{ $repartidor->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-cyan-300 dark:border-cyan-600 rounded text-xs text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-900/30"
                                            title="{{ $fondo ? __('Reforzar fondo') : __('Abrir fondo') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        @if($fondo)
                                            <button wire:click="verMovimientos({{ $fondo->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                                title="{{ __('Ver movimientos del fondo') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                            </button>
                                            <button wire:click="abrirRendir({{ $fondo->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-emerald-400 dark:border-emerald-500 rounded text-xs bg-emerald-600 text-white hover:bg-emerald-700"
                                                title="{{ __('Rendir fondo') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No hay repartidores. Creá el primero para armar salidas y fondos.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $repartidores->links() }}
        </div>
    </div>

    {{-- ==================== MODAL: ABM ==================== --}}
    @if($showModal)
        <x-bcn-modal
            :title="$editMode ? __('Editar repartidor') : __('Nuevo repartidor')"
            color="bg-bcn-primary"
            maxWidth="lg"
            onClose="cerrarModal"
        >
            <x-slot:body>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="rep-nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} <span class="text-red-500">*</span></label>
                            <input id="rep-nombre" type="text" wire:model="nombre" maxlength="150"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            @error('nombre') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="rep-telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Teléfono') }}</label>
                            <input id="rep-telefono" type="text" wire:model="telefono" maxlength="30"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="rep-tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Tipo') }}</label>
                            <select id="rep-tipo" wire:model.live="tipo"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                <option value="propio">{{ __('Propio') }}</option>
                                <option value="tercero">{{ __('Tercero') }}</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="activo"
                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Activo') }}</span>
                            </label>
                        </div>
                    </div>

                    @if($tipo === 'tercero')
                        <label class="flex items-start gap-2 cursor-pointer bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-2.5">
                            <input type="checkbox" wire:model="envioEsDelRepartidor"
                                class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ __('El costo de envío es del repartidor') }}
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('No es ingreso del comercio: se le liquida al rendir su fondo.') }}</span>
                            </span>
                        </label>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sucursales habilitadas') }} <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            @foreach($sucursales as $sucursal)
                                <label class="inline-flex items-center gap-2 cursor-pointer border border-gray-200 dark:border-gray-700 rounded-md px-2.5 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <input type="checkbox" wire:model="sucursalesSeleccionadas.{{ $sucursal->id }}"
                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $sucursal->nombre }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarModal"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="guardar"
                    class="px-4 py-2 bg-bcn-primary rounded-md text-sm font-semibold text-white hover:bg-opacity-90">
                    {{ __('Guardar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: ABRIR / REFORZAR FONDO ==================== --}}
    @if($showFondoModal)
        <x-bcn-modal
            :title="($fondoModalModo === 'abrir' ? __('Abrir fondo de') : __('Reforzar fondo de')) . ' ' . ($fondoInfo['repartidor'] ?? '')"
            color="bg-cyan-600"
            maxWidth="md"
            onClose="cerrarFondoModal"
        >
            <x-slot:body>
                <div class="space-y-3">
                    @if($fondoModalModo === 'reforzar' && ($fondoInfo['saldo_teorico'] ?? null) !== null)
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ __('Saldo teórico actual') }}: <span class="font-bold text-cyan-700 dark:text-cyan-300">${{ number_format($fondoInfo['saldo_teorico'], 2, ',', '.') }}</span>
                        </p>
                    @endif
                    <div>
                        <label for="fondo-monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $fondoModalModo === 'abrir' ? __('Cambio inicial') : __('Monto del refuerzo') }} ($)
                        </label>
                        <input id="fondo-monto" type="number" step="0.01" min="0" wire:model="fondoMonto"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        @if($fondoModalModo === 'abrir')
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Puede ser $0: el fondo también sirve solo para recibir cobros contra entrega.') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="fondo-caja" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Caja de origen') }}</label>
                        <select id="fondo-caja" wire:model="fondoCajaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($cajasSucursal as $caja)
                                <option value="{{ $caja->id }}">{{ $caja->nombre }}{{ $caja->estado !== 'abierta' ? ' ('.__('cerrada').')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fondo-detalle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Detalle') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                        <input id="fondo-detalle" type="text" wire:model="fondoDetalle" maxlength="255"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarFondoModal"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="confirmarFondo"
                    class="px-4 py-2 bg-cyan-600 rounded-md text-sm font-semibold text-white hover:bg-cyan-700">
                    {{ $fondoModalModo === 'abrir' ? __('Abrir fondo') : __('Reforzar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: RENDIR (D13) ==================== --}}
    @if($showRendirModal && !empty($rendirInfo))
        <x-bcn-modal
            :title="__('Rendir fondo de') . ' ' . $rendirInfo['repartidor']"
            color="bg-emerald-600"
            maxWidth="md"
            onClose="cerrarRendirModal"
        >
            <x-slot:body>
                <div class="space-y-3">
                    <div class="bg-gray-50 dark:bg-gray-900/40 rounded-lg p-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Abierto') }}</span>
                            <span class="text-gray-800 dark:text-gray-200">{{ $rendirInfo['abierto_at'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 dark:text-gray-400">{{ __('Saldo teórico') }}</span>
                            <span class="font-bold text-cyan-700 dark:text-cyan-300">${{ number_format($rendirInfo['saldo_teorico'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($rendirInfo['liquida_envios'])
                        <p class="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-2">
                            {{ __('Repartidor tercero con envío propio: al rendir se le liquidan los envíos de sus entregas (se descuentan del teórico).') }}
                        </p>
                    @endif

                    <div>
                        <label for="rendir-monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Efectivo declarado (arqueo)') }} ($)</label>
                        <input id="rendir-monto" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="rendirMontoDeclarado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        @if($rendirMontoDeclarado !== '' && ! $rendirInfo['liquida_envios'])
                            @php $dif = (float) $rendirMontoDeclarado - (float) $rendirInfo['saldo_teorico']; @endphp
                            @if(abs($dif) > 0.005)
                                <p class="mt-1 text-xs {{ $dif > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $dif > 0 ? __('Sobrante') : __('Faltante') }}: ${{ number_format(abs($dif), 2, ',', '.') }}
                                </p>
                            @endif
                        @endif
                    </div>
                    <div>
                        <label for="rendir-caja" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Caja receptora') }}</label>
                        <select id="rendir-caja" wire:model="rendirCajaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                            <option value="">{{ __('Seleccionar...') }}</option>
                            @foreach($cajasSucursal as $caja)
                                <option value="{{ $caja->id }}">{{ $caja->nombre }}{{ $caja->estado !== 'abierta' ? ' ('.__('cerrada').')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="rendir-obs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones') }} <span class="text-gray-400">({{ __('opcional') }})</span></label>
                        <input id="rendir-obs" type="text" wire:model="rendirObservaciones" maxlength="255"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarRendirModal"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="confirmarRendir"
                    class="px-4 py-2 bg-emerald-600 rounded-md text-sm font-semibold text-white hover:bg-emerald-700">
                    {{ __('Rendir fondo') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: MOVIMIENTOS DEL FONDO ==================== --}}
    @if($showMovimientosModal && $fondoMovimientos)
        <x-bcn-modal
            :title="__('Movimientos del fondo de') . ' ' . $fondoMovimientos->repartidor->nombre"
            color="bg-bcn-secondary"
            maxWidth="2xl"
            onClose="cerrarMovimientosModal"
        >
            <x-slot:body>
                <div class="space-y-2">
                    <p class="text-sm text-gray-600 dark:text-gray-300 -mt-1">
                        {{ __('Saldo teórico') }}: <span class="font-bold text-cyan-700 dark:text-cyan-300">${{ number_format($fondoMovimientos->saldoTeorico(), 2, ',', '.') }}</span>
                    </p>
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-bcn-light dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Fecha') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Tipo') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Detalle') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Monto') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($fondoMovimientos->movimientos as $mov)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $mov->created_at?->format('d/m H:i') }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-800 dark:text-gray-200">
                                            {{ __(\App\Models\RepartidorFondoMovimiento::TIPOS[$mov->tipo] ?? $mov->tipo) }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $mov->detalle ?? '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-right font-semibold {{ (float) $mov->monto >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ (float) $mov->monto >= 0 ? '+' : '−' }}${{ number_format(abs($mov->monto), 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-center text-gray-500 dark:text-gray-400 italic">{{ __('Sin movimientos') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarMovimientosModal"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
