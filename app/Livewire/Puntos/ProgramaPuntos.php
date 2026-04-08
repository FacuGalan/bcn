<?php

namespace App\Livewire\Puntos;

use App\Models\ConfiguracionPuntos;
use App\Models\ConfiguracionPuntosSucursal;
use App\Services\SucursalService;
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

    public array $sucursalesConfig = [];

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
        }

        $this->cargarSucursalesConfig();
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

    public function render()
    {
        return view('livewire.puntos.programa-puntos');
    }
}
