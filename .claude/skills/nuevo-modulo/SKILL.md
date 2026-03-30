---
name: nuevo-modulo
description: Workflow completo para agregar un nuevo módulo al sistema (menú, permisos, migraciones, componente, ruta, traducciones).
user-invocable: true
argument-hint: "[nombre-módulo]"
---

# Nuevo Módulo — Workflow Completo

Tu trabajo es crear un módulo completo en BCN Pymes siguiendo todos los pasos del workflow.

## Al ejecutar este skill:

### 1. Recopilar información

Preguntar al usuario:
- **Nombre del módulo** (ej: "Compras", "Reportes")
- **Es hijo de un menú existente?** Si no, crear menú padre
- **Sub-items del menú**: nombre, slug, ruta para cada uno
- **Icono Heroicon** para el padre (ej: `heroicon-o-shopping-cart`)
- **Posición** en el menú (orden numérico)
- **Qué roles** deben tener acceso (por defecto: Super Admin + Admin)
- **Tablas nuevas?** Si necesita tablas tenant, definir estructura
- **Es sucursal-aware?** Define si usa SucursalAware trait

### 2. Leer referencia

- Leer `.claude/docs/workflows-nuevo-modulo.md` para el workflow completo
- Leer `.claude/docs/workflows-migraciones.md` si hay tablas nuevas

### 3. Generar en este orden estricto

**A. Migración menu_items + permisos** (una sola migración)
- Insertar item padre en `pymes.menu_items`
- Insertar sub-items con `parent_id`
- Crear permisos `menu.{slug}` en `pymes.permissions`
- Asignar permisos a roles por cada comercio
- CUIDADO: `slug` tiene UNIQUE constraint

**B. Migraciones de tablas tenant** (si aplica)
- Seguir convenciones de `.claude/docs/workflows-migraciones.md`
- Iterar comercios, SQL raw con prefijo, try/catch

**C. Modelo(s)**
- `protected $connection = 'pymes_tenant'` si es tenant
- Definir `$fillable`, relaciones, scopes

**D. Service(s)** (si tiene lógica de negocio)
- Crear en `app/Services/`

**E. Componente(s) Livewire**
- Seguir `.claude/ESTANDARES_PROYECTO.md`
- Aplicar SucursalAware si corresponde
- `#[Lazy]` + `placeholder()` con skeleton reutilizable (OBLIGATORIO para full-page)
- Crear vista Blade correspondiente

**F. Ruta(s)**
- Agregar en `routes/web.php` dentro del grupo `auth+verified+tenant`

**G. Traducciones**
- Agregar a `lang/{es,en,pt}.json` manteniendo orden alfabético

### 4. Post-pasos

- Ejecutar `php artisan migrate` (pedir autorización)
- Regenerar `tenant_tables.sql` si se crearon tablas tenant
- Ejecutar `php artisan optimize:clear` para limpiar cache de menú
- Recordar al usuario actualizar `ProvisionComercioCommand` si se asignaron permisos a roles nuevos (Gerente, Vendedor)

### 5. Actualizar documentación

- **`docs/manual-usuario.md`**: Agregar nueva sección del módulo con todas las funcionalidades (acciones, filtros, modales, campos, flujos)
- **`docs/ai-knowledge-base.md`**: Agregar nuevas tablas al modelo de datos (columnas, tipos, FK), nueva lógica de negocio al dominio correspondiente, y queries de ejemplo

### 6. Checklist final

Mostrar:
```
[x] Migración menu_items + permisos
[x] Migraciones tablas tenant
[x] Modelo(s) creado(s)
[x] Service(s) creado(s)
[x] Componente(s) Livewire + vista(s)
[x] Ruta(s) en web.php
[x] Traducciones en 3 idiomas
[x] Documentación actualizada (manual-usuario.md + ai-knowledge-base.md)
[ ] Actualizar ProvisionComercioCommand (si otros roles necesitan acceso)
[ ] Regenerar tenant_tables.sql (si hubo cambios tenant)
```
