<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Comercio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder principal de la aplicación
 *
 * Ejecuta en orden:
 * 1. Datos compartidos (menú, permisos)
 * 2. Comercio demo
 * 3. Usuario administrador
 *
 * Uso: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== BCN Pymes - Instalación Inicial ===');

        // 1. Cargar datos compartidos (menú, permisos funcionales, permissions de Spatie)
        $this->command->info('Cargando datos compartidos...');
        $this->loadSharedData();

        // 2. Crear usuario administrador del sistema
        $this->command->info('Creando usuario administrador...');
        $admin = $this->createAdminUser();

        // 3. Ejecutar seeders de menú y permisos
        $this->command->info('Configurando menú y permisos...');
        $this->call([
            MenuItemSeeder::class,
            PermisosFuncionalesSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('=== Instalación completada ===');
        $this->command->info('');
        $this->command->info('Usuario administrador creado:');
        $this->command->info('  Email: admin@bcnpymes.com');
        $this->command->info('  Usuario: admin');
        $this->command->info('  Contraseña: admin123');
        $this->command->info('');
        $this->command->info('Para crear un comercio, ejecute:');
        $this->command->info('  php artisan comercio:create');
        $this->command->info('');
    }

    /**
     * Carga los datos compartidos desde el archivo SQL
     */
    protected function loadSharedData(): void
    {
        $sqlFile = database_path('sql/shared_data.sql');
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // Ejecutar en la conexión pymes (tablas sin prefijo)
            DB::connection('pymes')->unprepared($sql);
        }
    }

    /**
     * Crea el usuario administrador del sistema
     */
    protected function createAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@bcnpymes.com'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'is_system_admin' => true,
                'activo' => true,
            ]
        );
    }
}
