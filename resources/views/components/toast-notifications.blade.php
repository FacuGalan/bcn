{{-- Sistema de notificaciones toast --}}
{{-- Safelist Tailwind (clases dinámicas usadas en JS):
    bg-emerald-600 bg-emerald-500 bg-emerald-300
    bg-red-600 bg-red-500 bg-red-300
    bg-amber-500 bg-amber-200 bg-amber-300
    bg-blue-600 bg-blue-500 bg-blue-300
    dark:bg-emerald-500 dark:bg-emerald-300
    dark:bg-red-500 dark:bg-red-300
    dark:bg-amber-500 dark:bg-amber-300
    dark:bg-blue-500 dark:bg-blue-300
--}}
<div
    x-data="{
        notifications: [],
        nextId: 1,

        show(message, type = 'success', duration = 4000) {
            const id = this.nextId++;
            this.notifications.push({ id, message, type, show: true, duration });

            setTimeout(() => this.remove(id), duration);
        },

        remove(id) {
            const n = this.notifications.find(n => n.id === id);
            if (n) {
                n.show = false;
                setTimeout(() => {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                }, 300);
            }
        },

        getConfig(type) {
            return {
                success: {
                    bg: 'bg-emerald-600 dark:bg-emerald-500',
                    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                    bar: 'bg-emerald-300 dark:bg-emerald-300'
                },
                error: {
                    bg: 'bg-red-600 dark:bg-red-500',
                    icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
                    bar: 'bg-red-300 dark:bg-red-300'
                },
                warning: {
                    bg: 'bg-amber-500 dark:bg-amber-500',
                    icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                    bar: 'bg-amber-200 dark:bg-amber-300'
                },
                info: {
                    bg: 'bg-blue-600 dark:bg-blue-500',
                    icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    bar: 'bg-blue-300 dark:bg-blue-300'
                }
            }[type] || {
                bg: 'bg-blue-600 dark:bg-blue-500',
                icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                bar: 'bg-blue-300 dark:bg-blue-300'
            };
        }
    }"
    @notify.window="show($event.detail.message, $event.detail.type || 'success', $event.detail.duration || 4000)"
    @toast-success.window="show($event.detail.message, 'success')"
    @toast-error.window="show($event.detail.message, 'error')"
    @toast-warning.window="show($event.detail.message, 'warning')"
    @toast-info.window="show($event.detail.message, 'info')"
    class="fixed z-50 pointer-events-none bottom-4 inset-x-4 sm:top-4 sm:bottom-auto sm:left-auto sm:right-4 sm:inset-x-auto sm:w-full sm:max-w-sm flex flex-col-reverse sm:flex-col gap-2"
    role="region"
    aria-live="polite"
>
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="notification.show"
            x-transition:enter="transform ease-out duration-300"
            x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:-translate-y-2 sm:translate-x-4"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            :class="getConfig(notification.type).bg"
            class="pointer-events-auto w-full rounded-xl shadow-2xl overflow-hidden"
        >
            <div class="flex items-center gap-3 px-4 py-3 text-white">
                <svg class="h-5 w-5 flex-shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getConfig(notification.type).icon" />
                </svg>
                <p class="flex-1 text-sm font-medium" x-text="notification.message"></p>
                <button
                    @click="remove(notification.id)"
                    class="flex-shrink-0 p-1 -mr-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                >
                    <span class="sr-only">{{ __('Cerrar') }}</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            {{-- Barra de progreso --}}
            <div class="h-0.5 w-full bg-black/10">
                <div
                    :class="getConfig(notification.type).bar"
                    class="h-full opacity-60"
                    :style="`animation: toast-progress ${notification.duration}ms linear forwards`"
                ></div>
            </div>
        </div>
    </template>
</div>

<style>
    @keyframes toast-progress {
        from { width: 100%; }
        to { width: 0%; }
    }
</style>

{{-- Script helper para disparar notificaciones --}}
<script>
    function notify(message, type = 'success', duration = 4000) {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message, type, duration }
        }));
    }

    window.notify = notify;

    document.addEventListener('DOMContentLoaded', function() {
        @if(session()->has('notify'))
            @php $notifyData = session('notify'); @endphp
            notify(
                @json($notifyData['message'] ?? __('Operación exitosa')),
                @json($notifyData['type'] ?? 'success'),
                @json($notifyData['duration'] ?? 4000)
            );
        @endif
    });
</script>
