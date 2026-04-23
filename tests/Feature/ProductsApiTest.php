<?php

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->catalogPermission = Permission::create(['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo']);
    $this->adminRole->permissions()->attach($this->catalogPermission->id);
});

// ─── GET /api/products ────────────────────────────────────────────────────────

describe('GET /api/products', function () {
    it('returns active products without authentication', function () {
        Product::factory(2)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(2);
    });

    it('returns uuid as data.id', function () {
        Product::factory()->create();

        $response = $this->getJson('/api/products')->assertStatus(200);
        $item = $response->json('data.0');

        expect($item['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

// ─── POST /api/products ───────────────────────────────────────────────────────

describe('POST /api/products', function () {
    it('creates product when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $category = Category::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/products', [
            'name' => 'Cool Mug',
            'slug' => 'cool-mug',
            'price' => 19.99,
            'category_id' => $category->uuid,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Cool Mug');
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);
        $category = Category::factory()->create();

        Sanctum::actingAs($viewer);

        $this->postJson('/api/products', [
            'name' => 'X',
            'slug' => 'x',
            'price' => 10,
            'category_id' => $category->uuid,
        ])->assertStatus(403);
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/products', [])->assertStatus(401);
    });

    it('returns 422 for missing required fields', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'price', 'category_id']);
    });
});

// ─── DELETE /api/products/{product} ──────────────────────────────────────────

describe('DELETE /api/products/{product}', function () {
    it('deletes product when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/products/{$product->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Product deleted.');
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/products/{$product->uuid}")->assertStatus(403);
    });
});
