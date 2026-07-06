{{-- Badges delivery de un pedido (RF-01): tipo, origen, dirección, zona,
     repartidor y costo de envío. Compartido por card móvil, tabla y kanban.
     Requiere $pedido con repartidor/zona eager-loaded. --}}
<div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 {{ $class ?? '' }}">
    @if($pedido->tipo === \App\Models\PedidoDelivery::TIPO_TAKE_AWAY)
        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-violet-100 text-violet-800 dark:bg-violet-900/50 dark:text-violet-200">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            {{ __('Para llevar') }}
        </span>
    @else
        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-200">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            {{ __('Delivery') }}
        </span>
    @endif

    @if($pedido->origen !== \App\Models\PedidoDelivery::ORIGEN_PANEL)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $pedido->origen === 'tienda' ? 'bg-pink-100 text-pink-800 dark:bg-pink-900/50 dark:text-pink-200' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
            {{ __(\App\Models\PedidoDelivery::ORIGENES[$pedido->origen] ?? $pedido->origen) }}
        </span>
    @endif

    {{-- Promesa de entrega (RF-15): vencida en rojo mientras el pedido siga activo --}}
    @if($pedido->hora_pactada_at)
        @php($promesaVencida = $pedido->hora_pactada_at->isPast() && ! in_array($pedido->estado_pedido, [\App\Models\PedidoDelivery::ESTADO_ENTREGADO, \App\Models\PedidoDelivery::ESTADO_FACTURADO, \App\Models\PedidoDelivery::ESTADO_CANCELADO], true))
        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $promesaVencida ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' }}"
            title="{{ $pedido->tipo === \App\Models\PedidoDelivery::TIPO_TAKE_AWAY ? __('Listo para retirar') : __('Entrega estimada') }}: {{ $pedido->hora_pactada_at->format('d/m H:i') }}">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $pedido->hora_pactada_at->isToday() ? $pedido->hora_pactada_at->format('H:i') : $pedido->hora_pactada_at->format('d/m H:i') }}
        </span>
    @endif

    @if($pedido->tipo === \App\Models\PedidoDelivery::TIPO_DELIVERY)
        @if($pedido->direccion_entrega)
            <span class="inline-flex items-center gap-0.5 text-[11px] text-gray-600 dark:text-gray-300 min-w-0 max-w-[14rem]" title="{{ $pedido->direccion_entrega }}{{ $pedido->direccion_referencia ? ' — '.$pedido->direccion_referencia : '' }}">
                <svg class="w-3 h-3 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="truncate">{{ $pedido->direccion_entrega }}</span>
            </span>
        @endif
        @if($pedido->zona)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-200">
                {{ $pedido->zona->nombre }}
            </span>
        @endif
        @if($pedido->repartidor)
            <span class="inline-flex items-center gap-0.5 text-[11px] text-gray-600 dark:text-gray-300" title="{{ __('Repartidor') }}">
                <svg class="w-3 h-3 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                {{ $pedido->repartidor->nombre }}
            </span>
        @endif
        @if((float) $pedido->costo_envio > 0)
            <span class="inline-flex items-center text-[11px] text-gray-500 dark:text-gray-400" title="{{ __('Costo de envío') }}{{ $pedido->costo_envio_manual ? ' ('.__('manual').')' : '' }}">
                {{ __('Envío') }}: ${{ number_format($pedido->costo_envio, 2, ',', '.') }}{{ $pedido->costo_envio_manual ? '*' : '' }}
            </span>
        @endif
    @endif
</div>
