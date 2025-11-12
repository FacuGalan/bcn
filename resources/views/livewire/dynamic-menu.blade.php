{{--
    Menú Dinámico Multi-Nivel

    Desktop: Menú horizontal + Banda de submenu
    Mobile: Hamburguesa + Acordeón
--}}

<nav class="bg-white border-b border-gray-200">
    {{-- DESKTOP MENU - Hidden on mobile --}}
    <style>
        .menu-item-text {
            display: inline-block;
            width: 0;
            margin-left: 0;
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }
        .menu-item-text.show {
            width: auto;
            margin-left: 0.5rem;
            opacity: 1;
        }
        .menu-item:hover .menu-item-text {
            width: auto;
            margin-left: 0.5rem;
            opacity: 1;
        }
    </style>
    <div class="hidden md:block">
        {{-- Barra principal con items padres --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16">
                @foreach($parentItems as $parent)
                    @if($parent->route_type === 'none')
                        {{-- Padre con hijos: Solo activa la banda de submenu --}}
                        <button
                            wire:click="setActiveParent({{ $parent->id }})"
                            class="menu-item relative inline-flex items-center px-4 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            style="{{ !$loop->last ? 'border-right: 1px solid rgb(229, 231, 235);' : '' }}; color: red !important; background-color: yellow !important;"
                        >
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                            @endif
                            <span class="menu-item-text whitespace-nowrap overflow-hidden {{ $activeParentId === $parent->id ? 'show' : '' }}">
                                {{ $parent->nombre }}
                            </span>
                        </button>
                    @else
                        {{-- Padre sin hijos: Navega directo --}}
                        <a
                            href="{{ $parent->getUrl() }}"
                            class="menu-item relative inline-flex items-center px-4 pt-1 border-b-2 text-sm font-medium transition-colors duration-200"
                            style="{{ !$loop->last ? 'border-right: 1px solid rgb(229, 231, 235);' : '' }}; color: red !important; background-color: yellow !important;"
                        >
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                            @endif
                            <span class="menu-item-text whitespace-nowrap overflow-hidden {{ $parent->isCurrentRoute() ? 'show' : '' }}">
                                {{ $parent->nombre }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Banda secundaria con items hijos --}}
        @if($childrenItems->isNotEmpty())
            <div class="bg-gray-50 border-t border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex space-x-6 h-12">
                        @foreach($childrenItems as $child)
                            <a
                                href="{{ $child->getUrl() }}"
                                class="inline-flex items-center px-3 text-sm font-medium transition-colors duration-200
                                    {{ $child->isCurrentRoute()
                                        ? 'text-indigo-600 bg-indigo-50 rounded-md'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md'
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
    </div>

    {{-- MOBILE MENU - Hidden on desktop --}}
    <div class="md:hidden">
        {{-- Header móvil con botón hamburguesa --}}
        <div class="flex items-center justify-between h-16 px-4">
            <div class="flex items-center">
                <span class="text-xl font-semibold text-gray-900">Menú</span>
            </div>

            {{-- Botón hamburguesa --}}
            <button
                wire:click="toggleMobileMenu"
                type="button"
                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
            >
                <span class="sr-only">Abrir menú</span>
                @if($mobileMenuOpen)
                    <x-heroicon-o-x-mark class="block h-6 w-6" />
                @else
                    <x-heroicon-o-bars-3 class="block h-6 w-6" />
                @endif
            </button>
        </div>

        {{-- Panel móvil desplegable --}}
        <div
            x-data="{ show: @entangle('mobileMenuOpen') }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="border-t border-gray-200 bg-white shadow-lg"
            style="display: none;"
        >
            <div class="px-2 pt-2 pb-3 space-y-1 max-h-[80vh] overflow-y-auto">
                @foreach($parentItems as $parent)
                    @if($parent->route_type === 'none' && $this->getChildrenItems($parent->id)->isNotEmpty())
                        {{-- Padre con hijos: Acordeón --}}
                        <div class="space-y-1">
                            <button
                                wire:click="toggleMobileParent({{ $parent->id }})"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50"
                            >
                                <div class="flex items-center">
                                    @if($parent->icono)
                                        <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-400" />
                                    @endif
                                    {{ $parent->nombre }}
                                </div>
                                <x-heroicon-o-chevron-down
                                    class="h-5 w-5 text-gray-400 transition-transform duration-200
                                        {{ $mobileExpandedParentId === $parent->id ? 'transform rotate-180' : '' }}"
                                />
                            </button>

                            {{-- Hijos del acordeón --}}
                            @if($mobileExpandedParentId === $parent->id)
                                <div class="pl-11 pr-3 space-y-1">
                                    @foreach($this->getChildrenItems($parent->id) as $child)
                                        <a
                                            href="{{ $child->getUrl() }}"
                                            wire:click="closeMobileMenu"
                                            class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200
                                                {{ $child->isCurrentRoute()
                                                    ? 'text-indigo-700 bg-indigo-50'
                                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
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
                            wire:click="closeMobileMenu"
                            class="flex items-center px-3 py-2 rounded-md text-base font-medium transition-colors duration-200
                                {{ $parent->isCurrentRoute()
                                    ? 'text-indigo-700 bg-indigo-50'
                                    : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50'
                                }}"
                        >
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-400" />
                            @endif
                            {{ $parent->nombre }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</nav>
