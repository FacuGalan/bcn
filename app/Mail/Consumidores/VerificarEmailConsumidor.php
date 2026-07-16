<?php

namespace App\Mail\Consumidores;

use App\Models\Consumidor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de verificación de cuenta del consumidor (RF-T1). Branding neutro
 * BCN: el consumidor es GLOBAL (cross-comercio), el email no sale a nombre
 * de ningún comercio.
 */
class VerificarEmailConsumidor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Consumidor $consumidor,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Verificá tu email'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.consumidores.verificar',
            with: [
                'nombre' => $this->consumidor->nombre,
                'url' => config('tienda.url').'/verificar?token='.$this->token,
            ],
        );
    }
}
