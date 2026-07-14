<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Incrementos D23/D24 del spec compras-costos-precios:
 *  - compras.es_servicio: modalidad "factura de servicio" (sin grilla de
 *    artículos ni efectos de stock/costos; el detalle son los conceptos).
 *  - proveedores.es_servicio: default de la modalidad al elegir el proveedor
 *    (ej. EDESUR ⇒ servicio + su cuenta de compra default).
 *  - proveedores.percepciones_habituales: precarga de conveniencia (D24) de
 *    renglones de percepción [{impuesto_id, alicuota}] al elegir proveedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->aplicar([
            'compras' => [
                'es_servicio' => 'ADD COLUMN `es_servicio` tinyint(1) NOT NULL DEFAULT 0 AFTER `tipo_comprobante`',
            ],
            'proveedores' => [
                'es_servicio' => 'ADD COLUMN `es_servicio` tinyint(1) NOT NULL DEFAULT 0 AFTER `cuenta_compra_id`',
                'percepciones_habituales' => 'ADD COLUMN `percepciones_habituales` json DEFAULT NULL AFTER `es_servicio`',
            ],
        ]);
    }

    public function down(): void
    {
        $this->aplicar([
            'compras' => [
                'es_servicio' => 'DROP COLUMN `es_servicio`',
            ],
            'proveedores' => [
                'es_servicio' => 'DROP COLUMN `es_servicio`',
                'percepciones_habituales' => 'DROP COLUMN `percepciones_habituales`',
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
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tabla, $columna],
        ) !== null;
    }
};
