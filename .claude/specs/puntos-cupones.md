# Sistema de Puntos y Cupones de Fidelización - Especificación

## Estado: EN REVISIÓN

> Spec completo para programa de fidelización: acumulación de puntos por compras, canje como descuento o por artículos, cupones de descuento (desde puntos o promocionales). Multi-sucursal configurable.

---

## Contexto y Motivación

Los comercios necesitan un programa de fidelización para incentivar la recurrencia de clientes. El sistema permite acumular puntos con compras y canjearlos como descuento o por artículos específicos, además de generar cupones de descuento. Actualmente el canal de venta es uno (NuevaVenta/mostrador), pero la arquitectura debe soportar futuros canales (salón, delivery, ecommerce, facturación mayorista).

---

## Principios de Diseño

1. **Ledger append-only**: Movimientos de puntos siguen el patrón de `MovimientoCuentaCorriente` — contraasientos para anulaciones, nunca borrar
2. **Cache denormalizado**: Saldo de puntos cacheado en `clientes` (mismo patrón que `saldo_deudor_cache`)
3. **Dos ratios independientes**: Acumulación ($/punto) y canje (punto/$) configurables por separado
4. **Canal-agnostic**: Los puntos se acumulan/canjean independiente del canal de venta; el movimiento registra origen para analytics
5. **Multiplicador por forma de pago**: Cada forma de pago define cuánto multiplica los puntos (0 = no suma, 2 = doble)
6. **Configuración por comercio**: Cada comercio define sus propios ratios, con activación granular por sucursal y por cliente

---

## Requisitos Funcionales

### Configuración

#### RF-01: Configurar programa de puntos
- Tabla de configuración con una fila por comercio (tenant)
- Campos: activo, modo (global/por_sucursal), ratio acumulación, ratio canje, mínimo canje, redondeo
- Accesible desde menú "Programa de Puntos" bajo Ventas

#### RF-02: Activar/desactivar por sucursal
- Cada sucursal puede tener el programa activo o inactivo
- Si `modo_acumulacion = 'global'`: el saldo es único por cliente, los movimientos registran `sucursal_id`
- Si `modo_acumulacion = 'por_sucursal'`: el saldo se calcula filtrando por `sucursal_id`

#### RF-03: Activar/desactivar por cliente
- Campo `programa_puntos_activo` en `clientes` (default true)
- Si false, el cliente no acumula ni puede canjear puntos

#### RF-04: Multiplicador por forma de pago
- Campo `multiplicador_puntos` en `formas_pago` (decimal, default 1.00)
- 0 = no genera puntos (ej: cuenta corriente)
- 2 = doble puntos (ej: efectivo)
- Se aplica por tramo de pago en ventas mixtas

### Acumulación de Puntos

#### RF-05: Acumular puntos al completar venta
- Fórmula: `puntos = redondeo(SUM(monto_pago * multiplicador_forma_pago) / monto_por_punto)`
- Se calcula sobre cada `VentaPago` de la venta (excepto pagos con puntos y cupones)
- Requiere: programa activo + sucursal activa + cliente seleccionado + cliente con programa activo
- Sin cliente asignado en la venta → no se acumulan puntos

#### RF-06: Cálculo en ventas mixtas
- Cada VentaPago aporta: `monto_final * multiplicador_forma_pago`
- Se suman todos los aportes y se divide por `monto_por_punto`
- Ejemplo: $600 efectivo (×2) + $400 tarjeta (×1) con ratio 1pto/$100 → `(1200+400)/100 = 16 puntos`

#### RF-07: Exclusiones de acumulación
- Lo pagado con puntos (VentaPago con `es_pago_puntos = true`) NO genera puntos
- Lo cubierto por cupones NO genera puntos
- Cobranza de cuenta corriente NO genera puntos adicionales (ya se sumaron en la venta)

#### RF-08: Modo global vs por sucursal
- **Global**: saldo = SUM(puntos) de todos los movimientos activos del cliente
- **Por sucursal**: saldo = SUM(puntos) de movimientos activos WHERE sucursal_id = sucursal_activa
- Configurable en `configuracion_puntos.modo_acumulacion`

### Canje de Puntos

#### RF-09: Canjear puntos como forma de pago
- El cliente indica cuánto $ quiere pagar con puntos
- Sistema convierte: `puntos_necesarios = ceil(monto / valor_punto_canje)`
- Valida que tenga suficientes puntos y que supere el mínimo de canje
- Se registra como VentaPago con `es_pago_puntos = true`, `afecta_caja = false`
- Crea movimiento de puntos tipo 'canje_descuento' (negativo)

#### RF-10: Canjear artículo por puntos
- Artículos con `puntos_canje` definido pueden ser canjeados
- El canje es todo-o-nada: se necesitan exactamente los puntos indicados
- Se marca en VentaDetalle: `pagado_con_puntos = true`, `puntos_usados = X`
- El artículo se descuenta del total a pagar (su valor no se cobra en dinero)
- El stock se descuenta normalmente (el artículo sale del inventario)
- Crea movimiento de puntos tipo 'canje_articulo' (negativo)

#### RF-11: Coexistencia de canjes en misma venta
- Se puede canjear un artículo por puntos Y pagar parte del resto con puntos
- Orden: primero se aplican artículos canjeados (reducen total), luego el desglose de pagos (incluye puntos como pago)
- Total_a_pagar = total_venta - valor_articulos_canjeados
- El desglose de pagos cubre el total_a_pagar (con puntos + efectivo + tarjeta, etc.)

#### RF-12: Mínimo de puntos para canjear
- Configurable en `configuracion_puntos.minimo_canje`
- Si el cliente tiene menos del mínimo, no se muestra opción de canje en el POS

### Anulación

#### RF-13: Revertir puntos al anular venta
- Al cancelar una venta, se crean contraasientos para revertir:
  - Puntos acumulados → contraasiento negativo
  - Puntos canjeados → contraasiento positivo (devuelve puntos)
- Actualizar cache del cliente

#### RF-14: Bloquear anulación si puntos negativos
- Si al revertir los puntos acumulados el saldo quedaría negativo, bloquear la cancelación
- Mostrar mensaje: "No se puede anular: el cliente ya canjeó los puntos ganados en esta venta"

