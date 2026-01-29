{{--
    Componente: Caja Operativa Requerida

    Muestra un overlay bloqueador si la caja no está operativa.
    Útil para componentes que requieren una caja con turno abierto para operar.

    Uso:
    <x-caja-operativa-requerida :estado-caja="$estadoCaja">
        <!-- Contenido del componente -->
    </x-caja-operativa-requerida>

    Props:
    - estadoCaja: array con ['operativa' => bool, 'estado' => string, 'mensaje' => string, 'caja' => ?Caja]
    - rutaTurno: string (opcional) ruta para ir al turno actual, default 'cajas.turno-actual'
    - permisoTurno: string (opcional) permiso requerido para ver el botón de turno

    @see App\Services\CajaService::validarCajaOperativa()
--}}

@props([
    'estadoCaja' => ['operativa' => false, 'estado' => 'sin_caja', 'mensaje' => 'No hay caja seleccionada', 'caja' => null],
    'rutaTurno' => 'cajas.turno-actual',
    'permisoTurno' => 'cajas.ver',
])

@php
    $operativa = $estadoCaja['operativa'] ?? false;
    $estado = $estadoCaja['estado'] ?? 'sin_caja';
    $mensaje = $estadoCaja['mensaje'] ?? 'No hay caja seleccionada';
    $caja = $estadoCaja['caja'] ?? null;
    $cajaId = is_array($caja) ? ($caja['id'] ?? null) : ($caja->id ?? null);

    // Configuración según estado
    $config = match($estado) {
        'sin_caja' => [
            'titulo' => 'Sin Caja Seleccionada',
            'mensaje' => 'Selecciona una caja para comenzar a operar.',
            'icono' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'color' => 'gray',
            'accion' => 'Seleccionar una caja en la barra superior',
        ],
        'sin_acceso' => [
            'titulo' => 'Sin Acceso a la Caja',
            'mensaje' => 'No tienes permisos para operar esta caja.',
            'icono' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
            'color' => 'red',
            'accion' => 'Solicitar acceso al administrador',
        ],
        'sin_turno' => [
            'titulo' => 'Turno No Iniciado',
            'mensaje' => 'La caja no tiene un turno abierto. Abre un turno para comenzar a operar.',
            'icono' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'color' => 'amber',
            'accion' => 'Abrir turno para comenzar',
        ],
        'pausada' => [
            'titulo' => 'Caja Inactiva',
            'mensaje' => 'La caja tiene turno abierto pero se encuentra inactiva. Actívala para continuar operando.',
            'icono' => 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'color' => 'blue',
            'accion' => 'Activar la caja para continuar',
        ],
        default => [
            'titulo' => 'Caja No Disponible',
            'mensaje' => 'La caja no está disponible en este momento.',
            'icono' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'color' => 'gray',
            'accion' => 'Verificar estado de la caja',
        ],
    };

    // Usar mensaje personalizado si viene en estadoCaja, sino usar el del config
    $mensajeMostrar = ($mensaje && $mensaje !== 'No hay caja seleccionada') ? $mensaje : $config['mensaje'];

    $colorClasses = match($config['color']) {
        'red' => [
            'bg' => 'bg-red-50 dark:bg-red-900/20',
            'border' => 'border-red-200 dark:border-red-800',
            'icon' => 'text-red-500 dark:text-red-400',
            'title' => 'text-red-800 dark:text-red-200',
            'text' => 'text-red-600 dark:text-red-300',
        ],
        'amber' => [
            'bg' => 'bg-amber-50 dark:bg-amber-900/20',
            'border' => 'border-amber-200 dark:border-amber-800',
            'icon' => 'text-amber-500 dark:text-amber-400',
            'title' => 'text-amber-800 dark:text-amber-200',
            'text' => 'text-amber-600 dark:text-amber-300',
        ],
        'blue' => [
            'bg' => 'bg-blue-50 dark:bg-blue-900/20',
            'border' => 'border-blue-200 dark:border-blue-800',
            'icon' => 'text-blue-500 dark:text-blue-400',
            'title' => 'text-blue-800 dark:text-blue-200',
            'text' => 'text-blue-600 dark:text-blue-300',
        ],
        default => [
            'bg' => 'bg-gray-50 dark:bg-gray-900/20',
            'border' => 'border-gray-200 dark:border-gray-700',
            'icon' => 'text-gray-500 dark:text-gray-400',
            'title' => 'text-gray-800 dark:text-gray-200',
            'text' => 'text-gray-600 dark:text-gray-300',
        ],
    };

    // Verificar si puede ver el botón de ir al turno
    // El sistema de menú ya valida permisos, así que si el usuario está autenticado, puede acceder
    $puedeGestionarCaja = auth()->check();
