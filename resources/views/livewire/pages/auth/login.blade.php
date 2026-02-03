<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $showConfirmationModal = false;
    public int $sessionsToClose = 0;
    public array $sessionsInfo = [];
    public int $maxSessions = 1;
    public array $selectedSessions = [];
    public string $selectionError = '';

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $result = $this->form->authenticate();

        // Si necesita confirmación, mostrar modal
        if ($result['needsConfirmation'] ?? false) {
            $this->showConfirmationModal = true;
            $this->sessionsToClose = $result['sessionsToClose'];
            $this->sessionsInfo = $result['sessionsInfo'];
            $this->maxSessions = $result['maxSessions'];
            $this->selectedSessions = []; // Limpiar selección previa
            $this->selectionError = '';
            return;
        }

        // Si no necesita confirmación, completar el login
        Session::regenerate();

        // Si es System Admin, redirigir al selector de comercios
        if ($result['isSystemAdmin'] ?? false) {
            $this->redirect(route('comercio.selector'), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Confirma el login y cierra las sesiones seleccionadas
     */
    public function confirmLogin(): void
    {
        // Validar que se hayan seleccionado suficientes sesiones
        if (count($this->selectedSessions) < $this->sessionsToClose) {
            $this->selectionError = __('Debes seleccionar al menos') . " {$this->sessionsToClose} " .
                ($this->sessionsToClose === 1 ? __('sesión') : __('sesiones')) . ' ' . __('para cerrar') . '.';
            return;
        }

        // Completar login pasando las sesiones seleccionadas
        $result = $this->form->completeLogin($this->selectedSessions);

        Session::regenerate();

        $this->showConfirmationModal = false;
        $this->selectedSessions = [];
        $this->selectionError = '';

        // Si es System Admin, redirigir al selector de comercios
        if ($result['isSystemAdmin'] ?? false) {
            $this->redirect(route('comercio.selector'), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Cancela el proceso de login
     */
    public function cancelLogin(): void
    {
        $this->form->cancelLogin();
        $this->showConfirmationModal = false;
        $this->sessionsToClose = 0;
        $this->sessionsInfo = [];
        $this->selectedSessions = [];
        $this->selectionError = '';
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <!-- Warning Message (for closed sessions) -->
    @if (session('warning'))
        <div class="mb-4 p-4 text-sm text-yellow-800 bg-yellow-50 rounded-lg border border-yellow-200">
            {{ session('warning') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-5" x-data="{ savedEmail: localStorage.getItem('bcn_comercio_email') }" x-init="if (savedEmail) $wire.set('form.comercio_email', savedEmail)">
        <!-- Comercio Email (opcional para System Admin) -->
        <div>
            <x-input-label for="comercio_email" :value="__('Email del Comercio')" class="text-sm font-medium text-gray-700" />
            <x-text-input
                wire:model="form.comercio_email"
                id="comercio_email"
                class="block mt-1.5 w-full py-3 px-4 text-base rounded-xl border-gray-300 focus:border-bcn-primary focus:ring-bcn-primary"
                type="email"
                name="comercio_email"
                autofocus
                autocomplete="off"
                placeholder="comercio@ejemplo.com"
                x-on:change="localStorage.setItem('bcn_comercio_email', $event.target.value)" />
            <x-input-error :messages="$errors->get('form.comercio_email')" class="mt-2" />
        </div>

        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Usuario')" class="text-sm font-medium text-gray-700" />
            <x-text-input
                wire:model="form.username"
                id="username"
                class="block mt-1.5 w-full py-3 px-4 text-base rounded-xl border-gray-300 focus:border-bcn-primary focus:ring-bcn-primary"
                type="text"
                name="username"
                required
                autocomplete="username"
                placeholder="admin" />
            <x-input-error :messages="$errors->get('form.username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Contraseña')" class="text-sm font-medium text-gray-700" />
            <x-text-input
                wire:model="form.password"
                id="password"
                class="block mt-1.5 w-full py-3 px-4 text-base rounded-xl border-gray-300 focus:border-bcn-primary focus:ring-bcn-primary"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-bcn-primary shadow-sm focus:ring-bcn-primary" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Recordarme') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-bcn-secondary hover:text-bcn-primary font-medium transition-colors" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 bg-bcn-primary hover:bg-amber-500 text-bcn-secondary font-semibold text-base rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bcn-primary">
                <svg wire:loading class="animate-spin -ml-1 mr-2 h-5 w-5" xmlns="https://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove>{{ __('Iniciar Sesión') }}</span>
                <span wire:loading>{{ __('Ingresando...') }}</span>
            </button>
        </div>
    </form>

    <!-- Modal de Confirmación de Sesiones -->
    @if ($showConfirmationModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Overlay -->
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>

                <!-- Modal Panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <!-- Icon -->
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>

                            <!-- Content -->
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    {{ __('Límite de sesiones alcanzado') }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        {{ __('Has alcanzado el límite máximo de') }} <strong>{{ $maxSessions }}</strong>
                                        {{ $maxSessions === 1 ? __('sesión simultánea') : __('sesiones simultáneas') }}.
                                    </p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        {{ __('Debes seleccionar al menos') }} <strong class="text-red-600">{{ $sessionsToClose }}</strong>
                                        {{ $sessionsToClose === 1 ? __('sesión') : __('sesiones') }} {{ __('para cerrar') }}:
                                    </p>

                                    <!-- Mensaje de error de validación -->
                                    @if ($selectionError)
                                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded-md">
                                            <p class="text-xs text-red-600 font-medium">{{ $selectionError }}</p>
                                        </div>
                                    @endif

                                    <!-- Lista de sesiones activas con checkboxes -->
                                    <div class="mt-3 bg-gray-50 rounded-md p-3 max-h-64 overflow-y-auto">
                                        <p class="text-xs text-gray-600 mb-2 font-medium">
                                            {{ __('Tus sesiones activas') }} ({{ count($sessionsInfo) }}):
                                        </p>
                                        @foreach ($sessionsInfo as $session)
                                            <label class="flex items-start mb-3 pb-3 border-b border-gray-200 last:border-0 cursor-pointer hover:bg-gray-100 p-2 rounded transition">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="selectedSessions"
                                                    value="{{ $session['id'] }}"
                                                    class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                <div class="ml-3 flex-1">
                                                    <div class="flex items-center text-xs">
                                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                        <span class="font-medium text-gray-700">
                                                            {{ $session['user_agent']['browser'] ?? __('Desconocido') }} - {{ $session['user_agent']['platform'] ?? __('Desconocido') }}
                                                        </span>
                                                        @if ($session['is_current'])
                                                            <span class="ml-2 px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">{{ __('Esta sesión') }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center text-xs text-gray-500 mt-1">
                                                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                        </svg>
                                                        <span>IP: {{ $session['ip_address'] ?? 'N/A' }}</span>
                                                        <span class="mx-1">•</span>
                                                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span>{{ $session['last_activity_human'] ?? 'N/A' }}</span>
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                    <p class="text-xs text-gray-500 mt-2">
                                        <strong>{{ __('Nota') }}:</strong> {{ __('Seleccionadas') }}: <span class="font-semibold text-indigo-600">{{ count($selectedSessions) }}</span> / {{ __('Mínimo requerido') }}: <span class="font-semibold text-red-600">{{ $sessionsToClose }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            wire:click="confirmLogin"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Continuar e Ingresar') }}
                        </button>
                        <button
                            wire:click="cancelLogin"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