### Cupones

#### RF-15: Crear cupón desde puntos del cliente
- El cliente "compra" un cupón con sus puntos
- Los puntos se descuentan al momento de CREAR el cupón (no al usarlo)
- El cupón queda atado a ese cliente (`cliente_id` NOT NULL)
- Solo ese cliente puede usar el cupón
- Crea movimiento de puntos tipo 'canje_cupon' (negativo)

#### RF-16: Crear cupón promocional
- No requiere puntos ni cliente
- Cualquier persona puede usarlo (cliente_id = NULL)
- Creado por admin/usuario con acceso al menú de cupones

#### RF-17: Cupón aplica a total
- `aplica_a = 'total'`
- `modo_descuento = 'monto_fijo'`: descuenta $X del total
- `modo_descuento = 'porcentaje'`: descuenta X% del total
- Si el descuento supera el total, el descuento se limita al total (no genera saldo a favor)

#### RF-18: Cupón aplica a artículos específicos
- `aplica_a = 'articulos'`
- Tabla `cupon_articulos` vincula cupón ↔ artículos que bonifica
- `modo_descuento = 'porcentaje'` con `valor_descuento = 100` → bonifica 100% (artículo gratis)
- `modo_descuento = 'porcentaje'` con `valor_descuento = 50` → 50% de descuento en esos artículos
- `modo_descuento = 'monto_fijo'` → descuenta $X del precio de esos artículos
- Solo aplica si el artículo está en el carrito de la venta

#### RF-19: Control de uso de cupones
- `uso_maximo`: 0 = ilimitado, N = máximo N usos
- `uso_actual`: contador que se incrementa con cada uso
- Cada uso registrado en `cupon_usos` con: venta_id, cliente_id, sucursal_id, monto_descontado, fecha, usuario_id
- Validar: no expirado + uso_actual < uso_maximo (o ilimitado) + activo

#### RF-20: Código de cupón
- Autogenerado con formato `CUP-XXXXXX` (alfanumérico, 6 chars)
- Editable manualmente antes de guardar
- Único por comercio (UNIQUE constraint)
- Generar QR/código de barras del código para futuro escaneo

#### RF-21: Restricción de uso por tipo
- Cupón tipo `'puntos'` (cliente_id NOT NULL): solo puede usarlo el cliente dueño
- Cupón tipo `'promocional'` (cliente_id NULL): lo puede usar cualquiera

### Ajustes Manuales

#### RF-22: Ajuste manual de puntos
- Sumar o restar puntos a un cliente con motivo obligatorio
- Requiere permiso funcional `puntos.ajuste_manual` (solo Super Admin inicialmente)
- Crea movimiento tipo 'ajuste_manual' con concepto descriptivo
- Queda registro de quién hizo el ajuste y por qué

### Descuento General

#### RF-31: Descuento general porcentual (%)
- El usuario aplica un porcentaje de descuento a TODOS los artículos del carrito de forma masiva
- Mecanismo: utiliza el mismo `ajuste_manual` existente por renglón, aplicándolo a cada item
- Cada item queda con `ajuste_manual_tipo = 'porcentaje'` y `ajuste_manual_valor = X`
- El precio de cada item se recalcula: `precio = precio_base - (precio_base × porcentaje / 100)`
- `calcularVenta()` corre normal: promociones automáticas se calculan sobre los precios ya ajustados
- Conceptos (items sin artículo) también reciben el descuento (removible individualmente por renglón)

#### RF-32: Descuento general fijo ($)
- El usuario ingresa un monto fijo a descontar del total de la venta
- Se aplica DESPUÉS de calcular todos los descuentos (promociones + ajustes por renglón)
- Si el monto supera el total post-descuentos, se limita a ese total (no genera saldo a favor)
- Se registra a nivel de cabecera `ventas` (no distribuido por renglón)
- Se muestra como línea separada en el resumen de totales: "Descuento general: -$500"

#### RF-33: Exclusividad entre tipos
- Descuento general % y descuento general $ son mutuamente excluyentes en la misma venta
- Si el usuario tiene activo un % y elige $, se quitan los ajustes manuales de todos los items (restaurando precios originales) y se aplica el fijo
- Descuento general puede coexistir con cupones y canje de puntos

#### RF-34: Herencia en items nuevos
- Si hay un descuento general % activo y se agrega un nuevo artículo al carrito, este hereda automáticamente el porcentaje de descuento aplicándose `ajuste_manual` con el mismo valor
- El usuario puede sobreescribir el descuento de un renglón individual después (cambiándolo manualmente desde los botones $ o % del renglón)

#### RF-35: Re-aplicación del descuento general
- Si el usuario re-aplica un descuento general % (cambia el valor), se sobreescribe el ajuste manual de TODOS los items, incluidos los que fueron modificados individualmente
- Esto es intencional: el descuento general siempre pisa todo al momento de aplicarse

#### RF-36: Tope de descuento por rol
- Campo `descuento_maximo_porcentaje` en tabla `roles` (decimal, NULL = sin tope)
- Si el usuario tiene múltiples roles, se toma el MAX de sus `descuento_maximo_porcentaje`
- Aplica al descuento general %: no puede superar el tope
- Aplica al descuento general $: el monto fijo no puede superar ese % del total pre-descuento
- Si el usuario intenta superar su tope → toast de error indicando el máximo permitido

#### RF-37: Permiso para descuento general
- Permiso funcional `ventas.descuento_general`
- Sin este permiso, el usuario no ve la opción de descuento general en el modal de descuentos
- Super Admin tiene el permiso por defecto

#### RF-38: Almacenamiento en venta
- En tabla `ventas`: `descuento_general_tipo`, `descuento_general_valor`, `descuento_general_monto`
- Para % general: `descuento_general_monto` = suma de los descuentos aplicados por renglón (calculado al grabar, para reportes). El detalle individual queda en `ventas_detalle.ajuste_manual_tipo/valor`
- Para $ fijo: `descuento_general_monto` = monto efectivo descontado de la cabecera
- Ambos tipos registran `descuento_general_tipo` y `descuento_general_valor` para reconstruir la intención del usuario

