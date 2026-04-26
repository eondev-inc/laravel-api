<?php

use App\Auth\Chain\Authentication\AccountActiveHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('AccountActiveHandler defensive validation', function () {
    it('returns 403 when _resolved_user is missing from request (fail-closed)', function () {
        $handler = new AccountActiveHandler;

        $request = Request::create('/fake', 'POST');
        // No _resolved_user set — simulates missing context

        $result = $handler->handle($request);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(403);
    });

    it('returns 403 when _resolved_user is not a User instance (fail-closed)', function () {
        $handler = new AccountActiveHandler;

        $request = Request::create('/fake', 'POST');
        $request->merge(['_resolved_user' => 'not-a-user-object']);

        $result = $handler->handle($request);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(403);
    });

    it('returns 403 when user is inactive', function () {
        $user = User::factory()->create(['is_active' => false]);

        $handler = new AccountActiveHandler;

        $request = Request::create('/fake', 'POST');
        $request->merge(['_resolved_user' => $user]);

        $result = $handler->handle($request);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(403);
    });

    it('passes to next when user is active', function () {
        $user = User::factory()->create(['is_active' => true]);

        $handler = new AccountActiveHandler;
        // No next handler — passToNext returns true when no next is set

        $request = Request::create('/fake', 'POST');
        $request->merge(['_resolved_user' => $user]);

        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });
});
