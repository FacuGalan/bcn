<?php

use App\Models\PermisoFuncional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API v1 pedidos-delivery (Fase 6): infraestructura en BD CONFIG (RF-13, D8/D15).
 *
 * - `personal_access_tokens` (Sanctum) en config: los tokens de integración
 *   son POR COMERCIO (tokenable = Comercio) y los futuros de consumidores son
 *   globales — ambos cross-tenant, por eso viven acá y no en la BD tenant.
 * - `tiendas`: registro global de tiendas POR SUCURSAL (D15) — resoluble sin
 *   abrir la BD tenant (el slug de la URL identifica comercio+sucursal).
 * - `rubros` + `comercios.rubro_id`: rubro comercial de la tienda (convive
 *   con `comercios.rubro` string, que es la categoría MCC de Mercado Pago).
 * - `consumidores` + `consumidor_direcciones` + `consumidor_comercio`:
 *   cuentas GLOBALES de la tienda (D8/D11). Este spec solo deja la
 *   estructura + guard; el registro/login lo implementa el proyecto tienda.
 * - `comercios.tienda_alta_cliente_automatica` (D11, default OFF).
 * - Permiso funcional `api.tokens` (gestión de tokens de integración —
 *   quedó fuera de la migración 14).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('config');

        if (! $schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('rubros')) {
            $schema->create('rubros', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('slug', 60)->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('tiendas')) {
            $schema->create('tiendas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
                $table->unsignedBigInteger('sucursal_id')->comment('FK LOGICO a la sucursal tenant (D15)');
                $table->string('slug', 60)->unique();
                $table->boolean('habilitada')->default(false);
                $table->string('dominio_propio')->nullable()->unique();
                $table->timestamps();
                $table->unique(['comercio_id', 'sucursal_id']);
            });
        }

        if (! $schema->hasTable('consumidores')) {
            $schema->create('consumidores', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150);
                $table->string('email', 150)->unique();
                $table->string('password');
                $table->string('telefono', 30)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumidor_direcciones')) {
            $schema->create('consumidor_direcciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consumidor_id')->constrained('consumidores')->cascadeOnDelete();
                $table->string('alias', 50)->nullable()->comment('Casa, Trabajo...');
                $table->string('direccion');
                $table->string('referencia')->nullable();
                $table->unsignedBigInteger('localidad_id')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->boolean('es_default')->default(false);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumidor_comercio')) {
            $schema->create('consumidor_comercio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consumidor_id')->constrained('consumidores')->cascadeOnDelete();
                $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
                $table->unsignedBigInteger('cliente_id')->comment('FK logico al cliente en la BD tenant del comercio');
                $table->timestamps();
                $table->unique(['consumidor_id', 'comercio_id']);
            });
        }

        if (! $schema->hasColumn('comercios', 'rubro_id')) {
            $schema->table('comercios', function (Blueprint $table) {
                $table->foreignId('rubro_id')->nullable()->after('rubro')
                    ->comment('Rubro comercial de la tienda (comercios.rubro es el MCC de MP — conviven)')
                    ->constrained('rubros')->nullOnDelete();
                $table->boolean('tienda_alta_cliente_automatica')->default(false)->after('rubro_id')
                    ->comment('D11: primer pedido de consumidor crea cliente tenant + mapping');
            });
        }

        $this->crearPermisoApiTokens();
    }

    private function crearPermisoApiTokens(): void
    {
        $conn = DB::connection('pymes');

        if (! $conn->table('permisos_funcionales')->where('codigo', 'api.tokens')->exists()) {
            $conn->table('permisos_funcionales')->insert([
                'codigo' => 'api.tokens',
                'etiqueta' => 'Gestionar tokens de API',
                'descripcion' => 'Permite emitir y revocar tokens de integracion de la API (acceso de terceros a pedidos, catalogo y configuracion del comercio).',
                'grupo' => 'Pedidos Delivery',
                'orden' => 8,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        PermisoFuncional::syncAllToSpatie();

        $perm = $conn->table('permissions')
            ->where('name', PermisoFuncional::PERMISSION_PREFIX.'api.tokens')
            ->first(['id']);

        if (! $perm) {
            return;
        }

        // Asignar a Administrador / Super Administrador en todos los tenants.
        $tablas = DB::connection('pymes_tenant')->select('SHOW TABLES');

        foreach ($tablas as $t) {
            $nombre = array_values((array) $t)[0];
            if (! preg_match('/^(\d{6}_)roles$/', $nombre, $m)) {
                continue;
            }

            try {
                $rolesAdmin = DB::connection('pymes_tenant')->table($nombre)
                    ->whereIn('name', ['Administrador', 'Super Administrador'])
                    ->pluck('id');

                foreach ($rolesAdmin as $rolId) {
                    $tablaRHP = $m[1].'role_has_permissions';
                    $existe = DB::connection('pymes_tenant')->table($tablaRHP)
                        ->where('role_id', $rolId)
                        ->where('permission_id', $perm->id)
                        ->exists();

                    if (! $existe) {
                        DB::connection('pymes_tenant')->table($tablaRHP)->insert([
                            'role_id' => $rolId,
                            'permission_id' => $perm->id,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('config');

        if ($schema->hasColumn('comercios', 'rubro_id')) {
            $schema->table('comercios', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rubro_id');
                $table->dropColumn('tienda_alta_cliente_automatica');
            });
        }

        $schema->dropIfExists('consumidor_comercio');
        $schema->dropIfExists('consumidor_direcciones');
        $schema->dropIfExists('consumidores');
        $schema->dropIfExists('tiendas');
        $schema->dropIfExists('rubros');
        $schema->dropIfExists('personal_access_tokens');

        $conn = DB::connection('pymes');
        $conn->table('permisos_funcionales')->where('codigo', 'api.tokens')->delete();
        $conn->table('permissions')->where('name', PermisoFuncional::PERMISSION_PREFIX.'api.tokens')->delete();
    }
};