### UI — POS (NuevaVenta)

#### RF-23: Mostrar saldo de puntos al seleccionar cliente
- Al seleccionar cliente en NuevaVenta, mostrar badge con saldo de puntos disponibles
- Si programa inactivo o cliente sin programa → no mostrar
- Si modo por_sucursal → mostrar saldo de la sucursal activa

#### RF-24: Canjear puntos en el POS
- Acceso desde sección "Canjear puntos" dentro del modal de Descuentos y Beneficios
- Visible solo si: cliente seleccionado con puntos ≥ mínimo_canje
- El cliente indica cuánto $ quiere pagar con puntos
- Mostrar: puntos disponibles, valor máximo canjeable, puntos que se consumirán
- Al confirmar, agregar al desglose de pagos como "Pago con Puntos"

#### RF-25: Canjear artículo por puntos en el POS
- Artículos con `puntos_canje` muestran indicador visual (ícono/badge) en el catálogo y en el renglón del carrito
- Botón en el renglón del detalle (junto a los botones $ y % existentes) para canjear con puntos
- Solo visible si: cliente seleccionado con puntos suficientes
- Si se canjea, el artículo aparece marcado como "Canjeado con X puntos"
- Su valor se descuenta del total a pagar

#### RF-26: Aplicar cupón en el POS
- Acceso desde sección "Aplicar cupón" dentro del modal de Descuentos y Beneficios
- Campo para ingresar código de cupón (o escanear QR en el futuro)
- Validar: existe, no expirado, usos disponibles, cliente correcto (si tipo puntos)
- Si aplica a total → mostrar descuento en el resumen
- Si aplica a artículos → marcar los artículos bonificados en el carrito
- Cupón + puntos + descuento general pueden coexistir en la misma venta

#### RF-39: Modal de Descuentos y Beneficios
- Botón en el panel de acciones de NuevaVenta que abre un modal unificado (con atajo de teclado)
- El modal tiene secciones/tabs navegables con teclado (Tab/Shift+Tab entre secciones, Enter para confirmar):
  - **Descuento general**: radio % o $ → input de valor → botón aplicar/quitar. Muestra tope del usuario. Requiere permiso `ventas.descuento_general`
  - **Aplicar cupón**: input de código → botón validar → muestra detalle del cupón → botón aplicar/quitar
  - **Canjear puntos**: muestra saldo disponible → input monto $ → muestra puntos a consumir → botón aplicar/quitar. Requiere cliente seleccionado con puntos ≥ mínimo
- Cada sección muestra estado actual (ej: "Descuento 10% aplicado", "Cupón CUP-ABC123 aplicado", "Canje $500 = 10 puntos")
- Al cerrar el modal, los cambios ya están aplicados en el carrito/totales
- Navegación completa con teclado: flechas, Tab, Enter, Escape para cerrar

### UI — Administración

#### RF-27: Pantalla Programa de Puntos
- Ruta: `/ventas/programa-puntos`
- SucursalAware para ver/configurar por sucursal
- **Tab Configuración**: formulario con todos los campos de `configuracion_puntos`, toggle por sucursal
- **Tab Consulta**: buscar cliente, ver saldo, historial de movimientos paginado
- **Tab Ajustes**: formulario para ajuste manual (requiere permiso `puntos.ajuste_manual`)

#### RF-28: Pantalla Cupones
- Ruta: `/ventas/cupones`
- **Tab Listado**: tabla con todos los cupones, filtros (tipo, estado, vencimiento), CRUD
- **Tab Crear**: formulario para crear cupón promocional o desde puntos
- **Tab Historial**: tabla de usos de cupones con filtros

#### RF-29: Sección en ficha del cliente
- En GestionarClientes, sección "Puntos y Cupones"
- Mostrar: saldo actual, puntos acumulados histórico, puntos canjeados histórico
- Toggle `programa_puntos_activo`
- Últimos movimientos de puntos
- Cupones del cliente (activos/usados)

#### RF-30: Ticket de venta
- Si se acumularon puntos → mostrar "Puntos ganados: +X"
- Si se canjearon puntos → mostrar "Puntos usados: -X"
- Mostrar saldo actual después de la venta: "Saldo de puntos: Y"

---

## Modelo de Datos

### Tablas nuevas (tenant — con prefijo)

#### `configuracion_puntos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `activo` | tinyint(1) | 0 | Programa habilitado globalmente |
| `modo_acumulacion` | enum('global','por_sucursal') | 'global' | Modo de saldo |
| `monto_por_punto` | decimal(12,2) | 100.00 | Cuántos $ para ganar 1 punto |
| `valor_punto_canje` | decimal(12,2) | 50.00 | Cuánto vale 1 punto en $ al canjear |
| `minimo_canje` | int unsigned | 10 | Mínimo puntos para habilitar canje |
| `redondeo` | enum('floor','round','ceil') | 'floor' | Redondeo de puntos fraccionarios |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

#### `configuracion_puntos_sucursales`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `sucursal_id` | bigint unsigned | — | FK a sucursales |
| `activo` | tinyint(1) | 1 | Puntos activos en esta sucursal |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| | UNIQUE | | `(sucursal_id)` |

#### `movimientos_puntos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cliente_id` | bigint unsigned | — | FK a clientes |
| `sucursal_id` | bigint unsigned | — | FK a sucursales |
| `fecha` | datetime | — | Fecha del movimiento |
| `tipo` | enum | — | Ver tipos abajo |
| `puntos` | int | — | Positivo = acumulación, negativo = consumo |
| `monto_asociado` | decimal(12,2) | 0 | Monto de la transacción asociada |
| `documento_tipo` | varchar(50) | NULL | 'venta', 'venta_pago', 'cupon', 'ajuste' |
| `documento_id` | bigint unsigned | NULL | ID del documento referenciado |
| `venta_id` | bigint unsigned | NULL | FK directa a ventas (shortcut) |
| `venta_pago_id` | bigint unsigned | NULL | FK a venta_pagos (para canje como pago) |
| `cupon_id` | bigint unsigned | NULL | FK a cupones (para canje por cupón) |
| `concepto` | varchar(255) | — | Descripción legible |
| `observaciones` | text | NULL | Notas (para ajustes manuales) |
| `estado` | enum('activo','anulado') | 'activo' | |
| `anulado_por_movimiento_id` | bigint unsigned | NULL | FK a movimiento contraasiento |
| `usuario_id` | bigint unsigned | — | FK a users (quien registró) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Tipos de movimiento:**
- `'acumulacion'` — Puntos ganados por venta
- `'canje_descuento'` — Puntos usados como pago/descuento directo
- `'canje_articulo'` — Puntos usados para canjear artículo específico
- `'canje_cupon'` — Puntos usados para crear cupón
- `'ajuste_manual'` — Ajuste manual (positivo o negativo)
- `'anulacion'` — Contraasiento por anulación de venta

