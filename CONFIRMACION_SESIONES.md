# Sistema de Confirmación de Sesiones Concurrentes (Con Selección Manual)

## 📋 Descripción

Cuando un usuario intenta iniciar sesión y ya ha alcanzado su límite de sesiones simultáneas, el sistema muestra un **modal de confirmación interactivo** que permite al usuario **seleccionar manualmente qué sesiones cerrar** antes de completar el login.

## 🎯 Flujo de Usuario

### Escenario 1: Sin Límite Alcanzado
1. Usuario ingresa credenciales
2. Click en "Log in"
3. Login exitoso → Redirige al dashboard

### Escenario 2: Límite Alcanzado (CON SELECCIÓN MANUAL)
1. Usuario ingresa credenciales
2. Click en "Log in"
3. **Sistema detecta que se alcanzó el límite**
4. **Se muestra modal de confirmación con:**
   - Mensaje de advertencia
   - Límite máximo de sesiones
   - **Cantidad mínima de sesiones que debe cerrar**
   - **Lista de TODAS las sesiones activas con checkboxes:**
     - Navegador detectado (Chrome, Firefox, Edge, etc.)
     - Sistema operativo (Windows, macOS, Linux, etc.)
     - Dirección IP
     - Última actividad (tiempo relativo)
     - Badge "Esta sesión" para la sesión actual (no cerrable)
   - Contador dinámico: "Seleccionadas: X / Mínimo requerido: Y"
5. **Usuario selecciona qué sesiones cerrar** (mínimo requerido)
6. Usuario tiene 3 opciones:
   - **"Continuar e Ingresar"** → Cierra SOLO las sesiones seleccionadas y completa el login
   - **"Cancelar"** → No hace login, mantiene todas las sesiones existentes
   - Si no selecciona suficientes → Muestra mensaje de error en rojo

## 🖼️ Interfaz del Modal (Nueva Versión)

```
┌────────────────────────────────────────────────────────┐
│  ⚠️  Límite de sesiones alcanzado                      │
│                                                         │
│  Has alcanzado el límite máximo de 1 sesión simultánea.│
│  Debes seleccionar al menos 1 sesión para cerrar:      │
│                                                         │
│  Tus sesiones activas (2):                             │
│  ┌───────────────────────────────────────────────┐    │
│  │ ☑ 💻 Chrome - Windows                         │    │
│  │   🌐 IP: 192.168.1.100 • 🕐 hace 5 minutos   │    │
│  ├───────────────────────────────────────────────┤    │
│  │ ☐ 💻 Firefox - Windows [Esta sesión]         │    │
│  │   🌐 IP: 192.168.1.100 • 🕐 hace 1 minuto    │    │
│  └───────────────────────────────────────────────┘    │
│                                                         │
│  Nota: Seleccionadas: 1 / Mínimo requerido: 1          │
│                                                         │
│          [Cancelar]  [Continuar e Ingresar]            │
└────────────────────────────────────────────────────────┘
```

### Con Error de Validación:

```
┌────────────────────────────────────────────────────────┐
│  ⚠️  Límite de sesiones alcanzado                      │
│                                                         │
│  Has alcanzado el límite máximo de 3 sesiones.         │
│  Debes seleccionar al menos 2 sesiones para cerrar:    │
│                                                         │
│  ⚠️ Debes seleccionar al menos 2 sesiones para cerrar. │
│                                                         │
│  Tus sesiones activas (5):                             │
│  ┌───────────────────────────────────────────────┐    │
│  │ ☑ 💻 Chrome - Windows                         │    │
│  │   🌐 IP: 192.168.1.50 • 🕐 hace 2 horas      │    │
│  ├───────────────────────────────────────────────┤    │
│  │ ☐ 💻 Firefox - macOS                          │    │
│  │   🌐 IP: 192.168.1.100 • 🕐 hace 30 minutos  │    │
│  ├───────────────────────────────────────────────┤    │
│  │ ☐ 💻 Edge - Windows                           │    │
│  │   🌐 IP: 192.168.1.75 • 🕐 hace 1 hora       │    │
│  ├───────────────────────────────────────────────┤    │
│  │ ☐ 💻 Safari - iOS                             │    │
│  │   🌐 IP: 192.168.1.200 • 🕐 hace 3 horas     │    │
│  ├───────────────────────────────────────────────┤    │
│  │ ☐ 💻 Chrome - Android [Esta sesión]          │    │
│  │   🌐 IP: 192.168.1.201 • 🕐 hace 1 minuto    │    │
│  └───────────────────────────────────────────────┘    │
│                                                         │
│  Nota: Seleccionadas: 1 / Mínimo requerido: 2          │
│                                                         │
│          [Cancelar]  [Continuar e Ingresar]            │
└────────────────────────────────────────────────────────┘
```

