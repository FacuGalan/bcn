<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionImpresion extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'configuracion_impresion';

    protected $fillable = [
        'sucursal_id',
        'impresion_automatica_venta',
        'impresion_automatica_factura',
        'abrir_cajon_efectivo',
        'cortar_papel_automatico',
        'logo_ticket_path',
        'texto_pie_ticket',
        'texto_legal_factura',
    ];

    protected $casts = [
        'impresion_automatica_venta' => 'boolean',
        'impresion_automatica_factura' => 'boolean',
        'abrir_cajon_efectivo' => 'boolean',
        'cortar_papel_automatico' => 'boolean',
    ];

    // Relaciones
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    // Métodos estáticos
    public static function obtenerParaSucursal(int $sucursalId): self
    {
        return self::firstOrCreate(
            ['sucursal_id' => $sucursalId],
            [
                'impresion_automatica_venta' => true,
                'impresion_automatica_factura' => true,
                'abrir_cajon_efectivo' => true,
                'cortar_papel_automatico' => true,
                'texto_pie_ticket' => '¡Gracias por su compra!',
            ]
        );
    }

    // Métodos
    public function debeImprimirTicketAutomatico(): bool
    {
        return $this->impresion_automatica_venta;
    }

    public function debeImprimirFacturaAutomatico(): bool
    {
        return $this->impresion_automatica_factura;
    }

    public function debeAbrirCajon(): bool
    {
        return $this->abrir_cajon_efectivo;
    }

    public function debeCortarPapel(): bool
    {
        return $this->cortar_papel_automatico;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_ticket_path) {
            return null;
        }
        return asset('storage/' . $this->logo_ticket_path);
    }
}