**Índices:** `(cliente_id, estado)`, `(cliente_id, sucursal_id, estado)`, `(venta_id)`, `(cupon_id)`

#### `cupones`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `codigo` | varchar(50) | — | Código único del cupón (CUP-XXXXXX) |
| `tipo` | enum('puntos','promocional') | — | Origen del cupón |
| `cliente_id` | bigint unsigned | NULL | FK clientes (NOT NULL si tipo='puntos') |
| `descripcion` | varchar(255) | NULL | Descripción del cupón |
| `modo_descuento` | enum('monto_fijo','porcentaje') | — | Tipo de descuento |
| `valor_descuento` | decimal(12,2) | — | Monto en $ o porcentaje |
| `aplica_a` | enum('total','articulos') | 'total' | A qué aplica el descuento |
| `uso_maximo` | int unsigned | 1 | 0 = ilimitado |
| `uso_actual` | int unsigned | 0 | Contador de usos |
| `fecha_vencimiento` | date | NULL | NULL = no vence |
| `activo` | tinyint(1) | 1 | |
| `puntos_consumidos` | int unsigned | 0 | Puntos que costó crear (tipo='puntos') |
| `created_by_usuario_id` | bigint unsigned | — | FK users (quien creó) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| | UNIQUE | | `(codigo)` |

#### `cupon_articulos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cupon_id` | bigint unsigned | — | FK a cupones |
| `articulo_id` | bigint unsigned | — | FK a articulos |
| `created_at` | timestamp | | |
| | UNIQUE | | `(cupon_id, articulo_id)` |

#### `cupon_usos`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `cupon_id` | bigint unsigned | — | FK a cupones |
| `venta_id` | bigint unsigned | — | FK a ventas |
| `cliente_id` | bigint unsigned | NULL | FK clientes (quien lo usó) |
| `sucursal_id` | bigint unsigned | — | FK sucursales |
| `monto_descontado` | decimal(12,2) | — | Monto efectivo descontado |
| `fecha` | datetime | — | |
| `usuario_id` | bigint unsigned | — | FK users (cajero) |
| `created_at` | timestamp | | |

### Tablas modificadas (tenant)

#### `roles` — Agregar
- `descuento_maximo_porcentaje` decimal(5,2) DEFAULT NULL AFTER `guard_name` — Tope de descuento % permitido para el rol (NULL = sin tope)

#### `formas_pago` — Agregar
- `multiplicador_puntos` decimal(4,2) DEFAULT 1.00 AFTER `ajuste_porcentaje` — Multiplicador de puntos por forma de pago

#### `articulos` — Agregar
- `puntos_canje` int unsigned DEFAULT NULL AFTER `precio_base` — Puntos necesarios para canjear (NULL = no canjeable)

#### `articulos_sucursales` — Agregar
- `puntos_canje` int unsigned DEFAULT NULL AFTER `precio_base` — Override de puntos_canje por sucursal

#### `clientes` — Agregar
- `programa_puntos_activo` tinyint(1) DEFAULT 1 AFTER `bloqueado_por_mora` — Si participa del programa
- `puntos_acumulados_cache` int unsigned DEFAULT 0 AFTER `programa_puntos_activo` — Total histórico acumulado
- `puntos_canjeados_cache` int unsigned DEFAULT 0 AFTER `puntos_acumulados_cache` — Total histórico canjeado
- `puntos_saldo_cache` int DEFAULT 0 AFTER `puntos_canjeados_cache` — Saldo disponible actual
- `ultimo_movimiento_puntos_at` timestamp NULL AFTER `puntos_saldo_cache` — Fecha último movimiento

#### `ventas_detalle` — Agregar
- `pagado_con_puntos` tinyint(1) DEFAULT 0 AFTER `ajuste_manual_valor` — Si fue canjeado con puntos
- `puntos_usados` int unsigned DEFAULT 0 AFTER `pagado_con_puntos` — Puntos consumidos

#### `venta_pagos` — Agregar
- `es_pago_puntos` tinyint(1) DEFAULT 0 AFTER `es_cuenta_corriente` — Si es pago con puntos
- `puntos_usados` int unsigned DEFAULT 0 AFTER `es_pago_puntos` — Puntos consumidos

#### `ventas` — Agregar
- `descuento_general_tipo` enum('porcentaje','monto_fijo') DEFAULT NULL AFTER `observaciones` — Tipo de descuento general aplicado
- `descuento_general_valor` decimal(12,2) DEFAULT NULL AFTER `descuento_general_tipo` — Valor ingresado por el usuario (% o $)
- `descuento_general_monto` decimal(12,2) DEFAULT 0 AFTER `descuento_general_valor` — Monto efectivo descontado (para %: suma de descuentos por renglón; para $: monto fijo aplicado)
- `cupon_id` bigint unsigned DEFAULT NULL AFTER `descuento_general_monto` — FK cupón aplicado
- `monto_cupon` decimal(12,2) DEFAULT 0 AFTER `cupon_id` — Monto descontado por cupón
- `puntos_ganados` int unsigned DEFAULT 0 AFTER `monto_cupon` — Puntos acumulados en esta venta
- `puntos_usados` int unsigned DEFAULT 0 AFTER `puntos_ganados` — Puntos canjeados en esta venta

---

## Pantallas UI

### Pantalla 1: Programa de Puntos (`/ventas/programa-puntos`)
**Componente**: `App\Livewire\Puntos\ProgramaPuntos`
**Traits**: SucursalAware, #[Lazy]
**Menú**: Ventas → Programa de Puntos (slug: `programa-puntos`, orden: 4)

