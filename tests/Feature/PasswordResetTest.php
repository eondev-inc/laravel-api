<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

describe('POST /api/forgot-password', function () {
    it('returns 200 (generic message) when email exists', function () {
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertStatus(200)
            ->assertJsonPath('message', 'If an account exists with that email, we have sent a password reset link.');
    });

    it('returns 200 (generic message) when email does NOT exist — prevents enumeration', function () {
        $this->postJson('/api/forgot-password', ['email' => 'nonexistent@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('message', 'If an account exists with that email, we have sent a password reset link.');
    });

    it('returns 422 when email is missing', function () {
        $this->postJson('/api/forgot-password', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('returns 422 when email is invalid format', function () {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

describe('POST /api/reset-password', function () {
    it('resets password with valid token', function () {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Password reset successfully.');
    });

    it('returns 422 for invalid token', function () {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertStatus(422);
    });

    it('returns 422 when password is too short', function () {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('returns 422 when password_confirmation does not match', function () {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });
});
