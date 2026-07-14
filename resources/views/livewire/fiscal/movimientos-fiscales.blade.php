<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        @php
            $sentidoLabels = [
                'sufrido' => __('Sufrido'),
                'aplicado' => __('Aplicado'),
            ];
            $naturalezaLabels = [
                'percepcion' => __('Percepción'),
                'retencion' => __('Retención'),
                'tributo' => __('Tributo'),
                'debito_fiscal' => __('Débito fiscal'),
                'credito_fiscal' => __('Crédito fiscal'),
            ];
        @endphp

        {{-- ==================== Header ==================== --}}
        <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Movimientos fiscales') }}</h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Ledger fiscal por CUIT y período. Alta manual y anulación por contraasiento.') }}
                </p>
            </div>
            <button wire:click="abrirModalAlta"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span class="hidden sm:inline">{{ __('Alta manual') }}</span>
            </button>
        </div>

        {{-- ==================== Filtros ==================== --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('CUIT') }}</label>
                    <select wire:model.live="cuitId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        @forelse($cuits as $c)
                            <option value="{{ $c->id }}">{{ $c->razon_social }} ({{ $c->numero_cuit }})</option>
                        @empty
                            <option value="">{{ __('No hay CUITs configurados') }}</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Período') }}</label>
                    <input type="month" wire:model.live="periodo"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sentido') }}</label>
                    <select wire:model.live="filtroSentido"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        <option value="sufrido">{{ __('Sufrido') }}</option>
                        <option value="aplicado">{{ __('Aplicado') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Naturaleza') }}</label>
                    <select wire:model.live="filtroNaturaleza"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($naturalezaLabels as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="incluirAnulados"
                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-bcn-primary focus:ring-bcn-primary">
                    {{ __('Incluir anulados') }}
                </label>
            </div>
        </div>

        {{-- ==================== Cards móvil ==================== --}}
        <div class="sm:hidden space-y-3">
            @forelse($movimientos as $m)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ $m->estado === 'anulado' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $m->impuesto?->nombre ?? '—' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $m->fecha?->format('d/m/Y') }} · {{ $m->periodo_fiscal }}</p>
                        </div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($m->monto, 2, ',', '.') }}</p>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $naturalezaLabels[$m->naturaleza] ?? $m->naturaleza }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $sentidoLabels[$m->sentido] ?? $m->sentido }}</span>
                        @if($m->estado === 'anulado')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ __('Anulado') }}</span>
                        @endif
                    </div>
                    @if($m->estado === 'activo' && ! $m->movimiento_anulado_id)
                        <div class="mt-3">
                            @if($m->origen_tipo === null)
                                <button wire:click="abrirModalAnulacion({{ $m->id }})"
                                    class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">{{ __('Anular') }}</button>
                            @else
                                {{-- RF-B9: lo generó su origen — se revierte desde el circuito del origen --}}
                                <span class="text-xs text-gray-400 dark:text-gray-500"
                                      title="{{ __('Movimiento generado por su origen: se revierte cancelando o acreditando desde el origen') }}">{{ __('No anulable (lo maneja su origen)') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No hay movimientos fiscales para los filtros seleccionados.') }}</p>
                </div>
            @endforelse

            <div>{{ $movimientos->links() }}</div>
        </div>

        {{-- ==================== Tabla desktop ==================== --}}
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Fecha') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Impuesto') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Naturaleza') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Sentido') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Base imponible') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Monto') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Estado') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($movimientos as $m)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 {{ $m->estado === 'anulado' ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $m->fecha?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $m->impuesto?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $naturalezaLabels[$m->naturaleza] ?? $m->naturaleza }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $sentidoLabels[$m->sentido] ?? $m->sentido }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">{{ $m->base_imponible !== null ? '$'.number_format($m->base_imponible, 2, ',', '.') : '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-white">${{ number_format($m->monto, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($m->estado === 'anulado')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ __('Anulado') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Activo') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                    @if($m->estado === 'activo' && ! $m->movimiento_anulado_id && $m->origen_tipo === null)
                                        <button wire:click="abrirModalAnulacion({{ $m->id }})"
                                            class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 hover:underline">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            {{ __('Anular') }}
                                        </button>
                                    @elseif($m->estado === 'activo' && ! $m->movimiento_anulado_id)
                                        {{-- RF-B9: lo generó su origen — se revierte desde el circuito del origen --}}
                                        <span class="text-gray-400 dark:text-gray-600 cursor-help"
                                              title="{{ __('Movimiento generado por su origen: se revierte cancelando o acreditando desde el origen') }}">—</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No hay movimientos fiscales para los filtros seleccionados.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $movimientos->links() }}
            </div>
        </div>

        {{-- ==================== Modal alta manual ==================== --}}
        @if($mostrarModalAlta)
            <x-bcn-modal
                :title="__('Alta manual de movimiento fiscal')"
                color="bg-bcn-primary"
                maxWidth="3xl"
                onClose="cerrarModalAlta"
                submit="registrarMovimiento"
            >
                <x-slot:body>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Para retenciones/percepciones sufridas fuera de MercadoPago/compras y ajustes. El débito/crédito fiscal de IVA se genera automáticamente desde comprobantes y compras.') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('CUIT') }} <span class="text-red-500">*</span></label>
                            <select wire:model.live="formCuitId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Seleccionar') }}</option>
                                @foreach($cuits as $c)
                                    <option value="{{ $c->id }}">{{ $c->razon_social }} ({{ $c->numero_cuit }})</option>
                                @endforeach
                            </select>
                            @error('formCuitId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Impuesto') }} <span class="text-red-500">*</span></label>
                            @php
                                $impConfigurados = $impuestos->whereIn('id', $impuestoConfigIds);
                                $impOtros = $impuestos->whereNotIn('id', $impuestoConfigIds);
                            @endphp
                            <select wire:model.live="formImpuestoId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="">{{ __('Seleccionar') }}</option>
                                @if($impConfigurados->isNotEmpty())
                                    <optgroup label="{{ __('Configurados para este CUIT') }}">
                                        @foreach($impConfigurados as $imp)
                                            <option value="{{ $imp->id }}">{{ $imp->nombre }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="{{ __('Otros impuestos del catálogo') }}">
                                        @foreach($impOtros as $imp)
                                            <option value="{{ $imp->id }}">{{ $imp->nombre }}</option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    @foreach($impOtros as $imp)
                                        <option value="{{ $imp->id }}">{{ $imp->nombre }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @error('formImpuestoId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sentido') }} <span class="text-red-500">*</span></label>
                            <select wire:model="formSentido"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                <option value="sufrido">{{ __('Sufrido') }}</option>
                                <option value="aplicado">{{ __('Aplicado') }}</option>
                            </select>
                            @error('formSentido') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Naturaleza') }} <span class="text-red-500">*</span></label>
                            <select wire:model="formNaturaleza"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                                @foreach($naturalezasManuales as $valor)
                                    <option value="{{ $valor }}">{{ $naturalezaLabels[$valor] ?? $valor }}</option>
                                @endforeach
                            </select>
                            @error('formNaturaleza') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fecha') }} <span class="text-red-500">*</span></label>
                            <input type="date" wire:model="formFecha" data-enter-default
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('formFecha') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('N° de certificado') }}</label>
                            <input type="text" wire:model="formCertificadoNumero" maxlength="50" data-enter-default
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('formCertificadoNumero') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Base imponible') }}</label>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="formBaseImponible"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('formBaseImponible') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Alícuota (%)') }}</label>
                            <input type="number" step="0.0001" min="0" max="100" wire:model.live.debounce.400ms="formAlicuota"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('formAlicuota') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Monto') }} <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="0" wire:model="formMonto"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm">
                            @error('formMonto') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Se sugiere base × alícuota; editable.') }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Observaciones') }}</label>
                        <textarea wire:model="formObservaciones" rows="2" maxlength="1000"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"></textarea>
                        @error('formObservaciones') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </x-slot:body>

                <x-slot:footer>
                    <button type="button" wire:click="cerrarModalAlta"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors">
                        {{ __('Registrar') }}
                    </button>
                </x-slot:footer>
            </x-bcn-modal>
        @endif

        {{-- ==================== Modal anulación ==================== --}}
        @if($mostrarModalAnulacion)
            <x-bcn-modal
                :title="__('Anular movimiento fiscal')"
                color="bg-red-600"
                maxWidth="lg"
                onClose="cerrarModalAnulacion"
                submit="confirmarAnulacion"
            >
                <x-slot:body>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('Se generará un contraasiento que anula el movimiento. La operación queda registrada de forma inmutable.') }}
                    </p>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Motivo (opcional)') }}</label>
                        <textarea wire:model="motivoAnulacion" rows="3" maxlength="255"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring focus:ring-red-500 focus:ring-opacity-50 text-sm"></textarea>
                    </div>
                </x-slot:body>

                <x-slot:footer>
                    <button type="button" wire:click="cerrarModalAnulacion"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition-colors">
                        {{ __('Anular') }}
                    </button>
                </x-slot:footer>
            </x-bcn-modal>
        @endif
    </div>
</div>
