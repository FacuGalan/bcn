<?php

namespace Tests\Unit\Services;

use App\Models\ComprobanteFiscal;
use App\Models\ComprobanteFiscalIva;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Services\ARCA\ComprobanteFiscalService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * RF-V7 (hardening fiscal saliente, tanda 2): el registro del ledger fiscal
 * (registrarFiscal) se difiere al commit REAL de la conexión. Cuando la emisión
 * corre dentro de la transacción del cobro (NuevaVenta), el commit interno del
 * service es solo un savepoint: el "post-commit" corría con la transacción
 * externa abierta y un rollback posterior del cobro descartaba el comprobante
 * dejando hecho el intento de ledger. Con afterCommit: sin transacción externa
 * corre inmediato; con ella, corre tras el commit y se descarta en rollback.
 */
class ComprobanteFiscalLedgerAfterCommitTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // Impuesto iva_debito (lo consume registrarDesdeComprobante).
        Impuesto::firstOrCreate(
            ['codigo' => 'iva_debito'],
            [
                'nombre' => 'IVA Débito Fiscal',
                'tipo' => Impuesto::TIPO_IVA,
                'naturaleza_default' => 'debito_fiscal',
                'jurisdiccion' => 'AR',
                'es_sistema' => true,
                'activo' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /** Comprobante en memoria de un emisor RI con una alícuota 21% (no persiste). */
    private function comprobanteRI(int $id): ComprobanteFiscal
    {
        $condicionRI = CondicionIva::firstOrCreate(
            ['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO],
            ['nombre' => 'Responsable Inscripto']
        );

        $cuit = Cuit::create([
            'numero_cuit' => (string) random_int(20000000000, 29999999999),
            'razon_social' => 'Emisor RI',
            'condicion_iva_id' => $condicionRI->id,
            'entorno_afip' => 'testing',
            'activo' => true,
        ]);

        $c = new ComprobanteFiscal([
            'cuit_id' => $cuit->id,
            'sucursal_id' => $this->sucursalId,
            'fecha_emision' => now()->toDateString(),
            'usuario_id' => 1,
        ]);
        $c->id = $id;
        $c->setRelation('detallesIva', collect([
            new ComprobanteFiscalIva(['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]),
        ]));

        return $c;
    }

    private function registrarFiscal(ComprobanteFiscal $c): void
    {
        $ref = new ReflectionMethod(ComprobanteFiscalService::class, 'registrarFiscal');
        $ref->setAccessible(true);
        $ref->invoke(new ComprobanteFiscalService, $c);
    }

    private function movimientosDe(int $comprobanteId): int
    {
        return MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')
            ->where('origen_id', $comprobanteId)
            ->count();
    }

    public function test_sin_transaccion_externa_registra_inmediato(): void
    {
        $this->registrarFiscal($this->comprobanteRI(9101));

        $this->assertSame(1, $this->movimientosDe(9101));
    }

    public function test_dentro_de_transaccion_difiere_hasta_el_commit_real(): void
    {
        $c = $this->comprobanteRI(9102);

        DB::connection('pymes_tenant')->beginTransaction();
        $this->registrarFiscal($c);

        // Con la transacción del cobro abierta el ledger todavía NO se registró.
        $this->assertSame(0, $this->movimientosDe(9102));

        DB::connection('pymes_tenant')->commit();

        $this->assertSame(1, $this->movimientosDe(9102));
    }

    public function test_rollback_de_la_transaccion_externa_descarta_el_registro(): void
    {
        $c = $this->comprobanteRI(9103);

        DB::connection('pymes_tenant')->beginTransaction();
        $this->registrarFiscal($c);
        DB::connection('pymes_tenant')->rollBack();

        $this->assertSame(0, $this->movimientosDe(9103));
    }
}
