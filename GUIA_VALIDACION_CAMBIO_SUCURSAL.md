# Guía de Validación - Sistema de Cambio de Sucursal

**Fecha:** 2025-11-07
**Propósito:** Validar que el cambio de sucursal funcione correctamente en todas las operaciones

---

## ✅ Cambios Implementados

### 1. **Componente SucursalSelector**
- `app/Livewire/SucursalSelector.php`
- `resources/views/livewire/sucursal-selector.blade.php`
- Dropdown visual integrado en el header (desktop y móvil)
- Guarda la sucursal seleccionada en la sesión
- Redirige al dashboard después del cambio

### 2. **Middleware EnsureSucursalSelected**
- `app/Http/Middleware/EnsureSucursalSelected.php`
- Garantiza que siempre haya una sucursal activa en la sesión
- Se ejecuta en todos los requests web
- Selecciona automáticamente la sucursal principal si no hay ninguna

### 3. **Servicio Centralizado**
- `app/Services/SucursalService.php`
- Centraliza toda la lógica de sucursales
- Métodos para obtener sucursal activa, validar acceso, etc.

### 4. **Helper Global**
- `app/Helpers/helpers.php`
- Función `sucursal_activa()` para obtener el ID de la sucursal
- Función `sucursal_activa_model()` para obtener el modelo completo
- Función `tiene_acceso_sucursal($id)` para validar acceso

### 5. **Dashboard Actualizado**
- `app/Livewire/Dashboard/DashboardSucursal.php`
- Usa `sucursal_activa()` para obtener la sucursal de la sesión
- Header visual prominente que muestra sucursal activa
- Mensaje flash cuando se cambia de sucursal

### 6. **Integración en Navegación**
- `resources/views/livewire/layout/navigation.blade.php:174` (Desktop)
- `resources/views/livewire/layout/navigation.blade.php:375` (Móvil)

---

## 🔍 Pasos de Validación

### Paso 1: Login y Verificación Inicial

1. **Limpiar sesiones previas:**
   - Cerrar todos los navegadores
   - Abrir navegador en modo incógnito

2. **Hacer login:**
   - Email comercio: `comercio1@test.com`
   - Username: `admin1`
   - Password: `password`

3. **Verificar estado inicial:**
   - ✅ Deberías ver el selector de sucursal en el header (arriba a la derecha)
   - ✅ El dashboard debería mostrar "Casa Central" (sucursal principal)
   - ✅ El header del dashboard debe ser azul con el nombre de la sucursal en grande
   - ✅ Debe mostrar el código "CENTRAL" y badge "⭐ Sucursal Principal"

### Paso 2: Verificar Datos del Dashboard

1. **Revisar métricas mostradas:**
   - Ventas del día: $XXX (cantidad de operaciones)
   - Compras del día: $XXX
   - Cajas abiertas: 1/1
   - Alertas de stock: X artículos

2. **Anotar valores actuales:**
   ```
   Casa Central:
   - Total ventas: $_______
   - Cantidad ventas: ____
   - Artículos con stock bajo: ____
   ```

### Paso 3: Cambiar a Sucursal Norte

1. **Hacer click en el selector de sucursal** (header derecho)
2. **Seleccionar "Sucursal Norte"**
3. **Verificar que la página se recargue**

4. **Verificaciones POST-cambio:**
   - ✅ El dropdown ahora debe mostrar "Sucursal Norte"
   - ✅ Debe aparecer mensaje verde: "Cambiado a sucursal: Sucursal Norte"
   - ✅ El header del dashboard debe decir "Sucursal Norte"
   - ✅ El código debe ser "NORTE"
   - ✅ NO debe tener badge "Principal"
   - ✅ Las métricas deben ser DIFERENTES a las de Casa Central

5. **Anotar valores de Sucursal Norte:**
   ```
   Sucursal Norte:
   - Total ventas: $_______
   - Cantidad ventas: ____
   - Artículos con stock bajo: ____
   ```

### Paso 4: Cambiar a Sucursal Sur

1. **Hacer click en el selector nuevamente**
2. **Seleccionar "Sucursal Sur"**

3. **Verificaciones:**
   - ✅ Header debe decir "Sucursal Sur"
   - ✅ Código debe ser "SUR"
   - ✅ Las métricas deben ser DIFERENTES a Norte y Central
   - ✅ Mensaje flash de confirmación

4. **Anotar valores de Sucursal Sur:**
   ```
   Sucursal Sur:
   - Total ventas: $_______
   - Cantidad ventas: ____
   - Artículos con stock bajo: ____
   ```

### Paso 5: Navegación Entre Páginas

1. **Con Sucursal Sur activa, navegar a diferentes secciones:**
   - Click en "Ventas" (si está en el menú)
   - Click en "Stock" (si está en el menú)
   - Volver al Dashboard

2. **Verificar:**
   - ✅ El selector de sucursal siempre muestra "Sucursal Sur"
   - ✅ Al volver al dashboard, sigue mostrando datos de Sucursal Sur
   - ✅ La sucursal NO cambia al navegar

### Paso 6: Volver a Casa Central

