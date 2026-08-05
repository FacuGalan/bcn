<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regresión del bug "Too Many Attempts" al confirmar pedido (2026-08-05):
 * los throttle inline SIN prefijo comparten bucket por sha1(user|ip), así
 * que el contador del grupo público (60/min) pisaba el del alta de pedido
 * (15/min) — navegar el catálogo/cotizar agotaba el límite de confirmar.
 * Todo throttle de la API pública de tienda debe llevar prefijo propio.
 */
class ThrottlePrefijosTest extends TestCase
{
    public function test_throttles_de_la_api_publica_de_tienda_llevan_prefijo_propio(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'api.v1.tienda.'));

        $this->assertNotEmpty($rutas);

        foreach ($rutas as $ruta) {
            $throttles = array_filter($ruta->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
            foreach ($throttles as $throttle) {
                $this->assertCount(3, explode(',', $throttle), "Ruta {$ruta->getName()}: '{$throttle}' sin prefijo (comparte bucket con el resto)");
            }
        }
    }

    public function test_alta_cancelacion_y_vinculo_tienen_su_propio_contador(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($r) => (string) $r->getName());

        $this->assertContains('throttle:15,1,t-alta', $rutas['api.v1.tienda.pedidos.store']->gatherMiddleware());
        $this->assertContains('throttle:10,1,t-cancelar', $rutas['api.v1.tienda.pedidos.cancelar']->gatherMiddleware());
        $this->assertContains('throttle:10,1,t-vincular', $rutas['api.v1.tienda.pedidos.vincular']->gatherMiddleware());
    }
}
