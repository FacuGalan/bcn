<?php

namespace Tests\Traits;

use App\Models\Articulo;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\Receta;
use App\Models\RecetaIngrediente;
use App\Models\Stock;
use App\Models\TipoIva;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;

/**
 * Helpers reutilizables para los tests del módulo de ventas.
 *
 * Requiere: WithTenant, WithSucursal.
 */
trait WithVentaHelpers
{
    protected array $tiposIva = [];

    protected function crearTiposIva(): array
    {
        $this->tiposIva = [];

        foreach ([
            ['codigo' => 5, 'nombre' => 'IVA 21%', 'porcentaje' => 21.00],
            ['codigo' => 4, 'nombre' => 'IVA 10.5%', 'porcentaje' => 10.50],
            ['codigo' => 3, 'nombre' => 'IVA 0%', 'porcentaje' => 0.00],
        ] as $data) {
            $tipoIva = TipoIva::create(array_merge($data, ['activo' => true]));
            $this->tiposIva[$data['codigo']] = $tipoIva;
        }

        return $this->tiposIva;
    }

    protected function crearArticuloConStock(
        int $sucursalId,
        float $cantidad = 100,
        string $modoStock = 'unitario',
        array $overrides = []
    ): Articulo {
        $tipoIva = $this->tiposIva[5] ?? TipoIva::first();

        $articulo = Articulo::create(array_merge([
            'codigo' => 'ART-'.uniqid(),
            'nombre' => 'Artículo Test '.uniqid(),
            'precio_base' => 1000.00,
            'tipo_iva_id' => $tipoIva->id,
            'precio_iva_incluido' => true,
            'activo' => true,
            'unidad_medida' => 'unidad',
        ], $overrides));

        // Vincular artículo a sucursal
        DB::connection('pymes_tenant')->table('articulos_sucursales')->insert([
            'articulo_id' => $articulo->id,
            'sucursal_id' => $sucursalId,
            'activo' => true,
            'modo_stock' => $modoStock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear registro de stock
        if ($modoStock !== 'ninguno') {
            Stock::create([
                'articulo_id' => $articulo->id,
                'sucursal_id' => $sucursalId,
                'cantidad' => $cantidad,
                'cantidad_minima' => null,
                'cantidad_maxima' => null,
                'ultima_actualizacion' => now(),
            ]);
        }

        return $articulo;
    }

    protected function crearArticuloConReceta(
        int $sucursalId,
        array $ingredientes = [],
        array $overridesArticulo = []
    ): Articulo {
        $articulo = $this->crearArticuloConStock($sucursalId, 0, 'receta', $overridesArticulo);

        $receta = Receta::create([
            'recetable_type' => 'Articulo',
            'recetable_id' => $articulo->id,
            'sucursal_id' => null,
            'cantidad_producida' => 1,
            'activo' => true,
        ]);

        foreach ($ingredientes as $ingredienteData) {
            $ingredienteArticulo = $ingredienteData['articulo'] ?? $this->crearArticuloConStock(
                $sucursalId,
                $ingredienteData['stock'] ?? 100,
                'unitario',
                ['nombre' => 'Ingrediente '.uniqid(), 'precio_base' => 100],
            );

            RecetaIngrediente::create([
                'receta_id' => $receta->id,
                'articulo_id' => $ingredienteArticulo->id,
                'cantidad' => $ingredienteData['cantidad'] ?? 1,
            ]);
        }

        return $articulo;
    }

    protected function crearCajaAbierta(int $sucursalId, array $overrides = []): Caja
    {
        return Caja::create(array_merge([
            'sucursal_id' => $sucursalId,
            'numero' => rand(1, 999),
            'nombre' => 'Caja Test '.uniqid(),
            'codigo' => 'CAJA-'.uniqid(),
            'estado' => 'abierta',
            'activo' => true,
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'fecha_apertura' => now(),
        ], $overrides));
    }

    protected function crearFormaPagoEfectivo(): array
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => ConceptoPago::EFECTIVO],
            [
                'nombre' => 'Efectivo',
                'permite_cuotas' => false,
                'permite_vuelto' => true,
                'activo' => true,
                'orden' => 1,
            ]
        );