@endphp

{{-- Contenido del componente (siempre renderizado pero puede estar bloqueado) --}}
<div @class([
    'contents' => $operativa,
    'relative flex-1 min-h-0 pointer-events-none select-none opacity-30 blur-[2px]' => !$operativa,
])>
    {{ $slot }}
</div>

{{-- Overlay bloqueador (z-20 para que el header y sus menús queden por encima) --}}
@if(!$operativa)
    <div class="fixed inset-x-0 top-0 bottom-0 z-20 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm">
            <div class="max-w-md w-full mx-4 {{ $colorClasses['bg'] }} {{ $colorClasses['border'] }} border-2 rounded-xl shadow-2xl p-6 transform">
                {{-- Icono --}}
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-full {{ $colorClasses['bg'] }} flex items-center justify-center">
                        <svg class="w-10 h-10 {{ $colorClasses['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $config['icono'] }}"/>
                        </svg>
                    </div>
                </div>

                {{-- Título --}}
                <h3 class="text-xl font-bold text-center {{ $colorClasses['title'] }} mb-2">
                    {{ $config['titulo'] }}
                </h3>

                {{-- Mensaje --}}
                <p class="text-center {{ $colorClasses['text'] }} mb-4">
                    {{ $mensajeMostrar }}
                </p>

                {{-- Información de la caja (si existe) --}}
                @if($caja)
                    <div class="text-center text-sm {{ $colorClasses['text'] }} mb-4 opacity-75">
                        Caja: <span class="font-medium">{{ is_array($caja) ? $caja['nombre'] : $caja->nombre }}</span>
                    </div>
                @endif

                {{-- Acciones --}}
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    {{-- Estado: Caja pausada (turno abierto pero inactiva) --}}
                    @if($estado === 'pausada' && $puedeGestionarCaja && $cajaId)
                        <button type="button"
                                wire:click="activarCaja({{ $cajaId }})"
                                class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 shadow-lg hover:shadow-xl">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Activar Caja
                        </button>
                        <a href="{{ route($rutaTurno) }}"
                           class="inline-flex items-center justify-center px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Ir a Turnos
                        </a>
                    {{-- Estado: Sin turno --}}
                    @elseif($estado === 'sin_turno' && $puedeGestionarCaja)
                        <button type="button"
                                wire:click="abrirModalApertura"
                                class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors duration-200 shadow-lg hover:shadow-xl">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Abrir Turno
                        </button>
                        <a href="{{ route($rutaTurno) }}"
                           class="inline-flex items-center justify-center px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Ir a Turnos
                        </a>
                    {{-- Estado: Sin caja seleccionada --}}
                    @elseif($estado === 'sin_caja')
                        <div class="text-center text-sm {{ $colorClasses['text'] }}">
                            <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                            </svg>
                            Usa el selector de caja en la barra superior
                        </div>
                    {{-- Estado: Sin acceso --}}
                    @elseif($estado === 'sin_acceso')
                        <div class="text-center text-sm {{ $colorClasses['text'] }}">
                            Contacta al administrador para obtener acceso
                        </div>
                    {{-- Usuario sin permisos para gestionar --}}
                    @elseif(!$puedeGestionarCaja && in_array($estado, ['sin_turno', 'pausada']))
                        <div class="text-center text-sm {{ $colorClasses['text'] }}">
                            <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Solicita que activen la caja
                        </div>
                    @endif
                </div>

                {{-- Indicador de acción sugerida --}}
                <div class="mt-4 pt-4 border-t {{ $colorClasses['border'] }}">
                    <p class="text-xs text-center {{ $colorClasses['text'] }} opacity-75">
                        {{ $config['accion'] }}
                    </p>
                </div>
            </div>
        </div>
    @endif
