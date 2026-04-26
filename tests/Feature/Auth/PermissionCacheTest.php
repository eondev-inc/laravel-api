<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('Permission cache per request', function () {
    it('calling hasPermission twice does not execute two queries', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $permission = Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $freshUser = $user->fresh();

        DB::enableQueryLog();

        $freshUser->hasPermission('posts.edit');
        $freshUser->hasPermission('posts.edit');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Sin cache: 2 llamadas → 2 queries. Con cache: solo 1.
        expect(count($queries))->toBeLessThan(2);
    });

    it('calling hasRole twice does not execute two queries', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $user->roles()->attach($role);

        $freshUser = $user->fresh();

        DB::enableQueryLog();

        $freshUser->hasRole('admin');
        $freshUser->hasRole('admin');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect(count($queries))->toBeLessThan(2);
    });

    it('returns correct result after caching', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
        $permission = Permission::create(['name' => 'posts.read', 'display_name' => 'Read Posts']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $freshUser = $user->fresh();

        expect($freshUser->hasPermission('posts.read'))->toBeTrue();
        expect($freshUser->hasPermission('posts.read'))->toBeTrue();
        expect($freshUser->hasPermission('posts.delete'))->toBeFalse();
    });

    it('hasRole cache returns correct true/false', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'superadmin', 'display_name' => 'Super Admin']);
        $user->roles()->attach($role);

        $freshUser = $user->fresh();

        expect($freshUser->hasRole('superadmin'))->toBeTrue();
        expect($freshUser->hasRole('superadmin'))->toBeTrue();
        expect($freshUser->hasRole('ghost'))->toBeFalse();
    });
});
