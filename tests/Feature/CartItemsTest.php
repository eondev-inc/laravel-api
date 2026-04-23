<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Design;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─── POST /api/cart/items ─────────────────────────────────────────────────────

describe('POST /api/cart/items — price calculation', function () {
    it('calculates unit_price as variation price when no design', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_variation_id' => $variation->uuid,
        ])->assertSuccessful();

        $cart = Cart::where('user_id', $user->id)->first();
        expect($cart->items->first()->unit_price)->toBe('100.00');
    });

    it('adds design price_modifier to unit_price', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        $design = Design::factory()->create(['product_id' => $product->id, 'user_id' => $user->id, 'price_modifier' => 20]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_variation_id' => $variation->uuid,
            'design_id' => $design->uuid,
        ])->assertSuccessful();

        $cart = Cart::where('user_id', $user->id)->first();
        expect($cart->items->first()->unit_price)->toBe('120.00');
    });
});

describe('POST /api/cart/items — stacking', function () {
    it('increments quantity when adding same variation and design', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['price' => 50]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'design_id' => null,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_variation_id' => $variation->uuid,
            'quantity' => 2,
        ])->assertSuccessful();

        expect($cart->fresh()->items()->count())->toBe(1);
        expect($cart->fresh()->items()->first()->quantity)->toBe(3);
    });

    it('creates new row when variation is same but design differs', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['price' => 50]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        $design1 = Design::factory()->create(['product_id' => $product->id, 'user_id' => $user->id, 'price_modifier' => 0]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'design_id' => $design1->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        $design2 = Design::factory()->create(['product_id' => $product->id, 'user_id' => $user->id, 'price_modifier' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_variation_id' => $variation->uuid,
            'design_id' => $design2->uuid,
        ])->assertSuccessful();

        expect($cart->fresh()->items()->count())->toBe(2);
    });
});

// ─── PUT /api/cart/items/{cartItem} ──────────────────────────────────────────

describe('PUT /api/cart/items/{cartItem}', function () {
    it('updates item quantity', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $variation = ProductVariation::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/cart/items/{$item->uuid}", ['quantity' => 5])
            ->assertStatus(200);

        expect($item->fresh()->quantity)->toBe(5);
    });

    it('returns 403 when item does not belong to current cart', function () {
        $user = User::factory()->create();
        $otherCart = Cart::factory()->create();
        $variation = ProductVariation::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/cart/items/{$item->uuid}", ['quantity' => 3])
            ->assertStatus(403);
    });
});

// ─── DELETE /api/cart/items/{cartItem} ───────────────────────────────────────

describe('DELETE /api/cart/items/{cartItem}', function () {
    it('removes an item from the cart', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $variation = ProductVariation::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/cart/items/{$item->uuid}")
            ->assertStatus(200);

        expect($cart->fresh()->items()->count())->toBe(0);
    });
});
