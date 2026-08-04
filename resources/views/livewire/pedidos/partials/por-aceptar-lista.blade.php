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
    {{-- Layout en COLUMNA: los datos ocupan el ancho completo (con truncado) y
         el monto + acciones van en una fila propia abajo. En la tarjeta angosta
         de la burbuja, meter todo en una fila estrangulaba el texto hasta
         partirlo letra por letra. --}}
    <div class="bg-white dark:bg-gray-800 rounded-md px-3 py-2 border {{ $aceptacionDemorada ? 'border-red-400 dark:border-red-600 ring-1 ring-red-300 dark:ring-red-700' : 'border-orange-200 dark:border-orange-800' }}">
        <div class="flex items-center gap-1.5 min-w-0">
            <span class="flex-1 min-w-0 truncate text-sm font-semibold text-gray-900 dark:text-white">
                {{ $pedidoPA->nombre_cliente_final ?? __('Sin cliente') }}
            </span>
            @if($aceptacionDemorada)
                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200 animate-pulse">
                    {{ __('Demorado') }}
                </span>
            @endif
        </div>

        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
            {{ __(\App\Models\PedidoDelivery::TIPOS[$pedidoPA->tipo] ?? $pedidoPA->tipo) }}
            · {{ __(\App\Models\PedidoDelivery::ORIGENES[$pedidoPA->origen] ?? $pedidoPA->origen) }}
            · {{ $pedidoPA->created_at->diffForHumans(short: true) }}
        </p>

        {{-- RF-T26: promesa elegida por el cliente en la tienda --}}
        @if($promesaPA = $pedidoPA->promesaClienteInfo())
            <span class="mt-0.5 inline-flex max-w-full items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $promesaPA['tipo'] === 'asap' ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' }}">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="truncate">{{ $promesaPA['label'] }}</span>
            </span>
        @endif

        @if($pedidoPA->direccion_entrega)
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pedidoPA->direccion_entrega }}</p>
        @endif

        <div class="mt-2 flex items-center justify-between gap-2">
            <span class="text-sm font-bold text-bcn-primary whitespace-nowrap">${{ number_format($pedidoPA->total_final, 2, ',', '.') }}</span>
            {{-- Mismos botones-icono que la columna ACCIONES del listado:
                 ojo = ver, tilde = aceptar, cruz = rechazar. --}}
            <div class="flex shrink-0 gap-1">
                <button type="button" wire:click="verDetalle({{ $pedidoPA->id }})" @click="abierto = false"
                    class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    title="{{ __('Ver detalle') }}" aria-label="{{ __('Ver detalle') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
                <button type="button" wire:click="abrirAceptar({{ $pedidoPA->id }})" @click="abierto = false"
                    class="inline-flex items-center px-2 py-1 border border-emerald-300 dark:border-emerald-600 rounded text-xs text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                    title="{{ __('Aceptar') }}" aria-label="{{ __('Aceptar') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
                <button type="button" wire:click="abrirRechazar({{ $pedidoPA->id }})" @click="abierto = false"
                    class="inline-flex items-center px-2 py-1 border border-red-300 dark:border-red-600 rounded text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30"
                    title="{{ __('Rechazar') }}" aria-label="{{ __('Rechazar') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endforeach
