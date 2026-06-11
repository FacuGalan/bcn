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
        self::validarUserIdNoUsadoPorOtroComercio(
            $data['user_id_externo'] ?? null,
            $data['modo'] ?? null,
        );

        $config = DB::connection('pymes_tenant')->transaction(function () use ($data) {
            $config = IntegracionPagoSucursal::create($data);

            Log::info('IntegracionPagoSucursal creada', [
                'id' => $config->id,
                'integracion_pago_id' => $config->integracion_pago_id,
                'sucursal_id' => $config->sucursal_id,
                'modo' => $config->modo,
            ]);

            return $config;
        });

        self::autoVincularCuentaEmpresa($config);

        return $config;
    }

    /**
     * Actualiza una configuración existente.
     */
    public static function actualizar(IntegracionPagoSucursal $config, array $data): IntegracionPagoSucursal
    {
        self::validarUserIdNoUsadoPorOtroComercio(
            $data['user_id_externo'] ?? $config->user_id_externo,
            $data['modo'] ?? $config->modo,
        );

        $config = DB::connection('pymes_tenant')->transaction(function () use ($config, $data) {
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

        self::autoVincularCuentaEmpresa($config);

        return $config;
    }

    /**
     * Auto-vincula la CuentaEmpresa de la cuenta real del proveedor (RF-02).
     * Solo aplica en producción; en test es no-op. Nunca rompe el guardado de
     * credenciales: cualquier error queda en log.
     */
    private static function autoVincularCuentaEmpresa(IntegracionPagoSucursal $config): void
    {
        try {
            \App\Services\CuentaEmpresaService::findOrCreateParaIntegracion($config);
        } catch (\Throwable $e) {
            Log::warning('No se pudo auto-vincular la CuentaEmpresa de la integración', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rechaza configurar una cuenta MP (user_id_externo + modo) que ya está en
     * uso por OTRO COMERCIO. El webhook resuelve el comercio por el `user_id` MP
     * vía `mercadopago_collector_index`, único por (user_id_externo, modo): si
     * dos COMERCIOS distintos compartieran la misma cuenta, la última config
     * guardada pisaría el ruteo y desviaría las confirmaciones al comercio
     * equivocado (cruce de datos financieros entre clientes). Eso se bloquea.
     *
     * En cambio, varias SUCURSALES del MISMO comercio SÍ pueden compartir una
     * cuenta MP (una app por sucursal, varios Stores/POS bajo la misma cuenta):
     * el ruteo del webhook es a nivel comercio y la transacción se resuelve
     * después por su `external_id`, así que no hay ambigüedad. MP soporta este
     * modelo y el sistema debe aprovecharlo.
     *
     * @throws \RuntimeException si la cuenta ya está tomada por OTRO comercio
     */
    private static function validarUserIdNoUsadoPorOtroComercio(
        ?string $userIdExterno,
        ?string $modo,
    ): void {
        if (empty($userIdExterno) || empty($modo)) {
            return;
        }

        $comercioActualId = app(\App\Services\TenantService::class)->getComercioId();

        $existente = DB::connection('config')
            ->table('mercadopago_collector_index')
            ->where('user_id_externo', $userIdExterno)
            ->where('modo', $modo)
            ->first();

        if ($existente && (int) $existente->comercio_id !== (int) $comercioActualId) {
            throw new \RuntimeException(__('Esta cuenta de Mercado Pago (user_id :user) ya está en uso por otro comercio para el modo :modo. Usá una cuenta de Mercado Pago distinta.', [
                'user' => $userIdExterno,
                'modo' => $modo,
            ]));
        }
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
