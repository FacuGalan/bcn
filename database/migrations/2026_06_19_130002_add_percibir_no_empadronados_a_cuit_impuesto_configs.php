<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 10a, D7): flag por agente "percibir a no empadronados".
 *
 * Resuelve qué hacer cuando un cliente RI no tiene entrada en su perfil fiscal
 * (`cliente_impuesto_configs`) ni en el padrón para un IIBB que el agente percibe:
 *  - true  ⇒ el agente percibe a su alícuota fija (comportamiento 5b);
 *  - false ⇒ NO percibe (DEFAULT seguro).
 *
 * Decisión por CUIT/jurisdicción del usuario (config del AGENTE), no por cliente.
 * Aplica SOLO a IIBB; la percepción de IVA sigue siendo automática.
 *
 * Ref: .claude/specs/sistema-impositivo.md (D7, Fase 10a).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}cuit_impuesto_configs`
                    ADD COLUMN `percibir_no_empadronados` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'IIBB: percibir a RI sin config/padron (D7)' AFTER `es_agente_retencion`
                ");
            } catch (\Exception $e) {
                // No-op por comercio (columna ya existe).
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("ALTER TABLE `{$prefix}cuit_impuesto_configs` DROP COLUMN `percibir_no_empadronados`");
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }
};
