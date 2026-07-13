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

    {{-- Aviso: condiciones de IVA mixtas entre CUITs activos (D21) --}}
    @if($this->cuitsCondicionesMixtas)
        <div class="mb-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg px-4 py-3">
            <p class="text-xs text-yellow-800 dark:text-yellow-200 flex items-start gap-1.5">
                <svg class="w-4 h-4 flex-shrink-0 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ __('Hay CUITs activos con condiciones de IVA distintas: el precio sugerido y el margen de los artículos se calculan con el CUIT principal de cada sucursal, que puede no coincidir con el CUIT del punto de venta que factura. Verificá qué CUIT usa cada caja/punto de venta.') }}</span>
            </p>
        </div>
    @endif

    {{-- Lista de CUITs --}}
    @if($this->cuits->count() > 0)
        <div class="space-y-4">
            @foreach($this->cuits as $cuit)
                @php($domPrincipal = $cuit->domicilios->firstWhere('es_principal', true) ?? $cuit->domicilios->first())
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                    <div>
                        {{-- Encabezado: razón social + CUIT + estado --}}
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $cuit->razon_social }}</h4>
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $cuit->cuit_formateado }}</span>
                                    @if($cuit->activo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">{{ __('Activo') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">{{ __('Inactivo') }}</span>
                                    @endif
                                    @if($cuit->tieneCertificados())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100" title="{{ __('Certificado') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            {{ __('Certificado') }}
                                        </span>
                                    @endif
                                </div>
                                @if($cuit->nombre_fantasia)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $cuit->nombre_fantasia }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded text-xs font-medium {{ $cuit->entorno_afip === 'produccion' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' }}">
                                {{ ucfirst($cuit->entorno_afip) }}
                            </span>
                        </div>

                        {{-- Datos compactos en una línea --}}
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                            <span><span class="text-gray-400 dark:text-gray-500">{{ __('Cond. IVA:') }}</span> <span class="text-gray-700 dark:text-gray-200 font-medium">{{ $cuit->condicionIva?->nombre ?? '—' }}</span></span>
                            <span><span class="text-gray-400 dark:text-gray-500">{{ __('IIBB:') }}</span> <span class="text-gray-700 dark:text-gray-200 font-medium">{{ $cuit->numero_iibb ?: '—' }}</span></span>
                            <span class="min-w-0">
                                <span class="text-gray-400 dark:text-gray-500">{{ __('Domicilio:') }}</span>
                                <span class="text-gray-700 dark:text-gray-200 font-medium">
                                    @if($domPrincipal){{ $domPrincipal->direccion ?: __('(sin dirección)') }}{{ $domPrincipal->localidad ? ', '.$domPrincipal->localidad->nombre : '' }} <span class="font-mono text-gray-400 dark:text-gray-500">({{ $domPrincipal->provincia }})</span>@else—@endif
                                </span>
                            </span>
                            <span>
                                <span class="text-gray-400 dark:text-gray-500">{{ __('Puntos de venta:') }}</span>
                                <span class="text-gray-700 dark:text-gray-200 font-medium">{{ $cuit->puntosVenta->count() }}</span>
                                @if($cuit->puntosVenta->count() > 0)
                                    <span class="text-gray-400 dark:text-gray-500 font-mono">({{ $cuit->puntosVenta->sortBy('numero')->take(4)->map(fn ($pv) => str_pad($pv->numero, 4, '0', STR_PAD_LEFT))->implode(', ') }}{{ $cuit->puntosVenta->count() > 4 ? '…' : '' }})</span>
                                @endif
                            </span>
                        </div>

                        {{-- Acciones: barra inferior homogénea y responsive (wrap en móvil) --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600 flex flex-wrap items-center gap-2">
                            <button
                                wire:click="editarCuit({{ $cuit->id }})"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Editar') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$dispatch('abrir-puntos-cuit', { cuitId: {{ $cuit->id }} })"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Puntos de venta') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$dispatch('abrir-impuestos-cuit', { cuitId: {{ $cuit->id }} })"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Impuestos') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$dispatch('abrir-domicilios-cuit', { cuitId: {{ $cuit->id }} })"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Domicilios') }}</span>
                            </button>
                            <button
                                wire:click="confirmarEliminarCuit({{ $cuit->id }})"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-md hover:bg-red-50 dark:bg-gray-700 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 transition-colors sm:ml-auto"
                            >
                                <svg class="w-4 h-4 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Eliminar') }}</span>
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
