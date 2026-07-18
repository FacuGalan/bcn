{{-- Pedidos externos (D14): partial del PADRE ConfiguracionDelivery — bindea
     props del padre; se incluye en la zona delivery o dentro del apartado
     Tienda Online según el estado del switch (RF-T11). --}}
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
