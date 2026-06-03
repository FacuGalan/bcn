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
| `es_invitacion_total` | boolean default false | Si todos los items de la venta son cortesia (invitados) |
| `invitacion_motivo` | varchar(500) nullable | Motivo de la invitacion total (texto libre) |
| `invitado_por_usuario_id` | bigint FK nullable | FK logico a `config.users.id` — usuario que registro la cortesia |
| `invitado_at` | timestamp nullable | Timestamp cuando se registro la invitacion |
| `total_invitado` | decimal(15,2) default 0 | Cache: suma de `monto_invitado` de las lineas invitadas |

**Indices**: `sucursal_id`, `fecha`, `estado`, `cliente_id`, `caja_id`, `(es_invitacion_total, fecha)`.

**Estados posibles**:
- `completada` -- Venta pagada completamente o venta contado.
- `pendiente` -- Venta a cuenta corriente con saldo pendiente de cobro.
- `cancelada` -- Venta anulada. El stock y movimientos de caja se revierten.

#### Tabla: `ventas_detalle`
Cada linea/item de una venta. Puede representar un articulo del catalogo o un concepto libre (item sin articulo asociado).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_id` | bigint FK | Venta a la que pertenece |
| `articulo_id` | bigint FK nullable | Articulo vendido. NULL si `es_concepto=true` (ON DELETE SET NULL) |
| `es_concepto` | boolean | Si true, es un concepto libre sin articulo asociado |
| `concepto_descripcion` | varchar(255) nullable | Descripcion del concepto libre (solo si `es_concepto=true`) |
| `concepto_categoria_id` | bigint FK nullable | Categoria opcional del concepto para IVA (ON DELETE SET NULL) |
| `tipo_iva_id` | bigint FK | Tipo de IVA aplicado |
| `lista_precio_id` | bigint FK nullable | Lista de precios usada para este item |
| `cantidad` | decimal(12,3) | Cantidad vendida |
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
| `es_invitacion` | boolean default false | Si este item es cortesia (invitado) |
| `invitacion_motivo` | varchar(500) nullable | Motivo de la invitacion del item |
| `invitado_por_usuario_id` | bigint FK nullable | FK logico a `config.users.id` — usuario que invito el item |
| `invitado_at` | timestamp nullable | Timestamp de la invitacion del item |
| `monto_invitado` | decimal(15,2) default 0 | Cache: cantidad * precio_unitario_original. Monto monetario regalado por este item |
| `precio_unitario_original` | decimal(15,2) nullable | Snapshot del precio unitario antes de invitar. Permite revertir la cortesia |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indice**: `(es_invitacion)` para reportes de items invitados.

**Helper `obtenerNombre(): string`**: Devuelve `concepto_descripcion` si `es_concepto=true`, de lo contrario `articulo->nombre`. Usar siempre este metodo en vistas de impresion y listados para mostrar el nombre del item independientemente de su tipo.

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
| `venta_pago_reemplazado_id` | bigint FK nullable | FK a `{PREFIX}venta_pagos.id` — el pago que este registro reemplaza (append-only) |
| `operacion_origen` | enum | Origen del registro: `venta_original`, `cambio_pago`, `pago_agregado`, `anulacion_sin_reemplazo` |
| `creado_por_usuario_id` | bigint FK nullable | FK a `config.users.id` — quien creo este registro (puede diferir del usuario de la venta) |
| `nota_credito_generada_id` | bigint FK nullable | FK a `{PREFIX}comprobantes_fiscales.id` — NC disparada por la anulacion de este pago |
| `comprobante_fiscal_nuevo_id` | bigint FK nullable | FK a `{PREFIX}comprobantes_fiscales.id` — FC nueva emitida cuando este pago es reemplazo |
| `estado_facturacion` | enum | Estado fiscal del pago: `no_facturado`, `facturado`, `pendiente_de_facturar`, `error_arca` |
| `datos_snapshot_json` | json nullable | Snapshot JSON del estado del pago al momento de anularse (forense): forma_pago, montos, caja, usuario, turno |
| `integracion_pago_transaccion_id` | bigint FK nullable | FK a `{PREFIX}integraciones_pago_transacciones.id` (ON DELETE SET NULL) — transaccion QR que cobro este pago. Solo presente en el venta_pago que se cobro via integracion dentro de un desglose mixto o pago unico por QR. Habilita trazabilidad y bloqueo de modificacion/anulacion. |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Nota**: Los pagos originales de la venta tienen `operacion_origen = 'venta_original'`. Al cambiar un pago, el pago viejo queda con `estado = 'anulado'` y el nuevo tiene `venta_pago_reemplazado_id` apuntando al anulado y `operacion_origen = 'cambio_pago'`. Este patron es append-only, consistente con movimientos_stock y movimientos_cuenta_corriente.

**`estado_facturacion` — valores posibles**:
- `no_facturado`: pago sin comprobante fiscal asociado.
- `facturado`: pago con `comprobante_fiscal_id` valido y autorizado.
- `pendiente_de_facturar`: la FC nueva no pudo emitirse (error ARCA en Fase B del cambio de pago); se reintenta manualmente desde el reporte de pagos pendientes.
- `error_arca`: operador decidio sacar el pago del circuito automatico con motivo; ya no aparece en reintentos.

**Backfill**: la migracion `2026_04_16_100000` agrega la columna y rellena: pagos con `comprobante_fiscal_id` no nulo → `facturado`; el resto → `no_facturado`.

#### Tabla: `venta_pago_ajustes`
Audit log de cada operacion atomica de cambio de pagos. Un registro por operacion (cambio/agregado/eliminacion), independientemente de cuantos `venta_pagos` se generen internamente.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `venta_id` | bigint FK | FK a `{PREFIX}ventas.id` |
| `sucursal_id` | bigint FK | FK a `sucursales.id` (para filtros rapidos) |
| `tipo_operacion` | enum | `cambio_pago`, `agregar_pago`, `eliminar_pago` |
| `venta_pago_anulado_id` | bigint FK nullable | FK a `{PREFIX}venta_pagos.id` del pago anulado (NULL si es "agregar") |
| `venta_pago_nuevo_id` | bigint FK nullable | FK a `{PREFIX}venta_pagos.id` del pago nuevo (NULL si es "eliminar") |
| `forma_pago_anterior_id` | bigint FK nullable | FK a `formas_pago.id` snapshot — puede haberse modificado el catalogo |
| `forma_pago_nueva_id` | bigint FK nullable | FK a `formas_pago.id` |
| `monto_anterior` | decimal(12,2) nullable | `monto_final` del pago anulado |
| `monto_nuevo` | decimal(12,2) nullable | `monto_final` del pago nuevo |
| `delta_total` | decimal(12,2) | Diferencia en `total_final` de la venta (+/-) |
| `delta_fiscal` | boolean | true si cambio la condicion fiscal del pago |
| `turno_original_id` | bigint FK nullable | `cierre_turno_id` del pago anulado (NULL si era turno abierto) |
| `es_post_cierre` | boolean | true si `turno_original_id` no es NULL — aparece en el reporte de ajustes post-cierre |
| `nc_emitida_id` | bigint FK nullable | FK a `{PREFIX}comprobantes_fiscales.id` de NC generada |
| `fc_nueva_id` | bigint FK nullable | FK a `{PREFIX}comprobantes_fiscales.id` de FC nueva emitida |
| `nc_emitida_flag` | boolean | true si la matriz exigia NC y se emitio |
| `fc_nueva_flag` | boolean | true si se emitio FC nueva |
| `salteo_nc_autorizado` | boolean | true si el usuario omitio la NC cuando la matriz preguntaba (requiere `func.modificar_pagos_sin_nc`) |
| `config_auto_al_operar` | boolean | Snapshot de `sucursales.facturacion_fiscal_automatica` al momento de la operacion |
| `motivo` | text | Motivo obligatorio ingresado por el usuario (minimo 10 caracteres) |
| `descripcion_auto` | text | Descripcion narrativa auto-generada: "Cambio Debito Visa $300 por Transferencia Galicia $300" |
| `usuario_id` | bigint FK | FK a `config.users.id` — quien realizo el cambio |
| `ip_origen` | varchar(45) nullable | IP del request |
| `user_agent` | varchar(500) nullable | Navegador |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indices**: `idx_vpa_venta` (`venta_id`), `idx_vpa_sucursal_fecha` (`sucursal_id`, `created_at`), `idx_vpa_post_cierre` (`es_post_cierre`, `created_at`), `idx_vpa_usuario` (`usuario_id`).

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
| `pesable` | tinyint(1) | Si se vende por peso (abre modal de ingreso por peso/valor en POS) |
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

#### Import/Export de Categorias

**Service**: `App\Services\CategoriaImportExportService`

- `generarPlantilla(): string` — Genera un archivo `.xlsx` temporal con cabeceras "Nombre" y "Prefijo". Devuelve la ruta del archivo en el directorio temporal del sistema.
- `importar(UploadedFile $archivo): array{creadas:int, actualizadas:int, errores:array}` — Lee el archivo (.xlsx, .xls o .csv), detecta las columnas por nombre en la primera fila (busqueda case-insensitive), e itera las filas restantes con logica best-effort:
  - Fila con nombre y prefijo ambos vacios: se omite silenciosamente.
  - Nombre ausente, nombre > 100 caracteres o prefijo > 10 caracteres: se registra error en `errores` y se continua con la siguiente fila.
  - El prefijo se convierte a MAYUSCULAS antes de persistir.
  - Si el nombre ya existe (`Categoria::where('nombre', $nombre)->first()`): se actualiza solo el campo `prefijo`.
  - Si el nombre no existe: se crea con `color = '#3B82F6'` y `activo = true`.
  - Si hubo al menos una creacion o actualizacion: llama a `CatalogoCache::clear()` para invalidar el cache de catalogos.
  - Errores de base de datos por fila se capturan individualmente (try/catch) y se loguean sin abortar el resto.

**Dependencia**: `phpoffice/phpspreadsheet ^5.6`

#### Import/Export de Articulos

**Service**: `App\Services\ArticuloImportExportService`

- `generarPlantilla(bool $conDatos = false, ?int $sucursalId = null): string` — Genera un archivo `.xlsx` temporal. Si `$conDatos = true` (requiere `$sucursalId`), prellena las filas con todos los articulos de la sucursal (incluyendo soft-deleted, con fondo rojo y columna `P` "Eliminado"). Devuelve la ruta del archivo temporal.
- `importar(UploadedFile $archivo, int $sucursalId, int $usuarioId, bool $dryRun = false): array{creadas:int, actualizadas:int, sin_cambios:int, errores:array}` — Lee el archivo .xlsx, detecta columnas por cabecera (case-insensitive) e itera filas con logica best-effort. Cuando `$dryRun = true` valida y cuenta sin persistir nada.

**Columnas del archivo** (15 columnas activas; col P solo en export con datos):

| Col | Nombre | Notas |
|---|---|---|
| A | ID | Solo lectura. Vacio en filas nuevas. |
| B | Codigo | Autogenerado con prefijo de categoria si queda vacio en fila nueva. |
| C | Codigo de barras | Tipo TEXT en Excel (evita notacion cientifica). |
| D | Nombre | Obligatorio. |
| E | Descripcion | Opcional. |
| F | Categoria | Nombre de la categoria activa. Dropdown de validacion nativo. |
| G | Unidad | Default: "unidad". |
| H | Tipo IVA | Nombre del tipo IVA activo. Dropdown de validacion nativo. |
| I | Precio IVA incluido | "Si"/"No". |
| J | Materia prima | "Si"/"No". |
| K | Pesable | "Si"/"No". |
| L | Activo | "Si"/"No". Afecta `articulos_sucursales.activo` de la sucursal activa. |
| M | Vendible | "Si"/"No". Afecta `articulos_sucursales.vendible`. |
| N | Modo stock | "ninguno"/"unitario"/"receta". Afecta `articulos_sucursales.modo_stock`. |
| O | Precio | Precio efectivo de la sucursal activa (ver logica de precio abajo). |
| P | Eliminado | Solo export con datos. "Si" = soft-deleted (informativo, fila roja). |

**Logica de precio al importar**:
1. Se calcula el precio efectivo anterior: `articulos_sucursales.precio_base ?? articulos.precio_base`.
2. Si la celda O esta vacia: `nuevoOverride = null` (restaura precio base global).
3. Si el precio recibido coincide con `articulos.precio_base` (diferencia < 0.001): `nuevoOverride = null` (no se necesita override).
4. Si difiere: `nuevoOverride = precioRecibido`.
5. Si el override no cambia: no hay actualizacion ni historial.
6. Si el override cambia y el precio efectivo resultante difiere del anterior: se llama a `HistorialPrecio::registrar()` con `origen = 'importacion'` y `detalle = 'Importado desde {nombre_archivo}'`.

**Logica de creacion/actualizacion**:
- Fila con ID: busca `Articulo::find($id)`. Actualiza datos base y el pivot `articulos_sucursales` de la sucursal activa. Permite rename de nombre/codigo. Si se cambia la categoria a una con prefijo distinto, el codigo se regenera.
- Fila sin ID: crea el articulo y lo asocia a la sucursal activa via `articulos_sucursales`.
- Articulo soft-deleted con ID: se ignora con error (no se restaura por importacion).
- Cada fila se procesa en su propia transaccion (`DB::connection('pymes_tenant')->transaction()`): articulo + pivot + historial son atomicos por fila; un error en una fila no aborta el resto.

**Hoja auxiliar interna** (`_datos`): hoja oculta que contiene los rangos de validacion para los dropdowns de categorias y tipos de IVA. La validacion de celdas en Excel apunta a rangos de esta hoja.

#### Tabla: `historial_precios`
Registro append-only de cambios de precio por articulo y sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | Articulo |
| `sucursal_id` | bigint FK nullable | Sucursal afectada (NULL = cambio global) |
| `precio_anterior` | decimal(12,2) | Precio efectivo antes del cambio |
| `precio_nuevo` | decimal(12,2) | Precio efectivo despues del cambio |
| `usuario_id` | bigint FK | Usuario que realizo el cambio (conexion `config`) |
| `origen` | varchar libre | Fuente del cambio. Valores conocidos: `manual`, `cambio_masivo`, `importacion` |
| `porcentaje_cambio` | decimal(5,2) | Variacion porcentual respecto al precio anterior |
| `detalle` | text nullable | Texto descriptivo adicional (ej: nombre del archivo importado) |
| `created_at` | timestamp | Fecha del cambio |

**Notas**: `UPDATED_AT = null` (tabla inmutable). Usar `HistorialPrecio::registrar(array $datos)` para insertar (no `create()` directamente). El campo `origen` es VARCHAR libre; no requiere migracion para agregar nuevos origenes.

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
| `cantidad` | decimal(12,3) | Cantidad actual en stock |
| `cantidad_minima` | decimal(12,3) nullable | Stock minimo (para alertas) |
| `cantidad_maxima` | decimal(12,3) nullable | Stock maximo |
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
| `entrada` | decimal(12,3) | Cantidad que entra (0 si es salida) |
| `salida` | decimal(12,3) | Cantidad que sale (0 si es entrada) |
| `stock_resultante` | decimal(12,3) | Stock despues del movimiento |
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
| `mp_pos_id` | varchar(50) nullable | ID numerico devuelto por MP al crear el POS. Indexado. |
| `mp_pos_external_id` | varchar(40) nullable | Identificador externo en MP. Formato: `BCN{comercio_id}POS{caja_id}` (alfanumerico SIN guiones — MP exige este formato para POS). Unico (UNIQUE INDEX). Solo se envia al crear; en updates MP lo rechaza. |
| `mp_pos_qr_url` | text nullable | URL del PNG del QR estatico (`qr.image` en respuesta MP). |
| `mp_pos_qr_pdf_url` | text nullable | URL del PDF imprimible del QR (`qr.template_document` en respuesta MP). |
| `usa_pantalla_cliente` | tinyint(1) default 0 | Si el puesto tiene un segundo monitor orientado al cliente para mostrar el QR de cobro. Habilita el boton "Conectar pantalla cliente" en NuevaVenta y Pedidos Mostrador. |
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
- `latitud` -- `decimal(10,7)` nullable. Coordenada geografica. Requerida por MP para crear Store.
- `longitud` -- `decimal(10,7)` nullable. Coordenada geografica. Requerida por MP para crear Store.
- `localidad` -- `varchar(100)` nullable. Localidad de la sucursal. Requerida por MP (`city_name`).
- `provincia` -- `varchar(100)` nullable. Codigo ISO 3166-2 de provincia argentina (ej: `AR-B`, `AR-C`). Se traduce a nombre oficial al armar payloads externos. Ver `Sucursal::PROVINCIAS_AR[]` y `Sucursal::provinciaNombre()`.
- `mp_store_id` -- `varchar(50)` nullable. ID numerico devuelto por MP al crear la Store. Se usa para actualizar/eliminar. Indexado.
- `mp_store_external_id` -- `varchar(60)` nullable. Identificador externo en MP. Formato: `BCN-{comercio_id}-{sucursal_id}`. Unico (UNIQUE INDEX). Solo se envia al **crear** la store; en updates MP lo rechaza por colision consigo mismo.
- `config_pantalla_cliente` -- `json` nullable, cast `array`. Configuracion de personalizacion de la pantalla orientada al cliente. Se mergea con `Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS` antes de usarse. Ver helpers en el modelo `Sucursal`.

**Defaults de `config_pantalla_cliente`** (`Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS`):

| Clave | Tipo / Valores | Default |
|---|---|---|
| `mostrar_logo` | bool | `true` |
| `mostrar_nombre` | bool | `true` |
| `color_fondo` | hex string | `#222036` |
| `animacion` | `ninguna` / `respiracion` / `aurora` | `ninguna` |
| `color_acento` | hex string | `#22d3ee` |
| `color_texto` | `auto` / hex string | `auto` |
| `mensaje_idle` | string | `""` (usa texto por defecto en el frontend) |
| `tamano_logo` | `sm` / `md` / `lg` | `md` |

**Helpers en el modelo `Sucursal`**:
- `getConfigPantallaCliente(): array` — merge de `config_pantalla_cliente` (DB) con `CONFIG_PANTALLA_CLIENTE_DEFAULTS`. Garantiza que nunca falten claves aunque la columna este NULL o incompleta.
- `logoPantallaClienteUrl(): string|null` — devuelve la URL del logo a mostrar: logo de la sucursal si existe, logo de la empresa como fallback. Usa `asset()`.
- `nombrePantallaCliente(): string` — nombre a mostrar: `nombre_publico` ?? `nombre` ?? nombre de la empresa.
- `usaPantallaCliente(): bool` — true si al menos una caja de la sucursal tiene `usa_pantalla_cliente = 1`.

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

#### Tabla: `{PREFIX}forma_pago_integraciones` (tenant)
Pivote N:M entre `formas_pago` e `integraciones_pago`. Define que integraciones tiene asignadas una forma de pago simple y como debe usarlas al cobrar.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `forma_pago_id` | bigint FK | Forma de pago (ON DELETE CASCADE) |
| `integracion_pago_id` | bigint FK | Integracion de pago (ON DELETE CASCADE) |
| `modo_default` | varchar(50) nullable | Modo de cobro de la integracion (ej: `qr_dinamico`, `qr_estatico`). Una FP usa un unico modo; este campo es la fuente de verdad |
| `modos_permitidos` | json nullable | Array json conservado por compatibilidad de esquema; siempre se persiste como `[modo_default]` (espejo de un solo elemento) |
| `es_principal` | boolean | Si es la integracion preseleccionada cuando la FP tiene varias |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indices**: UNIQUE `(forma_pago_id, integracion_pago_id)`.

**Relacion en FormaPago**: `integraciones()` — `BelongsToMany` con `withPivot(['modo_default','modos_permitidos','es_principal'])`.

