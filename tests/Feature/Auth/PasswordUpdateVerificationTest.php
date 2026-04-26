<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

describe('Password update with current password verification', function () {
    it('rejects password update when current_password is wrong with 422', function () {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('correct-password'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    });

    it('rejects password update without current_password field with 422', function () {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/password', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    });

    it('updates password successfully when current_password is correct', function () {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('correct-password'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/password', [
            'current_password' => 'correct-password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password updated successfully.']);

        // Verify the password was actually changed
        $user->refresh();
        expect(Hash::check('new-secure-password-123', $user->password))->toBeTrue();
    });

    it('rejects request when not authenticated with 401', function () {
        $response = $this->postJson('/api/user/password', [
            'current_password' => 'any-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(401);
    });
});
