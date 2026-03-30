# BCN Pymes -- Base de Conocimiento para IA

> Este documento contiene toda la informacion necesaria para que una IA pueda comprender el sistema BCN Pymes, responder preguntas de usuarios finales, y potencialmente consultar la base de datos para generar reportes.
>
> **Importante**: Todas las tablas mencionadas en este documento llevan un prefijo de 6 digitos segun el comercio (ej: `000001_ventas`). Al construir queries SQL, siempre reemplazar `{PREFIX}` por el prefijo correspondiente.

---

## 1. Arquitectura General

### 1.1 Multi-Tenancy

BCN Pymes utiliza un modelo multi-tenant con **3 conexiones de base de datos**:

| Conexion | Base de datos | Que contiene | Prefijo de tablas |
|---|---|---|---|
| `config` | `bcn_config` | Usuarios (`users`), comercios (`comercios`), condiciones IVA, localidades | Sin prefijo |
| `pymes` | `bcn_pymes` | Menu items (`menu_items`), permisos (`permissions`), roles (`roles`) -- compartidas entre todos los comercios | Sin prefijo |
| `pymes_tenant` | `bcn_pymes` | Todas las tablas operativas del comercio | `{NNNNNN}_` (6 digitos, ej: `000001_`) |

**Como funciona el prefijo de tablas:**
- Cada comercio tiene un ID unico. El prefijo se genera con `str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_'`.
- Comercio con ID 1 tiene prefijo `000001_`, comercio con ID 42 tiene prefijo `000042_`.
- Todas las tablas operativas (ventas, articulos, clientes, etc.) llevan este prefijo.
- Esto permite que multiples comercios compartan la misma base de datos MySQL.

**Tablas en `config` (sin prefijo):**
- `users` -- Usuarios del sistema
- `comercios` -- Comercios/PYMEs registrados
- `comercio_user` -- Pivot usuarios-comercios (un usuario puede acceder a multiples comercios)
- `condiciones_iva` -- Catalogo de condiciones IVA de AFIP (Responsable Inscripto, Monotributista, Consumidor Final, etc.)
- `localidades`, `provincias` -- Datos geograficos

**Tablas en `pymes` (compartidas, sin prefijo):**
- `menu_items` -- Items del menu de navegacion
- `permissions` -- Permisos del sistema
- `roles` -- Roles del sistema

**Tablas en `pymes_tenant` (con prefijo `{PREFIX}`):**
- Todas las demas tablas operativas del comercio (ventas, articulos, clientes, cajas, stock, etc.)

### 1.2 Conceptos Clave

#### Comercio
Un comercio es una PYME que usa el sistema. Cada comercio tiene:
- Un `id` unico
- Un `nombre` comercial
- Una `database_name` donde estan sus tablas
- Un `max_usuarios` que limita cuantos usuarios puede tener
- Multiples usuarios asociados (relacion many-to-many via `comercio_user`)
- Multiples sucursales

#### Sucursal
Una sucursal es un punto fisico de operacion del comercio. Cada sucursal tiene:
- `nombre`, `codigo`, `direccion`, `telefono`, `email`
- `es_principal` -- Si es la sucursal principal del comercio (siempre hay una)
- `activa` -- Si esta operativa
- Configuraciones especificas: control de stock, facturacion automatica, agrupacion de articulos, impresion, WhatsApp
- Sus propias cajas, articulos habilitados, clientes asociados, listas de precios, formas de pago habilitadas

#### Caja
Una caja es un punto de cobro dentro de una sucursal. Cada caja tiene:
- `numero` -- Numero secuencial dentro de la sucursal (autogenerado)
- `nombre`, `codigo`
- `estado` -- `abierta` o `cerrada`
- `saldo_actual`, `saldo_inicial` -- Saldos de efectivo
- `fecha_apertura`, `fecha_cierre`
- Configuracion de carga inicial: `manual`, `ultimo_cierre` o `monto_fijo`
- Puede pertenecer a un `grupo_cierre` para cerrar multiples cajas juntas
- Asociacion con puntos de venta (para facturacion fiscal)

#### Roles y Permisos
- Los roles se asignan a nivel de sucursal. Un usuario puede tener rol "admin" en una sucursal y "vendedor" en otra.
- Los permisos se cachean con clave `user_permissions_{userId}_{comercioId}`.
- Tabla `model_has_roles` tiene campo `sucursal_id` (0 = todas las sucursales).

#### Flujo de Autenticacion
1. El usuario inicia sesion con email/password (tabla `users` en `config`).
2. Se verifican los comercios asociados al usuario (tabla `comercio_user`).
3. Si tiene un solo comercio, se selecciona automaticamente. Si tiene varios, elige uno.
4. Se establece el prefijo de tablas del comercio seleccionado en la sesion.
5. Se cargan los permisos y sucursales del usuario para ese comercio.
6. Si tiene una sola sucursal, se selecciona automaticamente. Si tiene varias, elige una.
7. El usuario selecciona (o se autoselecciona) una caja para operar.

---

## 2. Modelo de Datos

> **Nota sobre prefijo**: Todas las tablas que se mencionan en esta seccion llevan el prefijo del comercio. Por ejemplo, `ventas` en realidad es `{PREFIX}ventas` (ej: `000001_ventas`). Se omite el prefijo por legibilidad.

### 2.1 Ventas

#### Tabla: `ventas`
Registra cada venta realizada en el sistema.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `numero` | varchar(191) | Numero de la venta (formato por caja, ej: "0001-00000123") |
| `sucursal_id` | bigint FK | Sucursal donde se realizo la venta |
| `cliente_id` | bigint FK nullable | Cliente asociado (NULL = consumidor final anonimo) |
| `caja_id` | bigint FK nullable | Caja donde se registro |
| `canal_venta_id` | bigint FK nullable | Canal de venta (mostrador, delivery, web, etc.) |
| `forma_venta_id` | bigint FK nullable | Forma de venta (para llevar, para consumir, etc.) |
| `lista_precio_id` | bigint FK nullable | Lista de precios usada en la venta |
| `punto_venta_id` | bigint FK nullable | Punto de venta fiscal (para facturacion) |
| `forma_pago_id` | bigint FK nullable | Forma de pago principal (para pagos mixtos el detalle esta en venta_pagos) |
| `usuario_id` | bigint FK | Usuario que realizo la venta |
| `fecha` | timestamp | Fecha y hora de la venta |
| `subtotal` | decimal(12,2) | Subtotal de la venta (suma de detalles) |
| `iva` | decimal(12,2) | Total de IVA |
| `descuento` | decimal(12,2) | Descuento general aplicado |
| `total` | decimal(12,2) | Total de la venta (subtotal + iva - descuento) |
| `ajuste_forma_pago` | decimal(12,2) | Suma de ajustes (recargos/descuentos) por formas de pago |
| `total_final` | decimal(12,2) | Total final con ajustes de forma de pago |
| `estado` | enum | `completada`, `pendiente`, `cancelada` |
| `es_cuenta_corriente` | boolean | Si la venta es a credito (cuenta corriente) |
| `saldo_pendiente_cache` | decimal(12,2) | Cache del saldo pendiente de cobro (para CC) |
| `fecha_vencimiento` | timestamp nullable | Fecha de vencimiento del credito |
| `monto_fiscal_cache` | decimal(12,2) | Cache del monto facturado fiscalmente |
| `monto_no_fiscal_cache` | decimal(12,2) | Cache del monto no facturado |
| `anulado_por_usuario_id` | bigint FK nullable | Usuario que anulo la venta |
| `anulado_at` | timestamp nullable | Fecha de anulacion |
| `motivo_anulacion` | varchar nullable | Motivo de la anulacion |
| `observaciones` | text nullable | Notas adicionales |
| `cierre_turno_id` | bigint FK nullable | Cierre de turno donde se proceso |
| `created_at` | timestamp | Fecha de creacion del registro |
| `updated_at` | timestamp | Fecha de ultima modificacion |
| `deleted_at` | timestamp nullable | Soft delete |

**Indices**: `sucursal_id`, `fecha`, `estado`, `cliente_id`, `caja_id`.

**Estados posibles**:
- `completada` -- Venta pagada completamente o venta contado.
- `pendiente` -- Venta a cuenta corriente con saldo pendiente de cobro.
- `cancelada` -- Venta anulada. El stock y movimientos de caja se revierten.