**Helpers en FormaPago**:
- `tieneIntegracion()` — devuelve true si la FP tiene al menos una integracion vinculada.
- `integracionPrincipal()` — devuelve la `IntegracionPago` marcada `es_principal`, o la primera si ninguna esta marcada, o NULL si no hay ninguna.

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
| `permite_integracion` | boolean | Si conceptos de este tipo pueden vincularse a una integracion de pago externa (wallet, transferencia = true; efectivo, tarjeta = false) |
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
| `estatica` | boolean | Si los precios estan congelados en un snapshot |
| `precios_congelados_at` | timestamp nullable | Fecha y hora del ultimo snapshot de precios |
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
| `origen` | enum nullable | `manual` (ingresado por el usuario) o `snapshot` (calculado por CongelarPreciosListaService) |

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
| `nxm_articulos_ids` | text nullable | Array JSON de IDs de articulos para NxM (seleccion multiple) |
| `nxm_categoria_id` | bigint FK nullable | Categoria para NxM basico |
| `nxm_categorias_ids` | text nullable | Array JSON de IDs de categorias para NxM (seleccion multiple) |
| `usa_escalas` | boolean | Si usa escalas variables |
| `precio_tipo` | enum | `fijo` o `porcentaje` (para combos) |
| `precio_valor` | decimal(12,2) nullable | Valor del combo/menu |
| `prioridad` | int | Prioridad |
| Vigencia, dias_semana, horas | ... | Igual que promociones |
| `forma_venta_id`, `canal_venta_id`, `forma_pago_id` | bigint FK nullable | Condiciones de aplicacion |
| `formas_pago_ids` | text nullable | Array JSON de IDs de formas de pago (seleccion multiple) |
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

### 2.11 Puntos y Cupones de Fidelizacion

#### Tabla: `configuracion_puntos`
Una fila por comercio. Controla el programa de fidelizacion.
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `activo` | tinyint(1) | Programa habilitado globalmente |
| `modo_acumulacion` | enum(global, por_sucursal) | Modo de saldo |
| `monto_por_punto` | decimal(12,2) | Cuantos $ para ganar 1 punto |
| `valor_punto_canje` | decimal(12,2) | Cuanto vale 1 punto en $ al canjear |
| `minimo_canje` | int | Minimo puntos para habilitar canje |
| `redondeo` | enum(floor, round, ceil) | Redondeo de puntos fraccionarios |

#### Tabla: `configuracion_puntos_sucursales`
Activacion por sucursal: `sucursal_id` + `activo`.

#### Tabla: `movimientos_puntos`
Ledger append-only (patron MovimientoCuentaCorriente). Contraasientos para anulaciones.
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `cliente_id` | bigint | FK clientes |
| `sucursal_id` | bigint | FK sucursales |
| `tipo` | enum | acumulacion, canje_descuento, canje_articulo, canje_cupon, ajuste_manual, anulacion |
| `puntos` | int | Positivo = acumulacion, negativo = consumo |
| `monto_asociado` | decimal(12,2) | Monto de la transaccion |
| `venta_id` | bigint | FK ventas (shortcut) |
| `estado` | enum(activo, anulado) | |
| `anulado_por_movimiento_id` | bigint | FK al contraasiento |

#### Tabla: `cupones`
| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `codigo` | varchar(50) | Codigo unico (CUP-XXXXXX) |
| `tipo` | enum(puntos, promocional) | Origen del cupon |
| `cliente_id` | bigint NULL | Solo para tipo=puntos |
| `modo_descuento` | enum(monto_fijo, porcentaje) | Tipo de descuento |
| `valor_descuento` | decimal(12,2) | Monto o porcentaje |
| `aplica_a` | enum(total, articulos) | A que aplica |
| `uso_maximo` | int | 0 = ilimitado |
| `uso_actual` | int | Contador |
| `fecha_vencimiento` | date NULL | NULL = no vence |

#### Tabla: `cupon_articulos`
Vincula cupon con articulos especificos: `cupon_id` + `articulo_id` + `cantidad` (NULL = todas las unidades).
Cuando `cantidad` tiene valor, el descuento se aplica a max esa cantidad de unidades del articulo.

#### Tabla: `cupon_formas_pago`
Restriccion de formas de pago validas para un cupon: `cupon_id` + `forma_pago_id`.
Si la tabla esta vacia para un cupon, aplica a todas las formas de pago.
Validacion: al finalizar venta, TODAS las formas de pago usadas deben estar en la lista permitida (Opcion C).

#### Tabla: `cupon_usos`
Registra cada uso: `cupon_id`, `venta_id`, `cliente_id`, `sucursal_id`, `monto_descontado`, `usuario_id`.

#### Campos agregados a tablas existentes
- **clientes**: `programa_puntos_activo`, `puntos_acumulados_cache`, `puntos_canjeados_cache`, `puntos_saldo_cache`, `ultimo_movimiento_puntos_at`
- **formas_pago**: `multiplicador_puntos` (0=no suma, 2=doble)
- **articulos**: `puntos_canje` (NULL=no canjeable)
- **ventas**: `descuento_general_tipo/valor/monto`, `cupon_id`, `monto_cupon`, `puntos_ganados`, `puntos_usados`
- **ventas_detalle**: `pagado_con_puntos`, `puntos_usados`
- **venta_pagos**: `es_pago_puntos`, `puntos_usados`
- **roles**: `descuento_maximo_porcentaje` (tope por rol)

#### Services
- **PuntosService**: Acumulacion, canje, contraasientos, saldos, cache. Patron ledger.
- **CuponService**: Creacion (desde puntos o promocional), validacion, aplicacion, reversion.

### 2.12 Pedidos por Mostrador

Modulo para gestionar pedidos tomados en el local antes de convertirlos en venta. Cada pedido tiene ciclo de vida propio (`estado_pedido`) y estado de cobro (`estado_pago`) independientes. Al convertirse en venta, el pedido queda en estado `facturado` y se crea la `Venta` correspondiente en la tabla `ventas`.

#### Tabla: `pedidos_mostrador`
Documento operativo principal del modulo.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `numero` | int unsigned nullable | Numero correlativo por sucursal. NULL en borradores. |
| `identificador` | varchar(100) nullable | Texto libre: nombre del cliente, numero de mesa, etc. |
| `numero_beeper` | varchar(20) nullable | Numero de beeper si la sucursal usa sistema de beepers |
| `sucursal_id` | bigint FK | Sucursal donde se tomo el pedido |
| `cliente_id` | bigint FK nullable | Cliente del catalogo (NULL si es cliente temporal) |
| `nombre_cliente_temporal` | varchar(150) nullable | Nombre del cliente sin alta en sistema |
| `telefono_cliente_temporal` | varchar(30) nullable | Telefono del cliente temporal |
| `caja_id` | bigint FK nullable | Caja asociada (ON DELETE SET NULL) |
| `canal_venta_id` | bigint FK nullable | Canal de venta |
| `forma_venta_id` | bigint FK nullable | Forma de venta |
| `lista_precio_id` | bigint FK nullable | Lista de precios usada |
| `usuario_id` | bigint (FK logico cross-DB) | Usuario que creo el pedido (`config.users.id`) |
| `fecha` | timestamp | Fecha y hora de creacion del pedido |
| `estado_pedido` | enum | `borrador`, `confirmado`, `en_preparacion`, `listo`, `entregado`, `facturado`, `cancelado` |
| `estado_pago` | enum | `pendiente`, `parcial`, `pagado` -- cache recalculado sobre pagos activos |
| `subtotal` | decimal(12,2) | Suma de items |
| `iva` | decimal(12,2) | Total IVA |
| `descuento` | decimal(12,2) | Descuento general |
| `total` | decimal(12,2) | Total antes de ajuste por forma de pago |
| `ajuste_forma_pago` | decimal(12,2) | Ajuste por formas de pago |
| `total_final` | decimal(12,2) | Total a cobrar |
| `descuento_general_tipo` | enum nullable | `porcentaje` o `monto_fijo` |
| `descuento_general_valor` | decimal(12,2) nullable | Valor del descuento general |
| `descuento_general_monto` | decimal(12,2) | Monto aplicado del descuento general |
| `cupon_id` | bigint FK nullable | Cupon aplicado |
| `cupon_codigo_snapshot` | varchar(50) nullable | Snapshot del codigo del cupon |
| `monto_cupon` | decimal(12,2) | Monto descontado por cupon |
| `puntos_ganados` | int unsigned | Puntos que ganara el cliente al convertirse en venta |
| `puntos_usados` | int unsigned | Puntos canjeados en este pedido |
| `observaciones` | text nullable | Notas del pedido |
| `motivo_cancelacion` | varchar(500) nullable | Motivo al cancelar |
| `confirmado_at` | timestamp nullable | Timestamp al pasar a confirmado |
| `en_preparacion_at` | timestamp nullable | Timestamp al pasar a en_preparacion |
| `listo_at` | timestamp nullable | Timestamp al pasar a listo |
| `entregado_at` | timestamp nullable | Timestamp al pasar a entregado |
| `cancelado_at` | timestamp nullable | Timestamp de cancelacion |
| `cancelado_por_usuario_id` | bigint nullable | FK logico al usuario que cancelo |
| `venta_id` | bigint FK nullable | FK a `ventas.id` tras conversion (ON DELETE SET NULL) |
| `convertido_at` | timestamp nullable | Timestamp de conversion a venta |
| `orden_kanban` | bigint unsigned NOT NULL default 0 | Posicion dentro de la columna del Kanban. Inicializado con `id` en pedidos existentes y en `booted::created`. Renumerado al reordenar dentro de la columna. Reseteado a `id` al cambiar de columna. |
| `es_invitacion_total` | boolean default false | Si todos los items del pedido son cortesia (invitados) |
| `invitacion_motivo` | varchar(500) nullable | Motivo de la invitacion total (texto libre) |
| `invitado_por_usuario_id` | bigint nullable | FK logico a `config.users.id` — usuario que registro la cortesia |
| `invitado_at` | timestamp nullable | Timestamp cuando se registro la invitacion |
| `total_invitado` | decimal(15,2) default 0 | Cache: suma de `monto_invitado` de las lineas invitadas |
| `created_at`, `updated_at` | timestamp | Timestamps |
| `deleted_at` | timestamp nullable | Soft delete |

**Indices**: `sucursal_id`, `estado_pedido`, `estado_pago`, `fecha`, `cliente_id`, `caja_id`, `venta_id`, `telefono_cliente_temporal`, `(estado_pedido, orden_kanban)`, `(es_invitacion_total, fecha)`.

**Estados `estado_pedido`**:
- `borrador` -- Pedido en edicion, sin numero asignado, sin descuento de stock.
- `confirmado` -- Numero asignado, stock descontado, comanda impresa. Transicion inicial operativa.
- `en_preparacion` -- Cocina/produccion en proceso.
- `listo` -- Listo para entregar o retirar.
- `entregado` -- Entregado al cliente.
- `facturado` -- Convertido en venta. Estado terminal positivo.
- `cancelado` -- Cancelado con motivo. Estado terminal negativo. Revierte stock y pagos.

**Transiciones permitidas** (definidas en `PedidoMostrador::TRANSICIONES_PERMITIDAS`):
- `borrador` → `confirmado`, `cancelado`
- `confirmado` → `en_preparacion`, `listo`, `entregado`, `cancelado`
- `en_preparacion` → `listo`, `entregado`, `cancelado`
- `listo` → `entregado`, `cancelado`
- `entregado` → `facturado`, `cancelado`
- `facturado` → (ninguna, estado terminal)
- `cancelado` → (ninguna, estado terminal)

**Scope `activos()`**: excluye `facturado` y `cancelado`. Es la vista operativa predeterminada del listado.

**Hook `booted::created`**: al crear un registro nuevo, setea `orden_kanban = id` via `static::created(fn($p) => $p->update(['orden_kanban' => $p->id]))`. Garantiza que los pedidos nuevos entren al Kanban con un orden natural consistente sin requerir logica extra en el service.

**Constantes de estado de comanda** (no persisten en BD, se derivan de `detalles.comandado_at`):
- `ESTADO_COMANDA_NO = 'no_comandado'` -- todos los detalles tienen `comandado_at = null`.
- `ESTADO_COMANDA_PARCIAL = 'parcial'` -- hay mezcla: algunos `comandado_at != null` y otros `null`.
- `ESTADO_COMANDA_TOTAL = 'comandado'` -- todos los detalles tienen `comandado_at != null`.

**Accessors utiles**:
- `nombre_cliente_final`: devuelve `cliente.nombre` si hay cliente asociado, sino `nombre_cliente_temporal`.
- `total_cobrado`: suma de `monto_final` de pagos con `estado = activo`.
- `total_planificado`: suma de `monto_final` de pagos con `estado = planificado`.
- `estado_comanda`: calcula el estado de comanda derivado de la coleccion `detalles`. Si la relacion ya esta cargada la usa; si no, la consulta. Fallback `no_comandado` si el pedido no tiene detalles. Implementado como accessor PHP: `getEstadoComandaAttribute(): string`.

#### Tabla: `pedidos_mostrador_detalle`
Items del pedido. Espejo de `ventas_detalle`.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pedido_mostrador_id` | bigint FK | Pedido al que pertenece (ON DELETE CASCADE) |
| `articulo_id` | bigint FK nullable | Articulo del catalogo (ON DELETE SET NULL) |
| `es_concepto` | boolean | Si true, concepto libre sin articulo |
| `concepto_descripcion` | varchar(255) nullable | Descripcion del concepto libre |
| `cantidad` | decimal(12,3) | Cantidad (soporta decimales para pesables) |
| `precio_unitario` | decimal(12,2) | Precio unitario con IVA |
| `precio_sin_iva` | decimal(12,2) | Precio sin IVA |
| `precio_opcionales` | decimal(12,2) | Suma de precio extra de opcionales |
| `descuento_promocion` | decimal(12,2) | Descuento por promociones |
| `subtotal` | decimal(12,2) | Subtotal del item |
| `total` | decimal(12,2) | Total del item tras descuentos |
| `comandado_at` | timestamp NULL | Momento en que este item fue enviado a cocina. NULL = no comandado todavia. Agregado en migracion `2026_05_26_..._add_comandado_at_to_pedidos_mostrador_detalle`. |
| `es_invitacion` | boolean default false | Si este item es cortesia (invitado) |
| `invitacion_motivo` | varchar(500) nullable | Motivo de la invitacion del item |
| `invitado_por_usuario_id` | bigint nullable | FK logico a `config.users.id` — usuario que invito el item |
| `invitado_at` | timestamp nullable | Timestamp de la invitacion del item |
| `monto_invitado` | decimal(15,2) default 0 | Cache: cantidad * precio_unitario_original. Monto monetario regalado |
| `precio_unitario_original` | decimal(15,2) nullable | Snapshot del precio unitario antes de invitar |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indice**: `(es_invitacion)` para reportes de items invitados.

#### Tabla: `pedido_mostrador_detalle_opcionales`
Opcionales seleccionados por item de pedido. Snapshot del opcional al momento del pedido.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pedido_mostrador_detalle_id` | bigint FK | Item al que pertenece (ON DELETE CASCADE) |
| `grupo_opcional_id` | bigint FK | Grupo opcional |
| `opcional_id` | bigint FK | Opcional elegido |
| `nombre_grupo` | varchar(255) | Snapshot del nombre del grupo |
| `nombre_opcional` | varchar(255) | Snapshot del nombre del opcional |
| `cantidad` | decimal(12,3) | Cantidad del opcional |
| `precio_extra` | decimal(12,2) | Precio adicional del opcional |
| `subtotal_extra` | decimal(12,2) | Subtotal del opcional |
| `created_at` | timestamp | Timestamp de creacion |

#### Tabla: `pedido_mostrador_detalle_promociones`
Promociones aplicadas a nivel de item.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pedido_mostrador_detalle_id` | bigint FK | Item al que pertenece (ON DELETE CASCADE) |
| `tipo_promocion` | enum | `promocion`, `promocion_especial`, `lista_precio` |
| `descripcion_promocion` | varchar(255) | Descripcion de la promocion |
| `descuento_aplicado` | decimal(12,2) | Monto descontado |
| `created_at` | timestamp | Timestamp de creacion |

#### Tabla: `pedido_mostrador_promociones`
Promociones aplicadas a nivel de pedido completo.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pedido_mostrador_id` | bigint FK | Pedido al que pertenece (ON DELETE CASCADE) |
| `tipo_promocion` | enum | `promocion`, `promocion_especial`, `forma_pago`, `cupon` |
| `descripcion_promocion` | varchar(255) | Descripcion |
| `descuento_aplicado` | decimal(12,2) | Monto descontado |
| `created_at` | timestamp | Timestamp de creacion |

