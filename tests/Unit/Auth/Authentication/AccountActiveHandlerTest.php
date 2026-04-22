<?php

use App\Auth\Chain\AbstractHandler;
use App\Auth\Chain\Authentication\AccountActiveHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

describe('AccountActiveHandler', function () {
    it('returns 403 when account is inactive', function () {
        $user = new User;
        $user->is_active = false;

        $request = Request::create('/');
        $request->merge(['_resolved_user' => $user]);

        $handler = new AccountActiveHandler;
        $result = $handler->handle($request);

        expect($result)->not->toBeTrue();
        expect($result->getStatusCode())->toBe(403);
    });

    it('returns true when account is active and no next handler exists', function () {
        $user = new User;
        $user->is_active = true;

        $request = Request::create('/');
        $request->merge(['_resolved_user' => $user]);

        $handler = new AccountActiveHandler;
        $result = $handler->handle($request);

        expect($result)->toBeTrue();
    });

    it('delegates to next handler when account is active', function () {
        $user = new User;
        $user->is_active = true;

        $request = Request::create('/');
        $request->merge(['_resolved_user' => $user]);

        $next = new class extends AbstractHandler
        {
            public function handle(Request $request): true|JsonResponse
            {
                return new JsonResponse(['message' => 'next called'], 200);
            }
        };

        $handler = new AccountActiveHandler;
        $handler->setNext($next);

        $result = $handler->handle($request);

        expect($result->getStatusCode())->toBe(200);
    });
});
