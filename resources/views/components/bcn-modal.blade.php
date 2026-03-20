@props([
    'show' => false,
    'title' => '',
    'color' => 'bg-bcn-primary',
    'maxWidth' => '5xl',
    'onClose' => 'cancel',
    'submit' => null,
    'zIndex' => 'z-50',
])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div
    x-data="{
        show: false,
        dragY: 0,
        dragging: false,
        startY: 0,
        vvHeight: null,
        vvTop: 0,
        _vvHandler: null,
        close() {
            document.activeElement?.blur();
            this.cleanupViewport();
            this.show = false;
            $wire.{{ $onClose }}();
        },
        onTouchStart(e) {
            const scrollEl = this.$refs.scrollBody;
            if (scrollEl && scrollEl.scrollTop > 0) return;
            this.dragging = true;
            this.startY = e.touches[0].clientY;
        },
        onHandleStart(e) {
            this.dragging = true;
            this.startY = e.touches[0].clientY;
        },
        onTouchMove(e) {
            if (!this.dragging) return;
            this.dragY = Math.max(0, e.touches[0].clientY - this.startY);
            if (this.dragY > 0) e.preventDefault();
        },
        onTouchEnd() {
            if (!this.dragging) return;
            this.dragging = false;
            if (this.dragY > 120) { this.close(); }
            this.dragY = 0;
        },
        initViewport() {
            if (!window.visualViewport) return;
            this._vvHandler = () => {
                if (!this.show) return;
                this.vvHeight = window.visualViewport.height;
                this.vvTop = window.visualViewport.offsetTop;
            };
            window.visualViewport.addEventListener('resize', this._vvHandler);
            window.visualViewport.addEventListener('scroll', this._vvHandler);
        },
        cleanupViewport() {
            if (this._vvHandler && window.visualViewport) {
                window.visualViewport.removeEventListener('resize', this._vvHandler);
                window.visualViewport.removeEventListener('scroll', this._vvHandler);
                this._vvHandler = null;
            }
            this.vvHeight = null;
            this.vvTop = 0;
        },
        handleInputFocus(e) {
            if (!e.target.matches('input, textarea, select, [contenteditable]')) return;
            const scrollBody = this.$refs.scrollBody;
            if (!scrollBody) return;
            setTimeout(() => {
                const rect = e.target.getBoundingClientRect();
                const bodyRect = scrollBody.getBoundingClientRect();
                if (rect.bottom > bodyRect.bottom || rect.top < bodyRect.top) {
                    const targetTop = rect.top - bodyRect.top + scrollBody.scrollTop;
                    const centered = targetTop - (bodyRect.height / 3);
                    scrollBody.scrollTo({ top: Math.max(0, centered), behavior: 'smooth' });
                }
            }, 300);
        }
    }"
    x-init="$nextTick(() => { show = true; initViewport(); })"
    x-effect="document.body.classList.toggle('overflow-hidden', show)"
    x-on:remove.window="document.body.classList.remove('overflow-hidden')"
    x-on:keydown.escape.window="show && close()"
    class="fixed inset-0 {{ $zIndex }} overflow-hidden"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    <!-- Layout container -->
    <div class="fixed inset-0 flex flex-col justify-end sm:items-center sm:justify-center"
         :style="vvHeight !== null ? `height: ${vvHeight}px; top: ${vvTop}px; bottom: auto;` : ''"
    >
        <!-- Overlay -->
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="close()"
            class="fixed inset-0 bg-gray-500/75"
            aria-hidden="true"
        ></div>

        {{-- Mobile: bottom sheet | Desktop: modal centrado --}}
        <div
            class="relative z-10 w-full max-h-[92vh] sm:max-h-[90vh] flex flex-col bg-white dark:bg-gray-800 rounded-t-xl sm:rounded-lg text-left overflow-hidden shadow-xl sm:my-4 {{ $maxWidthClass }} sm:w-full"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
            x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
            :style="dragY > 0 ? `transform: translateY(${dragY}px); transition: none;` : (vvHeight !== null ? `max-height: ${vvHeight * 0.92}px;` : '')"
            @touchstart.passive="onTouchStart($event)"
            @touchmove="onTouchMove($event)"
            @touchend="onTouchEnd()"
        >
            @if($submit)
            <form wire:submit="{{ $submit }}" class="flex flex-col max-h-[92vh] sm:max-h-[90vh]">
            @else
            <div class="flex flex-col max-h-[92vh] sm:max-h-[90vh]">
            @endif

                <!-- Header -->
                <div class="{{ $color }} px-4 py-2 sm:px-6 sm:py-3 shrink-0">
                    {{-- Pill handle mobile --}}
                    <div class="sm:hidden py-1 -mt-1 mb-1 cursor-grab active:cursor-grabbing"
                         @touchstart.passive="onHandleStart($event)"
                         @touchmove.prevent="onTouchMove($event)"
                         @touchend="onTouchEnd()"
                    >
                        <div class="w-10 h-1 bg-white/40 rounded-full mx-auto"></div>
                    </div>
                    <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
                        {{ $title }}
                    </h3>
                </div>

                <!-- Body (scrolleable) -->
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 overflow-y-auto flex-1 min-h-0"
                     x-ref="scrollBody"
                     @focusin="handleInputFocus($event)"
                >
                    {{ $body }}
                </div>

                <!-- Footer fijo -->
                <div class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 shrink-0 flex flex-row justify-end gap-2 border-t border-gray-200 dark:border-gray-600">
                    {{ $footer }}
                </div>

            @if($submit)
            </form>
            @else
            </div>
            @endif
        </div>
    </div>
</div>