#### Tabla: `pedidos_mostrador_pagos`
Pagos aplicados al pedido. Espejo de `venta_pagos` sin campos fiscales.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pedido_mostrador_id` | bigint FK | Pedido al que pertenece (ON DELETE CASCADE) |
| `forma_pago_id` | bigint FK | Forma de pago utilizada |
| `concepto_pago_id` | bigint FK nullable | Concepto de pago (ON DELETE SET NULL) |
| `monto_base` | decimal(12,2) | Monto antes de ajustes |
| `ajuste_porcentaje` | decimal(6,2) | Ajuste de la forma de pago |
| `monto_ajuste` | decimal(12,2) | Monto del ajuste |
| `monto_final` | decimal(12,2) | Monto final cobrado o planificado |
| `monto_recibido` | decimal(12,2) nullable | Monto recibido del cliente (para vuelto en efectivo) |
| `vuelto` | decimal(12,2) nullable | Vuelto entregado |
| `cuotas` | tinyint unsigned nullable | Numero de cuotas |
| `referencia` | varchar(100) nullable | Numero de autorizacion, voucher, etc. |
| `es_cuenta_corriente` | boolean | Si este pago es a cuenta corriente |
| `es_pago_puntos` | boolean | Si es canje de puntos |
| `afecta_caja` | boolean | Si genera `MovimientoCaja` al materializarse |
| `estado` | enum | `activo`, `anulado`, `planificado` |
| `movimiento_caja_id` | bigint FK nullable | `MovimientoCaja` creado al materializar (ON DELETE SET NULL) |
| `anulado_at` | timestamp nullable | Fecha de anulacion |
| `motivo_anulacion` | varchar(500) nullable | Motivo de anulacion |
| `creado_por_usuario_id` | bigint (FK logico cross-DB) | Usuario que creo el pago |
| `moneda_id` | bigint FK nullable | Moneda del pago |
| `tipo_cambio_tasa` | decimal(14,6) nullable | Tasa de cambio aplicada |
| `venta_pago_id` | bigint FK nullable | FK a `venta_pagos.id` tras conversion (ON DELETE SET NULL) |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Estados `estado` en `pedidos_mostrador_pagos`**:
- `planificado` -- Pago configurado sin ejecutar. NO crea `MovimientoCaja`. NO cuenta para `estado_pago` del pedido. Sirve para pre-cargar el desglose antes de cobrar.
- `activo` -- Pago efectivamente cobrado, con `movimiento_caja_id` asociado. Cuenta para `estado_pago`.
- `anulado` -- Contraasiento aplicado (al cancelar el pedido).

**Transiciones de estado en `pedidos_mostrador_pagos`**:
- `planificado` → `activo` via `PedidoMostradorService::confirmarPagoPlanificado()` -- crea `MovimientoCaja`
- `planificado` → DELETE via `PedidoMostradorService::eliminarPagoPlanificado()` -- sin movimiento
- `activo` → `anulado` via `PedidoMostradorService::anularPago()` -- genera contraasiento en `MovimientoCaja`

**Calculo de `estado_pago` del pedido** (solo sobre pagos activos):
- Si `total_cobrado >= total_final` → `pagado`
- Si `total_cobrado > 0` → `parcial`
- Sino → `pendiente`

Los pagos `planificados` no cuentan para este calculo. Solo los `activos`.

#### Permisos del modulo

Los permisos se crean en la migracion `add_pedidos_mostrador_permissions_to_admin_roles` y se asignan a los roles Administrador y Super Administrador via `ProvisionComercioCommand::seedRolesYPermisos()` al provisionar un comercio nuevo.

| Permiso | Accion protegida |
|---------|-----------------|
| `func.pedidos_mostrador.cobrar` | Cobrar pedido (rapido, desglose, confirmar planificados) |
| `func.pedidos_mostrador.cancelar` | Cancelar pedido |
| `func.pedidos_mostrador.convertir_venta` | Convertir pedido en venta |
| `func.pedidos_mostrador.anular_pago` | Anular un pago activo del pedido |
| `func.pedidos_mostrador.modificar_pago` | Modificar un pago existente |
| `func.pedidos_mostrador.reordenar_kanban` | Reordenar cards dentro de una columna Kanban |
| `func.pedidos_mostrador.invitar_renglon` | Invitar (cortesia) un item individual del pedido |
| `func.pedidos_mostrador.invitar_pedido` | Invitar (cortesia) el pedido completo |

Los roles Administrador y Super Administrador reciben todos estos permisos automaticamente. Los roles operativos (cajero, vendedor, etc.) los reciben segun configuracion manual del comercio.

#### Campos agregados a tablas existentes

- **`sucursales`**: `pedido_mostrador_activo` (boolean, habilita el modulo por sucursal), `imprime_comanda_automatico` (boolean default 1, si es true al confirmar un pedido se llama `comandarPedido($pedido, 'todos')` automaticamente -- marca todos los detalles con `comandado_at = now()` y avanza el estado a `en_preparacion`), `pedido_mostrador_ultimo_numero` (int unsigned, contador atomico del numero correlativo).

#### Feature: Invitaciones / Cortesias

Permite registrar items o documentos completos como cortesia (precio cobrable = $0) preservando trazabilidad completa. Implementado en Pedidos por Mostrador y Ventas. Disenado como trait reutilizable para futuros canales (delivery).

**Principios de diseno:**

- Item invitado = `precio_unitario = 0` + `es_invitacion = true` + snapshot en `precio_unitario_original`. No se crea un tipo de item nuevo.
- `monto_invitado` en cada linea = `cantidad * precio_unitario_original`. Cache para reportes sin recomputar precios.
- `total_invitado` en cabecera = suma de `monto_invitado` de lineas. Util para listados sin joinear detalles.
- Items invitados se excluyen totalmente del motor de beneficios comerciales (RF-11): promos NxM, combos, menus, cupones (monto minimo), descuento general. Una cortesia y una promo son canales distintos y no se acumulan.
- El stock se descuenta normalmente (el bien fue consumido).
- Conversion pedido → venta: las columnas de invitacion se propagan 1:1 al crear la venta; `invitado_por_usuario_id` original se preserva (no se reemplaza por quien convierte).
- Reversibilidad solo mientras el documento es editable (borrador o confirmado con pago pendiente).

**Trait `WithInvitaciones`** (`app/Livewire/Concerns/Carrito/WithInvitaciones.php`):

Trait reutilizable que encapsula toda la mecanica de invitaciones en el carrito. Es compuesto por `NuevoPedidoMostrador` y `NuevaVenta`.

Props publicas del trait:
- `$invitarTodo` (bool) -- estado del switch "Invitar pedido completo" en el modal de cobro.
- `$motivoInvitacionTotal` (string) -- motivo cuando se invita todo el pedido/venta.
- `$mostrarModalInvitarTodo` (bool) -- visibilidad del modal global "Invitar pedido completo" (boton al lado de Descuentos en la vista principal).
- `$mostrarModalDesinvitarTodo` (bool) -- visibilidad del modal de confirmacion para quitar cortesia a todo.
- `$mostrarModalInvitarItem` (bool) -- visibilidad del mini-modal por item.
- `$invitarItemIndex` (?int) -- indice del item en `$this->items` que se esta invitando.
- `$invitarItemMotivo` (string) -- motivo ingresado en el mini-modal.
- `$mostrarModalDesinvitarItem` (bool) -- visibilidad del modal de des-invitacion.
- `$desinvitarItemIndex` (?int) -- indice del item que se va a des-invitar.
- `$totalInvitado` (float) -- cache calculado: suma de `monto_invitado` de los items.

Computed properties:
- `$puedeInvitarPedido` -- si el usuario autenticado tiene permiso para invitar el documento completo.
- `$puedeInvitarRenglon` -- si el usuario tiene permiso para invitar items individuales.
- `$esInvitacionTotal` -- `true` si hay al menos un item y todos tienen `es_invitacion=true`.

Metodos publicos:
- `abrirInvitarItem(int $index)` -- valida permiso, abre el mini-modal para el item.
- `confirmarInvitarItem()` -- aplica invitacion al item, cierra modal, recalcula totales.
- `cerrarModalInvitarItem()` -- cierra mini-modal sin modificar.
- `abrirDesinvitarItem(int $index)` -- valida permiso, abre confirmacion de des-invitacion.
- `confirmarDesinvitarItem()` -- quita invitacion del item, restaura precio, recalcula.
- `cerrarModalDesinvitarItem()` -- cierra sin modificar.
- `toggleInvitarTodo()` -- activa/desactiva el switch en el modal de cobro.
- `abrirModalInvitarTodo()` -- valida permiso y que haya items; abre el modal global.
- `cerrarModalInvitarTodo()` -- cierra sin confirmar.
- `confirmarInvitarTodo()` -- marca todos los items como invitados con el mismo motivo.
- `abrirModalDesinvitarTodo()` -- valida permiso y que sea invitacion total; abre confirmacion.
- `cerrarModalDesinvitarTodo()` -- cierra sin confirmar.
- `desinvitarTodos()` -- quita cortesia a todos los items, restaura precios, recalcula.

Hooks configurables (el componente host puede override):
- `getPermisoInvitacionPrefix(): string` -- prefijo del permiso. Default `'func.pedidos_mostrador'`. `NuevaVenta` lo override a `'func.ventas'`.
- `getPermisoInvitarTotalSuffix(): string` -- sufijo del permiso de invitacion total. Default `'invitar_pedido'`. `NuevaVenta` lo override a `'invitar_venta'`.

Logica interna de `marcarItemComoInvitado(int $index, string $motivo)`:
1. Guarda snapshot `precio_unitario_original = precio_actual` (solo si no estaba invitado ya).
2. Setea `precio = 0`.
3. Setea `es_invitacion = true`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `monto_invitado = cantidad * precio_unitario_original`.
4. Reset de todos los descuentos: `descuento`, `descuento_porcentaje`, `descuento_monto`, `descuento_promocion`, `descuento_promocion_especial`, `descuento_cupon`, `descuento_lista`, `tiene_promocion = false`, `_promociones_item = []`.
5. Reset del ajuste manual: `ajuste_manual_tipo`, `ajuste_manual_valor`, `ajuste_manual_origen`, `ajuste_manual_aplicado_por`, `precio_sin_ajuste_manual`, `tiene_ajuste = false`.

Logica interna de `desmarcarItem(int $index)`:
1. Restaura `precio = precio_unitario_original` (o el precio actual si no habia snapshot).
2. Limpia `es_invitacion = false`, `invitacion_motivo = null`, `invitado_por_usuario_id = null`, `invitado_at = null`, `monto_invitado = 0`, `precio_unitario_original = null`.
3. El caller llama `calcularVenta()` para que el motor re-evalue promos sobre el item.

**Helper `getItemsParaMotorBeneficios(): array`** (en `WithCalculoVenta`):

Retorna un array asociativo `[indice_original => item]` con solo los items que tienen `es_invitacion = false`. Preserva los indices originales de `$this->items` para que las modificaciones posteriores (aplicar descuentos, promos) afecten al item correcto.

Puntos de uso del helper (todos los lugares donde antes se iteraba `$this->items` para beneficios comerciales):
- Armar pool de items para promociones comunes.
- Armar pool para promociones especiales NxM/Combo/Menu (thresholds).
- Calcular base del descuento general porcentual.
- Calcular subtotal para validar monto minimo de cupon.
- Aplicar descuento de cupon por articulo especifico.
- Aplicar descuento general.

Los items invitados pasan por el calculo solo para totalizar su `monto_invitado` (que se suma en `$totalInvitado`); no contribuyen a los calculos de beneficios ni reciben descuentos.

**Permisos del feature (aplican en ambos canales):**

| Permiso | Canal | Accion protegida |
|---------|-------|-----------------|
| `func.pedidos_mostrador.invitar_renglon` | Pedidos Mostrador | Invitar item individual |
| `func.pedidos_mostrador.invitar_pedido` | Pedidos Mostrador | Invitar pedido completo |
| `func.ventas.invitar_renglon` | Ventas (POS) | Invitar item individual |
| `func.ventas.invitar_venta` | Ventas (POS) | Invitar venta completa |

Los permisos se crean via migracion. `ProvisionComercioCommand::seedRolesYPermisos()` los asigna automaticamente a Administrador y Super Administrador al provisionar comercios nuevos (itera todos los `func.*`).

**Integracion en `PedidoMostradorService::convertirEnVenta()`:**

Al crear la venta resultante, el service copia las columnas de invitacion:
- Cabecera: `es_invitacion_total`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `total_invitado`.
- Por linea (en `mapearDetalleAArrayVenta()`): `es_invitacion`, `invitacion_motivo`, `invitado_por_usuario_id`, `invitado_at`, `monto_invitado`, `precio_unitario_original`.
- El `invitado_por_usuario_id` original se preserva: no se reemplaza por el usuario que ejecuta la conversion.

**Integracion en `VentaService`:**

- `crearVenta()` persiste las columnas de invitacion de cabecera si estan en `$data`.
- `crearDetalleVenta()` persiste las columnas de invitacion de linea si estan en el array de detalle.
- `validarCajaAbierta()` se omite cuando `es_invitacion_total = true`: la venta cortesia no impacta caja, pero igualmente requiere `caja_id` para generar el numero de venta.

**Procesamiento de venta/pedido totalmente invitado:**

Cuando `es_invitacion_total = true` y `total_final = 0`:
- Se omite la validacion de caja abierta y de desglose de pagos.
- El documento pasa directamente a `estado_pago = pagado` sin crear registros de pago.
- `NuevaVenta` detecta `esInvitacionCompleta` antes de `procesarVenta()` y saltea la creacion de `VentaPago`, `MovimientoCaja`, movimiento en cuenta empresa y facturacion fiscal.

**Indicadores visuales (listados):**

- `pedidos-mostrador.blade.php`: badge emerald "Cortesia" en la fila de la Vista Lista y en la card del Kanban cuando `es_invitacion_total = true`.
- `ventas.blade.php`: badge emerald "Cortesia" en la fila cuando `es_invitacion_total = true`.

**Queries SQL utiles para reportes (out-of-scope en este PR, disponibles para el futuro):**

Total invitado por sucursal en un periodo:
```sql
SELECT SUM(total_invitado) FROM {PREFIX}ventas
WHERE sucursal_id = ? AND fecha BETWEEN ? AND ? AND es_invitacion_total = 1;
```

Top usuarios que invitan:
```sql
SELECT invitado_por_usuario_id, COUNT(*) AS cant, SUM(total_invitado) AS total
FROM {PREFIX}ventas
WHERE es_invitacion_total = 1
GROUP BY invitado_por_usuario_id
ORDER BY total DESC;
```

Items mas invitados:
```sql
SELECT articulo_id, SUM(monto_invitado) AS total_invitado, SUM(cantidad) AS unidades
FROM {PREFIX}ventas_detalle
WHERE es_invitacion = 1
GROUP BY articulo_id
ORDER BY total_invitado DESC;
```

Stock consumido por cortesia (con join para distinguir de stock comercial):
```sql
SELECT ms.*, vd.es_invitacion
FROM {PREFIX}movimientos_stock ms
JOIN {PREFIX}ventas_detalle vd ON vd.id = ms.origen_id AND ms.origen_tipo = 'venta_detalle'
WHERE vd.es_invitacion = 1;
```

#### Service: `PedidoMostradorService`

`app/Services/Pedidos/PedidoMostradorService.php`

Metodos principales:
- `crearPedido(array $data, array $detalles, bool $esBorrador): PedidoMostrador` -- Alta. Si no es borrador: asigna numero, descuenta stock, emite evento `PedidoCreado`, imprime comanda si esta configurado. Dispatcha broadcast `TIPO_CREADO` con `toOthers()`.
- `actualizarPedido(PedidoMostrador $pedido, array $data, array $detalles): PedidoMostrador` -- Edicion de pedido existente. **Regla de edicion ampliada**: cualquier estado no terminal (`borrador`, `confirmado`, `en_preparacion`, `listo`, `entregado`) con `estado_pago = pendiente` (sin cobros activos) es editable. Lanza excepcion si el pedido tiene cobros materializados activos. Estrategia: revierte stock previo si `estado_pedido != borrador` (stock comprometido desde `confirmado`), borra detalles, recrea y vuelve a descontar stock. Recalcula totales y `estado_pago` al final.
- `confirmarBorrador(PedidoMostrador $pedido): void` -- Convierte borrador en pedido confirmado. Asigna numero, descuenta stock. Dispatcha broadcast `TIPO_CREADO` con `toOthers()`.
- `cambiarEstado(PedidoMostrador $pedido, string $nuevoEstado, ?string $observacion): void` -- Valida transicion contra `TRANSICIONES_PERMITIDAS`, actualiza timestamps, emite `PedidoEstadoCambiado`. Resetea `orden_kanban = id` (el pedido entra en la nueva columna segun orden natural). Dispatcha broadcast `TIPO_ESTADO_CAMBIADO` con `toOthers()`.
- `confirmarPagoPlanificado(PedidoMostradorPago $pago): void` -- Materializa pago: crea `MovimientoCaja`, cambia estado a `activo`, recalcula `estado_pago`. Dispatcha broadcast `TIPO_PAGO_CAMBIADO` con `toOthers()` via `recalcularEstadoPago()`.
- `eliminarPagoPlanificado(PedidoMostradorPago $pago): void` -- Elimina sin movimiento de caja.
- `cancelarPedido(PedidoMostrador $pedido, string $motivo): void` -- Genera contraasientos de caja por pagos activos, revierte stock, marca cancelado. Dispatcha broadcast `TIPO_CANCELADO` con `toOthers()`.
- `convertirEnVenta(PedidoMostrador $pedido): Venta` -- Materializa planificados, crea `Venta` con todos sus detalles y pagos, marca pedido como `facturado`. Dispatcha broadcast `TIPO_CONVERTIDO_VENTA` con `toOthers()`.
- `agregarPago(PedidoMostrador $pedido, array $datosPago): PedidoMostradorPago` -- Agrega un pago al pedido. Si `$datosPago['planificado']` es `false`, crea `MovimientoCaja` inmediatamente (pago `activo`). Si es `true`, solo persiste el registro `planificado` sin movimiento de caja. Llama `recalcularEstadoPago()` al final. Dispatcha broadcast `TIPO_PAGO_CAMBIADO` con `toOthers()`.
- `reordenarColumna(int $sucursalId, ?int $cajaId, string $estado, array $idsOrdenados): void` -- Persiste el orden intra-columna del Kanban. Valida que `$estado` sea un estado de `ESTADOS_KANBAN`. Descarta IDs cuyo `estado_pedido` no coincida con `$estado` o cuya `caja_id` difiera de la activa (cuando hay caja activa). Renumera los IDs validos asignando valores decrecientes partiendo de `MAX(orden_kanban)` del estado + N, de modo que el primer ID del array quede con el valor mas alto (posicion 0 del DOM = top de la columna). Solo afecta drops dentro de la misma columna; los cross-column pasan por `cambiarEstado` sin tocar el orden.
- `comandarPedido(PedidoMostrador $pedido, string $alcance = 'todos'): array` -- **Metodo principal de comanda**. Orquesta: (1) marca `comandado_at = now()` en los detalles del alcance via `marcarDetallesComoComandados()`; (2) transiciona el pedido via `transicionarTrasComanda()`: CONFIRMADO → EN_PREPARACION (legal), LISTO/ENTREGADO → EN_PREPARACION (bypass con `forzarEstado()`), EN_PREPARACION/otros → sin cambio; (3) genera y retorna el payload `['escpos', 'html', 'tipo_documento' => 'comanda', 'pedido_id']` via `PlantillasComanda`. Constantes: `ALCANCE_COMANDA_TODOS = 'todos'`, `ALCANCE_COMANDA_NUEVOS = 'nuevos'`. Cuando `alcance='nuevos'`, solo procesa detalles con `comandado_at=null` y el ticket lleva header "AGREGADO". Lanza `Exception` si no hay detalles del alcance solicitado.
- `imprimirComanda(PedidoMostrador $pedido): array` -- Wrapper de `comandarPedido($pedido, 'todos')`. Mantiene el contrato publico para callers externos. Retorna payload para el evento `imprimir-comanda`.
- `imprimirPrecuenta(PedidoMostrador $pedido): array` -- Retorna payload para el evento `imprimir-precuenta`.
- `avanzarAEnPreparacionSiCorresponde(PedidoMostrador $pedido): void` -- **Deprecado**. Wrapper de compat mantenido para callers externos. La logica real fue absorbida por `transicionarTrasComanda()` dentro de `comandarPedido()`. No usar internamente.

Metodos protegidos relevantes:
- `comandarPedido` usa tres helpers protegidos:
  - `marcarDetallesComoComandados(PedidoMostrador $pedido, array $detalleIds): int` -- UPDATE masivo con `comandado_at = now()`. Refresca el timestamp aunque el detalle ya estuviera comandado (D5: reimpresion actualiza la fecha). Retorna count de filas afectadas.
  - `transicionarTrasComanda(PedidoMostrador $pedido): void` -- CONFIRMADO → `cambiarEstado(EN_PREPARACION)` (transicion legal); LISTO/ENTREGADO → `forzarEstado(EN_PREPARACION, 're-comandado')` (bypass); cualquier otro estado no transiciona.
  - `forzarEstado(PedidoMostrador $pedido, string $nuevoEstado, ?string $motivo): void` -- UPDATE directo sin validar `TRANSICIONES_PERMITIDAS`. Setea timestamp del estado destino. Registra `Log::info('Pedido estado forzado (bypass de transiciones)', ...)` con pedidoId, anterior, nuevo, motivo. Dispatcha broadcast `TIPO_ESTADO_CAMBIADO`. Solo uso interno.
- `maybeImprimirComandaAutomatica(PedidoMostrador $pedido): void` -- Si `sucursal.imprime_comanda_automatico = true`, delega en `comandarPedido($pedido, 'todos')`. El payload retornado se descarta; la impresion fisica la dispara el Livewire caller via el evento `imprimir-comanda` al recargar la lista.
- `guardConversionConPagosSuficientes(PedidoMostrador $pedido): void` -- Lanza excepcion si `total_cobrado + total_planificado < total_final`. Permite la conversion cuando los planificados cubren el resto (se materializan durante la conversion).

Todas las operaciones con escrituras usan `DB::connection('pymes_tenant')->transaction()`.

**Metodo privado `dispatchBroadcast(PedidoMostrador $pedido, string $tipo): void`**: resuelve `comercioId` via `TenantService::getComercioId()`. Si el resultado es `null` (contexto CLI o job sin sesion), hace silent-skip. Si el dispatch falla (Reverb caido, etc.), loggea `Log::warning` pero NO lanza excepcion -- la consistencia de BD es prioritaria sobre el tiempo real. Usa `broadcast(...)->toOthers()` en lugar de `dispatch()`: el header `X-Socket-ID` enviado por Laravel Echo permite a Reverb excluir automaticamente la conexion WebSocket de origen, de modo que la terminal que genera el cambio no recibe su propio evento de resaltado.

**Puntos de dispatch en el service**:

| Metodo | Tipo broadcast |
|--------|---------------|
| `crearPedido()` (si no borrador) | `TIPO_CREADO` |
| `confirmarBorrador()` | `TIPO_CREADO` |
| `cambiarEstado()` | `TIPO_ESTADO_CAMBIADO` |
| `cancelarPedido()` | `TIPO_CANCELADO` |
| `convertirEnVenta()` | `TIPO_CONVERTIDO_VENTA` |
| `recalcularEstadoPago()` (siempre) | `TIPO_PAGO_CAMBIADO` |

#### Service: `PlantillasComanda`

`app/Services/Impresion/PlantillasComanda.php`

Genera los payloads de impresion para documentos de cocina (comanda) y precuenta. Usada exclusivamente por `PedidoMostradorService::comandarPedido()` e `imprimirPrecuenta()`.

Metodos:

- `generarComandaESCPOS(PedidoMostrador $pedido, ?array $detalleIds = null, bool $esParcial = false): string`
  - Si `$detalleIds` provisto: itera solo los detalles cuyo ID este en el array. Si `null`: itera todos los detalles.
  - Si `$esParcial = true`: agrega un header destacado antes de la lista de items con el texto `*** AGREGADO ***`, centrado y en doble alto (secuencias ESC/POS: `\x1B\x61\x01` centrado + `\x1D\x21\x11` doble alto).
  - Retorna string binario ESC/POS.
- `generarComandaHTML(PedidoMostrador $pedido, ?array $detalleIds = null, bool $esParcial = false): string`
  - Mismos parametros que la version ESC/POS.
  - Si `$esParcial = true`: incluye `<div class="text-center text-2xl font-bold">*** AGREGADO ***</div>` antes de los items.
  - Retorna string HTML para preview en pantalla o impresion via navegador.
- `generarPrecuentaESCPOS(PedidoMostrador $pedido): string` -- precuenta completa en ESC/POS.
- `generarPrecuentaHTML(PedidoMostrador $pedido): string` -- precuenta completa en HTML.

**Nota de migracion de firma**: antes de este feature, `generarComandaESCPOS` y `generarComandaHTML` no aceptaban parametros adicionales. Los nuevos parametros son opcionales con defaults `null` y `false` respectivamente, manteniendo compat con callers existentes.

#### Componente Livewire: `PedidosMostrador` (Lista + Kanban)

`app/Livewire/Pedidos/PedidosMostrador.php` | Ruta: `GET /pedidos/mostrador` | Name: `pedidos.mostrador`

- Full-page, `#[Lazy]`, `SucursalAware`, `CajaAware`, `WithPagination`.
- `CajaAware` y `SucursalAware` se usan juntos; el conflicto en `getListeners()` se resuelve con `use CajaAware, SucursalAware { CajaAware::getListeners insteadof SucursalAware; }`. El override propio de `getListeners()` llama a ambos padres manualmente via alias o ignora el trait y reimplementa la logica combinada.
- Polling de 60 segundos via `wire:poll.60s` como fallback defensivo (reducido de 15s al agregar tiempo real).
- Filtra por sucursal activa, estado pedido, estado pago, rango de fechas y busqueda libre. Cuando hay caja activa, agrega filtro adicional por `caja_id`.
- Modales: detalle, cambiar estado, cobrar pendiente, convertir en venta, cancelar.
- Permisos chequeados con `hasPermissionTo()` al abrir los modales de cobrar, convertir y cancelar.
- **Tiempo real via WebSocket**: escucha el canal privado `comercios.{comercioId}.pedidos-mostrador` a traves de Laravel Echo/Reverb. Cualquier cambio en un pedido de la sucursal activa dispara un re-render automatico.
- **Dos modos de visualizacion**: Lista (paginada, todos los estados) y Kanban (agrupado por estado, solo estados activos). La preferencia se persiste en `localStorage` del dispositivo bajo la clave `pedidos_vista_preferida`.

