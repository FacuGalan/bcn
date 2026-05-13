@props(['estado'])

@php
    $config = match($estado) {
        'borrador' => ['label' => __('Borrador'), 'classes' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'confirmado' => ['label' => __('Confirmado'), 'classes' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'],
        'en_preparacion' => ['label' => __('En preparación'), 'classes' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
        'listo' => ['label' => __('Listo'), 'classes' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
        'entregado' => ['label' => __('Entregado'), 'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'],
        'facturado' => ['label' => __('Facturado'), 'classes' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'],
        'cancelado' => ['label' => __('Cancelado'), 'classes' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
        default => ['label' => $estado, 'classes' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $config['classes']]) }}>
    {{ $config['label'] }}
</span>
