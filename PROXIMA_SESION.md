# 📋 PRÓXIMA SESIÓN - Sistema de Precios Dinámico

**Fecha de última sesión:** 2025-11-18
**Estado actual:** UI de Precios Base COMPLETADA ✅ | PRÓXIMO: Precios Promocionales

---

## ✅ Lo que YA ESTÁ HECHO

### Sistema de Gestión de Artículos
- ✅ Vista de gestión de artículos con categorías y badges
- ✅ Configuración de artículos por sucursal (funcional y con auto-save)
- ✅ **Precio Base en gestión de artículos** (campo obligatorio que funciona como fallback global)
- ✅ Filtros por categoría
- ✅ Sistema de menú dinámico con rutas relacionadas
- ✅ Navegación SPA con wire:navigate

### Sistema de Precios Dinámico (FASE 1 - Base de datos)
- ✅ 11 tablas de base de datos creadas con prefijo `000001_`
- ✅ 11 modelos Eloquent con relaciones completas
- ✅ PrecioService.php completo y funcional (app/Services/)
- ✅ 8 seeders ejecutados con datos de prueba
- ✅ Documentación completa:
  - ROADMAP_SISTEMA_PRECIOS.md
  - PRECIO_SERVICE_GUIA.md

### 🆕 UI de Administración de Precios Base (COMPLETADA HOY)

#### Componentes Implementados:
1. **ListarPrecios.php** (`app/Livewire/Configuracion/Precios/`)
   - ✅ Listado paginado de precios con filtros
   - ✅ Búsqueda por artículo (código/nombre)
   - ✅ Filtros por: sucursal, forma de venta, canal de venta, estado (activo/inactivo)
   - ✅ Ordenamiento por artículo, precio, fecha
   - ✅ Vista responsive (cards móvil / tabla desktop)
   - ✅ Edición inline del precio (solo monto)
   - ✅ Toggle activo/inactivo
   - ✅ Eliminación de precios
   - ✅ **Badge azul mostrando precio_base del artículo como referencia**

2. **WizardPrecio.php** (`app/Livewire/Configuracion/Precios/`)
   - ✅ Wizard de 3 pasos para crear precios
   - ✅ **Paso 1:** Selección de artículo con búsqueda dinámica
   - ✅ **Paso 2:** Selección de contexto (sucursales, forma venta, canal venta)
   - ✅ **Paso 3:** Precio, vigencia desde/hasta, estado activo
   - ✅ **Selección múltiple de sucursales** (crea un precio por cada sucursal)
   - ✅ **Detección de conflictos:** valida que no exista un precio con mismo contexto y fechas solapadas
   - ✅ **Advertencias visuales** de precios conflictivos antes de guardar
   - ✅ Validación de solapamiento de fechas (permanentes, con inicio, con fin, con rango)
   - ✅ Notificaciones toast

3. **Vistas Blade:**
   - ✅ `listar-precios.blade.php` - responsive con TailwindCSS
   - ✅ `wizard-precio.blade.php` - wizard paso a paso con indicadores visuales

4. **Rutas Configuradas:**
   ```php
   Route::get('precios', ListarPrecios::class)->name('configuracion.precios');
   Route::get('precios/nuevo', WizardPrecio::class)->name('configuracion.precios.nuevo');
   ```

5. **Menú Items Configurados:**
   - ✅ "Precios" dentro de "Configuración" (icono: icon.dollar-sign)
   - ✅ Permisos asignados al rol "Administrador de Comercio"
   - ✅ Route value: `configuracion.precios`

#### Lógica de Precios Implementada:

**Jerarquía de especificidad (de más específico a más genérico):**
1. Precio con sucursal + forma_venta + canal_venta + rango de fechas
2. Precio con sucursal + forma_venta + rango de fechas
3. Precio con sucursal + canal_venta + rango de fechas
4. Precio con sucursal + rango de fechas
5. Precio con sucursal (permanente)
6. **Fallback final:** `articulos.precio_base` (campo obligatorio)

**Reglas de conflictos:**
- Solo detecta conflicto cuando la especificidad es **EXACTAMENTE IGUAL**
- No permite crear precio si se solapa con otro de mismo contexto
- Valida solapamiento de fechas:
  - Permanente vs permanente → conflicto
  - Con rango vs con rango solapado → conflicto
  - Permanente vs cualquier fecha → conflicto

**Datos de prueba disponibles:**
- 40+ precios base con 4 niveles de especificidad
- 10 categorías con colores
- 5 formas de venta (Local, Delivery, Take Away, Mayorista, Online)
- 8 canales de venta (POS, Salón, Web, WhatsApp, etc.)

