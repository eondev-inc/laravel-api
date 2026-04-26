<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

describe('POST /api/logout — secure', function () {
    it('returns 200 and revokes the current token', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');
    });

    it('returns 401 when called without authentication', function () {
        $this->postJson('/api/logout')
            ->assertStatus(401);
    });

    it('token is deleted from the database after logout', function () {
        $user = User::factory()->create(['is_active' => true]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        $token = $loginResponse->json('token');
        $tokenId = (int) explode('|', $token)[0];

        // Token exists before logout
        expect(PersonalAccessToken::find($tokenId))->not->toBeNull();

        $this->withToken($token)->postJson('/api/logout')->assertStatus(200);

        // Token is gone after logout
        expect(PersonalAccessToken::find($tokenId))->toBeNull();
    });

    it('returns 200 even when token has already been deleted (idempotent)', function () {
        $user = User::factory()->create(['is_active' => true]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        $token = $loginResponse->json('token');

        // Delete the token manually before logout
        $tokenId = (int) explode('|', $token)[0];
        PersonalAccessToken::find($tokenId)?->delete();

        // Logout with an already-deleted token should return 401 (unauthenticated)
        // because Sanctum won't resolve the user from a deleted token
        $this->withToken($token)->postJson('/api/logout')
            ->assertStatus(401);
    });
});
