<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        // 1. Crear el grupo "Marca" si no existe
        $grupoMarcaId = DB::connection($this->connection)->table('grupos_etiquetas')->insertGetId([
            'nombre' => 'Marca',
            'codigo' => 'MARCA',
            'descripcion' => 'Marcas de los artículos (migrado automáticamente)',
            'color' => '#3B82F6',
            'activo' => true,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Obtener todas las marcas únicas de artículos
        $marcas = DB::connection($this->connection)->table('articulos')
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->distinct()
            ->pluck('marca');

        // 3. Crear etiquetas para cada marca
        $etiquetasMap = [];
        $orden = 1;
        foreach ($marcas as $marca) {
            $etiquetaId = DB::connection($this->connection)->table('etiquetas')->insertGetId([
                'grupo_etiqueta_id' => $grupoMarcaId,
                'nombre' => $marca,
                'codigo' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $marca), 0, 20)),
                'activo' => true,
                'orden' => $orden++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $etiquetasMap[$marca] = $etiquetaId;
        }

        // 4. Asignar etiquetas a los artículos según su marca
        $articulos = DB::connection($this->connection)->table('articulos')
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->get(['id', 'marca']);

        foreach ($articulos as $articulo) {
            if (isset($etiquetasMap[$articulo->marca])) {
                DB::connection($this->connection)->table('articulo_etiqueta')->insert([
                    'articulo_id' => $articulo->id,
                    'etiqueta_id' => $etiquetasMap[$articulo->marca],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 5. Eliminar el campo marca de artículos
        Schema::connection($this->connection)->table('articulos', function (Blueprint $table) {
            $table->dropColumn('marca');
        });
    }

    public function down(): void
    {
        // 1. Restaurar el campo marca
        Schema::connection($this->connection)->table('articulos', function (Blueprint $table) {
            $table->string('marca', 100)->nullable()->after('categoria_id');
        });

        // 2. Obtener el grupo Marca
        $grupoMarca = DB::connection($this->connection)->table('grupos_etiquetas')
            ->where('codigo', 'MARCA')
            ->first();

        if ($grupoMarca) {
            // 3. Restaurar las marcas a los artículos
            $relaciones = DB::connection($this->connection)->table('articulo_etiqueta')
                ->join('etiquetas', 'articulo_etiqueta.etiqueta_id', '=', 'etiquetas.id')
                ->where('etiquetas.grupo_etiqueta_id', $grupoMarca->id)
                ->get(['articulo_etiqueta.articulo_id', 'etiquetas.nombre as marca']);

            foreach ($relaciones as $relacion) {
                DB::connection($this->connection)->table('articulos')
                    ->where('id', $relacion->articulo_id)
                    ->update(['marca' => $relacion->marca]);
            }

            // 4. Eliminar las relaciones de artículos con etiquetas de marca
            DB::connection($this->connection)->table('articulo_etiqueta')
                ->whereIn('etiqueta_id', function ($query) use ($grupoMarca) {
                    $query->select('id')
                          ->from('etiquetas')
                          ->where('grupo_etiqueta_id', $grupoMarca->id);
                })
                ->delete();

            // 5. Eliminar las etiquetas del grupo Marca
            DB::connection($this->connection)->table('etiquetas')
                ->where('grupo_etiqueta_id', $grupoMarca->id)
                ->delete();

            // 6. Eliminar el grupo Marca
            DB::connection($this->connection)->table('grupos_etiquetas')
                ->where('id', $grupoMarca->id)
                ->delete();
        }
    }
};
