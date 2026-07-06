<div class="px-3 sm:px-4 lg:px-6 py-4 space-y-4 max-w-5xl">
    {{-- ==================== HEADER ==================== --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold text-bcn-secondary dark:text-white">{{ __('Configuración de Delivery') }}</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Georreferenciación, costos de envío, zonas, horarios y promesa de entrega de la sucursal.') }}</p>
        </div>
        <button type="button" wire:click="guardarConfig"
            class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ __('Guardar') }}
        </button>
    </div>

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
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Al pasar a Entregado el pedido se factura automáticamente (requiere pagos completos y caja). Configuración compartida con pedidos de mostrador.') }}</span>
                </span>
            </label>
            <div class="flex flex-wrap items-end gap-3">
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
                <p class="text-xs text-gray-500 dark:text-gray-400 basis-full sm:basis-auto sm:flex-1">
                    {{ __('Resalta pedidos demorados en el panel: sin promesa mide desde la confirmación; con promesa avisa antes de vencer. 0 = sin alerta. Compartida con mostrador.') }}
                </p>
            </div>
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
        </div>
    </div>

    {{-- ==================== ENVÍO, ALCANCE Y ZONAS (RF-06, sub-componente con Maps) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
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
        <livewire:pedidos.configuracion-delivery-envio />
    @endif

    {{-- ==================== PEDIDOS EXTERNOS (D14) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Pedidos externos (tienda / API)') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label for="cd-aceptacion" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aceptación') }}</label>
                <select id="cd-aceptacion" wire:model="aceptacionPedidosExternos"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                    <option value="manual">{{ __('Manual (entra "por aceptar")') }}</option>
                    <option value="automatica">{{ __('Automática') }}</option>
                </select>
            </div>
            <div>
                <label for="cd-timeout" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aviso si no se acepta en (min)') }}</label>
                <input id="cd-timeout" type="number" min="1" wire:model="timeoutAceptacionMin" placeholder="{{ __('Sin aviso') }}"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
            </div>
            <label class="flex items-end gap-2 cursor-pointer pb-1">
                <input type="checkbox" wire:model="imprimirComandaAlAceptar" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Imprimir comanda al aceptar') }}</span>
            </label>
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

    {{-- ==================== CALENDARIO (RF-05/D16) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Calendario de atención') }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('La tienda/API rechaza pedidos fuera de horario; el panel solo advierte.') }}</p>

        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Días laborales') }}</label>
            <div class="flex flex-wrap gap-1.5">
                @foreach($diasSemana as $dia => $label)
                    <label class="inline-flex items-center gap-1 px-2 py-1 border rounded-md cursor-pointer text-xs {{ ($diasLaborales[$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">
                        <input type="checkbox" wire:model.live="diasLaborales.{{ $dia }}" class="sr-only" />
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-2 mb-1">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Horarios de atención') }} <span class="text-gray-400">({{ __('vacío = siempre') }})</span></label>
                <button type="button" wire:click="agregarHorario" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar rango') }}</button>
            </div>
            @foreach($horariosAtencion as $i => $rango)
                <div class="flex flex-wrap items-center gap-2 mb-1.5 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5">
                    <div class="flex flex-wrap gap-1">
                        @foreach($diasSemana as $dia => $label)
                            <label class="inline-flex items-center px-1.5 py-0.5 border rounded cursor-pointer text-[10px] {{ ($horariosAtencion[$i]['dias'][$dia] ?? false) ? 'border-bcn-primary bg-bcn-primary/10 text-bcn-primary font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400' }}">
                                <input type="checkbox" wire:model.live="horariosAtencion.{{ $i }}.dias.{{ $dia }}" class="sr-only" />
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <input type="time" wire:model="horariosAtencion.{{ $i }}.desde" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                    <span class="text-xs text-gray-400">—</span>
                    <input type="time" wire:model="horariosAtencion.{{ $i }}.hasta" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                    <button type="button" wire:click="quitarHorario({{ $i }})" class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endforeach
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Feriados sin atención') }}</label>
            <div class="flex flex-wrap items-center gap-2">
                <input type="date" wire:model="nuevoFeriado" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                <button type="button" wire:click="agregarFeriado" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar') }}</button>
                @foreach($feriados as $i => $feriado)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs rounded-full">
                        {{ $feriado }}
                        <button type="button" wire:click="quitarFeriado({{ $i }})" class="text-gray-400 hover:text-red-500">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Guardar (repetido abajo para no scrollear) --}}
    <div class="flex justify-end">
        <button type="button" wire:click="guardarConfig"
            class="h-9 px-4 inline-flex items-center gap-1.5 bg-bcn-primary border border-transparent rounded-md font-semibold text-sm text-white hover:bg-opacity-90 transition-colors">
            {{ __('Guardar configuración') }}
        </button>
    </div>

</div>
