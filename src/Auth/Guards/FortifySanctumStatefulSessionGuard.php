<?php

namespace Everware\LaravelFortifySanctum\Auth\Guards;

use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * For use in e.g. {@see AuthenticatedSessionController::__construct()}.
 * Based on {@see AuthManager::createTokenDriver()}.
 */
class FortifySanctumStatefulSessionGuard extends SessionGuard
{
    public function __construct(
        protected ?RequestGuard $sanctumGuard = null,
        string $name,
        array $config
    ) {
        /** {@see Guard} (registered in {@see SanctumServiceProvider::configureGuard()}). */
        $this->sanctumGuard ??= \Auth::guard('sanctum');
        $victim = \Auth::createSessionDriver($name, $config);
        parent::__construct($name, $victim->provider, $victim->session);
        foreach ($victim as $key => $value) {
            /** Because {@see SessionGuard::$name} is readonly. */
            if ($key != 'name') {
                $this->{$key} = $victim->{$key};
            }
        }

        \App::refresh('request', $this, 'setRequest');
    }

    /** @return \App\Models\User|AuthenticatableContract|null */
    public function user()
    {
        if ($this->loggedOut) {
            return;
        }
        if (!is_null($this->user)) {
            return $this->user;
        }

        /** @var \App\Models\User|null $user {@see Guard}. */
        $user = $this->sanctumGuard->user();
        if ($user) {
            /** Also called in {@see StartTemporarySessionMiddleware::startSession()} so overkill but kept for consistency {@see parent::user()}. */
            $this->session->migrate(true);
            $this->setUser($user);
            return $user;
        }
    }

    /** {@see static::userFromRecaller()} not necessary. */
    /** {@see static::recaller()} not necessary. */
    /** {@see static::id()} not necessary. */
    /** {@see static::once()} not necessary. */
    /** {@see static::onceUsingId()} not necessary. */
    /** {@see static::validate()} not necessary. */
    /** {@see static::basic()} not necessary. */
    /** {@see static::onceBasic()} not necessary. */
    /** {@see static::attemptBasic()} not necessary. */
    /** {@see static::basicCredentials()} not necessary. */
    /** {@see static::failedBasicResponse()} not necessary. */
    /** {@see static::attempt()} not necessary (but inner login() is). */
    /** {@see static::attemptWhen()} not necessary (but inner login() is). */
    /** {@see static::hasValidCredentials()} not necessary. */
    /** {@see static::shouldLogin()} not necessary. */
    /** {@see static::rehashPasswordIfRequired()} not necessary. */
    /** {@see static::loginUsingId()} not necessary. */
    //

    #[\Override]
    public function login(AuthenticatableContract $user, $remember = false)
    {
        /** Also called in {@see StartTemporarySessionMiddleware::startSession()} so overkill but kept for consistency {@see parent::login()}. */
        $this->session->migrate(true);

        // Requests fields altered in
        // LoginRequest (AuthenticatedSessionController > AttemptToAuthenticate)
        // Request (RegisteredUserController > CreatesNewUsers)
        // TwoFactorLoginRequest (TwoFactorAuthenticatedSessionController
        $token = $user->createToken($this->request->device_name)->plainTextToken;
        /** {@see StartTemporarySessionMiddleware::addCookieToResponse()}. */
        $this->session->put('auth-token', $token);

        $this->fireLoginEvent($user, $remember);

        $this->setUser($user);
    }

    /** ?{@see static::updateSession()} not necessary. */
    /** {@see static::ensureRememberTokenIsSet()} not necessary. */
    /** ?{@see static::queueRecallerCookie()} not necessary. */
    /** ?{@see static::createRecaller()} not necessary. */
    //

    public function logout()
    {
        return parent::logout(); /** Magic happens in {@see static::clearUserDataFromStorage()}. */
    }

    public function logoutCurrentDevice()
    {
        return parent::logoutCurrentDevice(); /** Magic happens in {@see static::clearUserDataFromStorage()}. */
    }

    protected function clearUserDataFromStorage()
    {
        $user = $this->user();
        /** {@see Guard::__invoke()} and {@see Sanctum::actingAs()} (unused). */
        $user->currentAccessToken()->delete();
    }

    /** {@see static::cycleRememberToken()} not necessary. */
    //

    public function logoutOtherDevices($password)
    {
        if (!$user = $this->user()) {
            return;
        }

        $result = $this->rehashUserPasswordForDeviceLogout($password);

        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        $this->fireOtherDeviceLogoutEvent($user);

        return $result;
    }

    /** {@see static::rehashUserPasswordForDeviceLogout()} not necessary. */
    /** {@see static::attempting($callback)} not necessary. */
    /** {@see static::fireAttemptEvent(array $credentials, $remember = false)} not necessary. */
    /** {@see static::fireValidatedEvent($user)} not necessary. */
    /** {@see static::fireLoginEvent($user, $remember = false)} not necessary. */
    /** {@see static::fireAuthenticatedEvent($user)} not necessary. */
    /** {@see static::fireOtherDeviceLogoutEvent($user)} not necessary. */
    /** {@see static::fireFailedEvent($user, array $credentials)} not necessary. */
    /** {@see static::getLastAttempted()} not necessary. */
    /** {@see static::getName()} not necessary. */
    /** {@see static::getRecallerName()} not necessary. */
    /** {@see static::viaRemember()} not necessary. */
    /** {@see static::getRememberDuration()} not necessary. */
    /** {@see static::setRememberDuration($minutes)} not necessary. */
    /** {@see static::getCookieJar()} not necessary. */
    /** {@see static::setCookieJar(CookieJar $cookie)} not necessary. */
    /** {@see static::getDispatcher()} not necessary. */
    /** {@see static::setDispatcher(Dispatcher $events)} not necessary. */
    /** {@see static::getSession()} not necessary. */
    /** {@see static::getUser()} not necessary. */
    /** {@see static::setUser(AuthenticatableContract $user)} not necessary. */
    /** {@see static::getRequest()} not necessary. */
    /** {@see static::setRequest(Request $request)} not necessary. */
    /** {@see static::getTimebox()} not necessary. */
    //
}
