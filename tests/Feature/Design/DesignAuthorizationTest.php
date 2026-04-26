<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('DesignController authorization', function () {
    it('rejects store without designs.create permission with 403', function () {
        $user = User::factory()->create(['is_active' => true]);
        // User without any role/permission
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/designs', []);

        $response->assertStatus(403);
    });

    it('rejects store when user is not authenticated with 401', function () {
        $response = $this->postJson('/api/designs', []);

        $response->assertStatus(401);
    });

    it('allows store when user has designs.create permission', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'designer', 'display_name' => 'Designer']);
        $permission = Permission::create(['name' => 'designs.create', 'display_name' => 'Create Designs']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        // We only test that auth passes — it will fail on validation (no image), not auth
        $response = $this->postJson('/api/designs', ['name' => 'Test']);

        // 422 (validation) or 404 (product not found) means auth passed
        $response->assertStatus(422);
    });

    it('allows store when user has admin role', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = Permission::create(['name' => 'designs.create', 'display_name' => 'Create Designs']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/designs', ['name' => 'Test']);

        // 422 (validation) means auth passed
        $response->assertStatus(422);
    });
});
