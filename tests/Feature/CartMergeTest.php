<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// ─── POST /api/cart/merge ─────────────────────────────────────────────────────

describe('POST /api/cart/merge', function () {
    it('merges guest cart items into user cart', function () {
        $user = User::factory()->create();
        $userCart = Cart::factory()->create(['user_id' => $user->id]);
        $sessionUuid = Str::uuid()->toString();
        $guestCart = Cart::factory()->create(['session_uuid' => $sessionUuid, 'user_id' => null]);
        $variation = ProductVariation::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $guestCart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 2,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/merge', [], ['X-Cart-Session' => $sessionUuid])
            ->assertStatus(200);

        expect($userCart->fresh()->items()->count())->toBe(1);
        expect($userCart->fresh()->items()->first()->quantity)->toBe(2);
        expect($guestCart->fresh()->status)->toBe('abandoned');
    });

    it('stacks quantities for overlapping items on merge', function () {
        $user = User::factory()->create();
        $userCart = Cart::factory()->create(['user_id' => $user->id]);
        $sessionUuid = Str::uuid()->toString();
        $guestCart = Cart::factory()->create(['session_uuid' => $sessionUuid, 'user_id' => null]);
        $variation = ProductVariation::factory()->create();

        // user already has 1 of this variation
        CartItem::factory()->create([
            'cart_id' => $userCart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        // guest has 3 of same variation
        CartItem::factory()->create([
            'cart_id' => $guestCart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 3,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/merge', [], ['X-Cart-Session' => $sessionUuid])
            ->assertStatus(200);

        expect($userCart->fresh()->items()->count())->toBe(1);
        expect($userCart->fresh()->items()->first()->quantity)->toBe(4); // 1 + 3
    });

    it('returns 401 when not authenticated', function () {
        $sessionUuid = Str::uuid()->toString();

        $this->postJson('/api/cart/merge', [], ['X-Cart-Session' => $sessionUuid])
            ->assertStatus(401);
    });

    it('returns 400 when X-Cart-Session header is missing', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/merge')->assertStatus(400);
    });

    it('returns 404 when guest cart does not exist', function () {
        $user = User::factory()->create();
        $sessionUuid = Str::uuid()->toString();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/merge', [], ['X-Cart-Session' => $sessionUuid])
            ->assertStatus(404);
    });
});
