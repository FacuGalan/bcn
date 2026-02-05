<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\CondicionIva;
use App\Models\ListaPrecio;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire para gestión de clientes
 *
 * Permite crear, editar, listar y gestionar el estado de los clientes.
 * Incluye funcionalidad de vinculación con proveedores.
 *
 * @package App\Livewire\Clientes
 */
#[Layout('layouts.app')]
class GestionarClientes extends Component
{
    use WithPagination, WithFileUploads;

    // Propiedades de filtros
    public string $search = '';
    public string $filterStatus = 'all'; // all, active, inactive
    public string $filterSucursal = 'all';
    public string $filterCondicionIva = 'all';
    public string $filterCuentaCorriente = 'all'; // all, con_cc, sin_cc, con_deuda
    public string $filterVinculacion = 'all'; // all, con_proveedor, sin_proveedor
    public bool $showFilters = false;

    // Propiedades del modal
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $clienteId = null;

    // Modal de confirmación de eliminación
    public bool $showDeleteModal = false;
    public ?int $clienteAEliminar = null;
    public ?string $nombreClienteAEliminar = null;

    // Modal de historial de ventas
    public bool $showHistorialModal = false;
    public ?int $clienteHistorialId = null;
    public ?string $nombreClienteHistorial = null;

    // Propiedades del formulario
    public string $nombre = '';
    public string $razon_social = '';
    public string $cuit = '';
    public string $email = '';
    public string $telefono = '';
    public string $direccion = '';
    public ?int $condicion_iva_id = null;
    public ?int $lista_precio_id = null;
    public bool $tiene_cuenta_corriente = false;
    public float $limite_credito = 0;
    public int $dias_credito = 30;
    public float $tasa_interes_mensual = 0;
    public bool $activo = true;

    // Vinculación con proveedor
    public bool $tambien_es_proveedor = false;
    public ?int $proveedor_vinculado_id = null;
    public string $proveedor_opcion = 'crear_nuevo'; // 'crear_nuevo' o ID del proveedor existente

    // Sucursales
    public array $sucursales_seleccionadas = [];

    // Modal de configuración de sucursales (listas de precios)
    public bool $showSucursalesModal = false;
    public ?int $clienteConfigId = null;
    public ?string $clienteConfigNombre = null;
    public array $sucursalesConfig = []; // ['sucursal_id' => ['lista_precio_id' => X, 'activo' => bool]]

    // Modal de importación
    public bool $showImportModal = false;
    public $archivoImportacion = null;
    public array $sucursales_importacion = [];
    public array $importacionResultado = [];
    public bool $importacionProcesada = false;

    /**
     * Reglas de validación
     */
    protected function rules(): array
    {
        $rules = [
            'nombre' => 'required|string|max:191',
            'razon_social' => 'nullable|string|max:191',
            'cuit' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'condicion_iva_id' => 'nullable|exists:config.condiciones_iva,id',
            'tiene_cuenta_corriente' => 'boolean',
            'limite_credito' => 'numeric|min:0',
            'dias_credito' => 'integer|min:0|max:365',
            'tasa_interes_mensual' => 'numeric|min:0|max:100',
            'activo' => 'boolean',
            'sucursales_seleccionadas' => 'array',
        ];

        // Validar CUIT único si se está editando otro cliente
        if ($this->cuit) {
            $rules['cuit'] = 'nullable|string|max:20|unique:pymes_tenant.clientes,cuit' .
                ($this->clienteId ? ',' . $this->clienteId : '');
        }

        return $rules;
    }

    /**
     * Mensajes de validación personalizados
     */
    protected function messages(): array
    {
        return [
            'nombre.required' => __('El nombre es obligatorio'),
            'nombre.max' => __('El nombre no puede exceder 191 caracteres'),
            'email.email' => __('Ingrese un email válido'),
            'cuit.unique' => __('Este CUIT ya está registrado'),
            'limite_credito.min' => __('El límite de crédito no puede ser negativo'),
        ];
    }

