<?php

namespace App\Services;

use App\Models\PantallaPublicaToken;
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
    public function __construct(private TenantService $tenant) {}

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
}
