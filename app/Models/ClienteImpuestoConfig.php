<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil fiscal de un cliente (RF-13 sistema-impositivo, Fase 10a).
 *
 * Espejo de CuitImpuestoConfig pero por CLIENTE y con semántica de SUJETO
 * PERCIBIDO (no de agente): define la percepción de IIBB que se le aplica a este
 * cliente — exención, alícuota por sujeto (override manual o del padrón ARBA/AGIP),
 * N° de inscripción/constancia y vigencia.
 *
 * Consumidor único: ImpuestoService::calcularTributos (refina la percepción 5b
 * que hoy aplica alícuota fija por jurisdicción del agente a todo RI).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-13, Fase 10a).
 */
class ClienteImpuestoConfig extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'cliente_impuesto_configs';

    protected $fillable = [
        'cliente_id',
        'impuesto_id',
        'exento',
        'alicuota',
        'alicuota_minimo_base',
        'numero_padron',
        'origen_alicuota',
        'vigente_desde',
        'vigente_hasta',
        'datos_extra',
    ];

    protected $casts = [
        'exento' => 'boolean',
        'alicuota' => 'decimal:4',
        'alicuota_minimo_base' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'datos_extra' => 'array',
    ];

    // Orígenes de alícuota (padrón = importador ARBA/AGIP, Fase 10b).
    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PADRON = 'padron';

    // ==================
    // RELACIONES
    // ==================

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
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

    public function estaExento(): bool
    {
        return $this->exento === true;
    }
}
