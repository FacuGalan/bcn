<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait para tests que necesitan sucursal activa.
 *
 * Requiere: WithTenant (debe estar configurado antes).
 *
 * Uso:
 *   use WithTenant, WithSucursal;
 *   $this->setUpTenant();
 *   $this->setUpSucursal();
 *   // $this->sucursalId está disponible
 */
trait WithSucursal
{
    protected int $sucursalId;

    /**
     * Crea una sucursal de prueba y la setea como activa.
     *
     * La sucursal arranca con `imprime_comanda_automatico=false` (opt-out del
     * default productivo) para que los tests no se vean afectados por la
     * transición automática a EN_PREPARACION al confirmar pedidos. Tests que
     * necesiten probar ese flujo deben hacer opt-in modificando el flag.
     */
    protected function setUpSucursal(string $nombre = 'Sucursal Test'): void
    {
        // Usar pymes_tenant (prefijo automático, dentro de la transacción)
        $this->sucursalId = DB::connection('pymes_tenant')->table('sucursales')->insertGetId([
            'nombre' => $nombre,
            'codigo' => 'SUC-TEST-'.uniqid(),
            'direccion' => 'Dirección Test 123',
            'activa' => true,
            'imprime_comanda_automatico' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simular sesión con sucursal activa
        session(['sucursal_id' => $this->sucursalId]);
    }

    /**
     * Crea sucursales adicionales para tests multi-sucursal.
     */
    protected function crearSucursalAdicional(string $nombre = 'Sucursal Extra'): int
    {
        return DB::connection('pymes_tenant')->table('sucursales')->insertGetId([
            'nombre' => $nombre,
            'codigo' => 'SUC-EXTRA-'.uniqid(),
            'direccion' => 'Dirección Extra',
            'activa' => true,
            'imprime_comanda_automatico' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
