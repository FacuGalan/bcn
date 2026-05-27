<?php

namespace App\Services\IntegracionesPago;

use App\Models\IntegracionPagoSucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRUD de configuraciones de integraciones de pago por sucursal.
 *
 * Los hooks `saved`/`updating`/`deleted` del modelo se encargan de mantener
 * sincronizado el índice global (`mercadopago_collector_index`) en DB config.
 * Este service centraliza las operaciones envueltas en transacción + logging
 * y deja un único punto de entrada que pueden consumir Livewire, API o CLI.
 */
class IntegracionPagoSucursalService
{
    /**
     * Crea una configuración de integración para una sucursal.
     *
     * @param  array  $data  fillable del modelo (integracion_pago_id, sucursal_id, modo, tokens, ...)
     */
    public static function crear(array $data): IntegracionPagoSucursal
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($data) {
            $config = IntegracionPagoSucursal::create($data);

            Log::info('IntegracionPagoSucursal creada', [
                'id' => $config->id,
                'integracion_pago_id' => $config->integracion_pago_id,
                'sucursal_id' => $config->sucursal_id,
                'modo' => $config->modo,
            ]);

            return $config;
        });
    }

    /**
     * Actualiza una configuración existente.
     */
    public static function actualizar(IntegracionPagoSucursal $config, array $data): IntegracionPagoSucursal
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($config, $data) {
            $config->update($data);

            Log::info('IntegracionPagoSucursal actualizada', [
                'id' => $config->id,
                'cambios' => array_keys($config->getChanges()),
            ]);

            return $config->refresh();
        });
    }

    /**
     * Elimina la configuración. El hook `deleted` del modelo limpia el índice.
     */
    public static function eliminar(IntegracionPagoSucursal $config): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($config) {
            $id = $config->id;
            $sucursalId = $config->sucursal_id;
            $integracionId = $config->integracion_pago_id;

            $config->delete();

            Log::info('IntegracionPagoSucursal eliminada', [
                'id' => $id,
                'integracion_pago_id' => $integracionId,
                'sucursal_id' => $sucursalId,
            ]);
        });
    }

    /**
     * Fuerza una sincronización del índice global a partir del estado actual del modelo.
     *
     * Útil para reparar inconsistencias o re-poblar el índice tras un import manual.
     */
    public static function sincronizarIndice(IntegracionPagoSucursal $config): void
    {
        $config->sincronizarIndiceColector();

        Log::info('IntegracionPagoSucursal índice resincronizado', [
            'id' => $config->id,
            'user_id_externo' => $config->user_id_externo,
            'modo' => $config->modo,
        ]);
    }
}
