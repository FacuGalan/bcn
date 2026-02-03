<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Establece el locale de la aplicación según la preferencia del usuario autenticado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->locale) {
            App::setLocale($request->user()->locale);
        }

        return $next($request);
    }
}
