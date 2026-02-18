@php
    $colors = match($tipo) {
        'venta' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        'compra' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'ajuste_manual' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'inventario_fisico' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
        'transferencia_salida' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
        'transferencia_entrada' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-300',
        'anulacion_venta' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/50 dark:text-pink-300',
        'anulacion_compra' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
        'devolucion' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
        'carga_inicial' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colors }}">
    {{ $label }}
</span>
