<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\ArticuloImagenTienda;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Galería de fotos de TIENDA por artículo (RF-T14).
 *
 * Hereda de ImagenArticuloService para reutilizar el pipeline de seguridad
 * completo (finfo MIME real, whitelist jpg/png/webp, re-encode WebP q85,
 * UUID, resize 800px, strip EXIF — ver doc de la clase padre). Diferencias:
 *  - 1:N (tabla articulo_imagenes_tienda) en vez de columna única.
 *  - Path scopeado en subcarpeta: articulos/{comercio_id}/tienda/{uuid}.webp.
 *  - Máximo MAX_IMAGENES fotos por artículo (validado acá, server-side).
 */
class ImagenArticuloTiendaService extends ImagenArticuloService
{
    public const MAX_IMAGENES = 5;

    /**
     * Procesa y agrega una foto a la galería de tienda del artículo.
     * La foto entra al final (orden = max + 1).
     *
     * @throws Exception si el archivo no pasa las validaciones o se llegó al máximo.
     */
    public function agregar(Articulo $articulo, UploadedFile $file): ArticuloImagenTienda
    {
        $this->validar($file);

        if ($articulo->imagenesTienda()->count() >= self::MAX_IMAGENES) {
            throw new Exception(__('El artículo ya tiene el máximo de :max fotos de tienda.', [
                'max' => self::MAX_IMAGENES,
            ]));
        }

        $comercioId = app(TenantService::class)->getComercioId() ?? 0;
        $filename = Str::uuid()->toString().'.webp';
        $path = "articulos/{$comercioId}/tienda/{$filename}";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            Log::warning('ImagenArticuloTiendaService: fallo al decodificar imagen', [
                'articulo_id' => $articulo->id,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        $ordenSiguiente = (int) $articulo->imagenesTienda()->max('orden') + 1;

        return $articulo->imagenesTienda()->create([
            'path' => $path,
            'orden' => $ordenSiguiente,
        ]);
    }

    /**
     * Borra una foto de la galería (archivo + registro).
     */
    public function quitar(ArticuloImagenTienda $imagen): void
    {
        Storage::disk('public')->delete($imagen->path);
        $imagen->delete();
    }

    /**
     * Persiste el orden elegido por drag & drop. Solo renumera los IDs que
     * pertenecen al artículo (uno ajeno se ignora — defensa ante payload
     * manipulado del cliente).
     *
     * @param  array<int, int|string>  $idsOrdenados  IDs en el orden final deseado.
     */
    public function reordenar(Articulo $articulo, array $idsOrdenados): void
    {
        $propios = $articulo->imagenesTienda()->pluck('id')->all();

        DB::connection('pymes_tenant')->transaction(function () use ($articulo, $idsOrdenados, $propios) {
            $orden = 1;
            foreach ($idsOrdenados as $id) {
                if (! in_array((int) $id, $propios, true)) {
                    continue;
                }

                $articulo->imagenesTienda()
                    ->where('id', (int) $id)
                    ->update(['orden' => $orden++]);
            }
        });
    }
}
