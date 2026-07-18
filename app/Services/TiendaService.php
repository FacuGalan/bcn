<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\Tienda;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tiendas online (RF-T10/RF-T11, spec tienda-online).
 *
 * Alta del registro GLOBAL `config.tiendas` de una sucursal (D15: una
 * tienda = una sucursal = un slug único global). Escribe SOLO sobre la
 * conexión `config` — acá no hay transacción tenant: es un único insert y
 * el UNIQUE de `slug` protege la carrera de la sugerencia.
 */
class TiendaService
{
    /**
     * Crea la tienda de la sucursal con slug sugerido, siempre despublicada:
     * publicarla es una decisión explícita posterior (switch + guardar).
     */
    public function crearParaSucursal(int $comercioId, Sucursal $sucursal, string $nombreComercio = ''): Tienda
    {
        $existente = Tienda::where('comercio_id', $comercioId)
            ->where('sucursal_id', (int) $sucursal->id)
            ->first();

        if ($existente) {
            return $existente;
        }

        try {
            $tienda = Tienda::create([
                'comercio_id' => $comercioId,
                'sucursal_id' => (int) $sucursal->id,
                'slug' => $this->slugSugerido($comercioId, $sucursal, $nombreComercio),
                'habilitada' => false,
            ]);

            Log::info('Tienda online creada', [
                'tienda_id' => $tienda->id,
                'comercio_id' => $comercioId,
                'sucursal_id' => $sucursal->id,
                'slug' => $tienda->slug,
            ]);

            return $tienda;
        } catch (Exception $e) {
            Log::error('Error al crear tienda online', [
                'error' => $e->getMessage(),
                'comercio_id' => $comercioId,
                'sucursal_id' => $sucursal->id,
            ]);

            throw new Exception(__('No se pudo crear la tienda online'));
        }
    }

    /**
     * Slug sugerido: comercio + sucursal slugificados, único global (la URL
     * pública identifica comercio+sucursal, D15). Ante colisión suma sufijo.
     */
    private function slugSugerido(int $comercioId, Sucursal $sucursal, string $nombreComercio): string
    {
        $base = Str::slug(trim($nombreComercio.' '.$sucursal->nombre));
        $base = Str::limit($base ?: 'tienda-'.$comercioId, 55, '');

        $slug = $base;
        $i = 2;
        while (Tienda::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
