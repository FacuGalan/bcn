<?php

use App\Livewire\Actions\Logout;
use App\Models\MenuItem;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $activeParentId = null;
    public bool $mobileMenuOpen = false;
    public ?int $mobileExpandedParentId = null;

    // Propiedades para almacenar el menú pre-cargado
    public $parentItems = [];
    public $allChildrenItems = []; // Estructura: ['parent_id' => [hijos...]]

    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    public function mount(): void
    {
        $this->loadMenuData();
    }

    /**
     * Carga todo el menú de una sola vez (padres + todos los hijos)
     * Usa caché para evitar consultas repetidas a la BD
     */
    protected function loadMenuData(): void
    {
        $cacheKey = 'menu_full_' . auth()->id() . '_' . session('sucursal_activa_id');

        $menuData = cache()->remember($cacheKey, 3600, function () {
            $parents = auth()->user()->getAllowedMenuItems();
            $allChildren = [];

            foreach ($parents as $parent) {
                if ($parent->route_type === 'none') {
                    $allChildren[$parent->id] = auth()->user()->getAllowedChildrenMenuItems($parent);
                }
            }

            return [
                'parents' => $parents,
                'children' => $allChildren,
            ];
        });

        $this->parentItems = $menuData['parents'];
        $this->allChildrenItems = $menuData['children'];

        // Detectar qué padre debe estar activo según la ruta actual
        if ($this->parentItems->isNotEmpty()) {
            $this->activeParentId = $this->parentItems->first()->id;
            $this->detectActiveParent();
        }
    }

    /**
     * Detecta qué padre debe estar activo según la ruta actual
     */
    protected function detectActiveParent(): void
    {
        foreach ($this->parentItems as $parent) {
            $children = $this->allChildrenItems[$parent->id] ?? collect();

            foreach ($children as $child) {
                if ($child->isCurrentRoute()) {
                    $this->activeParentId = $parent->id;
                    return;
                }
            }

            if ($parent->isCurrentRoute()) {
                $this->activeParentId = $parent->id;
                return;
            }
        }
    }

    /**
     * Maneja el cambio de sucursal
     * Limpia el caché y recarga el menú
     */
    public function handleSucursalChanged($sucursalId, $sucursalNombre): void
    {
        // Limpiar caché del menú
        $cacheKey = 'menu_full_' . auth()->id() . '_' . session('sucursal_activa_id');
        cache()->forget($cacheKey);

        // Recargar todo el menú
        $this->loadMenuData();
    }

    /**
     * Obtiene los hijos de un padre desde la data pre-cargada
     */
    public function getChildrenItems(int $parentId)
    {
        return $this->allChildrenItems[$parentId] ?? collect();
    }

    public function setActiveParent(int $parentId): void
    {
        $this->activeParentId = $parentId;
    }

    public function toggleMobileMenu(): void
    {
        $this->mobileMenuOpen = !$this->mobileMenuOpen;
    }

    public function toggleMobileParent(int $parentId): void
    {
        if ($this->mobileExpandedParentId === $parentId) {
            $this->mobileExpandedParentId = null;
        } else {
            $this->mobileExpandedParentId = $parentId;
        }
    }

    public function closeMobileMenu(): void
    {
        $this->mobileMenuOpen = false;
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        $childrenItems = $this->activeParentId
            ? $this->getChildrenItems($this->activeParentId)
            : collect();

        return [
            'parentItems' => $this->parentItems,
            'childrenItems' => $childrenItems,
        ];
    }
}; ?>

