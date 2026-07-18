<div class="px-3 sm:px-4 lg:px-6 py-4 space-y-4">
    {{-- ==================== HEADER ==================== --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold text-bcn-secondary dark:text-white">{{ __('Delivery / Take Away') }}</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Georreferenciación, costos de envío, zonas, horarios, promesa de entrega y tienda online de la sucursal.') }}</p>
        </div>
        <button type="button" wire:click="guardarConfig"
            class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ __('Guardar') }}
        </button>
    </div>

    {{-- ==================== ZONA DELIVERY/PANEL (full-width, 2 col en xl) ==================== --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">

        {{-- ==================== GENERAL ==================== --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('General') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="usaDelivery" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Habilitar delivery en esta sucursal') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Activa el panel de pedidos delivery/take-away.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="takeawayHabilitado" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Take-away habilitado') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Permite pedidos "para llevar" (retiro en el local).') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="exigirRepartidor" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Exigir repartidor para despachar') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Listo → En camino requiere repartidor asignado.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="usaEstadoListo" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Usar estado "Listo"') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Desactivado: la columna Listo se oculta y de "En preparación" se pasa directo al envío o retiro. Aun activado, se puede despachar sin pasar por Listo.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="convertirVentaAlEntregar" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Convertir en venta al entregar') }}
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Al pasar a Entregado el pedido se convierte en venta con todos sus movimientos y se emiten los comprobantes fiscales de las formas de pago marcadas como fiscales (requiere pagos completos y caja). Propia de delivery, no afecta mostrador.') }}</span>
                    </span>
                </label>
                <div>
                    <label for="cd-categoria-envio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría del renglón de envío') }}</label>
                    <select id="cd-categoria-envio" wire:model="conceptoCategoriaEnvioId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                        <option value="">{{ __('Sin categoría') }}</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Categoría contable del renglón "Costo de envío" en pedidos y ventas.') }}</p>
                </div>
                {{-- Numeración display PROPIA de delivery (rev9, separada de mostrador) --}}
                <div class="sm:col-span-2 border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="usaNumeracionDisplay" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ __('Numerar pedidos por turno (número de display)') }}
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('El panel muestra un número corto propio de delivery, independiente del de mostrador. Desactivado: se usa el número correlativo permanente.') }}</span>
                        </span>
                    </label>
                    @if($usaNumeracionDisplay)
                        <div class="flex flex-wrap items-end gap-3 pl-6">
                            <div>
                                <label for="cd-num-modo" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Reinicio del contador') }}</label>
                                <select id="cd-num-modo" wire:model.live="numeracionDisplayModo"
                                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option value="diario">{{ __('Automático (por horario)') }}</option>
                                    <option value="manual">{{ __('Manual (con botón)') }}</option>
                                </select>
                            </div>
                            @if($numeracionDisplayModo === 'diario')
                                <div>
                                    <label for="cd-num-horas" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Horas de reinicio (0-23)') }}</label>
                                    <input id="cd-num-horas" type="text" wire:model="numeracionDisplayHoras" placeholder="6, 18"
                                        class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                </div>
                            @else
                                <button type="button" wire:click="reiniciarNumeracionDisplay"
                                    wire:confirm="{{ __('¿Reiniciar la numeración de pedidos delivery a 0?') }}"
                                    class="px-3 py-1.5 border border-amber-300 dark:border-amber-600 rounded-md text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30">
                                    {{ __('Reiniciar numeración ahora') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="sm:col-span-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="cd-alerta-amarilla" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Alerta amarilla (min)') }}</label>
                        <input id="cd-alerta-amarilla" type="number" min="0" wire:model="alertaAmarillaMin"
                            class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                    <div>
                        <label for="cd-alerta-roja" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Alerta roja (min)') }}</label>
                        <input id="cd-alerta-roja" type="number" min="0" wire:model="alertaRojaMin"
                            class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 basis-full space-y-1">
                        <p class="font-medium text-gray-600 dark:text-gray-300">{{ __('Cómo funcionan las alertas de demora (0 = desactivada, compartidas con mostrador):') }}</p>
                        <p>• {{ __('Pedido SIN hora pactada ("lo antes posible"): los minutos se miden desde que se confirmó. Con 15/30, a los 15 minutos de vida el pedido se pinta amarillo y a los 30 rojo.') }}</p>
                        <p>• {{ __('Pedido CON hora pactada: el amarillo avisa esos minutos ANTES de la hora comprometida (ej: 15 = amarillo un cuarto de hora antes de la entrega) y el rojo aparece al vencer la hora pactada — en este caso el valor del rojo no se usa.') }}</p>
                        <p>• {{ __('El contador que aparece junto al número del pedido muestra siempre los minutos desde la confirmación, para dimensionar cuánto hace que está en curso.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== PROMESA DE ENTREGA (RF-15 core) ==================== --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Promesa de entrega') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="cd-promesa" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Modo') }}</label>
                    <select id="cd-promesa" wire:model.live="modoPromesa"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                        <option value="manual">{{ __('Manual (botones de demora)') }}</option>
                        <option value="automatica">{{ __('Automática por distancia') }}</option>
                        <option value="franjas">{{ __('Horarios fijos (franjas)') }}</option>
                    </select>
                </div>
                @if($modoPromesa === 'automatica')
                    <div>
                        <label for="cd-demora-base" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Demora base (min)') }}</label>
                        <input id="cd-demora-base" type="number" min="0" wire:model="demoraBaseMin"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                    <div>
                        <label for="cd-demora-km" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Minutos por km') }}</label>
                        <input id="cd-demora-km" type="number" min="0" step="0.5" wire:model="demoraMinPorKm"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                @elseif($modoPromesa === 'franjas')
                    <div class="sm:col-span-2 flex items-end pb-1.5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="aceptaLoAntesPosible"
                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ __('Aceptar "Lo antes posible"') }}</span>
                        </label>
                    </div>
                    <div class="sm:col-span-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Horarios de entrega') }}</label>
                            <button type="button" wire:click="agregarFranja" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar horario') }}</button>
                        </div>
                        @forelse($franjas as $i => $franja)
                            <div class="flex flex-wrap items-center gap-2 mb-1.5 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5">
                                <input type="time" wire:model="franjas.{{ $i }}.hora" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                                <div class="flex flex-wrap gap-1">
                                    @foreach($diasSemana as $dia => $label)
                                        <label class="inline-flex items-center px-1.5 py-0.5 border rounded cursor-pointer text-[10px] {{ ($franjas[$i]['dias'][$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400' }}">
                                            <input type="checkbox" wire:model.live="franjas.{{ $i }}.dias.{{ $dia }}" class="sr-only" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <label class="inline-flex items-center gap-1 cursor-pointer text-[11px] text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" wire:model.live="franjas.{{ $i }}.delivery"
                                        class="rounded border-gray-300 dark:border-gray-600 text-cyan-600 focus:ring-cyan-500 w-3.5 h-3.5" />
                                    {{ __('Delivery') }}
                                </label>
                                <label class="inline-flex items-center gap-1 cursor-pointer text-[11px] text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" wire:model.live="franjas.{{ $i }}.take_away"
                                        class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 w-3.5 h-3.5" />
                                    {{ __('Para llevar') }}
                                </label>
                                <button type="button" wire:click="quitarFranja({{ $i }})" class="text-red-500 hover:text-red-700 ml-auto" title="{{ __('Quitar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-[11px] text-orange-600 dark:text-orange-400">{{ __('Sin horarios cargados no se puede pactar hora de entrega: agregá al menos uno.') }}</p>
                        @endforelse
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            {{ __('Cada horario define qué días aplica y si sirve para delivery, para llevar o ambos. Se descuentan feriados y días no laborales.') }}
                        </p>
                    </div>
                @else
                    <div class="sm:col-span-2">
                        <label for="cd-botones" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Botones de demora (min, separados por coma)') }}</label>
                        <input id="cd-botones" type="text" wire:model="botonesDemora"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    </div>
                @endif
            </div>
        </div>

        {{-- ==================== ENVÍO, ALCANCE Y ZONAS (RF-06, sub-componente con Maps) ==================== --}}
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Costo de envío y zonas de entrega') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Georreferenciación, radio y costos por km, y zonas dibujadas en el mapa. Se abre a pedido para no cargar el mapa siempre.') }}</p>
                </div>
                <button type="button" wire:click="toggleEnvioZonas"
                    class="h-8 px-3 inline-flex items-center gap-1 rounded-md text-xs font-semibold {{ $showEnvioZonas ? 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' : 'bg-cyan-600 text-white hover:bg-cyan-700' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    {{ $showEnvioZonas ? __('Ocultar mapa') : __('Configurar envío y zonas') }}
                </button>
            </div>
        </div>

        @if($showEnvioZonas)
            <div class="xl:col-span-2">
                <livewire:pedidos.configuracion-delivery-envio />
            </div>
        @endif

        {{-- Pedidos externos y calendario (data del padre, config_delivery):
             viven DENTRO del apartado Tienda Online cuando está desplegado;
             acá quedan como fallback cuando no hay tienda o está apagada
             (aplican igual a franjas, panel y API). Un solo lugar a la vez. --}}
        @unless($tiendaExiste && $tiendaPublicada)
            <div wire:key="pedidos-externos-delivery">
                @include('livewire.pedidos.partials.config-pedidos-externos')
            </div>

            <div wire:key="calendario-delivery">
                @include('livewire.pedidos.partials.config-calendario')
            </div>
        @endunless
    </div>

    {{-- Guardar (repetido para no scrollear) --}}
    <div class="flex justify-end">
        <button type="button" wire:click="guardarConfig"
            class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
            {{ __('Guardar configuración') }}
        </button>
    </div>

    {{-- ==================== TIENDA ONLINE (RF-T11: switch maestro del padre) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <button type="button" wire:click="toggleTiendaOnline" role="switch" aria-checked="{{ $tiendaPublicada ? 'true' : 'false' }}"
                    @disabled(! $puedeConfigurarTienda)
                    title="{{ $tiendaPublicada ? __('Apagar la tienda online') : __('Prender la tienda online') }}"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed {{ $tiendaPublicada ? 'bg-bcn-primary' : 'bg-gray-300 dark:bg-gray-600' }}">
                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200 {{ $tiendaPublicada ? 'translate-x-[22px]' : 'translate-x-0.5' }}"></span>
                </button>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Tienda Online') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Tu tienda pública en internet: catálogo, carrito y pedidos delivery o take-away. Acá se configura todo lo que la tienda muestra y cómo entran sus pedidos.') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($tiendaExiste)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tiendaPublicadaPersistida ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $tiendaPublicadaPersistida ? __('Publicada') : __('No publicada') }}
                    </span>
                    @if($tiendaPublicada !== $tiendaPublicadaPersistida)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                            {{ $tiendaPublicada ? __('Se publica al guardar') : __('Se despublica al guardar') }}
                        </span>
                    @endif
                @else
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Prendé el switch para crear tu tienda') }}</span>
                @endif
            </div>
        </div>

        @if($tiendaExiste)
            <div @class(['space-y-4', 'hidden' => ! $tiendaPublicada])>
                {{-- Bloques del PADRE (se guardan con "Guardar configuración") --}}
                @if($tiendaPublicada)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
                        <div wire:key="pedidos-externos-tienda">
                            @include('livewire.pedidos.partials.config-pedidos-externos')
                        </div>
                        <div wire:key="calendario-tienda">
                            @include('livewire.pedidos.partials.config-calendario')
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('El calendario y los pedidos externos se guardan con el botón "Guardar configuración" y aplican también al panel y a la API, tengas o no la tienda publicada.') }}</p>
                @endif

                {{-- Registro config.tiendas (guardado propio del sub-componente) --}}
                <livewire:configuracion.configuracion-tienda />
            </div>
        @endif
    </div>

</div>