**Constantes de Kanban**:
- `ESTADOS_KANBAN` -- array con los cuatro estados activos del tablero: `[ESTADO_CONFIRMADO, ESTADO_EN_PREPARACION, ESTADO_LISTO, ESTADO_ENTREGADO]`. Excluye `borrador`, `facturado` y `cancelado`.

**Metodo `obtenerPedidosKanban(): Collection`**:
Retorna una `Collection` de pedidos agrupada por `estado_pedido`, con una key por cada estado de `ESTADOS_KANBAN`. Si un estado no tiene pedidos, su key existe pero contiene una coleccion vacia (inicializado con foreach explicito para evitar que `array_fill_keys` comparta la misma referencia entre keys). Aplica los mismos filtros de cliente, fecha y estado de pago que la vista lista, pero ignora el filtro `filterEstadoPedido` (siempre filtra solo a `ESTADOS_KANBAN`). Cuando hay caja activa, agrega filtro por `caja_id`. Limit de 200 pedidos total; sin paginacion. Ordena por `orden_kanban DESC, id DESC`.

**Metodo `cambiarEstadoDrag(int $pedidoId, string $nuevoEstado): void`**:
Punto de entrada del drag and drop. Validaciones en orden:
1. Verifica que el pedido pertenezca a la sucursal activa del usuario.
2. Verifica que `$nuevoEstado` este dentro de `ESTADOS_KANBAN` (rechaza `cancelado` y `facturado`).
3. Verifica que la transicion desde el estado actual del pedido sea legal segun `TRANSICIONES_PERMITIDAS`.
4. Llama a `PedidoMostradorService::cambiarEstado()`. No hay gate de cobro para la columna Entregado (RF-08): el drag a Entregado siempre procede si la transicion es legal.
Ante cualquier rechazo (incluyendo excepciones del service), dispatcha `kanban-revertir` con `['pedidoId' => $pedidoId]` para que el frontend deshaga el cambio visual, y dispatcha un toast de error. No lanza excepciones al caller.

**Propiedades de tiempo real**:
- `$idsVistos` (array<int, int>) -- snapshot de IDs de pedidos no-borrador al montar. Baseline para detectar pedidos nuevos.
- `$nuevosCount` (int) -- contador de pedidos que llegaron via broadcast sin estar en el snapshot. Controla la visibilidad del badge en la UI.

**Metodos de tiempo real**:
- `snapshotIdsVistos(): void` -- consulta los IDs actuales (excluye borradores) y los almacena en `$idsVistos`. Se llama en `mount()` y al resetear el contador.
- `getListeners(): array` -- override dinamico. Resuelve `comercioId` via `TenantService::getComercioId()`. Si no hay contexto tenant, retorna array vacio (sin suscripcion). Canal: `echo-private:comercios.{comercioId}.pedidos-mostrador,.PedidoMostradorBroadcast`.
- `onPedidoBroadcast(array $event): void` -- handler del evento. Filtra por `sucursalId` (ignora pedidos de otras sucursales). Si hay caja activa, consulta `PedidoMostrador::where('id', $pedidoId)->value('caja_id')` y descarta el evento si la caja del pedido no coincide con la activa. Si el tipo es `TIPO_CREADO` y el `pedidoId` no esta en `$idsVistos`, incrementa `$nuevosCount`. Para cualquier tipo de evento (`TIPO_CREADO`, `TIPO_ESTADO_CAMBIADO`, `TIPO_PAGO_CAMBIADO`, `TIPO_CANCELADO`, `TIPO_CONVERTIDO_VENTA`), dispatcha el evento de browser `pedido-destacado` con `['pedidoId' => $pedidoId]` para activar el resaltado visual en el frontend. Todos los tipos causan re-render automatico de Livewire.
- `marcarTodosVistos(): void` -- resetea `$nuevosCount` a 0, actualiza el snapshot con `snapshotIdsVistos()` y llama `resetPage()` para refrescar la lista.

**Metodos de acciones rapidas**:
- `entregarRapido(int $pedidoId): void` -- valida acceso a sucursal y que la transicion a `ESTADO_ENTREGADO` sea legal via `TRANSICIONES_PERMITIDAS`. NO llama `gatearPorCobro` (RF-08: entregar no requiere cobro). Llama directamente `PedidoMostradorService::cambiarEstado()`. Si la sucursal tiene conversion automatica configurada, la conversion ocurre como efecto del service. Dispatcha toast.
- `cobrarRapido(int $pedidoId): void` -- requiere permiso `func.pedidos_mostrador.cobrar`. Flujo condicional: (1) si el pedido tiene pagos `planificados` Y alguno de ellos usa una `FormaPago` con integracion (`tieneIntegracion()`), llama `abrirCobrar($pedidoId)` para confirmarlos de a uno via el modal "Cobrar pendiente" (cada QR necesita su propia espera); (2) si hay planificados pero ninguno tiene integracion, los confirma todos via `confirmarPagoPlanificado()` en loop y dispatcha toast; (3) si no hay planificados, llama `abrirCobroRapido($pedidoId)` independientemente del estado del pedido.
- `pedidoEsEditable(PedidoMostrador $pedido): bool` -- helper publico. Retorna `true` si `estado_pedido` no es `cancelado` ni `facturado` Y `estado_pago === ESTADO_PAGO_PENDIENTE`. Cubre borrador, confirmado, en_preparacion, listo y entregado (mientras no haya cobros materializados). Usado en la vista para mostrar/ocultar el boton Editar.
- `pedidoEstaCobrado(PedidoMostrador $pedido): bool` -- helper protegido. Retorna `true` si `total_cobrado + total_planificado >= total_final` o si `total_final <= 0.005`. Los planificados cuentan como cubiertos porque la conversion los materializa.
- `gatearPorCobro(PedidoMostrador $pedido, string $accion): bool` -- interceptor de acciones que requieren pedido cobrado. Unico caller activo: `abrirConvertir()` con `$accion = 'convertir'`. El arm `'entregar'` fue eliminado (RF-08). Si `pedidoEstaCobrado()` es `true`, retorna `false` (el caller continua normal). Si hay saldo pendiente: almacena `$accionPendiente = $accion` y `$accionPendientePedidoId = $pedido->id`, llama `abrirCobroRapido()` y retorna `true` (el caller debe abortar).
- `reanudarAccionPendienteSiCobrado(): void` -- ejecuta la accion almacenada en `$accionPendiente` si el pedido quedo cubierto tras el cobro. Limpia ambas propiedades antes de ejecutar. Si el pedido no quedo al 100%, descarta silenciosamente. Unico caso mapeado activo: `'convertir'` → `abrirConvertir()`.
- `abrirConvertir(int $pedidoId): void` -- requiere permiso `func.pedidos_mostrador.convertir_venta`. Verifica que el pedido no sea terminal/borrador. Llama `gatearPorCobro($pedido, 'convertir')`: si el pedido tiene saldo sin cubrir, se intercepta y se abre el cobro rapido en lugar del modal de conversion; retorna sin abrir el modal. Si el pedido esta cubierto, abre el modal de confirmacion de conversion.
- `abrirCobroRapido(int $pedidoId): void` -- verifica permiso `func.pedidos_mostrador.cobrar`, acceso a sucursal y que el pedido no este cancelado/facturado. Incrementa `$cobroRapidoKey`, asigna `$pedidoCobroRapidoId = $pedidoId`, cierra `$showCobrarModal` y `$showDetalleModal` si estaban abiertos.
- `confirmarPagoPlanificado(int $pagoId): void` -- materializa un pago planificado individual desde el modal "Cobrar pendiente". Si la `FormaPago` del pago tiene integracion, delega en `iniciarCobroIntegracionPagoPlanificado()` (inicia el QR y retorna sin tocar caja). Si no tiene integracion, llama directamente a `PedidoMostradorService::confirmarPagoPlanificado()` y reabre el modal con el estado actualizado.
- `iniciarCobroIntegracionPagoPlanificado(PedidoMostradorPago $pago): void` -- metodo protegido. Guarda `$cobroIntegracionPagoPlanificadoId`, cierra el modal "Cobrar pendiente" y llama a `iniciarCobroIntegracion()` del concern con los datos del pago y pedido. Si `iniciarCobroIntegracion` no abre el modal de espera (configuracion faltante), reabre el modal "Cobrar pendiente".
- `alConfirmarCobroIntegracion(): void` (hook del concern `WithCobroIntegracion`) -- materializa el pago planificado guardado en `$cobroIntegracionPagoPlanificadoId` via `PedidoMostradorService::confirmarPagoPlanificado()`, asocia la transaccion QR al `PedidoMostrador` via `asociarCobroIntegracionAlCobrable()`, limpia `$cobroIntegracionPagoPlanificadoId` y reabre el modal "Cobrar pendiente" con el estado fresco.
- `alCancelarCobroIntegracion(): void` (hook del concern `WithCobroIntegracion`) -- si habia un pago planificado en cobro, lo reabre en el modal "Cobrar pendiente" para reintentar o editar. No toca caja (el pago queda planificado intacto).
- `comandarPedido(int $pedidoId): void` -- flujo decisor de comanda. Lee el pedido con `detalles`. Calcula `$nuevos` (count de `comandado_at = null`) y `$comandados`. Si hay mezcla (`$nuevos > 0 && $comandados > 0`), abre el modal Comandar seteando las props de conteo. Si todos estan en el mismo estado (todos nuevos o todos comandados), llama directamente `ejecutarComandarPedido($pedido, 'todos')`.
- `confirmarComandar(string $alcance): void` -- ejecuta tras la eleccion del operario en el modal. Valida alcance, lee `$pedidoComandarId`, llama `cerrarComandarModal()` y luego `ejecutarComandarPedido($pedido, $alcance)`.
- `cerrarComandarModal(): void` -- resetea `$showComandarModal = false`, `$pedidoComandarId = null`, `$comandarNuevosCount = 0`, `$comandarComandadosCount = 0`.
- `ejecutarComandarPedido(PedidoMostrador $pedido, string $alcance): void` -- metodo protegido. Llama `PedidoMostradorService::comandarPedido($pedido, $alcance)`, dispatcha el evento `imprimir-comanda` con el payload retornado, dispatcha toast informativo y llama `resetPage()`.
- `reordenarColumna(string $estado, array $idsOrdenados): void` -- persiste el orden intra-columna del Kanban. Delega a `PedidoMostradorService::reordenarColumna()`. Ante error, dispatcha toast pero no lanza excepcion al caller. Al cambiar caja activa, los snapshots y `$nuevosCount` se resetean.

**Metodo `render()` -- datos adicionales para Kanban**:
- `pedidosKanban` -- resultado de `obtenerPedidosKanban()`.
- `estadosKanban` -- copia de `ESTADOS_KANBAN`.
- `transicionesKanban` -- subconjunto de `TRANSICIONES_PERMITIDAS` filtrado a las transiciones entre estados de `ESTADOS_KANBAN` (excluye transiciones hacia/desde borrador, cancelado, facturado). Usado por Alpine para validar el `onMove` de SortableJS sin round-trip al servidor.

**Layout fullscreen**:
El wrapper raiz del componente usa las clases `h-[calc(100vh-5.5rem)] flex flex-col overflow-hidden`, identico al layout de NuevaVenta. La pagina no scrollea verticalmente; el scroll interno queda dentro del contenedor de contenido (lista o kanban).

**Vista Blade (`pedidos-mostrador.blade.php`) -- patron Kanban**:

La raiz del componente usa `x-data` con las siguientes propiedades Alpine locales:
- `vista` -- inicializada con `localStorage.getItem('pedidos_vista_preferida') ?? 'lista'`. El toggle del header escribe en `localStorage` y cambia `vista` de forma reactiva.
- `mostrarBorradoresPanel` (bool, default `false`) -- controla la visibilidad del dropdown de borradores. Reemplaza la propiedad Livewire `$mostrarBorradores` que fue eliminada.
- `focusSearch()` -- funcion Alpine que pone el foco en el campo de busqueda del header.
- `esInputActivo()` -- funcion Alpine que retorna `true` si el elemento con foco activo es un `INPUT`, `TEXTAREA` o `SELECT`. Usada como guard en los listeners de atajos de teclado para evitar disparos accidentales.

La vista Lista esta envuelta en `x-show="vista === 'lista'"` y la vista Kanban en `x-show="vista === 'kanban'"`.

**Badge de estado de pago clickeable (patron boton-inline-hover)**:
Cuando el pedido tiene saldo pendiente (`total_cobrado < total_final - 0.005` o hay planificados) y el usuario tiene permiso `func.pedidos_mostrador.cobrar`, el badge de `estado_pago` se envuelve en un `<button>` con `wire:click="cobrarRapido({{ $pedido->id }})"`. El boton usa el patron `group` de Tailwind: un icono SVG de signo $ a la derecha del badge tiene clase `opacity-0 group-hover:opacity-100 transition-opacity`, haciendolo invisible por defecto y visible al pasar el cursor. Cuando el pedido esta pagado, el badge es un `<span>` normal sin accion (clase `cursor-default`). Este patron aplica tanto en la Vista Lista (columna de estado de pago) como en las cards del Kanban.

Los badges de `estado_pedido` y `estado_pago` usan tamano `text-sm` (antes `text-xs`) para mayor legibilidad.

**Header compacto (una sola fila, h-9)**:
El header ya no contiene titulo ni descripcion del modulo. Contiene en una sola fila: contador de pedidos, badge de nuevos, boton de borradores (condicional), chips removibles de filtros activos, search inline (md+), boton filtros, boton refrescar con estado loading (`wire:loading.remove` / `wire:loading`), toggle lista/kanban y boton nuevo.

**Boton borradores (Alpine puro, sin round-trip)**:
Reemplaza el desplegable Livewire previo. El boton aparece condicionalmente si `$borradores->count() > 0`. Click activa `mostrarBorradoresPanel = true`; `@click.outside` y `@keydown.escape.window` lo cierran. Las propiedades Livewire `$mostrarBorradores` y el metodo `toggleBorradores()` fueron eliminados del componente PHP.

**Chips de filtros activos**:
Por cada filtro con valor distinto al default (estado pedido, estado pago, busqueda), el header renderiza un chip con un boton de cierre. Click en el boton del chip hace `wire:click` que limpia la propiedad Livewire correspondiente (`filterEstadoPedido = ''`, `filterEstadoPago = ''`, `search = ''`).

**Atajos de teclado (`@keydown.window`)**:
Registrados en el `x-data` raiz via Alpine:
- `ctrl.n` / `meta.n` → llama `abrirModalNuevoPedido()` via `$wire`. Guard: `!esInputActivo()`.
- `ctrl.k` / `meta.k` → llama `focusSearch()`.
- `/` → llama `focusSearch()`. Guard: `!esInputActivo()`.
- `escape` → `mostrarBorradoresPanel = false`.

**Resaltado en vivo de pedidos (`pedido-destacado`)**:

El `x-data` raiz del componente mantiene un `Set` de IDs destacados (`pedidosDestacados = new Set()`). Cuando llega el evento de browser `pedido-destacado.window`, el ID del pedido se agrega al Set. Las filas `<tr>` de la lista y las cards `.kanban-card` del tablero evaluan si su ID esta en el Set: si es asi, aplican clases CSS con animacion de pulso naranja (keyframes propios, con variantes dark mode y soporte de `prefers-reduced-motion`). El comportamiento de cada vista es:

- **Lista**: fondo pulsante naranja que alterna opacidad 0.32 ↔ 0.62 + borde izquierdo `orange-500` en la fila. Periodo 1.8s.
- **Kanban**: triple capa de `box-shadow` naranja pulsante + ring de 3px + `scale(1.015)` en el pico de la animacion. Periodo 1.8s.

Un click sobre la fila o la card llama a `marcarVisto(id)`, que elimina el ID del Set y quita el resaltado de inmediato. El Set vive solo en memoria del navegador: un refresh limpia todos los resaltados.

**Funcion Alpine `kanbanBoard(transiciones)`**: inicializa SortableJS en cada columna del tablero. Configuracion relevante:
- `group: 'pedidos'` -- permite arrastrar entre columnas del mismo grupo.
- `onMove(evt)`: callback sincrono que valida si la transicion desde el estado de origen (obtenido del `data-estado` del contenedor origen) hacia el estado destino (del contenedor destino) existe en el mapa `transiciones`. Retorna `false` para bloquear el drop si la transicion no es legal.
- `onEnd(evt)`: se dispara al soltar. Bifurca segun tipo de drop:
  - **Cross-column** (`evt.from !== evt.to`): llama a `$wire.cambiarEstadoDrag(pedidoId, nuevoEstado)`. El `pedidoId` se lee del atributo `data-pedido-id` de la card arrastrada.
  - **Same-column** (`evt.from === evt.to && evt.oldIndex !== evt.newIndex`): recopila todos los `data-pedido-id` de las cards de la columna en el nuevo orden DOM y llama a `$wire.reordenarColumna(estado, idsOrdenados)`. El `estado` se lee del `data-estado` del contenedor de columna.
- El listener `livewire:navigated` re-invoca `kanbanBoard` para re-inicializar Sortable despues de cada re-render del componente (necesario porque Livewire destruye y recrea el DOM).

**Patron de rollback visual (`kanban-revertir`)**:
Cuando `cambiarEstadoDrag` rechaza el cambio, dispatcha el evento de browser `kanban-revertir` con el `pedidoId`. Alpine escucha `@kanban-revertir.window` y re-inserta la card en su columna original. El re-render de Livewire que sigue al rechazo tambien corrige el DOM desde el servidor, por lo que el rollback Alpine es solo una correccion visual inmediata mientras llega el re-render.

**Dependencia SortableJS**:
- Paquete `sortablejs` en `package.json`.
- Importado en `resources/js/bootstrap.js` como `window.Sortable = Sortable` para que sea accesible desde Alpine sin bundling adicional.

**Validacion dual de transiciones**:
| Capa | Donde | Que valida | Que hace si falla |
|------|-------|-----------|-------------------|
| Frontend `onMove` | Alpine/SortableJS | `transicionesKanban` pasado como prop desde el servidor | Bloquea el drop visualmente (retorna false en onMove) |
| Backend `cambiarEstadoDrag` | PHP/Livewire | `TRANSICIONES_PERMITIDAS` completo + acceso a sucursal + estado dentro de ESTADOS_KANBAN | Dispatcha `kanban-revertir` + toast de error |

**Integracion con NuevoPedidoMostrador (modal full-screen y cobro rapido)**:

El componente renderiza condicionalmente al final de su vista dos instancias posibles de `NuevoPedidoMostrador`:

```blade
{{-- Editor full-screen (alta y edicion) --}}
@if($modalNuevoPedidoAbierto)
<livewire:pedidos.nuevo-pedido-mostrador :pedidoId="$pedidoIdEnEdicion" :key="'modal-nuevo-pedido-' . $modalNuevoPedidoKey" />
@endif

{{-- Cobro rapido (solo modal de desglose, sin UI del editor) --}}
@if($pedidoCobroRapidoId)
<livewire:pedidos.nuevo-pedido-mostrador :pedidoId="$pedidoCobroRapidoId" :modoCobroRapido="true" :key="'cobro-rapido-' . $pedidoCobroRapidoId . '-' . $cobroRapidoKey" />
@endif
```

