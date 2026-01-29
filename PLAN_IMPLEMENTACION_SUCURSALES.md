# Plan de Implementación: Sistema Multi-Sucursal

**Fecha de Creación:** 2025-11-06
**Versión:** 1.0.0
**Estado:** Pendiente de Implementación

---

## 📋 Índice

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Decisiones Arquitectónicas](#decisiones-arquitectónicas)
3. [Requisitos Funcionales](#requisitos-funcionales)
4. [Estructura de Base de Datos](#estructura-de-base-de-datos)
5. [Plan de Implementación por Fases](#plan-de-implementación-por-fases)
6. [Casos de Uso a Implementar](#casos-de-uso-a-implementar)
7. [Próximos Pasos](#próximos-pasos)
8. [Notas Importantes](#notas-importantes)

---

## Resumen Ejecutivo

### Objetivo
Expandir el sistema actual de multi-tenant (multi-comercio) para soportar **múltiples sucursales por comercio**, permitiendo:
- Gestión independiente de cada sucursal
- Reportes consolidados a nivel comercio
- Transferencias de stock, efectivo y materia prima entre sucursales
- Control de acceso granular por usuario y sucursal

### Enfoque Seleccionado
**Sucursales como campo dentro del comercio** (NO comercios separados)

**Razón:** Facilita reportes consolidados, transferencias y mantiene integridad referencial en una sola base de datos.

### Alcance
- ✅ Estructura base de sucursales (este plan)
- ✅ Tablas principales con relaciones
- ✅ Sistema de permisos por sucursal
- ✅ UI para selector de sucursal
- ✅ Casos de uso ejemplo implementados
- ⏳ Funcionalidades avanzadas (listas de precios, descuentos, notas C/D, etc.) → Fase posterior

---

## Decisiones Arquitectónicas

### 1. Jerarquía del Sistema

```
┌─────────────────────────────────────┐
│         SISTEMA BCN PYMES            │
└──────────────┬──────────────────────┘
               │
    ┌──────────▼──────────┐
    │      COMERCIO       │  ← Tu cliente (con prefijo 000001_)
    │   (Nivel Tenant)    │
    └──────────┬──────────┘
               │
    ┌──────────▼──────────┐
    │      SUCURSAL       │  ← 1 a N por comercio
    │  (sucursal_id: 1-N) │
    └──────────┬──────────┘
               │
    ┌──────────▼──────────┐
    │        CAJA         │  ← 1 a N por sucursal
    │   (caja_id: 1-N)    │
    └─────────────────────┘
```

**Decisión:** NO incluir nivel "Empresa/Grupo" superior al comercio (puede agregarse en futuro si es necesario).

### 2. Ubicación de Datos

**Base de Datos:** PYMES (con prefijo dinámico por comercio)

**Ejemplos:**
```
Comercio ID 1 → Prefijo: 000001_
  Tablas: 000001_sucursales
          000001_articulos
          000001_ventas
          000001_stock
          etc.

Comercio ID 2 → Prefijo: 000002_
  Tablas: 000002_sucursales
          000002_articulos
          etc.
```

### 3. Tipos de Tablas

**A) Tablas Maestras Compartidas (SIN sucursal_id)**
- Catálogo unificado entre sucursales
- Ejemplos: `articulos`, `clientes`, `proveedores`, `sucursales`

**B) Tablas con Disponibilidad Selectiva (Pivot)**
- Controlan qué registros están disponibles en qué sucursales
- Ejemplos: `articulos_sucursales`, `clientes_sucursales`

**C) Tablas Específicas por Sucursal (CON sucursal_id)**
- Datos propios de cada sucursal
- Ejemplos: `stock`, `precios`, `ventas`, `compras`, `movimientos_caja`

**D) Tablas de Transferencias**
- Movimientos entre sucursales
- Ejemplos: `transferencias_stock`, `transferencias_efectivo`, `transferencias_materia_prima`

---

## Requisitos Funcionales

### RF-001: Gestión de Usuarios Multi-Sucursal

**Descripción:** Un usuario puede tener acceso a múltiples sucursales con diferentes roles en cada una.

**Escenarios:**
- Usuario es "Gerente" en Sucursal A y "Vendedor" en Sucursal B
- Usuario es "Super Admin" con acceso a TODAS las sucursales
- Usuario es "Super Admin Regional" con acceso solo a sucursales [1, 3, 5]

**Implementación:**
```sql
000001_model_has_roles
  - role_id
  - model_type (User)
  - model_id (user_id)
  - sucursal_id (nullable: NULL = acceso a todas)
```

### RF-002: Selector de Sucursal

**Descripción:** Al hacer login, el usuario selecciona sobre qué sucursal trabajará.

**Flujo:**
```
1. Usuario hace login
2. Sistema valida credenciales
3. Sistema verifica límite de sesiones
4. Usuario selecciona comercio (si tiene múltiples)
5. Usuario selecciona sucursal (si tiene múltiples) ← NUEVO
   - Si tiene 1 sola: asignación automática
   - Si tiene 2+: mostrar selector
   - Guardar sucursal preferida por defecto
6. Redireccionar a dashboard
```

**Sesión:**
```php
session('comercio_activo_id');   // Ya existe
session('sucursal_activa_id');   // NUEVO
```

### RF-003: Cambio de Sucursal sin Re-autenticar

**Descripción:** Dropdown en el header permite cambiar de sucursal sin logout.

**UI:**
```
Header:
[Logo] [Comercio: Don Juan] [Sucursal: Norte ▼] [Usuario: Admin ▼]
                            │
                            └─ Centro
                               Norte (actual)
                               Sur
```

**Comportamiento:**
- Click en otra sucursal → Actualiza `session('sucursal_activa_id')`
- Redirecciona a dashboard de esa sucursal
- NO requiere re-autenticación
- Limpia caché de menú/permisos

### RF-004: Sucursal Principal

**Descripción:** Cada comercio tiene una sucursal marcada como principal.

**Uso:**
- Sucursal por defecto para Super Admins al login
- Referencia en reportes ("Casa Central")
- Puede tener privilegios especiales (configurable)

**Campo:**
```sql
000001_sucursales.es_principal (boolean)
```

### RF-005: Artículos Compartidos con Disponibilidad Selectiva

**Descripción:** Un artículo es único en el comercio pero puede estar disponible solo en algunas sucursales.

**Ejemplo:**
```
Artículo: "Coca Cola 2L" (código: CC2L)
  ✓ Disponible en Sucursal Centro
  ✓ Disponible en Sucursal Norte
  ✗ NO disponible en Sucursal Sur
```

**Implementación:**
```sql
000001_articulos (catálogo maestro)
  - id: 1
  - codigo: "CC2L"
  - nombre: "Coca Cola 2L"

000001_articulos_sucursales (pivot)
  - articulo_id: 1, sucursal_id: 1, activo: true
  - articulo_id: 1, sucursal_id: 2, activo: true
  (NO hay registro para sucursal 3 = no disponible)
```

**Funcionalidad:**
- Al crear artículo, seleccionar en qué sucursales estará disponible
- Puede activarse/desactivarse en sucursales posteriormente
- Stock separado por sucursal (tabla `stock`)

### RF-006: Clientes Compartidos con Características por Sucursal

**Descripción:** Un cliente puede comprar en cualquier sucursal pero tener condiciones diferentes en cada una.

**Ejemplo:**
```
Cliente: "Juan Pérez"
  Sucursal Centro:
    - Lista de precios: Mayorista
    - Descuento: 10%
    - Límite de crédito: $50,000

  Sucursal Norte:
    - Lista de precios: Minorista
    - Descuento: 5%
    - Límite de crédito: $20,000
```

**Implementación:**
```sql
000001_clientes (datos compartidos)
  - id: 1
  - nombre: "Juan Pérez"
  - email: "juan@mail.com"
  - cuit: "20-12345678-9"

000001_clientes_sucursales (características por sucursal)
  - cliente_id: 1, sucursal_id: 1, lista_precio_id: 2, descuento: 10, limite_credito: 50000
  - cliente_id: 1, sucursal_id: 2, lista_precio_id: 1, descuento: 5, limite_credito: 20000
```

### RF-007: Proveedores - Sucursales como Proveedor/Cliente

**Descripción:** Una sucursal puede ser proveedor de otra y/o un proveedor puede ser cliente.

**Casos de Uso:**
1. **Sucursal Central provee a Sucursal Norte:**
   ```
   000001_proveedores
     - id: 1000
     - nombre: "Sucursal Central"
     - es_sucursal_interna: true
     - sucursal_id: 1

   000001_compras
     - id: 1
     - sucursal_id: 2 (Norte compra)
     - proveedor_id: 1000 (a Central)
   ```

2. **Proveedor que también es cliente:**
   ```
   000001_proveedores
     - id: 500
     - nombre: "Distribuidora XYZ"
     - es_tambien_cliente: true
     - cliente_id: 200

   Permite conciliación de deuda:
   - Saldo a pagar a proveedor: $10,000
   - Saldo a cobrar de cliente: $3,000
   - Neto: $7,000 a favor del proveedor
   ```

### RF-008: Transferencias de Stock

**Descripción:** Mover stock de una sucursal a otra con trazabilidad completa.

**Tipos de Transferencia:**

**A) Transferencia Simple:**
```
1. Super Admin solicita transferencia
2. Super Admin aprueba transferencia
3. Sistema descuenta stock origen
4. Sistema suma stock destino
5. Registro en transferencias_stock
```

**B) Transferencia como Venta/Compra Fiscal:**
```
1. Super Admin crea "venta" desde Sucursal A
2. Sistema genera venta con factura
3. Sistema descuenta stock de A
4. Sistema crea "compra" en Sucursal B
5. Sistema suma stock a B
6. Registro cruzado en transferencias_stock
```

**Estados:**
- `pendiente`: Solicitada, esperando aprobación
- `aprobada`: Aprobada, esperando envío
- `en_transito`: Enviada, esperando recepción
- `recibida`: Completada
- `rechazada`: Cancelada

**Tabla:**
```sql
000001_transferencias_stock
  - id
  - articulo_id
  - sucursal_origen_id
  - sucursal_destino_id
  - cantidad
  - estado
  - tipo (simple, venta_compra_fiscal)
  - venta_id (nullable)
  - compra_id (nullable)
  - solicitado_por_user_id
  - aprobado_por_user_id
  - recibido_por_user_id
  - fecha_solicitud
  - fecha_aprobacion
  - fecha_recepcion
  - observaciones
```

### RF-009: Transferencias de Efectivo entre Cajas

**Descripción:** Transferir dinero entre cajas de la misma o diferentes sucursales.

**Ejemplo:**
```
Caja Principal Sucursal Centro → Caja Mostrador Sucursal Norte
Monto: $5,000

Movimientos generados:
1. En Caja Principal Centro:
   - Tipo: transferencia_salida
   - Monto: -$5,000

2. En Caja Mostrador Norte:
   - Tipo: transferencia_entrada
   - Monto: +$5,000

Registro:
000001_transferencias_efectivo
  - caja_origen_id: 1
  - caja_destino_id: 5
  - monto: 5000
  - estado: recibida
```

### RF-010: Reportes Consolidados

**Descripción:** Super Admins pueden ver reportes que consolidan datos de múltiples sucursales.

**Ejemplos de Reportes:**

1. **Ventas por Sucursal (Período):**
```sql
SELECT
    s.nombre as sucursal,
    COUNT(v.id) as cantidad_ventas,
    SUM(v.total) as total_vendido
FROM 000001_ventas v
JOIN 000001_sucursales s ON v.sucursal_id = s.id
WHERE v.fecha BETWEEN '2025-01-01' AND '2025-01-31'
GROUP BY s.id
ORDER BY total_vendido DESC;
```

2. **Top 10 Artículos Más Vendidos (Consolidado):**
```sql
SELECT
    a.codigo,
    a.nombre,
    SUM(vd.cantidad) as total_vendido,
    SUM(vd.subtotal) as ingresos_totales
FROM 000001_ventas_detalle vd
JOIN 000001_ventas v ON vd.venta_id = v.id
JOIN 000001_articulos a ON vd.articulo_id = a.id
WHERE v.fecha BETWEEN '2025-01-01' AND '2025-01-31'
GROUP BY a.id
ORDER BY total_vendido DESC
LIMIT 10;
```

3. **Stock Consolidado por Artículo:**
```sql
SELECT
    s.nombre as sucursal,
    st.cantidad,
    st.minimo,
    st.maximo,
    CASE
        WHEN st.cantidad < st.minimo THEN 'Bajo'
        WHEN st.cantidad > st.maximo THEN 'Exceso'
        ELSE 'Normal'
    END as estado
FROM 000001_stock st
JOIN 000001_sucursales s ON st.sucursal_id = s.id
WHERE st.articulo_id = 1
ORDER BY s.nombre;
```

4. **Comparación de Sucursales:**
```sql
SELECT
    s.nombre,
    COUNT(DISTINCT v.id) as total_ventas,
    SUM(v.total) as facturacion,
    AVG(v.total) as ticket_promedio,
    COUNT(DISTINCT v.cliente_id) as clientes_unicos
FROM 000001_ventas v
JOIN 000001_sucursales s ON v.sucursal_id = s.id
WHERE v.fecha BETWEEN '2025-01-01' AND '2025-01-31'
GROUP BY s.id
ORDER BY facturacion DESC;
```

### RF-011: Dashboard por Nivel de Acceso

**A) Usuario Regular (1 Sucursal):**
```
Dashboard:
- Ventas de hoy (su sucursal)
- Top 5 productos (su sucursal)
- Alertas de stock (su sucursal)
- Pendientes (su sucursal)
```

