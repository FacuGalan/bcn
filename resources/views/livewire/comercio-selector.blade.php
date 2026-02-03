<div>
    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white text-center">
            @if ($isSystemAdmin)
                {{ __('Administrador de Sistema') }}
            @else
                {{ __('Selecciona un Comercio') }}
            @endif
        </h2>
    </div>

    <!-- Messages -->
    @if (session('error'))
        <div class="mb-4 p-3 text-sm text-red-800 bg-red-50 rounded-lg border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    @if ($isSystemAdmin)
        <!-- Buscador para System Admin -->
        <div class="mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    :placeholder="__('Buscar por nombre, email o ID...')"
                    class="w-full pl-10 pr-4 py-3 text-base rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-bcn-primary focus:ring-bcn-primary"
                    autofocus
                />
            </div>
        </div>

        <!-- Resultados de búsqueda -->
        @if (count($searchResults) > 0)
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @foreach ($searchResults as $comercio)
                    <button
                        wire:click="selectComercio({{ $comercio['id'] }})"
                        class="w-full p-3 text-left border rounded-lg transition-all hover:border-bcn-primary hover:bg-amber-50 dark:hover:bg-gray-700 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                        #{{ str_pad($comercio['id'], 5, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ $comercio['nombre'] }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $comercio['email'] }}
                                </p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </button>
                @endforeach
            </div>
        @elseif (strlen($search) >= 1)
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p class="text-sm">{{ __('No se encontraron comercios') }}</p>
            </div>
        @endif
    @else
        <!-- Comercios para usuarios normales -->
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @forelse ($comercios as $comercio)
                <button
                    wire:click="selectComercio({{ $comercio->id }})"
                    class="w-full p-3 text-left border rounded-lg transition-all hover:border-bcn-primary hover:bg-amber-50 dark:hover:bg-gray-700 {{ $comercioActual && $comercioActual->id === $comercio->id ? 'border-bcn-primary bg-amber-50 dark:bg-gray-700' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800' }}"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $comercio->nombre }}
                                </span>
                                @if ($comercioActual && $comercioActual->id === $comercio->id)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-bcn-primary text-bcn-secondary">
                                        {{ __('Activo') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $comercio->email }}
                            </p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </button>
            @empty
                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                    <p class="text-sm">{{ __('No tienes comercios asignados') }}</p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Logout Button -->
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                {{ __('Cerrar Sesión') }}
            </button>
        </form>
    </div>
</div>
