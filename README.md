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

# Usage
## Added 'device_name' field
First, Fortifys `/login` and `/two-factor-challenge` routes now also require a 'device_name' field, so make sure you add this to your post requests.  
We suggest something like: `{ email, password, device_name: window.navigator.userAgent }` in the browser or ```{ email, password, device_name: `${Device.deviceName} (${Device.modelName})` }``` using Expo Device (React Native).
## Token response, two factor & temp session
When you make a successful request to the Fortify login route, you will receive Fortifys original JSON response (e.g. `{two_factor: false}`).  
If the users 2fa is disabled, thus successfully logging in, you will also receive an 'Auth-Token' HTTP header containing the newly generated Sanctum access token.  
When making use of StartTemporarySessionMiddleware; if the users 2fa is enabled, you will receive a 'Temporary-Session-ID' HTTP header along with the response data `{two_factor: true}`.  
You can then make a post request containing the users OTP 'code' and the new 'device_name' field (see above) to `/two-factor-challenge` with this session id value in a 'Temporary-Session-ID' HTTP header.  
Note that the session id is regenerated on every request, so if for example the request to `/two-factor-challenge` fails in any way (e.g. 422 validation),
that response will contain a new 'Temporary-Session-ID' HTTP header which you will need use in the next request (the old id is now obsolete).
## Password confirmation
When not making use of StartTemporarySessionMiddleware; the password confirmation functionality works as it does normally.  
When making use of StartTemporarySessionMiddleware; the same 'Temporary-Session-ID' HTTP header functionality as described above 
is used with requests to `/user/confirm-password` and the response header value should be passed to whatever consecutive password-confirm-required route.  
Again, note the regeneration mentioned above.

# Troubleshooting
Make sure no Laravel Breeze or Starter Kit auth routes conflict with the Fortify routes.

# Flowchart
How Laravel Fortify works in combination with Laravel Sanctum is quite complex, so I've created a model which visualizes the main parts of the combined architecture:  
[Laravel Fortify and Sanctum architecture](README.laravel-architecture.md)