#### Tabla: `ventas_detalle`
Cada linea/item de una venta.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_id` | bigint FK | Venta a la que pertenece |
| `articulo_id` | bigint FK | Articulo vendido |
| `tipo_iva_id` | bigint FK | Tipo de IVA aplicado |
| `lista_precio_id` | bigint FK nullable | Lista de precios usada para este item |
| `cantidad` | decimal(12,2) | Cantidad vendida |
| `precio_unitario` | decimal(12,2) | Precio unitario final (con IVA incluido si corresponde) |
| `precio_lista` | decimal(12,2) | Precio original de lista (antes de promociones) |
| `precio_opcionales` | decimal(12,2) | Suma del precio extra de opcionales seleccionados |
| `iva_porcentaje` | decimal(5,2) | Porcentaje de IVA (ej: 21.00) |
| `precio_sin_iva` | decimal(12,2) | Precio unitario sin IVA |
| `descuento` | decimal(12,2) | Descuento individual del item |
| `descuento_promocion` | decimal(12,2) | Descuento aplicado por promociones |
| `tiene_promocion` | boolean | Si tiene promocion aplicada |
| `iva_monto` | decimal(12,2) | Monto de IVA del item |
| `subtotal` | decimal(12,2) | Subtotal del item (precio_unitario * cantidad) |
| `total` | decimal(12,2) | Total del item despues de descuentos de promocion |
| `ajuste_manual_tipo` | varchar nullable | Tipo de ajuste manual (porcentaje, monto) |
| `ajuste_manual_valor` | decimal(12,2) nullable | Valor del ajuste manual |
| `precio_sin_ajuste_manual` | decimal(12,2) nullable | Precio antes del ajuste manual |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `venta_pagos`
Desglose de formas de pago de una venta. Una venta puede tener multiples pagos (pago mixto).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_id` | bigint FK | Venta a la que pertenece |
| `forma_pago_id` | bigint FK | Forma de pago utilizada |
| `concepto_pago_id` | bigint FK nullable | Concepto de pago especifico (efectivo, tarjeta, etc.) |
| `monto_base` | decimal(12,2) | Monto antes de ajustes |
| `ajuste_porcentaje` | decimal(6,2) | Ajuste aplicado (+ recargo, - descuento) |
| `monto_ajuste` | decimal(12,2) | Monto del ajuste |
| `monto_final` | decimal(12,2) | Monto final despues de ajustes |
| `saldo_pendiente` | decimal(12,2) | Saldo pendiente (para pagos CC) |
| `monto_recibido` | decimal(12,2) nullable | Monto recibido del cliente (para vuelto) |
| `vuelto` | decimal(12,2) nullable | Vuelto entregado |
| `cuotas` | tinyint nullable | Numero de cuotas |
| `recargo_cuotas_porcentaje` | decimal(6,2) nullable | Recargo por cuotas |
| `recargo_cuotas_monto` | decimal(12,2) nullable | Monto del recargo por cuotas |
| `monto_cuota` | decimal(12,2) nullable | Monto de cada cuota |
| `referencia` | varchar(100) nullable | Numero de autorizacion, voucher, etc. |
| `observaciones` | text nullable | Notas |
| `es_cuenta_corriente` | boolean | Si este pago es a cuenta corriente |
| `afecta_caja` | boolean | Si genera movimiento de caja |
| `estado` | enum | `activo`, `pendiente`, `anulado` |
| `movimiento_caja_id` | bigint FK nullable | Movimiento de caja generado |
| `comprobante_fiscal_id` | bigint FK nullable | Comprobante fiscal asociado |
| `monto_facturado` | decimal(12,2) nullable | Monto facturado fiscalmente |
| `cierre_turno_id` | bigint FK nullable | Cierre de turno |
| `moneda_id` | bigint FK nullable | Moneda del pago |
| `monto_moneda_original` | decimal(14,2) nullable | Monto en moneda original (para pagos en moneda extranjera) |
| `tipo_cambio_tasa` | decimal(14,6) nullable | Tasa de tipo de cambio aplicada |
| `movimiento_cuenta_empresa_id` | bigint FK nullable | Movimiento en cuenta empresa (banco/billetera) |
| `anulado_por_usuario_id` | bigint FK nullable | Usuario que anulo |
| `anulado_at` | timestamp nullable | Fecha de anulacion |
| `motivo_anulacion` | text nullable | Motivo |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `venta_promociones`
Promociones aplicadas a nivel de venta completa.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_id` | bigint FK | Venta |
| `tipo_promocion` | varchar | `promocion` o `promocion_especial` |
| `promocion_id` | bigint FK nullable | FK a `promociones` (para comunes) |
| `promocion_especial_id` | bigint FK nullable | FK a `promociones_especiales` |
| `forma_pago_id` | bigint FK nullable | Forma de pago que activo la promo |
| `codigo_cupon` | varchar nullable | Cupon usado |
| `descripcion_promocion` | varchar | Descripcion legible |
| `tipo_beneficio` | varchar | `porcentaje` o `monto_fijo` |
| `valor_beneficio` | decimal | Valor original del beneficio |
| `descuento_aplicado` | decimal | Descuento efectivamente aplicado |
| `monto_minimo_requerido` | decimal nullable | Monto minimo si aplica |
| `created_at` | timestamp | Fecha |

#### Tabla: `venta_detalle_promociones`
Promociones aplicadas a nivel de item individual.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_detalle_id` | bigint FK | Detalle de venta |
| `tipo_promocion` | varchar | `promocion` o `promocion_especial` |
| `promocion_id` | bigint FK nullable | FK a `promociones` |
| `promocion_especial_id` | bigint FK nullable | FK a `promociones_especiales` |
| `lista_precio_id` | bigint FK nullable | Lista de precios |
| `descripcion_promocion` | varchar | Descripcion legible |
| `tipo_beneficio` | varchar | `porcentaje` o `monto_fijo` |
| `valor_beneficio` | decimal | Valor del beneficio |
| `descuento_aplicado` | decimal | Descuento aplicado |
| `cantidad_requerida` | int nullable | Para NxM |
| `cantidad_bonificada` | int nullable | Para NxM |
| `created_at` | timestamp | Fecha |

#### Tabla: `venta_detalle_opcionales`
Opcionales seleccionados para cada item de venta (ej: pan brioche, salsa BBQ).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_detalle_id` | bigint FK | Detalle de venta |
| `grupo_opcional_id` | bigint FK | Grupo opcional |
| `opcional_id` | bigint FK | Opcional seleccionado |
| `nombre_grupo` | varchar | Nombre del grupo al momento de la venta |
| `nombre_opcional` | varchar | Nombre del opcional al momento de la venta |
| `precio_extra` | decimal | Precio extra del opcional |
| `cantidad` | int | Cantidad (para cuantitativos) |
| `created_at` | timestamp | Fecha |

### 2.2 Clientes

#### Tabla: `clientes`
Clientes del comercio.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(191) | Nombre del cliente |
| `razon_social` | varchar(191) nullable | Razon social (para facturacion) |
| `cuit` | varchar(20) nullable | CUIT/CUIL |
| `email` | varchar(191) nullable | Email |
| `telefono` | varchar(50) nullable | Telefono |
| `direccion` | varchar(255) nullable | Direccion |
| `condicion_iva_id` | int FK nullable | Condicion IVA (FK a `config.condiciones_iva`) |
| `lista_precio_id` | bigint FK nullable | Lista de precios asignada al cliente |
| `activo` | boolean | Si esta activo |
| `tiene_cuenta_corriente` | boolean | Si puede comprar a credito |
| `limite_credito` | decimal(12,2) | Limite maximo de credito (0 = sin limite) |
| `dias_credito` | int | Dias de credito por defecto para nuevas ventas (default: 30) |
| `tasa_interes_mensual` | decimal(6,2) | Tasa de interes mensual por mora (%) |
| `saldo_deudor_cache` | decimal(12,2) | Cache: deuda total del cliente |
| `saldo_a_favor_cache` | decimal(12,2) | Cache: saldo a favor del cliente |
| `ultimo_movimiento_cc_at` | timestamp nullable | Ultimo movimiento en cuenta corriente |
| `bloqueado_por_mora` | boolean | Si esta bloqueado por mora |
| `dias_mora_max` | int | Maximos dias de mora actual |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

**Indices**: `nombre`, `cuit`, `lista_precio_id`, `tiene_cuenta_corriente`, `saldo_deudor_cache`, `bloqueado_por_mora`.

#### Tabla: `clientes_sucursales`
Tabla pivot que vincula clientes con sucursales. Permite configuracion por sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cliente_id` | bigint FK | Cliente |
| `sucursal_id` | bigint FK | Sucursal |
| `lista_precio_id` | bigint FK nullable | Lista de precios especifica para esta sucursal |
| `descuento_porcentaje` | decimal(5,2) | Descuento especial en esta sucursal |
| `limite_credito` | decimal(12,2) | Limite de credito especifico para esta sucursal |
| `saldo_actual` | decimal(12,2) | Saldo actual en esta sucursal |
| `activo` | boolean | Si esta activo en esta sucursal |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(cliente_id, sucursal_id)`.

### 2.3 Articulos y Catalogos

#### Tabla: `articulos`
Catalogo maestro de articulos/productos del comercio.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(191) UNIQUE | Codigo unico del articulo |
| `codigo_barras` | varchar(50) nullable | Codigo de barras (EAN, UPC) |
| `nombre` | varchar(191) | Nombre del articulo |
| `descripcion` | text nullable | Descripcion detallada |
| `categoria_id` | bigint FK nullable | Categoria del articulo |
| `unidad_medida` | varchar(20) | Unidad de medida (default: 'unidad') |
| `es_materia_prima` | boolean | Si es materia prima (para filtrado) |
| `tipo_iva_id` | bigint FK nullable | Tipo de IVA aplicable |
| `precio_iva_incluido` | boolean | Si los precios incluyen IVA (default: true) |
| `precio_base` | decimal(12,2) | Precio base del articulo |
| `activo` | boolean | Si esta activo |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

**Indices**: `codigo` (unique), `activo`, `tipo_iva_id`, `codigo_barras`.

#### Tabla: `articulos_sucursales`
Pivot que vincula articulos con sucursales. Controla disponibilidad y modo de stock por sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo |
| `sucursal_id` | bigint FK | Sucursal |
| `activo` | boolean | Si esta habilitado en esta sucursal |
| `modo_stock` | enum | `ninguno` (no controla), `unitario` (descuenta articulo), `receta` (descuenta ingredientes) |
| `vendible` | boolean | Si aparece en pantalla de ventas |
| `precio_base` | decimal(12,2) nullable | Override de precio base para esta sucursal (NULL = usa el global) |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(articulo_id, sucursal_id)`.