    /**
     * Actualiza la búsqueda y resetea la paginación
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza los filtros y resetea la paginación
     */
    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSucursal(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCondicionIva(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCuentaCorriente(): void
    {
        $this->resetPage();
    }

    public function updatingFilterVinculacion(): void
    {
        $this->resetPage();
    }

    /**
     * Alterna la visibilidad de los filtros
     */
    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene los clientes con filtros aplicados
     */
    protected function getClientes()
    {
        $query = Cliente::with(['condicionIva', 'listaPrecio', 'proveedor', 'sucursales' => function($query) {
            $query->wherePivot('activo', true);
        }]);

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('razon_social', 'like', '%' . $this->search . '%')
                  ->orWhere('cuit', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('telefono', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de estado
        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        // Filtro de sucursal
        if ($this->filterSucursal !== 'all') {
            $query->whereHas('sucursales', function($q) {
                $q->where('sucursal_id', $this->filterSucursal)
                  ->where('clientes_sucursales.activo', true);
            });
        }

        // Filtro de condición IVA
        if ($this->filterCondicionIva !== 'all') {
            $query->where('condicion_iva_id', $this->filterCondicionIva);
        }

        // Filtro de cuenta corriente
        if ($this->filterCuentaCorriente !== 'all') {
            switch ($this->filterCuentaCorriente) {
                case 'con_cc':
                    $query->where('tiene_cuenta_corriente', true);
                    break;
                case 'sin_cc':
                    $query->where('tiene_cuenta_corriente', false);
                    break;
                case 'con_deuda':
                    $query->where('tiene_cuenta_corriente', true)
                          ->where('saldo_deudor_cache', '>', 0);
                    break;
            }
        }

        // Filtro de vinculación con proveedor
        if ($this->filterVinculacion !== 'all') {
            if ($this->filterVinculacion === 'con_proveedor') {
                $query->whereHas('proveedor');
            } else {
                $query->whereDoesntHave('proveedor');
            }
        }

        return $query->orderBy('nombre')->paginate(15);
    }

    /**
     * Abre el modal para crear un nuevo cliente
     */
    public function create(): void
    {
        $this->resetForm();
        $this->editMode = false;

        // Establecer Consumidor Final como default
        $consumidorFinal = CondicionIva::where('codigo', CondicionIva::CONSUMIDOR_FINAL)->first();
        $this->condicion_iva_id = $consumidorFinal?->id;

        $this->showModal = true;
    }

    /**
     * Abre el modal para editar un cliente
     */
    public function edit(int $id): void
    {
        $cliente = Cliente::with(['sucursales'])->findOrFail($id);

        $this->clienteId = $cliente->id;
        $this->nombre = $cliente->nombre;
        $this->razon_social = $cliente->razon_social ?? '';
        $this->cuit = $cliente->cuit ?? '';
        $this->email = $cliente->email ?? '';
        $this->telefono = $cliente->telefono ?? '';
        $this->direccion = $cliente->direccion ?? '';
        $this->condicion_iva_id = $cliente->condicion_iva_id;
        $this->lista_precio_id = $cliente->lista_precio_id;
        $this->tiene_cuenta_corriente = $cliente->tiene_cuenta_corriente;
        $this->limite_credito = (float) $cliente->limite_credito;
        $this->dias_credito = $cliente->dias_credito;
        $this->tasa_interes_mensual = (float) $cliente->tasa_interes_mensual;
        $this->activo = $cliente->activo;
        $this->sucursales_seleccionadas = $cliente->sucursales->pluck('id')->toArray();

        // Verificar si tiene proveedor vinculado
        $proveedor = Proveedor::where('cliente_id', $cliente->id)->first();
        $this->tambien_es_proveedor = $proveedor !== null;
        $this->proveedor_vinculado_id = $proveedor?->id;
        $this->proveedor_opcion = $proveedor ? (string) $proveedor->id : 'crear_nuevo';

        $this->editMode = true;
        $this->showModal = true;
    }

    /**
     * Guarda el cliente (crear o actualizar)
     */
    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $data = [
                'nombre' => $this->nombre,
                'razon_social' => $this->razon_social ?: null,
                'cuit' => $this->cuit ?: null,
                'email' => $this->email ?: null,
                'telefono' => $this->telefono ?: null,
                'direccion' => $this->direccion ?: null,
                'condicion_iva_id' => $this->condicion_iva_id,
                'lista_precio_id' => null, // La lista se configura por sucursal
                'tiene_cuenta_corriente' => $this->tiene_cuenta_corriente,
                'limite_credito' => $this->tiene_cuenta_corriente ? $this->limite_credito : 0,
                'dias_credito' => $this->tiene_cuenta_corriente ? $this->dias_credito : 30,
                'tasa_interes_mensual' => $this->tiene_cuenta_corriente ? $this->tasa_interes_mensual : 0,
                'activo' => $this->activo,
            ];

            if ($this->editMode) {
                $cliente = Cliente::findOrFail($this->clienteId);
                $cliente->update($data);
            } else {
                $cliente = Cliente::create($data);
                $this->clienteId = $cliente->id;
            }

            // Sincronizar sucursales con lista de precio base de cada una
            $sucursalesData = [];
            foreach ($this->sucursales_seleccionadas as $sucursalId) {
                // Obtener lista base de la sucursal
                $listaBase = ListaPrecio::where('sucursal_id', $sucursalId)
                    ->where('es_lista_base', true)
                    ->value('id');

                $sucursalesData[$sucursalId] = [
                    'activo' => true,
                    'lista_precio_id' => $listaBase,
                ];
            }
            $cliente->sucursales()->sync($sucursalesData);

            // Manejar vinculación con proveedor
            $this->handleProveedorVinculacion($cliente);
        });

        $message = $this->editMode
            ? __('Cliente actualizado correctamente')
            : __('Cliente creado correctamente');

        $this->dispatch('notify', type: 'success', message: $message);
        $this->closeModal();
    }

    /**
     * Maneja la vinculación/desvinculación con proveedor
     * Opciones:
     * - 'crear_nuevo': Crea un nuevo proveedor con los datos del cliente
     * - ID numérico: Vincula a un proveedor existente
     */
    protected function handleProveedorVinculacion(Cliente $cliente): void
    {
        // Obtener proveedor actualmente vinculado a este cliente
        $proveedorActual = Proveedor::where('cliente_id', $cliente->id)->first();

        if ($this->tambien_es_proveedor) {
            if ($this->proveedor_opcion === 'crear_nuevo') {
                // Crear nuevo proveedor con datos del cliente
                if (!$proveedorActual) {
                    Proveedor::create([
                        'codigo' => 'CLI-' . str_pad($cliente->id, 4, '0', STR_PAD_LEFT),
                        'nombre' => $cliente->nombre,
                        'razon_social' => $cliente->razon_social,
                        'nombre_fiscal' => $cliente->razon_social,
                        'cuit' => $cliente->cuit,
                        'direccion' => $cliente->direccion,
                        'telefono' => $cliente->telefono,
                        'email' => $cliente->email,
                        'condicion_iva_id' => $cliente->condicion_iva_id,
                        'es_sucursal_interna' => false,
                        'cliente_id' => $cliente->id,
                        'activo' => $cliente->activo,
                    ]);
                }
            } else {
                // Vincular a proveedor existente seleccionado
                $proveedorSeleccionado = Proveedor::find($this->proveedor_opcion);
                if ($proveedorSeleccionado) {
                    // Desvincular el proveedor actual si existe y es diferente
                    if ($proveedorActual && $proveedorActual->id != $proveedorSeleccionado->id) {
                        $proveedorActual->update(['cliente_id' => null]);
                    }
                    // Vincular el proveedor seleccionado
                    $proveedorSeleccionado->update(['cliente_id' => $cliente->id]);
                }
            }
        } else {
            // Si se desmarca "también es proveedor" y existía, desvinculamos
            if ($proveedorActual) {
                $proveedorActual->update(['cliente_id' => null]);
            }
        }
    }

    /**
     * Alterna el estado activo de un cliente
     */
    public function toggleStatus(int $id): void
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['activo' => !$cliente->activo]);

