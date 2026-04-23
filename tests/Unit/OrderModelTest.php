<?php

use App\Models\Order;
use App\Models\OrderItem;

describe('Order model', function () {
    it('uses HasUuids with uuid as route key', function () {
        $order = new Order;
        expect($order->getRouteKeyName())->toBe('uuid');
        expect(in_array('uuid', $order->uniqueIds()))->toBeTrue();
    });

    it('has expected fillable fields via PHP8 Fillable attribute', function () {
        $order = new Order;
        expect($order->getFillable())->toContain('user_id')
            ->toContain('cart_id')
            ->toContain('status')
            ->toContain('subtotal')
            ->toContain('total')
            ->toContain('token_ws')
            ->toContain('webpay_url');
    });

    it('casts total and subtotal as decimal', function () {
        $order = new Order;
        $casts = $order->getCasts();
        expect($casts)->toHaveKey('subtotal')
            ->toHaveKey('total');
    });
});

describe('OrderItem model', function () {
    it('uses HasUuids with uuid as route key', function () {
        $item = new OrderItem;
        expect($item->getRouteKeyName())->toBe('uuid');
        expect(in_array('uuid', $item->uniqueIds()))->toBeTrue();
    });

    it('has expected fillable fields', function () {
        $item = new OrderItem;
        expect($item->getFillable())->toContain('order_id')
            ->toContain('product_variation_id')
            ->toContain('design_id')
            ->toContain('quantity')
            ->toContain('unit_price')
            ->toContain('line_total');
    });
});
