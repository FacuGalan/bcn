<?php

namespace App\Services\IntegracionesPago;

use App\Models\Caja;
use App\Models\IntegracionPagoSucursal;
use App\Models\Sucursal;
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
            $config->fill($data);

            // Si cambia el modo (test↔producción) o la cuenta MP (user_id_externo),
            // los Store/POS creados en la cuenta anterior dejan de existir en la
            // nueva. Se limpian los IDs/QR locales para que la próxima
            // sincronización los CREE de nuevo en lugar de intentar actualizarlos.
            $cuentaMpCambio = $config->isDirty('modo') || $config->isDirty('user_id_externo');

            $config->save();

            if ($cuentaMpCambio) {
                self::limpiarSincronizacionMp($config->sucursal_id);
            }

            Log::info('IntegracionPagoSucursal actualizada', [
                'id' => $config->id,
                'cambios' => array_keys($config->getChanges()),
                'cuenta_mp_cambio' => $cuentaMpCambio,
            ]);

            return $config->refresh();
        });
    }

    /**
     * Borra los IDs de Store/POS y URLs de QR de una sucursal y todas sus cajas.
     *
     * Se invoca al cambiar de cuenta MP (modo o user_id_externo): los recursos
     * de la cuenta vieja no existen en la nueva, así que el sync debe recrearlos.
     */
    private static function limpiarSincronizacionMp(int $sucursalId): void
    {
        Sucursal::where('id', $sucursalId)->update([
            'mp_store_id' => null,
            'mp_store_external_id' => null,
        ]);

        Caja::where('sucursal_id', $sucursalId)->update([
            'mp_pos_id' => null,
            'mp_pos_external_id' => null,
            'mp_pos_qr_url' => null,
            'mp_pos_qr_pdf_url' => null,
        ]);
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
