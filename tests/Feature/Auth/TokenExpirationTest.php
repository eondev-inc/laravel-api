<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

describe('Token Expiration', function () {
    it('rejects an expired token with 401', function () {
        $user = User::factory()->create(['is_active' => true]);

        // Crear token con expiración en el pasado
        $token = $user->createToken('test-token');
        $token->accessToken->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/user')
            ->assertStatus(401);
    });

    it('accepts a valid non-expired token', function () {
        $user = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('id', $user->id);
    });
});

describe('POST /api/refresh', function () {
    it('returns a new token when current token is valid', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/refresh')
            ->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');
    });

    it('returns 401 when not authenticated', function () {
        $this->postJson('/api/refresh')
            ->assertStatus(401);
    });

    it('new token is different from the old token', function () {
        $user = User::factory()->create(['is_active' => true]);

        // Login to get initial token
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        $oldToken = $loginResponse->json('token');

        // Refresh
        $refreshResponse = $this->withToken($oldToken)
            ->postJson('/api/refresh')
            ->assertStatus(200);

        $newToken = $refreshResponse->json('token');

        expect($newToken)->not->toBe($oldToken);
        expect($newToken)->not->toBeEmpty();
    });

    it('old token is revoked after refresh', function () {
        $user = User::factory()->create(['is_active' => true]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        $oldToken = $loginResponse->json('token');

        // Contar tokens antes del refresh
        $tokensBefore = $user->tokens()->count();
        expect($tokensBefore)->toBe(1);

        // Refresh — debe revocar el token actual y emitir uno nuevo
        $this->withToken($oldToken)->postJson('/api/refresh')->assertStatus(200);

        // El usuario ahora debe tener exactamente 1 token (el nuevo, no el viejo)
        expect($user->fresh()->tokens()->count())->toBe(1);

        // El token viejo ya no existe en la DB
        $oldTokenId = explode('|', $oldToken)[0];
        expect(PersonalAccessToken::find((int) $oldTokenId))->toBeNull();
    });
});
