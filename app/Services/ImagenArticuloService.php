<?php

namespace App\Services;

use App\Models\Articulo;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Procesa el upload de imágenes de artículos con foco en seguridad y
 * eficiencia de storage.
 *
 * Seguridad implementada (defensa en profundidad):
 *  - Tamaño máximo 5MB (verificado server-side, no confiar en el cliente).
 *  - MIME real detectado con finfo, no la extensión ni el Content-Type del
 *    request (un atacante puede mentir en ambos).
 *  - Whitelist estricta: solo image/jpeg, image/png, image/webp.
 *    SVG está prohibido (puede contener <script> = XSS).
 *    GIF y BMP también, porque no aportan valor para producto y amplían la
 *    superficie de ataque (GIFs animados pueden tener payloads).
 *  - Re-encoding completo con GD/Intervention a WebP: cualquier payload
 *    embebido (EXIF malicioso, código en metadata, polyglot files) se
 *    pierde en el re-encoding. La salida es siempre un WebP limpio.
 *  - Nombre de archivo determinado por UUID (no por nombre del cliente):
 *    elimina path traversal (../) y colisiones por nombres iguales.
 *  - Path scopeado por comercio: `articulos/{comercio_id}/{uuid}.webp`.
 *    Aísla las imágenes entre tenants.
 *  - Strip EXIF: Intervention al re-encodear no preserva EXIF por default,
 *    eliminando metadata personal (GPS, equipo, etc.) y posibles vectores.
 *
 * Optimización:
 *  - Resize máximo 800×800 manteniendo aspect ratio (no upscale).
 *  - WebP calidad 85% (balance peso/calidad).
 *  - Tamaño final típico: 40–100KB.
 */
class ImagenArticuloService
{
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    public const MAX_DIMENSION = 800;

    public const WEBP_QUALITY = 85;

    /** MIME types aceptados detectados por finfo (no por extensión). */
    protected const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Procesa y persiste una nueva imagen para el artículo. Reemplaza
     * (borra) la imagen anterior si la había.
     *
     * @throws Exception si el archivo no pasa las validaciones de seguridad.
     */
    public function actualizar(Articulo $articulo, UploadedFile $file): string
    {
        $this->validar($file);

        // Borrar la imagen anterior si existía. Lo hacemos antes del upload
        // para que en caso de error el espacio quede limpio (la nueva no se
        // grabó todavía).
        if ($articulo->imagen_path) {
            Storage::disk('public')->delete($articulo->imagen_path);
        }

        $comercioId = app(TenantService::class)->getComercioId() ?? 0;
        $filename = Str::uuid()->toString().'.webp';
        $path = "articulos/{$comercioId}/{$filename}";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            // Si Intervention falla al decodificar significa que el archivo
            // no es una imagen válida (aunque finfo dijera que sí). Defensa
            // adicional contra polyglot files.
            Log::warning('ImagenArticuloService: fallo al decodificar imagen', [
                'articulo_id' => $articulo->id,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        // Imagen nueva = focal point al centro. El usuario lo ajusta después
        // si quiere otro punto. Si no resetearamos, una imagen distinta podría
        // heredar el focal de la anterior (que ya no aplica).
        $articulo->update([
            'imagen_path' => $path,
            'imagen_focal_x' => 50,
            'imagen_focal_y' => 50,
        ]);

        return $path;
    }

    /**
     * Borra la imagen del artículo y limpia el campo.
     */
    public function eliminar(Articulo $articulo): void
    {
        if (! $articulo->imagen_path) {
            return;
        }

        Storage::disk('public')->delete($articulo->imagen_path);
        $articulo->update(['imagen_path' => null]);
    }

    /**
     * Valida el archivo antes de procesarlo. Lanza Exception con mensaje
     * traducible si falla.
     *
     * @throws Exception
     */
    protected function validar(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new Exception(__('El archivo no se subió correctamente.'));
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new Exception(__('La imagen supera el tamaño máximo permitido (:max MB).', [
                'max' => self::MAX_SIZE_BYTES / 1024 / 1024,
            ]));
        }

        // MIME real desde el contenido del archivo, no de la extensión ni
        // del Content-Type que envía el cliente. finfo lee los magic bytes.
        $mimeReal = $this->detectarMime($file->getRealPath());

        if (! in_array($mimeReal, self::ALLOWED_MIMES, true)) {
            throw new Exception(__('Formato de imagen no permitido. Aceptados: JPG, PNG, WebP.'));
        }
    }

    /**
     * Detecta el MIME real leyendo los magic bytes del archivo.
     */
    protected function detectarMime(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: null;
    }
}
