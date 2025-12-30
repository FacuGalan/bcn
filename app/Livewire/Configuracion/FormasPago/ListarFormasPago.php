<?php

namespace App\Livewire\Configuracion\FormasPago;

use Livewire\Component;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\FormaPagoSucursal;
use App\Models\Sucursal;

class ListarFormasPago extends Component
{
    public $formasPago = [];
    public $sucursales = [];
    public $sucursalSeleccionada = null;

    // Modal de edición de cuotas
    public $formaPagoEditando = null;
    public $nuevaCuota = [
        'cantidad_cuotas' => null,
        'interes_porcentaje' => 0,
        'coeficiente' => 1,
    ];

    public function mount()
    {
        $this->sucursales = Sucursal::orderBy('nombre')->get();
        $this->cargarFormasPago();
    }

    public function cargarFormasPago()
    {
        $this->formasPago = FormaPago::with(['cuotas' => function($q) {
            $q->orderBy('cantidad_cuotas');
        }])->orderBy('nombre')->get();
    }

    public function toggleActivo($formaPagoId)
    {
        $formaPago = FormaPago::find($formaPagoId);
        if ($formaPago) {
            $formaPago->activo = !$formaPago->activo;
            $formaPago->save();
            $this->cargarFormasPago();
            session()->flash('message', 'Estado actualizado correctamente');
        }
    }

    public function toggleSucursal($formaPagoId, $sucursalId)
    {
        $exists = FormaPagoSucursal::where('forma_pago_id', $formaPagoId)
            ->where('sucursal_id', $sucursalId)
            ->first();

        if ($exists) {
            $exists->delete();
            session()->flash('message', 'Forma de pago deshabilitada para la sucursal');
        } else {
            FormaPagoSucursal::create([
                'forma_pago_id' => $formaPagoId,
                'sucursal_id' => $sucursalId,
            ]);
            session()->flash('message', 'Forma de pago habilitada para la sucursal');
        }
    }

    public function editarCuotas($formaPagoId)
    {
        $this->formaPagoEditando = $formaPagoId;
    }

    public function agregarCuota()
    {
        if (!$this->nuevaCuota['cantidad_cuotas']) {
            session()->flash('error', 'Debes ingresar la cantidad de cuotas');
            return;
        }

        FormaPagoCuota::create([
            'forma_pago_id' => $this->formaPagoEditando,
            'cantidad_cuotas' => $this->nuevaCuota['cantidad_cuotas'],
            'interes_porcentaje' => $this->nuevaCuota['interes_porcentaje'] ?: 0,
            'coeficiente' => $this->nuevaCuota['coeficiente'] ?: 1,
        ]);

        $this->reset('nuevaCuota');
        $this->cargarFormasPago();
        session()->flash('message', 'Plan de cuotas agregado correctamente');
    }

    public function eliminarCuota($cuotaId)
    {
        FormaPagoCuota::find($cuotaId)?->delete();
        $this->cargarFormasPago();
        session()->flash('message', 'Plan de cuotas eliminado correctamente');
    }

    public function cerrarModal()
    {
        $this->formaPagoEditando = null;
        $this->reset('nuevaCuota');
    }

    public function render()
    {
        return view('livewire.configuracion.formas-pago.listar-formas-pago');
    }
}
