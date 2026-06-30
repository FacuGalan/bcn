<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Warmea las cachés SEGURAS de la app tras un deploy, en un solo comando.
 *
 * Incluye `icons:cache` a propósito: sin él, blade-icons (blade-heroicons,
 * ~1200 SVGs) escanea el filesystem en CADA request durante el boot de
 * providers → ~600 ms de latencia sistémica en todo el sistema. Mismo patrón
 * que el Volt mount. Ver `.claude/docs/deploy-playbook.md` (Gotcha 3).
 *
 * NUNCA agregar `config:cache` acá: serializa el `.env` real y puede envenenar
 * los tests (incidente 2026-05-04). Solo cachés que no tocan config.
 *
 * Uso en deploy (ver server-config.md): correr DESPUÉS de migrate + build, y
 * recargar php-fpm después (OPcache con validate_timestamps=0 no toma el cambio
 * hasta el reload).
 */
class DeployWarmCommand extends Command
{
    protected $signature = 'deploy:warm';

    protected $description = 'Warmea las cachés seguras tras un deploy (view, route, event, icons). NO config:cache.';

    /**
     * Cachés seguras a warmear, en orden. NO incluir config:cache.
     */
    private const CACHES = [
        'view:cache' => 'Vistas Blade compiladas',
        'route:cache' => 'Rutas',
        'event:cache' => 'Eventos/listeners',
        'icons:cache' => 'Manifest de blade-icons (evita escanear ~1200 SVGs por request)',
    ];

    public function handle(): int
    {
        $this->info('Warmeando cachés seguras (sin config:cache)...');

        foreach (self::CACHES as $comando => $descripcion) {
            $this->line("  → {$comando} ({$descripcion})");
            $code = $this->call($comando);

            if ($code !== self::SUCCESS) {
                $this->error("Falló {$comando} (código {$code}). Abortando.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('✓ Cachés warmeadas.');
        $this->warn('Recordá: en prod (OPcache validate_timestamps=0) recargá php-fpm para que tome el cambio:');
        $this->line('  sudo systemctl reload php*-fpm');

        return self::SUCCESS;
    }
}