Props de control en `PedidosMostrador` -- editor full-screen:
- `$modalNuevoPedidoAbierto` (bool) -- controla visibilidad del modal.
- `$pedidoIdEnEdicion` (int|null) -- null para alta, ID para edicion.
- `$modalNuevoPedidoKey` (int) -- counter incrementado cada vez que se abre, fuerza remount del sub-componente para resetear su estado.

Props de control en `PedidosMostrador` -- cobro rapido:
- `$pedidoCobroRapidoId` (?int) -- ID del pedido sobre el que se abrio el cobro rapido. `null` cuando no esta activo.
- `$cobroRapidoKey` (int) -- counter para forzar remount al re-abrir.

Props de control en `PedidosMostrador` -- accion pendiente de cobro (`#[Locked]`):
- `$accionPendiente` (?string) -- unico valor activo: `'convertir'`. El arm `'entregar'` fue eliminado (RF-08): entregar no requiere cobro previo. Marcada `#[Locked]` para prevenir manipulacion desde el cliente.
- `$accionPendientePedidoId` (?int) -- ID del pedido sobre el que se intercepto la accion. Marcado `#[Locked]`.

Props de control en `PedidosMostrador` -- modal Comandar:
- `$showComandarModal` (bool) -- visibilidad del modal de eleccion de alcance de comanda.
- `$pedidoComandarId` (?int) -- ID del pedido sobre el que se abrio el modal. NULL cuando el modal esta cerrado.
- `$comandarNuevosCount` (int) -- cantidad de items con `comandado_at = null` en el pedido. Se muestra en el boton "Comandar solo los nuevos (N)".
- `$comandarComandadosCount` (int) -- cantidad de items ya comandados. Se muestra en el boton "Comandar todo el pedido (N+M)".

Metodos de apertura (editor full-screen):
- `abrirModalNuevoPedido()` -- modo alta: `$pedidoIdEnEdicion = null`, incrementa key.
- `abrirModalEditarPedido($id)` -- modo edicion: verifica que el pedido no sea terminal (cancelado/facturado) y que `estado_pago === pendiente` (o sea borrador). Si no cumple, dispatcha toast de error. Si cumple, asigna `$pedidoIdEnEdicion = $id`, incrementa key.

Eventos escuchados (via `#[On]`) -- editor full-screen:
- `cerrar-modal-pedido` -- despacha el sub-componente al cancelar. Cierra el modal sin refrescar.
- `pedido-guardado` -- despacha el sub-componente tras alta o edicion exitosa. Cierra el modal y llama `$this->resetPage()` para refrescar la lista.

Eventos escuchados (via `#[On]`) -- cobro rapido:
- `cobro-rapido-completado` -- handler `trasCobroRapidoCompletado()`. Resetea `$pedidoCobroRapidoId = null`, llama `reanudarAccionPendienteSiCobrado()` y luego `resetPage()`.
- `cerrar-cobro-rapido` -- handler `trasCerrarCobroRapido()`. Resetea `$pedidoCobroRapidoId = null`, `$accionPendiente = null` y `$accionPendientePedidoId = null` sin refrescar.

El modal de detalle agrega el boton **"Editar pedido"** cuando el pedido no es terminal y `estado_pago === pendiente` (o es borrador). El boton **"Editar"** rapido tambien aparece directamente en cada fila de la lista y en cada card del Kanban cuando `pedidoEsEditable()` es `true`.

#### Componente Livewire: `NuevoPedidoMostrador` (Modal Full-Screen / Cobro Rapido)

`app/Livewire/Pedidos/NuevoPedidoMostrador.php` | Sin ruta dedicada -- invocado como sub-componente de `PedidosMostrador`.

No hay rutas `/pedidos/mostrador/nuevo` ni `/pedidos/mostrador/{pedido}/editar`. El alta y la edicion ocurren exclusivamente dentro del modal full-screen.

**Props**:
- `$pedidoId` (int|null) -- null = modo alta, ID = modo edicion o cobro rapido.
- `$modoCobroRapido` (bool, default `false`) -- cuando `true`, el componente monta solo el modal de desglose de pagos sobre el listado, sin renderizar la UI completa del editor. El `mount()` acepta este parametro directamente.

**Traits del Carrito incluidos** (11 de 11):
`WithCarritoItems`, `WithCarritoDescuentos`, `WithCarritoCupon`, `WithCarritoPuntos`, `WithCarritoCliente`, `WithCarritoListaPrecios`, `WithCarritoTotales`, `WithArticulosRapidos`, `WithClientesRapidos`, `WithCarritoOpcionales`, `WithPagosDesglose`.

**Vista**: `resources/views/livewire/pedidos/nuevo-pedido-mostrador.blade.php`

El archivo tiene un wrapper raiz `<div data-livewire-root="nuevo-pedido-mostrador">` que garantiza el tag root que Livewire requiere incluso cuando los modales internos estan cerrados. Adentro un `@if($modoCobroRapido) ... @else ...` bifurca:
- **Modo cobro rapido**: renderiza solo los tres parciales de pago (`_modal-pago-mixto`, `_modal-moneda-extranjera`, `_modal-vuelto`) superpuestos sobre el listado. No hay UI de editor.
- **Modo editor (default)**: renderiza el modal full-screen con header, carrito y panel de totales. Wrapper interno: `<div class="fixed inset-0 z-40 bg-black/40 flex items-stretch justify-center p-2 sm:p-3">`.

**Reutilizacion de modales del carrito** (modo editor): `_modal-cliente-rapido`, `_modal-articulo-rapido`, `_modal-busqueda-articulos`, `_modal-pesable`, `_wizard-opcionales`, `_modal-descuentos` (mismos parciales que NuevaVenta).

**Modales propios** (modo editor): concepto libre, confirmar limpiar carrito, edicion de nombre de item.

**Modos de operacion**:
- Alta (`$pedidoId === null`): sin numero, sin descuento de stock al guardar borrador.
- Edicion (`$pedidoId` provisto, `$modoCobroRapido = false`): precarga datos del pedido. Disponible para estados `borrador`, `confirmado`, `en_preparacion`, `listo` y `entregado`, siempre que `estado_pago === pendiente` (sin cobros materializados activos). En `cargarPedidoParaEditar()` se valida que el estado este en esta lista y que no haya pagos activos; si la condicion no se cumple, se dispatcha error y el componente no carga el pedido.
- Cobro rapido (`$pedidoId` provisto, `$modoCobroRapido = true`): llama `iniciarCobroRapido()` al final de `mount()`. Aplica la misma validacion de estados que el modo edicion. No carga catalogo tactil.

**Logica de beeper**: el campo `numero_beeper` es obligatorio al confirmar si `sucursal.usa_beepers = true`. No se valida al guardar como borrador.

**Metodo `iniciarCobroRapido()`**:
- Calcula `$saldo = round(total_final - total_cobrado - total_planificado, 2)`.
- Si `$saldo <= 0.01`, dispatcha error y `cerrar-cobro-rapido`.
- Sobreescribe `$this->resultado['total_final'] = $saldo` para que toda la logica de `WithPagosDesglose` (recalcularTotalConAjustes, desgloseCompleto, IVA mixto) opere sobre el saldo y no sobre el total original.
- Limpia `$desglosePagos`, inicializa `$montoPendienteDesglose`, `$totalConAjustes` y `$ajusteFormaPagoInfo` con el saldo.
- Abre el modal en modo cobro: `$modalPagoEnModoCobro = true`, `$mostrarModalPago = true`.

**Override `procesarVentaConDesglose()`**:
En modo cobro rapido delega a `procesarCobroRapido()` antes de la logica estandar.

**Metodo `procesarCobroRapido()`**:
- Valida que `$desglosePagos` no este vacio y que `$pedidoId` exista.
- Valida caja abierta para pagos que la afecten (pagos que no sean cuenta corriente).
- Itera `$this->desglosePagos` y llama `PedidoMostradorService::agregarPago($pedido, normalizarPagoDelDesglose($pago, planificadoForzado: false))` por cada fila.
- Dispatcha `toast-success` y evento `cobro-rapido-completado` al padre.
- Si una sola FP cubre el total, el pago queda con esa FP individual (no se usa la FP "mixta" del selector).

**Override `cerrarModalPago()`**:
- En modo cobro rapido: cierra `$mostrarModalPago` y dispatcha `cerrar-cobro-rapido`. No limpia el desglose (el componente se desmonta entero).
- En modo editor: replica la logica estandar del trait (recalcula ajuste si el desglose esta completo, limpia si no lo esta).

**Acciones (modo editor)**:
- **Guardar borrador**: llama a `PedidoMostradorService::crearPedido()` con `esBorrador = true` (alta) o actualiza el pedido existente. Sin numero, sin stock.
- **Confirmar pedido**: `esBorrador = false`. Asigna numero correlativo, descuenta stock, imprime comanda si corresponde.
- **Cerrar / Cancelar**: despacha evento `cerrar-modal-pedido` al componente padre.
- Tras alta o edicion exitosa: despacha evento `pedido-guardado` al componente padre.

**Atajo de teclado**: Esc cierra el modal (manejado en Alpine con `@keydown.escape.window`). Solo aplica en modo editor.

**Badge "Nuevo" por item en edicion**: el partial compartido `resources/views/livewire/carrito/_detalle-items.blade.php` renderiza un badge ambar "Nuevo" al lado del nombre del articulo cuando se cumplen dos condiciones simultaneamente: (1) el array del item tiene la clave `comandado_at` (presente cuando se rehidrata un pedido existente via `detalleAItemCarrito()`; ausente en NuevaVenta que no tiene este campo); (2) `$item['comandado_at'] === null`. No se muestra si el pedido esta en estado borrador (la vista del editor lo suprimir pasando el `$pedidoId` como null o verificando el estado). Este mecanismo de guarda por `array_key_exists` asegura que NuevaVenta (que no rehidrata `comandado_at`) no vea el badge.

#### Evento Broadcast: `PedidoMostradorBroadcast`

`app/Events/Broadcasting/PedidoMostradorBroadcast.php` -- extiende `TenantBroadcastEvent`.

Evento broadcast unificado para todos los cambios visibles en pedidos por mostrador. Transporta solo IDs y tipo (no el pedido completo); el cliente re-consulta la BD para obtener el estado fresco. Esto reduce el payload y evita race conditions cuando varios cambios llegan en rafaga.

**Canal**: `private-comercios.{comercioId}.pedidos-mostrador`

Patron multi-tenant: el canal esta prefijado por `comercioId`, por lo que un usuario del comercio A no puede recibir eventos del comercio B. La autorizacion del canal se valida en `routes/channels.php`.

**Clase base `TenantBroadcastEvent`** (`app/Events/Broadcasting/TenantBroadcastEvent.php`): clase abstracta que implementa `ShouldBroadcast`. Toda subclase transmite en el canal `private-comercios.{comercioId}.{resourceName()}`. Las subclases solo deben implementar `resourceName()` para definir el sufijo dinamico del canal.

**Tipos de evento** (constantes en `PedidoMostradorBroadcast`):

| Constante | Valor | Cuando se despacha |
|-----------|-------|--------------------|
| `TIPO_CREADO` | `'creado'` | Alta de pedido no borrador; confirmacion de borrador |
| `TIPO_ESTADO_CAMBIADO` | `'estado_cambiado'` | Cambio de estado operativo del pedido |
| `TIPO_PAGO_CAMBIADO` | `'pago_cambiado'` | Cada recalculo de `estado_pago` (incluye sin cambio real) |
| `TIPO_CANCELADO` | `'cancelado'` | Cancelacion del pedido |
| `TIPO_CONVERTIDO_VENTA` | `'convertido_venta'` | Conversion exitosa en venta |

**Payload broadcast** (`broadcastWith()`):
```json
{
  "pedidoId": 42,
  "sucursalId": 3,
  "tipo": "creado",
  "at": "2026-05-14T10:30:00+00:00"
}
```

El payload **no incluye `cajaId`**: el filtro por caja en el frontend consulta
`PedidoMostrador::where('id', $pedidoId)->value('caja_id')` cuando hay caja
activa, asi se evita inflar el payload con un campo que la mayoria de los
clientes no usa.

**Nombre de evento en el cliente**: `.PedidoMostradorBroadcast` (definido en `broadcastAs()` para evitar usar el FQCN completo de Laravel).

**Ejemplo de suscripcion desde Livewire** (via `getListeners()`):
```php
return [
    "echo-private:comercios.{$comercioId}.pedidos-mostrador,.PedidoMostradorBroadcast" => 'onPedidoBroadcast',
];
```

#### Patrones de consulta SQL utiles

**Pedidos activos de la sucursal hoy:**
```sql
SELECT p.*, c.nombre AS cliente_nombre
FROM {PREFIX}pedidos_mostrador p
LEFT JOIN {PREFIX}clientes c ON p.cliente_id = c.id
WHERE p.sucursal_id = ?
  AND p.estado_pedido NOT IN ('facturado', 'cancelado')
  AND DATE(p.fecha) = CURDATE()
  AND p.deleted_at IS NULL
ORDER BY p.fecha DESC;
```

**Total cobrado y pendiente por pedido:**
```sql
SELECT
    p.id,
    p.total_final,
    SUM(CASE WHEN pp.estado = 'activo' THEN pp.monto_final ELSE 0 END) AS total_cobrado,
    SUM(CASE WHEN pp.estado = 'planificado' THEN pp.monto_final ELSE 0 END) AS total_planificado,
    p.total_final - SUM(CASE WHEN pp.estado = 'activo' THEN pp.monto_final ELSE 0 END) AS pendiente
FROM {PREFIX}pedidos_mostrador p
LEFT JOIN {PREFIX}pedidos_mostrador_pagos pp ON pp.pedido_mostrador_id = p.id
WHERE p.id = ?
GROUP BY p.id;
```

**Items no comandados de un pedido (pendientes de envio a cocina):**
```sql
SELECT *
FROM {PREFIX}pedidos_mostrador_detalle
WHERE pedido_mostrador_id = ?
  AND comandado_at IS NULL;
```

**Estado de comanda derivado para un pedido (equivalente al accessor PHP):**
```sql
SELECT
    pedido_mostrador_id,
    COUNT(*) AS total_items,
    SUM(CASE WHEN comandado_at IS NOT NULL THEN 1 ELSE 0 END) AS comandados,
    CASE
        WHEN SUM(CASE WHEN comandado_at IS NOT NULL THEN 1 ELSE 0 END) = 0 THEN 'no_comandado'
        WHEN SUM(CASE WHEN comandado_at IS NOT NULL THEN 1 ELSE 0 END) = COUNT(*) THEN 'comandado'
        ELSE 'parcial'
    END AS estado_comanda
FROM {PREFIX}pedidos_mostrador_detalle
WHERE pedido_mostrador_id = ?
GROUP BY pedido_mostrador_id;
```

---

### 2.13 Integraciones de Pago

Modulo extensible para conectar sucursales y cajas con pasarelas de pago externas. Actualmente implementado: Mercado Pago (MVP). El framework esta disenado para agregar otros gateways (Oca, Prisma, etc.) en el futuro via el catalogo `integraciones_pago` y la interface `IntegracionPagoGatewayContract`.

#### Tabla: `integraciones_pago` (conexion `config`, sin prefijo)

Catalogo de pasarelas disponibles en el sistema. Se provisiona una vez por instalacion.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Nombre legible (ej: "Mercado Pago") |
| `codigo` | varchar(50) UNIQUE | Identificador tecnico (ej: `mercadopago_qr`). Cada producto del proveedor es una fila separada |
| `gateway_class` | varchar(255) | FQCN de la clase gateway (ej: `App\Services\IntegracionesPago\MercadoPagoGateway`) |
| `activo` | boolean | Si esta habilitado para ser configurado |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `{PREFIX}integraciones_pago_sucursales` (tenant)

Configuracion de una integracion para una sucursal especifica. Una sucursal puede tener a lo sumo una configuracion por proveedor.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `sucursal_id` | bigint FK | Sucursal configurada |
| `integracion_pago_id` | bigint (FK logico cross-DB) | ID en `config.integraciones_pago` |
| `modo` | enum | `test` o `produccion` |
| `user_id_externo` | varchar(100) nullable | User ID de la cuenta MP del comercio |
| `access_token_produccion` | text nullable | Access token de produccion (encriptado) |
| `access_token_test` | text nullable | Access token de test (encriptado) |
| `activo` | boolean | Si la integracion esta activa |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Notas de modelo**:
- Los campos `access_token_produccion` y `access_token_test` usan cast `encrypted` de Laravel.
- El metodo `getAccessTokenActivo()` devuelve el token correspondiente al `modo` actual.
- UNIQUE INDEX en `(sucursal_id, integracion_pago_id)`.

#### Tabla: `{PREFIX}integraciones_pago_transacciones` (tenant)

Registro append-only de transacciones de cobro. Una transaccion nace en estado `pendiente` al generar el QR y avanza por estados hasta confirmarse o cancelarse. La asociacion al cobrable (venta o pedido) es nullable: en el modelo "cobro primero, venta despues" el cobrable se asocia recien cuando la venta se crea tras la confirmacion del pago.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `integracion_pago_sucursal_id` | bigint FK | Configuracion de integracion usada |
| `forma_pago_id` | bigint FK | Forma de pago usada |
| `sucursal_id` | bigint FK | Sucursal donde se realizo el cobro |
| `caja_id` | bigint FK nullable | Caja del cajero |
| `usuario_iniciador_id` | bigint FK | Usuario que inicio el cobro |
| `cobrable_type` | varchar(255) nullable | FQCN del cobrable (`App\Models\Venta`, `App\Models\PedidoMostrador`). NULL hasta que se asocia. |
| `cobrable_id` | bigint unsigned nullable | ID del cobrable. NULL hasta que se asocia. |
| `modo_usado` | varchar(50) | Modo de cobro (`qr_dinamico`, `qr_estatico`) |
| `estado` | varchar(50) | Estado: `pendiente`, `confirmado`, `confirmado_manual`, `cancelado`, `fallido`, `expirado` |
| `monto` | decimal(12,2) | Monto del cobro |
| `moneda_id` | bigint FK nullable | Moneda del cobro |
| `qr_data` | text nullable | Trama EMVCo del QR dinamico |
| `link_pago` | text nullable | URL de pago alternativa |
| `external_reference` | varchar(255) nullable | Referencia interna enviada al proveedor |
| `external_id` | varchar(255) nullable | ID del recurso en el proveedor (ej: `order_id` de MP) |
| `payload_respuesta` | json nullable | Ultima respuesta del proveedor |
| `expira_en` | timestamp nullable | Momento en que expira el QR |
| `confirmado_en` | timestamp nullable | Momento en que se confirmo el pago |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Notas de modelo**:
- `cobrable_type` y `cobrable_id` son nullable desde Fase 5 (migracion `make_cobrable_nullable_integraciones_pago_transacciones`).
- Relacion `cobrable()` es `morphTo`: el cobrable puede ser `Venta` o `PedidoMostrador`.
- Modelo "cobro primero, cobrable despues": la transaccion nace sin cobrable (`cobrable_type/id = null`). El cobrable se asocia recien cuando se materializa el comprobante (venta nueva, pedido cobrado, pago planificado confirmado). Aplica en todos los flujos: Nueva Venta, NuevoPedidoMostrador y confirmacion de pagos planificados desde PedidosMostrador.
- `estaEnEstadoTerminal()`: devuelve true si `estado` es `confirmado`, `confirmado_manual`, `cancelado`, `fallido` o `expirado`.
- `estaConfirmada()`: devuelve true si `estado` es `confirmado` o `confirmado_manual`.
- Scope `vencidas()`: filtra transacciones con `estado = 'pendiente'` y `expira_en <= now()`. Usado por el comando de expiracion automatica.

#### Tabla: `{PREFIX}integraciones_pago_eventos` (tenant)

Ledger de auditoria append-only de cada transaccion. Cada cambio de estado o evento significativo genera una fila. Nunca se borran ni modifican registros existentes.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `transaccion_id` | bigint FK | Transaccion auditada |
| `integracion_pago_sucursal_id` | bigint FK | Configuracion usada (denormalizado para queries) |
| `evento` | varchar(100) | Tipo de evento (ver constantes) |
| `payload_externo` | json nullable | Payload del proveedor externo si aplica |
| `metadata` | json nullable | Datos adicionales (ej: `{motivo: '...'}` para errores) |
| `created_at` | timestamp | Momento del evento |

