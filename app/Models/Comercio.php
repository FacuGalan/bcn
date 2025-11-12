<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo Comercio
 *
 * Representa un comercio/PYME en el sistema. Cada comercio tiene sus propias tablas
 * en la base de datos PYMES con prefijo basado en su ID (formato: 000001_*).
 * Los comercios pueden tener múltiples usuarios asociados y viceversa.
 *
 * @package App\Models
 * @author BCN Pymes
 * @version 1.0.0
 *
 * @property int $id ID único del comercio
 * @property string $mail Email del comercio (usado para login)
 * @property string $nombre Nombre comercial del negocio
 * @property string $database_name Base de datos donde se almacenan las tablas del comercio
 * @property \Illuminate\Support\Carbon $created_at Fecha de creación
 * @property \Illuminate\Support\Carbon $updated_at Fecha de última actualización
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users Usuarios asociados
 * @property-read int $users_count Cantidad de usuarios asociados
 */
class Comercio extends Model
{
    use HasFactory;

    /**
     * Conexión de base de datos a utilizar
     *
     * @var string
     */
    protected $connection = 'config';

    /**
     * Nombre de la tabla en la base de datos
     *
     * @var string
     */
    protected $table = 'comercios';

    /**
     * Atributos asignables en masa
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'mail',
        'nombre',
        'database_name',
        'max_usuarios',
    ];

    /**
     * Atributos que deben ser casteados a tipos nativos
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación many-to-many con usuarios
     *
     * Un comercio puede tener múltiples usuarios y un usuario puede
     * pertenecer a múltiples comercios.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comercio_user', 'comercio_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Obtiene el prefijo de las tablas para este comercio en la base PYMES
     *
     * Formato: 000001_ (ID con 6 dígitos)
     *
     * @return string Prefijo del comercio (ej: "000001_")
     */
    public function getTablePrefix(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT) . '_';
    }

    /**
     * Obtiene el ID del comercio formateado con 6 dígitos
     *
     * @return string ID formateado (ej: "000001")
     */
    public function getFormattedId(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica si un usuario tiene acceso a este comercio
     *
     * @param int|User $user Usuario o ID del usuario
     * @return bool
     */
    public function hasUser($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Obtiene la cantidad actual de usuarios asociados al comercio
     *
     * @return int
     */
    public function getCurrentUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Verifica si el comercio puede agregar más usuarios
     *
     * @return bool
     */
    public function canAddMoreUsers(): bool
    {
        return $this->getCurrentUsersCount() < $this->max_usuarios;
    }

    /**
     * Obtiene la cantidad de usuarios disponibles que se pueden agregar
     *
     * @return int
     */
    public function getRemainingUsersSlots(): int
    {
        return max(0, $this->max_usuarios - $this->getCurrentUsersCount());
    }

    /**
     * Obtiene las sucursales del comercio
     *
     * NOTA: Este modelo está en la conexión 'config', pero sucursales está en 'pymes_tenant'.
     * Para obtener las sucursales, usar Sucursal::all() después de establecer el comercio activo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sucursales()
    {
        // Usamos HasMany con conexión tenant
        return $this->hasMany(\App\Models\Sucursal::class, 'comercio_id', 'id');
    }

    /**
     * Obtiene la sucursal principal del comercio
     *
     * @return \App\Models\Sucursal|null
     */
    public function sucursalPrincipal()
    {
        return $this->sucursales()->where('es_principal', true)->first();
    }

    /**
     * Obtiene solo las sucursales activas del comercio
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function sucursalesActivas()
    {
        return $this->sucursales()->where('activa', true)->get();
    }
}
