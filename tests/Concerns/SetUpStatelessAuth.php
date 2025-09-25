<?php

namespace Everware\LaravelFortifySanctum\Tests\Concerns;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Auth\Authenticatable;

trait SetUpStatelessAuth
{
    /** You can change this value in e.g. overridden @see TestCase::setUp() */
    public bool $useBearerToken = true;

    public function setUpSetUpStatelessAuth()
    {
        if ($this->useBearerToken && trait_exists('Laravel\Sanctum\HasApiTokens')) {
            /**
             * We want to make sure Laravel runs as it would in production.
             * Meaning, retrieve every user by Bearer token on each request @see \Laravel\Sanctum\Guard::__invoke()
             * Instead of caching User object in guard which is not reloaded when multiple requests made in same test.
             *
             * Used by 'auth:sanctum' middleware {@see RequestGuard::user()} {@see SanctumServiceProvider::configureGuard()}.
             * The @see AuthenticatedSessionController::destroy() only uses {@see SessionGuard} (not RequestGuard) and thus
             * only clears {@see SessionGuard::$user} in {@see SessionGuard::logout()}.
             * So, we have to manually clear all guard instances to make sure no ::$user model persists anywhere.
             **/
            $this->app->make(Kernel::class)->prependMiddleware(new class {
                /** {@see Pipeline::carry()} */
                public function handle($request, $next) {
                    app()->forgetInstance('session.store');
                    app('session')->forgetDrivers();
                    auth()->forgetGuards();
                    return $next($request);
                }
            });
            /** Can't use Kernel::pushMiddleware() because terminating middleware requires being class.
              * {@see Kernel::terminate()} into {@see Kernel::terminateMiddleware()} (!is_string()). */
            \App::terminating(function() { // \Event::listen(function(Terminating $e) {
                foreach (\Route::getRoutes() as $route) {
                    /** Required because otherwise controller instances like {@see AuthenticatedSessionController} are only created once
                      * and therefor keep outdated property instances of e.g. StatefulGuard on second+ calls. */
                    $route->flushController();
                }
            });
        }
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->useBearerToken
            && trait_exists(HasApiTokens::class)
            && in_array(HasApiTokens::class, class_uses_recursive($user))) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            $name = "$caller[class]$caller[type]$caller[function]#$caller[line]";
            /** Based on @see AuthController::authedResponse() */
            return $this->withToken($user->createToken($name)->plainTextToken);
        }

        return parent::actingAs($user, $guard);
    }
}
