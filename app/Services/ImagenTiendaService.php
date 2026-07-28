<?php

namespace App\Services;

use App\Models\Tienda;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Procesa el upload del logo y la portada de la tienda online (RF-T11).
 *
 * Mismas defensas que ImagenArticuloService (el patrón de referencia):
 * tamaño máximo server-side, MIME real por finfo (whitelist jpeg/png/webp,
 * SVG prohibido), re-encoding completo a WebP con GD/Intervention (mata
 * payloads embebidos y EXIF), nombre por UUID y path scopeado por comercio
 * (`tiendas/{comercio_id}/{uuid}.webp`). La tabla `tiendas` es GLOBAL (BD
 * config): acá no hay transacción tenant, es un update de un solo registro.
 */
class ImagenTiendaService
{
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    /** El logo se muestra chico (header/avatar): 800px sobra. */
    public const LOGO_MAX_DIMENSION = 800;

    /** La portada es el banner del header de la tienda: panorámica. */
    public const PORTADA_MAX_ANCHO = 1600;

    public const PORTADA_MAX_ALTO = 900;

    /** Historias: formato vertical tipo story (RF-T24). */
    public const HISTORIA_MAX_ANCHO = 1080;

    public const HISTORIA_MAX_ALTO = 1920;

    public const WEBP_QUALITY = 85;

    /** MIME types aceptados detectados por finfo (no por extensión). */
    protected const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function actualizarLogo(Tienda $tienda, UploadedFile $file): string
    {
        return $this->procesar($tienda, $file, 'logo_path', self::LOGO_MAX_DIMENSION, self::LOGO_MAX_DIMENSION);
    }

    public function actualizarPortada(Tienda $tienda, UploadedFile $file): string
    {
        return $this->procesar($tienda, $file, 'portada_path', self::PORTADA_MAX_ANCHO, self::PORTADA_MAX_ALTO);
    }

    public function eliminarLogo(Tienda $tienda): void
    {
        $this->eliminar($tienda, 'logo_path');
    }

    public function eliminarPortada(Tienda $tienda): void
    {
        $this->eliminar($tienda, 'portada_path');
    }

    /**
     * Agrega una historia destacada (RF-T24): mismas defensas que logo y
     * portada, formato vertical tipo story, tope Tienda::MAX_HISTORIAS.
     * Devuelve la entrada agregada {id, path, orden}.
     *
     * @throws Exception si el archivo no valida o ya hay 3 historias.
     */
    public function agregarHistoria(Tienda $tienda, UploadedFile $file): array
    {
        $historias = $tienda->historias ?? [];

        if (count($historias) >= Tienda::MAX_HISTORIAS) {
            throw new Exception(__('Ya alcanzaste el máximo de :max historias.', ['max' => Tienda::MAX_HISTORIAS]));
        }

        $this->validar($file);

        $id = Str::uuid()->toString();
        $path = "tiendas/{$tienda->comercio_id}/historias/{$id}.webp";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                ->scaleDown(width: self::HISTORIA_MAX_ANCHO, height: self::HISTORIA_MAX_ALTO)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            Log::warning('ImagenTiendaService: fallo al decodificar historia', [
                'tienda_id' => $tienda->id,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        $entrada = [
            'id' => $id,
            'path' => $path,
            'orden' => count($historias) + 1,
        ];
        $historias[] = $entrada;
        $tienda->update(['historias' => array_values($historias)]);

        return $entrada;
    }

    public function eliminarHistoria(Tienda $tienda, string $id): void
    {
        $historias = collect($tienda->historias ?? []);
        $entrada = $historias->firstWhere('id', $id);

        if ($entrada === null) {
            return;
        }

        Storage::disk('public')->delete($entrada['path']);

        // Re-numera el orden para que quede 1..N sin huecos.
        $restantes = $historias->reject(fn (array $h) => $h['id'] === $id)
            ->sortBy('orden')
            ->values()
            ->map(fn (array $h, int $i) => [...$h, 'orden' => $i + 1]);

        $tienda->update(['historias' => $restantes->all()]);
    }

    /**
     * Reordena las historias según la lista de ids recibida (ids ausentes
     * conservan su posición relativa al final).
     */
    public function reordenarHistorias(Tienda $tienda, array $idsOrdenados): void
    {
        $historias = collect($tienda->historias ?? []);

        $ordenadas = $historias
            ->sortBy(function (array $h) use ($idsOrdenados) {
                $pos = array_search($h['id'], $idsOrdenados, true);

                return $pos === false ? PHP_INT_MAX : $pos;
            })
            ->values()
            ->map(fn (array $h, int $i) => [...$h, 'orden' => $i + 1]);

        $tienda->update(['historias' => $ordenadas->all()]);
    }

    /**
     * Valida, re-encodea a WebP y persiste la imagen en el campo indicado.
     * Reemplaza (borra) la anterior si la había.
     *
     * @throws Exception si el archivo no pasa las validaciones de seguridad.
     */
    protected function procesar(Tienda $tienda, UploadedFile $file, string $campo, int $maxAncho, int $maxAlto): string
    {
        $this->validar($file);

        if ($tienda->{$campo}) {
            Storage::disk('public')->delete($tienda->{$campo});
        }

        $filename = Str::uuid()->toString().'.webp';
        $path = "tiendas/{$tienda->comercio_id}/{$filename}";

        try {
            $manager = new ImageManager(new Driver);
            $encoded = $manager->read($file->getRealPath())
                ->scaleDown(width: $maxAncho, height: $maxAlto)
                ->toWebp(quality: self::WEBP_QUALITY);
        } catch (\Throwable $e) {
            // Si Intervention no puede decodificar, el archivo no es una
            // imagen válida aunque finfo dijera que sí (polyglot files).
            Log::warning('ImagenTiendaService: fallo al decodificar imagen', [
                'tienda_id' => $tienda->id,
                'campo' => $campo,
                'error' => $e->getMessage(),
            ]);
            throw new Exception(__('La imagen no es válida o está corrupta.'));
        }

        Storage::disk('public')->put($path, (string) $encoded);

        $tienda->update([$campo => $path]);

        return $path;
    }

    protected function eliminar(Tienda $tienda, string $campo): void
    {
        if (! $tienda->{$campo}) {
            return;
        }

        Storage::disk('public')->delete($tienda->{$campo});
        $tienda->update([$campo => null]);
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
