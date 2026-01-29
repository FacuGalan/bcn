# Sistema de Tesorería - Estado Actual

## Estado: IMPLEMENTACIÓN COMPLETADA

**Última actualización:** 2026-01-22

---

## Resumen de lo Implementado

### Fase 1: Modificaciones Inmediatas
- [x] Campo `fondo_comun` y `saldo_fondo_comun` en `grupos_cierre`
- [x] Mostrar TODAS las cajas de la sucursal (incluyendo nunca abiertas)
- [x] Modal de apertura grupal con fondo común

### Fase 2: Sistema de Tesorería (Tablas)
- [x] Tabla `tesorerias` - Caja fuerte por sucursal
- [x] Tabla `movimientos_tesoreria` - Con saldo anterior/posterior
- [x] Tabla `cuentas_bancarias` - Para depósitos
- [x] Tabla `depositos_bancarios` - Registro de depósitos
- [x] Tabla `provision_fondos` - Tesorería → Cajas
- [x] Tabla `rendicion_fondos` - Cajas → Tesorería
- [x] Tabla `arqueos_tesoreria` - Arqueos periódicos

### Fase 3: Servicios y Componentes
- [x] `TesoreriaService.php` - Operaciones core
- [x] `CajaService.php` - Integración con tesorería
- [x] `GestionTesoreria.php` - Componente Livewire
- [x] `ReportesTesoreria.php` - Componente Livewire
- [x] Vistas blade completas
- [x] Rutas y menú

### Fase 4: Integración con Cajas (COMPLETADA)
- [x] Migraciones SQL aplicadas a base de datos
- [x] Tesorerías creadas para todas las sucursales (3)
- [x] Menú de tesorería creado con submenús
- [x] `TurnoActual.php` integrado con `CajaService::abrirCajaConTesoreria()`
- [x] `TurnoActual.php` integrado con `CajaService::cerrarCajaConTesoreria()`

### Fase 5: Mejoras en Cierre de Turno (COMPLETADA)
- [x] Modal de cierre rediseñado (full height, sin scroll)
- [x] Saldo declarado vacío por defecto (el usuario lo ingresa)
- [x] Cálculo de diferencia en tiempo real (faltante/sobrante)
- [x] Excluir movimientos de apertura del cálculo de ingresos operativos
- [x] Usar `saldo_actual` como saldo del sistema (incluye todos los movimientos)
- [x] Fix: ingresos de ventas ahora se muestran correctamente en modal

### Fase 6: Configuración de Grupos (COMPLETADA)
- [x] Toggle "Fondo Común" en modal de crear/editar grupo
- [x] Validación: no permite modificar grupos con turno abierto
- [x] Validación: no permite eliminar grupos con turno abierto
- [x] Badge visual "Común" en tarjetas de grupos con fondo compartido
- [x] Información actualizada en panel de ayuda

---

## Tablas Creadas en Base de Datos

Todas las tablas fueron creadas con el prefijo `000001_` para el comercio 1:

| Tabla | Descripción |
|-------|-------------|
| `000001_tesorerias` | Caja fuerte por sucursal |
| `000001_movimientos_tesoreria` | Historial con saldo anterior/posterior |
| `000001_cuentas_bancarias` | Cuentas para depósitos |
| `000001_depositos_bancarios` | Registro de depósitos bancarios |
| `000001_provision_fondos` | Tesorería → Cajas (apertura) |
| `000001_rendicion_fondos` | Cajas → Tesorería (cierre) |
| `000001_arqueos_tesoreria` | Arqueos periódicos |

---

## Estado de las Tesorerías

| ID | Sucursal | Nombre | Saldo |
|----|----------|--------|-------|
| 1 | Casa Central | Tesorería Principal | $0,00 |
| 2 | Sucursal Norte | Tesorería Principal | $0,00 |
| 3 | Sucursal Sur | Tesorería Principal | $0,00 |

---

## Menú de Tesorería

```
Tesorería (heroicon-o-banknotes)
├── Gestión (tesoreria.index)
└── Reportes (tesoreria.reportes)
```

---

## Flujo de Operaciones

### Apertura de Caja
1. Usuario abre modal de apertura
2. Ingresa fondo inicial
3. Sistema usa `CajaService::abrirCajaConTesoreria()`
4. Si hay tesorería y fondo > 0:
   - Verifica saldo suficiente en tesorería
   - Crea provisión de fondo
   - Registra egreso en tesorería
   - Registra ingreso en caja
5. Caja queda abierta con el fondo

### Cierre de Caja
1. Usuario abre modal de cierre
2. Ingresa saldo declarado
3. Sistema usa `CajaService::cerrarCajaConTesoreria()`
4. Calcula saldo del sistema y diferencia
5. Si hay tesorería y saldo > 0:
   - Crea rendición de fondo
   - Registra egreso en caja
   - Registra ingreso en tesorería
6. Caja queda cerrada con saldo 0

---

## Archivos Clave

### Modelos
```
app/Models/
├── Tesoreria.php
├── MovimientoTesoreria.php
├── CuentaBancaria.php
├── DepositoBancario.php
├── ProvisionFondo.php
├── RendicionFondo.php
└── ArqueoTesoreria.php
```

### Servicios
```
app/Services/
├── TesoreriaService.php
└── CajaService.php (métodos agregados)
```

### Componentes Livewire
```
app/Livewire/Tesoreria/
├── GestionTesoreria.php
└── ReportesTesoreria.php

app/Livewire/Cajas/
└── TurnoActual.php (integrado con tesorería)
```

### Vistas
```
resources/views/livewire/tesoreria/
├── gestion-tesoreria.blade.php
└── reportes-tesoreria.blade.php
```

---

## Tareas Pendientes (Prioridad Baja)

### Mejoras UI
- [ ] Agregar configuración de tesorería en `ConfiguracionEmpresa`
- [ ] Permitir crear/editar cuentas bancarias desde la UI
- [ ] Dashboard con widgets de tesorería
- [ ] Selección de tesorería en modal de apertura (si hay múltiples por sucursal)

### Migración de Datos
- [ ] Script para calcular saldo inicial de tesorería desde cajas cerradas
- [ ] Resetear saldos de cajas cerradas a 0 (si se desea)

---

## Acceso al Sistema

**URL de Tesorería:**
- Gestión: `/tesoreria`
- Reportes: `/tesoreria/reportes`

**Requisitos:**
- Usuario autenticado
- Comercio y sucursal seleccionados
- Permisos adecuados (según configuración de roles)
