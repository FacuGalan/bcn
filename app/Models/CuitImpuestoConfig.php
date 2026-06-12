<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración impositiva de un CUIT (RF-02 sistema-impositivo).
 *
 * Define si el CUIT está alcanzado por un impuesto del catálogo, con qué
 * alícuota (origen manual; padrón provincial = fase futura, D3), si actúa
 * como agente de percepción/retención, y la vigencia. La condición de IVA
 * vive en cuits.condicion_iva_id.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-02).
 */
class CuitImpuestoConfig extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cuit_impuesto_configs';

    protected $fillable = [
        'cuit_id',
        'impuesto_id',
        'inscripto',
        'numero_inscripcion',
        'es_agente_percepcion',
        'es_agente_retencion',
        'alicuota',
        'alicuota_minimo_base',
        'origen_alicuota',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'inscripto' => 'boolean',
        'es_agente_percepcion' => 'boolean',
        'es_agente_retencion' => 'boolean',
        'alicuota' => 'decimal:4',
        'alicuota_minimo_base' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    // Orígenes de alícuota (D3: padrón = integración futura ARBA/AGIP).
    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PADRON = 'padron';

    // ==================
    // RELACIONES
    // ==================

    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class)->withTrashed();
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }

    // ==================
    // SCOPES
    // ==================

    /**
     * Configs vigentes a una fecha (sin vigencia definida = siempre vigente).
     */
    public function scopeVigentes($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $fecha))
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha));
    }

    // ==================
    // HELPERS
    // ==================

    public function estaInscripto(): bool
    {
        return $this->inscripto === true;
    }
}
