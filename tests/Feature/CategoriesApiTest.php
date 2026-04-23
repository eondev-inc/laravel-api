<?php

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->catalogPermission = Permission::create(['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo']);
    $this->adminRole->permissions()->attach($this->catalogPermission->id);
});

// ─── GET /api/categories ──────────────────────────────────────────────────────

describe('GET /api/categories', function () {
    it('returns active categories without authentication', function () {
        Category::factory(3)->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $this->getJson('/api/categories')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);

        $response = $this->getJson('/api/categories');
        expect($response->json('data'))->toHaveCount(3);
    });

    it('returns uuid as data.id (no numeric id exposed)', function () {
        Category::factory()->create();

        $response = $this->getJson('/api/categories')->assertStatus(200);
        $item = $response->json('data.0');

        expect($item['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        expect($item)->not->toHaveKey('numeric_id');
    });
});

// ─── POST /api/categories ─────────────────────────────────────────────────────

describe('POST /api/categories', function () {
    it('creates category when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/categories', [
            'name' => 'T-Shirts',
            'slug' => 't-shirts',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'T-Shirts');
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/categories', ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(401);
    });

    it('returns 403 when non-admin authenticated user', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);

        Sanctum::actingAs($viewer);

        $this->postJson('/api/categories', ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(403);
    });

    it('returns 422 for missing required fields', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/categories', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    });
});

// ─── PUT /api/categories/{category} ──────────────────────────────────────────

describe('PUT /api/categories/{category}', function () {
    it('updates category when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $category = Category::factory()->create(['name' => 'Old']);

        Sanctum::actingAs($admin);

        $this->putJson("/api/categories/{$category->uuid}", ['name' => 'New'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New');
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);
        $category = Category::factory()->create();

        Sanctum::actingAs($viewer);

        $this->putJson("/api/categories/{$category->uuid}", ['name' => 'Hack'])
            ->assertStatus(403);
    });
});

// ─── DELETE /api/categories/{category} ───────────────────────────────────────

describe('DELETE /api/categories/{category}', function () {
    it('deletes category when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $category = Category::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/categories/{$category->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Category deleted.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    });

    it('returns 401 when unauthenticated', function () {
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->uuid}")->assertStatus(401);
    });
});
