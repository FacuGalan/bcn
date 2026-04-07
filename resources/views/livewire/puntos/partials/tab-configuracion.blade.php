<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <form wire:submit="guardarConfiguracion" class="space-y-6">

        {{-- Toggle programa activo --}}
        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('Programa de Puntos') }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Activar programa de puntos') }}
                </p>
            </div>
            <button type="button" wire:click="toggleActivo"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 {{ $activo ? 'bg-bcn-primary' : 'bg-gray-200 dark:bg-gray-600' }}"
                role="switch" aria-checked="{{ $activo ? 'true' : 'false' }}">
                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $activo ? 'translate-x-5' : 'translate-x-0' }}"></span>
            </button>
        </div>

        @if($activo)
        {{-- Ratios de acumulación y canje --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Monto por punto --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Monto por punto') }}
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('Cuántos $ debe gastar el cliente para ganar 1 punto') }}
                </p>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                    <input type="number" wire:model="montoPorPunto" step="0.01" min="0.01"
                        class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                @error('montoPorPunto') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            {{-- Valor punto canje --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Valor del punto') }}
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('Cuánto vale 1 punto en $ al canjear') }}
                </p>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                    <input type="number" wire:model="valorPuntoCanje" step="0.01" min="0.01"
                        class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                @error('valorPuntoCanje') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Mínimo canje --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Mínimo para canje') }}
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('Puntos mínimos para habilitar canje') }}
                </p>
                <input type="number" wire:model="minimoCanje" min="1"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @error('minimoCanje') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            {{-- Modo acumulación --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Modo de acumulación') }}
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('Cómo se gestionan los saldos') }}
                </p>
                <select wire:model="modoAcumulacion"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="global">{{ __('Global (todas las sucursales)') }}</option>
                    <option value="por_sucursal">{{ __('Por sucursal') }}</option>
                </select>
            </div>

            {{-- Redondeo --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Redondeo') }}
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('Cómo redondear puntos fraccionarios') }}
                </p>
                <select wire:model="redondeo"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="floor">{{ __('Hacia abajo') }}</option>
                    <option value="round">{{ __('Al más cercano') }}</option>
                    <option value="ceil">{{ __('Hacia arriba') }}</option>
                </select>
            </div>
        </div>

        {{-- Preview de ejemplo --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">
                {{ __('Ejemplo') }}
            </h4>
            <p class="text-sm text-blue-700 dark:text-blue-400">
                {{ __('Una compra de $1.000 genera') }}
                <span class="font-bold">
                    {{ $montoPorPunto > 0 ? intval(1000 / $montoPorPunto) : 0 }} {{ __('puntos') }}
                </span>
                {{ __('que valen') }}
                <span class="font-bold">
                    ${{ $montoPorPunto > 0 ? number_format(intval(1000 / $montoPorPunto) * $valorPuntoCanje, 2) : '0.00' }}
                </span>
                {{ __('al canjear') }}.
            </p>
        </div>

        {{-- Sucursales --}}
        @if(count($sucursalesConfig) > 1)
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                {{ __('Activación por sucursal') }}
            </h3>
            <div class="space-y-2">
                @foreach($sucursalesConfig as $sucursalId => $config)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $config['nombre'] }}
                    </span>
                    <button type="button" wire:click="toggleSucursal({{ $sucursalId }})"
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 {{ $config['activo'] ? 'bg-bcn-primary' : 'bg-gray-200 dark:bg-gray-600' }}"
                        role="switch" aria-checked="{{ $config['activo'] ? 'true' : 'false' }}">
                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $config['activo'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endif

        {{-- Botón guardar --}}
        <div class="flex justify-end">
            <button type="submit"
                wire:loading.attr="disabled"
                wire:target="guardarConfiguracion"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-bcn-primary hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary disabled:opacity-50 transition-all duration-150">
                {{-- Estado normal --}}
                <span wire:loading.remove wire:target="guardarConfiguracion">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ __('Guardar configuración') }}
                </span>
                {{-- Estado guardando --}}
                <span wire:loading wire:target="guardarConfiguracion">
                    <svg class="w-4 h-4 mr-2 inline animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{ __('Guardando...') }}
                </span>
            </button>
        </div>
    </form>
</div>
