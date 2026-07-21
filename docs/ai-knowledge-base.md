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
- `localidades`, `provincias` -- Datos geograficos. `localidades` tiene columnas `latitud` / `longitud` (`decimal(10,7)` nullable) populadas desde el dataset GeoRef; sirven para centrar el picker de Google Maps en la localidad elegida. Ver `Localidad::centro(?int $id): ?array`.
- `pantalla_publica_tokens` -- Indice global que mapea token/codigo publico → comercio + sucursal para las pantallas auxiliares Clase B (llamador de pedidos, consultor de precios). Permite resolver el tenant SIN sesion a partir del token de la URL, sin escanear las N BDs tenant. Ver seccion 2.15.

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
| `fecha_nacimiento` | date nullable | Cumpleanios del cliente (RF-T19, 2026-07-21): lo pide el checkout de la tienda para promociones; nunca se borra, solo se setea cuando el checkout lo trae |
| `direccion` | varchar(255) nullable | Direccion |
| `provincia` | varchar(6) nullable | Codigo ISO 3166-2 — jurisdiccion fiscal del cliente para IIBB (ej: `AR-B`, `AR-C`) |
| `localidad_id` | bigint nullable | Ref soft a `localidades` (config); sin FK cross-DB |
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

**Relaciones del modelo `Cliente`**:
- `localidad()`: BelongsTo `Localidad` via `localidad_id`. Ref soft a `localidades` (config); sin FK cross-DB.
- `impuestoConfigs()`: HasMany `ClienteImpuestoConfig` via `cliente_id`. Perfil fiscal del cliente: percepciones de IIBB por sujeto (RF-13, Fase 10a). Lo consume `ImpuestoService::calcularTributos` para refinar la percepcion.

#### Tabla: `cliente_impuesto_configs`
Perfil fiscal del cliente: espejo de `cuit_impuesto_configs` con semantica de sujeto percibido (RF-13, Fase 10a). Una fila por cliente + impuesto IIBB + vigencia. Permite exencion explicita o alicuota override que pisa la alicuota fija del agente.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cliente_id` | bigint FK (CASCADE) | Cliente |
| `impuesto_id` | bigint FK | Impuesto del catalogo (debe ser tipo IIBB) |
| `exento` | boolean | Si true, no se percibe este impuesto al cliente aunque el CUIT sea agente |
| `alicuota` | decimal(6,4) nullable | Alicuota porcentual override (pisa la fija del agente si no NULL) |
| `alicuota_minimo_base` | decimal(12,2) nullable | Umbral de base imponible; si NULL, se usa el del agente |
| `numero_padron` | varchar(30) nullable | N° de inscripcion o constancia del sujeto |
| `origen_alicuota` | enum | `manual` (cargada a mano) o `padron` (importado desde padron ARBA/AGIP — Fase 10b) |
| `vigente_desde` | date nullable | Inicio de vigencia; NULL = siempre vigente |
| `vigente_hasta` | date nullable | Fin de vigencia; NULL = sin fecha limite |
| `datos_extra` | json nullable | Trazabilidad del padron: `{ agencia: "arba"\|"agip", linea: "<fila cruda>" }`. Solo para `origen_alicuota = padron` |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Unique**: `(cliente_id, impuesto_id, vigente_desde)`.

**Scope `vigentes($fecha)`**: filtra configs activas en una fecha dada (mismo comportamiento que `cuit_impuesto_configs`).

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
| `precio_iva_incluido` | boolean | DEPRECADA (hardening-circuito-precios, 2026-07-14): forzada a `true` en toda escritura (alta, edicion, import, alta rapida). El precio de venta es SIEMPRE final con IVA incluido; la columna no se elimina para no romper reads existentes pero ya no se lee para decidir el desglose fiscal |
| `precio_base` | decimal(12,2) | Precio base del articulo |
| `utilidad_porcentaje` | decimal(6,2) nullable | Override de utilidad objetivo (markup % sobre costo neto); NULL = hereda de `categorias.utilidad_porcentaje` o, en su defecto, de `configuracion_costos.utilidad_default` (spec compras-costos, RF-08) |
| `precio_administrado_por_utilidad` | boolean | Default false. RF-11: opt-in de repricing automatico — al confirmar una compra que cambia el costo de este articulo, su precio se recalcula solo con la formula de precio sugerido |
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
| `utilidad_porcentaje` | decimal(6,2) nullable | Override de utilidad objetivo para los articulos de esta categoria; NULL = hereda de `configuracion_costos.utilidad_default` (spec compras-costos, RF-08, nivel intermedio de la cascada comercio → categoria → articulo) |
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
| I | Precio IVA incluido | Informativa. Se ignora al importar (hardening-circuito-precios: `precio_iva_incluido` se fuerza siempre a `true`); puede seguir saliendo en el export. |
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
| `origen` | varchar libre | Fuente del cambio. Valores conocidos: `manual`, `cambio_masivo`, `importacion`, `masivo_sucursal` (cambio masivo de precios, sucursal activa), `revision_compra`, `utilidad_automatica`, `articulo_editar`, `override_sucursal` |
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
| `mp_point_terminal_id` | varchar(64) nullable | ID de la terminal Point (posnet fisico) asignada a esta caja. Formato `{tipo}__{serial}`. Se obtiene de `GET /terminals/v1/list` y se vincula via `SincronizacionMercadoPagoService::vincularTerminalCaja()`. Point NO usa stores/POS (eso es del producto QR); usa vinculacion de devices. Indexado. |
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
| `identificador_externo` | varchar(100) nullable | ID de la cuenta en el proveedor de pago externo (para MP = `user_id_externo`). Junto con `subtipo` forma la identidad unica de la cuenta. Nullable: las cuentas bancarias/manuales no lo usan. |
| `conciliacion_automatica` | boolean | Default `false`. Si esta activa, el comando `conciliaciones:procesar` crea cada dia una corrida para el dia anterior (siempre queda pendiente de revision). Solo visible/editable para cuentas con `identificador_externo`. |
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

**Indices**: UNIQUE `(subtipo, identificador_externo)`. MySQL permite multiples NULL en indices unicos, por lo que las cuentas manuales (sin identificador_externo) no colisionan entre si.

**Scope `porIdentidad($subtipo, $identificadorExterno)`**: filtra por `(subtipo, identificador_externo)`. Es el punto de entrada del auto-vinculo de integraciones (`findOrCreateParaIntegracion`).

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
| `origen_tipo` | varchar(50) nullable | `VentaPago`, `CobroPago`, `TransferenciaCuentaEmpresa`, `DepositoBancario`, `Manual`, `IntegracionPagoTransaccion`, `ConciliacionFila` |
| `origen_id` | bigint nullable | ID del origen |
| `usuario_id` | bigint FK | Usuario |
| `sucursal_id` | bigint FK nullable | Sucursal |
| `estado` | enum | `activo` o `anulado` |
| `anulado_por_movimiento_id` | bigint FK nullable | Contraasiento |
| `observaciones` | text nullable | Notas |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Conceptos de movimiento conocidos** (tabla `{PREFIX}conceptos_movimiento_cuenta`, columna `codigo`):
- `venta` — ingreso por venta directa
- `cobro` — ingreso de cobranza de cuenta corriente
- `ajuste` — ajuste manual
- `transferencia_entrada` / `transferencia_salida` — transferencias entre cuentas
- `deposito_bancario` — deposito bancario confirmado
- `cobro_integracion` — ingreso por cobro confirmado a traves de una integracion de pago (QR, Point u otro proveedor). Registrado por `CobroIntegracionService` al confirmar la transaccion. Origen polimórfico: `IntegracionPagoTransaccion`. Permite filtrar los ingresos de integraciones para conciliacion.
- `comision_integracion` — egreso por comision cobrada por el proveedor sobre un cobro conciliado. Generado al aplicar una corrida de conciliacion (fila hija de tipo `comision`). Origen polimórfico: `ConciliacionFila`.
- `retiro_integracion` — egreso por retiro a banco desde el proveedor. Generado al aplicar conciliacion. Origen polimórfico: `ConciliacionFila`.
- `devolucion_integracion` — egreso por devolucion o contracargo registrado en el proveedor. Generado al aplicar conciliacion. Origen polimórfico: `ConciliacionFila`.
- `acreditacion_integracion` — ingreso por acreditacion o rendicion registrada en el proveedor (cobros externos, transferencias recibidas). Generado al aplicar conciliacion. Origen polimórfico: `ConciliacionFila`.
- `ajuste_conciliacion` — ingreso o egreso de ajuste por diferencia de saldo inicial al conciliar por primera vez una cuenta. Generado al aplicar conciliacion cuando se informa el saldo real inicial (RF-07). Origen polimórfico: `ConciliacionFila`.

#### Tabla: `cuenta_empresa_sucursal`
Pivot que vincula cuentas empresa con sucursales. Si no tiene sucursales asignadas, esta disponible en todas.

#### Tabla: `conciliaciones_cuenta`
Corridas de conciliacion de una `CuentaEmpresa` contra el proveedor de pago. Patron append-only: una corrida descartada o con error NO se edita, se crea una nueva.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cuenta_empresa_id` | bigint FK | Cuenta conciliada |
| `desde` | date | Inicio del periodo |
| `hasta` | date | Fin del periodo |
| `estado` | enum | `generando`, `pendiente_revision`, `aplicada`, `descartada`, `error` |
| `origen` | enum | `manual` (usuario) o `programada` (comando automatico) |
| `solicitud_reporte` | varchar(255) nullable | Identificador o rango de la solicitud al proveedor (para polling) |
| `archivo_reporte` | varchar(255) nullable | Nombre del archivo descargado (auditoria) |
| `saldo_sistema` | decimal(15,2) nullable | Snapshot del saldo del ledger al generar la corrida |
| `total_matcheados` | int | Cantidad de filas clasificadas como `matcheado` |
| `total_solo_proveedor` | int | Cantidad de filas `solo_proveedor` |
| `total_solo_sistema` | int | Cantidad de filas `solo_sistema` |
| `monto_propuesto_ingresos` | decimal(15,2) | Suma de ingresos propuestos (antes de aplicar) |
| `monto_propuesto_egresos` | decimal(15,2) | Suma de egresos propuestos (antes de aplicar) |
| `error_mensaje` | text nullable | Mensaje si estado = `error` |
| `usuario_id` | bigint FK nullable | Quien la creo (NULL = generada por el comando) |
| `aplicada_por` | bigint FK nullable | Quien aplico los ajustes |
| `aplicada_en` | timestamp nullable | Cuando se aplico |
| timestamps | | |

Indices: KEY (`cuenta_empresa_id`, `estado`), KEY (`estado`).

**Maquina de estados**: `generando` → `pendiente_revision` → `aplicada` o `descartada`. Terminal `error` (reintentable creando corrida nueva). Solo puede haber UNA corrida activa (`generando` o `pendiente_revision`) por cuenta a la vez.

**Origen polimórfico**: los movimientos generados usan `origen_tipo='ConciliacionFila'` como string plano (igual que `IntegracionPagoTransaccion`) — NO requiere morphMap porque `movimientos_cuenta_empresa.origen_tipo/origen_id` no usa relaciones Eloquent morph.

#### Tabla: `conciliacion_filas`
Detalle de cada corrida. Una fila por movimiento del reporte del proveedor, mas filas hijas de comision y filas de ajuste inicial.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `conciliacion_cuenta_id` | bigint FK | Corrida |
| `tipo` | enum | `cobro`, `comision`, `devolucion`, `contracargo`, `retiro`, `retiro_cancelado`, `acreditacion`, `ajuste_inicial`, `otro` |
| `clasificacion` | enum | `matcheado`, `solo_proveedor`, `solo_sistema`, `ya_registrado` |
| `id_externo` | varchar(100) nullable | ID de la operacion en el proveedor (MP: SOURCE_ID) |
| `referencia` | varchar(255) nullable | Referencia externa que el sistema seteo al cobrar (MP: EXTERNAL_REFERENCE) |
| `fecha` | datetime nullable | Fecha de la operacion en el proveedor |
| `descripcion` | varchar(255) nullable | Descripcion legible |
| `monto_bruto` | decimal(15,2) | Monto bruto |
| `comision` | decimal(15,2) | Comision cobrada por el proveedor |
| `monto_neto` | decimal(15,2) | Monto neto |
| `accion` | enum | `generar_movimiento`, `ignorar`, `sin_accion`. Editable durante la revision; propuestas arrancan en `generar_movimiento` |
| `tipo_movimiento` | enum nullable | `ingreso` o `egreso` del movimiento propuesto |
| `concepto_codigo` | varchar(50) nullable | Codigo del concepto del movimiento propuesto |
| `integracion_pago_transaccion_id` | bigint FK nullable | Transaccion matcheada (solo filas `matcheado`) |
| `impuesto_id` | bigint FK nullable | Impuesto identificado por el gateway segun `TAX_DETAIL` del reporte del proveedor (Fase 4a, RF-06). Si no NULL, `ImpuestoService::registrarDesdeConciliacion()` lo registra en el ledger al aplicar. |
| `movimiento_cuenta_empresa_id` | bigint FK nullable | Movimiento generado al aplicar |
| timestamps | | |

Indices: KEY (`conciliacion_cuenta_id`, `clasificacion`), KEY (`id_externo`).

**Idempotencia cross-corrida**: la unicidad no es un UNIQUE en la tabla (filas ignoradas o descartadas pueden repetirse legitimamente en corridas solapadas). La guarda es una query al aplicar: si ya existe un `MovimientoCuentaEmpresa` con origen `ConciliacionFila` de la misma cuenta y mismo `(tipo, id_externo)`, la fila se clasifica `ya_registrado` y no vuelve a proponer movimiento.

### 2.9 Compras, Costos y Cuenta Corriente de Proveedores

> Modulo reescrito por completo (spec `compras-costos-precios`, D1-D22): compra (fiscal o no) → costo computable neto del renglon → costos del articulo (ultimo/promedio/reposicion, por sucursal + consolidado) → historial → utilidad objetivo → precio de venta sugerido → revision/repricing. Incluye el lado PAGO: cuenta corriente de proveedores espejo de la de clientes. Incrementos post-merge D23 (factura de servicio), D24 (percepciones habituales por proveedor) y D25 (coeficiente de computabilidad de percepciones sufridas) — ver 3.8.8.

#### Tabla: `compras`
Encabezado de cada comprobante de compra (factura, NC de proveedor o compra no fiscal). `estado` es **solo ciclo de vida** (D11); lo impago se deriva SIEMPRE de `saldo_pendiente > 0`, nunca del estado.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `numero_comprobante` | varchar(191) | Numero interno autogenerado (`COM-{suc}-{8dig}`, o `NCP-` para notas de credito) |
| `numero_comprobante_proveedor` | varchar(20) nullable | Numero REAL del comprobante del proveedor (formato libre, ej: `0003-00012345`) |
| `sucursal_id` | bigint FK | Sucursal |
| `proveedor_id` | bigint FK | Proveedor |
| `cuit_id` | bigint FK nullable | CUIT del comercio (comprador) al que se imputa fiscalmente la compra. ON DELETE SET NULL. NULL = no alimenta el ledger fiscal |
| `usuario_id` | bigint FK | Usuario que registro |
| `tipo_comprobante` | varchar | `factura_a`, `factura_b`, `factura_c`, `factura_m`, `no_fiscal`, `nota_credito_a`, `nota_credito_b`, `nota_credito_c`, `nota_credito_no_fiscal` |
| `es_servicio` | boolean default false | D23: modalidad "factura de servicio" (luz, gas, alquiler...). Sin renglones de articulo ni efectos de stock/costos/repricing; el detalle son los `compra_conceptos`. Helper `Compra::esServicio()` |
| `compra_origen_id` | bigint FK nullable (compras) | Si es una NC: la compra original que devuelve. NULL en compras normales y NC sueltas |
| `cuenta_compra_id` | bigint FK nullable (cuentas_compra) | Agrupacion de gestion para reportes (precargada del proveedor, editable) |
| `fecha` | date | Fecha de carga (usada para agrupar reportes) |
| `fecha_comprobante` | date nullable | Fecha de la factura — **rige el periodo fiscal del credito de IVA**. Obligatoria si es fiscal |
| `fecha_vencimiento` | date nullable | Aging de deuda en cta cte (se precarga con `proveedores.dias_pago`) |
| `subtotal` | decimal(12,2) | Suma de renglones con descuentos aplicados (neto si discrimina IVA, final si no) |
| `descuento_global_porcentaje` | decimal(6,2) nullable | % de descuento del pie del comprobante |
| `descuento_global_monto` | decimal(12,2) | Monto del descuento global (se prorratea a los renglones por importe). SIEMPRE derivado de `descuento_global_porcentaje` (RF-B2 hardening-circuito-precios): sin porcentaje, el monto recalculado es 0 — no hay camino que persista un monto fijo sin porcentaje (antes borrar el % en un borrador dejaba un descuento "fantasma" via un fallback muerto en `montoDescuentoGlobal()`) |
| `neto_gravado`, `neto_no_gravado`, `neto_exento` | decimal(12,2) | Netos del encabezado (espejo de `comprobante_fiscal_iva`) |
| `total_iva` | decimal(12,2) | Total de IVA (suma de `compra_ivas`) |
| `total` | decimal(12,2) | Total del comprobante |
| `saldo_pendiente` | decimal(12,2) | Cache de lo impago; la fuente de verdad es el ledger `movimientos_cuenta_corriente_proveedor` |
| `forma_pago` | varchar | `efectivo`, `debito`, `credito`, `transferencia`, `cheque`, `cta_cte` |
| `estado` | enum | `borrador`, `completada`, `cancelada` (D11 — ciclo de vida puro) |
| `observaciones` | text nullable | |
| `created_at`, `updated_at` | timestamp | `created_at` = momento de carga |

**Anti-duplicado** (validacion de aplicacion, no UNIQUE de BD): `(proveedor_id, tipo_comprobante, numero_comprobante_proveedor)` excluyendo `estado = 'cancelada'` — permite recargar la misma factura tras cancelarla, pero no cargarla dos veces activa.

#### Tabla: `compras_detalle`
Renglones de cada compra. `cantidad` queda SIEMPRE en unidades de STOCK (no cambia el codigo de stock existente); `cantidad_comprada` es la unidad del proveedor (ej. bultos).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `compra_id` | bigint FK | Compra |
| `articulo_id` | bigint FK | Articulo comprado |
| `codigo_proveedor_usado` | varchar(50) nullable | Trazabilidad: codigo del proveedor usado para encontrar el articulo en la carga |
| `cantidad_comprada` | decimal(12,3) nullable | Cantidad en la unidad de COMPRA (bultos) |
| `factor_conversion` | decimal(10,4) | Unidades de stock por unidad de compra (default 1) |
| `cantidad` | decimal(12,3) | Cantidad en unidades de STOCK = `cantidad_comprada × factor_conversion` |
| `precio_unitario` | decimal(12,2) | Precio de factura por unidad de COMPRA (neto si discrimina IVA, final si no) |
| `precio_sin_iva` | decimal(12,2) nullable | |
| `descuentos` | json nullable | Lista ordenada de % en cascada sobre el renglon (ej: `[10, 5, 3]`) |
| `descuento_monto` | decimal(12,2) | Total descontado por la cascada propia del renglon |
| `descuento_global_monto` | decimal(12,4) | Porcion prorrateada del descuento global del comprobante (RF-05) |
| `conceptos_costo_monto` | decimal(12,4) | Porcion prorrateada de los conceptos que computan costo (RF-15, landed cost) |
| `costo_unitario_computable` | decimal(12,4) nullable | Resultado final de la cadena de costo, por unidad de STOCK (ver formula en 3.8) |
| `tipo_iva_id` | bigint FK nullable | Tipo de IVA del renglon |
| `subtotal` | decimal(12,2) | Subtotal del renglon |
| `created_at`, `updated_at` | timestamp | Timestamps |

**D25 — `percepciones_costo_monto`**: NO es columna persistida (a diferencia de `conceptos_costo_monto`). Es una clave transitoria del array `$renglon` que `CompraService::resolverProrrateosYComputables()` arma en memoria (porcion prorrateada por importe de la parte no computable de las percepciones sufridas) y que `CostoService::costoComputableRenglon()` consume para sumarla al importe DESPUES del gross-up de IVA no recuperable, antes de dividir por `factor_conversion`. No queda rastro propio en `compras_detalle`; el efecto se ve reflejado en `costo_unitario_computable`.

#### Tabla: `compra_ivas` (espejo de `comprobante_fiscal_iva`)
**Fuente CANONICA del credito fiscal** (nunca la suma de renglones) y del Libro IVA Compras. Se pre-sugiere desde los renglones + conceptos gravados, y es editable para calzar con la factura fisica.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `compra_id` | bigint FK | Compra |
| `alicuota` | decimal(5,2) | 21.00 / 10.50 / 27.00 / 0.00 |
| `base_imponible` | decimal(12,2) | Neto gravado a esa alicuota |
| `importe` | decimal(12,2) | IVA de esa alicuota |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `compra_conceptos` (D9, pie de factura)
Renglones no-articulo del comprobante (flete, impuestos internos, envases, otros). En una compra con `es_servicio = true` (D23), esta tabla es el UNICO detalle de la compra (no hay `compras_detalle`): la UI la retitula "Detalle del servicio", la muestra siempre abierta y fuerza `computa_costo = false` en todos los renglones (sin renglones de articulo no hay prorrateo posible). Requiere al menos 1 fila con monto > 0 para poder confirmar una factura de servicio.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `compra_id` | bigint FK | Compra |
| `tipo` | enum | `flete`, `impuestos_internos`, `envases`, `otro` |
| `descripcion` | varchar(150) nullable | |
| `monto` | decimal(12,2) | Misma base que los renglones (neto si discrimina, final si no) |
| `tipo_iva_id` | bigint FK nullable | NULL = no gravado/exento. Alimenta la sugerencia de `compra_ivas` |
| `computa_costo` | boolean | true = se prorratea a los renglones por importe (landed cost). Ej: impuestos internos de bebidas SI son costo real |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `compra_percepciones`
Percepciones sufridas en la compra (impuesto del catalogo, base/alicuota/monto/coeficiente). Al confirmar se cablean a `ImpuestoService::registrarDesdeCompra()` (credito de IVA + percepciones → ledger fiscal). Con comprador no-RI, se cargan solo informativas (suman a la deuda, sin ledger fiscal en v1). D25: solo `monto × coeficiente` va al ledger fiscal (sentido `sufrido`); `monto × (1 − coeficiente)` prorratea al costo de los renglones (ver 3.8.2/3.8.8) o, en factura de servicio, queda implicito en el total que se le atribuye a la cuenta de compra.

#### Tabla: `cuentas_compra` (RF-22, D22)
Catalogo de agrupacion de GESTION (no plan de cuentas contable formal) para responder "¿cuanto gaste en que?" por periodo.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `nombre` | varchar(100) | Ej: Mercaderia, Insumos, Servicios, Gastos generales (seed inicial) |
| `orden` | int | Orden de visualizacion |
| `activo` | boolean | |
| `created_at`, `updated_at` | timestamp | Timestamps |

Configuracion: default por PROVEEDOR (`proveedores.cuenta_compra_id`) + override por COMPRA (`compras.cuenta_compra_id`); NULL = "sin clasificar" (los reportes lo muestran como categoria propia). Las NC heredan la cuenta de su compra origen.

#### Tabla: `configuracion_costos`
Una fila por comercio (singleton, `ConfiguracionCostos::obtener()` con `firstOrCreate`).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `utilidad_default` | decimal(6,2) | Markup % por defecto del comercio (default 30.00), base de la cascada de utilidad |
| `costo_rector` | enum | `ultimo`, `promedio`, `reposicion` — v1 fijo en `'ultimo'` (UI de solo lectura) |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `articulo_costos`
Costos del articulo, POR SUCURSAL + fila consolidada (`sucursal_id = NULL`, se actualiza con cada compra de cualquier sucursal). Tres costos en paralelo, uno rector (`costo_ultimo`, configurable a futuro).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | ON DELETE CASCADE |
| `sucursal_id` | bigint FK nullable | NULL = consolidado del comercio |
| `costo_ultimo` | decimal(12,4) nullable | Costo computable de la ultima compra confirmada ("¿cuanto me sale hoy?") |
| `costo_promedio` | decimal(12,4) nullable | PPP — costo promedio ponderado ("¿cuanto me costo lo que tengo?") |
| `costo_reposicion` | decimal(12,4) nullable | Manual; NULL ⇒ fallback a `costo_ultimo` |
| `proveedor_ultimo_id` | bigint FK nullable | Proveedor de origen del ultimo costo |
| `compra_ultima_id` | bigint FK nullable | Compra de origen del ultimo costo |
| `fecha_costo_ultimo` | timestamp nullable | |
| `created_at`, `updated_at` | timestamp | Timestamps |

**UNIQUE** `(articulo_id, sucursal_id)` — gotcha: en MySQL NULL admite N filas, asi que esto NO impide duplicar el consolidado; la unicidad la garantiza `CostoService` (unica puerta de escritura) con `firstOrCreate` + `lockForUpdate` en transaccion.

#### Tabla: `historial_costos`
Append-only, espejo de `historial_precios`. El PPP NO se historiza (reconstruible desde `movimientos_stock.costo`; una fila por compra seria ruido). El historial se registra por CADA compra confirmada aunque el costo no cambie (marcador de idempotencia + trazabilidad de que costo trajo cada compra).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | CASCADE |
| `sucursal_id` | bigint FK nullable | NULL = consolidado |
| `tipo_costo` | enum | `ultimo`, `reposicion` |
| `costo_anterior` | decimal(12,4) nullable | |
| `costo_nuevo` | decimal(12,4) | |
| `porcentaje_cambio` | decimal(8,2) nullable | |
| `origen` | enum | `compra`, `manual`, `importacion`, `cancelacion`, `masivo` (cambio masivo de costos, agregado por migracion `add_masivo_a_historial_costos_origen`, hardening-circuito-precios) |
| `compra_id` | bigint FK nullable | |
| `proveedor_id` | bigint FK nullable | |
| `usuario_id` | bigint nullable | Sin FK (users vive en `config`) |
| `detalle` | varchar(255) nullable | |
| `created_at` | timestamp | `UPDATED_AT = null` (tabla inmutable) |

#### Tabla: `articulo_proveedor` (RF-04)
N proveedores por articulo, con su codigo y costos propios.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK | CASCADE |
| `proveedor_id` | bigint FK | CASCADE |
| `codigo_proveedor` | varchar(50) nullable | Codigo del articulo en el catalogo del proveedor (busqueda en la carga de compras) |
| `factor_conversion` | decimal(10,4) | Unidades de stock por unidad de compra (default 1.0000, D8: bulto x12 ⇒ 12) |
| `descuentos_habituales` | json nullable | Lista ordenada de % que se precargan en el renglon (editables). Es el FALLBACK: el editor prioriza los descuentos de la ULTIMA compra completada al proveedor si existen (ver 3.8.6) |
| `costo_ultimo` | decimal(12,4) nullable | Ultimo costo computable de ESTE proveedor en particular |
| `fecha_ultima_compra` | timestamp nullable | |
| `activo` | boolean | |
| `created_at`, `updated_at` | timestamp | Timestamps |

**UNIQUE** `(articulo_id, proveedor_id)`; **KEY** `(proveedor_id, codigo_proveedor)` para la busqueda por codigo. Un mismo `codigo_proveedor` en 2+ articulos no se bloquea por UNIQUE (se resuelve con un selector en la busqueda).

#### Tabla: `proveedores` (extendida)
Proveedores del comercio. Un proveedor puede estar vinculado a un cliente (para cuentas corrientes cruzadas) y puede ser una sucursal interna (transferencias entre sucursales, fase futura).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(50) nullable | Codigo |
| `nombre` | varchar(191) | Nombre |
| `razon_social` | varchar(191) nullable | Razon social |
| `cuit` | varchar(20) nullable | CUIT |
| `email`, `telefono`, `direccion` | varchar nullable | Contacto |
| `condicion_iva_id` | int FK nullable | Condicion IVA (define la letra de comprobante sugerida junto con el CUIT comprador) |
| `es_sucursal_interna` | boolean | Si es una sucursal propia (para transferencias) |
| `sucursal_id` | bigint FK nullable | FK a sucursal si es interna |
| `cliente_id` | bigint FK nullable | FK a cliente vinculado |
| `cuenta_compra_id` | bigint FK nullable | Cuenta de compra default (RF-22), precarga la de la compra |
| `es_servicio` | boolean default false | D23: proveedor de servicios (ej. EDESUR). Al elegirlo en `EditorCompra::seleccionarProveedor()`, PISA `compras.es_servicio` con este flag (editable despues, igual que la letra sugerida) |
| `percepciones_habituales` | json nullable, cast array | D24: `[{impuesto_id, alicuota}]` — percepciones tipicas de este proveedor. Se gestiona desde el componente `Compras\ProveedorImpuestos` (modal propio, combobox de alta rapida sobre el catalogo de percepciones), NO desde un repetidor inline del ABM. Al elegirlo en una compra FISCAL, `EditorCompra::precargarPercepcionesHabituales()` las precarga como renglones de percepcion (impuesto + alicuota; D25: `coeficiente` tambien se precarga con el default del CUIT de la compra, y `sugerirMontosPercepciones()` calcula base/monto en modo auto — ver 3.8.8) solo si no hay percepciones ya cargadas por el usuario. No es calculo del monto: el importe exacto sale siempre de la factura fisica |
| `tiene_cuenta_corriente` | boolean | Habilita el circuito de cta cte y pagos a plazo (default false) |
| `dias_pago` | int nullable | Precarga `fecha_vencimiento` de las compras de este proveedor |
| `saldo_cache` | decimal(12,2) | Cache del saldo consolidado del comercio (patron Cliente, `lockForUpdate`) |
| `ultimo_movimiento_ccp_at` | timestamp nullable | |
| `activo` | boolean | Si esta activo |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `movimientos_cuenta_corriente_proveedor` (RF-18, espejo de `movimientos_cuenta_corriente`)
Ledger append-only. **Semantica de PASIVO** (invertida respecto de clientes): **HABER = aumenta la deuda** (compra), **DEBE = la reduce** (pago). Saldo = `Σhaber − Σdebe` sobre movimientos `activo`, on-the-fly (nunca se persiste en la fila).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `proveedor_id` | bigint FK | Proveedor |
| `sucursal_id` | bigint FK | Sucursal (la operatoria de pago es POR SUCURSAL ACTIVA, D19) |
| `fecha` | date | |
| `tipo` | enum | `compra`, `pago`, `anticipo`, `uso_saldo_favor`, `nota_credito`, `devolucion_saldo`, `anulacion_compra`, `anulacion_pago`, `ajuste_debito`, `ajuste_credito` |
| `debe` | decimal(12,2) | Reduce la deuda (pago, NC del proveedor) |
| `haber` | decimal(12,2) | Aumenta la deuda (compra) |
| `saldo_favor_debe` | decimal(12,2) | Consume saldo a favor nuestro |
| `saldo_favor_haber` | decimal(12,2) | Genera saldo a favor nuestro (anticipo) |
| `documento_tipo` | enum | `compra`, `pago`, `pago_compra`, `ajuste` |
| `documento_id` | bigint | Polimorfico |
| `compra_id` | bigint FK nullable | SET NULL |
| `pago_proveedor_id` | bigint FK nullable | SET NULL |
| `concepto` | varchar(255) | |
| `observaciones` | text nullable | Motivo de anulacion |
| `estado` | enum | `activo`, `anulado` (contraasientos: AMBOS quedan `activo` y se cancelan matematicamente) |
| `anulado_por_movimiento_id` | bigint nullable (self-FK) | SET NULL |
| `usuario_id` | bigint | Sin FK (users en `config`) |
| `created_at`, `updated_at` | timestamp | Timestamps |

Sin columnas de snapshot de moneda extranjera en v1.

#### Tabla: `pagos_proveedores` (RF-19, analogo de `cobros`)

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `numero` | varchar(20) | `OP-{suc}-{8 dig}` (patron `generarNumeroRecibo`) |
| `proveedor_id` | bigint FK | |
| `sucursal_id` | bigint FK | |
| `caja_id` | bigint FK nullable | |
| `fecha` | date | |
| `monto_total` | decimal(12,2) | |
| `saldo_favor_usado` | decimal(12,2) | |
| `monto_a_favor` | decimal(12,2) | Excedente → anticipo |
| `tipo` | enum | `pago`, `anticipo` |
| `observaciones` | text nullable | |
| `estado` | enum | `activo`, `anulado` |
| `motivo_anulacion` | varchar(255) nullable | |
| `anulado_por_usuario_id` | bigint FK nullable | |
| `anulado_at` | timestamp nullable | |
| `cierre_turno_id` | bigint FK nullable | |
| `usuario_id` | bigint | |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `pago_proveedor_compras` (analogo de `cobro_ventas`)
Pivot: aplicacion de un pago a una o mas compras (FIFO o manual, parcial).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pago_proveedor_id` | bigint FK | CASCADE |
| `compra_id` | bigint FK | |
| `monto_aplicado` | decimal(12,2) | Baja `compras.saldo_pendiente` |
| `saldo_anterior`, `saldo_posterior` | decimal(12,2) | Snapshot de auditoria |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `pago_proveedor_pagos` (desglose de FP, analogo de `cobro_pagos`)
Espejo fiel de `cobro_pagos` (mismas FKs de movimiento/estado/cierre_turno por renglon — sin eso la anulacion por origen y D16 no serian implementables).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `pago_proveedor_id` | bigint FK | CASCADE |
| `forma_pago_id` | bigint FK | |
| `monto` | decimal(12,2) | |
| `origen` | enum | `caja`, `tesoreria`, `cuenta_empresa` (D14: de donde salen los fondos) |
| `caja_id` | bigint FK nullable | Origen 'caja'; default la caja activa |
| `cuenta_empresa_id` | bigint FK nullable | Origen 'cuenta_empresa' |
| `movimiento_caja_id` | bigint FK nullable | Para contraasentar exacto al anular |
| `movimiento_cuenta_empresa_id` | bigint FK nullable | Idem, origen cuenta |
| `movimiento_tesoreria_id` | bigint FK nullable | Idem, origen tesoreria |
| `cierre_turno_id` | bigint FK nullable | POR RENGLON (D16: turno cerrado bloquea solo renglones con origen 'caja') |
| `estado` | enum | `activo`, `anulado` |
| `created_at`, `updated_at` | timestamp | Timestamps |

Origenes distintos de `'caja'` requieren el permiso `func.compras.pagar_avanzado`. El origen `'tesoreria'` usa la Tesoreria de la sucursal del pago (sin FK de cuenta especifica).

#### Services
- **CostoService** (`app/Services/CostoService.php`): unica puerta de escritura de costos. `costoComputableRenglon()`, `registrarDesdeCompra()`, `revertirCostoUltimoSiCorresponde()`, `actualizarManual()`, `utilidadObjetivo()`, `margenReal()`, `precioSugerido()`, `alicuotaEfectiva()`, `costoRector()`, `prorratearPorImporte()`.
- **CompraService** (`app/Services/CompraService.php`, reescrito): `crearBorrador()`/`actualizarBorrador()`/`eliminarBorrador()`, `confirmarCompra()` (transaccion unica: prorrateos → costo → stock → `CostoService` → `ImpuestoService` → `CuentaCorrienteProveedorService` → pago inicial → repricing automatico), `cancelarCompra()`, `corregirCompra()` (cancelar+recrear atomico), `sugerirTipoComprobante()`, `advertenciaComprobanteCuit()`, `esComprobanteDuplicado()`.
- **CuentaCorrienteProveedorService** (`app/Services/CuentaCorrienteProveedorService.php`, espejo de `CuentaCorrienteService`): `registrarMovimientosCompra()`, `registrarMovimientosPago()`, `anularMovimientosCompra()`/`anularMovimientosPago()`, `obtenerExtracto()`/`obtenerExtractoResumido()`, `obtenerSaldos()`, `obtenerComprasPendientes()` (FIFO por `fecha_vencimiento`), `actualizarCacheProveedor()`.
- **PagoProveedorService** (`app/Services/PagoProveedorService.php`, espejo de `CobroService`): `registrarPago()`, `registrarAnticipo()`, `anularPago()`, `distribuirMontoFIFO()`, `generarNumeroOrdenPago()`. Egresa segun origen: `MovimientoCaja::crearEgresoPagoProveedor()`, `TesoreriaService::registrarEgresoExterno()` o `CuentaEmpresaService::registrarMovimientoAutomatico()`.

### 2.10 Configuracion y Precios

#### Tabla: `sucursales`
Ya descrita en seccion 1.2. Campos adicionales relevantes:
- `control_stock_venta` -- Modo de control de stock al vender: `bloquea`, `advierte`, `no_controla`
- `control_stock_produccion` -- Idem para produccion
- `facturacion_fiscal_automatica` -- Si emite factura automaticamente
- `agrupa_articulos_venta` -- Si agrupa articulos repetidos en pantalla de venta
- `agrupa_articulos_impresion` -- Si agrupa en impresion
- `latitud` -- `decimal(10,7)` nullable. Coordenada geografica. Requerida por MP para crear Store. Se puede fijar via picker de Google Maps (ver trait `ManejaDomicilio`).
- `longitud` -- `decimal(10,7)` nullable. Idem latitud.
- `localidad` -- `varchar(100)` nullable. String legado que MP consume directamente como `city_name` al crear/actualizar la Store. Se mantiene sincronizado con el nombre de la localidad del catalogo (`localidad_id`) al guardar desde cualquier formulario que use el trait `ManejaDomicilio` (tanto `ConfiguracionEmpresa::guardarSucursal()` como `IntegracionesPago::guardarDireccion()`): ambos resuelven `Localidad::find($datos['localidad_id'])?->nombre` y lo escriben aqui.
- `localidad_id` -- `bigint` nullable. Ref soft a `localidades` (config) para domicilio fisico estructurado (Fase 9, RF-11). Sin FK cross-DB. Relacion Eloquent `sucursal->localidad()` disponible. Al cambiar `localidad_id`, el trait `ManejaDomicilio` resuelve el centro geografico de esa localidad y lo expone como `$domLocalidadCentro` para acotar el mapa.
- `provincia` -- `varchar(100)` nullable. Codigo ISO 3166-2 de provincia argentina (ej: `AR-B`, `AR-C`). Se traduce a nombre oficial al armar payloads externos. Ver `Sucursal::PROVINCIAS_AR[]` y `Sucursal::provinciaNombre()`.
- `mp_store_id` -- `varchar(50)` nullable. ID numerico devuelto por MP al crear la Store. Se usa para actualizar/eliminar. Indexado.
- `mp_store_external_id` -- `varchar(60)` nullable. Identificador externo en MP. Formato: `BCN-{comercio_id}-{sucursal_id}`. Unico (UNIQUE INDEX). Solo se envia al **crear** la store; en updates MP lo rechaza por colision consigo mismo.
- `config_pantalla_cliente` -- `json` nullable, cast `array`. Configuracion de personalizacion de la pantalla orientada al cliente. Se mergea con `Sucursal::CONFIG_PANTALLA_CLIENTE_DEFAULTS` antes de usarse. Ver helpers en el modelo `Sucursal`.
- `token_publico` -- `varchar(40)` nullable UNIQUE. Copia del token largo en la tabla tenant. La resolucion sin sesion usa el indice global `pantalla_publica_tokens`; esta columna es para la UI de configuracion (no se usa en el middleware). Se genera en la migracion y se mantiene sincronizado con el indice via `PantallaPublicaService`.
- `config_llamador` -- `json` nullable, cast `array`. Personalizacion del monitor llamador. Se mergea con `Sucursal::CONFIG_LLAMADOR_DEFAULTS`.
- `config_consultor_precios` -- `json` nullable, cast `array`. Personalizacion del consultor de precios. Se mergea con `Sucursal::CONFIG_CONSULTOR_PRECIOS_DEFAULTS`.
- `usa_llamador` -- `boolean` default `false`. Si el monitor llamador esta activo. Cuando es `false`, `PedidoMostradorService` no emite `PedidoLlamadorPublicoBroadcast` al cambiar estados.
- `usa_consultor_precios` -- `boolean` default `false`. Si el consultor de precios esta activo. Cuando es `false`, el endpoint `/clase-b/precios/{token}/buscar` retorna 404.
- `usa_numeracion_display` -- `boolean` default `false`. Si la sucursal asigna un numero de turno (display) aparte del correlativo permanente.
- `numeracion_display_modo` -- `enum('diario','manual')` default `'diario'`. `diario` = reset automatico a las horas configuradas; `manual` = solo al presionar el boton.
- `numeracion_display_horas` -- `json` nullable, cast `array`. Lista de horas de reset diario (0-23). Si null o vacio, el helper `horasResetDisplay()` asume `[6]` (reset a las 06:00). Ej: `[6, 18]` para turno manana y tarde.
- `pedido_display_ultimo_numero` -- `int unsigned` default 0. Contador atomico del numero de display del segmento actual. Se incrementa con `DB::connection('pymes_tenant')->statement("UPDATE ... SET pedido_display_ultimo_numero = pedido_display_ultimo_numero + 1 ...")` via lock de fila para serializar accesos concurrentes.
- `pedido_display_segmento_at` -- `datetime` nullable, cast `datetime`. Inicio del segmento (jornada/turno) actual del contador display. Se usa para determinar si corresponde reiniciar el contador en modo diario.

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

**Defaults de `config_llamador`** (`Sucursal::CONFIG_LLAMADOR_DEFAULTS`):

| Clave | Tipo / Valores | Default |
|---|---|---|
| `titulo` | string | `"Pedidos"` |
| `mostrar_logo` | bool | `true` |
| `color_fondo` | hex string | `#0f172a` |
| `color_preparacion` | hex string | `#f59e0b` (ambar, columna "En preparacion") |
| `color_listo` | hex string | `#22c55e` (verde, columna "Listo / Retirar") |
| `sonido` | bool | `true` (chime al pasar a "Listo") |
| `tamano` | `compacto` / `normal` / `grande` | `normal` (densidad base; el auto-fit achica si no entran) |

**Defaults de `config_consultor_precios`** (`Sucursal::CONFIG_CONSULTOR_PRECIOS_DEFAULTS`):

| Clave | Tipo / Valores | Default |
|---|---|---|
| `titulo` | string | `"Consultá tu precio"` |
| `mostrar_logo` | bool | `true` |
| `color_fondo` | hex string | `#0f172a` |
| `color_acento` | hex string | `#22d3ee` (cian, precio destacado) |
| `mensaje_idle` | string | `"Escanee un artículo"` |
| `duracion_resultado` | int (segundos) | `5` |

**Helpers en el modelo `Sucursal`**:
- `getConfigPantallaCliente(): array` — merge de `config_pantalla_cliente` (DB) con `CONFIG_PANTALLA_CLIENTE_DEFAULTS`. Garantiza que nunca falten claves aunque la columna este NULL o incompleta.
- `getConfigLlamador(): array` — merge de `config_llamador` (DB) con `CONFIG_LLAMADOR_DEFAULTS`.
- `getConfigConsultorPrecios(): array` — merge de `config_consultor_precios` (DB) con `CONFIG_CONSULTOR_PRECIOS_DEFAULTS`.
- `horasResetDisplay(): array` — lista de horas de reset diario (0-23), ordenadas y sin duplicados. Fallback `[6]` si la columna es null/vacia.
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
| `orden` | int | Orden de visualizacion. (RF-T18) La API publica de tienda tambien ordena las formas de pago declarables por este campo (antes por nombre) |
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
| `disponible_en_tienda` | boolean, default true | (RF-T18) Si la FP se ofrece a los clientes de la TIENDA online en esta sucursal. Independiente de `activo` (una FP puede estar activa en el punto de venta pero oculta en la tienda). Solo aplica a FP no mixtas — el modal de Formas de Pago no muestra esta seccion para mixtas. `FormaPago::estaDisponibleEnTiendaEnSucursal()` exige `activo=true` Y `disponible_en_tienda=true`; `FormaPago::esDeclarableEnTienda(sucursalId)` suma ese chequeo y es la puerta unica que usan `GET /tiendas/{slug}`, `carrito/cotizar` y el alta de pedidos (RF-T18 F1). Ver "Multi-pago en la tienda" mas abajo (RF-T18 F2) para el pago con hasta 2 FP |

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
| `modo_default` | varchar(50) nullable | Modo de cobro de la integracion (`qr_dinamico`, `qr_estatico`, `qr_libre`, `point`). Una FP usa un unico modo; este campo es la fuente de verdad |
| `modos_permitidos` | json nullable | Array json conservado por compatibilidad de esquema; siempre se persiste como `[modo_default]` (espejo de un solo elemento) |
| `es_principal` | boolean | Si es la integracion preseleccionada cuando la FP tiene varias |
| `config_point` | json nullable | Configuracion especifica del modo Point: `{"default_type":"credit_card"|"debit_card"|"qr"}`. Ausente o null = "Abierto": no se envia `default_type` a MP y el cliente elige el medio en el aparato. Extensible a futuros parametros Point. |
| `config_qr_libre` | json nullable | Configuracion del modo `qr_libre`: `{"imagen_path": "integraciones/qr_libre/{comercioId}/{uuid}.webp", "imagen_url": "/storage/..."}`. NULL para los otros modos. La URL publica se reconstruye siempre a partir del `imagen_path` via `ImagenQrLibreService::urlPublica()` (root-relativa, portable entre hosts). |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indices**: UNIQUE `(forma_pago_id, integracion_pago_id)`.

**Relacion en FormaPago**: `integraciones()` — `BelongsToMany` con `withPivot(['modo_default','modos_permitidos','es_principal','config_point','config_qr_libre'])`.

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
- **`comprobantes_fiscales`**: Comprobantes emitidos ante AFIP. Tipos: factura_a/b/c/e/m, nota_credito_a/b/c/e/m, nota_debito_a/b/c/e/m, recibo_a/b/c. Incluye CAE, datos del receptor, totales desglosados por IVA. Campo `tributos` (decimal): suma total de percepciones aplicadas informadas en `ImpTrib` de AFIP.
- **`comprobante_fiscal_ventas`**: Pivot comprobante-venta (un comprobante puede cubrir una o mas ventas).
- **`comprobante_fiscal_iva`**: Desglose de IVA por alicuota dentro de un comprobante.
- **`comprobante_fiscal_items`**: Items detallados del comprobante fiscal.
- **`comprobante_fiscal_tributos`**: Desglose de percepciones aplicadas dentro de un comprobante (Fase 5b). Una fila por impuesto percibido. Campos: `comprobante_fiscal_id`, `impuesto_id`, `base_imponible`, `alicuota`, `monto`, `codigo_arca` (codigo ARCA para el array `Tributos[]` de AFIP). Paralelo a `compra_percepciones` del lado de compras. La relacion desde `ComprobanteFiscal` es `tributosDetalle`.
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
| `numero` | int unsigned nullable | Numero correlativo permanente por sucursal. NULL en borradores. |
| `numero_display` | int unsigned nullable | Numero de turno amigable mostrado en monitor/comanda/kanban. NULL = la sucursal no usa numeracion de display (cae al `numero` permanente). Se asigna en `PedidoMostradorService::siguienteNumeroDisplay()` al confirmar. |
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
- `numero_visible`: devuelve `numero_display ?? numero`. Es el numero que se muestra cara-al-publico (monitor llamador, comanda, kanban). Implementado como accessor PHP: `getNumeroVisibleAttribute(): ?int`.

**Metodo de negocio**:
- `nombreLlamador(): ?string` -- nombre para el monitor llamador publico: SOLO el primer nombre del cliente (nunca apellido) para no sobre-exponer datos en una pantalla publica. Usa `nombre_cliente_final`; si esta vacio, retorna `null`.

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

- **`sucursales`**: `pedido_mostrador_activo` (boolean, habilita el modulo por sucursal), `imprime_comanda_automatico` (boolean default 1, si es true al confirmar un pedido se llama `comandarPedido($pedido, 'todos')` automaticamente -- marca todos los detalles con `comandado_at = now()` y avanza el estado a `en_preparacion`), `pedido_mostrador_ultimo_numero` (int unsigned, contador atomico del numero correlativo). Columnas agregadas en Clase B: `usa_llamador`, `usa_numeracion_display`, `numeracion_display_modo`, `numeracion_display_horas`, `pedido_display_ultimo_numero`, `pedido_display_segmento_at` (ver seccion 2.10).
- **`pedidos_mostrador`**: `numero_display` (int unsigned nullable, numero de turno amigable mostrado publicamente; NULL si la sucursal no usa numeracion de display).

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
- **Fix cortesia total + concepto libre (hardening fiscal tanda 2, RF-V8)**: `crearDetalleVenta()` tenia dos ramas — "datos proporcionados" (usa los montos que ya vienen calculados desde la UI) y un modo legacy por linea que exigia `_usar_totales_proporcionados=true` para conceptos libres, flag que `NuevaVenta::procesarVenta()` (unico caller del path legacy) no pasaba. Una venta 100% cortesia que incluia un item de concepto libre explotaba con "Concepto libre requiere _usar_totales_proporcionados=true". Fix: un item con `es_concepto=true` siempre usa la rama de datos proporcionados (sus datos ya vienen completos desde la UI), sin depender del flag. El modo legacy por linea solo se ejercita hoy con montos $0 (cortesia).

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

#### Service: `ReporteCortesiasService`

`app/Services/ReporteCortesiasService.php`

Service de **solo lectura** (no abre transacciones ni muta datos). Genera el reporte de cortesias sobre ventas para una sucursal y rango de fechas. Fuente canonica: `ventas_detalle` con `es_invitacion = true`; excluye ventas anuladas (`anulado_at IS NOT NULL`). Opera exclusivamente sobre la tabla `ventas` (no `pedidos_mostrador`): las cortesias de pedidos ya convertidos quedan propagadas a `ventas_detalle`, evitando duplicar el conteo.

**Metodo publico:**

- `generar(int $sucursalId, Carbon $desde, Carbon $hasta): array` — Retorna un array con cuatro claves: `kpis`, `por_usuario`, `por_articulo`, `detalle`.

**Metodos privados (internos):**

- `obtenerRenglonesInvitados(sucursalId, desde, hasta): Collection` — Consulta `ventas_detalle` con `es_invitacion = true`, eager-loads `venta` y `articulo`, filtra por sucursal, rango de fechas y `ventas.anulado_at IS NULL`. Ordena por fecha de la venta.
- `calcularKpis(Collection): array` — Calcula `monto_total` (suma de `monto_invitado`), `cantidad_renglones`, `cantidad_comprobantes` (ventas unicas) y `cantidad_articulos` (suma de `cantidad`).
- `agruparPorUsuario(Collection): array` — Agrupa por `invitado_por_usuario_id`, resuelve nombres en una sola consulta a `config.users`, ordena por `monto` desc. Campos por fila: `usuario_id`, `usuario`, `monto`, `renglones`, `comprobantes`. Usuarios eliminados aparecen como "Usuario eliminado"; renglones sin usuario como "Sin usuario".
- `agruparPorArticulo(Collection): array` — Agrupa por `articulo_id`, ordena por `monto` desc. Campos: `articulo_id`, `articulo`, `cantidad`, `monto`, `renglones`. Renglones sin `articulo_id` (conceptos libres) se agrupan bajo "Concepto libre".
- `armarDetalle(Collection): array` — Listado plano renglon a renglon. Campos: `fecha` (formato `d/m/Y H:i`), `comprobante`, `articulo` (via `VentaDetalle::obtenerNombre()`), `cantidad`, `monto`, `motivo`, `usuario`.
- `resolverNombresUsuarios(Collection $ids): Collection` — Una sola query `User::whereIn('id', $ids)->pluck('name', 'id')` a la BD `config` para resolver todos los nombres a la vez.

**Reglas de negocio:**

- El monto canonico regalado por linea es `ventas_detalle.monto_invitado` (cache precalculado = `cantidad * precio_unitario_original`). No se recomputan precios.
- Las ventas `anulado_at IS NOT NULL` se excluyen en la clausula `whereHas` sobre `venta`.
- La fuente es siempre `ventas_detalle` (no `pedidos_mostrador_detalle`); las cortesias de pedidos convertidos quedan propagadas a ventas por `mapearDetalleAArrayVenta()` en el service de pedidos.
- El reporte esta acotado a la sucursal activa del operador; no hay vista consolidada multi-sucursal.

**Componente Livewire:** `App\Livewire\Ventas\ReportesVentas` (ruta `ventas.reportes`). Usa `SucursalAware`; al cambiar sucursal limpia `$resultado`. El tipo de reporte es `'cortesias'` por defecto; el selector `$tipoReporte` esta preparado para futuros reportes. Gate de permiso: `func.ver_reportes_ventas` (verificado en `mount()`).

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
- **Ordenamiento dinamico**: propiedades `$sortField` (string, default `'fecha'`) y `$sortDirection` (string, `'asc'|'desc'`, default `'desc'`). Campos permitidos: `numero`, `cliente`, `fecha`, `total_final`, `estado_pedido`, `estado_pago`. Los estados se ordenan por orden logico del flujo via `FIELD()` SQL, no alfabeticamente. El campo `cliente` ordena por nombre efectivo (subquery correlacionada sobre la tabla con prefijo tenant para comparar con `nombre_cliente_temporal` usando `COALESCE`).
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
- `sortBy(string $field): void` -- cambia el campo de orden. Si `$field` es el mismo que `$sortField`, invierte `$sortDirection`; si es distinto, setea el nuevo campo y resetea a `'asc'`. Solo acepta campos en el array de permitidos (`numero`, `cliente`, `fecha`, `total_final`, `estado_pedido`, `estado_pago`); ignora silenciosamente valores invalidos. Llama `resetPage()`.
- `toggleSortDirection(): void` -- invierte `$sortDirection` sin cambiar el campo. Lo usa el control de orden mobile (selector de campo + boton de direccion). Llama `resetPage()`.
- `aplicarOrden($query): void` -- metodo protegido. Aplica `ORDER BY` dinamico segun `$sortField`/`$sortDirection`. `numero` y `total_final`: `orderBy` simple. `estado_pedido` y `estado_pago`: `orderByRaw(FIELD(...))` con los valores de `PedidoMostrador::ESTADOS` / `ESTADOS_PAGO` para orden logico. `cliente`: `orderByRaw(COALESCE(subquery_nombre_catalogo, nombre_cliente_temporal))`. `fecha` (default): `orderBy('fecha', $dir)`. Tiebreak: siempre `orderByDesc('id')` al final.
- `fieldOrderSql(string $columna, array $valores): string` -- metodo protegido. Construye la expresion `FIELD(columna, 'v1','v2',...)` para ordenar por orden logico de un enum. Los valores se citan via `PDO::quote()` sobre la conexion `pymes_tenant`.

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

Los badges de `estado_pedido` usan color solido y vibrante (alta saturacion) para lectura rapida de un vistazo. Los badges de `estado_pago` usan tinte suave (baja saturacion) para diferenciarse visualmente de los de estado pedido. Ambos usan tamano `text-sm`.

**Header compacto (una sola fila, h-9)**:
El header ya no contiene titulo ni descripcion del modulo. Contiene en una sola fila: contador de pedidos, badge de nuevos, boton de borradores (condicional), chips removibles de filtros activos, search inline (md+), **selector `filterEstadoPedido`** (siempre visible), **selector `filterEstadoPago`** (siempre visible), boton filtros (abre panel colapsable con solo el rango de fechas), boton refrescar con estado loading (`wire:loading.remove` / `wire:loading`), toggle lista/kanban y boton nuevo.

**Boton borradores (Alpine puro, sin round-trip)**:
Reemplaza el desplegable Livewire previo. El boton aparece condicionalmente si `$borradores->count() > 0`. Click activa `mostrarBorradoresPanel = true`; `@click.outside` y `@keydown.escape.window` lo cierran. Las propiedades Livewire `$mostrarBorradores` y el metodo `toggleBorradores()` fueron eliminados del componente PHP.

**Chips de filtros activos**:
Por cada filtro con valor distinto al default (busqueda), el header renderiza un chip con un boton de cierre. Click en el boton del chip hace `wire:click` que limpia la propiedad Livewire correspondiente (`search = ''`). Los filtros de estado pedido y estado pago tienen sus propios selectores siempre visibles en la barra; no generan chips separados.

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

**Constante**:
- `NOMBRE_CLIENTE_DEFAULT = 'Consumidor final'` -- nombre temporal que se persiste cuando no se elige cliente del catalogo ni se ingresa nombre temporal. No usa `__()` porque es un dato persistido, no un label de UI.

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

**Logica de cliente**: el cliente es opcional. Si `cliente_id` es null y `nombreClienteTemporal` esta vacio, el service persiste `NOMBRE_CLIENTE_DEFAULT` (`'Consumidor final'`) en `nombre_cliente_temporal`. El campo `telefonoClienteTemporal` tampoco es obligatorio. El boton "Dar de alta como cliente" fue eliminado de la seccion de datos temporales; el alta rapida sigue disponible exclusivamente desde el buscador de cliente (boton "+").

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

**Atajos de teclado del editor** (registrados en `@keydown.window` del wrapper Alpine del modo editor):

| Atajo | Accion | Guard |
|-------|--------|-------|
| `F2` | `$wire.confirmarPedido()` | No se dispara si hay modal secundario abierto |
| `F3` | `$wire.confirmarSinCobrar()` | Idem |
| `F4` | `$wire.abrirModalDescuentos()` | Idem |
| `Ctrl+G` | `$wire.guardarBorrador()` | Solo en alta o cuando `estadoPedidoActual === 'borrador'` |
| `Ctrl+1` | `$dispatch('focus-busqueda')` | Sin guard |
| `Ctrl+6` | `$dispatch('focus-cliente')` | Sin guard |
| `Esc` | `$wire.cerrar()` | `@keydown.escape.window` |

Los modales secundarios que bloquean F2/F3/F4/Ctrl+G son: `$mostrarModalPago`, `$mostrarModalMonedaExtranjera`, `$mostrarModalVuelto`, `$mostrarModalEsperandoPago`, `$showModalDescuentos`, `$mostrarModalConcepto`, `$mostrarConfirmLimpiar`.

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
| `modo_usado` | varchar(50) | Modo de cobro (`qr_dinamico`, `qr_estatico`, `qr_libre`) |
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
  - `iniciarCobro(IntegracionPagoSucursal $config, array $datos, ?Model $cobrable = null): IntegracionPagoTransaccion` — Crea la transaccion en `pendiente`, aplica la `metadata` inicial opcional (`$datos['metadata']`) si se provee, llama al gateway para obtener el QR/datos de cobro (FUERA de la transaccion DB para no mantener locks durante la latencia de red) y persiste `qr_data`, `external_id`, etc. La `metadata` la lee el gateway al iniciar: el modo `point` la usa para `['point' => ['default_type' => 'credit_card', 'installments' => 3]]`; el modo `qr_libre` para `qr_libre_imagen_url`.
  - `consultarEstado(IntegracionPagoTransaccion $transaccion): string` — Consulta el estado en el proveedor. Devuelve `'pendiente'|'aprobado'|'cancelado'|'expirado'` sin mutar la transaccion.
  - `confirmarCobro(IntegracionPagoTransaccion $transaccion, ?Model $cobrable = null, array $payload = []): void` — Marca como `confirmado`, registra `confirmado_en`, asocia el cobrable si se provee. Idempotente. Al confirmar exitosamente (primera vez), invoca `registrarMovimientoCuentaEmpresa()` fuera de la transaccion DB (el movimiento no rollbackea con ella).
  - `asociarCobrable(IntegracionPagoTransaccion $transaccion, Model $cobrable): void` — Asocia el cobrable a una transaccion ya confirmada. Necesario en el modelo "cobro primero, venta despues": el pago se confirma cuando el cliente escanea el QR, pero el comprobante se crea despues. Idempotente.
  - `cancelarCobro(IntegracionPagoTransaccion $transaccion): bool` — Avisa al proveedor y marca como `cancelado`. Si el gateway falla al cancelar, la transaccion se cancela localmente igual y se loguea el error. Idempotente.
  - `confirmarManual(IntegracionPagoTransaccion $transaccion, ?int $usuarioId = null, ?string $motivo = null): void` — (Fase 8, RF-12) Marca la transaccion con estado `confirmado_manual` (distinto de `confirmado` para diferenciarlo en reportes y conciliacion) y registra en `integraciones_pago_eventos` quien la confirmo (`usuario_id` en `metadata`). El cobrable se materializa igual que en el camino automatico (el concern llama `alConfirmarCobroIntegracion()`). Idempotente: si la transaccion ya esta en estado terminal, no hace nada. Es el unico camino de cierre para el modo `qr_libre`. Al confirmar exitosamente, invoca `registrarMovimientoCuentaEmpresa()` con el `$usuarioId` del confirmador.
  - `expirarPendientesVencidas(): int` — (Fase 8, RF-16) Obtiene todas las transacciones en scope `vencidas()` del tenant activo, las marca como `expirado`, registra el evento `expirado` en el ledger y broadcastea `IntegracionPagoActualizado` con `estado = 'expirado'` por cada una para que el modal del cajero cierre solo. Bajo el modelo "cobro primero" no hay cobrable que anular. Retorna la cantidad de transacciones expiradas.
  - `registrarMovimientoCuentaEmpresa(IntegracionPagoTransaccion $transaccion, ?int $usuarioId = null): void` — (privado, Paso 2) Registra el ingreso en la cuenta real del proveedor al confirmarse el cobro. Solo en produccion. Idempotente por origen polimórfico (`IntegracionPagoTransaccion`, `transaccion->id`): si ya existe un movimiento con ese origen no crea uno nuevo (webhook + polling + confirmacion manual convergen a 1 solo movimiento). Resolucion de cuenta: (1) `CuentaEmpresaService::findOrCreateParaIntegracion($config)` segun la config de la sucursal de la transaccion (D7); (2) fallback: `transaccion->formaPago->cuenta_empresa_id` si la identidad es null; (3) si tampoco existe, no registra nada. Concepto: `cobro_integracion`. Nunca rompe la confirmacion: cualquier excepcion queda en log warning.
- **`MercadoPagoGateway`** (actualizado con soporte Point + qr_libre): Implementa `IntegracionPagoGatewayContract`. Metodos de sincronizacion (QR): `crearStore`, `actualizarStore`, `eliminarStore`, `crearPos`, `actualizarPos`, `eliminarPos`. Metodos de cobro: `iniciarCobro` (gateway public; delega a rama segun `transaccion->modo_usado`): si el modo es `point` llama al metodo privado `iniciarCobroPoint`; si es `qr_libre` llama al metodo privado `iniciarCobroQrLibre()` (no llama a la Orders API, retorna `qr_image_url` desde `metadata['qr_libre_imagen_url']` y `external_id = null`); de lo contrario usa la rama QR existente con `mapearModoOrdersApi()` (`dynamic`/`static`). Metodo privado `iniciarCobroPoint`: hace `POST /v1/orders` con `type:"point"`, `config.point.terminal_id = caja->mp_point_terminal_id`, `config.payment_method.default_type` (si la FP definio un medio; null = Abierto), `config.payment_method.default_installments` (solo credit_card), `expiration_time` ISO 8601 acotado a PT30S..PT3H. Devuelve `qr_data = null` (el QR no se renderiza en pantalla; el aparato lo muestra). `consultarEstado` — polling del order (no-op efectivo para `qr_libre` ya que `external_id = null`). `cancelarCobro`: para modo `point` agrega el header `x-allow-cancelable-status: at_terminal` para permitir cancelar la order mientras esta "en terminal" (estado que MP llama `at_terminal`). En modo `dynamic` MP devuelve `qr_data`; en `static` y `qr_libre` se usa `qr_image_url`. Metodos Point publicos: `listarTerminales(config)` — `GET /terminals/v1/list` (hasta 50 terminales); `activarModoPDV(config, terminalId)` — `PATCH /terminals/v1/setup` con `operating_mode: "PDV"` (requisito previo para poder empujar cobros al device). El webhook es identico para QR y Point (mismo topico "orders"; se distingue la transaccion por `external_id`). Constantes: `MODO_QR_DINAMICO`, `MODO_QR_ESTATICO`, `MODO_QR_LIBRE`, `MODO_POINT`.
- **`MercadoPagoWebhookService`**: Procesa notificaciones entrantes de MP. Contiene un guard explicito para el modo `qr_libre`: si la transaccion encontrada tiene `modo_usado = 'qr_libre'`, el webhook retorna inmediatamente con `status = 'ignored'` sin re-consultar ni confirmar. Razon: el QR "Cobrar" de MP es un QR estatico generico de la cuenta (no lleva el `external_id` de ninguna order nuestra), por lo que el pago real nunca puede matchear por `order_id`. La confirmacion es exclusivamente manual via `confirmarManual()`.
- **`ImagenQrLibreService`**: Procesa el upload de la imagen del QR "Cobrar" de Mercado Pago para el modo `qr_libre`. Defensas de seguridad: validacion de tamano (max 4 MB), deteccion de MIME real por magic bytes via `finfo` (no por extension ni Content-Type), whitelist JPG/PNG/WebP (SVG prohibido), re-encoding completo a WebP con Intervention Image/GD (elimina EXIF y payloads embebidos), nombre por UUID (sin path traversal), path scopeado por `comercioId`. NO redimensiona agresivamente (el QR debe quedar nitido y escaneable): solo achica si supera 1000px, con calidad WebP 90. Metodo estatico `urlPublica(?string $path): ?string` — deriva la URL root-relativa `/storage/{path}` a partir del path del disco publico (no usa `APP_URL`, portable entre hosts). Almacenamiento: disk `public`, ruta `integraciones/qr_libre/{comercioId}/{uuid}.webp`.
- **`SincronizacionMercadoPagoService`**: Orquesta crear-vs-actualizar para QR (Store/POS) y para Point (terminales/devices). Decide segun `mp_store_id` / `mp_pos_id`. Persiste IDs y URLs devueltos en una transaccion tenant. Metodos para Point: `listarTerminales(config)` — delega en el gateway; `vincularTerminalCaja(config, caja, terminalId)` — llama a `activarModoPDV` en el gateway y persiste `mp_point_terminal_id` en la caja en una transaccion tenant; `desvincularTerminalCaja(caja)` — limpia `mp_point_terminal_id` localmente (no toca el modo del device en MP).
- **`IntegracionPagoSucursalService`**: CRUD de configuraciones. Al cambiar `modo` o `user_id_externo` limpia los IDs de MP locales (Store + todos sus POS) via `limpiarSincronizacionMp()`. Al guardar credenciales en modo `produccion`, invoca `CuentaEmpresaService::findOrCreateParaIntegracion()` para crear o reutilizar la `CuentaEmpresa` que representa la cuenta real del proveedor (RF-02 del spec de vinculo).
- **`CuentaEmpresaService::findOrCreateParaIntegracion(IntegracionPagoSucursal $config): ?CuentaEmpresa`** (nuevo): resuelve la cuenta real del proveedor para una config de integracion. Solo aplica en modo `produccion`; no-op en `test`. Pide la identidad al gateway via `identidadCuentaEmpresa()` (unico codigo por-proveedor). Lookup en cascada: (a) match exacto por `(subtipo, identificador_externo)` → reutilizar; (b) si hay UNA UNICA cuenta del subtipo sin identificador → completarla; (c) crear nueva (`tipo=billetera_digital`, `activo=true`). Idempotente: ante carrera de creacion concurrente captura la excepcion del UNIQUE y re-busca el match exacto.
- **`CuentaEmpresaService::buscarParaIntegracion(IntegracionPagoSucursal $config): ?CuentaEmpresa`** (nuevo): variante solo-lectura de `findOrCreateParaIntegracion`. Devuelve la cuenta ya vinculada sin crear ni completar nada. La usa la UI de Formas de Pago para sugerir el `cuenta_empresa_id` default sin efectos secundarios.
- **`IntegracionPagoGatewayContract::identidadCuentaEmpresa(IntegracionPagoSucursal $config): ?array`** (nuevo metodo del contrato): cada gateway puede declarar su identidad de cuenta. Devuelve `['subtipo' => string, 'identificador_externo' => string, 'nombre_sugerido' => string]` o `null` si el proveedor no se mapea a una cuenta conciliable (o le faltan datos). `MercadoPagoGateway` lo implementa con `subtipo='mercadopago'`, `identificador_externo=$config->user_id_externo`, `nombre_sugerido='Mercado Pago '.$user_id_externo`; devuelve `null` si `user_id_externo` esta vacio. Futuros gateways lo implementan o heredan el default `null` (sin vinculo).

#### Componente Livewire: `IntegracionesPago`

`App\Livewire\Configuracion\IntegracionesPago`. Usa el trait `ManejaDomicilio` (igual que `ConfiguracionEmpresa`) para el modal "Direccion y coordenadas de la sucursal". El flujo del modal es identico al picker de Sucursales: provincia (ISO 3166-2) → localidad del catalogo GeoRef → mapa con marcador arrastrable y opcion "Usar mi ubicacion".

Diferencias respecto al formulario de Sucursales:
- Las reglas de validacion del trait se extienden via `array_merge($this->reglasDomicilio(...), [...])` para hacer obligatorios `domLocalidadId`, `domLatitud` y `domLongitud` (MP los exige todos para crear la Store).
- Al guardar, `guardarDireccion()` resuelve `Localidad::find($datos['localidad_id'])?->nombre` y lo escribe en `sucursales.localidad` (string legado que MP usa como `city_name`) ademas de `localidad_id`. Esto mantiene ambos campos sincronizados.
- Despues de persistir llama a `CatalogoCache::clear()` para invalidar el cache de sucursales (mismo patron que `ConfiguracionEmpresa`).

#### Catalogo de integraciones — codigos vigentes

| Codigo | Nombre | Descripcion | Orden |
|---|---|---|---|
| `mercadopago_qr` | Mercado Pago - QR | Cobro via QR dinamico, QR estatico o QR de monto libre. Renombrado desde `mercadopago` en Fase 4 para dar lugar a futuros productos MP como filas separadas del catalogo. Los modos disponibles (`modos_disponibles` en el catalogo): `qr_dinamico`, `qr_estatico`, `qr_libre` | 1 |
| `mercadopago_point` | Mercado Pago - Point | Cobros con Mercado Pago Point: el monto se envia a la terminal fisica desde el sistema y el cliente paga con tarjeta o QR en el propio aparato. Producto MP separado del QR; usa su propia aplicacion MP con access_token propio. Reusa `MercadoPagoGateway` con rama por modo. | 2 |

**Constantes PHP**:
- `IntegracionPago::CODIGO_MERCADOPAGO_QR = 'mercadopago_qr'`
- `IntegracionPago::CODIGO_MERCADOPAGO_POINT = 'mercadopago_point'`

**Constantes de modo en `IntegracionPagoTransaccion`**:
- `MODO_QR_DINAMICO = 'qr_dinamico'`
- `MODO_QR_ESTATICO = 'qr_estatico'`
- `MODO_QR_LIBRE = 'qr_libre'`
- `MODO_POINT = 'point'`

**Constantes de modo en `MercadoPagoGateway`** (mismos valores):
- `MODO_QR_DINAMICO`, `MODO_QR_ESTATICO`, `MODO_QR_LIBRE = 'qr_libre'`, `MODO_POINT = 'point'`

#### Reglas de negocio — asignacion de integraciones a formas de pago (Fase 4)

1. **Solo FP simples con concepto compatible**: Solo las formas de pago simples cuyo `ConceptoPago.permite_integracion = true` pueden tener integraciones vinculadas. Las FP mixtas no admiten integraciones (se limpian al cambiar a mixta).

2. **N integraciones por FP**: Una forma de pago puede tener N integraciones. Cada producto del proveedor (ej. Mercado Pago QR, Mercado Pago Point) es una integracion distinta con su propio access token aunque compartan el mismo `user_id_externo` (misma cuenta MP).

3. **Una sola fila por par (FP, integracion)**: El UNIQUE sobre `(forma_pago_id, integracion_pago_id)` impide duplicar la misma integracion en la misma FP. La validacion en Livewire lo verifica antes de llamar a `sync()`.

4. **Principal para cobro sin pregunta**: Al cobrar, si la FP tiene una unica integracion se usa automaticamente. Si tiene varias, se usa la marcada `es_principal`. Si ninguna esta marcada, se toma la primera. El helper `integracionPrincipal()` implementa esta logica.

5. **Modos de cobro**: Los modos (`qr_dinamico`, `qr_estatico`, `qr_libre`, `point`) son variantes de cobro, no integraciones separadas. Cada forma de pago usa **un unico modo**, configurado en el campo `modo_default` del pivote. El campo `modos_permitidos` (json array) se conserva por compatibilidad de esquema y se persiste siempre como `[modo_default]` (espejo de un solo elemento). No hay validacion de inclusion porque no existe seleccion multiple.

   Resolucion del modo al cobrar: `WithCobroIntegracion::iniciarCobroIntegracion` lee `$integracion->pivot->modo_default`; ese valor se pasa como `modo_usado` a la transaccion. Para `qr_dinamico` y `qr_estatico`, `MercadoPagoGateway::mapearModoOrdersApi()` lo convierte al valor esperado por la Orders API (`dynamic` / `static`). Para `point`, el concern valida que la caja tenga `mp_point_terminal_id`, construye `metadata['point']` con `default_type` (de `config_point` del pivote) e `installments` (de las cuotas del desglose, solo si `default_type = credit_card`), y pasa `metadata` a `CobroIntegracionService::iniciarCobro()`; el gateway detecta `modo_usado = 'point'` y delega en `iniciarCobroPoint()`. Para `qr_libre`, el gateway toma un camino alternativo (`iniciarCobroQrLibre`) que no llama a la Orders API.

   El modo `qr_libre` tiene requerimientos distintos al cobrar: NO exige `access_token` ni que la caja este sincronizada en MP (no hay credenciales de API involucradas). Solo requiere que la integracion exista y este activa en la sucursal, y que `config_qr_libre.imagen_path` no sea nulo en el pivote. La URL de la imagen se deriva root-relativamente via `ImagenQrLibreService::urlPublica($path)` y se pasa al gateway a traves de `metadata['qr_libre_imagen_url']`.

6. **Sincronizacion via sync()**: Al guardar, el componente llama a `$formaPago->integraciones()->sync($syncIntegraciones)` con el mapa `[integracion_pago_id => [modo_default, modos_permitidos, es_principal, config_point, config_qr_libre]]`, donde `modos_permitidos` es siempre `json_encode([$modo_default])`, `config_point` es `json_encode(['default_type' => ...])` o null (Abierto), y `config_qr_libre` es null para los modos que no lo usan. Si la FP no admite integraciones se llama a `detach()` para limpiar registros huerfanos.

#### Reglas de negocio — confirmacion manual segun modo

El concern `WithCobroIntegracion` distingue dos contextos al llamar a `confirmarCobroIntegracionManual()`:

- **Modo `qr_libre`**: la confirmacion manual ES el unico flujo de cierre (no hay webhook ni deteccion automatica). Por lo tanto el boton "Confirmar pago recibido" se muestra siempre visible en el modal, accesible a cualquier operario de la caja **sin necesidad del permiso `integraciones_pago.confirmar_manual`**.
- **Modos `qr_dinamico`, `qr_estatico` y `point`**: la confirmacion manual es un fallback excepcional (el sistema no detecto el pago automaticamente). Se muestra como un enlace discreto y **requiere el permiso `integraciones_pago.confirmar_manual`** para habilitarse.

El metodo `tienePermisoConfirmarManual()` (extraido del computed `puedeConfirmarManual`) verifica `hasPermissionTo('integraciones_pago.confirmar_manual')` y se usa desde la accion (no solo desde la computed) para permitir el chequeo en el momento de la llamada.

#### Reglas de negocio — polling para `qr_libre`

El metodo `pollearCobroIntegracion()` del concern tiene un short-circuit explicito para `qr_libre`: si la transaccion en curso tiene `modo_usado = 'qr_libre'`, el poll retorna sin hacer ninguna consulta al gateway ni a la BD (mas alla de la lectura del estado local). Razon: no existe `external_id` en MP que consultar; la unica fuente de verdad es la confirmacion manual del cajero.

#### Permisos del modulo de integraciones de pago

| Permiso | Descripcion |
|---|---|
| `func.integraciones_pago.administrar` | Configurar y sincronizar integraciones (acceso al modulo de configuracion) |
| `integraciones_pago.confirmar_manual` | Confirmar manualmente un cobro en los modos `qr_dinamico` y `qr_estatico` cuando el sistema no lo detecto automaticamente. Muestra el panel de fallback en el modal "Esperando pago". No aplica al modo `qr_libre` (su confirmacion manual no requiere este permiso). Recomendado solo para supervisores o cajeros de confianza. |

#### Comando de expiracion automatica (Fase 8, RF-16)

`php artisan integraciones-pago:expirar-pendientes`

- Corre cada minuto via el scheduler de Laravel (`bootstrap/app.php`, con `withoutOverlapping()` para evitar solapamientos).
- Itera TODOS los comercios (multi-tenant): para cada uno llama a `TenantService::setComercio()` y luego a `CobroIntegracionService::expirarPendientesVencidas()`.
- Marca como `expirado` las transacciones que tienen `estado = 'pendiente'` y `expira_en <= now()` (scope `vencidas()` del modelo).
- Por cada transaccion expirada broadcastea `IntegracionPagoActualizado` con `estado = 'expirado'` en el canal de la transaccion, para que el modal del cajero cierre y muestre "tiempo agotado" sin requerir accion manual.
- Bajo el modelo "cobro primero, cobrable despues" NO anula ninguna venta: las transacciones que expiran nunca tuvieron cobrable asociado.
- Tolerante a fallos por comercio: un error en un tenant no interrumpe el procesamiento del resto (try/catch por comercio).

#### Reglas de negocio — vinculo CuentaEmpresa e integraciones (Paso 2)

1. **Auto-vinculo al guardar credenciales de produccion** (RF-02): `IntegracionPagoSucursalService` invoca `CuentaEmpresaService::findOrCreateParaIntegracion()` al persistir credenciales prod. El gateway provee `identidadCuentaEmpresa()` — unico codigo por-proveedor. QR y Point de la misma cuenta MP comparten la MISMA `CuentaEmpresa` (mismo `user_id_externo = subtipo 'mercadopago'`). En modo `test` es no-op.

2. **Movimiento al confirmar, no al materializar** (D6): el ingreso en la `CuentaEmpresa` lo registra `CobroIntegracionService` al confirmar la transaccion, no al crear la venta. Esto refleja el instante real en que la plata entro al proveedor. Aplica a todos los modos (`qr_dinamico`, `qr_estatico`, `qr_libre`, `point`) y futuros proveedores.

3. **Anti doble registro** (D6): los sitios de materializacion de pagos (`WithPagosDesglose`, `NuevaVenta`, `CobroService`) saltean el registro de movimiento por `formaPago->cuenta_empresa_id` cuando el pago tiene `integracion_pago_transaccion_id` no nulo. En un desglose mixto (integracion + efectivo), solo saltean el pago cobrado por integracion; los demas pagos con cuenta vinculada registran el suyo normal. Como consecuencia, `venta_pagos.movimiento_cuenta_empresa_id` queda NULL para pagos por integracion.

4. **Cuenta resuelta por identidad de la config de la sucursal** (D7): `registrarMovimientoCuentaEmpresa()` resuelve la cuenta via `identidadCuentaEmpresa()` de la config de la transaccion (`transaccion->integracionSucursal`), no via `formaPago->cuenta_empresa_id`. Asi, dos sucursales con cuentas MP distintas impactan cada una la suya. Fallback: `formaPago->cuenta_empresa_id` si la identidad es null.

5. **Anulacion no revierte el movimiento** (D8): como `venta_pagos.movimiento_cuenta_empresa_id` queda NULL en pagos por integracion, los flujos de anulacion y cambio de forma de pago (que contraasientan por ese link) no encuentran nada que revertir. El dinero sigue en el proveedor salvo refund manual externo. Una futura funcionalidad de refund agregaria el egreso como contraasiento; hasta entonces el saldo queda acumulado.

6. **Idempotencia por origen polimórfico**: antes de registrar el movimiento se verifica que no exista ya un `MovimientoCuentaEmpresa` con `origen_tipo='IntegracionPagoTransaccion'` y el mismo `origen_id`. Webhook, polling y confirmacion manual pueden converger; solo el primero que llegue registra el movimiento.

7. **Solo produccion afecta el ledger** (D1): el guard esta en `confirmarCobro`/`confirmarManual` (un solo lugar). No se replica en los sitios de materializacion.

8. **Genericidad**: el unico codigo proveedor-especifico es `identidadCuentaEmpresa()` en cada clase gateway. El resto (columna, service de vinculo, movimiento, autocompletado) no menciona a ningun proveedor. Para agregar otro gateway, solo se implementa ese metodo.

#### Reglas de negocio — conciliacion de cuenta con el proveedor (Paso 3)

1. **Conciliacion por CuentaEmpresa, no por sucursal**: una cuenta MP compartida por N sucursales se concilia UNA sola vez. La corrida busca cualquier config prod activa cuya `identidadCuentaEmpresa()` coincida con la cuenta para obtener el access token.

2. **Solo produccion**: no se pueden conciliar cuentas sin `identificador_externo` ni cuentas cuya unica config activa sea de modo test. Guard en `ConciliacionCuentaService::crearCorrida()`.

3. **Una sola corrida activa por cuenta**: si ya existe una corrida en estado `generando` o `pendiente_revision` para la cuenta, `crearCorrida()` lanza excepcion. Reintentable creando una corrida nueva tras descartar o esperar a que la activa se resuelva.

4. **Asincronico sin bloquear la UI**: el reporte de MP se genera de forma asincrona (202). La corrida avanza por estados via el comando `conciliaciones:procesar` (scheduler cada minuto). La UI usa `wire:poll` mientras el estado es `generando`. Timeout: >60 min en `generando` → `error`.

5. **Match de cobros**: fila `tipo=cobro` del proveedor ↔ `IntegracionPagoTransaccion` confirmada por `referencia == transaccion.external_reference` o `id_externo == transaccion.external_id`. Tolerancia de ±1 dia por timezone al filtrar el periodo.

6. **Comision granular**: si una fila `matcheado` tiene `comision > 0`, se genera una fila hija de `tipo=comision` clasificada `matcheado` con accion `generar_movimiento` (egreso, concepto `comision_integracion`). No hay un egreso global de comisiones: es uno por cobro.

7. **Solo en el sistema = alerta, sin ajuste**: un `cobro_integracion` del periodo que no aparece en el reporte se marca `solo_sistema`. No se propone egreso automatico (puede ser diferencia de timing). Se muestra como alerta para que el usuario lo revise manualmente.

8. **Idempotencia cross-corrida**: al clasificar filas del proveedor, si ya existe un `MovimientoCuentaEmpresa` con `origen_tipo='ConciliacionFila'` de la misma cuenta y mismo `(tipo, id_externo)`, la fila se marca `ya_registrado` y no propone nada. Esto permite re-conciliar periodos solapados sin duplicar movimientos.

9. **Aplicar es idempotente**: guard de estado en `aplicar()` — si la corrida ya esta `aplicada`, la llamada es no-op. Protege contra doble click.

10. **Append-only**: aplicar genera `MovimientoCuentaEmpresa` via `registrarMovimientoAutomatico()` con origen polimórfico `ConciliacionFila` y `sucursal_id = null` (la conciliacion es de comercio, no de sucursal). Nunca modifica ni anula movimientos existentes.

11. **Corrida programada nunca auto-aplica**: la corrida creada por el comando (flag `conciliacion_automatica`) siempre queda `pendiente_revision`. La aplicacion siempre requiere accion humana.

12. **Ajuste inicial (cierre de D11)**: si la cuenta no tiene ninguna corrida `aplicada` previa, al aplicar se ofrece el campo "Saldo real total en el proveedor" (saldo ACTUAL: disponible + a liberar + reserva). El ajuste se registra DESPUES de los movimientos de la corrida: fila `tipo=ajuste_inicial` con movimiento `ajuste_conciliacion` por `saldo_real - saldo_ledger_conciliado`, dejando la cuenta exactamente en el saldo real. MP no expone el saldo por API a tokens estandar (403), por eso es ingreso manual.

13. **Genericidad del gateway**: `solicitarReporteCuenta()` y `obtenerReporteCuenta()` son metodos del contrato `IntegracionPagoGatewayContract`. La implementacion MP-especifica (endpoints `/v1/account/settlement_report/*`, parseo de CSV, mapeo de TRANSACTION_TYPE a tipo normalizado) vive exclusivamente en `MercadoPagoGateway`. Un proveedor que no soporte reportes devuelve `null` en `solicitarReporteCuenta()`.

**Mapeo de tipos MP → tipo normalizado:**
| TRANSACTION_TYPE (CSV de MP) | tipo normalizado |
|---|---|
| `SETTLEMENT` | `cobro` |
| `REFUND` | `devolucion` |
| `CHARGEBACK`, `DISPUTE` | `contracargo` |
| `WITHDRAWAL`, `PAYOUT` | `retiro` |
| `WITHDRAWAL_CANCEL` | `retiro_cancelado` |
| Creditos sin tipo conocido | `acreditacion` |
| Resto | `otro` |

**Mapeo de tipo normalizado → concepto propuesto:**
| tipo | clasificacion | tipo movimiento | concepto |
|---|---|---|---|
| `comision` | `matcheado` | egreso | `comision_integracion` |
| `retiro` | `solo_proveedor` | egreso | `retiro_integracion` |
| `devolucion`, `contracargo` | `solo_proveedor` | egreso | `devolucion_integracion` |
| `cobro` sin match, `acreditacion`, `retiro_cancelado` | `solo_proveedor` | ingreso | `acreditacion_integracion` |
| `ajuste_inicial` | — | ingreso o egreso | `ajuste_conciliacion` |

**Servicios:**
- `ConciliacionCuentaService` — `app/Services/IntegracionesPago/ConciliacionCuentaService.php`: `crearCorrida()`, `procesarPendientes()` (motor del comando), `ejecutarMatch()`, `aplicar()`, `descartar()`, `resolverConfigParaCuenta()`.
- Comando `conciliaciones:procesar` — `app/Console/Commands/ProcesarConciliacionesCommand.php`: itera comercios via TenantService manual, llama `procesarPendientes()`. Scheduler: cada minuto con `withoutOverlapping()`.

#### Reglas de negocio criticas (Mercado Pago)

1. **external_id NO en updates**: MP rechaza el campo `external_id` en las solicitudes PUT (Store y POS) con HTTP 400, porque valida unicidad incluso contra el propio recurso. Solo se envia al crear.

2. **external_id de POS estrictamente alfanumerico**: El endpoint `POST /pos` exige `external_id` sin caracteres especiales. Formato: `BCN{comercioId}POS{cajaId}` (sin guiones). El endpoint de Store si acepta guiones; por eso Store usa `BCN-{c}-{s}`.

3. **Limpieza al cambiar cuenta MP**: Al cambiar `modo` (test <-> produccion) o `user_id_externo`, los recursos de Store/POS de la cuenta anterior no existen en la nueva. El service limpia `mp_store_id`, `mp_store_external_id`, `mp_pos_id`, `mp_pos_external_id`, `mp_pos_qr_url`, `mp_pos_qr_pdf_url` para que la proxima sincronizacion los cree en lugar de intentar actualizarlos.

4. **Provincias como codigos ISO 3166-2**: El campo `sucursales.provincia` guarda el codigo ISO (ej: `AR-B`). Al armar el payload para MP (`state_name`), se traduce al nombre oficial usando `Sucursal::PROVINCIAS_AR[]` / `provinciaNombre()`. Esto garantiza consistencia entre integraciones sin depender de texto libre.

5. **Prerrequisito de Store para POS**: No se puede crear un POS si la sucursal no tiene `mp_store_id` y `mp_store_external_id`. El gateway lanza excepcion explicita.

6. **Coordenadas obligatorias**: La API de MP rechaza la creacion de Store sin `latitude`/`longitude`. El gateway valida que `sucursal->tieneCoordenadas()` antes de llamar a la API.

7. **Categoria MCC**: Solo se envia el campo `category` al crear un POS si el comercio tiene rubro `gastronomia` (MCC 621102) o `estacion_servicio` (MCC 443001). Para el resto se omite el campo.

8. **Eliminacion idempotente**: Si MP responde 404 al eliminar un Store o POS, se trata como exito (el recurso ya no existia).

9. **Point — prerequisito de modo PDV**: La terminal debe estar en modo `PDV` (integrado) antes de poder recibir cobros del sistema. `vincularTerminalCaja` activa el modo PDV via `PATCH /terminals/v1/setup` antes de persistir el `terminal_id`. Sin este paso MP rechaza las orders de tipo `point`.

10. **Point — expiration_time acotado**: La Orders API de Point acepta `expiration_time` en formato ISO 8601 (ej: `PT300S`), con un rango permitido de PT30S a PT3H. El gateway acota el `timeout_segundos` de la config dentro de ese rango antes de enviar el payload.

11. **Point — `at_terminal` al cancelar**: Cuando una order Point esta esperando en el aparato, su estado en MP es `at_terminal`. MP rechaza la cancelacion sin el header `x-allow-cancelable-status: at_terminal`. El gateway lo agrega automaticamente cuando `transaccion->modo_usado === 'point'`.

12. **Point no usa Store/POS**: El producto MP Point trabaja con "devices" (terminales), no con la estructura Store/POS del producto QR. No se sincroniza sucursal ni se crea POS para Point. La vinculacion es directamente a nivel de device via `/terminals/v1/list` y `/terminals/v1/setup`.

#### Formatos de external_id

| Recurso | Formato | Ejemplo | Limite MP |
|---|---|---|---|
| Store | `BCN-{comercio_id}-{sucursal_id}` | `BCN-1-5` | 60 chars |
| POS | `BCN{comercio_id}POS{caja_id}` | `BCN1POS3` | 40 chars |

---

### 2.14 Sistema Impositivo

Modulo de gestion fiscal (sistema-impositivo, Fases 1-10b). Centraliza el catalogo de impuestos, la configuracion por CUIT, el ledger fiscal, los domicilios fiscales, el perfil fiscal del cliente y el importador de padron de percepcion IIBB (ARBA/AGIP).

#### Tabla: `impuestos`
Catalogo de impuestos argentinos. Seeded por el sistema al crear un comercio. Extensible con impuestos custom del comercio (`es_sistema = false`).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `codigo` | varchar(50) UNIQUE | Codigo del impuesto (ej: `iva_debito`, `perc_iibb_ar_b`, `ret_ganancias`) |
| `nombre` | varchar(150) | Nombre legible |
| `tipo` | enum | `iva`, `iibb`, `ganancias`, `credito_debito`, `otro` |
| `naturaleza_default` | enum | `percepcion`, `retencion`, `debito_fiscal`, `credito_fiscal`, `tributo` |
| `jurisdiccion` | varchar(6) nullable | `AR` = nacional; codigo ISO 3166-2 = provincial (ej: `AR-B`, `AR-C`). NULL para impuestos sin jurisdiccion |
| `codigo_arca` | smallint nullable | Codigo de tipo de tributo del WS de ARCA (`FEParamGetTiposTributos`). NULL = no viaja en el comprobante (retenciones, IVA debito/credito). Valores: `6` = Percepcion de IVA, `7` = Percepcion de IIBB, `99` = Otros. |
| `es_sistema` | boolean | true = seeded, false = custom del comercio |
| `activo` | boolean | Si esta disponible para configurar |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Catalogo de impuestos del sistema** (seeded en la migracion y al provisionar comercios):
- `iva_debito` / `iva_credito`: IVA debito y credito fiscal (tipo `iva`, jurisdiccion `AR`).
- `perc_iva` / `ret_iva`: Percepcion y retencion de IVA (tipo `iva`, jurisdiccion `AR`).
- `ret_ganancias`: Retencion de Ganancias (tipo `ganancias`, jurisdiccion `AR`).
- `imp_creditos_debitos`: Impuesto Ley 25.413 (tipo `credito_debito`, jurisdiccion `AR`).
- `ret_sircreb`: Retencion SIRCREB IIBB sobre acreditaciones (tipo `iibb`, jurisdiccion `AR`).
- `perc_iibb_{iso}` / `ret_iibb_{iso}`: Percepcion y retencion de IIBB por cada provincia (24 pares, uno por jurisdiccion ISO 3166-2 argentina, ej: `perc_iibb_ar_b`, `ret_iibb_ar_c`).
- `otro`: generico para tributos sin categoria.

#### Tabla: `cuit_impuesto_configs`
Configuracion impositiva de un CUIT: que impuestos lo alcanzan y con que condiciones.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cuit_id` | bigint FK | CUIT del comercio |
| `impuesto_id` | bigint FK | Impuesto del catalogo |
| `inscripto` | boolean | Si el CUIT esta inscripto en este impuesto |
| `coeficiente_computable` | decimal(5,4) nullable | D25: default de que parte de una percepcion SUFRIDA en compras con este impuesto es credito fiscal computable (0 a 1). NULL = deriva de `inscripto` (1.00 si inscripto, 0.00 si no). Solo aplica a percepciones no-IVA (las de IVA siempre son credito pleno, ver 3.8.8) |
| `numero_inscripcion` | varchar(30) nullable | N° de inscripcion (ej: N° de IIBB) |
| `es_agente_percepcion` | boolean | Si actua como agente de percepcion |
| `es_agente_retencion` | boolean | Si actua como agente de retencion |
| `percibir_no_empadronados` | boolean | Solo IIBB. Si true, percibe a todo RI sin perfil fiscal propio a la alicuota fija; si false (default), solo a clientes con alicuota cargada (manual o padron) |
| `alicuota` | decimal(6,4) nullable | Alicuota porcentual (ej: `3.0000` = 3%) |
| `alicuota_minimo_base` | decimal(14,2) nullable | Umbral de BASE IMPONIBLE minima; si el neto es menor, no se percibe |
| `monto_minimo_percepcion` | decimal(15,2) nullable | Umbral sobre el IMPORTE RESULTANTE de la percepcion (distinto de `alicuota_minimo_base`, que es sobre la base); si el monto calculado no lo alcanza, la percepcion no se practica. Config del agente (revision Fable 2026-07-01) |
| `origen_alicuota` | enum | `manual` (cargada a mano) o `padron` (importado desde padron ARBA/AGIP — Fase 10b) |
| `vigente_desde` | date nullable | Inicio de vigencia; NULL = siempre vigente |
| `vigente_hasta` | date nullable | Fin de vigencia; NULL = sin fecha limite |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Scope `vigentes($fecha)`**: filtra configs activas en una fecha dada (respeta `vigente_desde` y `vigente_hasta`, NULL en cualquiera = sin limite en esa direccion).

#### Tabla: `movimientos_fiscales`
Ledger fiscal. Registro append-only de cada impuesto sufrido o aplicado. Unica puerta de escritura: `ImpuestoService`.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cuit_id` | bigint FK | CUIT del comercio al que se imputa |
| `sucursal_id` | bigint FK nullable | Sucursal de la operacion |
| `impuesto_id` | bigint FK | Impuesto del catalogo |
| `sentido` | enum | `sufrido` (el comercio paga/sufre) o `aplicado` (el comercio cobra/aplica como agente) |
| `naturaleza` | enum | `percepcion`, `retencion`, `debito_fiscal`, `credito_fiscal`, `tributo` |
| `fecha` | date | Fecha de la operacion |
| `periodo_fiscal` | char(7) | `YYYY-MM` calculado al registrar desde `fecha`. Inmutable, nunca depende del timezone al consultar |
| `base_imponible` | decimal(14,2) nullable | Base sobre la que se calcula el impuesto |
| `alicuota` | decimal(6,4) nullable | Alicuota aplicada |
| `monto` | decimal(14,2) | Monto del impuesto. Positivo salvo excepcion: las reversas de nota de credito (ver mas abajo) registran `monto` NEGATIVO. Fuera de ese caso, el signo semantico lo dan `sentido` + `naturaleza` |
| `certificado_numero` | varchar(50) nullable | N° de constancia de retencion |
| `origen_tipo` | varchar(50) nullable | `ComprobanteFiscal`, `Compra`, `ConciliacionFila`, NULL = manual |
| `origen_id` | bigint nullable | ID del origen |
| `movimiento_anulado_id` | bigint FK nullable | Si es contraasiento: apunta al movimiento anulado |
| `estado` | enum | `activo` o `anulado` |
| `observaciones` | text nullable | Notas libres |
| `usuario_id` | bigint FK nullable | Usuario que registro el movimiento |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indices**: `(cuit_id, periodo_fiscal)`, `(origen_tipo, origen_id)`, `(impuesto_id)`.

**Patron append-only**: la anulacion crea un contraasiento (fila nueva con `movimiento_anulado_id` apuntando al original y `estado = anulado`) y pasa el original a `estado = anulado`. La posicion fiscal solo suma movimientos `estado = activo`.

#### Tabla: `compra_percepciones`
Desglose de percepciones y retenciones sufridas en la factura de un proveedor (RF-05, Fase 6). Paralelo a `comprobante_fiscal_tributos` del lado de ventas.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `compra_id` | bigint FK | Compra (ON DELETE CASCADE) |
| `impuesto_id` | bigint FK | Impuesto del catalogo |
| `base_imponible` | decimal(14,2) nullable | Base imponible |
| `alicuota` | decimal(6,4) nullable | Alicuota porcentual |
| `monto` | decimal(14,2) | Monto de la percepcion/retencion |
| `coeficiente` | decimal(5,4) nullable | D25: snapshot editable por compra de que parte de `monto` es credito fiscal computable (0 a 1); el resto prorratea al costo. NULL = legado/sin dato ⇒ tratado como 100% computable (ver 3.8.8) |
| `certificado_numero` | varchar(50) nullable | N° de constancia |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Tabla: `cuit_domicilios`
Domicilios fiscales declarados por un CUIT ante AFIP (RF-11, Fase 9). Cada CUIT puede tener N domicilios. La jurisdiccion de IIBB de una operacion surge de la `provincia` del domicilio asignado al punto de venta, no de la ubicacion fisica de la sucursal.

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `cuit_id` | bigint FK (CASCADE) | CUIT al que pertenece |
| `tipo` | enum | `fiscal`, `comercial`, `otro` |
| `provincia` | varchar(6) | Codigo ISO 3166-2 — determina la jurisdiccion de IIBB (ej: `AR-B`, `AR-C`) |
| `localidad_id` | bigint nullable | Ref soft a `localidades` (config); sin FK cross-DB |
| `direccion` | varchar(255) | Calle y numero |
| `codigo_postal` | varchar(10) nullable | Diferido, no usado en UI aun |
| `latitud` | decimal(10,7) nullable | Coordenada |
| `longitud` | decimal(10,7) nullable | Coordenada |
| `es_principal` | boolean | Si es el domicilio principal del CUIT |
| `activo` | boolean | Si esta activo |
| `created_at`, `updated_at` | timestamp | Timestamps |

#### Columnas nuevas en tablas existentes

**`impuestos.codigo_arca`** (smallint nullable, Fase 5b): codigo de tipo de tributo del WS de ARCA (`FEParamGetTiposTributos`). Se pobla automaticamente al provisionar un comercio nuevo y al correr la migracion `2026_06_18_170000_add_codigo_arca_a_impuestos`. Mapeo: `perc_iva` → 6, `perc_iibb_*` → 7, `otro` → 99. NULL para impuestos que no viajan en el comprobante (retenciones, IVA debito/credito). Sin `codigo_arca`, el tributo se calcula y se cobra pero no se informa en el array `Tributos[]` de AFIP (comportamiento defensivo).

**`compras.cuit_id`** (bigint FK nullable, Fase 6): CUIT del comercio al que se imputa fiscalmente la compra. Si es NULL, la compra no alimenta el ledger fiscal. FK con `ON DELETE SET NULL`.

**`puntos_venta.cuit_domicilio_id`** (bigint FK nullable, Fase 9): domicilio fiscal declarado del punto de venta. Determina la jurisdiccion de IIBB de los comprobantes emitidos desde ese PV. FK con `ON DELETE SET NULL`. Helper `jurisdiccionFiscal(): ?string` devuelve `cuitDomicilio->provincia`.

**`sucursales.localidad_id`** (bigint nullable, Fase 9): ref soft a `localidades` (config) para el domicilio fisico estructurado de la sucursal. Independiente de CUIT o integracion de pago.

**`localidades.latitud` / `localidades.longitud`** (`decimal(10,7)` nullable, Fase 9 — picker Google Maps): centro geografico de la localidad. Se usa para centrar y acotar el picker de Google Maps cuando el usuario elige provincia+localidad antes de abrir el mapa. Backfill desde `database/data/localidades_georef.json` via migracion `2026_06_24_120000_add_geo_to_localidades`. `Localidad::centro(?int $id): ?array` devuelve `['lat'=>float,'lng'=>float]` o `null` si no hay dato.

**`clientes.provincia`** (varchar(6) nullable, Fase 10a): jurisdiccion fiscal del cliente expresada como ISO 3166-2 (ej: `AR-B`, `AR-C`). Define el destino geografico de las percepciones de IIBB. Opcional: consumidor final puede no tenerla.

**`clientes.localidad_id`** (bigint nullable, Fase 10a): ref soft a `localidades` (config). Complementa la provincia para el domicilio fiscal estructurado del cliente.

**`cuit_impuesto_configs.percibir_no_empadronados`** (tinyint, default 0, Fase 10a): flag D7. Solo aplica a impuestos IIBB. Cuando true, el CUIT agente percibe a la alicuota fija a todo cliente RI que no tenga perfil fiscal propio (`cliente_impuesto_configs`) para ese impuesto. Cuando false (default conservador), sin perfil fiscal del cliente no se percibe.

**`cuentas_empresa.cuit_id`** (Fase 4a): CUIT del comercio al que pertenece la cuenta bancaria/billetera. Se usa al registrar impuestos sufridos via conciliacion: el impuesto se imputa al CUIT de la cuenta conciliada.

**`conciliacion_filas.impuesto_id`** (bigint FK nullable, Fase 4a): impuesto identificado en la fila de conciliacion segun el codigo `TAX_DETAIL` del reporte MP. Mapa de codigos MP → impuesto del catalogo:
- `IVA` → `perc_iva`
- `INCOME_TAX` → `ret_ganancias`
- `SIRCREB` → `ret_sircreb`
- `IIBB_{ISO}` → `ret_iibb_{iso}` (por jurisdiccion)

#### Importador de padron ARBA/AGIP (Fase 10b, RF-14)

Componente: `App\Livewire\Fiscal\PadronImport` (ruta `fiscal.padrones`, permiso `func.fiscal.configuracion`). Global (no SucursalAware). Lazy con skeleton `page-form`.

Service principal: `App\Services\Fiscal\PadronImportService`. Metodo publico: `importar(string $rutaArchivo, string $agencia): ResumenImportacion`.

**Parsers** (namespace `App\Services\Fiscal\Padron\`):

| Clase | Agencia | Impuesto | Formato de archivo |
|---|---|---|---|
| `ArbaPadronParser` | `arba` | `perc_iibb_ar_b` | `PadronRGSPerMMAAAA.txt`, separado por `;`, solo filas regimen `P` (percepcion) |
| `AgipPadronParser` | `agip` | `perc_iibb_ar_c` | Padron unificado, separado por `;`, ambas alicuotas; solo se usa campo 7 (percepcion) |

**Formatos de campo comun (ARBA y AGIP)**:
- Alicuota: `9,99` (coma decimal). Ejemplo: `"1,50"` → `1.5`.
- Fecha: `DDMMAAAA`. ARBA rinde el cero inicial como espacio en algunos campos (ej: `" 1102014"` = 01/10/2014). El parser extrae solo los digitos y pad-left a 8 con ceros antes de parsear; con `checkdate()` valida la fecha.
- CUIT: 11 digitos. El parser normaliza eliminando no-digitos y rechaza si el resultado no tiene exactamente 11.

**Layout ARBA** (campos separados por `;`):
`0 Regimen(R/P)  1 FechaPubl  2 VigDesde  3 VigHasta  4 CUIT  5 Tipo  6 MarcaAltaBaja(S/B)  7 MarcaCambioAlic  8 Alicuota  9 NroGrupo`

**Layout AGIP** (campos separados por `;`):
`0 FechaPubl  1 VigDesde  2 VigHasta  3 CUIT  4 Tipo  5 MarcaAlta(S/N/B)  6 MarcaAlic  7 AlicPercep  8 AlicReten  9 GrupoPerc  10 GrupoReten  11 RazonSocial`

**DTO de salida del parser**: `PadronFila` (readonly): `cuit` (11 dig), `exento` (bool), `alicuota` (?float, null si exento), `vigenteDesde` (?string Y-m-d), `vigenteHasta` (?string Y-m-d), `lineaCruda` (string).

**Reglas de negocio**:
1. **Streaming**: `fgets` linea a linea; los CUIT que no son clientes se descartan al vuelo (memoria acotada independientemente del tamano del padron).
2. **Exencion conservadora**: alicuota `<= 0.0` o marca de baja (`B`) → `exento = true`, `alicuota = null`. No se asume percepcion ante la duda.
3. **Precedencia del manual**: si la fila existente tiene `origen_alicuota = 'manual'`, se incrementa `omitidasManual` y se retorna sin update. El override manual nunca se pisa.
4. **Idempotente**: unique `(cliente_id, impuesto_id, vigente_desde)`. Reimportar el mismo padron actualiza (no duplica) las filas de padron existentes.
5. **Transaccion tenant**: todo el upsert corre dentro de `DB::connection('pymes_tenant')->transaction()`.
6. **Mapa de clientes**: se construye una vez al inicio (`[cuit_normalizado => cliente_id]`) con `chunk(500)` sobre clientes con CUIT no nulo.

**Deteccion de formato comprimido**: `PadronImportService::abrirPadron()` detecta el formato por bytes magicos (no por extension, porque el temporal de Livewire no la conserva):
- `PK\x03\x04` / `PK\x05\x06` / `PK\x07\x08` → ZIP: abre con `zip://{ruta}#{primera_entrada_txt}` via `ZipArchive`.
- `\x1f\x8b` → GZIP: abre con `compress.zlib://{ruta}`.
- Otro → texto plano: `fopen` directo.
El archivo nunca se vuelca a disco; se descomprime y lee por streaming.

**Validacion en el componente Livewire**:
- `extensions:zip,gz` + `mimetypes:application/zip,...,application/gzip,...` + `max:102400` (100 MB comprimido).
- Mensajes de error explicitos: "El padron debe subirse comprimido (.zip o .gz)."
- `updatedArchivo()` valida solo el campo archivo (`validateOnly`); `importar()` valida el formulario completo.

**ResumenImportacion** (clase en `App\Services\Fiscal\Padron\ResumenImportacion`):

| Campo | Descripcion |
|---|---|
| `totalFilas` | Lineas leidas del archivo |
| `filasPadron` | Lineas validas de percepcion parseadas |
| `creadas` | Configs nuevas creadas con origen padron |
| `actualizadas` | Configs de padron actualizadas |
| `omitidasManual` | No pisadas por tener override manual |
| `sinMatch` | CUIT del padron que no son clientes del comercio |
| `impactadas()` | `creadas + actualizadas` |

**Comando artisan de fallback**: `fiscal:importar-padron {archivo} {--agencia=arba} {--comercio=1}` (`App\Console\Commands\ImportarPadronCommand`). Acepta `.txt`, `.zip` o `.gz` desde ruta del servidor. CLI configura el contexto tenant manualmente via `TenantService::setComercio()`. Util cuando el padron completo es demasiado grande para subir por la web o cuando se automatiza la importacion periodica.

**Consulta SQL util — clientes actualizados por un padron**:
```sql
SELECT
    c.id,
    c.razon_social,
    c.cuit,
    cic.alicuota,
    cic.exento,
    cic.origen_alicuota,
    cic.vigente_desde,
    cic.vigente_hasta,
    cic.datos_extra->>'$.agencia' AS agencia
FROM {PREFIX}clientes c
JOIN {PREFIX}cliente_impuesto_configs cic ON cic.cliente_id = c.id
JOIN {PREFIX}impuestos i ON i.id = cic.impuesto_id
WHERE i.codigo = 'perc_iibb_ar_b'  -- o 'perc_iibb_ar_c'
  AND cic.origen_alicuota = 'padron'
ORDER BY cic.updated_at DESC;
```

#### Trait ManejaDomicilio y picker de Google Maps (Fase 9, RF-11)

**Trait**: `App\Traits\ManejaDomicilio`. Se incluye en cualquier componente Livewire que tenga un formulario de domicilio (sucursales, clientes, CUITs). Expone:

| Propiedad / Metodo | Descripcion |
|---|---|
| `$domProvincia` | Codigo ISO 3166-2 seleccionado (ej: `AR-B`) |
| `$domLocalidadId` | ID de la localidad en `config.localidades` |
| `$domLocalidades` | Array `[id => nombre]` para el select dependiente |
| `$domLocalidadCentro` | `['lat'=>float,'lng'=>float]` o `null`. Se resuelve con `Localidad::centro()` cada vez que cambia `domLocalidadId`. Lo lee el JS del picker via `$wire.$watch`. |
| `$domLatitud`, `$domLongitud` | Coordenadas guardadas (string, 7 decimales) |
| `setCoordenadasDesdeMapa($lat, $lng)` | Recibe las coords desde el mapa (autocomplete / marcador / geolocalizacion). Valida rango; ignora valores invalidos. Invocado por el JS via `$wire`. |
| `mapsHabilitado(): bool` | `true` si `services.google_maps.key` esta configurada. Decide si se muestra el picker o los inputs manuales. |
| `updatedDomProvincia()` | Hook: resetea localidad y centro al cambiar provincia. |
| `updatedDomLocalidadId()` | Hook: resuelve `$domLocalidadCentro` via `Localidad::centro()`. |
| `setDomicilioDesde(array $datos)` | Hidrata todas las propiedades del domicilio desde un array (nombre, provincia, localidad_id, latitud, longitud). Tambien resuelve `$domLocalidadCentro`. |
| `resetDomicilio()` | Blanquea todas las propiedades incluyendo `$domLocalidadCentro`. |

**Flujo invertido (provincia → localidad → mapa)**:
1. El usuario elige provincia (ISO) → `updatedDomProvincia()` carga las localidades.
2. El usuario elige localidad → `updatedDomLocalidadId()` resuelve el centro geografico y lo escribe en `$domLocalidadCentro`.
3. Al abrir el mapa (boton "Abrir mapa"), el JS Alpine (`domicilioMapa`) lee `$wire.get('domLocalidadCentro')` y centra/acota el mapa ahi.
4. El usuario ubica el punto (autocomplete / clic / arrastre / geolocalizacion) → el JS llama `$wire.setCoordenadasDesdeMapa(lat, lng)` → el trait persiste las coords.

**Configuracion** (`config/services.php`):

```php
'google_maps' => [
    'key'    => env('GOOGLE_MAPS_API_KEY'),
    'map_id' => env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID'),
],
```

- `key`: API key de Google Cloud. APIs requeridas: Maps JavaScript API, Places API (nueva), Geocoding API. Restringir por dominio/HTTP referrer en Google Cloud. Sin key, el partial muestra inputs manuales de lat/lng.
- `map_id`: Map ID de Advanced Markers. `'DEMO_MAP_ID'` sirve para desarrollo local; en produccion crear uno propio en Google Cloud Console.

**Componente Alpine `domicilioMapa`** (`resources/js/domicilio-mapa.js`): registrado en `alpine:init`. Recibe `{key, mapId, txtGeoError}`. Carga perezosa: `init()` no hace nada; el SDK se carga en `abrir()` la primera vez. El mapa se construye una sola vez; llamadas sucesivas a `abrir()` solo recentran. Usa `PlaceAutocompleteElement` (API Places nueva, vigente para clientes nuevos desde 2025; el widget legacy `Autocomplete` fue discontinuado).

**Gotcha: `Alpine.raw()` con librerias que hacen comparacion de identidad**

`AdvancedMarkerElement` (Google Maps) verifica internamente que el objeto pasado como `map` sea la instancia real del `Map`, no un `Proxy`. Alpine envuelve todas las propiedades publicas en `Proxy` reactivos; pasar `this.map` directamente hace que el marcador no se renderice (`isConnected = false`). Solucion:

```js
this.marker = new this.AdvancedMarkerElement({
    map: window.Alpine.raw(this.map),  // instancia cruda, sin Proxy
    ...
});
```

Aplicar `Alpine.raw(obj)` siempre que se pase un objeto reactivo Alpine a una libreria JS de terceros que realice comparaciones de identidad (`===`) sobre el objeto (ej: objetos DOM, instancias de clases de terceros).

---

### 2.15 Pantallas Auxiliares Clase B (Llamador de Pedidos y Consultor de Precios)

Pantallas publicas remotas SIN sesion (TV en salon, tablet en mostrador) que resuelven el tenant a partir de un token de la URL contra el indice global `pantalla_publica_tokens` (DB config), sin escanear los N tenants. Mismo patron que `MercadoPagoCollectorIndex`.

#### Tabla: `pantalla_publica_tokens` (conexion `config`, sin prefijo)

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `token` | varchar(40) UNIQUE | Token largo no adivinable. Nombra el canal Reverb (`llamador.{token}`) y autoriza los endpoints. Se guarda en localStorage del dispositivo. |
| `codigo_corto` | varchar(8) UNIQUE | 6 caracteres del alfabeto sin ambiguedades (sin 0/O, 1/I/L). Credencial humana para vincular TVs tipando la URL corta. Se canjea por el token via `/clase-b/vincular/{codigo}`. |
| `comercio_id` | bigint FK | ID del comercio (referencia a `comercios`). |
| `sucursal_id` | bigint | FK logica cross-DB a `{prefix}sucursales.id` (sin FK real por ser cross-DB). |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indice unico**: `(comercio_id, sucursal_id)` — un solo registro por sucursal de cada comercio.

**Constantes del modelo `PantallaPublicaToken`**:
- `ALFABETO_CODIGO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ'` (31 simbolos sin ambiguedades)
- `LARGO_TOKEN = 40`
- `LARGO_CODIGO = 6`

#### Service: `PantallaPublicaService`

`app/Services/PantallaPublicaService.php`

Unico punto de entrada para operaciones de las pantallas Clase B. Requiere inyeccion de `TenantService` y `PrecioService`.

**Metodos principales**:
- `resolverPorToken(string $token): ?array` — busca el token en el indice global, configura el tenant via `TenantService::usarComercioParaProceso()` y devuelve `['comercio', 'sucursal', 'index']`. Retorna `null` si el token no existe o la sucursal fue eliminada. Lo llama el middleware `pantalla.token` (ver rutas).
- `canjearCodigoCorto(string $codigo): ?string` — canjea el codigo corto (tipeado en TV) por el token largo. Retorna `null` si el codigo no existe. Rate-limited a 10 req/min en la ruta.
- `regenerarToken(Sucursal $sucursal): array` — genera nuevo `token` + `codigo_corto`, actualiza el indice global y la columna `sucursales.token_publico`. Invalida todos los dispositivos vinculados. Retorna `['token', 'codigo_corto']`. Requiere tenant configurado (se llama con sesion desde Configuracion).
- `asegurarToken(Sucursal $sucursal): PantallaPublicaToken` — garantiza que la sucursal tenga registro en el indice. Si no lo tiene (sucursal creada antes del feature), lo genera. Sincroniza `sucursales.token_publico` si difiere. Llamado al abrir los modales de configuracion.
- `pedidosParaLlamador(Sucursal $sucursal): array` — snapshot cold start del monitor llamador: dos listas `{numero, nombre}` con pedidos `en_preparacion` (FIFO por numero) y `listo` (LIFO por numero). Usa `PedidoMostrador::numero_visible` (que devuelve `numero_display ?? numero`).
- `buscarPreciosPublico(Sucursal $sucursal, string $q, int $limite = 20): array` — busca articulos activos en la sucursal por nombre (parcial), codigo o codigo de barras exactos. Devuelve `[{nombre, unidad, precio, promos}]`. Payload minimo: NO expone costo, margen, stock ni listas internas. `promos` incluye nombres de ambos sistemas de promociones (normales y especiales NxM/combo/menu).

#### Evento broadcast: `PedidoLlamadorPublicoBroadcast`

`app/Events/Broadcasting/PedidoLlamadorPublicoBroadcast.php`

- **Implements**: `ShouldBroadcastNow` (sincronico, sin cola).
- **Canal**: `llamador.{token}` (canal PUBLICO — los dispositivos se suscriben sin autenticacion de broadcasting).
- **Nombre de evento** (`broadcastAs()`): `PedidoLlamador`.
- **Payload**: `{numero, nombre, estado, at}`.
  - `numero`: `PedidoMostrador::numero_visible` (turno si existe, sino correlativo).
  - `nombre`: `PedidoMostrador::nombreLlamador()` (primer nombre solamente).
  - `estado`: `'en_preparacion'` o `'listo'` o `'cancelado'` / `'facturado'` (para remover del monitor).
- **Se emite** desde `PedidoMostradorService::cambiarEstado()` cuando la sucursal tiene `usa_llamador = true` y el nuevo o viejo estado es `en_preparacion` o `listo`.
- **No se emite** si `usa_llamador = false` (ahorro de recursos, sin eventos vacios).

**Diferencia con `PedidoMostradorBroadcast`** (canal privado del comercio, para el POS con sesion): `PedidoLlamadorPublicoBroadcast` usa canal publico, payload minimo y no requiere autenticacion de broadcasting. Ambos eventos coexisten y se emiten de forma independiente.

#### Rutas publicas (sin sesion ni autenticacion)

```
GET  /llamador                → LlamadorController::index()    — shell generico (sin token)
GET  /llamador/{token}        → LlamadorController::porToken() — shell con token en URL
GET  /ll                      → alias de /llamador
GET  /ll/{codigo}             → LlamadorController::porCodigo() — URL tipeable con codigo

GET  /precios                 → ConsultorPreciosController::index()
GET  /precios/{token}         → ConsultorPreciosController::porToken()
GET  /pr                      → alias de /precios
GET  /pr/{codigo}             → ConsultorPreciosController::porCodigo()

GET  /clase-b/vincular/{codigo}              → VinculacionController::canjear()   throttle:10,1
GET  /clase-b/llamador/{token}/snapshot      → LlamadorController::snapshot()    throttle:60,1  middleware:pantalla.token
GET  /clase-b/precios/{token}/config         → ConsultorPreciosController::config() throttle:60,1  middleware:pantalla.token
GET  /clase-b/precios/{token}/buscar         → ConsultorPreciosController::buscar() throttle:120,1 middleware:pantalla.token
```

El middleware `pantalla.token` (`ResolvePublicTokenMiddleware`) resuelve el token via `PantallaPublicaService::resolverPorToken()` y lleva `sucursal` y `comercio` al request. Si el token no existe, devuelve 404 generico (sin indicar que el token fue incorrecto — anti-enumeracion).

#### Numeracion de display (turno)

`PedidoMostradorService::siguienteNumeroDisplay(int $sucursalId): ?int`

- Retorna `null` si `sucursal.usa_numeracion_display = false`.
- Si `numeracion_display_modo = 'diario'`: verifica si la hora actual corresponde a un nuevo segmento (segun `numeracion_display_horas`). Si el segmento cambio desde `pedido_display_segmento_at`, reinicia el contador a 0 y actualiza `pedido_display_segmento_at = now()`.
- Incrementa `pedido_display_ultimo_numero` con un `UPDATE ... SET pedido_display_ultimo_numero = pedido_display_ultimo_numero + 1` con `LOCK IN SHARE MODE` (serializa accesos concurrentes) y retorna el nuevo valor.
- El numero asignado a `pedidos_mostrador.numero_display` es el resultado de esta funcion.

`PedidoMostradorService::reiniciarNumeracionDisplay(int $sucursalId): void`

- Modo manual: reinicia `pedido_display_ultimo_numero = 0` y `pedido_display_segmento_at = now()` atomicamente.

#### Seguridad

- Los endpoints publicos tienen rate limiting individual.
- El `codigo_corto` (credencial humana) se usa solo para el intercambio inicial; el token largo se guarda en localStorage y nunca viaja en la URL de la pantalla operativa.
- Canal Reverb publico = solo suscripcion (el cliente no puede publicar ni susurrar).
- Los endpoints de datos (`/snapshot`, `/config`, `/buscar`) responden 404 si el token no existe (sin distinguir "token invalido" de "token correcto con sucursal desactivada" — anti-enumeracion).
- El endpoint de busqueda de precios (`/buscar`) no expone costo, margen, stock ni listas de precios internas. Solo nombre, unidad, precio base y nombres de promociones activas.

#### PWAs

El llamador y el consultor tienen manifests y conjuntos de iconos propios (`/manifest-llamador.json`, `/manifest-consultor-precios.json`). Pueden instalarse como apps independientes en el navegador, separadas de la app principal (scope `/app`) y de la pantalla cliente (scope `/pantalla-cliente`).

Cada pantalla tiene su propio set de iconos bajo `public/pwa-icons/`:
- **Llamador**: campana (icono de notificacion) en naranja `#FFAF22` sobre fondo navy. Archivos: `llamador-192x192.png`, `llamador-512x512.png`, `llamador-maskable-512x512.png`.
- **Consultor de precios**: codigo de barras en naranja `#FFAF22` sobre fondo navy. Archivos: `consultor-precios-192x192.png`, `consultor-precios-512x512.png`, `consultor-precios-maskable-512x512.png`.

#### Comportamiento JS del consultor de precios (`resources/js/consultor-precios.js`)

- **Fullscreen automatico**: en la primera interaccion del usuario (`pointerdown` o `keydown`) se invoca la Fullscreen API (`documentElement.requestFullscreen()`). Los navegadores solo permiten entrar a fullscreen desde un gesto del usuario; el scanner llega como `keydown` real, por lo que el primer escaneo tambien desbloquea el fullscreen. Se hace una sola vez (flag `interaccionLista`).
- **AudioContext**: se crea y reanuda en la misma primera interaccion (politica de autoplay). Si el navegador bloquea la creacion, `audioCtx` queda en `null` y el sonido se omite silenciosamente.
- **Sonido de exito (`playSuccess`)**: arpegio ascendente Do (523 Hz) → Mi (659 Hz) → Sol (784 Hz) con osciladores `triangle`, separados 85 ms, envolvente exponencial rapida (ataque 20 ms, caida 320 ms). Se dispara en `mostrarResultado()` cada vez que se encuentra un articulo. Distinto del chime de atencion del llamador (que usa una sola nota y canal distinto).

### 2.16 Pedidos Delivery / Take-Away

Modulo "espejo" de Pedidos por Mostrador (D1, spec `.claude/specs/pedidos-delivery.md`): mismas tablas satelite, mismo carrito (`Concerns/Carrito/*`), mismos services agnosticos (PrecioService, OpcionalService, CuponService, PuntosService), pero con tablas propias `pedidos_delivery*` (no columnas nullable en mostrador) y la dimension logistica que agrega: direccion georreferenciada, costo de envio, repartidores, salidas/vueltas y fondo de cambio. Tambien es la base de la **API REST v1** (Sanctum) y de la reserva de datos para la futura tienda online (consumidores globales, tiendas por sucursal).

#### Tabla: `pedidos_delivery`
Tiene TODAS las columnas de `pedidos_mostrador` (ver 2.12: numero, numero_display, identificador, numero_beeper, sucursal_id, cliente_id, datos de cliente temporal, caja_id, canal_venta_id, forma_venta_id, lista_precio_id, usuario_id, fecha, estado_pago, subtotal/iva/descuento/total/total_final, descuento general, cupon, puntos, invitaciones, observaciones, motivo_cancelacion, timestamps de estado, venta_id, convertido_at, orden_kanban, SoftDeletes) MAS las columnas propias:

| Columna | Tipo | Descripcion |
|---|---|---|
| `tipo` | enum('delivery','take_away') | Obligatorio al alta |
| `estado_pedido` | enum(...mostrador..., `en_camino`) | Agrega `en_camino`, COMPARTIDO entre delivery y take-away (ver logica 3.14) |
| `email_cliente_temporal` | varchar(150) nullable | Invitados de tienda/API |
| `direccion_entrega` | varchar(255) nullable | Snapshot inmutable (NULL en take_away) |
| `direccion_referencia` | varchar(255) nullable | Piso/depto/indicaciones |
| `localidad_entrega_id` | bigint nullable | Ref soft a localidades (config) |
| `latitud` / `longitud` | decimal(10,7) nullable | Snapshot geo |
| `zona_id` | bigint FK nullable | `delivery_zonas` que matcheo al cotizar (ON DELETE SET NULL) |
| `costo_envio` | decimal(12,2) | Fuente logistica del costo; el monto se materializa como renglon-concepto en el detalle (D17, ver 3.14) |
| `costo_envio_manual` | boolean | Pisado a mano (D7) |
| `costo_envio_usuario_id` | bigint nullable | FK logico config.users: quien lo piso |
| `distancia_km` | decimal(8,2) nullable | Calculada al cotizar (Haversine) |
| `fuera_de_alcance` | boolean | Confirmado con permiso `forzar_alcance` |
| `repartidor_id` | bigint FK nullable | `repartidores` (ON DELETE SET NULL) |
| `salida_id` | bigint FK nullable | `delivery_salidas` ACTUAL (ON DELETE SET NULL); el historial completo vive en el pivot `delivery_salida_pedidos` |
| `en_camino_at` | timestamp nullable | Metrica logistica |
| `no_entregado_motivo` | varchar(255) nullable | Ultima vuelta fallida |
| `hora_pactada_at` | timestamp nullable | Promesa de entrega/retiro (RF-15) |
| `lo_antes_posible` | boolean | Promesa ASAP del modo franjas/manual; EXCLUYENTE con `hora_pactada_at` (rev10) |
| `programado_para` | timestamp nullable | ENCARGO: dia/hora futuros del pedido (RF-T16, ver 3.14). Columna reservada en Fase 8/D22; los CUPOS por franja de esa fase siguen pendientes y no afectan a encargos |
| `datos_fiscales_snapshot` | json nullable | DNI/CUIT opcional del checkout de tienda |
| `origen` | enum('panel','tienda','api') | Default `panel` |
| `origen_referencia` | varchar(100) nullable | Id externo del integrador |
| `consumidor_id` | bigint nullable | FK logico a `config.consumidores` |
| `token_seguimiento` | char(26) UNIQUE nullable | ULID, generado en el hook `creating` de TODO pedido; credencial del seguimiento publico |

**Indices propios**: `tipo`, `(repartidor_id, estado_pedido)`, `salida_id`, `origen`, `consumidor_id`, `token_seguimiento` (unique) — ademas del espejo de indices de mostrador.

**Estados `estado_pedido`** (`App\Models\PedidoDelivery::ESTADOS`): iguales a mostrador (`borrador`, `confirmado`, `en_preparacion`, `listo`, `entregado`, `facturado`, `cancelado`) mas **`en_camino`**. `ESTADOS_DESPACHABLES` = `['confirmado', 'en_preparacion', 'listo']` (desde donde se puede despachar/pasar a "para retirar"). Ver logica de negocio 3.14 para la semantica compartida de `en_camino` y las transiciones completas.

#### Tablas satelite (espejo de las de mostrador, ver 2.12)
`pedidos_delivery_detalle`, `pedido_delivery_detalle_opcionales`, `pedido_delivery_detalle_promociones`, `pedido_delivery_promociones`, `pedidos_delivery_pagos` — mismas columnas que sus pares de mostrador, MAS:

- `pedidos_delivery_detalle.es_costo_envio` (boolean, default 0): identifica el renglon-concepto del costo de envio (D17) gestionado por `PedidoDeliveryService` (crea/actualiza/elimina al recotizar o editar), excluido de descuentos/cupones/promos/puntos y del ajuste por forma de pago.
- `pedidos_delivery_pagos.destino_fondo` (boolean, default 0) + `repartidor_fondo_id` (FK nullable a `repartidor_fondos`): el cobro contra entrega en efectivo entra al FONDO del repartidor en vez de generar `MovimientoCaja` (D13, ver 3.14).
- `pedidos_delivery.usuario_id` y `pedidos_delivery_pagos.creado_por_usuario_id` son NULLABLE (a diferencia de mostrador): pedidos de tienda/API y pagos online acreditados por webhook no tienen operador humano.

#### Tabla: `repartidores`
| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | |
| `nombre` | varchar(150) | |
| `telefono` | varchar(30) nullable | |
| `tipo` | enum('propio','tercero') default 'propio' | D3 |
| `envio_es_del_repartidor` | boolean default 0 | Si true, el costo de envio cobrado se liquida al repartidor en la rendicion (no es ingreso del comercio) |
| `user_id` | bigint nullable | FK logico `config.users` (app futura de repartidores) |
| `activo` | boolean default 1 | |
| `created_at`/`updated_at` | timestamp | |

Pivot `repartidor_sucursal` (`repartidor_id`, `sucursal_id`, UNIQUE compuesto): sucursales donde el repartidor puede operar.

#### Tabla: `repartidor_fondos` (RF-09, D4)
| Columna | Tipo | Descripcion |
|---|---|---|
| `repartidor_id` / `sucursal_id` | bigint FK | Un fondo abierto por repartidor+sucursal |
| `caja_origen_id` | bigint FK | Caja de la que salio el cambio (egreso) |
| `estado` | enum('abierto','rendido') default 'abierto' | Ciclo LARGO: puede quedar abierto entre salidas |
| `monto_inicial` | decimal(12,2) | |
| `monto_rendido` | decimal(12,2) nullable | Declarado al cerrar |
| `diferencia` | decimal(12,2) nullable | sobrante(+)/faltante(-) vs saldo teorico |
| `caja_rendicion_id` | bigint FK nullable | Caja donde ingreso la rendicion |
| `usuario_apertura_id` / `usuario_cierre_id` | bigint | FK logico config.users |
| `abierto_at` / `rendido_at` | timestamp | |

#### Tabla: `repartidor_fondo_movimientos` (append-only)
| Columna | Tipo | Descripcion |
|---|---|---|
| `fondo_id` | bigint FK CASCADE | |
| `tipo` | enum('entrega_inicial','refuerzo','cobro_pedido','vuelto','liquidacion_envios','devolucion','rendicion','ajuste') | `devolucion` agregado en rev12 (vuelta de repartidor tercero sin caja chica) |
| `monto` | decimal(12,2) | Con signo segun tipo |
| `pedido_id` | bigint FK nullable | `pedidos_delivery` (cobros/vueltos) |
| `movimiento_caja_id` | bigint FK nullable | Egreso/ingreso de caja vinculado (apertura/refuerzo/rendicion) |
| `usuario_id` | bigint | FK logico config.users |
| `detalle` | varchar(255) nullable | |
| `created_at` | timestamp | Sin `updated_at` (append-only) |

El saldo teorico del fondo se calcula sumando estos movimientos (`RepartidorService::saldoTeorico()`), nunca se persiste como columna.

#### Tabla: `delivery_zonas`
| Columna | Tipo | Descripcion |
|---|---|---|
| `sucursal_id` | bigint FK CASCADE | |
| `nombre` | varchar(100) | |
| `centro_lat` / `centro_lng` | decimal(10,7) | Legacy (v1 radio): centro del circulo. Con zonas dibujadas activas (E4) el alcance lo define `poligono` |
| `radio_km` | decimal(8,2) | Legacy: zona = circulo. Las zonas dibujadas no usan este campo para cotizar |
| `poligono` | json nullable | Coordenadas del poligono dibujado en el mapa (E4); `DeliveryZona::contienePunto()` hace ray casting. Con zonas dibujadas activas, fuera de todas ⇒ `fuera_de_alcance` (sin fallback a radio/km) |
| `costo_envio` | decimal(12,2) | Costo base de la zona; pisado por `rangos_horarios` si aplica |
| `rangos_horarios` | json nullable | E4: franjas de COSTO (no de disponibilidad) — `[{dias:[1..7], desde, hasta, costo}]`; `DeliveryZona::costoPara($hora)` resuelve el costo segun el momento |
| `orden` | int default 0 | Prioridad de match |
| `activo` | boolean default 1 | |

#### Tabla: `delivery_salidas`
| Columna | Tipo | Descripcion |
|---|---|---|
| `sucursal_id` / `repartidor_id` | bigint FK | |
| `estado` | enum('armando','en_camino','finalizada') default 'armando' | |
| `salida_at` / `vuelta_at` | timestamp nullable | |
| `usuario_id` | bigint | Quien la registro |
| `observaciones` | varchar(255) nullable | |

Un repartidor tiene **un unico viaje activo** por vez: despachar un pedido nuevo con un repartidor que ya esta `en_camino` SUMA el pedido a esa misma salida (lock `lockForUpdate`), nunca crea una salida paralela (E7).

#### Tabla: `delivery_salida_pedidos` (pivot append-only)
| Columna | Tipo | Descripcion |
|---|---|---|
| `salida_id` | bigint FK CASCADE | |
| `pedido_id` | bigint FK CASCADE | `pedidos_delivery` |
| `resultado` | enum('pendiente','entregado','no_entregado') default 'pendiente' | |
| `motivo` | varchar(255) nullable | |

Conserva TODO el historial de intentos (incluidos re-despachos); `pedidos_delivery.salida_id` solo apunta a la salida ACTUAL.

#### Cambios en tablas existentes (tenant)

- **`clientes`**: `direccion_entrega`, `direccion_entrega_referencia` (varchar(255) nullable), `latitud`/`longitud` (decimal(10,7) nullable) — domicilio de ENTREGA, separado de `direccion` (fiscal, usado por ARCA/impresion/padron y NUNCA pisado). `fecha_nacimiento` (date nullable, RF-T19, 2026-07-21, migracion aditiva `add_fecha_nacimiento_to_clientes` — itera comercios con SQL raw + try/catch, patron estandar de migracion tenant) — ver tabla `clientes` arriba.
- **`sucursales`**: `usa_delivery` (boolean), `config_delivery` (json nullable, DEFAULTS en `Sucursal::CONFIG_DELIVERY_DEFAULTS`, mergeados via `Sucursal::configDelivery()` — patron `config_llamador`), `pedido_delivery_ultimo_numero` (contador propio del correlativo), `pedido_delivery_display_ultimo_numero` / `pedido_delivery_display_segmento_at` (numeracion display PROPIA de delivery, separada del contador de mostrador desde rev9 — E3), `pedido_alerta_amarilla_min` / `pedido_alerta_roja_min` (int, defaults 15/30, COMPARTIDAS entre delivery y mostrador para las alertas visuales de demora).
- **`articulos`**: `disponible_delivery` / `disponible_take_away` / `permite_programado` (boolean default 1, RF-16; la logica de encargos que lo consume es RF-T16 — checkbox "Disponible para encargos" en `ConfiguracionTiendaArticulos` y validacion en `DeliveryEnvioService::validarProgramado()`); `orden` (int, 0), `destacado` (boolean, 0), `permite_venta_sin_stock` (boolean, 0) (RF-17); `badges_tienda` json nullable (RF-T14, 2026-07-20, `AFTER destacado`) — array `[{"tipo":"sin_tacc"}, {"tipo":"custom","texto":"..."}]`, maximo `Articulo::MAX_BADGES_TIENDA = 4`. Catalogo cerrado en `Articulo::BADGES_TIENDA` (`sin_tacc`, `vegetariano`, `vegano`, `picante`, `nuevo`, `mas_vendido`, `artesanal`, `sin_azucar`) + tipo `custom` (texto libre, `Articulo::MAX_BADGE_CUSTOM_LARGO = 30`). Icono/color de cada tipo los resuelve la TIENDA (espejo de tokens en bcn-tienda); el core solo persiste y sanea tipos.
- **`articulos_sucursales`**: `visible_tienda` (boolean, default 1) — visibilidad en el catalogo de la tienda de esa sucursal, independiente de `vendible` (pantalla POS interna).
- **`categorias`**: `imagen_path` (varchar(255) nullable), `orden` (int, 0).
- **`ventas`**: `origen_type` (varchar(30) nullable) + `origen_id` (bigint nullable) — morph al pedido de origen (NULL = venta POS directa). Indice `(origen_type, origen_id)`. Lo setean AMBAS conversiones (delivery y mostrador).

#### Tabla: `articulo_imagenes_tienda` (RF-T14, 2026-07-20)

Galeria de fotos ESPECIFICAS de la tienda por articulo (independiente de la imagen operativa `articulos.imagen_path`, que queda como fallback cuando esta vacia). Global al articulo (no por sucursal), maximo `ImagenArticuloTiendaService::MAX_IMAGENES = 5` (validado en el service, no en la BD).

| Columna | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | ID unico |
| `articulo_id` | bigint FK CASCADE | `articulos.id` |
| `path` | varchar(255) | Path relativo al disco `public`: `articulos/{comercio_id}/tienda/{uuid}.webp` |
| `orden` | int, default 0 | Orden de la galeria (drag & drop en el panel); `imagenes[0]` de la API es la foto principal |
| `created_at`, `updated_at` | timestamp | Timestamps |

**Indice**: `articulo_id`. Modelo `App\Models\ArticuloImagenTienda` (conexion `pymes_tenant`), relacion `Articulo::imagenesTienda()` (`hasMany`, `orderBy('orden')->orderBy('id')`). `ArticuloImagenTienda::url()` arma la URL root-relativa (`/storage/{path}`, mismo criterio que `Articulo::imagenUrl()`).

**`morphMap`** (`AppServiceProvider`): `'PedidoMostrador' => App\Models\PedidoMostrador`, `'PedidoDelivery' => App\Models\PedidoDelivery`, ademas de `'Articulo'`, `'Opcional'`, `'Comercio'`, `'Consumidor'`. Aliasar `PedidoMostrador` cambio su `getMorphClass()`: la migracion `normalize_cobrable_type_pedido_mostrador` actualizo las transacciones de integracion historicas que guardaban el FQCN completo.

#### Tablas en BD `config` (cross-comercio, RF-13 — reserva para la tienda online)

| Tabla | Columnas clave | Notas |
|---|---|---|
| `tiendas` | `comercio_id` FK, `sucursal_id` (FK logico tenant), `slug` UNIQUE(60), `habilitada`, `dominio_propio` UNIQUE nullable, `ga4_measurement_id` varchar(30) nullable, `meta_pixel_id` varchar(30) nullable, `tema` json nullable, `logo_path` varchar(255) nullable, `portada_path` varchar(255) nullable | UNIQUE(`comercio_id`,`sucursal_id`). La tienda es POR SUCURSAL (D15): el slug identifica comercio+sucursal, sin ambiguedad. `ga4_measurement_id`/`meta_pixel_id` (RF-T7, migracion `add_analytics_y_tema_a_tiendas`): IDs de Google Analytics 4 (formato `G-...`) y Meta Pixel (numerico); `null` = no inyectar el script de ese proveedor. `tema` (RF-T6, Principio 10): design tokens JSON PARCIAL o `null` — ver `Tienda::temaCompleto()` abajo. `logo_path`/`portada_path` (RF-T11, migracion `add_logo_portada_a_tiendas`, 2026-07-17): paths relativos al disco `public` (`tiendas/{comercio_id}/{uuid}.webp`), re-encodeados a WebP por `ImagenTiendaService` — ver mas abajo. Desde RF-T10 se alta y edita desde el panel (`Configuracion > Delivery / Take Away`, apartado "Tienda Online"), ya no solo por consola/soporte; desde RF-T11 la creacion y `habilitada` las escribe EXCLUSIVAMENTE el padre `ConfiguracionDelivery` (switch maestro) — ver "Alta y publicacion de una tienda" mas abajo |
| `rubros` | `nombre`, `slug` UNIQUE, `activo` | Catalogo global (hamburgueseria, pizzeria...). `comercios.rubro_id` (FK nullable) referencia esta tabla — CONVIVE con `comercios.rubro` (string, MCC de Mercado Pago) |
| `consumidores` | `nombre`, `email` UNIQUE, `password`, `telefono`, `fecha_nacimiento` date nullable, `email_verified_at`, `remember_token` | Cuenta GLOBAL cross-comercio (D8), guard `consumidores` + Sanctum. Auth completa (registro/login/logout/verificacion/reset) implementada en el CORE (RF-T1, Fase 0 de la spec tienda-online) — `App\Http\Controllers\Api\V1\Consumidores\AuthController`. `fecha_nacimiento` (RF-T19, 2026-07-21, migracion `add_fecha_nacimiento_to_consumidores`): se copia del checkout de CUALQUIER tienda cuando el pedido lo trae y el consumidor esta logueado — pre-llena el cumpleanios en el checkout de otras tiendas del ecosistema. Expuesto en `GET /v1/consumidores/me` |
| `consumidor_direcciones` | `consumidor_id` FK CASCADE, `alias`, `direccion`, `referencia`, `localidad_id` nullable, `latitud`/`longitud` nullable, `es_default` | Direcciones reutilizables en cualquier comercio. CRUD via `DireccionesController` (RF-T2), maximo 10 por consumidor |
| `consumidor_comercio` | `consumidor_id` FK, `comercio_id` FK, `cliente_id` (FK logico al `clientes` tenant) | UNIQUE(`consumidor_id`,`comercio_id`). Se crea SOLO segun `comercios.tienda_alta_cliente_automatica` (default OFF, D11) o manualmente ("convertir en cliente", diferido al proyecto tienda) |
| `personal_access_tokens` | Sanctum estandar (`tokenable_type/id` morph, `token`, `abilities`, `last_used_at`, `expires_at`) | Vive en CONFIG (no en tenant): los tokenables (`Comercio`, `Consumidor`) son cross-tenant. `Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)` |

`comercios` gana `rubro_id` (FK nullable a `rubros`) y `tienda_alta_cliente_automatica` (boolean, default 0).

**`Tienda::temaCompleto()`** (RF-T6, `app/Models/Tienda.php`): merge profundo (`array_replace_recursive`) de `Tienda::TEMA_DEFAULTS` con el JSON persistido en `tema` (que puede ser `null` o parcial). Las claves de `TEMA_DEFAULTS` son CONTRATO con `bcn-tienda`: agregar claves es aditivo, renombrar/quitar rompe el consumidor y exige v2 del endpoint. Shape (sub-objetos `portada`/`textos`/`redes`/`catalogo`/`destacados`/`promos` aditivos, RF-T13 2026-07-18):
```php
[
    'colores' => ['primario' => '#4f46e5', 'acento' => '#f59e0b', 'fondo' => '#f9fafb', 'superficie' => '#ffffff', 'texto' => '#111827'],
    'tipografia' => ['fuente' => 'system'],
    'radios' => 'md',
    'densidad' => 'normal',
    'portada' => ['overlay' => true, 'posicion' => 'center'],       // fade con color primario + encuadre vertical (top|center|bottom)
    'textos' => ['slogan' => '', 'descripcion' => ''],               // hero y seccion propia de la home ('' = no se muestran)
    'redes' => ['facebook' => '', 'instagram' => ''],                // URLs ('' = sin boton en el hero)
    'catalogo' => ['layout' => 'grilla'],                            // grilla|lista
    'destacados' => ['modo' => 'banner', 'adorno' => 'ninguno'],     // modo: banner|tarjeta_grande|ninguno; adorno solo en tarjeta_grande
    'promos' => ['mostrar_home' => false],                           // aviso "Promociones de hoy" en la home
]
```
Cada default de RF-T13 replica el comportamiento previo al RF: una tienda que no toca nada se ve exactamente igual que antes (overlay activo, encuadre centro, sin slogan/descripcion/redes, grilla, destacados en banner, sin aviso de promos). Un snapshot viejo de `tema` sin estas claves usa los defaults igual (tolerancia a clave ausente en ambos lados del contrato).

Catalogos cerrados (validados en `ConfiguracionTienda::guardarTienda()` y en el `GET /v1/tiendas/{slug}`): `Tienda::FUENTES_DISPONIBLES` (`system`, `inter`, `poppins`, `roboto`, `montserrat`, `lora` — self-hosted en `bcn-tienda`), `Tienda::RADIOS_DISPONIBLES` (`none`, `sm`, `md`, `lg`, `full`), `Tienda::DENSIDADES_DISPONIBLES` (`compacta`, `normal`, `amplia`), `Tienda::POSICIONES_PORTADA` (`top`, `center`, `bottom`, RF-T13), `Tienda::LAYOUTS_CATALOGO` (`grilla`, `lista`, RF-T13), `Tienda::MODOS_DESTACADOS` (`banner`, `tarjeta_grande`, `ninguno`, RF-T13), `Tienda::ADORNOS_DESTACADOS` (`glow`, `badge`, `ambos`, `ninguno`, RF-T13). `Tienda::COMPORTAMIENTO_DEFAULTS` esta reservado (array vacio, sin seteos en v1) — es el bloque `comportamiento` que devuelve la API.

**`Tienda::logoUrl()` / `Tienda::portadaUrl()`** (RF-T11): arman una URL root-relative (`/storage/{path}`, mismo criterio que `Articulo::imagenUrl()` — NUNCA `Storage::url()`, que arma con `APP_URL` y puede no coincidir con el host real del request) a partir de `logo_path`/`portada_path`, o `null` si no hay imagen cargada. La API las absolutiza con `url()` (host del request) porque `bcn-tienda` corre en otro origen y necesita URLs completas.

**`ImagenTiendaService`** (`app/Services/ImagenTiendaService.php`, RF-T11): procesa el upload del logo y la portada, mismo patron de defensas que `ImagenArticuloService` (referencia del proyecto):
- Tamano maximo server-side `MAX_SIZE_BYTES = 5MB` (ademas de la regla `image|max:5120` de Livewire).
- MIME real detectado por `finfo` (magic bytes, no por extension): whitelist `image/jpeg`, `image/png`, `image/webp` — SVG explicitamente prohibido.
- Re-encoding COMPLETO a WebP con Intervention Image (driver GD): mata payloads embebidos y EXIF; si Intervention no puede decodificar el archivo (polyglot file que paso el finfo pero no es una imagen valida) lanza excepcion.
- Logo: `scaleDown` a maximo `LOGO_MAX_DIMENSION = 800px` (cuadrado/alto). Portada: maximo `PORTADA_MAX_ANCHO = 1600` x `PORTADA_MAX_ALTO = 900`. Calidad WebP `85`.
- Nombre por UUID, path scopeado por comercio: `tiendas/{comercio_id}/{uuid}.webp` (disco `public`). Reemplaza (borra del disco) la imagen anterior al subir una nueva.
- Metodos: `actualizarLogo()`, `actualizarPortada()`, `eliminarLogo()`, `eliminarPortada()`. La tabla `tiendas` es GLOBAL (BD `config`): no hay transaccion tenant, es un `update()` de un solo registro.
- Se invoca desde `ConfiguracionTienda::guardarTienda()` (solo si hay un upload pendiente) y desde `eliminarLogo()`/`eliminarPortada()` del mismo componente. Mientras el upload esta pendiente (sin guardar) el preview usa `UploadedFile::temporaryUrl()`; si el archivo temporal expiro, degrada a la imagen ya persistida.

**`TiendaService::crearParaSucursal()`** (`app/Services/TiendaService.php`, RF-T11): reemplaza el alta que antes vivia en `ConfiguracionTienda::crearTienda()`. Idempotente (si ya existe una tienda para esa `comercio_id`+`sucursal_id` la devuelve sin duplicar), crea SIEMPRE `habilitada=false` (publicar es una decision explicita posterior) con slug sugerido (`Str::slug(nombre_comercio.' '.nombre_sucursal)`, truncado a 55 chars, sufijo numerico incremental ante colision del UNIQUE global de `slug`). Solo escribe sobre la conexion `config` (sin transaccion tenant).

**Regla del unico escritor de `habilitada`** (RF-T11): desde el rediseno, `App\Livewire\Pedidos\ConfiguracionDelivery` (el PADRE) es el UNICO componente que crea la tienda y que escribe `tiendas.habilitada`, via el switch maestro del apartado "Tienda Online" (`toggleTiendaOnline()` crea la tienda si no existe; `guardarConfig()` persiste la publicacion). `App\Livewire\Configuracion\ConfiguracionTienda` (el HIJO, embebido) ya NO tiene el campo `habilitada` ni el boton de creacion: solo edita slug, analytics, tema, logo y portada del registro YA existente, y el padre solo lo monta cuando la tienda existe. Este split evita que dos componentes Livewire distintos puedan pisarse la escritura de la misma columna.

**Visor en vivo del panel (RF-T12, 2026-07-17)**: reemplaza el drawer-mock de RF-T11 por un visor de la TIENDA REAL. El apartado "Tienda Online" pasa a layout de 2 columnas en pantallas xl+ (config a la izquierda, visor STICKY a la derecha); en <xl sigue el boton "Vista previa" que abre el drawer. Es un canal FRONTEND-ONLY: no agrega ni modifica ningun endpoint — el contrato `docs/api-v1-delivery.md` queda intacto.

- **Scope Alpine `tiendaPreview`** (`resources/js/tienda-preview.js`, importado en `bootstrap.js`): reemplaza el `x-data` inline que tenia el drawer desde RF-T11. Vive en el elemento raiz de `configuracion-tienda.blade.php` (ancestro comun del visor sticky y del drawer movil, ambos ahora hijos SIN `x-data` propio). Config inicial por DATASET (`data-origen-tienda`, `data-logo-url`, `data-portada-url`): cambiar un `data-*` no reinicializa Alpine, a diferencia de interpolar directamente en el atributo `x-data` (gotcha conocido del proyecto — morph de Livewire).
  - Observa los tokens en vivo (5 colores, fuente, radios, densidad; extendido en RF-T13, ver abajo) via `$wire.get(prop)` inicial + `$wire.$watch(prop, cb)` y, ante cada cambio, hace `postMessage` al iframe con debounce de 150ms (`enviarEstadoDebounced()`). **GOTCHA (bug corregido 2026-07-18)**: NUNCA `$wire.entangle(prop)` dentro de `init()` de un `Alpine.data()` — entangle devuelve un interceptor que Alpine solo inicializa al construir el objeto x-data (antes de `init()`); asignado en `init()` queda el objeto crudo, los watchers no disparan y `postMessage` lanza `DataCloneError`. `$wire.$watch` observa `component.reactive`, asi que refleja tanto cambios del cliente (`wire:model`) como server-side (`restablecerTema()`).
  - **Marco de celular con viewport virtual**: el iframe renderiza a 390x844 (viewport movil real) y se escala con `transform: scale(var(--tp-escala))` dentro de un marco decorativo de telefono (`tienda-preview-visor.blade.php`, clases `.tp-visor/.tp-pantalla/.tp-lienzo`); la escala baja por media queries de `max-height` para que el celular entero quede visible en pantallas bajas. Asi la proporcion tipografia/tarjetas es identica a un telefono. Del lado tienda, `preview.js` inyecta `#tienda-preview-scrollbars` (`scrollbar-width: none` + `::-webkit-scrollbar`) SOLO en modo preview para ocultar las barras sin deshabilitar el scroll, y remueve el `<style id="tienda-tema">` persistido tambien en `livewire:navigated` (el merge de head de wire:navigate puede re-inyectarlo y pisaria el estado en vivo del panel).
  - `targetOrigin` SIEMPRE estricto: el origen (`scheme://host[:port]`) de `config('tienda.url')`, calculado server-side por `ConfiguracionTienda::origenTienda()` (parsea con `parse_url()`) y expuesto como prop `origenTienda` — NUNCA `'*'`.
  - Shape del mensaje (`enviarEstado()`): `{ tipo: 'tienda-preview-estado', tema: { colores: {primario, acento, fondo, superficie, texto}, tipografia: {fuente}, radios, densidad }, logoUrl, portadaUrl }`. El bloque `tema` tiene el MISMO shape que `Tienda::temaCompleto()`/el contrato de la API, pero el mensaje en si no es parte del contrato: solo lo consume el iframe de `bcn-tienda` en modo preview (`?preview=1`, documentado del lado tienda en su `.claude/docs/preview-panel.md`).
  - El iframe, al cargar o navegar internamente, emite `{ tipo: 'tienda-preview-ready' }` por `postMessage`; el host valida `event.origin === origenTienda` y responde reenviando el estado actual (mismo patron ping/pong que la pantalla-cliente, ver seccion mas abajo).
  - `recargarIframe()` reasigna `iframe.src = iframe.src` (NUNCA `contentWindow.location`: cross-origin tira `SecurityError`).

- **Eventos Livewire nuevos** (`ConfiguracionTienda`):
  - `tienda-preview-imagenes` (dispatch en `updatedLogoUpload()`, `updatedPortadaUpload()` y en `eliminarImagen()` via `emitirImagenesPreview()`): manda `logoUrl`/`portadaUrl` calculadas server-side (`previewUrl()`, upload pendiente gana sobre lo persistido) porque son URLs server-rendered (`temporaryUrl()`), no entanglables.
  - `tienda-guardada` (dispatch al final de `guardarTienda()`): dispara `recargarIframe()` en el listener Alpine — lo que se estaba previsualizando ya quedo persistido.

- **Prop `publicadaPersistida`** (bool, montada por el PADRE `ConfiguracionDelivery` con `:publicada-persistida="$tiendaPublicadaPersistida"` y `wire:key="'config-tienda-'.($tiendaPublicadaPersistida ? 'pub' : 'nopub')"`): decide si el visor embebe el iframe real (`$publicadaPersistida && $urlPublica`, `src="{$urlPublica}?preview=1"`, `tienda-preview-visor.blade.php`) o cae al mock (`tienda-preview-mock.blade.php`, partial extraido del drawer de RF-T11 y ahora compartido por visor sticky + drawer movil). El `wire:key` fuerza un REMOUNT del hijo al publicar/despublicar, porque los props de un componente hijo Livewire 3 no se re-propagan solos tras el primer mount.

**Estetica avanzada (RF-T13, 2026-07-18)**: nuevos controles dentro del apartado "Apariencia de la tienda" de `ConfiguracionTienda`, todos persistidos en los sub-objetos de `tema` (ver shape de `Tienda::temaCompleto()` arriba). Props nuevas del componente: `portadaOverlay` (bool), `portadaPosicion`, `slogan`, `descripcion`, `redFacebook`, `redInstagram`, `catalogoLayout`, `destacadosModo`, `destacadosAdorno`, `promosMostrarHome`.

- **Validaciones** (`guardarTienda()`): `portadaPosicion`/`catalogoLayout`/`destacadosModo`/`destacadosAdorno` con `in:` contra los catalogos cerrados del modelo; `slogan` `max:120`, `descripcion` `max:1000` (ambos `nullable`); `redFacebook`/`redInstagram` con regex de host (`^https://(www\.)?(facebook\.com|fb\.com)/.+` y `^https://(www\.)?instagram\.com/.+`) — rechaza URLs de otro dominio aunque tengan formato valido. `temaDesdeForm()` hace `trim()` de slogan/descripcion/redes antes de persistir.
- **Miniatura de portada con encuadre real**: el `<img>` de la miniatura en el panel aplica `object-position: center {{ $portadaPosicion }}` — el operador ve exactamente el recorte vertical que va a ver el cliente, no solo un selector abstracto.
- **`restablecerTema()`**: desde RF-T13 el boton "Restablecer al tema default" ya NO restablece slogan/descripcion/redes (son CONTENIDO del comercio, no estetica) — arma `array_replace_recursive(Tienda::TEMA_DEFAULTS, $contenido)` preservando esos 4 campos antes de llamar `aplicarTema()`. Colores/tipografia/radios/densidad/overlay/posicion/layout/destacados/promos_home si vuelven al default.
- **Reflejo en vivo vs. server-rendered**: `resources/js/tienda-preview.js` extiende la lista de props observadas (`$wire.$watch`) con `portadaOverlay`, `portadaPosicion`, `slogan`, `descripcion`, `redFacebook`, `redInstagram` — viajan por el mismo `postMessage` debounced que los tokens de RF-T12 y el mock (`tienda-preview-mock.blade.php`) los refleja con Alpine (`x-show="portadaOverlay"` sobre un overlay `color-mix(in srgb, var(--tp-primario) 55%, transparent)`, `:style="'object-position: center ' + portadaPosicion"`, `x-show="slogan" x-text="slogan"`). `catalogoLayout`, `destacadosModo`, `destacadosAdorno` y `promosMostrarHome` son **server-rendered del lado bcn-tienda** (no viajan por postMessage): el cambio se ve recien al hacer clic en "Guardar tienda" (dispara `tienda-guardada` → `recargarIframe()`), documentado en el panel con el texto "Los cambios de esta seccion se ven al guardar".

**Configuracion de tienda POR ARTICULO (RF-T14, 2026-07-20)**: primer bloque de config de tienda a nivel ARTICULO — a diferencia del `tema` (JSON en `config.tiendas`), estos datos viven en la BD TENANT (ver tabla `articulo_imagenes_tienda` y columna `articulos.badges_tienda` arriba). Todo es ADITIVO: un articulo sin nada configurado se ve igual que antes del RF.

- **Sub-componente `App\Livewire\Configuracion\ConfiguracionTiendaArticulos`**: embebido (NO lazy full-page) dentro de `configuracion-tienda.blade.php`, seccion "Articulos de la tienda" debajo de "Presentacion del catalogo", en la columna de configuracion (el visor sticky de la derecha sigue montado por el padre). `use SucursalAware, WithFileUploads`. A diferencia del form del tema, **NO tiene boton "Guardar": cada metodo publico persiste al instante** (destacado, badges, orden, fotos) y termina llamando a `catalogoCambiado()`.
- **Visibilidad**: `queryVisibles()` replica el MISMO criterio que `CatalogoTiendaService` (activo + `articulos_sucursales.activo/vendible/visible_tienda` de la sucursal activa), sin filtrar por tipo de pedido (el panel configura el articulo para delivery y take-away a la vez). `articuloVisible($id)` y `idsVisibles()` son la unica puerta de entrada de todos los metodos de escritura del componente — un ID que no sea visible en la tienda de la sucursal activa se ignora silenciosamente (defensa ante payload manipulado del cliente, ej. un `wire:click`/evento JS con un ID ajeno).
- **Destacado**: `toggleDestacado()` invierte `articulos.destacado` (la MISMA columna que usa el modo "Tarjeta grande"/"Banner deslizable" de RF-T13). El orden del listado es **100% manual** (decision 2026-07-20): tanto el panel como `CatalogoTiendaService` ordenan `orderBy('orden')->orderBy('nombre')` — destacado NO fuerza posicion, es decoracion; el comercio ubica cada articulo con drag & drop (ej: un destacado tercero en la lista).
- **Badges**: `toggleBadge($tipo)` agrega/quita del array `badgesSel` (predefinidos) y `updatedBadgeCustom()` valida el texto libre (`max:30`, debounce 800ms del input); ambos llaman a `persistirBadges()`, que arma el array final (`[{tipo}, ...] + [{tipo:'custom', texto}]` si hay custom) y hace `$articulo->update(['badges_tienda' => $badges ?: null])`. Limite de 4 se valida ANTES de persistir (toast de error si se excede). `Articulo::badgesTienda()` (usado tanto por el panel como por `CatalogoTiendaService`) es el metodo que SANEA el JSON al leerlo: descarta tipos fuera de `BADGES_TIENDA`, `custom` sin texto o > 30 chars, y trunca a 4 — un JSON viejo/corrupto nunca rompe el catalogo publico ni el panel.
- **Galeria**: `updatedFotosUpload()` (hook de `WithFileUploads`, dispara al terminar cada upload) valida `image|max:5120` y llama a `ImagenArticuloTiendaService::agregar()` por cada archivo (soporta seleccion multiple), cortando ante el primer error (tipicamente "ya tiene el maximo de 5 fotos"). `quitarFoto($imagenId)` borra archivo + registro (`ImagenArticuloTiendaService::quitar()`). `reordenarFotos($ids)` persiste el orden del drag & drop (`ImagenArticuloTiendaService::reordenar()`, ignora IDs que no pertenezcan al articulo).
- **`ImagenArticuloTiendaService extends ImagenArticuloService`** (`app/Services/ImagenArticuloTiendaService.php`): reusa el pipeline de seguridad del padre (finfo MIME real, whitelist jpg/png/webp, resize `MAX_DIMENSION`, WebP `WEBP_QUALITY=85`) pero es 1:N (una fila por foto en `articulo_imagenes_tienda`, no una columna unica) y guarda en subcarpeta propia `articulos/{comercio_id}/tienda/{uuid}.webp`. `agregar()` valida el maximo de 5 ANTES de procesar el archivo (evita gastar CPU si ya esta lleno) y asigna `orden = max(orden) + 1` (entra al final). `reordenar()` corre dentro de `DB::connection('pymes_tenant')->transaction()`.
- **Orden de articulos y categorias (drag & drop)**: `reordenarArticulos($ids)` y `reordenarCategorias($ids)` reusan `articulos.orden`/`categorias.orden` (sin columnas nuevas) y **renumeran de a 10** (10, 20, 30…) dentro de la lista recibida — deja huecos para altas futuras sin tener que renumerar todo. Ambos filtran los IDs contra la lista de "propios" (visibles en la sucursal activa / categorias existentes) antes de escribir. Frontend: `resources/js/tienda-articulos.js` (Alpine.data `tiendaArticulos`, patron del proyecto — SortableJS bundleado via `bootstrap.js`, nada de JS inline en el Blade), tres instancias de `Sortable` por seccion: categorias (`[data-sortable-categorias]`, handle `[data-drag-handle-categoria]`, excluye la categoria virtual "Sin categoria" `id=0` del reorden), articulos (`[data-sortable-articulos]`, una instancia POR categoria — sin `group` compartido, los articulos no se pueden arrastrar entre categorias) y fotos de la galeria del editor abierto (`[data-sortable-fotos]`, inicializada con `x-init="initFotosSortable($el)"` porque el editor entra al DOM recien al abrirse).
- **Invalidacion de cache e evento al visor**: TODO metodo de escritura termina en `catalogoCambiado()`, que llama a `CatalogoTiendaService::invalidarCache($comercioId, $sucursalId)` (nuevo, forgetea la key de cache server-side del catalogo para AMBOS tipos de pedido — ver `cacheKey()`/`invalidarCache()` mas abajo, seccion API) y dispara el evento Livewire `tienda-catalogo-cambiado`. El listener Alpine en `tienda-preview.js` (`window.Livewire.on('tienda-catalogo-cambiado', ...)`) llama a `recargarIframeDebounced()` (debounce de 1200ms, mas largo que el de los tokens en vivo) para no recargar el iframe en cada micro-cambio de una rafaga (ej. subir varias fotos seguidas) — el catalogo es server-rendered del lado bcn-tienda, no hay canal `postMessage` para estos datos.
- **Permiso**: `func.tienda.config` (el mismo del resto del apartado "Tienda Online"), chequeado por `autorizado()` en cada metodo de escritura (no solo en el render): con permiso insuficiente, se ignora la accion y se dispara un toast de error.

**Toggle sol/luna en el visor (RF-T22, 2026-07-21)**: suma modo claro/oscuro FORZADO al visor en vivo (RF-T12) sin recargar la config. Frontend-only, no agrega endpoints — el contrato `docs/api-v1-delivery.md` queda intacto.

- `resources/js/tienda-preview.js`: el scope `tiendaPreview` suma `modoVisor` (string `'oscuro'|'claro'`, `null` = auto del dispositivo del que abre el panel) y `toggleModoVisor()` (alterna, primer click = oscuro, y llama a `enviarEstado()`). El campo `modo: this.modoVisor` se suma al shape del `postMessage` de `enviarEstado()` (junto a `tema`, `logoUrl`, `portadaUrl`).
- `tienda-preview-visor.blade.php`: boton circular sol/luna (icono `x-show` segun `modoVisor`) al lado del link "Abrir en pestana nueva", visible solo cuando la tienda esta publicada y persistida (mismo gate que el resto del visor real).
- Del lado `bcn-tienda`: en modo preview (`?preview=1`) acepta el campo `modo` del mensaje y setea `data-modo` en `:root` — reusa la infraestructura `data-modo` que YA existe (`app.css`, `comportamiento.modo_color`), no es un canal nuevo.

#### Permisos del modulo

Migracion `add_pedidos_delivery_menu_permisos_y_seeds` (menu + permisos) y `create_api_tiendas_y_consumidores` (`api.tokens`). Asignados a Administrador/Super Administrador via `ProvisionComercioCommand::seedRolesYPermisos()`. **No existen** permisos `.ver/.crear/.editar/.cambiar_estado` (E8): las acciones de flujo (crear/editar/cambiar estado/despachar/comandar) se gobiernan por el permiso de MENU, no por uno funcional.

| Permiso | Accion protegida |
|---|---|
| `func.pedidos_delivery.cobrar` | Cobrar pedido (rapido, desglose, confirmar planificados) |
| `func.pedidos_delivery.convertir_venta` | Convertir pedido en venta |
| `func.pedidos_delivery.cancelar` | Cancelar pedido |
| `func.pedidos_delivery.resetear_numeracion` | Reiniciar el correlativo/display de delivery |
| `func.pedidos_delivery.repartidores` | ABM de repartidores + despachar/asignar + salidas/vueltas + fondos |
| `func.pedidos_delivery.forzar_alcance` | Confirmar un pedido fuera del area de entrega |
| `func.pedidos_delivery.config` | Configuracion de delivery de la sucursal (zonas, promesa, aceptacion, calendario) |
| `func.api.tokens` | Emitir/revocar tokens de integracion de la API |
| `func.tienda.config` | Crear/publicar la tienda online de la sucursal (switch maestro en el padre `ConfiguracionDelivery`, RF-T11) y editar su configuracion (slug, analytics, tema visual, logo, portada, en el hijo `ConfiguracionTienda`) — grupo funcional propio "Tienda Online", NO usa el prefijo `pedidos_delivery` aunque vive en la misma pantalla (RF-T10, migracion `add_configuracion_delivery_menu_y_permiso_tienda`) |

Los `confirmar*` de vuelta/salida/asignacion re-chequean `repartidores` server-side (no confian solo en el gate de UI); `vueltaSalidaId` es `#[Locked]` en el componente Livewire (rev21, hardening de permisos).

#### Menu y ruta de Configuracion de Delivery (RF-T10, 2026-07-17)

La pantalla de configuracion de delivery (zonas, promesa, calendario, pedidos externos + apartado "Tienda Online") paso a tener item propio en el menu **Configuracion**: migracion `add_configuracion_delivery_menu_y_permiso_tienda` crea el `MenuItem` "Delivery / Take Away" (`slug=configuracion-delivery`, bajo el padre `configuracion`, icono `heroicon-o-truck`) apuntando a la ruta `configuracion.delivery` (`/configuracion/delivery`), que sigue siendo el mismo componente `App\Livewire\Pedidos\ConfiguracionDelivery`. La ruta vieja `pedidos.delivery.configuracion` (`/pedidos/delivery/configuracion`, usada por el engranaje del panel de Pedidos Delivery) ahora es un `redirect()->route('configuracion.delivery', [], 301)` en `routes/web.php` — no se borro para no romper links/bookmarks existentes. El permiso de MENU que gobierna el acceso sigue siendo `func.pedidos_delivery.config` (el `MenuItemObserver` crea `menu.configuracion-delivery` automaticamente al insertar el `MenuItem`, pero es el permiso funcional el que efectivamente restringe la vista dentro del componente).

#### Patrones de consulta SQL utiles

**Pedidos delivery activos de la sucursal hoy:**
```sql
SELECT p.*, c.nombre AS cliente_nombre, r.nombre AS repartidor_nombre
FROM {PREFIX}pedidos_delivery p
LEFT JOIN {PREFIX}clientes c ON p.cliente_id = c.id
LEFT JOIN {PREFIX}repartidores r ON p.repartidor_id = r.id
WHERE p.sucursal_id = ?
  AND p.estado_pedido NOT IN ('facturado', 'cancelado')
  AND DATE(p.fecha) = CURDATE()
  AND p.deleted_at IS NULL
ORDER BY p.lo_antes_posible DESC, p.hora_pactada_at ASC;
```

**Saldo teorico de un fondo de repartidor:**
```sql
SELECT COALESCE(SUM(monto), 0) AS saldo_teorico
FROM {PREFIX}repartidor_fondo_movimientos
WHERE fondo_id = ?;
```

**Total "en fondos de repartidores" (fondos abiertos de una caja, para tesoreria):**
```sql
SELECT rf.id, rf.repartidor_id,
       (SELECT COALESCE(SUM(monto), 0) FROM {PREFIX}repartidor_fondo_movimientos WHERE fondo_id = rf.id) AS saldo_teorico
FROM {PREFIX}repartidor_fondos rf
WHERE rf.caja_origen_id = ?
  AND rf.estado = 'abierto';
```

**Pedidos "por aceptar" (origen externo, sin confirmar):**
```sql
SELECT *
FROM {PREFIX}pedidos_delivery
WHERE sucursal_id = ?
  AND estado_pedido = 'borrador'
  AND origen IN ('tienda', 'api')
  AND deleted_at IS NULL
ORDER BY fecha ASC;
```

**Pedidos en una salida de reparto en curso:**
```sql
SELECT p.*
FROM {PREFIX}pedidos_delivery p
INNER JOIN {PREFIX}delivery_salidas s ON p.salida_id = s.id
WHERE s.repartidor_id = ?
  AND s.estado = 'en_camino';
```

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

**ABM de articulos (`GestionarArticulos`), UI de precio unico (2026-07-13, extendido RF-B4 hardening-circuito-precios 2026-07-14)**: el modal ya no expone ambos campos por separado. Muestra un unico campo "Precio de venta" que:
- Al **crear** un articulo: hace `wire:model` directo sobre `precio_base` (el generico), en cualquier comercio.
- Al **editar** un articulo existente: hace `wire:model` sobre `precio_sucursal` (override de `articulos_sucursales.precio_base` de la sucursal activa) y **siempre** persiste ahi al guardar — nunca vacia el override para "caer" al generico. RF-B4: esto aplica a **todo comercio, incluido mono-sucursal** (antes en mono-sucursal el modal editaba directo `precio_base`, que un cambio masivo previo podia haber dejado "muerto" detras de un override; ahora `edit()` siempre carga el precio EFECTIVO y `save()` siempre persiste sobre `articulos_sucursales`). El precio generico global (`articulos.precio_base`) deja de ser editable desde este modal una vez creado el articulo; queda como dato de fallback interno (se administrara desde Manager a futuro).
- El listado (`gestionar-articulos.blade.php`) dejo de mostrar el precio generico chico debajo del efectivo cuando hay override de sucursal.
- El historial de `override_sucursal` compara contra el precio EFECTIVO anterior (override si existia, si no el `precio_base` previo) — crear un override con el mismo valor que ya regia (ej. 500→500) no genera fila de historial (RF-B12).

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

**IVA**: Se calcula segun el `tipo_iva_id` del articulo. El precio de venta es SIEMPRE final con IVA incluido (hardening-circuito-precios, RF-A1/A2/A3, 2026-07-14): el IVA siempre se EXTRAE del precio (`neto = precio / (1 + alicuota/100)`), nunca se suma encima. La columna `precio_iva_incluido` quedo deprecada forzada a `true`; los caminos que antes sumaban IVA (rama `else` de `WithCalculoVenta`, `VentaService::crearDetalleVenta`) quedaron inalcanzables o reducidos a la semantica "precio final" con comentario.

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

### 3.8 Compras, Costos y Precios

Modulo reescrito por completo (spec `.claude/specs/compras-costos-precios.md`, D1-D22). Principio rector: **el costo se almacena SIEMPRE como costo COMPUTABLE** (neto cuando el IVA fue credito fiscal recuperable; total pagado cuando no lo fue). El campo se llama `costo_unitario_computable` (no "neto") a proposito: en compras B/C/no fiscales/monotributo CONTIENE el IVA no recuperable — es correcto contablemente (la venta genera debito pleno sin credito que lo compense).

#### 3.8.1 Ciclo de vida de la compra (D10/D11)

`estado` es SOLO ciclo de vida: `borrador` → `completada` → `cancelada`. Lo impago NUNCA se deriva del estado; siempre de `saldo_pendiente > 0`.

- **`borrador`**: editable, SIN NINGUN efecto (no toca stock, costos, ledger fiscal ni caja/cta cte). Se elimina directamente sin reversas.
- **Confirmar** (`CompraService::confirmarCompra()`, transaccion unica `DB::connection('pymes_tenant')->transaction()`), en este orden:
  1. Prorrateo del descuento global + de los conceptos que computan costo (por IMPORTE del renglon, nunca por cantidad).
  2. Calculo de `costo_unitario_computable` por renglon (ver 3.8.2).
  3. Movimiento de stock (`MovimientoStock::crearMovimientoCompra`, con el `costo_unitario_computable`, NO `precio_sin_iva`).
  4. `CostoService::registrarDesdeCompra()` — actualiza `articulo_costos` (fila sucursal + fila consolidada), upsert `articulo_proveedor`, historial.
  5. `ImpuestoService::registrarDesdeCompra()` — credito de IVA (si corresponde, ver 3.8.4) + percepciones sufridas al ledger fiscal.
  6. `CuentaCorrienteProveedorService::registrarMovimientosCompra()` — HABER por el total (+ DEBE por lo pagado en el momento).
  7. Pago inicial (si se cargo) via `PagoProveedorService::registrarPago()`.
  8. Repricing automatico (RF-11) de los articulos con `precio_administrado_por_utilidad = true`, via `CostoService::repricearArticulos()` (RF-C4, formula compartida con el cambio masivo de costos — ver 3.8.9).
  Todo el pipeline es idempotente.
- **Cancelar** (`cancelarCompra()`): reversas por CONTRAASIENTO de stock, costos (restaura `costo_ultimo` anterior si esta compra lo fijo, con fila nueva de `historial_costos` origen `cancelacion`), fiscal (patron NC cross-periodo: reversa NEGATIVA fechada hoy, el original queda activo) y cta cte de proveedor. Si tiene pagos aplicados, el usuario elige (D17): anular los pagos en cascada (bloqueado si algun renglon salio de una caja con turno cerrado) o dejarlos como saldo a favor del proveedor.
  - **RF-B3 (hardening-circuito-precios)**: la reversa de costo solo se aplica si el `costo_ultimo` VIGENTE sigue siendo el que esta compra fijo (`CostoService::revertirCostoUltimoSiCorresponde` compara contra `historialCompra->costo_nuevo`); si alguien lo edito a mano despues, la cancelacion NO lo pisa. Complemento: `CostoService::actualizarManual('ultimo')` limpia `compra_ultima_id`/`proveedor_ultimo_id` al editar a mano (el vigente paso a ser manual, no de una compra).
  - **RF-B7**: `cancelarCompra()` toma `lockForUpdate()` sobre la compra y re-chequea `estaCompletada()` DENTRO de la transaccion (mismo patron que `anularMovimientoFiscal`) — dos cancelaciones concurrentes no duplican reversas. `corregirCompra()` hereda el guard porque cancela llamando a este metodo.
- **Correccion de una completada** (D7 #12, `corregirCompra()`): una compra `completada` es INMUTABLE (no vuelve a borrador). "Corregir" = `cancelarCompra()` + `crearBorrador()` + `confirmarCompra()` en UNA transaccion (rastro cruzado en `observaciones`). Bloqueada si tiene notas de credito activas vinculadas; requiere permisos de confirmar Y cancelar.

#### 3.8.2 Formula canonica del costo (RF-01, por renglon)

```
precio_unitario de factura (neto si discrimina IVA; final si no)
  × cascada de descuentos del renglon        (1−d1/100)(1−d2/100)...
  − prorrateo del descuento global (por importe)
  + prorrateo de conceptos que computan costo (por importe, landed cost)
  = costo unitario facturado
  → ¿discrimina IVA Y el CUIT comprador es RI (esResponsableInscripto)?
       SI ⇒ ya es neto (el IVA fue credito fiscal, no integra el costo)
       NO ⇒ todo lo pagado ES el costo (incluye el IVA no recuperable)
  ÷ factor_conversion (unidad de compra → unidad de stock, D8)
  = COSTO UNITARIO COMPUTABLE (persiste en compras_detalle.costo_unitario_computable)
```

Caso RG 5003/2021 (factura A/M a comprador NO-RI): la factura viene NETA impresa, pero el IVA no recuperable ES costo ⇒ `costoComputableRenglon()` suma `neto + (neto × alicuota_no_recuperable)` cuando `discrimina AND !compradorRI`.

**Descuentos anidados** (D6): `factor = (1−d1/100) × (1−d2/100) × ... × (1−dn/100)`, `precio_efectivo = precio_lista × factor`. Ejemplo criterio de aceptacion: renglon de $1000 con "10+5+3" ⇒ `1000 × 0.90 × 0.95 × 0.97 = $829.35` (cascada, no suma de porcentajes).

**Costo promedio ponderado (PPP)**, por sucursal y consolidado:
```
nuevo_ppp = (stock_previo × ppp_previo + cantidad × costo_unitario_computable) / (stock_previo + cantidad)
(si ppp_previo es NULL ⇒ nuevo_ppp = costo_unitario_computable; el stock previo SIN costo no pondera)
```

#### 3.8.3 Cascada de utilidad y precio sugerido (RF-08/RF-09, D2)

```
utilidad objetivo = articulo.utilidad_porcentaje ?? categoria.utilidad_porcentaje ?? config.utilidad_default

alic_efectiva (D21, unica puerta CostoService::alicuotaEfectiva()):
  comercioComputaIva(sucursal) = true (CUIT default es RI)
      ⇒ alicuota del TipoIva del articulo
  comercio que NO computa IVA (monotributo/exento)
      ⇒ 0 en AMBAS formulas siguientes (para un no-RI el costo es bruto y TODO el precio es ingreso)
```

RF-A3 (hardening-circuito-precios, 2026-07-14): con el precio de venta SIEMPRE final con IVA incluido, `alicuotaEfectiva()` dejo de condicionar por `articulo.precio_iva_incluido` (columna deprecada) — la alicuota efectiva queda determinada SOLO por `comercioComputaIva()`. `comercioComputaIva()` paso de `private` a `public` para que el preview del cambio masivo de costos (Bloque C) la consulte una sola vez por lote.

`CostoService::comercioComputaIva()` — que CUIT determina si "el comercio computa IVA":
- **Con sucursal** (precio sugerido/margen de esa sucursal): CUIT `es_principal = true` del pivot `sucursal_cuit`, o el primero asignado si no hay marcado principal.
- **Sin sucursal** (consolidado): CUIT principal (o primer CUIT) de la **sucursal PRINCIPAL** del comercio (`Sucursal::principal()`) — fix 2026-07-13, antes usaba `Cuit::activos()->first()` (orden arbitrario, podia traer un CUIT Monotributo cuando el principal era RI o viceversa). Fallback final si la sucursal no tiene CUIT: primer CUIT activo del comercio.
- Sin ningun CUIT configurado ⇒ no computa IVA (pricing informal: costo bruto, precio pleno).
- Si conviven CUITs activos con condiciones de IVA distintas, `ConfiguracionEmpresa::cuitsCondicionesMixtas` (tab CUITs) muestra un aviso: el sugerido/margen puede no coincidir con el CUIT del punto de venta que factura.

```

precio_final_sugerido = costo_rector × (1 + utilidad/100) × (1 + alic_efectiva/100)  → redondeo sobre el FINAL

margen_real (formula inversa, misma division que hace la venta):
  neto_venta  = precio_final ÷ (1 + alic_efectiva/100)
  margen_real = (neto_venta − costo_rector) / costo_rector × 100
```

El costo rector para pricing es siempre `costo_ultimo` (v1; `configuracion_costos.costo_rector` fijo en `'ultimo'`). Ejemplo de aceptacion: costo neto $100, utilidad 40%, IVA 21% ⇒ precio final sugerido $169.40 (antes de redondeo).

**Revision de precios post-compra** (RF-10, `RevisionPreciosCompra`): al confirmar una compra, lista los articulos cuyo margen real quedo por debajo del objetivo. Es RETOMABLE — calcula siempre contra costo y precio VIGENTES (no una foto del momento de la compra). Aplicar en lote escribe el precio (override de la sucursal de la compra si existe, si no el global) + `HistorialPrecio::registrar()` con `origen = 'revision_compra'`.
- **RF-B8 (hardening-circuito-precios)**: una fila cuyo `precio_nuevo` queda `<= costo` (margen <= 0, incluido $0) arranca con `seleccionado = false` y el flag `bajo_costo = true` (badge amarillo "bajo costo" en la UI); editar el precio a mano re-evalua el piso en cada cambio (`updated()` del componente). `aplicar()` solo procesa filas marcadas — re-marcar una fila con el badge visible es la confirmacion explicita del usuario para aplicar un precio bajo costo. El parseo de `precio_nuevo` usa `num()` (coma decimal aceptada; mas de un separador decimal ⇒ 0), mismo criterio que `EditorCompra`.

**Repricing automatico** (RF-11): articulos con `precio_administrado_por_utilidad = true` se repricean solos al confirmar la compra (mismo calculo + redondeo `'ninguno'` en v1), `HistorialPrecio` con `origen = 'utilidad_automatica'`. RF-C4 (hardening-circuito-precios): la formula se extrajo de `CompraService::repricearAutomaticos()` a `CostoService::repricearArticulos(array $articuloIds, int $sucursalId, int $usuarioId, ?string $detalle = null): array` — UNA sola implementacion compartida entre el paso 8 de `confirmarCompra()` y el cambio masivo de costos (Bloque C, ver 3.8.9). Filtra internamente a los articulos con el flag activado; el caller solo pasa los IDs candidatos.

**Boton "Usar como precio"** (`GestionarArticulos::aplicarPrecioSugerido()`, ABM de articulos, fix 2026-07-13): copia `cuentaSugerida()['sugerido']` al campo de precio del modal en memoria (sin persistir nada por si solo); el `Guardar` posterior lo escribe por el camino normal de edicion de articulo, con su `HistorialPrecio` estandar (`origen = 'articulo_editar'` u `'override_sucursal'`).

#### 3.8.4 Circuito fiscal de la compra (gate del credito)

- El credito de IVA se envia al ledger fiscal SOLO si: `fiscal AND el tipo de comprobante discrimina AND el CUIT comprador es RI`. `ImpuestoService::registrarDesdeCompra()` NO gatea por condicion IVA — el gate es responsabilidad del `CompraService` (caller).
- **Fuente CANONICA del credito = `compra_ivas`** (el desglose de la factura fisica), nunca la suma de renglones.
- **Periodo del credito = `fecha_comprobante`** (no la fecha de carga): una factura de junio cargada en julio computa el credito en JUNIO.
- Toggle **compra no fiscal** (D15): desactiva TODO el calculo de impuestos (sin `compra_ivas`, sin percepciones, nada al ledger); el total pagado es directamente el costo.
- Cancelacion fiscal: patron NC cross-periodo (`anularDesdeCompra`, reversa negativa fechada hoy; el original queda activo y suman cero).
- **Nota de credito de proveedor** (RF-21, D18): fila de `compras` con `compra_origen_id`. Efectos inversos PARCIALES al confirmar: stock egreso por lo devuelto, fiscal con el desglose PROPIO de la NC (no derivado, solo precargado como sugerencia) en el periodo de la NC, cta cte tipo `nota_credito` que baja el saldo de la compra origen (el excedente genera saldo a favor). Los costos (`costo_ultimo`/PPP) NO se recalculan.
  - **RF-B10 (hardening-circuito-precios)**: `EditorCompra::precargarNcDesdeOrigen()` tambien precarga las **percepciones** de la compra origen (`impuesto_id`, `base_imponible`, `alicuota`, `monto` y el `coeficiente` SNAPSHOT de la origen — no la config vigente actual, que podria haber cambiado desde entonces), editables. `advertencias()` agrega un aviso NO bloqueante si el IVA o las percepciones cargadas en la NC superan a los de la compra origen (tolerancia $0,01).
  - **RF-B11**: `aplicarNotaCredito()` persiste en la propia NC (`compras.saldo_pendiente` de la fila NC, re-semantizado como "monto aplicado contra la origen") lo REALMENTE aplicado — no el total de la NC. `restaurarSaldoOrigenPorNcCancelada()` usa ese valor (con ledger: el DEBE del movimiento; sin ledger/proveedor sin CC: `nc.saldo_pendiente`) en vez de asumir `nc.total`, evitando que una NC parcialmente aplicada infle el saldo de la compra origen al cancelarse.

#### 3.8.5 Cuenta corriente de proveedores y pagos (RF-18/RF-19, D12)

Ledger `movimientos_cuenta_corriente_proveedor`, espejo de clientes pero con semantica de **PASIVO**: HABER aumenta la deuda (compra), DEBE la reduce (pago). Saldo on-the-fly sobre movimientos `activo` (nunca persiste en la fila). La operatoria de pago (deuda, FIFO, aging) es SIEMPRE de la sucursal ACTIVA (D19); `proveedores.saldo_cache` es el consolidado informativo del comercio.

- Proveedor sin `tiene_cuenta_corriente`: solo admite compras al contado, sin filas de ledger (aunque el pago igual genera `PagoProveedor` + egreso, para rastro auditable).
- Compra confirmada de proveedor con CC: HABER por el total + DEBE por lo pagado en el momento (contado total ⇒ par HABER/DEBE con saldo 0).
- **Origen de fondos del pago** (D14): por default, la CAJA ACTIVA (valida apertura y saldo). Con el permiso `func.compras.pagar_avanzado`, por renglon del desglose: otra caja, efectivo de Tesoreria (`TesoreriaService::registrarEgresoExterno()`) o una cuenta de empresa (`CuentaEmpresaService::registrarMovimientoAutomatico()`, egreso).
- **Anulacion de orden de pago** (D16): el bloqueo por `cierre_turno_id` aplica SOLO a renglones con origen `'caja'` cuyo turno esta cerrado; una OP 100% tesoreria/cuenta de empresa se anula siempre.
- Anticipos y saldo a favor: mismos tipos que clientes (`anticipo`, `uso_saldo_favor`).

#### 3.8.6 Unidades de compra vs stock (D8) y conceptos de pie (D9)

- `articulo_proveedor.factor_conversion`: se compra en la unidad del proveedor (ej. "bulto x12") y se stockea en unidades propias. `compras_detalle.cantidad_comprada` (bultos) × `factor_conversion` = `compras_detalle.cantidad` (SIEMPRE en stock).
- `compra_conceptos`: renglones no-articulo (flete, impuestos internos, envases, otro) con flag `computa_costo` — si esta activo, el importe se prorratea a los renglones POR IMPORTE (landed cost). Caso clave: los impuestos internos de bebidas SI son costo real (no se recuperan).
- **Precarga de descuentos al seleccionar articulo** (`EditorCompra::seleccionarArticuloFila()`, fix 2026-07-13): la celda de descuentos del renglon se precarga en este orden de precedencia — 1) los descuentos del `compras_detalle` de la ULTIMA compra `completada` de ese articulo a ese proveedor (`descuentosUltimaCompra()`: excluye notas de credito, filtra descuentos `> 0`, `orderByDesc('id')`); 2) si no hubo compra previa (o la ultima no tuvo descuentos), cae al fallback `articulo_proveedor.descuentos_habituales`. Motivo: los descuentos habituales del catalogo suelen quedar desactualizados; lo que efectivamente factura el proveedor la ultima vez es mejor senal.

#### 3.8.7 Permisos del modulo (RF-20)

`func.compras.crear` (cargar/editar borradores) es distinto de `func.compras.confirmar` (mueve stock/costos/ledger/plata) y de `func.compras.cancelar`/`func.compras.pagar`/`func.compras.pagar_avanzado` (elegir origen de fondos alternativo)/`func.compras.revisar_precios` (aplicar RF-10/RF-11). `func.costos.ver`/`func.costos.editar` gatean TODA visibilidad de costos y margenes en el sistema (GestionarArticulos, GestionarCategorias, ConfiguracionEmpresa, el editor y el detalle de compras) — sin `func.costos.ver` no se muestran ni columnas ni modales de costo. Menu: grupo padre "Compras" (Compras / Proveedores / Pagos a proveedores / Reportes).

**Compras internas**: si el proveedor es una sucursal interna (`es_sucursal_interna = true`), la compra representa una transferencia fiscal entre sucursales (modulo propio de transferencias inter-sucursal, fuera de alcance de este spec — ver seccion de fases futuras del spec).

#### 3.8.8 Factura de servicio (D23), percepciones habituales por proveedor (D24) y coeficiente de computabilidad (D25)

Incrementos post-merge del spec (D23/D24 acordados 2026-07-13, D25 2026-07-14; fuera del alcance original D1-D22). Misma pantalla y mismo modelo `Compra`, solo agregan una modalidad y dos refinamientos de percepciones.

**D23 — Factura de servicio** (`compras.es_servicio`, ej. luz, gas, alquiler, honorarios):

- Sin grilla de articulos: nada de stock, costos ni repricing automatico. El detalle son los `compra_conceptos` (ver tabla arriba).
- `CompraService::validarConfirmacion()`: si `es_servicio`, exige `detalles->isEmpty()` (rechaza renglones de articulo cargados), `conceptos->isNotEmpty()` (al menos 1 renglon de detalle) y `cuenta_compra_id !== null` (obligatoria, es el eje del reporte D22 RF-22). Sin NC (`esNotaCredito()`), la regla `detalles->isEmpty()` normal (compra sin renglones = error) queda excluida para servicio.
- `CompraService::confirmarCompra()`: saltea el paso 4 (`CostoService::registrarDesdeCompra()`) y el paso 8 (`repricearAutomaticos()`) cuando `esServicio()` es true (ademas de cuando es NC). `cancelarCompra()` saltea `revertirCostoUltimoSiCorresponde()` en el mismo caso. Como `resolverProrrateosYComputables()` retorna temprano si `detalles->isEmpty()` (siempre true en servicio), la parte no computable de las percepciones (D25) nunca se prorratea a un renglon — queda implicita en el `total` de la compra (que SI incluye el `monto` completo de la percepcion, ver `calcularTotales()`), que es lo que se le imputa a la `cuenta_compra` para los reportes de gasto.
- `EditorCompra`: `totales()` y `calcularSugerenciaFiscal()` ignoran la grilla de renglones si `esServicio`; `construirPayload()` fuerza `renglones = []` y `computa_costo = false` en todos los conceptos. `validarParaGuardar()` valida conceptos+cuenta en vez de renglones.
- **Precarga desde el proveedor**: `seleccionarProveedor()` pisa `$this->esServicio = $proveedor->es_servicio` (igual criterio que la letra sugerida — editable despues).
- **NC de un servicio**: `precargarNcDesdeOrigen()` copia `esServicio` de la compra origen y precarga `conceptos` (no `renglones`) desde `origen->conceptos`.
- Descuento global oculto en servicio (aplica solo a renglones, que no existen).
- Vistas: badge "Servicio" (sky) en listado y detalle de compra; el detalle oculta la tabla de renglones.

**D24 — Percepciones habituales por proveedor** (`proveedores.percepciones_habituales`):

- UI (fix post-D24, 2026-07-13/14): el repetidor inline que originalmente vivia dentro del form del ABM de `GestionarProveedores` se saco de ahi. Ahora es el componente embebido **`App\Livewire\Compras\ProveedorImpuestos`**, espejo de `Clientes\ClienteImpuestos`: modal propio abierto via evento `abrir-impuestos-proveedor` con `{ proveedorId: N }` (disparado desde la fila del ABM — icono de documento en desktop, boton "Fiscal" en movil). Combobox de alta rapida (`getImpuestosDisponiblesProperty()`, catalogo filtrado a `naturaleza_default = 'percepcion'`, excluye los ya configurados) + alicuota editable por fila; persiste TODO el perfil de una vez en `guardar()`. No es SucursalAware (perfil global al proveedor).
- `EditorCompra::precargarPercepcionesHabituales()` se invoca desde `seleccionarProveedor()`: si el proveedor tiene percepciones habituales Y la compra actual es fiscal Y NO hay percepciones ya cargadas (impuesto o monto > 0 en algun renglon existente), precarga renglones `{impuesto_id, alicuota, monto: '', base_imponible: '', coeficiente: <default D25>, auto: true}`.
- No reemplaza percepciones cargadas manualmente por el usuario (chequeo de "cargadas" antes de precargar).
- Sigue bloqueado (no codeado) el calculo automatico de percepciones IIBB por jurisdiccion/Convenio Multilateral — depende de la respuesta pendiente del contador (ver seccion 3.13 Sistema Impositivo).

**D25 — Coeficiente de computabilidad de percepciones sufridas** (`cuit_impuesto_configs.coeficiente_computable`, `compra_percepciones.coeficiente`):

- Motivacion: una percepcion sufrida (tipicamente IIBB) puede no ser 100% credito fiscal si el CUIT comprador no esta inscripto (o esta parcialmente inscripto) en esa jurisdiccion. La parte no computable no se pierde: es costo real de la mercaderia (o gasto, en factura de servicio).
- **Default por CUIT+impuesto**: `cuit_impuesto_configs.coeficiente_computable` (0-1). Editable en el modal "Impuestos" del CUIT (Configuracion → Empresa → pestana CUITs). NULL = deriva de `inscripto` (1.00 si inscripto, 0.00 si no).
- **Snapshot por compra**: `compra_percepciones.coeficiente` (0-1), copiado del default al agregar el renglon y editable por comprobante (la factura real puede diferir del criterio general). NULL en la fila persistida = legado (percepciones cargadas antes de D25) o compra no fiscal ⇒ tratado como 100% computable en todo el pipeline, sin cambiar el comportamiento historico.
- **`EditorCompra::coeficientePercepcionDefault(?int $impuestoId)`**: unica puerta de calculo del default en el editor.
  - **RF-B1 (hardening-circuito-precios)**: si el CUIT comprador cargado NO es Responsable Inscripto (`Cuit::condicionIva->esResponsableInscripto()`), devuelve `'0'` SIEMPRE — incluida la percepcion de IVA. Sin credito fiscal posible, el 100% del monto va al costo. Este chequeo se evalua ANTES que el resto de las reglas.
  - Impuesto tipo `Impuesto::TIPO_IVA` (con comprador RI) ⇒ siempre `1` (credito pleno, el coeficiente configurable NO aplica a percepciones de IVA).
  - Sin `cuitId` cargado en la compra ⇒ `0`.
  - Con `cuitId` RI: busca `CuitImpuestoConfig::where('cuit_id', ...)->where('impuesto_id', ...)` **VIGENTE a la fecha del comprobante** (RF-B5: `->vigentes($fechaComprobante)->orderByRaw('vigente_desde IS NULL, vigente_desde DESC')`, mismo criterio que `ImpuestoService::configVigente()` — ya no toma la primera fila a ciegas). Sin config vigente ⇒ `0` (no inscripto en la jurisdiccion, todo a costo). Con config: usa `coeficiente_computable` si no es NULL, si no `1` si `inscripto` o `0` si no.
  - Se recalcula: al agregar una percepcion nueva, al precargar percepciones habituales del proveedor, al cambiar el `impuesto_id` de un renglon, y para TODOS los renglones que siguen en modo `auto` cuando cambia el `cuitId` de la compra.
- **Base/monto sugeridos (`auto`)**: cada renglon de percepcion tiene un flag `auto` en memoria (no persiste). Mientras `auto = true`, `sugerirMontosPercepciones()` recalcula `base_imponible = Σ bases gravadas del desglose de IVA (compra_ivas en memoria)` y `monto = base × alicuota / 100` cada vez que cambia el desglose de IVA o la alicuota del renglon. Tipear `base_imponible` o `monto` a mano pone `auto = false` para ESE renglon (los demas siguen auto). Se dispara desde: `sugerirDesgloseFiscal()`, cambios en `ivas.*`/`netoNoGravado`/`netoExento`, y al precargar percepciones habituales.
- **Persistencia**: `construirPayload()` normaliza el coeficiente tipeado a `[0, 1]` (clamp) y lo manda como `null` si el campo quedo vacio (no como `0`) — la distincion importa: vacio = legado/100% computable, `0` explicito = "no inscripto, todo a costo".
- **RF-B6 (hardening-circuito-precios)**: `EditorCompra::validarParaGuardar()` bloquea (excepcion) si algun renglon de percepcion tiene `monto > 0` sin `impuesto_id` seleccionado — evita que el payload descarte silenciosamente ese renglon y el total guardado no coincida con lo que muestra el editor. `totales()` tambien excluye del total de percepciones cualquier renglon sin impuesto mientras se esta cargando (coherencia visual antes de guardar).
- **Efecto en el ledger fiscal** (`ImpuestoService::registrarDesdeCompra()`): por cada `compra_percepciones`, el monto que va a `movimientos_fiscales` (naturaleza `percepcion`, sentido `sufrido`) es `round(abs(monto) × coeficiente_efectivo, 2)` donde `coeficiente_efectivo = coeficiente ?? 1.0`. Si el coeficiente efectivo es 0, no se genera movimiento (monto <= 0 se filtra).
- **Efecto en el costo** (`CompraService::resolverProrrateosYComputables()` + `CostoService::costoComputableRenglon()`): `percepcionesCosto = Σ round(monto × (1 − coeficiente_efectivo), 2)` de TODAS las percepciones de la compra, prorrateado POR IMPORTE entre los renglones (misma funcion `prorratearPorImporte()` que el descuento global y los conceptos). El renglon recibe esa porcion en la clave transitoria `percepciones_costo_monto`, que `costoComputableRenglon()` suma al importe DESPUES del gross-up de IVA no recuperable (RG 5003) y ANTES de dividir por `factor_conversion` — la percepcion es un tributo sobre la operacion, no una base gravada. Solo aplica a compras con renglones (`detalles->isNotEmpty()`); en factura de servicio el efecto queda implicito en el total imputado a la cuenta de compra (ver D23 arriba).
- **RF-B1 (hardening-circuito-precios, "nada se evapora")**: `resolverProrrateosYComputables()` calcula `$compradorEsRI = $this->compradorEsRI($compra)` UNA vez; si es `false`, el `coeficiente_efectivo` de TODA percepcion de la compra se fuerza a `0` para el calculo de costo, sin importar lo cargado en el renglon (`$compradorEsRI ? ($p->coeficiente ?? 1) : 0`). La matriz cierra 100% en todas las filas: comprador RI ⇒ `coef` al ledger fiscal + `(1−coef)` al costo; comprador no-RI ⇒ el 100% de cada percepcion pasa al costo, cero al ledger.

#### 3.8.9 Cambio masivo extendido a COSTOS (Bloque C, hardening-circuito-precios)

`App\Livewire\Articulos\CambioMasivoPrecios` gano un selector **`objetivoCambio`** que decide sobre que se aplica el mismo ajuste (%/monto) configurado en el paso 1: `'precio'` (default, comportamiento clasico) | `'costo'` | `'ambos'` (mismo % a los dos, con precio y costo desacoplados en su calculo).

- **Gate de permiso (RF-C1)**: `puedeEditarCostos()` = `hasPermissionTo('func.costos.editar')`. Sin el permiso, el selector "Aplicar sobre" ni se renderiza y `objetivoCambio` no puede salir de `'precio'` — hay defensa server-side en `updatedObjetivoCambio()`, `siguientePaso()` y `aplicarCambios()` (triple chequeo, no confia solo en ocultar la UI).
- **Modo `'costo'` (RF-C2)**: el ajuste se aplica sobre `costo_ultimo` de la fila de `articulo_costos` de la **sucursal activa** de cada articulo filtrado, via `CostoService::actualizarManual($articulo, $sucursalId, 'ultimo', $costoNuevo, $usuarioId, origen: 'masivo')` — misma puerta unica que la edicion manual del ABM, con `historial_costos.origen = 'masivo'` (ver migracion `add_masivo_a_historial_costos_origen`, el ENUM no traia ese valor). El calculo del costo nuevo (`calcularNuevoCosto()`) usa el mismo % configurado en el paso 1 pero **nunca redondea** (a diferencia del precio): 4 decimales, coherente con el resto de la cadena de costos. Articulo sin costo base (ni en la sucursal ni en el consolidado) ⇒ `sin_costo = true` en el preview, se saltea (no hay base sobre la cual aplicar %).
  - Sub-opcion `actualizarPrecioTrasCosto`: `'no'` (default, solo costo) | `'automatico'` — tras actualizar los costos, llama a `CostoService::repricearArticulos($idsConCostoActualizado, $sucursalId, $usuarioId, __('cambio masivo de costos'))` (RF-C4, la misma formula que usa `CompraService::repricearAutomaticos()` en el paso 8 de `confirmarCompra`): SOLO los articulos con `precio_administrado_por_utilidad = true` dentro del lote se repricean con la formula del sugerido; los que no tienen el flag no se tocan.
- **Modo `'ambos'` (RF-C3)**: aplica el mismo % a `costo_ultimo` (via `actualizarManual`, origen `'masivo'`) Y al precio de venta efectivo (override de `articulos_sucursales` de la sucursal activa, mismo camino que el modo `'precio'`) — dos historiales independientes (`historial_costos` + `HistorialPrecio` origen `'masivo_sucursal'`).
- **Preview** (`procesarPreview()`/`agregarArticuloManual()`): en modos que tocan costo, agrega columnas `costo_viejo`/`costo_nuevo`/`margen_nuevo` por fila. El margen usa la misma alicuota efectiva y division que la venta (`neto = precio_nuevo / (1 + alicuota/100)`), consultando `CostoService::comercioComputaIva()` (publica desde este spec) UNA sola vez para todo el lote.
- **Programacion**: `programarCambios()` sigue restringida a `objetivoCambio === 'precio'` — el circuito de `CambioPrecioProgramado` no conoce costos; los modos costo/ambos siempre se aplican al instante (`modoAplicacion` se fuerza a `'ahora'` al cambiar de objetivo).
- **Transaccion unica**: `aplicarCambios()` corre todo (costos + precios + repricing) dentro de un solo `beginTransaction()/commit()` de `pymes_tenant` (rollback en catch); el mensaje de exito resume por separado articulos de precio actualizados, costos actualizados y repriceados por utilidad automatica.

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

#### Clasificacion gravado/exento (regla 0% unica, hardening fiscal tanda 2 RF-V4)

Una sola regla en TODO el sistema: **alicuota 0% = EXENTO** (`ImpOpEx`, `neto_exento`); **alicuota > 0% = GRAVADO** (`AlicIva[]`, `neto_gravado`). Antes de este fix, `ComprobanteFiscalService::calcularDetallesIva()` (rama service) clasificaba 0% como exento, pero la rama que consume el `desglose_iva` armado por el frontend (`clasificarDesgloseFrontend`) acumulaba todo — incluido 0% — en `neto_gravado`: el mismo item 0% quedaba clasificado distinto segun el camino de emision.

- `formatearDesgloseParaAFIP()` y `recalcularDesgloseIvaFiscal()` (`WithPagosDesglose`) exponen explicitamente las claves `neto_gravado` y `neto_exento` (antes solo `total_neto` sumando todas las alicuotas).
- El residuo de redondeo del desglose fiscal (para que `Σneto + Σiva == montoFacturaFiscal` exacto) se absorbe SIEMPRE en una alicuota GRAVADA, nunca en la fila exenta — tanto en venta directa (`formatearDesgloseParaAFIP`) como en conversion de pedidos (`desgloseIvaProporcional` de `PedidoDeliveryService`).
- Este cambio estructural es el que habilita la base de percepcion sobre neto gravado (ver mas abajo).

#### Base de percepcion = neto gravado (hardening fiscal tanda 2 RF-V1)

La base imponible de toda percepcion aplicada en ventas es el **neto GRAVADO** (alicuotas > 0%); el neto exento/0% nunca integra la base. Antes de este fix, `WithPagosDesglose::aplicarPercepcionFiscal()` usaba `desgloseIvaFiscal['total_neto']`, que sumaba TODAS las alicuotas incluida 0% — en carritos con items exentos, la percepcion se cobraba de mas.

- Fuentes de verdad: `WithPagosDesglose::netoGravadoDelResultado()` (venta directa, `NuevaVenta`) y `desgloseIvaFiscal['neto_gravado']` en el resto de los caminos.
- **Cambio de monto esperado**: en ventas con items exentos/0% a clientes percibidos, la percepcion cobrada es MENOR que antes de este fix (correccion, no regresion). Se mantiene el invariante cobrado == facturado.

#### Percepciones aplicadas en ventas (Fase 5b)

Cuando el CUIT del punto de venta actua como **agente de percepcion** (`cuit_impuesto_configs.es_agente_percepcion = true`) y el receptor es **Responsable Inscripto**, el sistema calcula y cobra percepciones (IIBB y/o IVA) que el comercio debe depositar ante el fisco.

**Cuándo se percibe**:
- La venta va a emitir comprobante fiscal (automatico o manual).
- El cliente tiene condicion IVA = Responsable Inscripto.
- El CUIT del PV tiene al menos un impuesto configurado como agente de percepcion con alicuota vigente y base imponible superior al minimo configurado (`alicuota_minimo_base`).

**Flujo (venta directa — `NuevaVenta`):**
1. Al seleccionar un cliente RI, tildar el checkbox de factura fiscal o modificar el carrito, `WithCalculoVenta` y `WithBusquedaClientes` llaman a `calcularMontoFacturaFiscal()`.
2. `aplicarPercepcionFiscal()` llama a `ImpuestoService::calcularPercepcionesComprobante()`, que a su vez llama a `calcularTributos()` con el CUIT del PV y la condicion IVA del cliente.
3. El monto de percepcion (`percepcionMonto`) se suma al `montoFacturaFiscal` y se distribuye proporcionalmente entre los pagos fiscales del desglose (`distribuirPercepcionEnDesglose`).
4. Al confirmar la venta, los tributos calculados se pasan como `opciones['tributos']` a `ComprobanteFiscalService::crearComprobanteFiscal()`.
5. El service persiste el desglose en `comprobante_fiscal_tributos`, ajusta `comprobantes_fiscales.tributos` e informa `ImpTrib` a AFIP.
6. Post-CAE, `ImpuestoService::registrarDesdeComprobante()` crea en `movimientos_fiscales` un movimiento por cada tributo del comprobante con `sentido = aplicado` y `naturaleza = percepcion`.

**Flujo (pago mixto con desglose):**
- La percepcion se distribuye proporcionalmente entre los pagos marcados con `factura_fiscal = true`, modificando su `monto_final`.
- El campo `percepcion` en cada entrada del desglose registra la cuota asignada (permite recalculos idempotentes).
- La base para calcular el monto a facturar descuenta la percepcion ya distribuida en los `monto_final` fiscales (evita recursion: la base gravada son los bienes, no los bienes + percepcion).

**Invariante cobrado == facturado**: el total que paga el cliente (incluyendo la percepcion) es exactamente el `ImpTotal` que se informa a AFIP (`ImpNeto + ImpIVA + ImpTrib`). El motor nunca autopercibe si no cobro la percepcion previamente.

**Notas de credito**: `crearNotaCredito()` carga `tributosDetalle` del comprobante original y replica el desglose de tributos en la NC. El `ImpTrib` de la NC refleja el mismo monto de percepciones que el comprobante original.

**Redondeo fiscal**: el IVA de la ultima alicuota absorbe el residuo de redondeo para garantizar `Σneto + Σiva == montoFacturaFiscal` exacto. AFIP tolera que el IVA difiera ±0.01 de `neto × alicuota`, pero no que `ImpTotal != ImpNeto + ImpIVA + ImpTrib`. El neto de cada alicuota no se modifica.

**Fix AFIP 10051 — desglose IVA con forma de pago con ajuste (Fase 10a)**: `formatearDesgloseParaAFIP` y `recalcularDesgloseIvaFiscal` (en `WithPagosDesglose`) ahora usan `neto_con_ajuste_fp` e `iva_con_ajuste_fp` en lugar de `neto` e `iva` cuando estan presentes. Esto garantiza que `AlicIVA = neto_ajustado × alicuota` cuando la forma de pago tiene descuento o recargo, evitando el rechazo AFIP 10051 ("IVA no coincide con base imponible").

**Registro en ledger**: los movimientos de percepcion aplicada NO integran la posicion de IVA propia del comercio. Aparecen como deuda a depositar ante el fisco en la posicion de IIBB/IVA (panel "percepciones aplicadas como agente").

**Metodo en ImpuestoService**:
- `calcularPercepcionesComprobante(Cuit, ?Cliente, float $netoGravado, ?string $jurisdiccion, ?Carbon $fecha): array` — wrapper sobre `calcularTributos` que recibe el cliente directamente (Fase 10a). Garantiza mismo origen de verdad en el cobro y en la emision.

**Propiedades de estado en NuevaVenta / WithPagosDesglose**:
- `percepcionMonto` (float): suma total de percepciones del comprobante actual.
- `percepcionTributos` (array): detalle por impuesto (`impuesto_id`, `codigo_arca`, `base_imponible`, `alicuota`, `monto`). Se pasa como `opciones['tributos']` al service.

**Accessor `VentaPago::percepcion` (Fase 10a)**:
Accessor derivado (no persiste columna) que reconstruye el monto de percepcion incluido en el pago:
```
percepcion = monto_final - monto_base - monto_ajuste - recargo_cuotas_monto
```
Devuelve 0.0 si el resultado es menor a 0.009 (umbral anti-ruido de redondeo). Usado por la vista de detalle de venta para mostrar la linea "incl. percep." en cards moviles y tabla desktop. El monto autoritativo del impuesto vive en `comprobante_fiscal_tributos`; este accessor solo sirve para mostrar al usuario cuanto de ese pago corresponde a percepcion.

**Componente embebido `App\Livewire\Clientes\ClienteImpuestos` (Fase 10a)**:
Modal embebido en la vista de Clientes. Se abre via evento `abrir-impuestos-cliente` con `{ clienteId: N }`. Gestiona el CRUD de `ClienteImpuestoConfig` para un cliente dado. No es SucursalAware (el perfil fiscal es global al cliente, no por sucursal).

**Gap de UI (revision Fable 2026-07-01)**: el combobox de este componente EXCLUYE explicitamente `Impuesto::TIPO_IVA` (`->where('tipo', '!=', Impuesto::TIPO_IVA)`, ver comentario en el archivo). Esto significa que la exclusion de percepcion de IVA por certificado (RG 2226, ver `calcularTributos()` mas arriba) es un camino de codigo funcional pero **sin UI para cargarlo hoy** — el registro `ClienteImpuestoConfig` con `impuesto_id` de tipo IVA y `exento = true` debe crearse por otra via (seed, tinker, futuro importador). No ofrecer esto como flujo de usuario hasta que se habilite en la UI.

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

#### Tributos en la re-emision (hardening fiscal tanda 2, RF-V2)

`CambioFormaPagoService::tributosParaReemision()` es la puerta unica que arma `opciones['tributos']` para la FC nueva de Fase B, y tambien la usa `reintentarFacturacionPago()` (reintento de una emision que fallo). Antes de este fix ambos caminos llamaban `crearComprobanteFiscal()` sin la clave `tributos` ⇒ `ImpTrib = 0` y la FC re-emitida perdia la percepcion que la venta original habia cobrado.

- **Con comprobante ORIGINAL de la venta**: toma un SNAPSHOT de sus `tributosDetalle` (mismo patron que usa `crearNotaCredito`) y los prorratea a la porcion facturada actual, en la misma proporcion que el resto del comprobante.
- **Sin comprobante original** (nunca se emitio la FC): RECALCULA con `ImpuestoService::calcularPercepcionesComprobante()`, pero con un guard **"no autopercibir"**: solo recalcula tributos si hay EVIDENCIA de que el cliente efectivamente pago la percepcion, medida como el excedente `monto_final − monto_base − monto_ajuste − recargo_cuotas_monto` (mismo calculo que el accessor `VentaPago::percepcion`) en los pagos activos que se estan facturando. Sin esa evidencia, no se inventan tributos (`ImpTrib` se mantiene en 0).
- El desglose recalculado se ESCALA al monto efectivamente cobrado (el cobro manda si la configuracion del agente cambio entre la venta original y la re-emision).
- `calcularDesgloseIvaProporcional()` descuenta el `ImpTrib` de la base a prorratear: el neto+IVA de bienes se calcula sobre `montoAFacturar − impTrib`, prorrateando solo bienes; el residuo de redondeo sigue absorbido por la ultima alicuota, garantizando el cierre AFIP `ImpNeto + ImpOpEx + ImpIVA + ImpTrib = ImpTotal`.

#### Regla fiscal binaria

La decision de emitir documentos fiscales se toma comparando montos:

| Condicion | NC | FC nueva |
|---|---|---|
| `monto_facturado_viejo == monto_facturado_nuevo` | No se emite | No se emite. Los pagos nuevos con `facturar=true` heredan el `comprobante_fiscal_id` original. |
| `monto_facturado_viejo != monto_facturado_nuevo` | Si (por el monto del pago viejo anulado, si tenia CF) | Si (por la suma de pagos nuevos con `facturar=true`) |

Esta regla es independiente del flag `facturacion_fiscal_automatica` de la sucursal. El usuario ya decidio explicitamente al marcar cada pago del desglose.

Para omitir la NC cuando la diferencia lo permitiria se requiere permiso `func.modificar_pagos_sin_nc`.

**Fix en `ComprobanteFiscalService`**: al emitir una FC donde el `total_a_facturar` coincide con `total_final` de la venta, se prioriza la lista explicita `pagos_facturar`; si no viene, se excluyen pagos anulados del branch masivo.

#### Cache fiscal de la venta: `monto_fiscal_cache` / `monto_no_fiscal_cache` (hardening fiscal tanda 2)

`ComprobanteFiscalService::montoFiscalFacturado(Venta)` es la unica fuente de verdad para `ventas.monto_fiscal_cache`: suma el saldo fiscal de las facturas AUTORIZADAS vigentes de la venta, netas de sus notas de credito, con tope en `total_final` (nunca supera el total de la venta). `monto_no_fiscal_cache = total_final − monto_fiscal_cache`. Antes de este fix, `crearComprobanteFiscal()` pisaba incondicionalmente `monto_fiscal_cache = total_final` / `monto_no_fiscal_cache = 0` aunque la emision fuera PARCIAL (facturacion por `pagos_facturar` o `total_a_facturar` menor al total de la venta), dejando el cache mal calculado.

- Facturacion parcial en dos tramos: venta de $1000 facturada primero por $400 ⇒ `monto_fiscal_cache=400`, `monto_no_fiscal_cache=600`; segunda FC por los $600 restantes ⇒ `1000/0`.
- Notas de credito y anulaciones descuentan del cache automaticamente (via `montoFiscalFacturado`, que ya resta las NC vigentes) — no hay un paso manual de "revertir cache".

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

A diferencia del flujo tradicional (donde el comprobante se crea y luego se registra el pago), el cobro via integracion de pago sigue el modelo inverso. Este modelo aplica en todos los flujos de cobro: Nueva Venta, NuevoPedidoMostrador (via `WithPagosDesglose`) y confirmacion de pagos planificados desde `PedidosMostrador`. Aplica para los modos `qr_dinamico`, `qr_estatico` y `point`.

**Flujo comun (todos los hosts)**:

1. El cajero inicia el cobro en cualquier punto de cobro del sistema.
2. Se crea una `IntegracionPagoTransaccion` en estado `pendiente` con `cobrable_type/id = NULL` y `modo_usado` = `qr_dinamico`, `qr_estatico` o `point` segun el `modo_default` del pivote.
3. Se llama al gateway (MercadoPago Orders API `POST /v1/orders`) FUERA de la transaccion DB para no mantener locks tenant durante la latencia de red.
4. Segun el modo:
   - **QR dinamico**: MP devuelve `qr_data` (trama EMVCo). Se persiste en la transaccion y el front renderiza el SVG del QR una vez, guardandolo en `cobroIntegracionQrSvg`.
   - **QR estatico**: MP no devuelve `qr_data`. El gateway retorna `qr_image_url` con la URL del QR impreso del POS (`caja->mp_pos_qr_url`), que se persiste en `transaccion.metadata['qr_image_url']` y el front lo expone via `cobroIntegracionQrImagenUrl`.
   - **Point**: MP recibe `type:"point"` y envia el cobro a la terminal fisica (`caja->mp_point_terminal_id`). No devuelve `qr_data`. El gateway retorna `qr_data = null`; el front muestra el modal en modo "esperando en la terminal" sin QR en pantalla. Las cuotas y el medio de pago default viajan en `transaccion.metadata['point']`.
5. Se muestra el modal "Esperando pago" con el QR (QR dinamico/estatico) o el mensaje "esperando en la terminal" (Point), con countdown hasta `expira_en`.
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

**Props publicas del concern**: `mostrarModalEsperandoPago`, `cobroIntegracionTransaccionId`, `cobroIntegracionModo`, `cobroIntegracionQrData`, `cobroIntegracionQrSvg`, `cobroIntegracionQrImagenUrl`, `cobroIntegracionMonto`, `cobroIntegracionExpiraTs`, `cobroIntegracionConfirmado`.

- `cobroIntegracionModo`: string nullable con el `modo_usado` del cobro en curso (`qr_dinamico`, `qr_estatico` o `point`). El blade del modal lo usa para decidir que mostrar (QR en pantalla vs. "esperando en la terminal").
- `cobroIntegracionQrSvg`: SVG renderizado de la trama EMVCo (modo dinamico). `null` en modo estatico y en modo point.
- `cobroIntegracionQrImagenUrl`: URL de la imagen del QR impreso del POS (modo estatico). `null` en modo dinamico y en modo point. Se lee de `transaccion.metadata['qr_image_url']` al iniciar el cobro.

**Metodos publicos**: 
- `iniciarCobroIntegracion(array $datos): void` — Recibe `forma_pago_id`, `monto`, `sucursal_id`, `caja_id`, `moneda_id`, `cuotas` (opcional, para Point credito) como array explicito (el concern no depende de props del host). Resuelve `integracionPrincipal()`, verifica `IntegracionPagoSucursal` activa. Para modo `point`: valida que la caja tenga `mp_point_terminal_id` (si no, dispatcha toast de error y retorna), construye `metadata['point']` con `default_type` de `config_point` del pivote e `installments` de `cuotas` si el medio es credito. Llama a `CobroIntegracionService::iniciarCobro()`, genera el SVG del QR (null para point), setea `cobroIntegracionModo` y abre el modal.
- `pollearCobroIntegracion(): void` — Respaldo via `wire:poll.3s`. Primero lee el estado LOCAL de la transaccion en DB (sin re-consultar al proveedor): si `estaConfirmada()` cierra el modal y llama `alConfirmarCobroIntegracion()`; si `estaEnEstadoTerminal()` (expirado/cancelado/fallido) dispatcha toast, resetea y llama `alCancelarCobroIntegracion()`. Solo si la transaccion sigue `pendiente` consulta al proveedor via `CobroIntegracionService::consultarEstado()`. Al estado `aprobado`: llama `confirmarCobro()`, setea `cobroIntegracionConfirmado = true`, cierra modal e invoca `alConfirmarCobroIntegracion()`. Al estado `cancelado/expirado/fallido`: dispatcha toast, resetea, dispatcha `cobro-integracion-no-confirmado` e invoca `alCancelarCobroIntegracion()`. Idempotente: si el webhook ya confirmo antes, `confirmarCobro()` es no-op.
> El camino rapido por webhook NO es un metodo PHP: la suscripcion al broadcast vive en el Blade del modal de espera (`_modal-esperando-pago-integracion.blade.php`) — Alpine se suscribe por Echo al canal de la transaccion en `init()` y, al recibir `.IntegracionPagoActualizado`, llama a `$wire.pollearCobroIntegracion()` (el mismo metodo de respaldo). No hay listener Livewire/`getListeners()` por transaccion.
- `confirmarCobroIntegracionManual(): void` — (Fase 8, RF-12) Llama a `CobroIntegracionService::confirmarManual()` con el `Auth::id()` del cajero, marca `cobroIntegracionConfirmado = true`, cierra el modal e invoca `alConfirmarCobroIntegracion()`. Verifica el permiso `integraciones_pago.confirmar_manual` antes de proceder; si el usuario no lo tiene, dispatcha un toast de error y no hace nada.
- `cancelarCobroIntegracion(): void` — Llama `cancelarCobro()` en el service, resetea estado, dispatcha `cobro-integracion-no-confirmado` e invoca `alCancelarCobroIntegracion()`.

**Metodos protegidos**:
- `resetCobroIntegracion(): void` — Limpia todas las props del cobro (incluyendo `cobroIntegracionModo`).
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
- **Anti doble registro en `procesarVentaConDesglose()`**: al crear cada `VentaPago`, el trait saltea el bloque de registro por `formaPago->cuenta_empresa_id` cuando `$integracionTransaccionId !== null` (el pago fue cobrado por integracion). En ese caso `venta_pagos.movimiento_cuenta_empresa_id` queda NULL; los flujos de anulacion no tienen movimiento que contraasientar. Para los demas pagos del desglose (sin integracion), el registro procede normal.

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
- `resources/views/pantalla-cliente.blade.php`: Vista liviana sin Livewire/Alpine. Muestra logo e idle state. Incluye tres botones flotantes: "Pantalla completa", "Enviar a la 2da pantalla" (Window Management API + fullscreen), "Instalar pantalla cliente" (PWA install prompt; solo visible en modo navegador, oculto si ya corre como app instalada). Cuando se accede con `?instalar=1`, muestra un cartel centrado con boton "Instalar ahora" (dispara el prompt nativo). Si tras ~2,5 s el navegador no ofrece instalacion (ya instalada o sin soporte), el cartel cambia a "Parece que ya esta instalada..." con boton "Entendido". Al completarse la instalacion, muestra "Listo! Ya esta instalada..." con boton verde. Fuera del parametro `?instalar=1`, el cartel no aparece. Respeta `prefers-reduced-motion`. Footer "Powered by BCNSOFT" (banner_bcn.png). Declara iconos propios (monitor naranja, `pantalla-cliente-192x192.png` / `512x512.png`) para la pestana/barra de tareas.
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
- Deteccion de modo instalado en `pantalla-cliente.js`: `enModoApp = standalone || minimal-ui || fullscreen || navigator.standalone`. Si `enModoApp` es true, el boton flotante "Instalar pantalla cliente" se oculta y el cartel `?instalar=1` no dispara el prompt (muestra directamente el mensaje de "ya instalada").
- El boton **"Instalar pantalla cliente"** en el navbar (desplegable de perfil, `resources/views/livewire/layout/navigation.blade.php`) abre `/pantalla-cliente?instalar=1` en una pestana nueva. Siempre visible; no se oculta cuando la PWA ya esta instalada porque `getInstalledRelatedApps()` solo funciona dentro del scope del manifest consultado y no es accesible desde `/app`. No es posible disparar el prompt de instalacion de otra PWA desde `/app` (cada documento tiene un unico manifest activo).

**Requisitos del navegador**: Chrome o Edge, contexto seguro (https o localhost), monitores en modo "Extender". El permiso `window-management` se solicita la primera vez que se usa `getScreenDetails()`. Si la API no esta disponible o el permiso se deniega, la ventana se abre de todas formas y el cajero la arrastra manualmente.

#### Webhook de Mercado Pago y confirmacion en tiempo real

**Endpoint**: `POST /api/integraciones/mercadopago/webhook`
- Ruta publica (sin autenticacion Sanctum ni CSRF).
- MP la llama cuando confirma un pago QR o cuando cambia el estado de una order Point (el topic "orders" es el mismo para ambos productos; la distincion es por `external_id` de la transaccion).

**Flujo del webhook**:

1. MP envia un `POST` con cabecera `x-signature` (HMAC-SHA256) y body JSON con el `id` de la order.
2. El sistema resuelve a que comercio/sucursal pertenece la notificacion usando la tabla `mercadopago_collector_index` (conexion `config`): busca el `user_id` de MP que esta en la notificacion, obtiene el `comercio_id` y el `sucursal_id`.
3. Configura la conexion tenant sin sesion HTTP via `TenantService::usarComercioParaProceso(int $comercioId)` (metodo nuevo de Fase 6, disenado para procesos sin request HTTP como webhooks y comandos artisan).
4. Verifica la firma `x-signature` con el `webhook_secret` encriptado de la `IntegracionPagoSucursal`. Si la firma es invalida retorna HTTP 401. Si no hay `webhook_secret` configurado, omite la verificacion de firma pero igual re-consulta el estado real de la order a la API de MP con el access token de la sucursal para asegurarse de que la notificacion es legitima.
5. Llama a `CobroIntegracionService::confirmarCobro()` para marcar la transaccion como `confirmado`. Idempotente: si ya estaba confirmada, no hace nada.
6. Broadcastea el evento `IntegracionPagoActualizado` (ver abajo).
7. Retorna HTTP 200. El cobrable **no se materializa** en el webhook (no tiene el carrito ni el contexto de la sesion del cajero); solo confirma la transaccion server-side.

**Resolucion multi-tenant**: el webhook es un endpoint global unico para todos los comercios. La tabla `mercadopago_collector_index` (conexion `config`, sin prefijo) actua como indice de routing: mapea `user_id_externo` (ID de cuenta MP) al `comercio_id` y `sucursal_id` tenant. Este indice se sincroniza automaticamente al guardar o actualizar una `IntegracionPagoSucursal`. El metodo `IntegracionPagoSucursal::sincronizarIndiceColector()` registra tanto las integraciones `mercadopago_qr` como `mercadopago_point`, ya que comparten el topic "orders" y el endpoint de webhook. QR y Point de la misma cuenta MP no colisionan porque el `external_id` de la transaccion (formato `BCN-TX-{id}`) es unico y unambiguo.

**Robustez**: si el cajero cierra el navegador despues de iniciar el cobro y antes de que el cliente pague, el pago queda confirmado server-side igualmente cuando MP llama al webhook. La transaccion queda en estado `confirmado` sin cobrable asociado, disponible para reconciliacion futura. Aplica tanto para QR como para Point.

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

### 3.13 Sistema Impositivo

#### Ledger fiscal (movimientos_fiscales)

El ledger fiscal es **append-only**, igual que los otros ledgers del sistema. Unica puerta de escritura: `App\Services\Fiscal\ImpuestoService`. Livewire, reportes y otros services solo leen.

Reglas de registro:
- El campo `monto` es positivo en el caso general. El significado del importe lo da la combinacion `sentido + naturaleza`:
  - `sentido=sufrido + naturaleza=percepcion` → percepcion sufrida (el comercio la pago a un proveedor o integrador).
  - `sentido=aplicado + naturaleza=debito_fiscal` → IVA debito fiscal de una factura emitida.
  - `sentido=sufrido + naturaleza=credito_fiscal` → IVA credito fiscal de una factura de proveedor (Factura A).
  - etc.
- **Excepcion — reversas de nota de credito**: `registrarMovimientoFiscal(datos, permitirNegativo: true)` es el UNICO camino que admite `monto` negativo (y cero nunca se admite, con o sin el flag). Lo usa exclusivamente `registrarDesdeComprobante()` cuando el comprobante es una NC (ver mas abajo). La posicion fiscal SUMA montos, asi que una fila negativa resta debito/percepcion en el periodo en que se imputa.
- El `periodo_fiscal` (YYYY-MM) se calcula **al registrar** desde la `fecha` del movimiento. Es inmutable y se usa como clave de particion para consultas; no depende del timezone en el momento de la consulta.
- La **anulacion** (`anularMovimientoFiscal`) crea un contraasiento: fila nueva con `movimiento_anulado_id` apuntando al original, ambos quedan con `estado = anulado`. La posicion fiscal solo suma `estado = activo`. Este mecanismo es SOLO para correccion de errores de carga (alta manual, RF-08); una nota de credito NO lo usa (ver abajo).
- No existe un contraasiento parcial en v1: la anulacion (correccion de error) es siempre total del movimiento original.
- **RF-B9 (hardening-circuito-precios)**: `anularMovimientoFiscal` RECHAZA (lanza `Exception`) movimientos con `origen_tipo !== null` — un movimiento generado por un origen (`Compra`, `ComprobanteFiscal`, `ConciliacionFila`) solo se revierte por el circuito de ESE origen (cancelacion de la compra/venta, o nota de credito); anularlo a mano desbalancearia la reversa espejo del origen. Solo los movimientos con `origen_tipo = NULL` (alta manual, RF-08) admiten anulacion manual. La UI (`MovimientosFiscales`) oculta el boton "Anular" para esos movimientos y muestra un texto "No anulable (lo maneja su origen)" con tooltip.

#### Nota de credito: movimientos propios en su propio periodo (revision Fable 2026-07-01)

Antes, una NC anulaba retroactivamente (contraasiento) los movimientos fiscales del comprobante original. Esto es incorrecto cuando la NC se emite en un periodo distinto a la factura: si la factura es de junio y la NC de julio, el periodo de junio puede estar YA DECLARADO ante el fisco, y el Libro IVA Ventas tambien computa la NC por su propia fecha de emision.

**Semantica actual**: `registrarDesdeComprobante()` para una NC (`comprobante_asociado_id !== null`) registra sus PROPIOS movimientos (mismo `origen_tipo/origen_id` = la NC, no el original) con `monto` y `base_imponible` NEGATIVOS, imputados a `fecha = c->fecha_emision` de la NC (osea al periodo de la NC). El comprobante original y sus movimientos activos NO se tocan. En el caso de NC del mismo periodo que la factura, el neto de la posicion es identico al comportamiento anterior.

Esto aplica tanto al debito fiscal de IVA como a las percepciones aplicadas (Fase 5b, `tributosDetalle`): ambos loops usan `$signo = $esNotaCredito ? -1 : 1` y pasan `permitirNegativo: $esNotaCredito`.

#### Origenes de alimentacion del ledger

| Origen | Metodo | Cuando se invoca |
|---|---|---|
| `ComprobanteFiscal` | `ImpuestoService::registrarDesdeComprobante()` | Despues de obtener el CAE, diferido al commit REAL de la transaccion (ver debajo). Solo CUIT Responsable Inscripto. Registra IVA debito fiscal por alicuota (sentido `aplicado`, naturaleza `debito_fiscal`) y percepciones aplicadas (Fase 5b). Una NC registra sus PROPIOS movimientos en negativo, imputados a su propio periodo (ver arriba) — ya NO anula los del comprobante original. |
| `Compra` | `ImpuestoService::registrarDesdeCompra()` | Al cargar una factura de proveedor con `cuit_id` asignado. Registra IVA credito fiscal (sentido `sufrido`, naturaleza `credito_fiscal`) y percepciones/retenciones sufridas (sentido `sufrido`, naturaleza segun el impuesto). |
| `ConciliacionFila` | `ImpuestoService::registrarDesdeConciliacion()` | Al aplicar una corrida de conciliacion, para filas con `impuesto_id` identificado. Registra sentido `sufrido` con la naturaleza del impuesto. El importe es `abs(monto_neto)`. |
| Manual | `ImpuestoService::registrarMovimientoFiscal()` | Carga manual por el usuario desde `MovimientosFiscales` (RF-08). `origen_tipo = NULL`. Permite naturalezas `percepcion`, `retencion`, `tributo` en cualquier sentido. Debito/credito fiscal NO se admiten en carga manual para evitar doble conteo con los origenes automaticos. |

Todos los metodos son **idempotentes por origen**: si ya existe un movimiento activo con el mismo `(origen_tipo, origen_id)`, no duplican.

#### Ledger fiscal diferido al commit real (hardening fiscal tanda 2, RF-V7)

`ComprobanteFiscalService::crearComprobanteFiscal()` invocaba `registrarFiscal()` (el paso que llama a `ImpuestoService::registrarDesdeComprobante()`) marcado como "POST-COMMIT best-effort", pero en `NuevaVenta` la emision del comprobante ocurria DENTRO de la transaccion externa del cobro (`beginTransaction` en 1318, `crearComprobanteFiscal()` en 1467): el `beginTransaction/commit` interno del service era un savepoint anidado, asi que `registrarFiscal()` corria con la transaccion externa todavia abierta. Si el cobro rollbackeaba despues de emitido el comprobante, el ledger fiscal ya se habia intentado registrar igual.

- **Fix**: `registrarFiscal()` se difiere via `DB::connection('pymes_tenant')->afterCommit(fn () => ...)`.
  - Si NO hay transaccion externa abierta (ej. venta simple sin wrapper), el callback corre INMEDIATO — sin regresion respecto al comportamiento previo.
  - Si hay una transaccion externa abierta (ej. `NuevaVenta::procesarVenta()`), el registro se difiere hasta que esa transaccion haga commit REAL.
  - Si la transaccion externa hace rollback, el callback `afterCommit` nunca se ejecuta: el ledger queda descartado junto con el resto del cobro.
- No hizo falta reordenar los callers existentes (cambio de venta/FP, reintento, conversion de pedidos): todos quedan coherentes con el mismo criterio porque el fix vive dentro de `registrarFiscal()`.

#### Calculo de tributos (percepciones aplicadas)

`ImpuestoService::calcularTributos(Cuit $emisor, ?Cliente $receptor, float $netoGravado, ?string $jurisdiccion, ?Carbon $fecha)` implementa la matriz v1 (Fase 10a):

**Firma**: el parametro `$receptor` es ahora `?Cliente` (antes era `?CondicionIva`). `calcularPercepcionesComprobante` recibe el cliente directamente y delega sin extraer la condicion IVA.

**Logica**:
1. El emisor debe ser Responsable Inscripto.
2. El receptor (cliente) debe ser Responsable Inscripto (`receptor->condicionIva->codigo === CondicionIva::RESPONSABLE_INSCRIPTO`). Si es NULL, CF, monotributo o exento, devuelve array vacio.
3. Se cargan las configs del agente (`cuit_impuesto_configs`) y del receptor (`ClienteImpuestoConfig::vigentes($fecha)`), cada una **deduplicada a un ganador por `impuesto_id`** (evita percibir dos veces el mismo impuesto cuando hay vigencias solapadas):
   - Config del agente: gana la de `vigente_desde` mas reciente (misma regla que `configVigente()`).
   - Config del cliente (revision Fable 2026-07-01, **precedencia manual > padron**): gana primero `origen_alicuota = manual` sobre `padron`; a igual origen, gana la `vigente_desde` mas reciente. Esto permite que el perfil cargado a mano por el contador conviva con filas importadas del padron ARBA/AGIP sin que el importador lo pise.
4. Para cada impuesto del agente con config vigente `inscripto=true, es_agente_percepcion=true, alicuota != null`:
   - **Exclusion de percepcion de IVA por certificado (RG 2226, revision Fable)**: si el impuesto es de tipo IVA y el cliente tiene `ClienteImpuestoConfig` con `exento = true` para ese impuesto, se excluye (`continue`) — todo o nada, no se refina por alicuota. Fuera de ese caso, la percepcion de IVA es automatica: se aplica la alicuota fija del agente a todo RI, sin condicionar por sujeto.
   - **Percepcion IIBB**: requiere que la jurisdiccion del impuesto coincida con `$jurisdiccion` (domicilio fiscal del PV). Ademas se refina por el perfil del receptor:
     - Si el cliente tiene `ClienteImpuestoConfig` vigente (ganadora segun la precedencia del punto 3) para ese impuesto:
       - `exento = true` → `continue` (no percibe).
       - `alicuota != null` → pisa la alicuota fija del agente.
       - `alicuota_minimo_base != null` → pisa la base minima del agente.
     - Sin config del cliente: si `cuit_impuesto_config.percibir_no_empadronados = false` (default) → `continue`. Si `= true` → aplica alicuota fija del agente.
   - Si la base imponible es menor a `alicuota_minimo_base` efectiva (umbral de BASE), no aplica.
   - Si el monto resultante es menor a `monto_minimo_percepcion` del agente (umbral de IMPORTE, campo nuevo revision Fable), no aplica.
5. Devuelve array de `{impuesto_id, codigo, tipo, jurisdiccion, base_imponible, alicuota, monto}`.

**Pendiente normativo (a validar con el contador)**: el gate "solo RI" como receptor puede saltear regimenes de IIBB que tambien empadronan monotributistas (los padrones ARBA/AGIP los incluyen). Convenio Multilateral (reparto de base entre jurisdicciones) diferido. El "monto no sujeto" (deduccion de base, tipico de retenciones) no se modela.

#### Posicion fiscal (PosicionFiscalService)

Servicio de solo lectura. Calcula:

**posicionIva(cuit, periodo)**:
- `debito_fiscal` = suma de movimientos activos con `naturaleza = debito_fiscal`.
- `credito_fiscal` = suma con `naturaleza = credito_fiscal`.
- `saldo_tecnico` = debito - credito.
- `percepciones_iva_sufridas` / `retenciones_iva_sufridas` = movimientos de IVA sufridos con naturaleza `percepcion`/`retencion`.
- `a_cuenta` = percepciones + retenciones sufridas.
- `saldo` = saldo_tecnico - a_cuenta. Positivo = a pagar; negativo = a favor.
- Las percepciones/retenciones aplicadas como agente (`sentido = aplicado`) se informan separadas y NO restan del saldo propio.

**posicionIibb(cuit, periodo)**:
- Agrupa por jurisdiccion ISO 3166-2.
- Para cada jurisdiccion, desglosa los ingresos del periodo (revision Fable 2026-07-01; las NC restan en los tres campos, `signo = -1`):
  - `base_imponible`: neto gravado por IVA de los comprobantes.
  - `no_gravado`: `comprobantes_fiscales.neto_no_gravado`.
  - `exento`: `comprobantes_fiscales.neto_exento`.
  - `ingresos_totales`: suma de los tres anteriores.
  - El desglose es informativo — que componentes integran la base de IIBB depende de cada jurisdiccion/rubro, lo define el contador viendo las columnas por separado. No se asume que IIBB se calcula solo sobre `base_imponible`.
- Ademas: percepciones/retenciones sufridas a cuenta, percepciones/retenciones aplicadas como agente.
- La jurisdiccion de cada comprobante surge de: `puntoVenta.cuitDomicilio.provincia` (Fase 9) → fallback `sucursal.provincia` → fallback `AR`.
- Pendiente diferido (contador/fase padrones): Convenio Multilateral (reparto de la base entre jurisdicciones por coeficiente).

**libroIvaVentas(cuit, periodo)**:
- Fuente: `comprobantes_fiscales` autorizados del CUIT en el periodo.
- Incluye desglose por alicuota (`detallesIva`), tributos y receptor.
- Ordenados por `fecha_emision, punto_venta_numero, numero_comprobante`.

**libroIvaCompras(cuit, periodo)**:
- Fuente: `movimientos_fiscales` activos del periodo, sentido `sufrido`, origen `Compra`.
- Agrupados por `origen_id` (compra). Muestra credito fiscal, percepciones y retenciones sufridas.

#### Jurisdiccion de la operacion (RF-11)

La jurisdiccion de IIBB de una operacion es la **provincia del domicilio fiscal declarado del punto de venta** (`puntos_venta.cuit_domicilio_id → cuit_domicilios.provincia`), no la ubicacion fisica de la sucursal. Esto permite que un CUIT domiciliado en CABA (`AR-C`) facture desde una sucursal en Buenos Aires sin que la jurisdiccion cambie por la ubicacion fisica.

Si el PV no tiene domicilio asignado, se usa `sucursal.provincia` como fallback. Si tampoco tiene provincia, se asigna `AR` (sin jurisdiccion provincial).

#### Permisos funcionales del modulo Fiscal

| Permiso | Descripcion |
|---|---|
| `func.fiscal.posicion` | Ver posicion de IVA e IIBB por CUIT y periodo |
| `func.fiscal.libros` | Ver y exportar libros de IVA ventas/compras |
| `func.fiscal.movimientos` | Registrar y anular movimientos del ledger fiscal manualmente (RF-08, implementado) |
| `func.fiscal.configuracion` | Configurar impuestos por CUIT desde Configuracion Empresa |

Permisos de menu: `menu.fiscal`, `menu.fiscal-posicion`, `menu.fiscal-libros`, `menu.fiscal-movimientos`. Los tres primeros se crean en la migracion `add_fiscal_menu_y_permisos`; `menu.fiscal-movimientos` se crea en `add_fiscal_movimientos_menu` (RF-08). Todos asignados a Administrador y Super Administrador al provisionar o al ejecutar la migracion correspondiente.

#### Rutas

| Ruta | Nombre | Componente |
|---|---|---|
| `GET /fiscal/posicion` | `fiscal.posicion` | `App\Livewire\Fiscal\PosicionFiscal` |
| `GET /fiscal/libros` | `fiscal.libros` | `App\Livewire\Fiscal\LibrosIva` |
| `GET /fiscal/movimientos` | `fiscal.movimientos` | `App\Livewire\Fiscal\MovimientosFiscales` |

#### Componente MovimientosFiscales (RF-08)

`App\Livewire\Fiscal\MovimientosFiscales` — pantalla de administracion del ledger fiscal.

- **Scope**: global (no es SucursalAware). Filtra por CUIT seleccionado.
- **Permiso**: `func.fiscal.movimientos` verificado en `mount()` y en cada accion de escritura.
- **Paginacion**: 15 registros por pagina, ordenados por `fecha DESC, id DESC`.
- **Filtros con `#[Url]`**: `cuitId`, `periodo` (YYYY-MM), `filtroSentido`, `filtroNaturaleza`. El checkbox `incluirAnulados` no se persiste en URL.
- **Carga inicial**: primer CUIT activo por razon social y mes actual.

**Alta manual** (`abrirModalAlta` / `registrarMovimiento`):
- Naturalezas permitidas: `percepcion`, `retencion`, `tributo` (constante `NATURALEZAS_MANUALES`).
- Naturalezas prohibidas en alta manual: `debito_fiscal`, `credito_fiscal` (se generan solos desde comprobantes/compras para evitar doble conteo).
- El selector de impuesto agrupa: "Configurados para este CUIT" (`cuit_impuesto_configs` del `formCuitId`) y "Otros impuestos del catalogo". No se filtra estricto: permite cargar impuestos no configurados (ej. retenciones que hacen clientes al pagar).
- Al elegir impuesto, si su `naturaleza_default` es una naturaleza manual valida, se pre-carga en `formNaturaleza`.
- Si se informan `formBaseImponible` y `formAlicuota`, el campo `formMonto` se sugiere como `base × alicuota / 100` (editable).
- Delega a `ImpuestoService::registrarMovimientoFiscal()` con `origen_tipo = NULL` (marca de carga manual).

**Anulacion** (`abrirModalAnulacion` / `confirmarAnulacion`):
- Solo disponible para movimientos con `estado = activo`, `movimiento_anulado_id = NULL` (no es ya un contraasiento) **y `origen_tipo = NULL`** (RF-B9 hardening-circuito-precios: los generados por Compra/ComprobanteFiscal/ConciliacionFila no son anulables a mano — la vista oculta el boton y muestra "No anulable (lo maneja su origen)").
- Delega a `ImpuestoService::anularMovimientoFiscal($movimiento, $usuarioId, $motivo)`, que ademas rechaza server-side cualquier intento sobre un movimiento con origen (defensa en profundidad).
- El service crea el contraasiento append-only; el componente no escribe directamente en la tabla.

---

### 3.14 Pedidos Delivery / Take-Away (circuito completo)

Modulo espejo de Pedidos por Mostrador (3.1) con la dimension logistica agregada. `PedidoDeliveryService` (`app/Services/Pedidos/PedidoDeliveryService.php`) es el UNICO camino de escritura: Livewire (`PedidosDelivery`, `NuevoPedidoDelivery`, `Repartidores`, `ConfiguracionDelivery`) y los controllers de la API v1 lo consumen por igual, con los mismos payloads y validaciones.

#### Estados y transiciones

`PedidoDelivery::TRANSICIONES_PERMITIDAS`:
```
borrador → confirmado, cancelado
confirmado → en_preparacion, en_camino, entregado, cancelado
en_preparacion → listo, en_camino, entregado, cancelado
listo → en_camino, entregado, cancelado
en_camino → entregado, listo (vuelta fallida), cancelado
entregado → facturado, cancelado
facturado / cancelado → (terminales)
```

**`en_camino` es un estado COMPARTIDO entre delivery y take-away** (E1, supersede el diseño original donde take-away saltaba `listo → entregado`):
- **Delivery**: `en_camino` = el repartidor tiene el pedido en la calle (`repartidor_en_camino` no-null, `salida_id` seteado).
- **Take-away**: `en_camino` = "**Para retirar**" — el cliente puede pasar a buscarlo. `repartidor_id`/`salida_id` quedan NULL; el chip "Para llevar" del panel se convierte en boton ("Para retirar") que llama a `despachar()` sin exigir repartidor. El label se resuelve por tipo via el accessor `estado_label` (no hay dos estados separados).
- El salto `confirmado/en_preparacion → en_camino` (sin pasar por `listo`) esta permitido: `usa_estado_listo=false` (ver abajo) o el pase directo del take-away lo usan.

**`usa_estado_listo` (config, default true)**: si esta en OFF, la columna "Listo" se oculta del Kanban, el modal de cambio de estado no la ofrece, y `avanzarAEnPreparacionSiCorresponde`/`cambiarEstado` saltan directo a `en_camino`/entrega, haciendo backfill de `listo_at` para no romper reportes que asuman el timestamp.

**Cancelar un pedido `en_camino`**: si tiene una salida en la calle, se registra a traves de la VUELTA (no se puede cancelar directo desde el panel — E9); sus pagos se contraasientan (incluido el efectivo del fondo, con movimiento inverso `repartidor_fondo_movimientos`).

#### Renglon-concepto del costo de envio (D17)

El costo de envio NUNCA es solo un campo de encabezado: `costo_envio`/`costo_envio_manual`/`distancia_km`/`zona_id` son la fuente logistica (kanban, cotizacion, auditoria), pero el monto se materializa como un **renglon `pedidos_delivery_detalle` con `es_costo_envio=true`** (mecanismo `es_concepto` ya existente, sin stock, con `tipo_iva_id` 21% y `concepto_categoria_id` configurable). `PedidoDeliveryService` lo crea/actualiza/elimina por delta cada vez que se recotiza o edita el pedido. Sin este renglon, `calcularDetallesIva` (que arma neto/IVA solo desde los detalles) dejaria `ImpTotal ≠ ImpNeto+ImpIVA` — rechazo directo de ARCA.

El renglon de envio **NO participa** de descuento general, cupones, promociones, puntos ni del ajuste por forma de pago (hook `baseAjustePagoDesglose` lo excluye de la base sobre la que se calcula el ajuste/recargo de cuotas): se calcula y suma aparte de toda la cascada de beneficios.

#### Cotizacion de envio (`DeliveryEnvioService`)

`cotizar(Sucursal, ?lat, ?lng, ?Carbon $cuando): CotizacionEnvio` resuelve en orden:
1. Sin coordenadas → sin cotizacion (`alcance = desconocido`), costo manual.
2. **Zonas dibujadas activas** (poligono, E4): `DeliveryZona::contienePunto()` (ray casting) — la primera que matchea por `orden` fija el costo. `rangos_horarios` de la zona son franjas de **costo** (no de disponibilidad): `costoPara($hora)` puede subir el precio de noche, por ejemplo. **Con zonas dibujadas activas NO hay fallback**: fuera de todas ⇒ `fuera_de_alcance`.
3. **Sin zonas activas**: distancia a la sucursal (Haversine) → dentro de `radio_entrega_km` ⇒ `costo_base + max(0, km − km_incluidos) × costo_km`; fuera ⇒ `fuera_de_alcance`.

El costo cotizado siempre se puede **pisar a mano** en el panel (`costo_envio_manual=true` + usuario); "fuera de alcance" solo se puede forzar con el permiso `func.pedidos_delivery.forzar_alcance` — la API publica NUNCA permite forzarlo.

#### Repartidores, salidas y vueltas (`RepartidorService`)

- **Viaje UNICO por repartidor** (E7): despachar un pedido con un repartidor que ya esta `en_camino` **suma el pedido a su salida actual** (`lockForUpdate` sobre la salida `en_camino` del repartidor) — nunca se crean salidas paralelas. El pase manual de un solo pedido (`despacharPedido`) crea una salida implicita de 1 si no hay una en curso.
- **Vuelta** (`registrarVuelta`): por cada pedido de la salida se marca `entregado` o `no_entregado` (motivo, vuelve a `listo` para re-despacho, pagos previos persisten). Los cobros se registran **ANTES** de marcar entregado (el guard de conversion exige pagos suficientes).
  - **Efectivo**: `confirmarCobroContraEntrega` marca el pago como planificado→activo con `destino_fondo=true` — **NO crea `MovimientoCaja`**, solo un movimiento `cobro_pedido` (y `vuelto` si aplica) en `repartidor_fondo_movimientos` (D13). El dinero "vive" en el fondo hasta que se rinde.
  - **No efectivo** (QR/transferencia en la puerta): circuito normal de pagos, nunca toca el fondo. Una FP con integracion (QR) **no puede confirmarse desde la vuelta** (exige su propio circuito de confirmacion — `test_vuelta_con_pago_planificado_de_fp_integrada_es_rechazada`).
  - **Mini-rendicion** (`vueltaRendicionModo`, E7): `nada` (se queda todo, sigue repartiendo) / `devolver_pedidos` (entrega SOLO los cobros de esta vuelta, netos de envios de terceros si `envio_es_del_repartidor`) / `devolver` (monto elegido) / `cerrar` (rendicion completa: declarado vs teorico, diferencia sobrante/faltante, cierra el fondo) / `reforzar` (agrega efectivo ademas de la vuelta). **Repartidor tercero**: forzado a `devolver_pedidos` (no tiene caja chica propia). Sin fondo abierto se auto-abre uno en $0 (informacional, para que el ledger tenga de donde salir).
  - **Vuelto planificado**: al confirmar un pedido sin cobrar con la intencion de efectivo contra entrega, se pregunta "¿con cuanto paga?" → `monto_recibido`/`vuelto` quedan planificados en el pago; la vuelta los precarga para que el repartidor salga con el cambio exacto.
- **Conversion automatica a venta al entregar** (config `conversion_automatica_al_entregar`, propia de delivery): corre **POST-vuelta, individual y FUERA de la transaccion de la vuelta** — una falla de ARCA en un pedido no puede dejar la vuelta a medias ni bloquear la entrega de los demas. El flag `convertirAutomatico` de `cambiarEstado` permite que la vuelta suprima la conversion en-transaccion y la dispare por afuera.
- **Reasignacion de repartidor**: libre hasta `listo`; en `en_camino` solo via vuelta fallida + re-despacho (evita salidas/fondos cruzados).

#### Fondo del repartidor — regla contable (D13)

El efectivo cobrado en la calle NUNCA genera `MovimientoCaja` al registrarse: vive en `repartidor_fondos`/`repartidor_fondo_movimientos` (append-only). Al **rendir** (o devolver en la vuelta), se genera **UN ingreso neto** a la caja receptora (inicial + cobros − vueltos − liquidacion de envios de terceros). Los movimientos del fondo NO llevan turno de caja (el fondo es cross-turno, puede quedar abierto entre cierres). El cierre de caja **advierte** si hay fondos abiertos con esa caja como origen (no bloquea) — implementado en los TRES caminos de cierre existentes: `TurnoActual::abrirModalCierre` (grupal e individual) y `CajaService::cerrarCajaConTesoreria` (key `advertencias` + `Log::warning`). Tesoreria muestra una linea informativa "En fondos de repartidores (abiertos)" (suma de saldos teoricos) para que la plata nunca sea invisible entre la vuelta y la rendicion.

#### Consistencia de pedidos en salida (E9, rev19)

`desvincularDeSalida()` (pivot append-only: marca `no_entregado` + motivo, o hace DELETE si la salida seguia `armando`) se invoca al **cancelar** un pedido en salida, al **volver a `listo`** desde la calle (vuelta fallida) y al **entregar/convertir** pedidos de salidas que nunca partieron. `convertirEnVenta` **bloquea** pedidos `en_camino` con salida `en_camino` (esos cobros van al fondo via vuelta, no se pueden materializar por afuera); `cambiarEstado` exige pasar por la vuelta para entregarlos, salvo el flag interno `viaVuelta` (el camino legitimo que usa la propia vuelta y el PATCH de la API).

**Caja de contexto**: pedidos de tienda/API (sin caja propia) cobrados desde el panel: `agregarPago`/`confirmarPagoPlanificado` aceptan una caja de contexto (la de quien ejecuta la accion) y el pedido la adopta — mismo criterio que usa `convertirEnVenta`. Un cobro que afecta caja sin ninguna caja disponible lanza excepcion (nunca se materializa "flotando").

#### Promesa de entrega (RF-15)

`hora_pactada_at` segun `modo_promesa` de la sucursal:
- **`franjas`**: horarios definidos A MANO por el comercio (`config_delivery.franjas`: `[{hora, dias, delivery, take_away}]`, soporta cruce de medianoche). Los cupos por franja son Fase 8.
- **`automatica`**: `hora_pactada = ahora + demora_base_min + demora_min_por_km × km` (la distancia sale de la cotizacion de envio). `usar_maps_para_demora` (Google Routes API) es Fase 8.
- **`manual`**: se fija al ACEPTAR el pedido con un modal de botones de demora (`botones_demora`, configurable).
- **`lo_antes_posible`** (columna + key `acepta_lo_antes_posible`): promesa valida sin hora ("Ya" = +0). **Excluyente** con `hora_pactada_at` en TODOS los caminos (crear/actualizar/promesa/API).
- **Alertas de demora**: `sucursales.pedido_alerta_amarilla_min`/`pedido_alerta_roja_min` (COMPARTIDAS con mostrador) alimentan `CalculaAlertaDemora` (trait) para pintar el kanban/lista; el kanban ordena "lo antes posible" primero.
- **`timeout_aceptacion_min`** (D14): vencido sin aceptar resalta el pedido "Demorado" en el strip por-aceptar y el seguimiento publico expone `demorado: true` — NO cancela solo.

#### Encargos — pedidos para dia futuro (RF-T16)

Aprovecha la estructura reservada en Fase 8 (`pedidos_delivery.programado_para`, keys `acepta_programados`/`programados_aparecen_min_antes`, `articulos.permite_programado`) **sin migraciones nuevas**. Los CUPOS por franja de Fase 8 siguen sin implementar (no aplican a encargos).

- **Config** (`Sucursal::CONFIG_DELIVERY_DEFAULTS['encargos']`, sub-objeto): `dias_laborales` (array de isoWeekday, default 1..7), `horarios` (`null` = todo el dia; si no, `[{dias, desde, hasta}]`, mismo shape que el calendario de atencion, cruce de medianoche recortado a 23:30 del dia), `feriados` (array de `Y-m-d`), `anticipacion_horas` (default 24), `max_dias_adelante` (default 30). `Sucursal::getConfigDelivery()` mergea `encargos` **por clave** (no reemplazo del sub-objeto completo) para que un JSON guardado antes de una key nueva no quede sin default. Es un calendario **PROPIO e independiente** del calendario de atencion (`dias_laborales`/`horarios_atencion`/`feriados` de nivel raiz): un encargo puede tomarse para un dia en que el local no atiende al publico. `ConfiguracionDelivery::updatedAceptaProgramados()` (hook fuera de la whitelist de auto-guardado generica) precarga `encargos` desde el calendario de atencion la PRIMERA vez que se activa el toggle (`! isset($sucursal->config_delivery['encargos'])`), antes de persistir.
- **`DeliveryEnvioService`** (metodos nuevos, `app/Services/Pedidos/DeliveryEnvioService.php`):
  - `aceptaEncargos(Sucursal): bool` — lee `acepta_programados`.
  - `fechasEncargosDisponibles(Sucursal): Carbon[]` — dias entre hoy y `hoy + max_dias_adelante` que tienen AL MENOS un slot elegible (delega en `slotsEncargos()` por dia).
  - `slotsEncargos(Sucursal, Carbon $dia): Carbon[]` — slots de **30 minutos** del dia segun los rangos de `encargos.horarios` (vacio = todo el dia 00:00–23:30), descartando dias no habilitados (`dias_laborales`/`feriados`), lo anterior a `now() + anticipacion_horas` y lo posterior a `hoy + max_dias_adelante`.
  - `validarProgramado(Sucursal, Carbon $cuando, array $articuloIds = []): void` — lanza `Exception` (mensaje claro, la API la traduce a 422) si: encargos desactivados, el `$cuando` no matchea un slot de `slotsEncargos()`, o alguno de los `$articuloIds` tiene `permite_programado=false`.
- **`PedidoTiendaService::resolverPromesa()`**: si el payload trae `entrega.programado_para`, es la rama ENCARGO (prioridad sobre franja/asap): valida con `validarProgramado()` (sucursal + `Carbon::parse($cuando)` + ids de `items[].articulo_id`) y devuelve `[hora_pactada=$cuando, lo_antes_posible=false, programado_para=$cuando]`. `crearPedidoExterno()` **saltea el bloqueo `estaAbierto()`** cuando `entrega.programado_para` viene seteado (`$esEncargo`): un encargo valido se puede crear con la tienda cerrada, porque se valida contra SU propio calendario.
- **Panel** (`PedidosDelivery`, `app/Livewire/Pedidos/PedidosDelivery.php`):
  - `excluirEncargosFuturos($query)`: agrega `whereNull('programado_para') OR programado_para <= now() + programados_aparecen_min_antes` — aplicado en `obtenerPedidos()` (filtro `activos`) y en `obtenerPedidosKanban()`. Un encargo cuya hora esta mas alla de esa ventana NO aparece en Lista "Solo activos" ni en el Kanban.
  - Filtro nuevo `filterEstadoPedido === 'programados'`: `activos()` + `estado_pedido != borrador` + `programado_para > now()` (los encargos aun fuera de ventana).
  - `obtenerEncargosProgramados()`: encargos activos (no borrador) con `programado_para > now() + programados_aparecen_min_antes`, agrupados por `programado_para->toDateString()`, para la vista "Encargos" del panel (limite 300 filas).
  - `adelantarEncargo(int $pedidoId)`: `$pedido->update(['programado_para' => now()])` — mueve el encargo al tablero YA, conservando `hora_pactada_at` original (el semaforo de demora sigue midiendo contra la hora pactada real). Exige el permiso `func.pedidos_delivery.cambiar_estado`.
  - Los pedidos "por aceptar" (borrador) **no pasan** por `excluirEncargosFuturos`: un encargo se acepta apenas llega (aunque sea para dentro de varios dias), no recien a su hora.
- **`ProduccionEncargos`** (`app/Livewire/Pedidos/ProduccionEncargos.php`, ruta `pedidos.encargos.produccion`, patron `ReportesCompras`: pagina lazy propia, rango de fechas): agrupa `pedidos_delivery.activos()->where('estado_pedido', '!=', 'borrador')->whereNotNull('programado_para')` del rango por `programado_para->toDateString()` → `detalles.articulo_id`, sumando `cantidad` (uasort DESC por cantidad dentro de cada dia). Los pedidos "por aceptar" (`borrador`) **no cuentan** en la produccion — todavia no son un compromiso en firme. Drill-down por renglon expandible (`toggleRenglon`) con el detalle de pedidos que componen esa cantidad.
- **Badge visual**: `_badges-delivery.blade.php` suma un chip fucsia "Encargo · fecha/hora" (`$pedido->programado_para` no-null), reusado en Lista/Kanban/vista Encargos.

#### Multi-pago en la tienda — hasta 2 formas de pago (RF-T18 F2, 2026-07-21)

Contrato ADITIVO: `pago.{forma_pago_id, paga_con}` singular sigue funcionando (1 FP); si viaja `pagos[]`, el singular se ignora. La PRIMERA FP del array es la principal (participa del precio como la FP unica hoy: promos/listas condicionadas por FP + restriccion de cupon); la o las siguientes solo aportan su propio ajuste sobre su porcion.

- **`CotizadorCarritoTienda::MAX_PAGOS_TIENDA = 2`** y **`desglosarPagos(Sucursal, array $pagosInput, float $totalACubrir, float $costoEnvio = 0.0): array`** (`app/Services/Pedidos/CotizadorCarritoTienda.php`): valida (entre 1 y 2 FP, sin IDs repetidos, suma de montos = `$totalACubrir` con tolerancia de redondeo `0.05`) y calcula, por cada FP, el ajuste (override de `formas_pago_sucursales.ajuste_porcentaje` > general) sobre SU monto — misma regla que el panel (`WithAjusteFormaPago`/`WithPagosDesglose`). Cada FP declarada debe pasar `FormaPago::esDeclarableEnTienda($sucursalId)` (mismo filtro que RF-T18 F1); si no, `Exception` (422). Devuelve `list<{forma_pago_id, nombre, monto_base, ajuste_porcentaje, monto_ajuste, monto_final, permite_vuelto, paga_con, vuelto}>`.
- **Exclusion proporcional del envio de la base del ajuste (D17)**: el envio es un valor FIJO, sin descuentos/recargos por FP. `desglosarPagos` recibe `$costoEnvio` (parte de `$totalACubrir`) y calcula `factorBase = ($totalACubrir - $costoEnvio) / $totalACubrir`; la base del ajuste de cada FP es `monto * factorBase` (si `$costoEnvio = 0`, `factorBase = 1` y no cambia nada) — espejo de `NuevoPedidoDelivery::baseAjustePagoDesglose()` del panel.
- **`CotizadorCarritoTienda::desgloseIvaConAjuste(float $montoAjuste): ?array`**: re-prorratea el `desglose_iva` de la ultima cotizacion con el ajuste COMBINADO (suma de `monto_ajuste` de todas las FP) llamando de nuevo a `actualizarDesgloseIvaConAjusteFormaPago()` (el metodo del trait re-deriva desde las bases por alicuota, re-llamarlo es seguro).
- **`POST carrito/cotizar`**: `pagos: [{forma_pago_id, monto}]` (`min:1, max:2`) + `costo_envio` (nullable numeric, la cotizacion de envio que la tienda ya obtuvo de `envios/cotizar`, para poder excluirla proporcionalmente de la base del ajuste). Con `pagos[]`, la respuesta trae `pagos: [{forma_pago_id, nombre, monto_base, ajuste_porcentaje, monto_ajuste, monto_final, permite_vuelto}]` (null si se cotizo con una sola FP), `forma_pago: null` (superado por el desglose) y `total_a_pagar` = suma de los `monto_final` (incluye el `costo_envio` informado). **Incompatible con `usar_puntos`** (`pagos[]` + `usar_puntos:true` → `422 {code:'pagos_invalidos'}`); el bloque `puntos` de la respuesta viaja `null` cuando hay `pagos[]`.
- **`POST /pedidos`**: `pagos: [{forma_pago_id, monto, paga_con?}]` (mismo `min:1, max:2`; `paga_con` solo tiene efecto en la FP con `permite_vuelto`, y si no cubre el `monto_final` de esa FP → `Exception`/422). `PedidoTiendaService::crearPedidoExterno()` recalcula el ajuste total como la suma de `monto_ajuste` del desglose y llama a `registrarPagosDesglosados()`, que hace UN `PedidoDeliveryService::agregarPago()` PLANIFICADO por FP con el MISMO shape que el alta manual del panel (`forma_pago_id, monto_base, ajuste_porcentaje, monto_ajuste, monto_final, monto_recibido, vuelto, planificado:true`) — el panel ve un pedido con `pagos[]` de la tienda IDENTICO a uno cargado a mano con 2 formas de pago. Tambien incompatible con `usar_puntos` (`Exception` si ambos vienen).
- Contrato `docs/api-v1-delivery.md` a actualizar por el equipo de contrato (aditivo, sin romper v1); fixtures/contract tests de `bcn-tienda` deben incorporar el bloque `pagos`.

#### Datos del cliente en el checkout — email y cumpleanios (RF-T19, 2026-07-21)

Patron de claves aditivas en `config_delivery` (igual que Encargos, RF-T16): `Sucursal::CONFIG_DELIVERY_DEFAULTS['checkout'] = ['pedir_email' => 'opcional', 'pedir_cumpleanios' => false]`, mergeado por clave en `Sucursal::getConfigDelivery()` (un JSON guardado antes de esta key no queda sin default).

- **Config panel** (`App\Livewire\Pedidos\ConfiguracionDelivery`, apartado "Pedidos externos" → seccion nueva "Datos del cliente en el checkout", `config-pedidos-externos.blade.php`): `checkoutPedirEmail` (`'no'|'opcional'|'obligatorio'`, `<select>`) y `checkoutPedirCumpleanios` (bool, checkbox) — ambos en la whitelist de auto-guardado de `ConfiguracionDelivery` (RF-T15, sin boton propio). `persistirConfig()` sanea `checkoutPedirEmail` contra el enum (fallback `'opcional'` si viene un valor invalido).
- **`GET /v1/tiendas/{slug}`**: suma bloque `checkout: {pedir_email, pedir_cumpleanios}` (aditivo). El cumpleanios NUNCA es obligatorio del lado servidor, sea cual sea la config.
- **Validacion server-side** (`PedidoTiendaService::crearPedidoExterno()`): si `checkout.pedir_email = 'obligatorio'` y no llega `cliente.email` NI el consumidor logueado tiene email en su cuenta → `Exception` (422). `cliente.fecha_nacimiento` (validacion `nullable|date|before:today`) es SIEMPRE opcional en el payload, sea cual sea la config — la tienda decide si lo pide o no, el core nunca lo exige.
- **`persistirFechaNacimiento()`** (mismo service): si el payload trae `cliente.fecha_nacimiento`, hace `update()` en el `Cliente` tenant (si `resolverClienteId` devolvio uno) Y en el `Consumidor` logueado (si hay Bearer) — nunca borra, solo setea cuando viene. Nunca frena el alta ya creada: cualquier excepcion al persistir el cumpleanios se loguea como warning y el pedido queda creado igual.
- **`GET /v1/consumidores/me`**: suma `fecha_nacimiento` (formato `Y-m-d` o `null`) al perfil — pre-llena el checkout de cualquier otra tienda del ecosistema para el mismo consumidor.
- Migraciones aditivas: `clientes.fecha_nacimiento` (tenant, `add_fecha_nacimiento_to_clientes`) y `config.consumidores.fecha_nacimiento` (`add_fecha_nacimiento_to_consumidores`) — ver tablas arriba.

#### Pedidos externos y aceptacion (D14)

`config_delivery.aceptacion_pedidos_externos`:
- **`manual`** (default): todo pedido de `origen IN ('tienda','api')` entra en `borrador` ("por aceptar", con badge/sonido en tiempo real). **Aceptar** (`aceptarPedidoExterno`) lo confirma y, si `modo_promesa=manual`, abre el modal de demora. **Rechazar** (`rechazarPedidoExterno`) lo cancela; si tenia pago online acreditado queda marcado **"a devolver"** (devolucion manual v1) y se avisa al consumidor por su canal de seguimiento.
- **`automatica`**: el pedido entra `confirmado` directo; si `imprimir_comanda_al_aceptar`, la comanda sale sola por la comandera.
- El pedido por aceptar **no descuenta stock** (patron borrador); al aceptar se valida stock y se respetan los precios/promos ya COTIZADOS al crearlo (snapshot en los renglones).
- Pago online acreditado: `afecta_caja=0` (se concilia por el circuito de integraciones de pago existente); una caja solo interviene si un operador cobra desde el panel.

#### Consumidor ↔ Cliente (D11)

El pedido de tienda/API SIEMPRE guarda `consumidor_id` (FK logico a `config.consumidores`) + snapshot de contacto. El `cliente_id` tenant solo se completa si existe `consumidor_comercio` para ese comercio:
- `comercios.tienda_alta_cliente_automatica = true`: el primer pedido crea el `cliente` tenant + el mapping automaticamente.
- `false` (default): el pedido queda solo con `consumidor_id`; la accion "convertir en cliente" del panel (crea cliente + mapping, vincula pedidos previos) queda **diferida al proyecto tienda** (seria UI muerta sin login de consumidores implementado).
- Puntos, cupones por cliente y cuenta corriente solo aplican cuando el cliente esta materializado.
- `resolverClienteId` usa el **comercio activo de `TenantService`** (no una columna `sucursal->comercio_id`, que no existe en tenant — bug real corregido en Fase 7/sdd-verify).

#### Percepciones fiscales al convertir en venta (hardening fiscal tanda 2, RF-V3/RF-V5)

Antes de este fix, `convertirEnVenta()` creaba la venta sin calcular tributos: un cliente RI percibido que compraba por pedido delivery no pagaba percepcion, mientras que el mismo cliente en venta directa (`NuevaVenta`) si. Se implemento SOLO en delivery (unico canal de conversion que emite FC hoy — ver nota de mostrador mas abajo).

- **`PedidoDeliveryService::percepcionParaConversion(PedidoDelivery)`**: reusa la MISMA puerta que `NuevaVenta` (`ImpuestoService::calcularPercepcionesComprobante()`) sobre el neto gravado del pedido (base RF-V1), condicionado a cliente RI + CUIT del PV como agente de percepcion.
- **`cobrarPercepcionEnPlanificado()`**: si corresponde percepcion, la suma al pago PLANIFICADO fiscal de MAYOR monto y ajusta `total`/`total_final` del pedido ANTES de materializar los pagos (espejo de como `NuevaVenta` suma la percepcion al total antes de cobrar). El total mostrado al cliente pasa a incluirla.
- **Guard "nunca autopercibir"**: si los pagos fiscales del pedido YA estan `activo` (por ejemplo, el pedido se cobro en la vuelta del repartidor antes de convertir), la percepcion NO se aplica — se loguea un warning. Nunca se factura un tributo que el cliente no pago.
- **Emision con desglose proporcional (`desgloseIvaProporcional`)**: la conversion SIEMPRE emite con el mismo mecanismo de `calcularDesgloseIvaProporcional` (prorratea solo bienes, descuenta `ImpTrib` de la base, absorbe el residuo en la ultima alicuota GRAVADA — nunca en la exenta, RF-V4). Esto tambien resuelve RF-V5: una conversion con descuento de cabecera (`descuento_general_monto` + `_usar_totales_proporcionados`) cierra exacto `ImpNeto+ImpOpEx+ImpIVA+ImpTrib=ImpTotal` (evita el rechazo AFIP 10048 que podia darse antes al facturar sobre los totales finales sin recalcular el desglose).
- **Pedidos MOSTRADOR no emiten FC en ningun punto de su ciclo hoy** (pendiente PR2.C/D del spec de pedidos-mostrador): sin emision no hay obligacion de percepcion, por eso RF-V3/RF-V5 quedaron acotados a delivery. Cuando mostrador incorpore su propia emision fiscal, debe reusar `percepcionParaConversion`/`desgloseIvaProporcional` extrayendolos a un lugar comun (hoy viven en `PedidoDeliveryService`).

#### Gaps de mostrador que delivery NO hereda (D19)

Corregidos a nivel service (y anotados como mejora pendiente de espejar en mostrador):
- **Puntos ganados**: `convertirEnVenta` de delivery los acredita siempre que haya cliente (en mostrador solo se acreditan desde el Livewire de venta directa, la conversion nunca los acredita).
- **Cupon**: la conversion de delivery registra `CuponUso` e incrementa `uso_actual` (mostrador solo copia montos).
- **Opcionales**: `mapearDetalleAArrayVenta` de delivery migra los opcionales a `venta_detalle_opcionales` (mostrador no los migra).
- **`cierre_turno_id`**: `marcarTransaccionesCierre` marca tambien `pedidos_delivery_pagos` (excluyendo los `destino_fondo`, que no tienen turno).

---

### 3.15 API v1 Pedidos Delivery / Tienda (Sanctum + consumidores + marketplace)

Base nueva bajo `/api/v1` (`routes/api.php`), documentada en detalle en `docs/api-v1-delivery.md`. Errores JSON uniformes `{error:{code,message,details}}` (bootstrap/app.php): excepciones "peladas" de los services → 422 con mensaje; el resto → 500 generico logueado.

#### Audiencias y autenticacion

1. **Publico por tienda** (sin auth, throttle 60/min): rutas `/v1/tiendas/{slug}/...`. El `slug` (tabla `config.tiendas`) identifica comercio+sucursal (D15, la tienda es POR SUCURSAL) — resuelto por `ApiTenantMiddleware` (alias `api.tenant`) sin abrir la BD tenant primero.
2. **Integracion** (Bearer Sanctum, throttle 120/min): tokens de `personal_access_tokens` (BD config) con **abilities** (`pedidos:read`, `pedidos:write`, `config:read`, `catalogo:read`) emitidos por comercio desde `/configuracion/api-tokens`. Sucursal por header `X-Sucursal-Id` (default: principal). El tokenable es `Comercio` (implementa `Authenticatable` + `HasApiTokens`, `sanctum.guard=null` porque no es un `User`).
3. **Consumidores** (RF-T1..T3, proyecto tienda): cuenta GLOBAL cross-comercio (`config.consumidores`, guard `consumidores` + Sanctum) con Bearer propio (`/v1/consumidores/...`). El token lo guarda la TIENDA en su sesion server-side, nunca en el navegador del consumidor. El endpoint publico de pedidos y `carrito/cotizar` lo aceptan opcionalmente (precios por cliente donde exista mapping, D11). Middleware `api.consumidor` (`EnsureApiConsumidor`) exige que el Bearer autenticado sea instancia de `Consumidor`; si es un token de integracion (tokenable `Comercio`) devuelve `403 sin_permiso`.
4. **Marketplace** (RF-T4, publico, throttle 30/min): `GET /v1/tiendas`, `GET /v1/rubros` — landing global sin tenant.

**Permisos sin sesion**: `User::loadAllPermissions` cachea por `session('comercio_activo_id')` y devuelve vacio sin sesion — bajo Sanctum, `hasPermissionTo()` de los services denegaria siempre. Se extendio para aceptar el **comercio explicito** que dejo `ApiTenantMiddleware` en `TenantService`, sin romper el camino web. Los tokens de integracion autorizan por abilities (no por permisos de usuario); los services solo chequean permisos cuando hay un usuario actor real.

#### Endpoints publicos (por slug)

- `GET /v1/tiendas/{slug}`: datos de la tienda + bloque `entrega` (modo_promesa, acepta_lo_antes_posible, demoras, usa_franjas) + `formas_pago` declarables contra entrega/retiro — (aditivo 2026-07-21, RF-T18) filtradas por `formas_pago_sucursales.disponible_en_tienda=true` (no solo `activo`) y ordenadas por el `orden` que el comercio define en el panel (antes por nombre; `FormaPago::esDeclarableEnTienda()` es la regla unica que usan tanto este endpoint como `carrito/cotizar` y el alta de pedidos) + (aditivo 2026-07-17, RF-T7/RF-T6) `analytics` (`{ga4_measurement_id, meta_pixel_id}`, cada uno `null` si no esta cargado) + `tema` (`Tienda::temaCompleto()`, el efectivo YA mergeado con defaults, sub-objetos `portada`/`textos`/`redes`/`catalogo`/`destacados`/`promos` aditivos desde RF-T13) + `comportamiento` (objeto reservado, vacio en v1) + (aditivo 2026-07-17, RF-T11) `logo_url`/`portada_url`: URLs ABSOLUTAS (`url($tienda->logoUrl())`, host del request — la tienda corre en otro origen), `null` si no hay imagen cargada + (aditivo 2026-07-20, RF-T16) `encargos: {activo, anticipacion_horas, max_dias_adelante}` — con `activo:true` la tienda ofrece "Encargar para otro dia" incluso con la tienda cerrada (el encargo valida contra SU calendario, no el de atencion) + (aditivo 2026-07-21, RF-T19) `checkout: {pedir_email: 'no'|'opcional'|'obligatorio', pedir_cumpleanios: bool}` — que datos del cliente pide el checkout de esta sucursal (el cumpleanios NUNCA es obligatorio, ver seccion "Datos del cliente en el checkout" mas abajo).
- `GET /v1/tiendas/{slug}/franjas?tipo=`: slots de la jornada en modo franjas (cupos = Fase 8).
- `GET /v1/tiendas/{slug}/encargos[?fecha=Y-m-d]` (RF-T16, `TiendaController::encargos`): sin `fecha` → `{activo, fechas: [{fecha, label}]}` (dias de la ventana `[ahora+anticipacion_horas, hoy+max_dias_adelante]` con al menos un slot, via `DeliveryEnvioService::fechasEncargosDisponibles()`); con `fecha` → `{activo, fecha, slots: [{hora: ISO8601, label: "HH:MM"}]}` (slots de 30 min del dia, via `slotsEncargos()`). Encargos inactivos ⇒ `activo:false` con listas vacias (nunca error).
- `GET /v1/tiendas/{slug}/catalogo?tipo=`: catalogo segun criterio RF-17 (activo + vendible + `visible_tienda` + disponible por tipo); agotados vienen marcados `pedible:false`. Precios FINALES calculados por `PrecioService` (nunca localmente, D12). Los grupos de opcionales que viajan por articulo son los ASIGNADOS a ese articulo EN LA SUCURSAL de la tienda (`ArticuloGrupoOpcional`, mismo criterio que `OpcionalService::obtenerOpcionalesParaVenta` del panel), con `precio_extra` de la asignacion (override por articulo, no el del catalogo global de `Opcional`); un grupo sin opciones vivas no se publica. Shape: `{grupo_id, nombre, tipo, obligatorio, min, max, opciones:[{opcional_id, nombre, precio_extra, disponible}]}`. **Cache HTTP (RF-T5)**: responde con `ETag` (md5 del payload) + `Cache-Control: public, max-age=60`; si el cliente manda `If-None-Match` con el mismo ETag devuelve `304` sin body (es el endpoint mas golpeado de la tienda). **Cache server-side (aditivo 2026-07-17, RF-T5; centralizado 2026-07-20, RF-T14)**: ademas del ETag, el armado del payload (`CatalogoTiendaService::catalogo()`) se cachea 60s con `Cache::remember(CatalogoTiendaService::cacheKey($comercioId, $sucursalId, $tipo), 60, ...)` — sin esto, cada revalidacion `If-None-Match` (que igual pega al servidor) recalculaba el catalogo completo contra la BD tenant y el motor de precios aunque terminara respondiendo `304`. Efecto: un cambio de catalogo/precio puede demorar hasta 60s en reflejarse en la tienda. `CatalogoTiendaService::cacheKey()` (key `tienda_catalogo:{comercio_id}:{sucursal_id}:{tipo}`) e `invalidarCache($comercioId, $sucursalId)` (forgetea ambos tipos de pedido) son METODOS ESTATICOS pensados para que otros puntos del sistema invaliden el cache al guardar algo que afecte al catalogo — hoy los usa `ConfiguracionTiendaArticulos` (RF-T14, ver seccion del panel arriba) tras cada guardado inmediato de galeria/badges/destacado/orden.
  - **Precios tachados y promos genericas** (aditivo 2026-07-18, RF-T13): cada articulo suma `precio_lista` (float o `null`) — el precio ANTES de promociones, para mostrar tachado junto al precio de oferta. `CatalogoTiendaService::precioLista()` lo deriva del MISMO `$precioInfo` de `PrecioService` (nunca lo recalcula): `precio_final * subtotal_sin_descuento / subtotal_con_descuento`, redondeado a 2 decimales; devuelve `null` si no hubo descuento o si los subtotales no permiten escalar (`descuento_total <= 0`, `subtotal_con_descuento <= 0`, etc.) — la tienda NO muestra tachado en ese caso. La respuesta del catalogo suma ademas `promociones_genericas: [{nombre, descripcion, precio_fijo, condiciones}]` (`CatalogoTiendaService::promocionesGenericas()`): promociones de alcance GENERAL (no atadas a un articulo puntual) vigentes HOY para la sucursal —`Promocion` automatica (sin cupon, `activas()->vigentes()->porSucursal()->automaticas()->conUsosDisponibles()`) SIN condicion `por_articulo` (combos por cantidad/total/forma de pago, descuentos por categoria) + `PromocionEspecial` automatica (`MODO_AUTOMATICA`, NxM/grupos) de la sucursal cuyo `canal_venta_id` es nulo o es el canal TIENDA. "Vigente hoy" filtra por `dias_semana` (vacio = todos los dias, `dayOfWeek` con domingo=0); las que ademas tienen horario limitado igual entran en la lista (el detalle de horario queda en `descripcion`). Alimenta el aviso "Promociones de hoy" de la home (`tema.promos.mostrar_home`); lista vacia = sin aviso. **`precio_fijo` y `condiciones` enriquecidos** (aditivo 2026-07-21, RF-T21): `precio_fijo` es `float|null` — el precio final fijo cuando la promo es de ese tipo (`Promocion::tipo === 'precio_fijo'` con `valor`, o `PromocionEspecial` de combo/menu con `precio_tipo === PRECIO_FIJO` y `precio_valor`); en cualquier otro tipo de promo va `null`. `condiciones` es `list<string>` legible por humanos, generado por `condicionesLegiblesComun()`/`condicionesLegiblesEspecial()`: para `Promocion` reusa `PromocionCondicion::obtenerDescripcion()` (mismo texto que ve el operador en el panel); para `PromocionEspecial` describe la mecanica NxM ("Llevas 3, pagas 2" / "Llevas 3 y 1 va de regalo" segun tenga `nxm_paga` o `nxm_bonifica`); AMBOS tipos suman la restriccion de dias/horario si la promo la tiene (`restriccionDiasYHorario()`: "Solo Lun, Mar" / "De 18:00 a 23:00" / "Desde las 20:00" / "Hasta las 15:00"). `condiciones` es `[]` si la promo no tiene ninguna restriccion estructurada mas alla de estar vigente.
  - **Galeria y badges por articulo** (aditivo 2026-07-20, RF-T14): cada articulo suma `imagenes: [url, ...]` (galeria de tienda ordenada, URLs ABSOLUTAS con `url()`; `[]` si el articulo no tiene fotos de tienda — la tienda cae a `imagen_url`, que se MANTIENE tal cual) y `badges: [{tipo, texto}]` (maximo 4, saneados por `Articulo::badgesTienda()` — ver seccion del panel arriba; `texto` es `null` salvo en `custom`; `[]` si no tiene badges). `CatalogoTiendaService::catalogo()` precarga la relacion `imagenesTienda` en el `with()` del query principal (evita N+1). Tipos de badge desconocidos no viajan (el core sanea al leer `badges_tienda`), pero el contrato exige que la tienda IGNORE tipos que no reconozca (tolerancia a catalogo futuro).
  - **`permite_encargo`** (aditivo 2026-07-20, RF-T16): bool por articulo, espejo directo de `articulos.permite_programado`. Apto para pedidos por encargue; la tienda avisa antes de cotizar, el core valida igual server-side (`validarProgramado()`).
- `POST /v1/tiendas/{slug}/envios/cotizar`: `{latitud, longitud, ?hora_pactada}` → `{alcance, pedible, costo_envio, distancia_km, zona, demora_estimada_min}`.
- `POST /v1/tiendas/{slug}/carrito/cotizar`: cotizacion server-side del carrito completo (**`CotizadorCarritoTienda`**, harness headless del trait `WithCalculoVenta` — mismo motor de precios/promos/cupones que el panel). Con Bearer de consumidor, cotiza con SU cliente materializado (mismo total que el checkout, D12). Los `opcional_id` de `items.*.opcionales` se validan contra las asignaciones (`ArticuloGrupoOpcional`) del articulo EN LA SUCURSAL de la tienda — no asignado o `disponible:false` → `422`. Se cobran al `precio_extra` de la asignacion (no el global del `Opcional`) y el precio del item que ve el motor de calculo YA INCLUYE los opcionales (paridad con el panel `WithOpcionales`: las promociones aplican sobre el precio con opcionales, `precio_opcionales` viaja aparte como desglose). **Encargo** (aditivo 2026-07-20, RF-T16): con `entrega.programado_para`, el controller valida TEMPRANO contra `DeliveryEnvioService::validarProgramado()` (slot vencido/invalido o articulo sin `permite_encargo` → `422 {code:'encargo_invalido'}`) antes de delegar en `CotizadorCarritoTienda` — el checkout falla ahi y no recien en el alta. **Multi-pago** (aditivo 2026-07-21, RF-T18 F2): `pagos: [{forma_pago_id, monto}]` (`min:1,max:2`) + `costo_envio` (nullable numeric) — si viaja `pagos[]`, el `forma_pago_id` singular se ignora y la respuesta suma `pagos: [{forma_pago_id, nombre, monto_base, ajuste_porcentaje, monto_ajuste, monto_final, permite_vuelto}]` con `total_a_pagar` recalculado (suma de `monto_final`); incompatible con `usar_puntos` (`422 {code:'pagos_invalidos'}`). Ver seccion "Multi-pago en la tienda" mas abajo para el algoritmo completo.
- `POST /v1/tiendas/{slug}/pedidos` (throttle 15/min, `PedidoTiendaService`): alta de pedido invitado o consumidor. `entrega.{franja|lo_antes_posible}` validados contra la config (franja inventada/vencida → 422); `entrega.programado_para` (RF-T16, aditivo 2026-07-20) es la rama ENCARGO — valida contra el calendario propio de encargos y los articulos del carrito (`validarProgramado()`), tiene prioridad sobre franja/asap y **saltea el bloqueo de tienda cerrada** (`estaAbierto()`); `pago.{forma_pago_id, paga_con}` crea un pago PLANIFICADO (nunca cobra); `pagos: [{forma_pago_id, monto, paga_con?}]` (aditivo 2026-07-21, RF-T18 F2, `min:1,max:2`) reemplaza al singular cuando viaja y crea N pagos PLANIFICADOS (uno por FP, mismo shape que el alta manual del panel) — incompatible con `usar_puntos`. `cliente.fecha_nacimiento` (aditivo 2026-07-21, RF-T19, `nullable|date|before:today`) se persiste en el cliente tenant y, con Bearer de consumidor, en su cuenta global; email obligatorio segun `checkout.pedir_email` de la sucursal (ver seccion "Datos del cliente en el checkout"). Bloqueos: fuera de horario/alcance/agotados → 422 (un encargo valido no cuenta como "fuera de horario"). Entra "por aceptar" o `confirmado` segun `aceptacion_pedidos_externos`.
- `GET /v1/tiendas/{slug}/pedidos/{token_seguimiento}`: seguimiento publico. El estado interno `facturado` **NUNCA se expone** (el GET lo mapea a `entregado`, el broadcast no lo emite). Incluye `lo_antes_posible`, `demorado` (timeout vencido), `repartidor_en_camino` (solo delivery).
- `POST /v1/tiendas/{slug}/pedidos/{token_seguimiento}/cancelar`: cancelacion por el consumidor, permitida hasta `confirmado` (antes de `en_preparacion`).

#### Endpoints de consumidores (RF-T1..T3, `App\Http\Controllers\Api\V1\Consumidores\*`)

Base `/v1/consumidores`, sin `api.tenant` (la cuenta es cross-comercio, BD `config`). **Decision RF-T1 (2026-07-16)**: se puede pedir SIN verificar el email; la verificacion desbloquea el historial. Throttle por endpoint via 3er parametro del middleware inline (`throttle:N,1,prefijo`) — ver gotcha de bucket compartido en la seccion 5.

- **Auth** (`AuthController`):
  - `POST /registro` (throttle 5/min): `{nombre, email, password (min 8), telefono?}` → `201 {data:{token, consumidor}}`, crea el `Consumidor`, dispara el email de verificacion (best-effort: si el mailer falla solo se loguea, el token igual se devuelve) y el Bearer sirve YA.
  - `POST /login` (throttle 10/min): `{email, password}` → `{data:{token, consumidor}}`; credenciales invalidas → `422 validacion` (mensaje generico, no revela si el email existe).
  - `POST /logout` *(Bearer)*: `$request->user()->currentAccessToken()->delete()`.
  - `GET /me` *(Bearer)*: perfil `{id, nombre, email, telefono, fecha_nacimiento, email_verificado}` — `fecha_nacimiento` (aditivo 2026-07-21, RF-T19) formato `Y-m-d` o `null`, pre-llena el checkout de cualquier tienda para este consumidor.
  - `POST /verificar`: `{token}` (el link del email aterriza en la TIENDA, que reenvia el token aca sin sesion) → idempotente, marca `email_verified_at`. Token invalido/vencido → excepcion generica (422).
  - `POST /reenviar-verificacion` *(Bearer)*: no-op si ya esta verificado.
  - `POST /recuperar`: `{email}` → SIEMPRE `200 {ok:true}` (no revela existencia); si el consumidor existe manda el link de reset.
  - `POST /restablecer`: `{token, password}` → cambia el password y **revoca TODOS los tokens** del consumidor (`$consumidor->tokens()->delete()`), fuerza re-login en cualquier sesion abierta.
  - Un Bearer de **integracion** (tokenable `Comercio`) contra cualquiera de estos endpoints → `403 sin_permiso` (middleware `api.consumidor`).
  - **`ConsumidorTokenService`** (`app/Services/Consumidores/`): tokens de verificacion/reset **STATELESS** (sin tabla), formato `base64url("{tipo}|{consumidor_id}|{expira_ts}").firma`, firma HMAC-SHA256 con `APP_KEY` + una "sal" que invalida el token cuando pierde sentido: verificacion usa el `email` actual (si cambia, los tokens viejos mueren); reset usa un fragmento (24 chars) del hash de password actual (usar el token para resetear cambia el hash → la firma deja de matchear → **single-use sin storage**). TTLs: verificacion 48h, reset 60min.
- **Direcciones** (`DireccionesController`, `GET|POST|PATCH|DELETE /direcciones[/{id}]`, Bearer): CRUD sobre `consumidor_direcciones`, tope `MAX_DIRECCIONES = 10`. La primera direccion creada queda `es_default` automaticamente; marcar otra como default desmarca las demas (`update(['es_default' => false])` previo); no se permite des-defaultear la unica default via `PATCH` (se ignora ese campo); al borrar la default, la mas nueva de las restantes se promueve. El checkout de la tienda las precarga — el pedido en si SIGUE copiando snapshot, esta tabla no se referencia desde `pedidos_delivery`.
- **Historial** (`PedidosController::index`, `GET /pedidos?page=&per_page=`, Bearer, **email verificado obligatorio** → `403 sin_permiso` si no): fan-out CROSS-comercio controlado — recorre los `comercio_id` con tienda (`config.tiendas`) mas los que ya tienen mapping (`consumidor_comercio`), por cada uno hace `TenantService::usarComercioParaProceso()` y consulta `pedidos_delivery.consumidor_id` (indexado), acotado a `CAP_POR_COMERCIO = 500` filas por comercio, mergea todo por fecha desc y pagina en memoria. Un tenant inaccesible se loguea y NO voltea el historial completo (try/catch por comercio). `estado` normaliza con la MISMA verdad publica del seguimiento (`facturado` interno → `entregado`). "Re-pedir" no es un endpoint: la tienda arma el carrito con `GET /pedidos/{token}` (publico) y **re-cotiza** (`carrito/cotizar`), nunca reusa precios historicos.

#### Endpoints de marketplace (RF-T4, `App\Http\Controllers\Api\V1\MarketplaceController`, publico, sin tenant)

- `GET /v1/tiendas?lat=&lng=&rubro_id=`: tiendas de `config.tiendas` con `habilitada=true`, filtradas por `rubro_id` de su `comercio` si se manda. **`MarketplaceTiendasService`** (`app/Services/Pedidos/`) resuelve cada tienda con un **snapshot cacheado** (`Cache::remember("marketplace_tienda_{id}", 300s)`) que abre la conexion tenant UNA vez cada 5 min (sucursal activa + `usa_delivery`, `config_delivery`, zonas dibujadas — solo los vertices de los poligonos) para no reabrir tenants en cada request; `valida:false` tambien se cachea (sucursal inactiva/sin delivery no reintenta). Alcance con la MISMA semantica de `envios/cotizar` (D5): zonas dibujadas > radio general > sin georreferenciar = `alcance: "desconocido"` (nunca se inventa alcance). Con `lat/lng` excluye `alcance=fuera` y ordena por `distancia_km`; sin coordenadas lista todas en orden alfabetico. `DeliveryEnvioService::estaAbiertoSegunConfig()` (variante de `estaAbierto()` que recibe el array de config YA resuelto, en vez de reabrir la sucursal) calcula `abierta_ahora` sobre el snapshot cacheado. `logo_url` (RF-T11, aditivo 2026-07-17): prima el logo propio de la tienda (`$tienda->logoUrl()`, config del panel); si no hay, cae al logo de pantalla-cliente/empresa de la sucursal (`Sucursal::logoPantallaClienteUrl()`, comportamiento previo a RF-T11). Por estar dentro del snapshot cacheado, un cambio de logo puede demorar hasta 5 min en reflejarse en el marketplace.
- `GET /v1/rubros`: catalogo global de `config.rubros` activos, `Cache::remember('marketplace_rubros', 3600s)`.

#### Endpoints de integracion (Bearer + `X-Sucursal-Id`)

- `GET /v1/pedidos-delivery` / `GET /v1/pedidos-delivery/{id}` (`pedidos:read`).
- `POST /v1/pedidos-delivery` (`pedidos:write`): mismo payload del endpoint publico, origen `api` + `origen_referencia` del integrador.
- `PATCH /v1/pedidos-delivery/{id}` (`pedidos:write`): `{estado, repartidor_id, observaciones, observacion_estado}`. `en_camino` con repartidor crea la salida implicita (mismo circuito que el panel); `entregado` sobre un pedido EN una salida en la calle → 422 (se entrega via la vuelta del panel, E9).
- `GET /v1/delivery/config` / `GET /v1/repartidores` (`config:read`).

#### Tiempo real (Reverb)

- **Seguimiento publico** (canal PUBLICO, sin auth): `pedidos-delivery.seguimiento.{token_seguimiento}`, evento `SeguimientoActualizado` (`PedidoSeguimientoPublicoBroadcast`, `ShouldBroadcastNow`) con `{estado, estado_label, repartidor, hora_pactada_at, lo_antes_posible, at}` en cada cambio de estado de un pedido externo.
- **Panel/integraciones** (canal privado del comercio): `comercios.{comercioId}.pedidos-delivery`, evento `PedidoDeliveryBroadcast` (`{pedidoId, sucursalId, tipo, at}`, tipos `creado`/`estado_cambiado`/`pago_cambiado`/`cancelado`/`convertido_venta`) — extiende `TenantBroadcastEvent`, mismo patron que `PedidoMostradorBroadcast`.

#### Alta y publicacion de una tienda

Hasta RF-T10 (2026-07-17), `config.tiendas` se creaba por consola/soporte: `Tienda::create(['comercio_id' => ..., 'sucursal_id' => ..., 'slug' => ..., 'habilitada' => true])`. Desde RF-T10 hay UI propia en el panel; desde RF-T11 (mismo dia, rediseno) el alta y la publicacion se moveron al **switch maestro** del padre `App\Livewire\Pedidos\ConfiguracionDelivery` (apartado "Tienda Online" al final de la pantalla `Configuracion > Delivery / Take Away`, permiso `func.tienda.config`):

- Prender el switch sin tienda creada dispara `ConfiguracionDelivery::toggleTiendaOnline()` → `TiendaService::crearParaSucursal()`, que crea el registro **despublicada** (`habilitada=false`) con un slug SUGERIDO (`Str::slug(nombre_comercio.' '.nombre_sucursal)`, truncado a 55 chars, sufijo numerico incremental ante colision de `slug` UNIQUE global) — el operador lo revisa/edita y publica cuando esta lista.
- El switch en si NO publica: solo marca la intencion (`$tiendaPublicada` en memoria) y despliega el resto del apartado (sub-componente `ConfiguracionTienda` para slug/analytics/tema/logo/portada). La publicacion efectiva (`tiendas.habilitada`) se persiste recien en `ConfiguracionDelivery::guardarConfig()`, comparando contra el valor persistido (`$tiendaPublicadaPersistida`) para no pisar sin necesidad.
- El alta por consola sigue siendo valida (multi-sucursal, migraciones de datos, etc.).

#### CORS y config del proyecto tienda (RF-T5)

- **`config/cors.php`** (nuevo): `allowed_origins` lee `CORS_ALLOWED_ORIGINS` (env, coma-separado; `*` = abierto, default actual hasta configurar el dominio real del frontend tienda). `exposed_headers` incluye `ETag` (aditivo 2026-07-17): sin exponerlo en CORS, un consumidor browser-side cross-origin no puede LEER el header `ETag` de la respuesta aunque venga, y la revalidacion `If-None-Match` del cache HTTP del catalogo (arriba) no puede funcionar desde `bcn-tienda`.
- **`config/tienda.php`** (nuevo): `url` (env `TIENDA_URL`, default `APP_URL`) — dominio del frontend `bcn-tienda` que consumen los Mailables de consumidores (`app/Mail/Consumidores/VerificarEmailConsumidor`, `RecuperarPasswordConsumidor`, vistas markdown en `resources/views/emails/consumidores/`) para armar los links `{TIENDA_URL}/verificar?token=...` y `{TIENDA_URL}/recuperar?token=...`. Tambien arma la URL publica que muestra `ConfiguracionTienda` (`{TIENDA_URL}/tienda/{slug}`).

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

#### Costo vigente de un articulo (sucursal + fallback al consolidado)

```sql
-- Fila de la sucursal (preferida); si no existe, cae a la fila consolidada (sucursal_id NULL)
SELECT costo_ultimo, costo_promedio,
       COALESCE(costo_reposicion, costo_ultimo) as costo_reposicion_efectivo
FROM {PREFIX}articulo_costos
WHERE articulo_id = ? AND sucursal_id = ?
ORDER BY sucursal_id DESC  -- prioriza la fila con sucursal_id no nulo si la busqueda es exacta
LIMIT 1;
```

#### Deuda con un proveedor en la sucursal activa (cta cte, semantica de pasivo)

```sql
SELECT COALESCE(SUM(haber), 0) - COALESCE(SUM(debe), 0) as saldo_deudor_nuestro,
       COALESCE(SUM(saldo_favor_haber), 0) - COALESCE(SUM(saldo_favor_debe), 0) as saldo_favor
FROM {PREFIX}movimientos_cuenta_corriente_proveedor
WHERE proveedor_id = ?
  AND sucursal_id = ?
  AND estado = 'activo';
```

#### Compras pendientes de un proveedor (aging por vencimiento, FIFO)

```sql
-- RF-B11 (hardening-circuito-precios): una NC re-usa saldo_pendiente con OTRA
-- semantica ("monto aplicado contra la origen", casi siempre > 0) -- toda
-- consulta de deuda debe excluirla (scope Compra::sinNotasCredito()).
SELECT id, numero_comprobante, numero_comprobante_proveedor, fecha, fecha_vencimiento,
       total, saldo_pendiente,
       DATEDIFF(CURDATE(), fecha_vencimiento) as dias_vencido
FROM {PREFIX}compras
WHERE proveedor_id = ?
  AND sucursal_id = ?
  AND estado = 'completada'
  AND (tipo_comprobante IS NULL OR tipo_comprobante NOT LIKE 'nota_credito%')
  AND saldo_pendiente > 0
ORDER BY fecha_vencimiento ASC, fecha ASC;
```

#### Compras por cuenta de compra en un periodo (RF-22, las NC restan)

```sql
SELECT cc.nombre as cuenta, COUNT(*) as comprobantes,
       SUM(CASE WHEN c.tipo_comprobante NOT LIKE 'nota_credito%' THEN c.total ELSE 0 END) as compras,
       SUM(CASE WHEN c.tipo_comprobante LIKE 'nota_credito%' THEN c.total ELSE 0 END) as notas_credito
FROM {PREFIX}compras c
LEFT JOIN {PREFIX}cuentas_compra cc ON cc.id = c.cuenta_compra_id
WHERE c.sucursal_id = ?
  AND c.estado = 'completada'
  AND c.fecha BETWEEN ? AND ?
GROUP BY cc.id, cc.nombre
ORDER BY (compras - notas_credito) DESC;
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

#### Movimientos de cuenta empresa generados por integraciones de pago

```sql
-- Todos los ingresos por cobros de integracion en un periodo
SELECT mce.created_at, mce.monto, mce.origen_id AS transaccion_id,
       ce.nombre AS cuenta, mce.sucursal_id
FROM {PREFIX}movimientos_cuenta_empresa mce
JOIN {PREFIX}cuentas_empresa ce ON ce.id = mce.cuenta_empresa_id
WHERE mce.origen_tipo = 'IntegracionPagoTransaccion'
  AND mce.tipo = 'ingreso'
  AND mce.estado = 'activo'
  AND mce.created_at >= ?
  AND mce.created_at <= ?
ORDER BY mce.created_at DESC;
```

```sql
-- Verificar idempotencia: buscar si ya existe movimiento para una transaccion
SELECT id FROM {PREFIX}movimientos_cuenta_empresa
WHERE origen_tipo = 'IntegracionPagoTransaccion'
  AND origen_id = ?
LIMIT 1;
```

```sql
-- Cuentas empresa con identidad de proveedor vinculada (subtipo mercadopago)
SELECT id, nombre, subtipo, identificador_externo, saldo_actual
FROM {PREFIX}cuentas_empresa
WHERE subtipo = 'mercadopago'
  AND identificador_externo IS NOT NULL
  AND activo = 1;
```

#### Corridas de conciliacion por cuenta y estado

```sql
-- Corridas activas (generando o pendiente de revision) por cuenta
SELECT id, estado, origen, desde, hasta, total_matcheados,
       total_solo_proveedor, total_solo_sistema, created_at
FROM {PREFIX}conciliaciones_cuenta
WHERE cuenta_empresa_id = ?
  AND estado IN ('generando', 'pendiente_revision')
ORDER BY created_at DESC
LIMIT 1;
```

```sql
-- Historial de corridas de una cuenta (todas, para auditoria)
SELECT cc.id, cc.estado, cc.origen, cc.desde, cc.hasta,
       cc.monto_propuesto_ingresos, cc.monto_propuesto_egresos,
       cc.aplicada_en, u.name AS aplicada_por
FROM {PREFIX}conciliaciones_cuenta cc
LEFT JOIN users u ON u.id = cc.aplicada_por
WHERE cc.cuenta_empresa_id = ?
ORDER BY cc.created_at DESC;
```

#### Movimientos generados por una corrida de conciliacion

```sql
-- Todos los movimientos generados al aplicar una corrida
SELECT mce.id, mce.tipo, mce.monto, mce.concepto_descripcion,
       mce.saldo_anterior, mce.saldo_posterior, mce.created_at
FROM {PREFIX}movimientos_cuenta_empresa mce
JOIN {PREFIX}conciliacion_filas cf ON cf.id = mce.origen_id
WHERE mce.origen_tipo = 'ConciliacionFila'
  AND cf.conciliacion_cuenta_id = ?
  AND mce.estado = 'activo'
ORDER BY mce.created_at ASC;
```

```sql
-- Verificar idempotencia cross-corrida: buscar si ya existe movimiento
-- para una combinacion (cuenta, tipo_fila, id_externo_proveedor)
SELECT mce.id
FROM {PREFIX}movimientos_cuenta_empresa mce
JOIN {PREFIX}conciliacion_filas cf ON cf.id = mce.origen_id
JOIN {PREFIX}conciliaciones_cuenta cc ON cc.id = cf.conciliacion_cuenta_id
WHERE mce.origen_tipo = 'ConciliacionFila'
  AND cc.cuenta_empresa_id = ?
  AND cf.tipo = ?
  AND cf.id_externo = ?
  AND mce.estado = 'activo'
LIMIT 1;
```

```sql
-- Resumen de conciliaciones aplicadas: impacto en saldo por cuenta y periodo
SELECT cc.desde, cc.hasta, cc.aplicada_en,
       SUM(CASE WHEN mce.tipo = 'ingreso' THEN mce.monto ELSE 0 END) AS ingresos_aplicados,
       SUM(CASE WHEN mce.tipo = 'egreso'  THEN mce.monto ELSE 0 END) AS egresos_aplicados
FROM {PREFIX}conciliaciones_cuenta cc
JOIN {PREFIX}conciliacion_filas cf ON cf.conciliacion_cuenta_id = cc.id
JOIN {PREFIX}movimientos_cuenta_empresa mce ON mce.origen_id = cf.id
  AND mce.origen_tipo = 'ConciliacionFila' AND mce.estado = 'activo'
WHERE cc.cuenta_empresa_id = ?
  AND cc.estado = 'aplicada'
GROUP BY cc.id, cc.desde, cc.hasta, cc.aplicada_en
ORDER BY cc.aplicada_en DESC;
```

#### Posicion de IVA de un CUIT en un periodo

```sql
-- Debito fiscal, credito fiscal y percepciones/retenciones sufridas de IVA.
-- Usar: cuit_id = ? y periodo_fiscal = 'YYYY-MM'
-- SUM(mf.monto) ya neta las notas de credito: sus movimientos se registran en
-- NEGATIVO, imputados al periodo de la NC (no al periodo del comprobante original).
SELECT
  mf.naturaleza,
  mf.sentido,
  SUM(mf.monto) AS total
FROM {PREFIX}movimientos_fiscales mf
JOIN {PREFIX}impuestos imp ON imp.id = mf.impuesto_id
WHERE mf.cuit_id = ?
  AND mf.periodo_fiscal = ?
  AND mf.estado = 'activo'
  AND imp.tipo = 'iva'
GROUP BY mf.naturaleza, mf.sentido;
```

#### Percepciones de IIBB sufridas por jurisdiccion en un periodo

```sql
SELECT
  imp.jurisdiccion,
  mf.naturaleza,
  SUM(mf.monto) AS total
FROM {PREFIX}movimientos_fiscales mf
JOIN {PREFIX}impuestos imp ON imp.id = mf.impuesto_id
WHERE mf.cuit_id = ?
  AND mf.periodo_fiscal = ?
  AND mf.estado = 'activo'
  AND mf.sentido = 'sufrido'
  AND imp.tipo = 'iibb'
GROUP BY imp.jurisdiccion, mf.naturaleza
ORDER BY imp.jurisdiccion;
```

#### Movimientos fiscales de un comprobante especifico

```sql
SELECT mf.*, imp.codigo, imp.nombre
FROM {PREFIX}movimientos_fiscales mf
JOIN {PREFIX}impuestos imp ON imp.id = mf.impuesto_id
WHERE mf.origen_tipo = 'ComprobanteFiscal'
  AND mf.origen_id = ?
  AND mf.estado = 'activo';
```

#### Configuracion impositiva vigente de un CUIT

```sql
-- Impuestos activos configurados para un CUIT a la fecha de hoy.
SELECT imp.codigo, imp.nombre, imp.tipo, imp.jurisdiccion,
  cfg.inscripto, cfg.es_agente_percepcion, cfg.es_agente_retencion,
  cfg.alicuota, cfg.alicuota_minimo_base, cfg.monto_minimo_percepcion,
  cfg.vigente_desde, cfg.vigente_hasta
FROM {PREFIX}cuit_impuesto_configs cfg
JOIN {PREFIX}impuestos imp ON imp.id = cfg.impuesto_id
WHERE cfg.cuit_id = ?
  AND cfg.inscripto = 1
  AND (cfg.vigente_desde IS NULL OR cfg.vigente_desde <= CURDATE())
  AND (cfg.vigente_hasta IS NULL OR cfg.vigente_hasta >= CURDATE())
ORDER BY imp.tipo, imp.jurisdiccion;
```

### 4.2 Convenciones de Datos

**Estados posibles de cada entidad:**

| Entidad | Estados | Descripcion |
|---|---|---|
| Venta | `completada`, `pendiente`, `cancelada` | Pendiente = cuenta corriente sin saldar |
| Compra | `borrador`, `completada`, `cancelada` | Ciclo de vida puro (D11, spec compras-costos): lo impago se deriva SIEMPRE de `saldo_pendiente > 0`, nunca del estado. Migracion de datos: el viejo `pendiente` paso a `completada` conservando su `saldo_pendiente` |
| Cobro | `activo`, `anulado` | |
| MovimientoCuentaCorrienteProveedor | `activo`, `anulado` | Anulado = contraasiento. Semantica de PASIVO: HABER aumenta la deuda, DEBE la reduce (invertida respecto de clientes) |
| PagoProveedor | `activo`, `anulado` | Analogo de `Cobro` |
| PagoProveedorPago | `activo`, `anulado` | Analogo de `CobroPago`; bloqueo de anulacion por `cierre_turno_id` solo si el origen del renglon es `'caja'` (D16) |
| VentaPago.estado | `activo`, `pendiente`, `anulado` | |
| VentaPago.estado_facturacion | `no_facturado`, `facturado`, `pendiente_de_facturar`, `error_arca` | Estado fiscal del pago; `pendiente_de_facturar` = FC nueva en cola por fallo ARCA |
| CobroPago | `activo`, `anulado` | |
| MovimientoStock | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaCorriente | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoCuentaEmpresa | `activo`, `anulado` | Anulado = contraasiento |
| MovimientoFiscal | `activo`, `anulado` | Anulado = contraasiento (solo correccion de errores de carga, no NC). El monto es positivo salvo las reversas de NC (propias, en su propio periodo), que son negativas; fuera de eso el signo semantico lo dan `sentido` + `naturaleza`. |
| Caja | `abierta`, `cerrada` | |
| ComprobanteFiscal | `pendiente`, `autorizado`, `rechazado`, `anulado` | |
| Produccion | `confirmado`, `anulado` | |
| ProvisionFondo | `pendiente`, `confirmado`, `cancelado` | |
| DepositoBancario | `pendiente`, `confirmado`, `cancelado` | |
| IntegracionPagoTransaccion | `pendiente`, `confirmado`, `confirmado_manual`, `cancelado`, `fallido`, `expirado` | `estaEnEstadoTerminal()` = true para todos menos `pendiente`. `estaConfirmada()` = true para `confirmado` y `confirmado_manual`. |
| ConciliacionCuenta | `generando`, `pendiente_revision`, `aplicada`, `descartada`, `error` | `generando` y `pendiente_revision` son activos (solo uno por cuenta). `aplicada`/`descartada`/`error` son terminales. |
| ConciliacionFila.clasificacion | `matcheado`, `solo_proveedor`, `solo_sistema`, `ya_registrado` | |
| ConciliacionFila.accion | `generar_movimiento`, `ignorar`, `sin_accion` | `sin_accion` para filas `solo_sistema` y `ya_registrado`; propuestas arrancan en `generar_movimiento`. |
| PedidoDelivery.estado_pedido | `borrador`, `confirmado`, `en_preparacion`, `listo`, `en_camino`, `entregado`, `facturado`, `cancelado` | `en_camino` es COMPARTIDO: delivery = en la calle con repartidor; take-away = "Para retirar" (sin repartidor). `listo` es opcional (`usa_estado_listo`) |
| PedidoDelivery.tipo | `delivery`, `take_away` | Determina si exige direccion/repartidor/envio |
| PedidoDelivery.origen | `panel`, `tienda`, `api` | `tienda`/`api` entran "por aceptar" o `confirmado` segun `aceptacion_pedidos_externos` |
| RepartidorFondo.estado | `abierto`, `rendido` | Ciclo largo: puede quedar `abierto` entre salidas, no se exige rendir a la vuelta |
| RepartidorFondoMovimiento.tipo | `entrega_inicial`, `refuerzo`, `cobro_pedido`, `vuelto`, `liquidacion_envios`, `devolucion`, `rendicion`, `ajuste` | Append-only, sin `updated_at`. El saldo teorico del fondo = suma de `monto` (con signo) |
| DeliverySalida.estado | `armando`, `en_camino`, `finalizada` | Un repartidor tiene UN viaje activo a la vez |
| DeliverySalidaPedido.resultado | `pendiente`, `entregado`, `no_entregado` | Pivot append-only (historial de intentos, incl. re-despachos) |

**Formatos de fecha:**
- Fechas se almacenan como `timestamp` o `date` en MySQL.
- Fechas de creacion/actualizacion: `created_at`, `updated_at` (timezone del servidor).
- Fechas de negocio (`venta.fecha`, `cobro.fecha`): generalmente `date` o `timestamp`.

**Formatos de moneda y cantidades:**
- **Cantidades de stock**: `decimal(12,3)` -- 3 decimales para soportar articulos pesables (kg, gr, lt)
- **Montos monetarios**: `decimal(12,2)` -- 2 decimales
- Costos unitarios como `decimal(12,4)` (4 decimales: `articulo_costos`, `historial_costos`, `articulo_proveedor`, `compras_detalle.costo_unitario_computable` -- el PPP y los descuentos en cascada generan fracciones; el precio se redondea recien al final de la cadena, ver 3.8.2/3.8.3).
- Porcentajes como `decimal(5,2)` o `decimal(8,2)` (utilidad objetivo: `decimal(6,2)`).
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
- `proveedores.saldo_cache` -- **Cache** consolidado del comercio. Se recalcula desde `movimientos_cuenta_corriente_proveedor` (patron Cliente, con `lockForUpdate`); el saldo POR SUCURSAL (D19, el que rige la operatoria de pago) se calcula siempre del ledger, nunca del cache.
- `compras.saldo_pendiente` -- **Cache** de lo impago de la compra. La fuente de verdad es `movimientos_cuenta_corriente_proveedor` (via `pago_proveedor_compras`).
- `articulo_costos.costo_ultimo/costo_promedio` -- NO son cache de otra tabla: son el dato primario, escrito UNICAMENTE por `CostoService::registrarDesdeCompra()`/`actualizarManual()`.

**Soft deletes:**
Las siguientes tablas usan soft delete (`deleted_at` no nulo = eliminado):
- `ventas`, `clientes`, `articulos`, `categorias`, `cobros`, `promociones`, `promociones_especiales`, `listas_precios`, `grupos_opcionales`, `opcionales`, `comprobantes_fiscales`, `grupos_etiquetas`, `etiquetas`, `impresoras`, `cuits`, `puntos_venta`, `pedidos_delivery` (espejo de `pedidos_mostrador`, que tambien usa soft delete).

Al consultar estas tablas, siempre agregar `AND deleted_at IS NULL` a menos que se quieran incluir registros eliminados. `compras` y `proveedores` NO usan soft delete (una compra se cancela, no se borra; un proveedor se desactiva con `activo = false`).

**Patron append-only (ledger):**
Las tablas `movimientos_stock`, `movimientos_cuenta_corriente`, `movimientos_cuenta_corriente_proveedor`, `movimientos_cuenta_empresa`, `movimientos_fiscales`, `historial_costos`, `venta_pagos` (para cambios de pago), `integraciones_pago_eventos`, `conciliaciones_cuenta`/`conciliacion_filas`, `repartidor_fondo_movimientos` y `delivery_salida_pedidos` (historial de intentos de entrega) siguen el patron append-only:
- Los registros nunca se modifican ni eliminan.
- Las anulaciones se hacen creando un **contraasiento** que invierte los montos (movimientos) o marcando el registro como `estado = 'anulado'` y creando uno nuevo (venta_pagos).
- El original se vincula al contraasiento via `anulado_por_movimiento_id` (movimientos) o via `venta_pago_reemplazado_id` (venta_pagos).
- Para calcular saldos de movimientos: sumar todos los activos.
- Para calcular totales de una venta: sumar los `venta_pagos` con `estado = 'activo'`.
- `historial_costos` no tiene contraasiento propio (es un log, no un ledger de saldos): una cancelacion que restaura un costo anterior simplemente agrega una fila nueva con `origen = 'cancelacion'`.
- `movimientos_cuenta_corriente_proveedor` invierte la semantica de `movimientos_cuenta_corriente`: HABER aumenta la deuda (pasivo), DEBE la reduce.

---

## 5. Notas Tecnicas

### 5.1 Patrones Livewire: propiedades publicas y snapshot

Los arrays PHP usados solo para traducir etiquetas en la vista (ej: tipos de ajuste, opciones de redondeo) NO deben declararse como propiedades publicas en componentes Livewire. Al ser publicas, Livewire las serializa en el snapshot cifrado y cualquier cambio de version o de contenido genera `CorruptComponentPayloadException`.

**Solucion**: declarar esos arrays como computed properties (`#[Computed]`) o getters privados que se recalculan en cada render. Esto aplica preventivamente a cualquier componente wizard o de configuracion compleja.

Componentes donde se aplico este patron: `WizardListaPrecio` (paso 2, opciones de ajuste/redondeo) y `ListarPromociones`.

### 5.2 Scope `conStock` en MovimientosStock

El componente `MovimientosStock` ejecuta busquedas de articulos via un scope del modelo. El scope debe existir en el modelo `Articulo`; de lo contrario lanza `BadMethodCallException: Call to undefined method conStock()`. El fix consiste en asegurarse de invocar el metodo de busqueda correcto disponible en el modelo (ej: `buscarPorNombreOCodigo` o equivalente) en lugar de un scope inexistente.

### 5.3 Invalidar CatalogoCache al guardar sucursal

`CatalogoCache` almacena la coleccion de sucursales con TTL de 1 hora. Si se guarda la sucursal (nombre, domicilio, logo) sin invalidar el cache, la UI seguira mostrando los datos viejos hasta que expire el TTL. El metodo `ConfiguracionEmpresa::guardarSucursal()` y `eliminarLogoSucursal()` llaman a `CatalogoCache::clear()` despues de persistir para garantizar consistencia inmediata. Cualquier otra operacion que modifique campos de la sucursal visibles en el catalogo debe hacer lo mismo.

### 5.4 Alpine.raw() con librerias JS que hacen comparacion de identidad

Ver seccion "Gotcha: Alpine.raw() con librerias que hacen comparacion de identidad" en el bloque del trait `ManejaDomicilio` (al final de la seccion 2 — Sistema Impositivo). Resumen: pasar `window.Alpine.raw(obj)` en lugar de `obj` directo cuando la libreria de terceros verifica identidad de instancia con `===` (ej: `AdvancedMarkerElement` de Google Maps verifica que `map` sea la instancia real de `Map`, no un `Proxy` de Alpine).

### 5.5 Cero negativo (-0.0) y checksum de Livewire

PHP puede producir `-0.0` al restar floats en ciertas condiciones (ej: distribuir el descuento de una promo compartida entre items cuando uno de los participantes se invita — su contribucion al bucket queda en `-0.0`). El problema surge porque `json_encode(-0.0)` en PHP produce la cadena `-0`, pero el runtime JS de Livewire lo reenvía como `0` en el siguiente request; el checksum del snapshot deja de coincidir y Livewire lanza `CorruptComponentPayloadException`.

**Solucion implementada** en el trait `WithCalculoVenta` mediante el hook de lifecycle `dehydrateWithCalculoVenta()`: justo antes de serializar el componente, el array `$this->resultado` se recorre recursivamente y cualquier float cuyo valor sea `== 0.0` se fuerza a `+0.0` literal. La condicion `-0.0 == 0.0` es `true` en PHP (igualdad de valor, no de bit), por lo que el cast captura el cero negativo sin afectar otros valores.

**Regla general**: si un componente Livewire expone propiedades publicas con floats calculados mediante restas (especialmente desgloses de IVA, descuentos o ajustes prorateados) y el usuario experimenta `CorruptComponentPayloadException` al cobrar o al enviar el formulario, verificar si algun float puede ser `-0.0`. Aplicar normalizacion recursiva con la condicion `is_float($v) && $v == 0.0 ? 0.0 : $v` antes de devolver el estado al frontend.

Componentes que aplican este patron: `NuevaVenta` y `NuevoPedidoMostrador` (ambos usan `WithCalculoVenta`).

### 5.6 Throttle inline de Laravel: bucket compartido por `sha1(user|ip)`

El middleware `throttle:N,1` (sin nombre de limiter) arma la clave del rate limiter como `sha1($ruta_del_middleware.'|'.$user_o_ip)` — **no incluye la ruta HTTP**. Si dos `throttle:N,1` inline distintos se aplican en el mismo request (uno de grupo + uno de ruta especifica, ej. `/v1/consumidores/registro` con throttle de grupo 60/min + throttle propio 5/min), ambos pueden terminar compartiendo el MISMO contador y pisandose entre si.

**Solucion**: pasar un 3er parametro como prefijo, `throttle:N,1,prefijo` (ej. `throttle:5,1,c-registro`), que Laravel concatena a la clave y separa los contadores. Descubierto al implementar los throttles agresivos de `/v1/consumidores/*` (RF-T5, Fase 0 tienda-online) — cada endpoint de auth tiene su propio prefijo (`c-registro`, `c-login`, `c-verificar`, etc.).
