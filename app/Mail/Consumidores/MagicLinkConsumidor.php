<?php

namespace App\Mail\Consumidores;

use App\Models\Consumidor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Magic link de login del consumidor (RF-T69). Branding neutro BCN (cuenta
 * global cross-comercio). El link aterriza en la TIENDA ({url}/entrar), que
 * canjea el token contra el core; `volver` y `pairing` son parámetros DE LA
 * TIENDA que viajan opacos en la URL (los valida ella al aterrizar).
 */
class MagicLinkConsumidor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Consumidor $consumidor,
        public string $token,
        public ?string $volver = null,
        public ?string $pairing = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Tu enlace para entrar'),
        );
    }

    public function content(): Content
    {
        $query = array_filter([
            'token' => $this->token,
            'volver' => $this->volver,
            'pairing' => $this->pairing,
        ], fn ($v) => $v !== null && $v !== '');

        return new Content(
            markdown: 'emails.consumidores.magic-link',
            with: [
                'nombre' => $this->consumidor->nombre,
                'url' => config('tienda.url').'/entrar?'.http_build_query($query),
            ],
        );
    }
}
