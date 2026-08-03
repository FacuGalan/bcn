<?php

namespace App\Services;

use App\Models\Categoria;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Banner de foto por categoría para la tienda online (RF-T62).
 *
 * Hereda de ImagenArticuloService para reutilizar el pipeline de seguridad
 * completo (finfo MIME real, whitelist jpg/png/webp, re-encode WebP q85,
 * UUID, strip EXIF — ver doc de la clase padre). Diferencias:
 *  - Persiste en `categorias.imagen_path` (campo RF-17, hasta ahora cargado
 *    crudo desde Artículos → Categorías; esta vía lo reemplaza).
 *  - Formato panorámico: el banner es el fondo del encabezado de categoría
 *    en el catálogo de la tienda (1600×600, mismo criterio que la portada).
 *  - Path scopeado: `categorias/{comercio_id}/{uuid}.webp`.
 */
class ImagenCategoriaTiendaService extends ImagenArticuloService
{
    /** El banner es una franja ancha: 1600px de ancho como la portada. */
    public const BANNER_MAX_ANCHO = 1600;

    public const BANNER_MAX_ALTO = 600;

    /**
     * Procesa y persiste el banner de la categoría. Reemplaza (borra) el
     * anterior si lo había.
     *
     * @throws Exception si el archivo no pasa las validaciones de seguridad.
     */
    public function actualizarBanner(Categoria $categoria, UploadedFile $file): string
    {
        $this->validar($file);

        if ($categoria->imagen_path) {
            Storage::disk('public')->delete($categoria->imagen_path);
        }

        $comercioId = app(TenantService::class)->getComercioId() ?? 0;
        $filename = Str::uuid()->toString().'.webp';
        $path = "categorias/{$comercioId}/{$filename}";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                ->scaleDown(width: self::BANNER_MAX_ANCHO, height: self::BANNER_MAX_ALTO)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            Log::warning('ImagenCategoriaTiendaService: fallo al decodificar imagen', [
                'categoria_id' => $categoria->id,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        // Imagen nueva = focal al centro (mismo criterio que ImagenArticuloService:
        // una foto distinta no debe heredar el focal de la anterior).
        $categoria->update([
            'imagen_path' => $path,
            'imagen_focal_x' => 50,
            'imagen_focal_y' => 50,
        ]);

        return $path;
    }

    /**
     * Borra el banner de la categoría y limpia el campo.
     */
    public function eliminarBanner(Categoria $categoria): void
    {
        if (! $categoria->imagen_path) {
            return;
        }

        Storage::disk('public')->delete($categoria->imagen_path);
        $categoria->update([
            'imagen_path' => null,
            'imagen_focal_x' => 50,
            'imagen_focal_y' => 50,
        ]);
    }
}
