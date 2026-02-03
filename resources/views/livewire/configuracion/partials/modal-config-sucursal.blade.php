{{-- Modal Configuración de Sucursal --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarModalConfigSucursal"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
            <form wire:submit.prevent="guardarConfigSucursal">
                <div class="bg-white dark:bg-gray-800 px-6 pt-5 pb-4">
                    {{-- Header --}}
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Configurar Sucursal: {{ $configSucursalNombre }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Define los parámetros de operación para esta sucursal') }}
                        </p>
                    </div>

                    {{-- Layout de 2 columnas --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- COLUMNA IZQUIERDA --}}
                        <div class="space-y-6">
                            {{-- SECCIÓN: Autorización --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Autorización
                                </h4>

                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            type="checkbox"
                                            id="usaClaveAutorizacion"
                                            wire:model.live="configUsaClaveAutorizacion"
                                            class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="usaClaveAutorizacion" class="font-medium text-gray-700 dark:text-gray-300">
                                            Usar clave de autorización
                                        </label>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Para anulaciones, descuentos, etc.') }}</p>
                                    </div>
                                </div>

                                @if($configUsaClaveAutorizacion)
                                    <div class="mt-3 ml-7">
                                        <input
                                            type="password"
                                            wire:model="configClaveAutorizacion"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            :placeholder="__('Clave (mín. 4 caracteres)')"
                                        >
                                        @error('configClaveAutorizacion')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            </div>

                            {{-- SECCIÓN: Impresión --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    Impresión
                                </h4>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Impresión en Facturas
                                    </label>
                                    <select
                                        wire:model="configTipoImpresionFactura"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                        @foreach(\App\Models\Sucursal::TIPOS_IMPRESION_FACTURA as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            type="checkbox"
                                            id="imprimeEncabezadoComanda"
                                            wire:model="configImprimeEncabezadoComanda"
                                            class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="imprimeEncabezadoComanda" class="font-medium text-gray-700 dark:text-gray-300">
                                            Imprimir encabezado en comandas
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN: Agrupación de Artículos --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    Agrupación de Artículos
                                </h4>

                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input
                                                type="checkbox"
                                                id="agrupaArticulosVenta"
                                                wire:model.live="configAgrupaArticulosVenta"
                                                class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="agrupaArticulosVenta" class="font-medium text-gray-700 dark:text-gray-300">
                                                Agrupar en detalle de venta
                                            </label>
                                            <p class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Suma cantidad en vez de nueva línea') }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input
                                                type="checkbox"
                                                id="agrupaArticulosImpresion"
                                                wire:model="configAgrupaArticulosImpresion"
                                                class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600 {{ $configAgrupaArticulosVenta ? 'opacity-50' : '' }}"
                                                {{ $configAgrupaArticulosVenta ? 'disabled' : '' }}
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="agrupaArticulosImpresion" class="font-medium text-gray-700 dark:text-gray-300 {{ $configAgrupaArticulosVenta ? 'opacity-50' : '' }}">
                                                Agrupar en impresiones
                                            </label>
                                            @if($configAgrupaArticulosVenta)
                                                <p class="text-gray-500 dark:text-gray-400 text-xs opacity-50">{{ __('Auto-activado') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- COLUMNA DERECHA --}}
                        <div class="space-y-6">
                            {{-- SECCIÓN: Facturación --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Facturación Fiscal
                                </h4>

                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            type="checkbox"
                                            id="facturacionFiscalAutomatica"
                                            wire:model="configFacturacionFiscalAutomatica"
                                            class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="facturacionFiscalAutomatica" class="font-medium text-gray-700 dark:text-gray-300">
                                            Facturación fiscal automática
                                        </label>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">
                                            {{ __('Si está activo, emite factura fiscal automáticamente según la configuración de las formas de pago seleccionadas, sin preguntar al usuario.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN: WhatsApp --}}
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 flex-1">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    WhatsApp
                                </h4>

                                <div class="space-y-4">
                                    {{-- Usa WhatsApp escritorio --}}
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input
                                                type="checkbox"
                                                id="usaWhatsappEscritorio"
                                                wire:model="configUsaWhatsappEscritorio"
                                                class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="usaWhatsappEscritorio" class="font-medium text-gray-700 dark:text-gray-300">
                                                Usar WhatsApp de escritorio
                                            </label>
                                            <p class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Abre con app desktop en lugar de web') }}</p>
                                        </div>
                                    </div>

                                    <hr class="border-gray-200 dark:border-gray-600">

                                    {{-- Envía WA al comandar --}}
                                    <div>
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input
                                                    type="checkbox"
                                                    id="enviaWhatsappComanda"
                                                    wire:model.live="configEnviaWhatsappComanda"
                                                    class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                                >
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="enviaWhatsappComanda" class="font-medium text-gray-700 dark:text-gray-300">
                                                    Enviar al comandar pedido
                                                </label>
                                                <p class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Notifica cuando se toma el pedido') }}</p>
                                            </div>
                                        </div>

                                        @if($configEnviaWhatsappComanda)
                                            <div class="mt-2 ml-7">
                                                <textarea
                                                    wire:model="configMensajeWhatsappComanda"
                                                    rows="2"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    :placeholder="__('Mensaje adicional (opcional)')"
                                                ></textarea>
                                                @error('configMensajeWhatsappComanda')
                                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Envía WA pedido listo --}}
                                    <div>
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input
                                                    type="checkbox"
                                                    id="enviaWhatsappListo"
                                                    wire:model.live="configEnviaWhatsappListo"
                                                    class="h-4 w-4 text-bcn-primary focus:ring-bcn-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                                >
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="enviaWhatsappListo" class="font-medium text-gray-700 dark:text-gray-300">
                                                    Enviar cuando pedido listo/en camino
                                                </label>
                                                <p class="text-gray-500 dark:text-gray-400 text-xs">{{ __('Notifica que está listo o en camino') }}</p>
                                            </div>
                                        </div>

                                        @if($configEnviaWhatsappListo)
                                            <div class="mt-2 ml-7">
                                                <textarea
                                                    wire:model="configMensajeWhatsappListo"
                                                    rows="2"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    :placeholder="__('Mensaje adicional (opcional)')"
                                                ></textarea>
                                                @error('configMensajeWhatsappListo')
                                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-bcn-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Guardar Configuración
                    </button>
                    <button
                        type="button"
                        wire:click="cerrarModalConfigSucursal"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        {{ __('Cancelar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
