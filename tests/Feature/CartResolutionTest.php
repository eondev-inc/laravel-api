<?php

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// ─── GET /api/cart ─────────────────────────────────────────────────────────────

describe('GET /api/cart — authenticated', function () {
    it('creates a cart for user if none exists and returns it', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart')->assertSuccessful();

        expect($response->json('data.id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        expect(Cart::where('user_id', $user->id)->count())->toBe(1);
    });

    it('returns existing cart for authenticated user', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart')->assertStatus(200);

        expect($response->json('data.id'))->toBe($cart->uuid);
        expect(Cart::where('user_id', $user->id)->count())->toBe(1);
    });
});

describe('GET /api/cart — guest', function () {
    it('creates a cart for guest using X-Cart-Session header', function () {
        $sessionUuid = Str::uuid()->toString();

        $response = $this->getJson('/api/cart', ['X-Cart-Session' => $sessionUuid])
            ->assertSuccessful();

        expect($response->json('data.id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        expect(Cart::where('session_uuid', $sessionUuid)->count())->toBe(1);
    });

    it('returns existing cart for guest using X-Cart-Session header', function () {
        $sessionUuid = Str::uuid()->toString();
        $cart = Cart::factory()->create(['session_uuid' => $sessionUuid, 'user_id' => null]);

        $response = $this->getJson('/api/cart', ['X-Cart-Session' => $sessionUuid])
            ->assertStatus(200);

        expect($response->json('data.id'))->toBe($cart->uuid);
    });

    it('returns 400 when no auth and no X-Cart-Session', function () {
        $this->getJson('/api/cart')->assertStatus(400);
    });
});
