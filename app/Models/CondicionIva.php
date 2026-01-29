<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Condición de IVA
 *
 * Representa las condiciones de IVA definidas por AFIP.
 * Esta es una tabla de referencia compartida en la base de datos config.
 *
 * @property int $id
 * @property int $codigo Código AFIP (1-14)
 * @property string $nombre
 * @property string|null $descripcion
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CondicionIva extends Model
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'config';

    /**
     * Nombre de la tabla
     */
    protected $table = 'condiciones_iva';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'codigo' => 'integer',
    ];

    // Constantes para los códigos de condición IVA
    public const RESPONSABLE_INSCRIPTO = 1;
    public const RESPONSABLE_NO_INSCRIPTO = 2;
    public const NO_RESPONSABLE = 3;
    public const SUJETO_EXENTO = 4;
    public const CONSUMIDOR_FINAL = 5;
    public const RESPONSABLE_MONOTRIBUTO = 6;
    public const SUJETO_NO_CATEGORIZADO = 7;
    public const PROVEEDOR_EXTERIOR = 8;
    public const CLIENTE_EXTERIOR = 9;
    public const IVA_LIBERADO_LEY_19640 = 10;
    public const AGENTE_PERCEPCION = 11;
    public const PEQUENO_CONTRIBUYENTE_EVENTUAL = 12;
    public const MONOTRIBUTISTA_SOCIAL = 13;
    public const PEQUENO_CONTRIBUYENTE_EVENTUAL_SOCIAL = 14;
    public const MONOTRIBUTO_TRABAJADOR_INDEPENDIENTE_PROMOVIDO = 16;

    /**
     * Scope para ordenar por código
     */
    public function scopeOrdenadas($query)
    {
        return $query->orderBy('codigo');
    }

    /**
     * Scope para buscar por código
     */
    public function scopePorCodigo($query, int $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    /**
     * Verifica si es Responsable Inscripto
     */
    public function esResponsableInscripto(): bool
    {
        return $this->codigo === self::RESPONSABLE_INSCRIPTO;
    }

    /**
     * Verifica si es Consumidor Final
     */
    public function esConsumidorFinal(): bool
    {
        return $this->codigo === self::CONSUMIDOR_FINAL;
    }

    /**
     * Verifica si es Monotributista (cualquier variante)
     * Códigos válidos para Factura A según AFIP: 6, 13, 16
     */
    public function esMonotributista(): bool
    {
        return in_array($this->codigo, [
            self::RESPONSABLE_MONOTRIBUTO,              // 6
            self::MONOTRIBUTISTA_SOCIAL,                 // 13
            self::MONOTRIBUTO_TRABAJADOR_INDEPENDIENTE_PROMOVIDO, // 16
        ]);
    }

    /**
     * Verifica si es Exento
     */
    public function esExento(): bool
    {
        return $this->codigo === self::SUJETO_EXENTO;
    }

    /**
     * Verifica si requiere CUIT para facturación
     */
    public function requiereCuit(): bool
    {
        return !in_array($this->codigo, [
            self::CONSUMIDOR_FINAL,
            self::SUJETO_NO_CATEGORIZADO,
        ]);
    }
}
