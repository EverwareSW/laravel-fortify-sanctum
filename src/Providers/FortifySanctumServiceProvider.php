<?php

namespace Everware\LaravelFortifySanctum\Providers;

use Everware\LaravelFortifySanctum\Auth\Guards\FortifySanctumStatefulSessionGuard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class FortifySanctumServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/auth.guards.php', 'auth.guards'
        );
    }

    public function boot(): void
    {
        // Set config('auth.guards.web.driver') to 'stateless'.
        \Auth::extend('fortify-sanctum', function(Application $app, string $name, array $config) {
            return new FortifySanctumStatefulSessionGuard(null, $name, $config);
        });

        /**
         * No longer directly checking `if (config('fortify.guard') == 'fortify-sanctum')` here
         * because other packages might have overriden StatefulGuard in certain situations.
         * E.g. Nova Overwrites our StatefulGuard ({@see FortifySanctumServiceProvider::boot()} into {@see FortifyServiceProvider::register()})
         * using `$app->scoped(StatefulGuard::class, static fn () => Auth::guard(Util::userGuard()));`
         * in {@see PendingFortifyConfiguration::bootstrap()}, but only when `Nova::serving(...)` (like when posting to their login route).
         */

        /** {@see AuthenticatedSessionController::store()}. */
        $this->app->bind(LoginRequest::class, function($app, $params = []) {
            return new class(...$params) extends LoginRequest {
                public function rules() {
                    /**
                     * See above `Auth::extend()` and
                     * {@see FortifyServiceProvider::register()} (Fortify)
                     * {@see PendingFortifyConfiguration::bootstrap()} (Nova).
                     */
                    return app(StatefulGuard::class) instanceof FortifySanctumStatefulSessionGuard
                        ? parent::rules() + ['device_name' => 'required']
                        : parent::rules();
                }
            };
        });
        /** {@see TwoFactorAuthenticatedSessionController::store()}. */
        $this->app->bind(TwoFactorLoginRequest::class, function() {
            return new class extends TwoFactorLoginRequest {
                public function rules() {
                    /**
                     * See above `Auth::extend()` and
                     * {@see FortifyServiceProvider::register()} (Fortify)
                     * {@see PendingFortifyConfiguration::bootstrap()} (Nova).
                     */
                    return app(StatefulGuard::class) instanceof FortifySanctumStatefulSessionGuard
                        ? parent::rules() + ['device_name' => 'required']
                        : parent::rules();
                }
            };
        });
        /** {@see RegisteredUserController::store()}. TODO If Fortify ever creates a RegisterRequest like above, use that instead of this: */
        $this->app->extend(CreatesNewUsers::class, function(CreatesNewUsers $action) {
            return new class($action) implements CreatesNewUsers {
                public function __construct(protected CreatesNewUsers $parent){}
                public function create(array $input) {
                    /**
                     * See above `Auth::extend()` and
                     * {@see FortifyServiceProvider::register()} (Fortify)
                     * {@see PendingFortifyConfiguration::bootstrap()} (Nova).
                     */
                    if (app(StatefulGuard::class) instanceof FortifySanctumStatefulSessionGuard) {
                        validator($input, ['device_name' => 'required'])->validate();
                    }

                    return $this->parent->create($input);
                }
            };
        });
    }
}
