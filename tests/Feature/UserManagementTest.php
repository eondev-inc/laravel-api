<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Crea los roles y permisos base para cada test
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
    $this->viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Lector']);

    $deletePermission = Permission::create(['name' => 'users.delete', 'display_name' => 'Eliminar usuarios']);
    $this->adminRole->permissions()->attach($deletePermission->id);
});

// ─── GET /api/users ───────────────────────────────────────────────────────────

describe('GET /api/users', function () {
    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/users')->assertStatus(401);
    });

    it('returns 403 when authenticated user lacks admin role', function () {
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

    it('returns 422 for invalid data', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });
});

// ─── GET /api/users/{user} ────────────────────────────────────────────────────

describe('GET /api/users/{user}', function () {
    it('returns user data for admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$target->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $target->id);
    });

    it('returns 404 for non-existent user', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        Sanctum::actingAs($admin);

        $this->getJson('/api/users/9999')->assertStatus(404);
    });
});

// ─── PUT /api/users/{user} ────────────────────────────────────────────────────

describe('PUT /api/users/{user}', function () {
    it('updates user name when admin', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create(['name' => 'Old Name']);

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$target->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    });
});

// ─── DELETE /api/users/{user} ─────────────────────────────────────────────────

describe('DELETE /api/users/{user}', function () {
    it('deletes user when admin with users.delete permission', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$target->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'User deleted.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('returns 403 when admin lacks users.delete permission', function () {
        // Admin sin el permiso users.delete
        $restrictedRole = Role::create(['name' => 'admin_no_delete', 'display_name' => 'Admin sin delete']);
        $admin = User::factory()->create();
        $admin->roles()->attach($restrictedRole->id);

        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$target->id}")->assertStatus(403);
    });
});
