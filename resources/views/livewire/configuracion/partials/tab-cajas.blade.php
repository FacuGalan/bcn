{{-- Tab Cajas --}}
<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Configuración de Cajas') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Configura los parámetros de cada caja, asigna puntos de venta AFIP y define grupos de cierre compartido') }}
        </p>
    </div>

    @if($this->cajas->count() > 0)
        {{-- Agrupar cajas por sucursal --}}
        @php
            $cajasPorSucursal = $this->cajas->groupBy('sucursal_id');
            $gruposPorSucursal = $this->gruposCierre->groupBy('sucursal_id');
        @endphp

        <div class="space-y-8">
            @foreach($cajasPorSucursal as $sucursalId => $cajasGrupo)
                @php
                    $sucursal = $cajasGrupo->first()->sucursal;
                    $gruposSucursal = $gruposPorSucursal->get($sucursalId, collect());
                    $cajasIndividuales = $cajasGrupo->whereNull('grupo_cierre_id');
                    $cajasEnGrupos = $cajasGrupo->whereNotNull('grupo_cierre_id');
                @endphp

                <div class="{{ !$loop->first ? 'mt-8 pt-8 border-t border-gray-200 dark:border-gray-700' : '' }}">
                    {{-- Header Sucursal --}}
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <h4 class="text-base font-semibold text-gray-700 dark:text-gray-300">
                                {{ $sucursal->nombre }}
                            </h4>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                ({{ $cajasGrupo->count() }} {{ $cajasGrupo->count() === 1 ? __('caja') : __('cajas') }})
                            </span>
                        </div>
                    </div>

                    {{-- Layout 70/30 --}}
                    <div class="flex flex-col lg:flex-row gap-6">
                        {{-- ==================== COLUMNA CAJAS (70%) ==================== --}}
                        <div class="w-full lg:w-[70%]">
                            {{-- Cajas Individuales --}}
                            @if($cajasIndividuales->count() > 0)
                                <div class="mb-6">
                                    <h5 class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        {{ __('Cajas Individuales') }}
                                        <span class="text-xs font-normal text-gray-400">({{ __('cierran solas') }})</span>
                                    </h5>

                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                        @foreach($cajasIndividuales as $caja)
                                            @include('livewire.configuracion.partials.caja-card', ['caja' => $caja])
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Cajas en Grupos (para configurar) --}}
                            @if($cajasEnGrupos->count() > 0)
                                <div>
                                    <h5 class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ __('Cajas en Grupos') }}
                                    </h5>

                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                        @foreach($cajasEnGrupos as $caja)
                                            @include('livewire.configuracion.partials.caja-card', ['caja' => $caja])
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Mensaje si no hay cajas --}}
                            @if($cajasIndividuales->count() === 0 && $cajasEnGrupos->count() === 0)
                                <div class="text-center py-8 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('No hay cajas en esta sucursal') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- ==================== COLUMNA GRUPOS (30%) ==================== --}}
                        <div class="w-full lg:w-[30%]">
                            <div class="sticky top-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        {{ __('Grupos de Cierre') }}
                                    </h5>
                                    <button
                                        type="button"
                                        wire:click="crearGrupoCierre({{ $sucursalId }})"
                                        class="inline-flex items-center p-1.5 text-bcn-primary hover:text-bcn-primary/80 hover:bg-bcn-primary/10 rounded-lg transition-colors"
                                        title="{{ __('Nuevo grupo') }}"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>
                                </div>

                                @if($gruposSucursal->count() > 0)
                                    <div class="space-y-3">
                                        @foreach($gruposSucursal as $grupo)
                                            <div class="bg-bcn-primary/5 dark:bg-bcn-primary/10 rounded-lg p-3 border border-bcn-primary/20">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <svg class="w-4 h-4 text-bcn-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                                            </svg>
                                                            <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                                                {{ $grupo->nombre ?? "Grupo #{$grupo->id}" }}
                                                            </span>
                                                            @if($grupo->fondo_comun)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-800/30 dark:text-green-300" title="{{ __('Fondo compartido entre todas las cajas') }}">
                                                                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                                    </svg>
                                                                    {{ __('Común') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="flex flex-wrap gap-1">
                                                            @foreach($grupo->cajas as $caja)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300">
                                                                    {{ $caja->nombre }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-0.5 flex-shrink-0">
                                                        <button
                                                            type="button"
                                                            wire:click="editarGrupoCierre({{ $grupo->id }})"
                                                            class="p-1.5 text-gray-500 hover:text-bcn-primary hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                            title="{{ __('Editar grupo') }}"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="confirmarEliminarGrupo({{ $grupo->id }})"
                                                            class="p-1.5 text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                                            title="{{ __('Eliminar grupo') }}"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-6 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Sin grupos configurados') }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                            {{ __('Las cajas cierran individualmente') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Estado vacío --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('No hay cajas') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('No se encontraron cajas configuradas en el sistema.') }}
            </p>
        </div>
    @endif

    {{-- Información adicional --}}
    @if($this->cajas->count() > 0)
        <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">
                        {{ __('Acerca de la configuración de Cajas') }}
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                        <ul class="list-disc list-inside space-y-1">
                            <li><strong>{{ __('Grupos de Cierre:') }}</strong> {{ __('Las cajas en un grupo comparten el cierre de turno y sus movimientos se consolidan') }}</li>
                            <li><strong>{{ __('Fondo Común:') }}</strong> {{ __('Si está activo, el grupo maneja un único fondo compartido; si no, cada caja tiene su fondo individual') }}</li>
                            <li><strong>{{ __('Cajas Individuales:') }}</strong> {{ __('Cierran de forma independiente') }}</li>
                            <li><strong>{{ __('Configurar:') }}</strong> {{ __('Define límites de efectivo y modo de carga inicial del turno') }}</li>
                            <li><strong>{{ __('Puntos Fiscales:') }}</strong> {{ __('Asigna los puntos de venta AFIP para facturación electrónica') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ==================== MODAL GRUPO DE CIERRE ==================== --}}
@if($mostrarModalGrupoCierre)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalGrupoCierre"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            {{-- Modal --}}
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-bcn-primary/10 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                {{ $modoEdicionGrupo ? __('Editar Grupo de Cierre') : __('Nuevo Grupo de Cierre') }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Las cajas en este grupo compartirán el cierre de turno') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        {{-- Nombre del grupo (opcional) --}}
                        <div>
                            <label for="grupoNombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('Nombre del grupo') }} <span class="text-gray-400 font-normal">({{ __('opcional') }})</span>
                            </label>
                            <input
                                type="text"
                                id="grupoNombre"
                                wire:model="grupoNombre"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                :placeholder="__('Ej: Cajas Mostrador, Delivery...')"
                            >
                        </div>

                        {{-- Tipo de fondo --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="grupoFondoComun"
                                    class="mt-1 h-4 w-4 text-bcn-primary border-gray-300 rounded focus:ring-bcn-primary dark:bg-gray-700 dark:border-gray-600"
                                >
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ __('Fondo Común') }}</span>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ __('Si está activo, el grupo maneja un único fondo compartido al abrir turno.') }}
                                        {{ __('Si no, cada caja tiene su propio fondo inicial individual.') }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        {{-- Selección de cajas --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Selecciona las cajas del grupo') }}
                                <span class="text-gray-400 font-normal">({{ __('mínimo 2') }})</span>
                            </label>

                            @php
                                $cajasDisponibles = $this->getCajasDisponiblesParaGrupo();
                            @endphp

                            @if($cajasDisponibles->count() > 0)
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                                    @foreach($cajasDisponibles as $caja)
                                        @php
                                            $estaSeleccionada = in_array($caja->id, $grupoCajasSeleccionadas);
                                        @endphp
                                        <div
                                            class="flex items-center p-3 rounded-lg border cursor-pointer transition-colors {{ $estaSeleccionada ? 'bg-bcn-primary/5 border-bcn-primary/30 dark:bg-bcn-primary/10' : 'bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                                            wire:click="toggleCajaEnGrupo({{ $caja->id }})"
                                        >
                                            {{-- Checkbox visual --}}
                                            <div class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors {{ $estaSeleccionada ? 'bg-bcn-primary border-bcn-primary text-white' : 'border-gray-300 dark:border-gray-500' }}">
                                                @if($estaSeleccionada)
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                            </div>

                                            {{-- Info de la caja --}}
                                            <div class="ml-3 flex-1">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $caja->nombre }}</span>
                                                <span class="ml-2 text-xs px-2 py-0.5 rounded {{ $caja->activo ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                                                    {{ $caja->activo ? __('Activa') : __('Inactiva') }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Contador de seleccionadas --}}
                                <p class="mt-2 text-sm {{ count($grupoCajasSeleccionadas) >= 2 ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ count($grupoCajasSeleccionadas) }} {{ count($grupoCajasSeleccionadas) === 1 ? __('caja seleccionada') : __('cajas seleccionadas') }}
                                    @if(count($grupoCajasSeleccionadas) < 2)
                                        <span class="text-yellow-600 dark:text-yellow-400">({{ __('necesitas al menos 2') }})</span>
                                    @endif
                                </p>
                            @else
                                <div class="text-center py-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                    <svg class="mx-auto h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        {{ __('No hay cajas disponibles para agrupar') }}
                                    </p>
                                    <p class="text-xs text-yellow-600 dark:text-yellow-400">
                                        {{ __('Todas las cajas ya pertenecen a otros grupos') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Botones --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button
                        type="button"
                        wire:click="guardarGrupoCierre"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        {{ count($grupoCajasSeleccionadas) < 2 ? 'disabled' : '' }}
                    >
                        {{ $modoEdicionGrupo ? __('Guardar Cambios') : __('Crear Grupo') }}
                    </button>
                    <button
                        type="button"
                        wire:click="cerrarModalGrupoCierre"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:mt-0 sm:w-auto sm:text-sm"
                    >
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== MODAL CONFIRMAR ELIMINAR GRUPO ==================== --}}
@if($mostrarConfirmacionEliminarGrupo)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelarEliminarGrupo"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                {{ __('Eliminar grupo de cierre') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('¿Estás seguro de eliminar este grupo? Las cajas pasarán a cerrar de forma individual.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button
                        type="button"
                        wire:click="eliminarGrupoCierre"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm"
                    >
                        {{ __('Eliminar') }}
                    </button>
                    <button
                        type="button"
                        wire:click="cancelarEliminarGrupo"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm"
                    >
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
