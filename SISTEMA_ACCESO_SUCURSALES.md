# Sistema de Acceso a Sucursales - Explicación Completa

**Fecha:** 2025-11-10
**Versión:** 1.0.0

---

## 📋 Resumen Ejecutivo

El sistema de acceso a sucursales está basado en **asignación por USUARIO**, NO por rol. Esto significa que cada usuario tiene acceso a sucursales específicas, independientemente de su rol.

---

## 🎯 Concepto Clave: Acceso por Usuario

### ❌ NO es así (por rol):
```
Rol "Vendedor" → Tiene acceso a Sucursales [1, 2, 3]
```
Si fuera así, TODOS los vendedores tendrían acceso a las mismas sucursales.

### ✅ SÍ es así (por usuario):
```
Usuario Juan (Vendedor) → Tiene acceso a Sucursales [1, 2]
Usuario María (Vendedor) → Tiene acceso a Sucursales [3]
Usuario Admin (Super Admin) → Tiene acceso a Sucursales [TODAS]
```

---

## 🗄️ Estructura de Base de Datos

### Tabla: `000001_model_has_roles`

Esta tabla vincula **usuarios** con **roles** en **sucursales específicas**.

**Estructura:**
```sql
CREATE TABLE 000001_model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(191) NOT NULL,  -- Tipo de modelo (User, Team, etc.)
    model_id BIGINT UNSIGNED NOT NULL,  -- ID del usuario
    sucursal_id BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = TODAS, >0 = sucursal específica
    PRIMARY KEY (role_id, model_type, model_id, sucursal_id)
);
```

**Ejemplo de datos:**
```
| role_id | model_type       | model_id | sucursal_id | Significado                          |
|---------|------------------|----------|-------------|--------------------------------------|
| 1       | App\Models\User  | 1        | 0           | admin1 es Super Admin en TODAS       |
| 4       | App\Models\User  | 2        | 1           | vendedor1 es Vendedor en Casa Central|
| 4       | App\Models\User  | 2        | 2           | vendedor1 es Vendedor en Suc. Norte  |
| 4       | App\Models\User  | 3        | 3           | vendedor2 es Vendedor en Suc. Sur    |
```

---

## 🔑 Sistema Spatie Permission

### ¿Qué es Spatie Permission?

Es un paquete de Laravel que gestiona **roles y permisos**. Tu sistema lo usa para controlar el acceso al menú y funcionalidades.

### Tablas Principales

#### 1. `permissions` (sin prefijo de comercio)
Almacena los permisos del sistema:
```
| id | name               | guard_name |
|----|-------------------|------------|
| 1  | menu.ventas       | web        |
| 2  | menu.nueva-venta  | web        |
| 3  | menu.articulos    | web        |
```

#### 2. `000001_model_has_roles` (con prefijo por comercio)
**ESTA ES LA TABLA CLAVE PARA SUCURSALES**

Vincula: Usuario + Rol + Sucursal

**¿Para qué sirve `model_type`?**
Permite que el sistema funcione con cualquier modelo, no solo `User`:
- `App\Models\User` → Usuarios
- `App\Models\Team` → Equipos (si existiera)
- `App\Models\Company` → Empresas (si existiera)

En tu sistema **solo usas User**, pero Spatie lo diseñó así para ser flexible.

#### 3. `000001_model_has_permissions` (con prefijo por comercio)
Permite asignar permisos **directamente a usuarios** sin pasar por roles.

**Tu sistema NO usa esto**, porque usas:
```
Usuario → Rol → Permisos ✅
```
NO:
```
Usuario → Permisos directos ❌
```

Por eso esta tabla está vacía.

#### 4. `000001_role_has_permissions` (con prefijo por comercio)
Vincula roles con permisos:
```
Rol "Vendedor" → Permisos: [menu.ventas, menu.nueva-venta, menu.listado-ventas]
Rol "Super Administrador" → Permisos: [TODOS]
```

---

## 🔄 Flujo de Login y Acceso

### Paso 1: Login
```
1. Usuario ingresa: demo1@gmail.com (email del comercio)
2. Usuario ingresa: vendedor1 (username)
3. Usuario ingresa: password
4. Sistema valida credenciales
5. Sistema autentica al usuario
6. Sistema establece comercio activo en sesión
7. Sistema establece sucursal por defecto en sesión ← NUEVO
```