## 💻 Implementación Técnica

### Archivos Modificados

#### 1. `app/Livewire/Forms/LoginForm.php`

**Almacenamiento temporal usando sesión:**
```php
protected const SESSION_VALIDATED_USER_ID = 'login_validation.user_id';
protected const SESSION_VALIDATED_COMERCIO_ID = 'login_validation.comercio_id';
```

**Método `authenticate()` modificado:**
- Retorna un array con información sobre si necesita confirmación
- Guarda IDs validados en sesión (persisten entre requests de Livewire)
- Detecta si se alcanzó el límite ANTES de hacer login
- Si hay límite, retorna toda la información de sesiones activas
- Si no hay límite, completa el login directamente

**Método `completeLogin()` mejorado:**
```php
public function completeLogin(array $selectedSessionIds = []): array
```
- Acepta parámetro opcional con IDs de sesiones a cerrar
- Si recibe IDs, cierra solo esas sesiones específicas
- Si no recibe IDs, cierra las más antiguas automáticamente
- Recupera datos validados desde la sesión

**Método `cancelLogin()` actualizado:**
```php
public function cancelLogin(): void
```
- Limpia datos temporales de la sesión

#### 2. `resources/views/livewire/pages/auth/login.blade.php`

**Nuevas propiedades en el componente Volt:**
```php
public bool $showConfirmationModal = false;
public int $sessionsToClose = 0;
public array $sessionsInfo = [];
public int $maxSessions = 1;
public array $selectedSessions = [];  // NUEVO
public string $selectionError = '';   // NUEVO
```

**Método `confirmLogin()` con validación:**
```php
public function confirmLogin(): void
{
    // Validar que se hayan seleccionado suficientes sesiones
    if (count($this->selectedSessions) < $this->sessionsToClose) {
        $this->selectionError = "Debes seleccionar al menos...";
        return;
    }

    // Pasar sesiones seleccionadas al LoginForm
    $this->form->completeLogin($this->selectedSessions);
    ...
}
```

**UI mejorada con checkboxes:**
- Modal interactivo con checkboxes para cada sesión
- Badge "Esta sesión" para la sesión actual
- Contador dinámico de sesiones seleccionadas
- Mensaje de error de validación en rojo
- Hover effects para mejorar UX
- Lista scrolleable con max-height

#### 3. `app/Services/SessionManagerService.php`

**Nuevo método agregado:**
```php
public function closeSpecificSessions(array $sessionIds): int
{
    if (empty($sessionIds)) {
        return 0;
    }

    return DB::connection('config')
        ->table('sessions')
        ->whereIn('id', $sessionIds)
        ->delete();
}
```

**Método existente `getSessionsInfo()`** proporciona:
- ID de sesión (para los checkboxes)
- IP address
- User agent parseado (navegador y plataforma)
- Última actividad (timestamp y formato humano)
- Indicador `is_current` para la sesión actual

## 🧪 Cómo Probar

### Preparación
```bash
# 1. Asegurarse de que no hay sesiones activas
php artisan tinker
DB::connection('config')->table('sessions')->truncate();
exit
```

### Prueba con user1 (límite: 1 sesión)

1. **Primera sesión** (debe funcionar normalmente):
   - Abrir Chrome
   - Ir a http://localhost/bcn_pymes/public/login
   - Ingresar:
     - Email Comercio: `comercio1@bcnpymes.com`
     - Username: `user1`
     - Password: `password`
   - Click "Log in"
   - ✅ Debe ingresar al dashboard sin modal

