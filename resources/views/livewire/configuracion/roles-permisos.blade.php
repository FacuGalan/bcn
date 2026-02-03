<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-bcn-secondary dark:text-white">{{ __('Gestión de Roles y Permisos') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('Administra los roles y sus permisos de acceso al menú') }}</p>
            </div>
            <button
                wire:click="create"
                class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Nuevo Rol') }}
            </button>
        </div>

        <!-- Búsqueda -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Buscar rol') }}</label>
                <input
                    type="text"
                    id="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Nombre del rol...') }}"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                />
            </div>
        </div>

        <!-- Tarjetas de roles -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($roles as $role)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-bcn-secondary dark:text-white flex items-center">
                                    @if($role->name === 'Super Administrador')
                                        <svg class="w-5 h-5 mr-2 text-bcn-primary" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                    {{ $role->name }}
                                </h3>
                                <div class="mt-3 space-y-2">
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <span>{{ $role->users_count ?? 0 }} {{ __('usuario(s)') }}</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span>{{ $role->permissions_count ?? 0 }} {{ __('permiso(s)') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex space-x-2">
                            <button
                                wire:click="edit({{ $role->id }})"
                                class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-bcn-primary text-sm font-medium rounded-md text-bcn-primary hover:bg-bcn-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ __('Editar') }}
                            </button>
                            @if($role->name !== 'Super Administrador')
                                <button
                                    wire:click="delete({{ $role->id }})"
                                    wire:confirm="{{ __('¿Estás seguro de eliminar este rol?') }}"
                                    class="inline-flex justify-center items-center px-3 py-2 border border-red-300 dark:border-red-600 text-sm font-medium rounded-md text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-red-500 transition-colors duration-150"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <p class="mt-2">{{ __('No se encontraron roles') }}</p>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Paginación -->
        @if($roles->hasPages())
            <div class="mt-6">
                {{ $roles->links() }}
            </div>
        @endif
    </div>

    <!-- Modal para crear/editar rol -->
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
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    @click="show = false; $wire.cancel()"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    aria-hidden="true"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-6 py-4 sm:px-8 sm:py-5">
                            <div class="w-full">
                                <div class="flex items-center gap-3 mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                                        {{ $editMode ? __('Editar Rol') : __('Nuevo Rol') }}
                                    </h3>
                                    @if($isSuperAdmin)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 whitespace-nowrap">
                                            <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                            </svg>
                                            {{ __('Usuarios y Roles y Permisos son obligatorios') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-4">
                                    <!-- Nombre del rol -->
                                    <div class="max-w-md">
                                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Nombre del rol *') }}</label>
                                        <input
                                            type="text"
                                            id="name"
                                            wire:model="name"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50 {{ $isSuperAdmin ? 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed' : '' }}"
                                            placeholder="{{ __('Ej: Vendedor, Cajero, Supervisor...') }}"
                                            {{ $isSuperAdmin ? 'readonly' : 'required' }}
                                        />
                                        @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Grid de 2 columnas para permisos -->
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                        <!-- Permisos de menú -->
                                        <div>
                                            <label class="block text-base font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                                <svg class="w-5 h-5 inline mr-2 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                                </svg>
                                                {{ __('Acceso al Menú') }}
                                            </label>
                                                <div class="max-h-[400px] overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3 space-y-2 bg-gray-50 dark:bg-gray-700/50">
                                                    @foreach($groupedPermissions as $moduleName => $permissions)
                                                        @php
                                                            $parentIsProtected = $isSuperAdmin && in_array($permissions['parent']->name, $protectedPermissions ?? []);
                                                        @endphp
                                                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-600">
                                                            <!-- Permiso padre (módulo) - cabecera plegable -->
                                                            <div class="flex items-center p-3 cursor-pointer" @click="open = !open">
                                                                <input
                                                                    type="checkbox"
                                                                    id="perm_{{ $permissions['parent']->id }}"
                                                                    value="{{ $permissions['parent']->id }}"
                                                                    @click.stop
                                                                    @if($parentIsProtected)
                                                                        checked
                                                                        disabled
                                                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary cursor-not-allowed opacity-60"
                                                                    @else
                                                                        wire:model="selectedPermissions"
                                                                        class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                                    @endif
                                                                />
                                                                <label class="ml-2 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex-1 cursor-pointer flex items-center">
                                                                    <span class="w-2 h-2 rounded-full bg-bcn-primary mr-2"></span>
                                                                    {{ $moduleName }}
                                                                    @if($parentIsProtected)
                                                                        <svg class="w-3.5 h-3.5 text-amber-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    @endif
                                                                </label>
                                                                @if(count($permissions['children']) > 0)
                                                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                    </svg>
                                                                @endif
                                                            </div>

                                                            <!-- Permisos hijos (plegables) -->
                                                            @if(count($permissions['children']) > 0)
                                                                <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-gray-600 px-3 pb-3 pt-2 space-y-1">
                                                                    @foreach($permissions['children'] as $childPermission)
                                                                        @php
                                                                            $childIsProtected = $isSuperAdmin && in_array($childPermission->name, $protectedPermissions ?? []);
                                                                        @endphp
                                                                        <label for="perm_{{ $childPermission->id }}" class="flex items-center p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer transition-colors">
                                                                            <input
                                                                                type="checkbox"
                                                                                id="perm_{{ $childPermission->id }}"
                                                                                value="{{ $childPermission->id }}"
                                                                                @if($childIsProtected)
                                                                                    checked
                                                                                    disabled
                                                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary cursor-not-allowed opacity-60"
                                                                                @else
                                                                                    wire:model="selectedPermissions"
                                                                                    class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                                                @endif
                                                                            />
                                                                            <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">
                                                                                {{ str_replace('menu.', '', $childPermission->name) }}
                                                                            </span>
                                                                            @if($childIsProtected)
                                                                                <svg class="w-3.5 h-3.5 text-amber-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                                                                </svg>
                                                                            @endif
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @error('selectedPermissions') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                        <!-- Permisos Especiales (Funcionales) -->
                                        <div>
                                            <label class="block text-base font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                                <svg class="w-5 h-5 inline mr-2 text-bcn-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                                {{ __('Permisos Especiales') }}
                                            </label>
                                            <div class="max-h-[400px] overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3 space-y-2 bg-gray-50 dark:bg-gray-700/50">
                                                @foreach($permisosFuncionales as $grupo => $permisos)
                                                    <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-600">
                                                        <div class="flex items-center p-3 cursor-pointer" @click="open = !open">
                                                            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex-1 flex items-center">
                                                                <span class="w-2 h-2 rounded-full bg-bcn-primary mr-2"></span>
                                                                {{ $grupo }}
                                                            </h4>
                                                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </div>
                                                        <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-gray-600 px-3 pb-3 pt-2 space-y-1">
                                                            @foreach($permisos as $permiso)
                                                                <label class="flex items-start p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer transition-colors">
                                                                    <input
                                                                        type="checkbox"
                                                                        value="{{ $permiso['codigo'] }}"
                                                                        wire:model="selectedFuncPermissions"
                                                                        class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                                    />
                                                                    <div class="ml-2 flex-1">
                                                                        <span class="text-sm font-medium text-gray-900 dark:text-white block">{{ $permiso['etiqueta'] }}</span>
                                                                        @if(!empty($permiso['descripcion']))
                                                                            <span class="text-xs text-gray-500 dark:text-gray-400 block leading-tight mt-0.5">{{ $permiso['descripcion'] }}</span>
                                                                        @endif
                                                                    </div>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @error('selectedFuncPermissions') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 sm:px-8 sm:flex sm:flex-row-reverse border-t border-gray-200 dark:border-gray-600">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-sm font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto"
                            >
                                {{ $editMode ? __('Actualizar') : __('Crear') }}
                            </button>
                            <button
                                type="button"
                                @click="show = false; $wire.cancel()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto"
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
