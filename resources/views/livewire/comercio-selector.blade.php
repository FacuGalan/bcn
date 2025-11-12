<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
    <div class="w-full sm:max-w-2xl mt-6 px-6 py-8 bg-white shadow-md overflow-hidden sm:rounded-lg">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Selecciona un Comercio</h2>
            <p class="mt-2 text-sm text-gray-600">Elige el comercio con el que deseas trabajar</p>
        </div>

        <!-- Messages -->
        @if (session('error'))
            <div class="mb-4 p-4 text-sm text-red-800 bg-red-50 rounded-lg border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 p-4 text-sm text-green-800 bg-green-50 rounded-lg border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        <!-- Comercios Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse ($comercios as $comercio)
                <button
                    wire:click="selectComercio({{ $comercio->id }})"
                    class="p-6 text-left border-2 rounded-lg transition-all hover:border-indigo-500 hover:shadow-lg {{ $comercioActual && $comercioActual->id === $comercio->id ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 bg-white' }}"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $comercio->nombre }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $comercio->mail }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500">
                                ID: {{ $comercio->getFormattedId() }}
                            </p>
                        </div>

                        @if ($comercioActual && $comercioActual->id === $comercio->id)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                Activo
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center text-sm text-indigo-600 font-medium">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Seleccionar comercio
                    </div>
                </button>
            @empty
                <div class="col-span-2 text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No tienes comercios asignados</h3>
                    <p class="mt-1 text-sm text-gray-500">Contacta con un administrador para obtener acceso.</p>
                </div>
            @endforelse
        </div>

        <!-- Logout Button -->
        <div class="mt-6 pt-6 border-t border-gray-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-center text-sm text-gray-600 hover:text-gray-900 underline">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</div>
