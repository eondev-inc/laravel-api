<?php

use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Pagination Limit', function () {
    beforeEach(function () {
        $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
    });

    it('caps per_page at 100 when requesting more', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($this->adminRole);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?per_page=500');

        $response->assertStatus(200);
        expect($response->json('meta.per_page'))->toBeLessThanOrEqual(100);
    });

    it('uses requested per_page when within limit', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($this->adminRole);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?per_page=50');

        $response->assertStatus(200);
        expect($response->json('meta.per_page'))->toBe(50);
    });

    it('uses default per_page of 15 when not specified', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($this->adminRole);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        expect($response->json('meta.per_page'))->toBe(15);
    });

    it('caps per_page exactly at 100', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($this->adminRole);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users?per_page=100');

        $response->assertStatus(200);
        expect($response->json('meta.per_page'))->toBe(100);
    });
});