**Constantes de evento** en `IntegracionPagoEvento`:
- `EVENTO_CREADO` = `'creado'`
- `EVENTO_INICIADO_EN_GATEWAY` = `'iniciado_en_gateway'`
- `EVENTO_CONFIRMADO` = `'confirmado'`
- `EVENTO_CONFIRMADO_MANUAL` = `'confirmado_manual'` — confirmacion manual por cajero con permiso; `metadata` incluye `usuario_id` y opcionalmente `motivo`
- `EVENTO_COBRABLE_ASOCIADO` = `'cobrable_asociado'`
- `EVENTO_CANCELADO` = `'cancelado'`
- `EVENTO_ERROR` = `'error'`
- `EVENTO_EXPIRADO` = `'expirado'` — generado por el comando de expiracion automatica

#### Tabla: `comercios.rubro` (campo en `config.comercios`)

Campo `rubro varchar(50) nullable` agregado a la tabla `comercios` de la conexion `config`. Valores posibles definidos como constantes en `Comercio::RUBRO_*`:
- `gastronomia` -- MCC 621102 en MP
- `estacion_servicio` -- MCC 443001 en MP
- `otro` (o NULL) -- No se envia `category` al crear POS en MP

#### Services

- **`CobroIntegracionService`** (Fase 5): Orquesta el ciclo de vida de un cobro por integracion. API unica consumida por todos los flujos de cobro via el concern `WithCobroIntegracion` (usado por `WithPagosDesglose` en `NuevaVenta`/`NuevoPedidoMostrador` y directamente por `PedidosMostrador` para pagos planificados). Metodos publicos:
  - `iniciarCobro(IntegracionPagoSucursal $config, array $datos, ?Model $cobrable = null): IntegracionPagoTransaccion` — Crea la transaccion en `pendiente`, llama al gateway para obtener el QR (FUERA de la transaccion DB para no mantener locks durante la latencia de red) y persiste `qr_data`, `external_id`, etc.
  - `consultarEstado(IntegracionPagoTransaccion $transaccion): string` — Consulta el estado en el proveedor. Devuelve `'pendiente'|'aprobado'|'cancelado'|'expirado'` sin mutar la transaccion.
  - `confirmarCobro(IntegracionPagoTransaccion $transaccion, ?Model $cobrable = null, array $payload = []): void` — Marca como `confirmado`, registra `confirmado_en`, asocia el cobrable si se provee. Idempotente.
  - `asociarCobrable(IntegracionPagoTransaccion $transaccion, Model $cobrable): void` — Asocia el cobrable a una transaccion ya confirmada. Necesario en el modelo "cobro primero, venta despues": el pago se confirma cuando el cliente escanea el QR, pero el comprobante se crea despues. Idempotente.
  - `cancelarCobro(IntegracionPagoTransaccion $transaccion): bool` — Avisa al proveedor y marca como `cancelado`. Si el gateway falla al cancelar, la transaccion se cancela localmente igual y se loguea el error. Idempotente.
  - `confirmarManual(IntegracionPagoTransaccion $transaccion, ?int $usuarioId = null, ?string $motivo = null): void` — (Fase 8, RF-12) Marca la transaccion con estado `confirmado_manual` (distinto de `confirmado` para diferenciarlo en reportes y conciliacion) y registra en `integraciones_pago_eventos` quien la confirmo (`usuario_id` en `metadata`). El cobrable se materializa igual que en el camino automatico (el concern llama `alConfirmarCobroIntegracion()`). Idempotente: si la transaccion ya esta en estado terminal, no hace nada.
  - `expirarPendientesVencidas(): int` — (Fase 8, RF-16) Obtiene todas las transacciones en scope `vencidas()` del tenant activo, las marca como `expirado`, registra el evento `expirado` en el ledger y broadcastea `IntegracionPagoActualizado` con `estado = 'expirado'` por cada una para que el modal del cajero cierre solo. Bajo el modelo "cobro primero" no hay cobrable que anular. Retorna la cantidad de transacciones expiradas.
- **`MercadoPagoGateway`** (actualizado Fase 7): Implementa `IntegracionPagoGatewayContract`. Metodos de sincronizacion: `crearStore`, `actualizarStore`, `eliminarStore`, `crearPos`, `actualizarPos`, `eliminarPos`. Metodos de cobro QR: `iniciarCobro` (usa Orders API `POST /v1/orders`; soporta modo `dynamic` y `static` segun `transaccion->modo_usado`; metodo privado `mapearModoOrdersApi()` convierte el valor interno al esperado por MP), `consultarEstado` (polling del order), `cancelarCobro`. En modo `dynamic` MP devuelve `qr_data`; en `static` no (se usa `qr_image_url` = `caja->mp_pos_qr_url`). El webhook es identico para ambos modos (mismo topico "Order", mismo matching por `external_id`).
- **`SincronizacionMercadoPagoService`**: Orquesta crear-vs-actualizar. Decide segun `mp_store_id` / `mp_pos_id`. Persiste IDs y URLs devueltos en una transaccion tenant.
- **`IntegracionPagoSucursalService`**: CRUD de configuraciones. Al cambiar `modo` o `user_id_externo` limpia los IDs de MP locales (Store + todos sus POS) via `limpiarSincronizacionMp()`.

#### Catalogo de integraciones — codigos vigentes

| Codigo | Nombre | Descripcion |
|---|---|---|
| `mercadopago_qr` | Mercado Pago - QR | Cobro via QR estatico o dinamico. Renombrado desde `mercadopago` en Fase 4 para dar lugar a futuros productos MP (Point, Checkout, etc.) como filas separadas del catalogo |

**Constante PHP**: `IntegracionPago::CODIGO_MERCADOPAGO_QR = 'mercadopago_qr'`.

#### Reglas de negocio — asignacion de integraciones a formas de pago (Fase 4)

1. **Solo FP simples con concepto compatible**: Solo las formas de pago simples cuyo `ConceptoPago.permite_integracion = true` pueden tener integraciones vinculadas. Las FP mixtas no admiten integraciones (se limpian al cambiar a mixta).

2. **N integraciones por FP**: Una forma de pago puede tener N integraciones. Cada producto del proveedor (ej. Mercado Pago QR, Mercado Pago Point) es una integracion distinta con su propio access token aunque compartan el mismo `user_id_externo` (misma cuenta MP).

3. **Una sola fila por par (FP, integracion)**: El UNIQUE sobre `(forma_pago_id, integracion_pago_id)` impide duplicar la misma integracion en la misma FP. La validacion en Livewire lo verifica antes de llamar a `sync()`.

4. **Principal para cobro sin pregunta**: Al cobrar, si la FP tiene una unica integracion se usa automaticamente. Si tiene varias, se usa la marcada `es_principal`. Si ninguna esta marcada, se toma la primera. El helper `integracionPrincipal()` implementa esta logica.

5. **Modos de cobro**: Los modos (`qr_dinamico`, `qr_estatico`) son variantes de una misma credencial/integracion, no integraciones separadas. Cada forma de pago usa **un unico modo**, configurado en el campo `modo_default` del pivote. El campo `modos_permitidos` (json array) se conserva por compatibilidad de esquema y se persiste siempre como `[modo_default]` (espejo de un solo elemento). No hay validacion de inclusion porque no existe seleccion multiple.

   Resolucion del modo al cobrar: `CobroIntegracionService` lee `$integracion->pivot->modo_default`; ese valor se pasa como `modo_usado` a la transaccion y luego `MercadoPagoGateway::mapearModoOrdersApi()` lo convierte al valor esperado por la Orders API (`dynamic` / `static`).

6. **Sincronizacion via sync()**: Al guardar, el componente llama a `$formaPago->integraciones()->sync($syncIntegraciones)` con el mapa `[integracion_pago_id => [modo_default, modos_permitidos, es_principal]]`, donde `modos_permitidos` es siempre `json_encode([$modo_default])`. Si la FP no admite integraciones se llama a `detach()` para limpiar registros huerfanos.

#### Permisos del modulo de integraciones de pago

| Permiso | Descripcion |
|---|---|
| `func.integraciones_pago.administrar` | Configurar y sincronizar integraciones (acceso al modulo de configuracion) |
| `integraciones_pago.confirmar_manual` | (Fase 8, RF-12) Confirmar manualmente un cobro pendiente cuando el sistema no lo detecto automaticamente. Muestra el panel de fallback en el modal "Esperando pago". Recomendado solo para supervisores o cajeros de confianza dado el riesgo de confirmar un pago no realizado. |

#### Comando de expiracion automatica (Fase 8, RF-16)

`php artisan integraciones-pago:expirar-pendientes`

- Corre cada minuto via el scheduler de Laravel (`bootstrap/app.php`, con `withoutOverlapping()` para evitar solapamientos).
- Itera TODOS los comercios (multi-tenant): para cada uno llama a `TenantService::setComercio()` y luego a `CobroIntegracionService::expirarPendientesVencidas()`.
- Marca como `expirado` las transacciones que tienen `estado = 'pendiente'` y `expira_en <= now()` (scope `vencidas()` del modelo).
- Por cada transaccion expirada broadcastea `IntegracionPagoActualizado` con `estado = 'expirado'` en el canal de la transaccion, para que el modal del cajero cierre y muestre "tiempo agotado" sin requerir accion manual.
- Bajo el modelo "cobro primero, cobrable despues" NO anula ninguna venta: las transacciones que expiran nunca tuvieron cobrable asociado.
- Tolerante a fallos por comercio: un error en un tenant no interrumpe el procesamiento del resto (try/catch por comercio).

#### Reglas de negocio criticas (Mercado Pago)

1. **external_id NO en updates**: MP rechaza el campo `external_id` en las solicitudes PUT (Store y POS) con HTTP 400, porque valida unicidad incluso contra el propio recurso. Solo se envia al crear.

2. **external_id de POS estrictamente alfanumerico**: El endpoint `POST /pos` exige `external_id` sin caracteres especiales. Formato: `BCN{comercioId}POS{cajaId}` (sin guiones). El endpoint de Store si acepta guiones; por eso Store usa `BCN-{c}-{s}`.

3. **Limpieza al cambiar cuenta MP**: Al cambiar `modo` (test <-> produccion) o `user_id_externo`, los recursos de Store/POS de la cuenta anterior no existen en la nueva. El service limpia `mp_store_id`, `mp_store_external_id`, `mp_pos_id`, `mp_pos_external_id`, `mp_pos_qr_url`, `mp_pos_qr_pdf_url` para que la proxima sincronizacion los cree en lugar de intentar actualizarlos.

4. **Provincias como codigos ISO 3166-2**: El campo `sucursales.provincia` guarda el codigo ISO (ej: `AR-B`). Al armar el payload para MP (`state_name`), se traduce al nombre oficial usando `Sucursal::PROVINCIAS_AR[]` / `provinciaNombre()`. Esto garantiza consistencia entre integraciones sin depender de texto libre.

5. **Prerrequisito de Store para POS**: No se puede crear un POS si la sucursal no tiene `mp_store_id` y `mp_store_external_id`. El gateway lanza excepcion explicita.

6. **Coordenadas obligatorias**: La API de MP rechaza la creacion de Store sin `latitude`/`longitude`. El gateway valida que `sucursal->tieneCoordenadas()` antes de llamar a la API.

7. **Categoria MCC**: Solo se envia el campo `category` al crear un POS si el comercio tiene rubro `gastronomia` (MCC 621102) o `estacion_servicio` (MCC 443001). Para el resto se omite el campo.

8. **Eliminacion idempotente**: Si MP responde 404 al eliminar un Store o POS, se trata como exito (el recurso ya no existia).

#### Formatos de external_id

| Recurso | Formato | Ejemplo | Limite MP |
|---|---|---|---|
| Store | `BCN-{comercio_id}-{sucursal_id}` | `BCN-1-5` | 60 chars |
| POS | `BCN{comercio_id}POS{caja_id}` | `BCN1POS3` | 40 chars |

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
   - **Articulos pesables**: Si el articulo tiene `pesable = true`, se abre un modal para ingresar cantidad (peso) o valor ($). Los campos estan sincronizados bidireccional: `valor = cantidad * precio_unitario`. El articulo se agrega con la cantidad decimal ingresada.
   - **Scanner buffer**: El frontend detecta escaneo rapido (3+ teclas en <50ms) y encola codigos en `scanQueue`. El metodo `agregarPorCodigo($codigo)` procesa cada codigo secuencialmente sin depender de wire:model, evitando race conditions al escanear rapido.

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
   - Se valida stock disponible (segun modo: `bloquea`, `advierte`, `no_controla`). Los conceptos libres (`es_concepto=true`) no afectan stock.
   - Se valida credito del cliente (si es CC)
   - Se valida que la caja este abierta -- **excepcion: si `es_invitacion_total = true`, esta validacion se omite** (la venta cortesia no impacta caja pero igualmente necesita `caja_id` para generar el numero).
   - Se genera numero de venta (formato: CCCC-NNNNNNNN, donde CCCC es la caja)
   - Se crea el registro en `ventas` (con columnas de invitacion si aplica)
   - Se crean los detalles en `ventas_detalle`. Los items de tipo concepto libre tienen `articulo_id=NULL`, `es_concepto=true`, `concepto_descripcion` y opcionalmente `concepto_categoria_id`. Los items invitados tienen `es_invitacion=true`, `precio_unitario=0`, `monto_invitado` y `precio_unitario_original`.
   - Se guardan promociones aplicadas
   - Se guardan opcionales seleccionados
   - Se descuenta stock (tabla `stock` cache + movimiento en `movimientos_stock`) -- **los items invitados descuentan stock normalmente** (el bien fue consumido).
   - Se registran pagos en `venta_pagos` -- **omitido si `es_invitacion_total = true`** (no hay pagos que registrar).
   - Se crean movimientos de caja -- **omitido si `es_invitacion_total = true`**.
   - Se registran movimientos en cuenta empresa (si la forma de pago tiene cuenta vinculada) -- **omitido si `es_invitacion_total = true`**.
   - Si es CC: se crea movimiento en `movimientos_cuenta_corriente` y se actualiza cache del cliente
   - Si la sucursal tiene facturacion automatica y la forma de pago lo requiere: se emite comprobante fiscal via AFIP -- **omitido si `es_invitacion_total = true`** (cortesia no genera factura fiscal).

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
- Promociones comunes ahora soportan multiples articulos, categorias y formas de pago como condiciones (tabla `promocion_condiciones` con multiples rows por tipo)
- Promociones especiales tienen columnas `formas_pago_ids`, `nxm_articulos_ids`, `nxm_categorias_ids` (TEXT con JSON arrays) para seleccion multiple. Se mantiene retrocompatibilidad con las columnas singulares (`forma_pago_id`, `nxm_articulo_id`, `nxm_categoria_id`)
- Las validaciones usan `in_array()` en vez de comparacion directa para soportar seleccion multiple

**Listas estaticas** (`estatica = true`):
- Al grabar o actualizar una lista estatica, `CongelarPreciosListaService::congelar(ListaPrecio): int` itera todos los articulos activos de la sucursal y aplica la jerarquia completa de ajustes (nivel articulo > nivel categoria > ajuste del encabezado), persistiendo el resultado como `precio_fijo` en `lista_precio_articulos`.
- Las filas con `origen = 'manual'` (precio ingresado a mano por el usuario en el paso 5) se preservan durante el re-snapshot; solo se recalculan las filas con `origen = 'snapshot'`.
- `ListaPrecio::cubreArticulo(Articulo): bool` devuelve `true` si existe una fila en `lista_precio_articulos` para ese articulo.
- `ListaPrecio::obtenerPrecioArticulo(Articulo)` devuelve `['precio' => ..., 'origen' => ...]`. Si la lista es estatica y el articulo no tiene fila, devuelve `['origen' => 'fuera_de_lista_estatica']`.
- En `NuevaVenta::obtenerPrecioConLista`: si la lista aplicable es estatica y `cubreArticulo` devuelve `false`, el sistema usa el precio de la lista base en lugar del precio de la lista estatica.
- El campo `precios_congelados_at` registra la fecha y hora del ultimo snapshot; `diffForHumans()` sobre este campo se muestra en la UI como "Actualizada: hace X".
- Al editar una lista y desactivar el flag `estatica`, el wizard elimina todos los registros `origen = 'snapshot'` de `lista_precio_articulos`.

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

### 3.10 Cambio de Forma de Pago en Ventas Registradas

Permite dividir un pago existente de una venta confirmada en N pagos nuevos con formas de pago distintas, manteniendo el total de la venta inmutable. Implementado en `App\Services\Ventas\CambioFormaPagoService` con patron append-only y procesamiento en dos fases para tolerancia a fallos ARCA.

#### Regla del pivot mixto

La suma de los montos de los pagos nuevos debe ser exactamente igual al `monto_final` del pago original. El `total_final` de la venta no cambia. Esta invariante se valida antes de procesar.

#### Bloqueos previos

Antes de ejecutar se valida:
1. La venta no esta en estado `cancelada`.
2. El `venta_pago` no esta en estado `anulado`.
3. El `venta_pago` no tiene cobros de CC imputados activos (`cobrosAplicados()->exists() === false`).
4. La venta no tiene puntos canjeados.
5. Si `venta_pago.cierre_turno_id` no es NULL: el usuario debe tener permiso `func.cambiar_forma_pago_turno_cerrado`.
6. **(Fase 9)** El `venta_pago` no tiene un cobro de integracion confirmado: `VentaPago::tieneIntegracionConfirmada()` devuelve false. Si devuelve true, `CambioFormaPagoService::puedeModificarVentaPago()` retorna `['puede' => false, 'razon' => ...]`.

#### Fase A — atomica

Envuelta en `DB::connection('pymes_tenant')->transaction()`. Incluye:
1. Anulacion del pago original: `estado = 'anulado'`, snapshot en `datos_snapshot_json`.
2. Reversion de movimientos vinculados al pago anulado:
   - **Caja** (si `afecta_caja = true` y `movimiento_caja_id` no nulo): contraasiento egreso + `caja->disminuirSaldo()`.
   - **Vuelto** (si hay movimiento de vuelto vinculado): contra-ingreso.
   - **Cuenta empresa** (si `movimiento_cuenta_empresa_id` no nulo): `CuentaEmpresaService::revertirMovimiento()`.
   - **Cuenta corriente** (si `es_cuenta_corriente = true`): `CuentaCorrienteService::anularMovimientosVentaPago()` — contraasientos append-only con `tipo = 'anulacion_venta'`.
3. Creacion de los pagos nuevos (uno por cada fila del desglose):
   - Si `afecta_caja = true`: nuevo `MovimientoCaja` tipo ingreso.
   - Si tiene cuenta empresa: `CuentaEmpresaService::registrarMovimientoAutomatico()`.
   - Si es CC: nuevo `MovimientoCuentaCorriente` tipo `venta`.
   - `estado_facturacion` inicial segun flag `facturar` del desglose: `no_facturado` o `pendiente_de_facturar` (se actualizara en Fase B).
4. Emision de NC si corresponde (ver regla fiscal abajo).
5. Creacion del registro en `venta_pago_ajustes`.
6. Recalculo de totales de la venta:
   - `ventas.ajuste_forma_pago` = SUM(`monto_ajuste`) de pagos activos.
   - `ventas.total_final` = SUM(`monto_final`) de pagos activos (no cambia por la regla del pivot).
   - `ventas.es_cuenta_corriente` = true si algun pago activo es CC.
   - `ventas.saldo_pendiente_cache` = SUM(`saldo_pendiente`) de pagos CC activos.

Si cualquier paso falla → rollback total, nada cambia.

#### Fase B — emision de FC nueva (post-commit)

Luego del commit de Fase A, se intenta emitir la FC nueva sobre los pagos nuevos con `facturar = true`. Si ARCA falla:
- Los pagos quedan con `estado_facturacion = 'pendiente_de_facturar'`.
- Se registra el error en el pago.
- El usuario ve un toast rojo con el mensaje de ARCA.
- Los pagos aparecen en el reporte "Pagos Pendientes de Facturar" (`App\Livewire\Cajas\PagosPendientesFacturacion`).

Si ARCA tiene exito → `estado_facturacion = 'facturado'` y `comprobante_fiscal_id` poblado.

#### Regla fiscal binaria

La decision de emitir documentos fiscales se toma comparando montos:

