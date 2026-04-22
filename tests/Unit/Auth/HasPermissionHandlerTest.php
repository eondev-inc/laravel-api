<?php

use App\Auth\Chain\HasPermissionHandler;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery\MockInterface;

describe('HasPermissionHandler', function () {
    it('returns 403 when user does not have the required permission', function () {
        $user = Mockery::mock(User::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasPermission')->with('users.delete')->andReturn(false);
        });

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new HasPermissionHandler('users.delete');
        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(403);
    });

    it('returns true when user has the required permission and no next handler', function () {
        $user = Mockery::mock(User::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasPermission')->with('users.delete')->andReturn(true);
        });

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $handler = new HasPermissionHandler('users.delete');
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });
})->afterEach(fn () => Mockery::close());
