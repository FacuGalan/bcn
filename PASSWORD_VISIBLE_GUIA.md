# Guía de Uso - Contraseñas Visibles (Cifradas)

## ⚠️ NOTA DE SEGURIDAD IMPORTANTE

Las contraseñas visibles están **cifradas con Laravel encryption**, NO están en texto plano. Solo pueden ser descifradas usando la clave `APP_KEY` de tu aplicación.

**Advertencias:**
- Si cambias `APP_KEY`, las contraseñas cifradas NO podrán ser descifradas
- Mantén seguro tu `.env` y tu `APP_KEY`
- Limita el acceso a esta funcionalidad solo a administradores autorizados
- Considera registrar en logs cada vez que se visualice una contraseña

---

## 📖 Cómo Funciona

### Campo en Base de Datos

```sql
users
- password (hash bcrypt - NO descifrable)
- password_visible (texto cifrado - SÍ descifrable)
```

### Cifrado Automático

El sistema cifra automáticamente la contraseña en dos momentos:

1. **Durante el registro de usuarios** (en seeders):
```php
User::create([
    'username' => 'admin',
    'password' => Hash::make('password'),
    'password_visible' => encrypt('password'), // ← Aquí
]);
```

2. **Durante el login** (si no existe password_visible):
```php
// En LoginForm, después de autenticar
if (!$user->hasPasswordVisible()) {
    $user->setPasswordVisible($this->password);
    $user->save();
}
```

---

## 🔧 Métodos Disponibles en el Modelo User

### 1. Establecer Contraseña Visible

```php
$user = User::find(1);
$user->setPasswordVisible('mi_contraseña_123');
$user->save();
```

### 2. Obtener Contraseña Descifrada

```php
$user = User::find(1);
$passwordPlain = $user->getPasswordVisible();

echo $passwordPlain; // "mi_contraseña_123"
```

### 3. Verificar si Tiene Contraseña Visible

```php
$user = User::find(1);

if ($user->hasPasswordVisible()) {
    echo "Este usuario tiene contraseña visible configurada";
}
```

---

## 💻 Ejemplos de Uso

### Ejemplo 1: Ver Contraseña desde Tinker

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('username', 'admin')->first();
echo $user->getPasswordVisible();
// Output: password
```

### Ejemplo 2: Ver Contraseña en un Controlador

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserPasswordController extends Controller
{
    /**
     * Muestra la contraseña de un usuario (solo para administradores)
     */
    public function showPassword(User $user)
    {
        // Verificar que el usuario autenticado es administrador
        if (!auth()->user()->isAdmin()) {
            abort(403, 'No autorizado');
        }

        // Obtener contraseña descifrada
        $passwordPlain = $user->getPasswordVisible();

        if (!$passwordPlain) {
            return response()->json([
                'error' => 'Este usuario no tiene contraseña visible configurada'
            ], 404);
        }

        // IMPORTANTE: Registrar en logs quien vio la contraseña
        \Log::warning('Password viewed', [
            'viewed_user' => $user->username,
            'by_user' => auth()->user()->username,
            'ip' => request()->ip(),
            'timestamp' => now()
        ]);

        return response()->json([
            'username' => $user->username,
            'password' => $passwordPlain
        ]);
    }
}
```

### Ejemplo 3: Ver Contraseñas de Todos los Usuarios

```php
use App\Models\User;

// Obtener todos los usuarios con sus contraseñas
$users = User::all();

foreach ($users as $user) {
    echo "{$user->username}: {$user->getPasswordVisible()}\n";
}

// Output:
// admin: password
// user1: password
// multiuser: password
```

### Ejemplo 4: Componente Livewire para Administradores

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class UserPasswordViewer extends Component
{
    public $userId;
    public $showPassword = false;
    public $password = null;

    public function viewPassword()
    {
        // Verificar permisos
        if (!auth()->user()->can('view-passwords')) {
            $this->addError('permission', 'No tienes permiso para ver contraseñas');
            return;
        }

        $user = User::find($this->userId);

        if (!$user) {
            $this->addError('user', 'Usuario no encontrado');
            return;
        }

        $this->password = $user->getPasswordVisible();
        $this->showPassword = true;

        // Registrar en logs
        \Log::warning('Password viewed via Livewire', [
            'viewed_user_id' => $user->id,
            'viewed_username' => $user->username,
            'by_user_id' => auth()->id(),
            'by_username' => auth()->user()->username,
        ]);
    }

    public function render()
    {
        return view('livewire.user-password-viewer');
    }
}
```

---

## 🛡️ Recomendaciones de Seguridad

### 1. Limitar Acceso con Policies

```php
// app/Policies/UserPolicy.php