- **Tab Configuración**:
  - Toggle programa activo/inactivo
  - Modo: global / por sucursal (select)
  - Ratio acumulación: monto por punto (input numérico)
  - Ratio canje: valor punto en $ (input numérico)
  - Mínimo canje (input numérico)
  - Redondeo: floor/round/ceil (select)
  - Toggle por sucursal: lista de sucursales con switch activo/inactivo

- **Tab Consulta de Puntos**:
  - Buscador de cliente (nombre, CUIT, teléfono)
  - Card con: saldo actual, acumulados totales, canjeados totales
  - Tabla paginada de movimientos: fecha, tipo, puntos (+/-), concepto, venta#, usuario
  - Filtros: rango de fechas, tipo de movimiento

- **Tab Ajustes Manuales** (requiere permiso `puntos.ajuste_manual`):
  - Buscador de cliente
  - Input: puntos a sumar/restar (positivo/negativo)
  - Input: motivo (obligatorio)
  - Botón confirmar con confirmación modal

### Pantalla 2: Cupones (`/ventas/cupones`)
**Componente**: `App\Livewire\Cupones\GestionCupones`
**Traits**: #[Lazy]
**Menú**: Ventas → Cupones (slug: `cupones`, orden: 5)

- **Tab Listado**:
  - Tabla: código, tipo (badge), descripción, descuento, aplica a, usos (actual/max), vencimiento, estado
  - Filtros: tipo (puntos/promocional), estado (activo/inactivo/vencido), búsqueda por código
  - Acciones: editar, activar/desactivar, ver detalle

- **Tab Crear Cupón**:
  - Tipo: promocional / desde puntos (radio)
  - Si desde puntos: buscador de cliente + puntos a consumir
  - Código: autogenerado + botón regenerar + editable
  - Modo descuento: monto fijo / porcentaje (select)
  - Valor descuento (input numérico)
  - Aplica a: total / artículos específicos (select)
  - Si artículos: buscador de artículos con multi-select
  - Uso máximo: 0=ilimitado, N (input numérico)
  - Fecha vencimiento (date picker, opcional)
  - Descripción (textarea)

- **Tab Historial de Uso**:
  - Tabla: fecha, cupón (código), venta#, cliente, sucursal, monto descontado, usuario
  - Filtros: rango de fechas, cupón específico, sucursal
  - Paginado

### Modificación: NuevaVenta (POS)
**Componente existente**: `App\Livewire\Ventas\NuevaVenta`

**Nuevas propiedades del componente:**
- `$descuentoGeneralActivo` (bool) — Si hay descuento general activo
- `$descuentoGeneralTipo` (string|null) — 'porcentaje' o 'monto_fijo'
- `$descuentoGeneralValor` (float|null) — El valor ingresado
- `$showModalDescuentos` (bool) — Controla visibilidad del modal

**Cambios en panel de acciones:**
- Nuevo botón "Descuentos" (con atajo de teclado) que abre modal de Descuentos y Beneficios
- Al seleccionar cliente: mostrar badge con saldo de puntos (si programa activo + cliente activo)

**Modal de Descuentos y Beneficios:**
- Sección 1 — Descuento general (si tiene permiso `ventas.descuento_general`):
  - Radio: porcentaje (%) / monto fijo ($)
  - Input numérico para el valor
  - Muestra tope del usuario: "Máx: X%" (leído del rol)
  - Botón "Aplicar" / "Quitar descuento" si ya hay uno activo
  - Estado: badge con descuento activo ("10% aplicado" o "$500 aplicado")
- Sección 2 — Aplicar cupón:
  - Input código + botón "Validar"
  - Al validar: card con detalle (tipo, descuento, vencimiento, usos restantes)
  - Botón "Aplicar" / "Quitar cupón"
  - Estado: badge con cupón activo
- Sección 3 — Canjear puntos (si cliente seleccionado con programa activo):
  - Muestra saldo disponible y valor máximo canjeable en $
  - Input monto $ a pagar con puntos
  - Muestra puntos que se consumirán
  - Botón "Aplicar" / "Quitar canje"
  - Estado: badge con canje activo
- Navegación: Tab entre secciones, Enter para confirmar acción, Escape para cerrar

**Cambios en tabla de detalle (por renglón):**
- Nuevo botón "Pts" en artículos con `puntos_canje` (junto a botones $ y % existentes)
- Solo visible si cliente seleccionado con puntos suficientes
- Al canjear: badge "Canjeado con X pts" reemplaza el precio
- En catálogo: ícono/badge en artículos con `puntos_canje` definido

**Cambios en resumen de totales:**
- Si descuento fijo activo: línea "Descuento general: -$X" entre descuentos de promos y total
- Si cupón activo: línea "Cupón (CUP-XXX): -$X"
- Si canje de puntos: aparece en desglose de pagos como "Pago con Puntos"

**Cambios en lógica:**
- `agregarArticulo()`: si `$descuentoGeneralActivo` y tipo='porcentaje', aplicar `ajuste_manual` automáticamente al nuevo item
- `aplicarDescuentoGeneral($tipo, $valor)`: nuevo método que aplica a todos los items
- `quitarDescuentoGeneral()`: restaura precios originales de todos los items
- `calcularVenta()`: si tipo='monto_fijo', restar `$descuentoGeneralValor` del total (limitado al total post-descuentos)
- `procesarVentaConDesglose()`: incluir `descuento_general_tipo/valor/monto` en `$datosVenta`

### Modificación: GestionarClientes
**Componente existente**: `App\Livewire\Clientes\GestionarClientes`

- Nueva sección "Puntos y Cupones" en modal/detalle del cliente:
  - Toggle `programa_puntos_activo`
  - Saldo de puntos actual
  - Puntos acumulados / canjeados histórico
  - Últimos 5-10 movimientos de puntos
  - Cupones del cliente (activos + últimos usados)

---

## Servicios

### `PuntosService` — `app/Services/PuntosService.php`

