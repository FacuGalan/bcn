{{-- Pedidos externos (D14): partial del PADRE ConfiguracionDelivery — bindea
     props del padre; se incluye en la zona delivery o dentro del apartado
     Tienda Online según el estado del switch (RF-T11). --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Pedidos externos (tienda / API)') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label for="cd-aceptacion" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aceptación') }}</label>
            <select id="cd-aceptacion" wire:model.live="aceptacionPedidosExternos"
                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                <option value="manual">{{ __('Manual (entra "por aceptar")') }}</option>
                <option value="automatica">{{ __('Automática') }}</option>
            </select>
        </div>
        <div>
            <label for="cd-timeout" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Aviso si no se acepta en (min)') }}</label>
            <input id="cd-timeout" type="number" min="1" wire:model.live.debounce.800ms="timeoutAceptacionMin" placeholder="{{ __('Sin aviso') }}"
                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
        </div>
        <label class="flex items-end gap-2 cursor-pointer pb-1">
            <input type="checkbox" wire:model.live="imprimirComandaAlAceptar" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Imprimir comanda al aceptar') }}</span>
        </label>
    </div>

    {{-- Datos del cliente en el checkout (RF-T19) --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
        <h3 class="text-xs font-semibold text-gray-900 dark:text-white mb-2">{{ __('Datos del cliente en el checkout') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label for="cd-pedir-email" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email') }}</label>
                <select id="cd-pedir-email" wire:model.live="checkoutPedirEmail"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                    <option value="no">{{ __('No pedir') }}</option>
                    <option value="opcional">{{ __('Pedir (opcional)') }}</option>
                    <option value="obligatorio">{{ __('Pedir (obligatorio)') }}</option>
                </select>
            </div>
            <div>
                <label for="cd-entre-calles" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Entre calles (solo delivery)') }}</label>
                <select id="cd-entre-calles" wire:model.live="checkoutPedirEntreCalles"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                    <option value="no">{{ __('No pedir') }}</option>
                    <option value="opcional">{{ __('Pedir (opcional)') }}</option>
                    <option value="obligatorio">{{ __('Pedir (obligatorio)') }}</option>
                </select>
            </div>
            <label class="flex items-end gap-2 cursor-pointer pb-1">
                <input type="checkbox" wire:model.live="checkoutPedirCumpleanios" class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Pedir fecha de cumpleaños (siempre opcional, con leyenda de promociones)') }}</span>
            </label>
        </div>
    </div>
</div>
