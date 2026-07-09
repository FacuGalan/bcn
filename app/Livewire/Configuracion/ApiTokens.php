<?php

namespace App\Livewire\Configuracion;

use App\Models\Comercio;
use App\Services\TenantService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Gestión de tokens de integración de la API v1 (RF-11, pedidos-delivery).
 *
 * Tokens Sanctum emitidos POR COMERCIO con abilities (pedidos:read,
 * pedidos:write, catalogo:read, config:read). El token en claro se muestra
 * UNA sola vez al crearlo (Sanctum guarda el hash). Requiere permiso
 * `func.api.tokens`.
 */
#[Layout('layouts.app')]
#[Lazy]
class ApiTokens extends Component
{
    public const ABILITIES = [
        'pedidos:read' => 'Leer pedidos delivery',
        'pedidos:write' => 'Crear y modificar pedidos delivery',
        'catalogo:read' => 'Leer catálogo',
        'config:read' => 'Leer configuración y repartidores',
    ];

    public bool $showCrearModal = false;

    public string $nombreToken = '';

    /** @var array<string, bool> ability => seleccionada */
    public array $abilitiesSeleccionadas = [];

    /** Token en claro recién creado (se muestra UNA vez). */
    public ?string $tokenPlano = null;

    public bool $showRevocarModal = false;

    public ?int $tokenARevocar = null;

    public ?string $nombreTokenARevocar = null;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="0" :columns="4" :rows="4" />
        HTML;
    }

    protected function comercio(): ?Comercio
    {
        return app(TenantService::class)->getComercio();
    }

    protected function validarPermiso(): bool
    {
        if (! auth()->user()?->hasPermissionTo('func.api.tokens')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para gestionar tokens de API'));

            return false;
        }

        return true;
    }

    public function abrirCrear(): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $this->nombreToken = '';
        $this->abilitiesSeleccionadas = array_fill_keys(array_keys(self::ABILITIES), false);
        $this->tokenPlano = null;
        $this->resetValidation();
        $this->showCrearModal = true;
    }

    public function crearToken(): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $this->validate([
            'nombreToken' => 'required|string|max:100',
        ], [
            'nombreToken.required' => __('Ingresá un nombre para identificar el token'),
        ]);

        $abilities = array_keys(array_filter($this->abilitiesSeleccionadas));
        if (empty($abilities)) {
            $this->dispatch('toast-error', message: __('Elegí al menos un permiso para el token'));

            return;
        }

        $comercio = $this->comercio();
        if (! $comercio) {
            $this->dispatch('toast-error', message: __('No hay comercio activo'));

            return;
        }

        $token = $comercio->createToken(trim($this->nombreToken), $abilities);

        // Se muestra UNA sola vez: Sanctum persiste solo el hash.
        $this->tokenPlano = $token->plainTextToken;

        $this->dispatch('toast-success', message: __('Token creado: copialo ahora, no se vuelve a mostrar'));
    }

    public function cerrarCrear(): void
    {
        $this->showCrearModal = false;
        $this->nombreToken = '';
        $this->abilitiesSeleccionadas = [];
        $this->tokenPlano = null;
    }

    public function abrirRevocar(int $tokenId): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $token = $this->comercio()?->tokens()->find($tokenId);
        if (! $token) {
            return;
        }

        $this->tokenARevocar = $token->id;
        $this->nombreTokenARevocar = $token->name;
        $this->showRevocarModal = true;
    }

    public function revocarToken(): void
    {
        if (! $this->validarPermiso()) {
            return;
        }

        $this->comercio()?->tokens()->where('id', $this->tokenARevocar)->delete();

        $this->dispatch('toast-success', message: __('Token revocado'));
        $this->cerrarRevocar();
    }

    public function cerrarRevocar(): void
    {
        $this->showRevocarModal = false;
        $this->tokenARevocar = null;
        $this->nombreTokenARevocar = null;
    }

    public function render()
    {
        $comercio = $this->comercio();

        return view('livewire.configuracion.api-tokens', [
            'tokens' => $comercio
                ? $comercio->tokens()->orderByDesc('created_at')->get()
                : collect(),
            'abilitiesCatalogo' => self::ABILITIES,
        ]);
    }
}
