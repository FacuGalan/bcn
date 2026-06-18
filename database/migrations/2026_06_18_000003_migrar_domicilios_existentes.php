<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 9, RF-11): migración de datos de domicilios.
 *
 * (1) Por cada CUIT con localidad, crea su `cuit_domicilios` principal desde
 *     `cuits.direccion` + `localidad_id` (la provincia se deriva de la localidad).
 * (2) Cada PV sin domicilio → el principal de su CUIT.
 * (3) Sucursales → backfill `localidad_id` best-effort desde el texto libre
 *     `localidad` + `provincia` (ISO o nombre).
 *
 * Idempotente: no duplica domicilios principales ni pisa asignaciones previas.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-11, Fase 9, paso 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $isoAId = DB::connection('config')->table('provincias')->pluck('id', 'codigo')->toArray();
        $idAIso = array_flip($isoAId);
        // nombre provincia (lower) → id, para sucursales que guarden el nombre
        $nombreAId = [];
        foreach (DB::connection('config')->table('provincias')->get(['id', 'nombre']) as $p) {
            $nombreAId[mb_strtolower($p->nombre)] = $p->id;
        }

        $comercios = DB::connection('config')->table('comercios')->get();
        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            // (1) + (2) Domicilio principal por CUIT y asignación a sus PV
            try {
                $cuits = DB::connection('pymes_tenant')->table($prefix.'cuits')->get(['id', 'direccion', 'localidad_id']);
                foreach ($cuits as $cuit) {
                    $iso = null;
                    if ($cuit->localidad_id) {
                        $provId = DB::connection('config')->table('localidades')->where('id', $cuit->localidad_id)->value('provincia_id');
                        $iso = $provId ? ($idAIso[$provId] ?? null) : null;
                    }
                    // Sin provincia no se puede crear el domicilio (provincia NOT NULL)
                    if (! $iso) {
                        continue;
                    }

                    $domId = DB::connection('pymes_tenant')->table($prefix.'cuit_domicilios')
                        ->where('cuit_id', $cuit->id)->where('es_principal', true)->value('id');

                    if (! $domId) {
                        $domId = DB::connection('pymes_tenant')->table($prefix.'cuit_domicilios')->insertGetId([
                            'cuit_id' => $cuit->id,
                            'tipo' => 'fiscal',
                            'provincia' => $iso,
                            'localidad_id' => $cuit->localidad_id,
                            'direccion' => $cuit->direccion ?? '',
                            'es_principal' => true,
                            'activo' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::connection('pymes_tenant')->table($prefix.'puntos_venta')
                        ->where('cuit_id', $cuit->id)
                        ->whereNull('cuit_domicilio_id')
                        ->update(['cuit_domicilio_id' => $domId]);
                }
            } catch (\Exception $e) {
                // comercio sin tablas / error puntual → continuar
            }

            // (3) Backfill localidad_id de sucursales desde el texto libre
            try {
                $sucursales = DB::connection('pymes_tenant')->table($prefix.'sucursales')
                    ->whereNull('localidad_id')->whereNotNull('localidad')->get(['id', 'localidad', 'provincia']);
                foreach ($sucursales as $suc) {
                    $prov = trim((string) $suc->provincia);
                    $provId = $isoAId[$prov] ?? ($nombreAId[mb_strtolower($prov)] ?? null);
                    if (! $provId) {
                        continue;
                    }
                    $locId = DB::connection('config')->table('localidades')
                        ->where('provincia_id', $provId)
                        ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim((string) $suc->localidad))])
                        ->value('id');
                    if ($locId) {
                        DB::connection('pymes_tenant')->table($prefix.'sucursales')
                            ->where('id', $suc->id)->update(['localidad_id' => $locId]);
                    }
                }
            } catch (\Exception $e) {
                // No-op por comercio.
            }
        }
    }

    public function down(): void
    {
        // Migración de datos: no se revierte (los domicilios y backfills creados
        // pasan a ser datos de producción). Las columnas/tablas se eliminan en la
        // migración estructural si hace falta rollback total.
    }
};
