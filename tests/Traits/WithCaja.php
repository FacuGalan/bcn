<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait para tests que necesitan caja activa.
 *
 * Requiere: WithTenant + WithSucursal (deben estar configurados antes).
 *
 * Uso:
 *   use WithTenant, WithSucursal, WithCaja;
 *   $this->setUpTenant();
 *   $this->setUpSucursal();
 *   $this->setUpCaja();
 *   // $this->cajaId está disponible
 */
trait WithCaja
{
    protected int $cajaId;

    /**
     * Crea una caja de prueba y la setea como activa.
     */
    protected function setUpCaja(string $nombre = 'Caja Test'): void
    {
        // Usar pymes_tenant (prefijo automático, dentro de la transacción)
        $this->cajaId = DB::connection('pymes_tenant')->table('cajas')->insertGetId([
            'nombre' => $nombre,
            'codigo' => 'CAJA-TEST-'.uniqid(),
            'numero' => 1,
            'sucursal_id' => $this->sucursalId,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simular sesión con caja activa
        session(['caja_id' => $this->cajaId]);
    }
}
