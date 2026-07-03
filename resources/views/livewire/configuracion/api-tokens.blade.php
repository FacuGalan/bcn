<div class="px-3 sm:px-4 lg:px-6 py-4 space-y-3 max-w-4xl">
    {{-- ==================== HEADER ==================== --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold text-bcn-secondary dark:text-white">{{ __('Tokens de API') }}</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Acceso de integraciones externas a la API v1 (pedidos delivery, catálogo, configuración). El token se muestra una sola vez.') }}
            </p>
        </div>
        @if(auth()->user()?->hasPermissionTo('func.api.tokens'))
            <button type="button" wire:click="abrirCrear"
                class="h-9 px-3 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Nuevo token') }}
            </button>
        @endif
    </div>

    {{-- ==================== TABLA ==================== --}}
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-bcn-light dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Permisos') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Último uso') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tokens as $token)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $token->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Creado') }} {{ $token->created_at->format('d/m/Y') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($token->abilities ?? [] as $ability)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $ability }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ $token->last_used_at?->diffForHumans() ?? __('Nunca') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if(auth()->user()?->hasPermissionTo('func.api.tokens'))
                                    <button wire:click="abrirRevocar({{ $token->id }})"
                                        class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                        {{ __('Revocar') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Sin tokens. Creá uno para conectar una integración externa.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== MODAL: CREAR ==================== --}}
    @if($showCrearModal)
        <x-bcn-modal :title="__('Nuevo token de API')" color="bg-bcn-primary" maxWidth="lg" onClose="cerrarCrear">
            <x-slot:body>
                @if($tokenPlano)
                    <div class="space-y-3">
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg p-3">
                            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200 mb-2">
                                {{ __('Copiá el token AHORA: no se vuelve a mostrar.') }}
                            </p>
                            <div class="flex items-center gap-2" x-data="{ copiado: false }">
                                <code class="flex-1 text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded px-2 py-1.5 break-all select-all">{{ $tokenPlano }}</code>
                                <button type="button"
                                    @click="navigator.clipboard.writeText(@js($tokenPlano)); copiado = true; setTimeout(() => copiado = false, 2000)"
                                    class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span x-show="!copiado">{{ __('Copiar') }}</span>
                                    <span x-show="copiado" x-cloak class="text-emerald-600">✓</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Usalo como Bearer token. La sucursal se indica con el header X-Sucursal-Id.') }}
                        </p>
                    </div>
                @else
                    <div class="space-y-3">
                        <div>
                            <label for="token-nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} <span class="text-red-500">*</span></label>
                            <input id="token-nombre" type="text" wire:model="nombreToken" maxlength="100"
                                placeholder="{{ __('Ej: App repartidores, PedidosYa...') }}"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            @error('nombreToken') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Permisos del token') }}</label>
                            <div class="space-y-1.5">
                                @foreach($abilitiesCatalogo as $ability => $label)
                                    <label class="flex items-center gap-2 cursor-pointer border border-gray-200 dark:border-gray-700 rounded-md px-2.5 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <input type="checkbox" wire:model="abilitiesSeleccionadas.{{ $ability }}"
                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __($label) }}</span>
                                        <code class="ml-auto text-[10px] text-gray-400">{{ $ability }}</code>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarCrear"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ $tokenPlano ? __('Listo, lo copié') : __('Cancelar') }}
                </button>
                @unless($tokenPlano)
                    <button type="button" wire:click="crearToken"
                        class="px-4 py-2 bg-bcn-primary rounded-md text-sm font-semibold text-white hover:bg-opacity-90">
                        {{ __('Crear token') }}
                    </button>
                @endunless
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: REVOCAR ==================== --}}
    @if($showRevocarModal)
        <x-bcn-modal :title="__('¿Revocar token?')" color="bg-red-600" maxWidth="sm" onClose="cerrarRevocar">
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('La integración ":nombre" dejará de poder acceder a la API inmediatamente.', ['nombre' => $nombreTokenARevocar]) }}
                </p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarRevocar"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="revocarToken"
                    class="px-4 py-2 bg-red-600 rounded-md text-sm font-semibold text-white hover:bg-red-700">
                    {{ __('Revocar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
