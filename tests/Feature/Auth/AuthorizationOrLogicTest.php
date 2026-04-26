<?php

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('OR logic authorization - User model methods', function () {
    it('hasAnyRole returns true when user has at least one of the given roles', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $user->roles()->attach($role);

        expect($user->hasAnyRole(['admin', 'editor']))->toBeTrue();
    });

    it('hasAnyRole returns false when user has none of the given roles', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
        $user->roles()->attach($role);

        expect($user->hasAnyRole(['admin', 'editor']))->toBeFalse();
    });

    it('hasAnyPermission returns true when user has at least one of the given permissions', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $permission = Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        expect($user->hasAnyPermission(['posts.edit', 'catalog.manage']))->toBeTrue();
    });

    it('hasAnyPermission returns false when user has none of the given permissions', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $user->roles()->attach($role);

        expect($user->hasAnyPermission(['catalog.manage', 'users.delete']))->toBeFalse();
    });

    it('Controller::authorize accepts array of roles and passes when user has any', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $user->roles()->attach($role);

        // Use DesignController as a real controller that extends Controller
        $controller = new class extends Controller
        {
            public function testAuthorize(Request $request): true|JsonResponse
            {
                return $this->authorize($request, role: ['admin', 'editor']);
            }
        };

        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $user);

        $result = $controller->testAuthorize($request);
        expect($result)->toBeTrue();
    });

    it('Controller::authorize accepts array of roles and rejects when user has none', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
        $user->roles()->attach($role);

        $controller = new class extends Controller
        {
            public function testAuthorize(Request $request): true|JsonResponse
            {
                return $this->authorize($request, role: ['admin', 'editor']);
            }
        };

        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $user);

        $result = $controller->testAuthorize($request);
        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(403);
    });

    it('Controller::authorize accepts array of permissions and passes when user has any', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $permission = Permission::create(['name' => 'users.view', 'display_name' => 'View Users']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $controller = new class extends Controller
        {
            public function testAuthorize(Request $request): true|JsonResponse
            {
                return $this->authorize($request, permission: ['users.view', 'users.create']);
            }
        };

        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $user);

        $result = $controller->testAuthorize($request);
        expect($result)->toBeTrue();
    });

    it('Controller::authorize accepts array of permissions and rejects when user has none', function () {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
        $user->roles()->attach($role);

        $controller = new class extends Controller
        {
            public function testAuthorize(Request $request): true|JsonResponse
            {
                return $this->authorize($request, permission: ['users.view', 'users.create']);
            }
        };

        $request = Request::create('/fake', 'GET');
        $request->setUserResolver(fn () => $user);

        $result = $controller->testAuthorize($request);
        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(403);
    });
});
