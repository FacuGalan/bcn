<div wire:poll.60s class="h-[calc(100vh-5.5rem)] flex flex-col overflow-hidden px-3 sm:px-4 lg:px-6 py-2"
    x-data="{
        vista: localStorage.getItem('pedidos_vista_preferida') || 'lista',
        destacados: new Set(),
        mostrarBorradoresPanel: false,
        setVista(v) {
            this.vista = v;
            localStorage.setItem('pedidos_vista_preferida', v);
        },
        toggleBorradoresPanel() {
            this.mostrarBorradoresPanel = !this.mostrarBorradoresPanel;
        },
        focusSearch() {
            const el = this.$refs.searchInput || document.getElementById('search');
            if (el) { el.focus(); el.select?.(); }
        },
        destacar(id) {
            const n = parseInt(id);
            if (!n) return;
            this.destacados = new Set([...this.destacados, n]);
        },
        marcarVisto(id) {
            const n = parseInt(id);
            if (!this.destacados.has(n)) return;
            const next = new Set(this.destacados);
            next.delete(n);
            this.destacados = next;
        },
        estaDestacado(id) {
            return this.destacados.has(parseInt(id));
        },
        esInputActivo(target) {
            return target && ['INPUT','TEXTAREA','SELECT'].includes(target.tagName);
        },
    }"
    @pedido-destacado.window="destacar($event.detail.pedidoId)"
    @keydown.window="
        if ($event.key === 'Escape' && mostrarBorradoresPanel) {
            mostrarBorradoresPanel = false;
            return;
        }
        if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'n' && !esInputActivo($event.target)) {
            $event.preventDefault();
            $wire.abrirModalNuevoPedido();
            return;
        }
        if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') {
            $event.preventDefault();
            focusSearch();
            return;
        }
        if ($event.key === '/' && !esInputActivo($event.target)) {
            $event.preventDefault();
            focusSearch();
        }
    "
