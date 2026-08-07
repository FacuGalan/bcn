<x-mail::message>
# {{ __('¡Hola :nombre!', ['nombre' => $nombre]) }}

{{ __('Tocá el botón para entrar a tu cuenta sin contraseña.') }}

<x-mail::button :url="$url">
{{ __('Entrar a mi cuenta') }}
</x-mail::button>

{{ __('El enlace vence en :minutos minutos y sirve una sola vez. Si no lo pediste, ignorá este email: tu cuenta sigue segura.', ['minutos' => \App\Services\Consumidores\ConsumidorTokenService::TTL_MAGIC_MINUTOS]) }}

{{ __('Gracias') }},<br>
{{ config('app.name') }}
</x-mail::message>
