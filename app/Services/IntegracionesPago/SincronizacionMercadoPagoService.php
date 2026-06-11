<?php

namespace App\Services\IntegracionesPago;

use App\Models\Caja;
use App\Models\IntegracionPagoSucursal;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la sincronización de Sucursales→Stores y Cajas→POS de
 * Mercado Pago. Decide si crear o actualizar según el estado local.
 *
 * No conoce HTTP: delega al `MercadoPagoGateway`. Persiste los IDs/URLs
 * devueltos por MP en las tablas locales dentro de una transacción tenant.
 */
class SincronizacionMercadoPagoService
{
    /**
     * Sincroniza una sucursal con MP. Si ya tiene `mp_store_id`, actualiza;
     * si no, crea. Persiste los IDs en la tabla.
     */
    public static function sincronizarSucursal(
        IntegracionPagoSucursal $config,
        Sucursal $sucursal,
        int $comercioId
    ): Sucursal {
        $gateway = $config->integracion->getGatewayInstance();
        if (! $gateway instanceof MercadoPagoGateway) {
            throw new \RuntimeException('El gateway de la integración no es Mercado Pago');
        }

        $response = $sucursal->estaSincronizadaEnMp()
            ? $gateway->actualizarStore($config, $sucursal, $comercioId)
            : $gateway->crearStore($config, $sucursal, $comercioId);

        return DB::connection('pymes_tenant')->transaction(function () use ($sucursal, $response, $comercioId) {
            $sucursal->update([
                'mp_store_id' => (string) ($response['id'] ?? $sucursal->mp_store_id),
                'mp_store_external_id' => $response['external_id']
                    ?? $sucursal->mp_store_external_id
                    ?? MercadoPagoGateway::externalIdStore($comercioId, $sucursal->id),
            ]);

            Log::info('SincronizacionMercadoPagoService::sincronizarSucursal OK', [
                'sucursal_id' => $sucursal->id,
                'mp_store_id' => $sucursal->mp_store_id,
            ]);

            return $sucursal->refresh();
        });
    }

    /**
     * Sincroniza una caja (POS) con MP. Requiere que la sucursal ya esté
     * sincronizada.
     */
    public static function sincronizarCaja(
        IntegracionPagoSucursal $config,
        Caja $caja,
        Sucursal $sucursal,
        ?string $rubro,
        int $comercioId
    ): Caja {
        $gateway = $config->integracion->getGatewayInstance();
        if (! $gateway instanceof MercadoPagoGateway) {
            throw new \RuntimeException('El gateway de la integración no es Mercado Pago');
        }

        $response = $caja->estaSincronizadaEnMp()
            ? $gateway->actualizarPos($config, $caja, $sucursal, $rubro, $comercioId)
            : $gateway->crearPos($config, $caja, $sucursal, $rubro, $comercioId);

        return DB::connection('pymes_tenant')->transaction(function () use ($caja, $response, $comercioId) {
            $caja->update([
                'mp_pos_id' => (string) ($response['id'] ?? $caja->mp_pos_id),
                'mp_pos_external_id' => $response['external_id']
                    ?? $caja->mp_pos_external_id
                    ?? MercadoPagoGateway::externalIdPos($comercioId, $caja->id),
                'mp_pos_qr_url' => $response['qr']['image'] ?? $caja->mp_pos_qr_url,
                'mp_pos_qr_pdf_url' => $response['qr']['template_document'] ?? $caja->mp_pos_qr_pdf_url,
            ]);

            Log::info('SincronizacionMercadoPagoService::sincronizarCaja OK', [
                'caja_id' => $caja->id,
                'mp_pos_id' => $caja->mp_pos_id,
            ]);

            return $caja->refresh();
        });
    }

    /**
     * Lista las terminales Point de la cuenta MP de esta config (para elegir
     * cuál asignar a cada caja en la UI). Point NO usa stores/POS: trabaja con
     * vinculación de devices.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarTerminales(IntegracionPagoSucursal $config): array
    {
        $gateway = $config->integracion->getGatewayInstance();
        if (! $gateway instanceof MercadoPagoGateway) {
            throw new \RuntimeException('El gateway de la integración no es Mercado Pago');
        }

        return $gateway->listarTerminales($config);
    }

    /**
     * Versión legible de un terminal_id de Point para mostrar en UI. MP arma el
     * id como `{MARCA}_{MODELO}__{SERIE}` y la serie suele repetir el modelo:
     * "NEWLAND_N950__N950NCD200152797" → "N950 · SC:NCD200152797".
     */
    public static function formatearTerminal(?string $terminalId): string
    {
        if (! $terminalId || ! str_contains($terminalId, '__')) {
            return (string) $terminalId;
        }

        [$marcaModelo, $serie] = explode('__', $terminalId, 2);
        $modelo = str_contains($marcaModelo, '_')
            ? substr($marcaModelo, strrpos($marcaModelo, '_') + 1)
            : $marcaModelo;

        if ($modelo !== '' && str_starts_with($serie, $modelo)) {
            $serie = substr($serie, strlen($modelo));
        }

        return $serie !== '' ? $modelo.' · SC:'.$serie : $modelo;
    }

    /**
     * Vincula una terminal Point a una caja: la pone en modo integrado (PDV) en
     * MP y persiste el `terminal_id` en la caja. A partir de ahí el sistema le
     * empuja cobros con `type:"point"`.
     */
    public static function vincularTerminalCaja(
        IntegracionPagoSucursal $config,
        Caja $caja,
        string $terminalId
    ): Caja {
        $gateway = $config->integracion->getGatewayInstance();
        if (! $gateway instanceof MercadoPagoGateway) {
            throw new \RuntimeException('El gateway de la integración no es Mercado Pago');
        }

        $gateway->activarModoPDV($config, $terminalId);

        return DB::connection('pymes_tenant')->transaction(function () use ($caja, $terminalId) {
            $caja->update(['mp_point_terminal_id' => $terminalId]);

            Log::info('SincronizacionMercadoPagoService::vincularTerminalCaja OK', [
                'caja_id' => $caja->id,
                'terminal_id' => $terminalId,
            ]);

            return $caja->refresh();
        });
    }

    /**
     * Quita la terminal Point asignada a una caja (no toca el modo del device
     * en MP; solo limpia la asociación local).
     */
    public static function desvincularTerminalCaja(Caja $caja): Caja
    {
        $terminalId = $caja->mp_point_terminal_id;

        return DB::connection('pymes_tenant')->transaction(function () use ($caja, $terminalId) {
            $caja->update(['mp_point_terminal_id' => null]);

            Log::info('SincronizacionMercadoPagoService::desvincularTerminalCaja OK', [
                'caja_id' => $caja->id,
                'terminal_id' => $terminalId,
            ]);

            return $caja->refresh();
        });
    }
}
