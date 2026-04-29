<?php

use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->ordersView = Permission::create(['name' => 'orders.view', 'display_name' => 'Ver órdenes']);
    $this->ordersManage = Permission::create(['name' => 'orders.manage', 'display_name' => 'Gestionar estado de órdenes']);

    $this->adminRole->permissions()->attach([$this->ordersView->id, $this->ordersManage->id]);
});

// ─── GET /api/orders ──────────────────────────────────────────────────────────

describe('GET /api/orders', function () {
    it('returns only the authenticated user orders', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory(2)->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(2);
    });

    it('returns empty array when user has no orders', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(0);
    });

    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/orders')->assertStatus(401);
    });
});

// ─── GET /api/orders/{order} ─────────────────────────────────────────────────

describe('GET /api/orders/{order}', function () {
    it('user can view their own order with items', function () {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $product->id]);

        $order->items()->create([
            'product_variation_id' => $variation->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'line_total' => 2000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/orders/{$order->uuid}")->assertStatus(200);

        expect($response->json('data.id'))->toEqual($order->uuid);
        expect($response->json('data.items'))->toHaveCount(1);
    });

    it('returns 403 when trying to view another user order', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->uuid}")->assertStatus(403);
    });
});

// ─── GET /api/admin/orders ───────────────────────────────────────────────────

describe('GET /api/admin/orders', function () {
    it('admin can list all orders', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        Order::factory(3)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);
    });

    it('admin can filter by status', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        Order::factory()->create(['status' => 'paid']);
        Order::factory()->create(['status' => 'pending']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/orders?status=paid')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.status'))->toBe('paid');
    });

    it('returns 403 when non-admin without orders.view', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/admin/orders')->assertStatus(403);
    });
});

// ─── GET /api/admin/orders/{order} ──────────────────────────────────────────

describe('GET /api/admin/orders/{order}', function () {
    it('admin can view any order', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $order = Order::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/orders/{$order->uuid}")->assertStatus(200);
    });
});

// ─── PATCH /api/admin/orders/{order}/status ─────────────────────────────────

describe('PATCH /api/admin/orders/{order}/status', function () {
    it('admin can update status to shipped', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $order = Order::factory()->create(['status' => 'paid']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->uuid}/status", ['status' => 'shipped'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'shipped');
    });

    it('returns 422 for invalid status value', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $order = Order::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->uuid}/status", ['status' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 404 when order not found', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/orders/00000000-0000-0000-0000-000000000000/status', ['status' => 'shipped'])
            ->assertStatus(404);
    });
});
