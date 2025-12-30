<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Formas de Pago</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Configura formas de pago y planes de cuotas</p>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forma de Pago</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Planes de Cuotas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($formasPago as $fp)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $fp->nombre }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                            {{ $fp->codigo }}
                        </td>
                        <td class="px-6 py-4">
                            @if($fp->cuotas->count() > 0)
                                <div class="flex flex-wrap gap-1">
                                    @foreach($fp->cuotas as $cuota)
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                            {{ $cuota->cantidad_cuotas }}x
                                            @if($cuota->interes_porcentaje > 0)
                                                ({{ $cuota->interes_porcentaje }}%)
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Sin planes de cuotas</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <button wire:click="toggleActivo({{ $fp->id }})"
                                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $fp->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $fp->activo ? 'Activa' : 'Inactiva' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="editarCuotas({{ $fp->id }})"
                                    class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                Gestionar Cuotas
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal para Gestionar Cuotas --}}
    @if($formaPagoEditando)
        @php
            $formaPago = $formasPago->firstWhere('id', $formaPagoEditando);
        @endphp

        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Gestionar Cuotas - {{ $formaPago->nombre }}</h3>
                        <button wire:click="cerrarModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Tabla de cuotas existentes --}}
                    @if($formaPago->cuotas->count() > 0)
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold mb-2">Planes Configurados</h4>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Cuotas</th>
                                        <th class="px-4 py-2 text-left">Interés %</th>
                                        <th class="px-4 py-2 text-left">Coeficiente</th>
                                        <th class="px-4 py-2 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($formaPago->cuotas as $cuota)
                                        <tr>
                                            <td class="px-4 py-2">{{ $cuota->cantidad_cuotas }}</td>
                                            <td class="px-4 py-2">{{ $cuota->interes_porcentaje }}%</td>
                                            <td class="px-4 py-2">{{ $cuota->coeficiente }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <button wire:click="eliminarCuota({{ $cuota->id }})"
                                                        wire:confirm="¿Eliminar este plan?"
                                                        class="text-red-600 hover:text-red-900">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Formulario para agregar nuevo plan --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold mb-3">Agregar Nuevo Plan</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad Cuotas *</label>
                                <input type="number"
                                       wire:model="nuevaCuota.cantidad_cuotas"
                                       min="1"
                                       class="w-full rounded border-gray-300 dark:border-gray-600 text-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Interés %</label>
                                <input type="number"
                                       wire:model="nuevaCuota.interes_porcentaje"
                                       step="0.01"
                                       class="w-full rounded border-gray-300 dark:border-gray-600 text-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Coeficiente</label>
                                <input type="number"
                                       wire:model="nuevaCuota.coeficiente"
                                       step="0.01"
                                       class="w-full rounded border-gray-300 dark:border-gray-600 text-sm">
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button wire:click="cerrarModal"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300">
                                Cerrar
                            </button>
                            <button wire:click="agregarCuota"
                                    class="px-4 py-2 bg-bcn-primary text-white rounded hover:bg-bcn-primary-dark">
                                Agregar Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