        $formaPago = FormaPago::create([
            'nombre' => 'Efectivo',
            'codigo' => 'efectivo',
            'concepto' => 'efectivo',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        return compact('concepto', 'formaPago');
    }

    protected function crearFormaPagoCC(): array
    {
        $concepto = ConceptoPago::where('codigo', ConceptoPago::CREDITO_CLIENTE)->first();
        if (! $concepto) {
            $concepto = ConceptoPago::create([
                'codigo' => ConceptoPago::CREDITO_CLIENTE,
                'nombre' => 'Crédito Cliente',
                'permite_cuotas' => false,
                'permite_vuelto' => false,
                'activo' => true,
                'orden' => 7,
            ]);
        }

        $formaPago = FormaPago::create([
            'nombre' => 'Cuenta Corriente',
            'codigo' => 'cuenta_corriente',
            'concepto' => 'credito_cliente',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        return compact('concepto', 'formaPago');
    }

    protected function crearClienteConCC(
        int $sucursalId,
        float $limiteCredito = 100000,
        array $overrides = []
    ): Cliente {
        $cliente = Cliente::create(array_merge([
            'nombre' => 'Cliente Test '.uniqid(),
            'activo' => true,
            'tiene_cuenta_corriente' => true,
            'limite_credito' => $limiteCredito,
            'dias_credito' => 30,
            'tasa_interes_mensual' => 5.00,
            'saldo_deudor_cache' => 0,
            'saldo_a_favor_cache' => 0,
            'bloqueado_por_mora' => false,
        ], $overrides));

        // Vincular cliente a sucursal
        DB::connection('pymes_tenant')->table('clientes_sucursales')->insert([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $sucursalId,
            'saldo_actual' => 0,
            'limite_credito' => $limiteCredito,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cliente;
    }

    protected function crearVentaBasica(array $overrides = []): Venta
    {
        $articulo = $overrides['_articulo'] ?? $this->crearArticuloConStock($this->sucursalId, 100);
        $caja = $overrides['_caja'] ?? $this->crearCajaAbierta($this->sucursalId);
        unset($overrides['_articulo'], $overrides['_caja']);

        $venta = Venta::create(array_merge([
            'numero' => '0001-'.str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT),
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'usuario_id' => 1,
            'fecha' => now(),
            'subtotal' => 1000,
            'iva' => 0,
            'descuento' => 0,
            'total' => 1000,
            'ajuste_forma_pago' => 0,
            'total_final' => 1000,
            'estado' => 'completada',
            'es_cuenta_corriente' => false,
            'saldo_pendiente_cache' => 0,
        ], $overrides));

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 826.45,
            'descuento' => 0,
            'iva_monto' => 173.55,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        return $venta;
    }

    protected function crearVentaCC(int $clienteId, float $monto, array $overrides = []): Venta
    {
        $venta = $this->crearVentaBasica(array_merge([
            'cliente_id' => $clienteId,
            'es_cuenta_corriente' => true,
            'estado' => 'pendiente',
            'subtotal' => $monto,
            'total' => $monto,
            'total_final' => $monto,
            'saldo_pendiente_cache' => $monto,
        ], $overrides));

        $ccData = $this->crearFormaPagoCC();

        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $ccData['formaPago']->id,
            'concepto_pago_id' => $ccData['concepto']->id,
            'monto_base' => $monto,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $monto,
            'saldo_pendiente' => $monto,
            'es_cuenta_corriente' => true,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        return $venta;
    }

    protected function datosVentaBase(array $overrides = []): array
    {
        return array_merge([
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId ?? null,
            'usuario_id' => 1,
            'fecha' => now()->toDateString(),
        ], $overrides);
    }

    protected function detalleVentaBase(int $articuloId, array $overrides = []): array
    {
        $articulo = Articulo::find($articuloId);
        $tipoIva = $articulo->tipoIva;
        $precio = (float) $articulo->precio_base;

        return array_merge([
            'articulo_id' => $articuloId,
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'tipo_iva_id' => $tipoIva->id,
            'iva_porcentaje' => (float) $tipoIva->porcentaje,
            'precio_sin_iva' => round($precio / (1 + $tipoIva->porcentaje / 100), 2),
            'iva_monto' => round($precio - $precio / (1 + $tipoIva->porcentaje / 100), 2),
            'subtotal' => $precio,
            'total' => $precio,
            'descuento' => 0,
        ], $overrides);
    }
}
