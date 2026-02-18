<?php

namespace App\Livewire\Configuracion;

use App\Models\Caja;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\EmpresaConfig;
use App\Models\GrupoCierre;
use App\Models\Localidad;
use App\Models\MovimientoCaja;
use App\Models\Provincia;
use App\Models\PuntoVenta;
use App\Models\PuntoVentaCaja;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class ConfiguracionEmpresa extends Component
{
    use WithFileUploads;

    // ==================== TAB CONTROL ====================
    public string $tabActivo = 'empresa';

    // ==================== TAB EMPRESA ====================
    public $empresaNombre = '';
    public $empresaDireccion = '';
    public $empresaTelefono = '';
    public $empresaEmail = '';
    public $empresaLogo = null;
    public $empresaLogoActual = null;

    // ==================== TAB CUITS ====================
    public $mostrarModalCuit = false;
    public $modoEdicionCuit = false;
    public $cuitId = null;

    // Campos del formulario CUIT
    public $cuitNumeroCuit = '';
    public $cuitRazonSocial = '';
    public $cuitNombreFantasia = '';
    public $cuitDireccion = '';
    public $cuitProvinciaId = null;
    public $cuitLocalidadId = null;
    public $cuitCondicionIvaId = null;
    public $cuitNumeroIibb = '';
    public $cuitFechaInicioActividades = null;
    public $cuitFechaVencimientoCertificado = null;
    public $cuitEntornoAfip = 'testing';
    public $cuitActivo = true;

    // Certificados CUIT
    public $cuitCertificado = null;
    public $cuitClave = null;
    public $cuitTieneCertificado = false;
    public $cuitTieneClave = false;

    // Puntos de venta
    public $puntosVenta = [];
    public $nuevoPuntoVentaNumero = '';
    public $nuevoPuntoVentaNombre = '';

    // Confirmación de eliminación CUIT
    public $mostrarConfirmacionEliminarCuit = false;
    public $cuitEliminarId = null;

    // Confirmación de eliminación Punto de Venta
    public $mostrarConfirmacionEliminarPV = false;
    public $pvEliminarId = null;
    public $pvEliminarNumero = null;

    // ==================== TAB SUCURSALES ====================
    public $sucursalEditandoId = null;
    public $sucursalNombre = '';
    public $sucursalNombrePublico = '';
    public $sucursalDireccion = '';
    public $sucursalTelefono = '';
    public $sucursalEmail = '';
    public $sucursalLogo = null;

    // Modal Configuración de Sucursal
    public $mostrarModalConfigSucursal = false;
    public $configSucursalId = null;
    public $configSucursalNombre = '';

    // Campos de configuración de sucursal
    public $configUsaClaveAutorizacion = false;
    public $configClaveAutorizacion = '';
    public $configTipoImpresionFactura = 'ambos';
    public $configImprimeEncabezadoComanda = true;
    public $configAgrupaArticulosVenta = true;
    public $configAgrupaArticulosImpresion = true;
    public $configControlStockVenta = 'bloquea';
    public $configFacturacionFiscalAutomatica = false;
    public $configUsaWhatsappEscritorio = false;
    public $configEnviaWhatsappComanda = false;
    public $configMensajeWhatsappComanda = '';
    public $configEnviaWhatsappListo = false;
    public $configMensajeWhatsappListo = '';

    // ==================== TAB CAJAS ====================
    // Edición de puntos de venta
    public $cajaEditandoPuntosId = null;
    public $cajaPuntosAsignados = [];
    public $cajaPuntoDefecto = null;

    // Modal configuración de caja
    public $mostrarModalConfigCaja = false;
    public $configCajaId = null;
    public $configCajaNombre = '';
    public $configCajaLimiteEfectivo = null;
    public $configCajaModoCargaInicial = 'manual';
    public $configCajaMontoFijoInicial = null;

    // ==================== TAB CAJAS - GRUPOS DE CIERRE ====================
    public $mostrarModalGrupoCierre = false;
    public $modoEdicionGrupo = false;
    public $grupoId = null;
    public $grupoSucursalId = null;
    public $grupoNombre = '';
    public $grupoCajasSeleccionadas = [];
    public $grupoFondoComun = false;

    // Confirmación de eliminación de grupo
    public $mostrarConfirmacionEliminarGrupo = false;
    public $grupoEliminarId = null;

    // ==================== DATOS DE REFERENCIA ====================
    public $localidades = [];

    public function mount()
    {
        $this->cargarDatosEmpresa();
    }

    public function cambiarTab($tab)
    {
        $this->tabActivo = $tab;
    }

    // ==================== GETTERS COMPUTADOS ====================

    public function getCuitsProperty()
    {
        return Cuit::with(['puntosVenta', 'condicionIva', 'localidad.provincia'])
            ->orderBy('razon_social')
            ->get();
    }

    public function getSucursalesProperty()
    {
        return Sucursal::orderBy('nombre')->get();
    }

    public function getProvinciasProperty()
    {
        return Provincia::ordenadas()->get();
    }

    public function getCondicionesIvaProperty()
    {
        return CondicionIva::ordenadas()->get();
    }

    public function getCajasProperty()
    {
        return Caja::with(['sucursal', 'puntosVenta.cuit', 'grupoCierre'])
            ->orderBy('sucursal_id')
            ->orderBy('nombre')
            ->get();
    }

    public function getGruposCierreProperty()
    {
        return GrupoCierre::with(['sucursal', 'cajas'])
            ->orderBy('sucursal_id')
            ->orderBy('nombre')
            ->get();
    }

    public function getPuntosVentaDisponiblesProperty()
    {
        return PuntoVenta::with('cuit')
            ->whereHas('cuit', fn($q) => $q->where('activo', true))
            ->activos()
            ->orderBy('cuit_id')
            ->orderBy('numero')
            ->get();
    }

    // ==================== TAB EMPRESA ====================

    protected function cargarDatosEmpresa()
    {
        $config = EmpresaConfig::getConfig();
        $this->empresaNombre = $config->nombre ?? '';
        $this->empresaDireccion = $config->direccion ?? '';
        $this->empresaTelefono = $config->telefono ?? '';
        $this->empresaEmail = $config->email ?? '';
        $this->empresaLogoActual = $config->logo_path;
    }

    public function guardarEmpresa()
    {
        $this->validate([
            'empresaNombre' => 'required|max:200',
            'empresaDireccion' => 'nullable|max:500',
            'empresaTelefono' => 'nullable|max:50',
            'empresaEmail' => 'nullable|email|max:100',
            'empresaLogo' => 'nullable|image|max:2048',
        ], [
            'empresaNombre.required' => __('El nombre de la empresa es obligatorio.'),
            'empresaEmail.email' => __('El email no es válido.'),
            'empresaLogo.image' => __('El archivo debe ser una imagen.'),
            'empresaLogo.max' => __('La imagen no debe superar los 2MB.'),
        ]);

        $config = EmpresaConfig::getConfig();
        $config->nombre = $this->empresaNombre;
        $config->direccion = $this->empresaDireccion;
        $config->telefono = $this->empresaTelefono;
        $config->email = $this->empresaEmail;

        if ($this->empresaLogo) {
            $config->updateLogo($this->empresaLogo);
            $this->empresaLogoActual = $config->logo_path;
            $this->empresaLogo = null;
        }

        $config->save();

        $this->dispatch('notify', message: __('Datos de empresa guardados correctamente'), type: 'success');
    }

    public function eliminarLogoEmpresa()
    {
        $config = EmpresaConfig::getConfig();
        $config->deleteLogo();
        $this->empresaLogoActual = null;

        $this->dispatch('notify', message: __('Logo eliminado'), type: 'success');
    }

    // ==================== TAB CUITS ====================

    public function crearCuit()
    {
        $this->resetFormularioCuit();
        $this->modoEdicionCuit = false;
        $this->mostrarModalCuit = true;
    }

    public function editarCuit($id)
    {
        $cuit = Cuit::with('puntosVenta')->findOrFail($id);

        $this->cuitId = $cuit->id;
        $this->cuitNumeroCuit = $cuit->numero_cuit;
        $this->cuitRazonSocial = $cuit->razon_social;
        $this->cuitNombreFantasia = $cuit->nombre_fantasia ?? '';
        $this->cuitDireccion = $cuit->direccion ?? '';

        // Cargar localidades si hay provincia
        if ($cuit->localidad) {
            $this->cuitProvinciaId = $cuit->localidad->provincia_id;
            $this->localidades = Localidad::porProvincia($this->cuitProvinciaId)->ordenadas()->get();
            $this->cuitLocalidadId = $cuit->localidad_id;
        } else {
            $this->cuitProvinciaId = null;
            $this->cuitLocalidadId = null;
            $this->localidades = [];
        }

        $this->cuitCondicionIvaId = $cuit->condicion_iva_id;
        $this->cuitNumeroIibb = $cuit->numero_iibb ?? '';
        $this->cuitFechaInicioActividades = $cuit->fecha_inicio_actividades?->format('Y-m-d');
        $this->cuitFechaVencimientoCertificado = $cuit->fecha_vencimiento_certificado?->format('Y-m-d');
        $this->cuitEntornoAfip = $cuit->entorno_afip;
        $this->cuitActivo = $cuit->activo;

        // Estado de certificados
        $this->cuitTieneCertificado = !empty($cuit->certificado_path);
        $this->cuitTieneClave = !empty($cuit->clave_path);

        $this->puntosVenta = $cuit->puntosVenta->toArray();

        $this->modoEdicionCuit = true;
        $this->mostrarModalCuit = true;
    }

    public function updatedCuitProvinciaId($value)
    {
        if ($value) {
            $this->localidades = Localidad::porProvincia($value)->ordenadas()->get();
        } else {
            $this->localidades = [];
        }
        $this->cuitLocalidadId = null;
    }

    public function guardarCuit()
    {
        $rules = [
            'cuitNumeroCuit' => 'required|digits:11',
            'cuitRazonSocial' => 'required|max:200',
            'cuitNombreFantasia' => 'nullable|max:200',
            'cuitDireccion' => 'nullable|max:500',
            'cuitCondicionIvaId' => 'required|exists:config.condiciones_iva,id',
            'cuitNumeroIibb' => 'nullable|max:50',
            'cuitFechaInicioActividades' => 'nullable|date',
            'cuitFechaVencimientoCertificado' => 'nullable|date',
            'cuitEntornoAfip' => 'required|in:testing,produccion',
            'cuitCertificado' => 'nullable|file|max:1024',
            'cuitClave' => 'nullable|file|max:1024',
        ];

        // Validar unicidad del CUIT
        if (!$this->modoEdicionCuit) {
            $rules['cuitNumeroCuit'] .= '|unique:pymes_tenant.cuits,numero_cuit';
        } else {
            $rules['cuitNumeroCuit'] .= '|unique:pymes_tenant.cuits,numero_cuit,' . $this->cuitId;
        }

        $this->validate($rules, [
            'cuitNumeroCuit.required' => __('El número de CUIT es obligatorio.'),
            'cuitNumeroCuit.digits' => __('El CUIT debe tener 11 dígitos.'),
            'cuitNumeroCuit.unique' => __('Este CUIT ya está registrado.'),
            'cuitRazonSocial.required' => __('La razón social es obligatoria.'),
            'cuitCondicionIvaId.required' => __('La condición de IVA es obligatoria.'),
            'cuitCertificado.max' => __('El certificado no debe superar 1MB.'),
            'cuitClave.max' => __('La clave no debe superar 1MB.'),
        ]);

        // Validar dígito verificador del CUIT
        if (!Cuit::validarCuit($this->cuitNumeroCuit)) {
            $this->addError('cuitNumeroCuit', __('El número de CUIT no es válido.'));
            return;
        }

        try {
            $datos = [
                'numero_cuit' => preg_replace('/\D/', '', $this->cuitNumeroCuit),
                'razon_social' => $this->cuitRazonSocial,
                'nombre_fantasia' => $this->cuitNombreFantasia ?: null,
                'direccion' => $this->cuitDireccion ?: null,
                'localidad_id' => $this->cuitLocalidadId ?: null,
                'condicion_iva_id' => $this->cuitCondicionIvaId,
                'numero_iibb' => $this->cuitNumeroIibb ?: null,
                'fecha_inicio_actividades' => $this->cuitFechaInicioActividades ?: null,
                'fecha_vencimiento_certificado' => $this->cuitFechaVencimientoCertificado ?: null,
                'entorno_afip' => $this->cuitEntornoAfip,
                'activo' => $this->cuitActivo,
            ];

            if ($this->modoEdicionCuit) {
                $cuit = Cuit::findOrFail($this->cuitId);
                $cuit->update($datos);
                $mensaje = __('CUIT actualizado correctamente');
            } else {
                $cuit = Cuit::create($datos);
                $this->cuitId = $cuit->id;
                $this->modoEdicionCuit = true;
                $mensaje = __('CUIT creado correctamente');
            }

            // Guardar certificados si se subieron
            if ($this->cuitCertificado) {
                $cuit->guardarCertificado($this->cuitCertificado);
                $this->cuitCertificado = null;
            }

            if ($this->cuitClave) {
                $cuit->guardarClave($this->cuitClave);
                $this->cuitClave = null;
            }

            // Cerrar modal y resetear formulario
            $this->mostrarModalCuit = false;
            $this->resetFormularioCuit();

            $this->dispatch('notify', message: $mensaje, type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function eliminarCertificadosCuit()
    {
        if (!$this->cuitId) {
            return;
        }

        try {
            $cuit = Cuit::findOrFail($this->cuitId);
            $cuit->eliminarCertificados();

            $this->cuitTieneCertificado = false;
            $this->cuitTieneClave = false;

            $this->dispatch('notify', message: __('Certificados eliminados'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function confirmarEliminarCuit($id)
    {
        $this->cuitEliminarId = $id;
        $this->mostrarConfirmacionEliminarCuit = true;
    }

    public function eliminarCuit()
    {
        try {
            $cuit = Cuit::findOrFail($this->cuitEliminarId);

            // Eliminar puntos de venta y sus certificados
            foreach ($cuit->puntosVenta as $pv) {
                $pv->eliminarCertificados();
                $pv->delete();
            }

            $cuit->delete();

            $this->mostrarConfirmacionEliminarCuit = false;
            $this->cuitEliminarId = null;

            $this->dispatch('notify', message: __('CUIT eliminado correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cerrarModalCuit()
    {
        $this->mostrarModalCuit = false;
        $this->resetFormularioCuit();
    }

    protected function resetFormularioCuit()
    {
        $this->cuitId = null;
        $this->cuitNumeroCuit = '';
        $this->cuitRazonSocial = '';
        $this->cuitNombreFantasia = '';
        $this->cuitDireccion = '';
        $this->cuitProvinciaId = null;
        $this->cuitLocalidadId = null;
        $this->cuitCondicionIvaId = null;
        $this->cuitNumeroIibb = '';
        $this->cuitFechaInicioActividades = null;
        $this->cuitFechaVencimientoCertificado = null;
        $this->cuitEntornoAfip = 'testing';
        $this->cuitActivo = true;
        $this->cuitCertificado = null;
        $this->cuitClave = null;
        $this->cuitTieneCertificado = false;
        $this->cuitTieneClave = false;
        $this->puntosVenta = [];
        $this->localidades = [];
        $this->resetFormularioPuntoVenta();
        $this->resetErrorBag();
    }

    // ==================== PUNTOS DE VENTA ====================

    public function agregarPuntoVenta()
    {
        if (!$this->cuitId) {
            $this->dispatch('notify', message: __('Primero debe guardar el CUIT'), type: 'warning');
            return;
        }

        $this->validate([
            'nuevoPuntoVentaNumero' => 'required|integer|min:1|max:99999',
            'nuevoPuntoVentaNombre' => 'nullable|max:100',
        ], [
            'nuevoPuntoVentaNumero.required' => __('El número de punto de venta es obligatorio.'),
            'nuevoPuntoVentaNumero.integer' => __('El número debe ser un entero.'),
            'nuevoPuntoVentaNumero.min' => __('El número debe ser al menos 1.'),
            'nuevoPuntoVentaNumero.max' => __('El número no puede superar 99999.'),
        ]);

        // Verificar que no exista el número para este CUIT
        $existe = PuntoVenta::where('cuit_id', $this->cuitId)
            ->where('numero', $this->nuevoPuntoVentaNumero)
            ->exists();

        if ($existe) {
            $this->addError('nuevoPuntoVentaNumero', __('Este número de punto de venta ya existe para este CUIT.'));
            return;
        }

        try {
            PuntoVenta::create([
                'cuit_id' => $this->cuitId,
                'numero' => $this->nuevoPuntoVentaNumero,
                'nombre' => $this->nuevoPuntoVentaNombre ?: null,
                'activo' => true,
            ]);

            $this->resetFormularioPuntoVenta();
            $this->puntosVenta = Cuit::find($this->cuitId)->puntosVenta->toArray();

            $this->dispatch('notify', message: __('Punto de venta agregado'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function togglePuntoVentaActivo($id)
    {
        $pv = PuntoVenta::findOrFail($id);
        $pv->activo = !$pv->activo;
        $pv->save();

        $this->puntosVenta = Cuit::find($this->cuitId)->puntosVenta->toArray();

        $estado = $pv->activo ? __('activado') : __('desactivado');
        $this->dispatch('notify', message: __('Punto de venta :numero :estado', ['numero' => $pv->numero_formateado, 'estado' => $estado]), type: 'success');
    }

    public function confirmarEliminarPuntoVenta($id)
    {
        $pv = PuntoVenta::findOrFail($id);
        $this->pvEliminarId = $id;
        $this->pvEliminarNumero = $pv->numero_formateado;
        $this->mostrarConfirmacionEliminarPV = true;
    }

    public function eliminarPuntoVenta()
    {
        try {
            $pv = PuntoVenta::findOrFail($this->pvEliminarId);
            $numero = $pv->numero_formateado;
            $pv->delete();

            $this->puntosVenta = Cuit::find($this->cuitId)->puntosVenta->toArray();
            $this->mostrarConfirmacionEliminarPV = false;
            $this->pvEliminarId = null;
            $this->pvEliminarNumero = null;

            $this->dispatch('notify', message: __('Punto de venta :numero eliminado', ['numero' => $numero]), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cancelarEliminarPuntoVenta()
    {
        $this->mostrarConfirmacionEliminarPV = false;
        $this->pvEliminarId = null;
        $this->pvEliminarNumero = null;
    }

    protected function resetFormularioPuntoVenta()
    {
        $this->nuevoPuntoVentaNumero = '';
        $this->nuevoPuntoVentaNombre = '';
    }

    // ==================== TAB SUCURSALES ====================

    public function editarSucursal($id)
    {
        $sucursal = Sucursal::findOrFail($id);

        $this->sucursalEditandoId = $sucursal->id;
        $this->sucursalNombre = $sucursal->nombre;
        $this->sucursalNombrePublico = $sucursal->nombre_publico ?? '';
        $this->sucursalDireccion = $sucursal->direccion ?? '';
        $this->sucursalTelefono = $sucursal->telefono ?? '';
        $this->sucursalEmail = $sucursal->email ?? '';
    }

    public function guardarSucursal()
    {
        $this->validate([
            'sucursalNombre' => 'required|max:100',
            'sucursalNombrePublico' => 'nullable|max:200',
            'sucursalDireccion' => 'nullable|max:500',
            'sucursalTelefono' => 'nullable|max:50',
            'sucursalEmail' => 'nullable|email|max:100',
            'sucursalLogo' => 'nullable|image|max:2048',
        ], [
            'sucursalNombre.required' => __('El nombre interno es obligatorio.'),
            'sucursalEmail.email' => __('El email no es válido.'),
            'sucursalLogo.image' => __('El archivo debe ser una imagen.'),
            'sucursalLogo.max' => __('La imagen no debe superar los 2MB.'),
        ]);

        try {
            $sucursal = Sucursal::findOrFail($this->sucursalEditandoId);

            $sucursal->nombre = $this->sucursalNombre;
            $sucursal->nombre_publico = $this->sucursalNombrePublico ?: null;
            $sucursal->direccion = $this->sucursalDireccion ?: null;
            $sucursal->telefono = $this->sucursalTelefono ?: null;
            $sucursal->email = $this->sucursalEmail ?: null;

            if ($this->sucursalLogo) {
                $sucursal->updateLogo($this->sucursalLogo);
                $this->sucursalLogo = null;
            }

            $sucursal->save();

            $this->cancelarEdicionSucursal();

            $this->dispatch('notify', message: __('Sucursal actualizada correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cancelarEdicionSucursal()
    {
        $this->sucursalEditandoId = null;
        $this->sucursalNombre = '';
        $this->sucursalNombrePublico = '';
        $this->sucursalDireccion = '';
        $this->sucursalTelefono = '';
        $this->sucursalEmail = '';
        $this->sucursalLogo = null;
        $this->resetErrorBag();
    }

    public function eliminarLogoSucursal($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->deleteLogo();

        $this->dispatch('notify', message: __('Logo eliminado'), type: 'success');
    }

    // ==================== CONFIGURACIÓN DE SUCURSAL ====================

    public function abrirConfigSucursal($id)
    {
        $sucursal = Sucursal::findOrFail($id);

        $this->configSucursalId = $sucursal->id;
        $this->configSucursalNombre = $sucursal->nombre;

        // Cargar configuración actual
        $this->configUsaClaveAutorizacion = $sucursal->usa_clave_autorizacion ?? false;
        $this->configClaveAutorizacion = $sucursal->clave_autorizacion ?? '';
        $this->configTipoImpresionFactura = $sucursal->tipo_impresion_factura ?? 'ambos';
        $this->configImprimeEncabezadoComanda = $sucursal->imprime_encabezado_comanda ?? true;
        $this->configAgrupaArticulosVenta = $sucursal->agrupa_articulos_venta ?? true;
        $this->configAgrupaArticulosImpresion = $sucursal->agrupa_articulos_impresion ?? true;
        $this->configControlStockVenta = $sucursal->control_stock_venta ?? 'bloquea';
        $this->configFacturacionFiscalAutomatica = $sucursal->facturacion_fiscal_automatica ?? false;
        $this->configUsaWhatsappEscritorio = $sucursal->usa_whatsapp_escritorio ?? false;
        $this->configEnviaWhatsappComanda = $sucursal->envia_whatsapp_comanda ?? false;
        $this->configMensajeWhatsappComanda = $sucursal->mensaje_whatsapp_comanda ?? '';
        $this->configEnviaWhatsappListo = $sucursal->envia_whatsapp_listo ?? false;
        $this->configMensajeWhatsappListo = $sucursal->mensaje_whatsapp_listo ?? '';

        $this->mostrarModalConfigSucursal = true;
    }

    public function updatedConfigAgrupaArticulosVenta($value)
    {
        // Si agrupa en venta es true, agrupa en impresión debe ser true también
        if ($value) {
            $this->configAgrupaArticulosImpresion = true;
        }
    }

    public function guardarConfigSucursal()
    {
        $this->validate([
            'configClaveAutorizacion' => $this->configUsaClaveAutorizacion ? 'required|min:4' : 'nullable',
            'configTipoImpresionFactura' => 'required|in:solo_datos,solo_logo,ambos',
            'configMensajeWhatsappComanda' => 'nullable|max:500',
            'configMensajeWhatsappListo' => 'nullable|max:500',
        ], [
            'configClaveAutorizacion.required' => __('Debe ingresar una clave de autorización.'),
            'configClaveAutorizacion.min' => __('La clave debe tener al menos 4 caracteres.'),
            'configMensajeWhatsappComanda.max' => __('El mensaje no puede superar 500 caracteres.'),
            'configMensajeWhatsappListo.max' => __('El mensaje no puede superar 500 caracteres.'),
        ]);

        try {
            $sucursal = Sucursal::findOrFail($this->configSucursalId);

            // Si agrupa en venta es true, agrupa en impresión obligatoriamente es true
            $agrupaImpresion = $this->configAgrupaArticulosVenta ? true : $this->configAgrupaArticulosImpresion;

            $sucursal->update([
                'usa_clave_autorizacion' => $this->configUsaClaveAutorizacion,
                'clave_autorizacion' => $this->configUsaClaveAutorizacion ? $this->configClaveAutorizacion : null,
                'tipo_impresion_factura' => $this->configTipoImpresionFactura,
                'imprime_encabezado_comanda' => $this->configImprimeEncabezadoComanda,
                'agrupa_articulos_venta' => $this->configAgrupaArticulosVenta,
                'agrupa_articulos_impresion' => $agrupaImpresion,
                'control_stock_venta' => $this->configControlStockVenta,
                'facturacion_fiscal_automatica' => $this->configFacturacionFiscalAutomatica,
                'usa_whatsapp_escritorio' => $this->configUsaWhatsappEscritorio,
                'envia_whatsapp_comanda' => $this->configEnviaWhatsappComanda,
                'mensaje_whatsapp_comanda' => $this->configEnviaWhatsappComanda ? $this->configMensajeWhatsappComanda : null,
                'envia_whatsapp_listo' => $this->configEnviaWhatsappListo,
                'mensaje_whatsapp_listo' => $this->configEnviaWhatsappListo ? $this->configMensajeWhatsappListo : null,
            ]);

            $this->cerrarModalConfigSucursal();
            $this->dispatch('notify', message: __('Configuración guardada correctamente'), type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cerrarModalConfigSucursal()
    {
        $this->mostrarModalConfigSucursal = false;
        $this->configSucursalId = null;
        $this->configSucursalNombre = '';
        $this->configUsaClaveAutorizacion = false;
        $this->configClaveAutorizacion = '';
        $this->configTipoImpresionFactura = 'ambos';
        $this->configImprimeEncabezadoComanda = true;
        $this->configAgrupaArticulosVenta = true;
        $this->configAgrupaArticulosImpresion = true;
        $this->configControlStockVenta = 'bloquea';
        $this->configFacturacionFiscalAutomatica = false;
        $this->configUsaWhatsappEscritorio = false;
        $this->configEnviaWhatsappComanda = false;
        $this->configMensajeWhatsappComanda = '';
        $this->configEnviaWhatsappListo = false;
        $this->configMensajeWhatsappListo = '';
        $this->resetErrorBag();
    }

    // ==================== TAB CAJAS ====================

    // --- Modal Configuración General de Caja ---

    public function abrirConfigCaja($id)
    {
        $caja = Caja::findOrFail($id);

        $this->configCajaId = $caja->id;
        $this->configCajaNombre = $caja->nombre;
        $this->configCajaLimiteEfectivo = $caja->limite_efectivo;
        $this->configCajaModoCargaInicial = $caja->modo_carga_inicial ?? 'manual';
        $this->configCajaMontoFijoInicial = $caja->monto_fijo_inicial;

        $this->mostrarModalConfigCaja = true;
    }

    public function guardarConfigCaja()
    {
        $this->validate([
            'configCajaLimiteEfectivo' => 'nullable|numeric|min:0',
            'configCajaModoCargaInicial' => 'required|in:manual,ultimo_cierre,monto_fijo',
            'configCajaMontoFijoInicial' => 'nullable|numeric|min:0',
        ], [
            'configCajaLimiteEfectivo.numeric' => __('El límite debe ser un número.'),
            'configCajaLimiteEfectivo.min' => __('El límite no puede ser negativo.'),
            'configCajaMontoFijoInicial.numeric' => __('El monto fijo debe ser un número.'),
            'configCajaMontoFijoInicial.min' => __('El monto fijo no puede ser negativo.'),
        ]);

        try {
            $caja = Caja::findOrFail($this->configCajaId);

            $caja->limite_efectivo = $this->configCajaLimiteEfectivo ?: null;
            $caja->modo_carga_inicial = $this->configCajaModoCargaInicial;
            $caja->monto_fijo_inicial = $this->configCajaModoCargaInicial === 'monto_fijo'
                ? $this->configCajaMontoFijoInicial
                : null;

            $caja->save();

            $this->cerrarModalConfigCaja();

            $this->dispatch('notify', message: __('Configuración guardada correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cerrarModalConfigCaja()
    {
        $this->mostrarModalConfigCaja = false;
        $this->configCajaId = null;
        $this->configCajaNombre = '';
        $this->configCajaLimiteEfectivo = null;
        $this->configCajaModoCargaInicial = 'manual';
        $this->configCajaMontoFijoInicial = null;
        $this->resetErrorBag();
    }

    // --- Edición de Puntos de Venta ---

    public function editarPuntosCaja($id)
    {
        $caja = Caja::with('puntosVenta')->findOrFail($id);

        $this->cajaEditandoPuntosId = $caja->id;

        // Cargar puntos de venta asignados
        $this->cajaPuntosAsignados = $caja->puntosVenta->pluck('id')->toArray();

        // Cargar el punto de venta por defecto
        $puntoDefecto = $caja->puntosVenta->where('pivot.es_defecto', true)->first();
        $this->cajaPuntoDefecto = $puntoDefecto ? $puntoDefecto->id : null;
    }

    public function togglePuntoVentaCaja($puntoVentaId)
    {
        $puntoVentaId = (int) $puntoVentaId;

        if (in_array($puntoVentaId, $this->cajaPuntosAsignados)) {
            // Quitar de asignados
            $this->cajaPuntosAsignados = array_values(array_filter(
                $this->cajaPuntosAsignados,
                fn($id) => $id !== $puntoVentaId
            ));

            // Si era el defecto, quitar
            if ($this->cajaPuntoDefecto === $puntoVentaId) {
                $this->cajaPuntoDefecto = null;
            }
        } else {
            // Agregar a asignados
            $this->cajaPuntosAsignados[] = $puntoVentaId;
        }
    }

    public function setPuntoVentaDefecto($puntoVentaId)
    {
        $puntoVentaId = (int) $puntoVentaId;

        // Solo se puede establecer como defecto si está asignado
        if (in_array($puntoVentaId, $this->cajaPuntosAsignados)) {
            $this->cajaPuntoDefecto = $puntoVentaId;
        }
    }

    public function guardarPuntosCaja()
    {
        if (!$this->cajaEditandoPuntosId) {
            return;
        }

        try {
            $caja = Caja::findOrFail($this->cajaEditandoPuntosId);

            // Eliminar asignaciones previas
            PuntoVentaCaja::where('caja_id', $caja->id)->delete();

            // Si hay puntos asignados pero ninguno es defecto, usar el primero
            if (count($this->cajaPuntosAsignados) > 0 && !$this->cajaPuntoDefecto) {
                $this->cajaPuntoDefecto = $this->cajaPuntosAsignados[0];
            }

            // Crear nuevas asignaciones
            foreach ($this->cajaPuntosAsignados as $puntoVentaId) {
                PuntoVentaCaja::create([
                    'punto_venta_id' => $puntoVentaId,
                    'caja_id' => $caja->id,
                    'es_defecto' => $puntoVentaId === $this->cajaPuntoDefecto,
                ]);
            }

            $this->cancelarEdicionPuntosCaja();

            $this->dispatch('notify', message: __('Puntos de venta actualizados correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    public function cancelarEdicionPuntosCaja()
    {
        $this->cajaEditandoPuntosId = null;
        $this->cajaPuntosAsignados = [];
        $this->cajaPuntoDefecto = null;
    }

    // ==================== GRUPOS DE CIERRE ====================

    /**
     * Abre el modal para crear un nuevo grupo de cierre
     */
    public function crearGrupoCierre($sucursalId)
    {
        $this->resetFormularioGrupo();
        $this->grupoSucursalId = $sucursalId;
        $this->modoEdicionGrupo = false;
        $this->mostrarModalGrupoCierre = true;
    }

    /**
     * Abre el modal para editar un grupo existente
     */
    public function editarGrupoCierre($grupoId)
    {
        $grupo = GrupoCierre::with('cajas')->findOrFail($grupoId);

        // Verificar si el grupo tiene turno abierto
        if ($this->grupoTieneTurnoAbierto($grupo)) {
            $this->dispatch('notify', message: __('No se puede modificar el grupo porque tiene un turno abierto. Cierre el turno primero.'), type: 'warning');
            return;
        }

        $this->grupoId = $grupo->id;
        $this->grupoSucursalId = $grupo->sucursal_id;
        $this->grupoNombre = $grupo->nombre ?? '';
        $this->grupoCajasSeleccionadas = $grupo->cajas->pluck('id')->toArray();
        $this->grupoFondoComun = (bool) $grupo->fondo_comun;

        $this->modoEdicionGrupo = true;
        $this->mostrarModalGrupoCierre = true;
    }

    /**
     * Alterna la selección de una caja en el grupo
     */
    public function toggleCajaEnGrupo($cajaId)
    {
        $cajaId = (int) $cajaId;

        if (in_array($cajaId, $this->grupoCajasSeleccionadas)) {
            $this->grupoCajasSeleccionadas = array_values(array_filter(
                $this->grupoCajasSeleccionadas,
                fn($id) => $id !== $cajaId
            ));
        } else {
            $this->grupoCajasSeleccionadas[] = $cajaId;
        }
    }

    /**
     * Guarda el grupo de cierre (crear o actualizar)
     */
    public function guardarGrupoCierre()
    {
        // Validación: mínimo 2 cajas para un grupo
        if (count($this->grupoCajasSeleccionadas) < 2) {
            $this->dispatch('notify', message: __('Un grupo de cierre debe tener al menos 2 cajas'), type: 'warning');
            return;
        }

        try {
            if ($this->modoEdicionGrupo) {
                $grupo = GrupoCierre::findOrFail($this->grupoId);
                $grupo->nombre = $this->grupoNombre ?: null;
                $grupo->fondo_comun = $this->grupoFondoComun;
                $grupo->save();
                $mensaje = __('Grupo de cierre actualizado correctamente');
            } else {
                $grupo = GrupoCierre::create([
                    'sucursal_id' => $this->grupoSucursalId,
                    'nombre' => $this->grupoNombre ?: null,
                    'fondo_comun' => $this->grupoFondoComun,
                    'activo' => true,
                ]);
                $mensaje = __('Grupo de cierre creado correctamente');
            }

            // Primero, quitar las cajas que ya no están en el grupo
            Caja::where('grupo_cierre_id', $grupo->id)
                ->whereNotIn('id', $this->grupoCajasSeleccionadas)
                ->update(['grupo_cierre_id' => null]);

            // Luego, asignar las cajas seleccionadas al grupo
            Caja::whereIn('id', $this->grupoCajasSeleccionadas)
                ->update(['grupo_cierre_id' => $grupo->id]);

            $this->cerrarModalGrupoCierre();
            $this->dispatch('notify', message: $mensaje, type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ') . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Confirma la eliminación de un grupo
     */
    public function confirmarEliminarGrupo($grupoId)
    {
        $grupo = GrupoCierre::with('cajas')->find($grupoId);

        if (!$grupo) {
            $this->dispatch('notify', message: __('Grupo no encontrado'), type: 'error');
            return;
        }

        // Verificar si el grupo tiene turno abierto
        if ($this->grupoTieneTurnoAbierto($grupo)) {
            $this->dispatch('notify', message: __('No se puede eliminar el grupo porque tiene un turno abierto. Cierre el turno primero.'), type: 'warning');
            return;
        }

        $this->grupoEliminarId = $grupoId;
        $this->mostrarConfirmacionEliminarGrupo = true;
    }

    /**
     * Elimina el grupo de cierre
     */
    public function eliminarGrupoCierre()
    {
        try {
            $grupo = GrupoCierre::with('cajas')->findOrFail($this->grupoEliminarId);

            // Verificar nuevamente si el grupo tiene turno abierto
            if ($this->grupoTieneTurnoAbierto($grupo)) {
                $this->dispatch('notify', message: __('No se puede eliminar el grupo porque tiene un turno abierto.'), type: 'warning');
                $this->mostrarConfirmacionEliminarGrupo = false;
                return;
            }

            // Quitar las cajas del grupo (pasan a individuales)
            Caja::where('grupo_cierre_id', $grupo->id)
                ->update(['grupo_cierre_id' => null]);

            $grupo->delete();

            $this->mostrarConfirmacionEliminarGrupo = false;
            $this->grupoEliminarId = null;

            $this->dispatch('notify', message: __('Grupo eliminado. Las cajas ahora cierran de forma individual.'), type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Cancela la eliminación del grupo
     */
    public function cancelarEliminarGrupo()
    {
        $this->mostrarConfirmacionEliminarGrupo = false;
        $this->grupoEliminarId = null;
    }

    /**
     * Cierra el modal de grupo de cierre
     */
    public function cerrarModalGrupoCierre()
    {
        $this->mostrarModalGrupoCierre = false;
        $this->resetFormularioGrupo();
    }

    /**
     * Resetea el formulario de grupo
     */
    protected function resetFormularioGrupo()
    {
        $this->grupoId = null;
        $this->grupoSucursalId = null;
        $this->grupoNombre = '';
        $this->grupoCajasSeleccionadas = [];
        $this->grupoFondoComun = false;
        $this->modoEdicionGrupo = false;
        $this->resetErrorBag();
    }

    /**
     * Verifica si un grupo tiene alguna caja con turno abierto
     */
    protected function grupoTieneTurnoAbierto(GrupoCierre $grupo): bool
    {
        // Un grupo tiene turno abierto si alguna de sus cajas está abierta
        // o tiene movimientos sin cerrar
        return $grupo->cajas->contains(function ($caja) {
            return $caja->estado === 'abierta' ||
                   MovimientoCaja::where('caja_id', $caja->id)
                       ->whereNull('cierre_turno_id')
                       ->exists();
        });
    }

    /**
     * Obtiene las cajas disponibles para un grupo (de la misma sucursal)
     */
    public function getCajasDisponiblesParaGrupo()
    {
        if (!$this->grupoSucursalId) {
            return collect();
        }

        // En modo edición, mostrar todas las cajas de la sucursal
        // En modo creación, mostrar solo cajas sin grupo o las del grupo actual
        return Caja::where('sucursal_id', $this->grupoSucursalId)
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('grupo_cierre_id');
                if ($this->grupoId) {
                    $query->orWhere('grupo_cierre_id', $this->grupoId);
                }
            })
            ->orderBy('nombre')
            ->get();
    }

    public function render()
    {
        return view('livewire.configuracion.configuracion-empresa');
    }
}
