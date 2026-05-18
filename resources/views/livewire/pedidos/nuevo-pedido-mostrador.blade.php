{{-- Modal Full-Screen con margen mínimo: Alta/Edición de Pedido por Mostrador --}}
{{-- En modo "cobro rapido" no renderizamos el editor full-screen — solo se
     monta el modal de desglose (_modal-pago-mixto) superpuesto sobre el
     listado de pedidos. El componente sigue cargando todos sus traits para
     reusar la logica de calculo, pero la UI visible es solo el modal.
     Wrapper <div> raiz comun para garantizar el tag root que requiere
     Livewire (los modales internos pueden estar cerrados y no emitir
     ningun tag HTML). --}}
<div data-livewire-root="nuevo-pedido-mostrador">
@if($modoCobroRapido)
    @include("livewire.carrito._modal-pago-mixto")
    @include("livewire.carrito._modal-moneda-extranjera")
    @include("livewire.carrito._modal-vuelto")
@else
<div class="fixed inset-0 z-40 bg-black/40 flex items-stretch justify-center p-2 sm:p-3"
    x-data="{
        _stackId: null,
        init() {
            window._bcnModalStack = window._bcnModalStack || [];
            this._stackId = Symbol();
            window._bcnModalStack.push(this._stackId);
            document.body.classList.add('overflow-hidden');
        },
        destroy() {
            if (this._stackId && window._bcnModalStack) {
                window._bcnModalStack = window._bcnModalStack.filter(id => id !== this._stackId);
            }
            if (!(window._bcnModalStack || []).length) {
                document.body.classList.remove('overflow-hidden');
            }
        }
    }"
    @keydown.escape.window="$wire.cerrar()"
