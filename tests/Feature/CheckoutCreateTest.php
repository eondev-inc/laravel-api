<?php

use App\Contracts\Payments\TransbankGateway;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('POST /api/checkout', function () {
    it('creates a pending order and returns token_ws and url', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $product = Product::factory()->create(['price' => 1000]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 2,
            'unit_price' => 1000,
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('create')
            ->once()
            ->andReturn(['token' => 'tok_test_123', 'url' => 'https://webpay.cl/pay']);
        $this->app->instance(TransbankGateway::class, $mock);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response->assertStatus(200)
            ->assertJsonStructure(['token_ws', 'url']);

        expect($response->json('token_ws'))->toBe('tok_test_123');
        expect($response->json('url'))->toBe('https://webpay.cl/pay');

        $order = Order::where('user_id', $user->id)->first();
        expect($order)->not->toBeNull();
        expect($order->status)->toBe('pending');
        expect($order->items()->count())->toBe(1);

        expect($cart->fresh()->status)->toBe('converted');
    });

    it('rolls back on TransbankGateway failure — no order persisted, cart unchanged', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $product = Product::factory()->create(['price' => 500]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'price' => null]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Transbank unavailable'));
        $this->app->instance(TransbankGateway::class, $mock);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response->assertStatus(422);

        expect(Order::where('user_id', $user->id)->count())->toBe(0);
        expect($cart->fresh()->status)->toBe('active');
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/checkout')->assertStatus(401);
    });

    it('returns 422 when cart has no items', function () {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout')->assertStatus(422);
    });
});
