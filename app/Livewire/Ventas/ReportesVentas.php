<?php

namespace App\Livewire\Ventas;

use App\Services\ReporteCortesiasService;
use App\Traits\SucursalAware;
use Carbon\Carbon;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Componente Livewire: Reportes de Ventas.
 *
 * Activa el ítem de menú "Reportes" (ya existente, slug `reportes-ventas`,
 * ruta `ventas.reportes`). Arranca con el reporte de Cortesías (invitaciones);
 * el selector `$tipoReporte` queda preparado para futuros reportes de ventas.
 *
 * Sucursal-aware: todos los reportes se acotan a la sucursal activa.
 */
#[Lazy]
class ReportesVentas extends Component
{
    use SucursalAware;

    /** Tipo de reporte seleccionado. */
    public string $tipoReporte = 'cortesias';

    /** Filtros de período. */
    public string $fechaDesde = '';

    public string $fechaHasta = '';

    /** Resultado del reporte (KPIs + desgloses + detalle). */
    public array $resultado = [];

    /** Flag para saber si ya se generó al menos una vez. */
    public bool $generado = false;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="3" :filterCount="3" :columns="5" :rows="8" />
        HTML;
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasPermissionTo('func.ver_reportes_ventas'),
            403
        );

        $this->fechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->fechaHasta = now()->format('Y-m-d');

        $this->mountSucursalAware();
    }

    /**
     * Genera el reporte según el tipo seleccionado y el período.
     */
    public function generarReporte(ReporteCortesiasService $cortesiasService): void
    {
        $desde = Carbon::parse($this->fechaDesde)->startOfDay();
        $hasta = Carbon::parse($this->fechaHasta)->endOfDay();

        $this->resultado = match ($this->tipoReporte) {
            'cortesias' => $cortesiasService->generar($this->sucursalActual(), $desde, $hasta),
            default => [],
        };

        $this->generado = true;
    }

    /**
     * Al cambiar el tipo de reporte, limpia los resultados previos.
     */
    public function updatedTipoReporte(): void
    {
        $this->resultado = [];
        $this->generado = false;
    }

    /**
     * Al cambiar de sucursal, limpia el reporte (los datos eran de otra sucursal).
     */
    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->resultado = [];
        $this->generado = false;
    }

    public function render()
    {
        return view('livewire.ventas.reportes-ventas');
    }
}
