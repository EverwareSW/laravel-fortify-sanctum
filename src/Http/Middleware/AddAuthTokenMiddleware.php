<?php

namespace Everware\LaravelFortifySanctum\Http\Middleware;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
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

            /** {@see LoginResponse} and {@see TwoFactorLoginResponse}. */
            if ($response instanceof Responsable) {
                $response = $response->toResponse($request);
            }
            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                $data === '' and $data = [];
                $response->setData($data + ['auth_token' => $token]);
                $response->getStatusCode() === 204 /*NoContent*/ and $response->setStatusCode(200);
            }
        }

        return $response;
    }
}