| Condicion | NC | FC nueva |
|---|---|---|
| `monto_facturado_viejo == monto_facturado_nuevo` | No se emite | No se emite. Los pagos nuevos con `facturar=true` heredan el `comprobante_fiscal_id` original. |
| `monto_facturado_viejo != monto_facturado_nuevo` | Si (por el monto del pago viejo anulado, si tenia CF) | Si (por la suma de pagos nuevos con `facturar=true`) |

Esta regla es independiente del flag `facturacion_fiscal_automatica` de la sucursal. El usuario ya decidio explicitamente al marcar cada pago del desglose.

Para omitir la NC cuando la diferencia lo permitiria se requiere permiso `func.modificar_pagos_sin_nc`.

**Fix en `ComprobanteFiscalService`**: al emitir una FC donde el `total_a_facturar` coincide con `total_final` de la venta, se prioriza la lista explicita `pagos_facturar`; si no viene, se excluyen pagos anulados del branch masivo.

#### Fix en TurnoActual

Las 4 queries que calculan totales por forma de pago y concepto en `TurnoActual` ahora filtran por `estado = 'activo'` en `venta_pagos`. Sin este filtro, los pagos anulados (creados por cambios de forma de pago) se sumaban duplicando los totales del turno.

#### Operaciones sobre turnos cerrados

Si el `venta_pago` tiene `cierre_turno_id` no nulo:
- Se requiere permiso `func.cambiar_forma_pago_turno_cerrado`.
- Los contraasientos y movimientos nuevos se crean con `cierre_turno_id = NULL` (van al turno actual abierto).
- El cierre historico no se modifica: `total_ingresos`, `total_egresos` no cambian.
- El registro en `venta_pago_ajustes` tendra `es_post_cierre = true` y `turno_original_id` con el ID del cierre afectado.
- Estos registros aparecen en el reporte "Ajustes Post-Cierre" (`App\Livewire\Cajas\AjustesPostCierre`).

#### Reporte: Pagos Pendientes de Facturar

Componente `App\Livewire\Cajas\PagosPendientesFacturacion`. Ruta `/cajas/pagos-pendientes-facturacion`. Permiso `func.ver_pagos_pendientes_facturacion`.

Lista pagos con `estado_facturacion IN ('pendiente_de_facturar', 'error_arca')`. Acciones:
- **Reintentar** (permiso `func.reintentar_facturacion`): reintenta emision ARCA. Si exito → `facturado`. Si falla → permanece pendiente con nuevo mensaje de error.
- **Marcar como error**: cambia a `error_arca` con motivo obligatorio; excluye del circuito automatico.

#### Trazabilidad

Cada operacion crea exactamente un registro en `venta_pago_ajustes` con:
- Referencias cruzadas a los `venta_pagos` anulado y nuevos.
- `descripcion_auto` generada automaticamente (narrativa legible).
- Motivo, usuario, IP y user_agent.
- Flags `nc_emitida_flag`, `fc_nueva_flag`, `es_post_cierre`.
- `fc_nueva_id` poblado si se emitio FC nueva en Fase B.

El pago anulado tiene `datos_snapshot_json` con sus datos completos al momento de anularse.

#### Permisos funcionales

| Permiso | Descripcion |
|---|---|
| `func.cambiar_forma_pago_venta` | Modificar pagos en ventas registradas |
| `func.cambiar_forma_pago_turno_cerrado` | Operar sobre pagos de turnos ya cerrados |
| `func.modificar_pagos_sin_nc` | Omitir NC cuando la regla fiscal lo permitiria (no aplica cuando es obligatoria) |
| `func.ver_ajustes_post_cierre` | Ver el reporte de ajustes post-cierre |
| `func.reintentar_facturacion` | Reintentar emision de FC sobre pagos pendientes |
| `func.ver_pagos_pendientes_facturacion` | Ver el reporte de pagos pendientes de facturar |

### 3.11 Puntos y Cupones

#### Acumulacion de puntos
1. Al completar una venta con cliente asignado + programa activo + sucursal activa
2. Formula: `SUM(monto_pago * multiplicador_forma_pago) / monto_por_punto`
3. Pagos con puntos y cuenta corriente NO generan nuevos puntos
4. Se registra movimiento tipo `acumulacion` en `movimientos_puntos`
5. Se actualiza cache en `clientes` (puntos_saldo_cache, etc.)

#### Canje de puntos como pago (RF-09)
1. Cliente indica monto $ a pagar con puntos
2. Sistema convierte: `puntos_necesarios = ceil(monto / valor_punto_canje)`
3. Se crea VentaPago con `es_pago_puntos=true`, `afecta_caja=false`
4. Se registra movimiento tipo `canje_descuento` (negativo)

#### Canje de articulo por puntos (RF-10)
1. Articulos con `puntos_canje` definido son canjeables
2. Canje todo-o-nada: puntos_canje * cantidad
3. El valor del articulo se descuenta del total a pagar
4. Stock se descuenta normalmente
5. Se registra movimiento tipo `canje_articulo` (negativo)

#### Descuento general (RF-31 a RF-38)
1. **Porcentaje (%)**: Aplica `ajuste_manual` masivo a todos los items del carrito
2. **Monto fijo ($)**: Se resta del total despues de promociones
3. % y $ son mutuamente excluyentes
4. Items nuevos heredan el descuento % activo
5. Tope por rol: `MAX(roles.descuento_maximo_porcentaje)` de los roles del usuario
6. Requiere permiso funcional `func.descuento_general`

#### Cupones (RF-15 a RF-21)
- Tipo `puntos`: atado a un cliente, puntos se descuentan al crear (no al usar)
- Tipo `promocional`: sin cliente, cualquiera lo puede usar
- Aplica a `total` (monto_fijo o porcentaje) o a `articulos` especificos
- **Cantidad por articulo**: pivot `cupon_articulos.cantidad` (NULL=todas). Si definido, descuento aplica a `min(qty_carrito, cantidad_cupon)` unidades. Para monto fijo: `min(valor_descuento, precio_unit * cant_elegible)`. Para porcentaje: `precio_unit * cant_elegible * %`.
- **Formas de pago**: tabla `cupon_formas_pago`. Si vacia → todas validas. Si tiene registros → la venta debe ser 100% con formas permitidas (se valida en `procesarVentaConDesglose`).
- Control de uso: `uso_actual < uso_maximo` (0=ilimitado)
- Al anular venta con cupon: se revierte el uso (uso_actual--)

#### Anulacion
- Contraasientos ledger para revertir acumulacion y devolver canjes
- Si el saldo quedaria negativo, la anulacion se bloquea (RF-14)

---

### 3.12 Cobro QR Dinamico con Integracion de Pago (Fase 5)

#### Modelo "cobro primero, cobrable despues"

A diferencia del flujo tradicional (donde el comprobante se crea y luego se registra el pago), el cobro via integracion de pago sigue el modelo inverso. Este modelo aplica en todos los flujos de cobro: Nueva Venta, NuevoPedidoMostrador (via `WithPagosDesglose`) y confirmacion de pagos planificados desde `PedidosMostrador`. Aplica tanto para el modo `qr_dinamico` como para `qr_estatico`.

**Flujo comun (todos los hosts)**:

1. El cajero inicia el cobro en cualquier punto de cobro del sistema.
2. Se crea una `IntegracionPagoTransaccion` en estado `pendiente` con `cobrable_type/id = NULL` y `modo_usado` = `qr_dinamico` o `qr_estatico` segun el `modo_default` del pivote.
3. Se llama al gateway (MercadoPago Orders API `POST /v1/orders`) con `config.qr.mode = dynamic` o `static` segun el modo. Esta llamada HTTP ocurre **FUERA de la transaccion DB** para no mantener locks tenant durante la latencia de red.
4. Segun el modo:
   - **QR dinamico**: MP devuelve `qr_data` (trama EMVCo). Se persiste en la transaccion y el front renderiza el SVG del QR una vez, guardandolo en `cobroIntegracionQrSvg`.
   - **QR estatico**: MP no devuelve `qr_data`. El gateway retorna `qr_image_url` con la URL del QR impreso del POS (`caja->mp_pos_qr_url`), que se persiste en `transaccion.metadata['qr_image_url']` y el front lo expone via `cobroIntegracionQrImagenUrl`.
5. Se muestra el modal "Esperando pago" con el QR y un countdown hasta `expira_en`.
6. La confirmacion puede llegar por tres vias (la primera que llegue gana):
   - **Webhook (camino principal)**: MP llama a `POST /api/integraciones/mercadopago/webhook`. El servidor confirma la transaccion y broadcastea el evento `IntegracionPagoActualizado`. El frontend escucha via Echo/Reverb en el canal privado `comercios.{id}.integraciones-pago.transaccion.{txId}`, re-consulta el estado y detecta `confirmado` de forma instantanea.
   - **Polling (respaldo)**: Livewire hace polling cada 3 segundos (`wire:poll.3s="pollearCobroIntegracion"`). Cada tick lee primero el estado LOCAL de la transaccion; solo si sigue `pendiente` consulta al proveedor via `CobroIntegracionService::consultarEstado()`. Actua como red de seguridad si el webhook no llego (webhook no configurado, fallo de red, etc.).
   - **Confirmacion manual (fallback, Fase 8, RF-12)**: si el usuario tiene el permiso `integraciones_pago.confirmar_manual` y el cajero verifica fisicamente que el cliente pago, puede presionar "Si, el cliente pago" en el panel de fallback del modal. Llama a `CobroIntegracionService::confirmarManual()`. La transaccion queda en estado `confirmado_manual` (distinto de `confirmado` para diferenciarlo en reportes). La auditoria registra quien confirmo.
7. Cuando el pago se confirma (por cualquiera de las tres vias):
   - La transaccion queda en `confirmado` (automatico) o `confirmado_manual` (fallback). En ambos casos `estaConfirmada()` devuelve true.
   - Se setea `cobroIntegracionConfirmado = true`.
   - Se invoca el hook `alConfirmarCobroIntegracion()` para que el host materialice su cobrable.
8. El host materializa el cobrable (crea la venta, cobra el pedido, materializa el pago planificado) y llama a `asociarCobroIntegracionAlCobrable($cobrable)`.
9. `asociarCobrable()` vincula el cobrable a la transaccion ya confirmada y registra el evento `cobrable_asociado`.
10. Si el cliente no paga o el cajero cancela: `cancelarCobro()` avisa al proveedor (silencia errores de red), marca la transaccion como `cancelado` y no se materializa ningun cobrable. Se invoca el hook `alCancelarCobroIntegracion()`.
11. **Expiracion automatica (Fase 8, RF-16)**: si la transaccion sigue `pendiente` pasado `expira_en`, el comando `integraciones-pago:expirar-pendientes` (corre cada minuto) la marca como `expirado` y broadcastea `IntegracionPagoActualizado` con `estado = 'expirado'`. El polling del modal detecta el estado terminal localmente y cierra el modal mostrando "tiempo agotado". No se anula ninguna venta.

**Variante — pago planificado con QR** (`PedidosMostrador`):
- Al confirmar un pago planificado cuya `FormaPago` tiene integracion: NO se materializa inmediatamente. Se inicia el cobro QR y se espera al polling.
- Al aprobarse: `confirmarPagoPlanificado()` del service crea el `MovimientoCaja`, pasa el estado del pago a `activo` y recalcula `estado_pago` del pedido. Luego se asocia la transaccion al `PedidoMostrador`.
- Si el QR se cancela o expira: el `PedidoMostradorPago` queda en estado `planificado` intacto (sin ningun movimiento de caja). El modal "Cobrar pendiente" se reabre para reintentar o editar.

**Variante — fallo de facturacion fiscal con QR ya confirmado** (`NuevaVenta`):
- Si `ComprobanteFiscalService` falla despues de que el cobro QR fue confirmado, la venta queda registrada igual (los pagos y movimientos de caja ya se persistieron). Solo la emision fiscal queda pendiente.
- El sistema muestra un toast con el mensaje: "El cobro se registro, pero la facturacion quedo pendiente. Reintentala desde Cajas → Pagos Pendientes de Facturacion."
- Los pagos afectados quedan con `estado_facturacion = 'pendiente_de_facturar'` y aparecen en el reporte "Pagos Pendientes de Facturar".

#### Concern `WithCobroIntegracion` — unica fuente de verdad del cobro QR

`app/Livewire/Concerns/Carrito/WithCobroIntegracion.php` centraliza toda la maquinaria del cobro por integracion. Es usado por:
- `WithPagosDesglose` (que a su vez es usado por `NuevaVenta` y `NuevoPedidoMostrador`)
- `PedidosMostrador` (directamente, para cobro de pagos planificados)

Cualquier cambio a la logica de cobro QR debe hacerse en este concern para que impacte en todos los puntos de cobro.

**Props publicas del concern**: `mostrarModalEsperandoPago`, `cobroIntegracionTransaccionId`, `cobroIntegracionQrData`, `cobroIntegracionQrSvg`, `cobroIntegracionQrImagenUrl`, `cobroIntegracionMonto`, `cobroIntegracionExpiraTs`, `cobroIntegracionConfirmado`.

- `cobroIntegracionQrSvg`: SVG renderizado de la trama EMVCo (modo dinamico). `null` en modo estatico.
- `cobroIntegracionQrImagenUrl`: URL de la imagen del QR impreso del POS (modo estatico). `null` en modo dinamico. Se lee de `transaccion.metadata['qr_image_url']` al iniciar el cobro.

**Metodos publicos**: 
- `iniciarCobroIntegracion(array $datos): void` — Recibe `forma_pago_id`, `monto`, `sucursal_id`, `caja_id`, `moneda_id` como array explicito (el concern no depende de props del host). Resuelve `integracionPrincipal()`, verifica `IntegracionPagoSucursal` activa, llama a `CobroIntegracionService::iniciarCobro()`, genera el SVG del QR y abre el modal.
- `pollearCobroIntegracion(): void` — Respaldo via `wire:poll.3s`. Primero lee el estado LOCAL de la transaccion en DB (sin re-consultar al proveedor): si `estaConfirmada()` cierra el modal y llama `alConfirmarCobroIntegracion()`; si `estaEnEstadoTerminal()` (expirado/cancelado/fallido) dispatcha toast, resetea y llama `alCancelarCobroIntegracion()`. Solo si la transaccion sigue `pendiente` consulta al proveedor via `CobroIntegracionService::consultarEstado()`. Al estado `aprobado`: llama `confirmarCobro()`, setea `cobroIntegracionConfirmado = true`, cierra modal e invoca `alConfirmarCobroIntegracion()`. Al estado `cancelado/expirado/fallido`: dispatcha toast, resetea, dispatcha `cobro-integracion-no-confirmado` e invoca `alCancelarCobroIntegracion()`. Idempotente: si el webhook ya confirmo antes, `confirmarCobro()` es no-op.
> El camino rapido por webhook NO es un metodo PHP: la suscripcion al broadcast vive en el Blade del modal de espera (`_modal-esperando-pago-integracion.blade.php`) — Alpine se suscribe por Echo al canal de la transaccion en `init()` y, al recibir `.IntegracionPagoActualizado`, llama a `$wire.pollearCobroIntegracion()` (el mismo metodo de respaldo). No hay listener Livewire/`getListeners()` por transaccion.
- `confirmarCobroIntegracionManual(): void` — (Fase 8, RF-12) Llama a `CobroIntegracionService::confirmarManual()` con el `Auth::id()` del cajero, marca `cobroIntegracionConfirmado = true`, cierra el modal e invoca `alConfirmarCobroIntegracion()`. Verifica el permiso `integraciones_pago.confirmar_manual` antes de proceder; si el usuario no lo tiene, dispatcha un toast de error y no hace nada.
- `cancelarCobroIntegracion(): void` — Llama `cancelarCobro()` en el service, resetea estado, dispatcha `cobro-integracion-no-confirmado` e invoca `alCancelarCobroIntegracion()`.

**Metodos protegidos**:
- `resetCobroIntegracion(): void` — Limpia todas las props del cobro.
- `asociarCobroIntegracionAlCobrable(Model $cobrable): void` — Asocia la transaccion confirmada al cobrable. No-op si no hubo cobro por integracion.
- `renderizarQrSvg(?string $qrData): ?string` — Genera SVG inline a partir de la trama EMVCo. Sin imagick/gd. Se genera una vez al iniciar para sobrevivir los morphs de `wire:poll`.
- `cajaIdParaPantallaCliente(): ?int` — Default: `caja_activa()`. Los hosts con caja seleccionada propia lo overridean (p. ej. `WithPagosDesglose` retorna `$this->cajaSeleccionada ?? caja_activa()`).

**Propiedades computed**:
- `usaPantallaClienteActiva: bool` — Lee `cajas.usa_pantalla_cliente` usando `cajaIdParaPantallaCliente()`.
- `puedeConfirmarManual: bool` — (Fase 8) Evalua si el usuario autenticado tiene el permiso `integraciones_pago.confirmar_manual`. El modal de espera lo usa para mostrar u ocultar el panel de confirmacion manual.

**Hooks que el host puede overridear**:
- `alConfirmarCobroIntegracion(): void` — Default no-op. Se invoca con `cobroIntegracionConfirmado = true` y el modal ya cerrado. Cada host lo implementa para materializar su cobrable.
- `alCancelarCobroIntegracion(): void` — Default no-op. Se invoca tras resetear el estado. El host puede reabrir su modal para reintentar.

#### Enganche en el trait `WithPagosDesglose`

El trait es compartido entre `NuevaVenta` y `NuevoPedidoMostrador`. Usa `WithCobroIntegracion` internamente. Implementa los elementos especificos del desglose:

- `desglosePagoConIntegracion(): ?array` — Detecta si algun pago del desglose usa una FP con integracion.
- `interceptarCobroPorIntegracion(): bool` — Punto unico de enganche: si hay un pago con integracion no confirmado, llama `iniciarCobroIntegracion()` y retorna `true` (el caller debe abortar y esperar al polling).
- `cajaIdParaPantallaCliente(): ?int` — Override: retorna `$this->cajaSeleccionada ?? caja_activa()`.
- `alConfirmarCobroIntegracion(): void` — Override: llama `verificarPuntoVentaYProcesar()` para reanudar el flujo de venta/pedido con el flag `cobroIntegracionConfirmado = true`.

**Guard en `verificarPuntoVentaYProcesar()`**: si `!cobroIntegracionConfirmado && desglosePagoConIntegracion() !== null`, llama `interceptarCobroPorIntegracion()` y retorna. La segunda vez que entra (con `cobroIntegracionConfirmado = true`), el guard no aplica y el flujo continua normalmente.

**Hook `alCancelarCobroIntegracion()` en `NuevoPedidoMostrador`**: si `$modalPagoEnModoCobro` era true, reabre `$mostrarModalPago` para que el operario retome sin perder el desglose armado.

#### Auditoria append-only

Cada paso relevante genera una fila en `{PREFIX}integraciones_pago_eventos`:
- `creado` → al crear la transaccion en DB
- `iniciado_en_gateway` → al recibir el QR del proveedor
- `webhook_recibido` → al recibir la notificacion de MP en el endpoint webhook (con `metadata.payload` del body recibido)
- `confirmado` → al detectar el pago aprobado (por webhook o por polling)
- `confirmado_manual` → al confirmar manualmente via el panel de fallback en el modal de espera; `metadata` contiene `usuario_id` del cajero que confirmo
- `cobrable_asociado` → al vincular la venta/pedido a la transaccion
- `cancelado` → al cancelar
- `error` → si el gateway falla al iniciar (con `metadata.motivo`)
- `expirado` → generado por el comando `integraciones-pago:expirar-pendientes` al marcar la transaccion como expirada

#### Pantalla orientada al cliente (segundo monitor)

Arquitectura client-side para mostrar el QR al cliente en un segundo monitor, sin backend adicional. La apariencia es personalizable por sucursal via `sucursales.config_pantalla_cliente`.

