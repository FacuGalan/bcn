<?php

namespace App\Traits;

use App\Models\Localidad;
use App\Models\Provincia;

/**
 * Trait ManejaDomicilio (RF-11, Fase 9)
 *
 * Lógica compartida de un formulario de domicilio estructurado:
 * provincia (ISO 3166-2) → localidad dependiente + geo opcional.
 *
 * Es la capa de compatibilidad de domicilios del sistema: lo usan la gestión de
 * domicilios fiscales del CUIT, el domicilio físico de la sucursal y desarrollos
 * futuros. El partial Blade asociado es
 * `resources/views/livewire/partials/domicilio-form.blade.php`.
 *
 * El host (componente Livewire) decide CÓMO persiste estos valores (a
 * cuit_domicilios, a sucursales, etc.); el trait solo maneja el estado del form
 * y la dependencia provincia→localidad.
 */
trait ManejaDomicilio
{
    /** Tipo de domicilio (fiscal|comercial|otro). Aplica a cuit_domicilios. */
    public string $domTipo = 'fiscal';

    /** Provincia en ISO 3166-2 (ej: AR-B) — define la jurisdicción. */
    public ?string $domProvincia = null;

    /** Localidad seleccionada (ref a localidades de config). */
    public ?int $domLocalidadId = null;

    public string $domDireccion = '';

    public ?string $domLatitud = null;

    public ?string $domLongitud = null;

    /** Opciones de localidad para la provincia elegida: [id => nombre]. */
    public array $domLocalidades = [];

    /**
     * Centro geográfico de la localidad elegida (['lat'=>, 'lng'=>] o null).
     * Lo consume el picker de Google Maps (JS) para centrar y ACOTAR el mapa a
     * la localidad. Se entabla con Alpine vía $wire. Ver spec domicilio-google-maps.
     */
    public ?array $domLocalidadCentro = null;

    /**
     * Reglas de validación del domicilio. El host las compone con sus propias
     * reglas. La localidad es opcional (puede no estar en el padrón).
     */
    protected function reglasDomicilio(bool $direccionRequerida = true): array
    {
        return [
            'domTipo' => 'required|in:fiscal,comercial,otro',
            'domProvincia' => 'required|string|max:6',
            'domLocalidadId' => 'nullable|integer',
            'domDireccion' => ($direccionRequerida ? 'required' : 'nullable').'|string|max:255',
            'domLatitud' => 'nullable|numeric|between:-90,90',
            'domLongitud' => 'nullable|numeric|between:-180,180',
        ];
    }

    /**
     * Hook de Livewire: al cambiar la provincia, recargar las localidades y
     * resetear la localidad elegida (puede no existir en la nueva provincia).
     */
    public function updatedDomProvincia(): void
    {
        $this->domLocalidadId = null;
        $this->domLocalidadCentro = null;
        $this->cargarLocalidadesDomicilio();
    }

    /**
     * Hook de Livewire: al cambiar la localidad, resolver su centro geográfico
     * para que el mapa se centre/acote ahí (flujo invertido localidad → mapa).
     */
    public function updatedDomLocalidadId(): void
    {
        $this->domLocalidadCentro = Localidad::centro($this->domLocalidadId);
    }

    /**
     * Recibe las coordenadas elegidas desde el mapa (autocomplete / marcador
     * arrastrable / "usar mi ubicación"). Invocado por el JS vía $wire.
     * Valida rango; ignora valores inválidos para no romper el form.
     */
    public function setCoordenadasDesdeMapa($lat, $lng): void
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $this->domLatitud = (string) round($lat, 7);
        $this->domLongitud = (string) round($lng, 7);
    }

    /** ¿Hay API key de Google Maps configurada? Decide map vs inputs manuales. */
    public function mapsHabilitado(): bool
    {
        return filled(config('services.google_maps.key'));
    }

    /**
     * Carga las localidades de la provincia seleccionada (por código ISO).
     */
    protected function cargarLocalidadesDomicilio(): void
    {
        if (! $this->domProvincia) {
            $this->domLocalidades = [];

            return;
        }

        $provinciaId = Provincia::porCodigo($this->domProvincia)->value('id');
        $this->domLocalidades = $provinciaId ? Localidad::paraSelect($provinciaId) : [];
    }

    /**
     * Provincias para el select (código ISO => nombre).
     *
     * @return array<string,string>
     */
    public function getProvinciasDomicilioProperty(): array
    {
        return Provincia::ordenadas()->pluck('nombre', 'codigo')->toArray();
    }

    /**
     * Carga el form desde un domicilio existente (para editar).
     */
    protected function setDomicilioDesde(array $datos): void
    {
        $this->domTipo = $datos['tipo'] ?? 'fiscal';
        $this->domProvincia = $datos['provincia'] ?? null;
        $this->domLocalidadId = $datos['localidad_id'] ?? null;
        $this->domDireccion = $datos['direccion'] ?? '';
        $this->domLatitud = isset($datos['latitud']) ? (string) $datos['latitud'] : null;
        $this->domLongitud = isset($datos['longitud']) ? (string) $datos['longitud'] : null;
        $this->cargarLocalidadesDomicilio();
        $this->domLocalidadCentro = Localidad::centro($this->domLocalidadId);
    }

    /**
     * Devuelve los datos del domicilio listos para persistir.
     *
     * @return array<string,mixed>
     */
    protected function datosDomicilio(): array
    {
        return [
            'tipo' => $this->domTipo,
            'provincia' => $this->domProvincia,
            'localidad_id' => $this->domLocalidadId ?: null,
            'direccion' => $this->domDireccion,
            'latitud' => $this->domLatitud !== null && $this->domLatitud !== '' ? $this->domLatitud : null,
            'longitud' => $this->domLongitud !== null && $this->domLongitud !== '' ? $this->domLongitud : null,
        ];
    }

    /**
     * Resetea el form de domicilio a sus valores por defecto.
     */
    protected function resetDomicilio(): void
    {
        $this->domTipo = 'fiscal';
        $this->domProvincia = null;
        $this->domLocalidadId = null;
        $this->domDireccion = '';
        $this->domLatitud = null;
        $this->domLongitud = null;
        $this->domLocalidades = [];
        $this->domLocalidadCentro = null;
    }
}