public function viewPassword(User $authUser, User $targetUser): bool
{
    // Solo super administradores pueden ver contraseñas
    return $authUser->hasRole('Super Admin');
}
```

### 2. Registrar en Logs Cada Visualización

```php
\Log::channel('security')->warning('Password viewed', [
    'viewed_user_id' => $user->id,
    'by_user_id' => auth()->id(),
    'ip' => request()->ip(),
    'timestamp' => now(),
    'user_agent' => request()->userAgent()
]);
```

### 3. Notificar por Email al Usuario

```php
// Cuando alguien ve la contraseña, enviar email al usuario
Mail::to($user->email)->send(
    new PasswordViewedNotification($user, auth()->user())
);
```

### 4. Implementar Autorización en Rutas

```php
// routes/web.php
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/users/{user}/password', [UserPasswordController::class, 'show'])
        ->middleware('can:viewPassword,user')
        ->name('users.password.show');
});
```

---

## 🔐 Verificación de Usuarios Actuales

Para ver las contraseñas de los usuarios de prueba:

```bash
php artisan tinker
```

```php
use App\Models\User;

User::all()->each(function($user) {
    echo sprintf(
        "Username: %-15s | Password: %s\n",
        $user->username,
        $user->getPasswordVisible()
    );
});
```

**Output esperado:**
```
Username: admin           | Password: password
Username: user1           | Password: password
Username: multiuser       | Password: password
```

---

## 📝 Actualizar Contraseña Visible Manualmente

Si necesitas actualizar la contraseña visible de un usuario:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('username', 'admin')->first();

// Actualizar ambas contraseñas
$newPassword = 'nueva_contraseña_123';

$user->password = Hash::make($newPassword);
$user->setPasswordVisible($newPassword);
$user->save();

echo "Contraseña actualizada para: " . $user->username;
```

---

## ❓ Preguntas Frecuentes

### ¿Qué pasa si cambio APP_KEY?

Las contraseñas cifradas NO podrán ser descifradas. Deberás:
1. Hacer que los usuarios se logueen nuevamente
2. El sistema actualizará automáticamente `password_visible` con la nueva clave

### ¿Puedo ver la contraseña desde MySQL directamente?

No, verás texto cifrado. Ejemplo:

```sql
SELECT username, password_visible FROM users;
```

Output:
```
| username  | password_visible                                    |
|-----------|-----------------------------------------------------|
| admin     | eyJpdiI6IlVRQ0pSb... (texto cifrado largo)       |
```

### ¿Es seguro esto?

Es **MÁS seguro que texto plano** pero **MENOS seguro que solo hash**.

Recomendaciones:
- Solo usar cuando sea absolutamente necesario
- Limitar acceso a super administradores
- Registrar cada visualización
- Considerar políticas de rotación de contraseñas

### ¿Cómo desactivo password_visible para un usuario?

```php
$user = User::find(1);
$user->password_visible = null;
$user->save();
```

---

## 📊 Estructura de la Implementación

```
┌─────────────────────────────────────────┐
│  LoginForm                              │
│  - authenticate()                       │
│  - Guarda password_visible si no existe │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  User Model                             │
│  - setPasswordVisible($plain)           │
│  - getPasswordVisible(): ?string        │
│  - hasPasswordVisible(): bool           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Laravel Encryption                     │
│  - encrypt($value)                      │
│  - decrypt($value)                      │
│  - Usa APP_KEY del .env                 │
└─────────────────────────────────────────┘
```

---

## ✅ Estado Actual

- ✅ Campo `password_visible` agregado a tabla `users`
- ✅ Métodos de cifrado/descifrado implementados
- ✅ Actualización automática en login
- ✅ Todos los usuarios de prueba tienen password_visible
- ✅ Documentación completa con PHPDoc

---

**Fecha de Implementación:** 2025-11-03

**Versión:** 1.0.0
