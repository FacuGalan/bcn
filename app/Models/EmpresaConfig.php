<?php

namespace App\Models;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo de Configuración de Empresa
 *
 * Almacena los datos generales del comercio.
 * Solo debe existir un registro por comercio.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $logo_path
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EmpresaConfig extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Nombre de la tabla
     */
    protected $table = 'empresa_config';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'logo_path',
    ];

    /**
     * Obtiene o crea la configuración de empresa
     * Solo debe existir un registro por comercio
     */
    public static function getConfig(): self
    {
        $config = static::first();

        if (!$config) {
            $config = static::create([
                'nombre' => 'Mi Empresa',
            ]);
        }

        return $config;
    }

    /**
     * Obtiene la URL del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Actualiza el logo de la empresa
     */
    public function updateLogo($file): void
    {
        // Eliminar logo anterior si existe
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }

        // Guardar nuevo logo
        $comercioId = app(TenantService::class)->getComercioId();
        $path = $file->store("logos/{$comercioId}/empresa", 'public');

        $this->logo_path = $path;
        $this->save();
    }

    /**
     * Elimina el logo
     */
    public function deleteLogo(): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
            $this->logo_path = null;
            $this->save();
        }
    }

    /**
     * Verifica si tiene logo
     */
    public function hasLogo(): bool
    {
        return !empty($this->logo_path) && Storage::disk('public')->exists($this->logo_path);
    }
}
