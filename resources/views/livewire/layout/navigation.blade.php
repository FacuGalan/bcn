<?php

use App\Livewire\Actions\Logout;
use App\Models\MenuItem;
use Illuminate\Support\Facades\App;
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
        // Solo si NO estamos en el dashboard
        if ($this->parentItems->isNotEmpty() && !request()->routeIs('dashboard')) {
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

    public function changeLocale(string $locale): void
    {
        if (!in_array($locale, ['es', 'en', 'pt'])) {
            return;
        }

        $user = auth()->user();
        $user->locale = $locale;
        $user->save();

        App::setLocale($locale);

        $this->redirect(request()->header('Referer', route('dashboard')), navigate: true);
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        // Recalcular el padre activo en cada render para detectar cambios de ruta (wire:navigate)
        // Si estamos en el dashboard, ningún item debe estar seleccionado
        $this->activeParentId = null;
        if (!request()->routeIs('dashboard') && $this->parentItems && count($this->parentItems) > 0) {
            $this->detectActiveParent();
        }

        $childrenItems = $this->activeParentId
            ? $this->getChildrenItems($this->activeParentId)
            : collect();

        return [
            'parentItems' => $this->parentItems,
            'childrenItems' => $childrenItems,
        ];
    }
}; ?>

<div x-data="{
    mobileMenuOpen: false,
    expandedParent: null,
    activeParentId: {{ $activeParentId ?? 'null' }},
    toggleMenu() {
        this.mobileMenuOpen = !this.mobileMenuOpen;
        document.body.classList.toggle('overflow-hidden', this.mobileMenuOpen);
    },
    closeMenu() {
        this.mobileMenuOpen = false;
        document.body.classList.remove('overflow-hidden');
    },
    toggleParent(id) {
        this.expandedParent = this.expandedParent === id ? null : id;
    },
    setActiveParent(id) {
        this.activeParentId = id;
    }
}">
<nav class="bg-bcn-secondary border-b border-bcn-secondary relative z-40">
    <!-- Primary Navigation Menu -->
    <div class="px-2">
        <div class="flex justify-between h-12">
            <!-- Hamburger (móvil a la izquierda) -->
            <div class="flex items-center md:hidden">
                <button
                    @click="toggleMenu()"
                    class="inline-flex items-center justify-center p-2 rounded-md text-bcn-light hover:text-bcn-white hover:bg-bcn-secondary hover:bg-opacity-80 focus:outline-none focus:bg-bcn-secondary focus:bg-opacity-80 focus:text-bcn-white transition duration-150 ease-in-out"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <div class="flex">
                <!-- Logo (móvil a la derecha, desktop a la izquierda) -->
                <div class="shrink-0 flex items-center md:order-first order-last">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <img src="{{ asset('banner_bcn.png') }}" alt="BCN Pymes" class="block h-5 w-auto" />
                    </a>
                </div>

                <!-- Desktop Navigation Links - Menu Dinámico -->
                <div class="hidden md:flex md:ms-4">
                    @foreach($parentItems as $parent)
                        @if($parent->route_type === 'none')
                            {{-- Padre con hijos: Solo activa la banda de submenu (hijos precargados) --}}
                            <button
                                @click="setActiveParent({{ $parent->id }})"
                                class="group relative inline-flex items-center px-4 pt-1 border-b-4 text-sm font-medium transition-all duration-200"
                                :class="activeParentId === {{ $parent->id }}
                                    ? 'border-bcn-primary text-bcn-white'
                                    : 'border-transparent text-bcn-light hover:border-gray-300 hover:text-bcn-white'"
                            >
                                @if($parent->icono)
                                    <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span
                                    class="inline-block whitespace-nowrap overflow-hidden transition-all duration-200 ease-in-out"
                                    :class="activeParentId === {{ $parent->id }}
                                        ? 'max-w-xs ml-2 opacity-100'
                                        : 'max-w-0 ml-0 opacity-0 group-hover:max-w-xs group-hover:ml-2 group-hover:opacity-100'"
                                >
                                    {{ __($parent->nombre) }}
                                </span>
                            </button>
                        @else
                            {{-- Padre sin hijos: Navega directo --}}
                            <a
                                href="{{ $parent->getUrl() }}"
                                wire:navigate
                                @click="setActiveParent({{ $parent->id }})"
                                class="group relative inline-flex items-center px-4 pt-1 border-b-4 text-sm font-medium transition-all duration-200"
                                :class="activeParentId === {{ $parent->id }}
                                    ? 'border-bcn-primary text-bcn-white'
                                    : 'border-transparent text-bcn-light hover:border-gray-300 hover:text-bcn-white'"
                            >
                                @if($parent->icono)
                                    <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span
                                    class="inline-block whitespace-nowrap overflow-hidden transition-all duration-200 ease-in-out"
                                    :class="activeParentId === {{ $parent->id }}
                                        ? 'max-w-xs ml-2 opacity-100'
                                        : 'max-w-0 ml-0 opacity-0 group-hover:max-w-xs group-hover:ml-2 group-hover:opacity-100'"
                                >
                                    {{ __($parent->nombre) }}
                                </span>
                            </a>
                        @endif

                        @if(!$loop->last)
                            {{-- Separador vertical delicado y centrado --}}
                            <div class="h-5 w-0.5 bg-white/20 mx-1 self-center"></div>
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
                        <button class="inline-flex items-center gap-1 px-2 py-2 border border-transparent rounded-md text-bcn-light bg-bcn-secondary hover:text-bcn-white hover:bg-opacity-80 focus:outline-none transition ease-in-out duration-150">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        {{-- Nombre del usuario --}}
                        <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-600">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email }}</p>
                        </div>

                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Perfil') }}
                        </x-dropdown-link>

                        <!-- Language Selector -->
                        <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-600">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Idioma') }}</p>
                            <div class="flex gap-1">
                                @foreach(['es' => 'ES', 'en' => 'EN', 'pt' => 'PT'] as $code => $label)
                                    <button
                                        wire:click="changeLocale('{{ $code }}')"
                                        class="px-2 py-1 text-xs font-medium rounded transition-colors duration-150
                                            {{ app()->getLocale() === $code
                                                ? 'bg-bcn-primary text-white'
                                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- PWA Install Button (Desktop) -->
                        <div
                            x-data="{
                                canInstall: false,
                                isStandalone: false,
                                init() {
                                    this.checkInstallability();
                                    window.addEventListener('beforeinstallprompt', () => this.checkInstallability());
                                    window.addEventListener('appinstalled', () => this.checkInstallability());
                                },
                                checkInstallability() {
                                    this.$nextTick(() => {
                                        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                                        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                                        this.canInstall = !this.isStandalone && (isIOS || window.pwaInstallManager?.deferredPrompt);
                                    });
                                }
                            }"
                            x-show="canInstall"
                            x-cloak
                            data-pwa-install-container
                        >
                            <button
                                @click="window.installPWA()"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 transition duration-150 ease-in-out"
                                data-pwa-install-button
                            >
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    {{ __('Instalar App') }}
                                </span>
                            </button>
                        </div>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Cerrar Sesión') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    {{-- Banda secundaria con items hijos (Desktop) - Pre-renderizada para cada padre --}}
    @foreach($parentItems as $parent)
        @if($parent->route_type === 'none' && $this->getChildrenItems($parent->id)->isNotEmpty())
            <div
                x-show="activeParentId === {{ $parent->id }}"
                x-cloak
                class="hidden md:block bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-sm relative z-40"
            >
                <div class="px-4">
                    <div class="flex items-center h-9 gap-1">
                        @foreach($this->getChildrenItems($parent->id) as $child)
                            <a
                                href="{{ $child->getUrl() }}"
                                wire:navigate
                                class="relative inline-flex items-center px-3 py-1 text-sm font-medium rounded-full transition-all duration-150
                                    {{ $child->isCurrentRoute()
                                        ? 'text-bcn-secondary bg-bcn-primary shadow-md'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-white hover:bg-bcn-secondary hover:shadow-sm'
                                    }}"
                            >
                                @if($child->icono)
                                    <x-dynamic-component :component="$child->icono" class="h-4 w-4 mr-1.5" />
                                @endif
                                {{ __($child->nombre) }}
                            </a>

                            @if(!$loop->last)
                                <span class="text-gray-300 dark:text-gray-600 mx-1">•</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</nav>

{{-- Overlay para móvil --}}
<div
    x-show="mobileMenuOpen"
    x-transition:enter="transition-opacity ease-linear duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="closeMenu()"
    class="fixed inset-0 bg-gray-600 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 z-40 md:hidden"
    style="display: none;"
></div>

{{-- Sidebar móvil --}}
<div
    x-show="mobileMenuOpen"
    x-transition:enter="transition ease-in-out duration-200 transform"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in-out duration-200 transform"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="fixed inset-y-0 left-0 w-64 bg-bcn-white dark:bg-gray-800 shadow-xl z-50 md:hidden flex flex-col"
    style="display: none;"
>
    {{-- Header del Sidebar con información del usuario --}}
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-bcn-secondary">
        <div class="flex items-center flex-1 min-w-0">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-bcn-white truncate">
                    {{ auth()->user()->name }}
                </p>
                <p class="text-xs text-bcn-light truncate">
                    {{ auth()->user()->email }}
                </p>
            </div>
        </div>
        <button
            @click="closeMenu()"
            class="p-2 rounded-md text-bcn-light hover:text-bcn-white hover:bg-bcn-secondary hover:bg-opacity-80 flex-shrink-0"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Contenido del Sidebar (scrollable) --}}
    <div class="flex-1 overflow-y-auto px-2 py-4 space-y-1">
        @foreach($parentItems as $parent)
            @if($parent->route_type === 'none' && $this->getChildrenItems($parent->id)->isNotEmpty())
                {{-- Padre con hijos: Acordeón --}}
                <div class="space-y-1">
                    <button
                        @click="toggleParent({{ $parent->id }})"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-bcn-secondary hover:bg-bcn-light dark:hover:bg-gray-700"
                    >
                        <div class="flex items-center">
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-500 dark:text-gray-400" />
                            @endif
                            {{ __($parent->nombre) }}
                        </div>
                        <svg
                            class="h-5 w-5 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                            :class="expandedParent === {{ $parent->id }} ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Hijos del acordeón --}}
                    <div
                        x-show="expandedParent === {{ $parent->id }}"
                        x-collapse
                        class="pl-11 pr-3 space-y-1"
                    >
                        @foreach($this->getChildrenItems($parent->id) as $child)
                            <a
                                href="{{ $child->getUrl() }}"
                                wire:navigate
                                @click="closeMenu()"
                                class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200
                                    {{ $child->isCurrentRoute()
                                        ? 'text-bcn-secondary bg-bcn-primary bg-opacity-20'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-bcn-secondary hover:bg-bcn-light dark:hover:bg-gray-700'
                                    }}"
                            >
                                <div class="flex items-center">
                                    @if($child->icono)
                                        <x-dynamic-component :component="$child->icono" class="h-4 w-4 mr-2" />
                                    @endif
                                    {{ __($child->nombre) }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Padre sin hijos: Link directo --}}
                <a
                    href="{{ $parent->getUrl() }}"
                    wire:navigate
                    @click="closeMenu()"
                    class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200
                        {{ $parent->isCurrentRoute()
                            ? 'text-bcn-secondary bg-bcn-primary bg-opacity-20'
                            : 'text-gray-700 dark:text-gray-300 hover:text-bcn-secondary hover:bg-bcn-light dark:hover:bg-gray-700'
                        }}"
                >
                    @if($parent->icono)
                        <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-500 dark:text-gray-400" />
                    @endif
                    {{ __($parent->nombre) }}
                </a>
            @endif
        @endforeach
    </div>

    {{-- Selectores y opciones en el footer del sidebar --}}
    <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-bcn-light dark:bg-gray-900 p-4">
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
                @click="closeMenu()"
                class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-md hover:bg-bcn-white dark:hover:bg-gray-700 hover:text-bcn-secondary"
            >
                <svg class="h-5 w-5 mr-3 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Perfil
            </a>

            <!-- PWA Install Button (Mobile) -->
            <div
                x-data="{
                    canInstall: false,
                    isStandalone: false,
                    init() {
                        this.checkInstallability();
                        window.addEventListener('beforeinstallprompt', () => this.checkInstallability());
                        window.addEventListener('appinstalled', () => this.checkInstallability());
                    },
                    checkInstallability() {
                        this.$nextTick(() => {
                            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                            this.canInstall = !this.isStandalone && (isIOS || window.pwaInstallManager?.deferredPrompt);
                        });
                    }
                }"
                x-show="canInstall"
                x-cloak
                data-pwa-install-container
            >
                <button
                    @click="window.installPWA()"
                    class="w-full flex items-center px-3 py-2 text-sm font-medium text-bcn-primary rounded-md hover:bg-bcn-white dark:hover:bg-gray-700 hover:text-bcn-secondary"
                    data-pwa-install-button
                >
                    <svg class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {{ __('Instalar App') }}
                </button>
            </div>

            <!-- Language Selector (Mobile) -->
            <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-600 mt-1">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Idioma') }}</p>
                <div class="flex gap-1">
                    @foreach(['es' => 'ES', 'en' => 'EN', 'pt' => 'PT'] as $code => $label)
                        <button
                            wire:click="changeLocale('{{ $code }}')"
                            class="px-2 py-1 text-xs font-medium rounded transition-colors duration-150
                                {{ app()->getLocale() === $code
                                    ? 'bg-bcn-primary text-white'
                                    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <button
                wire:click="logout"
                class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-md hover:bg-bcn-white dark:hover:bg-gray-700 hover:text-bcn-secondary"
            >
                <svg class="h-5 w-5 mr-3 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                {{ __('Cerrar Sesión') }}
            </button>
        </div>
    </div>
</div>

{{-- Overlay de instalación PWA --}}
<div
    x-data="{ status: 'idle', installEventCount: 0 }"
    x-init="
        window.addEventListener('pwa-installing', () => {
            status = 'installing';
            installEventCount = 0;
        });
        window.addEventListener('appinstalled', () => {
            installEventCount++;
            if (installEventCount >= 2) {
                status = 'installed';
                setTimeout(() => status = 'idle', 5000);
            }
        });
    "
    x-show="status !== 'idle'"
    x-cloak
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[200] flex flex-col items-center justify-center bg-bcn-secondary bg-opacity-95"
>
    {{-- Flecha arriba (solo cuando está instalado) --}}
    <div
        x-show="status === 'installed'"
        x-transition:enter="ease-out duration-500"
        x-transition:enter-start="opacity-0 -translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute top-16 left-1/2 transform -translate-x-1/2"
    >
        <div class="animate-bounce">
            <svg class="w-10 h-10 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
        </div>
    </div>

    {{-- Contenido central --}}
    <div class="text-center p-8">
        {{-- Icono con transición --}}
        <div class="relative w-20 h-20 mx-auto mb-6">
            {{-- Icono descarga (instalando) --}}
            <div
                x-show="status === 'installing'"
                x-transition:leave="ease-in duration-300"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-75"
                class="absolute inset-0 flex items-center justify-center rounded-full bg-bcn-primary bg-opacity-20"
            >
                <svg class="w-10 h-10 text-bcn-primary animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </div>

            {{-- Icono tilde (instalado) --}}
            <div
                x-show="status === 'installed'"
                x-transition:enter="ease-out duration-500 delay-200"
                x-transition:enter-start="opacity-0 scale-75"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute inset-0 flex items-center justify-center rounded-full bg-green-500 bg-opacity-20"
            >
                <svg class="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>

        {{-- Título --}}
        <h2
            class="text-2xl font-bold mb-2 transition-colors duration-300"
            :class="status === 'installed' ? 'text-green-500' : 'text-white'"
            x-text="status === 'installed' ? 'Aplicación Instalada' : 'Instalando BCN Pymes'"
        ></h2>

        {{-- Texto instalando --}}
        <div
            x-show="status === 'installing'"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <p class="text-bcn-light mb-4">La aplicación se está instalando en tu dispositivo...</p>
            <div class="flex justify-center">
                <svg class="animate-spin h-8 w-8 text-bcn-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        {{-- Texto instalado --}}
        <p
            x-show="status === 'installed'"
            x-transition:enter="ease-out duration-500 delay-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="text-white text-base"
        >
            Hacé click en la notificación para abrir la App
        </p>
    </div>
</div>

{{-- Modal de instrucciones para iOS --}}
<div
    x-data="{ showIOSModal: false }"
    x-init="
        window.addEventListener('show-ios-install-modal', () => { showIOSModal = true });
        window.addEventListener('close-ios-install-modal', () => { showIOSModal = false });
    "
    x-show="showIOSModal"
    x-cloak
    class="fixed inset-0 z-[200]"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    {{-- Overlay --}}
    <div
        x-show="showIOSModal"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-bcn-secondary bg-opacity-95 transition-opacity"
        @click="showIOSModal = false"
    ></div>

    {{-- Flecha animada apuntando al botón compartir (esquina superior derecha) --}}
    <div
        x-show="showIOSModal"
        x-transition:enter="ease-out duration-500 delay-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed top-3 right-1 animate-bounce"
    >
        <svg class="w-12 h-12 text-bcn-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </div>

    {{-- Modal en la parte superior --}}
    <div class="fixed top-24 left-4 right-4 sm:left-1/2 sm:-translate-x-1/2 sm:w-full sm:max-w-sm">
        <div
            x-show="showIOSModal"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4"
            class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-5"
        >
            {{-- Botón cerrar --}}
            <button
                @click="showIOSModal = false"
                class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Header --}}
            <div class="text-center mb-4">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-bcn-primary bg-opacity-10 mb-3">
                    <svg class="h-7 w-7 text-bcn-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    Instalar BCN Pymes
                </h3>
            </div>

            {{-- Pasos --}}
            <div class="space-y-3">
                {{-- Paso 1 --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3">
                    <div class="flex items-center">
                        <span class="flex-shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-bcn-primary text-white text-xs font-bold">
                            1
                        </span>
                        <div class="ml-3 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-200">Tocá el botón <strong>Compartir</strong></span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-10">Es el ícono con una flecha hacia arriba</p>
                </div>

                {{-- Paso 2 --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3">
                    <div class="flex items-center">
                        <span class="flex-shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-bcn-primary text-white text-xs font-bold">
                            2
                        </span>
                        <div class="ml-3 flex items-center">
                            <svg class="h-5 w-5 mr-2 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-200">Buscá <strong>Agregar a inicio</strong></span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-10">También puede aparecer como "Añadir a pantalla de inicio". Si no lo ves, tocá <strong>Más...</strong> para encontrarlo.</p>
                </div>

                {{-- Paso 3 --}}
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-3">
                    <div class="flex items-center">
                        <span class="flex-shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-bcn-primary text-white text-xs font-bold">
                            3
                        </span>
                        <div class="ml-3">
                            <span class="text-sm text-gray-700 dark:text-gray-200">Tocá <strong>Agregar</strong> para confirmar</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-10">¡Listo! La app aparecerá en tu pantalla de inicio.</p>
                </div>
            </div>

            {{-- Botón entendido --}}
            <button
                @click="showIOSModal = false"
                type="button"
                class="w-full mt-4 inline-flex justify-center items-center rounded-xl bg-bcn-primary px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 transition-all"
            >
                Entendido
            </button>
        </div>
    </div>
</div>

</div>{{-- Cierre del contenedor raíz de Livewire --}}
