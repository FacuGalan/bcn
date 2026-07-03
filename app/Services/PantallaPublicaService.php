<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\PantallaPublicaToken;
use App\Models\PedidoMostrador;
use App\Models\PromocionEspecial;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de las pantallas públicas Clase B (llamador de pedidos, consultor de
 * precios). Resuelve el tenant SIN sesión a partir del token de la URL usando el
 * índice global `pantalla_publica_tokens` (config) y configura la conexión
 * tenant con TenantService::usarComercioParaProceso().
 *
 * Fase 1: resolución por token, canje de código corto y regeneración.
 * Los métodos de datos (pedidosParaLlamador, buscarPreciosPublico) se agregan en
 * las fases 2 y 3.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (Servicios).
 */
class PantallaPublicaService
{
    public function __construct(
        private TenantService $tenant,
        private PrecioService $precios,
    ) {}

    /**
     * Resuelve un token largo: busca en el índice global, configura el tenant y
     * devuelve la sucursal + comercio. Null si el token no existe o la sucursal
     * fue borrada.
     *
     * @return array{comercio: \App\Models\Comercio, sucursal: Sucursal, index: PantallaPublicaToken}|null
     */
    public function resolverPorToken(string $token): ?array
    {
        $index = PantallaPublicaToken::query()->where('token', $token)->first();

        if (! $index) {
            return null;
        }

        try {
            $comercio = $this->tenant->usarComercioParaProceso($index->comercio_id);
        } catch (\Throwable $e) {
            Log::warning('PantallaPublica: comercio del token no existe', [
                'comercio_id' => $index->comercio_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $sucursal = Sucursal::find($index->sucursal_id);

        if (! $sucursal) {
            return null;
        }

        return ['comercio' => $comercio, 'sucursal' => $sucursal, 'index' => $index];
    }

    /**
     * Canjea un código corto (tipeado en una TV) por el token largo, para que el
     * dispositivo lo guarde en localStorage. Devuelve null si el código no existe.
     * El llamador debe aplicar rate limiting (anti fuerza bruta).
     */
    public function canjearCodigoCorto(string $codigo): ?string
    {
        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            return null;
        }

        return PantallaPublicaToken::query()
            ->where('codigo_corto', $codigo)
            ->value('token');
    }

    /**
     * Regenera token + código corto de una sucursal (rotación). Requiere el
     * tenant ya configurado (se llama desde Configuración con sesión). Actualiza
     * el índice global y la columna tenant, e invalida los dispositivos viejos.
     *
     * @return array{token: string, codigo_corto: string}
     */
    public function regenerarToken(Sucursal $sucursal): array
    {
        $comercio = $this->tenant->getComercio();

        if (! $comercio) {
            throw new \RuntimeException('No hay comercio activo para regenerar el token de la pantalla pública.');
        }

        $token = PantallaPublicaToken::generarTokenUnico();
        $codigo = PantallaPublicaToken::generarCodigoUnico();

        PantallaPublicaToken::updateOrCreate(
            ['comercio_id' => $comercio->id, 'sucursal_id' => $sucursal->id],
            ['token' => $token, 'codigo_corto' => $codigo],
        );

        $sucursal->update(['token_publico' => $token]);

        return ['token' => $token, 'codigo_corto' => $codigo];
    }

    /**
     * Garantiza que la sucursal tenga token + código en el índice global. Si no
     * los tiene (sucursal creada antes del feature o índice desincronizado), los
     * genera. Devuelve el registro del índice.
     */
    public function asegurarToken(Sucursal $sucursal): PantallaPublicaToken
    {
        $comercio = $this->tenant->getComercio();

        if (! $comercio) {
            throw new \RuntimeException('No hay comercio activo para asegurar el token de la pantalla pública.');
        }

        $index = PantallaPublicaToken::query()
            ->where('comercio_id', $comercio->id)
            ->where('sucursal_id', $sucursal->id)
            ->first();

        if ($index) {
            // Sincronizar la columna tenant si quedó vacía.
            if ($sucursal->token_publico !== $index->token) {
                $sucursal->update(['token_publico' => $index->token]);
            }

            return $index;
        }

        $token = PantallaPublicaToken::generarTokenUnico();
        $codigo = PantallaPublicaToken::generarCodigoUnico();

        $index = PantallaPublicaToken::create([
            'token' => $token,
            'codigo_corto' => $codigo,
            'comercio_id' => $comercio->id,
            'sucursal_id' => $sucursal->id,
        ]);

        $sucursal->update(['token_publico' => $token]);

        return $index;
    }

    /**
     * Snapshot de pedidos para el cold start del monitor llamador: las dos
     * columnas (en preparación / listo) con payload mínimo {numero, nombre}.
     * "En preparación" ordenada por número ascendente (FIFO); "Listo" por número
     * descendente (el último llamado, arriba).
     *
     * @return array{en_preparacion: list<array{numero:int, nombre:?string}>, listo: list<array{numero:int, nombre:?string}>}
     */
    public function pedidosParaLlamador(Sucursal $sucursal): array
    {
        $mapear = static fn ($p): array => [
            'numero' => (int) $p->numero_visible,
            'nombre' => $p->nombreLlamador(),
        ];

        // Los TAKE-AWAY de delivery comparten numero_display con mostrador
        // (contador único por sucursal) y se anuncian en el mismo llamador
        // (RF-03). Los delivery puros NO: los retira el repartidor.
        $takeAway = fn (string $estado) => \App\Models\PedidoDelivery::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('tipo', \App\Models\PedidoDelivery::TIPO_TAKE_AWAY)
            ->where('estado_pedido', $estado)
            ->with('cliente:id,nombre')
            ->get();

        $enPreparacion = PedidoMostrador::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('estado_pedido', PedidoMostrador::ESTADO_EN_PREPARACION)
            ->with('cliente:id,nombre')
            ->get()
            ->concat($takeAway(\App\Models\PedidoDelivery::ESTADO_EN_PREPARACION))
            ->sortBy(fn ($p) => (int) $p->numero_visible)
            ->values();

        $listo = PedidoMostrador::query()
            ->where('sucursal_id', $sucursal->id)
            ->where('estado_pedido', PedidoMostrador::ESTADO_LISTO)
            ->with('cliente:id,nombre')
            ->get()
            ->concat($takeAway(\App\Models\PedidoDelivery::ESTADO_LISTO))
            ->sortByDesc(fn ($p) => (int) $p->numero_visible)
            ->values();

        return [
            'en_preparacion' => $enPreparacion->map($mapear)->all(),
            'listo' => $listo->map($mapear)->all(),
        ];
    }

    /**
     * Búsqueda pública del consultor de precios: artículos ACTIVOS en la sucursal
     * que matcheen por nombre, código o código de barras. Devuelve el precio de
     * lista base (vía PrecioService) + los NOMBRES de las promociones vigentes en
     * las que participa (sin calcular el precio promocional). Payload mínimo: NO
     * expone costo, margen, stock ni listas internas.
     *
     * @return list<array{nombre:string, unidad:?string, precio:?float, promos:list<string>}>
     */
    public function buscarPreciosPublico(Sucursal $sucursal, string $q, int $limite = 20): array
    {
        $q = trim($q);

        if (mb_strlen($q) < 2) {
            return [];
        }

        $articulos = Articulo::query()
            ->activos()
            ->whereHas('sucursales', function ($s) use ($sucursal) {
                $s->where('sucursal_id', $sucursal->id)->where('articulos_sucursales.activo', true);
            })
            ->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', $q)
                    ->orWhere('codigo_barras', $q);
            })
            ->orderBy('nombre')
            ->limit($limite)
            ->get(['id', 'nombre', 'unidad_medida']);

        return $articulos->map(function (Articulo $a) use ($sucursal) {
            $precio = $this->precios->obtenerPrecioBase($a->id, $sucursal->id);

            return [
                'nombre' => $a->nombre,
                'unidad' => $a->unidad_medida,
                'precio' => $precio ? round((float) $precio['precio'], 2) : null,
                'promos' => $this->promosDelArticulo($a, $sucursal->id),
            ];
        })->all();
    }

