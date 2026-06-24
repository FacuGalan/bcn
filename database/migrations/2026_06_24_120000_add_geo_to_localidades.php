<?php

use Database\Seeders\LocalidadesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Picker Google Maps del domicilio (Fase 1) — geo en el catálogo de localidades.
 *
 * Agrega `latitud`/`longitud` a `localidades` (tabla COMPARTIDA, conexión config)
 * y las backfillea desde el dataset GeoRef (`database/data/localidades_georef.json`,
 * mismo origen que el reseed de Fase 9), matcheando por (provincia ISO, nombre).
 *
 * Sirve para centrar y ACOTAR el mapa a la localidad elegida (flujo invertido:
 * provincia → localidad → mapa restringido). Ver spec domicilio-google-maps.md.
 *
 * NO es tenant: no itera comercios ni prefijos; no toca tenant_tables.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('config')->hasColumn('localidades', 'latitud')) {
            DB::connection('config')->statement(
                'ALTER TABLE `localidades`
                    ADD COLUMN `latitud` decimal(10,7) NULL AFTER `nombre`,
                    ADD COLUMN `longitud` decimal(10,7) NULL AFTER `latitud`'
            );
        }

        // ISO 3166-2 → provincia_id y su inverso (config).
        $isoAId = DB::connection('config')->table('provincias')->pluck('id', 'codigo')->toArray();
        $idAIso = array_flip($isoAId);

        // Mapa "ISO|nombre_lower" → ['lat'=>, 'lon'=>] desde el JSON GeoRef.
        $path = database_path(LocalidadesSeeder::DATA_PATH);
        $data = json_decode((string) file_get_contents($path), true) ?: [];

        $geoPorClave = [];
        foreach ($data as $loc) {
            if (! isset($loc['lat'], $loc['lon'])) {
                continue;
            }
            $clave = $loc['p'].'|'.mb_strtolower(trim($loc['n']));
            // Si hay duplicados (provincia+nombre), queda el primero.
            $geoPorClave[$clave] ??= ['lat' => $loc['lat'], 'lon' => $loc['lon']];
        }

        // Resolver el geo de cada localidad existente (id => [lat,lon]).
        $matched = [];
        foreach (DB::connection('config')->table('localidades')->get(['id', 'provincia_id', 'nombre']) as $l) {
            $iso = $idAIso[$l->provincia_id] ?? null;
            if (! $iso) {
                continue;
            }
            $clave = $iso.'|'.mb_strtolower(trim($l->nombre));
            if (isset($geoPorClave[$clave])) {
                $matched[$l->id] = $geoPorClave[$clave];
            }
        }

        // Update masivo por CASE en chunks (eficiente, una query por chunk).
        DB::connection('config')->transaction(function () use ($matched) {
            foreach (array_chunk($matched, 500, true) as $chunk) {
                $casesLat = '';
                $casesLng = '';
                $ids = [];
                foreach ($chunk as $id => $geo) {
                    $id = (int) $id;
                    $lat = sprintf('%.7f', (float) $geo['lat']);
                    $lng = sprintf('%.7f', (float) $geo['lon']);
                    $casesLat .= "WHEN {$id} THEN {$lat} ";
                    $casesLng .= "WHEN {$id} THEN {$lng} ";
                    $ids[] = $id;
                }
                $idList = implode(',', $ids);
                DB::connection('config')->statement(
                    "UPDATE `localidades`
                        SET `latitud` = CASE `id` {$casesLat} END,
                            `longitud` = CASE `id` {$casesLng} END
                        WHERE `id` IN ({$idList})"
                );
            }
        });
    }

    public function down(): void
    {
        if (Schema::connection('config')->hasColumn('localidades', 'latitud')) {
            DB::connection('config')->statement(
                'ALTER TABLE `localidades` DROP COLUMN `latitud`, DROP COLUMN `longitud`'
            );
        }
    }
};
