<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\FormaPago;
use App\Models\Sucursal;
use App\Models\FormaPagoSucursal;
use App\Models\Comercio;

/**
 * Seeder: Formas de Pago por Sucursal
 *
 * Habilita las formas de pago en cada sucursal.
 * Permite configurar qué métodos de pago están disponibles por sucursal.
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class FormasPagoSucursalesSeeder extends Seeder
{
    private $comercioId = 1;
    private $sucursales = [];
    private $formasPago = [];

    public function run(): void
    {
        echo "🏢 Iniciando seeder de Formas de Pago por Sucursal...\n\n";

        $this->configurarTenant();
        $this->obtenerSucursales();
        $this->obtenerFormasPago();
        $this->asignarFormasPagoASucursales();

        echo "\n✅ Seeder completado exitosamente!\n\n";
    }

    private function configurarTenant(): void
    {
        echo "⚙️  Configurando tenant para comercio {$this->comercioId}...\n";

        $comercio = Comercio::find($this->comercioId);
        $prefix = str_pad($this->comercioId, 6, '0', STR_PAD_LEFT) . '_';

        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes'
        ]);

        DB::purge('pymes_tenant');
        echo "   ✓ Tenant configurado (prefix: {$prefix})\n\n";
    }

    private function obtenerSucursales(): void
    {
        echo "🏢 Obteniendo sucursales...\n";

        $this->sucursales = Sucursal::where('activa', true)->get();

        foreach ($this->sucursales as $sucursal) {
            echo "   ✓ {$sucursal->nombre}\n";
        }
        echo "\n";
    }

    private function obtenerFormasPago(): void
    {
        echo "💳 Obteniendo formas de pago...\n";

        $this->formasPago = FormaPago::where('activo', true)->get();

        foreach ($this->formasPago as $fp) {
            echo "   ✓ {$fp->nombre}\n";
        }
        echo "\n";
    }

    private function asignarFormasPagoASucursales(): void
    {
        echo "🔗 Asignando formas de pago a sucursales...\n\n";

        if ($this->sucursales->isEmpty()) {
            echo "   ⚠️  No hay sucursales disponibles\n";
            return;
        }

        if ($this->formasPago->isEmpty()) {
            echo "   ⚠️  No hay formas de pago disponibles\n";
            return;
        }

        $totalAsignaciones = 0;

        foreach ($this->sucursales as $sucursal) {
            echo "   📍 {$sucursal->nombre}:\n";
            $contador = 0;

            foreach ($this->formasPago as $formaPago) {
                // Verificar si ya existe la asignación
                $existing = FormaPagoSucursal::where('forma_pago_id', $formaPago->id)
                                             ->where('sucursal_id', $sucursal->id)
                                             ->first();

                if ($existing) {
                    continue;
                }

                // Lógica de habilitación:
                // - Casa Central: todas las formas de pago
                // - Sucursal Norte: todas menos Cheque y Cuenta Corriente
                // - Sucursal Sur: solo efectivo, débito y crédito
                $activo = $this->debeHabilitarse($sucursal, $formaPago);

                FormaPagoSucursal::create([
                    'forma_pago_id' => $formaPago->id,
                    'sucursal_id' => $sucursal->id,
                    'activo' => $activo,
                ]);

                if ($activo) {
                    echo "      ✓ {$formaPago->nombre}\n";
                    $contador++;
                } else {
                    echo "      ✗ {$formaPago->nombre} [DESHABILITADO]\n";
                }

                $totalAsignaciones++;
            }

            echo "      Total habilitados: {$contador}\n\n";
        }

        echo "📊 Total asignaciones creadas: {$totalAsignaciones}\n";
    }

    /**
     * Determina si una forma de pago debe estar habilitada en una sucursal
     */
    private function debeHabilitarse(Sucursal $sucursal, FormaPago $formaPago): bool
    {
        // Casa Central - todas habilitadas
        if ($sucursal->es_principal) {
            return true;
        }

        // Sucursal Norte - todas excepto Cheque y Cuenta Corriente
        if (str_contains(strtolower($sucursal->nombre), 'norte')) {
            return !in_array($formaPago->concepto, ['cheque', 'otro']);
        }

        // Sucursal Sur - solo efectivo y tarjetas
        if (str_contains(strtolower($sucursal->nombre), 'sur')) {
            return in_array($formaPago->concepto, ['efectivo', 'tarjeta_debito', 'tarjeta_credito']);
        }

        // Por defecto, habilitar todas
        return true;
    }
}
