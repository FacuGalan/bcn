<?php

namespace Tests\Unit\Livewire\Concerns\Carrito;

use App\Livewire\Concerns\Carrito\WithInvitaciones;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\WithTenant;

/**
 * Tests focales del trait WithInvitaciones (Fase 2 del spec
 * `.claude/specs/invitaciones-pedidos-ventas.md`).
 *
 * Estrategia: usamos un host stub que compone el trait y override los hooks
 * de permisos. Asi probamos la mecanica del trait (marcar item, snapshot,
 * reset de descuentos, recalcular total) sin acoplar a Livewire ni Spatie.
 */
class WithInvitacionesTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function host(bool $puedePedido = true, bool $puedeRenglon = true): object
    {
        $host = new _HostInvitacionesStub;
        $host->permisoPedido = $puedePedido;
        $host->permisoRenglon = $puedeRenglon;

        return $host;
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            'articulo_id' => 1,
            'nombre' => 'Articulo Test',
            'cantidad' => 2,
            'precio' => 100.0,
            'precio_base' => 100.0,
            'es_concepto' => false,
            'descuento_promocion' => 0,
            'descuento_promocion_especial' => 0,
            'descuento_cupon' => 0,
            'descuento_lista' => 0,
            'tiene_promocion' => false,
            '_promociones_item' => [],
            'tiene_ajuste' => false,
            'es_invitacion' => false,
        ], $overrides);
    }

    public function test_invitar_item_setea_precio_cero_y_snapshot_del_precio_original(): void
    {
        $host = $this->host();
        $host->items[] = $this->item(['precio' => 250.0, 'cantidad' => 3]);

        $host->abrirInvitarItem(0);
        $this->assertTrue($host->mostrarModalInvitarItem);
        $this->assertSame(0, $host->invitarItemIndex);

        $host->invitarItemMotivo = 'Cortesía gerencia';
        $host->confirmarInvitarItem();

        $this->assertFalse($host->mostrarModalInvitarItem, 'Mini-modal debe cerrarse al confirmar');
        $this->assertTrue($host->items[0]['es_invitacion']);
        $this->assertSame(0.0, (float) $host->items[0]['precio']);
        $this->assertSame(250.0, (float) $host->items[0]['precio_unitario_original']);
        $this->assertSame('Cortesía gerencia', $host->items[0]['invitacion_motivo']);
        $this->assertNotNull($host->items[0]['invitado_por_usuario_id']);
        $this->assertNotNull($host->items[0]['invitado_at']);
        $this->assertEquals(750.0, $host->items[0]['monto_invitado'], 'monto_invitado = cantidad * precio_original');
        $this->assertTrue($host->calcularVentaInvocado, 'calcularVenta debe haberse llamado');
    }

    public function test_invitar_item_resetea_todos_los_descuentos_y_promociones(): void
    {
        $host = $this->host();
        $host->items[] = $this->item([
            'descuento_promocion' => 15.0,
            'descuento_promocion_especial' => 10.0,
            'descuento_cupon' => 5.0,
            'descuento_lista' => 8.0,
            'tiene_promocion' => true,
            '_promociones_item' => [['id' => 1, 'monto' => 15]],
            'ajuste_manual_tipo' => 'monto',
            'ajuste_manual_valor' => 20,
            'tiene_ajuste' => true,
        ]);

        $host->invitarItemIndex = 0;
        $host->invitarItemMotivo = 'Test';
        $host->confirmarInvitarItem();

        $item = $host->items[0];
        $this->assertSame(0, (int) $item['descuento_promocion']);
        $this->assertSame(0, (int) $item['descuento_promocion_especial']);
        $this->assertSame(0, (int) $item['descuento_cupon']);
        $this->assertSame(0, (int) $item['descuento_lista']);
        $this->assertFalse($item['tiene_promocion']);
        $this->assertSame([], $item['_promociones_item']);
        $this->assertNull($item['ajuste_manual_tipo']);
        $this->assertNull($item['ajuste_manual_valor']);
        $this->assertFalse($item['tiene_ajuste']);
    }

    public function test_motivo_vacio_rechaza_la_invitacion_y_dispara_toast(): void
    {
        $host = $this->host();
        $host->items[] = $this->item();

        $host->invitarItemIndex = 0;
        $host->invitarItemMotivo = '   '; // solo whitespace
        $host->confirmarInvitarItem();

        $this->assertFalse(
            $host->items[0]['es_invitacion'] ?? false,
            'El item no debe quedar invitado cuando el motivo esta vacio'
        );

        $errores = array_filter($host->eventos, fn ($e) => $e['event'] === 'toast-error');
        $this->assertNotEmpty($errores, 'Debe haberse dispatcheado al menos un toast-error');
    }

    public function test_sin_permiso_para_invitar_renglon_no_modifica_items(): void
    {
        $host = $this->host(puedeRenglon: false);
        $host->items[] = $this->item();

        $host->abrirInvitarItem(0);
        $this->assertFalse($host->mostrarModalInvitarItem, 'Sin permiso no se abre el mini-modal');
        $this->assertFalse(
            $host->items[0]['es_invitacion'] ?? false,
            'Sin permiso el item no se modifica'
        );

        $errores = array_filter($host->eventos, fn ($e) => $e['event'] === 'toast-error');
        $this->assertNotEmpty($errores);
    }

    public function test_desinvitar_item_restaura_precio_original_y_limpia_metadatos(): void
    {
        $host = $this->host();
        $host->items[] = $this->item(['precio' => 500.0, 'cantidad' => 1]);

        // Primero invitamos
        $host->invitarItemIndex = 0;
        $host->invitarItemMotivo = 'Cortesia';
        $host->confirmarInvitarItem();

        $this->assertSame(0.0, (float) $host->items[0]['precio']);
        $host->calcularVentaInvocado = false; // reset para la siguiente assertion

        // Ahora des-invitamos
        $host->abrirDesinvitarItem(0);
        $this->assertTrue($host->mostrarModalDesinvitarItem);
        $host->confirmarDesinvitarItem();

        $this->assertFalse($host->mostrarModalDesinvitarItem);
        $this->assertFalse($host->items[0]['es_invitacion']);
        $this->assertSame(500.0, (float) $host->items[0]['precio'], 'precio debe restaurarse al snapshot');
        $this->assertNull($host->items[0]['precio_unitario_original']);
        $this->assertNull($host->items[0]['invitacion_motivo']);
        $this->assertNull($host->items[0]['invitado_por_usuario_id']);
        $this->assertNull($host->items[0]['invitado_at']);
        $this->assertEquals(0, $host->items[0]['monto_invitado']);
        $this->assertTrue($host->calcularVentaInvocado, 'calcularVenta debe re-evaluarse al des-invitar');
    }

    public function test_confirmar_invitar_todo_marca_todos_los_items_y_calcula_total_invitado(): void
    {
        $host = $this->host();
        $host->items[] = $this->item(['precio' => 100.0, 'cantidad' => 2]);
        $host->items[] = $this->item(['precio' => 50.0, 'cantidad' => 1]);
        $host->items[] = $this->item(['precio' => 200.0, 'cantidad' => 1]);

        $host->motivoInvitacionTotal = 'Evento de prensa';
        $host->confirmarInvitarTodo();

        foreach ($host->items as $i => $item) {
            $this->assertTrue($item['es_invitacion'], "Item $i debe quedar invitado");
            $this->assertSame(0.0, (float) $item['precio']);
            $this->assertSame('Evento de prensa', $item['invitacion_motivo']);
        }

        $this->assertEquals(
            450.0,
            $host->totalInvitado,
            'totalInvitado = 100*2 + 50*1 + 200*1 = 450'
        );
        $this->assertTrue($host->getEsInvitacionTotalProperty(), 'esInvitacionTotal debe ser true');
    }

    public function test_es_invitacion_total_es_false_si_algun_item_no_esta_invitado(): void
    {
        $host = $this->host();
        $host->items[] = $this->item(['precio' => 100.0]);
        $host->items[] = $this->item(['precio' => 50.0]);

        $host->invitarItemIndex = 0;
        $host->invitarItemMotivo = 'Cortesia';
        $host->confirmarInvitarItem();

        $this->assertTrue($host->items[0]['es_invitacion']);
        $this->assertFalse($host->items[1]['es_invitacion']);
        $this->assertFalse(
            $host->getEsInvitacionTotalProperty(),
            'esInvitacionTotal debe ser false si hay items NO invitados'
        );

        // monto_invitado parcial = 100 * 2 (default cantidad del item helper)
        $this->assertEquals(200.0, $host->totalInvitado);
    }

    public function test_toggle_invitar_todo_sin_permiso_lo_deja_en_false(): void
    {
        $host = $this->host(puedePedido: false);

        $host->toggleInvitarTodo();

        $this->assertFalse($host->invitarTodo);
        $errores = array_filter($host->eventos, fn ($e) => $e['event'] === 'toast-error');
        $this->assertNotEmpty($errores);
    }
}

/**
 * Host stub: compone el trait y simula las dependencias minimas que el trait
 * necesita del componente Livewire (items, calcularVenta, dispatch). Override
 * los hooks de permisos para que el test los controle via flags.
 */
class _HostInvitacionesStub
{
    use WithInvitaciones;

    public array $items = [];

    public array $eventos = [];

    public bool $calcularVentaInvocado = false;

    public bool $permisoPedido = true;

    public bool $permisoRenglon = true;

    public function calcularVenta(): void
    {
        $this->calcularVentaInvocado = true;
    }

    public function dispatch(string $event, ...$args): void
    {
        $this->eventos[] = ['event' => $event, 'args' => $args];
    }

    protected function puedeInvitarPedido(): bool
    {
        return $this->permisoPedido;
    }

    protected function puedeInvitarRenglon(): bool
    {
        return $this->permisoRenglon;
    }
}
