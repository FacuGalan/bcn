<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Descuentos y ajustes manuales en NuevaVenta.
 *
 * Encapsula:
 * - Ajuste manual por item (popover con tipo monto/porcentaje, validaciones, toggle tiene_ajuste).
 * - Descuento general al carrito (porcentaje hereda a items, monto fijo se descuenta del total).
 * - Tope por rol del usuario (max porcentaje permitido segun roles).
 * - Restauracion de precios originales al quitar descuento general.
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->items                  (WithCarritoItems)
 * - $this->resultado              (NuevaVenta)
 * - $this->calcularVenta()        (NuevaVenta — ira a WithCalculoVenta)
 * - $this->obtenerPrecioConLista() (NuevaVenta — ira a WithCalculoVenta)
 */
trait WithDescuentos
{
    // =========================================
    // PROPIEDADES DE AJUSTE MANUAL DE PRECIOS
    // =========================================

    /** @var int|null Índice del item con popover de ajuste abierto */
    public $ajusteManualPopoverIndex = null;

    /** @var string Tipo de ajuste en el popover ('monto' o 'porcentaje') */
    public $ajusteManualTipo = 'monto';

    /** @var float|null Valor ingresado en el popover de ajuste */
    public $ajusteManualValor = null;

    // =========================================
    // PROPIEDADES DE DESCUENTO GENERAL
    // =========================================

    /** @var bool Modal de descuentos y beneficios visible */
    public bool $showModalDescuentos = false;

    /** @var bool Si hay un descuento general activo */
    public bool $descuentoGeneralActivo = false;

    /** @var string|null Tipo de descuento general: 'porcentaje' o 'monto_fijo' */
    public ?string $descuentoGeneralTipo = null;

    /** @var float|null Valor del descuento general (% o $) */
    public ?float $descuentoGeneralValor = null;

    /** @var float Monto efectivo descontado por descuento general (calculado) */
    public float $descuentoGeneralMonto = 0;

    /** @var float|null Tope de descuento % del usuario (MAX de sus roles, null = sin tope) */
    public ?float $topeDescuentoUsuario = null;

    /** @var float|null Valor temporal del input en el modal */
    public ?float $descuentoGeneralInputValor = null;

    /** @var string Tipo temporal del input en el modal */
    public string $descuentoGeneralInputTipo = 'porcentaje';

    // =========================================
    // AJUSTE MANUAL DE PRECIOS
    // =========================================

    /**
     * Abre el popover de ajuste manual para un item
     */
    public function abrirAjusteManual(int $index, string $tipo): void
    {
        $this->ajusteManualPopoverIndex = $index;
        $this->ajusteManualTipo = $tipo; // 'monto' o 'porcentaje'
        $this->ajusteManualValor = null;
    }

    /**
     * Cierra el popover de ajuste manual
     */
    public function cerrarAjusteManual(): void
    {
        $this->ajusteManualPopoverIndex = null;
        $this->ajusteManualTipo = 'monto';
        $this->ajusteManualValor = null;
        // Devolver foco al buscador de artículos
        $this->dispatch('focus-busqueda');
    }

    /**
     * Aplica el ajuste manual al precio del item
     */
    public function aplicarAjusteManual(): void
    {
        $index = $this->ajusteManualPopoverIndex;

        if ($index === null || ! isset($this->items[$index])) {
            $this->cerrarAjusteManual();

            return;
        }

        $item = $this->items[$index];
        $precioBase = (float) $item['precio_base'];
        $valor = $this->ajusteManualValor;

        // Validar que se ingresó un valor
        if ($valor === null || $valor === '') {
            $this->dispatch('toast-error', message: 'Ingrese un valor');

            return;
        }

        $valor = (float) $valor;

        if ($this->ajusteManualTipo === 'monto') {
            // El valor es el nuevo precio directo
            if ($valor <= 0) {
                $this->dispatch('toast-error', message: 'El precio debe ser mayor a cero');

                return;
            }
            $nuevoPrecio = $valor;
        } else {
            // El valor es un porcentaje (positivo = descuento, negativo = recargo)
            if ($valor < -100 || $valor > 100) {
                $this->dispatch('toast-error', message: 'El porcentaje debe estar entre -100% y 100%');

                return;
            }
            // Positivo resta (descuento), negativo suma (recargo)
            $nuevoPrecio = round($precioBase - ($precioBase * $valor / 100), 2);
            if ($nuevoPrecio <= 0) {
                $this->dispatch('toast-error', message: 'El precio resultante debe ser mayor a cero');

                return;
            }
        }

        // Guardar el precio anterior para mostrar tachado
        $this->items[$index]['precio_sin_ajuste_manual'] = $item['precio'];
        $this->items[$index]['precio'] = $nuevoPrecio;
        $this->items[$index]['ajuste_manual_tipo'] = $this->ajusteManualTipo;
        $this->items[$index]['ajuste_manual_valor'] = $valor;
        // Marcar que tiene ajuste (para mostrar visualmente)
        $this->items[$index]['tiene_ajuste'] = true;

        $this->cerrarAjusteManual();
        $this->calcularVenta();

        $tipoTexto = $this->ajusteManualTipo === 'monto' ? 'Precio manual' : 'Descuento';
        $this->dispatch('toast-success', message: "{$tipoTexto} aplicado");
    }

