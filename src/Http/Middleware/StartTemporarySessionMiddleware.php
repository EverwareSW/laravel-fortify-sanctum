<?php

namespace Everware\LaravelFortifySanctum\Http\Middleware;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;

/**
 * @NOTE The whole idea of 'stateless' means things like sessions should not work!
 * Therefore, this middleware should ONLY be used to re-enable two-factor auth.
 */
class StartTemporarySessionMiddleware extends StartSession
{
    /**
     * @return Session
     */
    public function getSession(Request $request)
    {
        /** @var Store $store */
        $store = $this->manager->driver();

        if ($sessionIdOrNull = $request->header('Temporary-Session-ID')) {
            $store->setId($sessionIdOrNull);
        }

        return $store;
    }

    protected function startSession(Request $request, $session)
    {
        /** @var Store $store */
        $store = parent::startSession($request, $session);
        // Temporary Sessions should only exist for 1 request.
        // Also, make sure config(session.lifetime) is set to > 0.
        $store->migrate(true);
        return $session;
    }

    protected function addCookieToResponse(Response $response, Session $session)
    {
        // We don't set session cookie anymore because stateless should not use cookies to retrieve sessions.
        // If two-factor is enabled, we DO need a way to use sessions because that's how Fortifys two-factor works.

        // This value should be read & stored in JS and added to Temporary-Session-ID header when posting two-factor after login.
        $response->headers->set('Temporary-Session-ID', $session->getId());
        $response->headers->set('Temporary-Session-Expires', \Date::parse($this->getCookieExpirationDate())->toJSON());
    }
}