2. **Segunda sesión** (debe mostrar modal):
   - **SIN CERRAR CHROME**, abrir Firefox (o modo incógnito)
   - Ir a http://localhost/bcn_pymes/public/login
   - Ingresar las mismas credenciales
   - Click "Log in"
   - ⚠️ **Debe aparecer el modal** con:
     - Mensaje: "Has alcanzado el límite máximo de 1 sesión simultánea"
     - Información de la sesión de Chrome que se cerrará
     - Navegador, IP, y última actividad

3. **Opciones en el modal**:

   **Opción A: Cancelar**
   - Click en "Cancelar"
   - Modal se cierra
   - No se hace login
   - Sesión de Chrome sigue activa

   **Opción B: Continuar**
   - Click en "Continuar e Ingresar"
   - Modal se cierra
   - Se cierra automáticamente la sesión de Chrome
   - Firefox ingresa al dashboard
   - Si vuelves a Chrome, estás deslogueado

### Prueba con admin (límite: 5 sesiones)

```
Email Comercio: comercio1@bcnpymes.com
Username: admin
Password: password
```

Repite el proceso abriendo hasta 6 navegadores diferentes. El modal aparecerá en el 6to intento.

### Prueba con multiuser (límite: 3 sesiones)

```
Email Comercio: comercio1@bcnpymes.com
Username: multiuser
Password: password
```

El modal aparecerá en el 4to intento de login.

## 🔍 Verificar en Base de Datos

```sql
-- Ver sesiones activas
USE config;
SELECT
    id,
    user_id,
    ip_address,
    FROM_UNIXTIME(last_activity) as last_activity_time
FROM sessions
WHERE user_id = 2  -- user1
ORDER BY last_activity DESC;
```

## 📊 Comportamiento Esperado

| Usuario   | Límite | Comportamiento |
|-----------|--------|----------------|
| user1     | 1      | Modal aparece en el 2do login |
| multiuser | 3      | Modal aparece en el 4to login |
| admin     | 5      | Modal aparece en el 6to login |

## ⚙️ Configuración

Para cambiar el límite de sesiones de un usuario:

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('username', 'user1')->first();
$user->max_concurrent_sessions = 3;
$user->save();
```

## 🎨 Personalización del Modal

El modal usa **Tailwind CSS** y está completamente personalizable:

**Colores:**
- Fondo overlay: `bg-gray-500 bg-opacity-75`
- Icono: `bg-yellow-100` con `text-yellow-600`
- Botón continuar: `bg-red-600 hover:bg-red-700`
- Botón cancelar: `bg-white border-gray-300`

**Ubicación en código:**
`resources/views/livewire/pages/auth/login.blade.php:152-232`

## 🔒 Seguridad

- Las credenciales se validan ANTES de mostrar el modal
- Solo usuarios autorizados ven información de sus propias sesiones
- La información de sesiones es descriptiva pero no sensible
- El modal no muestra IDs de sesión completos

## 📝 Notas Importantes

1. **No se hace login hasta confirmar**: El usuario NO está autenticado hasta que click en "Continuar"
2. **Sesiones más antiguas se cierran primero**: Ordenadas por `last_activity`
3. **Sesión actual protegida**: Nunca se cierra la sesión desde donde se está intentando loguear
4. **Información en tiempo real**: Los datos de sesiones se obtienen en el momento del login

## 🐛 Troubleshooting

### El modal no aparece
- Verificar que `SESSION_CONNECTION=config` en `.env`
- Verificar que hay sesiones activas en la BD config
- Verificar que el usuario tiene límite configurado

### Las sesiones no se cierran
- Verificar que `SessionManagerService` usa `DB::connection('config')`
- Limpiar cache: `php artisan config:clear`

### El modal aparece pero no muestra sesiones
- Verificar que `getSessionsInfo()` retorna datos
- Verificar que las sesiones tienen `user_agent` e `ip_address`

## ✅ Estado de Implementación

- ✅ LoginForm modificado con lógica de confirmación
- ✅ Métodos completeLogin() y cancelLogin() implementados
- ✅ Modal de confirmación con diseño profesional
- ✅ Lista detallada de sesiones a cerrar
- ✅ Detección de navegador y plataforma
- ✅ Formato de tiempo relativo (ej: "hace 5 minutos")
- ✅ Responsive design (mobile y desktop)
- ✅ Documentación completa

---

**Fecha de Implementación:** 2025-11-03
**Versión:** 2.0.0