>
    <div class="w-full bg-white dark:bg-gray-900 flex flex-col overflow-hidden rounded-lg shadow-2xl">
    {{-- Header naranja --}}
    <div class="bg-bcn-primary text-white px-4 sm:px-6 py-3 flex items-center justify-between gap-3 flex-shrink-0 rounded-t-lg">
        <h2 class="text-base sm:text-lg font-bold flex items-center gap-2 flex-wrap">
            @if($modoEdicion)
                {{ __('Editar Pedido de Mostrador') }} #{{ $pedidoId }}
                @if($estadoPedidoActual)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white">
                        {{ __(ucfirst($estadoPedidoActual)) }}
                    </span>
                @endif
            @else
                {{ __('Nuevo Pedido de Mostrador') }}
            @endif
        </h2>
        <button type="button" wire:click="cerrar" class="text-white/80 hover:text-white flex-shrink-0"
            title="{{ __('Cerrar') }} (Esc)">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Cuerpo: layout 2 columnas --}}
    <div class="flex-1 overflow-hidden flex flex-col lg:flex-row gap-3 p-3 sm:p-4 min-h-0">
        {{-- Columna izquierda: búsqueda (siempre) + toggle Detalle/Táctil --}}
        <div class="flex-1 flex flex-col gap-2 min-h-0 lg:min-w-0"
            x-data="{
                tactil: @entangle('panelTactilAbierto').live,
                catalogo: @js($catalogoTactil),
                categoriaSel: null,
                init() {
                    // Preseleccionar la primera categoría disponible
                    if (this.catalogo.length > 0) {
                        this.categoriaSel = this.catalogo[0].id;
                    }
                },
                categoriaActual() {
                    if (!this.categoriaSel) return null;
                    return this.catalogo.find(c => c.id === this.categoriaSel) || null;
                },
                articulosCategoria() {
                    const cat = this.categoriaActual();
                    return cat ? cat.articulos : [];
                },
                seleccionar(id) {
                    $wire.seleccionarArticulo(id);
                    $nextTick(() => $dispatch('focus-busqueda'));
                }
            }"
            @keydown.window.ctrl.b.prevent="tactil = !tactil"
        >
            {{-- Búsqueda / scan (siempre visible) --}}
            @include('livewire.carrito._busqueda-articulos')

            {{-- Toggle Panel táctil / Detalle (panel táctil primero) --}}
            <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-1 flex gap-1">
                <button type="button" @click="tactil = true"
                    :class="tactil ? 'bg-bcn-primary text-white shadow' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="flex-1 inline-flex justify-center items-center px-3 py-1.5 rounded text-xs font-medium transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    {{ __('Panel táctil') }}
                    <kbd class="hidden sm:inline ml-1.5 px-1 py-0 text-[9px] bg-black/20 rounded">Ctrl+B</kbd>
                </button>
                <button type="button" @click="tactil = false"
                    :class="!tactil ? 'bg-bcn-primary text-white shadow' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="flex-1 inline-flex justify-center items-center px-3 py-1.5 rounded text-xs font-medium transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    {{ __('Detalle') }}
                </button>
            </div>

            {{-- Vista DETALLE (con promociones aplicadas debajo) --}}
            <div x-show="!tactil" x-transition.opacity class="flex-1 flex flex-col gap-2 min-h-0">
                @include('livewire.carrito._detalle-items')
                @include('livewire.carrito._promociones-aplicadas')
            </div>

            {{-- Vista PANEL TÁCTIL: categorías a la izquierda + grilla artículos a la derecha --}}
            <div x-show="tactil" x-transition.opacity
                class="flex-1 flex flex-row min-h-0 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">

                {{-- Columna lateral: categorías con scroll vertical e indicadores estilo header --}}
                <div
                    x-data="{
                        canScrollUp: false,
                        canScrollDown: false,
                        autoScrollFrame: null,
                        autoScrollDelta: 0,
                        init() {
                            this.$nextTick(() => this.updateScrollState());
                            this.$refs.catScroller.addEventListener('scroll', () => this.updateScrollState(), { passive: true });
                            new ResizeObserver(() => this.updateScrollState()).observe(this.$refs.catScroller);
                        },
                        updateScrollState() {
                            const el = this.$refs.catScroller;
                            if (!el) return;
                            this.canScrollUp = el.scrollTop > 0;
                            this.canScrollDown = el.scrollTop < (el.scrollHeight - el.clientHeight - 1);
                        },
                        handleMouseMove(e) {
                            const el = this.$refs.catScroller;
                            const rect = el.getBoundingClientRect();
                            const y = e.clientY - rect.top;
                            const edge = 50;
                            const max = 8;
                            if (y < edge && this.canScrollUp) {
                                this.autoScrollDelta = -max * (1 - y / edge);
                                this.startAutoScroll();
                            } else if (y > rect.height - edge && this.canScrollDown) {
                                this.autoScrollDelta = max * (1 - (rect.height - y) / edge);
                                this.startAutoScroll();
                            } else {
                                this.stopAutoScroll();
                            }
                        },
                        startAutoScroll() {
                            if (this.autoScrollFrame !== null) return;
                            const tick = () => {
                                if (this.autoScrollDelta === 0) {
                                    this.autoScrollFrame = null;
                                    return;
                                }
                                this.$refs.catScroller.scrollTop += this.autoScrollDelta;
                                this.autoScrollFrame = requestAnimationFrame(tick);
                            };
                            this.autoScrollFrame = requestAnimationFrame(tick);
                        },
                        stopAutoScroll() {
                            this.autoScrollDelta = 0;
                            if (this.autoScrollFrame !== null) {
                                cancelAnimationFrame(this.autoScrollFrame);
                                this.autoScrollFrame = null;
                            }
                        }
                    }"
                    @mousemove="handleMouseMove($event)"
                    @mouseleave="stopAutoScroll()"
                    class="relative w-[100px] flex-shrink-0 bg-gray-50 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600"
                >
                    {{-- Indicador superior de scroll --}}
                    <div
                        x-show="canScrollUp"
                        x-cloak
                        x-transition.opacity
                        class="absolute top-0 inset-x-0 flex justify-center items-start pt-1 pointer-events-none h-6 bg-gradient-to-b from-gray-50 dark:from-gray-700 to-transparent z-10"
                    >
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-300 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                        </svg>
                    </div>

                    {{-- Indicador inferior de scroll --}}
                    <div
                        x-show="canScrollDown"
                        x-cloak
                        x-transition.opacity
                        class="absolute bottom-0 inset-x-0 flex justify-center items-end pb-1 pointer-events-none h-6 bg-gradient-to-t from-gray-50 dark:from-gray-700 to-transparent z-10"
                    >
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-300 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div x-ref="catScroller"
                        class="h-full overflow-y-auto p-1.5 space-y-1.5 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar]:h-1 [scrollbar-width:thin]">
                        <template x-for="cat in catalogo" :key="cat.id">
                            <button type="button"
                                @click="categoriaSel = cat.id"
                                :class="categoriaSel === cat.id ? 'ring-2 ring-offset-1 ring-bcn-primary shadow-md' : 'opacity-90 hover:opacity-100 hover:shadow'"
                                :style="categoriaSel === cat.id
                                    ? `background-color: ${cat.color}; color: white;`
                                    : `background-color: ${cat.color}1F; color: ${cat.color};`"
                                class="relative w-full aspect-square rounded-md p-1.5 flex flex-col items-center justify-center gap-1 transition-all">
                                {{-- Contador de artículos --}}
                                <span class="absolute top-1 right-1 text-[9px] font-bold leading-none px-1.5 py-0.5 rounded-full bg-white/80 dark:bg-gray-900/70"
                                    :style="`color: ${cat.color};`"
                                    x-text="cat.articulos.length"></span>
                                {{-- Ícono Heroicon pre-renderizado (con fallback si no tiene) --}}
                                <div class="flex items-center justify-center"
                                    x-html="cat.icono_svg || `<svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M4 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 14a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z'/></svg>`">
                                </div>
                                <div class="text-[10px] font-semibold text-center leading-tight line-clamp-2"
                                    x-text="cat.nombre"></div>
                            </button>
                        </template>
                        <template x-if="catalogo.length === 0">
                            <div class="px-1 py-4 text-[10px] italic text-gray-500 text-center">
                                {{ __('Sin categorías con artículos') }}
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Grilla de artículos: foto/ícono compacto arriba + info abajo --}}
                {{-- Wrapper Alpine para indicadores de scroll + auto-scroll por hover (mismo patrón que la columna de categorías). --}}
                <div
                    x-data="{
                        canScrollUp: false,
                        canScrollDown: false,
                        autoScrollFrame: null,
                        autoScrollDelta: 0,
                        init() {
                            this.$nextTick(() => this.updateScrollState());
                            this.$refs.artScroller.addEventListener('scroll', () => this.updateScrollState(), { passive: true });
                            new ResizeObserver(() => this.updateScrollState()).observe(this.$refs.artScroller);
                            // Recalcular cuando cambia la categoría seleccionada (otra cantidad de artículos).
                            this.$watch('categoriaSel', () => this.$nextTick(() => {
                                this.$refs.artScroller.scrollTop = 0;
                                this.updateScrollState();
                            }));
                        },
                        updateScrollState() {
                            const el = this.$refs.artScroller;
                            if (!el) return;
                            this.canScrollUp = el.scrollTop > 0;
                            this.canScrollDown = el.scrollTop < (el.scrollHeight - el.clientHeight - 1);
                        },
                        handleMouseMove(e) {
                            const el = this.$refs.artScroller;
                            const rect = el.getBoundingClientRect();
                            const y = e.clientY - rect.top;
                            const edge = 60;
                            const max = 10;
                            if (y < edge && this.canScrollUp) {
                                this.autoScrollDelta = -max * (1 - y / edge);
                                this.startAutoScroll();
                            } else if (y > rect.height - edge && this.canScrollDown) {
                                this.autoScrollDelta = max * (1 - (rect.height - y) / edge);
                                this.startAutoScroll();
                            } else {
                                this.stopAutoScroll();
                            }
                        },
                        startAutoScroll() {
                            if (this.autoScrollFrame !== null) return;
                            const tick = () => {
                                if (this.autoScrollDelta === 0) {
                                    this.autoScrollFrame = null;
                                    return;
                                }
                                this.$refs.artScroller.scrollTop += this.autoScrollDelta;
                                this.autoScrollFrame = requestAnimationFrame(tick);
                            };
                            this.autoScrollFrame = requestAnimationFrame(tick);
                        },
                        stopAutoScroll() {
                            this.autoScrollDelta = 0;
                            if (this.autoScrollFrame !== null) {
                                cancelAnimationFrame(this.autoScrollFrame);
                                this.autoScrollFrame = null;
                            }
                        }
                    }"
                    @mousemove="handleMouseMove($event)"
                    @mouseleave="stopAutoScroll()"
                    class="relative flex-1 min-w-0"
                >
                    {{-- Indicador superior de scroll --}}
                    <div
                        x-show="canScrollUp"
                        x-cloak
                        x-transition.opacity
                        class="absolute top-0 inset-x-0 flex justify-center items-start pt-1 pointer-events-none h-8 bg-gradient-to-b from-white dark:from-gray-900 to-transparent z-10"
                    >
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-300 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                        </svg>
                    </div>

                    {{-- Indicador inferior de scroll --}}
                    <div
                        x-show="canScrollDown"
                        x-cloak
                        x-transition.opacity
                        class="absolute bottom-0 inset-x-0 flex justify-center items-end pb-1 pointer-events-none h-8 bg-gradient-to-t from-white dark:from-gray-900 to-transparent z-10"
                    >
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-300 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div x-ref="artScroller"
                        class="h-full overflow-y-auto p-2 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar]:h-1 [scrollbar-width:thin]">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <template x-for="art in articulosCategoria()" :key="art.id">
                            <button type="button" @click="seleccionar(art.id)"
                                class="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-600 rounded-md overflow-hidden text-left hover:border-bcn-primary hover:shadow active:scale-95 transition-all flex flex-col">
                                {{-- Foto del artículo (cuando exista) o ícono de la categoría como fallback. Aspect 4:3 para que la card no quede muy alta. --}}
                                <div class="relative aspect-[4/3] w-full flex items-center justify-center"
                                    :style="`background-color: ${categoriaActual()?.color || '#9CA3AF'}14;`">
                                    <template x-if="art.imagen_url">
                                        <img :src="art.imagen_url" :alt="art.nombre"
                                            :style="`object-position: ${art.imagen_focal || '50% 50%'};`"
                                            class="w-full h-full object-cover" />
                                    </template>
                                    <template x-if="!art.imagen_url">
                                        <div class="opacity-60"
                                            :style="`color: ${categoriaActual()?.color || '#6B7280'};`"
                                            x-html="categoriaActual()?.icono_svg || `<svg class='w-8 h-8' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'/></svg>`">
                                        </div>
                                    </template>
                                    {{-- Código como overlay en esquina inferior izquierda. Fondo oscuro semi-transparente para que se lea sobre cualquier imagen. --}}
                                    <template x-if="art.codigo">
                                        <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold text-white bg-black/60 backdrop-blur-sm leading-none tracking-wide"
                                            x-text="art.codigo"></span>
                                    </template>
                                </div>
                                {{-- Info compacta abajo --}}
                                <div class="px-1.5 py-1 flex-shrink-0">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white truncate leading-tight" x-text="art.nombre"></div>
                                    <div class="flex items-center justify-between gap-1 mt-0.5">
                                        {{-- Badges: pesable y/o tiene opcionales. Reemplazan al código (ahora en overlay). --}}
                                        <div class="flex items-center gap-1 min-h-[14px]">
                                            <template x-if="art.es_pesable">
                                                <span class="inline-flex items-center text-[9px] text-amber-700 dark:text-amber-400 font-semibold leading-none"
                                                    title="{{ __('Pesable') }}">⚖️</span>
                                            </template>
                                            <template x-if="art.tiene_opcionales">
                                                <span class="inline-flex items-center text-blue-700 dark:text-blue-400 leading-none"
                                                    title="{{ __('Con opcionales') }}">
                                                    <x-heroicon-o-adjustments-horizontal class="w-3 h-3" />
                                                </span>
                                            </template>
                                        </div>
                                        <span class="text-xs font-bold text-bcn-primary whitespace-nowrap" x-text="'$' + Number(art.precio).toLocaleString('es-AR', { minimumFractionDigits: 2 })"></span>
                                    </div>
                                </div>
                            </button>
                        </template>
                        <template x-if="articulosCategoria().length === 0">
                            <div class="col-span-full py-8 text-center text-sm text-gray-500 dark:text-gray-400 italic">
                                {{ __('Seleccioná una categoría para ver los artículos') }}
                            </div>
                        </template>
                    </div>
                    </div>{{-- /artScroller --}}
                </div>{{-- /wrapper Alpine de artículos --}}
            </div>{{-- /panel táctil flex-row --}}
        </div>{{-- /columna izquierda del modal --}}

        {{-- Columna derecha: contenido scrolleable + footer fijo --}}
        <div class="w-full lg:w-96 lg:flex-shrink-0 flex flex-col min-h-0 gap-2">
            {{-- Contenido scrolleable. Inputs compactos al estilo NuevaVenta. --}}
            <div class="flex-1 overflow-y-auto space-y-2 pr-1 min-h-0">
                {{-- Cliente (reutilizado) --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                    @include('livewire.carrito._busqueda-cliente')

                    @unless($clienteSeleccionado)
                        {{-- Cliente temporal (RF-17): nombre+teléfono en una sola fila --}}
                        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1.5">
                            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('O datos temporales') }}
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <input type="text" wire:model.live.debounce.300ms="nombreClienteTemporal"
                                    placeholder="{{ __('Nombre') }}"
                                    class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                                <input type="text" wire:model.live.debounce.300ms="telefonoClienteTemporal"
                                    placeholder="{{ __('Teléfono') }}"
                                    class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                            </div>
                            @if(trim($nombreClienteTemporal ?? '') !== '' && trim($telefonoClienteTemporal ?? '') !== '')
                                <button type="button" wire:click="abrirModalAltaClienteTemporal"
                                    class="text-[10px] text-bcn-primary hover:underline">
                                    + {{ __('Dar de alta como cliente') }}
                                </button>
                            @endif
                        </div>
                    @endunless
                </div>

                {{-- Beeper (solo si la sucursal lo usa) --}}
                @if($sucursalUsaBeepers)
                    <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2">
                        <label class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">
                            {{ __('Beeper') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="numeroBeeper" maxlength="20"
                            placeholder="{{ __('N° de beeper') }}"
                            class="block w-full pl-2 pr-2 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md" />
                    </div>
                @endif

                {{-- Lista de Precios + Forma de Pago en el mismo renglón --}}
                <div class="bg-white dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700 p-2 space-y-1.5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="listaPrecioId" class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Lista de Precios') }}</label>
                            @if(count($listasPreciosDisponibles) > 1)
                                <select id="listaPrecioId" wire:model.live="listaPrecioId"
                                    class="block w-full pl-2 pr-6 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                                    @foreach($listasPreciosDisponibles as $lista)
                                        <option value="{{ $lista['id'] }}">{{ $lista['nombre'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <div class="text-xs text-gray-700 dark:text-gray-200 py-1 truncate">
                                    {{ $listasPreciosDisponibles[0]['nombre'] ?? __('—') }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <label for="formaPagoId" class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Forma de Pago') }}</label>
                            <select wire:model.live="formaPagoId" id="formaPagoId"
                                class="block w-full pl-2 pr-6 py-1 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-bcn-primary focus:border-bcn-primary rounded-md">
                                <option value="">{{ __('Seleccionar...') }}</option>
                                @foreach($this->formasPago as $fp)
                                    <option value="{{ $fp['id'] }}">{{ $fp['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Selector de Cuotas (estilo NuevaVenta, con detalle de valor + recargo + total) --}}
                    @if($formaPagoPermiteCuotas && count($cuotasFormaPagoDisponibles) > 0)
                        <div class="relative">
                            <label class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">{{ __('Cuotas') }}</label>

                            <div wire:click="toggleCuotasSelector"
                                class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                                @if(!$cuotaSeleccionadaId)
                                    <div class="flex items-center px-2 py-1.5">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                        </div>
                                        <div class="text-center px-2"><span class="text-[10px] text-gray-400">—</span></div>
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">${{ number_format(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}</span>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                @else
                                    @php
                                        $cuotaSel = collect($cuotasFormaPagoDisponibles)->firstWhere('id', (int) $cuotaSeleccionadaId);
                                    @endphp
                                    @if($cuotaSel)
                                        <div class="flex items-center px-2 py-1.5">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuotaSel['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuotaSel['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuotaSel['recargo_porcentaje'] > 0)
                                                    <span class="text-[10px] font-medium text-red-600">+{{ $cuotaSel['recargo_porcentaje'] }}%</span>
                                                @else
                                                    <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold {{ $cuotaSel['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuotaSel['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 ml-1 transition-transform {{ $cuotasSelectorAbierto ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if($cuotasSelectorAbierto)
                                <div class="absolute z-20 w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-y-auto">
                                    <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ !$cuotaSeleccionadaId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                        <input type="radio" wire:model.live="cuotaSeleccionadaId" value="" class="sr-only">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-medium text-gray-900 dark:text-white">{{ __('1 pago') }}</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('sin financiación') }}</div>
                                        </div>
                                        <div class="text-center px-2"><span class="text-[10px] text-gray-400">—</span></div>
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">${{ number_format(($resultado['total_final'] ?? 0) + ($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}</span>
                                        </div>
                                    </label>
                                    @foreach($cuotasFormaPagoDisponibles as $cuota)
                                        <label class="flex items-center px-2 py-1.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $cuotaSeleccionadaId == $cuota['id'] ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}">
                                            <input type="radio" wire:model.live="cuotaSeleccionadaId" value="{{ $cuota['id'] }}" class="sr-only">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 dark:text-white">{{ $cuota['cantidad_cuotas'] }} {{ __('cuotas') }}</div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('de') }} ${{ number_format($cuota['valor_cuota'], 2, ',', '.') }}</div>
                                            </div>
                                            <div class="text-center px-2">
                                                @if($cuota['recargo_porcentaje'] > 0)
                                                    <span class="text-[10px] font-medium text-red-600">+{{ $cuota['recargo_porcentaje'] }}%</span>
                                                @else
                                                    <span class="text-[10px] font-medium text-green-600">0%</span>
                                                @endif
                                            </div>
                                            <div class="text-right min-w-[70px]">
                                                <span class="text-xs font-semibold {{ $cuota['recargo_porcentaje'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">${{ number_format($cuota['total_con_recargo'], 2, ',', '.') }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($formaPagoId && ($ajusteFormaPagoInfo['porcentaje'] ?? 0) != 0)
                        <div class="text-[10px] text-gray-600 dark:text-gray-400">
                            @if($ajusteFormaPagoInfo['porcentaje'] > 0)
                                {{ __('Recargo') }} {{ $ajusteFormaPagoInfo['porcentaje'] }}%: +${{ number_format($ajusteFormaPagoInfo['monto'] ?? 0, 2, ',', '.') }}
                            @else
                                {{ __('Descuento') }} {{ abs($ajusteFormaPagoInfo['porcentaje']) }}%: -${{ number_format(abs($ajusteFormaPagoInfo['monto'] ?? 0), 2, ',', '.') }}
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Botón compacto de Descuentos y Beneficios (estilo NuevaVenta) --}}
                @if(!empty($items))
                    <button
                        wire:click="abrirModalDescuentos"
                        type="button"
                        class="w-full inline-flex justify-center items-center px-2 py-1.5 border rounded-md shadow-sm text-xs font-medium
                            {{ ($descuentoGeneralActivo || $cuponAplicado || $canjePuntosActivo)
                                ? 'border-purple-400 dark:border-purple-500 text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 hover:bg-purple-100 dark:hover:bg-purple-900/50'
                                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        {{ __('Descuentos') }}
                        @if($descuentoGeneralActivo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-purple-200 dark:bg-purple-700 text-purple-800 dark:text-purple-200 rounded">
                                {{ $descuentoGeneralTipo === 'porcentaje' ? $descuentoGeneralValor . '%' : '$' . number_format($descuentoGeneralValor, 2, ',', '.') }}
                            </span>
                        @endif
                        @if($cuponAplicado && $cuponInfo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-amber-200 dark:bg-amber-700 text-amber-800 dark:text-amber-200 rounded">
                                {{ $cuponInfo['codigo'] }}
                            </span>
                        @endif
                        @if($canjePuntosActivo)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-yellow-200 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-200 rounded">
                                {{ $canjePuntosUnidades }}pts
                            </span>
                        @endif
                    </button>
                @endif

                {{-- Resumen de Totales (reusado 1:1 de NuevaVenta: subtotal, descuentos, ajuste FP, recargo cuotas, total, desglose IVA colapsable) --}}
                @if($resultado)
                    @include('livewire.carrito._resumen-totales')
                @endif
            </div>

            {{-- Footer fijo de la columna (acciones) --}}
            @php
                // Total a cobrar para el botón verde (mismo cálculo que NuevaVenta).
                $totalACobrar = 0;
                if (! empty($resultado)) {
                    if (isset($resultado['desglose_iva'])) {
                        $dgIva = $resultado['desglose_iva'];
                        if (isset($dgIva['total_mixto'])) {
                            $totalACobrar = $dgIva['total_mixto'];
                        } elseif (isset($dgIva['total_con_ajuste_fp']) && $dgIva['total_con_ajuste_fp'] != ($dgIva['total'] ?? 0)) {
                            $totalACobrar = $dgIva['total_con_ajuste_fp'];
                        } else {
                            $totalACobrar = $resultado['total_final'] ?? 0;
                        }
                    } else {
                        $totalACobrar = $resultado['total_final'] ?? 0;
                    }
                }
            @endphp
            <div class="flex-shrink-0 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1.5">
                {{-- Fila 1: Borrador + Sin cobrar (50/50) --}}
                <div class="flex gap-1.5">
                    @if(!$modoEdicion || $estadoPedidoActual === 'borrador')
                        <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled"
                            class="flex-1 inline-flex justify-center items-center px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            {{ __('Guardar borrador') }}
                        </button>
                    @endif
                    <button type="button" wire:click="confirmarSinCobrar" wire:loading.attr="disabled"
                        class="flex-1 inline-flex justify-center items-center px-2 py-1.5 border border-gray-400 dark:border-gray-500 rounded-md text-xs font-medium text-gray-800 dark:text-gray-100 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500">
                        {{ __('Confirmar sin cobrar') }}
                    </button>
                </div>

                {{-- Fila 2: Confirmar pedido $XXXX en verde --}}
                <button type="button" wire:click="confirmarPedido" wire:loading.attr="disabled"
                    @if(empty($items)) disabled @endif
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent rounded-md text-sm font-bold text-white bg-green-600 hover:bg-green-700 shadow disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    {{ __('Confirmar') }}
                    @if($totalACobrar > 0)
                        <span class="ml-1.5">${{ number_format($totalACobrar, 2, ',', '.') }}</span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- Modales reutilizados de NuevaVenta --}}
    @include('livewire.carrito._modal-cliente-rapido')
    @include('livewire.carrito._modal-articulo-rapido')
    @include('livewire.carrito._modal-busqueda-articulos')
    @include('livewire.carrito._modal-pesable')
    @include('livewire.ventas._wizard-opcionales')
    @include('livewire.ventas._modal-descuentos')

    {{-- Modal: Concepto libre --}}
    @if($mostrarModalConcepto)
        <x-bcn-modal :title="__('Agregar concepto')" color="bg-emerald-600" maxWidth="md" onClose="cerrarModalConcepto" submit="agregarConcepto">
            <x-slot:body>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Descripción') }}</label>
                        <input type="text" wire:model="conceptoDescripcion" placeholder="{{ __('Ej: Adicional de empaque') }}"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Categoría') }}</label>
                        <select wire:model="conceptoCategoriaId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm">
                            <option value="">{{ __('Sin categoría') }}</option>
                            @foreach($categoriasDisponibles as $cat)
                                <option value="{{ $cat['id'] }}">{{ $cat['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Importe') }} *</label>
                        <input type="number" step="0.01" min="0" wire:model="conceptoImporte" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-500 focus:ring-opacity-50 text-sm" />
                    </div>
                </div>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-emerald-600 hover:bg-emerald-700">
                    {{ __('Agregar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal: Confirmar limpiar carrito --}}
    @if($mostrarConfirmLimpiar)
        <x-bcn-modal :title="__('¿Limpiar el carrito?')" color="bg-red-600" maxWidth="sm" onClose="cancelarLimpiarCarrito">
            <x-slot:body>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ __('Se eliminarán todos los artículos del carrito.') }}</p>
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="ejecutarLimpiarCarrito" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-red-600 hover:bg-red-700">
                    {{ __('Limpiar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Popover edición de nombre de item --}}
    @if($editarNombreIndex !== null)
        <x-bcn-modal :title="__('Editar nombre')" color="bg-blue-600" maxWidth="md" onClose="cerrarEditarNombre" submit="aplicarEditarNombre">
            <x-slot:body>
                <input type="text" wire:model="editarNombreValor"
                    x-init="$nextTick(() => $el.focus())"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 text-sm" />
            </x-slot:body>
            <x-slot:footer>
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm text-white bg-blue-600 hover:bg-blue-700">
                    {{ __('Aplicar') }}
                </button>
            </x-slot:footer>
        </x-bcn-modal>
    @endif

    {{-- Modal de Pago / Desglose Mixto (reusado de NuevaVenta) --}}
    @include("livewire.carrito._modal-pago-mixto")
    @include("livewire.carrito._modal-moneda-extranjera")
    @include("livewire.carrito._modal-vuelto")
    </div>{{-- /modal contenedor con margen --}}
</div>
@endif
</div>{{-- /wrapper raiz --}}