| Método | Descripción |
|--------|-------------|
| `acumularPuntosPorVenta(Venta $venta, Collection $pagos, int $usuarioId): ?MovimientoPunto` | Calcula y registra puntos ganados. Retorna null si no aplica. |
| `canjearPuntosComoDescuento(int $clienteId, int $sucursalId, float $montoDescuento, int $usuarioId): array` | Retorna `['puntos_usados' => int, 'monto_equivalente' => float]`. Crea movimiento tipo 'canje_descuento'. |
| `canjearArticuloConPuntos(int $clienteId, int $articuloId, int $sucursalId, int $puntosNecesarios, int $usuarioId): MovimientoPunto` | Crea movimiento tipo 'canje_articulo'. Valida disponibilidad. |
| `crearContraasientosVenta(Venta $venta, int $usuarioId): array` | Revierte puntos al anular venta. Retorna array de contraasientos. |
| `validarAnulacionVenta(Venta $venta): bool` | Verifica que el saldo no quede negativo post-anulación. |
| `ajustarPuntos(int $clienteId, int $sucursalId, int $puntos, string $concepto, int $usuarioId): MovimientoPunto` | Ajuste manual (+/-). |
| `obtenerSaldo(int $clienteId, ?int $sucursalId = null): int` | Calcula saldo desde movimientos (modo global o por sucursal). |
| `actualizarCacheCliente(int $clienteId): void` | Recalcula y actualiza campos cache en `clientes`. |
| `getConfiguracion(): ?ConfiguracionPuntos` | Retorna config del comercio actual. |
| `isProgramaActivo(?int $sucursalId = null): bool` | Verifica si el programa está activo (global + sucursal). |
| `calcularPuntosVenta(Collection $pagos): int` | Calcula puntos sin registrar (preview para mostrar en POS). |

### `CuponService` — `app/Services/CuponService.php`

| Método | Descripción |
|--------|-------------|
| `crearCuponDesdePuntos(int $clienteId, array $data, int $usuarioId): Cupon` | Crea cupón y descuenta puntos. Usa PuntosService internamente. |
| `crearCuponPromocional(array $data, int $usuarioId): Cupon` | Crea cupón sin puntos. |
| `validarCupon(string $codigo, ?int $clienteId = null): array` | Retorna `['valid' => bool, 'cupon' => ?Cupon, 'message' => string]`. |
| `aplicarCuponEnVenta(Cupon $cupon, Venta $venta, float $montoDescontado, int $usuarioId): CuponUso` | Registra uso, incrementa uso_actual, crea CuponUso. |
| `calcularDescuento(Cupon $cupon, float $totalVenta, array $articuloIdsEnCarrito = []): array` | Retorna `['monto_descuento' => float, 'articulos_bonificados' => array]`. |
| `generarCodigo(): string` | Genera código único `CUP-XXXXXX`. |
| `revertirUsoCupon(CuponUso $uso): void` | Revierte uso al anular venta (decrementa uso_actual). |

### Modificaciones a servicios existentes

#### `VentaService` — Cambios
- En `crearVenta()`: después del commit, llamar `PuntosService->acumularPuntosPorVenta()` si aplica
- En `crearVenta()`: procesar artículos canjeados con puntos (pagado_con_puntos en detalle)
- En `crearVenta()`: procesar cupón aplicado (registrar uso, monto descontado)
- En `cancelarVentaCompleta()`: llamar `PuntosService->validarAnulacionVenta()` antes de proceder
- En `cancelarVentaCompleta()`: llamar `PuntosService->crearContraasientosVenta()` y `CuponService->revertirUsoCupon()` si aplica

---

## Migraciones Necesarias

1. `add_multiplicador_puntos_to_formas_pago` — Agregar `multiplicador_puntos` a `formas_pago`
2. `add_puntos_canje_to_articulos` — Agregar `puntos_canje` a `articulos` y `articulos_sucursales`
3. `add_puntos_fields_to_clientes` — Agregar campos de puntos a `clientes`
4. `add_puntos_fields_to_ventas` — Agregar `cupon_id`, `monto_cupon`, `puntos_ganados`, `puntos_usados` a `ventas`
5. `add_puntos_fields_to_ventas_detalle` — Agregar `pagado_con_puntos`, `puntos_usados` a `ventas_detalle`
6. `add_puntos_fields_to_venta_pagos` — Agregar `es_pago_puntos`, `puntos_usados` a `venta_pagos`
7. `create_configuracion_puntos` — Crear tabla `configuracion_puntos`
8. `create_configuracion_puntos_sucursales` — Crear tabla `configuracion_puntos_sucursales`
9. `create_movimientos_puntos` — Crear tabla `movimientos_puntos`
10. `create_cupones` — Crear tabla `cupones`
11. `create_cupon_articulos` — Crear tabla `cupon_articulos`
12. `create_cupon_usos` — Crear tabla `cupon_usos`
13. `add_menu_items_puntos_cupones` — Agregar menu_items "Programa de Puntos" y "Cupones" bajo Ventas
14. `add_permisos_puntos` — Agregar permiso funcional `puntos.ajuste_manual`
15. `add_descuento_maximo_to_roles` — Agregar `descuento_maximo_porcentaje` a `roles`
16. `add_descuento_general_to_ventas` — Agregar `descuento_general_tipo/valor/monto` a `ventas`
17. `add_permiso_descuento_general` — Agregar permiso funcional `ventas.descuento_general`

**Nota:** Después de implementar, regenerar `database/sql/tenant_tables.sql`

---

## Traducciones

Claves nuevas necesarias:

