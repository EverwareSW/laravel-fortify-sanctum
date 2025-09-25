# Laravel Fortify Sanctum integration
This package solves a few things.
1. Mainly, it makes Laravel Fortify give out Sanctum access tokens on login without the need to add or overwrite routes, 
   while keeping all Fortify functionality intact (like 2FA, password confirmation and registration).
1. It optionally allows you to use different route middleware (groups) than 'web', so you can use the 'api' middleware group for example.
1. It does so by removing the required use of cookies within Fortify, making the Fortify routes/authentication "stateless" (-ish)*.
   This is valuable when working with environments that disallow the use of cookies or sessions.

\* The use of sessions is not completely removed because it is required for the two factor authentication and password confirmation actions within Fortify.

# Setup
Install the package
```bash
composer require everware/laravel-fortify-sanctum
```

Add this guard to `config/auth.php 'guards'`:
```php
'fortify-sanctum' => [
    'driver' => 'fortify-sanctum',
    'provider' => 'users',
],
```

Set `config/fortify.php 'guard'` to:
```php
'guard' => 'fortify-sanctum', // originally: 'web',
```

Finally, set `config/fortify.php 'middleware' to either:
```php
// If the middleware (group) contains StartSession (like 'web'), only add our AddAuthTokenMiddleware.
['web', AddAuthTokenMiddleware::class],
// Or, if the middleware (group) does not contain StartSession, add StartTemporarySessionMiddleware and AddAuthTokenMiddleware.
['api', StartTemporarySessionMiddleware::class, AddAuthTokenMiddleware::class],

// Add the imports at the top of the file:
use Everware\LaravelFortifySanctum\Http\Middleware\StartTemporarySessionMiddleware;
use Everware\LaravelFortifySanctum\Http\Middleware\AddAuthTokenMiddleware;
```

# Flowchart
How Laravel Fortify works in combination with Laravel Sanctum is quite complex, so I've created a model which visualizes the main parts of the combined architecture:  
See [Laravel Fortify and Sanctum architecture](README.laravel-architecture.md)


Make sure no laravel breeze or starter kit auth routes.