    /**
     * Quita el ajuste manual de un item y restaura el precio calculado
     */
    public function quitarAjusteManual(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $item = $this->items[$index];

        // Solo procesar si tiene ajuste manual
        if ($item['ajuste_manual_tipo'] === null) {
            return;
        }

        // Recalcular el precio según lista de precios
        $articulo = Articulo::find($item['articulo_id']);
        if ($articulo) {
            $precioInfo = $this->obtenerPrecioConLista($articulo);
            $this->items[$index]['precio'] = $precioInfo['precio'];
            $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
            $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
        }

        // Limpiar campos de ajuste manual
        $this->items[$index]['ajuste_manual_tipo'] = null;
        $this->items[$index]['ajuste_manual_valor'] = null;
        $this->items[$index]['precio_sin_ajuste_manual'] = null;

        $this->calcularVenta();
        $this->dispatch('toast-info', message: 'Ajuste manual eliminado');
    }

    // =========================================
    // DESCUENTO GENERAL
    // =========================================

    /**
     * Abre el modal de Descuentos y Beneficios
     */
    public function abrirModalDescuentos(): void
    {
        // Inicializar inputs del modal con los valores activos (si hay)
        if ($this->descuentoGeneralActivo) {
            $this->descuentoGeneralInputTipo = $this->descuentoGeneralTipo;
            $this->descuentoGeneralInputValor = $this->descuentoGeneralValor;
        } else {
            $this->descuentoGeneralInputTipo = 'porcentaje';
            $this->descuentoGeneralInputValor = null;
        }

        $this->showModalDescuentos = true;
    }

    /**
     * Cierra el modal de Descuentos y Beneficios
     */
    public function cerrarModalDescuentos(): void
    {
        $this->showModalDescuentos = false;
    }

    /**
     * Carga el tope de descuento del usuario (MAX de sus roles)
     */
    protected function cargarTopeDescuentoUsuario(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $tope = DB::connection('pymes_tenant')
            ->table('roles')
            ->join('model_has_roles', function ($join) use ($user) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.model_id', $user->id);
            })
            ->whereNotNull('roles.descuento_maximo_porcentaje')
            ->max('roles.descuento_maximo_porcentaje');