<div>
<nav x-data="{ open: false }" class="bg-bcn-secondary border-b border-bcn-secondary">
    <!-- Primary Navigation Menu -->
    <div class="px-2">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-12 w-auto" />
                    </a>
                </div>

                <!-- Desktop Navigation Links - Menu Dinámico -->
                <div class="hidden md:flex md:ms-10">
                    @foreach($parentItems as $parent)
                        @if($parent->route_type === 'none')
                            {{-- Padre con hijos: Solo activa la banda de submenu (hijos precargados) --}}
                            <button
                                wire:click="setActiveParent({{ $parent->id }})"
                                class="group relative inline-flex items-center px-4 pt-1 border-b-4 text-sm font-medium transition-all duration-300
                                    {{ $activeParentId === $parent->id
                                        ? 'border-bcn-primary text-bcn-white'
                                        : 'border-transparent text-bcn-light hover:border-gray-300 hover:text-bcn-white'
                                    }}"
                            >
                                @if($parent->icono)
                                    <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span class="inline-block whitespace-nowrap overflow-hidden transition-all duration-300 ease-in-out
                                    {{ $activeParentId === $parent->id
                                        ? 'max-w-xs ml-2 opacity-100'
                                        : 'max-w-0 ml-0 opacity-0 group-hover:max-w-xs group-hover:ml-2 group-hover:opacity-100'
                                    }}">
                                    {{ $parent->nombre }}
                                </span>
                            </button>
                        @else
                            {{-- Padre sin hijos: Navega directo --}}
                            <a
                                href="{{ $parent->getUrl() }}"
                                wire:navigate
                                class="group relative inline-flex items-center px-4 pt-1 border-b-4 text-sm font-medium transition-all duration-300
                                    {{ $parent->isCurrentRoute()
                                        ? 'border-bcn-primary text-bcn-white'
                                        : 'border-transparent text-bcn-light hover:border-gray-300 hover:text-bcn-white'
                                    }}"
                            >
                                @if($parent->icono)
                                    <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span class="inline-block whitespace-nowrap overflow-hidden transition-all duration-300 ease-in-out
                                    {{ $parent->isCurrentRoute()
                                        ? 'max-w-xs ml-2 opacity-100'
                                        : 'max-w-0 ml-0 opacity-0 group-hover:max-w-xs group-hover:ml-2 group-hover:opacity-100'
                                    }}">
                                    {{ $parent->nombre }}
                                </span>
                            </a>
                        @endif

                        @if(!$loop->last)
                            {{-- Separador vertical delicado y centrado --}}
                            <div class="h-7 w-0.5 bg-white/20 mx-1 self-center"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:ms-6 md:gap-3">
                <!-- Selector de Sucursal -->
                <livewire:sucursal-selector />

                <!-- Selector de Caja -->
                <livewire:caja-selector :key="'caja-selector-' . (session('sucursal_id') ?? 'default')" />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-bcn-light bg-bcn-secondary hover:text-bcn-white hover:bg-opacity-80 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center md:hidden">
                <button
                    wire:click="toggleMobileMenu"
                    class="inline-flex items-center justify-center p-2 rounded-md text-bcn-light hover:text-bcn-white hover:bg-bcn-secondary hover:bg-opacity-80 focus:outline-none focus:bg-bcn-secondary focus:bg-opacity-80 focus:text-bcn-white transition duration-150 ease-in-out"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Banda secundaria con items hijos (Desktop) --}}
    @if($childrenItems->isNotEmpty())
        <div class="hidden md:block bg-bcn-light border-t border-gray-200">
            <div class="px-2">
                <div class="flex space-x-6 h-12">
                    @foreach($childrenItems as $child)
                        <a
                            href="{{ $child->getUrl() }}"
                            wire:navigate
                            class="inline-flex items-center px-3 text-sm font-medium transition-colors duration-200
                                {{ $child->isCurrentRoute()
                                    ? 'text-bcn-secondary bg-bcn-primary bg-opacity-20 rounded-md'
                                    : 'text-gray-700 hover:text-bcn-secondary hover:bg-bcn-primary hover:bg-opacity-10 rounded-md'
                                }}"
                        >
                            @if($child->icono)
                                <x-dynamic-component :component="$child->icono" class="h-4 w-4 mr-1.5" />
                            @endif
                            {{ $child->nombre }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</nav>

{{-- Overlay para móvil --}}
<div
    x-data="{ show: @entangle('mobileMenuOpen').live }"
    x-show="show"
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="$wire.closeMobileMenu()"
    class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40 md:hidden"
    style="display: none;"
></div>

{{-- Sidebar móvil --}}
<div
    x-data="{ show: @entangle('mobileMenuOpen').live }"
    x-show="show"
    x-transition:enter="transition ease-in-out duration-300 transform"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in-out duration-300 transform"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="fixed inset-y-0 left-0 w-64 bg-bcn-white shadow-xl z-50 md:hidden overflow-y-auto"
    style="display: none;"
>
    {{-- Header del Sidebar --}}
    <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-bcn-secondary">
        <div class="flex items-center">
            <x-application-logo class="block h-8 w-auto" />
        </div>
        <button
            wire:click="closeMobileMenu"
            class="p-2 rounded-md text-bcn-light hover:text-bcn-white hover:bg-bcn-secondary hover:bg-opacity-80"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Contenido del Sidebar --}}
    <div class="px-2 py-4 space-y-1">
        @foreach($parentItems as $parent)
            @if($parent->route_type === 'none' && $this->getChildrenItems($parent->id)->isNotEmpty())
                {{-- Padre con hijos: Acordeón --}}
                <div class="space-y-1">
                    <button
                        wire:click="toggleMobileParent({{ $parent->id }})"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-bcn-secondary hover:bg-bcn-light"
                    >
                        <div class="flex items-center">
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-500" />
                            @endif
                            {{ $parent->nombre }}
                        </div>
                        <svg
                            class="h-5 w-5 text-gray-500 transition-transform duration-200 {{ $mobileExpandedParentId === $parent->id ? 'transform rotate-180' : '' }}"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Hijos del acordeón --}}
                    @if($mobileExpandedParentId === $parent->id)
                        <div class="pl-11 pr-3 space-y-1">
                            @foreach($this->getChildrenItems($parent->id) as $child)
                                <a
                                    href="{{ $child->getUrl() }}"
                                    wire:navigate
                                    wire:click="closeMobileMenu"
                                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200
                                        {{ $child->isCurrentRoute()
                                            ? 'text-bcn-secondary bg-bcn-primary bg-opacity-20'
                                            : 'text-gray-600 hover:text-bcn-secondary hover:bg-bcn-light'
                                        }}"
                                >
                                    <div class="flex items-center">
                                        @if($child->icono)
                                            <x-dynamic-component :component="$child->icono" class="h-4 w-4 mr-2" />
                                        @endif
                                        {{ $child->nombre }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                {{-- Padre sin hijos: Link directo --}}
                <a
                    href="{{ $parent->getUrl() }}"
                    wire:navigate
                    wire:click="closeMobileMenu"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200
                        {{ $parent->isCurrentRoute()
                            ? 'text-bcn-secondary bg-bcn-primary bg-opacity-20'
                            : 'text-gray-700 hover:text-bcn-secondary hover:bg-bcn-light'
                        }}"
                >
                    @if($parent->icono)
                        <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-500" />
                    @endif
                    {{ $parent->nombre }}
                </a>
            @endif
        @endforeach
    </div>

    {{-- Usuario y opciones en el footer del sidebar --}}
    <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 bg-bcn-light p-4">
        <div class="flex items-center mb-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-bcn-secondary truncate">
                    {{ auth()->user()->name }}
                </p>
                <p class="text-xs text-gray-600 truncate">
                    {{ auth()->user()->email }}
                </p>
            </div>
        </div>

        {{-- Selector de Sucursal para móvil --}}
        <div class="mb-3">
            <livewire:sucursal-selector />
        </div>

        {{-- Selector de Caja para móvil --}}
        <div class="mb-3">
            <livewire:caja-selector :key="'caja-selector-mobile-' . (session('sucursal_id') ?? 'default')" />
        </div>

        <div class="space-y-1">
            <a
                href="{{ route('profile') }}"
                wire:navigate
                wire:click="closeMobileMenu"
                class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-bcn-white hover:text-bcn-secondary"
            >
                <svg class="h-5 w-5 mr-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Profile
            </a>
            <button
                wire:click="logout"
                class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-bcn-white hover:text-bcn-secondary"
            >
                <svg class="h-5 w-5 mr-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Log Out
            </button>
        </div>
    </div>
</div>

</div>{{-- Cierre del contenedor raíz de Livewire --}}
