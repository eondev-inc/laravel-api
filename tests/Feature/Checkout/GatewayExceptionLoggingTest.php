<?php

use App\Contracts\Payments\TransbankGateway;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

describe('Checkout gateway exception logging', function () {
    it('returns generic error and does not expose SDK details when gateway throws during create', function () {
        $user = User::factory()->create(['is_active' => true]);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $product->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $gateway = Mockery::mock(TransbankGateway::class);
        $gateway->shouldReceive('create')->andThrow(new RuntimeException('Transbank SDK: internal connection timeout xyz123'));
        app()->instance(TransbankGateway::class, $gateway);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/checkout');

        $response->assertStatus(422);
        $body = $response->getContent();

        // Client must NOT see SDK internals
        expect($body)->not->toContain('Transbank SDK');
        expect($body)->not->toContain('connection timeout xyz123');
        expect($body)->not->toContain('internal connection timeout');
    });

    it('redirects to failure and does not expose SDK details when gateway throws during commit', function () {
        Order::factory()->create([
            'status' => 'pending',
            'token_ws' => 'test-token-abc',
        ]);

        $gateway = Mockery::mock(TransbankGateway::class);
        $gateway->shouldReceive('commit')->andThrow(new RuntimeException('Internal SDK error details xyz'));
        app()->instance(TransbankGateway::class, $gateway);

        $response = $this->post('/api/checkout/commit', ['token_ws' => 'test-token-abc']);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        // Redirect to failure — not exposing error in URL
        expect($redirectUrl)->toContain('failure');
        expect($redirectUrl)->not->toContain('Internal SDK error');
    });

    it('logs error with exception context when gateway throws during create', function () {
        $user = User::factory()->create(['is_active' => true]);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $product->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_variation_id' => $variation->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $gateway = Mockery::mock(TransbankGateway::class);
        $gateway->shouldReceive('create')->andThrow(new RuntimeException('SDK error'));
        app()->instance(TransbankGateway::class, $gateway);

        $logged = false;
        Log::listen(function ($log) use (&$logged) {
            if ($log->level === 'error' && isset($log->context['exception'])) {
                $logged = true;
            }
        });

        Sanctum::actingAs($user);
        $this->postJson('/api/checkout');

        expect($logged)->toBeTrue();
    });

    it('order is marked as failed when gateway throws during commit', function () {
        $order = Order::factory()->create([
            'status' => 'pending',
            'token_ws' => 'test-token-xyz',
        ]);

        $gateway = Mockery::mock(TransbankGateway::class);
        $gateway->shouldReceive('commit')->andThrow(new RuntimeException('Gateway unreachable'));
        app()->instance(TransbankGateway::class, $gateway);

        $this->post('/api/checkout/commit', ['token_ws' => 'test-token-xyz']);

        $order->refresh();
        expect($order->status)->toBe('failed');
    });
});
