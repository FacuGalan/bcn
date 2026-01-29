# Credenciales y Datos de Demo - Comercio 1

**Fecha de creación:** 2025-11-07

---

## 🔑 Credenciales de Acceso

### Usuario Admin1 (Acceso completo a las 3 sucursales)

- **Email del comercio:** `comercio1@test.com`
- **Username:** `admin1`
- **Password:** `password`
- **Email personal:** `admin1@comercio1.com`
- **Rol:** Administrador en las 3 sucursales
- **Permisos:** Acceso completo a todas las funcionalidades

---

## 🏢 Sucursales Creadas

### 1. Casa Central (Principal)
- **ID:** 1
- **Código:** CENTRAL
- **Dirección:** Av. Corrientes 1234, CABA
- **Teléfono:** 011-4567-8901
- **Email:** central@comercio1.com
- **Estado:** Activa
- **Es Principal:** Sí

### 2. Sucursal Norte
- **ID:** 2
- **Código:** NORTE
- **Dirección:** Av. Cabildo 2345, Belgrano
- **Teléfono:** 011-4567-8902
- **Email:** norte@comercio1.com
- **Estado:** Activa
- **Es Principal:** No

### 3. Sucursal Sur
- **ID:** 3
- **Código:** SUR
- **Dirección:** Av. Avellaneda 3456, Avellaneda
- **Teléfono:** 011-4567-8903
- **Email:** sur@comercio1.com
- **Estado:** Activa
- **Es Principal:** No

---

## 📦 Artículos Creados (13 total)

### Bebidas
1. Coca Cola 500ml (BEB001) - $350
2. Agua Mineral 500ml (BEB002) - $200
3. Cerveza Quilmes 1L (BEB003) - $550
4. Jugo Baggio 1L (BEB004) - $380

### Snacks
5. Papas Lays 150g (SNK001) - $420
6. Alfajor Jorgito (SNK002) - $180
7. Galletitas Oreo (SNK003) - $450

### Limpieza
8. Detergente Magistral 500ml (LIM001) - $580
9. Lavandina Ayudín 1L (LIM002) - $320
10. Esponja Scotch Brite (LIM003) - $250

### Alimentos
11. Arroz Gallo 1kg (ALM001) - $680
12. Fideos Marolio 500g (ALM002) - $320
13. Aceite Cocinero 900ml (ALM003) - $1250

**Nota:** Todos los artículos están asignados a las 3 sucursales con stock variable:
- Casa Central: 50-100 unidades por artículo
- Sucursal Norte: 30-60 unidades por artículo
- Sucursal Sur: 20-40 unidades por artículo

---

## 👥 Clientes Creados (4 total)

1. **Juan Pérez**
   - CUIT: 20-12345678-5
   - Email: juan.perez@email.com
   - Teléfono: 11-2345-6789

2. **María García**
   - CUIT: 27-23456789-3
   - Email: maria.garcia@email.com
   - Teléfono: 11-3456-7890

3. **Empresa XYZ S.A.**
   - CUIT: 30-12345678-9
   - Razón Social: Empresa XYZ S.A.
   - Email: contacto@empresaxyz.com
   - Teléfono: 11-4567-8901

4. **Carlos López**
   - CUIT: 20-34567890-7
   - Email: carlos.lopez@email.com
   - Teléfono: 11-5678-9012

**Nota:** Todos los clientes están disponibles en las 3 sucursales.

---

## 💵 Cajas Creadas

### Casa Central
- **Nombre:** Caja Principal Casa Central
- **Código:** CAJA-CENTRAL
- **Saldo Inicial:** $5.000
- **Estado:** Abierta
- **Movimientos:** Apertura + 3 ingresos + 2 egresos

### Sucursal Norte
- **Nombre:** Caja Principal Sucursal Norte
- **Código:** CAJA-NORTE
- **Saldo Inicial:** $5.000
- **Estado:** Abierta
- **Movimientos:** Apertura + 3 ingresos + 2 egresos

### Sucursal Sur
- **Nombre:** Caja Principal Sucursal Sur
- **Código:** CAJA-SUR
- **Saldo Inicial:** $5.000
- **Estado:** Abierta
- **Movimientos:** Apertura + 3 ingresos + 2 egresos

---

## 🛒 Ventas Generadas

### Resumen por Sucursal
- **Casa Central:** 5-8 ventas
- **Sucursal Norte:** 5-8 ventas
- **Sucursal Sur:** 5-8 ventas

### Características de las Ventas
- Fechas: Últimos 7 días
- Formas de pago: Efectivo, Tarjeta, Transferencia
- Items por venta: 2-5 artículos
- Cantidad por item: 1-3 unidades
- Clientes: Asignados aleatoriamente
- Estado: Todas completadas

**Nota:** Las ventas en efectivo generan automáticamente movimientos de ingreso en la caja correspondiente.

---

## 💰 Tipos de IVA Configurados

1. **IVA 21%** (Código AFIP: 5)
   - Aplica a: Bebidas, Snacks, Limpieza

2. **IVA 10.5%** (Código AFIP: 4)
   - Aplica a: Alimentos

3. **Exento** (Código AFIP: 3)
   - Aplica a: Productos sin IVA

---

## 🔄 Flujo de Prueba Recomendado

### 1. Login
1. Ir a la página de login
2. Ingresar:
   - Email comercio: `comercio1@test.com`
   - Username: `admin1`
   - Password: `password`

### 2. Selector de Sucursal
El usuario admin1 tiene acceso a las 3 sucursales. Podrá:
- Ver un selector en el header para cambiar entre sucursales
- Ver el dashboard específico de cada sucursal
- Acceder a todas las funcionalidades en cada sucursal

### 3. Áreas a Explorar

#### Dashboard
- Ver métricas de ventas del día
- Ver estado de cajas
- Ver alertas de stock bajo mínimo
- Ver últimas operaciones

#### Ventas
- Listado de ventas realizadas
- Filtrar por fecha, cliente, forma de pago
- Ver detalles de cada venta

#### Stock/Inventario
- Ver stock por artículo
- Alertas de stock bajo mínimo
- Movimientos de stock

#### Cajas
- Estado de cajas
- Movimientos de caja
- Saldos actuales

---

## 📝 Notas Importantes

1. **Stock Variable:** El stock es diferente en cada sucursal, simulando un escenario real.

2. **Movimientos de Caja:** Incluyen apertura, ingresos varios, egresos y ventas en efectivo.

3. **Ventas Distribuidas:** Las ventas están distribuidas en los últimos 7 días con diferentes formas de pago.

4. **IVA Calculado:** Todas las ventas tienen el IVA calculado automáticamente según el tipo de artículo.

5. **Acceso Multi-Sucursal:** El usuario admin1 puede cambiar entre sucursales sin necesidad de re-autenticarse.

---

## 🚀 Próximos Pasos

Una vez probado el sistema con estos datos, puedes:

1. **Crear más ventas** manualmente
2. **Agregar más artículos**
3. **Registrar compras** a proveedores
4. **Realizar transferencias** de stock entre sucursales
5. **Gestionar cajas** (cierre, arqueos, etc.)
6. **Crear más usuarios** con diferentes permisos por sucursal

---

## 🔧 Comandos Útiles

### Regenerar datos de demo
```bash
php artisan db:seed --class=DemoComercio1Seeder
```

### Ver estado del sistema
```bash
php artisan migrate:status
```

### Limpiar cachés
```bash
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

---

**Generado automáticamente por el seeder DemoComercio1Seeder**
