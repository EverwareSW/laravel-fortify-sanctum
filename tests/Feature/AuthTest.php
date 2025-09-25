<?php

use Everware\LaravelFortifySanctum\Http\Middleware\StartTemporarySessionMiddleware;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

test('login', function() {
    $isUsingStateless = in_array(StartTemporarySessionMiddleware::class, config('fortify.middleware'));
    $password = 'password';

    $logoutRoute = route('logout');

    if (!Features::enabled(Features::registration())) {
        $auth = User::factory()->create();
    } else {
        $registerRoute = route('register.store');
        $response = $this->postJson($registerRoute);
        $response->assertUnprocessable();

        $response = $this->postJson($registerRoute, $registerData = [
            'name' => 'Test User',
            'email' => 'user@email.test',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['device_name']);

        $response = $this->postJson($registerRoute, $registerData + ['device_name' => 'device']);
        $response->assertCreated();
        if ($isUsingStateless) {
            $response->assertHeaderMissing('Set-Cookie');
            $response->assertCookieMissing(config('session.cookie'));
        }
        $authToken = $response->headers->get('auth-token');
        expect($authToken)->toBeString()->not->toBeEmpty();

        $auth = User::orderByDesc('id')->first();
        $this->assertEquals(1, $auth->tokens()->count());

        $response = $this->postJson($logoutRoute);
        $response->assertUnauthorized();

        $this->withToken($authToken);

        if (Features::enabled(Features::emailVerification())) {
            $route = route('verification.send');
            $response = $this->postJson($route);
            $response->assertAccepted();

            /** @var \Illuminate\Mail\MailManager $mailManager */
            $mailManager = \Mail::getFacadeRoot();
            /** @var \Illuminate\Mail\Mailer $arrayMailer */
            $arrayMailer = $mailManager->mailer('array');
            /** @var \Illuminate\Mail\Transport\ArrayTransport $arrayTransport */
            $arrayTransport = $arrayMailer->getSymfonyTransport();
            /** @var \Symfony\Component\Mailer\SentMessage $sentMessage */
            $sentMessage = $arrayTransport->messages()[0];
            /** @var \Symfony\Component\Mime\Email $mimeEmail */
            $mimeEmail = $sentMessage->getOriginalMessage();
            preg_match('~"http://localhost/email/verify/(.+?)/(.+?)(\?.+?)?"~', $mimeEmail->getHtmlBody(), $matches);
            $verifyRoute = route('verification.verify', ['id' => html_entity_decode($matches[1]), 'hash' => html_entity_decode($matches[2])]) . html_entity_decode($matches[3] ?? '');
            $response = $this->getJson($verifyRoute);
            $response->assertNoContent();
        }

        $response = $this->postJson($logoutRoute);
        $response->assertNoContent();

        $this->assertEquals(0, $auth->tokens()->count());
    }

    $loginRoute = route('login.store');
    $response = $this->postJson($loginRoute);
    $response->assertUnprocessable();

    $response = $this->postJson($loginRoute, [
        'email' => $auth->email,
        'password' => $password,
    ]);
    $response->assertUnprocessable();

    $response = $this->postJson($loginRoute, $credentials = [
        'email' => $auth->email,
        'password' => $password,
        'device_name' => 'loco device',
    ]);
    $response->assertOk();
    $response->assertJsonPath('two_factor', false);
    if ($isUsingStateless) {
        $response->assertHeaderMissing('Set-Cookie');
        $response->assertCookieMissing(config('session.cookie'));
    }
    $authToken = $response->headers->get('auth-token');
    expect($authToken)->toBeString()->not->toBeEmpty();
    $this->assertEquals(1, $auth->tokens()->count());
    // $this->assertAuthenticatedAs($auth);

    $this->withToken($authToken);

    $response = $this->postJson($logoutRoute);
    $response->assertNoContent();
    $this->assertEquals(0, $auth->tokens()->count());

    $this->withoutToken();

    $response = $this->postJson($logoutRoute);
    $response->assertUnauthorized();

    if (Features::enabled(Features::twoFactorAuthentication())) {
        $response = $this->postJson($loginRoute, $credentials);
        $response->assertOk();
        $response->assertJsonPath('two_factor', false);
        $authToken = $response->headers->get('auth-token');
        expect($authToken)->toBeString()->not->toBeEmpty();

        $this->withToken($authToken);

        $twoFactorEnableRoute = route('two-factor.enable');

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $response = $this->postJson($twoFactorEnableRoute);
            $response->assertStatus(423);

            $response = $this->postJson(route('password.confirm.store'));
            $response->assertUnprocessable();

            $response = $this->postJson(route('password.confirm.store'), ['password' => $password]);
            $response->assertCreated();
            if ($isUsingStateless) {
                $response->assertHeaderMissing('Set-Cookie');
                $response->assertCookieMissing(config('session.cookie'));
                $sessionId = $response->headers->get('temporary-session-id');
                expect($sessionId)->toBeString()->not->toBeEmpty();

                $this->withHeader('temporary-session-id', $sessionId);
            }
        }

        /**
         * Based on {@see TwoFactorAuthenticationController::store()}
         * Also {@see EnableTwoFactorAuthentication::__invoke()}
         */
        $response = $this->postJson($twoFactorEnableRoute);
        $response->assertOk();

        $auth->refresh(); // Load new two_factor_secret
        $twoFactorEngine = app(Google2FA::class);
        /** Because of timestamp check in {@see TwoFactorAuthenticationProvider::verify()} we have to clear cache
         * before we can generate new OTP (`resolve(Repository::class)->clear()`).
         * Based on @see AuthenticatedSessionControllerTest::test_two_factor_challenge_can_be_passed_via_code()()
         * See https://github.com/laravel/fortify/blob/ebc9045ef7bd2a2a37d54dde9c5df7d1b9d48780/tests/AuthenticatedSessionControllerTest.php#L277 */
        $validOtp = $twoFactorEngine->getCurrentOtp(decrypt($auth->two_factor_secret));

        $response = $this->postJson($logoutRoute);
        $response->assertNoContent();
        $this->assertEquals(0, $auth->tokens()->count());

        if (Fortify::confirmsTwoFactorAuthentication()) {
            $this->withoutToken();

            $response = $this->postJson($loginRoute, $credentials);
            $response->assertOk();
            // 2FA still disabled because should be confirmed.
            $response->assertJsonPath('two_factor', false);
            $authToken = $response->headers->get('auth-token');

            $this->withToken($authToken);

            $twoFactorConfirmRoute = route('two-factor.confirm');

            if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
                $response = $this->postJson($twoFactorConfirmRoute, ['code' => 'some bad code']);
                $response->assertStatus(423);

                $response = $this->postJson(route('password.confirm.store'));
                $response->assertUnprocessable();

                $response = $this->postJson(route('password.confirm.store'), ['password' => $password]);
                $response->assertCreated();
                if ($isUsingStateless) {
                    $response->assertHeaderMissing('Set-Cookie');
                    $response->assertCookieMissing(config('session.cookie'));
                    $sessionId = $response->headers->get('temporary-session-id');
                    expect($sessionId)->toBeString()->not->toBeEmpty();

                    $this->withHeader('temporary-session-id', $sessionId);
                }
            }

            /**
             * Based on {@see ConfirmedTwoFactorAuthenticationController::store()}
             * Also {@see ConfirmTwoFactorAuthentication::__invoke()}
             */
            $response = $this->postJson($twoFactorConfirmRoute, ['code' => 'some bad code']);
            $response->assertUnprocessable();
            if ($isUsingStateless) {
                // Because session id regenerated every time it's used.
                $sessionId = $response->headers->get('temporary-session-id');
                expect($sessionId)->toBeString()->not->toBeEmpty();

                $this->withHeader('temporary-session-id', $sessionId);
            }

            $response = $this->postJson($twoFactorConfirmRoute, ['code' => $validOtp]);
            $response->assertOk();
            /** So we can use $validOtp again {@see TwoFactorAuthenticationProvider::verify()}. */
            resolve(Repository::class)->clear();

            $response = $this->postJson($logoutRoute);
            $response->assertNoContent();
            $this->assertEquals(0, $auth->tokens()->count());
        }

        $this->withoutToken();

        $response = $this->postJson($loginRoute, $credentials);
        $response->assertOk();
        $response->assertJsonPath('two_factor', true);
        $response->assertHeaderMissing('auth-token');
        if ($isUsingStateless) {
            $sessionId = $response->headers->get('temporary-session-id');
            expect($sessionId)->toBeString()->not->toBeEmpty();
        }

        $twoFactorLoginRoute = route('two-factor.login.store');
        $response = $this->postJson($twoFactorLoginRoute, ['code' => 'some bad code']);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['device_name']);

        $response = $this->postJson($twoFactorLoginRoute, [
            'code' => 'some bad code',
            'device_name' => 'correct device',
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
        $response->assertJsonMissingValidationErrors(['device_name']);

        if ($isUsingStateless) {
            // No (deleted) session-id passed
            $response = $this->postJson($twoFactorLoginRoute, [
                'code' => $validOtp,
                'device_name' => 'correct device',
            ]);
            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['code']);
            $response->assertJsonMissingValidationErrors(['device_name']);

            /** So we can use $validOtp again {@see TwoFactorAuthenticationProvider::verify()}. */
            resolve(Repository::class)->clear();

            $this->withHeader('temporary-session-id', $sessionId);
        }

        $response = $this->postJson($twoFactorLoginRoute, [
            'code' => $validOtp,
            'device_name' => 'correct device',
        ]);
        $response->assertNoContent();
        $authToken = $response->headers->get('auth-token');
        expect($authToken)->toBeString()->not->toBeEmpty();
        $this->assertEquals(1, $auth->tokens()->count());
    }
});
