<?php

namespace App\Livewire\Concerns\Carrito;

use Illuminate\Support\Facades\Auth;

/**
 * Manejo de invitaciones (cortesias) en el carrito.
 *
 * Encapsula la mecanica de marcar items o el pedido/venta completo como
 * "invitacion" (regalo) preservando trazabilidad: motivo, usuario y fecha.
 * Item invitado = precio_unitario en 0 + flag + snapshot del precio original
 * (`precio_unitario_original`) para poder revertir. NO desdoblamos cantidades:
 * la granularidad es la linea completa.
 *
 * Items invitados deben quedar EXCLUIDOS del motor de promociones, cupones y
 * descuentos generales (RF-11 del spec). Esa exclusion se implementa en la
 * Fase 3 al refactorizar `WithCalculoVenta/WithCupones/WithDescuentos`; el
 * trait solo se encarga de DEJAR el item con todos sus campos de descuento en
 * cero al marcarlo como invitado (defense in depth).
 *
 * El componente host puede override `getPermisoInvitacionPrefix()` para usar
 * un prefijo distinto (ej: `func.pedidos_mostrador` vs `func.ventas`). Esto
 * permite reutilizar el trait en NuevoPedidoMostrador, NuevaVenta y futuros
 * canales (delivery) con permisos independientes.
 *
 * Espec: .claude/specs/invitaciones-pedidos-ventas.md.
 *
 * Dependencias externas (resueltas via $this-> desde el componente host):
 * - $this->items                      (WithCarritoItems)
 * - $this->calcularVenta()            (WithCalculoVenta)
 * - $this->getPermisoInvitacionPrefix() — el host puede override.
 */
trait WithInvitaciones
{
    // =========================================
    // PROPIEDADES — SWITCH GLOBAL DE INVITACION
    // =========================================

    /** @var bool Switch "Invitar pedido completo" del modal de cobro. */
    public bool $invitarTodo = false;

    /** @var string Motivo cuando se invita el pedido entero. */
    public string $motivoInvitacionTotal = '';

    // =========================================
    // PROPIEDADES — MINI-MODAL POR ITEM
    // =========================================

    /** @var bool Visibility del mini-modal para invitar un item. */
    public bool $mostrarModalInvitarItem = false;

    /** @var int|null Indice del item que se esta por invitar. */
    public ?int $invitarItemIndex = null;

    /** @var string Motivo ingresado en el mini-modal. */
    public string $invitarItemMotivo = '';

    /** @var bool Visibility del mini-modal de confirmacion de des-invitacion. */
    public bool $mostrarModalDesinvitarItem = false;

    /** @var int|null Indice del item que se esta por des-invitar. */
    public ?int $desinvitarItemIndex = null;

    // =========================================
    // HOOK CONFIGURABLE — PREFIJO DE PERMISOS
    // =========================================

    /**
     * Prefijo de permisos a usar para validar invitar_pedido / invitar_renglon.
     * El componente host DEBE overridear este metodo (`'func.pedidos_mostrador'`
     * o `'func.ventas'`).
     */
    protected function getPermisoInvitacionPrefix(): string
    {
        return 'func.pedidos_mostrador';
    }

    /**
     * Nombre del permiso "invitar pedido/venta completo".
     * Para Pedidos: `func.pedidos_mostrador.invitar_pedido`.
     * Para Ventas:  `func.ventas.invitar_venta`.
     * Los componentes que override `getPermisoInvitacionPrefix()` pueden
     * override este metodo si su sufijo no es `invitar_pedido`.
     */
    protected function getPermisoInvitarTotalSuffix(): string
    {
        return 'invitar_pedido';
    }

    protected function puedeInvitarPedido(): bool
    {
        $permiso = $this->getPermisoInvitacionPrefix().'.'.$this->getPermisoInvitarTotalSuffix();

        return Auth::user()?->hasPermissionTo($permiso) ?? false;
    }

    protected function puedeInvitarRenglon(): bool
    {
        $permiso = $this->getPermisoInvitacionPrefix().'.invitar_renglon';

        return Auth::user()?->hasPermissionTo($permiso) ?? false;
    }

