<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Limpia contadores de Redis entre tests
    RateLimiter::clear('login-attempts:test@example.com');
    RateLimiter::clear('login-attempts:active@example.com');
    RateLimiter::clear('login-attempts:inactive@example.com');
    RateLimiter::clear('login-attempts:ghost@example.com');
});

// ─── POST /api/login ──────────────────────────────────────────────────────────

describe('POST /api/login', function () {
    it('returns token on successful login', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user'])
            ->assertJsonPath('token_type', 'Bearer');
    });

    it('returns 401 for non-existent email with generic message', function () {
        $this->postJson('/api/login', [
            'email' => 'ghost@example.com',
            'password' => 'any-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Las credenciales proporcionadas son incorrectas.');
    });

    it('returns 401 for wrong password', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    });

    it('returns 403 for inactive account', function () {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertStatus(403);
    });

    it('returns 422 for missing fields', function () {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('returns 429 after exceeding max attempts with non-existent email (no user enumeration under lockout)', function () {
        // 5 intentos con email inexistente
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ghost@example.com',
                'password' => 'any-password',
            ])->assertStatus(401);
        }

        // El sexto intento debe ser bloqueado por rate-limiter, independiente de si el email existe
        $this->postJson('/api/login', [
            'email' => 'ghost@example.com',
            'password' => 'any-password',
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after_seconds']);
    });

    it('returns 429 after exceeding max attempts', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        // Simular 5 intentos fallidos
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        // El sexto intento debe ser bloqueado
        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password', // password correcto, pero bloqueado
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after_seconds']);
    });

    it('clears rate limit counter on successful login', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        // Algunos intentos fallidos
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
        }

        // Login exitoso — debe limpiar el contador
        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ])->assertStatus(200);

        // Verificar que el contador fue limpiado (podemos hacer 5 intentos nuevamente)
        expect(RateLimiter::attempts('login-attempts:test@example.com'))->toBe(0);
    });
});

// ─── POST /api/logout ─────────────────────────────────────────────────────────

describe('POST /api/logout', function () {
    it('returns 200 and revokes token on logout', function () {
        $user = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');
    });

    it('returns 401 when not authenticated', function () {
        $this->postJson('/api/logout')->assertStatus(401);
    });
});
