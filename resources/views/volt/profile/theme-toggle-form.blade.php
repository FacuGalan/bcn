<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $darkMode = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->darkMode = Auth::user()->dark_mode ?? false;
    }

    /**
     * Toggle dark mode and save preference.
     */
    public function toggleDarkMode(): void
    {
        $this->darkMode = !$this->darkMode;

        $user = Auth::user();
        $user->dark_mode = $this->darkMode;
        $user->save();

        $this->dispatch('theme-changed', darkMode: $this->darkMode);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Apariencia') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Personaliza la apariencia de la aplicación.') }}
        </p>
    </header>

    <div class="mt-6">
        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center gap-3">
                {{-- Icono de sol/luna --}}
                <div class="flex-shrink-0">
                    @if($darkMode)
                        <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    @endif
                </div>

                <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ __('Modo Oscuro') }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $darkMode ? __('Activado') : __('Desactivado') }}
                    </p>
                </div>
            </div>

            {{-- Toggle Switch --}}
            <button
                wire:click="toggleDarkMode"
                type="button"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 {{ $darkMode ? 'bg-bcn-primary' : 'bg-gray-300' }}"
                role="switch"
                aria-checked="{{ $darkMode ? 'true' : 'false' }}"
            >
                <span class="sr-only">{{ __('Activar modo oscuro') }}</span>
                <span
                    class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $darkMode ? 'translate-x-5' : 'translate-x-0' }}"
                >
                    <span
                        class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity {{ $darkMode ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in' }}"
                        aria-hidden="true"
                    >
                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                            <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                    <span
                        class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity {{ $darkMode ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out' }}"
                        aria-hidden="true"
                    >
                        <svg class="h-3 w-3 text-bcn-primary" fill="currentColor" viewBox="0 0 12 12">
                            <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"></path>
                        </svg>
                    </span>
                </span>
            </button>
        </div>
    </div>
</section>