    /**
     * Computed property — accesible desde Blade como `$puedeInvitarPedido`.
     * Permite al modal de cobro decidir si mostrar el switch "Invitar pedido
     * completo" sin filtrar el permiso a mano en cada vista.
     */
    public function getPuedeInvitarPedidoProperty(): bool
    {
        return $this->puedeInvitarPedido();
    }

    /**
     * Computed property — accesible desde Blade como `$puedeInvitarRenglon`.
     * Usable por `_detalle-items` para deshabilitar el botón de invitar item.
     */
    public function getPuedeInvitarRenglonProperty(): bool
    {
        return $this->puedeInvitarRenglon();
    }

    // =========================================
    // FLUJO POR ITEM
    // =========================================

    /**
     * Abre el mini-modal para invitar un item individual.
     */
    public function abrirInvitarItem(int $index): void
    {
        if (! $this->puedeInvitarRenglon()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar renglones'));

            return;
        }

        if (! isset($this->items[$index])) {
            return;
        }

        $this->invitarItemIndex = $index;
        $this->invitarItemMotivo = '';
        $this->mostrarModalInvitarItem = true;
    }

    /**
     * Aplica la invitacion al item activo: precio=0, snapshot, metadatos,
     * reset de todos los descuentos.
     */
    public function confirmarInvitarItem(): void
    {
        if (! $this->puedeInvitarRenglon()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar renglones'));

            return;
        }

        $motivo = trim($this->invitarItemMotivo);
        if ($motivo === '') {
            $this->dispatch('toast-error', message: __('El motivo es obligatorio'));

            return;
        }

        $index = $this->invitarItemIndex;
        if ($index === null || ! isset($this->items[$index])) {
            $this->cerrarModalInvitarItem();

            return;
        }

        $this->marcarItemComoInvitado($index, $motivo);

        $this->cerrarModalInvitarItem();
        $this->recalcularTotalInvitado();
        $this->calcularVenta();
    }

    public function cerrarModalInvitarItem(): void
    {
        $this->mostrarModalInvitarItem = false;
        $this->invitarItemIndex = null;
        $this->invitarItemMotivo = '';
    }

    /**
     * Abre la confirmacion para quitar la invitacion a un item.
     */
    public function abrirDesinvitarItem(int $index): void
    {
        if (! $this->puedeInvitarRenglon()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar renglones'));

            return;
        }

        if (! isset($this->items[$index]) || empty($this->items[$index]['es_invitacion'])) {
            return;
        }

        $this->desinvitarItemIndex = $index;
        $this->mostrarModalDesinvitarItem = true;
    }

    public function confirmarDesinvitarItem(): void
    {
        if (! $this->puedeInvitarRenglon()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar renglones'));

            return;
        }

        $index = $this->desinvitarItemIndex;
        if ($index === null || ! isset($this->items[$index])) {
            $this->cerrarModalDesinvitarItem();

            return;
        }

        $this->desmarcarItem($index);

        $this->cerrarModalDesinvitarItem();
        $this->recalcularTotalInvitado();
        $this->calcularVenta();
    }

    public function cerrarModalDesinvitarItem(): void
    {
        $this->mostrarModalDesinvitarItem = false;
        $this->desinvitarItemIndex = null;
    }

    // =========================================
    // FLUJO MASIVO (PEDIDO/VENTA COMPLETO)
    // =========================================

    /**
     * Toggle del switch "Invitar pedido completo". Al apagar resetea el motivo.
     */
    public function toggleInvitarTodo(): void
    {
        if (! $this->puedeInvitarPedido()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar el pedido'));
            $this->invitarTodo = false;

            return;
        }

        $this->invitarTodo = ! $this->invitarTodo;

        if (! $this->invitarTodo) {
            $this->motivoInvitacionTotal = '';
        }
    }

    /**
     * Marca todos los items del carrito como invitados con el mismo motivo.
     * No persiste — solo prepara el estado en memoria. El service persiste al
     * llamar a `confirmarPago()` / `procesarVenta()`.
     */
    public function confirmarInvitarTodo(): void
    {
        if (! $this->puedeInvitarPedido()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para invitar el pedido'));

            return;
        }

        $motivo = trim($this->motivoInvitacionTotal);
        if ($motivo === '') {
            $this->dispatch('toast-error', message: __('El motivo es obligatorio'));

            return;
        }

        foreach ($this->items as $index => $_item) {
            $this->marcarItemComoInvitado($index, $motivo);
        }

        $this->recalcularTotalInvitado();
        $this->calcularVenta();
    }

