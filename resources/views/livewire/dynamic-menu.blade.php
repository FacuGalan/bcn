{{--
    Menú Dinámico Multi-Nivel

    Desktop: Menú horizontal + Banda de submenu
    Mobile: Hamburguesa + Acordeón

    OPTIMIZADO V2: Todo el HTML pre-renderizado, solo x-show para mostrar/ocultar (instantáneo)
--}}

<!-- DEBUG: activeParentId={{ $activeParentId ?? 'NULL' }} route={{ request()->route()?->getName() ?? 'unknown' }} -->
<nav
    x-data="{
        activeParentId: {{ $activeParentId ?? 'null' }},
        mobileMenuOpen: false,
        mobileExpandedParentId: null
    }"
    x-init="activeParentId = {{ $activeParentId ?? 'null' }}"
    wire:key="menu-{{ $activeParentId ?? 'null' }}"
    class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700"
>
    {{-- DESKTOP MENU --}}
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
                        <button
                            @click="activeParentId = {{ $parent->id }}"
                            :class="activeParentId === {{ $parent->id }}
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                            class="menu-item relative inline-flex items-center px-4 pt-1 border-b-2 text-sm font-medium"
                            style="{{ !$loop->last ? 'border-right: 1px solid rgb(229, 231, 235);' : '' }}"
                        >
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                            @endif
                            <span
                                class="menu-item-text whitespace-nowrap overflow-hidden"
                                :class="activeParentId === {{ $parent->id }} ? 'show' : ''"
                            >{{ $parent->nombre }}</span>
                        </button>
                    @else
                        <a
                            href="{{ $parent->getUrl() }}"
                            class="menu-item relative inline-flex items-center px-4 pt-1 border-b-2 text-sm font-medium
                                {{ $parent->isCurrentRoute()
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}"
                            style="{{ !$loop->last ? 'border-right: 1px solid rgb(229, 231, 235);' : '' }}"
                        >
                            @if($parent->icono)
                                <x-dynamic-component :component="$parent->icono" class="h-5 w-5 flex-shrink-0" />
                            @endif
                            <span class="menu-item-text whitespace-nowrap overflow-hidden {{ $parent->isCurrentRoute() ? 'show' : '' }}">{{ $parent->nombre }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Bandas de hijos PRE-RENDERIZADAS - Solo x-show para mostrar/ocultar (instantáneo) --}}
        @foreach($parentItems as $parent)
            @php $children = $allChildrenItems[$parent->id] ?? collect(); @endphp
            @if($children->isNotEmpty())
                <div
                    x-show="activeParentId === {{ $parent->id }}"
                    class="bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-700"
                >
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex space-x-6 h-12">
                            @foreach($children as $child)
                                <a
                                    href="{{ $child->getUrl() }}"
                                    class="inline-flex items-center px-3 text-sm font-medium rounded-md
                                        {{ $child->isCurrentRoute()
                                            ? 'text-indigo-600 bg-indigo-50'
                                            : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-600' }}"
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
        @endforeach
    </div>

    {{-- MOBILE MENU --}}
    <div class="md:hidden">
        <div class="flex items-center justify-between h-16 px-4">
            <span class="text-xl font-semibold text-gray-900 dark:text-white">Menú</span>
            <button
                @click="mobileMenuOpen = !mobileMenuOpen"
                type="button"
                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                <svg x-show="mobileMenuOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Panel móvil PRE-RENDERIZADO --}}
        <div
            x-show="mobileMenuOpen"
            x-cloak
            class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg"
        >
            <div class="px-2 pt-2 pb-3 space-y-1 max-h-[80vh] overflow-y-auto">
                @foreach($parentItems as $parent)
                    @php $children = $allChildrenItems[$parent->id] ?? collect(); @endphp
                    @if($parent->route_type === 'none' && $children->isNotEmpty())
                        <div class="space-y-1">
                            <button
                                @click="mobileExpandedParentId = mobileExpandedParentId === {{ $parent->id }} ? null : {{ $parent->id }}"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <div class="flex items-center">
                                    @if($parent->icono)
                                        <x-dynamic-component :component="$parent->icono" class="h-5 w-5 mr-3 text-gray-400" />
                                    @endif
                                    {{ $parent->nombre }}
                                </div>
                                <svg
                                    class="h-5 w-5 text-gray-400"
                                    :class="mobileExpandedParentId === {{ $parent->id }} ? 'rotate-180' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            {{-- Hijos PRE-RENDERIZADOS --}}
                            <div
                                x-show="mobileExpandedParentId === {{ $parent->id }}"
                                x-cloak
                                class="pl-11 pr-3 space-y-1"
                            >
                                @foreach($children as $child)
                                    <a
                                        href="{{ $child->getUrl() }}"
                                        @click="mobileMenuOpen = false"
                                        class="block px-3 py-2 rounded-md text-sm font-medium
                                            {{ $child->isCurrentRoute()
                                                ? 'text-indigo-700 bg-indigo-50'
                                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                    >{{ $child->nombre }}</a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ $parent->getUrl() }}"
                            @click="mobileMenuOpen = false"
                            class="flex items-center px-3 py-2 rounded-md text-base font-medium
                                {{ $parent->isCurrentRoute()
                                    ? 'text-indigo-700 bg-indigo-50'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
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
