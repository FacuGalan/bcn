<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCuentaEmpresa extends Model
{
    protected $connection = 'pymes_tenant';

    protected $table = 'movimientos_cuenta_empresa';

    const ORIGEN_VENTA_PAGO = 'VentaPago';

    const ORIGEN_COBRO_PAGO = 'CobroPago';

    const ORIGEN_TRANSFERENCIA = 'TransferenciaCuentaEmpresa';

    const ORIGEN_DEPOSITO = 'DepositoBancario';

    const ORIGEN_MANUAL = 'Manual';

    protected $fillable = [
        'cuenta_empresa_id',
        'tipo',
        'concepto_movimiento_cuenta_id',
        'concepto_descripcion',
        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'origen_tipo',
        'origen_id',
        'usuario_id',
        'sucursal_id',
        'estado',
        'anulado_por_movimiento_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    // ==================== Relaciones ====================

    public function cuentaEmpresa(): BelongsTo
    {
        return $this->belongsTo(CuentaEmpresa::class, 'cuenta_empresa_id');
    }

    public function conceptoMovimiento(): BelongsTo
    {
        return $this->belongsTo(ConceptoMovimientoCuenta::class, 'concepto_movimiento_cuenta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function movimientoAnulacion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anulado_por_movimiento_id');
    }

    /**
     * El movimiento original que este contraasiento anuló
     */
    public function movimientoAnulado()
    {
        return $this->hasOne(self::class, 'anulado_por_movimiento_id');
    }

    // ==================== Scopes ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeAnulados($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }

    public function scopePorCuenta($query, int $cuentaId)
    {
        return $query->where('cuenta_empresa_id', $cuentaId);
    }

    public function scopePorFecha($query, $desde, $hasta)
    {
        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $query->where('created_at', '<=', $hasta);
        }

        return $query;
    }

    public function scopePorConcepto($query, int $conceptoId)
    {
        return $query->where('concepto_movimiento_cuenta_id', $conceptoId);
    }

    public function scopePorOrigen($query, string $tipo, ?int $id = null)
    {
        $query->where('origen_tipo', $tipo);
        if ($id !== null) {
            $query->where('origen_id', $id);
        }

        return $query;
    }

    // ==================== Métodos ====================

    public function esActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function esAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    public function esIngreso(): bool
    {
        return $this->tipo === 'ingreso';
    }

    public function esEgreso(): bool
    {
        return $this->tipo === 'egreso';
    }

    public function esContraasiento(): bool
    {
        return $this->movimientoAnulado !== null;
    }

    /**
     * Crea un contraasiento que anula este movimiento (patrón append-only).
     *
     * El contraasiento queda con estado='activo' y el original pasa a
     * estado='anulado' + anulado_por_movimiento_id apuntando al contraasiento.
     * Ambos quedan en BD y se cancelan matemáticamente.
     *
     * Actualiza el saldo_actual de la cuenta. Asume que la cuenta está
     * bloqueada (lockForUpdate) por el caller cuando aplica.
     *
     * @throws \Exception si el movimiento ya fue anulado.
     */
    public static function crearContraasiento(
        self $movimientoOriginal,
        string $motivo,
        int $usuarioId
    ): self {
        if ($movimientoOriginal->esAnulado()) {
            throw new \Exception('El movimiento ya fue anulado');
        }

        $cuenta = CuentaEmpresa::lockForUpdate()->find($movimientoOriginal->cuenta_empresa_id);

        $tipoInverso = $movimientoOriginal->tipo === 'ingreso' ? 'egreso' : 'ingreso';

        $saldoAnterior = $cuenta->saldo_actual;
        $saldoPosterior = $tipoInverso === 'ingreso'
            ? $saldoAnterior + $movimientoOriginal->monto
            : $saldoAnterior - $movimientoOriginal->monto;

        $contraasiento = static::create([
            'cuenta_empresa_id' => $cuenta->id,
            'tipo' => $tipoInverso,
            'concepto_movimiento_cuenta_id' => $movimientoOriginal->concepto_movimiento_cuenta_id,
            'concepto_descripcion' => "Anulación: {$motivo}",
            'monto' => $movimientoOriginal->monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'origen_tipo' => $movimientoOriginal->origen_tipo,
            'origen_id' => $movimientoOriginal->origen_id,
            'usuario_id' => $usuarioId,
            'sucursal_id' => $movimientoOriginal->sucursal_id,
            'estado' => 'activo',
            'observaciones' => $motivo,
        ]);

        $movimientoOriginal->update([
            'estado' => 'anulado',
            'anulado_por_movimiento_id' => $contraasiento->id,
        ]);

        $cuenta->update(['saldo_actual' => $saldoPosterior]);

        return $contraasiento;
    }
}
