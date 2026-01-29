# BCN Pymes - Roadmap y Próximos Pasos

## 📋 Estado Actual del Proyecto

### ✅ Completado

**Stack Tecnológico Instalado:**
- Laravel 12.36.1
- PHP 8.2.12
- Composer 2.8.12
- Node.js 22.20.0 / NPM 11.6.2
- Livewire 3.6.4
- Livewire Volt 1.8.0
- Laravel Breeze (Livewire stack)
- Spatie Laravel Permission 6.22.0
- Tailwind CSS
- Vite

**Bases de Datos:**
- **config**: Almacena comercios, usuarios, sesiones y tablas del sistema
- **pymes**: Almacena datos de cada comercio con prefijo (000001_, 000002_, etc.)

**Arquitectura Multi-Tenant Implementada:**
- [x] Conexiones múltiples de base de datos (config y pymes_tenant)
- [x] Modelo `Comercio` con relaciones y métodos utilitarios
- [x] Modelo `User` modificado con campo `username` y relaciones many-to-many con comercios
- [x] Tabla pivot `user_comercio` para gestión multi-comercio
- [x] Servicio `TenantService` para gestionar comercio activo y conexión dinámica
- [x] Comando `php artisan comercio:init {id}` para inicializar tablas de nuevo comercio
- [x] Seeder con datos de prueba (2 comercios, 3 usuarios)
- [x] Tablas con prefijo creadas automáticamente (roles, permissions, artículos, ventas_encabezado)

**Credenciales de Prueba:**
```
Comercios:
- comercio1@bcnpymes.com (Comercio Demo 1)
- comercio2@bcnpymes.com (Comercio Demo 2)

Usuarios:
- Username: admin | Password: password (Acceso a ambos comercios)
- Username: user1 | Password: password (Solo Comercio 1)
- Username: multiuser | Password: password (Acceso a ambos comercios)
```

**Configuración:**
- Locale: Español (es)
- Migraciones ejecutadas en ambas bases de datos
- Git inicializado con commit inicial
- Servidor de desarrollo funcional en http://127.0.0.1:8000

**Funcionalidades Base:**
- Sistema de autenticación completo (login, registro, recuperación de contraseña) *[Pendiente: Adaptar a multi-tenant]*
- Dashboard
- Perfil de usuario
- Verificación de email
- Sistema de roles y permisos por comercio con tablas prefijadas

---

## 🏗️ Arquitectura Multi-Tenant

### Estructura de Bases de Datos

**Base `config`:**
```
- users (username, email, password)
- comercios (mail, nombre)
- user_comercio (pivot table)
- sessions, cache, jobs (sistema)
- migrations, permissions, roles (sistema global)
```

**Base `pymes`:**
```
Comercio 1 (ID: 1):
- 000001_roles
- 000001_permissions
- 000001_model_has_roles
- 000001_model_has_permissions
- 000001_role_has_permissions
- 000001_articulos
- 000001_ventas_encabezado
... (más tablas según necesidad)

Comercio 2 (ID: 2):
- 000002_roles
- 000002_permissions
... (misma estructura con prefijo 000002_)
```

### Flujo de Login Multi-Tenant (A Implementar)

1. Usuario ingresa:
   - Email del comercio: `comercio1@bcnpymes.com`
   - Username: `admin`
   - Password: `password`

2. Sistema busca comercio por email en `config.comercios`
3. Obtiene `comercio_id`
4. Busca usuario con username en `config.users`
5. Verifica en `user_comercio` que el usuario tiene acceso al comercio
6. Establece comercio activo en sesión con `TenantService`
7. Configura conexión `pymes_tenant` con prefijo del comercio
8. Redirecciona al dashboard del comercio

### Servicios y Comandos Disponibles

**TenantService:**
```php
app(TenantService::class)->setComercio($comercioId);
app(TenantService::class)->getComercio(); // Retorna Comercio actual
app(TenantService::class)->getTablePrefix(); // Retorna "000001_"
app(TenantService::class)->switchComercio($newComercioId, $userId);
```

**Comando de Inicialización:**
```bash
php artisan comercio:init 1  # Crea tablas para comercio ID 1
```

---

## 🚀 Próximos Pasos

### Fase 1: Configuración del Sistema de Permisos

**1.1 Crear Seeder de Roles y Permisos Iniciales**
- [ ] Crear archivo `database/seeders/RoleAndPermissionSeeder.php`
- [ ] Definir roles principales:
  - Super Admin (acceso total)
  - Administrador (gestión general)
  - Gerente (lectura y edición limitada)
  - Usuario (solo lectura)
- [ ] Definir permisos base:
  - Gestión de usuarios
  - Gestión de roles
  - Gestión de empresas/PYMEs
  - Visualización de reportes
  - Configuración del sistema
- [ ] Ejecutar seeder

**1.2 Crear Middleware de Permisos**
- [ ] Implementar middleware para verificar roles
- [ ] Implementar middleware para verificar permisos
- [ ] Aplicar middleware a rutas protegidas

**1.3 Crear Panel de Gestión de Usuarios y Roles**
- [ ] Crear componente Livewire para listar usuarios
- [ ] Crear componente Livewire para asignar roles
- [ ] Crear componente Livewire para gestionar permisos
- [ ] Agregar validaciones y notificaciones

---

### Fase 2: Sistema de Menús Dinámicos

**2.1 Diseñar Sistema de Menús**
- [ ] Crear modelo `Menu` con campos:
  - nombre, icono, ruta, orden, parent_id
  - roles permitidos (relación many-to-many)
