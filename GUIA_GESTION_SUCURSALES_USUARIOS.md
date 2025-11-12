# Guía: Gestión de Sucursales por Usuario

**Fecha:** 2025-11-10
**Versión:** 1.0.0
**Módulo:** Configuración → Usuarios

---

## 📋 Descripción

Esta funcionalidad permite a los **Super Administradores** asignar sucursales específicas a cada usuario del sistema. Esto determina a qué sucursales puede acceder cada usuario cuando inicie sesión.

---

## 👥 Permisos Requeridos

**Solo Super Administradores** pueden:
- Ver el selector de sucursales al editar usuarios
- Asignar/modificar sucursales de usuarios
- Ver la contraseña visible de los usuarios

**Otros roles** (Administrador, Gerente, Vendedor, etc.):
- NO ven el selector de sucursales
- Los usuarios que crean/editan tendrán acceso a TODAS las sucursales por defecto

---

## 🎯 Cómo Usar

### 1. Acceder a Gestión de Usuarios

```
1. Iniciar sesión como Super Admin
2. Ir a: Configuración → Usuarios
3. Click en "Editar" en el usuario deseado
```

### 2. Asignar Sucursales

En el modal de edición, si eres Super Admin verás:

```
┌─────────────────────────────────────┐
│  Sucursales con Acceso              │
├─────────────────────────────────────┤
│  ℹ️ Selecciona las sucursales a las │
│     que tendrá acceso este usuario. │
│     Si no seleccionas ninguna,      │
│     tendrá acceso a todas.          │
│                                     │
│  ☐ Casa Central (CENTRAL) Principal │
│  ☐ Sucursal Norte (NORTE)           │
│  ☐ Sucursal Sur (SUR)               │
│                                     │
│  ℹ️ Acceso a todas las sucursales   │
└─────────────────────────────────────┘
```

### 3. Opciones de Configuración

#### Opción A: Acceso a TODAS las Sucursales
- **NO** seleccionar ninguna sucursal
- El usuario podrá trabajar en cualquier sucursal del comercio
- Útil para: Super Admins, Gerentes Generales

**Resultado en BD:**
```sql
| role_id | model_id | sucursal_id |
|---------|----------|-------------|
| 4       | 2        | 0           | ← 0 = TODAS
```

#### Opción B: Acceso a Sucursales Específicas
- **Seleccionar** las sucursales deseadas (una o más)
- El usuario solo podrá trabajar en las sucursales seleccionadas
- Útil para: Vendedores, Cajeros asignados a sucursales específicas

**Resultado en BD:**
```sql
| role_id | model_id | sucursal_id |
|---------|----------|-------------|
| 4       | 2        | 1           | ← Casa Central
| 4       | 2        | 2           | ← Sucursal Norte
```

---

## 📊 Ejemplos de Uso

### Ejemplo 1: Vendedor con Acceso a 2 Sucursales

**Escenario:**
- Usuario: `vendedor1`
- Rol: Vendedor
- Necesita trabajar en: Casa Central y Sucursal Norte

**Pasos:**
1. Editar usuario `vendedor1`
2. Seleccionar rol: "Vendedor"
3. Marcar checkboxes:
   - ✅ Casa Central
   - ✅ Sucursal Norte
   - ☐ Sucursal Sur
4. Guardar

**Resultado:**
- El usuario `vendedor1` verá un selector con 2 opciones al iniciar sesión
- Podrá cambiar entre Casa Central y Norte
- NO verá la Sucursal Sur en el selector

---

### Ejemplo 2: Gerente General con Acceso a Todas

**Escenario:**
- Usuario: `gerente_general`
- Rol: Gerente
- Necesita trabajar en: TODAS las sucursales

**Pasos:**
1. Editar usuario `gerente_general`
2. Seleccionar rol: "Gerente"
3. **NO** marcar ningún checkbox (dejar todos sin seleccionar)
4. Guardar

**Resultado:**
- El usuario `gerente_general` verá un selector con TODAS las sucursales
- Podrá cambiar a cualquier sucursal del comercio

---

### Ejemplo 3: Cajero de una Sucursal Específica

**Escenario:**
- Usuario: `cajero_norte`
- Rol: Cajero (hipotético)
- Necesita trabajar en: Solo Sucursal Norte

**Pasos:**
1. Editar usuario `cajero_norte`
2. Seleccionar rol: "Cajero"
3. Marcar checkbox:
   - ☐ Casa Central
   - ✅ Sucursal Norte
   - ☐ Sucursal Sur
4. Guardar

**Resultado:**
- El usuario `cajero_norte` iniciará sesión directamente en Sucursal Norte
- NO verá el selector de sucursales (solo tiene acceso a 1)
- Estará "bloqueado" a trabajar solo en esa sucursal

---

## 🔄 Flujo Completo

```
1. Super Admin edita usuario
   ↓
2. Selecciona rol del usuario
   ↓
3. Selecciona sucursales (o deja vacío para todas)
   ↓
4. Guarda cambios
   ↓
5. Sistema elimina asignaciones anteriores
   ↓
6. Sistema crea nuevas asignaciones en model_has_roles
   ↓
7. Usuario ve los cambios en su próximo login
```

---

## 💾 Estructura en Base de Datos

### Tabla: `000001_model_has_roles`

Almacena las asignaciones de roles y sucursales:

```sql
CREATE TABLE 000001_model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(191) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    sucursal_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (role_id, model_type, model_id, sucursal_id)
);
```

