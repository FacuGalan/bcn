{{-- Filas de pedidos por aceptar (D14/RF-T26/RF-T27) — compartidas por el
     panel de la burbuja (desktop) y el bloque plegable (móvil). Requiere
     $pedidosPorAceptar y $timeoutAceptacionMin del render de PedidosDelivery.
     Las acciones repliegan el contenedor Alpine (`abierto`) para que los
     modales del componente queden al frente sin pelear z-index. --}}
@foreach($pedidosPorAceptar as $pedidoPA)
    @php
        // D14: timeout de aceptación vencido ⇒ resaltar (no se cancela solo).
        $aceptacionDemorada = $timeoutAceptacionMin > 0
            && $pedidoPA->created_at->diffInMinutes(now()) >= $timeoutAceptacionMin;
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-2 bg-white dark:bg-gray-800 rounded-md px-3 py-2 border {{ $aceptacionDemorada ? 'border-red-400 dark:border-red-600 ring-1 ring-red-300 dark:ring-red-700' : 'border-orange-200 dark:border-orange-800' }}">
        <div class="flex-1 min-w-0">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ $pedidoPA->nombre_cliente_final ?? __('Sin cliente') }}
            </span>
            @if($aceptacionDemorada)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200 animate-pulse align-middle">
                    {{ __('Demorado') }}
                </span>
            @endif
            <span class="text-xs text-gray-500 dark:text-gray-400">
                — {{ __(\App\Models\PedidoDelivery::TIPOS[$pedidoPA->tipo] ?? $pedidoPA->tipo) }}
                · {{ __(\App\Models\PedidoDelivery::ORIGENES[$pedidoPA->origen] ?? $pedidoPA->origen) }}
                · {{ $pedidoPA->created_at->diffForHumans(short: true) }}
            </span>
            {{-- RF-T26: promesa elegida por el cliente en la tienda --}}
            @if($promesaPA = $pedidoPA->promesaClienteInfo())
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold align-middle {{ $promesaPA['tipo'] === 'asap' ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $promesaPA['label'] }}
                </span>
            @endif
            @if($pedidoPA->direccion_entrega)
                <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ $pedidoPA->direccion_entrega }}</span>
            @endif
        </div>
        <span class="text-sm font-bold text-bcn-primary whitespace-nowrap">${{ number_format($pedidoPA->total_final, 2, ',', '.') }}</span>
        <div class="flex gap-1.5">
            <button type="button" wire:click="verDetalle({{ $pedidoPA->id }})" @click="abierto = false"
                class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Ver') }}
            </button>
            <button type="button" wire:click="abrirAceptar({{ $pedidoPA->id }})" @click="abierto = false"
                class="inline-flex items-center px-2.5 py-1 bg-emerald-600 rounded text-xs font-semibold text-white hover:bg-emerald-700">
                {{ __('Aceptar') }}
            </button>
            <button type="button" wire:click="abrirRechazar({{ $pedidoPA->id }})" @click="abierto = false"
                class="inline-flex items-center px-2.5 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                {{ __('Rechazar') }}
            </button>
        </div>
    </div>
@endforeach
