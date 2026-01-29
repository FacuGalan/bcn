# Guía de Gestión Automática de Permisos de Menú

Este sistema automatiza completamente la gestión de permisos cuando se agregan nuevos items al menú de navegación.

## ¿Qué hace automáticamente?

Cuando se crea un nuevo item de menú, el sistema **automáticamente**:

1. ✅ Crea el permiso `menu.{slug}` en la tabla `permissions`
2. ✅ Asigna el permiso a los roles de **Super Administrador** y **Administrador**
3. ✅ Aplica los permisos en **todos los tenants** existentes
4. ✅ Registra todas las operaciones en los logs

## Componentes del Sistema

### 1. MenuItemObserver

**Ubicación**: `app/Observers/MenuItemObserver.php`

Este Observer se ejecuta automáticamente en los siguientes eventos:

#### Evento `created`
- Crea el permiso `menu.{slug}` si no existe
- Lo asigna a roles de administrador en todos los tenants
- Registra la operación en logs

#### Evento `updated`
- Si cambió el slug, actualiza el nombre del permiso
- Mantiene las asignaciones existentes

#### Evento `deleted`
- Elimina el permiso de todos los tenants
- Elimina el registro del permiso

#### Evento `restored`
- Recrea el permiso y asignaciones si se restaura un item eliminado

### 2. Comando Artisan `menu:create`

**Ubicación**: `app/Console/Commands/CreateMenuItem.php`

Comando interactivo para crear items de menú fácilmente.

#### Uso Interactivo

```bash
php artisan menu:create
```

El comando te guiará paso a paso para:
- Definir el nombre del item
- Generar/personalizar el slug
- Seleccionar el item padre (para submenús)
- Configurar el tipo de ruta (route/component/none)
- Asignar un icono
- Establecer el orden

#### Uso con Opciones

```bash
php artisan menu:create \
  --nombre="Mi Nuevo Menú" \
  --slug="mi-nuevo-menu" \
  --parent=9 \
  --route="configuracion.mi-menu" \
  --icono="icon.gear" \
  --orden=5
```

#### Opciones Disponibles

| Opción | Descripción | Ejemplo |
|--------|-------------|---------|
| `--nombre` | Nombre del item | `"Formas de Pago"` |
| `--slug` | Slug (se genera automáticamente si no se proporciona) | `"formas-pago"` |
| `--parent` | ID del item padre (para crear submenús) | `9` |
| `--route` | Nombre de la ruta Laravel | `"configuracion.formas-pago"` |
| `--component` | Nombre del componente Livewire | `"Configuracion\\FormasPago"` |
| `--icono` | Icono a usar | `"icon.credit-card"` |
| `--orden` | Orden de visualización | `5` |

## Ejemplos de Uso

### Ejemplo 1: Crear un Item Padre (Categoría Principal)

```bash
php artisan menu:create \
  --nombre="Reportes" \
  --slug="reportes" \
  --icono="icon.chart-column" \
  --orden=5
```

Cuando se pregunta por el tipo de ruta, seleccionar "none" ya que solo agrupa items.

### Ejemplo 2: Crear un SubItem

```bash
php artisan menu:create \
  --nombre="Reporte de Ventas" \
  --slug="reporte-ventas" \
  --parent=5 \
  --route="reportes.ventas" \
  --icono="icon.chart-column" \
  --orden=1
```

### Ejemplo 3: Desde Código (Seeders, Controllers, etc.)

```php
use App\Models\MenuItem;

// El Observer se ejecutará automáticamente
$menuItem = MenuItem::create([
    'nombre' => 'Formas de Pago',
    'slug' => 'formas-pago',
    'parent_id' => 9, // ID del menú Configuración
    'route_type' => 'route',
    'route_value' => 'configuracion.formas-pago',
    'icono' => 'icon.credit-card',
    'orden' => 5,
    'activo' => true,
]);

// Automáticamente:
// - Se creó el permiso "menu.formas-pago"
// - Se asignó a Super Administrador y Administrador
// - Se aplicó en todos los tenants
```

## Flujo Completo

```
1. Usuario crea MenuItem
   ↓
2. MenuItemObserver::created() se dispara
   ↓
3. Se crea Permission "menu.{slug}"
   ↓
4. Se obtienen todos los tenants (000001_, 000002_, etc.)
   ↓
5. Para cada tenant:
   - Se obtienen roles de administrador
   - Se crea relación en role_has_permissions
   ↓
6. Se registra en logs
   ↓
7. ✅ Listo - Los administradores ya tienen acceso
```

