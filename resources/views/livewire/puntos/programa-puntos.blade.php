<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary dark:text-white">
                        {{ __('Programa de Puntos') }}
                    </h2>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Configura el programa de fidelización de tu comercio') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Configuración --}}
        @include('livewire.puntos.partials.tab-configuracion')
    </div>
</div>
