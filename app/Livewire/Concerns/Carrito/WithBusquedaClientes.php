<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\ListaPrecio;
use App\Services\ARCA\PadronARCAService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Búsqueda y alta rápida de clientes en NuevaVenta.
 *
 * Encapsula:
 * - Selección y búsqueda inteligente (nombre, CUIT, teléfono).
 * - Filtrado por sucursal (clientes vinculados o sin vinculación).
 * - Determinación automática del tipo de factura (A/B/C) según condición IVA emisor/cliente.
 * - Modal de alta rápida con consulta a padrón ARCA y validación de CUIT.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->sucursalId            (NuevaVenta / SucursalAware)
 * - $this->cajaSeleccionada      (CajaAware)
 * - $this->listaPrecioId         (NuevaVenta)
 * - $this->actualizarPreciosItems()  (NuevaVenta)
 * - $this->calcularVenta()       (NuevaVenta)
 * - $this->cargarSaldoPuntosCliente() (NuevaVenta — irá a WithPuntos en extracción posterior)
 */
trait WithBusquedaClientes
{
    // =========================================
    // PROPIEDADES DE CLIENTE SELECCIONADO
    // =========================================

    /** @var int|null ID del cliente seleccionado */
    public $clienteSeleccionado = null;

    // =========================================
    // PROPIEDADES DE BÚSQUEDA DE CLIENTES
    // =========================================

    /** @var string Búsqueda de clientes */
    public $busquedaCliente = '';

    /** @var array Clientes encontrados en la búsqueda */
    public $clientesResultados = [];

    /** @var string Nombre del cliente seleccionado */
    public $clienteNombre = '';

    /** @var string Condición IVA del cliente seleccionado */
    public $clienteCondicionIva = '';

    /** @var string Tipo de factura que se emitirá (A, B, C) */
    public $tipoFacturaCliente = 'B';

    /** @var bool Modal de alta rápida de cliente visible */
    public $mostrarModalClienteRapido = false;

    // Campos del modal de alta de cliente
    public string $clienteRapidoNombre = '';

    public string $clienteRapidoRazonSocial = '';

    public string $clienteRapidoCuit = '';

    public string $clienteRapidoEmail = '';

    public string $clienteRapidoTelefono = '';

    public string $clienteRapidoDireccion = '';

    public ?int $clienteRapidoCondicionIvaId = null;

    // CUIT / ARCA
    public string $clienteRapidoModoAlta = 'manual';

    public bool $clienteRapidoArcaDisponible = false;

    public bool $clienteRapidoConsultandoCuit = false;

    public string $clienteRapidoErrorCuit = '';

    public string $clienteRapidoExitoCuit = '';

    public bool $clienteRapidoDatosDesdeArca = false;

    public string $clienteRapidoValidacionCuitMsg = '';

    public string $clienteRapidoValidacionCuitTipo = '';

    // =========================================
    // MÉTODOS DE BÚSQUEDA DE CLIENTES
    // =========================================

    /**
     * Handler para cambios en la búsqueda de clientes
     */
    public function updatedBusquedaCliente($value)
    {
        $value = trim($value);

        // Solo mostrar resultados si hay al menos 2 caracteres
        if (strlen($value) < 2) {
            $this->clientesResultados = [];

            return;
        }

        $this->buscarClientes($value);
    }

