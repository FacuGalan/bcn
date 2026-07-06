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
                <input type="checkbox" wire:model="convertirVentaAlEntregar" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Convertir en venta al entregar') }}
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Al pasar a Entregado el pedido se factura automáticamente (requiere pagos completos y caja). Configuración compartida con pedidos de mostrador.') }}</span>
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
        </div>
    </div>

    {{-- ==================== ENVÍO Y ALCANCE (RF-06) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Costo de envío y alcance') }}</h2>
        <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" wire:model.live="georreferenciarPedidos" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
            <span class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('Georreferenciar pedidos') }}
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Con el mapa activado se cotiza el envío automáticamente y se valida el alcance. Apagado: solo dirección de texto y costo manual.') }}</span>
            </span>
        </label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 {{ $georreferenciarPedidos ? '' : 'opacity-50' }}">
            <div>
                <label for="cd-radio" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Radio de entrega (km)') }}</label>
                <input id="cd-radio" type="number" step="0.1" min="0" wire:model="radioEntregaKm" placeholder="{{ __('Sin límite') }}"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" @disabled(! $georreferenciarPedidos) />
            </div>
            <div>
                <label for="cd-costo-base" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Costo base') }} ($)</label>
                <input id="cd-costo-base" type="number" step="0.01" min="0" wire:model="costoEnvioBase"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" @disabled(! $georreferenciarPedidos) />
            </div>
            <div>
                <label for="cd-km-incluidos" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Km incluidos en base') }}</label>
                <input id="cd-km-incluidos" type="number" step="0.1" min="0" wire:model="kmIncluidosEnBase"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" @disabled(! $georreferenciarPedidos) />
            </div>
            <div>
                <label for="cd-costo-km" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Costo por km extra') }} ($)</label>
                <input id="cd-costo-km" type="number" step="0.01" min="0" wire:model="costoPorKmExtra"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" @disabled(! $georreferenciarPedidos) />
            </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Las zonas (abajo) tienen prioridad sobre este cálculo por km. Fuera del radio, el pedido solo puede confirmarse con el permiso "forzar alcance".') }}
        </p>
    </div>

    {{-- ==================== ZONAS (RF-05/RF-06) ==================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Zonas de entrega') }}</h2>
            <button type="button" wire:click="abrirCrearZona"
                class="h-8 px-3 inline-flex items-center gap-1 bg-cyan-600 rounded-md text-xs font-semibold text-white hover:bg-cyan-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Nueva zona') }}
            </button>
        </div>

        @if($zonas->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">{{ __('Sin zonas: el envío se cotiza por distancia a la sucursal.') }}</p>
        @else
            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-bcn-light dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Orden') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Nombre') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Radio') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Costo') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Horario') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Estado') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($zonas as $zona)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $zona->orden }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $zona->nombre }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($zona->radio_km, 1, ',', '.') }} km</td>
                                <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">${{ number_format($zona->costo_envio, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ empty($zona->rangos_horarios) ? __('Siempre') : __(':n rango(s)', ['n' => count($zona->rangos_horarios)]) }}
                                </td>
                                <td class="px-3 py-2">
                                    @if($zona->activo)
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Activa') }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('Inactiva') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button wire:click="abrirEditarZona({{ $zona->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-amber-300 dark:border-amber-600 rounded text-xs text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30"
                                            title="{{ __('Editar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmarEliminarZona({{ $zona->id }})"
                                            class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30"
                                            title="{{ __('Eliminar') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Las zonas se evalúan por orden: la primera que contenga la dirección (y esté en horario) define el costo.') }}</p>
        @endif
    </div>

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

    {{-- ==================== MODAL: ZONA ==================== --}}
    @if($showZonaModal)
        <x-bcn-modal
            :title="$zonaId ? __('Editar zona') : __('Nueva zona de entrega')"
            color="bg-cyan-600"
            maxWidth="2xl"
            onClose="cerrarZonaModal"
        >
            <x-slot:body>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="zona-nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre') }} <span class="text-red-500">*</span></label>
                            <input id="zona-nombre" type="text" wire:model="zonaNombre" maxlength="100"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            @error('zonaNombre') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label for="zona-radio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Radio (km)') }} <span class="text-red-500">*</span></label>
                                <input id="zona-radio" type="number" step="0.1" min="0.1" wire:model="zonaRadioKm"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                @error('zonaRadioKm') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="zona-costo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Costo') }} ($) <span class="text-red-500">*</span></label>
                                <input id="zona-costo" type="number" step="0.01" min="0" wire:model="zonaCostoEnvio"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                @error('zonaCostoEnvio') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="zona-orden" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Orden') }}</label>
                                <input id="zona-orden" type="number" min="0" wire:model="zonaOrden"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>

                    {{-- Centro del radio: picker de Maps (partial de domicilio, solo geo) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Centro de la zona') }} <span class="text-red-500">*</span></label>
                        @include('livewire.partials.domicilio-form', [
                            'conTipo' => false,
                            'conDireccion' => false,
                            'conReferencia' => false,
                            'conGeo' => true,
                            'provinciaRequerida' => false,
                            'idPrefix' => 'zona-centro',
                        ])
                    </div>

                    {{-- Rangos horarios de la zona --}}
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Horarios de la zona') }} <span class="text-gray-400">({{ __('vacío = siempre') }})</span></label>
                            <button type="button" wire:click="agregarZonaRango" class="text-xs text-bcn-primary hover:underline">+ {{ __('Agregar rango') }}</button>
                        </div>
                        @foreach($zonaRangos as $i => $rango)
                            <div class="flex flex-wrap items-center gap-2 mb-1.5 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($diasSemana as $dia => $label)
                                        <label class="inline-flex items-center px-1.5 py-0.5 border rounded cursor-pointer text-[10px] {{ ($zonaRangos[$i]['dias'][$dia] ?? false) ? 'border-cyan-600 bg-cyan-600/10 text-cyan-700 dark:text-cyan-300 font-semibold' : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400' }}">
                                            <input type="checkbox" wire:model.live="zonaRangos.{{ $i }}.dias.{{ $dia }}" class="sr-only" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <input type="time" wire:model="zonaRangos.{{ $i }}.desde" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                                <span class="text-xs text-gray-400">—</span>
                                <input type="time" wire:model="zonaRangos.{{ $i }}.hasta" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs py-1" />
                                <button type="button" wire:click="quitarZonaRango({{ $i }})" class="text-red-500 hover:text-red-700" title="{{ __('Quitar') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="zonaActivo" class="rounded border-gray-300 dark:border-gray-600 text-cyan-600 focus:ring-cyan-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Activa') }}</span>
                    </label>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarZonaModal"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="guardarZona"
                    class="px-4 py-2 bg-cyan-600 rounded-md text-sm font-semibold text-white hover:bg-cyan-700">
                    {{ __('Guardar zona') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- ==================== MODAL: ELIMINAR ZONA ==================== --}}
    @if($showEliminarZonaModal)
        <x-bcn-modal :title="__('¿Eliminar zona?')" color="bg-red-600" maxWidth="sm" onClose="cerrarEliminarZona">
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Se eliminará la zona ":nombre". Los pedidos históricos conservan sus datos; la zona solo deja de cotizar.', ['nombre' => $zonaNombreAEliminar]) }}
                </p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" wire:click="cerrarEliminarZona"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="eliminarZona"
                    class="px-4 py-2 bg-red-600 rounded-md text-sm font-semibold text-white hover:bg-red-700">
                    {{ __('Eliminar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif
</div>
