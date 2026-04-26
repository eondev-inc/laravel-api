<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

describe('Full Login Chain Integration', function () {
    it('successful login executes full chain and returns token', function () {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'user@example.com');
    });

    it('RateLimitHandler blocks after max attempts — short-circuit at first handler', function () {
        $user = User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => bcrypt('any-password'),
            'is_active' => true,
        ]);

        // Exhaust the rate limit
        $maxAttempts = (int) config('auth.login_max_attempts', 5);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->postJson('/api/login', [
                'email' => 'blocked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // Next attempt must be blocked with 429
        $response = $this->postJson('/api/login', [
            'email' => 'blocked@example.com',
            'password' => 'correct-password',  // Even correct password is blocked
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after_seconds']);
    });

    it('CredentialsValidationHandler rejects wrong password — returns 401', function () {
        User::factory()->create([
            'email' => 'exists@example.com',
            'password' => bcrypt('right-password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'exists@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Las credenciales proporcionadas son incorrectas.');
    });

    it('CredentialsValidationHandler rejects non-existent email — returns 401 generic message', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Las credenciales proporcionadas son incorrectas.');
    });

    it('AccountActiveHandler blocks inactive users after credentials pass — returns 403', function () {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('correct-password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(403);
    });

    it('chain order is RateLimitHandler → CredentialsValidationHandler → AccountActiveHandler', function () {
        // If rate limited, the 429 comes first — before credential check
        $user = User::factory()->create([
            'email' => 'order-test@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,  // Account is inactive
        ]);

        // Exhaust rate limiter
        $maxAttempts = (int) config('auth.login_max_attempts', 5);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->postJson('/api/login', [
                'email' => 'order-test@example.com',
                'password' => 'wrong',
            ]);
        }

        // Rate limited wins over account-inactive — proves RateLimitHandler runs first
        $response = $this->postJson('/api/login', [
            'email' => 'order-test@example.com',
            'password' => 'password',
        ]);

        // 429 from RateLimitHandler, not 403 from AccountActiveHandler
        $response->assertStatus(429);
    });

    it('failed login increments rate limit counter', function () {
        User::factory()->create([
            'email' => 'counter@example.com',
            'password' => bcrypt('real-password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'counter@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $hashedEmail = hash('sha256', 'counter@example.com');
        $key = 'login-attempts:'.$hashedEmail.'|127.0.0.1';

        expect(RateLimiter::attempts($key))->toBeGreaterThan(0);
    });

    it('successful login clears rate limit counter', function () {
        User::factory()->create([
            'email' => 'clear@example.com',
            'password' => bcrypt('correct-password'),
            'is_active' => true,
        ]);

        // First, fail once to increment counter
        $this->postJson('/api/login', [
            'email' => 'clear@example.com',
            'password' => 'wrong',
        ]);

        // Then succeed
        $this->postJson('/api/login', [
            'email' => 'clear@example.com',
            'password' => 'correct-password',
        ])->assertStatus(200);

        $hashedEmail = hash('sha256', 'clear@example.com');
        $key = 'login-attempts:'.$hashedEmail.'|127.0.0.1';

        expect(RateLimiter::attempts($key))->toBe(0);
    });
});