| Clave (es) | en | pt |
|------------|----|----|
| Programa de Puntos | Loyalty Program | Programa de Pontos |
| Cupones | Coupons | Cupons |
| Puntos | Points | Pontos |
| Saldo de puntos | Points balance | Saldo de pontos |
| Puntos acumulados | Points earned | Pontos acumulados |
| Puntos canjeados | Points redeemed | Pontos resgatados |
| Canjear puntos | Redeem points | Resgatar pontos |
| Monto por punto | Amount per point | Valor por ponto |
| Valor del punto | Point value | Valor do ponto |
| Mínimo para canje | Minimum to redeem | Mínimo para resgate |
| Multiplicador de puntos | Points multiplier | Multiplicador de pontos |
| Ajuste manual | Manual adjustment | Ajuste manual |
| Motivo del ajuste | Adjustment reason | Motivo do ajuste |
| Puntos ganados en esta venta | Points earned in this sale | Pontos ganhos nesta venda |
| Puntos usados | Points used | Pontos usados |
| Puntos insuficientes | Insufficient points | Pontos insuficientes |
| Pagar con puntos | Pay with points | Pagar com pontos |
| Canjeado con puntos | Redeemed with points | Resgatado com pontos |
| Código de cupón | Coupon code | Código do cupom |
| Cupón aplicado | Coupon applied | Cupom aplicado |
| Cupón inválido | Invalid coupon | Cupom inválido |
| Cupón expirado | Coupon expired | Cupom expirado |
| Uso máximo alcanzado | Maximum uses reached | Uso máximo atingido |
| Crear cupón | Create coupon | Criar cupom |
| Cupón desde puntos | Coupon from points | Cupom de pontos |
| Cupón promocional | Promotional coupon | Cupom promocional |
| Monto fijo | Fixed amount | Valor fixo |
| Porcentaje | Percentage | Porcentagem |
| Aplica a | Applies to | Aplica-se a |
| Total de la venta | Sale total | Total da venda |
| Artículos específicos | Specific items | Artigos específicos |
| Uso único | Single use | Uso único |
| Usos ilimitados | Unlimited uses | Usos ilimitados |
| Historial de uso | Usage history | Histórico de uso |
| Programa inactivo | Program inactive | Programa inativo |
| Activar programa de puntos | Enable loyalty program | Ativar programa de pontos |
| Global (todas las sucursales) | Global (all branches) | Global (todas as filiais) |
| Por sucursal | Per branch | Por filial |
| Redondeo | Rounding | Arredondamento |
| Hacia abajo | Round down | Para baixo |
| Al más cercano | Round nearest | Mais próximo |
| Hacia arriba | Round up | Para cima |
| No se puede anular: el cliente ya canjeó los puntos ganados | Cannot cancel: customer already redeemed the earned points | Não é possível cancelar: o cliente já resgatou os pontos ganhos |
| Puntos por canje | Points for redemption | Pontos para resgate |
| Configuración de puntos | Points configuration | Configuração de pontos |
| Consulta de puntos | Points inquiry | Consulta de pontos |
| Descuento general | General discount | Desconto geral |
| Descuentos y beneficios | Discounts & benefits | Descontos e benefícios |
| Descuento general aplicado | General discount applied | Desconto geral aplicado |
| Máximo descuento permitido | Maximum discount allowed | Desconto máximo permitido |
| El descuento supera el máximo permitido para su rol | Discount exceeds the maximum allowed for your role | O desconto excede o máximo permitido para seu perfil |
| Quitar descuento | Remove discount | Remover desconto |
| Descuento fijo | Fixed discount | Desconto fixo |

---

## Criterios de Aceptación

### Configuración
- [ ] Comercio puede activar/desactivar programa de puntos
- [ ] Ratios de acumulación y canje son configurables independientemente
- [ ] Modo global/por_sucursal funciona correctamente
- [ ] Se puede activar/desactivar por sucursal individual
- [ ] Se puede activar/desactivar por cliente individual

### Acumulación
- [ ] Venta con cliente acumula puntos según fórmula (monto × multiplicador / ratio)
- [ ] Venta sin cliente NO acumula puntos
- [ ] Venta mixta calcula multiplicador por tramo de pago
- [ ] Forma de pago con multiplicador 0 no genera puntos en ese tramo
- [ ] Lo pagado con puntos no genera nuevos puntos
- [ ] Cache de puntos en `clientes` se actualiza correctamente
- [ ] Cobranza de cuenta corriente NO genera puntos adicionales

### Canje
- [ ] Cliente puede pagar parte de la venta con puntos (ingresa monto $)
- [ ] Conversión correcta: puntos_necesarios = ceil(monto / valor_punto_canje)
- [ ] Canje parcial funciona (no tiene que usar todos los puntos)
- [ ] Mínimo de canje se respeta (botón oculto si no alcanza)
- [ ] Artículo con puntos_canje se puede canjear en el POS
- [ ] Artículo canjeado se descuenta del stock normalmente
- [ ] Coexistencia: canje artículo + canje descuento en misma venta

### Anulación
- [ ] Al anular venta, se crean contraasientos (revierte acumulación y devuelve canjes)
- [ ] Si los puntos quedarían negativos, la anulación se bloquea con mensaje

### Cupones
- [ ] Cupón promocional creado correctamente con código autogenerado
- [ ] Cupón desde puntos descuenta puntos al crear
- [ ] Cupón tipo 'puntos' solo usable por el cliente dueño
- [ ] Cupón tipo 'promocional' usable por cualquiera
- [ ] Control de uso: se bloquea al alcanzar uso_maximo
- [ ] Vencimiento: cupón expirado no se puede usar
- [ ] Cupón aplica a total (monto fijo y porcentaje)
- [ ] Cupón aplica a artículos específicos (bonifica o % descuento)
- [ ] Registro completo en cupon_usos
- [ ] Al anular venta con cupón, se revierte el uso (uso_actual--)

### POS
- [ ] Saldo de puntos visible al seleccionar cliente
- [ ] Botón "Canjear puntos" aparece solo cuando procede
- [ ] Campo cupón funciona: ingreso, validación, aplicación
- [ ] Puntos y cupón pueden coexistir en misma venta
- [ ] Ticket muestra puntos ganados, usados y saldo

### Descuento General
- [ ] Descuento % general aplica ajuste_manual a TODOS los items del carrito
- [ ] Descuento $ fijo se resta del total después de todos los demás descuentos
- [ ] % y $ son mutuamente excluyentes (cambiar de uno a otro limpia el anterior)
- [ ] Items agregados después heredan el descuento % general activo
- [ ] Re-aplicar descuento % general pisa ajustes individuales previos
- [ ] Tope por rol se respeta (MAX de roles del usuario)
- [ ] Descuento $ fijo no puede superar el % tope del total
- [ ] Permiso `ventas.descuento_general` controla visibilidad de la opción
- [ ] Se graba `descuento_general_tipo/valor/monto` en tabla ventas
- [ ] Para %: el detalle por renglón queda en ventas_detalle.ajuste_manual_*
- [ ] Modal de Descuentos y Beneficios navegable con teclado (Tab, Enter, Escape)
- [ ] Coexistencia: descuento general + cupón + puntos en misma venta funciona