---

## 🎯 PRÓXIMO PASO: UI de Promociones (MÁS COMPLEJO)

### ¿Por qué es más complejo?

Las promociones tienen:
1. **Múltiples tipos de descuento:**
   - Porcentaje sobre precio base
   - Monto fijo de descuento
   - Precio final fijo
   - 2x1, 3x2, etc.

2. **Condiciones de activación:**
   - Por día de semana
   - Por rango horario
   - Por cantidad mínima de unidades
   - Por monto mínimo de compra
   - Por forma de pago específica
   - Por canal de venta
   - Cupones/códigos

3. **Escalas de descuento:**
   - 2 unidades: 15% OFF
   - 3-4 unidades: 25% OFF
   - 5+ unidades: 35% OFF

4. **Compatibilidad:**
   - Algunas promociones son acumulables
   - Otras son excluyentes
   - Orden de aplicación (de mayor a menor prioridad)

### Componentes a Crear:

```
app/Livewire/Configuracion/Promociones/
├── ListarPromociones.php          ← Listado con filtros
├── WizardPromocion.php            ← Wizard de 4-5 pasos
└── GestionarCondiciones.php       ← Sub-componente para condiciones

resources/views/livewire/configuracion/promociones/
├── listar-promociones.blade.php
├── wizard-promocion.blade.php
└── partials/
    ├── paso-basico.blade.php      ← Nombre, descripción, tipo
    ├── paso-descuento.blade.php   ← Config del descuento
    ├── paso-condiciones.blade.php ← Día, hora, monto, cantidad
    ├── paso-escalas.blade.php     ← Si tiene descuento escalonado
    └── paso-vigencia.blade.php    ← Fechas y activación
```

### Estructura del Wizard de Promoción (Propuesta):

#### **Paso 1: Información Básica**
- Nombre de la promoción
- Descripción interna
- Tipo de promoción:
  - [ ] Descuento porcentual
  - [ ] Descuento monto fijo
  - [ ] Precio final fijo
  - [ ] NxM (2x1, 3x2, etc.)
  - [ ] Descuento escalonado por cantidad

#### **Paso 2: Configuración del Descuento**
Depende del tipo seleccionado:
- **Porcentual:** % de descuento
- **Monto fijo:** $ de descuento
- **Precio final:** $ precio final
- **NxM:** N pagas, M llevas
- **Escalonado:** Tabla de escalas (cantidad desde/hasta → descuento %)

#### **Paso 3: Artículos Aplicables**
- [ ] Todos los artículos
- [ ] Por categoría
- [ ] Artículos específicos (multi-select con búsqueda)
- [ ] Excluir artículos específicos

#### **Paso 4: Condiciones de Activación** (Todas opcionales)
- **Temporal:**
  - Días de semana: [ ] Lun [ ] Mar [ ] Mié [ ] Jue [ ] Vie [ ] Sab [ ] Dom
  - Rango horario: Desde __:__ Hasta __:__
  - Fechas: Desde __/__/__ Hasta __/__/__

- **Por Cantidad:**
  - Cantidad mínima de unidades
  - Cantidad máxima de unidades

- **Por Monto:**
  - Monto mínimo de compra
  - Monto máximo de compra

- **Por Contexto:**
  - Forma de venta específica
  - Canal de venta específico
  - Forma de pago específica

- **Por Cupón:**
  - [ ] Requiere código de cupón
  - Código: ________

#### **Paso 5: Configuración Final**
- Prioridad (1-10, más alto = más prioridad)
- ¿Es acumulable con otras promociones? [ ] Sí [ ] No
- Sucursales donde aplica: [multi-select]
- Estado: [ ] Activa [ ] Inactiva

#### **Paso 6: Resumen y Confirmación**
- Vista previa de la promoción configurada
- Warnings si hay conflictos
- Botón "Crear Promoción"

### Validaciones Complejas Necesarias:

1. **Detección de Conflictos:**
   - Promociones con mismo código de cupón
   - Promociones incompatibles (ej: 2 de tipo "precio final" en mismo artículo)

2. **Validación de Lógica:**
   - Si tipo = NxM: validar que N < M
   - Si tiene escalas: validar que rangos no se solapen
   - Si requiere cupón: validar que código sea único

3. **Warnings (no bloqueantes):**
   - Promoción muy genérica (sin condiciones) puede generar muchos descuentos
   - Descuento > 90% (posible error)
   - Fecha de fin anterior a fecha de inicio

### Tablas de BD Involucradas:

