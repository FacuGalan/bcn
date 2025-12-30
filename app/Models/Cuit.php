<?php

namespace App\Models;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo de CUIT
 *
 * Representa un CUIT del comercio con sus datos fiscales
 * para facturación electrónica AFIP.
 *
 * @property int $id
 * @property string $numero_cuit CUIT sin guiones (11 dígitos)
 * @property string $razon_social
 * @property string|null $nombre_fantasia
 * @property string|null $direccion
 * @property int|null $localidad_id
 * @property int $condicion_iva_id
 * @property string|null $numero_iibb
 * @property \Carbon\Carbon|null $fecha_inicio_actividades
 * @property \Carbon\Carbon|null $fecha_vencimiento_certificado
 * @property string $entorno_afip 'testing' o 'produccion'
 * @property bool $activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Cuit extends Model
{
    use SoftDeletes;

    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'cuits';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'numero_cuit',
        'razon_social',
        'nombre_fantasia',
        'direccion',
        'localidad_id',
        'condicion_iva_id',
        'numero_iibb',
        'fecha_inicio_actividades',
        'fecha_vencimiento_certificado',
        'entorno_afip',
        'certificado_path',
        'clave_path',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'fecha_inicio_actividades' => 'date',
        'fecha_vencimiento_certificado' => 'date',
        'activo' => 'boolean',
        'localidad_id' => 'integer',
        'condicion_iva_id' => 'integer',
    ];

    /**
     * Relación con puntos de venta
     */
    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    /**
     * Relación con localidad (tabla en config)
     */
    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }

    /**
     * Relación con condición de IVA (tabla en config)
     */
    public function condicionIva(): BelongsTo
    {
        return $this->belongsTo(CondicionIva::class);
    }

    /**
     * Relación con sucursales
     */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'cuit_sucursal')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    /**
     * Scope para CUITs activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para CUITs en producción
     */
    public function scopeEnProduccion($query)
    {
        return $query->where('entorno_afip', 'produccion');
    }

    /**
     * Scope para CUITs en testing
     */
    public function scopeEnTesting($query)
    {
        return $query->where('entorno_afip', 'testing');
    }

    /**
     * Obtiene el CUIT formateado (XX-XXXXXXXX-X)
     */
    public function getCuitFormateadoAttribute(): string
    {
        $cuit = $this->numero_cuit;
        if (strlen($cuit) !== 11) {
            return $cuit;
        }

        return substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10, 1);
    }

    /**
     * Valida un número de CUIT
     */
    public static function validarCuit(string $cuit): bool
    {
        $cuit = preg_replace('/\D/', '', $cuit);

        if (strlen($cuit) !== 11) {
            return false;
        }

        $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$cuit[$i] * $multiplicadores[$i];
        }

        $resto = $suma % 11;
        $digitoVerificador = 11 - $resto;

        if ($digitoVerificador === 11) {
            $digitoVerificador = 0;
        } elseif ($digitoVerificador === 10) {
            $digitoVerificador = 9;
        }

        return (int)$cuit[10] === $digitoVerificador;
    }

    /**
     * Verifica si está en producción
     */
    public function isEnProduccion(): bool
    {
        return $this->entorno_afip === 'produccion';
    }

    /**
     * Verifica si está en testing
     */
    public function isEnTesting(): bool
    {
        return $this->entorno_afip === 'testing';
    }

    /**
     * Verifica si el certificado está vigente
     */
    public function tieneCertificadoVigente(): bool
    {
        if (!$this->fecha_vencimiento_certificado) {
            return false;
        }

        return $this->fecha_vencimiento_certificado->isFuture();
    }

    /**
     * Obtiene los días hasta el vencimiento del certificado
     */
    public function diasHastaVencimientoCertificado(): ?int
    {
        if (!$this->fecha_vencimiento_certificado) {
            return null;
        }

        return now()->diffInDays($this->fecha_vencimiento_certificado, false);
    }

    /**
     * Verifica si tiene puntos de venta configurados
     */
    public function tienePuntosVenta(): bool
    {
        return $this->puntosVenta()->activos()->exists();
    }

    /**
     * Obtiene el nombre para mostrar
     */
    public function getNombreMostrarAttribute(): string
    {
        return $this->nombre_fantasia ?: $this->razon_social;
    }

    // ==================== MANEJO DE CERTIFICADOS ====================

    /**
     * Verifica si tiene certificados configurados
     */
    public function tieneCertificados(): bool
    {
        return !empty($this->certificado_path) && !empty($this->clave_path);
    }

    /**
     * Guarda el certificado encriptado
     */
    public function guardarCertificado($file): void
    {
        // Eliminar certificado anterior si existe
        if ($this->certificado_path) {
            Storage::disk('local')->delete($this->certificado_path);
        }

        $path = $this->guardarArchivoEncriptado($file, 'certificado');
        $this->certificado_path = $path;
        $this->save();
    }

    /**
     * Guarda la clave privada encriptada
     */
    public function guardarClave($file): void
    {
        // Eliminar clave anterior si existe
        if ($this->clave_path) {
            Storage::disk('local')->delete($this->clave_path);
        }

        $path = $this->guardarArchivoEncriptado($file, 'clave');
        $this->clave_path = $path;
        $this->save();
    }

    /**
     * Obtiene el contenido del certificado desencriptado
     */
    public function getCertificadoContenido(): ?string
    {
        if (!$this->certificado_path) {
            return null;
        }

        return $this->obtenerArchivoDesencriptado($this->certificado_path);
    }

    /**
     * Obtiene el contenido de la clave desencriptada
     */
    public function getClaveContenido(): ?string
    {
        if (!$this->clave_path) {
            return null;
        }

        return $this->obtenerArchivoDesencriptado($this->clave_path);
    }

    /**
     * Elimina los archivos de certificados
     */
    public function eliminarCertificados(): void
    {
        if ($this->certificado_path) {
            Storage::disk('local')->delete($this->certificado_path);
            $this->certificado_path = null;
        }

        if ($this->clave_path) {
            Storage::disk('local')->delete($this->clave_path);
            $this->clave_path = null;
        }

        $this->save();
    }

    /**
     * Guarda un archivo encriptado
     */
    protected function guardarArchivoEncriptado($file, string $tipo): string
    {
        $comercioId = app(TenantService::class)->getComercioId();
        $contenido = file_get_contents($file->getRealPath());
        $contenidoEncriptado = encrypt($contenido);

        $directorio = "certificados/{$comercioId}/{$this->id}";
        $nombreArchivo = "{$tipo}_" . time() . '.enc';
        $path = "{$directorio}/{$nombreArchivo}";

        Storage::disk('local')->put($path, $contenidoEncriptado);

        return $path;
    }

    /**
     * Obtiene un archivo desencriptado
     */
    protected function obtenerArchivoDesencriptado(string $path): ?string
    {
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $contenidoEncriptado = Storage::disk('local')->get($path);

        try {
            return decrypt($contenidoEncriptado);
        } catch (\Exception $e) {
            return null;
        }
    }
}
