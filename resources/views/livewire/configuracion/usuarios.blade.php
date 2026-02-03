<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3 sm:block">
                        <h2 class="text-xl sm:text-2xl font-bold text-bcn-secondary flex items-center h-10 sm:h-auto">{{ __('Gestión de Usuarios') }}</h2>
                        <!-- Botón Nuevo Usuario - Solo icono en móviles -->
                        <button
                            wire:click="create"
                            class="sm:hidden inline-flex items-center justify-center flex-shrink-0 w-10 h-10 bg-bcn-primary border border-transparent rounded-md text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                            :title="__('Crear nuevo usuario')"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los usuarios y sus roles en el sistema') }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Usuarios') }}: {{ $comercio->getCurrentUsersCount() }} {{ __('de') }} {{ $comercio->max_usuarios }}
                        @if($comercio->canAddMoreUsers())
                            <span class="text-green-600">({{ $comercio->getRemainingUsersSlots() }} {{ __('disponibles') }})</span>
                        @else
                            <span class="text-red-600">({{ __('límite alcanzado') }})</span>
                        @endif
                    </p>
                </div>
                <!-- Botón Nuevo Usuario - Desktop -->
                <button
                    wire:click="create"
                    class="hidden sm:inline-flex items-center justify-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 transition ease-in-out duration-150"
                    :title="__('Crear nuevo usuario')"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Nuevo Usuario') }}
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4 sm:mb-6">
            <!-- Botón de filtros (solo móvil) -->
            <div class="sm:hidden p-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    wire:click="toggleFilters"
                    class="w-full flex items-center justify-between text-left text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-primary transition-colors"
                >
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ __('Filtros') }}
                        @if($search || $filterStatus !== 'all' || $filterRole !== 'all')
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary text-white">
                                {{ __('Activos') }}
                            </span>
                        @endif
                    </span>
                    <svg
                        class="w-5 h-5 transition-transform {{ $showFilters ? 'rotate-180' : '' }}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Contenedor de filtros -->
            <div class="{{ $showFilters ? 'block' : 'hidden' }} sm:block p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <!-- Búsqueda -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar') }}</label>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            :placeholder="__('Nombre, usuario o email...')"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        />
                    </div>

                    <!-- Filtro de estado -->
                    <div>
                        <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Estado') }}</label>
                        <select
                            id="filterStatus"
                            wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todos') }}</option>
                            <option value="active">{{ __('Activos') }}</option>
                            <option value="inactive">{{ __('Inactivos') }}</option>
                        </select>
                    </div>

                    <!-- Filtro de rol -->
                    <div>
                        <label for="filterRole" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Rol') }}</label>
                        <select
                            id="filterRole"
                            wire:model.live="filterRole"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 text-sm"
                        >
                            <option value="all">{{ __('Todos los roles') }}</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista de Tarjetas (Móviles) -->
        <div class="sm:hidden space-y-3">
            @forelse($users as $user)
                @php
                    $roleName = $this->getUserRole($user);
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center flex-1">
                            <div class="flex-shrink-0 h-12 w-12 bg-bcn-primary bg-opacity-20 rounded-full flex items-center justify-center">
                                <span class="text-bcn-secondary font-semibold text-sm">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </span>
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ '@' . $user->username }}</div>
                                @if($roleName)
                                    <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-bcn-primary bg-opacity-20 text-bcn-secondary">
                                        {{ $roleName }}
                                    </span>
                                @else
                                    <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-white">
                                        {{ __('Sin rol') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <button
                            wire:click="edit({{ $user->id }})"
                            class="ml-2 inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                            :title="__('Editar usuario')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="mt-2 text-sm">{{ __('No se encontraron usuarios') }}</p>
                </div>
            @endforelse

            <!-- Paginación Móvil -->
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>

        <!-- Tabla de usuarios (Desktop) -->
        <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-bcn-light dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Usuario') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Email') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Rol') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Estado') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Acciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-bcn-primary bg-opacity-20 rounded-full flex items-center justify-center">
                                            <span class="text-bcn-secondary font-semibold">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ '@' . $user->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $roleName = $this->getUserRole($user);
                                    @endphp
                                    @if($roleName)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-bcn-primary bg-opacity-20 text-bcn-secondary">
                                            {{ $roleName }}
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-white">
                                            {{ __('Sin rol') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        wire:click="toggleStatus({{ $user->id }})"
                                        class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $user->activo ? 'bg-green-600' : 'bg-gray-300' }}"
                                    >
                                        <span class="sr-only">{{ $user->activo ? __('Desactivar') : __('Activar') }} {{ __('usuario') }}</span>
                                        <span
                                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $user->activo ? 'translate-x-5' : 'translate-x-0' }}"
                                        ></span>
                                    </button>
                                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $user->activo ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button
                                        wire:click="edit({{ $user->id }})"
                                        class="inline-flex items-center justify-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                                        :title="__('Editar usuario')"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        {{ __('Editar') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="mt-2">{{ __('No se encontraron usuarios') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación Desktop -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar usuario -->
    @if($showModal)
        <div
            x-data="{ show: @entangle('showModal').live }"
            x-show="show"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <!-- Overlay -->
            <div class="flex items-end justify-center min-h-screen pt-4 px-2 pb-20 text-center sm:block sm:p-0">
                <div
                    @click="show = false; $wire.cancel()"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    aria-hidden="true"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full mt-3 sm:mt-0 text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                        {{ $editMode ? __('Editar Usuario') : __('Nuevo Usuario') }}
                                    </h3>

                                    <div class="space-y-4">
                                        <!-- Grid de 2 columnas para datos básicos -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Columna 1 -->
                                            <div class="space-y-4">
                                                <!-- Nombre -->
                                                <div>
                                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre completo') }} *</label>
                                                    <input
                                                        type="text"
                                                        id="name"
                                                        wire:model="name"
                                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        required
                                                    />
                                                    @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Username -->
                                                <div>
                                                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Nombre de usuario') }} *</label>
                                                    <input
                                                        type="text"
                                                        id="username"
                                                        wire:model="username"
                                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        required
                                                    />
                                                    @error('username') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Email y Teléfono en una fila -->
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }} *</label>
                                                        <input
                                                            type="email"
                                                            id="email"
                                                            wire:model="email"
                                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                            required
                                                        />
                                                        @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                    </div>
                                                    <div>
                                                        <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Teléfono') }}</label>
                                                        <input
                                                            type="tel"
                                                            id="telefono"
                                                            wire:model="telefono"
                                                            :placeholder="__('Ej: +54 11 1234-5678')"
                                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        />
                                                        @error('telefono') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>

                                                <!-- Rol -->
                                                <div>
                                                    <label for="roleId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Rol') }}</label>
                                                    <select
                                                        id="roleId"
                                                        wire:model="roleId"
                                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    >
                                                        <option value="">{{ __('Sin rol asignado') }}</option>
                                                        @foreach($roles as $role)
                                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('roleId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <!-- Columna 2 -->
                                            <div class="space-y-4">
                                                <!-- Contraseña Visible (Solo si el usuario logueado es Super Administrador) -->
                                                @if($currentUserIsSuperAdmin && $editMode)
                                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-md p-3">
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                            </svg>
                                                            {{ __('Contraseña Actual') }}
                                                        </label>
                                                        <div class="flex items-center space-x-2">
                                                            <input
                                                                type="text"
                                                                value="{{ $passwordVisible ?: __('No disponible') }}"
                                                                readonly
                                                                class="flex-1 rounded-md border-gray-300 bg-yellow-50 dark:bg-yellow-900/30 text-gray-700 dark:text-gray-300 shadow-sm font-mono text-sm"
                                                            />
                                                            <button
                                                                type="button"
                                                                onclick="navigator.clipboard.writeText('{{ $passwordVisible }}')"
                                                                class="px-3 py-2 bg-yellow-100 dark:bg-yellow-800 border border-yellow-300 dark:border-yellow-600 rounded-md hover:bg-yellow-200 dark:hover:bg-yellow-700 transition-colors"
                                                                :title="__('Copiar contraseña')"
                                                            >
                                                                <svg class="w-4 h-4 text-yellow-700 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Contraseña -->
                                                <div>
                                                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ $editMode ? __('Nueva Contraseña') : __('Contraseña') . ' *' }}
                                                    </label>
                                                    <input
                                                        type="password"
                                                        id="password"
                                                        wire:model="password"
                                                        autocomplete="new-password"
                                                        :placeholder="__('{{ $editMode ? 'Dejar en blanco para no cambiar' : '' }}')"
                                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                        {{ $editMode ? '' : 'required' }}
                                                    />
                                                    @error('password') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Confirmar contraseña -->
                                                <div>
                                                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirmar contraseña') }}</label>
                                                    <input
                                                        type="password"
                                                        id="password_confirmation"
                                                        wire:model="password_confirmation"
                                                        autocomplete="new-password"
                                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Switch Usuario Activo (arriba de permisos) -->
                                        <div class="flex items-center justify-between py-3 px-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Usuario Activo') }}</span>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="$set('activo', !$activo)"
                                                class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary {{ $activo ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }}"
                                                role="switch"
                                                aria-checked="{{ $activo ? 'true' : 'false' }}"
                                            >
                                                <span class="sr-only">{{ __('Usuario activo') }}</span>
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200 {{ $activo ? 'translate-x-5' : 'translate-x-0' }}"
                                                ></span>
                                            </button>
                                        </div>

                                        <!-- Sucursales y Cajas en grid de 2 columnas (Solo para Super Admin) -->
                                        @if($currentUserIsSuperAdmin)
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <!-- Sucursales -->
                                                <div class="bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded-md p-4">
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        <svg class="w-4 h-4 inline mr-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                        </svg>
                                                        {{ __('Sucursales con Acceso') }}
                                                    </label>
                                                    <p class="text-xs text-blue-700 dark:text-blue-300 mb-3">
                                                        {{ __('Si no seleccionas ninguna, tendrá acceso a todas.') }}
                                                    </p>
                                                    <div class="space-y-2 max-h-64 overflow-y-auto">
                                                        @foreach($sucursales as $sucursal)
                                                            <label class="flex items-center p-2 hover:bg-blue-100 dark:hover:bg-gray-600 rounded cursor-pointer transition-colors">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model.live="selectedSucursales"
                                                                    value="{{ $sucursal->id }}"
                                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                                />
                                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 flex-1">
                                                                    {{ $sucursal->nombre }}
                                                                    <span class="text-xs text-gray-500">({{ $sucursal->codigo }})</span>
                                                                    @if($sucursal->es_principal)
                                                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 text-xs font-medium text-indigo-800 bg-indigo-100 rounded">
                                                                            {{ __('Principal') }}
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                                                        @if(count($selectedSucursales) > 0)
                                                            {{ count($selectedSucursales) }} {{ __('sucursal(es) seleccionada(s)') }}
                                                        @else
                                                            {{ __('Acceso a todas las sucursales') }}
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Cajas -->
                                                <div class="bg-indigo-50 dark:bg-gray-700 border border-indigo-200 dark:border-gray-600 rounded-md p-4">
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        <svg class="w-4 h-4 inline mr-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                        {{ __('Cajas con Acceso') }}
                                                    </label>
                                                    <p class="text-xs text-indigo-700 dark:text-indigo-300 mb-3">
                                                        {{ __('Si no seleccionas ninguna, tendrá acceso a todas.') }}
                                                    </p>

                                                    @php
                                                        // Si no hay sucursales seleccionadas, mostrar todas
                                                        $sucursalesParaCajas = !empty($selectedSucursales) ? $selectedSucursales : $sucursales->pluck('id')->toArray();
                                                        $todasLasCajas = $this->getCajas();
                                                    @endphp

                                                    <div class="space-y-3 max-h-64 overflow-y-auto">
                                                        @forelse($sucursalesParaCajas as $sucursalId)
                                                            @php
                                                                $sucursal = $sucursales->firstWhere('id', $sucursalId);
                                                                $cajasSucursal = $todasLasCajas->get($sucursalId, collect());
                                                            @endphp

                                                            @if($sucursal && $cajasSucursal->isNotEmpty())
                                                                <div class="bg-white dark:bg-gray-800 border border-indigo-300 dark:border-gray-600 rounded-md p-3">
                                                                    <div class="text-xs font-semibold text-indigo-900 dark:text-white mb-2 flex items-center">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                                        </svg>
                                                                        {{ $sucursal->nombre }}
                                                                    </div>
                                                                    <div class="space-y-1">
                                                                        @foreach($cajasSucursal as $caja)
                                                                            <label class="flex items-center p-1.5 hover:bg-indigo-50 dark:hover:bg-gray-600 rounded cursor-pointer transition-colors">
                                                                                @php $sucursalIdKey = (string)$sucursalId; @endphp
                                                                                <input
                                                                                    type="checkbox"
                                                                                    wire:click="toggleCaja({{ $sucursalId }}, {{ $caja->id }})"
                                                                                    @checked(isset($selectedCajas[$sucursalIdKey]) && is_array($selectedCajas[$sucursalIdKey]) && in_array($caja->id, $selectedCajas[$sucursalIdKey]))
                                                                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                                                                />
                                                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300 flex-1 flex items-center justify-between">
                                                                                    <span>{{ $caja->nombre }}</span>
                                                                                    @if($caja->estado === 'abierta')
                                                                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 text-xs font-medium text-green-800 bg-green-100 rounded-full">
                                                                                            {{ __('Abierta') }}
                                                                                        </span>
                                                                                    @endif
                                                                                </span>
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                    @php
                                                                        $sucursalIdKeyCount = (string)$sucursalId;
                                                                        $cajasSeleccionadasEnSucursal = isset($selectedCajas[$sucursalIdKeyCount]) && is_array($selectedCajas[$sucursalIdKeyCount]) ? count($selectedCajas[$sucursalIdKeyCount]) : 0;
                                                                    @endphp
                                                                    @if($cajasSeleccionadasEnSucursal > 0)
                                                                        <div class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">
                                                                            {{ $cajasSeleccionadasEnSucursal }} {{ __('caja(s) seleccionada(s)') }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @elseif($sucursal && $cajasSucursal->isEmpty())
                                                                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md p-3">
                                                                    <div class="text-xs font-semibold text-amber-900 dark:text-amber-400 flex items-center">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                                        </svg>
                                                                        {{ $sucursal->nombre }} - {{ __('Sin cajas activas') }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @empty
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">
                                                                {{ __('Selecciona al menos una sucursal para ver las cajas disponibles') }}
                                                            </div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editMode ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                @click="show = false; $wire.cancel()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