### Paso 2: Establecer Sucursal por Defecto
```php
// En LoginForm::establecerSucursalPorDefecto()
$sucursalesDisponibles = SucursalService::getSucursalesDisponibles();
// Para vendedor1: retorna [Casa Central, Sucursal Norte]
// Para admin1: retorna [Casa Central, Sucursal Norte, Sucursal Sur]

$sucursalPorDefecto = $sucursalesDisponibles->first();
// Establece la primera (principal primero)
session(['sucursal_id' => $sucursalPorDefecto->id]);
```

### Paso 3: Cargar Roles
```php
// En User::roles()
// Si hay sucursal activa (1):
SELECT role_id FROM model_has_roles
WHERE model_id = 2
  AND (sucursal_id = 0 OR sucursal_id = 1)
// Retorna: [4] (Vendedor)

// Si NO hay sucursal activa (fallback):
SELECT role_id FROM model_has_roles
WHERE model_id = 2
// Retorna: [4] (todos los roles del usuario)
```

### Paso 4: Cargar Permisos
```php
// En User::hasPermissionTo()
$roles = $user->roles(); // [Vendedor]
// Busca permisos del rol Vendedor
// Retorna: menu.ventas, menu.nueva-venta, etc.
```

### Paso 5: Cargar Menú
```php
// En User::getAllowedMenuItems()
$userPermissions = $user->loadAllPermissions();
// Filtra items del menú que coincidan con los permisos
// Muestra solo items permitidos
```

---

## 🎨 Selector de Sucursales

### Cuándo se Muestra

**Se muestra SI:** Usuario tiene acceso a 2 o más sucursales
**NO se muestra SI:** Usuario tiene acceso a solo 1 sucursal

```php
// En sucursal-selector.blade.php:2
@if($sucursalesDisponibles && $sucursalesDisponibles->count() > 1)
    <!-- Mostrar dropdown -->
@elseif($sucursalActual)
    <!-- Mostrar solo nombre de sucursal (sin dropdown) -->
@endif
```

### Casos de Uso

**vendedor1:**
- Tiene acceso a 2 sucursales
- ✅ VE el dropdown con [Casa Central, Sucursal Norte]
- ✅ PUEDE cambiar entre ellas

**vendedor2 (hipotético con 1 sucursal):**
- Tiene acceso a 1 sucursal
- ❌ NO VE el dropdown
- ✅ VE el nombre de la sucursal (sin poder cambiar)

**admin1:**
- Tiene acceso a 3 sucursales (todas)
- ✅ VE el dropdown con [Casa Central, Sucursal Norte, Sucursal Sur]
- ✅ PUEDE cambiar entre todas

---

## 🔧 Cambios Realizados

### 1. Modificado `LoginForm::completeLogin()`
```php
// ANTES:
Auth::login($user, $this->remember);
$tenantService->setComercio($comercio);
// → No establecía sucursal, el menú no se cargaba

// AHORA:
Auth::login($user, $this->remember);
$tenantService->setComercio($comercio);
$this->establecerSucursalPorDefecto($user); // ← NUEVO
// → Establece sucursal inmediatamente
```

### 2. Modificado `User::roles()`
```php
// ANTES:
// Solo obtenía roles sin filtrar por sucursal
$roleIds = DB::table('model_has_roles')
    ->where('model_id', $this->id)
    ->pluck('role_id');

// AHORA:
// Filtra por sucursal activa O retorna todos si no hay sucursal
if ($sucursalActiva) {
    $query->where(function($q) use ($sucursalActiva) {
        $q->where('sucursal_id', 0)
          ->orWhere('sucursal_id', $sucursalActiva);
    });
}
// Si no hay sucursal activa, retorna TODOS (fallback)
```

### 3. Modificado `SucursalService::getSucursalesDisponibles()`
```php
// AHORA maneja correctamente sucursal_id = 0
if (in_array(0, $sucursalIds)) {
    // Retorna TODAS las sucursales
    return Sucursal::where('activa', true)->get();
}

// Si no, retorna solo las específicas
return Sucursal::whereIn('id', $sucursalIds)->get();
```

---

## 🧪 Cómo Probar

