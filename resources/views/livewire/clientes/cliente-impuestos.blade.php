<div>
    @if($mostrarModal)
        <x-bcn-modal
            :title="__('Perfil fiscal de :cliente', ['cliente' => $clienteNombre])"
            color="bg-bcn-primary"
            maxWidth="5xl"
            onClose="cerrar"
            submit="guardar"
        >
            <x-slot:body>
                {{-- Cliente sobre el que se está trabajando --}}
                <div class="mb-4 px-3 py-2 rounded-md bg-bcn-primary/10 dark:bg-bcn-primary/20">
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">{{ $clienteNombre }}</span>
                        @if($clienteCuit)
                            <span class="text-gray-500 dark:text-gray-400">· {{ __('CUIT') }} <span class="font-mono">{{ $clienteCuit }}</span></span>
                        @endif
                    </p>
                </div>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('La percepción de IVA es automática y no se configura acá. Este perfil define las percepciones provinciales (Ingresos Brutos) que se le aplican al cliente: exención o alícuota por sujeto (manual o de padrón).') }}
                    <span class="block mt-1">{{ __('Agregá la jurisdicción del cliente desde el buscador y luego cargá su alícuota (o marcala como exenta).') }}</span>
                </p>

                {{-- Alta rápida: buscar en el catálogo --}}
                <div class="mb-5" x-data="{ open: false }" @click.outside="open = false">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('Agregar percepción') }}
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="buscarImpuesto"
                            @focus="open = true"
                            data-enter-default
                            placeholder="{{ __('Buscar por nombre o código (ej: IIBB, percepción)') }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            autocomplete="off"
                        >
                        {{-- El listado solo se despliega cuando se tipea algo, para no tapar la vista --}}
                        <div
                            x-show="open && ($wire.buscarImpuesto || '').length > 0"
                            x-transition
                            class="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto rounded-md bg-white dark:bg-gray-700 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
                        >
                            @forelse($impuestosDisponibles as $imp)
                                <button
                                    type="button"
                                    wire:click="agregarImpuesto({{ $imp->id }})"
                                    @click="open = false"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-bcn-primary/10 dark:hover:bg-bcn-primary/20 flex items-center justify-between gap-2"
                                >
                                    <span class="text-gray-900 dark:text-white">{{ $imp->nombre }}</span>
                                    @if($imp->jurisdiccion)
                                        <span class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300 font-mono">{{ $imp->jurisdiccion }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $buscarImpuesto !== '' ? __('Sin resultados en el catálogo') : __('Escribí para buscar en el catálogo') }}
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Lista de percepciones configuradas --}}
                @if(count($filas) > 0)
                    <div class="space-y-3">
                        @foreach($filas as $i => $fila)
                            <div wire:key="fila-{{ $fila['id'] }}"
                                class="p-4 rounded-md border border-gray-200 dark:border-gray-600">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $fila['nombre'] }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $fila['codigo'] }}</span>
                                            @if($fila['jurisdiccion'])
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300 font-mono">{{ $fila['jurisdiccion'] }}</span>
                                            @endif
                                            @if($fila['origen_alicuota'] === 'padron')
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">{{ __('Padrón') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" wire:click="quitarImpuesto({{ $fila['id'] }})"
                                        class="shrink-0 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        title="{{ __('Quitar') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    {{-- Alícuota --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Alícuota (%)') }}</label>
                                        <input type="number" step="0.0001" min="0" max="100" wire:model="filas.{{ $i }}.alicuota" @disabled($fila['exento'])
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white disabled:opacity-60">
                                        @error('filas.'.$i.'.alicuota') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                    {{-- Base mínima --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Base mínima') }}</label>
                                        <input type="number" step="0.01" min="0" wire:model="filas.{{ $i }}.alicuota_minimo_base" @disabled($fila['exento'])
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white disabled:opacity-60">
                                    </div>
                                    {{-- N° padrón --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('N° padrón') }}</label>
                                        <input type="text" wire:model="filas.{{ $i }}.numero_padron" maxlength="30"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>
                                    {{-- Vigencia desde --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Vigente desde') }}</label>
                                        <input type="date" wire:model="filas.{{ $i }}.vigente_desde"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>
                                    {{-- Vigencia hasta --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Vigente hasta') }}</label>
                                        <input type="date" wire:model="filas.{{ $i }}.vigente_hasta"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        @error('filas.'.$i.'.vigente_hasta') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                {{-- Flag exento --}}
                                <div class="mt-3 flex flex-wrap items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="filas.{{ $i }}.exento"
                                            class="rounded border-gray-300 text-bcn-primary focus:ring-bcn-primary dark:bg-gray-700 dark:border-gray-600">
                                        {{ __('Exento (no se le percibe este impuesto)') }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('La edición manual tiene prioridad: la importación de padrón no pisa lo cargado a mano.') }}
                    </p>
                @else
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Este cliente no tiene percepciones configuradas.') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            {{ __('Buscá la jurisdicción del cliente arriba (ej: IIBB Buenos Aires) y agregala; después vas a poder cargar la alícuota o marcarla como exenta.') }}
                        </p>
                    </div>
                @endif
            </x-slot:body>

            <x-slot:footer>
                <button type="button" wire:click="cerrar"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                    {{ __('Cerrar') }}
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors">
                    {{ __('Guardar cambios') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
