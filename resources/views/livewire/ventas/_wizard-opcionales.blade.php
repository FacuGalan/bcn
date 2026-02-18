{{-- Wizard de Opcionales --}}
@if($mostrarWizardOpcionales && !empty($wizardGrupos))
    @php
        $grupoActual = $wizardGrupos[$wizardPasoActual] ?? null;
        $totalGrupos = count($wizardGrupos);
        $seleccionesGrupo = $wizardSelecciones[$grupoActual['grupo_id'] ?? 0] ?? [];
        $cantidadSeleccionada = ($grupoActual['tipo'] ?? '') === 'cuantitativo'
            ? array_sum($seleccionesGrupo)
            : count($seleccionesGrupo);
        $esCuantitativo = ($grupoActual['tipo'] ?? '') === 'cuantitativo';
        $opcionesGrupo = $grupoActual['opciones'] ?? [];
    @endphp
    <div
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="wizard-opcionales"
        role="dialog"
        aria-modal="true"
        wire:key="wizard-paso-{{ $wizardPasoActual }}"
        x-data="{
            focusIndex: 0,
            searchQuery: '',
            totalOpciones: {{ count($opcionesGrupo) }},
            nombres: @js(collect($opcionesGrupo)->pluck('nombre')->map(fn($n) => mb_strtolower($n))->values()->toArray()),
            agotados: @js(collect($opcionesGrupo)->map(fn($o) => !($o['disponible'] ?? true))->values()->toArray()),
            isVisible(idx) {
                if (!this.searchQuery) return true;
                return (this.nombres[idx] || '').includes(this.searchQuery.toLowerCase());
            },
            visibleIndexes() {
                let arr = [];
                for (let i = 0; i < this.totalOpciones; i++) {
                    if (this.isVisible(i)) arr.push(i);
                }
                return arr;
            },
            availableIndexes() {
                return this.visibleIndexes().filter(i => !this.agotados[i]);
            },
            nextFocus() {
                const avail = this.availableIndexes();
                const cur = avail.indexOf(this.focusIndex);
                if (cur < avail.length - 1) this.focusIndex = avail[cur + 1];
                else if (cur === -1 && avail.length > 0) this.focusIndex = avail[0];
                this.scrollToFocused();
            },
            prevFocus() {
                const avail = this.availableIndexes();
                const cur = avail.indexOf(this.focusIndex);
                if (cur > 0) this.focusIndex = avail[cur - 1];
                this.scrollToFocused();
            },
            init() {
                const avail = this.availableIndexes();
                this.focusIndex = avail.length > 0 ? avail[0] : 0;
                this.$nextTick(() => this.$el.focus());
            },
            scrollToFocused() {
                this.$nextTick(() => {
                    const el = this.$el.querySelector('[data-oidx=\'' + this.focusIndex + '\']');
                    if (el) el.scrollIntoView({ block: 'nearest' });
                });
            },
            handleKey(e) {
                const key = e.key;
                if (key === 'ArrowDown') { e.preventDefault(); this.nextFocus(); return; }
                if (key === 'ArrowUp') { e.preventDefault(); this.prevFocus(); return; }
                if (key === 'Enter') { e.preventDefault(); $wire.confirmarPasoWizard(e.ctrlKey); return; }
                if (key === 'Tab') {
                    e.preventDefault();
                    e.shiftKey ? $wire.anteriorPasoWizard() : $wire.confirmarPasoWizard(false);
                    return;
                }
                if (key === 's' && e.ctrlKey) {
                    e.preventDefault();
                    $wire.saltearWizardOpcionales();
                    return;
                }
                if (key === 'Escape') {
                    e.preventDefault();
                    $wire.cerrarWizardOpcionales();
                    return;
                }
                if (key === 'Backspace') { this.searchQuery = this.searchQuery.slice(0, -1); this.focusIndex = this.availableIndexes()[0] ?? 0; return; }
                // +/- para seleccionar/quitar opción enfocada
                if (key === '+' || key === '=' || key === '-') {
                    e.preventDefault();
                    const el = this.$el.querySelector('[data-oidx=\'' + this.focusIndex + '\']');
                    if (!el) return;
                    const opId = parseInt(el.dataset.opid);
                    if (key === '-') {
                        {{ $esCuantitativo ? '$wire.cambiarCantidadOpcion(opId, -1)' : '$wire.toggleOpcion(opId)' }};
                    } else {
                        {{ $esCuantitativo ? '$wire.cambiarCantidadOpcion(opId, 1)' : '$wire.toggleOpcion(opId)' }};
                    }
                    return;
                }
                // Cualquier carácter imprimible (incluido espacio) → búsqueda
                if (key.length === 1 && /[a-zA-Z0-9\u00e1\u00e9\u00ed\u00f3\u00fa\u00f1\u00fc\s]/.test(key)) {
                    this.searchQuery += key;
                    this.focusIndex = this.availableIndexes()[0] ?? 0;
                    return;
                }
            }
        }"
        @keydown="handleKey($event)"
        tabindex="-1"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarWizardOpcionales"></div>

            {{-- Centrado --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            {{-- Modal --}}
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                {{-- Header --}}
                <div class="bg-bcn-primary px-5 py-4 flex items-center gap-4">
                    <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-white/20">
                        <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-xl font-semibold text-white truncate">
                            {{ $wizardArticuloData['nombre'] ?? '' }}
                        </h3>
                        <p class="text-sm text-white/80">
                            $@precio($wizardArticuloData['precio'] ?? 0)
                            @if($wizardEditandoIndex !== null)
                                <span class="ml-1 opacity-75">— {{ __('Editando') }}</span>
                            @endif
                        </p>
                    </div>
                    <button wire:click="cerrarWizardOpcionales" class="text-white/80 hover:text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Subheader: grupo actual + indicadores --}}
                <div class="px-5 py-3 bg-orange-50 dark:bg-gray-700 border-b border-orange-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-semibold text-gray-900 dark:text-white">{{ $grupoActual['nombre'] }}</span>
                            <span class="text-sm text-orange-600 dark:text-orange-400 font-medium">
                                {{ $wizardPasoActual + 1 }}/{{ $totalGrupos }}
                            </span>
                            @if($grupoActual['obligatorio'])
                                <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 text-xs rounded-full font-medium">{{ __('Obligatorio') }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            @if($grupoActual['min_seleccion'] || $grupoActual['max_seleccion'])
                                @if($grupoActual['min_seleccion']){{ __('Mín') }}: {{ $grupoActual['min_seleccion'] }}@endif
                                @if($grupoActual['min_seleccion'] && $grupoActual['max_seleccion']) · @endif
                                @if($grupoActual['max_seleccion']){{ __('Máx') }}: {{ $grupoActual['max_seleccion'] }}@endif
                                —
                            @endif
                            <span class="font-semibold {{ $cantidadSeleccionada > 0 ? 'text-bcn-primary' : '' }}">
                                {{ $cantidadSeleccionada }} {{ __('selec.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Indicador de búsqueda --}}
                    <div x-show="searchQuery" x-cloak class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-600 rounded-md px-3 py-1.5 border border-orange-200 dark:border-gray-500">
                        <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span x-text="searchQuery" class="italic flex-1"></span>
                        <button @click="searchQuery = ''; focusIndex = 0;" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-lg leading-none">&times;</button>
                    </div>

                    {{-- Dots de paso --}}
                    @if($totalGrupos > 1)
                        <div class="flex items-center justify-center gap-2 mt-2.5">
                            @for($i = 0; $i < $totalGrupos; $i++)
                                <div class="w-2.5 h-2.5 rounded-full transition-colors {{ $i === $wizardPasoActual ? 'bg-bcn-primary' : ($i < $wizardPasoActual ? 'bg-orange-300 dark:bg-orange-600' : 'bg-gray-300 dark:bg-gray-500') }}"></div>
                            @endfor
                        </div>
                    @endif
                </div>

                {{-- Lista de opciones --}}
                <div class="max-h-[55vh] overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($opcionesGrupo as $opIdx => $opcion)
                        @php $agotado = !($opcion['disponible'] ?? true); @endphp
                        <div
                            data-oidx="{{ $opIdx }}"
                            data-opid="{{ $opcion['opcional_id'] }}"
                            x-show="isVisible({{ $opIdx }})"
                            :class="{
                                'bg-orange-50 dark:bg-orange-900/20 ring-2 ring-inset ring-bcn-primary/40': focusIndex === {{ $opIdx }} && !{{ $agotado ? 'true' : 'false' }},
                            }"
                            class="px-5 py-3.5 transition-colors {{ $agotado ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-orange-50/50 dark:hover:bg-gray-700/50' }}"
                            @if(!$agotado)
                                @click="
                                    focusIndex = {{ $opIdx }};
                                    @if($esCuantitativo)
                                        $wire.cambiarCantidadOpcion({{ $opcion['opcional_id'] }}, 1)
                                    @else
                                        $wire.toggleOpcion({{ $opcion['opcional_id'] }})
                                    @endif
                                "
                            @endif
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if(!$esCuantitativo)
                                        {{-- Checkbox visual --}}
                                        @php $gid = $grupoActual['grupo_id']; @endphp
                                        <div
                                            @if($agotado)
                                                class="w-6 h-6 rounded border-2 border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex-shrink-0"
                                            @else
                                                :class="$wire.wizardSelecciones[{{ $gid }}]?.[{{ $opcion['opcional_id'] }}]
                                                    ? 'bg-bcn-primary border-bcn-primary'
                                                    : 'border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700'"
                                                class="w-6 h-6 rounded border-2 flex items-center justify-center transition-colors flex-shrink-0"
                                            @endif
                                        >
                                            @if(!$agotado)
                                                <svg
                                                    x-show="$wire.wizardSelecciones[{{ $gid }}]?.[{{ $opcion['opcional_id'] }}]"
                                                    x-cloak
                                                    class="w-4 h-4 text-white"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            @endif
                                        </div>
                                    @endif

                                    <div>
                                        <span class="text-base {{ $agotado ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-900 dark:text-white' }}">{{ $opcion['nombre'] }}</span>
                                        @if($agotado)
                                            <span class="ml-2 px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 text-xs rounded-full font-medium">{{ __('Agotado') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    {{-- Precio extra --}}
                                    @if($opcion['precio_extra'] > 0)
                                        <span class="text-sm {{ $agotado ? 'text-gray-400 dark:text-gray-500' : 'text-green-600 dark:text-green-400' }} font-semibold whitespace-nowrap">
                                            +$@precio($opcion['precio_extra'])
                                        </span>
                                    @endif

                                    @if($esCuantitativo)
                                        @php $gid = $grupoActual['grupo_id']; @endphp
                                        @if($agotado)
                                            <div class="flex items-center gap-2">
                                                <span class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-300 dark:text-gray-600 flex items-center justify-center text-base font-bold">-</span>
                                                <span class="w-10 text-center text-lg font-bold tabular-nums text-gray-300 dark:text-gray-600">0</span>
                                                <span class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-300 dark:text-gray-600 flex items-center justify-center text-base font-bold">+</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <button
                                                    @click.stop="$wire.cambiarCantidadOpcion({{ $opcion['opcional_id'] }}, -1)"
                                                    class="w-9 h-9 rounded-lg bg-orange-100 dark:bg-gray-600 text-orange-700 dark:text-orange-300 flex items-center justify-center hover:bg-orange-200 dark:hover:bg-gray-500 text-base font-bold transition-colors"
                                                >-</button>
                                                <span
                                                    class="w-10 text-center text-lg font-bold tabular-nums"
                                                    :class="($wire.wizardSelecciones[{{ $gid }}]?.[{{ $opcion['opcional_id'] }}] || 0) > 0
                                                        ? 'text-bcn-primary'
                                                        : 'text-gray-400 dark:text-gray-500'"
                                                    x-text="$wire.wizardSelecciones[{{ $gid }}]?.[{{ $opcion['opcional_id'] }}] || 0"
                                                ></span>
                                                <button
                                                    @click.stop="$wire.cambiarCantidadOpcion({{ $opcion['opcional_id'] }}, 1)"
                                                    class="w-9 h-9 rounded-lg bg-orange-100 dark:bg-gray-600 text-orange-700 dark:text-orange-300 flex items-center justify-center hover:bg-orange-200 dark:hover:bg-gray-500 text-base font-bold transition-colors"
                                                >+</button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <p class="text-base text-gray-400 dark:text-gray-500">{{ __('No hay opciones disponibles') }}</p>
                        </div>
                    @endforelse

                    {{-- Sin resultados de búsqueda --}}
                    <div x-show="searchQuery && visibleIndexes().length === 0" x-cloak class="px-5 py-10 text-center">
                        <p class="text-base text-gray-400 dark:text-gray-500">{{ __('No se encontraron opciones') }}</p>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                    <div>
                        @if($wizardPasoActual > 0)
                            <button
                                wire:click="anteriorPasoWizard"
                                type="button"
                                class="inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-5 py-2.5 bg-white dark:bg-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary"
                            >
                                {{ __('Anterior') }}
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            wire:click="saltearWizardOpcionales"
                            type="button"
                            class="inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-5 py-2.5 bg-white dark:bg-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary"
                        >
                            {{ __('Saltear') }}
                        </button>

                        <button
                            wire:click="confirmarPasoWizard"
                            type="button"
                            class="inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-bcn-primary text-sm font-semibold text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary"
                        >
                            {{ $wizardPasoActual < $totalGrupos - 1 ? __('Siguiente') : __('Confirmar') }}
                        </button>
                    </div>
                </div>

                {{-- Atajos de teclado --}}
                <div class="px-5 py-2 bg-gray-100 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-600">
                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                        ↑↓ {{ __('navegar') }} · +/- {{ __('selec.') }} · Enter {{ __('Confirmar') }} · Ctrl+Enter {{ __('forzar') }} · Esc {{ __('Cancelar') }} · Ctrl+S {{ __('saltear todo') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
