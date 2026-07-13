{{-- Badge de pago derivado del saldo (D11: lo impago NO es un estado) --}}
@php
    $saldo = (float) $compra->saldo_pendiente;
    $total = (float) $compra->total;
@endphp
@if($saldo <= 0)
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium select-none bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
        {{ __('Pagada') }}
    </span>
@elseif($saldo < $total)
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium select-none bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
        {{ __('Saldo') }}: $@precio($saldo)
    </span>
@else
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium select-none bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
        {{ __('Impaga') }}
    </span>
@endif
