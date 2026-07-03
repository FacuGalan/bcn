@props(['estado', 'label' => null])

@php
    $config = match($estado) {
        'borrador' => ['label' => __('Borrador'), 'classes' => 'bg-gray-500 text-white dark:bg-gray-600'],
        'confirmado' => ['label' => __('Confirmado'), 'classes' => 'bg-blue-600 text-white dark:bg-blue-500'],
        'en_preparacion' => ['label' => __('En preparación'), 'classes' => 'bg-amber-500 text-white dark:bg-amber-500'],
        'listo' => ['label' => __('Listo'), 'classes' => 'bg-green-600 text-white dark:bg-green-500'],
        'en_camino' => ['label' => __('En camino'), 'classes' => 'bg-cyan-600 text-white dark:bg-cyan-500'],
        'entregado' => ['label' => __('Entregado'), 'classes' => 'bg-emerald-600 text-white dark:bg-emerald-500'],
        'facturado' => ['label' => __('Facturado'), 'classes' => 'bg-purple-600 text-white dark:bg-purple-500'],
        'cancelado' => ['label' => __('Cancelado'), 'classes' => 'bg-red-600 text-white dark:bg-red-500'],
        default => ['label' => $estado, 'classes' => 'bg-gray-500 text-white dark:bg-gray-600'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-sm font-semibold cursor-default select-none ' . $config['classes']]) }}>
    {{ $label ?? $config['label'] }}
</span>
