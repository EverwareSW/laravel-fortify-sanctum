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
            /** No need to call {@see Responsable::toResponse()} for {@see LoginResponse} and {@see TwoFactorLoginResponse},
              * that's already done in {@see Pipeline::handleCarry()}. */
            $response->headers->set('Auth-Token', $token);

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