    // =========================================
    // HELPERS DE BAJO NIVEL
    // =========================================

    /**
     * Setea un item como invitado: snapshot del precio, precio=0, metadatos
     * y reset de todos los descuentos (defensa para que el motor de promos
     * no aplique sobre items invitados aunque no estuviera filtrado).
     */
    protected function marcarItemComoInvitado(int $index, string $motivo): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $item = $this->items[$index];

        // Snapshot del precio actual SOLO si no estaba invitado de antes.
        // Si re-invitamos un item que ya tenia precio_unitario_original
        // (caso edge: backend rehidrato uno cancelado), preservamos el snapshot.
        if (empty($item['es_invitacion'])) {
            $item['precio_unitario_original'] = (float) ($item['precio'] ?? 0);
        }

        $cantidad = (float) ($item['cantidad'] ?? 0);
        $precioOriginal = (float) ($item['precio_unitario_original'] ?? 0);

        $item['es_invitacion'] = true;
        $item['invitacion_motivo'] = $motivo;
        $item['invitado_por_usuario_id'] = Auth::id();
        $item['invitado_at'] = now()->toDateTimeString();
        $item['monto_invitado'] = round($cantidad * $precioOriginal, 2);

        // El item invitado tiene precio cobrable 0.
        $item['precio'] = 0.0;

        // Reset de todos los descuentos (RF-11).
        $item['descuento'] = 0;
        $item['descuento_porcentaje'] = 0;
        $item['descuento_monto'] = 0;
        $item['descuento_promocion'] = 0;
        $item['descuento_promocion_especial'] = 0;
        $item['descuento_cupon'] = 0;
        $item['descuento_lista'] = 0;
        $item['tiene_promocion'] = false;
        $item['_promociones_item'] = [];

        // Reset del ajuste manual si lo tenia.
        $item['ajuste_manual_tipo'] = null;
        $item['ajuste_manual_valor'] = null;
        $item['ajuste_manual_origen'] = null;
        $item['ajuste_manual_aplicado_por'] = null;
        $item['precio_sin_ajuste_manual'] = null;
        $item['tiene_ajuste'] = false;

        $this->items[$index] = $item;
    }

    /**
     * Quita la invitacion: restaura el precio desde el snapshot y limpia
     * metadatos. Despues el host llama a `calcularVenta()` para que el motor
     * de promos re-evalue el item.
     */
    protected function desmarcarItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $item = $this->items[$index];

        if (empty($item['es_invitacion'])) {
            return;
        }

        $item['precio'] = (float) ($item['precio_unitario_original'] ?? $item['precio'] ?? 0);
        $item['es_invitacion'] = false;
        $item['invitacion_motivo'] = null;
        $item['invitado_por_usuario_id'] = null;
        $item['invitado_at'] = null;
        $item['monto_invitado'] = 0;
        $item['precio_unitario_original'] = null;

        $this->items[$index] = $item;
    }

    /**
     * Recalcula total_invitado sumando los `monto_invitado` de los items.
     * El componente host puede leer `$this->totalInvitado` en su resumen.
     */
    protected function recalcularTotalInvitado(): void
    {
        $this->totalInvitado = round(
            array_sum(array_map(
                fn ($item) => (float) ($item['monto_invitado'] ?? 0),
                $this->items
            )),
            2
        );
    }

    /** @var float Total monetario regalado (suma de monto_invitado de items). */
    public float $totalInvitado = 0.0;

    /**
     * True si el pedido/venta completo es invitacion: hay al menos un item y
     * todos estan marcados como `es_invitacion=true`.
     */
    public function getEsInvitacionTotalProperty(): bool
    {
        if (empty($this->items)) {
            return false;
        }

        foreach ($this->items as $item) {
            if (empty($item['es_invitacion'])) {
                return false;
            }
        }

        return true;
    }
}
