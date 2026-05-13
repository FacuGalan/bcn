@props(['estado'])

@php
    $config = match($estado) {
        'pendiente' => ['label' => __('Pendiente'), 'classes' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
        'parcial' => ['label' => __('Parcial'), 'classes' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'],
        'pagado' => ['label' => __('Pagado'), 'classes' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
        default => ['label' => $estado, 'classes' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $config['classes']]) }}>
    {{ $config['label'] }}
</span>
