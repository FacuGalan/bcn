<?php

use Database\Seeders\LocalidadesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 9 (Sistema impositivo, RF-11): reseed del padrón de localidades.
 *
 * Reemplaza el dataset anterior (centrado en códigos postales, con errores
 * conocidos como Mercedes BA 6100 vs 6600 real) por GeoRef Argentina
 * (datos.gob.ar), fuente oficial y actual para provincia → localidad.
 *
 * `localidades` es una tabla COMPARTIDA (conexión config) referenciada por
 * `cuits.localidad_id` (tenant, una por comercio) → al recrearla hay que
 * REMAPEAR esas referencias por (provincia, nombre) para no romperlas.
 * No se trunca a ciegas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Mapas de provincias (config)
        $isoAId = DB::connection('config')->table('provincias')->pluck('id', 'codigo')->toArray();
        $idAIso = array_flip($isoAId);

        // 2) Capturar referencias actuales: id viejo → "ISO|nombre_lower"
        $localidadesViejas = DB::connection('config')->table('localidades')->get(['id', 'provincia_id', 'nombre']);
        $claveVieja = [];
        foreach ($localidadesViejas as $l) {
            $iso = $idAIso[$l->provincia_id] ?? null;
            if ($iso) {
                $claveVieja[$l->id] = $iso.'|'.mb_strtolower(trim($l->nombre));
            }
        }

        // 3) Capturar los cuits (de TODOS los comercios) que referencian una localidad
        $refsCuits = []; // [ ['prefix'=>..., 'cuit_id'=>..., 'clave'=>...] ]
        $comercios = DB::connection('config')->table('comercios')->get();
        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            try {
                $cuits = DB::connection('pymes_tenant')
                    ->table($prefix.'cuits')
                    ->whereNotNull('localidad_id')
                    ->get(['id', 'localidad_id']);
                foreach ($cuits as $cuit) {
                    $refsCuits[] = [
                        'prefix' => $prefix,
                        'cuit_id' => $cuit->id,
                        'clave' => $claveVieja[$cuit->localidad_id] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // comercio sin tabla cuits o error puntual → continuar
            }
        }

        // 4) Reemplazar localidades con el dataset GeoRef
        DB::connection('config')->table('localidades')->delete();
        DB::connection('config')->statement('ALTER TABLE `localidades` AUTO_INCREMENT = 1');

        $filas = LocalidadesSeeder::cargarLocalidades($isoAId);
        foreach (array_chunk($filas, 500) as $chunk) {
            DB::connection('config')->table('localidades')->insert($chunk);
        }

        // 5) Construir mapa nuevo "ISO|nombre_lower" → id nuevo
        $claveNueva = [];
        foreach (DB::connection('config')->table('localidades')->get(['id', 'provincia_id', 'nombre']) as $l) {
            $iso = $idAIso[$l->provincia_id] ?? null;
            if ($iso) {
                $claveNueva[$iso.'|'.mb_strtolower(trim($l->nombre))] = $l->id;
            }
        }

        // 6) Remapear cada cuit a su nueva localidad (NULL si no hay match)
        foreach ($refsCuits as $ref) {
            $nuevoId = $ref['clave'] ? ($claveNueva[$ref['clave']] ?? null) : null;
            try {
                DB::connection('pymes_tenant')
                    ->table($ref['prefix'].'cuits')
                    ->where('id', $ref['cuit_id'])
                    ->update(['localidad_id' => $nuevoId]);
            } catch (\Exception $e) {
                // no bloquear el reseed por un update puntual
            }
        }
    }

    public function down(): void
    {
        // El dataset anterior (padrón AFIP por CP) no se restaura: era la fuente
        // de datos incorrectos que esta migración corrige. Reversión no aplicable.
    }
};