    /**
     * Nombres de TODAS las promociones vigentes en las que participa el artículo,
     * de los DOS sistemas del proyecto:
     *  - Promociones normales (descuentos, escalas) → Articulo::obtenerPromocionesActivas.
     *  - Promociones especiales (NxM, combos, menús) → participación por artículo,
     *    categoría (ambos como campo simple o lista json) o por estar en un grupo
     *    trigger/reward del combo.
     *
     * Solo nombres, sin calcular el beneficio (es info para el cliente).
     *
     * @return list<string>
     */
    private function promosDelArticulo(Articulo $articulo, int $sucursalId): array
    {
        $normales = $articulo->obtenerPromocionesActivas($sucursalId)->pluck('nombre');

        $especiales = PromocionEspecial::query()
            ->where('sucursal_id', $sucursalId)
            ->activas()
            ->vigentes()
            ->where(function ($q) use ($articulo) {
                $q->where('nxm_articulo_id', $articulo->id)
                    ->orWhereJsonContains('nxm_articulos_ids', $articulo->id)
                    ->orWhereJsonContains('nxm_articulos_ids', (string) $articulo->id)
                    ->orWhereHas('grupos.articulos', fn ($g) => $g->where('articulo_id', $articulo->id));

                if ($articulo->categoria_id) {
                    $q->orWhere('nxm_categoria_id', $articulo->categoria_id)
                        ->orWhereJsonContains('nxm_categorias_ids', $articulo->categoria_id)
                        ->orWhereJsonContains('nxm_categorias_ids', (string) $articulo->categoria_id);
                }
            })
            ->pluck('nombre');

        return $normales->merge($especiales)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