1. **Cambiar nuevamente a Casa Central**
2. **Verificar que los valores sean los mismos que anotaste en el Paso 2**
3. **Confirmar:**
   - ✅ Total ventas coincide
   - ✅ Cantidad de ventas coincide
   - ✅ Stock bajo coincide

---

## ⚠️ Qué NO Debe Pasar

### Problemas a Detectar:

1. **Dashboard no cambia:**
   - ❌ Si el selector cambia pero las métricas siguen igual
   - ❌ Si siempre muestra Casa Central sin importar qué selecciones

2. **Sucursal se resetea:**
   - ❌ Si al navegar entre páginas vuelve a Casa Central
   - ❌ Si al recargar la página cambia de sucursal

3. **Valores inconsistentes:**
   - ❌ Si Casa Central muestra diferentes valores al volver
   - ❌ Si las 3 sucursales muestran los mismos valores

4. **Errores visuales:**
   - ❌ Si el dropdown no se ve
   - ❌ Si no aparece el mensaje de confirmación
   - ❌ Si el header del dashboard no cambia

---

## 🎯 Criterios de Éxito

Para considerar la validación **EXITOSA**, todos estos puntos deben cumplirse:

- [x] El selector de sucursal es visible en el header
- [x] Cada sucursal muestra datos DIFERENTES
- [x] Al cambiar de sucursal, el dashboard se actualiza inmediatamente
- [x] La sucursal seleccionada se mantiene al navegar entre páginas
- [x] El header visual muestra claramente qué sucursal está activa
- [x] Aparece mensaje de confirmación al cambiar
- [x] Al volver a una sucursal, muestra los mismos datos que antes

---

## 🔧 Validación Técnica (Para el Desarrollador)

### Verificar en Base de Datos:

```sql
-- Ver datos de cada sucursal
SELECT
    s.nombre,
    COUNT(DISTINCT v.id) as total_ventas,
    SUM(v.total) as monto_ventas
FROM 000001_sucursales s
LEFT JOIN 000001_ventas v ON v.sucursal_id = s.id
WHERE s.activa = 1
GROUP BY s.id, s.nombre;
```

### Verificar en Session (Chrome DevTools):

1. Abrir DevTools (F12)
2. Ir a Application > Cookies
3. Buscar cookie de Laravel session
4. Al cambiar de sucursal, ver que la sesión se actualiza

### Verificar que otros componentes usen `sucursal_activa()`:

```bash
# Buscar componentes que deberían usar el helper
grep -r "session('sucursal_id')" app/Livewire/
grep -r "where('sucursal_id')" app/Livewire/
```

---

## 📊 Ejemplo de Valores Esperados

Según `CREDENCIALES_DEMO.md`, deberías ver aproximadamente:

| Sucursal | Stock Casa Central | Stock Sucursal Norte | Stock Sucursal Sur |
|----------|-------------------|---------------------|--------------------|
| Coca Cola 500ml | 50-100 unidades | 30-60 unidades | 20-40 unidades |
| Agua Mineral | 50-100 unidades | 30-60 unidades | 20-40 unidades |

**Las ventas varían por sucursal (5-8 ventas por sucursal)**, por lo que cada una debe mostrar diferentes totales.

---

## 🐛 Problemas Conocidos a Validar

### Si el dashboard NO cambia:

1. **Verificar que el Dashboard use `sucursal_activa()`:**
   - Revisar `app/Livewire/Dashboard/DashboardSucursal.php:48`
   - Debe decir: `$this->sucursalSeleccionada = sucursal_activa()`

2. **Verificar que el helper esté cargado:**
   ```bash
   php artisan tinker
   >>> sucursal_activa()
   >>> # Debe retornar un número (1, 2, o 3)
   ```

3. **Verificar sesión:**
   ```php
   dd(session()->all());  // Agregar en el Dashboard mount()
   ```

---

## ✅ Checklist Final

Después de las pruebas, marca cada punto:

- [ ] Selector visible en header (desktop)
- [ ] Selector visible en móvil (sidebar)
- [ ] Casa Central muestra badge "Principal"
- [ ] Cada sucursal muestra datos diferentes
- [ ] Dashboard se actualiza al cambiar sucursal
- [ ] Aparece mensaje "Cambiado a sucursal: X"
- [ ] Header del dashboard muestra nombre y código correctos
- [ ] Sucursal se mantiene al navegar entre páginas
- [ ] Al volver a una sucursal, datos son consistentes
- [ ] No hay errores en consola del navegador
- [ ] No hay errores en logs de Laravel

---

## 📞 Próximos Pasos

Una vez validado el cambio de sucursal en el Dashboard:

1. **Validar en módulo de Ventas:**
   - Crear una venta en Sucursal Norte
   - Verificar que se registre en esa sucursal
   - Cambiar a Casa Central y verificar que NO aparezca esa venta

2. **Validar en módulo de Stock:**
   - Ver stock de un artículo en cada sucursal
   - Confirmar que los valores sean diferentes

3. **Validar en módulo de Cajas:**
   - Abrir caja en una sucursal
   - Cambiar de sucursal
   - Verificar que cada sucursal tiene su propia caja

---

**Documento generado:** 2025-11-07
**Versión del sistema:** Post-implementación cambio de sucursal
**Responsable:** Equipo de desarrollo BCN Pymes