### Permisos
- [ ] Menú "Programa de Puntos" requiere permiso `menu.programa-puntos`
- [ ] Menú "Cupones" requiere permiso `menu.cupones`
- [ ] Ajuste manual requiere permiso funcional `puntos.ajuste_manual`
- [ ] Super Admin tiene todos los permisos por defecto

---

## Plan de Implementación

### Fase 1: Base de Datos + Models [COMPLETO]
1. Migración: agregar `multiplicador_puntos` a `formas_pago`
2. Migración: agregar `puntos_canje` a `articulos` y `articulos_sucursales`
3. Migración: agregar campos de puntos a `clientes`
4. Migración: agregar campos de puntos a `ventas`, `ventas_detalle`, `venta_pagos`
5. Migración: crear `configuracion_puntos` y `configuracion_puntos_sucursales`
6. Migración: crear `movimientos_puntos`
7. Migración: crear `cupones`, `cupon_articulos`, `cupon_usos`
8. Crear models: ConfiguracionPuntos, ConfiguracionPuntosSucursal, MovimientoPunto, Cupon, CuponArticulo, CuponUso
9. Modificar models existentes: FormaPago, Articulo, Cliente, Venta, VentaDetalle, VentaPago (agregar campos a fillable/casts/relaciones)
10. Regenerar `tenant_tables.sql`

### Fase 2: Services [COMPLETO]
1. Crear `PuntosService` con toda la lógica de acumulación, canje, saldos, cache
2. Crear `CuponService` con creación, validación, aplicación, generación de código
3. Integrar en `VentaService`: hook de acumulación post-venta
4. Integrar en `VentaService`: procesamiento de artículos canjeados con puntos
5. Integrar en `VentaService`: procesamiento de cupones
6. Integrar en `VentaService`: validación y reversión en cancelación

### Fase 3: Menú + Permisos + Provisioning [COMPLETO]
1. Migración: agregar menu_items "Programa de Puntos" y "Cupones" bajo Ventas
2. Agregar permiso funcional `puntos.ajuste_manual`
3. Actualizar `ProvisionComercioCommand::seedRolesYPermisos()` con nuevos permisos
4. Agregar rutas en `routes/web.php`

### Fase 4: UI — Configuración de Puntos [PENDIENTE]
1. Crear componente `ProgramaPuntos` (Livewire + vista)
2. Tab Configuración: formulario de ratios + toggles sucursales
3. Tab Consulta: buscador + historial paginado
4. Tab Ajustes: formulario con validación de permiso

### Fase 5: UI — Gestión de Cupones [PENDIENTE]
1. Crear componente `GestionCupones` (Livewire + vista)
2. Tab Listado: tabla con filtros y acciones
3. Tab Crear: formulario con lógica de puntos/promocional
4. Tab Historial: tabla de usos

### Fase 6: Integración POS (NuevaVenta) [PENDIENTE]
1. Migración: `descuento_maximo_porcentaje` en `roles`, `descuento_general_*` en `ventas`, permiso `ventas.descuento_general`
2. Modal de Descuentos y Beneficios con navegación por teclado:
   a. Sección Descuento General: radio %/$, input valor, validación tope por rol, aplicar/quitar
   b. Sección Cupón: input código, validar, mostrar detalle, aplicar/quitar
   c. Sección Canjear Puntos: saldo, input monto $, puntos a consumir, aplicar/quitar
3. Lógica descuento general %: `aplicarDescuentoGeneral()` aplica `ajuste_manual` a todos los items
4. Lógica descuento general $: resta monto fijo del total en `calcularVenta()`
5. Herencia: items nuevos heredan descuento % activo en `agregarArticulo()`
6. Botón "Pts" por renglón para canjear artículo con puntos
7. Mostrar saldo de puntos al seleccionar cliente
8. Indicador en artículos canjeables con puntos en catálogo
9. "Pago con Puntos" en desglose de pagos
10. Ajustar `procesarVentaConDesglose()` para grabar descuento_general_*, cupón, puntos
11. Mostrar puntos ganados/usados en el resumen pre-confirmación

### Fase 7: Integración Cliente + Ticket [PENDIENTE]
1. Sección "Puntos y Cupones" en GestionarClientes
2. Toggle programa_puntos_activo por cliente
3. Mostrar puntos ganados/usados/saldo en ticket de venta
4. Traducciones (3 idiomas)
5. Actualizar documentación (`manual-usuario.md`, `ai-knowledge-base.md`)

---

## Notas y Decisiones

- 2026-04-01: Puntos se calculan sobre `total_final` (con IVA, después de descuentos)
- 2026-04-01: Cobranza de CC no genera puntos (se suman en la venta según multiplicador)
- 2026-04-01: Sin expiración en fase 1 (evaluar FIFO más adelante)
- 2026-04-01: Cupón tipo 'puntos' atado al cliente creador; promocional para cualquiera
- 2026-04-01: Puntos se descuentan al CREAR cupón, no al usarlo
- 2026-04-01: Artículo por puntos es todo-o-nada (no parcial)
- 2026-04-01: Anulación bloqueada si puntos quedarían negativos
- 2026-04-01: Futuro: portal de clientes con cuenta digital multi-comercio, expiración FIFO, tiers, multiplicadores por categoría/producto/día, múltiples canales
- 2026-04-01: Descuento general: % usa mecanismo existente de ajuste_manual masivo (no es un nuevo cálculo), $ fijo se resta del total al final
- 2026-04-01: Descuento general % y $ son mutuamente excluyentes; pueden coexistir con cupones y puntos
- 2026-04-01: Tope de descuento en tabla `roles` (no por usuario) — se toma MAX de roles del usuario
- 2026-04-01: Modal unificado "Descuentos y Beneficios" para descuento general + cupón + canje puntos; canje artículo por puntos es botón en renglón
