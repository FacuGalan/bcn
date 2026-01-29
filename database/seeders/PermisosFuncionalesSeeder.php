<?php

namespace Database\Seeders;

use App\Models\PermisoFuncional;
use Illuminate\Database\Seeder;

class PermisosFuncionalesSeeder extends Seeder
{
    /**
     * Permisos funcionales del sistema agrupados por categoría.
     *
     * Cada permiso tiene:
     * - codigo: identificador único (se usa como func.{codigo} en Spatie)
     * - etiqueta: texto a mostrar en la UI
     * - descripcion: explicación detallada del permiso
     */
    protected array $permisos = [
        'Facturación' => [
            [
                'codigo' => 'seleccion_cuit',
                'etiqueta' => 'Seleccionar CUIT/Punto de Venta',
                'descripcion' => 'Permite elegir un punto de venta diferente al predeterminado de la caja al facturar',
            ],
            [
                'codigo' => 'reimprimir_comprobantes',
                'etiqueta' => 'Reimprimir comprobantes',
                'descripcion' => 'Permite reimprimir comprobantes fiscales ya emitidos',
            ],
            [
                'codigo' => 'emitir_notas_credito',
                'etiqueta' => 'Emitir notas de crédito',
                'descripcion' => 'Permite emitir notas de crédito fiscales',
            ],
            [
                'codigo' => 'emitir_notas_debito',
                'etiqueta' => 'Emitir notas de débito',
                'descripcion' => 'Permite emitir notas de débito fiscales',
            ],
        ],
        'Ventas' => [
            [
                'codigo' => 'modificar_precios_venta',
                'etiqueta' => 'Modificar precios en venta',
                'descripcion' => 'Permite cambiar el precio de los artículos durante una venta',
            ],
            [
                'codigo' => 'aplicar_descuentos_manuales',
                'etiqueta' => 'Aplicar descuentos manuales',
                'descripcion' => 'Permite aplicar descuentos que no están definidos como promoción',
            ],
            [
                'codigo' => 'anular_ventas',
                'etiqueta' => 'Anular ventas',
                'descripcion' => 'Permite anular ventas ya realizadas',
            ],
            [
                'codigo' => 'modificar_vendedor',
                'etiqueta' => 'Modificar vendedor asignado',
                'descripcion' => 'Permite cambiar el vendedor asignado a una venta',
            ],
            [
                'codigo' => 'venta_precio_cero',
                'etiqueta' => 'Vender a precio cero',
                'descripcion' => 'Permite agregar artículos con precio cero en una venta',
            ],
            [
                'codigo' => 'venta_sin_stock',
                'etiqueta' => 'Vender sin stock',
                'descripcion' => 'Permite vender artículos que no tienen stock disponible',
            ],
        ],
        'Caja' => [
            [
                'codigo' => 'abrir_caja',
                'etiqueta' => 'Abrir caja',
                'descripcion' => 'Permite abrir una caja para comenzar a operar',
            ],
            [
                'codigo' => 'cerrar_caja',
                'etiqueta' => 'Cerrar caja',
                'descripcion' => 'Permite cerrar una caja y generar el arqueo',
            ],
            [
                'codigo' => 'realizar_retiros',
                'etiqueta' => 'Realizar retiros de caja',
                'descripcion' => 'Permite registrar retiros de efectivo de la caja',
            ],
            [
                'codigo' => 'realizar_ingresos',
                'etiqueta' => 'Realizar ingresos a caja',
                'descripcion' => 'Permite registrar ingresos de efectivo a la caja',
            ],
            [
                'codigo' => 'ver_movimientos_caja',
                'etiqueta' => 'Ver movimientos de caja',
                'descripcion' => 'Permite ver el detalle de movimientos de la caja',
            ],
            [
                'codigo' => 'cerrar_caja_individual',
                'etiqueta' => 'Cerrar caja individual',
                'descripcion' => 'Permite cerrar una caja individualmente aunque pertenezca a un grupo de cierre',
            ],
        ],
        'Artículos' => [
            [
                'codigo' => 'ver_costos',
                'etiqueta' => 'Ver costos de productos',
                'descripcion' => 'Permite visualizar el costo de los artículos',
            ],
            [
                'codigo' => 'modificar_costos',
                'etiqueta' => 'Modificar costos',
                'descripcion' => 'Permite modificar el costo de los artículos',
            ],
            [
                'codigo' => 'modificar_precios',
                'etiqueta' => 'Modificar precios de lista',
                'descripcion' => 'Permite modificar los precios de lista de los artículos',
            ],
            [
                'codigo' => 'ajustar_stock',
                'etiqueta' => 'Ajustar stock',
                'descripcion' => 'Permite realizar ajustes manuales de stock',
            ],
            [
                'codigo' => 'transferir_stock',
                'etiqueta' => 'Transferir stock entre sucursales',
                'descripcion' => 'Permite realizar transferencias de stock entre sucursales',
            ],
        ],
        'Clientes' => [
            [
                'codigo' => 'crear_clientes',
                'etiqueta' => 'Crear clientes',
                'descripcion' => 'Permite crear nuevos clientes en el sistema',
            ],
            [
                'codigo' => 'modificar_clientes',
                'etiqueta' => 'Modificar clientes',
                'descripcion' => 'Permite modificar los datos de clientes existentes',
            ],
            [
                'codigo' => 'eliminar_clientes',
                'etiqueta' => 'Eliminar clientes',
                'descripcion' => 'Permite eliminar clientes del sistema',
            ],
            [
                'codigo' => 'ver_cuenta_corriente',
                'etiqueta' => 'Ver cuenta corriente',
                'descripcion' => 'Permite ver el estado de cuenta corriente de los clientes',
            ],
            [
                'codigo' => 'modificar_limite_credito',
                'etiqueta' => 'Modificar límite de crédito',
                'descripcion' => 'Permite modificar el límite de crédito de los clientes',
            ],
        ],
        'Reportes' => [
            [
                'codigo' => 'exportar_reportes',
                'etiqueta' => 'Exportar reportes',
                'descripcion' => 'Permite exportar reportes a Excel, PDF u otros formatos',
            ],
            [
                'codigo' => 'ver_reportes_ventas',
                'etiqueta' => 'Ver reportes de ventas',
                'descripcion' => 'Permite acceder a los reportes de ventas',
            ],
            [
                'codigo' => 'ver_reportes_financieros',
                'etiqueta' => 'Ver reportes financieros',
                'descripcion' => 'Permite acceder a reportes financieros y de rentabilidad',
            ],
        ],
        'Sistema' => [
            [
                'codigo' => 'acceso_modo_offline',
                'etiqueta' => 'Acceso en modo offline',
                'descripcion' => 'Permite operar el sistema sin conexión a internet',
            ],
            [
                'codigo' => 'cambiar_sucursal',
                'etiqueta' => 'Cambiar de sucursal',
                'descripcion' => 'Permite cambiar a otra sucursal durante la sesión',
            ],
            [
                'codigo' => 'cambiar_caja',
                'etiqueta' => 'Cambiar de caja',
                'descripcion' => 'Permite cambiar a otra caja durante la sesión',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creando permisos funcionales...');

        $orden = 0;
        $total = 0;

        foreach ($this->permisos as $grupo => $permisosGrupo) {
            $ordenGrupo = 1;

            foreach ($permisosGrupo as $permiso) {
                PermisoFuncional::updateOrCreate(
                    ['codigo' => $permiso['codigo']],
                    [
                        'etiqueta' => $permiso['etiqueta'],
                        'descripcion' => $permiso['descripcion'],
                        'grupo' => $grupo,
                        'orden' => $ordenGrupo++,
                        'activo' => true,
                    ]
                );
                $total++;
            }
        }

        $this->command->info("Se crearon/actualizaron {$total} permisos funcionales.");

        // Sincronizar con Spatie
        $this->command->info('Sincronizando con tabla de permisos de Spatie...');
        PermisoFuncional::syncAllToSpatie();
        $this->command->info('Sincronización completada.');
    }
}