#### Tabla: `categorias`
Categorias para clasificar articulos.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Nombre de la categoria |
| `codigo` | varchar(50) nullable | Codigo alfanumerico |
| `prefijo` | varchar(10) nullable | Prefijo para codigo automatico de articulos |
| `descripcion` | text nullable | Descripcion |
| `color` | varchar(7) nullable | Color hex para UI (#RRGGBB) |
| `icono` | varchar(50) nullable | Nombre del icono |
| `activo` | boolean | Si esta activa |
| `tipo_iva_id` | bigint FK nullable | IVA por defecto para articulos de esta categoria |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `grupos_opcionales`
Grupos de opciones reutilizables (ej: "Panes a eleccion", "Salsas", "Agregados").

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(150) | Nombre del grupo |
| `descripcion` | text nullable | Descripcion |
| `obligatorio` | boolean | Si el cliente DEBE elegir |
| `tipo` | enum | `seleccionable` (si/no por opcion) o `cuantitativo` (cantidad por opcion) |
| `min_seleccion` | int | Minimo de opciones/cantidad total |
| `max_seleccion` | int nullable | Maximo (NULL = sin limite) |
| `activo` | boolean | Si esta activo |
| `orden` | int | Orden de visualizacion |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `opcionales`
Opciones individuales dentro de un grupo opcional.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `grupo_opcional_id` | bigint FK | Grupo al que pertenece |
| `nombre` | varchar(150) | Nombre del opcional |
| `descripcion` | text nullable | Descripcion |
| `precio_extra` | decimal(12,2) | Precio extra template/default |
| `activo` | boolean | Si esta activo globalmente |
| `orden` | int | Orden de visualizacion |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `articulo_grupo_opcional`
Asignacion de grupos opcionales a articulos (por sucursal).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo |
| `grupo_opcional_id` | bigint FK | Grupo opcional |
| `sucursal_id` | bigint FK | Sucursal |
| `activo` | boolean | Si esta activo para este articulo en esta sucursal |
| `orden` | int | Orden de visualizacion |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(articulo_id, grupo_opcional_id, sucursal_id)`.

#### Tabla: `articulo_grupo_opcional_opcion`
Opciones concretas disponibles para cada asignacion articulo-grupo. Permite personalizar precios y disponibilidad por sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_grupo_opcional_id` | bigint FK | Asignacion articulo-grupo |
| `opcional_id` | bigint FK | Opcional |
| `precio_extra` | decimal(12,2) | Precio concreto para esta asignacion |
| `activo` | boolean | Decision del admin: desactivar sin borrar |
| `disponible` | boolean | Estado de stock: false = agotado en esta sucursal |
| `orden` | int | Orden |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(articulo_grupo_opcional_id, opcional_id)`.

#### Tabla: `recetas`
Formulas de ingredientes para produccion. Polimorfica (puede ser de un Articulo o un Opcional).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `recetable_type` | varchar(50) | `Articulo` o `Opcional` |
| `recetable_id` | bigint | ID del articulo u opcional |
| `sucursal_id` | bigint FK nullable | NULL = receta default para todas las sucursales. Con valor = override para esa sucursal |
| `cantidad_producida` | decimal(12,3) | Cuantas unidades produce esta receta |
| `notas` | text nullable | Notas del proceso |
| `activo` | boolean | Si esta activa |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(recetable_type, recetable_id, sucursal_id)`.

**Logica de resolucion**: Para encontrar la receta de un articulo en una sucursal, primero se busca un override para esa sucursal. Si existe pero esta inactivo, la receta esta anulada. Si no hay override, se usa la receta default (sucursal_id = NULL).

#### Tabla: `receta_ingredientes`
Ingredientes de cada receta.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `receta_id` | bigint FK | Receta |
| `articulo_id` | bigint FK | Ingrediente (siempre un articulo) |
| `cantidad` | decimal(12,3) | Cantidad necesaria del ingrediente |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `grupos_etiquetas`
Grupos de etiquetas para clasificar articulos (ej: "Sabor", "Tamano", "Material").

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Nombre del grupo |
| `codigo` | varchar(50) nullable UNIQUE | Codigo |
| `descripcion` | varchar(500) nullable | Descripcion |
| `color` | varchar(7) | Color hex (default: #6B7280) |
| `activo` | boolean | Si esta activo |
| `orden` | int | Orden |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `etiquetas`
Etiquetas individuales dentro de un grupo.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `grupo_etiqueta_id` | bigint FK | Grupo al que pertenece |
| `nombre` | varchar(100) | Nombre |
| `codigo` | varchar(50) nullable | Codigo |
| `color` | varchar(7) nullable | Color |
| `activo` | boolean | Si esta activa |
| `orden` | int | Orden |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `articulo_etiqueta`
Pivot articulo-etiqueta.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo |
| `etiqueta_id` | bigint FK | Etiqueta |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(articulo_id, etiqueta_id)`.

### 2.4 Stock

#### Tabla: `stock`
**Tabla cache** que almacena el stock actual de cada articulo en cada sucursal. El stock "real" se calcula sumando `movimientos_stock`, pero esta tabla se mantiene sincronizada como cache para consultas rapidas.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo |
| `sucursal_id` | bigint FK | Sucursal |
| `cantidad` | decimal(10,2) | Cantidad actual en stock |
| `cantidad_minima` | decimal(10,2) nullable | Stock minimo (para alertas) |
| `cantidad_maxima` | decimal(10,2) nullable | Stock maximo |
| `ultima_actualizacion` | timestamp nullable | Ultima vez que se actualizo |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `movimientos_stock`
**Tabla append-only** que registra cada movimiento de stock. Los movimientos anulados no se borran: se crean contraasientos que invierten la operacion.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo afectado |
| `sucursal_id` | bigint FK | Sucursal |
| `fecha` | date | Fecha del movimiento |
| `tipo` | varchar(50) | Tipo: `venta`, `compra`, `ajuste_manual`, `inventario_fisico`, `transferencia_salida`, `transferencia_entrada`, `devolucion`, `anulacion_venta`, `anulacion_compra`, `carga_inicial`, `produccion_entrada`, `produccion_salida`, `anulacion_produccion` |
| `entrada` | decimal(10,2) | Cantidad que entra (0 si es salida) |
| `salida` | decimal(10,2) | Cantidad que sale (0 si es entrada) |
| `stock_resultante` | decimal(10,2) | Stock despues del movimiento |
| `documento_tipo` | varchar(50) nullable | Tipo de documento origen (venta, compra, etc.) |
| `documento_id` | bigint nullable | ID del documento origen |
| `venta_id` | bigint FK nullable | FK a venta (si es movimiento por venta) |
| `venta_detalle_id` | bigint FK nullable | FK a detalle de venta |
| `compra_id` | bigint FK nullable | FK a compra |
| `compra_detalle_id` | bigint FK nullable | FK a detalle de compra |
| `transferencia_stock_id` | bigint FK nullable | FK a transferencia de stock |
| `concepto` | varchar(255) | Descripcion del movimiento |
| `observaciones` | text nullable | Notas adicionales |
| `costo_unitario` | decimal(10,4) nullable | Costo unitario del item |
| `estado` | varchar(20) | `activo` o `anulado` |
| `anulado_por_movimiento_id` | bigint FK nullable | ID del contraasiento que anulo este movimiento |
| `usuario_id` | bigint FK | Usuario que realizo el movimiento |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indices**: `(articulo_id, sucursal_id, fecha)`, `(articulo_id, sucursal_id, estado)`, `(documento_tipo, documento_id)`, `venta_id`, `compra_id`, `transferencia_stock_id`, `estado`.

### 2.5 Cajas y Movimientos

#### Tabla: `cajas`
Cajas registradoras del sistema.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `numero` | int UNIQUE (por sucursal) | Numero secuencial de caja |
| `nombre` | varchar(191) | Nombre descriptivo |
| `codigo` | varchar(50) | Codigo |
| `saldo_inicial` | decimal(12,2) | Saldo al abrir la caja |
| `saldo_actual` | decimal(12,2) | Saldo actual de efectivo |
| `fecha_apertura` | timestamp nullable | Cuando se abrio |
| `fecha_cierre` | timestamp nullable | Cuando se cerro |
| `usuario_apertura_id` | bigint FK nullable | Quien la abrio |
| `usuario_cierre_id` | bigint FK nullable | Quien la cerro |
| `estado` | enum | `abierta` o `cerrada` |
| `activo` | boolean | Si esta activa |
| `limite_efectivo` | decimal(12,2) nullable | Limite maximo de efectivo |
| `modo_carga_inicial` | enum | `manual`, `ultimo_cierre`, `monto_fijo` |
| `monto_fijo_inicial` | decimal(12,2) nullable | Monto fijo si el modo es `monto_fijo` |
| `grupo_cierre_id` | bigint FK nullable | Grupo de cierre compartido |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `movimientos_caja`
Registra movimientos de **efectivo fisico** en caja. Solo efectivo que entra/sale fisicamente.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `caja_id` | bigint FK | Caja |
| `tipo` | enum | `ingreso` o `egreso` |
| `concepto` | varchar(191) | Descripcion del movimiento |
| `monto` | decimal(12,2) | Monto del movimiento |
| `usuario_id` | bigint FK nullable | Usuario que lo realizo |
| `referencia_tipo` | varchar(191) nullable | Tipo de entidad: `venta`, `compra`, `cobro`, `apertura`, `retiro`, `ajuste`, `transferencia`, `ingreso_manual`, `egreso_manual`, `vuelto_venta`, `vuelto_cobro`, `anulacion_venta`, `anulacion_cobro` |
| `referencia_id` | bigint nullable | ID de la entidad |
| `cierre_turno_id` | bigint FK nullable | NULL = no cerrado aun. Con valor = ya procesado en cierre |
| `moneda_id` | bigint FK nullable | Moneda del movimiento |
| `tipo_cambio_id` | bigint FK nullable | Tipo de cambio aplicado |
| `monto_moneda_original` | decimal(14,2) nullable | Monto en moneda original |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `cierres_turno`
Registra cada cierre de turno/caja.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `grupo_cierre_id` | bigint FK nullable | NULL si fue cierre individual |
| `usuario_id` | bigint FK | Usuario que cerro |
| `tipo` | enum | `individual` o `grupo` |
| `fecha_apertura` | datetime nullable | Fecha de apertura mas antigua |
| `fecha_cierre` | datetime | Fecha del cierre |
| `total_saldo_inicial` | decimal(14,2) | Suma de saldos iniciales |
| `total_saldo_final` | decimal(14,2) | Suma de saldos finales |
| `total_ingresos` | decimal(14,2) | Suma de ingresos |
| `total_egresos` | decimal(14,2) | Suma de egresos |
| `total_diferencia` | decimal(14,2) | Diferencia (positivo=sobrante, negativo=faltante) |
| `observaciones` | text nullable | Notas |
| `revertido` | boolean | Si el cierre fue revertido |
| `fecha_reversion`, `usuario_reversion_id`, `motivo_reversion` | ... | Datos de reversion |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `cierre_turno_cajas`
Detalle por caja de cada cierre de turno.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cierre_turno_id` | bigint FK | Cierre de turno |
| `caja_id` | bigint FK | Caja |
| `caja_nombre` | varchar(100) | Nombre de la caja al momento del cierre |
| `saldo_inicial` | decimal(14,2) | Saldo al abrir |
| `saldo_final` | decimal(14,2) | Saldo al cerrar |
| `saldo_sistema` | decimal(14,2) | Saldo calculado por el sistema |
| `saldo_declarado` | decimal(14,2) | Saldo declarado por el usuario |
| `total_ingresos` | decimal(14,2) | Total ingresos |
| `total_egresos` | decimal(14,2) | Total egresos |
| `diferencia` | decimal(14,2) | Diferencia (faltante/sobrante) |
| `desglose_formas_pago` | text JSON nullable | Desglose por forma de pago |
| `desglose_conceptos` | text JSON nullable | Desglose por concepto |
| `desglose_monedas` | text JSON nullable | Desglose por moneda |
| `observaciones` | text nullable | Notas |
| `created_at`, `updated_at` | timestamp | Timestamps |

### 2.6 Cuenta Corriente y Cobranzas

#### Tabla: `movimientos_cuenta_corriente`
**Tabla append-only (ledger)** que unifica todos los movimientos de cuenta corriente de clientes. El saldo NO se almacena en cada registro; se CALCULA sumando todos los movimientos activos.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cliente_id` | bigint FK | Cliente |
| `sucursal_id` | bigint FK | Sucursal |
| `fecha` | date | Fecha del movimiento |
| `tipo` | enum | `venta`, `cobro`, `anticipo`, `uso_saldo_favor`, `devolucion_saldo`, `anulacion_venta`, `anulacion_cobro`, `nota_credito`, `ajuste_debito`, `ajuste_credito` |
| `debe` | decimal(12,2) | Monto que aumenta la deuda |
| `haber` | decimal(12,2) | Monto que disminuye la deuda |
| `saldo_favor_debe` | decimal(12,2) | Monto que consume saldo a favor |
| `saldo_favor_haber` | decimal(12,2) | Monto que genera saldo a favor |
| `documento_tipo` | enum | `venta`, `venta_pago`, `cobro`, `cobro_venta`, `cobro_pago`, `nota_credito`, `ajuste` |
| `documento_id` | bigint | ID del documento origen |
| `venta_id` | bigint FK nullable | FK a venta |
| `venta_pago_id` | bigint FK nullable | FK a pago de venta CC |
| `cobro_id` | bigint FK nullable | FK a cobro |
| `concepto` | varchar(255) | Descripcion del movimiento |
| `descripcion_comprobantes` | varchar(255) nullable | Descripciones de comprobantes (ticket, factura) |
| `observaciones` | text nullable | Notas |
| `estado` | enum | `activo` o `anulado` |
| `anulado_por_movimiento_id` | bigint FK nullable | ID del contraasiento |
| `usuario_id` | bigint FK | Usuario que realizo el movimiento |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Calculo de saldos:**
- **Saldo deudor** = `SUM(debe) - SUM(haber)` de movimientos activos
- **Saldo a favor** = `SUM(saldo_favor_haber) - SUM(saldo_favor_debe)` de movimientos activos

#### Tabla: `cobros`
Cobros realizados para saldar cuenta corriente.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `cliente_id` | bigint FK | Cliente |
| `caja_id` | bigint FK nullable | Caja donde se registro |
| `numero_recibo` | varchar(50) UNIQUE (por sucursal) | Numero de recibo (formato: RC-XX-NNNNNNNN) |
| `tipo` | enum | `cobro` (aplica a ventas) o `anticipo` (genera saldo a favor) |
| `fecha` | date | Fecha del cobro |
| `hora` | time nullable | Hora |
| `monto_cobrado` | decimal(12,2) | Monto total cobrado |
| `interes_aplicado` | decimal(12,2) | Interes cobrado |
| `descuento_aplicado` | decimal(12,2) | Descuento por pronto pago |
| `monto_aplicado_a_deuda` | decimal(12,2) | Monto que se aplico a cancelar deuda |
| `monto_a_favor` | decimal(12,2) | Monto que quedo a favor del cliente |
| `saldo_favor_usado` | decimal(12,2) | Saldo a favor que se uso en este cobro |
| `estado` | enum | `activo` o `anulado` |
| `observaciones` | text nullable | Notas |
| `usuario_id` | bigint FK | Usuario que registro |
| `anulado_por_usuario_id` | bigint FK nullable | Usuario que anulo |
| `anulado_at` | timestamp nullable | Fecha de anulacion |
| `motivo_anulacion` | varchar(500) nullable | Motivo |
| `cierre_turno_id` | bigint FK nullable | Cierre de turno |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `cobro_ventas`
Pivot que relaciona cobros con ventas saldadas. Un cobro puede aplicarse a multiples ventas.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cobro_id` | bigint FK | Cobro |
| `venta_id` | bigint FK | Venta saldada |
| `venta_pago_id` | bigint FK nullable | Pago especifico de CC afectado |
| `monto_aplicado` | decimal(12,2) | Monto del cobro aplicado a esta venta |
| `interes_aplicado` | decimal(12,2) | Interes cobrado por esta venta |
| `saldo_anterior` | decimal(12,2) | Saldo pendiente de la venta antes del cobro |
| `saldo_posterior` | decimal(12,2) | Saldo pendiente despues del cobro |
| `created_at` | timestamp | Fecha |

#### Tabla: `cobro_pagos`
Desglose de formas de pago de un cobro (estructura similar a `venta_pagos`).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cobro_id` | bigint FK | Cobro |
| `forma_pago_id` | bigint FK | Forma de pago |
| `concepto_pago_id` | bigint FK nullable | Concepto especifico |
| `monto_base` | decimal(12,2) | Monto antes de ajustes |
| `ajuste_porcentaje` | decimal(6,2) | Ajuste aplicado |
| `monto_ajuste` | decimal(12,2) | Monto del ajuste |
| `monto_final` | decimal(12,2) | Monto final |
| `monto_recibido` | decimal(12,2) nullable | Monto recibido |
| `vuelto` | decimal(12,2) nullable | Vuelto |
| `cuotas` | tinyint nullable | Cuotas |
| `recargo_cuotas_porcentaje`, `recargo_cuotas_monto`, `monto_cuota` | decimal nullable | Datos de cuotas |
| `referencia` | varchar(100) nullable | Nro autorizacion, voucher |
| `observaciones` | text nullable | Notas |
| `afecta_caja` | boolean | Si genera movimiento de caja |
| `movimiento_caja_id` | bigint FK nullable | Movimiento generado |
| `estado` | enum | `activo` o `anulado` |
| `cierre_turno_id` | bigint FK nullable | Cierre de turno |
| `moneda_id` | bigint FK nullable | Moneda |
| `monto_moneda_original` | decimal(14,2) nullable | Monto en moneda original |
| `tipo_cambio_tasa` | decimal(14,6) nullable | Tasa de cambio |
| `movimiento_cuenta_empresa_id` | bigint FK nullable | Movimiento en cuenta empresa |
| `created_at`, `updated_at` | timestamp | Timestamps |

### 2.7 Tesoreria

#### Tabla: `tesorerias`
Caja fuerte de la sucursal que centraliza el manejo de efectivo.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `nombre` | varchar | Nombre |
| `saldo_actual` | decimal(14,2) | Saldo actual |
| `saldo_minimo` | decimal(14,2) nullable | Saldo minimo (alerta) |
| `saldo_maximo` | decimal(14,2) nullable | Saldo maximo (sugiere deposito) |
| `activo` | boolean | Si esta activa |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `movimientos_tesoreria`
Movimientos de efectivo en tesoreria.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `tesoreria_id` | bigint FK | Tesoreria |
| `tipo` | enum | `ingreso` o `egreso` |
| `concepto` | varchar(191) | Descripcion |
| `monto` | decimal(14,2) | Monto |
| `saldo_anterior` | decimal(14,2) | Saldo antes del movimiento |
| `saldo_posterior` | decimal(14,2) | Saldo despues |
| `usuario_id` | bigint FK | Usuario |
| `referencia_tipo` | varchar(50) nullable | Tipo de referencia |
| `referencia_id` | bigint nullable | ID de referencia |
| `observaciones` | text nullable | Notas |
| `moneda_id` | bigint FK nullable | Moneda (para multi-moneda) |
| `monto_moneda_original` | decimal(14,2) nullable | Monto en moneda original |
| `saldo_anterior_moneda`, `saldo_posterior_moneda` | decimal(14,2) nullable | Saldos en moneda extranjera |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `provision_fondos`
Transferencias de tesoreria a caja (para fondear cajas).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `tesoreria_id` | bigint FK | Tesoreria origen |
| `caja_id` | bigint FK | Caja destino |
| `monto` | decimal(14,2) | Monto transferido |
| `usuario_entrega_id` | bigint FK | Usuario que entrega |
| `usuario_recibe_id` | bigint FK nullable | Usuario que recibe |
| `fecha` | timestamp | Fecha |
| `estado` | enum | `pendiente`, `confirmado`, `cancelado` |
| `movimiento_tesoreria_id` | bigint FK nullable | Movimiento generado en tesoreria |
| `movimiento_caja_id` | bigint FK nullable | Movimiento generado en caja |
| `observaciones` | text nullable | Notas |
| `moneda_id` | bigint FK nullable | Moneda |
| `monto_moneda_original` | decimal(14,2) nullable | Monto en moneda original |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `rendicion_fondos`
Transferencias de caja a tesoreria (rendicion de efectivo al cerrar turno).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `caja_id` | bigint FK | Caja origen |
| `tesoreria_id` | bigint FK | Tesoreria destino |
| `monto_declarado` | decimal(14,2) | Monto declarado por el usuario |
| `monto_sistema` | decimal(14,2) | Monto segun sistema |
| `monto_entregado` | decimal(14,2) | Monto efectivamente entregado |
| Otros campos similares a provision_fondos | | |

### 2.8 Bancos / Cuentas Empresa

#### Tabla: `cuentas_empresa`
Cuentas bancarias y billeteras digitales de la empresa.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Nombre descriptivo |
| `tipo` | enum | `banco` o `billetera_digital` |
| `subtipo` | varchar(50) nullable | `cuenta_corriente`, `caja_ahorro`, `mercadopago`, `uala`, `paypal`, `otro` |
| `banco` | varchar(100) nullable | Nombre del banco |
| `numero_cuenta` | varchar(50) nullable | Numero de cuenta |
| `cbu` | varchar(22) nullable | CBU |
| `alias` | varchar(50) nullable | Alias de transferencia |
| `titular` | varchar(191) nullable | Titular |
| `moneda_id` | bigint FK nullable | Moneda de la cuenta |
| `saldo_actual` | decimal(14,2) | Saldo actual |
| `activo` | boolean | Si esta activa |
| `orden` | int | Orden de visualizacion |
| `color` | varchar(7) nullable | Color para UI |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `movimientos_cuenta_empresa`
Movimientos en cuentas bancarias/billeteras. Patron append-only con contraasientos.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cuenta_empresa_id` | bigint FK | Cuenta |
| `tipo` | enum | `ingreso` o `egreso` |
| `concepto_movimiento_cuenta_id` | bigint FK nullable | Concepto del movimiento |
| `concepto_descripcion` | varchar(255) | Descripcion |
| `monto` | decimal(14,2) | Monto |
| `saldo_anterior` | decimal(14,2) | Saldo antes |
| `saldo_posterior` | decimal(14,2) | Saldo despues |
| `origen_tipo` | varchar(50) nullable | `VentaPago`, `CobroPago`, `TransferenciaCuentaEmpresa`, `DepositoBancario`, `Manual` |
| `origen_id` | bigint nullable | ID del origen |
| `usuario_id` | bigint FK | Usuario |
| `sucursal_id` | bigint FK nullable | Sucursal |
| `estado` | enum | `activo` o `anulado` |
| `anulado_por_movimiento_id` | bigint FK nullable | Contraasiento |
| `observaciones` | text nullable | Notas |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `cuenta_empresa_sucursal`
Pivot que vincula cuentas empresa con sucursales. Si no tiene sucursales asignadas, esta disponible en todas.

### 2.9 Compras

#### Tabla: `compras`
Compras realizadas a proveedores.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `numero` | varchar(191) | Numero de comprobante |
| `sucursal_id` | bigint FK | Sucursal |
| `proveedor_id` | bigint FK | Proveedor |
| `caja_id` | bigint FK nullable | Caja de pago |
| `usuario_id` | bigint FK | Usuario que registro |
| `fecha` | timestamp | Fecha |
| `subtotal` | decimal(12,2) | Subtotal |
| `iva` | decimal(12,2) | Total IVA |
| `total` | decimal(12,2) | Total |
| `forma_pago` | enum | `efectivo`, `tarjeta`, `transferencia`, `cheque`, `cuenta_corriente` |
| `estado` | enum | `pendiente`, `completada`, `cancelada` |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `compras_detalle`
Items de cada compra.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `compra_id` | bigint FK | Compra |
| `articulo_id` | bigint FK | Articulo comprado |
| `cantidad` | decimal(12,3) | Cantidad |
| `precio_unitario` | decimal(12,2) | Precio unitario |
| `subtotal` | decimal(12,2) | Subtotal |
| `iva_porcentaje` | decimal(5,2) | Porcentaje IVA |
| `iva_monto` | decimal(12,2) | Monto IVA |
| `total` | decimal(12,2) | Total del item |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `proveedores`
Proveedores del comercio. Un proveedor puede estar vinculado a un cliente (para cuentas corrientes cruzadas) y puede ser una sucursal interna (transferencias entre sucursales).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(50) nullable | Codigo |
| `nombre` | varchar(191) | Nombre |
| `razon_social` | varchar(191) nullable | Razon social |
| `cuit` | varchar(20) nullable | CUIT |
| `email`, `telefono`, `direccion` | varchar nullable | Contacto |
| `condicion_iva_id` | int FK nullable | Condicion IVA |
| `es_sucursal_interna` | boolean | Si es una sucursal propia (para transferencias) |
| `sucursal_id` | bigint FK nullable | FK a sucursal si es interna |
| `cliente_id` | bigint FK nullable | FK a cliente vinculado |
| `activo` | boolean | Si esta activo |
| `created_at`, `updated_at` | timestamp | Timestamps |

### 2.10 Configuracion y Precios

#### Tabla: `sucursales`
Ya descrita en seccion 1.2. Campos adicionales relevantes:
- `control_stock_venta` -- Modo de control de stock al vender: `bloquea`, `advierte`, `no_controla`
- `control_stock_produccion` -- Idem para produccion
- `facturacion_fiscal_automatica` -- Si emite factura automaticamente
- `agrupa_articulos_venta` -- Si agrupa articulos repetidos en pantalla de venta
- `agrupa_articulos_impresion` -- Si agrupa en impresion

#### Tabla: `formas_pago`
Formas de pago disponibles. Pueden ser simples (un concepto) o mixtas (multiples conceptos).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Nombre |
| `codigo` | varchar(50) nullable | Codigo |
| `descripcion` | text nullable | Descripcion |
| `concepto_pago_id` | bigint FK nullable | Concepto de pago (NULL para mixtas) |
| `es_mixta` | boolean | Si es forma de pago mixta |
| `concepto` | enum | `efectivo`, `tarjeta_debito`, `tarjeta_credito`, `transferencia`, `wallet`, `cheque`, `credito_cliente`, `otro` |
| `permite_cuotas` | boolean | Si permite cuotas |
| `ajuste_porcentaje` | decimal(8,2) | Ajuste: positivo=recargo, negativo=descuento |
| `factura_fiscal` | boolean | Si genera factura fiscal |
| `activo` | boolean | Si esta activa |
| `orden` | int | Orden de visualizacion |
| `cuenta_empresa_id` | bigint FK nullable | Cuenta empresa vinculada (registro automatico) |
| `moneda_id` | bigint FK nullable | Moneda |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `formas_pago_sucursales`
Pivot forma de pago - sucursal. Permite habilitar/deshabilitar y personalizar ajustes por sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `forma_pago_id` | bigint FK | Forma de pago |
| `sucursal_id` | bigint FK | Sucursal |
| `activo` | boolean | Si esta disponible |
| `ajuste_porcentaje` | decimal(8,2) nullable | Ajuste especifico (NULL = usar el de la forma de pago) |
| `factura_fiscal` | boolean nullable | Override de factura fiscal |

#### Tabla: `formas_pago_cuotas`
Planes de cuotas configurables por forma de pago.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `forma_pago_id` | bigint FK | Forma de pago |
| `sucursal_id` | bigint FK nullable | NULL = aplica a todas |
| `cantidad_cuotas` | int | 1, 3, 6, 12, etc. |
| `recargo_porcentaje` | decimal(5,2) | Recargo (0 = sin interes) |
| `descripcion` | varchar(200) nullable | Descripcion del plan |
| `activo` | boolean | Si esta activo |

#### Tabla: `conceptos_pago`
Conceptos de pago disponibles (tipos reales de medio de pago).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(50) UNIQUE | `efectivo`, `tarjeta_debito`, `tarjeta_credito`, `transferencia`, `wallet`, `cheque`, `otro` |
| `nombre` | varchar(100) | Nombre legible |
| `descripcion` | text nullable | Descripcion |
| `permite_cuotas` | boolean | Si permite cuotas |
| `permite_vuelto` | boolean | Si permite dar vuelto |
| `activo` | boolean | Si esta activo |
| `orden` | int | Orden |

#### Tabla: `listas_precios`
Listas de precios por sucursal con vigencia temporal y condiciones.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `nombre` | varchar(255) | Nombre |
| `codigo` | varchar(50) nullable | Codigo (UNIQUE por sucursal) |
| `descripcion` | text nullable | Descripcion |
| `ajuste_porcentaje` | decimal(8,2) | Ajuste global (+ recargo, - descuento) |
| `redondeo` | enum | `ninguno`, `entero`, `decena`, `centena` |
| `aplica_promociones` | boolean | Si permite aplicar promociones |
| `promociones_alcance` | enum | `todos` o `excluir_lista` |
| `vigencia_desde`, `vigencia_hasta` | date nullable | Rango de fechas |
| `dias_semana` | text JSON nullable | Dias de la semana [0-6] |
| `hora_desde`, `hora_hasta` | time nullable | Rango horario |
| `cantidad_minima`, `cantidad_maxima` | decimal nullable | Rango de cantidad |
| `es_lista_base` | boolean | Si es la lista base (obligatoria, no eliminable) |
| `prioridad` | int | Menor numero = mayor prioridad |
| `activo` | boolean | Si esta activa |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `lista_precio_articulos`
Ajustes por articulo o categoria dentro de una lista.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `lista_precio_id` | bigint FK | Lista de precios |
| `articulo_id` | bigint FK nullable | Articulo especifico |
| `categoria_id` | bigint FK nullable | Categoria (aplica a todos sus articulos) |
| `precio_fijo` | decimal(12,2) nullable | Precio fijo (pisa al precio base) |
| `ajuste_porcentaje` | decimal(8,2) nullable | Ajuste porcentual |
| `precio_base_original` | decimal(12,2) nullable | Precio base al momento de crear |

#### Tabla: `lista_precio_condiciones`
Condiciones que deben cumplirse para que aplique una lista.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `lista_precio_id` | bigint FK | Lista de precios |
| `tipo_condicion` | enum | `por_forma_pago`, `por_forma_venta`, `por_canal`, `por_total_compra` |
| `forma_pago_id` | bigint FK nullable | FK a formas_pago |
| `forma_venta_id` | bigint FK nullable | FK a formas_venta |
| `canal_venta_id` | bigint FK nullable | FK a canales_venta |
| `monto_minimo` | decimal(12,2) nullable | Monto minimo de compra |
| `monto_maximo` | decimal(12,2) nullable | Monto maximo de compra |

#### Tabla: `promociones`
Promociones y descuentos. Cada promocion es especifica de una sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `nombre` | varchar(255) | Nombre |
| `descripcion` | text nullable | Descripcion |
| `codigo_cupon` | varchar(50) nullable UNIQUE | Codigo de cupon (NULL = automatica) |
| `tipo` | enum | `descuento_porcentaje`, `descuento_monto`, `precio_fijo`, `recargo_porcentaje`, `recargo_monto`, `descuento_escalonado` |
| `valor` | decimal(12,2) | Valor segun tipo |
| `prioridad` | int | Orden de aplicacion (1 = mayor prioridad) |
| `combinable` | boolean | Si puede combinarse con otras |
| `activo` | boolean | Si esta activa |
| `vigencia_desde`, `vigencia_hasta` | date nullable | Rango de fechas |
| `dias_semana` | text JSON nullable | Dias de la semana |
| `hora_desde`, `hora_hasta` | time nullable | Rango horario |
| `usos_maximos` | int nullable | Limite de usos totales |
| `usos_por_cliente` | int nullable | Limite de usos por cliente |
| `usos_actuales` | int | Contador de usos |
| `created_at`, `updated_at`, `deleted_at` | timestamp | Timestamps + soft delete |

#### Tabla: `promociones_especiales`
Promociones de tipo NxM (lleva 3 paga 2), Combo y Menu.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal |
| `nombre` | varchar(191) | Nombre |
| `tipo` | enum | `nxm`, `nxm_avanzado`, `combo`, `menu` |
| `nxm_lleva` | int nullable | Cantidad que lleva (para NxM basico) |
| `nxm_paga` | int nullable | Cantidad que paga |
| `nxm_bonifica` | int nullable | Cantidad bonificada |
| `beneficio_tipo` | enum | `gratis` o `descuento` |
| `beneficio_porcentaje` | decimal(5,2) nullable | Porcentaje de descuento (si no es gratis) |
| `nxm_articulo_id` | bigint FK nullable | Articulo especifico para NxM basico |
| `nxm_categoria_id` | bigint FK nullable | Categoria para NxM basico |
| `usa_escalas` | boolean | Si usa escalas variables |
| `precio_tipo` | enum | `fijo` o `porcentaje` (para combos) |
| `precio_valor` | decimal(12,2) nullable | Valor del combo/menu |
| `prioridad` | int | Prioridad |
| Vigencia, dias_semana, horas | ... | Igual que promociones |
| `forma_venta_id`, `canal_venta_id`, `forma_pago_id` | bigint FK nullable | Condiciones de aplicacion |
| `usos_maximos`, `usos_actuales` | int | Control de usos |

#### Tabla: `tipos_iva`
Tipos de IVA segun AFIP (en tabla tenant, no en config).

Codigos AFIP: 3=0%, 4=10.5%, 5=21%, 6=27%, 8=5%, 9=2.5%.

#### Tabla: `monedas`
Monedas disponibles.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(3) UNIQUE | Codigo ISO (ARS, USD, EUR, etc.) |
| `nombre` | varchar(50) | Nombre |
| `simbolo` | varchar(5) | Simbolo ($, US$, etc.) |
| `es_principal` | boolean | Si es la moneda principal (ARS) |
| `decimales` | tinyint | Cantidad de decimales |
| `activo` | boolean | Si esta activa |
| `orden` | int | Orden |

#### Tabla: `tipo_cambio`
Tasas de cambio entre monedas.

#### Tablas de facturacion fiscal

- **`cuits`**: CUITs de la empresa con certificados AFIP. Campos: `numero_cuit`, `razon_social`, `condicion_iva_id`, `entorno_afip` (testing/produccion), `certificado_path`, `clave_path`.
- **`puntos_venta`**: Puntos de venta fiscales. Cada uno tiene un CUIT asociado y un numero.
- **`comprobantes_fiscales`**: Comprobantes emitidos ante AFIP. Tipos: factura_a/b/c/e/m, nota_credito_a/b/c/e/m, nota_debito_a/b/c/e/m, recibo_a/b/c. Incluye CAE, datos del receptor, totales desglosados por IVA.
- **`comprobante_fiscal_ventas`**: Pivot comprobante-venta (un comprobante puede cubrir una o mas ventas).
- **`comprobante_fiscal_iva`**: Desglose de IVA por alicuota dentro de un comprobante.
- **`comprobante_fiscal_items`**: Items detallados del comprobante fiscal.
- **`cuit_sucursal`**: Asignacion de CUITs a sucursales.
- **`punto_venta_caja`**: Asignacion de puntos de venta a cajas.

---

## 3. Logica de Negocio

### 3.1 Flujo de Venta

Paso a paso completo desde que el usuario abre la pantalla de Nueva Venta hasta que se confirma el pago:

1. **Apertura de pantalla**: Se verifica que el usuario tenga una caja activa y abierta. Se carga la lista de articulos disponibles para la sucursal activa.

2. **Seleccion de contexto**: El usuario puede opcionalmente seleccionar:
   - Canal de venta (mostrador, delivery, web)
   - Forma de venta (para llevar, para consumir)
   - Cliente (si es venta a nombre de alguien)
   - Lista de precios (override manual)

3. **Busqueda de lista de precios aplicable** (automatica, via `PrecioService`):
   - Prioridad 1: Lista seleccionada manualmente por el vendedor
   - Prioridad 2: Lista asignada al cliente
   - Prioridad 3: Listas que cumplan condiciones (forma de pago, canal, forma de venta, monto), ordenadas por prioridad
   - Prioridad 4: Lista base de la sucursal (fallback, siempre existe)

4. **Agregado de articulos**: El usuario busca y agrega articulos al carrito. Para cada articulo:
   - Se busca el **precio base efectivo** (override de sucursal o global)
   - Se aplica la **lista de precios**: busca precio fijo para articulo, luego ajuste para articulo, luego ajuste para categoria, luego ajuste del encabezado
   - Se aplica **redondeo** segun la lista
   - Se buscan y aplican **promociones** (si la lista lo permite):
     - Se obtienen todas las promociones vigentes, activas, con usos disponibles
     - Se filtran por condiciones (articulo, categoria, forma de pago, etc.)
     - Se separan en excluyentes y combinables
     - Se evalua la mejor combinacion (busqueda exhaustiva para <=15 promos, greedy para mas)
     - Limite maximo de descuento: 70%
   - Se buscan y aplican **promociones especiales** (NxM, combos, menus)
   - Si el articulo tiene **opcionales**, se muestran para que el cliente elija

5. **Calculo de totales**:
   - Subtotal = suma de (precio_unitario * cantidad) de todos los items
   - Descuentos de promociones
   - IVA (incluido o excluido segun configuracion del articulo)
   - Total = subtotal + IVA - descuentos

6. **Seleccion de forma de pago**: El usuario elige como paga el cliente:
   - **Forma simple**: Un solo medio (efectivo, tarjeta, etc.)
   - **Forma mixta**: Multiples medios en una venta
   - **Cuenta corriente**: A credito del cliente
   - Para cada medio se calcula: ajuste (recargo/descuento), cuotas si aplica, vuelto si es efectivo

7. **Confirmacion de venta** (via `VentaService::crearVenta`):
   - Se valida stock disponible (segun modo: `bloquea`, `advierte`, `no_controla`)
   - Se valida credito del cliente (si es CC)
   - Se valida que la caja este abierta
   - Se genera numero de venta (formato: CCCC-NNNNNNNN, donde CCCC es la caja)
   - Se crea el registro en `ventas`
   - Se crean los detalles en `ventas_detalle`
   - Se guardan promociones aplicadas
   - Se guardan opcionales seleccionados
   - Se descuenta stock (tabla `stock` cache + movimiento en `movimientos_stock`)
   - Se registran pagos en `venta_pagos`
   - Se crean movimientos de caja (solo para pagos en efectivo)
   - Se registran movimientos en cuenta empresa (si la forma de pago tiene cuenta vinculada)
   - Si es CC: se crea movimiento en `movimientos_cuenta_corriente` y se actualiza cache del cliente
   - Si la sucursal tiene facturacion automatica y la forma de pago lo requiere: se emite comprobante fiscal via AFIP

8. **Resultado**: Se muestra el resumen de la venta con opcion de imprimir ticket.

### 3.2 Sistema de Precios

El sistema de precios tiene **4 niveles de especificidad** (de mayor a menor):

1. **Precio fijo por articulo en la lista**: Si el articulo tiene un `precio_fijo` en `lista_precio_articulos`, se usa directamente.

2. **Ajuste porcentaje por articulo en la lista**: Si el articulo tiene un `ajuste_porcentaje` en `lista_precio_articulos`, se aplica al precio base.

3. **Ajuste porcentaje por categoria en la lista**: Si la categoria del articulo tiene un `ajuste_porcentaje` en `lista_precio_articulos`, se aplica al precio base.

4. **Ajuste global del encabezado de la lista**: Se aplica el `ajuste_porcentaje` de `listas_precios` al precio base.

**Precio base**: El precio base puede venir de:
- `articulos_sucursales.precio_base` (override por sucursal, si existe y no es NULL)
- `articulos.precio_base` (precio global del articulo)

**Redondeo**: Despues de aplicar el ajuste, se redondea segun la configuracion de la lista:
- `ninguno` -- 2 decimales
- `entero` -- Al entero mas cercano
- `decena` -- A la decena (10, 20, 30...)
- `centena` -- A la centena (100, 200, 300...)

**Promociones**:
- Las promociones se aplican DESPUES de la lista de precios
- La lista puede configurar si aplican promociones (`aplica_promociones`)
- Alcance `excluir_lista`: articulos con precio especial en la lista no participan en promociones
- Las promociones pueden ser **combinables** o **excluyentes**
- Para combinables, se busca la mejor combinacion posible (exhaustiva 2^n para <=15 promos)
- Limite de descuento: 70% maximo

**Ajustes por forma de pago**: Cada forma de pago puede tener un `ajuste_porcentaje` (recargo o descuento). Tambien puede tener planes de cuotas con recargo.

**IVA**: Se calcula segun el `tipo_iva_id` del articulo. Si `precio_iva_incluido` es true, el IVA se extrae del precio. Si es false, se agrega.

### 3.3 Stock Dual

El sistema mantiene dos representaciones del stock:

- **`stock`** (tabla cache): Contiene la cantidad actual de cada articulo en cada sucursal. Se actualiza atomicamente en cada operacion. Es lo que se consulta para verificar disponibilidad.

- **`movimientos_stock`** (tabla historial, append-only): Contiene TODOS los movimientos de stock. Nunca se borran ni modifican registros; las anulaciones se hacen con contraasientos.

**Como se descuenta stock en una venta:**
1. Se consulta `articulos_sucursales.modo_stock` para el articulo en la sucursal:
   - `ninguno` -- No se descuenta stock
   - `unitario` -- Se descuenta la cantidad vendida del articulo directamente
   - `receta` -- Se descuenta stock de cada ingrediente segun la receta
2. Se actualiza la tabla `stock` (cache)
3. Se crea un registro en `movimientos_stock` con tipo `venta`

**Como se agrega stock en produccion:**
1. Se resuelve la receta del articulo para la sucursal (override > default)
2. Se calculan las cantidades de ingredientes necesarias
3. Se descuenta stock de cada ingrediente (movimiento tipo `produccion_salida`)
4. Se aumenta stock del producto terminado (movimiento tipo `produccion_entrada`)

**Anulaciones**: Se crean contraasientos que invierten entrada/salida. El movimiento original se marca con `anulado_por_movimiento_id` apuntando al contraasiento. Ambos permanecen activos; se cancelan matematicamente.

**Reconciliacion**: Si el cache (`stock.cantidad`) se desincroniza, se puede recalcular desde movimientos: `SUM(entrada) - SUM(salida)` de movimientos activos.

### 3.4 Cuenta Corriente (Ledger)

La cuenta corriente usa un **principio append-only (ledger)**. Nunca se modifican ni borran registros. Las anulaciones se hacen con contraasientos.

**Registros de debito** (aumentan deuda): Una venta a CC genera un movimiento con `debe = monto_venta`, `haber = 0`.

**Registros de credito** (disminuyen deuda): Un cobro genera un movimiento con `debe = 0`, `haber = monto_cobrado`.

**Saldo a favor**: Anticipos generan `saldo_favor_haber`. Uso de saldo a favor genera `saldo_favor_debe` + `haber` (disminuye deuda y consume saldo a favor).

**Calculo de saldos** (siempre en tiempo real):
```sql
-- Saldo deudor de un cliente en una sucursal
SELECT COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor
FROM {PREFIX}movimientos_cuenta_corriente
WHERE cliente_id = ? AND sucursal_id = ? AND estado = 'activo';

-- Saldo a favor de un cliente (global)
SELECT COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
FROM {PREFIX}movimientos_cuenta_corriente
WHERE cliente_id = ? AND estado = 'activo';
```

**Cache**: Los campos `saldo_deudor_cache` y `saldo_a_favor_cache` en la tabla `clientes` se actualizan periodicamente como cache. La fuente de verdad son los movimientos.

**Dias de mora**: Se calculan como la diferencia entre hoy y la `fecha_vencimiento` de la venta. Si hay tasa de interes configurada, se calcula interes proporcional: `saldo * (tasa_mensual / 30) / 100 * dias_mora`.

**FIFO**: Los cobros se aplican a las ventas mas antiguas primero (First In, First Out).

### 3.5 Flujo de Cobro

Paso a paso desde que el usuario registra un cobro:

1. **Seleccion del cliente**: Se busca y selecciona el cliente con deuda.

2. **Consulta de deuda**: Se obtienen las ventas pendientes de cobro ordenadas por antiguedad (FIFO) via `CobroService::obtenerVentasPendientesFIFO`. Se trabaja a nivel de `VentaPago` (pagos individuales de CC), no de venta completa.

3. **Seleccion de ventas a saldar**: El usuario puede:
   - Seleccionar manualmente que ventas pagar y cuanto de cada una
   - Ingresar un monto y dejarlo distribuir automaticamente (FIFO)
   - Registrar un anticipo (sin seleccionar ventas)

4. **Calculo de intereses**: Para cada venta seleccionada, se calcula:
   - Dias de mora = hoy - fecha_vencimiento
   - Interes = saldo_pendiente * (tasa_diaria/100) * dias_mora
   - Tasa diaria = tasa_mensual / 30

5. **Uso de saldo a favor**: Si el cliente tiene saldo a favor, se puede usar parcial o totalmente para cancelar deuda.

6. **Forma de pago**: Se selecciona como paga el cliente (igual que en ventas).

7. **Confirmacion** (via `CobroService::registrarCobro`):
   - Se genera numero de recibo (RC-XX-NNNNNNNN)
   - Se crea registro en `cobros`
   - Por cada venta aplicada: se crea `cobro_ventas`, se actualiza `venta_pagos.saldo_pendiente`, se actualiza `ventas.saldo_pendiente_cache`
   - Se crean registros en `cobro_pagos` (desglose de pagos)
   - Si afecta caja: se crean movimientos de caja
   - Si la forma de pago tiene cuenta empresa: se registra movimiento
   - Se crean movimientos en `movimientos_cuenta_corriente`:
     - Tipo `cobro` por cada venta aplicada (haber = monto_aplicado)
     - Tipo `anticipo` si quedo excedente (saldo_favor_haber = monto)
     - Tipo `uso_saldo_favor` si se uso saldo a favor (haber = monto + saldo_favor_debe = monto)
   - Se actualiza cache del cliente

### 3.6 Cajas y Tesoreria

**Apertura de caja**:
1. Se verifica que la caja este cerrada
2. Se determina el saldo inicial segun `modo_carga_inicial`: manual (el usuario ingresa), ultimo_cierre (se carga automaticamente), monto_fijo (usa `monto_fijo_inicial`)
3. Se crea movimiento de caja tipo `apertura`
4. Se actualiza saldo de la caja

**Movimientos de caja**: Solo registran efectivo fisico. Los pagos con tarjeta, transferencia, etc. NO generan movimiento de caja. Los tipos de referencia incluyen: venta, compra, cobro, apertura, retiro, ajuste, transferencia, vuelto.

**Cierre de turno** (via `CajaService`):
1. Se cierran las cajas (individual o por grupo)
2. Se genera reporte con:
   - Saldo inicial, ingresos, egresos, saldo final
   - Saldo declarado por el usuario vs saldo sistema
   - Diferencia (sobrante/faltante)
   - Desglose por forma de pago y concepto
3. Se marcan todos los movimientos con `cierre_turno_id`
4. Se marcan ventas y cobros con `cierre_turno_id`
5. Si hay grupo de cierre y tesoreria vinculada: se puede hacer rendicion automatica

**Tesoreria**: Centraliza el efectivo de la sucursal.
- **Provision de fondos**: Tesoreria -> Caja (para fondear cajas)
- **Rendicion de fondos**: Caja -> Tesoreria (al cerrar turno)
- **Deposito bancario**: Tesoreria -> Banco
- **Arqueo**: Verificacion de saldo fisico vs sistema
- Soporta **multi-moneda**: saldos independientes por moneda extranjera

### 3.7 Produccion

Flujo de produccion (via `ProduccionService`):

1. **Seleccion de articulos a producir**: El usuario selecciona articulo(s) y cantidad(es).

2. **Resolucion de recetas**: Para cada articulo, se resuelve su receta (override de sucursal > default).

3. **Calculo de ingredientes**: Se multiplican las cantidades de ingredientes por la cantidad a producir.

4. **Validacion de stock** (segun `control_stock_produccion` de la sucursal):
   - `bloquea` -- Si falta stock, no permite producir
   - `advierte` -- Si falta stock, muestra advertencia pero permite continuar
   - `no_controla` -- No verifica stock

5. **Confirmacion**:
   - Se crea registro en `producciones`
   - Se crea detalle en `produccion_detalles` (articulos producidos)
   - Se crea detalle en `produccion_ingredientes` (ingredientes usados)
   - Se descuenta stock de cada ingrediente (movimiento `produccion_salida`)
   - Se aumenta stock del producto terminado (movimiento `produccion_entrada`)

6. **Anulacion**: Se crean contraasientos para revertir todos los movimientos de stock.

### 3.8 Compras

Flujo de compra (via `CompraService`):

1. Se selecciona el proveedor.
2. Se ingresan los articulos comprados con precio unitario, cantidad e IVA.
3. Se elige la forma de pago: efectivo (genera egreso en caja), cuenta corriente (queda pendiente), transferencia, etc.
4. Al confirmar:
   - Se crea registro en `compras`
   - Se crean detalles en `compras_detalle`
   - Se aumenta stock de cada articulo (movimiento `compra`)
   - Si es pago en efectivo: se crea movimiento de caja (egreso)
   - Si es cuenta corriente: la compra queda en estado `pendiente` con saldo_pendiente > 0

**Compras internas**: Si el proveedor es una sucursal interna (`es_sucursal_interna = true`), la compra representa una transferencia fiscal entre sucursales.

### 3.9 Facturacion Fiscal

**Tipos de comprobante** segun AFIP:
- **Factura A**: Para clientes Responsables Inscriptos. Discrimina IVA.
- **Factura B**: Para clientes Consumidores Finales, Monotributistas o Exentos. No discrimina IVA.
- **Factura C**: Emitida por Monotributistas.
- **Nota de Credito A/B/C**: Para anular parcial o totalmente una factura.
- **Nota de Debito A/B/C**: Para debitos adicionales.

**Cuando se genera**:
- Automaticamente al confirmar una venta, si la sucursal tiene `facturacion_fiscal_automatica = true` y la forma de pago tiene `factura_fiscal = true`
- Manualmente desde la pantalla de ventas

**Datos del receptor**: Se determinan segun la condicion IVA del cliente:
- Responsable Inscripto -> Factura A (requiere CUIT)
- Consumidor Final -> Factura B (puede ser anonimo hasta cierto monto)
- Monotributista -> Factura B
- Exento -> Factura B

**CUIT y Punto de Venta**: Cada sucursal puede tener uno o mas CUITs asignados (tabla `cuit_sucursal`). Cada CUIT tiene puntos de venta (tabla `puntos_venta`). Los puntos de venta se asignan a cajas (tabla `punto_venta_caja`).

**Proceso de emision**:
1. Se determina el tipo de comprobante segun condicion IVA del receptor
2. Se arman los datos del comprobante (items, IVA desglosado por alicuota, totales)
3. Se envia a AFIP via webservice (entorno testing o produccion segun configuracion del CUIT)
4. AFIP devuelve CAE y fecha de vencimiento
5. Se almacena el comprobante con la respuesta completa

---

## 4. Patrones de Consulta

### 4.1 Consultas Comunes

> **IMPORTANTE**: En todas las queries, reemplazar `{PREFIX}` por el prefijo del comercio (ej: `000001_`). Todas las tablas estan en la base de datos `bcn_pymes`.

#### Total de ventas de hoy

```sql
SELECT COUNT(*) as cantidad,
       SUM(total_final) as total
FROM {PREFIX}ventas
WHERE sucursal_id = ?
  AND DATE(fecha) = CURDATE()
  AND estado = 'completada'
  AND deleted_at IS NULL;
```

#### Total de ventas de un periodo

```sql
SELECT COUNT(*) as cantidad,
       SUM(total_final) as total,
       SUM(iva) as total_iva
FROM {PREFIX}ventas
WHERE sucursal_id = ?
  AND fecha >= ?
  AND fecha <= ?
  AND estado = 'completada'
  AND deleted_at IS NULL;
```

#### Saldo deudor de un cliente

```sql
SELECT COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor,
       COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
FROM {PREFIX}movimientos_cuenta_corriente
WHERE cliente_id = ?
  AND estado = 'activo';
```

#### Saldo deudor de un cliente en una sucursal especifica

```sql
SELECT COALESCE(SUM(debe), 0) - COALESCE(SUM(haber), 0) as saldo_deudor
FROM {PREFIX}movimientos_cuenta_corriente
WHERE cliente_id = ?
  AND sucursal_id = ?
  AND estado = 'activo';
```

#### Stock actual de un articulo en una sucursal

```sql
-- Via cache (rapido)
SELECT cantidad FROM {PREFIX}stock
WHERE articulo_id = ? AND sucursal_id = ?;

-- Via movimientos (preciso, para reconciliar)
SELECT COALESCE(SUM(entrada), 0) - COALESCE(SUM(salida), 0) as stock
FROM {PREFIX}movimientos_stock
WHERE articulo_id = ? AND sucursal_id = ? AND estado = 'activo';
```

#### Movimientos de caja del turno actual

```sql
SELECT mc.*, c.nombre as caja_nombre
FROM {PREFIX}movimientos_caja mc
JOIN {PREFIX}cajas c ON c.id = mc.caja_id
WHERE mc.caja_id = ?
  AND mc.cierre_turno_id IS NULL
ORDER BY mc.created_at DESC;
```

#### Articulos mas vendidos (por cantidad)

```sql
SELECT a.id, a.nombre, a.codigo,
       SUM(vd.cantidad) as total_cantidad,
       SUM(vd.total) as total_facturado
FROM {PREFIX}ventas_detalle vd
JOIN {PREFIX}ventas v ON v.id = vd.venta_id
JOIN {PREFIX}articulos a ON a.id = vd.articulo_id
WHERE v.sucursal_id = ?
  AND v.fecha >= ?
  AND v.fecha <= ?
  AND v.estado = 'completada'
  AND v.deleted_at IS NULL
GROUP BY a.id, a.nombre, a.codigo
ORDER BY total_cantidad DESC
LIMIT 20;
```

#### Clientes con deuda vencida

```sql
SELECT c.id, c.nombre, c.cuit,
       c.saldo_deudor_cache as deuda,
       c.dias_mora_max,
       c.tasa_interes_mensual
FROM {PREFIX}clientes c
WHERE c.saldo_deudor_cache > 0
  AND c.tiene_cuenta_corriente = 1
  AND c.deleted_at IS NULL
ORDER BY c.saldo_deudor_cache DESC;
```

#### Ventas pendientes de cobro (cuenta corriente)

```sql
SELECT v.id, v.numero, v.fecha, v.total_final,
       v.saldo_pendiente_cache, v.fecha_vencimiento,
       cl.nombre as cliente
FROM {PREFIX}ventas v
JOIN {PREFIX}clientes cl ON cl.id = v.cliente_id
WHERE v.es_cuenta_corriente = 1
  AND v.saldo_pendiente_cache > 0
  AND v.estado IN ('completada', 'pendiente')
  AND v.deleted_at IS NULL
ORDER BY v.fecha ASC;
```

#### Desglose de ventas por forma de pago

```sql
SELECT fp.nombre as forma_pago,
       COUNT(DISTINCT vp.venta_id) as cantidad_ventas,
       SUM(vp.monto_final) as total
FROM {PREFIX}venta_pagos vp
JOIN {PREFIX}formas_pago fp ON fp.id = vp.forma_pago_id
JOIN {PREFIX}ventas v ON v.id = vp.venta_id
WHERE v.sucursal_id = ?
  AND v.fecha >= ?
  AND v.fecha <= ?
  AND v.estado = 'completada'
  AND v.deleted_at IS NULL
  AND vp.estado = 'activo'
GROUP BY fp.id, fp.nombre
ORDER BY total DESC;
```

#### Stock bajo minimo

```sql
SELECT s.id, a.nombre, a.codigo,
       s.cantidad, s.cantidad_minima,
       (s.cantidad_minima - s.cantidad) as faltante
FROM {PREFIX}stock s
JOIN {PREFIX}articulos a ON a.id = s.articulo_id
WHERE s.sucursal_id = ?
  AND s.cantidad_minima IS NOT NULL
  AND s.cantidad < s.cantidad_minima
  AND a.deleted_at IS NULL
ORDER BY faltante DESC;
```

#### Resumen de movimientos de cuenta empresa

```sql
SELECT ce.nombre, ce.tipo, ce.saldo_actual,
       (SELECT SUM(monto) FROM {PREFIX}movimientos_cuenta_empresa
        WHERE cuenta_empresa_id = ce.id AND tipo = 'ingreso' AND estado = 'activo'
        AND created_at >= ?) as ingresos_periodo,
       (SELECT SUM(monto) FROM {PREFIX}movimientos_cuenta_empresa
        WHERE cuenta_empresa_id = ce.id AND tipo = 'egreso' AND estado = 'activo'
        AND created_at >= ?) as egresos_periodo
FROM {PREFIX}cuentas_empresa ce
WHERE ce.activo = 1
ORDER BY ce.orden;
```

#### Historial de cuenta corriente de un cliente

```sql
SELECT mcc.fecha, mcc.tipo, mcc.concepto,
       mcc.debe, mcc.haber,
       mcc.saldo_favor_debe, mcc.saldo_favor_haber,
       mcc.descripcion_comprobantes,
       mcc.estado
FROM {PREFIX}movimientos_cuenta_corriente mcc
WHERE mcc.cliente_id = ?
  AND mcc.estado = 'activo'
ORDER BY mcc.fecha DESC, mcc.id DESC
LIMIT 50;
```

### 4.2 Convenciones de Datos

**Estados posibles de cada entidad:**

| Entidad | Estados | Descripcion |
|---|---|---|
| Venta | `completada`, `pendiente`, `cancelada` | Pendiente = cuenta corriente sin saldar |
| Compra | `completada`, `pendiente`, `cancelada` | Pendiente = cuenta corriente proveedor |
| Cobro | `activo`, `anulado` | |
| VentaPago | `activo`, `pendiente`, `anulado` | |
| CobroPago | `activo`, `anulado` | |
| MovimientoStock | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaCorriente | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaEmpresa | `activo`, `anulado` | Anulado = contraasiento |
| Caja | `abierta`, `cerrada` | |
| ComprobanteFiscal | `pendiente`, `autorizado`, `rechazado`, `anulado` | |
| Produccion | `confirmado`, `anulado` | |
| ProvisionFondo | `pendiente`, `confirmado`, `cancelado` | |
| DepositoBancario | `pendiente`, `confirmado`, `cancelado` | |

**Formatos de fecha:**
- Fechas se almacenan como `timestamp` o `date` en MySQL.
- Fechas de creacion/actualizacion: `created_at`, `updated_at` (timezone del servidor).
- Fechas de negocio (`venta.fecha`, `cobro.fecha`): generalmente `date` o `timestamp`.

**Formatos de moneda:**
- Montos se almacenan como `decimal(12,2)` (2 decimales).
- Costos unitarios como `decimal(10,4)` (4 decimales).
- Porcentajes como `decimal(5,2)` o `decimal(8,2)`.
- Tasas de cambio como `decimal(14,6)` (6 decimales).
- La moneda principal es ARS (Peso Argentino).
- Monedas extranjeras se manejan con `moneda_id` y `tipo_cambio_tasa`.

**Campos cache vs calculados:**
- `stock.cantidad` -- **Cache** del stock real. Se recalcula desde `movimientos_stock`.
- `clientes.saldo_deudor_cache` -- **Cache** de la deuda. Se recalcula desde `movimientos_cuenta_corriente`.
- `clientes.saldo_a_favor_cache` -- **Cache** del saldo a favor. Idem.
- `ventas.saldo_pendiente_cache` -- **Cache** del saldo pendiente. Se recalcula desde `venta_pagos`.
- `cuentas_empresa.saldo_actual` -- Se actualiza atomicamente con cada movimiento.
- `tesorerias.saldo_actual` -- Se actualiza atomicamente con cada movimiento.
- `cajas.saldo_actual` -- Se actualiza atomicamente con cada movimiento de caja.

**Soft deletes:**
Las siguientes tablas usan soft delete (`deleted_at` no nulo = eliminado):
- `ventas`, `clientes`, `articulos`, `categorias`, `cobros`, `promociones`, `promociones_especiales`, `listas_precios`, `grupos_opcionales`, `opcionales`, `comprobantes_fiscales`, `grupos_etiquetas`, `etiquetas`, `impresoras`, `cuits`, `puntos_venta`.

Al consultar estas tablas, siempre agregar `AND deleted_at IS NULL` a menos que se quieran incluir registros eliminados.

**Patron append-only (ledger):**
Las tablas `movimientos_stock`, `movimientos_cuenta_corriente` y `movimientos_cuenta_empresa` siguen el patron append-only:
- Los registros nunca se modifican ni eliminan.
- Las anulaciones se hacen creando un **contraasiento** que invierte los montos.
- El original se vincula al contraasiento via `anulado_por_movimiento_id`.
- Ambos registros permanecen con `estado = 'activo'` -- se cancelan matematicamente.
- Para calcular saldos: sumar todos los movimientos activos.
