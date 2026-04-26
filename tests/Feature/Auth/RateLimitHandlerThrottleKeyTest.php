<?php

use App\Auth\Chain\Authentication\RateLimitHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

describe('RateLimitHandler throttle key', function () {
    it('does not expose email in plain text in the throttle key', function () {
        $email = 'victim@example.com';
        $capturedKey = null;

        // Spy on RateLimiter to capture the key used
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturnUsing(function (string $key) use (&$capturedKey) {
                $capturedKey = $key;

                return false;
            });

        RateLimiter::shouldReceive('hit')->never();

        $request = Request::create('/', 'POST', ['email' => $email, 'password' => 'pass']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // passToNext returns true when no next handler
        $handler = new RateLimitHandler;
        $handler->handle($request);

        // The key must NOT contain the raw email
        expect($capturedKey)->not->toContain($email);
        expect($capturedKey)->not->toContain('victim');
    });

    it('throttle key contains a SHA256 hash of the email', function () {
        $email = 'hashed@example.com';
        $expectedHash = hash('sha256', strtolower($email));
        $capturedKey = null;

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturnUsing(function (string $key) use (&$capturedKey) {
                $capturedKey = $key;

                return false;
            });

        $request = Request::create('/', 'POST', ['email' => $email, 'password' => 'pass']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $handler = new RateLimitHandler;
        $handler->handle($request);

        expect($capturedKey)->toContain($expectedHash);
    });

    it('same email always produces the same throttle key (deterministic)', function () {
        $email = 'deterministic@example.com';
        $keys = [];

        RateLimiter::shouldReceive('tooManyAttempts')
            ->twice()
            ->andReturnUsing(function (string $key) use (&$keys) {
                $keys[] = $key;

                return false;
            });

        $request = Request::create('/', 'POST', ['email' => $email, 'password' => 'pass']);
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $handler = new RateLimitHandler;
        $handler->handle($request);
        $handler->handle($request);

        expect($keys[0])->toBe($keys[1]);
    });

    it('error message does not contain closing parenthesis typo', function () {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(true);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->andReturn(60);

        $request = Request::create('/', 'POST', ['email' => 'test@example.com', 'password' => 'pass']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $handler = new RateLimitHandler;
        $result = $handler->handle($request);

        $message = $result->getData(true)['message'];

        // 4.5: Fix typo — should NOT end with '.)'
        expect($message)->not->toEndWith('.)');
        expect($message)->toEndWith('.');
    });
});
