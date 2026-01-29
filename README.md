# BCN Pymes

Sistema de gestión multi-tenant para pequeñas y medianas empresas (PYMEs) construido con Laravel 11 y Livewire 3.

![Laravel](https://img.shields.io/badge/Laravel-11.x-red)
![Livewire](https://img.shields.io/badge/Livewire-3.x-purple)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
![License](https://img.shields.io/badge/License-Propietario-yellow)

---

## 📋 Tabla de Contenidos

- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Uso Inicial](#uso-inicial)
- [Documentación](#documentación)
- [Stack Tecnológico](#stack-tecnológico)
- [Arquitectura](#arquitectura)
- [Comandos Útiles](#comandos-útiles)
- [Contribución](#contribución)
- [Licencia](#licencia)

---

## ✨ Características

### Multi-Tenancy
- **Múltiples comercios** en una sola instalación
- **Aislamiento completo** de datos por comercio mediante tablas con prefijo
- **Cambio dinámico** entre comercios sin cerrar sesión

### Gestión de Usuarios
- **Multi-comercio:** Un usuario puede acceder a múltiples comercios
- **Roles y permisos** dinámicos por comercio
- **Control de sesiones concurrentes** por dispositivo
- **Contraseñas recuperables** (cifradas) para administradores

### Menú Dinámico
- **Generación automática** según permisos del usuario
- **Estructura jerárquica** de dos niveles
- **Responsive** con menú hamburguesa en móvil
- **Cacheado** para máximo rendimiento

### Optimizaciones
- ✅ Modales instantáneos con Alpine.js
- ✅ Eliminación de queries N+1
- ✅ Caché de permisos y menú (5 minutos)
- ✅ Eager loading automático

---

## 💻 Requisitos

### Obligatorios
- **PHP:** 8.2 o superior
- **Composer:** 2.x
- **Node.js:** 18.x o superior
- **NPM:** 9.x o superior
- **MySQL:** 8.0 o superior
- **Apache/Nginx** con mod_rewrite habilitado

### Extensiones PHP Requeridas
```
BCMath
Ctype
cURL
DOM
Fileinfo
JSON
Mbstring
OpenSSL
PDO
PDO MySQL
Tokenizer
XML
```

---

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone <repository-url> bcn_pymes
cd bcn_pymes
```

### 2. Instalar Dependencias
```bash
# Dependencias de PHP
composer install

# Dependencias de Node.js
npm install
```

### 3. Configurar el Entorno
```bash
# Copiar archivo de entorno
cp .env.example .env

# Generar key de la aplicación
php artisan key:generate
```

### 4. Editar `.env`

```env
APP_NAME="BCN Pymes"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Base de datos CONFIG (usuarios, comercios)
DB_CONNECTION=config
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=config
DB_USERNAME=root
DB_PASSWORD=

# Base de datos PYMES (datos de comercios)
DB_DATABASE_PYMES=pymes
DB_DATABASE_CONFIG=config

# Sesiones
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### 5. Crear las Bases de Datos

```sql
CREATE DATABASE config CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pymes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Ejecutar Migraciones
```bash
# Migrar base de datos CONFIG
php artisan migrate --database=config

# Migrar base de datos PYMES (tablas compartidas)
php artisan migrate --database=pymes
```

### 7. Compilar Assets
```bash
# Desarrollo (con watch)
npm run dev

# O para producción
npm run build
```

---

## ⚙️ Configuración

### Crear Primer Comercio y Usuario

```bash
# 1. Crear comercio y usuario de prueba
php artisan db:seed --class=ComercioUserSeeder

# Esto crea:
# - Comercio ID 1 (comercio1@test.com)
# - Usuario admin (admin@test.com / password)

# 2. Inicializar tablas del comercio
php artisan comercio:init 1

# 3. Poblar menú y permisos
php artisan comercio:seed-menu 1
```

### Credenciales por Defecto

```
Email: admin@test.com
Password: password

Comercio: comercio1@test.com
```

**⚠️ IMPORTANTE:** Cambiar estas credenciales en producción.

---

## 🎯 Uso Inicial

### 1. Iniciar el Servidor
```bash
php artisan serve
```

Visitar: http://localhost:8000

### 2. Login
- Ingresar con `admin@test.com` / `password`
- El sistema detectará automáticamente el comercio y lo establecerá

### 3. Explorar el Dashboard
- Ver el menú dinámico generado automáticamente
- Navegar por los módulos según permisos

### 4. Gestionar Usuarios
- Ir a **Configuración → Usuarios**
- Crear nuevos usuarios con diferentes roles

### 5. Gestionar Roles y Permisos
- Ir a **Configuración → Roles y Permisos**
- Ver roles predefinidos
- Crear nuevos roles según necesidades

---

## 📚 Documentación

### Documentos Principales

| Documento | Descripción |
|-----------|-------------|
| **[ARQUITECTURA.md](ARQUITECTURA.md)** | ⭐ **LEER PRIMERO** - Arquitectura completa del sistema |
| **[GUIA_RAPIDA.md](GUIA_RAPIDA.md)** | Referencia rápida, patrones comunes y troubleshooting |
| **[INDICE_COMPONENTES.md](INDICE_COMPONENTES.md)** | Índice de todos los archivos y componentes |
| **[ROADMAP.md](ROADMAP.md)** | Funcionalidades planificadas y próximos pasos |

### Documentos Técnicos

| Documento | Descripción |
|-----------|-------------|
| **[ESTRUCTURA_MULTITENANT.md](ESTRUCTURA_MULTITENANT.md)** | Detalles del sistema multi-tenant |
| **[PASSWORD_VISIBLE_GUIA.md](PASSWORD_VISIBLE_GUIA.md)** | Sistema de contraseñas recuperables |

### 🚀 Guías de Desarrollo

| Documento | Descripción | Audiencia |
|-----------|-------------|-----------|
| **[.claude/ESTANDARES_PROYECTO.md](.claude/ESTANDARES_PROYECTO.md)** | ⭐ **OBLIGATORIO** - Estándares de desarrollo | Desarrolladores y Claude Code |
| **[GUIA_DESARROLLO_COMPONENTES.md](GUIA_DESARROLLO_COMPONENTES.md)** | Guía completa para crear componentes Livewire | Desarrolladores |
| **[RESUMEN_DESARROLLO_RAPIDO.md](RESUMEN_DESARROLLO_RAPIDO.md)** | Checklist rápido de desarrollo | Desarrolladores |
| **[SISTEMA_EVENTOS_SUCURSALES.md](SISTEMA_EVENTOS_SUCURSALES.md)** | Arquitectura del sistema de eventos | Desarrolladores |
| **[OPTIMIZACIONES_SUCURSALES.md](OPTIMIZACIONES_SUCURSALES.md)** | Optimizaciones de rendimiento | Desarrolladores |

### Para Empezar

1. **Nuevo en el proyecto?** → Leer `ARQUITECTURA.md`
2. **Vas a desarrollar componentes?** → **LEER `.claude/ESTANDARES_PROYECTO.md`** ⚠️
3. **Buscar algo específico?** → Consultar `INDICE_COMPONENTES.md`
4. **Necesitas ejemplos rápidos?** → Ver `GUIA_RAPIDA.md` o `RESUMEN_DESARROLLO_RAPIDO.md`
5. **Quieres contribuir?** → Revisar `ROADMAP.md`

---

## 🛠️ Stack Tecnológico

### Backend
- **Laravel 11.x** - Framework PHP
- **Livewire 3.x** - Framework full-stack reactivo
- **Spatie Laravel Permission** - Sistema de roles y permisos
- **MySQL 8.0+** - Base de datos relacional

### Frontend
- **Alpine.js 3.x** - Framework JavaScript ligero
- **Tailwind CSS 3.x** - Framework CSS utility-first
- **Vite** - Build tool y bundler

### Herramientas
- **Laravel Breeze** - Starter kit de autenticación
- **Laravel Pail** - Visualizador de logs en tiempo real

---

## 🏗️ Arquitectura

### Patrón Multi-Tenant

El sistema implementa **multi-tenancy con tablas prefijadas**:

```
Comercio ID: 1 → Prefijo: 000001_
Comercio ID: 2 → Prefijo: 000002_

Base PYMES:
├── 000001_roles
├── 000001_model_has_roles
├── 000001_articulos
├── 000002_roles
├── 000002_model_has_roles
└── 000002_articulos
```

### Conexiones de Base de Datos

```
config        → Usuarios, comercios, sesiones (centralizado)
pymes         → Menús, permisos (compartidos)
pymes_tenant  → Roles, datos de negocio (con prefijo dinámico)
```

### Flujo de Request

```
Request
  ↓
ConfigureTenantMiddleware (configura prefijo)
  ↓
TenantMiddleware (valida acceso)
  ↓
Controller/Livewire (usa tablas con prefijo)
  ↓
Response
```

**📖 Detalles completos:** Ver `ARQUITECTURA.md`

---

## 🎮 Comandos Útiles

### Comercios

```bash
# Inicializar comercio (crear tablas)
php artisan comercio:init {comercio_id}

# Poblar menú y permisos
php artisan comercio:seed-menu {comercio_id}
```

### Desarrollo

```bash
# Compilar assets en tiempo real
npm run dev

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ver logs en tiempo real
php artisan pail
```

### Bases de Datos

```bash
# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Rollback última migración
php artisan migrate:rollback

# Fresh migration (resetear todo)
php artisan migrate:fresh
```

---

## 🧪 Testing

_Pendiente de implementación_

```bash
# Ejecutar tests
php artisan test

# Con coverage
php artisan test --coverage
```

---

## 📦 Estructura del Proyecto

```
bcn_pymes/
├── app/
│   ├── Console/Commands/      # Comandos Artisan personalizados
│   ├── Http/
│   │   ├── Controllers/       # Controladores
│   │   └── Middleware/        # Middleware personalizado
│   ├── Livewire/             # Componentes Livewire
│   │   └── Configuracion/    # Módulo de configuración
│   ├── Models/               # Modelos Eloquent
│   └── Services/             # Servicios del negocio
├── database/
│   ├── migrations/           # Migraciones
│   │   └── config/          # Migraciones para BD config
│   └── seeders/             # Seeders
├── resources/
│   ├── css/                 # Estilos
│   ├── js/                  # JavaScript
│   └── views/               # Vistas Blade
│       ├── components/      # Componentes Blade
│       ├── layouts/         # Layouts
│       └── livewire/        # Vistas Livewire
├── routes/
│   └── web.php             # Rutas web
├── ARQUITECTURA.md         # 📘 Documentación arquitectura
├── GUIA_RAPIDA.md          # 📗 Guía de referencia rápida
└── INDICE_COMPONENTES.md   # 📙 Índice de componentes
```

---

## 🤝 Contribución

### Proceso

1. Crear branch desde `master`
2. Implementar cambios
3. Actualizar documentación si es necesario
4. Crear Pull Request
5. Esperar revisión

### Convenciones

**Commits:**
```
feat: Nueva funcionalidad
fix: Corrección de bug
refactor: Refactorización
docs: Cambios en documentación
style: Formato de código
perf: Mejora de rendimiento
test: Tests
```

**Código:**
- Seguir PSR-12 para PHP
- Documentar con PHPDoc
- Escribir tests para funcionalidades nuevas

---

## 🔒 Seguridad

### Reportar Vulnerabilidades

Si descubres una vulnerabilidad de seguridad, por favor envía un email a [security@bcnpymes.com](mailto:security@bcnpymes.com).

**NO** abras issues públicos para problemas de seguridad.

### Buenas Prácticas

✅ Cambiar credenciales por defecto
✅ Usar HTTPS en producción
✅ Mantener dependencias actualizadas
✅ Configurar correctamente permisos de archivos
✅ No exponer `.env` en repositorio

---

## 📝 Changelog

Ver `ROADMAP.md` para cambios planificados.

**Versión Actual:** 1.0.0

---

## 📄 Licencia

Este proyecto es software propietario de BCN Pymes.

Todos los derechos reservados. No está permitida la distribución, modificación o uso comercial sin autorización expresa.

---

## 👥 Equipo

Desarrollado por el equipo de BCN Pymes.

---

## 🌟 Agradecimientos

- Laravel Framework
- Livewire
- Spatie
- Alpine.js
- Tailwind CSS

---

## 📞 Soporte

Para soporte técnico:
- Email: support@bcnpymes.com
- Documentación: Ver archivos .md en el proyecto

---

**Última actualización:** 2025-11-06
**Versión:** 1.0.0