        $status = $cliente->activo ? __('activado') : __('desactivado');
        $this->dispatch('notify', type: 'success', message: __('Cliente') . ' ' . $status);
    }

    /**
     * Confirmar eliminación de un cliente
     */
    public function confirmDelete(int $id): void
    {
        $cliente = Cliente::findOrFail($id);
        $this->clienteAEliminar = $cliente->id;
        $this->nombreClienteAEliminar = $cliente->nombre;
        $this->showDeleteModal = true;
    }

    /**
     * Elimina un cliente
     */
    public function delete(): void
    {
        if ($this->clienteAEliminar) {
            $cliente = Cliente::find($this->clienteAEliminar);

            if ($cliente) {
                // Verificar si tiene ventas asociadas
                if ($cliente->ventas()->exists()) {
                    $this->dispatch('notify', type: 'error', message: __('No se puede eliminar el cliente porque tiene ventas asociadas'));
                    $this->closeDeleteModal();
                    return;
                }

                // Desvincular proveedor si existe
                Proveedor::where('cliente_id', $cliente->id)->update(['cliente_id' => null]);

                // Eliminar relaciones con sucursales
                $cliente->sucursales()->detach();

                // Eliminar cliente
                $cliente->delete();

                $this->dispatch('notify', type: 'success', message: __('Cliente eliminado correctamente'));
            }
        }

        $this->closeDeleteModal();
    }

    /**
     * Muestra el historial de ventas del cliente
     */
    public function showHistorial(int $id): void
    {
        $cliente = Cliente::findOrFail($id);
        $this->clienteHistorialId = $cliente->id;
        $this->nombreClienteHistorial = $cliente->nombre;
        $this->showHistorialModal = true;
    }

    /**
     * Cierra el modal de historial
     */
    public function closeHistorialModal(): void
    {
        $this->showHistorialModal = false;
        $this->clienteHistorialId = null;
        $this->nombreClienteHistorial = null;
    }

    /**
     * Abre el modal de configuración de sucursales para un cliente
     */
    public function openSucursalesConfig(int $id): void
    {
        $cliente = Cliente::with(['sucursales'])->findOrFail($id);

        $this->clienteConfigId = $cliente->id;
        $this->clienteConfigNombre = $cliente->nombre;

        // Cargar configuración actual de sucursales
        $this->sucursalesConfig = [];
        foreach ($cliente->sucursales as $sucursal) {
            $this->sucursalesConfig[$sucursal->id] = [
                'activo' => (bool) $sucursal->pivot->activo,
                'lista_precio_id' => $sucursal->pivot->lista_precio_id,
            ];
        }

        $this->showSucursalesModal = true;
    }

    /**
     * Cierra el modal de configuración de sucursales
     */
    public function closeSucursalesModal(): void
    {
        $this->showSucursalesModal = false;
        $this->clienteConfigId = null;
        $this->clienteConfigNombre = null;
        $this->sucursalesConfig = [];
    }

    /**
     * Alterna el estado activo de una sucursal para el cliente
     */
    public function toggleSucursalConfig(int $sucursalId): void
    {
        if (!isset($this->sucursalesConfig[$sucursalId])) {
            // Obtener lista base de la sucursal
            $listaBase = ListaPrecio::where('sucursal_id', $sucursalId)
                ->where('es_lista_base', true)
                ->value('id');

            $this->sucursalesConfig[$sucursalId] = [
                'activo' => true,
                'lista_precio_id' => $listaBase,
            ];
        } else {
            $this->sucursalesConfig[$sucursalId]['activo'] = !$this->sucursalesConfig[$sucursalId]['activo'];
        }
    }

    /**
     * Guarda la configuración de sucursales del cliente
     */
    public function saveSucursalesConfig(): void
    {
        $cliente = Cliente::findOrFail($this->clienteConfigId);

        $syncData = [];
        foreach ($this->sucursalesConfig as $sucursalId => $config) {
            $syncData[$sucursalId] = [
                'activo' => $config['activo'],
                'lista_precio_id' => $config['lista_precio_id'],
            ];
        }

        $cliente->sucursales()->sync($syncData);

        $this->dispatch('notify', type: 'success', message: __('Configuración de sucursales actualizada'));
        $this->closeSucursalesModal();
    }

    /**
     * Obtiene las listas de precios de una sucursal
     */
    public function getListasPrecioSucursal(int $sucursalId)
    {
        return ListaPrecio::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('es_lista_base', 'desc')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene las ventas del cliente para el historial
     */
    public function getVentasHistorial()
    {
        if (!$this->clienteHistorialId) {
            return collect();
        }

        return \App\Models\Venta::where('cliente_id', $this->clienteHistorialId)
            ->with(['sucursal', 'formaPago'])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
    }

    /**
     * Exporta el listado de clientes a CSV
     */
    public function exportarExcel()
    {
        $clientes = Cliente::with(['condicionIva', 'sucursales'])
            ->when($this->filterStatus !== 'all', function($q) {
                $q->where('activo', $this->filterStatus === 'active');
            })
            ->when($this->filterCondicionIva !== 'all', function($q) {
                $q->where('condicion_iva_id', $this->filterCondicionIva);
            })
            ->orderBy('nombre')
            ->get();

        $filename = 'clientes_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($clientes) {
            $file = fopen('php://output', 'w');
            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                __('Nombre'),
                __('Razón Social'),
                __('CUIT'),
                __('Email'),
                __('Teléfono'),
                __('Dirección'),
                __('Condición IVA'),
                __('Cuenta Corriente'),
                __('Límite Crédito'),
                __('Saldo Deudor'),
                __('Estado'),
            ], ';');

            // Data
            foreach ($clientes as $cliente) {
                fputcsv($file, [
                    $cliente->nombre,
                    $cliente->razon_social ?? '',
                    $cliente->cuit ?? '',
                    $cliente->email ?? '',
                    $cliente->telefono ?? '',
                    $cliente->direccion ?? '',
                    $cliente->condicionIva?->nombre ?? __('Consumidor Final'),
                    $cliente->tiene_cuenta_corriente ? __('Sí') : __('No'),
                    number_format($cliente->limite_credito, 2, ',', '.'),
                    number_format($cliente->saldo_deudor_cache, 2, ',', '.'),
                    $cliente->activo ? __('Activo') : __('Inactivo'),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Abre el modal de importación
     */
    public function openImportModal(): void
    {
        $this->archivoImportacion = null;
        $this->sucursales_importacion = Sucursal::activas()->pluck('id')->toArray(); // Por defecto todas
        $this->importacionResultado = [];
        $this->importacionProcesada = false;
        $this->showImportModal = true;
    }

    /**
     * Cierra el modal de importación
     */
    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->archivoImportacion = null;
        $this->sucursales_importacion = [];
        $this->importacionResultado = [];
        $this->importacionProcesada = false;
    }

    /**
     * Importa clientes desde un archivo CSV
     */
    public function importarClientes(): void
    {
        $this->validate([
            'archivoImportacion' => 'required|file|mimes:csv,txt|max:5120',
            'sucursales_importacion' => 'required|array|min:1',
        ], [
            'archivoImportacion.required' => __('Debe seleccionar un archivo'),
            'archivoImportacion.mimes' => __('El archivo debe ser CSV'),
            'archivoImportacion.max' => __('El archivo no debe superar 5MB'),
            'sucursales_importacion.required' => __('Debe seleccionar al menos una sucursal'),
            'sucursales_importacion.min' => __('Debe seleccionar al menos una sucursal'),
        ]);

        $resultado = [
            'exitosos' => 0,
            'errores' => [],
            'omitidos' => 0,
        ];

        try {
            $path = $this->archivoImportacion->getRealPath();
            $handle = fopen($path, 'r');

            if ($handle === false) {
                throw new \Exception(__('No se pudo abrir el archivo'));
            }

            // Detectar BOM y delimitador
            $firstLine = fgets($handle);
            rewind($handle);

            // Quitar BOM si existe
            $bom = pack('H*', 'EFBBBF');
            $firstLine = preg_replace("/^$bom/", '', $firstLine);

            // Detectar delimitador (punto y coma o coma)
            $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';

            // Leer cabecera
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                throw new \Exception(__('El archivo está vacío o tiene formato incorrecto'));
            }

            // Limpiar BOM del primer campo del header
            $header[0] = preg_replace("/^$bom/", '', $header[0]);

            // Mapear condiciones IVA por nombre
            $condicionesIva = CondicionIva::all()->keyBy(function($item) {
                return mb_strtolower(trim($item->nombre));
            });

            // Obtener lista base de cada sucursal seleccionada
            $listasBase = [];
            foreach ($this->sucursales_importacion as $sucursalId) {
                $listasBase[$sucursalId] = ListaPrecio::where('sucursal_id', $sucursalId)
                    ->where('es_lista_base', true)
                    ->value('id');
            }

            // Consumidor Final por defecto
            $consumidorFinal = CondicionIva::where('codigo', CondicionIva::CONSUMIDOR_FINAL)->first();

            $lineNumber = 1;
            DB::beginTransaction();

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;

                // Saltar filas vacías
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // Mapear columnas
                    $data = [];
                    foreach ($header as $index => $column) {
                        $columnName = mb_strtolower(trim($column));
                        $data[$columnName] = isset($row[$index]) ? trim($row[$index]) : '';
                    }

                    // Nombre es obligatorio
                    $nombre = $data['nombre'] ?? $data[__('nombre')] ?? '';
                    if (empty($nombre)) {
                        $resultado['errores'][] = __('Línea :line: El nombre es obligatorio', ['line' => $lineNumber]);
                        continue;
                    }

                    // Verificar si ya existe por CUIT (si tiene)
                    $cuit = $data['cuit'] ?? $data[__('cuit')] ?? '';
                    if (!empty($cuit)) {
                        $existente = Cliente::where('cuit', $cuit)->first();
                        if ($existente) {
                            $resultado['omitidos']++;
                            continue;
                        }
                    }

                    // Buscar condición IVA
                    $condicionIvaNombre = mb_strtolower($data['condición iva'] ?? $data['condicion iva'] ?? $data[__('condición iva')] ?? '');
                    $condicionIvaId = $consumidorFinal?->id;
                    if (!empty($condicionIvaNombre) && isset($condicionesIva[$condicionIvaNombre])) {
                        $condicionIvaId = $condicionesIva[$condicionIvaNombre]->id;
                    }

                    // Determinar cuenta corriente
                    $cuentaCorriente = mb_strtolower($data['cuenta corriente'] ?? $data[__('cuenta corriente')] ?? '');
                    $tieneCuentaCorriente = in_array($cuentaCorriente, ['sí', 'si', 'yes', '1', 'true']);

                    // Parsear límite de crédito
                    $limiteCredito = $data['límite crédito'] ?? $data['limite credito'] ?? $data[__('límite crédito')] ?? '0';
                    $limiteCredito = (float) str_replace(['.', ','], ['', '.'], $limiteCredito);

                    // Determinar estado
                    $estado = mb_strtolower($data['estado'] ?? $data[__('estado')] ?? 'activo');
                    $activo = !in_array($estado, ['inactivo', 'inactive', '0', 'false']);

                    // Crear cliente
                    $cliente = Cliente::create([
                        'nombre' => $nombre,
                        'razon_social' => $data['razón social'] ?? $data['razon social'] ?? $data[__('razón social')] ?? null,
                        'cuit' => $cuit ?: null,
                        'email' => $data['email'] ?? $data[__('email')] ?? null,
                        'telefono' => $data['teléfono'] ?? $data['telefono'] ?? $data[__('teléfono')] ?? null,
                        'direccion' => $data['dirección'] ?? $data['direccion'] ?? $data[__('dirección')] ?? null,
                        'condicion_iva_id' => $condicionIvaId,
                        'tiene_cuenta_corriente' => $tieneCuentaCorriente,
                        'limite_credito' => $tieneCuentaCorriente ? $limiteCredito : 0,
                        'activo' => $activo,
                    ]);

                    // Asignar a las sucursales seleccionadas con lista base
                    $sucursalesData = [];
                    foreach ($this->sucursales_importacion as $sucursalId) {
                        $sucursalesData[$sucursalId] = [
                            'activo' => true,
                            'lista_precio_id' => $listasBase[$sucursalId] ?? null,
                        ];
                    }
                    $cliente->sucursales()->sync($sucursalesData);

                    $resultado['exitosos']++;

                } catch (\Exception $e) {
                    $resultado['errores'][] = __('Línea :line: :error', ['line' => $lineNumber, 'error' => $e->getMessage()]);
                }
            }

            fclose($handle);
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $resultado['errores'][] = $e->getMessage();
        }

        $this->importacionResultado = $resultado;
        $this->importacionProcesada = true;

        if ($resultado['exitosos'] > 0) {
            $this->dispatch('notify', type: 'success', message: __(':count clientes importados correctamente', ['count' => $resultado['exitosos']]));
        }
    }

    /**
     * Cierra el modal
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Cierra el modal de eliminación
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->clienteAEliminar = null;
        $this->nombreClienteAEliminar = null;
    }

    /**
     * Resetea el formulario
     */
    protected function resetForm(): void
    {
        $this->clienteId = null;
        $this->nombre = '';
        $this->razon_social = '';
        $this->cuit = '';
        $this->email = '';
        $this->telefono = '';
        $this->direccion = '';
        $this->condicion_iva_id = null;
        $this->tiene_cuenta_corriente = false;
        $this->limite_credito = 0;
        $this->dias_credito = 30;
        $this->tasa_interes_mensual = 0;
        $this->activo = true;
        $this->tambien_es_proveedor = false;
        $this->proveedor_vinculado_id = null;
        $this->proveedor_opcion = 'crear_nuevo';
        $this->sucursales_seleccionadas = [];
        $this->resetValidation();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        // Obtener proveedores sin cliente vinculado (disponibles para vincular)
        $proveedoresDisponibles = Proveedor::where('activo', true)
            ->where(function($q) {
                $q->whereNull('cliente_id')
                  ->orWhere('cliente_id', $this->clienteId); // Incluir el actual si está editando
            })
            ->orderBy('nombre')
            ->get();

        return view('livewire.clientes.gestionar-clientes', [
            'clientes' => $this->getClientes(),
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
            'condicionesIva' => CondicionIva::orderBy('nombre')->get(),
            'proveedoresDisponibles' => $proveedoresDisponibles,
            'ventasHistorial' => $this->getVentasHistorial(),
        ]);
    }
}
