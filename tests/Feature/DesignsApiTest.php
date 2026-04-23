<?php

use App\Models\Design;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->catalogPermission = Permission::create(['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo']);
    $this->adminRole->permissions()->attach($this->catalogPermission->id);
});

// ─── GET /api/designs ─────────────────────────────────────────────────────────

describe('GET /api/designs', function () {
    it('returns active designs without authentication', function () {
        Design::factory(2)->create(['is_active' => true]);
        Design::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/designs')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(2);
    });
});

// ─── POST /api/designs ────────────────────────────────────────────────────────

describe('POST /api/designs', function () {
    it('accepts valid png upload', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.png', 100, 'image/png');

        $this->postJson('/api/designs', [
            'name' => 'My Design',
            'product_id' => $product->uuid,
            'image' => $file,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'My Design')
            ->assertJsonPath('data.file_extension', 'png');

        Storage::disk('public')->assertExists(
            Design::where('name', 'My Design')->first()->file_path
        );
    });

    it('accepts valid jpg upload', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.jpg', 100, 'image/jpeg');

        $this->postJson('/api/designs', [
            'name' => 'JPG Design',
            'product_id' => $product->uuid,
            'image' => $file,
        ])->assertStatus(201);
    });

    it('accepts jpeg extension upload (jpg and jpeg are the same format)', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.jpeg', 100, 'image/jpeg');

        $this->postJson('/api/designs', [
            'name' => 'JPEG Design',
            'product_id' => $product->uuid,
            'image' => $file,
        ])->assertStatus(201);
    });

    it('rejects pdf upload with 422', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.pdf', 100, 'application/pdf');

        $this->postJson('/api/designs', [
            'name' => 'Bad Design',
            'product_id' => $product->uuid,
            'image' => $file,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    });

    it('rejects invalid mime type with 422', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.exe', 100, 'application/octet-stream');

        $this->postJson('/api/designs', [
            'name' => 'Virus',
            'product_id' => $product->uuid,
            'image' => $file,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    });

    it('returns 401 for unauthenticated upload', function () {
        $this->postJson('/api/designs', [])->assertStatus(401);
    });
});

// ─── DELETE /api/designs/{design} ────────────────────────────────────────────

describe('DELETE /api/designs/{design}', function () {
    it('deletes design when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);
        $design = Design::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/designs/{$design->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Design deleted.');
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);
        $design = Design::factory()->create();

        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/designs/{$design->uuid}")->assertStatus(403);
    });
});
