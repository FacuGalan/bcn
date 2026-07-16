<?php

namespace App\Mail\Consumidores;

use App\Models\Consumidor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de recuperación de password del consumidor (RF-T1). Branding
 * neutro BCN (ver VerificarEmailConsumidor).
 */
class RecuperarPasswordConsumidor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Consumidor $consumidor,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Restablecé tu password'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.consumidores.recuperar',
            with: [
                'nombre' => $this->consumidor->nombre,
                'url' => config('tienda.url').'/recuperar?token='.$this->token,
            ],
        );
    }
}