## Roles que Reciben Automáticamente los Permisos

Los siguientes roles reciben automáticamente acceso a nuevos items de menú:

- ✅ **Super Administrador** (ID: 1)
- ✅ **Administrador** (ID: 2)

Los demás roles (Gerente, Vendedor, Visualizador) deben configurarse manualmente desde la UI de Roles y Permisos.

## Verificación Manual

### Ver Permisos Creados

```sql
-- Ver todos los permisos de menú
SELECT * FROM pymes.permissions WHERE name LIKE 'menu.%' ORDER BY id;

-- Ver permiso específico
SELECT * FROM pymes.permissions WHERE name = 'menu.formas-pago';
```

### Ver Asignaciones de Permisos

```sql
-- Ver permisos del rol Administrador en tenant 000001
SELECT
    rp.role_id,
    r.name as role_name,
    p.name as permission_name
FROM pymes.000001_role_has_permissions rp
JOIN pymes.000001_roles r ON rp.role_id = r.id
JOIN pymes.permissions p ON rp.permission_id = p.id
WHERE p.name LIKE 'menu.%' AND rp.role_id = 2
ORDER BY p.name;
```

## Logs

Todas las operaciones se registran en `storage/logs/laravel.log`:

```
[INFO] MenuItem created: Permission 'menu.formas-pago' created and assigned to admin roles
    menu_item_id: 14
    permission_id: 14

[INFO] Permission assigned to admin roles in tenant 000001_
    permission_id: 14
    role_ids: [1, 2]

[INFO] Permission assigned to admin roles in tenant 000002_
    permission_id: 14
    role_ids: [1, 2]
```

## Mantenimiento

### Limpiar Cachés Después de Cambios

```bash
php artisan optimize:clear
```

Esto limpia:
- Cache de configuración
- Cache de rutas
- Cache de vistas
- Cache de permisos
- Cache de menú del usuario

### Re-sincronizar Permisos Existentes

Si agregaste items de menú antes de implementar este sistema:

```php
use App\Models\MenuItem;
use App\Observers\MenuItemObserver;

$observer = new MenuItemObserver();

MenuItem::all()->each(function($menuItem) use ($observer) {
    $observer->created($menuItem);
});
```

## Troubleshooting

### El menú no aparece para el administrador

1. Verificar que el permiso fue creado:
   ```bash
   php artisan tinker
   ```
   ```php
   \App\Models\Permission::where('name', 'menu.formas-pago')->first();
   ```

2. Verificar asignación al rol:
   ```php
   DB::connection('pymes_tenant')
       ->table('000001_role_has_permissions')
       ->where('permission_id', 14)
       ->where('role_id', 2)
       ->exists();
   ```

3. Limpiar caché:
   ```bash
   php artisan optimize:clear
   ```

4. Verificar logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### El Observer no se ejecuta

1. Verificar que está registrado en `AppServiceProvider`:
   ```php
   // app/Providers/AppServiceProvider.php
   public function boot(): void
   {
       MenuItem::observe(MenuItemObserver::class);
   }
   ```

2. Verificar que no hay errores de sintaxis:
   ```bash
   php artisan optimize:clear
   composer dump-autoload
   ```

## Consideraciones Importantes

1. **Multi-tenant**: El sistema detecta automáticamente todos los tenants existentes y aplica permisos en cada uno

2. **Seguridad**: Solo los roles de administrador reciben automáticamente acceso. Otros roles deben configurarse manualmente

3. **Performance**: Las operaciones se hacen en batch para minimizar queries a la base de datos

4. **Idempotencia**: El Observer verifica si ya existe el permiso antes de crearlo, evitando duplicados

5. **Logs**: Todas las operaciones se registran para auditoría y debugging

## Próximos Pasos Recomendados

1. **UI para gestionar permisos de menú**: Crear una interfaz gráfica para asignar/desasignar permisos de menú a roles

2. **Migración de datos**: Ejecutar script para crear permisos de items de menú existentes

3. **Pruebas unitarias**: Agregar tests para el Observer y el comando

4. **Documentación de iconos**: Mantener lista actualizada de iconos disponibles

## Contacto

Para más información o soporte, consultar con el equipo de desarrollo.

---

**Última actualización**: 2025-11-18
**Versión**: 1.0.0
