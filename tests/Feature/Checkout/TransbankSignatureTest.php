<?php

use App\Contracts\Payments\TransbankGateway;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

describe('TBK_MAC signature verification', function () {
    it('rejects request with TBK_TOKEN but without TBK_MAC with 400', function () {
        // Transbank cancellation callback without MAC = invalid
        $response = $this->postJson('/api/checkout/commit', [
            'TBK_TOKEN' => 'some_tbk_token',
            'TBK_ORDEN_COMPRA' => 'ORDER-123',
            'TBK_ID_SESSION' => 'SESSION-123',
            // No TBK_MAC provided
        ]);

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/failure');
    });

    it('rejects TBK_MAC cancellation with invalid signature', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => 'pending',
            'token_ws' => null,
        ]);

        $response = $this->postJson('/api/checkout/commit', [
            'TBK_TOKEN' => 'fake_tbk_token',
            'TBK_ORDEN_COMPRA' => (string) $order->id,
            'TBK_ID_SESSION' => (string) $order->uuid,
            'TBK_MAC' => 'invalid_mac_signature',
        ]);

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/failure');
    });

    it('logs a warning when TBK_MAC signature verification fails', function () {
        Log::spy();

        $this->postJson('/api/checkout/commit', [
            'TBK_TOKEN' => 'some_token',
            'TBK_ORDEN_COMPRA' => 'ORDER-999',
            'TBK_ID_SESSION' => 'SESSION-999',
            'TBK_MAC' => 'bad_mac',
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'TBK_MAC'));
    });

    it('processes normal token_ws flow without TBK_MAC interference', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => 'pending',
            'token_ws' => 'tok_normal_flow',
        ]);

        $mock = Mockery::mock(TransbankGateway::class);
        $mock->shouldReceive('commit')
            ->once()
            ->with('tok_normal_flow')
            ->andReturn(['response_code' => 0, 'status' => 'AUTHORIZED', 'amount' => 5000]);
        $this->app->instance(TransbankGateway::class, $mock);

        $response = $this->getJson('/api/checkout/commit?token_ws=tok_normal_flow');
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/checkout/success');
    });
});
