<?php

namespace App\Livewire\Configuracion;

use App\Models\Caja;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\EmpresaConfig;
use App\Models\GrupoCierre;
use App\Models\Localidad;
use App\Models\MovimientoCaja;
use App\Models\PuntoVenta;
use App\Models\PuntoVentaCaja;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\PantallaPublicaService;
use App\Services\Pedidos\PedidoMostradorService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Lazy]
#[Layout('layouts.app')]
class ConfiguracionEmpresa extends Component
{
    use \App\Traits\ManejaDomicilio;
    use WithFileUploads;

    // ==================== TAB CONTROL ====================
    // Persistido en la URL (?tab=sucursales) para que F5 mantenga la pestaña.
    // 'empresa' es el default y se omite del query string.
    #[Url(as: 'tab', except: 'empresa')]
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

    // Confirmación de eliminación CUIT
    public $mostrarConfirmacionEliminarCuit = false;

    public $cuitEliminarId = null;

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

    public $configControlStockProduccion = 'bloquea';

    public $configFacturacionFiscalAutomatica = false;

    public $configUsaWhatsappEscritorio = false;

    public $configEnviaWhatsappComanda = false;

    public $configMensajeWhatsappComanda = '';

    public $configEnviaWhatsappListo = false;

    public $configMensajeWhatsappListo = '';

    // Pedidos por Mostrador (flags de la sucursal)
    public bool $configPedidoConversionAutomaticaAlEntregar = false;

    public bool $configUsaBeepers = false;

    public bool $configImprimeComandaAutomatico = false;

    // Numeración de display (turno) — se edita dentro del modal Configurar Sucursal
    public bool $configUsaNumeracionDisplay = false;

    public string $configNumeracionDisplayModo = 'diario';

    /** @var list<int> horas de reset 0-23 para modo diario */
    public array $configNumeracionDisplayHoras = [6];

    public $configNumeracionNuevaHora = '';

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

    public $configCajaUsaPantallaCliente = false;

    // ==================== PERSONALIZAR PANTALLA CLIENTE (2da pantalla) ====================
    public $mostrarModalPersonalizarPantalla = false;

    public $pcSucursalId = null;

    public $pcSucursalNombre = '';

    public $pcLogoUrl = null;

    public $pcMostrarLogo = true;

    public $pcMostrarNombre = true;

    public $pcColorFondo = '#222036';

    public $pcAnimacion = 'aurora';

    public $pcColorAcento = '#22d3ee';

    public $pcColorTexto = 'auto';

    public $pcMensajeIdle = 'Listo para cobrar';

    public $pcTamanoLogo = 'md';

    // ==================== MONITOR LLAMADOR (pantalla Clase B) ====================
    public bool $mostrarModalLlamador = false;

    public ?int $llSucursalId = null;

    public string $llSucursalNombre = '';

    public bool $llUsaLlamador = false;

    public ?string $llToken = null;

    public ?string $llCodigo = null;

    public bool $llLogoActual = false;

    public ?string $llLogoUrl = null;

    // Personalización del llamador (config_llamador)
    public string $llTitulo = 'Pedidos';

    public bool $llMostrarLogo = true;

    public string $llColorFondo = '#0f172a';

    public string $llColorPreparacion = '#f59e0b';

    public string $llColorListo = '#22c55e';

    public bool $llSonido = true;

    public string $llTamano = 'normal';

    // ==================== CONSULTOR DE PRECIOS (pantalla Clase B) ====================
    public bool $mostrarModalConsultor = false;

    public ?int $cpSucursalId = null;

    public string $cpSucursalNombre = '';

    public bool $cpUsaConsultor = false;

    public ?string $cpToken = null;

    public ?string $cpCodigo = null;

    public bool $cpLogoActual = false;

    public ?string $cpLogoUrl = null;

    // Personalización del consultor (config_consultor_precios)
    public string $cpTitulo = 'Consultá tu precio';

    public bool $cpMostrarLogo = true;

    public string $cpColorFondo = '#0f172a';

    public string $cpColorAcento = '#22d3ee';

    public string $cpMensajeIdle = 'Escanee un artículo';

