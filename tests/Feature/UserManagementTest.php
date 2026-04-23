<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);
    $this->editorRole = Role::create(['name' => 'editor', 'display_name' => 'Editor']);

    $this->viewPermission = Permission::create(['name' => 'users.view', 'display_name' => 'Ver usuarios']);
    $this->createPermission = Permission::create(['name' => 'users.create', 'display_name' => 'Crear usuarios']);
    $this->updatePermission = Permission::create(['name' => 'users.update', 'display_name' => 'Actualizar usuarios']);
    $this->deletePermission = Permission::create(['name' => 'users.delete', 'display_name' => 'Eliminar usuarios']);

    $this->adminRole->permissions()->attach([
        $this->viewPermission->id,
        $this->createPermission->id,
        $this->updatePermission->id,
        $this->deletePermission->id,
    ]);

    $this->editorRole->permissions()->attach([
        $this->viewPermission->id,
        $this->updatePermission->id,
    ]);
});

// ─── GET /api/users ───────────────────────────────────────────────────────────

describe('GET /api/users', function () {
    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/users')->assertStatus(401);
    });

    it('returns 403 when user lacks role and permission', function () {
        $user = User::factory()->create();
        $user->roles()->attach($this->viewerRole->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertStatus(403);
    });

    it('returns paginated users for admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        User::factory(3)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);
    });

    it('returns paginated users for user with users.view permission', function () {
        $editor = User::factory()->create();
        $editor->roles()->attach($this->editorRole->id);

        Sanctum::actingAs($editor);

        $this->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);
    });

    it('returns uuid as data.id (no numeric id exposed)', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users')->assertStatus(200);
        $firstItem = $response->json('data.0');

        expect($firstItem)->toHaveKey('id');
        // UUID format: 8-4-4-4-12
        expect($firstItem['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

// ─── POST /api/users ──────────────────────────────────────────────────────────

describe('POST /api/users', function () {
    it('creates a user with roles when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['viewer'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.roles.0', 'viewer');
    });

    it('returns 403 when user lacks create permission', function () {
        $editor = User::factory()->create();
        $editor->roles()->attach($this->editorRole->id); // editor tiene view y update, no create

        Sanctum::actingAs($editor);

        $this->postJson('/api/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(403);
    });

    it('returns 422 for invalid data', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('returns uuid as data.id after creation', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'UUID User',
            'email' => 'uuid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        expect($response->json('data.id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

// ─── GET /api/users/{user} ────────────────────────────────────────────────────

describe('GET /api/users/{user}', function () {
    it('returns user data for admin using uuid route key', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$target->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $target->uuid);
    });

    it('returns 404 when accessing with numeric id instead of uuid', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$target->id}")->assertStatus(404);
    });

    it('returns 404 for non-existent uuid', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->getJson('/api/users/00000000-0000-0000-0000-000000000000')->assertStatus(404);
    });

    it('passes for user with users.view permission', function () {
        $editor = User::factory()->create();
        $editor->roles()->attach($this->editorRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($editor);

        $this->getJson("/api/users/{$target->uuid}")->assertStatus(200);
    });
});

// ─── PUT /api/users/{user} ────────────────────────────────────────────────────

describe('PUT /api/users/{user}', function () {
    it('updates user name when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create(['name' => 'Old Name']);

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$target->uuid}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    });

    it('updates user for editor with users.update permission', function () {
        $editor = User::factory()->create();
        $editor->roles()->attach($this->editorRole->id);

        $target = User::factory()->create(['name' => 'Before']);

        Sanctum::actingAs($editor);

        $this->putJson("/api/users/{$target->uuid}", ['name' => 'After'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'After');
    });

    it('returns 403 when user lacks update permission', function () {
        $viewer = User::factory()->create();
        $viewer->roles()->attach($this->viewerRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($viewer);

        $this->putJson("/api/users/{$target->uuid}", ['name' => 'Hack'])->assertStatus(403);
    });
});

// ─── DELETE /api/users/{user} ─────────────────────────────────────────────────

describe('DELETE /api/users/{user}', function () {
    it('deletes user when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$target->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'User deleted.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('returns 403 when user lacks delete permission', function () {
        $editor = User::factory()->create();
        $editor->roles()->attach($this->editorRole->id); // editor no tiene users.delete

        $target = User::factory()->create();

        Sanctum::actingAs($editor);

        $this->deleteJson("/api/users/{$target->uuid}")->assertStatus(403);
    });

    it('returns 401 when unauthenticated', function () {
        $target = User::factory()->create();

        $this->deleteJson("/api/users/{$target->uuid}")->assertStatus(401);
    });
});
