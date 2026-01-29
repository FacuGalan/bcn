<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Comando para crear items de menú
 *
 * Crea un nuevo item de menú de forma interactiva.
 * Gracias al MenuItemObserver, automáticamente:
 * - Crea el permiso menu.{slug}
 * - Lo asigna a roles de administrador en todos los tenants
 */
class CreateMenuItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:create
                            {--nombre= : Nombre del item de menú}
                            {--slug= : Slug del item (se genera automáticamente si no se proporciona)}
                            {--parent= : ID del item padre (opcional, para crear submenús)}
                            {--route= : Nombre de la ruta (ej: ventas.index)}
                            {--component= : Nombre del componente Livewire (alternativo a route)}
                            {--icono= : Nombre del icono (ej: icon.shopping-cart)}
                            {--orden=0 : Orden de visualización}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuevo item de menú con permisos automáticos para administradores';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Crear Nuevo Item de Menú ===');
        $this->newLine();

        // Obtener datos del item
        $nombre = $this->option('nombre') ?: $this->ask('Nombre del item de menú');

        // Generar slug automáticamente si no se proporciona
        $slugSuggestion = Str::slug($nombre);
        $slug = $this->option('slug') ?: $this->ask('Slug del item', $slugSuggestion);

        // Verificar si ya existe
        if (MenuItem::where('slug', $slug)->exists()) {
            $this->error("Ya existe un item de menú con el slug '{$slug}'");
            return 1;
        }

        // Preguntar si es un submenú
        $parentId = $this->option('parent');
        if (!$parentId && $this->confirm('¿Es un submenú (tiene un item padre)?', false)) {
            $this->showParentMenuItems();
            $parentId = $this->ask('ID del item padre');

            // Validar que existe el padre
            if ($parentId && !MenuItem::find($parentId)) {
                $this->error("No existe un item de menú con ID {$parentId}");
                return 1;
            }
        }

        // Determinar tipo de ruta
        $routeType = 'none';
        $routeValue = null;

        if ($parentId || $this->confirm('¿Este item tiene una ruta o componente?', true)) {
            $tipoRuta = $this->choice(
                '¿Qué tipo de navegación usa?',
                ['route' => 'Ruta de Laravel', 'component' => 'Componente Livewire', 'none' => 'Ninguna (solo agrupa items)'],
                'route'
            );

            $routeType = $tipoRuta;

            if ($tipoRuta === 'route') {
                $routeValue = $this->option('route') ?: $this->ask('Nombre de la ruta (ej: ventas.index)');
            } elseif ($tipoRuta === 'component') {
                $routeValue = $this->option('component') ?: $this->ask('Nombre del componente (ej: Ventas\\\\Index)');
            }
        }

        // Icono
        $icono = $this->option('icono');
        if (!$icono && $this->confirm('¿Desea asignar un icono?', true)) {
            $this->showIconExamples();
            $icono = $this->ask('Nombre del icono (ej: icon.shopping-cart)');
        }

        // Orden
        $orden = $this->option('orden') ?: $this->ask('Orden de visualización', '0');

        // Crear el item de menú
        try {
            $menuItem = MenuItem::create([
                'nombre' => $nombre,
                'slug' => $slug,
                'parent_id' => $parentId ?: null,
                'route_type' => $routeType,
                'route_value' => $routeValue,
                'icono' => $icono,
                'orden' => (int) $orden,
                'activo' => true,
            ]);

            $this->newLine();
            $this->info('✓ Item de menú creado exitosamente');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['ID', $menuItem->id],
                    ['Nombre', $menuItem->nombre],
                    ['Slug', $menuItem->slug],
                    ['Permiso', $menuItem->getPermissionName()],
                    ['Padre', $menuItem->parent_id ?: 'Ninguno (item raíz)'],
                    ['Tipo Ruta', $menuItem->route_type],
                    ['Valor Ruta', $menuItem->route_value ?: 'N/A'],
                    ['Icono', $menuItem->icono ?: 'N/A'],
                    ['Orden', $menuItem->orden],
                ]
            );

            $this->newLine();
            $this->info("El permiso '{$menuItem->getPermissionName()}' ha sido creado automáticamente");
            $this->info('y asignado a los roles de administrador en todos los tenants.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error al crear el item de menú: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Muestra los items de menú padre disponibles
     */
    protected function showParentMenuItems(): void
    {
        $parents = MenuItem::whereNull('parent_id')
            ->orderBy('orden')
            ->get(['id', 'nombre', 'slug']);

        if ($parents->isEmpty()) {
            $this->warn('No hay items padre disponibles');
            return;
        }

        $this->info('Items padre disponibles:');
        $this->table(
            ['ID', 'Nombre', 'Slug'],
            $parents->map(fn($item) => [$item->id, $item->nombre, $item->slug])->toArray()
        );
    }

    /**
     * Muestra ejemplos de iconos disponibles
     */
    protected function showIconExamples(): void
    {
        $this->info('Ejemplos de iconos disponibles:');
        $this->line('  • icon.shopping-cart    - Carrito de compras');
        $this->line('  • icon.shopping-bag     - Bolsa de compras');
        $this->line('  • icon.credit-card      - Tarjeta de crédito');
        $this->line('  • icon.dollar-sign      - Signo de dólar');
        $this->line('  • icon.users            - Usuarios');
        $this->line('  • icon.gear             - Configuración');
        $this->line('  • icon.chart-column     - Gráfico de barras');
        $this->line('  • icon.file             - Archivo');
        $this->line('  • icon.folder           - Carpeta');
        $this->line('  • icon.cube             - Cubo/Producto');
        $this->line('  • icon.tag              - Etiqueta');
        $this->newLine();
    }
}
