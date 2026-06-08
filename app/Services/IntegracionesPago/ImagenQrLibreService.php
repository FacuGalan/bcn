<?php

namespace App\Services\IntegracionesPago;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Procesa el upload de la imagen del QR "Cobrar" de Mercado Pago que el comercio
 * sube para el modo de cobro `qr_libre` (QR de monto libre). La imagen se muestra
 * al cliente (en el modal del cajero y/o en la 2da pantalla) para que escanee y
 * pague el monto que el cajero le indica.
 *
 * Mismo enfoque de seguridad que ImagenArticuloService (defensa en profundidad):
 *  - Tamaño máximo verificado server-side.
 *  - MIME real con finfo (magic bytes), no la extensión ni el Content-Type.
 *  - Whitelist estricta JPG/PNG/WebP. SVG prohibido (puede contener <script>).
 *  - Re-encoding completo a WebP con GD/Intervention: cualquier payload embebido
 *    (EXIF, polyglot) se pierde. Strip de EXIF de yapa.
 *  - Nombre por UUID (no por nombre del cliente): sin path traversal ni colisiones.
 *  - Path scopeado por comercio: aísla las imágenes entre tenants.
 *
 * Diferencia con artículos: NO se redimensiona agresivamente. Un QR debe quedar
 * nítido y escaneable, así que el límite es amplio (1000px) y la calidad alta (90).
 *
 * No usa DB ni transacciones: solo storage de archivos. El comercioId lo resuelve
 * y pasa el caller (no lo lee del TenantService internamente, para ser testeable).
 */
class ImagenQrLibreService
{
    public const MAX_SIZE_BYTES = 4 * 1024 * 1024; // 4 MB

    public const MAX_DIMENSION = 1000;

    public const WEBP_QUALITY = 90;

    /** MIME types aceptados detectados por finfo (no por extensión). */
    protected const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Valida, re-encodea a WebP y persiste la imagen del QR en el disk público,
     * scopeada por comercio. Devuelve el path relativo y la URL pública.
     *
     * @return array{path: string, url: string}
     *
     * @throws Exception si el archivo no pasa las validaciones de seguridad.
     */
    public function guardar(int $comercioId, UploadedFile $file): array
    {
        $this->validar($file);

        $filename = Str::uuid()->toString().'.webp';
        $path = "integraciones/qr_libre/{$comercioId}/{$filename}";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                // scaleDown nunca hace upscale: solo achica si supera el límite.
                ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            // Si Intervention no puede decodificar, el archivo no es una imagen
            // válida aunque finfo dijera que sí (defensa contra polyglot files).
            Log::warning('ImagenQrLibreService: fallo al decodificar imagen', [
                'comercio_id' => $comercioId,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen del QR no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        Log::info('ImagenQrLibreService: imagen de QR libre guardada', [
            'comercio_id' => $comercioId,
            'path' => $path,
        ]);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * Borra la imagen del disk público si el path apunta a un archivo existente.
     */
    public function eliminar(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
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

        // MIME real desde el contenido del archivo (magic bytes), no de la
        // extensión ni del Content-Type que envía el cliente.
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
