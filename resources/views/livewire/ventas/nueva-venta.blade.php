<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Nueva Venta</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Seleccione una caja para comenzar a registrar la venta
                </p>
            </div>

        {{-- Mensaje de Estado --}}
        @if($mensaje)
            <div class="mb-6">
                <div class="rounded-lg {{ $cajaSeleccionada ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }} p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            @if($cajaSeleccionada)
                                <x-heroicon-o-check-circle class="h-5 w-5 text-green-600" />
                            @else
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-600" />
                            @endif
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium {{ $cajaSeleccionada ? 'text-green-800' : 'text-yellow-800' }}">
                                {{ $mensaje }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Contenido Principal --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">

            {{-- Card de Información de Caja --}}
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Información de Caja</h2>

                @if($cajaSeleccionada)
                    @php
                        $caja = \App\Models\Caja::find($cajaSeleccionada);
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <dt class="text-sm font-medium text-gray-500">Caja</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $caja->nombre }}</dd>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $caja->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $caja->activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </dd>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <dt class="text-sm font-medium text-gray-500">Saldo Actual</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">
                                ${{ number_format($caja->saldo_actual ?? 0, 2) }}
                            </dd>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-600">
                            No hay caja seleccionada. Use el selector de caja en la parte superior derecha.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Área de Acciones --}}
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-700">¿Listo para comenzar?</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Haga clic en el botón para iniciar el proceso de venta
                        </p>
                    </div>

                    <button
                        wire:click="iniciarVenta"
                        type="button"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary transition-colors duration-200 {{ !$cajaSeleccionada ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ !$cajaSeleccionada ? 'disabled' : '' }}
                    >
                        <x-heroicon-o-shopping-cart class="h-5 w-5 mr-2" />
                        Iniciar Venta
                    </button>
                </div>
            </div>

            {{-- Información Adicional --}}
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-information-circle class="h-5 w-5 text-gray-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-xs font-medium text-gray-700">Nota</h3>
                        <div class="mt-1 text-xs text-gray-600">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Asegúrese de tener una caja seleccionada antes de iniciar la venta</li>
                                <li>La caja debe estar activa y tener saldo disponible</li>
                                <li>Todas las ventas quedarán registradas en la caja seleccionada</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cajas Disponibles (solo si no hay caja seleccionada) --}}
        @if(!$cajaSeleccionada && $cajas->isNotEmpty())
            <div class="mt-6 bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Cajas Disponibles</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($cajas as $caja)
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-bcn-primary hover:bg-bcn-light transition-colors duration-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900">{{ $caja->nombre }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">Saldo: ${{ number_format($caja->saldo_actual ?? 0, 2) }}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $caja->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $caja->activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    👉 Use el selector flotante de caja (botón en la esquina inferior derecha) para seleccionar una de estas cajas
                </p>
            </div>
        @endif

        </div>
    </div>
</div>
