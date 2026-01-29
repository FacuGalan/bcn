<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Comprobante Fiscal
 *
 * Representa un comprobante fiscal emitido ante AFIP (factura, nota de crédito, etc.)
 */
class ComprobanteFiscal extends Model
{
    use SoftDeletes;

    protected $connection = 'pymes_tenant';
    protected $table = 'comprobantes_fiscales';

    protected $fillable = [
        'sucursal_id',
        'punto_venta_id',
        'cuit_id',
        'tipo',
        'letra',
        'punto_venta_numero',
        'numero_comprobante',
        'cae',
        'cae_vencimiento',
        'fecha_emision',
        'fecha_servicio_desde',
        'fecha_servicio_hasta',
        'cliente_id',
        'condicion_iva_id',
        'receptor_nombre',
        'receptor_documento_tipo',
        'receptor_documento_numero',
        'receptor_domicilio',
        'neto_gravado',
        'neto_no_gravado',
        'neto_exento',
        'iva_total',
        'tributos',
        'total',
        'moneda',
        'cotizacion',
        'estado',
        'afip_response',
        'afip_observaciones',
        'afip_errores',
        'comprobante_asociado_id',
        'usuario_id',
        'observaciones',
        'es_total_venta', // true = factura por el total, false = factura parcial (mixto)
    ];

    protected $casts = [
        'cae_vencimiento' => 'date',
        'fecha_emision' => 'date',
        'fecha_servicio_desde' => 'date',
        'fecha_servicio_hasta' => 'date',
        'neto_gravado' => 'decimal:2',
        'neto_no_gravado' => 'decimal:2',
        'neto_exento' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'tributos' => 'decimal:2',
        'total' => 'decimal:2',
        'cotizacion' => 'decimal:6',
        'punto_venta_numero' => 'integer',
        'numero_comprobante' => 'integer',
        'es_total_venta' => 'boolean',
    ];

    // ==================== Relaciones ====================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function cuit(): BelongsTo
    {
        return $this->belongsTo(Cuit::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function comprobanteAsociado(): BelongsTo
    {
        return $this->belongsTo(ComprobanteFiscal::class, 'comprobante_asociado_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(ComprobanteFiscal::class, 'comprobante_asociado_id')
            ->whereIn('tipo', ['nota_credito_a', 'nota_credito_b', 'nota_credito_c']);
    }

    public function detallesIva(): HasMany
    {
        return $this->hasMany(ComprobanteFiscalIva::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteFiscalItem::class);
    }

    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class, 'comprobante_fiscal_ventas')
            ->withPivot(['monto', 'es_anulacion', 'created_at']);
    }

    public function comprobanteFiscalVentas(): HasMany
    {
        return $this->hasMany(ComprobanteFiscalVenta::class);
    }

    /**
     * Pagos de venta asociados a este comprobante fiscal
     */
    public function pagosFacturados(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'comprobante_fiscal_id');
    }

    // ==================== Scopes ====================

    public function scopeAutorizados($query)
    {
        return $query->where('estado', 'autorizado');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeFacturas($query)
    {
        return $query->whereIn('tipo', [
            'factura_a', 'factura_b', 'factura_c', 'factura_e', 'factura_m'
        ]);
    }

    public function scopeNotasCredito($query)
    {
        return $query->whereIn('tipo', [
            'nota_credito_a', 'nota_credito_b', 'nota_credito_c', 'nota_credito_e', 'nota_credito_m'
        ]);
    }

    // ==================== Métodos ====================

    /**
     * Obtiene el número de comprobante formateado (0001-00000001)
     */
    public function getNumeroFormateadoAttribute(): string
    {
        return sprintf('%04d-%08d', $this->punto_venta_numero, $this->numero_comprobante);
    }

    /**
     * Obtiene el tipo legible
     */
    public function getTipoLegibleAttribute(): string
    {
        $tipos = [
            'factura_a' => 'Factura A',
            'factura_b' => 'Factura B',
            'factura_c' => 'Factura C',
            'factura_e' => 'Factura E',
            'factura_m' => 'Factura M',
            'nota_credito_a' => 'Nota de Crédito A',
            'nota_credito_b' => 'Nota de Crédito B',
            'nota_credito_c' => 'Nota de Crédito C',
            'nota_debito_a' => 'Nota de Débito A',
            'nota_debito_b' => 'Nota de Débito B',
            'nota_debito_c' => 'Nota de Débito C',
            'recibo_a' => 'Recibo A',
            'recibo_b' => 'Recibo B',
            'recibo_c' => 'Recibo C',
        ];

        return $tipos[$this->tipo] ?? $this->tipo;
    }

    /**
     * Verifica si está autorizado
     */
    public function estaAutorizado(): bool
    {
        return $this->estado === 'autorizado';
    }

    /**
     * Verifica si es una factura
     */
    public function esFactura(): bool
    {
        return str_starts_with($this->tipo, 'factura_');
    }

    /**
     * Verifica si es una nota de crédito
     */
    public function esNotaCredito(): bool
    {
        return str_starts_with($this->tipo, 'nota_credito_');
    }

    /**
     * Verifica si el CAE está vigente
     */
    public function tieneCAEVigente(): bool
    {
        return $this->cae && $this->cae_vencimiento && $this->cae_vencimiento->isFuture();
    }
}