        $this->topeDescuentoUsuario = $tope !== null ? (float) $tope : null;
    }

    /**
     * Aplica descuento general al carrito.
     * % → aplica ajuste_manual masivo a todos los items
     * $ → se resta del total en calcularVenta()
     */
    public function aplicarDescuentoGeneral(): void
    {
        $tipo = $this->descuentoGeneralInputTipo;
        $valor = $this->descuentoGeneralInputValor;

        if ($valor === null || $valor === '' || (float) $valor <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese un valor mayor a cero'));

            return;
        }

        $valor = (float) $valor;

        // Verificar permiso
        $user = Auth::user();
        if (! $user || ! $user->hasPermissionTo('func.descuento_general')) {
            $this->dispatch('toast-error', message: __('No tiene permiso para aplicar descuento general'));

            return;
        }

        // Validar tope por rol
        if ($this->topeDescuentoUsuario !== null) {
            if ($tipo === 'porcentaje' && $valor > $this->topeDescuentoUsuario) {
                $this->dispatch('toast-error', message: __('El descuento supera el máximo permitido para su rol')." ({$this->topeDescuentoUsuario}%)");

                return;
            }

            if ($tipo === 'monto_fijo') {
                // El monto fijo no puede superar el % tope del total pre-descuento
                $totalPreDescuento = $this->resultado['subtotal'] ?? 0;
                $maxMonto = round($totalPreDescuento * $this->topeDescuentoUsuario / 100, 2);
                if ($valor > $maxMonto) {
                    $this->dispatch('toast-error', message: __('El descuento supera el máximo permitido para su rol')." (\${$maxMonto})");

                    return;
                }
            }
        }

        if ($tipo === 'porcentaje') {
            if ($valor > 100) {
                $this->dispatch('toast-error', message: __('El porcentaje no puede superar 100%'));

                return;
            }
        }

        // RF-33: Exclusividad % / $ — si ya hay uno activo, quitar primero
        // RF-35: Re-aplicar % pisa ajustes individuales previos
        if ($this->descuentoGeneralActivo) {
            if ($this->descuentoGeneralTipo === 'porcentaje') {
                $this->restaurarPreciosOriginalesItems();
            }
            $this->descuentoGeneralActivo = false;
        }

        if ($tipo === 'porcentaje') {
            $this->aplicarDescuentoPorcentajeATodosLosItems($valor);
        }

        // Guardar estado del descuento general
        $this->descuentoGeneralActivo = true;
        $this->descuentoGeneralTipo = $tipo;
        $this->descuentoGeneralValor = $valor;

        $this->calcularVenta();

        $etiqueta = $tipo === 'porcentaje' ? "{$valor}%" : "\${$valor}";
        $this->dispatch('toast-success', message: __('Descuento general aplicado').": {$etiqueta}");
    }

    /**
     * Quita el descuento general, restaurando precios originales si era porcentaje
     */
    public function quitarDescuentoGeneral(): void
    {
        if (! $this->descuentoGeneralActivo) {
            return;
        }

        // Si era porcentaje, restaurar precios de todos los items
        if ($this->descuentoGeneralTipo === 'porcentaje') {
            $this->restaurarPreciosOriginalesItems();
        }

        $this->descuentoGeneralActivo = false;
        $this->descuentoGeneralTipo = null;
        $this->descuentoGeneralValor = null;
        $this->descuentoGeneralMonto = 0;
        $this->descuentoGeneralInputValor = null;
        $this->descuentoGeneralInputTipo = 'porcentaje';

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Descuento general eliminado'));
    }

    /**
     * Aplica ajuste_manual porcentaje a todos los items del carrito (RF-31)
     */
    protected function aplicarDescuentoPorcentajeATodosLosItems(float $porcentaje): void
    {
        foreach ($this->items as $index => $item) {
            $precioBase = (float) $item['precio_base'];
            $nuevoPrecio = round($precioBase - ($precioBase * $porcentaje / 100), 2);

            if ($nuevoPrecio < 0) {
                $nuevoPrecio = 0;
            }

            $this->items[$index]['precio_sin_ajuste_manual'] = $item['precio_base'];
            $this->items[$index]['precio'] = $nuevoPrecio;
            $this->items[$index]['ajuste_manual_tipo'] = 'porcentaje';
            $this->items[$index]['ajuste_manual_valor'] = $porcentaje;
            $this->items[$index]['tiene_ajuste'] = true;
        }
    }

    /**
     * Restaura precios originales de todos los items (quita ajuste manual masivo)
     */
    protected function restaurarPreciosOriginalesItems(): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['ajuste_manual_tipo'] === null) {
                continue;
            }

            $articuloId = $item['articulo_id'] ?? null;
            if ($articuloId) {
                $articulo = Articulo::find($articuloId);
                if ($articulo) {
                    $precioInfo = $this->obtenerPrecioConLista($articulo);
                    $this->items[$index]['precio'] = $precioInfo['precio'];
                    $this->items[$index]['precio_base'] = $precioInfo['precio_base'];
                    $this->items[$index]['tiene_ajuste'] = $precioInfo['tiene_ajuste'];
                }
            } else {
                // Concepto: restaurar precio original
                $this->items[$index]['precio'] = $item['precio_sin_ajuste_manual'] ?? $item['precio'];
                $this->items[$index]['tiene_ajuste'] = false;
            }

            $this->items[$index]['ajuste_manual_tipo'] = null;
            $this->items[$index]['ajuste_manual_valor'] = null;
            $this->items[$index]['precio_sin_ajuste_manual'] = null;
        }
    }
}