**B) Gerente Multi-Sucursal:**
```
Dashboard:
- Ventas de hoy (sucursal activa)
- Top 5 productos (sucursal activa)
- [Botón] Comparar con otras sucursales
  → Abre modal con sus otras sucursales
```

**C) Super Admin:**
```
Dashboard:
- Vista por defecto: Sucursal Principal
- [Botón destacado] Vista Consolidada
  → Cambia a dashboard con datos de todas las sucursales
  → Gráficos comparativos
  → Ranking de sucursales
  → Alertas globales
```

---

## Estructura de Base de Datos

### Convención de Nombres

**Prefijo:** Cada comercio tiene prefijo de 6 dígitos: `000001_`, `000002_`, etc.

**Conexiones:**
- `config`: Base de datos centralizada (usuarios, comercios, sesiones)
- `pymes`: Base de datos con prefijo dinámico (datos del comercio)
- `pymes_tenant`: Alias de `pymes` con prefijo aplicado en runtime

### Tablas Nuevas

#### 1. Sucursales (Maestra)

```sql
CREATE TABLE {prefix}_sucursales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre de la sucursal',
    codigo VARCHAR(50) NOT NULL COMMENT 'Código corto (ej: CENTRO, NORTE)',
    direccion TEXT COMMENT 'Dirección física',
    telefono VARCHAR(50) COMMENT 'Teléfono de contacto',
    email VARCHAR(100) COMMENT 'Email de contacto',
    es_principal BOOLEAN DEFAULT FALSE COMMENT 'Si es la sucursal principal/central',
    datos_fiscales_id BIGINT UNSIGNED NULL COMMENT 'Si factura con datos propios',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si está operativa',
    configuracion JSON COMMENT 'Configuraciones específicas',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_codigo (codigo),
    INDEX idx_activo (activo),
    INDEX idx_es_principal (es_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. Artículos (Modificar existente si ya existe, o crear nueva)

```sql
CREATE TABLE {prefix}_articulos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único del artículo en el comercio',
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre del artículo',
    descripcion TEXT COMMENT 'Descripción detallada',
    categoria_id BIGINT UNSIGNED NULL COMMENT 'Categoría del artículo',
    marca_id BIGINT UNSIGNED NULL COMMENT 'Marca del artículo',
    unidad_medida VARCHAR(20) DEFAULT 'unidad' COMMENT 'Unidad de medida',
    codigo_barra VARCHAR(100) NULL COMMENT 'Código de barras',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si está activo en el catálogo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_codigo (codigo),
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo),
    INDEX idx_categoria (categoria_id),
    INDEX idx_marca (marca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. Artículos-Sucursales (Pivot - Disponibilidad)

```sql
CREATE TABLE {prefix}_articulos_sucursales (
    articulo_id BIGINT UNSIGNED NOT NULL,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si está disponible en esta sucursal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (articulo_id, sucursal_id),
    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE CASCADE,
    INDEX idx_sucursal_activo (sucursal_id, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. Stock (Por Sucursal)

```sql
CREATE TABLE {prefix}_stock (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    articulo_id BIGINT UNSIGNED NOT NULL,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(10,2) DEFAULT 0 COMMENT 'Cantidad disponible',
    minimo DECIMAL(10,2) DEFAULT 0 COMMENT 'Stock mínimo',
    maximo DECIMAL(10,2) DEFAULT 0 COMMENT 'Stock máximo',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_articulo_sucursal (articulo_id, sucursal_id),
    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE CASCADE,
    INDEX idx_sucursal (sucursal_id),
    INDEX idx_cantidad (cantidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. Precios (Por Sucursal y Tipo)

```sql
CREATE TABLE {prefix}_precios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    articulo_id BIGINT UNSIGNED NOT NULL,
    sucursal_id BIGINT UNSIGNED NULL COMMENT 'NULL = precio por defecto para todas',
    tipo_precio_id BIGINT UNSIGNED NOT NULL COMMENT 'Local, Web, Mayorista, etc.',
    precio DECIMAL(10,2) NOT NULL COMMENT 'Precio del artículo',
    vigencia_desde DATE NULL COMMENT 'Fecha desde la cual aplica',
    vigencia_hasta DATE NULL COMMENT 'Fecha hasta la cual aplica',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si está activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE CASCADE,
    INDEX idx_articulo_sucursal_tipo (articulo_id, sucursal_id, tipo_precio_id),
    INDEX idx_vigencia (vigencia_desde, vigencia_hasta),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. Clientes (Compartidos)

```sql
CREATE TABLE {prefix}_clientes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre o razón social',
    email VARCHAR(100) NULL COMMENT 'Email de contacto',
    telefono VARCHAR(50) NULL COMMENT 'Teléfono de contacto',
    direccion TEXT NULL COMMENT 'Dirección',
    cuit VARCHAR(20) NULL COMMENT 'CUIT/CUIL',
    tipo_cliente ENUM('consumidor_final', 'monotributista', 'responsable_inscripto') DEFAULT 'consumidor_final',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_nombre (nombre),
    INDEX idx_email (email),
    INDEX idx_cuit (cuit),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. Clientes-Sucursales (Características por Sucursal)

```sql
CREATE TABLE {prefix}_clientes_sucursales (
    cliente_id BIGINT UNSIGNED NOT NULL,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    lista_precio_id BIGINT UNSIGNED NULL COMMENT 'Lista de precios asignada',
    descuento_porcentaje DECIMAL(5,2) DEFAULT 0 COMMENT 'Descuento % por defecto',
    limite_credito DECIMAL(10,2) DEFAULT 0 COMMENT 'Límite de crédito',
    saldo_actual DECIMAL(10,2) DEFAULT 0 COMMENT 'Saldo de cuenta corriente',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si está activo en esta sucursal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (cliente_id, sucursal_id),
    FOREIGN KEY (cliente_id) REFERENCES {prefix}_clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE CASCADE,
    INDEX idx_sucursal (sucursal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. Proveedores

```sql
CREATE TABLE {prefix}_proveedores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    cuit VARCHAR(20) NULL,
    direccion TEXT NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(100) NULL,
    es_sucursal_interna BOOLEAN DEFAULT FALSE COMMENT 'Si es otra sucursal del comercio',
    sucursal_id BIGINT UNSIGNED NULL COMMENT 'Si es sucursal interna, referencia',
    es_tambien_cliente BOOLEAN DEFAULT FALSE COMMENT 'Si también es cliente',
    cliente_id BIGINT UNSIGNED NULL COMMENT 'Si es cliente, referencia para conciliación',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES {prefix}_clientes(id) ON DELETE SET NULL,
    INDEX idx_nombre (nombre),
    INDEX idx_cuit (cuit),
    INDEX idx_es_sucursal_interna (es_sucursal_interna),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. Cajas (Por Sucursal)

```sql
CREATE TABLE {prefix}_cajas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre de la caja',
    tipo ENUM('efectivo', 'banco', 'tarjeta', 'cheque', 'otro') DEFAULT 'efectivo',
    saldo_inicial DECIMAL(10,2) DEFAULT 0 COMMENT 'Saldo al iniciar',
    saldo_actual DECIMAL(10,2) DEFAULT 0 COMMENT 'Saldo actual',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE CASCADE,
    INDEX idx_sucursal (sucursal_id),
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. Movimientos de Caja

```sql
CREATE TABLE {prefix}_movimientos_caja (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caja_id BIGINT UNSIGNED NOT NULL,
    tipo_movimiento ENUM('venta', 'cobranza', 'gasto', 'transferencia_entrada', 'transferencia_salida', 'ajuste') NOT NULL,
    referencia_tipo VARCHAR(50) NULL COMMENT 'Tipo de documento (venta, compra, transferencia, etc.)',
    referencia_id BIGINT UNSIGNED NULL COMMENT 'ID del documento relacionado',
    monto DECIMAL(10,2) NOT NULL COMMENT 'Monto del movimiento (+ o -)',
    saldo_anterior DECIMAL(10,2) NOT NULL COMMENT 'Saldo antes del movimiento',
    saldo_nuevo DECIMAL(10,2) NOT NULL COMMENT 'Saldo después del movimiento',
    descripcion TEXT NULL,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que realizó el movimiento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (caja_id) REFERENCES {prefix}_cajas(id) ON DELETE CASCADE,
    INDEX idx_caja_fecha (caja_id, created_at),
    INDEX idx_tipo_movimiento (tipo_movimiento),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. Ventas (Por Sucursal)

```sql
CREATE TABLE {prefix}_ventas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caja_id BIGINT UNSIGNED NULL COMMENT 'Caja donde se registró la venta',
    numero_comprobante VARCHAR(50) NOT NULL COMMENT 'Número de factura/ticket',
    tipo_comprobante ENUM('factura_a', 'factura_b', 'factura_c', 'ticket', 'nota_credito', 'nota_debito') NOT NULL,
    fecha DATE NOT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0,
    descuento DECIMAL(10,2) DEFAULT 0,
    impuestos DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'pagada', 'parcial', 'anulada') DEFAULT 'pendiente',
    observaciones TEXT NULL,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que realizó la venta',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE RESTRICT,
    FOREIGN KEY (cliente_id) REFERENCES {prefix}_clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (caja_id) REFERENCES {prefix}_cajas(id) ON DELETE SET NULL,
    UNIQUE KEY unique_numero_comprobante_sucursal (sucursal_id, numero_comprobante),
    INDEX idx_sucursal_fecha (sucursal_id, fecha),
    INDEX idx_cliente (cliente_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 12. Ventas Detalle

```sql
CREATE TABLE {prefix}_ventas_detalle (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id BIGINT UNSIGNED NOT NULL,
    articulo_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (venta_id) REFERENCES {prefix}_ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE RESTRICT,
    INDEX idx_venta (venta_id),
    INDEX idx_articulo (articulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 13. Compras (Por Sucursal)

```sql
CREATE TABLE {prefix}_compras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    proveedor_id BIGINT UNSIGNED NOT NULL,
    numero_comprobante VARCHAR(50) NOT NULL,
    tipo_comprobante ENUM('factura_a', 'factura_b', 'factura_c', 'remito', 'nota_credito', 'nota_debito') NOT NULL,
    fecha DATE NOT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0,
    impuestos DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'pagada', 'parcial', 'anulada') DEFAULT 'pendiente',
    observaciones TEXT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (sucursal_id) REFERENCES {prefix}_sucursales(id) ON DELETE RESTRICT,
    FOREIGN KEY (proveedor_id) REFERENCES {prefix}_proveedores(id) ON DELETE RESTRICT,
    INDEX idx_sucursal_fecha (sucursal_id, fecha),
    INDEX idx_proveedor (proveedor_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 14. Compras Detalle

```sql
CREATE TABLE {prefix}_compras_detalle (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compra_id BIGINT UNSIGNED NOT NULL,
    articulo_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (compra_id) REFERENCES {prefix}_compras(id) ON DELETE CASCADE,
    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE RESTRICT,
    INDEX idx_compra (compra_id),
    INDEX idx_articulo (articulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 15. Transferencias de Stock

```sql
CREATE TABLE {prefix}_transferencias_stock (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    articulo_id BIGINT UNSIGNED NOT NULL,
    sucursal_origen_id BIGINT UNSIGNED NOT NULL,
    sucursal_destino_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'en_transito', 'recibida', 'rechazada') DEFAULT 'pendiente',
    tipo ENUM('simple', 'venta_compra_fiscal') DEFAULT 'simple',
    venta_id BIGINT UNSIGNED NULL COMMENT 'Si es venta/compra fiscal',
    compra_id BIGINT UNSIGNED NULL COMMENT 'Si es venta/compra fiscal',
    solicitado_por_user_id BIGINT UNSIGNED NOT NULL,
    aprobado_por_user_id BIGINT UNSIGNED NULL,
    recibido_por_user_id BIGINT UNSIGNED NULL,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion TIMESTAMP NULL,
    fecha_recepcion TIMESTAMP NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (articulo_id) REFERENCES {prefix}_articulos(id) ON DELETE RESTRICT,
    FOREIGN KEY (sucursal_origen_id) REFERENCES {prefix}_sucursales(id) ON DELETE RESTRICT,
    FOREIGN KEY (sucursal_destino_id) REFERENCES {prefix}_sucursales(id) ON DELETE RESTRICT,
    FOREIGN KEY (venta_id) REFERENCES {prefix}_ventas(id) ON DELETE SET NULL,
    FOREIGN KEY (compra_id) REFERENCES {prefix}_compras(id) ON DELETE SET NULL,
    INDEX idx_origen_destino (sucursal_origen_id, sucursal_destino_id),
    INDEX idx_estado (estado),
    INDEX idx_articulo (articulo_id),
    INDEX idx_fecha_solicitud (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 16. Transferencias de Efectivo

```sql
CREATE TABLE {prefix}_transferencias_efectivo (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caja_origen_id BIGINT UNSIGNED NOT NULL,
    caja_destino_id BIGINT UNSIGNED NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'recibida', 'rechazada') DEFAULT 'pendiente',
    autorizado_por_user_id BIGINT UNSIGNED NOT NULL,
    recibido_por_user_id BIGINT UNSIGNED NULL,
    fecha_autorizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_recepcion TIMESTAMP NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (caja_origen_id) REFERENCES {prefix}_cajas(id) ON DELETE RESTRICT,
    FOREIGN KEY (caja_destino_id) REFERENCES {prefix}_cajas(id) ON DELETE RESTRICT,
    INDEX idx_origen_destino (caja_origen_id, caja_destino_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_autorizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modificaciones a Tablas Existentes

#### model_has_roles (Agregar sucursal_id)

```sql
ALTER TABLE {prefix}_model_has_roles
ADD COLUMN sucursal_id BIGINT UNSIGNED NULL COMMENT 'NULL = acceso a todas las sucursales' AFTER model_type,
ADD INDEX idx_sucursal (sucursal_id);

-- Modificar primary key para incluir sucursal_id
ALTER TABLE {prefix}_model_has_roles
DROP PRIMARY KEY,
ADD PRIMARY KEY (role_id, model_id, model_type, sucursal_id);
```

**Nota:** Esta modificación debe hacerse con cuidado si ya existen datos.

---

## Plan de Implementación por Fases

### FASE 1: Migraciones de Base de Datos ✅

**Objetivo:** Crear toda la estructura de tablas sin tocar código existente.

**Tareas:**
1. ✅ Crear migración para tabla `sucursales`
2. ✅ Crear migración para tabla `articulos` (si no existe)
3. ✅ Crear migración para pivot `articulos_sucursales`
4. ✅ Crear migración para tabla `stock`
5. ✅ Crear migración para tabla `precios`
6. ✅ Crear migración para tabla `clientes`
7. ✅ Crear migración para pivot `clientes_sucursales`
8. ✅ Crear migración para tabla `proveedores`
9. ✅ Crear migración para tabla `cajas`
10. ✅ Crear migración para tabla `movimientos_caja`
11. ✅ Crear migración para tabla `ventas`
12. ✅ Crear migración para tabla `ventas_detalle`
13. ✅ Crear migración para tabla `compras`
14. ✅ Crear migración para tabla `compras_detalle`
15. ✅ Crear migración para tabla `transferencias_stock`
16. ✅ Crear migración para tabla `transferencias_efectivo`
17. ✅ Crear migración para modificar `model_has_roles` (agregar `sucursal_id`)

**Archivos a crear:**
```
database/migrations/
├── 2025_11_06_xxxxxx_create_sucursales_table.php
├── 2025_11_06_xxxxxx_create_articulos_table.php
├── 2025_11_06_xxxxxx_create_articulos_sucursales_table.php
├── 2025_11_06_xxxxxx_create_stock_table.php
├── 2025_11_06_xxxxxx_create_precios_table.php
├── 2025_11_06_xxxxxx_create_clientes_table.php
├── 2025_11_06_xxxxxx_create_clientes_sucursales_table.php
├── 2025_11_06_xxxxxx_create_proveedores_table.php
├── 2025_11_06_xxxxxx_create_cajas_table.php
├── 2025_11_06_xxxxxx_create_movimientos_caja_table.php
├── 2025_11_06_xxxxxx_create_ventas_table.php
├── 2025_11_06_xxxxxx_create_ventas_detalle_table.php
├── 2025_11_06_xxxxxx_create_compras_table.php
├── 2025_11_06_xxxxxx_create_compras_detalle_table.php
├── 2025_11_06_xxxxxx_create_transferencias_stock_table.php
├── 2025_11_06_xxxxxx_create_transferencias_efectivo_table.php
└── 2025_11_06_xxxxxx_add_sucursal_id_to_model_has_roles_table.php
```

**Pruebas Fase 1:**
```bash
# Ejecutar para comercio de prueba
php artisan comercio:init 1
# Verificar que las tablas se crean correctamente con el prefijo
```

**Entregables:**
- [x] Todas las migraciones creadas
- [x] Migraciones probadas y funcionando
- [x] Documentación de estructura en ARQUITECTURA.md actualizada

---

### FASE 2: Modelos Eloquent ⏳

**Objetivo:** Crear modelos para todas las nuevas tablas con relaciones.

**Tareas:**
1. ⏳ Crear modelo `Sucursal` con relaciones
2. ⏳ Crear modelo `Articulo` (si no existe) con relaciones
3. ⏳ Crear modelo `Stock` con relaciones
4. ⏳ Crear modelo `Precio` con relaciones
5. ⏳ Crear modelo `Cliente` con relaciones
6. ⏳ Crear modelo `Proveedor` con relaciones
7. ⏳ Crear modelo `Caja` con relaciones
8. ⏳ Crear modelo `MovimientoCaja` con relaciones
9. ⏳ Crear modelo `Venta` con relaciones
10. ⏳ Crear modelo `VentaDetalle` con relaciones
11. ⏳ Crear modelo `Compra` con relaciones
12. ⏳ Crear modelo `CompraDetalle` con relaciones
13. ⏳ Crear modelo `TransferenciaStock` con relaciones
14. ⏳ Crear modelo `TransferenciaEfectivo` con relaciones
15. ⏳ Actualizar modelo `User` con métodos de sucursales
16. ⏳ Actualizar modelo `Comercio` con relación a sucursales
17. ⏳ Actualizar modelo `Role` si es necesario

**Archivos a crear:**
```
app/Models/
├── Sucursal.php
├── Articulo.php
├── Stock.php
├── Precio.php
├── Cliente.php
├── Proveedor.php
├── Caja.php
├── MovimientoCaja.php
├── Venta.php
├── VentaDetalle.php
├── Compra.php
├── CompraDetalle.php
├── TransferenciaStock.php
└── TransferenciaEfectivo.php
```

**Ejemplo de Modelo:**
```php
// app/Models/Sucursal.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Sucursal
 *
 * Representa una sucursal de un comercio.
 *
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string $direccion
 * @property string $telefono
 * @property string $email
 * @property bool $es_principal
 * @property bool $activo
 */
class Sucursal extends Model
{
    protected $connection = 'pymes_tenant';
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'email',
        'es_principal',
        'activo',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activo' => 'boolean',
        'configuracion' => 'array',
    ];

    // Relaciones
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }
}
```

**Entregables:**
- [ ] Todos los modelos creados con PHPDoc completo
- [ ] Relaciones definidas
- [ ] Scopes útiles agregados
- [ ] Pruebas básicas de relaciones

---

### FASE 3: Servicios y Lógica de Negocio ⏳

**Objetivo:** Expandir TenantService y crear lógica para gestión de sucursales.

**Tareas:**
1. ⏳ Expandir `TenantService` con métodos de sucursal
2. ⏳ Crear `SucursalService` para lógica específica
3. ⏳ Actualizar `SessionManagerService` si es necesario
4. ⏳ Crear observers para sincronización de stock
5. ⏳ Crear observers para movimientos de caja

**Archivos a crear/modificar:**
```
app/Services/
├── TenantService.php (modificar)
├── SucursalService.php (nuevo)
└── TransferenciaService.php (nuevo)

app/Observers/
├── StockObserver.php (nuevo)
└── MovimientoCajaObserver.php (nuevo)
```

**Ejemplo TenantService expandido:**
```php
class TenantService
{
    // ... métodos existentes ...

    /**
     * Establece la sucursal activa en la sesión
     */
    public function setSucursal(Sucursal $sucursal): void
    {
        Session::put('sucursal_activa_id', $sucursal->id);

        // Limpiar caché de permisos
        $this->limpiarCachePermisos();
    }

    /**
     * Obtiene la sucursal activa
     */
    public function getSucursal(): ?Sucursal
    {
        $sucursalId = Session::get('sucursal_activa_id');

        if (!$sucursalId) {
            return null;
        }

        return Sucursal::find($sucursalId);
    }

    /**
     * Obtiene las sucursales disponibles para un usuario
     */
    public function getSucursalesUsuario(User $user): Collection
    {
        // Obtener sucursales según roles del usuario
        // Si es Super Admin (sucursal_id NULL) → todas
        // Si no → solo las que tiene en model_has_roles
    }

    /**
     * Verifica si el usuario tiene acceso a una sucursal
     */
    public function hasAccessToSucursal(User $user, int $sucursalId): bool
    {
        // Verificar en model_has_roles
    }
}
```

**Entregables:**
- [ ] TenantService expandido
- [ ] SucursalService creado
- [ ] TransferenciaService creado
- [ ] Observers implementados

---

### FASE 4: Middleware y Rutas ⏳

**Objetivo:** Crear middleware para validación de sucursal y proteger rutas.

**Tareas:**
1. ⏳ Crear `SucursalMiddleware`
2. ⏳ Actualizar `TenantMiddleware` si es necesario
3. ⏳ Definir grupos de rutas por nivel de acceso
4. ⏳ Crear rutas para gestión de sucursales

**Archivos a crear:**
```
app/Http/Middleware/
└── SucursalMiddleware.php (nuevo)

routes/
└── web.php (modificar)
```

**Ejemplo SucursalMiddleware:**
```php
class SucursalMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantService = app(TenantService::class);

        // Verificar que haya sucursal activa
        if (!$tenantService->getSucursal()) {
            return redirect()->route('sucursal.selector');
        }

        // Verificar que el usuario tenga acceso
        $user = Auth::user();
        $sucursalId = session('sucursal_activa_id');

        if (!$tenantService->hasAccessToSucursal($user, $sucursalId)) {
            return redirect()->route('sucursal.selector')
                ->with('error', 'No tienes acceso a esta sucursal.');
        }

        return $next($request);
    }
}
```

**Entregables:**
- [ ] SucursalMiddleware creado
- [ ] Middleware registrado en bootstrap/app.php
- [ ] Rutas protegidas correctamente
- [ ] Documentación de rutas actualizada

---

### FASE 5: UI y Componentes Livewire ⏳

**Objetivo:** Crear interfaz para selección de sucursal y gestión.

**Tareas:**
1. ⏳ Crear componente `SucursalSelector` (similar a ComercioSelector)
2. ⏳ Crear dropdown de sucursales en header
3. ⏳ Actualizar componente `DynamicMenu` para filtrar por sucursal
4. ⏳ Crear componente `Configuracion\Sucursales` (CRUD)
5. ⏳ Actualizar componentes existentes para usar sucursal activa

**Archivos a crear:**
```
app/Livewire/
├── SucursalSelector.php (nuevo)
├── SucursalDropdown.php (nuevo)
└── Configuracion/
    └── Sucursales.php (nuevo)

resources/views/livewire/
├── sucursal-selector.blade.php
├── sucursal-dropdown.blade.php
└── configuracion/
    └── sucursales.blade.php
```

**Entregables:**
- [ ] Componente SucursalSelector funcional
- [ ] Dropdown en header funcional
- [ ] CRUD de sucursales funcional
- [ ] Componentes existentes actualizados

---

### FASE 6: Sistema de Permisos por Sucursal ⏳

**Objetivo:** Adaptar sistema de roles/permisos para trabajar por sucursal.

**Tareas:**
1. ⏳ Actualizar método `User::hasPermissionTo()` para considerar sucursal
2. ⏳ Actualizar método `User::roles()` para filtrar por sucursal
3. ⏳ Actualizar componente `RolesPermisos` para asignar por sucursal
4. ⏳ Crear seeder de roles por defecto con sucursales
5. ⏳ Actualizar lógica del menú dinámico

**Modificación en User.php:**
```php
public function hasPermissionTo($permission): bool
{
    // Obtener sucursal activa
    $sucursalId = session('sucursal_activa_id');

    // Obtener roles del usuario en esta sucursal
    $roles = DB::connection('pymes_tenant')
        ->table('model_has_roles')
        ->where('model_id', $this->id)
        ->where('model_type', static::class)
        ->where(function($q) use ($sucursalId) {
            $q->whereNull('sucursal_id') // Super Admin
              ->orWhere('sucursal_id', $sucursalId); // Rol en sucursal específica
        })
        ->pluck('role_id');

    // Verificar permiso en esos roles
    // ...
}
```

**Entregables:**
- [ ] Lógica de permisos actualizada
- [ ] Componente RolesPermisos adaptado
- [ ] Seeders actualizados
- [ ] Pruebas de permisos por sucursal

---

### FASE 7: Casos de Uso Completos (Ejemplos) ⏳

**Objetivo:** Implementar casos de uso end-to-end como ejemplo.

**Casos a Implementar:**

#### Caso 1: Consulta de Stock Multi-Sucursal
```
Dashboard → Inventario → Artículos → [Ver Stock por Sucursal]

Muestra:
- Stock en cada sucursal
- Total consolidado
- Alertas de stock bajo
- Botón "Transferir Stock" entre sucursales
```

#### Caso 2: Transferencia de Stock Simple
```
Super Admin → Transferencias → Nueva Transferencia

Formulario:
1. Seleccionar artículo
2. Sucursal origen (dropdown)
3. Sucursal destino (dropdown)
4. Cantidad
5. Observaciones
6. [Solicitar Transferencia]

Flujo:
- Crear registro en transferencias_stock (estado: pendiente)
- Notificación a Super Admin para aprobar
- Al aprobar: descuenta stock origen, suma stock destino
- Cambia estado a "recibida"
```

#### Caso 3: Venta en Sucursal Específica
```
Vendedor en Sucursal Norte → Ventas → Nueva Venta

Comportamiento:
- Solo ve artículos disponibles en Sucursal Norte
- Solo ve clientes (con configuración de Sucursal Norte)
- Usa precios de Sucursal Norte
- Descuenta stock de Sucursal Norte
- Registra venta con sucursal_id = Norte
- Genera movimiento en caja de Sucursal Norte
```

#### Caso 4: Reporte Consolidado
```
Super Admin → Reportes → Ventas Consolidadas

Filtros:
- Rango de fechas
- [x] Sucursal Centro
- [x] Sucursal Norte
- [ ] Sucursal Sur

Muestra:
- Tabla con ventas por sucursal
- Gráfico comparativo
- Top 10 productos (consolidado)
- Total general
```

**Entregables:**
- [ ] Caso 1 implementado y funcional
- [ ] Caso 2 implementado y funcional
- [ ] Caso 3 implementado y funcional
- [ ] Caso 4 implementado y funcional
- [ ] Documentación de casos de uso

---

### FASE 8: Testing y Ajustes ⏳

**Objetivo:** Probar todo el sistema y hacer ajustes finales.

**Tareas:**
1. ⏳ Crear tests unitarios para modelos
2. ⏳ Crear tests de feature para flujos completos
3. ⏳ Probar con múltiples usuarios y sucursales
4. ⏳ Optimizar queries N+1 si aparecen
5. ⏳ Documentar en ARQUITECTURA.md y GUIA_RAPIDA.md

**Escenarios de Prueba:**
```
1. Usuario con 1 sucursal
2. Usuario con múltiples sucursales
3. Super Admin con todas las sucursales
4. Super Admin regional (solo algunas)
5. Transferencia de stock entre sucursales
6. Transferencia de efectivo entre cajas
7. Reportes consolidados
8. Cambio de sucursal en dropdown
9. Permisos por sucursal
10. Venta/Compra en sucursal específica
```

**Entregables:**
- [ ] Tests creados
- [ ] Sistema probado end-to-end
- [ ] Optimizaciones aplicadas
- [ ] Documentación completa actualizada

---

## Casos de Uso a Implementar

### CU-001: Login con Selección de Sucursal

**Actor:** Usuario con acceso a múltiples sucursales

**Precondición:** Usuario tiene credenciales válidas

**Flujo Principal:**
1. Usuario ingresa email/username y contraseña
2. Sistema valida credenciales
3. Sistema verifica límite de sesiones concurrentes
4. Sistema detecta que usuario tiene acceso a múltiples comercios
5. Muestra selector de comercio
6. Usuario selecciona comercio
7. Sistema detecta que usuario tiene acceso a múltiples sucursales en ese comercio
8. Muestra selector de sucursal
9. Usuario selecciona sucursal
10. Sistema establece `comercio_activo_id` y `sucursal_activa_id` en sesión
11. Redirecciona a dashboard de esa sucursal

**Flujo Alternativo 1:** Usuario tiene una sola sucursal
- En paso 7: Sistema establece automáticamente la única sucursal
- Salta al paso 10

**Flujo Alternativo 2:** Usuario es Super Admin
- En paso 8: Sistema establece sucursal principal por defecto
- Usuario puede cambiar después con dropdown

---

### CU-002: Cambio de Sucursal desde Header

**Actor:** Usuario con acceso a múltiples sucursales

**Precondición:** Usuario está autenticado y en dashboard

**Flujo Principal:**
1. Usuario ve dropdown en header con sucursal actual
2. Usuario hace click en dropdown
3. Sistema muestra lista de sucursales disponibles para ese usuario
4. Usuario selecciona otra sucursal
5. Sistema actualiza `sucursal_activa_id` en sesión
6. Sistema limpia caché de permisos
7. Sistema redirecciona a dashboard
8. Dashboard muestra datos de la nueva sucursal

**Reglas de Negocio:**
- Solo ve sucursales a las que tiene acceso
- Super Admin ve todas las sucursales
- Cambio no requiere re-autenticación

---

### CU-003: Crear Artículo con Disponibilidad Selectiva

**Actor:** Super Admin o Gerente

**Precondición:** Usuario tiene permiso para crear artículos

**Flujo Principal:**
1. Usuario va a Inventario → Artículos → Nuevo
2. Sistema muestra formulario:
   - Código
   - Nombre
   - Descripción
   - Categoría
   - Marca
   - [Checkboxes] Disponible en sucursales: □ Centro □ Norte □ Sur
3. Usuario completa datos y marca sucursales
4. Usuario hace click en Guardar
5. Sistema crea registro en `articulos`
6. Sistema crea registros en `articulos_sucursales` para cada sucursal marcada
7. Sistema crea registros en `stock` (cantidad 0) para cada sucursal
8. Muestra mensaje de éxito

**Validaciones:**
- Código único en el comercio
- Al menos una sucursal debe estar marcada

---

### CU-004: Transferencia Simple de Stock

**Actor:** Super Admin

**Precondición:**
- Artículo existe en ambas sucursales
- Stock suficiente en sucursal origen

**Flujo Principal:**
1. Super Admin va a Transferencias → Stock → Nueva
2. Sistema muestra formulario:
   - Artículo (dropdown con búsqueda)
   - Sucursal Origen (dropdown)
   - Sucursal Destino (dropdown)
   - Cantidad
   - Tipo: (●) Simple ( ) Venta/Compra Fiscal
   - Observaciones
3. Usuario completa y hace click en Solicitar
4. Sistema valida stock disponible
5. Sistema crea registro en `transferencias_stock` (estado: pendiente)
6. Sistema muestra confirmación
7. Super Admin hace click en Aprobar
8. Sistema descuenta stock de origen
9. Sistema suma stock a destino
10. Sistema cambia estado a "recibida"
11. Muestra mensaje de éxito

**Validaciones:**
- Stock suficiente en origen
- Cantidad > 0
- Sucursales diferentes
- Artículo disponible en ambas sucursales

---

### CU-005: Venta en Sucursal Específica

**Actor:** Vendedor

**Precondición:**
- Vendedor está en sucursal activa
- Tiene permiso para crear ventas

**Flujo Principal:**
1. Vendedor va a Ventas → Nueva Venta
2. Sistema muestra formulario con:
   - Cliente (solo clientes con config en esta sucursal)
   - Lista de artículos (solo disponibles en esta sucursal)
   - Caja (solo cajas de esta sucursal)
3. Vendedor selecciona cliente
4. Sistema carga configuración del cliente en esta sucursal (lista precios, descuento)
5. Vendedor agrega artículos
6. Sistema usa precios de esta sucursal
7. Sistema aplica descuento del cliente
8. Sistema calcula total
9. Vendedor hace click en Finalizar Venta
10. Sistema valida stock disponible en esta sucursal
11. Sistema crea registro en `ventas` (sucursal_id)
12. Sistema crea registros en `ventas_detalle`
13. Sistema descuenta stock de esta sucursal
14. Sistema genera movimiento en caja seleccionada
15. Sistema actualiza saldo de caja
16. Muestra comprobante

**Validaciones:**
- Stock suficiente en sucursal activa
- Cliente tiene configuración en sucursal activa
- Caja está abierta

---

### CU-006: Reporte de Ventas Consolidado

**Actor:** Super Admin

**Precondición:** Usuario tiene permiso para reportes consolidados

**Flujo Principal:**
1. Super Admin va a Reportes → Ventas Consolidadas
2. Sistema muestra filtros:
   - Fecha Desde / Hasta
   - Sucursales: □ Centro □ Norte □ Sur □ Todas
3. Super Admin selecciona período y sucursales
4. Hace click en Generar
5. Sistema ejecuta query consolidada
6. Sistema muestra:
   - Tabla con ventas por sucursal
   - Total general
   - Gráfico de barras comparativo
   - Top 10 productos vendidos (consolidado)
   - Promedio de ticket por sucursal
7. Super Admin puede exportar a PDF o Excel

**Datos Mostrados:**
```
| Sucursal | Cant. Ventas | Total $ | Ticket Promedio |
|----------|--------------|---------|-----------------|
| Centro   | 150          | $75,000 | $500            |
| Norte    | 120          | $60,000 | $500            |
| Sur      | 100          | $45,000 | $450            |
|----------|--------------|---------|-----------------|
| TOTAL    | 370          |$180,000 | $486            |
```

---

### CU-007: Gestión de Sucursales (CRUD)

**Actor:** Super Admin

**Precondición:** Acceso al módulo de configuración

**Flujo Principal - Crear:**
1. Super Admin va a Configuración → Sucursales → Nueva
2. Sistema muestra formulario:
   - Nombre
   - Código
   - Dirección
   - Teléfono
   - Email
   - [Checkbox] Es Principal
   - [Checkbox] Activa
3. Super Admin completa y guarda
4. Sistema crea registro en `sucursales`
5. Sistema crea cajas por defecto (configurable)
6. Muestra mensaje de éxito

**Flujo Principal - Editar:**
1. Super Admin hace click en Editar
2. Sistema muestra formulario con datos actuales
3. Super Admin modifica y guarda
4. Sistema actualiza registro
5. Muestra mensaje de éxito

**Flujo Principal - Desactivar:**
1. Super Admin hace click en Desactivar
2. Sistema muestra confirmación
3. Super Admin confirma
4. Sistema marca `activo = false`
5. Usuarios pierden acceso a esa sucursal
6. Muestra mensaje de éxito

**Validaciones:**
- Código único en el comercio
- Solo puede haber una sucursal principal
- No se puede desactivar si tiene movimientos pendientes

---

## Próximos Pasos

### Paso 1: Revisar y Aprobar este Plan
- [ ] Usuario revisa el plan completo
- [ ] Usuario aprueba o solicita ajustes
- [ ] Se definen prioridades si es necesario

### Paso 2: Iniciar FASE 1
- [ ] Crear branch `feature/multi-sucursal`
- [ ] Comenzar con migraciones de base de datos
- [ ] Probar cada migración individualmente
- [ ] Commit incremental por cada tabla

### Paso 3: Revisión de FASE 1
- [ ] Usuario prueba las migraciones
- [ ] Se verifica integridad de datos
- [ ] Se ajusta si es necesario
- [ ] Se aprueba para continuar

### Paso 4: Continuar con FASE 2
- (Repetir proceso de implementación → revisión → ajuste)

---

## Notas Importantes

### 🔴 Cosas a NO Hacer

1. **NO romper funcionalidad existente**
   - El sistema actual debe seguir funcionando
   - Las nuevas tablas son adicionales
   - Los componentes existentes se adaptan, no se reemplazan

2. **NO implementar funcionalidades avanzadas aún**
   - Listas de precios múltiples → Fase posterior
   - Descuentos complejos → Fase posterior
   - Notas de crédito/débito → Fase posterior
   - Encargos/Pedidos → Fase posterior
   - Todo esto se agregará luego sobre la estructura base

3. **NO cerrar la arquitectura**
   - Dejar campos y tablas preparados para expandir
   - Usar JSON para configuraciones que puedan cambiar
   - No hardcodear valores que puedan ser dinámicos

### 🟢 Buenas Prácticas

1. **Commits Incrementales**
   - Un commit por tabla/migración
   - Mensajes descriptivos
   - Fácil de hacer rollback si es necesario

2. **Documentación Continua**
   - PHPDoc en cada método
   - Comentarios en SQL para campos complejos
   - Actualizar ARQUITECTURA.md con cada cambio

3. **Testing Incremental**
   - Probar cada fase antes de continuar
   - No acumular deuda técnica
   - Verificar integridad referencial

4. **Comunicación**
   - Avisar antes de hacer cambios grandes
   - Pedir revisión en puntos críticos
   - Documentar decisiones tomadas

### 📝 Convenciones

**Nombres de Tablas:**
- Plural en español
- snake_case
- Con prefijo del comercio

**Nombres de Columnas:**
- snake_case
- Descriptivos y en español
- Siempre con comentarios

**Nombres de Modelos:**
- Singular en español
- PascalCase
- Siempre con PHPDoc completo

**Migraciones:**
- Formato: `YYYY_MM_DD_HHMMSS_descripcion.php`
- Reversibles cuando sea posible
- Con índices en foreign keys

---

## Checklist General

### Antes de Empezar
- [x] Plan documentado y aprobado
- [x] Branch creado
- [x] Respaldo de base de datos actual

### Durante Implementación
- [ ] Seguir fases en orden
- [ ] Commits incrementales
- [ ] Documentación actualizada
- [ ] Pruebas en cada fase

### Antes de Merge
- [ ] Todas las fases completadas
- [ ] Tests pasando
- [ ] Documentación completa
- [ ] Revisión final con usuario

---

## Información de Contacto

**Usuario del Proyecto:** [Tu nombre/contacto]
**IA Asistente:** Claude (Anthropic)
**Fecha de Inicio:** 2025-11-06
**Versión del Plan:** 1.0.0

---

## Control de Versiones del Plan

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0.0 | 2025-11-06 | Creación inicial del plan |

---

**FIN DEL DOCUMENTO**

Para continuar la implementación, iniciar con **FASE 1: Migraciones de Base de Datos**.