### Prueba 1: Login con vendedor1
```
1. Ir a /login
2. Email comercio: demo1@gmail.com
3. Username: vendedor1
4. Password: [la que tengas configurada]
5. Verificar:
   ✅ Se carga el menú con opciones de Vendedor
   ✅ Se muestra el selector de sucursales
   ✅ El selector tiene 2 opciones: Casa Central y Sucursal Norte
   ✅ Por defecto está seleccionada "Casa Central"
```

### Prueba 2: Cambiar de sucursal
```
1. Estando como vendedor1
2. Click en selector de sucursales
3. Seleccionar "Sucursal Norte"
4. Verificar:
   ✅ Se recarga la página
   ✅ El menú sigue mostrándose correctamente
   ✅ El selector ahora muestra "Sucursal Norte" como activa
```

### Prueba 3: Login con admin1
```
1. Ir a /login
2. Email comercio: demo1@gmail.com
3. Username: admin1
4. Password: [la que tengas configurada]
5. Verificar:
   ✅ Se carga el menú completo de administrador
   ✅ Se muestra el selector de sucursales
   ✅ El selector tiene 3 opciones: Casa Central, Norte y Sur
```

---

## 📊 Estado Actual de la Base de Datos

### Usuarios y Sucursales

**admin1 (ID: 1):**
```
| role_id | rol              | sucursal_id | sucursal       |
|---------|------------------|-------------|----------------|
| 1       | Super Admin      | 0           | TODAS          |
```

**vendedor1 (ID: 2):**
```
| role_id | rol              | sucursal_id | sucursal       |
|---------|------------------|-------------|----------------|
| 4       | Vendedor         | 1           | Casa Central   |
| 4       | Vendedor         | 2           | Sucursal Norte |
```

---

## 🎓 Conceptos Clave Finales

### 1. Acceso a Sucursales = Por Usuario
Cada usuario tiene su lista de sucursales permitidas en `model_has_roles`.

### 2. Permisos = Por Rol
Los permisos (qué puede hacer) vienen del ROL, no de la sucursal.

### 3. Combinación Usuario + Rol + Sucursal
```
vendedor1 + Rol Vendedor + Sucursal 1
vendedor1 + Rol Vendedor + Sucursal 2

Esto significa:
- vendedor1 puede trabajar en Sucursal 1 con permisos de Vendedor
- vendedor1 puede trabajar en Sucursal 2 con permisos de Vendedor
- vendedor1 NO puede trabajar en Sucursal 3 (no tiene acceso)
```

### 4. sucursal_id = 0 → Súper Poder
Cuando un usuario tiene `sucursal_id = 0` en algún registro, tiene acceso a TODAS las sucursales del comercio.

---

## 🚨 Preguntas Frecuentes

### ¿Por qué no usar una tabla separada para sucursales?
Porque Spatie Permission ya tiene `model_has_roles` que es perfecta para vincular Usuario + Rol + Contexto (en este caso, la sucursal).

### ¿Qué pasa si un usuario no tiene acceso a ninguna sucursal?
El sistema no le mostrará el menú y probablemente necesite ser configurado por un administrador.

### ¿Puedo darle a un usuario diferentes roles en diferentes sucursales?
¡SÍ! Por ejemplo:
```
Usuario Juan:
- Gerente en Sucursal 1 (role_id = 3, sucursal_id = 1)
- Vendedor en Sucursal 2 (role_id = 4, sucursal_id = 2)
```

### ¿Cómo asigno sucursales a un usuario?
Insertando registros en `model_has_roles`:
```sql
INSERT INTO 000001_model_has_roles
(role_id, model_type, model_id, sucursal_id)
VALUES
(4, 'App\\Models\\User', 2, 1),  -- Vendedor en sucursal 1
(4, 'App\\Models\\User', 2, 2);  -- Vendedor en sucursal 2
```

---

## 📝 Notas de Desarrollo

- La tabla `model_has_permissions` existe pero NO se usa en este sistema
- El campo `model_type` permite flexibilidad futura (equipos, departamentos, etc.)
- El flujo de login establece automáticamente la primera sucursal disponible
- El selector solo se muestra si hay 2+ sucursales disponibles
- Los permisos se cachean en `loadAllPermissions()` para optimizar rendimiento

---

**FIN DEL DOCUMENTO**
