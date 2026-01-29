{{-- Sistema de notificaciones toast con Alpine.js --}}
<div
    x-data="{
        notifications: [],
        nextId: 1,

        show(message, type = 'success', duration = 5000) {
            const id = this.nextId++;
            const notification = {
                id,
                message,
                type, // success, error, warning, info
                show: true
            };

            this.notifications.push(notification);

            // Auto-remover después de la duración especificada
            setTimeout(() => {
                this.remove(id);
            }, duration);
        },

        remove(id) {
            const index = this.notifications.findIndex(n => n.id === id);
            if (index !== -1) {
                this.notifications[index].show = false;
                // Remover del array después de la animación
                setTimeout(() => {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                }, 300);
            }
        },

        getIcon(type) {
            const icons = {
                success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
                warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            };
            return icons[type] || icons.info;
        },

        getColors(type) {
            const colors = {
                success: {
                    bg: 'bg-green-50',
                    border: 'border-green-200',
                    icon: 'text-green-500',
                    text: 'text-green-800'
                },
                error: {
                    bg: 'bg-red-50',
                    border: 'border-red-200',
                    icon: 'text-red-500',
                    text: 'text-red-800'
                },
                warning: {
                    bg: 'bg-yellow-50',
                    border: 'border-yellow-200',
                    icon: 'text-yellow-500',
                    text: 'text-yellow-800'
                },
                info: {
                    bg: 'bg-blue-50',
                    border: 'border-blue-200',
                    icon: 'text-blue-500',
                    text: 'text-blue-800'
                }
            };
            return colors[type] || colors.info;
        }
    }"
    @notify.window="show($event.detail.message, $event.detail.type || 'success', $event.detail.duration || 5000)"
    @toast-success.window="show($event.detail.message, 'success')"
    @toast-error.window="show($event.detail.message, 'error')"
    @toast-warning.window="show($event.detail.message, 'warning')"
    @toast-info.window="show($event.detail.message, 'info')"
    class="fixed top-4 right-4 z-50 space-y-2 w-full max-w-sm pointer-events-none"
    role="region"
    aria-live="polite"
>
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="notification.show"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="getColors(notification.type).bg + ' ' + getColors(notification.type).border"
            class="pointer-events-auto w-full rounded-lg border shadow-lg p-4"
        >
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg
                        :class="getColors(notification.type).icon"
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            :d="getIcon(notification.type)"
                        />
                    </svg>
                </div>
                <div class="ml-3 w-0 flex-1">
                    <p
                        :class="getColors(notification.type).text"
                        class="text-sm font-medium"
                        x-text="notification.message"
                    ></p>
                </div>
                <div class="ml-4 flex flex-shrink-0">
                    <button
                        @click="remove(notification.id)"
                        :class="getColors(notification.type).text"
                        class="inline-flex rounded-md hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="'focus:ring-' + notification.type + '-500'"
                    >
                        <span class="sr-only">Cerrar</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- Script helper para disparar notificaciones desde cualquier parte --}}
<script>
    function notify(message, type = 'success', duration = 5000) {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message, type, duration }
        }));
    }

    // Exponer globalmente para usar desde Livewire
    window.notify = notify;

    // Mostrar notificaciones flash de sesión al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        @if(session()->has('notify'))
            @php
                $notifyData = session('notify');
            @endphp
            notify(
                @json($notifyData['message'] ?? 'Operación exitosa'),
                @json($notifyData['type'] ?? 'success'),
                @json($notifyData['duration'] ?? 5000)
            );
        @endif
    });
</script>
