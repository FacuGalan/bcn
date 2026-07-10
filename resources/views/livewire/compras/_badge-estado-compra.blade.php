{{-- Badge de estado del ciclo de vida de la compra (D11: borrador/completada/cancelada) --}}
@php
    [$claseEstado, $labelEstado] = match ($compra->estado) {
        \App\Models\Compra::ESTADO_BORRADOR => ['bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', __('Borrador')],
        \App\Models\Compra::ESTADO_COMPLETADA => ['bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', __('Completada')],
        \App\Models\Compra::ESTADO_CANCELADA => ['bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', __('Cancelada')],
        default => ['bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200', $compra->estado],
    };
@endphp
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium select-none {{ $claseEstado }}">
    {{ $labelEstado }}
</span>
