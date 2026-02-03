{{-- Tab CUITs --}}
<div class="p-6">
    {{-- Header con botón agregar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('CUITs Registrados') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Administra los CUITs para facturación electrónica') }}
            </p>
        </div>
        <button
            wire:click="crearCuit"
            class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('Agregar CUIT') }}
        </button>
    </div>

    {{-- Lista de CUITs --}}
    @if($this->cuits->count() > 0)
        <div class="space-y-4">
            @foreach($this->cuits as $cuit)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        {{-- Info del CUIT --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                    {{ $cuit->razon_social }}
                                </h4>
                                @if($cuit->activo)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                        {{ __('Activo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                        {{ __('Inactivo') }}
                                    </span>
                                @endif
                                @if($cuit->tieneCertificados())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        {{ __('Certificado') }}
                                    </span>
                                @endif
                            </div>

                            @if($cuit->nombre_fantasia)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $cuit->nombre_fantasia }}
                                </p>
                            @endif

                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('CUIT:') }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white font-mono">{{ $cuit->cuit_formateado }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Cond. IVA:') }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white">{{ $cuit->condicionIva?->nombre ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('IIBB:') }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white">{{ $cuit->numero_iibb ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Dirección:') }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white">{{ $cuit->direccion ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Localidad:') }}</span>
                                    <span class="ml-1 text-gray-900 dark:text-white">{{ $cuit->localidad?->nombre ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Entorno:') }}</span>
                                    <span class="ml-1 px-2 py-0.5 rounded text-xs font-medium {{ $cuit->entorno_afip === 'produccion' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' }}">
                                        {{ ucfirst($cuit->entorno_afip) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Puntos de Venta --}}
                            @if($cuit->puntosVenta->count() > 0)
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ __('Puntos de Venta') }} ({{ $cuit->puntosVenta->count() }})
                                    </h5>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($cuit->puntosVenta as $pv)
                                            <span class="inline-flex items-center px-3 py-1 rounded-md text-sm {{ $pv->activo ? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500' }}">
                                                <span class="font-mono mr-1">{{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }}</span>
                                                @if($pv->nombre)
                                                    <span class="text-gray-500 dark:text-gray-400">- {{ $pv->nombre }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        {{ __('Sin puntos de venta configurados') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Acciones --}}
                        <div class="flex items-center gap-2 lg:flex-col lg:items-end">
                            <button
                                wire:click="editarCuit({{ $cuit->id }})"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                {{ __('Editar') }}
                            </button>
                            <button
                                wire:click="confirmarEliminarCuit({{ $cuit->id }})"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-md hover:bg-red-50 dark:bg-gray-700 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                {{ __('Eliminar') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Estado vacío --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('No hay CUITs registrados') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Comienza agregando un CUIT para poder emitir facturas electrónicas.') }}
            </p>
            <div class="mt-6">
                <button
                    wire:click="crearCuit"
                    class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('Agregar CUIT') }}
                </button>
            </div>
        </div>
    @endif
</div>
