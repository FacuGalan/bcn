<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder para crear comercios y usuarios de prueba
 *
 * Este seeder crea datos de prueba para:
 * - Comercios de ejemplo
 * - Usuarios de prueba con acceso a los comercios
 * - Relaciones entre usuarios y comercios
 * - Inicializa las tablas de cada comercio
 *
 * @package Database\Seeders
 * @author BCN Pymes
 * @version 1.0.0
 */
class ComercioUserSeeder extends Seeder
{
    /**
     * Ejecuta el seeder
     *
     * Crea comercios y usuarios de prueba con sus relaciones,
     * y ejecuta el comando de inicialización para cada comercio.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando seeder de Comercios y Usuarios...');

        // Crear comercios de prueba
        $comercio1 = Comercio::create([
            'mail' => 'comercio1@bcnpymes.com',
            'nombre' => 'Comercio Demo 1',
        ]);
        $this->command->info("✓ Comercio creado: {$comercio1->nombre} (ID: {$comercio1->id})");

        $comercio2 = Comercio::create([
            'mail' => 'comercio2@bcnpymes.com',
            'nombre' => 'Comercio Demo 2',
        ]);
        $this->command->info("✓ Comercio creado: {$comercio2->nombre} (ID: {$comercio2->id})");

        // Crear usuarios de prueba
        $admin = User::create([
            'name' => 'Admin Sistema',
            'username' => 'admin',
            'email' => 'admin@bcnpymes.com',
            'password' => Hash::make('password'),
            'password_visible' => encrypt('password'), // Contraseña cifrada
            'email_verified_at' => now(),
            'max_concurrent_sessions' => 5, // Admin puede tener 5 sesiones simultáneas
        ]);
        $this->command->info("✓ Usuario creado: {$admin->name} (username: {$admin->username}, max_sessions: 5)");

        $user1 = User::create([
            'name' => 'Usuario Comercio 1',
            'username' => 'user1',
            'email' => 'user1@bcnpymes.com',
            'password' => Hash::make('password'),
            'password_visible' => encrypt('password'), // Contraseña cifrada
            'email_verified_at' => now(),
            'max_concurrent_sessions' => 1, // Usuario normal: 1 sesión
        ]);
        $this->command->info("✓ Usuario creado: {$user1->name} (username: {$user1->username}, max_sessions: 1)");

        $user2 = User::create([
            'name' => 'Usuario Multi-Comercio',
            'username' => 'multiuser',
            'email' => 'multiuser@bcnpymes.com',
            'password' => Hash::make('password'),
            'password_visible' => encrypt('password'), // Contraseña cifrada
            'email_verified_at' => now(),
            'max_concurrent_sessions' => 3, // Usuario multi-comercio: 3 sesiones
        ]);
        $this->command->info("✓ Usuario creado: {$user2->name} (username: {$user2->username}, max_sessions: 3)");

        // Asociar usuarios a comercios
        // Admin tiene acceso a ambos comercios
        $admin->comercios()->attach([$comercio1->id, $comercio2->id]);
        $this->command->info("✓ Admin asociado a comercio 1 y 2");

        // User1 solo tiene acceso al comercio 1
        $user1->comercios()->attach($comercio1->id);
        $this->command->info("✓ User1 asociado a comercio 1");

        // User2 tiene acceso a ambos comercios
        $user2->comercios()->attach([$comercio1->id, $comercio2->id]);
        $this->command->info("✓ MultiUser asociado a comercio 1 y 2");

        // Inicializar tablas de cada comercio
        $this->command->info("\nInicializando tablas de comercios...");

        $this->command->call('comercio:init', ['comercio_id' => $comercio1->id]);
        $this->command->call('comercio:init', ['comercio_id' => $comercio2->id]);

        $this->command->info("\n✓ Seeder completado exitosamente!");
        $this->command->info("\n--- Credenciales de prueba ---");
        $this->command->info("Comercio 1: comercio1@bcnpymes.com");
        $this->command->info("Comercio 2: comercio2@bcnpymes.com");
        $this->command->info("\nUsuarios:");
        $this->command->info("- Username: admin | Password: password (Acceso: Comercio 1 y 2)");
        $this->command->info("- Username: user1 | Password: password (Acceso: Comercio 1)");
        $this->command->info("- Username: multiuser | Password: password (Acceso: Comercio 1 y 2)");
    }
}
