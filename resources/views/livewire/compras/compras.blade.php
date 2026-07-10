{{-- Listado de compras (Fase 6, sesión UX D7 — patrón lista de pedidos) --}}
@php
    $labelTipo = fn (string $tipo) => __('compra_tipo_'.$tipo);
    $puedeCrear = auth()->user()?->hasPermissionTo('func.compras.crear');
    $puedeCancelar = auth()->user()?->hasPermissionTo('func.compras.cancelar');
    $puedePagar = auth()->user()?->hasPermissionTo('func.compras.pagar');
    $puedeVerCostos = auth()->user()?->hasPermissionTo('func.costos.ver');
    $puedeConfirmar = auth()->user()?->hasPermissionTo('func.compras.confirmar');
    $puedeCorregir = $puedeConfirmar && $puedeCancelar;
@endphp
<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">

        {{-- 1. HEADER --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">
                            {{ __('Compras') }}
                        </h2>
                        {{-- Botones mobile --}}
                        @if($puedeCrear)
                            <div class="sm:hidden flex gap-2">
                                <button wire:click="abrirNuevaNC" title="{{ __('Nueva NC') }}"
                                    class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-purple-600 border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-600 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" /></svg>
                                </button>
                                <button wire:click="abrirNuevaCompra" title="{{ __('Nueva Compra') }}"
                                    class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                </button>
                            </div>
                        @endif
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Carga de comprobantes de proveedores: stock, costos, crédito fiscal y cuenta corriente') }}
                    </p>
                </div>
                {{-- Botones desktop --}}
                @if($puedeCrear)
                    <div class="hidden sm:flex gap-3">
                        <button wire:click="abrirNuevaNC"
                            class="inline-flex items-center justify-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-600 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" /></svg>
                            {{ __('Nueva NC') }}
                        </button>
                        <button wire:click="abrirNuevaCompra"
                            class="inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            {{ __('Nueva Compra') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- 2. FILTROS --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button wire:click="toggleFilters" class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                        {{ __('Filtros') }}
                    </span>
                    <svg class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Buscar') }}</label>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('N° comprobante o proveedor...') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Estado') }}</label>
                        <select wire:model.live="filterEstado"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="all">{{ __('Todos') }}</option>
                            <option value="borrador">{{ __('Borradores') }}</option>
                            <option value="completada">{{ __('Completadas') }}</option>
                            <option value="con_saldo">{{ __('Con saldo pendiente') }}</option>
                            <option value="cancelada">{{ __('Canceladas') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Proveedor') }}</label>
                        <select wire:model.live="filterProveedor"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                            <option value="">{{ __('Todos') }}</option>
                            @foreach($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Desde') }}</label>
                        <input type="date" wire:model.live="filterFechaDesde"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Hasta') }}</label>
                            <input type="date" wire:model.live="filterFechaHasta"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50">
                        </div>
                        <button wire:click="resetFilters" title="{{ __('Limpiar filtros') }}"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. CARDS MOBILE --}}
        <div class="sm:hidden space-y-3">
            @forelse($compras as $compra)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $compra->numero_comprobante }}
                                @if($compra->esNotaCredito())
                                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">NC</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $labelTipo($compra->tipo_comprobante) }}
                                @if($compra->numero_comprobante_proveedor) · {{ $compra->numero_comprobante_proveedor }} @endif
                            </p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $compra->proveedor?->nombre ?? '—' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $compra->fecha?->format('d/m/Y') }}</p>
                        </div>
                        <p class="text-base font-bold text-gray-900 dark:text-white whitespace-nowrap">$@precio($compra->total)</p>
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        @include('livewire.compras._badge-estado-compra', ['compra' => $compra])
                        {{-- Badge de pago = botón pagar (D7 #10) --}}
                        @if($compra->estaCompletada() && ! $compra->esNotaCredito())
                            @if($compra->tieneSaldoPendiente() && $puedePagar)
                                <button type="button" wire:click="abrirPago({{ $compra->id }})" class="inline-flex items-center gap-1 group cursor-pointer" title="{{ __('Registrar pago') }}">
                                    @include('livewire.compras._badge-pago-compra', ['compra' => $compra])
                                    <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-opacity flex-shrink-0" aria-hidden="true">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" /></svg>
                                    </span>
                                </button>
                            @else
                                @include('livewire.compras._badge-pago-compra', ['compra' => $compra])
                            @endif
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <button wire:click="verDetalle({{ $compra->id }})"
                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{ __('Ver') }}
                        </button>
                        @if($compra->esBorrador() && $puedeCrear)
                            <button wire:click="abrirEditarCompra({{ $compra->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                {{ __('Editar') }}
                            </button>
                            <button wire:click="abrirEliminarBorrador({{ $compra->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                {{ __('Eliminar') }}
                            </button>
                        @endif
                        @if($compra->estaCompletada() && ! $compra->esNotaCredito() && $puedeCorregir)
                            <button wire:click="abrirEditarCompra({{ $compra->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                {{ __('Corregir') }}
                            </button>
                        @endif
                        @if($compra->estaCompletada() && ! $compra->esNotaCredito() && $puedeCrear)
                            <button wire:click="abrirNCDesdeCompra({{ $compra->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-purple-300 dark:border-purple-600 rounded text-xs text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/30">
                                {{ __('Cargar NC') }}
                            </button>
                        @endif
                        @if($compra->estaCompletada() && $puedeCancelar)
                            <button wire:click="abrirCancelar({{ $compra->id }})"
                                class="inline-flex items-center px-2.5 py-1.5 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                {{ __('Cancelar') }}
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No hay compras registradas') }}</p>
                </div>
            @endforelse
            @if($compras->hasPages())
                <div>{{ $compras->links() }}</div>
            @endif
        </div>

        {{-- 4. TABLA DESKTOP --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('N°') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Proveedor') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Comprobante') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Total') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Pago') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($compras as $compra)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                {{-- N°: lápiz en hover si es editable — borrador (edición directa) o completada (corrección D7 #12) --}}
                                @php
                                    $editableInline = ($compra->esBorrador() && $puedeCrear)
                                        || ($compra->estaCompletada() && ! $compra->esNotaCredito() && $puedeCorregir);
                                @endphp
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($editableInline)
                                        <button type="button" wire:click="abrirEditarCompra({{ $compra->id }})"
                                            class="inline-flex items-center gap-1 group cursor-pointer" title="{{ $compra->esBorrador() ? __('Editar borrador') : __('Corregir compra') }}">
                                            <span class="font-bold text-gray-900 dark:text-white">{{ $compra->numero_comprobante }}</span>
                                            <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-amber-500 transition-opacity flex-shrink-0" aria-hidden="true">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </span>
                                        </button>
                                    @else
                                        <span class="font-bold text-gray-900 dark:text-white">{{ $compra->numero_comprobante }}</span>
                                    @endif
                                    @if($compra->esNotaCredito())
                                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">NC</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $compra->proveedor?->nombre ?? '—' }}
                                    @if($compra->cuentaCompra)
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $compra->cuentaCompra->nombre }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $labelTipo($compra->tipo_comprobante) }}
                                    @if($compra->numero_comprobante_proveedor)
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $compra->numero_comprobante_proveedor }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $compra->fecha?->format('d/m/Y') }}
                                    @if($compra->fecha_vencimiento && $compra->tieneSaldoPendiente())
                                        <p class="text-xs {{ $compra->fecha_vencimiento->isPast() ? 'text-red-500 dark:text-red-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ __('Vence') }}: {{ $compra->fecha_vencimiento->format('d/m/Y') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                    $@precio($compra->total)
                                </td>
                                {{-- Pago: badge-botón (D7 #10) --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($compra->estaCompletada() && ! $compra->esNotaCredito())
                                        @if($compra->tieneSaldoPendiente() && $puedePagar)
                                            <button type="button" wire:click="abrirPago({{ $compra->id }})" class="inline-flex items-center gap-1 group cursor-pointer" title="{{ __('Registrar pago') }}">
                                                @include('livewire.compras._badge-pago-compra', ['compra' => $compra])
                                                <span class="opacity-0 group-hover:opacity-100 text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400 transition-opacity flex-shrink-0" aria-hidden="true">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" /></svg>
                                                </span>
                                            </button>
                                        @else
                                            @include('livewire.compras._badge-pago-compra', ['compra' => $compra])
                                        @endif
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @include('livewire.compras._badge-estado-compra', ['compra' => $compra])
                                </td>
                                {{-- Acciones acotadas (D7 #10) --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-1.5 flex-wrap">
                                        <button wire:click="verDetalle({{ $compra->id }})" title="{{ __('Ver detalle') }}"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        @if($compra->esBorrador() && $puedeCrear)
                                            <button wire:click="abrirEliminarBorrador({{ $compra->id }})" title="{{ __('Eliminar borrador') }}"
                                                class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        @endif
                                        @if($compra->estaCompletada() && ! $compra->esNotaCredito() && $puedeCrear)
                                            <button wire:click="abrirNCDesdeCompra({{ $compra->id }})" title="{{ __('Cargar NC') }}"
                                                class="inline-flex items-center px-2 py-1 border border-purple-300 dark:border-purple-600 rounded text-xs text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" /></svg>
                                            </button>
                                        @endif
                                        @if($compra->estaCompletada() && $puedeCancelar)
                                            <button wire:click="abrirCancelar({{ $compra->id }})" title="{{ __('Cancelar') }}"
                                                class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('No hay compras registradas') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $compras->links() }}
            </div>
        </div>

    </div>

    {{-- ============ MODAL DETALLE (D7 #11: reconstrucción perfecta) ============ --}}
    @if($showDetalleModal && $compraDetalle)
        @include('livewire.compras._detalle-compra', [
            'compra' => $compraDetalle,
            'pagosAplicados' => $pagosDetalle,
            'costosGenerados' => $costosDetalle,
            'puedeVerCostos' => $puedeVerCostos,
            'puedeCrear' => $puedeCrear,
            'puedeCancelar' => $puedeCancelar,
            'labelTipo' => $labelTipo,
        ])
    @endif

    {{-- ============ MODAL CANCELAR (D17) ============ --}}
    @if($showCancelarModal)
        <x-bcn-modal :title="__('Cancelar compra')" color="bg-red-600" maxWidth="lg" onClose="$set('showCancelarModal', false)">
            <x-slot:body>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Se revierten stock, costos, crédito fiscal y cuenta corriente por contraasiento. La factura podrá volver a cargarse.') }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Motivo') }} *</label>
                        <input type="text" wire:model="motivoCancelacion"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                        @error('motivoCancelacion') <span class="text-red-600 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    @if($cancelarTienePagos)
                        <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 px-4 py-3 space-y-2">
                            <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">{{ __('La compra tiene pagos aplicados — elegí qué hacer con ellos (D17)') }}</p>
                            <label class="flex items-start gap-2 text-sm text-yellow-800 dark:text-yellow-200 cursor-pointer">
                                <input type="radio" wire:model="manejoPagos" value="saldo_favor" class="mt-0.5 border-yellow-400 text-yellow-600 focus:ring-yellow-500">
                                <span><strong>{{ __('Dejar como saldo a favor') }}</strong> — {{ __('la plata salió de verdad: queda a favor nuestro con el proveedor') }}</span>
                            </label>
                            <label class="flex items-start gap-2 text-sm text-yellow-800 dark:text-yellow-200 cursor-pointer">
                                <input type="radio" wire:model="manejoPagos" value="anular_pagos" class="mt-0.5 border-yellow-400 text-yellow-600 focus:ring-yellow-500">
                                <span><strong>{{ __('Anular los pagos en cascada') }}</strong> — {{ __('error de carga: se anula cada orden de pago completa (bloqueado si el turno de caja está cerrado)') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="button" wire:click="confirmarCancelar" wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    {{ __('Cancelar compra') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ============ MODAL ELIMINAR BORRADOR ============ --}}
    @if($showEliminarModal)
        <x-bcn-modal :title="__('Eliminar borrador')" color="bg-red-600" maxWidth="md" onClose="$set('showEliminarModal', false)">
            <x-slot:body>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('El borrador nunca tuvo efectos: se elimina definitivamente. ¿Continuar?') }}</p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="button" wire:click="confirmarEliminarBorrador" wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    {{ __('Eliminar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ============ MODAL PAGO RÁPIDO (badge de pago, D7 #10) ============ --}}
    @if($showPagoModal && $compraPago)
        <x-bcn-modal :title="__('Pagar :numero — :proveedor', ['numero' => $compraPago->numero_comprobante, 'proveedor' => $compraPago->proveedor?->nombre])" color="bg-green-600" maxWidth="2xl" onClose="$set('showPagoModal', false)">
            <x-slot:body>
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-700 px-4 py-3">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Saldo pendiente') }}</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">$@precio($compraPago->saldo_pendiente)</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Monto a aplicar a esta compra') }}</label>
                        <input type="text" wire:model="montoAPagar"
                            class="mt-1 block w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm text-right focus:border-green-500 focus:ring-green-500">
                    </div>
                    @if($saldoFavorDisponible > 0)
                        <div class="flex items-center gap-3 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                            <p class="text-sm text-green-700 dark:text-green-300 flex-1">{{ __('Saldo a favor disponible') }}: <strong>$@precio($saldoFavorDisponible)</strong></p>
                            <label class="text-sm text-green-700 dark:text-green-300">{{ __('Usar') }}</label>
                            <input type="text" wire:model="saldoFavorUsado"
                                class="w-28 rounded-md border-green-300 dark:border-green-700 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                    @endif
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Formas de pago') }}</h4>
                            <button type="button" wire:click="agregarRenglonPago" class="text-xs text-green-600 hover:underline">{{ __('+ Agregar renglón') }}</button>
                        </div>
                        <div class="space-y-2">
                            @foreach($pagos as $index => $pago)
                                <div class="flex flex-wrap items-center gap-2" wire:key="pago-listado-{{ $index }}">
                                    <select wire:model="pagos.{{ $index }}.forma_pago_id"
                                        class="flex-1 min-w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="">{{ __('Forma de pago...') }}</option>
                                        @foreach($formasPago as $fp)
                                            <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="pagos.{{ $index }}.monto" placeholder="{{ __('Monto') }}"
                                        class="w-28 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                    @if($this->puedePagarAvanzado())
                                        <select wire:model.live="pagos.{{ $index }}.origen"
                                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                            <option value="caja">{{ __('Caja') }}</option>
                                            <option value="tesoreria">{{ __('Tesorería') }}</option>
                                            <option value="cuenta_empresa">{{ __('Cuenta de empresa') }}</option>
                                        </select>
                                        @if(($pago['origen'] ?? 'caja') === 'caja')
                                            <select wire:model="pagos.{{ $index }}.caja_id"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                                @foreach($cajasDisponibles as $caja)
                                                    <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(($pago['origen'] ?? '') === 'cuenta_empresa')
                                            <select wire:model="pagos.{{ $index }}.cuenta_empresa_id"
                                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:border-green-500 focus:ring-green-500">
                                                <option value="">{{ __('Cuenta...') }}</option>
                                                @foreach($cuentasEmpresa as $cuenta)
                                                    <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif
                                    @if(count($pagos) > 1)
                                        <button type="button" wire:click="quitarRenglonPago({{ $index }})"
                                            class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if(! $this->puedePagarAvanzado())
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('El pago sale de la caja activa (para otros orígenes se requiere el permiso de pago avanzado)') }}</p>
                        @endif
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                    {{ __('Volver') }}
                </button>
                <button type="button" wire:click="confirmarPago" wire:loading.attr="disabled"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    {{ __('Registrar pago') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ============ EDITOR (modal fullscreen, montaje condicional con key) ============ --}}
    @if($editorAbierto)
        <livewire:compras.editor-compra
            :compraId="$compraIdEnEdicion"
            :ncOrigenId="$editorNcOrigenId"
            :esNC="$editorEsNC"
            :key="'editor-compra-'.$editorKey" />
    @endif
</div>
