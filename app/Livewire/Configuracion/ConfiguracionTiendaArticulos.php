<?php

namespace App\Livewire\Configuracion;

use App\Models\Articulo;
use App\Models\ArticuloImagenTienda;
use App\Models\Categoria;
use App\Services\ImagenArticuloTiendaService;
use App\Services\Pedidos\CatalogoTiendaService;
use App\Services\TenantService;
use App\Traits\SucursalAware;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Configuración de tienda POR ARTÍCULO (RF-T14): galería de fotos de
 * tienda, badges, destacado y orden (drag & drop de artículos y
 * categorías). Sub-componente embebido en ConfiguracionTienda, en la
 * columna de configuración (el visor sticky queda siempre visible).
 *
 * A diferencia del tema (JSON en config.tiendas, se guarda con el botón
 * "Guardar tienda"), estos datos viven en la BD TENANT y se GUARDAN AL
 * INSTANTE por acción. Tras cada guardado se invalida el cache del
 * catálogo público y el visor recarga (debounced en tienda-preview.js,
 * evento `tienda-catalogo-cambiado`) porque el catálogo es server-rendered.
 *
 * Permiso: `func.tienda.config` (mismo del apartado tienda).
 */
class ConfiguracionTiendaArticulos extends Component
{
    use SucursalAware, WithFileUploads;

    /** Artículo con el editor (galería + badges) expandido. */
    public ?int $articuloAbierto = null;

    /** Uploads múltiples pendientes de proceso (se procesan al llegar). */
    public $fotosUpload = [];

    /** Badges del artículo abierto: tipos predefinidos seleccionados. */
    public array $badgesSel = [];

    /** Texto del badge custom del artículo abierto ('' = sin custom). */
    public string $badgeCustom = '';

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->cerrarEditor();
    }

    // ==================== DESTACADO ====================

    public function toggleDestacado(int $articuloId): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $articulo = $this->articuloVisible($articuloId);
        if (! $articulo) {
            return;
        }

        $articulo->update(['destacado' => ! $articulo->destacado]);

        $this->catalogoCambiado();
    }

    // ==================== EDITOR (GALERÍA + BADGES) ====================

    public function abrirEditor(int $articuloId): void
    {
        $articulo = $this->articuloVisible($articuloId);
        if (! $articulo) {
            return;
        }

        $this->articuloAbierto = $articulo->id;
        $this->fotosUpload = [];
        $this->resetValidation();

        $this->badgesSel = [];
        $this->badgeCustom = '';
        foreach ($articulo->badgesTienda() as $badge) {
            if ($badge['tipo'] === 'custom') {
                $this->badgeCustom = (string) $badge['texto'];
            } else {
                $this->badgesSel[] = $badge['tipo'];
            }
        }
    }

    public function cerrarEditor(): void
    {
        $this->articuloAbierto = null;
        $this->fotosUpload = [];
        $this->badgesSel = [];
        $this->badgeCustom = '';
        $this->resetValidation();
    }

    // ==================== GALERÍA ====================

    /** Livewire llama esto al terminar el upload: procesa AL INSTANTE. */
    public function updatedFotosUpload(): void
    {
        if (! $this->autorizado()) {
            $this->fotosUpload = [];

            return;
        }

        $articulo = $this->articuloVisible((int) $this->articuloAbierto);
        if (! $articulo) {
            $this->fotosUpload = [];

            return;
        }

        $this->validate(
            ['fotosUpload.*' => 'image|max:5120'],
            ['fotosUpload.*.image' => __('Formato de imagen no permitido. Aceptados: JPG, PNG, WebP.')],
        );

        $service = app(ImagenArticuloTiendaService::class);
        $procesadas = 0;

        foreach ((array) $this->fotosUpload as $upload) {
            try {
                $service->agregar($articulo, $upload);
                $procesadas++;
            } catch (Exception $e) {
                $this->dispatch('toast-error', message: $e->getMessage());
                break; // el resto va a fallar igual (máximo alcanzado) o es el mismo error
            }
        }

        $this->fotosUpload = [];

        if ($procesadas > 0) {
            $this->catalogoCambiado();
        }
    }

    public function quitarFoto(int $imagenId): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $imagen = ArticuloImagenTienda::find($imagenId);
        $articulo = $imagen ? $this->articuloVisible((int) $imagen->articulo_id) : null;
        if (! $imagen || ! $articulo) {
            return;
        }

        app(ImagenArticuloTiendaService::class)->quitar($imagen);

        $this->catalogoCambiado();
    }

    /** Orden nuevo de la galería del artículo abierto (drag & drop). */
    public function reordenarFotos(array $ids): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $articulo = $this->articuloVisible((int) $this->articuloAbierto);
        if (! $articulo) {
            return;
        }

        app(ImagenArticuloTiendaService::class)->reordenar($articulo, $ids);

        $this->catalogoCambiado();
    }

    // ==================== BADGES ====================

    public function toggleBadge(string $tipo): void
    {
        if (! $this->autorizado() || ! in_array($tipo, Articulo::BADGES_TIENDA, true)) {
            return;
        }

        if (in_array($tipo, $this->badgesSel, true)) {
            $this->badgesSel = array_values(array_diff($this->badgesSel, [$tipo]));
        } else {
            if ($this->cantidadBadges() >= Articulo::MAX_BADGES_TIENDA) {
                $this->dispatch('toast-error', message: __('Máximo :max badges por artículo', ['max' => Articulo::MAX_BADGES_TIENDA]));

                return;
            }
            $this->badgesSel[] = $tipo;
        }

        $this->persistirBadges();
    }

    /** wire:model.live.debounce del input custom. */
    public function updatedBadgeCustom(): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $this->badgeCustom = trim($this->badgeCustom);

        $this->validate(
            ['badgeCustom' => 'nullable|string|max:'.Articulo::MAX_BADGE_CUSTOM_LARGO],
            ['badgeCustom.max' => __('El badge propio admite hasta :max caracteres', ['max' => Articulo::MAX_BADGE_CUSTOM_LARGO])],
        );

        if ($this->badgeCustom !== '' && count($this->badgesSel) >= Articulo::MAX_BADGES_TIENDA) {
            $this->dispatch('toast-error', message: __('Máximo :max badges por artículo', ['max' => Articulo::MAX_BADGES_TIENDA]));
            $this->badgeCustom = '';

            return;
        }

        $this->persistirBadges();
    }

    protected function cantidadBadges(): int
    {
        return count($this->badgesSel) + ($this->badgeCustom !== '' ? 1 : 0);
    }

    protected function persistirBadges(): void
    {
        $articulo = $this->articuloVisible((int) $this->articuloAbierto);
        if (! $articulo) {
            return;
        }

        $badges = array_map(fn (string $tipo) => ['tipo' => $tipo], $this->badgesSel);
        if ($this->badgeCustom !== '') {
            $badges[] = ['tipo' => 'custom', 'texto' => $this->badgeCustom];
        }

        $articulo->update(['badges_tienda' => $badges !== [] ? $badges : null]);

        $this->catalogoCambiado();
    }

    // ==================== ORDEN (DRAG & DROP) ====================

    /**
     * Persiste el orden visual de los artículos de una categoría. Renumera
     * de a 10 para dejar huecos (altas posteriores sin re-renumerar todo).
     * El orden es 100% MANUAL: destacado no fuerza posición (decisión del
     * usuario 2026-07-20 — puede querer el destacado tercero en la lista
     * aunque también salga en el banner).
     */
    public function reordenarArticulos(array $ids): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $visibles = $this->idsVisibles();

        DB::connection('pymes_tenant')->transaction(function () use ($ids, $visibles) {
            $orden = 10;
            foreach ($ids as $id) {
                if (! in_array((int) $id, $visibles, true)) {
                    continue; // ID ajeno o no visible: se ignora (payload manipulado)
                }
                Articulo::where('id', (int) $id)->update(['orden' => $orden]);
                $orden += 10;
            }
        });

        $this->catalogoCambiado();
    }

    public function reordenarCategorias(array $ids): void
    {
        if (! $this->autorizado()) {
            return;
        }

        $propias = Categoria::whereIn('id', array_map('intval', $ids))->pluck('id')->all();

        DB::connection('pymes_tenant')->transaction(function () use ($ids, $propias) {
            $orden = 10;
            foreach ($ids as $id) {
                if (! in_array((int) $id, $propias, true)) {
                    continue;
                }
                Categoria::where('id', (int) $id)->update(['orden' => $orden]);
                $orden += 10;
            }
        });

        $this->catalogoCambiado();
    }

    // ==================== HELPERS ====================

    protected function autorizado(): bool
    {
        if (auth()->user()?->hasPermissionTo('func.tienda.config')) {
            return true;
        }

        $this->dispatch('toast-error', message: __('No tenés permiso para configurar la tienda online'));

        return false;
    }

    /**
     * El artículo SOLO si es visible en la tienda de la sucursal activa —
     * ningún método de este componente puede tocar artículos fuera de eso.
     */
    protected function articuloVisible(int $articuloId): ?Articulo
    {
        if (! $articuloId) {
            return null;
        }

        return $this->queryVisibles()->where('id', $articuloId)->first();
    }

    /** @return list<int> */
    protected function idsVisibles(): array
    {
        return $this->queryVisibles()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Mismo criterio de visibilidad que CatalogoTiendaService (RF-17), sin
     * filtrar por tipo de pedido: el panel configura el artículo para ambos.
     */
    protected function queryVisibles()
    {
        $sucursalId = (int) $this->sucursalActual();

        return Articulo::query()
            ->where('activo', true)
            ->whereHas('sucursales', function ($q) use ($sucursalId) {
                $q->where('sucursales.id', $sucursalId)
                    ->where('articulos_sucursales.activo', true)
                    ->where('articulos_sucursales.vendible', true)
                    ->where('articulos_sucursales.visible_tienda', true);
            });
    }

    /**
     * Guardado inmediato consumado: invalidar el cache del catálogo público
     * (sin esto la tienda/visor sirven viejo hasta 60s) y avisar al visor
     * (recarga debounced — el catálogo es server-rendered).
     */
    protected function catalogoCambiado(): void
    {
        CatalogoTiendaService::invalidarCache(
            (int) (app(TenantService::class)->getComercio()?->id ?? 0),
            (int) $this->sucursalActual(),
        );

        $this->dispatch('tienda-catalogo-cambiado');
    }

    // ==================== RENDER ====================

    public function render()
    {
        $articulos = $this->queryVisibles()
            ->with('imagenesTienda')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $categorias = Categoria::where('activo', true)
            ->whereIn('id', $articulos->pluck('categoria_id')->filter()->unique())
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $porCategoria = $articulos->groupBy(fn (Articulo $a) => (int) ($a->categoria_id ?? 0));

        $grupos = $categorias
            ->map(fn (Categoria $cat) => [
                'id' => (int) $cat->id,
                'nombre' => $cat->nombre,
                'articulos' => $porCategoria->get($cat->id, collect()),
            ])
            ->values();

        // Artículos sin categoría: grupo final, no arrastrable como categoría.
        if ($porCategoria->has(0)) {
            $grupos->push([
                'id' => 0,
                'nombre' => __('Sin categoría'),
                'articulos' => $porCategoria->get(0),
            ]);
        }

        return view('livewire.configuracion.configuracion-tienda-articulos', [
            'grupos' => $grupos,
            'puedeConfigurar' => (bool) auth()->user()?->hasPermissionTo('func.tienda.config'),
            'badgesCatalogo' => self::badgesCatalogo(),
            'maxFotos' => ImagenArticuloTiendaService::MAX_IMAGENES,
            'maxBadges' => Articulo::MAX_BADGES_TIENDA,
        ]);
    }

    /** Labels de los badges predefinidos (el icono/color lo pinta la tienda). */
    public static function badgesCatalogo(): array
    {
        return [
            'sin_tacc' => __('Sin TACC'),
            'vegetariano' => __('Vegetariano'),
            'vegano' => __('Vegano'),
            'picante' => __('Picante'),
            'nuevo' => __('Nuevo'),
            'mas_vendido' => __('Más vendido'),
            'artesanal' => __('Artesanal'),
            'sin_azucar' => __('Sin azúcar'),
        ];
    }
}
