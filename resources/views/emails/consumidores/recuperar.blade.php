<x-mail::message>
# {{ __('¡Hola :nombre!', ['nombre' => $nombre]) }}

{{ __('Recibimos un pedido para restablecer el password de tu cuenta.') }}

<x-mail::button :url="$url">
{{ __('Restablecer password') }}
</x-mail::button>

{{ __('El link vence en :minutos minutos y sirve una sola vez. Si no pediste este cambio, ignorá este email: tu password sigue igual.', ['minutos' => \App\Services\Consumidores\ConsumidorTokenService::TTL_RESET_MINUTOS]) }}

{{ __('Gracias') }},<br>
{{ config('app.name') }}
</x-mail::message>