    /**
     * Busca clientes por nombre, filtrando por sucursal
     */
    protected function buscarClientes(string $busqueda): void
    {
        $query = Cliente::where('activo', true);

        // Filtrar por sucursal si está seleccionada
        if ($this->sucursalId) {
            $query->where(function ($q) {
                // Clientes vinculados a la sucursal y activos en ella
                $q->whereHas('sucursales', function ($subQ) {
                    $subQ->where('sucursal_id', $this->sucursalId)
                        ->where('clientes_sucursales.activo', true);
                })
                // O clientes sin vinculación a ninguna sucursal (disponibles para todas)
                    ->orWhereDoesntHave('sucursales');
            });
        }

        // Búsqueda inteligente por nombre, CUIT y teléfono
        $query->where(function ($q) use ($busqueda) {
            $q->where('nombre', 'like', '%'.$busqueda.'%')
                ->orWhere('cuit', 'like', '%'.$busqueda.'%')
                ->orWhere('telefono', 'like', '%'.$busqueda.'%');
        });

        $this->clientesResultados = $query->orderBy('nombre')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'cuit' => $c->cuit,
                'telefono' => $c->telefono,
            ])
            ->toArray();
    }

    /**
     * Selecciona un cliente de los resultados de búsqueda
     */
    public function seleccionarCliente($clienteId)
    {
        $cliente = Cliente::with('condicionIva')->find($clienteId);

        if ($cliente) {
            $this->clienteSeleccionado = $cliente->id;
            $this->clienteNombre = $cliente->nombre;
            $this->busquedaCliente = '';
            $this->clientesResultados = [];

            // Cargar condición IVA del cliente
            $this->clienteCondicionIva = $cliente->condicionIva?->nombre ?? 'Consumidor Final';
            $this->determinarTipoFacturaCliente($cliente);

            // Cargar saldo de puntos del cliente (RF-23)
            $this->cargarSaldoPuntosCliente($cliente);

            // Si el cliente tiene lista de precios asignada, actualizarla
            if ($cliente->lista_precio_id) {
                $this->listaPrecioId = $cliente->lista_precio_id;
                $this->actualizarPreciosItems();
                $this->calcularVenta();
            }
        }
    }

    /**
     * Determina el tipo de factura según la condición IVA del cliente y del emisor
     */
    protected function determinarTipoFacturaCliente(?Cliente $cliente = null): void
    {
        // Por defecto, Factura B (Consumidor Final)
        $this->tipoFacturaCliente = 'B';

        if (! $cliente) {
            return;
        }

        // Obtener condición IVA del emisor desde la caja activa
        try {
            $cajaId = $this->cajaSeleccionada ?? caja_activa();
            if (! $cajaId) {
                return;
            }

            $caja = Caja::with('puntosVenta.cuit.condicionIva')->find($cajaId);
            if (! $caja) {
                return;
            }

            $puntoVenta = $caja->puntoVentaDefecto();
            if (! $puntoVenta || ! $puntoVenta->cuit) {
                return;
            }

            $condicionEmisor = $puntoVenta->cuit->condicionIva;
            if (! $condicionEmisor) {
                return;
            }

            // Si el emisor es Monotributista o Exento, siempre es C
            if ($condicionEmisor->esMonotributista() || $condicionEmisor->esExento()) {
                $this->tipoFacturaCliente = 'C';

                return;
            }

            // Emisor es Responsable Inscripto
            if ($condicionEmisor->esResponsableInscripto()) {
                $condicionCliente = $cliente->condicionIva;

                // Factura A: Cliente RI o Monotributista (códigos 1, 6, 13, 16)
                // Factura B: Cliente CF, Exento u otros
                if ($condicionCliente && ($condicionCliente->esResponsableInscripto() || $condicionCliente->esMonotributista())) {
                    $this->tipoFacturaCliente = 'A';
                } else {
                    $this->tipoFacturaCliente = 'B';
                }
            }
        } catch (\Exception $e) {
            // Si hay error, mantener B por defecto
            $this->tipoFacturaCliente = 'B';
        }
    }

    /**
     * Limpia la selección de cliente
     */
    public function limpiarCliente()
    {
        $this->clienteSeleccionado = null;
        $this->clienteNombre = '';
        $this->clienteCondicionIva = '';
        $this->tipoFacturaCliente = 'B';
        $this->busquedaCliente = '';
        $this->clientesResultados = [];
    }

    /**
     * Selecciona el primer cliente de la lista de resultados (para Enter)
     */
    public function seleccionarPrimerCliente()
    {
        if (empty($this->clientesResultados) && ! empty($this->busquedaCliente)) {
            // Si no hay resultados, buscar
            $this->buscarClientes($this->busquedaCliente);
        }

        if (! empty($this->clientesResultados)) {
            $this->seleccionarCliente($this->clientesResultados[0]['id']);
        }
    }

    /**
     * Abre el modal de alta de cliente
     */
    public function abrirModalClienteRapido()
    {
        $this->resetClienteRapido();
        $consumidorFinal = CondicionIva::where('codigo', CondicionIva::CONSUMIDOR_FINAL)->first();
        $this->clienteRapidoCondicionIvaId = $consumidorFinal?->id;
        $this->clienteRapidoArcaDisponible = PadronARCAService::estaDisponible();
        $this->mostrarModalClienteRapido = true;
    }

    /**
     * Cierra el modal de alta de cliente
     */
    public function cerrarModalClienteRapido()
    {
        $this->mostrarModalClienteRapido = false;
        $this->resetClienteRapido();
    }

    /**
     * Resetea campos del modal de cliente
     */
    protected function resetClienteRapido(): void
    {
        $this->clienteRapidoNombre = '';
        $this->clienteRapidoRazonSocial = '';
        $this->clienteRapidoCuit = '';
        $this->clienteRapidoEmail = '';
        $this->clienteRapidoTelefono = '';
        $this->clienteRapidoDireccion = '';
        $this->clienteRapidoCondicionIvaId = null;
        $this->clienteRapidoModoAlta = 'manual';
        $this->clienteRapidoConsultandoCuit = false;
        $this->clienteRapidoErrorCuit = '';
        $this->clienteRapidoExitoCuit = '';
        $this->clienteRapidoDatosDesdeArca = false;
        $this->clienteRapidoValidacionCuitMsg = '';
        $this->clienteRapidoValidacionCuitTipo = '';
    }

    /**
     * Validación en tiempo real del CUIT (modo manual)
     */
    public function updatedClienteRapidoCuit(): void
    {
        if ($this->clienteRapidoModoAlta !== 'manual') {
            return;
        }

        $this->clienteRapidoDatosDesdeArca = false;
        $this->clienteRapidoValidacionCuitMsg = '';
        $this->clienteRapidoValidacionCuitTipo = '';

        if (empty($this->clienteRapidoCuit)) {
            return;
        }

        $cuitLimpio = preg_replace('/\D/', '', $this->clienteRapidoCuit);

        if (strlen($cuitLimpio) < 11) {
            return;
        }

        if (strlen($cuitLimpio) > 11) {
            $this->clienteRapidoValidacionCuitMsg = __('El CUIT debe tener 11 dígitos');
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        if (! Cuit::validarCuit($cuitLimpio)) {
            $this->clienteRapidoValidacionCuitMsg = __('CUIT inválido (dígito verificador incorrecto)');
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        $existente = Cliente::withTrashed()->where(function ($q) use ($cuitLimpio) {
            $q->where('cuit', $this->clienteRapidoCuit)->orWhere('cuit', $cuitLimpio);
        })->first();

        if ($existente) {
            $this->clienteRapidoValidacionCuitMsg = $existente->trashed()
                ? __('Existe un cliente eliminado con este CUIT: :nombre', ['nombre' => $existente->nombre])
                : __('Ya existe un cliente con este CUIT: :nombre', ['nombre' => $existente->nombre]);
            $this->clienteRapidoValidacionCuitTipo = 'error';

            return;
        }

        if ($this->clienteRapidoArcaDisponible) {
            try {
                $cuitComercio = PadronARCAService::obtenerCuitDisponible();
                if ($cuitComercio) {
                    $servicio = new PadronARCAService($cuitComercio);
                    $datos = $servicio->consultarCuit($cuitLimpio);
                    if ($datos['condicion_iva_id']) {
                        $this->clienteRapidoCondicionIvaId = $datos['condicion_iva_id'];
                        $this->clienteRapidoDatosDesdeArca = true;
                        $condicion = CondicionIva::find($datos['condicion_iva_id']);
                        $this->clienteRapidoValidacionCuitMsg = __('CUIT válido — :condicion (según ARCA)', ['condicion' => $condicion->nombre ?? '']);
                        $this->clienteRapidoValidacionCuitTipo = 'success';
                    }

                    return;
                }
            } catch (\Exception $e) {
                Log::info('Validación ARCA en modo manual falló', ['error' => $e->getMessage()]);
            }
        }

        $this->clienteRapidoValidacionCuitMsg = __('CUIT válido');
        $this->clienteRapidoValidacionCuitTipo = 'success';
    }

    /**
     * Consulta CUIT en ARCA (modo CUIT)
     */
    public function consultarCuitClienteRapido(): void
    {
        $this->clienteRapidoErrorCuit = '';
        $this->clienteRapidoExitoCuit = '';

        if (empty($this->clienteRapidoCuit)) {
            $this->clienteRapidoErrorCuit = __('Ingrese un CUIT para consultar');

            return;
        }

        $cuitLimpio = preg_replace('/\D/', '', $this->clienteRapidoCuit);

        if (! Cuit::validarCuit($cuitLimpio)) {
            $this->clienteRapidoErrorCuit = __('El CUIT ingresado no es válido. Verifique el número.');

            return;
        }

        $existente = Cliente::withTrashed()->where(function ($q) use ($cuitLimpio) {
            $q->where('cuit', $this->clienteRapidoCuit)->orWhere('cuit', $cuitLimpio);
        })->first();

        if ($existente) {
            $this->clienteRapidoErrorCuit = $existente->trashed()
                ? __('Existe un cliente eliminado con este CUIT: :nombre', ['nombre' => $existente->nombre])
                : __('Ya existe un cliente con el CUIT :cuit: :nombre', ['cuit' => $this->clienteRapidoCuit, 'nombre' => $existente->nombre]);

            return;
        }

        $this->clienteRapidoConsultandoCuit = true;

        try {
            $cuitComercio = PadronARCAService::obtenerCuitDisponible();
            if (! $cuitComercio) {
                $this->clienteRapidoErrorCuit = __('No hay certificados ARCA configurados para realizar la consulta');
                $this->clienteRapidoConsultandoCuit = false;

                return;
            }

            $servicio = new PadronARCAService($cuitComercio);
            $datos = $servicio->consultarCuit($cuitLimpio);

            $this->clienteRapidoRazonSocial = $datos['denominacion'] ?? '';
            $this->clienteRapidoNombre = $datos['denominacion'] ?? '';
            $this->clienteRapidoDireccion = $datos['direccion'] ?? '';
            $this->clienteRapidoCuit = $cuitLimpio;

            if ($datos['condicion_iva_id']) {
                $this->clienteRapidoCondicionIvaId = $datos['condicion_iva_id'];
            }

            $this->clienteRapidoDatosDesdeArca = true;

            $estadoTexto = $datos['estado_activo'] ? __('Activo') : __('Inactivo');
            $this->clienteRapidoExitoCuit = __('Datos obtenidos correctamente. Estado: :estado', ['estado' => $estadoTexto]);

        } catch (\Exception $e) {
            $this->clienteRapidoErrorCuit = $e->getMessage();
            Log::error('Error al consultar padrón ARCA', ['cuit' => $cuitLimpio, 'error' => $e->getMessage()]);
        }

        $this->clienteRapidoConsultandoCuit = false;
    }

    /**
     * Guarda un cliente y lo selecciona
     */
    public function guardarClienteRapido()
    {
        $this->validate([
            'clienteRapidoNombre' => 'required|min:2|max:255',
            'clienteRapidoEmail' => 'nullable|email|max:191',
            'clienteRapidoTelefono' => 'nullable|max:50',
            'clienteRapidoCuit' => 'nullable|string|max:20',
        ], [
            'clienteRapidoNombre.required' => __('El nombre es obligatorio'),
            'clienteRapidoNombre.min' => __('El nombre debe tener al menos 2 caracteres'),
            'clienteRapidoEmail.email' => __('Ingrese un email válido'),
        ]);

        try {
            $cliente = Cliente::create([
                'nombre' => $this->clienteRapidoNombre,
                'razon_social' => $this->clienteRapidoRazonSocial ?: null,
                'cuit' => $this->clienteRapidoCuit ?: null,
                'email' => $this->clienteRapidoEmail ?: null,
                'telefono' => $this->clienteRapidoTelefono ?: null,
                'direccion' => $this->clienteRapidoDireccion ?: null,
                'condicion_iva_id' => $this->clienteRapidoCondicionIvaId,
                'activo' => true,
            ]);

            // Asignar sucursal activa con lista base
            $sucursalActiva = sucursal_activa();
            if ($sucursalActiva) {
                $listaPrecioId = ListaPrecio::where('sucursal_id', $sucursalActiva)
                    ->where('es_lista_base', true)
                    ->value('id');
                $cliente->sucursales()->syncWithoutDetaching([
                    $sucursalActiva => ['activo' => true, 'lista_precio_id' => $listaPrecioId],
                ]);
            }

            // Seleccionar el cliente recién creado
            $this->clienteSeleccionado = $cliente->id;
            $this->clienteNombre = $cliente->nombre;
            $this->busquedaCliente = '';
            $this->clientesResultados = [];

            $this->cerrarModalClienteRapido();

            $this->dispatch('notify',
                message: __('Cliente ":nombre" creado correctamente', ['nombre' => $cliente->nombre]),
                type: 'success'
            );

        } catch (Exception $e) {
            Log::error('Error al crear cliente: '.$e->getMessage());
            $this->dispatch('notify',
                message: __('Error al crear el cliente'),
                type: 'error'
            );
        }
    }
}
