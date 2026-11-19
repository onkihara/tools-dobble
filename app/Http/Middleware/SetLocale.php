<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('dobble.default_locale'));

        if (! is_string($locale) || ! in_array($locale, config('dobble.locales'), true)) {
            $locale = config('dobble.default_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