**Componentes**:
- `resources/js/pantalla-cliente-host.js`: Objeto `window.bcnPantallaClienteHost` que vive en la pestana del POS (cajero). Abre la ventana del cliente via `window.open()` y la posiciona en el segundo monitor usando la **Window Management API** (`window.getScreenDetails()`). Comunica mensajes via **BroadcastChannel** (canal `bcn-pantalla-cliente`). Envia la config de personalizacion (logo_url, nombre, colores, animacion) junto con el primer mensaje y ante cada pong recibido.
- `resources/js/pantalla-cliente.js`: Script de la ventana del cliente (`/pantalla-cliente`). Escucha mensajes del BroadcastChannel y actualiza la UI: aplica config via CSS custom properties + clases de animacion, muestra QR en fullscreen o vuelve al estado idle. Persiste la ultima config recibida en `localStorage` (clave `bcn-pc-config`) para sobrevivir recargas.
- `resources/views/pantalla-cliente.blade.php`: Vista liviana sin Livewire/Alpine. Muestra logo e idle state. Incluye tres botones flotantes: "Pantalla completa", "Enviar a la 2da pantalla" (Window Management API + fullscreen), "Instalar pantalla cliente" (PWA install prompt; solo visible en modo navegador, oculto si ya corre como app instalada). Respeta `prefers-reduced-motion`. Footer "Powered by BCNSOFT" (banner_bcn.png). Declara iconos propios (monitor naranja, `pantalla-cliente-192x192.png` / `512x512.png`) para la pestana/barra de tareas.
- `resources/views/livewire/carrito/_boton-pantalla-cliente.blade.php`: Boton flotante (Alpine, client-side) que se renderiza solo si `usaPantallaClienteActiva` es true. Refresca el estado de conexion cada 2 segundos via `bcnPantallaClienteHost.pingear()`. **Comportamiento segun contexto**: en navegador normal (`'open' in window` = true), el clic llama a `window.open()` para abrir la pantalla cliente; en PWA instalada (`'open' in window` = false, `soportada = false`), el boton queda como indicador de estado no clickeable — muestra "Pantalla cliente desconectada" en gris cuando no hay conexion, y pasa a verde automaticamente cuando la app Pantalla Cliente (instalada por separado) esta abierta y responde pong.
- `resources/views/livewire/carrito/_modal-esperando-pago-integracion.blade.php`: Modal con logica Alpine que, al abrirse, detecta si hay pantalla cliente conectada y envia el QR via `host.enviarQr()`. Si va al cliente, el cajero ve un panel compacto en lugar del QR.

**Mensajes BroadcastChannel**:
- `{ type: 'qr', svg, monto, leyenda }` → muestra el QR en la pantalla cliente
- `{ type: 'idle' }` → vuelve al estado de espera
- `{ type: 'ping' }` / `{ type: 'pong' }` → heartbeat para detectar conexion activa
- `{ type: 'config', logo_url, nombre, color_fondo, color_acento, color_texto, animacion, tamano_logo, mostrar_logo, mostrar_nombre, mensaje_idle }` → aplica personalizacion visual

**Flujo ping/pong (entrega robusta de config)**:
1. Al cargar, la pantalla cliente emite `pong` por el BroadcastChannel.
2. El host detecta el `pong` y reenvía inmediatamente la config actual.
3. Esto garantiza que si la pantalla se recarga mientras el host esta activo, recibe la config sin necesitar una accion del cajero.
4. `estaConectada()` en el host devuelve true si la referencia `window` de la ventana cliente sigue abierta O si se recibio un `pong` en los ultimos N segundos.
5. Antes de enviar la config via `postMessage`, el host clona el objeto a plano (`JSON.parse(JSON.stringify(...))`) para evitar errores de structured clone con proxies Alpine.

**Config computed en Livewire**:
`WithCobroIntegracion::configPantallaCliente()` (computed) — llama a `sucursal->getConfigPantallaCliente()` y agrega `logo_url` (via `logoPantallaClienteUrl()`) y `nombre` (via `nombrePantallaCliente()`). Es el objeto que el host envia al cliente via BroadcastChannel.

**Ruta**: `GET /pantalla-cliente` (autenticada con `auth`, sin `verified`/`tenant`; fuera del grupo `prefix('app')`). Carga `empresaConfig` para mostrar logo y nombre como fallback inicial antes de recibir la config del host.

**PWA principal (app)**:
- Manifest: `public/manifest.json`. `scope: /app`, `start_url: /app`, `id: /app`, `display: standalone`.
- `public/sw.js`: CACHE_NAME `bcn-pymes-v5`, precachea `/app`, `/app/dashboard`, `/offline.html`, `/manifest.json`.
- Ruta `GET /app` (sin sub-path): redirect server-side a `route('dashboard')` si hay sesion activa, o a `route('login')` si no. Permite que al abrir la PWA (`start_url: /app`) el login caiga dentro del scope (no abre pestaña del navegador).
- Todas las rutas autenticadas viven bajo `/app/*` (nombres de ruta sin cambios: `dashboard`, `ventas.index`, etc.).
- Redirects de cortesia 301 desde URLs viejas sin prefijo (`/dashboard`, `/login`, `/ventas`, etc.) hacia `/app/*` para no romper bookmarks.

**PWA pantalla cliente**:
- Manifest: `public/manifest-pantalla-cliente.json`. `display: standalone` (no `fullscreen`; el fullscreen lo aplica el boton "Enviar a la 2da pantalla" en runtime), `display_override: ["standalone","minimal-ui"]`, `scope: /pantalla-cliente`, `id: /pantalla-cliente`. Icono propio: monitor naranja (#FFAF22), archivos `public/pwa-icons/pantalla-cliente-*.png`.
- Los scopes `/app` y `/pantalla-cliente` son disjuntos: el navegador los trata como apps independientes instalables al mismo tiempo. Ya no existe la limitacion anterior de scope hijo.
- Deteccion de modo instalado en `pantalla-cliente.js`: `enModoApp = standalone || minimal-ui || fullscreen || navigator.standalone`. Si `enModoApp` es true, el boton "Instalar pantalla cliente" se oculta.

**Requisitos del navegador**: Chrome o Edge, contexto seguro (https o localhost), monitores en modo "Extender". El permiso `window-management` se solicita la primera vez que se usa `getScreenDetails()`. Si la API no esta disponible o el permiso se deniega, la ventana se abre de todas formas y el cajero la arrastra manualmente.

#### Webhook de Mercado Pago y confirmacion en tiempo real

**Endpoint**: `POST /api/integraciones/mercadopago/webhook`
- Ruta publica (sin autenticacion Sanctum ni CSRF).
- MP la llama cuando confirma un pago QR.

**Flujo del webhook**:

1. MP envia un `POST` con cabecera `x-signature` (HMAC-SHA256) y body JSON con el `id` de la order.
2. El sistema resuelve a que comercio/sucursal pertenece la notificacion usando la tabla `mercadopago_collector_index` (conexion `config`): busca el `user_id` de MP que esta en la notificacion, obtiene el `comercio_id` y el `sucursal_id`.
3. Configura la conexion tenant sin sesion HTTP via `TenantService::usarComercioParaProceso(int $comercioId)` (metodo nuevo de Fase 6, disenado para procesos sin request HTTP como webhooks y comandos artisan).
4. Verifica la firma `x-signature` con el `webhook_secret` encriptado de la `IntegracionPagoSucursal`. Si la firma es invalida retorna HTTP 401. Si no hay `webhook_secret` configurado, omite la verificacion de firma pero igual re-consulta el estado real de la order a la API de MP con el access token de la sucursal para asegurarse de que la notificacion es legitima.
5. Llama a `CobroIntegracionService::confirmarCobro()` para marcar la transaccion como `confirmado`. Idempotente: si ya estaba confirmada, no hace nada.
6. Broadcastea el evento `IntegracionPagoActualizado` (ver abajo).
7. Retorna HTTP 200. El cobrable **no se materializa** en el webhook (no tiene el carrito ni el contexto de la sesion del cajero); solo confirma la transaccion server-side.

**Resolucion multi-tenant**: el webhook es un endpoint global unico para todos los comercios. La tabla `mercadopago_collector_index` (conexion `config`, sin prefijo) actua como indice de routing: mapea `user_id_externo` (ID de cuenta MP) al `comercio_id` y `sucursal_id` tenant. Este indice se sincroniza automaticamente al guardar o actualizar una `IntegracionPagoSucursal`.

**Robustez**: si el cajero cierra el navegador despues de iniciar el cobro QR y antes de que el cliente pague, el pago queda confirmado server-side igualmente cuando MP llama al webhook. La transaccion queda en estado `confirmado` sin cobrable asociado, disponible para reconciliacion futura.

#### Evento broadcast `IntegracionPagoActualizado`

`app/Events/Broadcasting/IntegracionPagoActualizado.php` — implementa `ShouldBroadcastNow` (dispatch sincrono, sin cola).

**Canal**: canal privado `comercios.{comercioId}.integraciones-pago.transaccion.{transaccionId}`.

**Payload** (`broadcastWith()`):
```json
{
  "transaccion_id": 123,
  "estado": "confirmado"
}
```

**Suscripcion (frontend, no PHP)**: el modal de espera `_modal-esperando-pago-integracion.blade.php` (compartido por NuevaVenta, NuevoPedidoMostrador y PedidosMostrador) renderiza el nombre del canal en un `data-cobro-canal` y, vía Alpine `init()`, se suscribe con `window.Echo.private(canal).listen('.IntegracionPagoActualizado', () => $wire.pollearCobroIntegracion())`; en `destroy()` hace `Echo.leave()`. La via que llegue primero (webhook→broadcast o polling) toma el control; la segunda es no-op por idempotencia de `confirmarCobro()`. No hay listener Livewire por transaccion (evita pelear con `getListeners()` de cada host).

#### Tabla: `mercadopago_collector_index` (conexion `config`, sin prefijo)

Indice de routing para el webhook global. Permite resolver a que comercio/sucursal pertenece una notificacion de MP sin iterar todos los tenants.

| columna | tipo | descripcion |
|---|---|---|
| `id` | bigint PK | - |
| `comercio_id` | bigint | ID del comercio en `config.comercios` |
| `sucursal_id` | bigint | ID de la sucursal en el tenant |
| `user_id_externo` | varchar(50) | User ID de la cuenta de Mercado Pago |
| `modo` | enum | `test` o `produccion` |
| `created_at` / `updated_at` | timestamps | - |

Indice UNIQUE en `(user_id_externo, modo)`. Se sincroniza via `IntegracionPagoSucursalService` al guardar la configuracion.

#### `TenantService::usarComercioParaProceso(int $comercioId)`

Metodo nuevo (Fase 6). Configura la conexion tenant (`pymes_tenant`) para un proceso que corre fuera de una sesion HTTP (webhook, comando artisan, job de cola). A diferencia del flujo normal (que resuelve el comercio desde la sesion del usuario autenticado), este metodo acepta el `comercioId` directamente y establece el prefijo de tablas en el `TenantService` sin requerir `Auth::user()`.

Uso tipico: el webhook lo llama tras resolver el `comercio_id` desde `mercadopago_collector_index`, antes de cualquier consulta a tablas tenant.

#### Trazabilidad de cobros en pagos mixtos (Fase 9)

La columna `venta_pagos.integracion_pago_transaccion_id` (FK nullable a `{PREFIX}integraciones_pago_transacciones`, ON DELETE SET NULL) vincula el `venta_pago` que fue cobrado via QR con su transaccion confirmada. Resuelve el caso de pagos mixtos donde solo una de las formas de pago usa integracion QR.

**Regla de asignacion (en `WithPagosDesglose::procesarVentaConDesglose`)**: al materializar la venta, se itera el desglose de pagos y se asigna `integracion_pago_transaccion_id` al primer `venta_pago` cuya `FormaPago` tenga integracion (`tieneIntegracion()`), usando el `cobroIntegracionTransaccionId` ya confirmado. El flag `$txIntegracionAsignada` garantiza que solo un `venta_pago` recibe el vinculo (modo unico por FP).

**Helpers en `VentaPago` (Fase 9)**:
- `integracionTransaccion(): BelongsTo` — relacion a `IntegracionPagoTransaccion` via `integracion_pago_transaccion_id`.
- `tieneIntegracionConfirmada(): bool` — retorna true si `integracion_pago_transaccion_id` no es null Y `integracionTransaccion()->confirmadas()->exists()` (usa el scope `confirmadas()` del modelo, que cubre tanto `confirmado` como `confirmado_manual`).

**Helper en `Venta` (Fase 9)**:
- `tieneIntegracionPagoConfirmada(): bool` — retorna true si algun `venta_pago` de la venta tiene `integracion_pago_transaccion_id` no nulo con transaccion confirmada. Usa `whereNotNull` + `whereHas('integracionTransaccion', fn($q) => $q->confirmadas())`.

#### Bloqueo de anulacion y modificacion por cobro QR confirmado (Fase 9)

Una venta o pago con cobro de integracion QR ya confirmado no puede anularse ni modificarse porque el dinero ya fue acreditado en la cuenta del proveedor (MercadoPago) y no existe mecanismo de refund automatico implementado todavia.

**Metodo protegido `VentaService::protegerContraIntegracionConfirmada(Venta $venta): void`**: lanza `Exception` con el mensaje traducible `'No se puede anular ni modificar: esta venta tiene un cobro por integracion (QR) ya confirmado. La devolucion debe hacerse desde el proveedor de pago.'` si `$venta->tieneIntegracionPagoConfirmada()` devuelve true.

**Donde se aplica el bloqueo**:
- `VentaService::cancelarVentaCompleta()` — antes de iniciar la transaccion. Bloquea la anulacion total de la venta.
- `VentaService::anularPagosYPasarACtaCte()` — antes de la transaccion. Bloquea el pase a cuenta corriente (que anularia los pagos actuales).
- `CambioFormaPagoService::puedeModificarVentaPago()` — retorna `['puede' => false, 'razon' => ...]` si el `venta_pago` tiene `tieneIntegracionConfirmada() === true`. Bloquea la modificacion de ese pago especifico.

**Que NO bloquea**: la anulacion de solo la parte fiscal (emision de nota de credito sobre el comprobante) no toca el cobro y sigue siendo posible. El bloqueo aplica solo a operaciones que reverterian el dinero cobrado.

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

#### Ajustes post-cierre de una sucursal

```sql
SELECT vpa.created_at as fecha_ajuste,
       vpa.tipo_operacion,
       vpa.descripcion_auto,
       vpa.motivo,
       fp_ant.nombre as forma_pago_anterior,
       fp_nue.nombre as forma_pago_nueva,
       vpa.monto_anterior,
       vpa.monto_nuevo,
       vpa.delta_total,
       vpa.turno_original_id,
       v.numero as venta_numero,
       u.name as usuario
FROM {PREFIX}venta_pago_ajustes vpa
JOIN {PREFIX}ventas v ON v.id = vpa.venta_id
JOIN users u ON u.id = vpa.usuario_id
LEFT JOIN formas_pago fp_ant ON fp_ant.id = vpa.forma_pago_anterior_id
LEFT JOIN formas_pago fp_nue ON fp_nue.id = vpa.forma_pago_nueva_id
WHERE vpa.sucursal_id = ?
  AND vpa.es_post_cierre = 1
  AND vpa.created_at >= ?
  AND vpa.created_at <= ?
ORDER BY vpa.created_at DESC;
```

#### Historial de cambios de pagos de una venta

```sql
SELECT vpa.created_at, vpa.tipo_operacion, vpa.descripcion_auto,
       vpa.motivo, vpa.es_post_cierre,
       vpa.nc_emitida_flag, vpa.fc_nueva_flag,
       u.name as usuario
FROM {PREFIX}venta_pago_ajustes vpa
JOIN users u ON u.id = vpa.usuario_id
WHERE vpa.venta_id = ?
ORDER BY vpa.created_at ASC;
```

#### Pagos pendientes de facturar (en cola de reintento ARCA)

```sql
SELECT vp.id, vp.estado_facturacion,
       vp.monto_facturado, vp.created_at,
       v.numero as venta_numero, v.fecha as venta_fecha,
       fp.nombre as forma_pago
FROM {PREFIX}venta_pagos vp
JOIN {PREFIX}ventas v ON v.id = vp.venta_id
JOIN {PREFIX}formas_pago fp ON fp.id = vp.forma_pago_id
WHERE vp.estado_facturacion IN ('pendiente_de_facturar', 'error_arca')
  AND vp.estado = 'activo'
  AND v.sucursal_id = ?
ORDER BY vp.created_at ASC;
```

#### Articulos pesables

```sql
SELECT id, nombre, codigo, unidad_medida, precio_base
FROM {PREFIX}articulos
WHERE pesable = 1 AND activo = 1
  AND deleted_at IS NULL
ORDER BY nombre;
```

#### Transacciones de cobro por integracion (pendientes o recientes)

```sql
SELECT t.id, t.estado, t.monto, t.cobrable_type, t.cobrable_id,
       t.external_id, t.expira_en, t.confirmado_en, t.created_at
FROM {PREFIX}integraciones_pago_transacciones t
WHERE t.sucursal_id = ?
  AND t.created_at >= NOW() - INTERVAL 24 HOUR
ORDER BY t.created_at DESC;
```

#### Auditoria de eventos de una transaccion de cobro

```sql
SELECT e.evento, e.payload_externo, e.metadata, e.created_at
FROM {PREFIX}integraciones_pago_eventos e
WHERE e.transaccion_id = ?
ORDER BY e.created_at ASC;
```

### 4.2 Convenciones de Datos

**Estados posibles de cada entidad:**

| Entidad | Estados | Descripcion |
|---|---|---|
| Venta | `completada`, `pendiente`, `cancelada` | Pendiente = cuenta corriente sin saldar |
| Compra | `completada`, `pendiente`, `cancelada` | Pendiente = cuenta corriente proveedor |
| Cobro | `activo`, `anulado` | |
| VentaPago.estado | `activo`, `pendiente`, `anulado` | |
| VentaPago.estado_facturacion | `no_facturado`, `facturado`, `pendiente_de_facturar`, `error_arca` | Estado fiscal del pago; `pendiente_de_facturar` = FC nueva en cola por fallo ARCA |
| CobroPago | `activo`, `anulado` | |
| MovimientoStock | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaCorriente | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaEmpresa | `activo`, `anulado` | Anulado = contraasiento |
| Caja | `abierta`, `cerrada` | |
| ComprobanteFiscal | `pendiente`, `autorizado`, `rechazado`, `anulado` | |
| Produccion | `confirmado`, `anulado` | |
| ProvisionFondo | `pendiente`, `confirmado`, `cancelado` | |
| DepositoBancario | `pendiente`, `confirmado`, `cancelado` | |
| IntegracionPagoTransaccion | `pendiente`, `confirmado`, `cancelado`, `fallido`, `expirado` | `estaEnEstadoTerminal()` = true para todos menos `pendiente` |

**Formatos de fecha:**
- Fechas se almacenan como `timestamp` o `date` en MySQL.
- Fechas de creacion/actualizacion: `created_at`, `updated_at` (timezone del servidor).
- Fechas de negocio (`venta.fecha`, `cobro.fecha`): generalmente `date` o `timestamp`.

**Formatos de moneda y cantidades:**
- **Cantidades de stock**: `decimal(12,3)` -- 3 decimales para soportar articulos pesables (kg, gr, lt)
- **Montos monetarios**: `decimal(12,2)` -- 2 decimales
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
Las tablas `movimientos_stock`, `movimientos_cuenta_corriente`, `movimientos_cuenta_empresa`, `venta_pagos` (para cambios de pago) e `integraciones_pago_eventos` siguen el patron append-only:
- Los registros nunca se modifican ni eliminan.
- Las anulaciones se hacen creando un **contraasiento** que invierte los montos (movimientos) o marcando el registro como `estado = 'anulado'` y creando uno nuevo (venta_pagos).
- El original se vincula al contraasiento via `anulado_por_movimiento_id` (movimientos) o via `venta_pago_reemplazado_id` (venta_pagos).
- Para calcular saldos de movimientos: sumar todos los activos.
- Para calcular totales de una venta: sumar los `venta_pagos` con `estado = 'activo'`.

---

## 5. Notas Tecnicas

### 5.1 Patrones Livewire: propiedades publicas y snapshot

Los arrays PHP usados solo para traducir etiquetas en la vista (ej: tipos de ajuste, opciones de redondeo) NO deben declararse como propiedades publicas en componentes Livewire. Al ser publicas, Livewire las serializa en el snapshot cifrado y cualquier cambio de version o de contenido genera `CorruptComponentPayloadException`.

**Solucion**: declarar esos arrays como computed properties (`#[Computed]`) o getters privados que se recalculan en cada render. Esto aplica preventivamente a cualquier componente wizard o de configuracion compleja.

Componentes donde se aplico este patron: `WizardListaPrecio` (paso 2, opciones de ajuste/redondeo) y `ListarPromociones`.

### 5.2 Scope `conStock` en MovimientosStock

El componente `MovimientosStock` ejecuta busquedas de articulos via un scope del modelo. El scope debe existir en el modelo `Articulo`; de lo contrario lanza `BadMethodCallException: Call to undefined method conStock()`. El fix consiste en asegurarse de invocar el metodo de busqueda correcto disponible en el modelo (ej: `buscarPorNombreOCodigo` o equivalente) en lugar de un scope inexistente.
