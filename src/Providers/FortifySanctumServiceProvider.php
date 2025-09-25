<?php

namespace Everware\LaravelFortifySanctum\Providers;

use Everware\LaravelFortifySanctum\Auth\Guards\FortifySanctumStatefulSessionGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class FortifySanctumServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Set config('auth.guards.web.driver') to 'stateless'.
        \Auth::extend('fortify-sanctum', function(Application $app, string $name, array $config) {
            return new FortifySanctumStatefulSessionGuard(null, $name, $config);
        });
        if (config('fortify.guard') == 'fortify-sanctum') {
            /** {@see AuthenticatedSessionController::store()}. */
            $this->app->bind(LoginRequest::class, function() {
                return new class extends LoginRequest {
                    public function rules() {
                        return parent::rules() + ['device_name' => 'required'];
                    }
                };
            });
            /** {@see TwoFactorAuthenticatedSessionController::store()}. */
            $this->app->bind(TwoFactorLoginRequest::class, function() {
                return new class extends TwoFactorLoginRequest {
                    public function rules() {
                        return parent::rules() + ['device_name' => 'required'];
                    }
                };
            });
            /** {@see RegisteredUserController::store()}. TODO If Fortify ever creates a RegisterRequest like above, use that instead of this: */
            $this->app->extend(CreatesNewUsers::class, function(CreatesNewUsers $action) {
                return new class($action) implements CreatesNewUsers {
                    public function __construct(protected CreatesNewUsers $parent){}
                    public function create(array $input) {
                        validator($input, ['device_name' => 'required'])->validate();
                        return $this->parent->create($input);
                    }
                };
            });
        }
    }
}