```
000001_promociones             ← Datos básicos de la promoción
000001_promociones_condiciones ← Condiciones (día, hora, monto, etc.)
000001_promociones_escalas     ← Escalas de descuento por cantidad
```

### Modelos ya Disponibles:

```php
App\Models\Promocion              ← Con todos los scopes y métodos
App\Models\PromocionCondicion     ← Relación hasMany
App\Models\PromocionEscala        ← Relación hasMany
```

---

## 📝 Tareas Pendientes para Próxima Sesión

### FASE 2: UI de Promociones

1. **Crear ListarPromociones.php**
   - Listado con filtros (tipo, estado, categoría)
   - Cards responsive
   - Edición/eliminación
   - Duplicar promoción
   - Preview de condiciones

2. **Crear WizardPromocion.php**
   - 6 pasos del wizard
   - Validaciones complejas
   - Detección de conflictos
   - Preview antes de crear

3. **Crear vistas blade correspondientes**
   - listar-promociones.blade.php
   - wizard-promocion.blade.php
   - Partials para cada paso del wizard

4. **Configurar rutas**
   ```php
   Route::get('promociones', ListarPromociones::class)->name('configuracion.promociones');
   Route::get('promociones/nueva', WizardPromocion::class)->name('configuracion.promociones.nueva');
   ```

5. **Agregar menú item**
   - Nombre: "Promociones"
   - Parent: Configuración
   - Icono: icon.tag
   - Route: configuracion.promociones

6. **Asignar permisos**
   - Crear permission: `menu.promociones`
   - Asignar a rol "Administrador de Comercio"

---

## 🔍 Comandos Útiles para Debug

```bash
# Ver promociones de prueba cargadas
php artisan tinker
>>> \App\Models\Promocion::with('condiciones', 'escalas')->get()

# Ver precios base
>>> \App\Models\PrecioBase::with('articulo', 'sucursal')->count()

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ver logs
tail -f storage/logs/laravel.log

# Ver estructura de tablas
"C:\xampp\mysql\bin\mysql.exe" -u root -p40500273 -e "DESCRIBE pymes.000001_promociones"
"C:\xampp\mysql\bin\mysql.exe" -u root -p40500273 -e "DESCRIBE pymes.000001_promociones_condiciones"
"C:\xampp\mysql\bin\mysql.exe" -u root -p40500273 -e "DESCRIBE pymes.000001_promociones_escalas"
```

---

## 📚 Documentación de Referencia

**Lee antes de continuar:**
- `ROADMAP_SISTEMA_PRECIOS.md` - Roadmap completo con detalles técnicos
- `PRECIO_SERVICE_GUIA.md` - Guía de uso del servicio con ejemplos
- `MENU_PERMISSIONS_GUIDE.md` - Guía de permisos y menú

**Archivos clave:**
- Servicio: `app/Services/PrecioService.php` (método `aplicarPromociones()`)
- Modelos: `app/Models/Promocion.php` (scopes: `activas()`, `aplicableA()`)
- Seeders: `database/seeders/PromocionesSeeder.php` (10 promociones de ejemplo)

**Casos de prueba disponibles en seeders:**
1. Happy Hour (30% OFF bebidas, Lun-Vie 17:00-20:00)
2. Descuento escalonado por cantidad (2 unid: 15%, 3-4: 25%, 5+: 35%)
3. Cupón VERANO2025 (15% OFF)
4. Delivery >$500 (10% OFF)
5. Pago en efectivo (5% extra)
6. 2x1 en productos seleccionados
7. Precio fijo promocional
8. Combo familiar (4+ unidades, 40% OFF)
9. Compra mínima $1000 (20% OFF)
10. Black Friday especial (50% OFF, solo fines de semana)

---

## 🎯 Prioridad para Próxima Sesión

**OPCIÓN 1: Continuar con UI de Promociones** ⭐ RECOMENDADA
- Crear ListarPromociones y WizardPromocion
- Completar el sistema de administración
- Luego integrar con POS

**OPCIÓN 2: Integrar Precios con POS**
- Modificar NuevaVenta.php
- Agregar selectores de contexto
- Usar PrecioService.calcularPrecioFinal()
- Dejar promociones para después

**OPCIÓN 3: Testing y validación**
- Probar creación de precios base
- Validar detección de conflictos
- Verificar cálculos con PrecioService
- Documentar casos de prueba

---

**Última actualización:** 2025-11-18 (tarde)
**Completado hoy:** UI de Precios Base ✅
**Próxima tarea:** UI de Promociones (más compleja)
**Estado general:** 60% del sistema de precios completado
