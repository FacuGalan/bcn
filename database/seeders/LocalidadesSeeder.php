<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para las localidades de Argentina
 *
 * Fuente: GeoRef Argentina (datos.gob.ar) — entidad "localidades".
 * Dataset oficial y actual (provincia → localidad confiables), reemplaza el
 * padrón anterior centrado en códigos postales (con errores conocidos).
 *
 * El dataset normalizado vive en database/data/localidades_georef.json
 * (generado desde la API de GeoRef, deduplicado por provincia+nombre).
 *
 * Tabla compartida en la base de datos config.
 */
class LocalidadesSeeder extends Seeder
{
    /**
     * Ruta del dataset normalizado de GeoRef.
     */
    public const DATA_PATH = 'data/localidades_georef.json';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mapa ISO 3166-2 (codigo de provincia) → id de provincia en config
        $provincias = DB::connection('config')
            ->table('provincias')
            ->pluck('id', 'codigo')
            ->toArray();

        $localidades = static::cargarLocalidades($provincias);

        $chunks = array_chunk($localidades, 500);
        $total = 0;

        foreach ($chunks as $chunk) {
            DB::connection('config')->table('localidades')->insert($chunk);
            $total += count($chunk);
        }

        if (isset($this->command)) {
            $this->command->info('Localidades creadas: '.$total);
        }
    }

    /**
     * Carga el dataset GeoRef y lo transforma en filas listas para insertar.
     *
     * @param  array<string,int>  $provincias  mapa ISO → provincia_id
     * @return array<int,array<string,mixed>>
     */
    public static function cargarLocalidades(array $provincias): array
    {
        $now = now();
        $path = database_path(static::DATA_PATH);
        $data = json_decode((string) file_get_contents($path), true) ?: [];

        $localidades = [];
        foreach ($data as $loc) {
            $provinciaId = $provincias[$loc['p']] ?? null;
            if (! $provinciaId) {
                continue;
            }

            $localidades[] = [
                'provincia_id' => $provinciaId,
                'codigo_postal' => null, // GeoRef no provee CP confiable; diferido
                'nombre' => $loc['n'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $localidades;
    }
}
