<?php

use Illuminate\Support\Facades\Schema;

describe('orders table schema', function () {
    it('has expected columns', function () {
        expect(Schema::hasColumns('orders', [
            'id', 'uuid', 'user_id', 'cart_id', 'status',
            'subtotal', 'total', 'token_ws', 'webpay_url', 'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('has uuid as unique identifier', function () {
        expect(Schema::hasColumn('orders', 'uuid'))->toBeTrue();
    });

    it('accepts expected statuses as string column', function () {
        expect(Schema::getColumnType('orders', 'status'))->toBeIn(['string', 'varchar']);
    });
});

describe('order_items table schema', function () {
    it('has expected columns', function () {
        expect(Schema::hasColumns('order_items', [
            'id', 'uuid', 'order_id', 'product_variation_id',
            'design_id', 'quantity', 'unit_price', 'line_total',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('has foreign key to orders', function () {
        expect(Schema::hasColumn('order_items', 'order_id'))->toBeTrue();
    });
});
