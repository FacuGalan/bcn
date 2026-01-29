# Problema Resuelto: Menú Vacío y Sin Selector de Sucursales

**Fecha:** 2025-11-10
**Usuario Afectado:** vendedor1
**Estado:** ✅ RESUELTO

---

## 🔴 Problema

Al iniciar sesión con `vendedor1`:
- El menú aparecía vacío
- El selector de sucursales NO se mostraba
- El usuario NO podía trabajar

---

## 🔍 Causa Raíz

Los datos en la tabla `000001_model_has_roles` estaban **mal insertados**:

### Datos Incorrectos:
```sql
| role_id | model_type       | model_id | sucursal_id |
|---------|------------------|----------|-------------|
| 4       | App\Models\User  | 2        | 0           | ← ❌ Da acceso a TODAS
| 4       | AppModelsUser    | 2        | 1           | ← ❌ model_type sin barras
| 4       | AppModelsUser    | 2        | 2           | ← ❌ model_type sin barras
```

### Problemas:
1. **`sucursal_id = 0`**: Hacía que el usuario tuviera acceso a TODAS las sucursales en lugar de solo 2
2. **`model_type = 'AppModelsUser'`**: Sin las barras invertidas escapadas (`\\`), el sistema NO reconocía los registros

---

## ✅ Solución

### Paso 1: Eliminar registros incorrectos
```sql
DELETE FROM 000001_model_has_roles WHERE model_id = 2;
```

### Paso 2: Insertar registros correctos
```sql
INSERT INTO 000001_model_has_roles
(role_id, model_type, model_id, sucursal_id)
VALUES
(4, 'App\\\\Models\\\\User', 2, 1),  -- Casa Central
(4, 'App\\\\Models\\\\User', 2, 2);  -- Sucursal Norte
```

**Nota:** En MySQL desde línea de comandos se necesitan **4 barras invertidas** (`\\\\`) para que se guarden correctamente como **2 barras** (`\\`) en la base de datos.

### Datos Correctos:
```sql
| role_id | model_type        | model_id | sucursal_id |
|---------|-------------------|----------|-------------|
| 4       | App\\Models\\User | 2        | 1           | ✅ Casa Central
| 4       | App\\Models\\User | 2        | 2           | ✅ Sucursal Norte
```

---

## 🧪 Verificación

Después de la corrección:

```
✅ Sucursales disponibles: 2 (Casa Central, Sucursal Norte)
✅ Roles: Vendedor
✅ Permisos: menu.ventas, menu.nueva-venta, menu.listado-ventas, menu.configuracion
✅ Items del menú: 2 (Ventas, Configuración)
✅ Selector de sucursales: Visible con 2 opciones
```

---

## 📝 Lecciones Aprendidas

### 1. Escape de Barras Invertidas en MySQL
Al insertar desde línea de comandos, las barras invertidas deben escaparse:
- `App\Models\User` → `App\\\\Models\\\\User` (4 barras)
- En la BD se guarda como: `App\\Models\\User` (2 barras)

### 2. Importancia del model_type
El campo `model_type` debe coincidir **EXACTAMENTE** con el namespace de la clase:
- ✅ Correcto: `App\\Models\\User`
- ❌ Incorrecto: `AppModelsUser`
- ❌ Incorrecto: `App\Models\User` (solo 1 barra)

### 3. sucursal_id = 0 es un Súper Poder
Cualquier registro con `sucursal_id = 0` da acceso a **TODAS las sucursales**. Usar con cuidado solo para Super Admins.

---

## 🔧 Cómo Insertar Correctamente Usuarios por Sucursal

### Desde MySQL CLI:
```bash
"C:\xampp\mysql\bin\mysql.exe" -u root -pPASSWORD -e "
INSERT INTO 000001_model_has_roles
(role_id, model_type, model_id, sucursal_id)
VALUES
(4, 'App\\\\\\\\Models\\\\\\\\User', 2, 1);
" pymes
```
**Nota:** Se necesitan 8 barras (`\\\\\\\\`) desde Bash para que lleguen como 4 (`\\\\`) a MySQL.

### Desde PHP/Laravel:
```php
DB::connection('pymes_tenant')
    ->table('model_has_roles')
    ->insert([
        'role_id' => 4,
        'model_type' => 'App\\Models\\User',  // ✅ Correcto en PHP
        'model_id' => 2,
        'sucursal_id' => 1,
    ]);
```

### Desde Seeder:
```php
User::find(2)->assignRole(Role::find(4), sucursalId: 1);
```

---

## 🎯 Estado Final

**vendedor1:**
- ✅ Tiene acceso a 2 sucursales: Casa Central (1) y Sucursal Norte (2)
- ✅ Rol: Vendedor
- ✅ NO tiene acceso a Sucursal Sur (3)
- ✅ El menú se muestra correctamente
- ✅ El selector de sucursales funciona

**admin1:**
- ✅ Tiene acceso a TODAS las sucursales (sucursal_id = 0)
- ✅ Rol: Super Administrador
- ✅ El menú completo se muestra
- ✅ El selector muestra las 3 sucursales

---

## 📚 Referencias

- `SISTEMA_ACCESO_SUCURSALES.md`: Documentación completa del sistema
- `app/Services/SucursalService.php`: Lógica de obtención de sucursales
- `app/Models/User.php`: Método `roles()` con filtrado por sucursal
- `app/Livewire/Forms/LoginForm.php`: Establece sucursal por defecto al login

---

**FIN DEL DOCUMENTO**
