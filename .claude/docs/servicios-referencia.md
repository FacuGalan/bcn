# Servicios y Archivos de Referencia

## Models (`app/Models/`)

### Core
Articulo, Categoria, Etiqueta, ListaPrecio, Promocion, FormaPago, ConceptoPago

### Stock
Stock, MovimientoStock, Produccion, ProduccionDetalle, ProduccionIngrediente

### Opcionales y Recetas
GrupoOpcional, Opcional, Receta, RecetaIngrediente, ArticuloGrupoOpcional, ArticuloGrupoOpcionalOpcion

### Ventas
Venta, VentaDetalle, VentaDetalleOpcional, Compra

### Bancos
Moneda, TipoCambio, CuentaEmpresa, MovimientoCuentaEmpresa, ConceptoMovimientoCuenta, TransferenciaCuentaEmpresa

### Sistema
Comercio, Sucursal, Caja, User

## Services (`app/Services/`)

| Service | Responsabilidad |
|---------|----------------|
| VentaService | Crear, cancelar, anular ventas |
| CompraService | Gestión de compras |
| StockService | Movimientos de stock, ajustes |
| TransferenciaStockService | Transferencias entre sucursales |
| OpcionalService | Opcionales por artículo |
| ProduccionService | Producción y descuento de recetas |
| PrecioService | Cálculo de precios (4 niveles) + promociones + IVA |
| CajaService | Caché 3 niveles, permisos, apertura/cierre |
| SucursalService | Sucursales disponibles por usuario |
| TenantService | Configuración de conexión tenant |
| CuentaEmpresaService | Cuentas bancarias/billeteras, movimientos ledger |

## Traits (`app/Traits/`)

- `SucursalAware` — Para componentes con datos por sucursal
- `CajaAware` — Para componentes que dependen de caja

## Livewire (`app/Livewire/`)

| Módulo | Componentes |
|--------|------------|
| Articulos | GestionarArticulos, GestionarGruposOpcionales, GestionarRecetas, GestionarCategorias, GestionarEtiquetas, ListasPrecios, Promociones |
| Stock | StockInventario, MovimientosStock, InventarioGeneral, Produccion |
| Ventas | NuevaVenta, ListadoVentas |
| Bancos | ResumenCuentas, GestionCuentas, MovimientosCuenta, TransferenciasCuenta |
| Configuracion | ConfiguracionEmpresa, Usuarios, RolesPermisos, GestionMonedas |

## Middleware (`app/Http/Middleware/`)

- `TenantMiddleware` — Configura conexión tenant por sesión
- `CajaMiddleware` — Auto-selecciona caja si no hay activa

## Rutas (`routes/web.php`)

Todas agrupadas con middleware `['auth', 'verified', 'tenant']`.

## Estructura de Menús

### Bancos
1. Resumen (slug: resumen-cuentas, ruta: bancos.resumen)
2. Cuentas (slug: cuentas-empresa, ruta: bancos.cuentas)
3. Movimientos (slug: movimientos-cuenta, ruta: bancos.movimientos)
4. Transferencias (slug: transferencias-cuenta, ruta: bancos.transferencias)

### Artículos
1. Listado de Artículos (slug: listado-articulos)
2. Opcionales (slug: grupos-opcionales)
3. Categorías (slug: categorias)
4. Etiquetas (slug: etiquetas)
5. Listas de Precios (slug: listas-precios)
6. Promociones (slug: promociones)
7. Formas de Pago (slug: formas-pago)

### Stock
1. Inventario (slug: inventario)
2. Movimientos (slug: movimientos-stock)
3. Inventario General (slug: inventario-general)
4. Recetas (slug: recetas)
5. Producción (slug: produccion)

### Configuración
1. Usuarios (slug: usuarios, ruta: configuracion.usuarios)
2. Roles y Permisos (slug: roles-permisos, ruta: configuracion.roles)
3. Empresa (slug: empresa, ruta: configuracion.empresa)
4. Impresoras (slug: impresoras, ruta: configuracion.impresoras)
5. Formas de Pago (slug: formas-pago, ruta: configuracion.formas-pago)
6. Monedas (slug: monedas, ruta: configuracion.monedas)

## Cache Keys

- `menu_parent_items` — Items padre del menú (5 min TTL)
- `menu_children_{parentId}` — Hijos de un item de menú
- `user_permissions_{userId}_{comercioId}` — Permisos del usuario
- `cajas_sucursal_{sucursalId}` — Cajas por sucursal
- Limpiar: `php artisan optimize:clear`

## Roles Predefinidos

| Rol | Alcance |
|-----|---------|
| Super Administrador | Todo + gestión usuarios/sucursales |
| Administrador | Todo operativo |
| Gerente | Sin config usuarios, con stock/reportes |
| Vendedor | Solo ventas y operaciones básicas |
| Visualizador | Solo lectura |