- [ ] Crear migración para tabla `menus`
- [ ] Crear tabla pivot `menu_role`

**2.2 Implementar Componente de Navegación Dinámica**
- [ ] Crear componente Livewire para renderizar menú según rol
- [ ] Implementar lógica de menú jerárquico (menú y submenú)
- [ ] Aplicar estilos con Tailwind CSS
- [ ] Crear panel admin para gestionar menús

**2.3 Crear Seeder de Menús Iniciales**
- [ ] Dashboard
- [ ] Gestión de Usuarios (solo admin)
- [ ] Gestión de Empresas/PYMEs
- [ ] Reportes
- [ ] Configuración

---

### Fase 3: Estructura de Datos para PYMEs

**3.1 Definir Modelos y Relaciones**

**Modelo: Empresa/PYME**
- [ ] Crear migración y modelo `Company`
- Campos sugeridos:
  - nombre, razón social, NIF/CIF
  - dirección, teléfono, email
  - sector, tamaño (micro, pequeña, mediana)
  - fecha de alta, estado (activa/inactiva)
  - user_id (responsable asignado)

**Modelo: Contacto**
- [ ] Crear migración y modelo `Contact`
- Campos sugeridos:
  - nombre, cargo, email, teléfono
  - company_id (relación con empresa)

**Modelo: Perfil de Empresa (Información adicional)**
- [ ] Crear migración y modelo `CompanyProfile`
- Campos sugeridos:
  - facturación anual
  - número de empleados
  - descripción
  - sitio web
  - company_id

**3.2 Crear Factory y Seeders**
- [ ] Factory para generar empresas de prueba
- [ ] Factory para generar contactos de prueba
- [ ] Seeder con 20-30 empresas de ejemplo

**3.3 Crear Políticas de Acceso (Policies)**
- [ ] Policy para Company (quién puede ver/editar cada empresa)
- [ ] Policy para Contact
- [ ] Aplicar policies en controladores

---

### Fase 4: Componentes Livewire para PYMEs

**4.1 Listado de Empresas**
- [ ] Crear componente Livewire `CompanyList`
- [ ] Implementar búsqueda y filtros
- [ ] Implementar paginación
- [ ] Agregar acciones (ver, editar, eliminar)

**4.2 Formulario de Empresa**
- [ ] Crear componente Livewire `CompanyForm`
- [ ] Validaciones en tiempo real
- [ ] Subir logo/imagen (opcional)
- [ ] Guardar/actualizar empresa

**4.3 Vista Detalle de Empresa**
- [ ] Crear componente Livewire `CompanyDetail`
- [ ] Mostrar información completa
- [ ] Listado de contactos asociados
- [ ] Historial de actividad

**4.4 Gestión de Contactos**
- [ ] Crear componente Livewire `ContactList`
- [ ] Crear componente Livewire `ContactForm`
- [ ] Asociar contactos a empresas

---

### Fase 5: Dashboard y Reportes

**5.1 Dashboard Principal**
- [ ] Tarjetas con estadísticas:
  - Total de empresas
  - Empresas activas/inactivas
  - Nuevas empresas del mes
  - Distribución por sector
- [ ] Gráficos (usar Chart.js o ApexCharts)
- [ ] Filtros por fecha

**5.2 Sistema de Reportes**
- [ ] Reporte de empresas por sector
- [ ] Reporte de empresas por tamaño
- [ ] Exportación a Excel/PDF (Laravel Excel)

---

### Fase 6: Mejoras y Funcionalidades Avanzadas

**6.1 Sistema de Actividad/Logs**
- [ ] Instalar `spatie/laravel-activitylog`
- [ ] Registrar acciones importantes (crear, editar, eliminar)
- [ ] Panel de auditoría para admins

**6.2 Notificaciones**
- [ ] Notificaciones en tiempo real (Livewire polling)
- [ ] Emails automáticos para eventos importantes

**6.3 Búsqueda Avanzada**
- [ ] Implementar Laravel Scout para búsqueda full-text
- [ ] Búsqueda global en toda la aplicación

**6.4 Multiidioma (Opcional)**
- [ ] Configurar archivos de traducción es/ca
- [ ] Selector de idioma en la interfaz

---

## 🎯 Prioridades Recomendadas

### Primera sesión siguiente:
1. Crear seeder de roles y permisos
2. Crear modelos de Company y Contact
3. Crear componente Livewire para listar empresas

### Segunda sesión:
1. Implementar formulario de empresas
2. Sistema de menús dinámicos
3. Dashboard con estadísticas básicas

---

## 📝 Notas Técnicas

**Comandos Útiles:**
```bash
# Iniciar servidor
php artisan serve

# Crear migración
php artisan make:migration create_companies_table

# Crear modelo con migración, factory y seeder
php artisan make:model Company -mfs

# Crear componente Livewire
php artisan make:livewire CompanyList

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Compilar assets
npm run dev
```

**Estructura de Archivos:**
- Modelos: `app/Models/`
- Migraciones: `database/migrations/`
- Seeders: `database/seeders/`
- Componentes Livewire: `app/Livewire/`
- Vistas Livewire: `resources/views/livewire/`
- Políticas: `app/Policies/`

---

## 🔗 Recursos

- Laravel Docs: https://laravel.com/docs/12.x
- Livewire Docs: https://livewire.laravel.com/docs
- Spatie Permission: https://spatie.be/docs/laravel-permission
- Tailwind CSS: https://tailwindcss.com/docs

---

**Última actualización:** 2025-11-01
**Estado del servidor:** Corriendo en http://127.0.0.1:8000