    public int $cpDuracion = 5;

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

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-form :tabs="5" :fields="8" />
        HTML;
    }

    /** Pestañas válidas (para sanear el valor que viene de la URL). */
    public const TABS = ['empresa', 'cuits', 'sucursales', 'cajas'];

    public function mount()
    {
        // Sanear el tab que llega por query string (?tab=...): si es inválido,
        // volver al default para no romper la vista.
        if (! in_array($this->tabActivo, self::TABS, true)) {
            $this->tabActivo = 'empresa';
        }

        $this->cargarDatosEmpresa();
    }

    public function cambiarTab($tab)
    {
        $this->tabActivo = in_array($tab, self::TABS, true) ? $tab : 'empresa';
    }

    // ==================== GETTERS COMPUTADOS ====================

    public function getCuitsProperty()
    {
        return Cuit::with(['puntosVenta', 'condicionIva', 'domicilios.localidad'])
            ->orderBy('razon_social')
            ->get();
    }

    public function getSucursalesProperty()
    {
        return CatalogoCache::sucursalesTodas();
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
            ->whereHas('cuit', fn ($q) => $q->where('activo', true))
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
        $cuit = Cuit::findOrFail($id);

        $this->cuitId = $cuit->id;
        $this->cuitNumeroCuit = $cuit->numero_cuit;
        $this->cuitRazonSocial = $cuit->razon_social;
        $this->cuitNombreFantasia = $cuit->nombre_fantasia ?? '';
        // El domicilio fiscal se gestiona aparte (botón Domicilios → cuit_domicilios).
        $this->cuitCondicionIvaId = $cuit->condicion_iva_id;
        $this->cuitNumeroIibb = $cuit->numero_iibb ?? '';
        $this->cuitFechaInicioActividades = $cuit->fecha_inicio_actividades?->format('Y-m-d');
        $this->cuitFechaVencimientoCertificado = $cuit->fecha_vencimiento_certificado?->format('Y-m-d');
        $this->cuitEntornoAfip = $cuit->entorno_afip;
        $this->cuitActivo = $cuit->activo;

        // Estado de certificados
        $this->cuitTieneCertificado = ! empty($cuit->certificado_path);
        $this->cuitTieneClave = ! empty($cuit->clave_path);

        $this->modoEdicionCuit = true;
        $this->mostrarModalCuit = true;
    }

    /**
     * Refresca el resumen de PV de la card de CUITs cuando el modal de puntos de
     * venta los modifica (forzar re-render recomputa $this->cuits).
     */
    #[On('puntos-venta-actualizados')]
    public function refrescarResumenCuits(): void
    {
        // El listener basta para disparar el re-render; $this->cuits se recomputa.
    }

    public function guardarCuit()
    {
        $rules = [
            'cuitNumeroCuit' => 'required|digits:11',
            'cuitRazonSocial' => 'required|max:200',
            'cuitNombreFantasia' => 'nullable|max:200',
            'cuitCondicionIvaId' => 'required|exists:config.condiciones_iva,id',
            'cuitNumeroIibb' => 'nullable|max:50',
            'cuitFechaInicioActividades' => 'nullable|date',
            'cuitFechaVencimientoCertificado' => 'nullable|date',
            'cuitEntornoAfip' => 'required|in:testing,produccion',
            'cuitCertificado' => 'nullable|file|max:1024',
            'cuitClave' => 'nullable|file|max:1024',
        ];

        // Validar unicidad del CUIT
        if (! $this->modoEdicionCuit) {
            $rules['cuitNumeroCuit'] .= '|unique:pymes_tenant.cuits,numero_cuit';
        } else {
            $rules['cuitNumeroCuit'] .= '|unique:pymes_tenant.cuits,numero_cuit,'.$this->cuitId;
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
        if (! Cuit::validarCuit($this->cuitNumeroCuit)) {
            $this->addError('cuitNumeroCuit', __('El número de CUIT no es válido.'));

            return;
        }

        try {
            $datos = [
                'numero_cuit' => preg_replace('/\D/', '', $this->cuitNumeroCuit),
                'razon_social' => $this->cuitRazonSocial,
                'nombre_fantasia' => $this->cuitNombreFantasia ?: null,
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
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    public function eliminarCertificadosCuit()
    {
        if (! $this->cuitId) {
            return;
        }

        try {
            $cuit = Cuit::findOrFail($this->cuitId);
            $cuit->eliminarCertificados();

            $this->cuitTieneCertificado = false;
            $this->cuitTieneClave = false;

            $this->dispatch('notify', message: __('Certificados eliminados'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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

            // Eliminar puntos de venta. Los certificados digitales se manejan a
            // nivel de CUIT (no de PV), por eso solo se borra el PV.
            foreach ($cuit->puntosVenta as $pv) {
                $pv->delete();
            }

            // Eliminar los certificados del CUIT antes de borrarlo.
            $cuit->eliminarCertificados();
            $cuit->delete();

            $this->mostrarConfirmacionEliminarCuit = false;
            $this->cuitEliminarId = null;

            $this->dispatch('notify', message: __('CUIT eliminado correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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
        $this->resetErrorBag();
    }

    // El ABM de puntos de venta se movió al componente embebido CuitPuntosVenta
    // (RF-11, refactor UI: un botón por sección — Impuestos / Domicilios / Puntos).

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

        // Domicilio físico estructurado (RF-11): provincia (ISO) + localidad + geo.
        // La dirección se mantiene en $sucursalDireccion (el partial la oculta acá).
        $this->setDomicilioDesde([
            'provincia' => $sucursal->provincia,
            'localidad_id' => $sucursal->localidad_id,
            'latitud' => $sucursal->latitud,
            'longitud' => $sucursal->longitud,
        ]);
    }

    public function guardarSucursal()
    {
        $this->validate(array_merge([
            'sucursalNombre' => 'required|max:100',
            'sucursalNombrePublico' => 'nullable|max:200',
            'sucursalDireccion' => 'nullable|max:500',
            'sucursalTelefono' => 'nullable|max:50',
            'sucursalEmail' => 'nullable|email|max:100',
            'sucursalLogo' => 'nullable|image|max:2048',
        ], [
            // La provincia del domicilio físico es opcional (la sucursal puede no
            // tener el domicilio cargado todavía).
            'domProvincia' => 'nullable|string|max:6',
            'domLocalidadId' => 'nullable|integer',
            'domLatitud' => 'nullable|numeric|between:-90,90',
            'domLongitud' => 'nullable|numeric|between:-180,180',
        ]), [
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

            // Domicilio físico estructurado (RF-11, Fase 9).
            $sucursal->provincia = $this->domProvincia ?: null;
            $sucursal->localidad_id = $this->domLocalidadId ?: null;
            // Mantener el string `localidad` en sync con el catálogo: lo consume el
            // sync de Mercado Pago (city_name) y el estado en integraciones de pago.
            $sucursal->localidad = $this->domLocalidadId ? Localidad::find($this->domLocalidadId)?->nombre : null;
            $sucursal->latitud = $this->domLatitud !== null && $this->domLatitud !== '' ? $this->domLatitud : null;
            $sucursal->longitud = $this->domLongitud !== null && $this->domLongitud !== '' ? $this->domLongitud : null;

            if ($this->sucursalLogo) {
                $sucursal->updateLogo($this->sucursalLogo);
                $this->sucursalLogo = null;
            }

            $sucursal->save();

            // Invalidar el caché de catálogos para que las cards reflejen los
            // cambios (logo, nombre, domicilio) al instante; si no, leen la
            // colección cacheada (TTL 1h) y parecería que "no se guardó".
            CatalogoCache::clear();

            $this->cancelarEdicionSucursal();

            $this->dispatch('notify', message: __('Sucursal actualizada correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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
        $this->resetDomicilio();
        $this->resetErrorBag();
    }

    public function eliminarLogoSucursal($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->deleteLogo();

        // Invalidar caché para que la card/preview deje de mostrar el logo borrado.
        CatalogoCache::clear();

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
        $this->configControlStockProduccion = $sucursal->control_stock_produccion ?? 'bloquea';
        $this->configFacturacionFiscalAutomatica = $sucursal->facturacion_fiscal_automatica ?? false;
        $this->configUsaWhatsappEscritorio = $sucursal->usa_whatsapp_escritorio ?? false;
        $this->configEnviaWhatsappComanda = $sucursal->envia_whatsapp_comanda ?? false;
        $this->configMensajeWhatsappComanda = $sucursal->mensaje_whatsapp_comanda ?? '';
        $this->configEnviaWhatsappListo = $sucursal->envia_whatsapp_listo ?? false;
        $this->configMensajeWhatsappListo = $sucursal->mensaje_whatsapp_listo ?? '';
        $this->configPedidoConversionAutomaticaAlEntregar = (bool) ($sucursal->pedido_conversion_automatica_al_entregar ?? false);
        $this->configUsaBeepers = (bool) ($sucursal->usa_beepers ?? false);
        $this->configImprimeComandaAutomatico = (bool) ($sucursal->imprime_comanda_automatico ?? false);
        $this->configUsaNumeracionDisplay = (bool) ($sucursal->usa_numeracion_display ?? false);
        $this->configNumeracionDisplayModo = $sucursal->numeracion_display_modo ?? 'diario';
        $this->configNumeracionDisplayHoras = $sucursal->horasResetDisplay();
        $this->configNumeracionNuevaHora = '';

        $this->mostrarModalConfigSucursal = true;
    }

    /**
     * Agrega una hora de reset (0-23) a la numeración de display, evitando
     * duplicados y manteniendo la lista ordenada.
     */
    public function agregarHoraNumeracion(): void
    {
        $hora = (int) $this->configNumeracionNuevaHora;

        if ($this->configNumeracionNuevaHora === '' || $hora < 0 || $hora > 23) {
            return;
        }

        $horas = $this->configNumeracionDisplayHoras;
        $horas[] = $hora;
        $horas = array_values(array_unique($horas));
        sort($horas);

        $this->configNumeracionDisplayHoras = $horas;
        $this->configNumeracionNuevaHora = '';
    }

    public function quitarHoraNumeracion(int $hora): void
    {
        $this->configNumeracionDisplayHoras = array_values(
            array_filter($this->configNumeracionDisplayHoras, fn ($h) => (int) $h !== $hora)
        );
    }

    /**
     * Reinicia manualmente el contador de numeración de display de la sucursal en
     * edición (modo manual: por turno/tanda). Append-only: el `numero` permanente
     * no se toca, solo el contador del número visible.
     */
    public function reiniciarNumeracionDisplay(PedidoMostradorService $service): void
    {
        if (! $this->configSucursalId) {
            return;
        }

        $service->reiniciarNumeracionDisplay((int) $this->configSucursalId, (int) auth()->id());

        $this->dispatch('notify', message: __('Numeración reiniciada. El próximo pedido arranca de 1.'), type: 'success');
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
            'configNumeracionDisplayModo' => 'required|in:diario,manual',
        ], [
            'configClaveAutorizacion.required' => __('Debe ingresar una clave de autorización.'),
            'configClaveAutorizacion.min' => __('La clave debe tener al menos 4 caracteres.'),
            'configMensajeWhatsappComanda.max' => __('El mensaje no puede superar 500 caracteres.'),
            'configMensajeWhatsappListo.max' => __('El mensaje no puede superar 500 caracteres.'),
        ]);

        // Numeración de display: en modo diario garantizamos al menos una hora (default 6am).
        $horas = array_values(array_unique(array_map('intval', $this->configNumeracionDisplayHoras)));
        sort($horas);
        if ($this->configNumeracionDisplayModo === 'diario' && empty($horas)) {
            $horas = [6];
        }

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
                'control_stock_produccion' => $this->configControlStockProduccion,
                'facturacion_fiscal_automatica' => $this->configFacturacionFiscalAutomatica,
                'usa_whatsapp_escritorio' => $this->configUsaWhatsappEscritorio,
                'envia_whatsapp_comanda' => $this->configEnviaWhatsappComanda,
                'mensaje_whatsapp_comanda' => $this->configEnviaWhatsappComanda ? $this->configMensajeWhatsappComanda : null,
                'envia_whatsapp_listo' => $this->configEnviaWhatsappListo,
                'mensaje_whatsapp_listo' => $this->configEnviaWhatsappListo ? $this->configMensajeWhatsappListo : null,
                'pedido_conversion_automatica_al_entregar' => $this->configPedidoConversionAutomaticaAlEntregar,
                'usa_beepers' => $this->configUsaBeepers,
                'imprime_comanda_automatico' => $this->configImprimeComandaAutomatico,
                'usa_numeracion_display' => $this->configUsaNumeracionDisplay,
                'numeracion_display_modo' => $this->configNumeracionDisplayModo,
                'numeracion_display_horas' => $horas,
            ]);

            $this->cerrarModalConfigSucursal();
            $this->dispatch('notify', message: __('Configuración guardada correctamente'), type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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
        $this->configControlStockProduccion = 'bloquea';
        $this->configFacturacionFiscalAutomatica = false;
        $this->configUsaWhatsappEscritorio = false;
        $this->configEnviaWhatsappComanda = false;
        $this->configMensajeWhatsappComanda = '';
        $this->configEnviaWhatsappListo = false;
        $this->configMensajeWhatsappListo = '';
        $this->configPedidoConversionAutomaticaAlEntregar = false;
        $this->configUsaBeepers = false;
        $this->configImprimeComandaAutomatico = false;
        $this->configUsaNumeracionDisplay = false;
        $this->configNumeracionDisplayModo = 'diario';
        $this->configNumeracionDisplayHoras = [6];
        $this->configNumeracionNuevaHora = '';
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
        $this->configCajaUsaPantallaCliente = (bool) $caja->usa_pantalla_cliente;

        $this->mostrarModalConfigCaja = true;
    }

    public function guardarConfigCaja()
    {
        $this->configCajaNombre = trim((string) $this->configCajaNombre);

        $this->validate([
            'configCajaNombre' => 'required|string|max:15',
            'configCajaLimiteEfectivo' => 'nullable|numeric|min:0',
            'configCajaModoCargaInicial' => 'required|in:manual,ultimo_cierre,monto_fijo',
            'configCajaMontoFijoInicial' => 'nullable|numeric|min:0',
        ], [
            'configCajaNombre.required' => __('El nombre de la caja es obligatorio.'),
            'configCajaNombre.max' => __('El nombre no puede superar los 15 caracteres.'),
            'configCajaLimiteEfectivo.numeric' => __('El límite debe ser un número.'),
            'configCajaLimiteEfectivo.min' => __('El límite no puede ser negativo.'),
            'configCajaMontoFijoInicial.numeric' => __('El monto fijo debe ser un número.'),
            'configCajaMontoFijoInicial.min' => __('El monto fijo no puede ser negativo.'),
        ]);

        try {
            $caja = Caja::findOrFail($this->configCajaId);

            $caja->nombre = $this->configCajaNombre;
            $caja->limite_efectivo = $this->configCajaLimiteEfectivo ?: null;
            $caja->modo_carga_inicial = $this->configCajaModoCargaInicial;
            $caja->monto_fijo_inicial = $this->configCajaModoCargaInicial === 'monto_fijo'
                ? $this->configCajaMontoFijoInicial
                : null;
            $caja->usa_pantalla_cliente = (bool) $this->configCajaUsaPantallaCliente;

            $caja->save();

            $this->cerrarModalConfigCaja();

            $this->dispatch('notify', message: __('Configuración guardada correctamente'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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
        $this->configCajaUsaPantallaCliente = false;
        $this->resetErrorBag();
    }

    // --- Modal Personalizar Pantalla Cliente (2da pantalla, config por sucursal) ---

    /**
     * Abre el modal de personalización de la 2da pantalla. La config es POR
     * SUCURSAL: se carga la config de la sucursal (con defaults mergeados) en
     * las props del modal. Todas las cajas de la sucursal heredan.
     */
    public function abrirPersonalizarPantalla($sucursalId)
    {
        $sucursal = Sucursal::findOrFail($sucursalId);

        $config = $sucursal->getConfigPantallaCliente();

        $this->pcSucursalId = $sucursal->id;
        $this->pcSucursalNombre = $sucursal->nombrePantallaCliente();
        $this->pcLogoUrl = $sucursal->logoPantallaClienteUrl();
        $this->pcMostrarLogo = (bool) $config['mostrar_logo'];
        $this->pcMostrarNombre = (bool) $config['mostrar_nombre'];
        $this->pcColorFondo = $config['color_fondo'];
        $this->pcAnimacion = $config['animacion'];
        $this->pcColorAcento = $config['color_acento'];
        $this->pcColorTexto = $config['color_texto'];
        $this->pcMensajeIdle = $config['mensaje_idle'];
        $this->pcTamanoLogo = $config['tamano_logo'];

        $this->mostrarModalPersonalizarPantalla = true;
    }

    public function guardarPersonalizarPantalla()
    {
        $this->validate([
            'pcColorFondo' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pcColorAcento' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pcColorTexto' => ['required', 'regex:/^(auto|#[0-9a-fA-F]{6})$/'],
            'pcAnimacion' => ['required', 'in:ninguna,respiracion,aurora'],
            'pcTamanoLogo' => ['required', 'in:sm,md,lg'],
            'pcMensajeIdle' => ['nullable', 'string', 'max:60'],
        ], [
            'pcColorFondo.regex' => __('El color de fondo debe ser un código hexadecimal válido.'),
            'pcColorAcento.regex' => __('El color de acento debe ser un código hexadecimal válido.'),
            'pcColorTexto.regex' => __('El color de texto debe ser "auto" o un hexadecimal válido.'),
        ]);

        try {
            $sucursal = Sucursal::findOrFail($this->pcSucursalId);

            $sucursal->update([
                'config_pantalla_cliente' => [
                    'mostrar_logo' => (bool) $this->pcMostrarLogo,
                    'mostrar_nombre' => (bool) $this->pcMostrarNombre,
                    'color_fondo' => $this->pcColorFondo,
                    'animacion' => $this->pcAnimacion,
                    'color_acento' => $this->pcColorAcento,
                    'color_texto' => $this->pcColorTexto,
                    'mensaje_idle' => trim((string) $this->pcMensajeIdle) ?: Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS['mensaje_idle'],
                    'tamano_logo' => $this->pcTamanoLogo,
                ],
            ]);

            $this->cerrarModalPersonalizarPantalla();

            $this->dispatch('notify', message: __('Personalización de la pantalla cliente guardada'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    public function cerrarModalPersonalizarPantalla()
    {
        $this->mostrarModalPersonalizarPantalla = false;
        $this->pcSucursalId = null;
        $this->pcSucursalNombre = '';
        $this->pcLogoUrl = null;
        $this->resetErrorBag();
    }

    // --- Modal Monitor Llamador (pantalla Clase B remota) ---

    /**
     * Abre el modal del llamador: asegura que la sucursal tenga token + código en
     * el índice global (los genera si faltan) y carga el toggle + la
     * personalización (config_llamador con defaults mergeados).
     */
    public function abrirLlamador($sucursalId, PantallaPublicaService $service)
    {
        $sucursal = Sucursal::findOrFail($sucursalId);

        $index = $service->asegurarToken($sucursal);
        $config = $sucursal->getConfigLlamador();

        $this->llSucursalId = $sucursal->id;
        $this->llSucursalNombre = $sucursal->nombre;
        $this->llUsaLlamador = (bool) $sucursal->usa_llamador;
        $this->llToken = $index->token;
        $this->llCodigo = $index->codigo_corto;
        $this->llLogoUrl = $sucursal->logoPantallaClienteUrl();
        $this->llLogoActual = (bool) $this->llLogoUrl;

        $this->llTitulo = $config['titulo'];
        $this->llMostrarLogo = (bool) $config['mostrar_logo'];
        $this->llColorFondo = $config['color_fondo'];
        $this->llColorPreparacion = $config['color_preparacion'];
        $this->llColorListo = $config['color_listo'];
        $this->llSonido = (bool) $config['sonido'];
        $this->llTamano = $config['tamano'] ?? 'normal';

        $this->mostrarModalLlamador = true;
    }

    public function guardarLlamador()
    {
        $this->validate([
            'llTitulo' => ['nullable', 'string', 'max:40'],
            'llColorFondo' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'llColorPreparacion' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'llColorListo' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'llTamano' => ['required', 'in:compacto,normal,grande'],
        ], [
            'llColorFondo.regex' => __('El color de fondo debe ser un código hexadecimal válido.'),
            'llColorPreparacion.regex' => __('El color debe ser un código hexadecimal válido.'),
            'llColorListo.regex' => __('El color debe ser un código hexadecimal válido.'),
        ]);

        try {
            $sucursal = Sucursal::findOrFail($this->llSucursalId);

            $sucursal->update([
                'usa_llamador' => $this->llUsaLlamador,
                'config_llamador' => [
                    'titulo' => trim((string) $this->llTitulo) ?: Sucursal::CONFIG_LLAMADOR_DEFAULTS['titulo'],
                    'mostrar_logo' => (bool) $this->llMostrarLogo,
                    'color_fondo' => $this->llColorFondo,
                    'color_preparacion' => $this->llColorPreparacion,
                    'color_listo' => $this->llColorListo,
                    'sonido' => (bool) $this->llSonido,
                    'tamano' => $this->llTamano,
                ],
            ]);

            $this->cerrarModalLlamador();
            $this->dispatch('notify', message: __('Configuración del llamador guardada'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    /**
     * Regenera el token + código corto de la sucursal (rotación). Invalida las
     * URLs/QR viejos y desvincula los dispositivos ya vinculados (vuelven a la
     * pantalla de vinculación).
     */
    public function regenerarTokenLlamador(PantallaPublicaService $service)
    {
        if (! $this->llSucursalId) {
            return;
        }

        $sucursal = Sucursal::findOrFail($this->llSucursalId);
        $nuevo = $service->regenerarToken($sucursal);

        $this->llToken = $nuevo['token'];
        $this->llCodigo = $nuevo['codigo_corto'];

        $this->dispatch('notify', message: __('Token regenerado. Los dispositivos vinculados deberán volver a vincularse.'), type: 'success');
    }

    public function cerrarModalLlamador()
    {
        $this->mostrarModalLlamador = false;
        $this->llSucursalId = null;
        $this->llSucursalNombre = '';
        $this->llToken = null;
        $this->llCodigo = null;
        $this->resetErrorBag();
    }

    /** URL larga (QR / tablets que escanean): /llamador/{token}. */
    #[Computed]
    public function llUrlLarga(): string
    {
        return $this->llToken ? route('llamador.token', $this->llToken) : '';
    }

    /** URL corta tipeable en TV: /ll/{codigo}. */
    #[Computed]
    public function llUrlCorta(): string
    {
        return $this->llCodigo ? route('llamador.codigo', $this->llCodigo) : '';
    }

    /** SVG del QR de la URL larga (no se serializa: es computed). */
    #[Computed]
    public function llQrSvg(): string
    {
        if (! $this->llToken) {
            return '';
        }

        return (string) QrCode::format('svg')->size(180)->margin(1)->generate($this->llUrlLarga());
    }

    // --- Modal Consultor de Precios (pantalla Clase B remota) ---

    /**
     * Abre el modal del consultor: asegura token + código (comparte el mismo token
     * de la sucursal con el llamador) y carga el toggle + la personalización
     * (config_consultor_precios con defaults mergeados).
     */
    public function abrirConsultorPrecios($sucursalId, PantallaPublicaService $service)
    {
        $sucursal = Sucursal::findOrFail($sucursalId);

        $index = $service->asegurarToken($sucursal);
        $config = $sucursal->getConfigConsultorPrecios();

        $this->cpSucursalId = $sucursal->id;
        $this->cpSucursalNombre = $sucursal->nombre;
        $this->cpUsaConsultor = (bool) $sucursal->usa_consultor_precios;
        $this->cpToken = $index->token;
        $this->cpCodigo = $index->codigo_corto;
        $this->cpLogoUrl = $sucursal->logoPantallaClienteUrl();
        $this->cpLogoActual = (bool) $this->cpLogoUrl;

        $this->cpTitulo = $config['titulo'];
        $this->cpMostrarLogo = (bool) $config['mostrar_logo'];
        $this->cpColorFondo = $config['color_fondo'];
        $this->cpColorAcento = $config['color_acento'];
        $this->cpMensajeIdle = $config['mensaje_idle'] ?? Sucursal::CONFIG_CONSULTOR_PRECIOS_DEFAULTS['mensaje_idle'];
        $this->cpDuracion = (int) ($config['duracion_resultado'] ?? 5);

        $this->mostrarModalConsultor = true;
    }

    public function guardarConsultorPrecios()
    {
        $this->validate([
            'cpTitulo' => ['nullable', 'string', 'max:40'],
            'cpColorFondo' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cpColorAcento' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cpMensajeIdle' => ['nullable', 'string', 'max:60'],
            'cpDuracion' => ['required', 'integer', 'min:1', 'max:60'],
        ], [
            'cpColorFondo.regex' => __('El color de fondo debe ser un código hexadecimal válido.'),
            'cpColorAcento.regex' => __('El color debe ser un código hexadecimal válido.'),
        ]);

        try {
            $sucursal = Sucursal::findOrFail($this->cpSucursalId);

            $sucursal->update([
                'usa_consultor_precios' => $this->cpUsaConsultor,
                'config_consultor_precios' => [
                    'titulo' => trim((string) $this->cpTitulo) ?: Sucursal::CONFIG_CONSULTOR_PRECIOS_DEFAULTS['titulo'],
                    'mostrar_logo' => (bool) $this->cpMostrarLogo,
                    'color_fondo' => $this->cpColorFondo,
                    'color_acento' => $this->cpColorAcento,
                    'mensaje_idle' => trim((string) $this->cpMensajeIdle) ?: Sucursal::CONFIG_CONSULTOR_PRECIOS_DEFAULTS['mensaje_idle'],
                    'duracion_resultado' => $this->cpDuracion,
                ],
            ]);

            $this->cerrarModalConsultor();
            $this->dispatch('notify', message: __('Configuración del consultor de precios guardada'), type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    /**
     * Regenera el token + código (rotación). Como el token es ÚNICO por sucursal,
     * afecta también al llamador: ambas pantallas deberán volver a vincularse.
     */
    public function regenerarTokenConsultor(PantallaPublicaService $service)
    {
        if (! $this->cpSucursalId) {
            return;
        }

        $sucursal = Sucursal::findOrFail($this->cpSucursalId);
        $nuevo = $service->regenerarToken($sucursal);

        $this->cpToken = $nuevo['token'];
        $this->cpCodigo = $nuevo['codigo_corto'];

        $this->dispatch('notify', message: __('Token regenerado. Los dispositivos vinculados deberán volver a vincularse.'), type: 'success');
    }

    public function cerrarModalConsultor()
    {
        $this->mostrarModalConsultor = false;
        $this->cpSucursalId = null;
        $this->cpSucursalNombre = '';
        $this->cpToken = null;
        $this->cpCodigo = null;
        $this->resetErrorBag();
    }

    /** URL larga (QR / tablets que escanean): /precios/{token}. */
    #[Computed]
    public function cpUrlLarga(): string
    {
        return $this->cpToken ? route('precios.token', $this->cpToken) : '';
    }

    /** URL corta tipeable: /pr/{codigo}. */
    #[Computed]
    public function cpUrlCorta(): string
    {
        return $this->cpCodigo ? route('precios.codigo', $this->cpCodigo) : '';
    }

    /** SVG del QR de la URL larga del consultor (computed, no se serializa). */
    #[Computed]
    public function cpQrSvg(): string
    {
        if (! $this->cpToken) {
            return '';
        }

        return (string) QrCode::format('svg')->size(180)->margin(1)->generate($this->cpUrlLarga());
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
                fn ($id) => $id !== $puntoVentaId
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
        if (! $this->cajaEditandoPuntosId) {
            return;
        }

        try {
            $caja = Caja::findOrFail($this->cajaEditandoPuntosId);

            // Eliminar asignaciones previas
            PuntoVentaCaja::where('caja_id', $caja->id)->delete();

            // Si hay puntos asignados pero ninguno es defecto, usar el primero
            if (count($this->cajaPuntosAsignados) > 0 && ! $this->cajaPuntoDefecto) {
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
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
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
                fn ($id) => $id !== $cajaId
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
            $this->dispatch('notify', message: __('Error: ').$e->getMessage(), type: 'error');
        }
    }

    /**
     * Confirma la eliminación de un grupo
     */
    public function confirmarEliminarGrupo($grupoId)
    {
        $grupo = GrupoCierre::with('cajas')->find($grupoId);

        if (! $grupo) {
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
            $this->dispatch('notify', message: 'Error: '.$e->getMessage(), type: 'error');
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
        if (! $this->grupoSucursalId) {
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
