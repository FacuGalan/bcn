<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocionEspecialEscala extends Model
{
    use HasFactory;

    protected $connection = 'pymes_tenant';
    protected $table = 'promocion_especial_escalas';

    protected $fillable = [
        'promocion_especial_id',
        'cantidad_desde',
        'cantidad_hasta',
        'lleva',
        'paga',
        'bonifica',
        'beneficio_tipo',
        'beneficio_porcentaje',
    ];

    protected $casts = [
        'cantidad_desde' => 'integer',
        'cantidad_hasta' => 'integer',
        'lleva' => 'integer',
        'paga' => 'integer',
        'bonifica' => 'integer',
        'beneficio_porcentaje' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function promocionEspecial()
    {
        return $this->belongsTo(PromocionEspecial::class);
    }

    // ==================== Helpers ====================

    /**
     * Retorna una descripción legible de la escala
     * Ejemplo: "Lleva 3 → 1 gratis (desde 3 unidades)"
     */
    public function getDescripcionAttribute(): string
    {
        $desc = "Lleva {$this->lleva} → {$this->bonifica}";

        if ($this->beneficio_tipo === 'gratis') {
            $desc .= ' gratis';
        } else {
            $desc .= " con {$this->beneficio_porcentaje}% dto";
        }

        if ($this->cantidad_hasta) {
            $desc .= " (de {$this->cantidad_desde} a {$this->cantidad_hasta} u.)";
        } else {
            $desc .= " (desde {$this->cantidad_desde} u.)";
        }

        return $desc;
    }

    /**
     * Verifica si esta escala aplica para una cantidad dada
     */
    public function aplicaParaCantidad(int $cantidad): bool
    {
        if ($cantidad < $this->cantidad_desde) {
            return false;
        }

        if ($this->cantidad_hasta !== null && $cantidad > $this->cantidad_hasta) {
            return false;
        }

        return true;
    }

    /**
     * Calcula cuántas unidades se bonifican para una cantidad dada
     */
    public function calcularUnidadesBonificadas(int $cantidad): int
    {
        if (!$this->aplicaParaCantidad($cantidad)) {
            return 0;
        }

        $packsCompletos = intdiv($cantidad, $this->lleva);
        return $packsCompletos * $this->bonifica;
    }

    /**
     * Calcula cuántas unidades paga el cliente (precio completo) para una cantidad dada
     * Si es beneficio gratis: paga = total - bonificadas
     * Si es beneficio descuento: paga = total (pero algunas con descuento)
     */
    public function calcularUnidadesAPagar(int $cantidad): int
    {
        if (!$this->aplicaParaCantidad($cantidad)) {
            return $cantidad;
        }

        if ($this->beneficio_tipo === 'descuento') {
            // Todas las unidades se pagan, pero algunas con descuento
            return $cantidad;
        }

        // Beneficio gratis: algunas unidades no se pagan
        return $cantidad - $this->calcularUnidadesBonificadas($cantidad);
    }

    /**
     * Retorna el porcentaje de descuento efectivo
     * Para gratis: 100%, para descuento: el porcentaje configurado
     */
    public function getPorcentajeDescuentoEfectivo(): float
    {
        return $this->beneficio_tipo === 'gratis' ? 100 : (float) $this->beneficio_porcentaje;
    }
}
