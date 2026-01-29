{{-- Tab Sucursales --}}
<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Configuración de Sucursales</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Configura el logo y datos adicionales de cada sucursal
        </p>
    </div>

    {{-- Lista de Sucursales --}}
    @if($this->sucursales->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($this->sucursales as $sucursal)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 {{ $sucursalEditandoId === $sucursal->id ? 'ring-2 ring-bcn-primary' : '' }}">
                    @if($sucursalEditandoId === $sucursal->id)
                        {{-- Modo Edición --}}
                        <form wire:submit.prevent="guardarSucursal">
                            <div class="space-y-4">
                                {{-- Nombre Interno --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre Interno <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalNombre"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="Ej: Sucursal Norte"
                                    >
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Para uso interno del sistema</p>
                                    @error('sucursalNombre')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Nombre Público --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre Público
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalNombrePublico"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        placeholder="Ej: Helados Favoritos Rivadavia"
                                    >
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nombre comercial visible al público</p>
                                    @error('sucursalNombrePublico')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Dirección --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Dirección
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="sucursalDireccion"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    >
                                </div>

                                {{-- Teléfono y Email --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Teléfono
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="sucursalTelefono"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Email
                                        </label>
                                        <input
                                            type="email"
                                            wire:model="sucursalEmail"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-bcn-primary focus:ring-bcn-primary sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >
                                        @error('sucursalEmail')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Logo --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Logo
                                    </label>
                                    <div class="flex items-center gap-4">
                                        <div class="w-16 h-16 rounded-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                                            @if($sucursalLogo)
                                                <img src="{{ $sucursalLogo->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                                            @elseif($sucursal->hasLogo())
                                                <img src="{{ $sucursal->logo_url }}" alt="Logo" class="w-full h-full object-cover">
                                            @else
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <label class="cursor-pointer inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                            </svg>
                                            Cambiar
                                            <input type="file" wire:model="sucursalLogo" accept="image/*" class="hidden">
                                        </label>
                                        <div wire:loading wire:target="sucursalLogo" class="text-sm text-bcn-primary">
                                            Subiendo...
                                        </div>
                                    </div>
                                    @error('sucursalLogo')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Botones --}}
                                <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <button
                                        type="button"
                                        wire:click="cancelarEdicionSucursal"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-bcn-primary text-white text-sm font-medium rounded-md hover:bg-bcn-primary/90 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Guardar
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        {{-- Modo Vista --}}
                        <div class="flex items-start gap-4">
                            {{-- Logo --}}
                            <div class="flex-shrink-0">
                                <div class="w-20 h-20 rounded-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                                    @if($sucursal->hasLogo())
                                        <img src="{{ $sucursal->logo_url }}" alt="Logo {{ $sucursal->nombre }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $sucursal->nombre }}
                                    </h4>
                                    @if($sucursal->es_principal)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-bcn-primary/10 text-bcn-primary dark:bg-bcn-primary/20">
                                            Principal
                                        </span>
                                    @endif
                                    @if($sucursal->activa)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            Activa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                            Inactiva
                                        </span>
                                    @endif
                                </div>

                                @if($sucursal->nombre_publico)
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <span class="font-medium">Público:</span> {{ $sucursal->nombre_publico }}
                                    </p>
                                @endif

                                <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    @if($sucursal->direccion)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span class="truncate">{{ $sucursal->direccion }}</span>
                                        </div>
                                    @endif
                                    @if($sucursal->telefono)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                            <span>{{ $sucursal->telefono }}</span>
                                        </div>
                                    @endif
                                    @if($sucursal->email)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="truncate">{{ $sucursal->email }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Botones --}}
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        wire:click="editarSucursal({{ $sucursal->id }})"
                                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Editar
                                    </button>
                                    <button
                                        wire:click="abrirConfigSucursal({{ $sucursal->id }})"
                                        class="inline-flex items-center px-3 py-1.5 bg-bcn-primary/10 border border-bcn-primary/30 text-bcn-primary text-sm font-medium rounded-md hover:bg-bcn-primary/20 dark:bg-bcn-primary/20 dark:border-bcn-primary/40 dark:hover:bg-bcn-primary/30 transition-colors"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        Configurar
                                    </button>
                                    @if($sucursal->hasLogo())
                                        <button
                                            wire:click="eliminarLogoSucursal({{ $sucursal->id }})"
                                            class="inline-flex items-center px-3 py-1.5 text-red-700 bg-white border border-red-300 text-sm font-medium rounded-md hover:bg-red-50 dark:bg-gray-700 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 transition-colors"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Quitar Logo
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- Estado vacío --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No hay sucursales</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                No se encontraron sucursales configuradas.
            </p>
        </div>
    @endif
</div>
