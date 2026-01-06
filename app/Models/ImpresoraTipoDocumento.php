<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpresoraTipoDocumento extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'impresora_tipo_documento';

    public const TIPOS_DOCUMENTO = [
        'ticket_venta' => 'Ticket de Venta',
        'factura_a' => 'Factura A',
        'factura_b' => 'Factura B',
        'factura_c' => 'Factura C',
        'comanda' => 'Comanda',
        'precuenta' => 'Precuenta',
        'cierre_turno' => 'Cierre de Turno',
        'cierre_caja' => 'Cierre de Caja',
        'arqueo' => 'Arqueo',
        'recibo' => 'Recibo',
    ];

    public const TIPOS_FACTURA = ['factura_a', 'factura_b', 'factura_c'];
    public const TIPOS_VENTA = ['ticket_venta', 'factura_a', 'factura_b', 'factura_c'];
    public const TIPOS_CAJA = ['cierre_turno', 'cierre_caja', 'arqueo'];

    protected $fillable = [
        'impresora_sucursal_caja_id',
        'tipo_documento',
        'copias',
        'activo',
    ];

    protected $casts = [
        'copias' => 'integer',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(ImpresoraSucursalCaja::class, 'impresora_sucursal_caja_id');
    }

    // Métodos
    public function getTipoLegibleAttribute(): string
    {
        return self::TIPOS_DOCUMENTO[$this->tipo_documento] ?? $this->tipo_documento;
    }

    public function esFactura(): bool
    {
        return in_array($this->tipo_documento, self::TIPOS_FACTURA);
    }

    public function esTicket(): bool
    {
        return $this->tipo_documento === 'ticket_venta';
    }

    public function esCierre(): bool
    {
        return in_array($this->tipo_documento, self::TIPOS_CAJA);
    }
}
