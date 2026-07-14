<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D25 del spec compras-costos-precios: coeficiente de computabilidad de
 * percepciones SUFRIDAS en compras.
 *  - cuit_impuesto_configs.coeficiente_computable: default del comercio por
 *    jurisdicción (0-1). NULL = derivar de `inscripto` (1.00 si inscripto,
 *    0.00 si no). Qué parte de la percepción sufrida es crédito computable;
 *    el resto es costo.
 *  - compra_percepciones.coeficiente: snapshot editable por compra (0-1).
 *    NULL = no computable (compra no fiscal o sin config).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->aplicar([
            'cuit_impuesto_configs' => [
                'coeficiente_computable' => 'ADD COLUMN `coeficiente_computable` decimal(5,4) DEFAULT NULL AFTER `inscripto`',
            ],
            'compra_percepciones' => [
                'coeficiente' => 'ADD COLUMN `coeficiente` decimal(5,4) DEFAULT NULL AFTER `monto`',
            ],
        ]);
    }

    public function down(): void
    {
        $this->aplicar([
            'cuit_impuesto_configs' => [
                'coeficiente_computable' => 'DROP COLUMN `coeficiente_computable`',
            ],
            'compra_percepciones' => [
                'coeficiente' => 'DROP COLUMN `coeficiente`',
            ],
        ], invertir: true);
    }

    /**
     * Corre cada ALTER por comercio con guarda de idempotencia por columna
     * (en up saltea si ya existe; en down saltea si no existe).
     */
    private function aplicar(array $cambios, bool $invertir = false): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            foreach ($cambios as $tabla => $columnas) {
                foreach ($columnas as $columna => $alter) {
                    try {
                        if (! $this->tablaExiste($prefix.$tabla)) {
                            continue;
                        }

                        $existe = $this->columnaExiste($prefix.$tabla, $columna);

                        if ($invertir ? ! $existe : $existe) {
                            continue;
                        }

                        DB::connection('pymes')->statement("ALTER TABLE `{$prefix}{$tabla}` {$alter}");
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
    }

    private function tablaExiste(string $tabla): bool
    {
        return DB::connection('pymes')->selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tabla],
        ) !== null;
    }

    private function columnaExiste(string $tabla, string $columna): bool
    {
        return DB::connection('pymes')->selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?  AND column_name = ?',
            [$tabla, $columna],
        ) !== null;
    }
};