>
    {{-- ==================== HEADER COMPACTO (1 fila) ==================== --}}
    <div class="flex flex-wrap items-center gap-2 mb-2 flex-shrink-0">
        {{-- Izquierda: contador + badges + chips filtros activos --}}
        <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
            <span class="text-sm font-semibold text-bcn-secondary dark:text-white whitespace-nowrap">
                {{ $pedidos->total() }} {{ trans_choice('pedido|pedidos', $pedidos->total()) }}
            </span>

            @if($nuevosCount > 0)
                <button type="button" wire:click="marcarTodosVistos"
                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-bcn-primary text-white text-xs font-semibold rounded-full hover:bg-opacity-90 animate-pulse"
                    title="{{ __('Pedidos nuevos entraron en tiempo real') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    {{ __(':n nuevos', ['n' => $nuevosCount]) }}
                </button>
            @endif

            @if($borradores->isNotEmpty())
                <button type="button" @click="toggleBorradoresPanel()"
                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200 text-xs font-semibold rounded-full hover:bg-yellow-200 dark:hover:bg-yellow-900/60 transition-colors"
                    title="{{ __('Borradores guardados sin confirmar') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ $borradores->count() }} {{ __('en borrador') }}
                    <svg class="w-3 h-3 transition-transform" :class="mostrarBorradoresPanel ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            @endif

            {{-- Chips: filtros activos. Estado del pedido y estado del pago ya no
                 generan chip porque sus selectores están siempre visibles en la
                 barra superior; solo la búsqueda mantiene su chip removible. --}}
            @if($search !== '')
                <button type="button" wire:click="$set('search', '')"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs rounded-full hover:bg-gray-200 dark:hover:bg-gray-600">
                    <span>"{{ \Illuminate\Support\Str::limit($search, 18) }}"</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>

        {{-- Derecha: buscador + filtros estado/pago + acciones (mismo renglón) --}}
        <div class="flex flex-wrap items-center justify-end gap-1.5 flex-shrink-0">
            {{-- Buscador --}}
            <div class="relative">
                <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input x-ref="searchInput" type="text" id="search" wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Buscar...') }}"
                    class="pl-7 pr-9 py-1.5 w-40 lg:w-52 h-9 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-1 focus:ring-bcn-primary" />
                <kbd class="hidden lg:inline-flex absolute right-2 top-1/2 -translate-y-1/2 items-center text-[10px] font-mono text-gray-400 border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 pointer-events-none">/</kbd>
            </div>

            {{-- Filtro estado del pedido --}}
            <select wire:model.live="filterEstadoPedido" title="{{ __('Estado del pedido') }}"
                class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                <option value="activos">{{ __('Solo activos') }}</option>
                <option value="all">{{ __('Todos los estados') }}</option>
                @foreach($estadosPedido as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>

            {{-- Filtro estado del pago --}}
            <select wire:model.live="filterEstadoPago" title="{{ __('Estado del pago') }}"
                class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                <option value="all">{{ __('Todos los pagos') }}</option>
                @foreach($estadosPago as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>

            {{-- Orden (solo mobile: en desktop se ordena clickeando los encabezados) --}}
            <div class="sm:hidden flex items-center gap-1.5">
                <select wire:model.live="sortField" title="{{ __('Ordenar por') }}"
                    class="h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                    <option value="fecha">{{ __('Fecha') }}</option>
                    <option value="numero">{{ __('N°') }}</option>
                    <option value="cliente">{{ __('Cliente') }}</option>
                    <option value="total_final">{{ __('Total') }}</option>
                    <option value="estado_pedido">{{ __('Estado') }}</option>
                    <option value="estado_pago">{{ __('Pago') }}</option>
                </select>
                <button type="button" wire:click="toggleSortDirection"
                    class="h-9 w-9 inline-flex items-center justify-center border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    title="{{ $sortDirection === 'asc' ? __('Ascendente') : __('Descendente') }}">
                    <svg class="w-4 h-4 transition-transform {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            {{-- Filtros toggle --}}
            <button type="button" wire:click="toggleFilters"
                class="h-9 px-2.5 inline-flex items-center gap-1 border rounded-md text-sm transition-colors {{ $showFilters ? 'border-bcn-primary text-bcn-primary bg-bcn-primary/5' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                title="{{ __('Filtros') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="hidden sm:inline">{{ __('Filtros') }}</span>
            </button>

            {{-- Refrescar --}}
            <button type="button" wire:click="$refresh"
                class="h-9 w-9 inline-flex items-center justify-center border border-gray-300 dark:border-gray-600 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                title="{{ __('Refrescar') }}">
                <svg wire:loading.remove wire:target="$refresh" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="$refresh" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>

            {{-- Toggle vista Lista/Kanban --}}
            <div class="inline-flex rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden h-9">
                <button type="button" @click="setVista('lista')"
                    :class="vista === 'lista' ? 'bg-bcn-primary text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                    class="inline-flex items-center justify-center px-2.5 transition-colors"
                    title="{{ __('Vista Lista') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <button type="button" @click="setVista('kanban')"
                    :class="vista === 'kanban' ? 'bg-bcn-primary text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                    class="inline-flex items-center justify-center px-2.5 transition-colors border-l border-gray-300 dark:border-gray-600"
                    title="{{ __('Vista Kanban') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                    </svg>
                </button>
            </div>

            {{-- Nuevo Pedido --}}
            <button type="button" wire:click="abrirModalNuevoPedido"
                class="h-9 px-3 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors"
                title="{{ __('Nuevo Pedido') }} (Ctrl+N)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="hidden sm:inline">{{ __('Nuevo') }}</span>
            </button>
        </div>
    </div>

    {{-- ==================== FILTROS COLAPSABLES (avanzado: rango de fechas) ==================== --}}
    @if($showFilters)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-md mb-2 flex-shrink-0 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3">
                <div>
                    <label for="filterFechaDesde" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Desde') }}</label>
                    <input type="date" id="filterFechaDesde" wire:model.live="filterFechaDesde"
                        class="w-full h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                </div>
                <div>
                    <label for="filterFechaHasta" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Hasta') }}</label>
                    <input type="date" id="filterFechaHasta" wire:model.live="filterFechaHasta"
                        class="w-full h-9 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                </div>
            </div>
            <div class="px-3 pb-2 flex justify-end">
                <button wire:click="resetFilters"
                    class="text-xs text-gray-600 dark:text-gray-400 hover:text-bcn-primary underline">
                    {{ __('Limpiar filtros') }}
                </button>
            </div>
        </div>
    @endif

    {{-- ==================== DROPDOWN BORRADORES (Alpine) ==================== --}}
    @if($borradores->isNotEmpty())
        <div x-cloak x-show="mostrarBorradoresPanel" x-transition.opacity.duration.150ms
            @click.outside="mostrarBorradoresPanel = false"
            class="bg-white dark:bg-gray-800 shadow-md rounded-md border border-gray-200 dark:border-gray-700 mb-2 max-h-72 overflow-y-auto flex-shrink-0 divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($borradores as $borrador)
                <div class="px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            @if($borrador->cliente)
                                {{ $borrador->cliente->nombre }}
                                @if($borrador->cliente->telefono)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">— {{ $borrador->cliente->telefono }}</span>
                                @endif
                            @elseif($borrador->nombre_cliente_temporal)
                                {{ $borrador->nombre_cliente_temporal }}
                                @if($borrador->telefono_cliente_temporal)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">— {{ $borrador->telefono_cliente_temporal }}</span>
                                @endif
                            @else
                                <span class="italic text-gray-500">{{ __('Sin cliente') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Actualizado') }}: {{ $borrador->updated_at->diffForHumans() }}
                            · ${{ number_format($borrador->total_final, 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button type="button" wire:click="abrirModalEditarPedido({{ $borrador->id }})"
                            class="inline-flex items-center px-2.5 py-1 bg-bcn-primary text-white text-xs font-medium rounded hover:bg-opacity-90"
                            title="{{ __('Continuar borrador') }}">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Continuar') }}
                        </button>
                        <button type="button" wire:click="abrirCancelar({{ $borrador->id }})"
                            class="inline-flex items-center p-1 border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30"
                            title="{{ __('Descartar borrador') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ==================== CONTENIDO PRINCIPAL ====================
         Sin overflow propio: cada vista decide su comportamiento de scroll.
         Lista scrollea verticalmente; Kanban mantiene su alto fijo con scroll
         interno por columna. --}}
    <div class="flex-1 min-h-0 flex flex-col">

        {{-- ==================== VISTA LISTA ==================== --}}
        <div x-show="vista === 'lista'" x-cloak class="h-full overflow-y-auto">
        {{-- Cards móvil --}}
        <div class="sm:hidden space-y-3">
            @forelse($pedidos as $pedido)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4"
                    x-data="demoraAlerta(@js($pedido->alertaDemora($alertaAmarillaMin, $alertaRojaMin)))" :class="clases()"
                    wire:key="card-movil-{{ $pedido->id }}">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-base font-bold text-bcn-secondary dark:text-white">
                                    @if($pedido->numero)
                                        #{{ $pedido->numero_visible }}
                                    @else
                                        <span class="italic text-gray-500">{{ __('Borrador') }}</span>
                                    @endif
                                </span>
                                <span x-show="nivel !== 'ok'" x-cloak x-text="edad()"
                                    :class="nivel === 'rojo' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'"
                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-tight"
                                    title="{{ __('Tiempo desde la confirmación') }}"></span>
                                @if($pedido->numero_beeper)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        🔔 {{ $pedido->numero_beeper }}
                                    </span>
                                @endif
                                @if($pedido->es_invitacion_total)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200"
                                          @if($pedido->invitacion_motivo) title="{{ $pedido->invitacion_motivo }}" @endif>
                                        {{ __('Cortesía') }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $pedido->nombre_cliente_final ?? __('Sin cliente') }}
                                @if($pedido->telefono_cliente_final) — {{ $pedido->telefono_cliente_final }} @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $pedido->fecha->format('d/m/Y H:i') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-base font-bold text-bcn-secondary dark:text-white">
                                ${{ number_format($pedido->total_final, 2, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 flex-wrap mb-2 items-center">
                        @if(! in_array($pedido->estado_pedido, ['cancelado', 'facturado']))
                            {{-- Badge-botón: abre el cambio de estado (paso siguiente preseleccionado) --}}
                            <button type="button"
                                    wire:click="abrirCambiarEstado({{ $pedido->id }})"
                                    class="inline-flex items-center gap-1 group cursor-pointer"
                                    title="{{ __('Cambiar estado') }}">
                                <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" class="select-none" />
                                <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-opacity flex-shrink-0"
                                      aria-hidden="true">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </span>
                            </button>
                        @else
                            <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" class="cursor-default select-none" />
                        @endif
                        @if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
                            <button type="button"
                                    wire:click="cobrarRapido({{ $pedido->id }})"
                                    class="inline-flex items-center gap-1 group cursor-pointer"
                                    title="{{ $pedido->total_planificado > 0 ? __('Confirmar pagos planificados') : __('Abrir desglose de cobro') }}">
                                <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" class="select-none" />
                                <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-opacity flex-shrink-0"
                                      aria-hidden="true">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            </button>
                        @else
                            <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" class="cursor-default select-none" />
                        @endif
                        @if($pedido->total_cobrado > 0 && $pedido->estado_pago !== 'pagado')
                            <span class="text-[10px] text-green-700 dark:text-green-400">
                                {{ __('Cob.') }}: ${{ number_format($pedido->total_cobrado, 2, ',', '.') }}
                            </span>
                        @endif
                        @if($pedido->total_planificado > 0)
                            <span class="text-[10px] text-blue-700 dark:text-blue-400">
                                {{ __('Plan.') }}: ${{ number_format($pedido->total_planificado, 2, ',', '.') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        @php $puedeEditar = ! in_array($pedido->estado_pedido, ['cancelado', 'facturado']) && $pedido->estado_pago === 'pendiente'; @endphp
                        @if($puedeEditar)
                            <button wire:click="abrirModalEditarPedido({{ $pedido->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                {{ __('Editar') }}
                            </button>
                        @endif
                        <button wire:click="verDetalle({{ $pedido->id }})"
                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{ __('Ver') }}
                        </button>
                        @if(!in_array($pedido->estado_pedido, ['cancelado','facturado']))
                            {{-- Entregar/Estado viven en el badge-botón de estado de arriba --}}
                            @if($pedido->estado_pedido !== 'borrador' && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta'))
                                <button wire:click="abrirConvertir({{ $pedido->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-bcn-primary rounded text-xs text-bcn-primary hover:bg-bcn-primary hover:text-white">
                                    {{ __('Convertir') }}
                                </button>
                            @endif
                            @if(auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar'))
                                <button wire:click="abrirCancelar({{ $pedido->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                    {{ __('Cancelar') }}
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No hay pedidos para estos filtros') }}</p>
                </div>
            @endforelse
            <div class="mt-4">{{ $pedidos->links() }}</div>
        </div>

        {{-- Tabla desktop --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('numero')" class="group inline-flex items-center gap-1 hover:text-bcn-primary transition-colors select-none">
                                    {{ __('N°') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'numero'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('cliente')" class="group inline-flex items-center gap-1 hover:text-bcn-primary transition-colors select-none">
                                    {{ __('Cliente') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'cliente'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('fecha')" class="group inline-flex items-center gap-1 hover:text-bcn-primary transition-colors select-none">
                                    {{ __('Fecha') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'fecha'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('total_final')" class="group inline-flex items-center gap-1 w-full justify-end hover:text-bcn-primary transition-colors select-none">
                                    {{ __('Total') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'total_final'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('estado_pago')" class="group inline-flex items-center gap-1 hover:text-bcn-primary transition-colors select-none">
                                    {{ __('Pago') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'estado_pago'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('estado_pedido')" class="group inline-flex items-center gap-1 hover:text-bcn-primary transition-colors select-none">
                                    {{ __('Estado') }}
                                    @include('livewire.pedidos._sort-icon', ['field' => 'estado_pedido'])
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($pedidos as $pedido)
                            <tr
                                wire:key="row-{{ $pedido->id }}"
                                x-data="demoraAlerta(@js($pedido->alertaDemora($alertaAmarillaMin, $alertaRojaMin)))"
                                :class="[estaDestacado({{ $pedido->id }}) ? 'pedido-destacado-row' : 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors',
                                    nivel === 'rojo' ? 'bg-red-50 dark:bg-red-900/10' : (nivel === 'amarillo' ? 'bg-amber-50 dark:bg-amber-900/10' : '')]"
                                @click="marcarVisto({{ $pedido->id }})"
                            >
                                @php
                                    $puedeEditar = ! in_array($pedido->estado_pedido, ['cancelado', 'facturado']) && $pedido->estado_pago === 'pendiente';
                                    // Vuelto total y planificados para el desplegable de Pago.
                                    $vueltoPedido = (float) $pedido->pagos->sum('vuelto');
                                    $pagosPlanificadosLista = $pedido->pagos->where('estado', 'planificado');
                                @endphp
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($puedeEditar)
                                        {{-- Edición circunstancial: número visible + lápiz en hover --}}
                                        <button type="button" wire:click="abrirModalEditarPedido({{ $pedido->id }})"
                                            class="inline-flex items-center gap-1 group cursor-pointer"
                                            title="{{ __('Editar pedido') }}">
                                            <span class="text-sm font-bold text-bcn-secondary dark:text-white">
                                                @if($pedido->numero)
                                                    #{{ $pedido->numero_visible }}
                                                @else
                                                    <span class="italic text-gray-500 text-xs">{{ __('Borrador') }}</span>
                                                @endif
                                            </span>
                                            <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-opacity flex-shrink-0"
                                                  aria-hidden="true">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </span>
                                        </button>
                                    @else
                                        <div class="text-sm font-bold text-bcn-secondary dark:text-white">
                                            @if($pedido->numero)
                                                #{{ $pedido->numero_visible }}
                                            @else
                                                <span class="italic text-gray-500 text-xs">{{ __('Borrador') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if($pedido->numero_beeper)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 mt-1">
                                            🔔 {{ $pedido->numero_beeper }}
                                        </span>
                                    @endif
                                    @if($pedido->es_invitacion_total)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 mt-1"
                                              @if($pedido->invitacion_motivo) title="{{ $pedido->invitacion_motivo }}" @endif>
                                            {{ __('Cortesía') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        {{ $pedido->nombre_cliente_final ?? __('Sin cliente') }}
                                    </div>
                                    @if($pedido->telefono_cliente_final)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $pedido->telefono_cliente_final }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    {{ $pedido->fecha->format('d/m/Y H:i') }}
                                    {{-- Minutos desde la confirmación cuando la alerta está activa --}}
                                    <span x-show="nivel !== 'ok'" x-cloak x-text="edad()"
                                        :class="nivel === 'rojo' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'"
                                        class="inline-block ml-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-tight align-middle"
                                        title="{{ __('Tiempo desde la confirmación') }}"></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="text-sm font-bold text-bcn-secondary dark:text-white">
                                        ${{ number_format($pedido->total_final, 2, ',', '.') }}
                                    </div>
                                    @if($vueltoPedido > 0.005)
                                        <div class="text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                            {{ __('Vuelto') }}: ${{ number_format($vueltoPedido, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
                                        <button type="button"
                                                wire:click="cobrarRapido({{ $pedido->id }})"
                                                class="inline-flex items-center gap-1 group cursor-pointer"
                                                title="{{ $pedido->total_planificado > 0 ? __('Confirmar pagos planificados') : __('Abrir desglose de cobro') }}">
                                            <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" class="select-none" />
                                            <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-opacity flex-shrink-0"
                                                  aria-hidden="true">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                        </button>
                                    @else
                                        <x-pedidos.badge-estado-pago :estado="$pedido->estado_pago" class="cursor-default select-none" />
                                    @endif
                                    @if($pedido->total_cobrado > 0 && $pedido->estado_pago !== 'pagado')
                                        <div class="text-[10px] text-green-700 dark:text-green-400 mt-0.5">
                                            {{ __('Cob.') }}: ${{ number_format($pedido->total_cobrado, 2, ',', '.') }}
                                        </div>
                                    @endif
                                    @if($pagosPlanificadosLista->isNotEmpty())
                                        {{-- Desplegable con el detalle de lo planificado (FP + monto + vuelto) --}}
                                        <div x-data="{ openPlan: false }" class="mt-0.5">
                                            <button type="button" @click.stop="openPlan = !openPlan"
                                                class="inline-flex items-center gap-0.5 text-[10px] text-blue-700 dark:text-blue-400 hover:underline">
                                                {{ __('Plan.') }}: ${{ number_format($pedido->total_planificado, 2, ',', '.') }}
                                                <svg class="w-3 h-3 transition-transform" :class="openPlan && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <div x-show="openPlan" x-cloak class="mt-1 space-y-0.5">
                                                @foreach($pagosPlanificadosLista as $pp)
                                                    <div class="text-[10px] text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                                        {{ $pp->formaPago?->nombre ?? __('Sin especificar') }}: ${{ number_format($pp->monto_final, 2, ',', '.') }}
                                                        @if((float) $pp->vuelto > 0.005)
                                                            <span class="text-amber-700 dark:text-amber-400">({{ __('vuelto') }} ${{ number_format($pp->vuelto, 2, ',', '.') }})</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if(! in_array($pedido->estado_pedido, ['cancelado', 'facturado']))
                                        {{-- Badge-botón: click abre el cambio de estado con el siguiente
                                             paso natural preseleccionado (se puede saltear en el modal) --}}
                                        <button type="button"
                                                wire:click="abrirCambiarEstado({{ $pedido->id }})"
                                                class="inline-flex items-center gap-1 group cursor-pointer"
                                                title="{{ __('Cambiar estado') }}">
                                            <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" class="select-none" />
                                            <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-opacity flex-shrink-0"
                                                  aria-hidden="true">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                </svg>
                                            </span>
                                        </button>
                                    @else
                                        <x-pedidos.badge-estado-pedido :estado="$pedido->estado_pedido" class="cursor-default select-none" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1 flex-wrap">
                                        <button wire:click="verDetalle({{ $pedido->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                            title="{{ __('Ver detalle') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        @if(!in_array($pedido->estado_pedido, ['cancelado','facturado']))
                                            {{-- Editar vive en el N° (lápiz en hover); Entregar y Cambiar
                                                 estado se movieron al badge-botón de la columna Estado. --}}
                                            @if($pedido->estado_pedido !== 'borrador' && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta'))
                                                <button wire:click="abrirConvertir({{ $pedido->id }})"
                                                    class="inline-flex items-center px-2 py-1 border border-emerald-300 dark:border-emerald-600 rounded text-xs text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                                                    title="{{ __('Convertir en venta') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            @endif
                                            @php
                                                $estadoComanda = $pedido->estado_comanda;
                                                $comandarTooltip = $estadoComanda === 'comandado'
                                                    ? __('Reimprimir comanda')
                                                    : ($estadoComanda === 'parcial' ? __('Comandar (hay items nuevos)') : __('Comandar pedido'));
                                            @endphp
                                            <button wire:click="comandarPedido({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-blue-300 dark:border-blue-600 rounded text-xs text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                                title="{{ $comandarTooltip }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                            </button>
                                            @if(auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar'))
                                                <button wire:click="abrirCancelar({{ $pedido->id }})"
                                                    class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30"
                                                    title="{{ __('Cancelar pedido') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('No hay pedidos para estos filtros') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $pedidos->links() }}
            </div>
        </div>
        </div>
        {{-- FIN VISTA LISTA --}}

        {{-- ==================== VISTA KANBAN ==================== --}}
        @php
            $estadoLabels = [
                'confirmado' => __('Confirmado'),
                'en_preparacion' => __('En preparación'),
                'listo' => __('Listo'),
                'entregado' => __('Entregado'),
            ];
            $estadoColores = [
                'confirmado' => ['header' => 'bg-blue-500', 'border' => 'border-blue-300 dark:border-blue-700'],
                'en_preparacion' => ['header' => 'bg-amber-500', 'border' => 'border-amber-300 dark:border-amber-700'],
                'listo' => ['header' => 'bg-green-500', 'border' => 'border-green-300 dark:border-green-700'],
                'entregado' => ['header' => 'bg-emerald-600', 'border' => 'border-emerald-300 dark:border-emerald-700'],
            ];
        @endphp
        <div x-show="vista === 'kanban'" x-cloak class="h-full flex flex-col min-h-0">
        <div x-data="kanbanBoard"
            data-transiciones="{{ json_encode($transicionesKanban) }}"
            wire:key="kanban-{{ collect($pedidosKanban)->map->pluck('id')->flatten()->implode('-') }}"
            class="flex-1 flex flex-col min-h-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 flex-1 min-h-0">
                @foreach($estadosKanban as $estado)
                    <div class="bg-gray-100 dark:bg-gray-900 rounded-lg border {{ $estadoColores[$estado]['border'] }} flex flex-col min-h-0">
                        {{-- Header columna --}}
                        <div class="{{ $estadoColores[$estado]['header'] }} text-white px-3 py-2 rounded-t-lg flex justify-between items-center text-sm font-semibold flex-shrink-0">
                            <span>{{ $estadoLabels[$estado] }}</span>
                            <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">
                                {{ $pedidosKanban[$estado]->count() }}
                            </span>
                        </div>
                        {{-- Cards (scroll interno por columna, altura dinamica que se adapta al espacio disponible) --}}
                        <div class="flex-1 min-h-0 p-2 space-y-2 kanban-col overflow-y-auto"
                            data-estado="{{ $estado }}">
                            @forelse($pedidosKanban[$estado] as $pedido)
                                <div class="kanban-card bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2.5 cursor-move hover:shadow-md transition-shadow select-none"
                                    x-data="demoraAlerta(@js($pedido->alertaDemora($alertaAmarillaMin, $alertaRojaMin)))"
                                    :class="[estaDestacado({{ $pedido->id }}) ? 'pedido-destacado-card' : '', clases()]"
                                    @click="marcarVisto({{ $pedido->id }})"
                                    data-pedido-id="{{ $pedido->id }}"
                                    wire:key="kanban-card-{{ $pedido->id }}">
                                    {{-- Fila 1: numero + beeper (izq), estado pago (der) --}}
                                    <div class="flex justify-between items-center gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="font-bold text-base text-bcn-secondary dark:text-white">
                                                @if($pedido->numero)
                                                    #{{ $pedido->numero_visible }}
                                                @else
                                                    <span class="italic text-gray-500 text-sm">{{ __('S/N') }}</span>
                                                @endif
                                            </span>
                                            @if($pedido->numero_beeper)
                                                <span class="bg-bcn-primary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-tight">
                                                    B{{ $pedido->numero_beeper }}
                                                </span>
                                            @endif
                                            <span x-show="nivel !== 'ok'" x-cloak x-text="edad()"
                                                :class="nivel === 'rojo' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'"
                                                class="text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-tight"
                                                title="{{ __('Tiempo desde la confirmación') }}"></span>
                                        </div>
                                        @if($pedido->estado_pago === 'pagado')
                                            <span class="text-green-700 dark:text-green-400 font-bold text-xs">{{ __('Pagado') }}</span>
                                        @elseif($pedido->estado_pago === 'parcial')
                                            <span class="text-amber-700 dark:text-amber-400 font-bold text-xs">{{ __('Parcial') }}</span>
                                        @else
                                            <span class="text-red-700 dark:text-red-400 font-bold text-xs">{{ __('Pendiente') }}</span>
                                        @endif
                                    </div>
                                    {{-- Fila 2: cliente --}}
                                    <div class="text-sm text-gray-800 dark:text-gray-200 mt-1 truncate font-medium">
                                        {{ $pedido->cliente?->nombre ?? $pedido->nombre_cliente_temporal ?? __('Sin cliente') }}
                                    </div>
                                    {{-- Fila 3: acciones (izq) + monto (der) en un solo footer --}}
                                    <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2"
                                        @mousedown.stop @touchstart.stop>
                                        <div class="flex gap-1 flex-wrap">
                                        @php $puedeEditar = ! in_array($pedido->estado_pedido, ['cancelado', 'facturado']) && $pedido->estado_pago === 'pendiente'; @endphp
                                        @if($puedeEditar)
                                            <button type="button" wire:click="abrirModalEditarPedido({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30"
                                                title="{{ __('Editar pedido') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        @endif
                                        <button type="button" wire:click="verDetalle({{ $pedido->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                            title="{{ __('Ver detalle') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        @if(($pedido->total_planificado > 0 || $pedido->total_cobrado < $pedido->total_final - 0.005) && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cobrar'))
                                            <button type="button" wire:click="cobrarRapido({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-green-300 dark:border-green-600 rounded text-xs text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30"
                                                title="{{ $pedido->total_planificado > 0 ? __('Confirmar pagos planificados') : __('Abrir desglose de cobro') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        @endif
                                        @if($pedido->estado_pedido !== 'borrador' && auth()->user()?->hasPermissionTo('func.pedidos_mostrador.convertir_venta'))
                                            <button type="button" wire:click="abrirConvertir({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-bcn-primary rounded text-xs text-bcn-primary hover:bg-bcn-primary hover:text-white"
                                                title="{{ __('Convertir en venta') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        @endif
                                        @php
                                            $estadoComandaK = $pedido->estado_comanda;
                                            $comandarTooltipK = $estadoComandaK === 'comandado'
                                                ? __('Reimprimir comanda')
                                                : ($estadoComandaK === 'parcial' ? __('Comandar (hay items nuevos)') : __('Comandar pedido'));
                                        @endphp
                                        <button type="button" wire:click="comandarPedido({{ $pedido->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-blue-300 dark:border-blue-600 rounded text-xs text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30"
                                            title="{{ $comandarTooltipK }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </button>
                                        @if(auth()->user()?->hasPermissionTo('func.pedidos_mostrador.cancelar'))
                                            <button type="button" wire:click="abrirCancelar({{ $pedido->id }})"
                                                class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30"
                                                title="{{ __('Cancelar pedido') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        @endif
                                        </div>{{-- /acciones --}}
                                        <span class="text-base font-bold text-bcn-primary whitespace-nowrap">
                                            ${{ number_format($pedido->total_final, 2, ',', '.') }}
                                        </span>
                                    </div>{{-- /footer --}}
                                </div>
                            @empty
                                <div class="text-xs text-gray-400 dark:text-gray-500 text-center py-4 italic">
                                    {{ __('Sin pedidos') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500 text-center leading-tight flex-shrink-0">
                {{ __('Arrastrá entre columnas para cambiar estado · dentro de la columna para reordenar') }}
            </p>
        </div>
        </div>
        {{-- FIN VISTA KANBAN --}}

        <style>
            .kanban-card { user-select: none; -webkit-user-select: none; }
            .kanban-ghost { opacity: 0.4; background: #fef3c7; }
            .kanban-dragging { cursor: grabbing; opacity: 0.9; transform: rotate(2deg); }
            [x-cloak] { display: none !important; }

            /* Highlight de pedidos nuevos/modificados en vivo — pulso naranja
               intenso que llama la atencion sin apagarse del todo. Persiste
               hasta que el usuario clickee la fila/card. */
            @keyframes pedidoPulseRow {
                0%, 100% {
                    background-color: rgba(251, 146, 60, 0.32);
                    box-shadow: inset 4px 0 0 rgb(249, 115, 22),
                                0 0 0 1px rgba(249, 115, 22, 0.35);
                }
                50% {
                    background-color: rgba(251, 146, 60, 0.62);
                    box-shadow: inset 5px 0 0 rgb(234, 88, 12),
                                0 0 12px 2px rgba(249, 115, 22, 0.55),
                                0 0 0 1px rgba(249, 115, 22, 0.75);
                }
            }
            .pedido-destacado-row {
                animation: pedidoPulseRow 1.8s ease-in-out infinite;
                position: relative;
                z-index: 1;
                cursor: pointer;
            }
            .dark .pedido-destacado-row {
                animation-name: pedidoPulseRowDark;
            }
            @keyframes pedidoPulseRowDark {
                0%, 100% {
                    background-color: rgba(251, 146, 60, 0.22);
                    box-shadow: inset 4px 0 0 rgb(251, 146, 60),
                                0 0 0 1px rgba(251, 146, 60, 0.40);
                }
                50% {
                    background-color: rgba(251, 146, 60, 0.50);
                    box-shadow: inset 5px 0 0 rgb(253, 186, 116),
                                0 0 14px 3px rgba(251, 146, 60, 0.70),
                                0 0 0 1px rgba(251, 186, 116, 0.85);
                }
            }

            @keyframes pedidoPulseCard {
                0%, 100% {
                    box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.85),
                                0 0 14px 3px rgba(249, 115, 22, 0.45),
                                0 0 28px 6px rgba(249, 115, 22, 0.18);
                    transform: scale(1);
                }
                50% {
                    box-shadow: 0 0 0 3px rgba(234, 88, 12, 1),
                                0 0 22px 6px rgba(249, 115, 22, 0.80),
                                0 0 44px 12px rgba(249, 115, 22, 0.40);
                    transform: scale(1.015);
                }
            }
            .pedido-destacado-card {
                animation: pedidoPulseCard 1.8s ease-in-out infinite;
                cursor: pointer;
                position: relative;
                z-index: 5;
            }
            .dark .pedido-destacado-card {
                animation-name: pedidoPulseCardDark;
            }
            @keyframes pedidoPulseCardDark {
                0%, 100% {
                    box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.85),
                                0 0 16px 3px rgba(251, 146, 60, 0.55),
                                0 0 32px 8px rgba(251, 146, 60, 0.25);
                    transform: scale(1);
                }
                50% {
                    box-shadow: 0 0 0 3px rgba(253, 186, 116, 1),
                                0 0 24px 6px rgba(251, 146, 60, 0.90),
                                0 0 50px 14px rgba(251, 146, 60, 0.50);
                    transform: scale(1.015);
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .pedido-destacado-row,
                .pedido-destacado-card {
                    animation: none;
                    background-color: rgba(251, 146, 60, 0.35);
                    box-shadow: 0 0 0 2px rgb(249, 115, 22);
                }
            }
        </style>
    </div>

    {{-- ==================== MODAL: DETALLE ==================== --}}
    @if($showDetalleModal && $pedidoDetalle)
        <x-bcn-modal
            :title="__('Pedido') . ' ' . ($pedidoDetalle->numero ? '#' . $pedidoDetalle->numero_visible : __('(Borrador)'))"
            color="bg-bcn-secondary"
            maxWidth="4xl"
            onClose="cerrarDetalle"
        >
            <x-slot:body>
                <p class="text-sm text-gray-500 dark:text-gray-400 -mt-2 mb-4">
                    {{ $pedidoDetalle->fecha->format('d/m/Y H:i') }}
                    @if($pedidoDetalle->sucursal)
                        | {{ $pedidoDetalle->sucursal->nombre }}
                    @endif
                </p>
                <div class="space-y-5">
                    {{-- Información general --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cliente') }}</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {{ $pedidoDetalle->nombre_cliente_final ?? __('Sin cliente') }}
                            </p>
                            @if($pedidoDetalle->telefono_cliente_final)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $pedidoDetalle->telefono_cliente_final }}</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Caja') }}</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $pedidoDetalle->caja->nombre ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Identificador') }}</label>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $pedidoDetalle->identificador ?? '—' }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</label>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <x-pedidos.badge-estado-pedido :estado="$pedidoDetalle->estado_pedido" />
                                <x-pedidos.badge-estado-pago :estado="$pedidoDetalle->estado_pago" />
                            </div>
                        </div>
                    </div>

                    @if($pedidoDetalle->numero_beeper || $pedidoDetalle->venta_id)
                        <div class="flex flex-wrap gap-2 -mt-2">
                            @if($pedidoDetalle->numero_beeper)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-sm font-mono font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    🔔 {{ __('Beeper') }} {{ $pedidoDetalle->numero_beeper }}
                                </span>
                            @endif
                            @if($pedidoDetalle->venta_id)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                    {{ __('Convertido en venta') }} #{{ $pedidoDetalle->venta?->numero ?? $pedidoDetalle->venta_id }}
                                </span>
                            @endif
                        </div>
                    @endif

                    {{-- Detalles de items --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Artículos') }}</label>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Artículo') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cant.') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('P.U.') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Subtotal') }}</th>
                                    </tr>
                                </thead>
                                @php
                                    // Solo mostrar el indicador "no comandado" cuando hay
                                    // mezcla (algunos items comandados + otros no). Si todos
                                    // están sin comandar o todos comandados, el indicador es
                                    // ruido.
                                    $sinComandarVer = $pedidoDetalle->detalles->whereNull('comandado_at')->count();
                                    $comandadosVer = $pedidoDetalle->detalles->whereNotNull('comandado_at')->count();
                                    $hayMezclaComandaVer = $sinComandarVer > 0 && $comandadosVer > 0;
                                @endphp
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($pedidoDetalle->detalles as $detalle)
                                        <tr>
                                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                                @if(!$detalle->es_concepto && $detalle->articulo?->hasImagen())
                                                    <img src="{{ $detalle->articulo->imagenUrl() }}"
                                                        alt="{{ $detalle->articulo->nombre }}"
                                                        style="object-position: {{ $detalle->articulo->imagenFocalPosition() }};"
                                                        class="inline-block w-8 h-8 rounded object-cover align-middle mr-2 border border-gray-200 dark:border-gray-700" />
                                                @endif
                                                {{ $detalle->es_concepto ? ($detalle->concepto_descripcion ?? '—') : ($detalle->articulo?->nombre ?? '—') }}
                                                @if($detalle->es_concepto)
                                                    <span class="text-xs text-gray-500">({{ __('Concepto') }})</span>
                                                @endif
                                                @if($detalle->pagado_con_puntos)
                                                    <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                        ⭐ {{ $detalle->puntos_usados }} pts
                                                    </span>
                                                @endif
                                                @if($detalle->descuento_cupon > 0)
                                                    <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                        {{ __('Cupón') }} -${{ number_format($detalle->descuento_cupon, 2, ',', '.') }}
                                                    </span>
                                                @endif
                                                @if($detalle->tiene_promocion)
                                                    <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                                        🏷️ {{ __('Promo') }}
                                                    </span>
                                                @endif
                                                @if($hayMezclaComandaVer && $detalle->comandado_at === null)
                                                    <span class="inline-block w-1.5 h-1.5 ml-1 rounded-full bg-amber-500 dark:bg-amber-400 align-middle"
                                                          title="{{ __('Este item aún no se envió a cocina') }}"
                                                          aria-label="{{ __('No comandado') }}"></span>
                                                @endif
                                                @if($detalle->opcionales->isNotEmpty())
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        @foreach($detalle->opcionales as $opc)
                                                            <span class="inline-block mr-2">+ {{ $opc->descripcion ?? $opc->nombre_opcional ?? '—' }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white text-right">{{ number_format($detalle->cantidad, 2, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-white text-right">${{ number_format($detalle->precio_unitario, 2, ',', '.') }}</td>
                                            <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-white text-right">${{ number_format($detalle->subtotal, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Promociones aplicadas (nivel pedido) --}}
                    @if($pedidoDetalle->promociones->count() > 0)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Promociones aplicadas') }}</label>
                            <div class="space-y-2">
                                @foreach($pedidoDetalle->promociones as $promo)
                                    <div class="flex items-center justify-between bg-amber-50 dark:bg-amber-900/20 rounded-lg px-4 py-2 border border-amber-200 dark:border-amber-800">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                            <div>
                                                <span class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ $promo->descripcion_promocion }}</span>
                                                <span class="text-xs text-amber-600 dark:text-amber-400 ml-2">
                                                    ({{ $promo->esPromocionEspecial() ? __('Especial') : __('Común') }})
                                                </span>
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-red-600 dark:text-red-400">-${{ number_format($promo->descuento_aplicado, 2, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Cupón aplicado --}}
                    @if($pedidoDetalle->cupon_id && $pedidoDetalle->monto_cupon > 0)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Cupón aplicado') }}</label>
                            <div class="flex items-center justify-between bg-amber-50 dark:bg-amber-900/20 rounded-lg px-4 py-2 border border-amber-200 dark:border-amber-800">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                        {{ $pedidoDetalle->cupon_codigo_snapshot ?? $pedidoDetalle->cupon?->codigo ?? __('Cupón') }}
                                    </span>
                                    @if($pedidoDetalle->cupon_descripcion_snapshot)
                                        <span class="text-xs text-amber-600 dark:text-amber-400">{{ $pedidoDetalle->cupon_descripcion_snapshot }}</span>
                                    @endif
                                </div>
                                <span class="text-sm font-semibold text-red-600 dark:text-red-400">-${{ number_format($pedidoDetalle->monto_cupon, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- Puntos canjeados (artículos + monto) --}}
                    @if($pedidoDetalle->puntos_usados > 0)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Puntos canjeados') }}</label>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg px-4 py-3 border border-yellow-200 dark:border-yellow-800 space-y-2">
                                @if($pedidoDetalle->puntos_canjeados_articulos > 0)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-yellow-800 dark:text-yellow-200">
                                                ⭐ {{ __('Artículos canjeados') }}: {{ $pedidoDetalle->puntos_canjeados_articulos }} pts
                                            </span>
                                        </div>
                                        <span class="text-sm font-semibold text-yellow-700 dark:text-yellow-300">
                                            -${{ number_format($pedidoDetalle->articulos_canjeados_monto, 2, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                                @if($pedidoDetalle->puntos_canjeados_pago > 0)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-yellow-800 dark:text-yellow-200">
                                                💳 {{ __('Pago con puntos') }}: {{ $pedidoDetalle->puntos_canjeados_pago }} pts
                                            </span>
                                        </div>
                                        <span class="text-sm font-semibold text-yellow-700 dark:text-yellow-300">
                                            -${{ number_format($pedidoDetalle->puntos_usados_monto, 2, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between pt-1 border-t border-yellow-200 dark:border-yellow-700">
                                    <span class="text-xs font-medium text-yellow-700 dark:text-yellow-400">{{ __('Total puntos usados') }}</span>
                                    <span class="text-sm font-bold text-yellow-800 dark:text-yellow-200">{{ number_format($pedidoDetalle->puntos_usados) }} pts</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Puntos ganados --}}
                    @if($pedidoDetalle->puntos_ganados > 0)
                        <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 rounded-lg px-4 py-2.5 border border-green-200 dark:border-green-800">
                            <span class="text-sm text-green-800 dark:text-green-200">
                                ⭐ {{ __('Puntos a ganar al cobrar') }}:
                                <span class="font-bold">+{{ number_format($pedidoDetalle->puntos_ganados) }} pts</span>
                            </span>
                        </div>
                    @endif

                    {{-- Desglose de pagos --}}
                    @if($pedidoDetalle->pagos->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Desglose de pagos') }}</label>

                            {{-- Cards móvil --}}
                            <div class="sm:hidden space-y-2">
                                @foreach($pedidoDetalle->pagos as $pago)
                                    @php
                                        $pagoAnulado = $pago->estado === 'anulado';
                                        $pagoPlanificado = $pago->estado === 'planificado';
                                    @endphp
                                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 {{ $pagoAnulado ? 'opacity-60' : '' }}">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $pago->formaPago?->nombre ?? '—' }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                                @if($pago->estado === 'activo') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                @elseif($pagoPlanificado) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                @else bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                                {{ __(ucfirst($pago->estado)) }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">{{ __('Base') }}:</span>
                                                <span class="text-gray-700 dark:text-gray-200">${{ number_format($pago->monto_base, 2, ',', '.') }}</span>
                                            </div>
                                            @if((float) $pago->monto_ajuste !== 0.0)
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Ajuste FP') }}:</span>
                                                    <span class="{{ $pago->monto_ajuste < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                        {{ $pago->monto_ajuste < 0 ? '-' : '+' }}${{ number_format(abs($pago->monto_ajuste), 2, ',', '.') }}
                                                    </span>
                                                </div>
                                            @endif
                                            @if($pago->cuotas && $pago->cuotas > 1)
                                                <div class="col-span-2">
                                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Cuotas') }}:</span>
                                                    <span class="text-gray-700 dark:text-gray-200">
                                                        {{ $pago->cuotas }}x ${{ number_format($pago->monto_cuota ?? 0, 2, ',', '.') }}
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="col-span-2 pt-1 border-t border-gray-200 dark:border-gray-600">
                                                <span class="text-gray-500 dark:text-gray-400">{{ __('Total cobrado') }}:</span>
                                                <span class="text-sm font-bold text-gray-900 dark:text-white">${{ number_format($pago->monto_final, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Tabla desktop --}}
                            <div class="hidden sm:block border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Forma de pago') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Base') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ajuste FP') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cuotas') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($pedidoDetalle->pagos as $pago)
                                            @php
                                                $pagoAnulado = $pago->estado === 'anulado';
                                                $pagoPlanificado = $pago->estado === 'planificado';
                                            @endphp
                                            <tr class="{{ $pagoAnulado ? 'opacity-60' : '' }}">
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                    {{ $pago->formaPago?->nombre ?? '—' }}
                                                    @if($pago->es_pago_puntos)
                                                        <span class="ml-1 text-[10px] text-yellow-600 dark:text-yellow-400">(puntos)</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200 text-right">${{ number_format($pago->monto_base, 2, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-sm text-right">
                                                    @if((float) $pago->monto_ajuste !== 0.0)
                                                        <span class="{{ $pago->monto_ajuste < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                            {{ $pago->monto_ajuste < 0 ? '-' : '+' }}${{ number_format(abs($pago->monto_ajuste), 2, ',', '.') }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200 text-right">
                                                    @if($pago->cuotas && $pago->cuotas > 1)
                                                        {{ $pago->cuotas }}x ${{ number_format($pago->monto_cuota ?? 0, 2, ',', '.') }}
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white text-right">${{ number_format($pago->monto_final, 2, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($pago->estado === 'activo') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                        @elseif($pagoPlanificado) bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                        @else bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                                        {{ __(ucfirst($pago->estado)) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Totales (desglose) --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">{{ __('Subtotal') }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100">${{ number_format($pedidoDetalle->subtotal, 2, ',', '.') }}</dd>
                            </div>
                            @if((float) $pedidoDetalle->iva > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('IVA') }}</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">${{ number_format($pedidoDetalle->iva, 2, ',', '.') }}</dd>
                                </div>
                            @endif
                            @if((float) $pedidoDetalle->descuento_general_monto > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">
                                        {{ __('Descuento general') }}
                                        @if($pedidoDetalle->descuento_general_tipo === 'porcentaje' && $pedidoDetalle->descuento_general_valor)
                                            ({{ number_format($pedidoDetalle->descuento_general_valor, 2, ',', '.') }}%)
                                        @endif
                                    </dt>
                                    <dd class="text-red-600 dark:text-red-400">-${{ number_format($pedidoDetalle->descuento_general_monto, 2, ',', '.') }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between font-medium">
                                <dt class="text-gray-700 dark:text-gray-200">{{ __('Total') }}</dt>
                                <dd class="text-gray-900 dark:text-white">${{ number_format($pedidoDetalle->total, 2, ',', '.') }}</dd>
                            </div>
                            @if((float) $pedidoDetalle->ajuste_forma_pago !== 0.0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Ajuste forma de pago') }}</dt>
                                    <dd class="{{ $pedidoDetalle->ajuste_forma_pago < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $pedidoDetalle->ajuste_forma_pago < 0 ? '-' : '+' }}${{ number_format(abs($pedidoDetalle->ajuste_forma_pago), 2, ',', '.') }}
                                    </dd>
                                </div>
                            @endif
                            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-600 text-base font-bold">
                                <dt class="text-gray-900 dark:text-white">{{ __('Total final') }}</dt>
                                <dd class="text-bcn-secondary dark:text-white">${{ number_format($pedidoDetalle->total_final, 2, ',', '.') }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if($pedidoDetalle->observaciones)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones') }}</label>
                            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line bg-gray-50 dark:bg-gray-700/40 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700">{{ $pedidoDetalle->observaciones }}</p>
                        </div>
                    @endif
                </div>
            </x-slot:body>

            <x-slot:footer>
                {{-- "Editar pedido" disponible mientras el pedido siga activo
                     (no cancelado ni facturado) y no tenga cobros materializados.
                     Mientras el cliente no haya pagado, el operario puede ajustar
                     el carrito en cualquier punto del flujo (en preparación, listo,
                     etc). Los pedidos con cobros activos se editan via "Cobrar
                     pendiente" desde la lista. --}}
                @if(! in_array($pedidoDetalle->estado_pedido, ['cancelado', 'facturado'])
                    && ($pedidoDetalle->estado_pedido === 'borrador' || $pedidoDetalle->estado_pago === 'pendiente'))
                    <button type="button" wire:click="abrirModalEditarPedido({{ $pedidoDetalle->id }})"
                        class="w-full inline-flex justify-center rounded-md border border-bcn-primary shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-bcn-primary hover:bg-bcn-primary hover:text-white sm:w-auto sm:text-sm">
                        {{ $pedidoDetalle->estado_pedido === 'borrador' ? __('Continuar borrador') : __('Editar pedido') }}
                    </button>
                @endif
                <button type="button" wire:click="reimprimirPrecuenta({{ $pedidoDetalle->id }})"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Imprimir precuenta') }}
                </button>
                <button type="button" wire:click="comandarPedido({{ $pedidoDetalle->id }})"
                    class="w-full inline-flex justify-center rounded-md border border-blue-300 dark:border-blue-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30 sm:w-auto sm:text-sm">
                    {{ __('Comandar') }}
                </button>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-secondary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                    {{ __('Cerrar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CAMBIAR ESTADO ==================== --}}
    @if($showCambiarEstadoModal)
        <x-bcn-modal
            :title="__('Cambiar estado del pedido')"
            color="bg-blue-600"
            maxWidth="md"
            onClose="cancelarCambiarEstado"
            submit="confirmarCambiarEstado"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nuevo estado') }}</label>
                        <select wire:model="nuevoEstado"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm">
                            @foreach($transicionesDisponibles as $estado)
                                <option value="{{ $estado }}">{{ __($estadosPedido[$estado] ?? $estado) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observación (opcional)') }}</label>
                        <textarea wire:model="observacionEstado" rows="3"
                            placeholder="{{ __('Ej: Demora en cocina, ajuste de pedido...') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm"></textarea>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:w-auto sm:text-sm">
                    {{ __('Aplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL COMANDAR ==================== --}}
    @if($showComandarModal)
        <x-bcn-modal
            :title="__('Comandar pedido')"
            color="bg-blue-600"
            maxWidth="md"
            onClose="cerrarComandarModal"
        >
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                    {{ __('Este pedido tiene items nuevos sin enviar a cocina. ¿Qué querés mandar?') }}
                </p>
                <div class="space-y-2">
                    <button type="button"
                        wire:click="confirmarComandar('nuevos')"
                        class="w-full inline-flex items-center justify-between px-4 py-3 border-2 border-amber-300 dark:border-amber-600 rounded-md bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:hover:bg-amber-900/50 text-left transition-colors">
                        <div class="flex flex-col">
                            <span class="font-semibold text-amber-900 dark:text-amber-200">{{ __('Comandar solo los nuevos') }}</span>
                            <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('Cocina recibirá solo lo agregado, con etiqueta AGREGADO') }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-200 dark:bg-amber-700 text-amber-900 dark:text-amber-100 font-bold text-sm">{{ $comandarNuevosCount }}</span>
                    </button>
                    <button type="button"
                        wire:click="confirmarComandar('todos')"
                        class="w-full inline-flex items-center justify-between px-4 py-3 border-2 border-blue-300 dark:border-blue-600 rounded-md bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-left transition-colors">
                        <div class="flex flex-col">
                            <span class="font-semibold text-blue-900 dark:text-blue-200">{{ __('Comandar todo el pedido') }}</span>
                            <span class="text-xs text-blue-700 dark:text-blue-300">{{ __('Cocina recibirá el ticket completo (reimpresión)') }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-200 dark:bg-blue-700 text-blue-900 dark:text-blue-100 font-bold text-sm">{{ $comandarNuevosCount + $comandarComandadosCount }}</span>
                    </button>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CANCELAR ==================== --}}
    @if($showCancelarModal)
        <x-bcn-modal
            :title="__('Cancelar pedido')"
            color="bg-red-600"
            maxWidth="md"
            onClose="cancelarCancelar"
            submit="ejecutarCancelar"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded p-3 text-sm">
                        <p class="text-red-800 dark:text-red-200">
                            {{ __('Al cancelar el pedido se anulan todos sus pagos activos (con contraasiento de caja) y se revierte el stock descontado. Esta acción no se puede deshacer.') }}
                        </p>
                        @if($cancelarPedidoInfo['tiene_pagos_activos'] ?? false)
                            <p class="text-red-800 dark:text-red-200 mt-2 font-semibold">
                                ⚠ {{ __('Este pedido tiene pagos cobrados. Se generarán contraasientos de caja.') }}
                            </p>
                        @endif
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <div><strong>{{ __('Pedido') }}:</strong>
                            @if($cancelarPedidoInfo['numero'] ?? null) #{{ $cancelarPedidoInfo['numero'] }} @endif
                        </div>
                        <div><strong>{{ __('Cliente') }}:</strong> {{ $cancelarPedidoInfo['cliente'] ?? '—' }}</div>
                        <div><strong>{{ __('Total') }}:</strong> ${{ number_format($cancelarPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo') }} *</label>
                        <textarea wire:model="motivoCancelacion" rows="3" required minlength="5"
                            placeholder="{{ __('Ej: cliente no se presentó, error de carga, etc.') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring focus:ring-red-500 focus:ring-opacity-50 text-sm"></textarea>
                        @error('motivoCancelacion')
                            <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm">
                    {{ __('Cancelar pedido') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: COBRAR PENDIENTE ==================== --}}
    @if($showCobrarModal)
        <x-bcn-modal
            :title="__('Cobrar pedido') . ' ' . (($cobrarPedidoInfo['numero'] ?? null) ? '#' . $cobrarPedidoInfo['numero'] : '')"
            color="bg-green-600"
            maxWidth="lg"
            onClose="cerrarCobrar"
        >
            <x-slot:body>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="bg-gray-100 dark:bg-gray-700 rounded p-3">
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ __('Total') }}</div>
                            <div class="text-sm font-bold text-bcn-secondary dark:text-white">${{ number_format($cobrarPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/30 rounded p-3">
                            <div class="text-xs text-green-700 dark:text-green-300">{{ __('Cobrado') }}</div>
                            <div class="text-sm font-bold text-green-800 dark:text-green-200">${{ number_format($cobrarPedidoInfo['total_cobrado'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded p-3">
                            <div class="text-xs text-blue-700 dark:text-blue-300">{{ __('Planificado') }}</div>
                            <div class="text-sm font-bold text-blue-800 dark:text-blue-200">${{ number_format($cobrarPedidoInfo['total_planificado'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded p-3">
                            <div class="text-xs text-yellow-700 dark:text-yellow-300">{{ __('Pendiente') }}</div>
                            <div class="text-sm font-bold text-yellow-800 dark:text-yellow-200">${{ number_format($cobrarPedidoInfo['pendiente'] ?? 0, 2, ',', '.') }}</div>
                        </div>
                    </div>

                    @if(empty($cobrarPagosPlanificados))
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded p-4 text-sm text-blue-800 dark:text-blue-200">
                            {{ __('Este pedido no tiene pagos planificados. Usá "Definir pagos" para cobrar el saldo pendiente con desglose de formas de pago.') }}
                        </div>
                    @else
                        <div>
                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 text-sm">{{ __('Pagos planificados') }}</div>
                            <div class="space-y-2">
                                @foreach($cobrarPagosPlanificados as $pago)
                                    <div class="flex justify-between items-center bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded p-3">
                                        <div class="flex-1">
                                            <div class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ $pago['forma_pago'] }}</div>
                                            <div class="text-xs text-blue-700 dark:text-blue-300">
                                                ${{ number_format($pago['monto_final'], 2, ',', '.') }}
                                                @if($pago['cuotas']) — {{ $pago['cuotas'] }} {{ __('cuotas') }} @endif
                                                @if($pago['referencia']) — {{ $pago['referencia'] }} @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="confirmarPagoPlanificado({{ $pago['id'] }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700">
                                                {{ __('Cobrar') }}
                                            </button>
                                            <button wire:click="eliminarPagoPlanificado({{ $pago['id'] }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center px-2 py-1.5 border border-red-300 dark:border-red-600 text-xs text-red-700 dark:text-red-300 rounded hover:bg-red-50 dark:hover:bg-red-900/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-slot:body>
            <x-slot:footer>
                @if(($cobrarPedidoInfo['pendiente'] ?? 0) > 0.01)
                    <button type="button"
                        wire:click="abrirCobroRapido({{ $pedidoCobrarId ?? 0 }})"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 sm:w-auto sm:text-sm">
                        <svg class="w-5 h-5 mr-1 hidden sm:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('Definir pagos') }}
                    </button>
                @endif
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:w-auto sm:text-sm">
                    {{ __('Listo') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: CONVERTIR EN VENTA ==================== --}}
    @if($showConvertirModal)
        <x-bcn-modal
            :title="__('Convertir pedido en venta')"
            color="bg-bcn-primary"
            maxWidth="md"
            onClose="cancelarConvertir"
            submit="ejecutarConvertir"
        >
            <x-slot:body>
                <div class="space-y-3">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <div><strong>{{ __('Pedido') }}:</strong>
                            @if($convertirPedidoInfo['numero'] ?? null) #{{ $convertirPedidoInfo['numero'] }} @endif
                        </div>
                        <div><strong>{{ __('Cliente') }}:</strong> {{ $convertirPedidoInfo['cliente'] ?? '—' }}</div>
                        <div><strong>{{ __('Total') }}:</strong> ${{ number_format($convertirPedidoInfo['total'] ?? 0, 2, ',', '.') }}</div>
                        <div><strong>{{ __('Cobrado') }}:</strong> ${{ number_format($convertirPedidoInfo['total_cobrado'] ?? 0, 2, ',', '.') }}</div>
                        @if(($convertirPedidoInfo['total_planificado'] ?? 0) > 0)
                            <div><strong>{{ __('Planificado') }}:</strong> ${{ number_format($convertirPedidoInfo['total_planificado'], 2, ',', '.') }}</div>
                        @endif
                    </div>

                    @if(($convertirPedidoInfo['pendiente'] ?? 0) > 0.005)
                        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded p-3 text-sm">
                            <p class="text-red-800 dark:text-red-200 font-semibold">
                                ⚠ {{ __('Faltan') }} ${{ number_format($convertirPedidoInfo['pendiente'], 2, ',', '.') }} {{ __('sin cubrir') }}
                            </p>
                            <p class="text-red-700 dark:text-red-300 mt-1 text-xs">
                                {{ __('Cargá pagos planificados o cobrados antes de convertir, o agregá un pago con forma "cuenta corriente".') }}
                            </p>
                        </div>
                    @elseif($convertirPedidoInfo['tiene_pagos_planificados'] ?? false)
                        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded p-3 text-sm text-blue-800 dark:text-blue-200">
                            {{ __('Los pagos planificados se materializarán al convertir (se generarán MovimientoCaja por cada uno).') }}
                        </div>
                    @endif

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Al convertir se crea la venta, se migran los pagos, se reasignan los movimientos de stock y caja. El pedido queda en estado "Facturado".') }}
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit"
                    @disabled(($convertirPedidoInfo['pendiente'] ?? 0) > 0.005)
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto sm:text-sm">
                    {{ __('Convertir en venta') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Sub-componente Livewire: modal full-screen de alta/edición --}}
    @if($modalNuevoPedidoAbierto)
        <livewire:pedidos.nuevo-pedido-mostrador
            :pedidoId="$pedidoIdEnEdicion"
            :key="'modal-nuevo-pedido-' . $modalNuevoPedidoKey"
        />
    @endif

    {{-- Sub-componente Livewire: cobro rapido (solo modal de desglose) --}}
    @if($pedidoCobroRapidoId)
        <livewire:pedidos.nuevo-pedido-mostrador
            :pedidoId="$pedidoCobroRapidoId"
            :modoCobroRapido="true"
            :key="'cobro-rapido-' . $pedidoCobroRapidoId . '-' . $cobroRapidoKey"
        />
    @endif

    {{-- Modal "Esperando pago" (QR) para materializar pagos planificados con
         forma de pago integrada desde "Cobrar pendiente". --}}
    @include('livewire.carrito._modal-esperando-pago-integracion')
</div>
