<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de impuestos argentinos (RF-01 sistema-impositivo).
 *
 * Tabla descriptiva seeded por el sistema: IVA débito/crédito, percepciones y
 * retenciones de IVA/IIBB (por jurisdicción ISO 3166-2), ganancias, ley
 * 25.413, SIRCREB. Si el comercio está alcanzado y con qué alícuota lo decide
 * CuitImpuestoConfig. Extensible con impuestos custom (es_sistema=false).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-01).
 */
class Impuesto extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'impuestos';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'naturaleza_default',
        'jurisdiccion',
        'es_sistema',
        'activo',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'activo' => 'boolean',
    ];

    // Tipos.
    public const TIPO_IVA = 'iva';

    public const TIPO_IIBB = 'iibb';

    public const TIPO_GANANCIAS = 'ganancias';

    public const TIPO_CREDITO_DEBITO = 'credito_debito';

    public const TIPO_OTRO = 'otro';

    // ==================
    // RELACIONES
    // ==================

    public function configs(): HasMany
    {
        return $this->hasMany(CuitImpuestoConfig::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeSistema($query)
    {
        return $query->where('es_sistema', true);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorCodigo($query, string $codigo)
    {
        return $query->where('codigo', $codigo);
    }

    // ==================
    // HELPERS
    // ==================

    public function esProvincial(): bool
    {
        return $this->jurisdiccion !== null && $this->jurisdiccion !== 'AR';
    }
}
