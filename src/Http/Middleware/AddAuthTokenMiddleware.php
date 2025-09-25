<?php

namespace Everware\LaravelFortifySanctum\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddAuthTokenMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        /** {@see FortifySanctumStatefulSessionGuard::login()}. */
        if ($token = session()->pull('auth-token')) {
            $response->headers->set('Auth-Token', $token);
        }

        return $response;
    }
}
