<?php

use App\Auth\Chain\AbstractHandler;
use App\Auth\Chain\RoleOrPermissionHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('RoleOrPermissionHandler', function () {
    it('passes when user has the required role', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->with('admin')->andReturn(true);
        $user->shouldReceive('hasPermission')->never();

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new RoleOrPermissionHandler('admin', 'users.view');
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });

    it('passes when user lacks role but has the required permission', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->with('admin')->andReturn(false);
        $user->shouldReceive('hasPermission')->with('users.view')->andReturn(true);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new RoleOrPermissionHandler('admin', 'users.view');
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });

    it('returns 403 when user has neither role nor permission', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->with('admin')->andReturn(false);
        $user->shouldReceive('hasPermission')->with('users.view')->andReturn(false);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new RoleOrPermissionHandler('admin', 'users.view');
        $result = $handler->handle($request);

        expect($result)->not->toBeTrue();
        expect($result->getStatusCode())->toBe(403);
    });

    it('delegates to next handler when authorization passes', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->with('admin')->andReturn(true);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $nextHandler = new class extends AbstractHandler
        {
            public function handle(Request $request): true|JsonResponse
            {
                return new JsonResponse(['message' => 'next called'], 403);
            }
        };

        $handler = new RoleOrPermissionHandler('admin', 'users.view');
        $handler->setNext($nextHandler);

        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(403);
    });
});
