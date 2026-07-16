<x-mail::message>
# {{ __('¡Hola :nombre!', ['nombre' => $nombre]) }}

{{ __('Gracias por crear tu cuenta. Verificá tu email para desbloquear tu historial de pedidos y tus direcciones guardadas.') }}

<x-mail::button :url="$url">
{{ __('Verificar mi email') }}
</x-mail::button>

{{ __('El link vence en :horas horas. Si no creaste esta cuenta, ignorá este email.', ['horas' => \App\Services\Consumidores\ConsumidorTokenService::TTL_VERIFICACION_HORAS]) }}

{{ __('Gracias') }},<br>
{{ config('app.name') }}
</x-mail::message>
