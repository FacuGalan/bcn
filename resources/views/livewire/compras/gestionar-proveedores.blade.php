<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white flex items-center h-10 sm:h-auto">{{ __('Proveedores') }}</h2>
                        <div class="sm:hidden flex gap-2">
                            <button wire:click="openCuentasModal" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition ease-in-out duration-150" title="{{ __('Cuentas de compra') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                            </button>
                            <button wire:click="create" class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150" title="{{ __('Nuevo proveedor') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            </button>
                        </div>
                    </div>
                    <p class="hidden sm:block mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Catálogo de proveedores, cuenta corriente y cuentas de compra') }}</p>
                </div>
                <div class="hidden sm:flex gap-2">
                    <button wire:click="openCuentasModal" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                        {{ __('Cuentas de compra') }}
                    </button>
                    <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        {{ __('Nuevo proveedor') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar por nombre, código o CUIT...') }}"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
            </div>
            <select wire:model.live="filterStatus" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                <option value="all">{{ __('Todos') }}</option>
                <option value="active">{{ __('Activos') }}</option>
                <option value="inactive">{{ __('Inactivos') }}</option>
            </select>
        </div>

        <!-- Cards móvil -->
        <div class="sm:hidden space-y-3">
            @forelse($proveedores as $proveedor)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 {{ $proveedor->activo ? '' : 'opacity-60' }}">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $proveedor->nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $proveedor->cuit ?: '—' }} @if($proveedor->codigo) · {{ $proveedor->codigo }} @endif</p>
                        </div>
                        <div class="text-right">
                            @if($proveedor->tiene_cuenta_corriente)
                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Cta cte') }}</span>
                                <p class="mt-1 text-sm font-bold {{ $proveedor->saldo_cache > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">$@precio($proveedor->saldo_cache)</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2 justify-end">
                        <button wire:click="verExtracto({{ $proveedor->id }})" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ __('Cuenta') }}</button>
                        <button wire:click="edit({{ $proveedor->id }})" class="px-3 py-1.5 text-xs rounded-md bg-bcn-primary text-white">{{ __('Editar') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">{{ __('No hay proveedores') }}</p>
            @endforelse
        </div>

        <!-- Tabla desktop -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Proveedor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('CUIT') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Cuenta de compra') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Cta cte') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Saldo') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($proveedores as $proveedor)
                            <tr class="{{ $proveedor->activo ? '' : 'opacity-60' }} hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $proveedor->nombre }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $proveedor->codigo ?: '' }} {{ $proveedor->razon_social ? '· '.$proveedor->razon_social : '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $proveedor->cuit ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $proveedor->cuentaCompra?->nombre ?: '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($proveedor->tiene_cuenta_corriente)
                                        <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Sí') }}@if($proveedor->dias_pago) · {{ $proveedor->dias_pago }}d @endif</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold {{ $proveedor->saldo_cache > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">
                                    $@precio($proveedor->saldo_cache)
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button wire:click="verExtracto({{ $proveedor->id }})" class="text-gray-500 hover:text-bcn-primary dark:text-gray-400 mr-2" title="{{ __('Estado de cuenta') }}">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    </button>
                                    <button wire:click="edit({{ $proveedor->id }})" class="text-gray-500 hover:text-bcn-primary dark:text-gray-400 mr-2" title="{{ __('Editar') }}">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button wire:click="toggleActivo({{ $proveedor->id }})" class="text-gray-500 hover:text-bcn-primary dark:text-gray-400" title="{{ $proveedor->activo ? __('Desactivar') : __('Activar') }}">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14L21 3m0 0l-6.5 18a.55.55 0 01-1 0L10 14l-7-3.5a.55.55 0 010-1L21 3" /></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No hay proveedores') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $proveedores->links() }}</div>
    </div>

    {{-- Modal ABM proveedor --}}
    @if($showModal)
        <x-bcn-modal :title="$editMode ? __('Editar Proveedor') : __('Nuevo Proveedor')" color="bg-bcn-primary" maxWidth="2xl" onClose="cancel" submit="save">
            <x-slot:body>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre') }} *</label>
                        <input type="text" wire:model="nombre" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        @error('nombre') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Razón social') }}</label>
                        <input type="text" wire:model="razon_social" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Código') }}</label>
                        <input type="text" wire:model="codigo" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('CUIT') }}</label>
                        <input type="text" wire:model="cuit" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Condición IVA') }}</label>
                        <select wire:model="condicion_iva_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                            <option value="">{{ __('Sin especificar') }}</option>
                            @foreach($condicionesIva as $condicion)
                                <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cuenta de compra (default)') }}</label>
                        <select wire:model="cuenta_compra_id" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                            <option value="">{{ __('Sin clasificar') }}</option>
                            @foreach($cuentasCompra->where('activo', true) as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Tipifica el gasto en los reportes; se precarga en cada compra (editable)') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Teléfono') }}</label>
                        <input type="text" wire:model="telefono" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Dirección') }}</label>
                        <input type="text" wire:model="direccion" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    </div>

                    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" wire:model.live="tiene_cuenta_corriente" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                {{ __('Tiene cuenta corriente') }}
                            </label>
                            @if($tiene_cuenta_corriente)
                                <div class="flex items-center gap-2">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('Días de pago') }}</label>
                                    <input type="number" wire:model="dias_pago" min="0" max="365" class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                                </div>
                            @endif
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" wire:model="activo" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                                {{ __('Activo') }}
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Con cuenta corriente, las compras pueden quedar como deuda y pagarse después; los días de pago precargan el vencimiento') }}</p>
                    </div>

                    {{-- D23: proveedor de servicios --}}
                    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="es_servicio" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary">
                            {{ __('Proveedor de servicios (luz, gas, alquiler...)') }}
                        </label>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Sus compras se sugieren como "factura de servicio": sin artículos ni stock, con la cuenta de compra como eje del gasto') }}</p>
                    </div>

                    {{-- D24: percepciones habituales --}}
                    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Percepciones habituales') }}</label>
                            <button type="button" wire:click="agregarPercepcionHabitual" class="text-xs text-bcn-primary hover:underline">{{ __('+ Agregar percepción') }}</button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Se precargan como renglones de percepción al elegir el proveedor en una compra (el monto exacto sale de la factura física)') }}</p>
                        <div class="mt-2 space-y-2">
                            @foreach($percepciones_habituales as $index => $percepcion)
                                <div class="flex items-center gap-2" wire:key="percepcion-habitual-{{ $index }}">
                                    <select wire:model="percepciones_habituales.{{ $index }}.impuesto_id"
                                        class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                                        <option value="">{{ __('Impuesto...') }}</option>
                                        @foreach($impuestosPercepcion as $impuesto)
                                            <option value="{{ $impuesto->id }}">{{ $impuesto->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="percepciones_habituales.{{ $index }}.alicuota" placeholder="{{ __('Alíc. %') }}"
                                        class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                                    <button type="button" wire:click="quitarPercepcionHabitual({{ $index }})" tabindex="-1"
                                        class="text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="{{ __('Quitar') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">{{ __('Cancelar') }}</button>
                <button type="button" wire:click="save" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">{{ $editMode ? __('Actualizar') : __('Crear') }}</button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal cuentas de compra (RF-22) --}}
    @if($showCuentasModal)
        <x-bcn-modal :title="__('Cuentas de compra')" color="bg-bcn-primary" maxWidth="md" onClose="cerrarCuentasModal">
            <x-slot:body>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Agrupan las compras para los reportes de gastos (¿cuánto gasté en qué?). Cada proveedor tiene una cuenta default, editable por compra.') }}</p>
                <div class="space-y-2">
                    @foreach($cuentasCompra as $cuenta)
                        <div class="flex items-center justify-between px-3 py-2 rounded-md bg-gray-50 dark:bg-gray-700 {{ $cuenta->activo ? '' : 'opacity-60' }}">
                            <span class="text-sm text-gray-800 dark:text-gray-200">{{ $cuenta->nombre }}</span>
                            <button wire:click="toggleCuenta({{ $cuenta->id }})" class="text-xs {{ $cuenta->activo ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}">
                                {{ $cuenta->activo ? __('Desactivar') : __('Activar') }}
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-2">
                    <input type="text" wire:model="nuevaCuentaNombre" wire:keydown.enter="agregarCuenta" placeholder="{{ __('Nueva cuenta...') }}"
                           class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                    <button wire:click="agregarCuenta" class="px-4 py-2 bg-bcn-primary text-white text-sm rounded-md hover:bg-opacity-90">{{ __('Agregar') }}</button>
                </div>
                @error('nuevaCuentaNombre') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:w-auto sm:text-sm">{{ __('Cerrar') }}</button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal estado de cuenta --}}
    @if($showExtractoModal && $proveedorExtracto)
        <x-bcn-modal :title="__('Estado de cuenta — :nombre', ['nombre' => $proveedorExtracto->nombre])" color="bg-bcn-primary" maxWidth="3xl" onClose="cerrarExtracto">
            <x-slot:body>
                <div class="flex gap-4 mb-4">
                    <div class="flex-1 rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3">
                        <p class="text-xs text-red-700 dark:text-red-300">{{ __('Deuda (sucursal activa)') }}</p>
                        <p class="text-lg font-bold text-red-700 dark:text-red-300">$@precio($saldosExtracto['saldo_deuda'] ?? 0)</p>
                    </div>
                    <div class="flex-1 rounded-md bg-green-50 dark:bg-green-900/30 px-4 py-3">
                        <p class="text-xs text-green-700 dark:text-green-300">{{ __('Saldo a favor nuestro') }}</p>
                        <p class="text-lg font-bold text-green-700 dark:text-green-300">$@precio($saldosExtracto['saldo_favor'] ?? 0)</p>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Fecha') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Debe') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Haber') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Saldo') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($extracto as $mov)
                                <tr class="{{ $mov['es_anulacion'] ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $mov['concepto'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $mov['debe'] > 0 ? '$'.number_format($mov['debe'], 2, ',', '.') : '' }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $mov['haber'] > 0 ? '$'.number_format($mov['haber'], 2, ',', '.') : '' }}</td>
                                    <td class="px-3 py-2 text-right font-medium {{ $mov['saldo_deuda'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">$@precio($mov['saldo_deuda'])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('Sin movimientos en esta sucursal') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none sm:w-auto sm:text-sm">{{ __('Cerrar') }}</button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
