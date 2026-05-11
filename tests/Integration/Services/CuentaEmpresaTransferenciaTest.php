<?php

namespace Tests\Integration\Services;

use App\Models\CuentaEmpresa;
use App\Models\Moneda;
use App\Models\MovimientoCuentaEmpresa;
use App\Models\TransferenciaCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithTenant;

/**
 * PR N del Repaso 3: cobertura de transferencias entre cuentas empresa.
 *
 * La validación mismo-moneda ya existe en CuentaEmpresaService::transferirEntreCuentas
 * (sin conversión USD↔ARS implícita). Este test asegura que esa defensa no se rompa
 * por refactors futuros, junto con los otros guards (monto > 0, origen != destino).
 */
class CuentaEmpresaTransferenciaTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        DB::connection('pymes_tenant')->table('transferencias_cuenta_empresa')->delete();
        DB::connection('pymes_tenant')->table('movimientos_cuenta_empresa')->delete();
        DB::connection('pymes_tenant')->table('cuentas_empresa')->delete();

        $this->tearDownTenant();
        parent::tearDown();
    }

    private function ars(): Moneda
    {
        return Moneda::firstOrCreate(
            ['codigo' => 'ARS'],
            ['nombre' => 'Peso Argentino', 'simbolo' => '$', 'es_principal' => true, 'activo' => true]
        );
    }

    private function usd(): Moneda
    {
        return Moneda::firstOrCreate(
            ['codigo' => 'USD'],
            ['nombre' => 'Dolar', 'simbolo' => 'US$', 'es_principal' => false, 'activo' => true]
        );
    }

    private function crearCuenta(int $monedaId, string $nombre, float $saldo = 1000.0): CuentaEmpresa
    {
        return CuentaEmpresa::create([
            'nombre' => $nombre,
            'tipo' => CuentaEmpresa::TIPO_BANCO,
            'subtipo' => 'cuenta_corriente',
            'moneda_id' => $monedaId,
            'saldo_actual' => $saldo,
            'activo' => true,
            'orden' => 0,
        ]);
    }

    public function test_transferencia_misma_moneda_actualiza_saldos_y_persiste_transferencia(): void
    {
        $ars = $this->ars();
        $origen = $this->crearCuenta($ars->id, 'Origen ARS', 1000);
        $destino = $this->crearCuenta($ars->id, 'Destino ARS', 500);

        $transferencia = CuentaEmpresaService::transferirEntreCuentas(
            $origen->id,
            $destino->id,
            300,
            'Test transferencia ok',
            1,
        );

        $this->assertInstanceOf(TransferenciaCuentaEmpresa::class, $transferencia);
        $this->assertEquals($ars->id, $transferencia->moneda_id);
        $this->assertEquals('300.00', $transferencia->monto);

        $origen->refresh();
        $destino->refresh();
        $this->assertEquals('700.00', $origen->saldo_actual);
        $this->assertEquals('800.00', $destino->saldo_actual);

        $movs = MovimientoCuentaEmpresa::orderBy('id')->get();
        $this->assertCount(2, $movs);
        $this->assertEquals('egreso', $movs[0]->tipo);
        $this->assertEquals('ingreso', $movs[1]->tipo);
        $this->assertEquals($transferencia->id, $movs[0]->origen_id);
        $this->assertEquals($transferencia->id, $movs[1]->origen_id);
    }

    public function test_transferencia_distinta_moneda_aborta_con_excepcion(): void
    {
        $ars = $this->ars();
        $usd = $this->usd();
        $origen = $this->crearCuenta($ars->id, 'Origen ARS', 1000);
        $destino = $this->crearCuenta($usd->id, 'Destino USD', 0);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('misma moneda');

        CuentaEmpresaService::transferirEntreCuentas($origen->id, $destino->id, 100, 'X', 1);
    }

    public function test_transferencia_distinta_moneda_no_deja_efectos_colaterales(): void
    {
        $ars = $this->ars();
        $usd = $this->usd();
        $origen = $this->crearCuenta($ars->id, 'Origen ARS', 1000);
        $destino = $this->crearCuenta($usd->id, 'Destino USD', 500);

        try {
            CuentaEmpresaService::transferirEntreCuentas($origen->id, $destino->id, 100, 'X', 1);
            $this->fail('Debio lanzar excepcion');
        } catch (Exception $e) {
            // esperado
        }

        $origen->refresh();
        $destino->refresh();
        $this->assertEquals('1000.00', $origen->saldo_actual);
        $this->assertEquals('500.00', $destino->saldo_actual);
        $this->assertEquals(0, TransferenciaCuentaEmpresa::count());
        $this->assertEquals(0, MovimientoCuentaEmpresa::count());
    }

    public function test_transferencia_monto_cero_aborta(): void
    {
        $ars = $this->ars();
        $origen = $this->crearCuenta($ars->id, 'O', 1000);
        $destino = $this->crearCuenta($ars->id, 'D', 0);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mayor a cero');

        CuentaEmpresaService::transferirEntreCuentas($origen->id, $destino->id, 0, 'X', 1);
    }

    public function test_transferencia_origen_igual_destino_aborta(): void
    {
        $ars = $this->ars();
        $cuenta = $this->crearCuenta($ars->id, 'C', 1000);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('diferentes');

        CuentaEmpresaService::transferirEntreCuentas($cuenta->id, $cuenta->id, 100, 'X', 1);
    }
}
