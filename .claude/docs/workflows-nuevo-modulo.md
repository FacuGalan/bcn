# Workflow: Agregar Nuevo Módulo

## Pasos completos

### 1. Migración de menu_items + permisos (una sola migración)

```php
// database/migrations/2026_MM_DD_HHMMSS_add_MODULO_menu_items_and_permissions.php
return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear item padre en pymes.menu_items
        $parentId = DB::connection('pymes')->table('menu_items')->insertGetId([
            'nombre' => 'Mi Módulo',
            'slug' => 'mi-modulo',
            'icono' => 'heroicon-o-icon-name',
            'orden' => 7, // siguiente disponible
            'parent_id' => null,
            'route' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Crear sub-items
        $items = [
            ['nombre' => 'Sub Item 1', 'slug' => 'sub-item-1', 'route' => 'mi-modulo.sub1', 'orden' => 1],
            ['nombre' => 'Sub Item 2', 'slug' => 'sub-item-2', 'route' => 'mi-modulo.sub2', 'orden' => 2],
        ];

        foreach ($items as $item) {
            DB::connection('pymes')->table('menu_items')->insert([
                'nombre' => $item['nombre'],
                'slug' => $item['slug'],
                'route' => $item['route'],
                'icono' => null,
                'orden' => $item['orden'],
                'parent_id' => $parentId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Crear permisos en pymes.permissions
        $allSlugs = array_merge(['mi-modulo'], array_column($items, 'slug'));
        $permissionIds = [];

        foreach ($allSlugs as $slug) {
            $permissionIds[] = DB::connection('pymes')->table('permissions')->insertGetId([
                'name' => "menu.{$slug}",
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Asignar permisos a roles Super Admin y Admin en cada comercio
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                $roleIds = DB::connection('pymes')
                    ->table("{$prefix}roles")
                    ->whereIn('name', ['Super Administrador', 'Administrador'])
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permissionIds as $permId) {
                        DB::connection('pymes')->table("{$prefix}role_has_permissions")->insertOrIgnore([
                            'permission_id' => $permId,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
```

### 2. Migraciones de tablas tenant (si aplica)

Seguir workflow de `workflows-migraciones.md`.

### 3. Modelo(s)

```php
// app/Models/MiModelo.php
class MiModelo extends Model
{
    protected $connection = 'pymes_tenant'; // OBLIGATORIO para tenant
    protected $table = 'mi_tabla';
    protected $fillable = ['nombre', 'sucursal_id', ...];
}
```

### 4. Service(s)

Crear en `app/Services/MiModuloService.php` con lógica de negocio.

### 5. Componente(s) Livewire

Seguir estándares de `.claude/ESTANDARES_PROYECTO.md`.
Usar trait `SucursalAware` si aplica.

### 6. Vista(s) Blade

Crear en `resources/views/livewire/mi-modulo/`.

### 7. Ruta(s)

```php
// routes/web.php — dentro del grupo auth+verified+tenant
Route::get('/mi-modulo/sub1', \App\Livewire\MiModulo\SubItem1::class)->name('mi-modulo.sub1');
Route::get('/mi-modulo/sub2', \App\Livewire\MiModulo\SubItem2::class)->name('mi-modulo.sub2');
```

### 8. Traducciones

Agregar a `lang/{es,en,pt}.json` manteniendo orden alfabético.

### 9. Actualizar ProvisionComercioCommand

En `seedRolesYPermisos()`, agregar los nuevos permisos a los roles que correspondan (Gerente, Vendedor si aplica).

### 10. Regenerar tenant_tables.sql

Si se crearon tablas tenant nuevas.
