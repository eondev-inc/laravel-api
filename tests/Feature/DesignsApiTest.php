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
    Storage::fake('s3_private');

    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $this->catalogPermission = Permission::create(['name' => 'catalog.manage', 'display_name' => 'Gestionar catálogo']);
    $this->adminRole->permissions()->attach($this->catalogPermission->id);

    // Permiso requerido para subir diseños
    $this->designerRole = Role::create(['name' => 'designer', 'display_name' => 'Diseñador']);
    $this->designCreatePermission = Permission::create(['name' => 'designs.create', 'display_name' => 'Crear diseños']);
    $this->designerRole->permissions()->attach($this->designCreatePermission->id);
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
        $user->roles()->attach($this->designerRole->id);
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

        Storage::disk('s3_private')->assertExists(
            Design::where('name', 'My Design')->first()->file_path
        );
    });

    it('accepts valid jpg upload', function () {
        $user = User::factory()->create();
        $user->roles()->attach($this->designerRole->id);
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
        $user->roles()->attach($this->designerRole->id);
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
        $user->roles()->attach($this->designerRole->id);
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
        $user->roles()->attach($this->designerRole->id);
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

        // Subir un archivo fake para que exista en s3_private
        $filePath = 'designs/test-file.png';
        Storage::disk('s3_private')->put($filePath, 'fake-content');
        $design = Design::factory()->create(['file_path' => $filePath]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/designs/{$design->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Design deleted.');

        Storage::disk('s3_private')->assertMissing($filePath);
    });

    it('returns 403 when non-admin', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);

        $filePath = 'designs/protected-file.png';
        Storage::disk('s3_private')->put($filePath, 'fake-content');
        $design = Design::factory()->create(['file_path' => $filePath]);

        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/designs/{$design->uuid}")->assertStatus(403);

        // El archivo debe seguir existiendo — no fue eliminado
        Storage::disk('s3_private')->assertExists($filePath);
    });
});

// ─── POST /api/designs — verificaciones adicionales de almacenamiento ─────────

describe('POST /api/designs — s3_private storage', function () {
    it('stores file_path in DB pointing to s3_private and does not store file_url', function () {
        $user = User::factory()->create();
        $user->roles()->attach($this->designerRole->id);
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('design.png', 100, 'image/png');

        $this->postJson('/api/designs', [
            'name' => 'S3 Design',
            'product_id' => $product->uuid,
            'image' => $file,
        ])->assertStatus(201);

        $design = Design::where('name', 'S3 Design')->first();

        expect($design->file_path)->toStartWith('designs/')
            ->and($design->file_path)->toEndWith('.png');

        // file_url column fue eliminada en Phase 2 — no debe existir en el modelo
        expect($design->toArray())->not->toHaveKey('file_url');

        // El archivo debe existir en s3_private
        Storage::disk('s3_private')->assertExists($design->file_path);
    });
});
