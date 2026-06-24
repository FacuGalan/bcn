<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
        {{-- ==================== Header ==================== --}}
        <div class="mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Importar padrón') }}</h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                {{ __('Actualiza el perfil de percepción de IIBB de tus clientes desde el padrón oficial de ARBA o AGIP.') }}
            </p>
        </div>

        {{-- ==================== Dos mitades: indicaciones | controles ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            {{-- Mitad izquierda: indicaciones --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 sm:p-5">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-500 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div class="text-xs sm:text-sm text-blue-800 dark:text-blue-200 space-y-1.5">
                        <p class="font-semibold">{{ __('Cómo se aplica el padrón:') }}</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>{{ __('Subí el archivo comprimido (.zip) tal cual lo descargás de la agencia; no hace falta descomprimirlo.') }}</li>
                            <li>{{ __('ARBA actualiza la percepción de IIBB de Buenos Aires; AGIP, la de CABA.') }}</li>
                            <li>{{ __('Solo se usa la alícuota de percepción del padrón (la de retención se ignora).') }}</li>
                            <li>{{ __('Una alícuota 0,00 o una baja del padrón dejan al cliente exento (no se le percibe).') }}</li>
                            <li>{{ __('No se pisan los perfiles cargados a mano: el ajuste manual tiene prioridad sobre el padrón.') }}</li>
                            <li>{{ __('Solo se actualizan los clientes con CUIT cargado que figuren en el padrón; el resto se informa como “sin coincidencia”.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Mitad derecha: controles + loader + botón --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6 flex flex-col">
                {{-- Agencia --}}
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Agencia') }}</label>
                    <select wire:model.live="agencia"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring-bcn-primary text-sm">
                        <option value="arba">{{ __('ARBA — Buenos Aires (Régimen general por sujeto)') }}</option>
                        <option value="agip">{{ __('AGIP — CABA (Padrón unificado)') }}</option>
                    </select>
                </div>

                {{-- Archivo + loader --}}
                <div x-data="{ uploading: false, progress: 0 }"
                    x-on:livewire-upload-start="uploading = true; progress = 0"
                    x-on:livewire-upload-finish="uploading = false"
                    x-on:livewire-upload-cancel="uploading = false"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Archivo de padrón comprimido (.zip)') }}</label>
                    <input type="file" wire:model="archivo" accept=".zip,.gz,application/zip,application/gzip"
                        class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-bcn-primary file:text-white hover:file:opacity-90 file:cursor-pointer">

                    {{-- Loader con barra de progreso (durante la subida) --}}
                    <div x-show="uploading" x-cloak class="mt-3">
                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <svg class="w-4 h-4 animate-spin text-bcn-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span>{{ __('Subiendo archivo…') }}</span>
                            <span class="ml-auto font-semibold tabular-nums" x-text="progress + '%'"></span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                            <div class="h-full rounded-full bg-bcn-primary transition-all duration-150" x-bind:style="`width: ${progress}%`"></div>
                        </div>
                    </div>

                    @error('archivo') <span class="mt-2 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    @error('agencia') <span class="mt-2 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                {{-- Botón importar --}}
                <div class="mt-auto pt-5 flex justify-end">
                    <button wire:click="importar" wire:loading.attr="disabled" wire:target="importar,archivo"
                        @disabled(! $archivo)
                        class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg wire:loading.remove wire:target="importar" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        <svg wire:loading wire:target="importar" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span wire:loading.remove wire:target="importar">{{ __('Importar padrón') }}</span>
                        <span wire:loading wire:target="importar">{{ __('Procesando…') }}</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== Resumen de la corrida ==================== --}}
        @if($resumen)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6 mt-4 sm:mt-6">
                <h3 class="text-sm font-semibold text-bcn-secondary dark:text-white uppercase tracking-wide mb-4">{{ __('Resultado de la importación') }}</h3>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Clientes actualizados') }}</p>
                        <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($resumen['impactadas'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Nuevos perfiles') }}</p>
                        <p class="mt-1 text-lg font-bold text-bcn-secondary dark:text-white">{{ number_format($resumen['creadas'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Perfiles actualizados') }}</p>
                        <p class="mt-1 text-lg font-bold text-bcn-secondary dark:text-white">{{ number_format($resumen['actualizadas'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Omitidos (carga manual)') }}</p>
                        <p class="mt-1 text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format($resumen['omitidas_manual'], 0, ',', '.') }}</p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Se leyeron :total filas del padrón (:padron de percepción). :sinmatch CUIT del padrón no son clientes del comercio.', [
                        'total' => number_format($resumen['total_filas'], 0, ',', '.'),
                        'padron' => number_format($resumen['filas_padron'], 0, ',', '.'),
                        'sinmatch' => number_format($resumen['sin_match'], 0, ',', '.'),
                    ]) }}
                </p>
            </div>
        @endif
    </div>
</div>
