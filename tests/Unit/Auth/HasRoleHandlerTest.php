<?php

use App\Auth\Chain\HasPermissionHandler;
use App\Auth\Chain\HasRoleHandler;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery\MockInterface;

describe('HasRoleHandler', function () {
    it('returns 403 when user does not have the required role', function () {
        $user = Mockery::mock(User::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasRole')->with('admin')->andReturn(false);
        });

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new HasRoleHandler('admin');
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(403);
    });

    it('returns true when user has the required role and no next handler', function () {
        $user = Mockery::mock(User::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasRole')->with('admin')->andReturn(true);
        });

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new HasRoleHandler('admin');
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });

    it('delegates to next handler when role check passes', function () {
        $user = Mockery::mock(User::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasRole')->with('admin')->andReturn(true);
            $mock->shouldReceive('hasPermission')->with('users.delete')->andReturn(false);
        });

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new HasRoleHandler('admin');
        $handler->setNext(new HasPermissionHandler('users.delete'));

        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(403);
    });
})->afterEach(fn () => Mockery::close());
