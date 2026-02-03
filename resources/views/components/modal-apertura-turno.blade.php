{{--
    Modal de Apertura de Turno

    Para usar en componentes Livewire que incluyan el AperturaTurnoTrait.

    Uso:
    @include('components.modal-apertura-turno')

    Requiere las siguientes propiedades en el componente:
    - showAperturaModal: bool
    - esAperturaGrupal: bool
    - grupoUsaFondoComun: bool
    - fondoComunTotal: string
    - cajasAAbrir: array
    - fondosIniciales: array
    - grupoAperturaId: ?int
    - cajaAperturaId: ?int

    Y los métodos:
    - procesarApertura()
    - cancelarApertura()
    - getCajaParaApertura(int $cajaId): ?array
    - getGrupoParaApertura(): ?array
--}}

@if($showAperturaModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        {{-- Fondo oscuro --}}
        <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"
             wire:click="cancelarApertura"></div>

        {{-- Centrar modal --}}
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        {{-- Contenido del modal --}}
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            {{-- Header --}}
            <div class="bg-indigo-600 px-4 py-3">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    @if($esAperturaGrupal)
                        {{ __('Abrir Turno del Grupo') }}
                    @else
                        {{ __('Abrir Turno de Caja') }}
                    @endif
                </h3>
            </div>

            {{-- Body --}}
            <div class="px-4 py-5 sm:p-6">
                @if($esAperturaGrupal && $grupoUsaFondoComun)
                    {{-- Apertura grupal con fondo común --}}
                    @php $grupo = $this->getGrupoParaApertura(); @endphp
                    @if($grupo)
                        <div class="mb-4 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                            <p class="text-sm text-indigo-700 dark:text-indigo-300 font-medium">
                                {{ $grupo['nombre'] }}
                            </p>
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">
                                {{ __(':count cajas con fondo en comun', ['count' => $grupo['cantidad_cajas']]) }}
                            </p>
                        </div>

                        {{-- Lista de cajas del grupo --}}
                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Cajas que se abriran:') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($grupo['cajas'] ?? [] as $cajaInfo)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ $cajaInfo['nombre'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Input para fondo común --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Fondo Inicial Comun') }}
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                <input type="number"
                                       wire:model="fondoComunTotal"
                                       step="0.01"
                                       min="0"
                                       class="pl-8 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="0.00">
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Este monto sera el fondo compartido por todas las cajas del grupo') }}
                            </p>
                        </div>
                    @endif
                @elseif($esAperturaGrupal)
                    {{-- Apertura grupal sin fondo común --}}
                    @php $grupo = $this->getGrupoParaApertura(); @endphp
                    @if($grupo)
                        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">
                                {{ $grupo['nombre'] }}
                            </p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                {{ __(':count cajas con fondos individuales', ['count' => $grupo['cantidad_cajas']]) }}
                            </p>
                        </div>

                        {{-- Input para cada caja --}}
                        <div class="space-y-4">
                            @foreach($cajasAAbrir as $cajaId)
                                @php $cajaInfo = $this->getCajaParaApertura($cajaId); @endphp
                                @if($cajaInfo)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ $cajaInfo['nombre'] }}
                                            @if($cajaInfo['modo_carga'] === 'monto_fijo')
                                                <span class="text-xs text-gray-500">{{ __('(Monto fijo)') }}</span>
                                            @elseif($cajaInfo['modo_carga'] === 'ultimo_cierre')
                                                <span class="text-xs text-gray-500">{{ __('(Ultimo cierre)') }}</span>
                                            @endif
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                            <input type="number"
                                                   wire:model="fondosIniciales.{{ $cajaId }}"
                                                   step="0.01"
                                                   min="0"
                                                   class="pl-8 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @else
                    {{-- Apertura individual --}}
                    @php $cajaInfo = $this->getCajaParaApertura($cajaAperturaId); @endphp
                    @if($cajaInfo)
                        <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <p class="text-sm text-green-700 dark:text-green-300 font-medium">
                                {{ $cajaInfo['nombre'] }}
                            </p>
                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                {{ __('Caja #:numero', ['numero' => $cajaInfo['numero']]) }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Fondo Inicial') }}
                                @if($cajaInfo['modo_carga'] === 'monto_fijo')
                                    <span class="text-xs text-gray-500">{{ __('(Monto fijo configurado)') }}</span>
                                @elseif($cajaInfo['modo_carga'] === 'ultimo_cierre')
                                    <span class="text-xs text-gray-500">{{ __('(Segun ultimo cierre)') }}</span>
                                @endif
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                <input type="number"
                                       wire:model="fondosIniciales.{{ $cajaAperturaId }}"
                                       step="0.01"
                                       min="0"
                                       class="pl-8 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="0.00">
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Ingresa el monto con el que inicias el turno') }}
                            </p>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button"
                        wire:click="procesarApertura"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm disabled:opacity-50">
                    <span wire:loading.remove wire:target="procesarApertura">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ __('Abrir Turno') }}
                    </span>
                    <span wire:loading wire:target="procesarApertura">
                        <svg class="animate-spin h-4 w-4 mr-2 inline" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Procesando...') }}
                    </span>
                </button>
                <button type="button"
                        wire:click="cancelarApertura"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                    {{ __('Cancelar') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif
