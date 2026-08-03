<?php

namespace App\Livewire\Puntos;

use App\Models\Articulo;
use App\Models\ConfiguracionPuntos;
use App\Models\ConfiguracionPuntosSucursal;
use App\Services\Pedidos\CatalogoTiendaService;
use App\Services\SucursalService;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
#[Layout('layouts.app')]
class ProgramaPuntos extends Component
{
    // ==================== CONFIGURACIÓN ====================
    public bool $activo = false;

    public string $modoAcumulacion = 'global';

    public string $montoPorPunto = '100.00';

    public string $valorPuntoCanje = '50.00';

    public int $minimoCanje = 10;

    public string $redondeo = 'floor';

    /** RF-T58: canje (artículo y pago) limitado a artículos habilitados. */
    public bool $restringirCanjeArticulos = false;

    public array $sucursalesConfig = [];

    // ==================== CANJE DE ARTÍCULOS (RF-T58/T60) ====================

    /** Sucursal cuyo canje por artículo se está editando. */
    public ?int $canjeSucursalId = null;

    /** Filtro de búsqueda de la tabla de artículos. */
    public string $busquedaCanje = '';

    /** Tope de filas de la tabla (con búsqueda para ir más allá). */
    public const MAX_FILAS_CANJE = 100;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-form :fields="6" />
        HTML;
    }

    public function mount()
    {
        $this->cargarConfiguracion();
    }

    public function cargarConfiguracion(): void
    {
        $config = ConfiguracionPuntos::first();

        if ($config) {
            $this->activo = $config->activo;
            $this->modoAcumulacion = $config->modo_acumulacion;
            $this->montoPorPunto = $config->monto_por_punto;
            $this->valorPuntoCanje = $config->valor_punto_canje;
            $this->minimoCanje = $config->minimo_canje;
            $this->redondeo = $config->redondeo;
            $this->restringirCanjeArticulos = (bool) $config->restringir_canje_articulos;
        }

        $this->cargarSucursalesConfig();

        // Sucursal por defecto de la tabla de canje: la ACTIVA (resolver
        // canónico con sus fallbacks), si no la primera configurada.
        $primera = array_key_first($this->sucursalesConfig);
        $this->canjeSucursalId ??= (int) (sucursal_activa() ?? 0)
            ?: ($primera !== null ? (int) $primera : null);
    }

    public function cargarSucursalesConfig(): void
    {
        $sucursales = SucursalService::getSucursalesDisponibles();
        $configSucursales = ConfiguracionPuntosSucursal::pluck('activo', 'sucursal_id')->toArray();

        $this->sucursalesConfig = [];
        foreach ($sucursales as $sucursal) {
            $this->sucursalesConfig[$sucursal->id] = [
                'nombre' => $sucursal->nombre,
                'activo' => $configSucursales[$sucursal->id] ?? true,
            ];
        }
    }

    public function guardarConfiguracion(): void
    {
        $this->validate([
            'montoPorPunto' => 'required|numeric|min:0.01',
            'valorPuntoCanje' => 'required|numeric|min:0.01',
            'minimoCanje' => 'required|integer|min:1',
            'modoAcumulacion' => 'required|in:global,por_sucursal',
            'redondeo' => 'required|in:floor,round,ceil',
        ]);

        ConfiguracionPuntos::updateOrCreate(
            [],
            [
                'activo' => $this->activo,
                'modo_acumulacion' => $this->modoAcumulacion,
                'monto_por_punto' => $this->montoPorPunto,
                'valor_punto_canje' => $this->valorPuntoCanje,
                'minimo_canje' => $this->minimoCanje,
                'redondeo' => $this->redondeo,
                'restringir_canje_articulos' => $this->restringirCanjeArticulos,
            ]
        );

        foreach ($this->sucursalesConfig as $sucursalId => $config) {
            ConfiguracionPuntosSucursal::updateOrCreate(
                ['sucursal_id' => $sucursalId],
                ['activo' => $config['activo']]
            );
        }

        $this->dispatch('toast-success', message: __('Configuración de puntos guardada'));
    }

    public function toggleActivo(): void
    {
        $this->activo = ! $this->activo;
    }

    public function toggleSucursal(int $sucursalId): void
    {
        if (isset($this->sucursalesConfig[$sucursalId])) {
            $this->sucursalesConfig[$sucursalId]['activo'] = ! $this->sucursalesConfig[$sucursalId]['activo'];
        }
    }

    /**
     * RF-T58: switch "restringir canje a artículos habilitados". Guardado
     * INMEDIATO (como toda la sección de canje): cambia el comportamiento
     * del canje en POS y tienda, no debe depender del botón del form.
     */
    public function toggleRestringirCanje(): void
    {
        $this->restringirCanjeArticulos = ! $this->restringirCanjeArticulos;

        ConfiguracionPuntos::updateOrCreate([], [
            'restringir_canje_articulos' => $this->restringirCanjeArticulos,
        ]);

        $this->dispatch('toast-success', message: $this->restringirCanjeArticulos
            ? __('Canje restringido a los artículos habilitados')
            : __('Canje sin restricción por artículo'));
    }

    // ==================== CANJE DE ARTÍCULOS (RF-T58/T60) ====================

    /**
     * Habilita/deshabilita el canje del artículo en la sucursal elegida
     * (pivot articulos_sucursales.canje_tienda — el MISMO flag que usa la
     * tienda online y, con la restricción activa, el POS). Guardado
     * inmediato, como la pantalla de configuración de tienda.
     */
    public function toggleCanjeArticulo(int $articuloId): void
    {
        if (! $this->canjeSucursalId) {
            return;
        }

        $pivot = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $this->canjeSucursalId);

        $actual = (bool) $pivot->clone()->value('canje_tienda');
        $pivot->update(['canje_tienda' => ! $actual]);

        $this->canjeCambiado();
    }

    /** Puntos fijos del artículo (campo GLOBAL; vacío/0 = costo derivado). */
    public function guardarPuntosCanjeArticulo(int $articuloId, $valor): void
    {
        $puntos = max(0, (int) $valor);
        Articulo::where('id', $articuloId)->update(['puntos_canje' => $puntos > 0 ? $puntos : null]);

        $this->canjeCambiado();
    }

    /** Modo de opcionales del artículo (campo GLOBAL, RF-T59). */
    public function guardarCanjeOpcionales(int $articuloId, string $modo): void
    {
        if (! in_array($modo, Articulo::CANJE_OPCIONALES, true)) {
            return;
        }

        Articulo::where('id', $articuloId)->update(['canje_opcionales' => $modo]);

        $this->canjeCambiado();
    }

    /** RF-T58: habilita el canje de TODOS los artículos de la sucursal. */
    public function habilitarTodosCanje(): void
    {
        $this->setearCanjeMasivo(true);
    }

    /** RF-T58: deshabilita el canje de TODOS los artículos de la sucursal. */
    public function quitarTodosCanje(): void
    {
        $this->setearCanjeMasivo(false);
    }

    protected function setearCanjeMasivo(bool $valor): void
    {
        if (! $this->canjeSucursalId) {
            return;
        }

        DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('sucursal_id', $this->canjeSucursalId)
            ->update(['canje_tienda' => $valor]);

        $this->canjeCambiado();

        $this->dispatch('toast-success', message: $valor
            ? __('Canje habilitado para todos los artículos de la sucursal')
            : __('Canje deshabilitado para todos los artículos de la sucursal'));
    }

    /** El catálogo público cachea: invalidarlo tras cada cambio de canje. */
    protected function canjeCambiado(): void
    {
        CatalogoTiendaService::invalidarCache(
            (int) (app(TenantService::class)->getComercio()?->id ?? 0),
            (int) $this->canjeSucursalId,
        );
    }

    /**
     * Artículos activos y vendibles de la sucursal elegida, con su estado
     * de canje. Tope de filas: la búsqueda refina más allá del corte.
     */
    protected function articulosCanje(): array
    {
        if (! $this->canjeSucursalId) {
            return ['filas' => [], 'total' => 0];
        }

        $query = Articulo::query()
            ->where('activo', true)
            ->whereHas('sucursales', function ($q) {
                $q->where('sucursales.id', $this->canjeSucursalId)
                    ->where('articulos_sucursales.activo', true)
                    ->where('articulos_sucursales.vendible', true);
            })
            ->when(trim($this->busquedaCanje) !== '', function ($q) {
                $texto = trim($this->busquedaCanje);
                $q->where(fn ($sub) => $sub->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('codigo', 'like', "%{$texto}%"));
            });

        $total = (clone $query)->count();

        $filas = $query
            ->with(['sucursales' => fn ($q) => $q->where('sucursales.id', $this->canjeSucursalId)])
            ->orderBy('nombre')
            ->limit(self::MAX_FILAS_CANJE)
            ->get();

        return ['filas' => $filas, 'total' => $total];
    }

    public function render()
    {
        $canje = $this->articulosCanje();

        return view('livewire.puntos.programa-puntos', [
            'articulosCanje' => $canje['filas'],
            'articulosCanjeTotal' => $canje['total'],
            'maxFilasCanje' => self::MAX_FILAS_CANJE,
        ]);
    }
}