**Campos:**
- `role_id`: ID del rol (Vendedor, Gerente, etc.)
- `model_type`: Siempre `App\Models\User`
- `model_id`: ID del usuario
- `sucursal_id`: ID de la sucursal (0 = todas)

---

## 🎨 Interfaz de Usuario

### Vista Desktop

```
┌──────────────────────────────────────────────────────────┐
│  Editar Usuario                                          │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  Nombre completo *                                       │
│  [Juan Pérez                                       ]     │
│                                                          │
│  Nombre de usuario *                                     │
│  [jperez                                           ]     │
│                                                          │
│  Email *                                                 │
│  [jperez@example.com                               ]     │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ 🔑 Contraseña Actual del Usuario                  │ │
│  │ [password123               ] [Copiar]             │ │
│  │ 🔒 Visible solo para Super Administradores        │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Rol                                                     │
│  [Vendedor                     ▼]                       │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ 🏢 Sucursales con Acceso                          │ │
│  │ ℹ️ Selecciona las sucursales a las que tendrá    │ │
│  │    acceso este usuario. Si no seleccionas        │ │
│  │    ninguna, tendrá acceso a todas.               │ │
│  │                                                   │ │
│  │ ☑ Casa Central (CENTRAL) Principal               │ │
│  │ ☑ Sucursal Norte (NORTE)                         │ │
│  │ ☐ Sucursal Sur (SUR)                             │ │
│  │                                                   │ │
│  │ ✓ 2 sucursal(es) seleccionada(s)                 │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  ☑ Usuario activo                                       │
│                                                          │
│            [Cancelar]  [Actualizar]                     │
└──────────────────────────────────────────────────────────┘
```

### Indicador de Contador

El selector muestra un contador dinámico:
- **Si hay selecciones:** `✓ 2 sucursal(es) seleccionada(s)`
- **Si no hay selecciones:** `ℹ️ Acceso a todas las sucursales`

---

## ⚙️ Lógica de Guardado

### Código Simplificado

```php
// Determinar sucursales a asignar
if ($isSuperAdmin && !empty($selectedSucursales)) {
    // Super Admin seleccionó sucursales específicas
    $sucursalesToAssign = [1, 2]; // Ejemplo
} else {
    // No es Super Admin o no hay selección → TODAS
    $sucursalesToAssign = [0];
}

// Asignar el rol con las sucursales
foreach ($sucursalesToAssign as $sucursalId) {
    DB::insert([
        'role_id' => $roleId,
        'model_type' => 'App\\Models\\User',
        'model_id' => $userId,
        'sucursal_id' => $sucursalId,
    ]);
}
```

---

## 🔒 Seguridad

### Validación de Permisos

```php
// En mount()
$this->currentUserIsSuperAdmin = auth()->user()->hasRole('Super Administrador');

// En la vista
@if($currentUserIsSuperAdmin)
    <!-- Selector de sucursales -->
@endif
```

Solo los Super Admins ven y pueden modificar las sucursales.

### Protección en el Backend

```php
// En save()
if ($this->currentUserIsSuperAdmin && !empty($this->selectedSucursales)) {
    // Usar selección del Super Admin
} else {
    // Asignar todas (fallback seguro)
}
```

Si alguien intenta manipular el request, el sistema asignará todas las sucursales por defecto (comportamiento seguro).

---

## 🐛 Resolución de Problemas

### Problema: No veo el selector de sucursales

**Solución:**
- Verifica que estés autenticado como Super Administrador
- El selector solo aparece en el modal de edición de usuarios
- Si el usuario autenticado NO es Super Admin, no verá el selector

### Problema: Las sucursales no se guardan

**Verificar:**
1. Conexión a la base de datos correcta (`pymes_tenant`)
2. Prefijo de tabla correcto (ejemplo: `000001_`)
3. Que el campo `model_type` tenga el valor correcto: `App\Models\User`

**Query de verificación:**
```sql
SELECT * FROM 000001_model_has_roles WHERE model_id = 2;
```

### Problema: Usuario tiene acceso a todas aunque seleccioné algunas

**Causa posible:**
- Existe un registro con `sucursal_id = 0` para ese usuario

**Solución:**
```sql
-- Verificar registros
SELECT * FROM 000001_model_has_roles WHERE model_id = 2;

-- Si hay sucursal_id = 0, eliminarlo
DELETE FROM 000001_model_has_roles
WHERE model_id = 2 AND sucursal_id = 0;
```

---

## 📝 Notas Importantes

1. **Los cambios se aplican en el próximo login**: El usuario debe cerrar sesión y volver a entrar para ver los cambios

2. **Sin selección = Todas**: Si no seleccionas ninguna sucursal, el usuario tendrá acceso a todas

3. **Super Admin siempre ve todas**: Aunque asignes sucursales específicas a un Super Admin, seguirá viendo todas

4. **Compatibilidad con password_visible**: Usa la misma validación (`$currentUserIsSuperAdmin`)

5. **Una sola sucursal = Sin selector**: Si el usuario solo tiene acceso a 1 sucursal, no verá el selector al iniciar sesión

---

## 🔗 Referencias

- **Componente:** `app/Livewire/Configuracion/Usuarios.php`
- **Vista:** `resources/views/livewire/configuracion/usuarios.blade.php`
- **Sistema de sucursales:** `SISTEMA_ACCESO_SUCURSALES.md`
- **Problema resuelto:** `PROBLEMA_RESUELTO_SUCURSALES.md`

---

**FIN DEL DOCUMENTO**
