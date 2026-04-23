<?php

use App\Contracts\Payments\TransbankGateway;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;

describe('GET|POST /api/checkout/commit', function () {
    it('updates order to paid and redirects to success URL on valid token', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => 'pending',
            'token_ws' => 'tok_valid_123',
            'total' => 2000,
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('commit')
            ->once()
            ->with('tok_valid_123')
            ->andReturn(['response_code' => 0, 'status' => 'AUTHORIZED', 'amount' => 2000]);
        $this->app->instance(TransbankGateway::class, $mock);

        $response = $this->getJson('/api/checkout/commit?token_ws=tok_valid_123');

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/success');
        expect($order->fresh()->status)->toBe('paid');
    });

    it('updates order to failed and redirects to failure URL on error response_code', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => 'pending',
            'token_ws' => 'tok_fail_456',
            'total' => 1000,
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('commit')
            ->once()
            ->with('tok_fail_456')
            ->andReturn(['response_code' => -1, 'status' => 'FAILED']);
        $this->app->instance(TransbankGateway::class, $mock);

        $response = $this->getJson('/api/checkout/commit?token_ws=tok_fail_456');

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/failure');
        expect($order->fresh()->status)->toBe('failed');
    });

    it('redirects to failure when token_ws is missing', function () {
        $response = $this->getJson('/api/checkout/commit');
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/failure');
    });

    it('handles POST callback from Transbank', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => 'pending',
            'token_ws' => 'tok_post_789',
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('commit')
            ->once()
            ->andReturn(['response_code' => 0, 'status' => 'AUTHORIZED']);
        $this->app->instance(TransbankGateway::class, $mock);

        $response = $this->postJson('/api/checkout/commit', ['token_ws' => 'tok_post_789']);
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/success');
    });
});
