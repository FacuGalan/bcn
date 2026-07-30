<?php

namespace Tests\Unit\Services;

use App\Services\Consumidores\GoogleIdTokenService;
use Tests\TestCase;

/**
 * Lógica pura del verificador de Google (RF-T49). La verificación de firma
 * real contra las JWKS se cubre con el mock en ApiV1ConsumidoresTest: acá
 * se prueba la regla de autoritatividad (doc oficial de GIS) y el apagado
 * sin client ID.
 */
class GoogleIdTokenServiceTest extends TestCase
{
    protected GoogleIdTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleIdTokenService;
    }

    public function test_gmail_es_autoritativo_aunque_no_venga_email_verified(): void
    {
        $this->assertTrue($this->service->esAutoritativo(['email' => 'alguien@gmail.com']));
        $this->assertTrue($this->service->esAutoritativo(['email' => 'Alguien@GMAIL.com']));
    }

    public function test_workspace_verificado_con_hd_es_autoritativo(): void
    {
        $this->assertTrue($this->service->esAutoritativo([
            'email' => 'juan@empresa.com',
            'email_verified' => true,
            'hd' => 'empresa.com',
        ]));
    }

    public function test_no_gmail_sin_hd_o_sin_verificar_no_es_autoritativo(): void
    {
        // Verificado pero sin hd (cuenta Google con email de terceros).
        $this->assertFalse($this->service->esAutoritativo([
            'email' => 'juan@hotmail.com',
            'email_verified' => true,
        ]));

        // Con hd pero sin verificar.
        $this->assertFalse($this->service->esAutoritativo([
            'email' => 'juan@empresa.com',
            'email_verified' => false,
            'hd' => 'empresa.com',
        ]));

        $this->assertFalse($this->service->esAutoritativo([]));
    }

    public function test_sin_client_id_no_esta_configurado_y_verificar_da_null(): void
    {
        config(['services.google.client_id' => null]);

        $this->assertFalse($this->service->configurado());
        $this->assertNull($this->service->verificar('lo-que-sea'));
    }
}
