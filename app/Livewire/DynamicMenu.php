<?php

namespace App\Livewire;

use App\Models\MenuItem;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Componente de Menú Dinámico
 *
 * Renderiza el menú de navegación basado en los permisos del usuario.
 *
 * Características:
 * - Desktop: Menú horizontal con submenu en banda secundaria
 * - Mobile: Menú hamburguesa con acordeón
 * - Filtrado por permisos del usuario
 * - Detección de ruta activa
 * - Gestión de estado activo del menú
 *
 * @package App\Livewire
 * @version 1.0.0
 */
class DynamicMenu extends Component
{
    /**
     * ID del item padre actualmente seleccionado
     *
     * @var int|null
     */
    public ?int $activeParentId = null;

    /**
     * Estado del menú móvil (abierto/cerrado)
     *
     * @var bool
     */
    public bool $mobileMenuOpen = false;

    /**
     * ID del padre expandido en móvil (para acordeón)
     *
     * @var int|null
     */
    public ?int $mobileExpandedParentId = null;

    /**
     * Propiedades para almacenar el menú pre-cargado
     * Evita consultas repetidas a la BD
     */
    public $parentItems = [];
    public $allChildrenItems = []; // Estructura: ['parent_id' => [hijos...]]

    /**
     * Escuchar evento de cambio de sucursal
     */
    protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

    /**
     * Inicialización del componente
     * Carga todo el menú de una sola vez
     */
    public function mount(): void
    {
        $this->loadMenuData();
    }

    /**
     * Carga todo el menú de una sola vez (padres + todos los hijos)
     * Usa caché para evitar consultas repetidas a la BD
     * Solo consulta cuando:
     * - El usuario se loguea (primera vez)
     * - El usuario cambia de sucursal
     */
    protected function loadMenuData(): void
    {
        $cacheKey = 'menu_full_' . auth()->id() . '_' . session('sucursal_activa_id');

        $menuData = cache()->remember($cacheKey, 3600, function () {
            // Una sola consulta para todos los padres
            $parents = auth()->user()->getAllowedMenuItems();
            $allChildren = [];

            // Pre-cargar todos los hijos de todos los padres
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
        // Si estamos en el dashboard, no seleccionar ningún item
        $this->activeParentId = null;
        if ($this->parentItems->isNotEmpty()) {
            $this->detectActiveParent();
        }
    }

    /**
     * Detecta qué padre debe estar activo según la ruta actual
     * Usa la data pre-cargada, sin consultas a BD
     */
    protected function detectActiveParent(): void
    {
        foreach ($this->parentItems as $parent) {
            // Obtener hijos desde la data pre-cargada (sin consulta BD)
            $children = $this->allChildrenItems[$parent->id] ?? collect();

            foreach ($children as $child) {
                if ($child->isCurrentRoute()) {
                    $this->activeParentId = $parent->id;
                    return;
                }
            }

            // Si el padre mismo coincide con la ruta
            if ($parent->isCurrentRoute()) {
                $this->activeParentId = $parent->id;
                return;
            }
        }
    }

    /**
     * Obtiene los hijos de un padre desde la data pre-cargada
     * NO hace consultas a la BD
     *
     * @param int $parentId
     * @return \Illuminate\Support\Collection
     */
    public function getChildrenItems(int $parentId)
    {
        return $this->allChildrenItems[$parentId] ?? collect();
    }

    /**
     * Cambia el padre activo (Desktop)
     * Solo cambia el estado, sin consultas a BD
     *
     * @param int $parentId
     */
    public function setActiveParent(int $parentId): void
    {
        $this->activeParentId = $parentId;
    }

    /**
     * Toggle del menú móvil
     */
    public function toggleMobileMenu(): void
    {
        $this->mobileMenuOpen = !$this->mobileMenuOpen;
    }

    /**
     * Toggle de acordeón en móvil
     * Solo cambia el estado, sin consultas a BD
     *
     * @param int $parentId
     */
    public function toggleMobileParent(int $parentId): void
    {
        if ($this->mobileExpandedParentId === $parentId) {
            $this->mobileExpandedParentId = null;
        } else {
            $this->mobileExpandedParentId = $parentId;
        }
    }

    /**
     * Cierra el menú móvil
     */
    public function closeMobileMenu(): void
    {
        $this->mobileMenuOpen = false;
    }

    /**
     * Maneja el cambio de sucursal
     * Limpia el caché y recarga todo el menú de una sola vez
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
     * Renderiza el componente
     * Usa la data pre-cargada, sin consultas adicionales
     * Recalcula activeParentId en cada render para detectar la ruta actual
     */
    public function render()
    {
        // Recalcular el padre activo en cada render para detectar cambios de ruta
        $this->activeParentId = null;

        // Si estamos en el dashboard, no seleccionar nada
        $isDashboard = request()->routeIs('dashboard');

        \Log::info('DynamicMenu render', [
            'isDashboard' => $isDashboard,
            'currentRoute' => request()->route()?->getName(),
            'activeParentId_before' => $this->activeParentId,
        ]);

        if (!$isDashboard) {
            $this->detectActiveParent();
        }

        \Log::info('DynamicMenu after detect', [
            'activeParentId_after' => $this->activeParentId,
        ]);

        return view('livewire.dynamic-menu', [
            'parentItems' => $this->parentItems,
            'allChildrenItems' => $this->allChildrenItems,
        ]);
    }
}
