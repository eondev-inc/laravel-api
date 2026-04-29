<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->catalogPermission = Permission::create(['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo']);
    $this->adminRole->permissions()->attach($this->catalogPermission->id);
});

describe('GET /api/products/{product}/variations', function () {
    it('returns active variations without authentication', function () {
        $product = Product::factory()->create();
        ProductVariation::factory(2)->create(['product_id' => $product->id, 'is_active' => true]);
        ProductVariation::factory()->create(['product_id' => $product->id, 'is_active' => false]);

        $response = $this->getJson("/api/products/{$product->uuid}/variations")->assertStatus(200);

        expect($response->json('data'))->toHaveCount(2);
    });

    it('returns uuid as data.id', function () {
        $product = Product::factory()->create();
        ProductVariation::factory()->create(['product_id' => $product->id]);

        $response = $this->getJson("/api/products/{$product->uuid}/variations")->assertStatus(200);
        $item = $response->json('data.0');

        expect($item['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('returns 404 when product not found', function () {
        $this->getJson('/api/products/00000000-0000-0000-0000-000000000000/variations')
            ->assertStatus(404);
    });
});

describe('GET /api/products/{product}/variations/{variation}', function () {
    it('returns variation details', function () {
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $product->id]);

        $response = $this->getJson("/api/products/{$product->uuid}/variations/{$variation->uuid}")
            ->assertStatus(200);

        expect($response->json('data.id'))->toEqual($variation->uuid);
    });

    it('returns 404 when variation belongs to different product', function () {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $otherProduct->id]);

        $this->getJson("/api/products/{$product->uuid}/variations/{$variation->uuid}")
            ->assertStatus(404);
    });
});

describe('POST /api/products/{product}/variations', function () {
    it('creates variation when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/products/{$product->uuid}/variations", [
            'name' => 'Large',
            'stock' => 10,
            'price' => 29.99,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Large');
    });

    it('returns 401 when unauthenticated', function () {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->uuid}/variations", [
            'name' => 'X',
            'stock' => 1,
        ])->assertStatus(401);
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($viewer);

        $this->postJson("/api/products/{$product->uuid}/variations", [
            'name' => 'X',
            'stock' => 1,
        ])->assertStatus(403);
    });

    it('returns 422 for missing required fields', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/products/{$product->uuid}/variations", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'stock']);
    });

    it('returns 422 when stock is negative', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/products/{$product->uuid}/variations", [
            'name' => 'X',
            'stock' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);
    });
});

describe('PUT /api/products/{product}/variations/{variation}', function () {
    it('updates variation when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create([
            'product_id' => $product->id,
            'name' => 'Old',
            'stock' => 5,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/products/{$product->uuid}/variations/{$variation->uuid}", [
            'name' => 'Updated',
            'stock' => 20,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('data.stock', 20);
    });

    it('supports partial update', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create([
            'product_id' => $product->id,
            'name' => 'Original',
            'stock' => 5,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/products/{$product->uuid}/variations/{$variation->uuid}", [
            'stock' => 50,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Original')
            ->assertJsonPath('data.stock', 50);
    });

    it('returns 404 when variation belongs to different product', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $otherProduct->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/products/{$product->uuid}/variations/{$variation->uuid}", [
            'name' => 'Hacked',
        ])->assertStatus(404);
    });
});

describe('DELETE /api/products/{product}/variations/{variation}', function () {
    it('deletes variation when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();
        $variation = ProductVariation::factory()->create(['product_id' => $product->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/products/{$product->uuid}/variations/{$variation->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Variation deleted.');

        $this->assertDatabaseMissing('product_variations', ['id' => $variation->id]);
    });

    it('returns 404 when variation not found', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/products/{$product->uuid}/variations/00000000-0000-0000-0000-000000000000")
            ->assertStatus(404);
    });
});
