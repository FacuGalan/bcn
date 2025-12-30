<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-bcn-secondary">Gestión de Roles y Permisos</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Administra los roles y sus permisos de acceso al menú</p>
            </div>
            <button
                wire:click="create"
                class="inline-flex items-center px-4 py-2 bg-bcn-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-bcn-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Rol
            </button>
        </div>

        <!-- Búsqueda -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar rol</label>
                <input
                    type="text"
                    id="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nombre del rol..."
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
                                <h3 class="text-lg font-semibold text-bcn-secondary flex items-center">
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
                                        <span>{{ $role->users_count ?? 0 }} usuario(s)</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span>{{ $role->permissions_count ?? 0 }} permiso(s)</span>
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
                                Editar
                            </button>
                            @if($role->name !== 'Super Administrador')
                                <button
                                    wire:click="delete({{ $role->id }})"
                                    wire:confirm="¿Estás seguro de eliminar este rol?"
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
                        <p class="mt-2">No se encontraron roles</p>
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

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                                        {{ $editMode ? 'Editar Rol' : 'Nuevo Rol' }}
                                    </h3>

                                    <div class="space-y-4">
                                        <!-- Nombre del rol -->
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre del rol *</label>
                                            <input
                                                type="text"
                                                id="name"
                                                wire:model="name"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-bcn-primary focus:ring focus:ring-bcn-primary focus:ring-opacity-50"
                                                required
                                            />
                                            @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <!-- Permisos de menú -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Permisos de acceso al menú</label>
                                            <div class="max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-4">
                                                @foreach($groupedPermissions as $moduleName => $permissions)
                                                    <div class="border-b border-gray-200 dark:border-gray-700 pb-3 last:border-b-0">
                                                        <!-- Permiso padre (módulo) -->
                                                        <div class="flex items-center mb-2">
                                                            <input
                                                                type="checkbox"
                                                                id="perm_{{ $permissions['parent']->id }}"
                                                                value="{{ $permissions['parent']->id }}"
                                                                wire:model="selectedPermissions"
                                                                class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                            />
                                                            <label for="perm_{{ $permissions['parent']->id }}" class="ml-2 text-sm font-semibold text-gray-900 dark:text-white">
                                                                {{ $moduleName }}
                                                            </label>
                                                        </div>

                                                        <!-- Permisos hijos -->
                                                        @if(count($permissions['children']) > 0)
                                                            <div class="ml-6 space-y-2">
                                                                @foreach($permissions['children'] as $childPermission)
                                                                    <div class="flex items-center">
                                                                        <input
                                                                            type="checkbox"
                                                                            id="perm_{{ $childPermission->id }}"
                                                                            value="{{ $childPermission->id }}"
                                                                            wire:model="selectedPermissions"
                                                                            class="rounded border-gray-300 dark:border-gray-600 text-bcn-primary focus:ring-bcn-primary"
                                                                        />
                                                                        <label for="perm_{{ $childPermission->id }}" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                                            {{ str_replace('menu.', '', $childPermission->name) }}
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                            @error('selectedPermissions') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-bcn-primary text-base font-medium text-white hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $editMode ? 'Actualizar' : 'Crear' }}
                            </button>
                            <button
                                type="button"
                                @click="show = false; $wire.cancel()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-bcn-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
